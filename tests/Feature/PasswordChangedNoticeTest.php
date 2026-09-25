<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\PasswordChanged;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;

/**
 * "Dein Passwort wurde geändert" (Gesamtprüfung 25.09.2026, shots/G-12b).
 *
 * A changed password went unannounced. Whoever changed it in an open session
 * locked the owner out without a word. Now the account's address hears of it,
 * however the password was changed: the Statamic profile form, the Control
 * Panel, a reset link, the host's own code. Watched at the save, because the
 * front-end password form of Statamic fires no event of its own.
 */
class PasswordChangedNoticeTest extends TestCase
{
    #[Test]
    public function a_changed_password_is_announced_to_the_accounts_address(): void
    {
        $user = $this->makeUser();
        Mail::fake();
        Event::fake([PasswordChanged::class]);

        $user->password('ein-ganz-neues-9');
        $user->save();

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->hasTo('sina@example.com') && $m->templateKey === 'password_changed');
        Event::assertDispatched(PasswordChanged::class, fn (PasswordChanged $e) => $e->email === 'sina@example.com'
            && ! array_key_exists('password', $e->payload()));
    }

    #[Test]
    public function saving_without_touching_the_password_announces_nothing(): void
    {
        $user = $this->makeUser();
        Mail::fake();

        $user->set('name', 'Sina Umbenannt');
        $user->save();

        Mail::assertNotSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'password_changed');
    }

    #[Test]
    public function a_new_account_is_not_told_its_password_changed(): void
    {
        Mail::fake();

        $this->makeUser('neu@example.com');

        Mail::assertNotSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'password_changed');
    }

    #[Test]
    public function the_notice_can_be_switched_off(): void
    {
        config(['accounts.password_change.notify' => false]);

        $user = $this->makeUser();
        Mail::fake();

        $user->password('ein-ganz-neues-9');
        $user->save();

        Mail::assertNotSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'password_changed');
    }

    #[Test]
    public function a_user_loaded_fresh_from_the_store_is_watched_too(): void
    {
        $this->makeUser();
        Mail::fake();

        $user = User::findByEmail('sina@example.com');
        $user->password('ein-ganz-neues-9');
        $user->save();

        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->templateKey === 'password_changed');
    }
}
