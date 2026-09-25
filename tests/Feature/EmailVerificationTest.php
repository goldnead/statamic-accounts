<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\EmailVerificationSent;
use Goldnead\Accounts\Events\EmailVerified;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\UserRegistered;
use Statamic\Facades\Antlers;
use Statamic\Facades\User;

class EmailVerificationTest extends TestCase
{
    /**
     * Before Statamic's catch-all, which would answer `/members` itself.
     */
    protected function defineRoutes($router): void
    {
        $router->middleware(['web', 'accounts.verified'])->get('/members', fn () => 'members area');
    }

    #[Test]
    public function a_valid_link_confirms_the_address(): void
    {
        Event::fake([EmailVerified::class]);
        $user = $this->makeUser();

        $this->get(Accounts::verification()->url($user))
            ->assertRedirect('/')
            ->assertSessionHas('accounts.status.kind', 'success');

        $this->assertTrue(Accounts::verification()->isVerified(User::find($user->id())));
        Event::assertDispatched(EmailVerified::class, fn ($e) => $e->userId === (string) $user->id());
    }

    #[Test]
    public function an_expired_link_is_refused(): void
    {
        $user = $this->makeUser();
        $url = Accounts::verification()->url($user);

        $this->travel(2)->days();

        $this->get($url)->assertForbidden();
        $this->assertFalse(Accounts::verification()->isVerified(User::find($user->id())));
    }

    #[Test]
    public function a_manipulated_link_is_refused(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('other@example.com');
        $url = Accounts::verification()->url($user);

        // Someone else's id in an otherwise valid link.
        $this->get(str_replace((string) $user->id(), (string) $other->id(), $url))->assertForbidden();
        // A longer life.
        $this->get(preg_replace('/expires=\d+/', 'expires='.(time() + 999999), $url))->assertForbidden();

        $this->assertFalse(Accounts::verification()->isVerified(User::find($other->id())));
        $this->assertFalse(Accounts::verification()->isVerified(User::find($user->id())));
    }

    #[Test]
    public function a_link_for_an_address_the_account_no_longer_has_is_refused(): void
    {
        $user = $this->makeUser();
        $url = Accounts::verification()->url($user);

        $user->email('new@example.com')->save();

        $this->get($url)->assertRedirect('/')->assertSessionHas('accounts.status.kind', 'error');
        $this->assertFalse(Accounts::verification()->isVerified(User::find($user->id())));
    }

    #[Test]
    public function registering_sends_the_mail_with_a_signed_link(): void
    {
        Mail::fake();
        Event::fake([EmailVerificationSent::class]);

        $user = $this->makeUser();
        event(new UserRegistered($user));

        Mail::assertSent(AccountMail::class, function (AccountMail $mail) use ($user) {
            return $mail->hasTo($user->email())
                && $mail->templateKey === 'verify_email'
                && str_contains($mail->htmlBody, '/!/statamic-accounts/verify/'.$user->id())
                && str_contains($mail->htmlBody, 'signature=')
                // The link sits in an href unescaped, or the signature breaks.
                && ! str_contains($mail->htmlBody, '&amp;signature');
        });
        Event::assertDispatched(EmailVerificationSent::class);
    }

    #[Test]
    public function the_resend_form_needs_a_signed_in_user_and_sends_again(): void
    {
        Mail::fake();

        $this->post(route('statamic.accounts.verification.resend'))->assertForbidden();

        $user = $this->makeUser();
        $this->actingAs($user)
            ->post(route('statamic.accounts.verification.resend'))
            ->assertSessionHas('accounts.verify.success');

        Mail::assertSent(AccountMail::class, 1);
    }

    #[Test]
    public function the_middleware_sends_unconfirmed_users_to_the_notice_page(): void
    {
        config()->set('accounts.verification.notice_url', '/bestaetigen');

        $this->get('/members')->assertOk();

        $user = $this->makeUser();
        $this->actingAs($user)->get('/members')->assertRedirect('/bestaetigen');
        $this->actingAs($user)->getJson('/members')->assertForbidden();

        Accounts::verification()->markVerified($user);
        $this->actingAs($user)->get('/members')->assertOk()->assertSee('members area');
    }

    #[Test]
    public function the_notice_tag_shows_only_for_unconfirmed_users(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $html = (string) Antlers::parse('{{ accounts:verify_notice }}Bitte bestätige {{ email }}{{ /accounts:verify_notice }}', [], true);

        $this->assertStringContainsString('Bitte bestätige sina@example.com', $html);
        $this->assertStringContainsString('action="'.route('statamic.accounts.verification.resend').'"', $html);
        $this->assertStringContainsString('name="_token"', $html);

        Accounts::verification()->markVerified($user);

        $this->assertSame('', trim((string) Antlers::parse('{{ accounts:verify_notice }}x{{ /accounts:verify_notice }}', [], true)));
    }
}
