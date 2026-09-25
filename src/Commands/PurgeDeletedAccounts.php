<?php

namespace Goldnead\Accounts\Commands;

use Goldnead\Accounts\Services\AccountDeletion;
use Illuminate\Console\Command;

/**
 * Deletes the accounts whose grace period is over. Scheduled daily by the
 * addon; safe to run by hand, it only touches what is due.
 */
class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge';

    protected $description = 'Delete the accounts whose deletion grace period is over';

    public function handle(AccountDeletion $deletion): int
    {
        $count = $deletion->purgeDue();

        $this->info(trans_choice('accounts::messages.purged', $count, ['count' => $count]));

        return self::SUCCESS;
    }
}
