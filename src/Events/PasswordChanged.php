<?php

namespace Goldnead\Accounts\Events;

/**
 * The account's password was replaced by a different one. Never carries the
 * password or its hash: the fact alone is what a receiver needs (a security
 * log, a CRM note, a flow that warns on a second channel).
 */
class PasswordChanged extends AccountEvent
{
    public static function handle(): string
    {
        return 'accounts.password.changed';
    }
}
