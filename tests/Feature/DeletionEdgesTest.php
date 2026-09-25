<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Contracts\ErasesPersonalData;
use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Events\AccountDeletionBlocked;
use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\PersonalData\ErasureRegistry;
use Goldnead\Accounts\PersonalData\ErasureResult;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Tests\Concerns\SeedsSiblingTables;
use Goldnead\Accounts\Tests\Fakes\ActivityAnonymizeCommand;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\Activity\Facades\Activity;
use Goldnead\StatamicPayments\Support\Subscriptions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Statamic\Auth\User as StatamicUser;
use Statamic\Events\ImpersonationStarted;
use Statamic\Facades\User;

/**
 * The deletion's edges, taken over from the coordinator's probes
 * (ProbeR2Test, ProbeElevTest, 25.09.2026): a blocker that appears during the
 * grace period, the cancel policy, withdrawing while impersonating, a failing
 * eraser, a site that switches elevated sessions on without the routes.
 */
class DeletionEdgesTest extends TestCase
{
    use SeedsSiblingTables;

    protected function setUp(): void
    {
        require_once __DIR__.'/../Fakes/siblings.php';

        parent::setUp();

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

    #[Test]
    public function a_blocker_during_the_grace_period_marks_the_request_blocked_and_tells_the_person_once(): void
    {
        Mail::fake();
        Event::fake([AccountDeletionBlocked::class]);
        $user = $this->customer();
        $this->endSubscriptions();
        $request = Accounts::deletion()->request($user);
        $this->teamGetsAnotherMember();

        $this->travel(15)->days();

        $this->assertSame(0, Accounts::deletion()->purgeDue());
        $this->assertSame(AccountRequest::STATUS_BLOCKED, $request->fresh()->status);
        $this->assertNotNull(User::find($user->id()));

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'deletion_blocked'
            && $m->hasTo('sina@example.com')
            && str_contains($m->htmlBody, 'Kammerchor Nord')
            && str_contains($m->htmlBody, 'deletion/cancel/'.$request->id));
        Event::assertDispatched(AccountDeletionBlocked::class, fn ($e) => $e->userId === (string) $user->id() && $e->payload()['reasons'] === 1);

        // The next daily run does not write again.
        Accounts::deletion()->purgeDue();
        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'deletion_blocked', 1);
        $this->assertCount(1, Mail::sent(AccountMail::class, fn ($m) => $m->templateKey === 'deletion_blocked'));
    }

    #[Test]
    public function a_blocked_or_overdue_request_can_still_be_withdrawn(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->endSubscriptions();
        $request = Accounts::deletion()->request($user);
        $this->teamGetsAnotherMember();
        $this->travel(15)->days();
        Accounts::deletion()->purgeDue();

        // By the signed link from the blocked-mail, and from the account.
        $this->assertTrue(Accounts::deletion()->cancel($user));
        $this->assertSame(AccountRequest::STATUS_CANCELLED, $request->fresh()->status);
        $this->assertNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function once_the_blocker_is_gone_the_blocked_request_goes_through(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->endSubscriptions();
        Accounts::deletion()->request($user);
        $this->teamGetsAnotherMember();
        $this->travel(15)->days();
        Accounts::deletion()->purgeDue();

        DB::table('team_members')->where('user_id', 'neu')->delete();

        $this->assertSame(1, Accounts::deletion()->purgeDue());
        $this->assertNull(User::find($user->id()));
    }

    #[Test]
    public function the_cancel_policy_cancels_nothing_while_another_blocker_refuses_the_request(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.active_subscriptions', 'cancel');
        $user = $this->customer();
        $this->teamGetsAnotherMember();

        try {
            Accounts::deletion()->request($user);
            $this->fail('The team blocks.');
        } catch (AccountException) {
        }

        $this->assertSame([], Subscriptions::$cancelled);
        $this->assertSame('active', DB::table('subscriptions')->where('email', 'sina@example.com')->value('status'));
    }

    #[Test]
    public function the_cancel_policy_cancels_only_when_the_deletion_runs(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.active_subscriptions', 'cancel');
        $user = $this->customer();

        Accounts::deletion()->request($user);
        $this->assertSame([], Subscriptions::$cancelled, 'Nothing is cancelled at the request.');

        Accounts::deletion()->cancel($user);
        $this->assertSame('active', DB::table('subscriptions')->where('email', 'sina@example.com')->value('status'), 'Withdrawn, the subscription runs on.');

        Accounts::deletion()->request($user);
        $this->travel(15)->days();
        $this->assertSame(1, Accounts::deletion()->purgeDue());
        $this->assertCount(1, Subscriptions::$cancelled);
    }

    #[Test]
    public function withdrawing_and_exporting_are_closed_while_impersonating_the_admin_route_stays(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->endSubscriptions();
        Accounts::deletion()->request($user);
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'manage accounts']);

        $this->actingAs($user)
            ->withSession([Impersonation::SESSION_KEY => (string) $admin->id()])
            ->post(route('statamic.accounts.deletion.withdraw'))
            ->assertForbidden();
        $this->assertNotNull(Accounts::deletion()->pending($user));

        session([Impersonation::SESSION_KEY => (string) $admin->id()]);

        foreach ([fn () => Accounts::deletion()->cancel($user), fn () => Accounts::export()->build($user, 'admin')] as $call) {
            try {
                $call();
                $this->fail('Refused while impersonating.');
            } catch (AccountException $e) {
                $this->assertSame('impersonation', $e->field);
            }
        }

        session()->forget(Impersonation::SESSION_KEY);

        // The admin in the Control Panel is not impersonating.
        $this->actingAs($admin)->delete(cp_route('accounts.customers.deletion.cancel', $user->id()))->assertRedirect();
        $this->assertNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function a_failing_eraser_changes_nothing_and_announces_nothing(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->endSubscriptions();
        Accounts::deletion()->request($user);

        app(ErasureRegistry::class)->register(new class implements ErasesPersonalData
        {
            public function key(): string
            {
                return 'zz-fail';
            }

            public function label(): string
            {
                return 'x';
            }

            public function available(): bool
            {
                return true;
            }

            public function blockers(StatamicUser $user, string $audience = 'customer'): array
            {
                return [];
            }

            public function erase(StatamicUser $user): ErasureResult
            {
                throw new RuntimeException('boom');
            }
        });

        $deleting = 0;
        Event::listen(AccountDeleting::class, function () use (&$deleting) {
            $deleting++;
        });

        $before = [DB::table('entitlements')->count(), DB::table('leadhub_contacts')->count(), DB::table('team_members')->count()];
        $this->travel(15)->days();

        $this->assertSame(0, Accounts::deletion()->purgeDue());
        $this->assertSame(0, Accounts::deletion()->purgeDue());

        $this->assertSame($before, [DB::table('entitlements')->count(), DB::table('leadhub_contacts')->count(), DB::table('team_members')->count()]);
        $this->assertNotNull(User::find($user->id()));
        $this->assertSame(0, $deleting, 'AccountDeleting only after a successful erase.');
    }

    #[Test]
    public function own_ledger_entries_carry_no_address(): void
    {
        Mail::fake();
        $user = $this->customer();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'impersonate']);

        ImpersonationStarted::dispatch($admin, $user);
        Accounts::emailChange()->request($user, 'neu@example.com');
        $request = AccountRequest::query()->ofType(AccountRequest::TYPE_EMAIL_CHANGE)->first();
        Accounts::emailChange()->confirm($request->id, sha1('neu@example.com'));

        // The properties this addon writes. (The actor goes in as an
        // Identity; the ledger keeps its type and ids, it has no address
        // column.)
        $json = json_encode(array_map(fn ($entry) => $entry['attributes']['properties'] ?? [], Activity::$recorded));

        $this->assertStringNotContainsString('@example.com', (string) $json);
        $this->assertNotNull(collect(Activity::$recorded)->firstWhere('type', 'accounts.email_changed'));
    }

    #[Test]
    public function the_cp_says_the_blockers_in_the_third_person_with_a_link(): void
    {
        config()->set('accounts.deletion.portal_url', 'https://example.com/konto');
        app()->setLocale('de');
        $user = $this->customer();

        $admin = Accounts::overview()->account($user)['blockers'];
        $customer = Accounts::deletion()->blockers($user);

        $this->assertStringStartsWith('Das Konto hat', $admin[0]);
        $this->assertStringContainsString('https://example.com/konto', $admin[0]);
        $this->assertStringStartsWith('Dein Abo', $customer[0]);
    }
}
