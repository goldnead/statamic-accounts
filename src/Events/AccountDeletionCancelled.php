<?php

namespace Goldnead\Accounts\Events;

/**
 * A scheduled deletion was withdrawn within the grace period.
 */
class AccountDeletionCancelled extends AccountEvent
{
    public static function handle(): string
    {
        return 'accounts.deletion.cancelled';
    }
}
