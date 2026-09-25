<?php

namespace Goldnead\Accounts;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Goldnead\Accounts\Http\Middleware\AttributeImpersonation;
use Goldnead\Accounts\Http\Middleware\EnsureEmailIsVerified;
use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Integrations\Automations\AutomationsBridge;
use Goldnead\Accounts\Integrations\EmailTemplates\DefaultTemplateSource;
use Goldnead\Accounts\Integrations\WebhookManager\WebhookManagerBridge;
use Goldnead\Accounts\PersonalData\Contributors\AccountContributor;
use Goldnead\Accounts\PersonalData\Contributors\EntitlementsContributor;
use Goldnead\Accounts\PersonalData\Contributors\LeadhubContributor;
use Goldnead\Accounts\PersonalData\Contributors\NotificationsContributor;
use Goldnead\Accounts\PersonalData\Contributors\PaymentsContributor;
use Goldnead\Accounts\PersonalData\Contributors\TeamsContributor;
use Goldnead\Accounts\PersonalData\PersonalDataRegistry;
use Goldnead\Accounts\Support\Settings;
use Illuminate\Console\Scheduling\Schedule;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
        'actions' => __DIR__.'/../routes/actions.php',
    ];

    // Registered by hand in register() under the short `accounts` namespace,
    // plus the JSON path the Vue layer's `__('Some sentence')` calls resolve
    // through. The parent's automatic registration would use the package
    // slug and cover only the first of the two.
    protected $translations = false;

    protected $config = false;

    protected $viewNamespace = 'accounts';

    protected $middlewareGroups = [
        'web' => [AttributeImpersonation::class],
    ];

    /**
     * Statamic 6 reads the addon's Vite configuration from this property and
     * from nowhere else. The three values must byte-match `laravel()` in
     * vite.config.js.
     */
    protected $vite = [
        'hotFile' => __DIR__.'/../dist/hot',
        'publicDirectory' => 'dist',
        'input' => ['resources/js/cp.js', 'resources/css/cp.css'],
    ];

    /**
     * The contributors shipped with the addon. Each checks for itself whether
     * its sibling is installed.
     *
     * @var list<class-string<ContributesPersonalData>>
     */
    public const CONTRIBUTORS = [
        AccountContributor::class,
        PaymentsContributor::class,
        EntitlementsContributor::class,
        LeadhubContributor::class,
        NotificationsContributor::class,
        TeamsContributor::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/accounts.php', 'accounts');

        $langPath = __DIR__.'/../lang';

        $this->app->resolving('translator', function ($translator) use ($langPath) {
            $translator->addNamespace('accounts', $langPath);
            $translator->addJsonPath($langPath);
        });

        if ($this->app->resolved('translator')) {
            $this->app['translator']->addNamespace('accounts', $langPath);
            $this->app['translator']->addJsonPath($langPath);
        }

        $this->app->singleton(AccountsManager::class);
        $this->app->singleton(PersonalDataRegistry::class);
        $this->app->singleton(ActivityBridge::class);
        $this->app->singleton(AutomationsBridge::class);
        $this->app->singleton(WebhookManagerBridge::class);

        $this->app->afterResolving(PersonalDataRegistry::class, function (PersonalDataRegistry $registry) {
            foreach (self::CONTRIBUTORS as $contributor) {
                $registry->register($contributor);
            }
        });

        // Offer the default mails to `email-templates:import`, which writes
        // them as editable entries. Tagged only when the sibling is there.
        if (interface_exists('Goldnead\EmailTemplates\Contracts\EmailTemplateSource')) {
            $this->app->tag([DefaultTemplateSource::class], 'email-templates.sources');
        }
    }

    /**
     * In `boot()`, not `bootAddon()`: brand-context applies saved values
     * from an `app->booted()` callback, and every provider's registration has
     * to be in by then.
     */
    public function boot(): void
    {
        parent::boot();

        if (interface_exists('Goldnead\BrandContext\Contracts\ProvidesSettings')
            && class_exists('Goldnead\BrandContext\Settings\SettingsRegistry')) {
            $this->app->make('Goldnead\BrandContext\Settings\SettingsRegistry')->register(Settings::class);
        }
    }

    public function bootAddon(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accounts');

        $this->app['router']->aliasMiddleware('accounts.verified', EnsureEmailIsVerified::class);

        $this
            ->bootNav()
            ->bootPermissions()
            ->bootBridges()
            ->bootPublishables();
    }

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('accounts:purge')->dailyAt('03:40')->withoutOverlapping();
    }

    protected function bootNav(): self
    {
        Nav::extend(function ($nav) {
            $nav->create(__('accounts::messages.nav'))
                ->section('Users')
                ->icon('users')
                ->route('accounts.index')
                ->can('view accounts')
                ->children([
                    $nav->item(__('accounts::messages.nav_customers'))->route('accounts.index')->can('view accounts'),
                    $nav->item(__('accounts::messages.nav_wiring'))->route('accounts.wiring')->can('view accounts'),
                ]);
        });

        return $this;
    }

    protected function bootPermissions(): self
    {
        Permission::extend(function () {
            Permission::group('accounts', __('accounts::messages.permission_group'), function () {
                Permission::register('view accounts')
                    ->label(__('accounts::messages.permission_view'))
                    ->children([
                        Permission::make('manage accounts')
                            ->label(__('accounts::messages.permission_manage')),
                        Permission::make('export account data')
                            ->label(__('accounts::messages.permission_export')),
                    ]);

                Permission::register('manage accounts settings')
                    ->label(__('accounts::messages.permission_settings'));
            });
        });

        return $this;
    }

    /**
     * Offer the events to automations and webhook-manager.
     *
     * From a booted callback: the siblings' bindings exist only once their
     * own providers have booted, and this one may boot first. Both bridges
     * are idempotent, so the retry at the end of the queue costs nothing.
     */
    protected function bootBridges(): self
    {
        $register = function (): void {
            $this->app->make(AutomationsBridge::class)->register();
            $this->app->make(WebhookManagerBridge::class)->register();
        };

        $this->app->booted(function () use ($register): void {
            $register();

            $this->app->booted($register);
        });

        return $this;
    }

    protected function bootPublishables(): self
    {
        $this->publishes([
            __DIR__.'/../config/accounts.php' => config_path('accounts.php'),
        ], 'accounts-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/accounts'),
        ], 'accounts-views');

        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/accounts'),
        ], 'accounts-translations');

        return $this;
    }
}
