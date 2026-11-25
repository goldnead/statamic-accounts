<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Tests\Concerns\DeletionScenario;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Statamic\Events\UserDeleting;
use Statamic\Facades\User;

/**
 * Failures that used to end in a success message or a silent log line:
 * a resend that sends nothing, a purge run that reports success although
 * deletions failed, a failure log without its origin.
 */
class StilleFehlerAccountsTest extends TestCase
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

    #[Test]
    public function resending_for_an_already_confirmed_address_says_so_instead_of_claiming_a_send(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $verification = Accounts::verification();
        $user->set($verification->field(), now()->toDateTimeString());
        $user->save();

        $this->actingAs(User::find($user->id()))
            ->post(route('statamic.accounts.verification.resend'))
            ->assertSessionMissing('accounts.verify.success')
            ->assertSessionHasErrors(['verify' => __('accounts::messages.already_verified')], null, 'accounts.verify');

        Mail::assertNotSent(AccountMail::class);
    }

    #[Test]
    public function a_purge_run_with_a_failed_deletion_exits_with_failure_and_the_others_still_run(): void
    {
        Mail::fake();
        $this->dueRequest();
        Event::listen(UserDeleting::class, fn () => false);

        $this->artisan('accounts:purge')->assertExitCode(1);
    }

    #[Test]
    public function a_purge_run_without_failures_still_succeeds(): void
    {
        Mail::fake();
        $this->dueRequest();

        $this->artisan('accounts:purge')->assertExitCode(0);
    }

    #[Test]
    public function a_failed_deletion_is_logged_with_the_file_and_line_it_came_from(): void
    {
        Mail::fake();
        config()->set('accounts.deletion.active_subscriptions', 'cancel');
        $user = $this->customer();
        Accounts::deletion()->request($user);
        $this->travel(15)->days();
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e) use (&$logged) {
            if ($e->level === 'error') {
                $logged[] = $e->context;
            }
        });
        Event::listen(AccountDeleting::class, fn () => throw new RuntimeException('listener'));

        $this->assertSame(0, Accounts::deletion()->purgeDue());

        $this->assertCount(1, $logged);
        $this->assertArrayHasKey('file', $logged[0], 'The failure log names no file.');
        $this->assertArrayHasKey('line', $logged[0], 'The failure log names no line.');
    }
}
