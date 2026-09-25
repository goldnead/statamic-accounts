<?php

namespace Goldnead\Accounts\Events;

/**
 * The account is gone. Only the ids and the address are left, in this
 * payload and nowhere else in this addon.
 */
class AccountDeleted extends AccountEvent
{
    public static function handle(): string
    {
        return 'accounts.deleted';
    }
}
