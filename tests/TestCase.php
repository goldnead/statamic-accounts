<?php

namespace Goldnead\Accounts\Tests;

use Goldnead\Accounts\ServiceProvider;
use Goldnead\Accounts\Support\Schema as AccountsSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Statamic\Contracts\Auth\User as UserContract;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

/**
 * The suite runs with brand-context and identity-contracts installed (both
 * dev dependencies) and every other sibling absent. The siblings a test
 * wants to see are stand-ins under `tests/Fakes/`, loaded before the
 * application boots.
 */
abstract class TestCase extends AddonTestCase
{
    use PreventsSavingStacheItemsToDisk;
    use RefreshDatabase;

    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getPackageProviders($app): array
    {
        return [
            \Goldnead\BrandContext\ServiceProvider::class,
            \Goldnead\IdentityContracts\ServiceProvider::class,
            ...parent::getPackageProviders($app),
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $app['config']->set('mail.default', 'array');
        $app['config']->set('statamic.users.repository', 'file');
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.users.elevated_sessions_enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        AccountsSchema::flush();
    }

    protected function tearDown(): void
    {
        foreach (glob(__DIR__.'/__fixtures__/users/*.yaml') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function makeUser(string $email = 'sina@example.com', array $data = []): UserContract
    {
        $user = User::make()->email($email)->data(array_merge(['name' => 'Sina Sänger'], $data));
        $user->password('geheim-123');
        $user->save();

        return $user;
    }

    /**
     * A CP user holding exactly the listed permissions, granted through the
     * gate.
     *
     * @param  list<string>  $permissions
     */
    protected function cpUser(string $email, array $permissions = []): UserContract
    {
        $allowed = array_merge(['access cp'], $permissions);

        Gate::before(function ($user, $ability) use ($allowed, $email) {
            if (method_exists($user, 'email') && $user->email() !== $email) {
                return null;
            }

            return in_array($ability, $allowed, true) ? true : null;
        });

        $account = User::make()->email($email)->data(['name' => 'Admin']);
        $account->save();

        return $account;
    }
}
