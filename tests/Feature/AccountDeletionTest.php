<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\AccountDeleted;
use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Events\AccountDeletionCancelled;
use Goldnead\Accounts\Events\AccountDeletionRequested;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Statamic\Facades\User;

class AccountDeletionTest extends TestCase
{
    #[Test]
    public function a_request_schedules_the_deletion_after_the_grace_period(): void
    {
        Mail::fake();
        Event::fake([AccountDeletionRequested::class]);
        $this->freezeTime();

        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('statamic.accounts.deletion.request'), ['password' => 'geheim-123'])
            ->assertSessionHas('accounts.delete.success');

        $request = Accounts::deletion()->pending($user);
        $this->assertNotNull($request);
        $this->assertSame(now()->addDays(14)->timestamp, $request->due_at->timestamp);
        $this->assertNotNull(User::find($user->id()), 'The account stays until the grace period is over.');

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'deletion_scheduled' && str_contains($m->htmlBody, 'deletion/cancel/'.$request->id));
        Event::assertDispatched(AccountDeletionRequested::class, fn ($e) => $e->payload()['scheduled_for'] === now()->addDays(14)->toIso8601String());
    }

    #[Test]
    public function the_grace_period_comes_from_the_config(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.grace_days', 30);
        $this->freezeTime();

        $request = Accounts::deletion()->request($this->makeUser());

        $this->assertSame(now()->addDays(30)->timestamp, $request->fresh()->due_at->timestamp);
    }

    #[Test]
    public function the_request_can_be_withdrawn_within_the_grace_period_and_nothing_is_deleted(): void
    {
        Mail::fake();
        Event::fake([AccountDeletionCancelled::class]);
        $user = $this->makeUser();
        $request = Accounts::deletion()->request($user);

        $this->travel(13)->days();

        $this->get(Accounts::deletion()->cancelUrl($request))
            ->assertRedirect('/')
            ->assertSessionHas('accounts.status.kind', 'success');

        $this->travel(5)->days();
        $this->assertSame(0, Accounts::deletion()->purgeDue());

        $this->assertNotNull(User::find($user->id()));
        $this->assertSame(AccountRequest::STATUS_CANCELLED, $request->fresh()->status);
        Event::assertDispatched(AccountDeletionCancelled::class);
    }

    #[Test]
    public function a_signed_in_customer_can_withdraw_from_the_account_page(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        Accounts::deletion()->request($user);

        $this->actingAs($user)
            ->post(route('statamic.accounts.deletion.withdraw'))
            ->assertSessionHas('accounts.delete.success');

        $this->assertNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function after_the_grace_period_the_purge_deletes_the_account_and_announces_it(): void
    {
        Mail::fake();
        Event::fake([AccountDeleting::class, AccountDeleted::class]);
        $user = $this->makeUser();
        $keep = $this->makeUser('bleibt@example.com');
        $request = Accounts::deletion()->request($user);

        $this->travel(14)->days();
        $this->travel(1)->minutes();

        $this->artisan('accounts:purge')->assertSuccessful();

        $this->assertNull(User::find($user->id()));
        $this->assertNotNull(User::find($keep->id()));
        $this->assertSame(AccountRequest::STATUS_COMPLETED, $request->fresh()->status);
        $this->assertNull($request->fresh()->email, 'The finished request keeps no address.');
        Event::assertDispatched(AccountDeleting::class);
        Event::assertDispatched(AccountDeleted::class, fn ($e) => $e->email === 'sina@example.com');
        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'account_deleted' && $m->hasTo('sina@example.com'));
    }

    #[Test]
    public function the_cancel_link_is_dead_once_the_deletion_is_due(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $request = Accounts::deletion()->request($user);
        $url = Accounts::deletion()->cancelUrl($request);

        $this->travel(15)->days();

        $this->get($url)->assertForbidden();
    }

    #[Test]
    public function a_listener_that_throws_keeps_the_account_scheduled(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        Accounts::deletion()->request($user);

        Event::listen(AccountDeleting::class, fn () => throw new RuntimeException('Abo läuft noch'));

        $this->travel(15)->days();

        $this->assertSame(0, Accounts::deletion()->purgeDue());
        $this->assertNotNull(User::find($user->id()));
        $this->assertNotNull(Accounts::deletion()->pending($user));
    }
}
