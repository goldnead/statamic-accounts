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
        $report = $deletion->purge();

        $this->info(trans_choice('accounts::messages.purged', $report['deleted'], ['count' => $report['deleted']]));

        // The rest was deleted all the same; the run still says that some did not go.
        if ($report['failed'] > 0) {
            $this->error(trans_choice('accounts::messages.purge_failed', $report['failed'], ['count' => $report['failed']]));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
