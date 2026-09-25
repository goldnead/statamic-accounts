<?php

namespace Goldnead\Accounts\Events;

/**
 * The grace period is over and the account is about to be deleted.
 *
 * Dispatched synchronously while the user still exists, so a listener can
 * still read what it needs (a subscription to cancel, a CRM contact to
 * anonymise). A listener that throws stops the deletion of this one account;
 * the purge run logs it and tries again the next day.
 */
class AccountDeleting extends AccountEvent
{
    public static function handle(): string
    {
        return 'accounts.deleting';
    }
}
