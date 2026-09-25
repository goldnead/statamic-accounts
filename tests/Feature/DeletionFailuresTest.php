<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Support\AccountMailer;
use Goldnead\Accounts\Tests\Concerns\DeletionScenario;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\Activity\Facades\Activity;
use Goldnead\StatamicPayments\Support\Subscriptions;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Statamic\Events\UserDeleting;
use Statamic\Facades\Antlers;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

/**
 * When the last step fails. Taken over from the coordinator's probe
 * (ProbeR3Test, 25.09.2026): a user that does not go (a `UserDeleting` veto,
 * a file that cannot be removed), a subscription cancelled before a rollback,
 * a blocked-mail that cannot be sent, a user already gone.
 */
class DeletionFailuresTest extends TestCase
{
    use DeletionScenario;

    protected function setUp(): void
    {
        require_once __DIR__.'/../Fakes/siblings.php';

        // setUpDeletionScenario() runs from Laravel's setUpTraits().
        parent::setUp();
    }

    protected function dueRequest(): AccountRequest
    {
        $user = $this->customer();
        $this->endSubscriptions();
        $request = Accounts::deletion()->request($user);
        $this->travel(15)->days();

        return $request;
    }

    protected function assertNothingHappened(AccountRequest $request, string $userId): void
    {
        $this->assertSame(AccountRequest::STATUS_PENDING, $request->fresh()->status);
        $this->assertNotNull(User::find($userId));
        $this->assertSame(0, count(Mail::sent(AccountMail::class, fn ($m) => $m->templateKey === 'account_deleted')));
        // Rolled back: her grants are still there.
        $this->assertSame(2, DB::table('entitlements')->whereIn('subject_id', ['sina@example.com', $userId])->count());
    }

    #[Test]
    public function a_user_deleting_veto_rolls_everything_back(): void
    {
        Mail::fake();
        $request = $this->dueRequest();
        Event::listen(UserDeleting::class, fn () => false);

        $this->assertSame(0, Accounts::deletion()->purgeDue());

        $this->assertNothingHappened($request, $request->user_id);
    }

    #[Test]
    public function a_user_file_that_cannot_be_removed_rolls_everything_back(): void
    {
        Mail::fake();
        $request = $this->dueRequest();
        $path = User::find($request->user_id)->path();
        // Only her file refuses to go; everything else the test harness
        // deletes (the Stache fixture directory) still can.
        File::partialMock()->shouldReceive('delete')->andReturnUsing(
            fn ($paths) => in_array($path, (array) $paths, true) ? false : (new Filesystem)->delete($paths)
        );

        $this->assertSame(0, Accounts::deletion()->purgeDue());

        File::swap(new Filesystem);
        $this->assertFileExists($path);
        Stache::clear();
        $this->assertNothingHappened($request, $request->user_id);
    }

    #[Test]
    public function a_subscription_cancelled_before_a_rollback_is_on_record_and_said_when_withdrawing(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.active_subscriptions', 'cancel');
        $user = $this->customer();
        $request = Accounts::deletion()->request($user);
        Event::listen(AccountDeleting::class, fn () => throw new RuntimeException('listener'));
        $this->travel(15)->days();

        $this->assertSame(0, Accounts::deletion()->purgeDue());

        $this->assertCount(1, Subscriptions::$cancelled);
        $this->assertSame(1, $request->fresh()->meta['subscriptions_cancelled']);
        $entry = collect(Activity::$recorded)->firstWhere('type', 'accounts.subscriptions_cancelled');
        $this->assertNotNull($entry);
        $this->assertSame($request->id, $entry['attributes']['properties']['request_id']);

        app()->setLocale('de');
        $this->actingAs($user)
            ->post(route('statamic.accounts.deletion.withdraw'))
            ->assertSessionHas('accounts.delete.success', fn ($message) => str_contains($message, 'gekündigt'));
    }

    #[Test]
    public function the_blocked_state_is_saved_only_once_the_mail_went_out(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->endSubscriptions();
        $request = Accounts::deletion()->request($user);
        $this->teamGetsAnotherMember();
        $this->travel(15)->days();

        $this->mock(AccountMailer::class)->shouldReceive('send')->andThrow(new RuntimeException('smtp down'));

        $this->assertSame(0, Accounts::deletion()->purgeDue());
        $this->assertSame(AccountRequest::STATUS_PENDING, $request->fresh()->status, 'Not blocked: the next run tries the mail again.');
    }

    #[Test]
    public function a_request_whose_user_is_already_gone_is_logged_not_closed_silently(): void
    {
        Mail::fake();
        $request = $this->dueRequest();
        Log::spy();

        // Deleted some other way, e.g. in the Control Panel.
        User::find($request->user_id)->delete();

        Accounts::deletion()->purgeDue();

        $this->assertSame(AccountRequest::STATUS_COMPLETED, $request->fresh()->status);
        $this->assertSame('user_missing', $request->fresh()->meta['outcome']);
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'was already gone'));
    }

    #[Test]
    public function the_list_shows_since_when_a_deletion_is_blocked_not_when_it_was_due(): void
    {
        Mail::fake();
        $user = $this->customer();
        $this->endSubscriptions();
        Accounts::deletion()->request($user);
        $this->teamGetsAnotherMember();
        $this->travel(20)->days();
        Accounts::deletion()->purgeDue();

        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $this->actingAs($admin)
            ->get(cp_route('accounts.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('customers', fn ($rows) => collect($rows)->contains(fn ($row) => $row['email'] === 'sina@example.com'
                    && $row['deletion_blocked'] === true
                    && $row['deletion_blocked_since'] === now()->isoFormat('L'))));
    }

    #[Test]
    public function the_delete_form_lists_a_running_subscription_as_blocker(): void
    {
        config()->set('accounts.deletion.portal_url', 'https://example.com/konto');
        app()->setLocale('de');
        $user = $this->customer();
        $this->actingAs($user);

        $html = (string) Antlers::parse('{{ accounts:delete_form }}{{ blockers }}<li>{{ value }}</li>{{ /blockers }}{{ /accounts:delete_form }}', [], true);

        $this->assertStringContainsString('<li>Dein Abo', $html);
        $this->assertStringContainsString('https://example.com/konto', $html);
    }

    #[Test]
    public function the_blocked_mail_says_how_long_its_link_works(): void
    {
        Mail::fake();
        app()->setLocale('de');
        $user = $this->customer();
        $this->endSubscriptions();
        Accounts::deletion()->request($user);
        $this->teamGetsAnotherMember();
        $this->travel(15)->days();

        Accounts::deletion()->purgeDue();

        Mail::assertSent(AccountMail::class, fn ($m) => $m->templateKey === 'deletion_blocked' && str_contains($m->htmlBody, '30 Tage'));
    }
}
