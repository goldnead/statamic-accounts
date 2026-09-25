<?php

namespace Goldnead\Accounts\Integrations\WebhookManager;

use Goldnead\Accounts\Events\AccountEvent;
use Goldnead\Accounts\Support\EventCatalog;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Offers every account event to goldnead/statamic-webhook-manager as a
 * trigger.
 *
 * Through the sibling's own `registerEventTrigger()`: it registers a trigger
 * that shows up in its trigger picker and subscribes one listener that
 * normalises the event into its `TriggerDetected` pipeline. The webhook body
 * is {@see AccountEvent::payload()}, ids and addresses, no tokens.
 */
class WebhookManagerBridge
{
    public const FACADE = '\Goldnead\WebhookManager\Facades\WebhookManager';

    protected bool $registered = false;

    public function available(): bool
    {
        return (bool) config('accounts.integrations.webhook_manager', true)
            && class_exists(self::FACADE);
    }

    public function register(): void
    {
        if ($this->registered || ! $this->available()) {
            return;
        }

        // The binding appears only once the sibling's own provider has
        // booted. Bail without marking registered, so the retry at the end
        // of the booted queue can still succeed.
        if (! app()->bound('webhook-manager')) {
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
                    'label' => __('accounts::messages.addon_name').': '.$event['label'],
                    'source_type' => 'user',
                    'description' => $event['description'],
                    // `reference` becomes the delivery's source reference in
                    // the webhook log, so a delivery is findable by user.
                    'payload' => fn (AccountEvent $fired) => array_merge(['reference' => $fired->userId], $fired->payload()),
                ]);
            }

            $this->registered = true;
        } catch (Throwable $e) {
            Log::warning('statamic-accounts: the webhook-manager triggers could not be registered.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function registered(): bool
    {
        return $this->registered;
    }
}
