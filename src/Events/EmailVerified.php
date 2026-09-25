<?php

namespace Goldnead\Accounts\Events;

/**
 * The customer opened a valid confirmation link, or an admin marked the
 * address as confirmed.
 */
class EmailVerified extends AccountEvent
{
    public static function handle(): string
    {
        return 'accounts.email.verified';
    }
}
