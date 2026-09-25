<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\EmailVerificationSent;
use Goldnead\Accounts\Events\EmailVerified;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Tests\Fixtures\VerifyingEloquentUser;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Blink;
use Statamic\Facades\User;

/**
 * One line for the confirmation mail, not two.
 *
 * A user model that implements Laravel's `MustVerifyEmail`, on a site that
 * has Laravel's `verification.verify` route, gets Laravel's `VerifyEmail`
 * (which email-templates renders as `core-verify-email`). Everyone else, file
 * users included, gets this addon's mail. Either way the account events and
 * the ledger entry are the same.
 */
class LaravelVerificationTest extends EloquentUsersTest
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.providers.users.model', VerifyingEloquentUser::class);
        $app['config']->set('statamic.users.repositories.eloquent.model', VerifyingEloquentUser::class);
    }

    protected function defineRoutes($router): void
    {
        $router->get('/email/verify/{id}/{hash}', fn () => 'ok')->name('verification.verify');
    }

    protected function verifyingUser(): \Statamic\Auth\User
    {
        $model = VerifyingEloquentUser::create(['name' => 'Chorleiterin', 'email' => 'leitung@example.com']);

        return User::find($model->id);
    }

    #[Test]
    public function a_must_verify_email_model_gets_laravels_notification_and_not_a_second_mail(): void
    {
        Notification::fake();
        Mail::fake();
        Event::fake([EmailVerificationSent::class]);

        $user = $this->verifyingUser();

        $this->assertTrue(Accounts::verification()->send($user));

        Notification::assertSentTo(VerifyingEloquentUser::find($user->id()), VerifyEmail::class);
        Mail::assertNotSent(AccountMail::class);
        Event::assertDispatched(EmailVerificationSent::class);
    }

    #[Test]
    public function laravels_verified_event_becomes_the_account_event(): void
    {
        Event::fake([EmailVerified::class]);

        $user = $this->verifyingUser();
        $model = VerifyingEloquentUser::find($user->id());
        $model->markEmailAsVerified();

        event(new Verified($model));

        Event::assertDispatched(EmailVerified::class, fn ($e) => $e->userId === (string) $user->id() && $e->email === 'leitung@example.com');

        // Statamic caches the Eloquent lookup for the request (Blink).
        Blink::flush();
        $this->assertTrue(Accounts::verification()->isVerified(User::find($user->id())));
    }

    #[Test]
    public function set_to_accounts_the_addon_sends_its_own_mail_even_then(): void
    {
        Notification::fake();
        Mail::fake();
        config()->set('accounts.verification.mail', 'accounts');

        Accounts::verification()->send($this->verifyingUser());

        Mail::assertSent(AccountMail::class, fn ($m) => $m->templateKey === 'verify_email');
        Notification::assertNothingSent();
    }
}
