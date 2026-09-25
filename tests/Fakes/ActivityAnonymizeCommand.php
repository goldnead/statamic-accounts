<?php

namespace Goldnead\Accounts\Tests\Fakes;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Stands in for statamic-activity's `activity:anonymize`: same signature for
 * the options this addon passes, same columns stripped
 * (AnonymizeActivitiesCommand::strip()).
 */
class ActivityAnonymizeCommand extends Command
{
    protected $signature = 'activity:anonymize {--contact=} {--user=} {--anonymous-id=} {--days=} {--dry-run}';

    public function handle(): int
    {
        $query = DB::table('activities')->where('anonymized', false);

        if ($user = $this->option('user')) {
            $query->where('user_id', $user);
        }

        if ($contact = $this->option('contact')) {
            $query->where('contact_uuid', $contact);
        }

        $query->update([
            'contact_uuid' => null, 'user_id' => null, 'actor_id' => null,
            'properties' => null, 'context' => null, 'anonymized' => true,
        ]);

        return self::SUCCESS;
    }
}
