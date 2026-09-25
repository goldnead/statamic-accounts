<?php

namespace Goldnead\Accounts\Integrations;

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
                'actor' => $actor,
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
}
