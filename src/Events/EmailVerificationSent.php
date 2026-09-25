<?php

namespace Goldnead\Accounts\Events;

/**
 * A confirmation link went out to the account's address.
 */
class EmailVerificationSent extends AccountEvent
{
    public static function handle(): string
    {
        return 'accounts.verification.sent';
    }
}
