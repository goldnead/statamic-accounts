<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Tests\Concerns\SeedsSiblingTables;
use Goldnead\Accounts\Tests\Fakes\ActivityAnonymizeCommand;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\StatamicPayments\Support\Subscriptions;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;

/**
 * Deleting an account deletes what the suite holds about the person, keeps
 * what the law says must be kept, and says which is which.
 */
class ErasureTest extends TestCase
{
    use SeedsSiblingTables;

    protected function setUp(): void
    {
        require_once __DIR__.'/../Fakes/siblings.php';

        parent::setUp();

        $this->createSiblingTables();
        $this->app[Kernel::class]->registerCommand(new ActivityAnonymizeCommand);
    }

    protected function seededCustomer(): \Statamic\Auth\User
    {
        $user = $this->makeUser();
        $this->seedSiblingRows($user);
        $this->seedStranger();

        // The shared seed has a team grant whose subject id equals the user
        // id, to prove the export does not mix them up. Teams have integer
        // ids and users uuids here, so that collision cannot happen for real;
        // it would only blur the "nothing names her" scan below.
        DB::table('entitlements')->where('subject_type', 'team')->delete();

        // A second team where she is only a member, with its own roles and
        // an invitation she sent.
        $team = DB::table('teams')->insertGetId(['name' => 'Gospelprojekt', 'owner_id' => 'jemand-anders', 'created_at' => now()]);
        DB::table('team_members')->insert([
            ['team_id' => $team, 'user_id' => 'jemand-anders', 'role' => 'owner', 'created_at' => now()],
            ['team_id' => $team, 'user_id' => (string) $user->id(), 'role' => 'member', 'created_at' => now()],
        ]);
        DB::table('team_roles')->insert(['team_id' => $team, 'handle' => 'member', 'label' => 'Mitglied']);
        DB::table('team_invitations')->insert(['team_id' => $team, 'email' => 'freundin@example.com', 'role' => 'member', 'invited_by' => (string) $user->id(), 'created_at' => now()]);

        return $user;
    }

    protected function endSubscriptions(): void
    {
        DB::table('subscriptions')->where('email', 'sina@example.com')->update(['status' => 'cancelled']);
    }

    #[Test]
    public function after_the_purge_nothing_names_the_person_except_the_retained_records(): void
    {
        Mail::fake();
        $user = $this->seededCustomer();
        $this->endSubscriptions();
        $id = (string) $user->id();

        // An earlier, withdrawn request and an address change: their rows
        // must not keep the name either.
        Accounts::emailChange()->request($user, 'neu@example.com');
        Accounts::deletion()->request($user);
        Accounts::deletion()->cancel($user);

        $request = Accounts::deletion()->request($user);
        $this->travel(15)->days();

        $this->assertSame(1, Accounts::deletion()->purgeDue());
        $this->assertNull(User::find($id));

        $retained = ['payments', 'payment_items', 'subscriptions', 'invoices'];

        // Address and name: nowhere outside the retained records, this
        // addon's own table included.
        $this->assertSame([], $this->rowsNaming('no-id-check', 'sina@example.com', $retained));
        $this->assertSame([], $this->rowsNaming('no-id-check', 'Sina Sänger', $retained));
        $this->assertSame([], $this->rowsNaming('no-id-check', 'Sina', $retained));

        // The id: only in the deletion record, where it is pseudonymous (it
        // points at an account that no longer exists).
        $this->assertSame([], $this->rowsNaming($id, 'no-address-check@invalid', array_merge($retained, ['account_requests'])));
        // Two deletion rows stay (the withdrawn one and this one), the
        // address change is gone; none carries an address, only this one
        // carries meta (the erasure counts).
        $this->assertSame(2, AccountRequest::query()->where('user_id', $id)->count());
        $this->assertSame(0, AccountRequest::query()->where('user_id', $id)->where('type', '!=', AccountRequest::TYPE_DELETION)->count());
        $this->assertSame(0, AccountRequest::query()->where('user_id', $id)->whereNotNull('email')->count());
        $this->assertSame(1, AccountRequest::query()->where('user_id', $id)->whereNotNull('meta')->count());

        // Retained, untouched.
        $this->assertSame(1, DB::table('payments')->whereRaw('lower(email) = ?', ['sina@example.com'])->count());
        $this->assertSame(1, DB::table('invoices')->where('buyer_email', 'sina@example.com')->count());

        // The stranger keeps everything.
        $this->assertSame(1, DB::table('entitlements')->where('subject_id', 'fremd@example.com')->count());
        $this->assertSame(1, DB::table('leadhub_contacts')->where('email', 'fremd@example.com')->count());
        $this->assertSame(1, DB::table('activities')->where('user_id', 'fremd-id')->count());

        // Her own team went with her; the one with others stayed, without her.
        $this->assertSame(0, DB::table('teams')->where('name', 'Kammerchor Nord')->count());
        $this->assertSame(1, DB::table('teams')->where('name', 'Gospelprojekt')->count());

        // The request says what happened, and names nobody.
        $erasure = $request->fresh()->meta['erasure'];
        $this->assertSame(2, $erasure['entitlements']['deleted']['entitlements']);
        $this->assertSame(1, $erasure['leadhub']['deleted']['contacts']);
        $this->assertSame(1, $erasure['payments']['retained']['payments']);
        $this->assertSame(1, $erasure['invoices']['retained']['invoices']);
        $this->assertNotEmpty($erasure['payments']['note']);
        $this->assertStringNotContainsString('sina', json_encode($request->fresh()->meta));
    }

    #[Test]
    public function a_running_subscription_blocks_the_request_with_a_link_to_the_portal(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.portal_url', 'https://example.com/konto');
        $user = $this->seededCustomer();

        try {
            Accounts::deletion()->request($user);
            $this->fail('A running subscription must block the deletion.');
        } catch (AccountException $e) {
            $this->assertStringContainsString('chor-abo', $e->getMessage());
            $this->assertStringContainsString('https://example.com/konto', $e->getMessage());
        }

        $this->assertNull(Accounts::deletion()->pending($user));
        Mail::assertNothingSent();
    }

    #[Test]
    public function with_the_cancel_setting_the_subscription_is_cancelled_through_payments(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.active_subscriptions', 'cancel');
        $user = $this->seededCustomer();

        Subscriptions::$cancelled = [];

        // The request goes through: the subscription is no blocker with
        // this policy, and nothing is cancelled yet.
        Accounts::deletion()->request($user);
        $this->assertNotNull(Accounts::deletion()->pending($user));
        $this->assertCount(0, Subscriptions::$cancelled);

        // Cancelled right before the erasure.
        $this->travel(15)->days();
        $this->assertSame(1, Accounts::deletion()->purgeDue());
        $this->assertCount(1, Subscriptions::$cancelled);
    }

    #[Test]
    public function the_last_owner_of_a_team_with_other_members_cannot_be_deleted(): void
    {
        Mail::fake();
        $user = $this->seededCustomer();
        $this->endSubscriptions();

        $team = DB::table('teams')->where('name', 'Kammerchor Nord')->value('id');
        DB::table('team_members')->insert(['team_id' => $team, 'user_id' => 'mitsaengerin', 'role' => 'member', 'created_at' => now()]);

        $this->expectException(AccountException::class);
        $this->expectExceptionMessage('Kammerchor Nord');

        Accounts::deletion()->request($user);
    }

    #[Test]
    public function a_blocker_that_appears_during_the_grace_period_keeps_the_account(): void
    {
        Mail::fake();
        $user = $this->seededCustomer();
        $this->endSubscriptions();
        Accounts::deletion()->request($user);

        // She started a new subscription in the meantime.
        DB::table('subscriptions')->insert(['provider' => 'mollie', 'product' => 'neu', 'amount_cent' => 900, 'currency' => 'EUR', 'interval' => '1 month', 'status' => 'active', 'email' => 'sina@example.com', 'created_at' => now()]);

        $this->travel(15)->days();

        $this->assertSame(0, Accounts::deletion()->purgeDue());
        $this->assertNotNull(User::find($user->id()));
        $this->assertNotNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function the_mail_says_what_is_deleted_and_what_stays_for_legal_reasons(): void
    {
        Mail::fake();
        app()->setLocale('de');
        $user = $this->seededCustomer();
        $this->endSubscriptions();

        Accounts::deletion()->request($user);

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'deletion_scheduled'
            && str_contains($m->htmlBody, 'Rechnungen')
            && str_contains($m->htmlBody, '§ 147 AO'));
    }

    #[Test]
    public function the_account_requests_keep_no_address(): void
    {
        Mail::fake();
        $user = $this->seededCustomer();
        $this->endSubscriptions();

        Accounts::emailChange()->request($user, 'neu@example.com');
        Accounts::deletion()->request($user);
        $this->travel(15)->days();
        Accounts::deletion()->purgeDue();

        $this->assertSame(0, AccountRequest::query()->whereNotNull('email')->count());
    }
}
