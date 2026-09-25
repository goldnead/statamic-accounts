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
            // Forms come back to the page they were on; the download comes
            // back to itself and starts.
            ['post', route('statamic.accounts.email.change'), ['email' => 'neu@example.com'], '/konto'],
            ['post', route('statamic.accounts.deletion.request'), [], '/konto'],
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
