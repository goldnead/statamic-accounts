<?php

namespace Goldnead\Accounts\Integrations;

use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Support\Users;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes account facts into goldnead/statamic-activity.
 *
 * Without the sibling the same line goes to the application log, so an
 * impersonation is on record either way. The ledger swallows its own
 * failures; this does too, because a verification must not fail on a broken
 * `activities` table.
 */
class ActivityBridge
{
    public const FACADE = '\Goldnead\Activity\Facades\Activity';

    public const IDENTITY = '\Goldnead\IdentityContracts\Facades\IdentityContext';

    public function available(): bool
    {
        return (bool) config('accounts.integrations.activity', true)
            && class_exists(self::FACADE);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(string $type, array $properties = [], mixed $actor = null, ?string $dedupeKey = null): void
    {
        if (! $this->available()) {
            Log::info('statamic-accounts: '.$type, $properties);

            return;
        }

        try {
            $facade = self::FACADE;

            $attributes = array_filter([
                'actor' => $this->identity($actor),
                'dedupe_key' => $dedupeKey,
                'properties' => $properties,
            ], fn ($value) => $value !== null);

            $facade::record($type, $attributes);
        } catch (Throwable $e) {
            Log::warning('statamic-accounts: the activity entry could not be written.', [
                'type' => $type,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The ledger takes an `Identity`, not a user: its `ActivityData` is typed
     * that way. A user is turned into one through identity-contracts (which
     * the ledger requires, so it is there whenever the ledger is). Without an
     * actor the ledger fills in the current one itself.
     */
    protected function identity(mixed $actor): mixed
    {
        if (! class_exists(self::IDENTITY)) {
            return null;
        }

        $facade = self::IDENTITY;

        // While an admin is signed in as the customer, the admin acted, and
        // the entry says so: the admin as actor, the customer in
        // `meta.acting_as`. The pinned identity (set by
        // AttributeImpersonation) is where both are known.
        $impersonation = app(Impersonation::class);

        if ($impersonation->active()) {
            $current = $facade::current();
            $adminId = $current->meta['impersonated_by'] ?? $impersonation->impersonatorId();
            $admin = Users::find($adminId);

            if ($admin !== null) {
                return $facade::resolve($admin)->withMeta(['acting_as' => $current->userId ?? Users::current()?->id()]);
            }
        }

        return $actor === null ? null : $facade::resolve($actor);
    }
}
