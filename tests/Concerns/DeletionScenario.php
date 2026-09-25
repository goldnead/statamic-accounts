<?php

namespace Goldnead\Accounts\Tests\Concerns;

use Goldnead\Accounts\Tests\Fakes\ActivityAnonymizeCommand;
use Goldnead\Activity\Facades\Activity;
use Goldnead\StatamicPayments\Support\Subscriptions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Statamic\Auth\User as StatamicUser;

/**
 * A customer with rows in every sibling, a stranger beside her, the ledger's
 * anonymise command and clean stand-ins: the setting of the deletion tests.
 */
trait DeletionScenario
{
    use SeedsSiblingTables;

    /**
     * Called by Laravel's `setUpTraits()` (the `setUp<TraitName>` convention),
     * after the application and the database are up.
     */
    protected function setUpDeletionScenario(): void
    {
        $this->createSiblingTables();
        $this->app[Kernel::class]->registerCommand(new ActivityAnonymizeCommand);
        Activity::$recorded = [];
        Subscriptions::$cancelled = [];
    }

    protected function customer(): StatamicUser
    {
        $user = $this->makeUser();
        $this->seedSiblingRows($user);
        $this->seedStranger();
        DB::table('entitlements')->where('subject_type', 'team')->delete();

        return $user;
    }

    protected function endSubscriptions(): void
    {
        DB::table('subscriptions')->where('email', 'sina@example.com')->update(['status' => 'cancelled']);
    }

    protected function teamGetsAnotherMember(): void
    {
        $team = DB::table('teams')->where('name', 'Kammerchor Nord')->value('id');
        DB::table('team_members')->insert(['team_id' => $team, 'user_id' => 'neu', 'role' => 'member', 'created_at' => now()]);
    }
}
