<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Exceptions\AccountException;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;
use Statamic\Testing\Concerns\ElevatesSessions;

/**
 * Changing the address, deleting and exporting ask for Statamic's elevated
 * session (password, passkey or mailed code, whatever the account has), not
 * for a password this addon checks itself. While an admin is signed in as
 * the customer, all three are closed.
 */
class ElevatedSessionTest extends TestCase
{
    use ElevatesSessions;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('statamic.users.elevated_sessions_enabled', true);
    }

    #[Test]
    public function without_an_elevated_session_the_forms_send_to_cores_confirmation_and_come_back(): void
    {
        Mail::fake();
        $user = $this->makeUser();

        foreach ([
            // Forms come back to `resume`, which runs what they asked for
            // and then goes to the page they were on; the download comes
            // back to itself and starts.
            ['post', route('statamic.accounts.email.change'), ['email' => 'neu@example.com'], '/!/statamic-accounts/resume'],
            ['post', route('statamic.accounts.deletion.request'), [], '/!/statamic-accounts/resume'],
            ['get', route('statamic.accounts.export'), [], '/!/statamic-accounts/export'],
        ] as [$method, $url, $data, $back]) {
            $this->actingAs($user)
                ->from('/konto')
                ->{$method}($url, $data)
                ->assertRedirect(route('statamic.elevated-session'));

            $this->assertSame($back, parse_url((string) session('url.intended'), PHP_URL_PATH));
        }

        $this->assertNull(Accounts::emailChange()->pending($user));
        $this->assertNull(Accounts::deletion()->pending($user));
        Mail::assertNothingSent();
    }

    #[Test]
    public function after_the_confirmation_the_confirmed_action_runs_without_a_second_click(): void
    {
        Mail::fake();
        $user = $this->makeUser();

        // Delete: the form sends to the confirmation...
        $this->actingAs($user)
            ->from('/konto')
            ->post(route('statamic.accounts.deletion.request'), ['_redirect' => '/konto/einstellungen'])
            ->assertRedirect(route('statamic.elevated-session'));
        $this->assertNull(Accounts::deletion()->pending($user));

        // ...core's confirmation answers with the intended URL...
        $this->post(route('statamic.elevated-session.confirm'), ['password' => 'geheim-123'])
            ->assertRedirect(route('statamic.accounts.resume'));

        // ...and that runs the deletion and lands where the form said.
        $this->get(route('statamic.accounts.resume'))
            ->assertRedirect('/konto/einstellungen')
            ->assertSessionHas('accounts.delete.success');
        $this->assertNotNull(Accounts::deletion()->pending($user));

        // Once: opening it again does nothing more.
        Accounts::deletion()->cancel($user);
        $this->get(route('statamic.accounts.resume'))->assertRedirect('/');
        $this->assertNull(Accounts::deletion()->pending($user));

        // Change of address, the same way, with the address from the form.
        $this->flushSession();
        $this->actingAs($user)
            ->from('/konto')
            ->post(route('statamic.accounts.email.change'), ['email' => 'neu@example.com'])
            ->assertRedirect(route('statamic.elevated-session'));
        $this->post(route('statamic.elevated-session.confirm'), ['password' => 'geheim-123']);
        $this->get(route('statamic.accounts.resume'))
            ->assertRedirect('/konto')
            ->assertSessionHas('accounts.change_email.success');
        $this->assertSame('neu@example.com', Accounts::emailChange()->pending($user)?->email);
    }

    #[Test]
    public function the_sites_own_confirmation_page_posts_to_core_and_continues(): void
    {
        Mail::fake();
        config(['statamic.users.elevated_sessions_url' => '/!/statamic-accounts/confirm']);
        $user = $this->makeUser();

        $this->actingAs($user)->from('/konto')->post(route('statamic.accounts.deletion.request'));

        // Core's page hands over to the site's.
        $this->get(route('statamic.elevated-session'))->assertRedirect('/!/statamic-accounts/confirm');

        $this->get(route('statamic.accounts.confirm'))
            ->assertOk()
            ->assertSee('action="'.route('statamic.elevated-session.confirm').'"', false)
            ->assertSee('name="password"', false)
            ->assertSee(__('accounts::messages.confirm_then_delete'))
            ->assertSee('href="/konto"', false);

        // A wrong password comes back to the page with the error on it.
        $this->from(route('statamic.accounts.confirm'))
            ->post(route('statamic.elevated-session.confirm'), ['password' => 'falsch'])
            ->assertRedirect(route('statamic.accounts.confirm'));
        $this->get(route('statamic.accounts.confirm'))->assertSee('role="alert"', false);

        $this->post(route('statamic.elevated-session.confirm'), ['password' => 'geheim-123'])
            ->assertRedirect(route('statamic.accounts.resume'));
        $this->get(route('statamic.accounts.resume'))->assertRedirect('/konto');
        $this->assertNotNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function an_account_without_a_password_gets_the_code_field(): void
    {
        Mail::fake();
        $user = User::make()->email('code@example.com');
        $user->save();

        $this->actingAs($user)->get(route('statamic.accounts.confirm'))
            ->assertOk()
            ->assertSee('name="verification_code"', false)
            ->assertSee('code@example.com')
            ->assertSee(route('statamic.elevated-session.resend-code'), false);
    }

    #[Test]
    public function the_remembered_action_needs_the_confirmation_and_expires(): void
    {
        Mail::fake();
        $user = $this->makeUser();

        $this->actingAs($user)->from('/konto')->post(route('statamic.accounts.deletion.request'));

        // Not confirmed: back to the confirmation, nothing happens.
        $this->get(route('statamic.accounts.resume'))->assertRedirect(route('statamic.elevated-session'));
        $this->assertNull(Accounts::deletion()->pending($user));

        // Confirmed, but later than the confirmation is meant for.
        $this->travel(31)->minutes();
        $this->post(route('statamic.elevated-session.confirm'), ['password' => 'geheim-123']);
        $this->get(route('statamic.accounts.resume'))->assertRedirect('/konto');
        $this->assertNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function with_an_elevated_session_they_go_through_without_a_password_field(): void
    {
        Mail::fake();
        // An account without a password (passkey or OAuth only).
        $user = User::make()->email('pass@example.com');
        $user->save();

        $this->actingAsWithElevatedSession($user)
            ->post(route('statamic.accounts.email.change'), ['email' => 'neu@example.com'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull(Accounts::emailChange()->pending($user));

        $this->actingAsWithElevatedSession($user)
            ->post(route('statamic.accounts.deletion.request'))
            ->assertSessionHasNoErrors();
        $this->assertNotNull(Accounts::deletion()->pending($user));

        $this->actingAsWithElevatedSession($user)->get(route('statamic.accounts.export'))->assertOk();
    }

    #[Test]
    public function while_impersonating_all_three_are_closed(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        // Core's StopImpersonating middleware looks the admin up.
        $admin = $this->cpUser('admin@example.com');

        $session = ['statamic_elevated_session' => now()->timestamp, Impersonation::SESSION_KEY => (string) $admin->id()];

        $this->actingAs($user)->withSession($session)
            ->post(route('statamic.accounts.email.change'), ['email' => 'neu@example.com'])
            ->assertForbidden();
        $this->actingAs($user)->withSession($session)
            ->post(route('statamic.accounts.deletion.request'))
            ->assertForbidden();
        $this->actingAs($user)->withSession($session)
            ->get(route('statamic.accounts.export'))
            ->assertForbidden();

        $this->assertNull(Accounts::emailChange()->pending($user));
        $this->assertNull(Accounts::deletion()->pending($user));
    }

    #[Test]
    public function the_services_refuse_too_so_an_api_layer_cannot_skip_it(): void
    {
        $user = $this->makeUser();
        session([Impersonation::SESSION_KEY => 'admin-id']);

        foreach ([
            fn () => Accounts::emailChange()->request($user, 'neu@example.com'),
            fn () => Accounts::deletion()->request($user),
            fn () => Accounts::export()->build($user),
        ] as $call) {
            try {
                $call();
                $this->fail('Refused while impersonating.');
            } catch (AccountException $e) {
                $this->assertSame('impersonation', $e->field);
            }
        }
    }
}
