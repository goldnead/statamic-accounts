<?php

namespace Goldnead\Accounts\Support;

use Goldnead\Accounts\Integrations\ActivityBridge;
use Goldnead\Accounts\Integrations\Automations\AutomationsBridge;
use Goldnead\Accounts\Integrations\EmailTemplates\RegistersTemplates as RegistersTemplatesBinding;
use Goldnead\Accounts\Integrations\WebhookManager\WebhookManagerBridge;
use Goldnead\Accounts\PersonalData\PersonalDataRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * What this addon is connected to on this site, for the "Wiring" screen:
 * each event with its mail template and how many automations and webhooks
 * listen to it, which sibling addons are there, which contributors feed the
 * data export.
 *
 * The counts read the siblings' tables across all brands. They answer "is
 * anything listening", not "what fires in this brand".
 */
class Wiring
{
    public function __construct(
        protected MailTemplates $templates,
        protected AutomationsBridge $automations,
        protected WebhookManagerBridge $webhooks,
        protected ActivityBridge $activity,
        protected PersonalDataRegistry $personalData,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $automationsInstalled = $this->automations->available();
        $webhooksInstalled = $this->webhooks->available();

        $events = array_map(function (array $event) use ($automationsInstalled, $webhooksInstalled) {
            return [
                'handle' => $event['handle'],
                'label' => $event['label'],
                'description' => $event['description'],
                'fields' => array_keys($event['fields']),
                'template' => $event['template_key'] === null ? null : [
                    'slug' => $event['template'],
                    'custom' => $this->templates->hasEntry($event['template_key']),
                ],
                'automations' => $automationsInstalled ? $this->countAutomations($event['handle']) : null,
                'webhooks' => $webhooksInstalled ? $this->countWebhooks($event['handle']) : null,
            ];
        }, EventCatalog::all());

        return [
            'events' => $events,
            'integrations' => [
                'email_templates' => [
                    'installed' => $this->templates->siblingInstalled(),
                    'url' => $this->cpRoute('collections.entries.index', 'et_templates'),
                ],
                'automations' => [
                    'installed' => $automationsInstalled,
                    'registered' => $this->automations->registered(),
                    'url' => $this->cpRoute('statamic-automations.automations.index'),
                ],
                'webhook_manager' => [
                    'installed' => $webhooksInstalled,
                    'registered' => $this->webhooks->registered(),
                    'url' => $this->cpRoute('webhook-manager.outbound.index'),
                    'catalogue_url' => $this->cpRoute('webhook-manager.debug'),
                ],
                'activity' => [
                    'installed' => $this->activity->available(),
                ],
                'identity' => [
                    'installed' => class_exists('\Goldnead\IdentityContracts\Facades\IdentityContext'),
                ],
                'brand_context' => [
                    'installed' => interface_exists('\Goldnead\BrandContext\Contracts\ProvidesSettings'),
                    'url' => $this->cpRoute('brand-context.settings.index'),
                ],
            ],
            'coreMails' => $this->coreMails(),
            'verificationMail' => (string) config('accounts.verification.mail', 'auto'),
            'contributors' => array_values(array_map(fn ($contributor) => [
                'key' => $contributor->key(),
                'label' => $contributor->label(),
                'available' => $contributor->available(),
            ], $this->personalData->all())),
        ];
    }

    /**
     * The account mails Statamic and Laravel send themselves (password reset,
     * activation, `VerifyEmail`, …), as email-templates registers them under
     * the addon name "Statamic". They sit next to this addon's own mails in
     * an account's life, so they are shown here; which of them are sent from
     * a template is email-templates' `core_mails.enabled`.
     *
     * @return list<array{slug: string, title: string, trigger: string, custom: bool, enabled: bool}>
     */
    protected function coreMails(): array
    {
        if (! app()->bound(RegistersTemplatesBinding::BINDING)) {
            return [];
        }

        try {
            $registry = app(RegistersTemplatesBinding::BINDING);
            $groups = method_exists($registry, 'byAddon') ? $registry->byAddon() : [];
        } catch (Throwable) {
            return [];
        }

        $enabled = (bool) config('email-templates.core_mails.enabled', false);

        return array_values(array_map(function ($definition) use ($enabled) {
            $read = fn (string $field) => $this->readDefinition($definition, $field);
            $slug = $read('slug');

            return [
                'slug' => $slug,
                'title' => $read('title'),
                'trigger' => $read('trigger'),
                'custom' => $this->templates->hasSlug($slug),
                'enabled' => $enabled,
            ];
        }, $groups['Statamic'] ?? []));
    }

    /**
     * A definition is email-templates' `TemplateDefinition` (methods, or a
     * public `slug`), or an array in a stand-in. Read either without naming
     * the sibling's class.
     */
    protected function readDefinition(mixed $definition, string $field): string
    {
        if (is_array($definition)) {
            $value = $definition[$field] ?? '';
        } elseif (is_object($definition) && method_exists($definition, $field)) {
            $value = $definition->{$field}();
        } elseif (is_object($definition) && isset($definition->{$field})) {
            $value = $definition->{$field};
        } else {
            $value = '';
        }

        if ($value instanceof \Closure) {
            $value = $value();
        }

        return is_scalar($value) ? (string) $value : '';
    }

    protected function countAutomations(string $handle): ?int
    {
        try {
            if (! Schema::hasTable('automation_nodes')) {
                return null;
            }

            return (int) DB::table('automation_nodes')->where('type', $handle)->distinct()->count('automation_id');
        } catch (Throwable) {
            return null;
        }
    }

    protected function countWebhooks(string $handle): ?int
    {
        try {
            $count = 0;

            foreach (['webhook_outbounds', 'webhook_rules'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'trigger_type')) {
                    $count += DB::table($table)->where('trigger_type', $handle)->count();
                }
            }

            return $count;
        } catch (Throwable) {
            return null;
        }
    }

    protected function cpRoute(string $name, mixed $parameters = []): ?string
    {
        return Route::has('statamic.cp.'.$name) ? cp_route($name, $parameters) : null;
    }
}
