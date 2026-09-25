<?php

namespace Goldnead\Accounts\Integrations\Automations;

use Goldnead\Accounts\Events\AccountEvent;
use Goldnead\Accounts\Support\EventCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Offers every account event to goldnead/statamic-automations as a trigger.
 *
 * Through the sibling's own `registerEventTrigger()`, which registers the
 * trigger in its node library and subscribes one listener that hands the
 * event to its dispatcher. Matching, enrolment and queueing stay its rules.
 * No class of the sibling is named outside the `class_exists` guard.
 */
class AutomationsBridge
{
    public const FACADE = '\Goldnead\StatamicAutomations\Facades\Automations';

    protected bool $registered = false;

    public function available(): bool
    {
        return (bool) config('accounts.integrations.automations', true)
            && class_exists(self::FACADE);
    }

    /**
     * Idempotent: Statamic fires booted callbacks more than once, and a
     * trigger registered twice would also listen twice.
     */
    public function register(): void
    {
        if ($this->registered || ! $this->available()) {
            return;
        }

        try {
            $facade = self::FACADE;
            $root = $facade::getFacadeRoot();

            if (! is_object($root) || ! method_exists($root, 'registerEventTrigger')) {
                return;
            }

            foreach (EventCatalog::all() as $event) {
                $root->registerEventTrigger($event['class'], [
                    'handle' => $event['handle'],
                    'label' => $event['label'],
                    'group' => __('accounts::messages.addon_name'),
                    'description' => $event['description'],
                    'output_schema' => ['account' => $event['fields']],
                    'payload' => fn (AccountEvent $fired) => ['account' => $fired->payload()],
                ]);
            }

            $this->registered = true;
        } catch (Throwable $e) {
            Log::warning('statamic-accounts: the automations triggers could not be registered.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function registered(): bool
    {
        return $this->registered;
    }
}
