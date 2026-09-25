<?php

namespace Goldnead\Accounts\Listeners;

use Goldnead\Accounts\Integrations\ActivityBridge;
use Statamic\Events\ImpersonationEnded;
use Statamic\Events\ImpersonationStarted;

/**
 * Puts every impersonation on record, however it was started: from this
 * addon's customer overview or from core's user listing action.
 */
class RecordImpersonation
{
    public function __construct(protected ActivityBridge $activity) {}

    public function handleStarted(ImpersonationStarted $event): void
    {
        $this->record('accounts.impersonation_started', $event->impersonator, $event->impersonated);
    }

    public function handleEnded(ImpersonationEnded $event): void
    {
        $this->record('accounts.impersonation_ended', $event->impersonator, $event->impersonated);
    }

    /**
     * Ids only, no addresses: the entry is written under the admin's user id,
     * which the ledger's anonymisation of the customer does not reach. The
     * customer's id stays in it, pseudonymous once the account is deleted.
     */
    protected function record(string $type, mixed $impersonator, mixed $impersonated): void
    {
        $this->activity->record($type, [
            'impersonator_id' => $this->id($impersonator),
            'user_id' => $this->id($impersonated),
        ], $impersonator);
    }

    protected function id(mixed $user): ?string
    {
        if (is_object($user) && method_exists($user, 'id')) {
            return (string) $user->id();
        }

        if (is_object($user) && method_exists($user, 'getAuthIdentifier')) {
            return (string) $user->getAuthIdentifier();
        }

        return null;
    }
}
