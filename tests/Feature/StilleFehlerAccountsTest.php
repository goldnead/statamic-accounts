<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\AccountDeleting;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Http\Controllers\Web\AccountController;
use Goldnead\Accounts\Integrations\EmailTemplates\RegistersTemplates as RegistersTemplatesBinding;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Support\Wiring;
use Goldnead\Accounts\Tests\Concerns\DeletionScenario;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
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

    #[Test]
    public function an_expired_confirmation_says_so_instead_of_redirecting_silently(): void
    {
        $user = $this->makeUser();

        $this->actingAs(User::find($user->id()))
            ->withSession([AccountController::PENDING => [
                'action' => 'delete',
                'return' => '/account',
                'at' => now()->subHours(2)->getTimestamp(),
            ]])
            ->get(route('statamic.accounts.resume'))
            ->assertRedirect('/account')
            ->assertSessionHasErrors(['action' => __('accounts::messages.confirmation_expired')], null, 'accounts.delete');
    }

    #[Test]
    public function withdrawing_an_email_change_with_nothing_pending_does_not_claim_success(): void
    {
        $user = $this->makeUser();

        $this->actingAs(User::find($user->id()))
            ->post(route('statamic.accounts.email.cancel'))
            ->assertSessionMissing('accounts.change_email.success')
            ->assertSessionHasErrors(['email' => __('accounts::messages.nothing_to_withdraw')], null, 'accounts.change_email');
    }

    #[Test]
    public function a_failed_automation_count_is_logged_and_reported_as_unknown(): void
    {
        Log::spy();
        Schema::shouldReceive('hasTable')->andThrow(new RuntimeException('schema unavailable'));

        $method = new ReflectionMethod(Wiring::class, 'countAutomations');

        $this->assertNull($method->invoke(app(Wiring::class), 'accounts.deleted'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'automation'));
    }

    #[Test]
    public function a_failed_webhook_count_is_logged_and_reported_as_unknown(): void
    {
        Log::spy();
        Schema::shouldReceive('hasTable')->andThrow(new RuntimeException('schema unavailable'));

        $method = new ReflectionMethod(Wiring::class, 'countWebhooks');

        $this->assertNull($method->invoke(app(Wiring::class), 'accounts.deleted'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'webhook'));
    }

    #[Test]
    public function a_failed_core_mail_lookup_is_logged_not_swallowed(): void
    {
        Log::spy();
        app()->instance(RegistersTemplatesBinding::BINDING, new class
        {
            public function byAddon(): array
            {
                throw new RuntimeException('registry unavailable');
            }
        });

        $method = new ReflectionMethod(Wiring::class, 'coreMails');

        $this->assertSame([], $method->invoke(app(Wiring::class)));
        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'core mail'));
    }
}
