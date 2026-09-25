<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\EmailChanged;
use Goldnead\Accounts\Events\EmailChangeRequested;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Models\AccountRequest;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;

class EmailChangeTest extends TestCase
{
    protected function linkFromMail(): string
    {
        $url = null;

        Mail::assertSent(AccountMail::class, function (AccountMail $mail) use (&$url) {
            if ($mail->templateKey === 'confirm_email_change' && preg_match('/href="([^"]+email\/confirm[^"]+)"/', $mail->htmlBody, $m)) {
                $url = html_entity_decode($m[1]);
            }

            return true;
        });

        $this->assertNotNull($url, 'No confirmation link in the mail.');

        return $url;
    }

    #[Test]
    public function the_new_address_is_active_only_after_its_link_is_opened(): void
    {
        Mail::fake();
        Event::fake([EmailChangeRequested::class, EmailChanged::class]);

        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('statamic.accounts.email.change'), ['email' => 'neu@example.com', 'password' => 'geheim-123'])
            ->assertSessionHas('accounts.change_email.success');

        // Nothing moved yet.
        $this->assertSame('sina@example.com', User::find($user->id())->email());
        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->hasTo('neu@example.com') && $m->templateKey === 'confirm_email_change');
        Event::assertDispatched(EmailChangeRequested::class, fn ($e) => $e->payload()['new_email'] === 'neu@example.com' && $e->email === 'sina@example.com');

        $this->get($this->linkFromMail())->assertRedirect('/')->assertSessionHas('accounts.status.kind', 'success');

        $fresh = User::find($user->id());
        $this->assertSame('neu@example.com', $fresh->email());
        $this->assertTrue(Accounts::verification()->isVerified($fresh));
        Mail::assertSent(AccountMail::class, fn (AccountMail $m) => $m->hasTo('sina@example.com') && $m->templateKey === 'email_changed');
        Event::assertDispatched(EmailChanged::class, fn ($e) => $e->payload() === [
            'user_id' => (string) $user->id(), 'email' => 'neu@example.com', 'name' => 'Sina Sänger', 'old_email' => 'sina@example.com',
        ]);
    }

    #[Test]
    public function a_wrong_password_changes_nothing(): void
    {
        Mail::fake();
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('statamic.accounts.email.change'), ['email' => 'neu@example.com', 'password' => 'falsch'])
            ->assertSessionHasErrors(['password'], null, 'accounts.change_email');

        Mail::assertNothingSent();
        $this->assertSame(0, AccountRequest::query()->count());
    }

    #[Test]
    public function an_address_another_account_uses_is_refused(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $this->makeUser('taken@example.com');

        $this->actingAs($user)
            ->post(route('statamic.accounts.email.change'), ['email' => 'Taken@example.com', 'password' => 'geheim-123'])
            ->assertSessionHasErrors(['email'], null, 'accounts.change_email');
    }

    #[Test]
    public function an_expired_or_tampered_link_is_refused(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        Accounts::emailChange()->request($user, 'neu@example.com');
        $url = $this->linkFromMail();

        // A different address hash in an otherwise valid link.
        $this->get(preg_replace('#confirm/(\d+)/[0-9a-f]{40}#', 'confirm/$1/'.sha1('boese@example.com'), $url))->assertForbidden();

        $this->travel(2)->days();
        $this->get($url)->assertForbidden();

        $this->assertSame('sina@example.com', User::find($user->id())->email());
    }

    #[Test]
    public function a_second_request_replaces_the_first_and_the_old_link_dies(): void
    {
        Mail::fake();
        $user = $this->makeUser();

        $first = Accounts::emailChange()->request($user, 'eins@example.com');
        $firstUrl = Accounts::emailChange()->url($first);
        Accounts::emailChange()->request($user, 'zwei@example.com');

        $this->get($firstUrl)->assertSessionHas('accounts.status.kind', 'error');
        $this->assertSame('sina@example.com', User::find($user->id())->email());
        $this->assertSame('zwei@example.com', Accounts::emailChange()->pending($user)->email);
    }

    #[Test]
    public function the_form_tag_shows_the_pending_address(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        $this->actingAs($user);
        Accounts::emailChange()->request($user, 'neu@example.com');

        $html = (string) \Statamic\Facades\Antlers::parse('{{ accounts:change_email_form redirect="/konto" }}{{ if pending_email }}Wartet: {{ pending_email }}{{ /if }}{{ /accounts:change_email_form }}', [], true);

        $this->assertStringContainsString('Wartet: neu@example.com', $html);
        $this->assertStringContainsString('action="'.route('statamic.accounts.email.change').'"', $html);
        $this->assertStringContainsString('name="_redirect" value="/konto"', $html);
    }
}
