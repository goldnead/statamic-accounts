<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Statamic registers its confirmation page only when elevated sessions are
 * on while the routes load. A site that switches them on later (a config
 * cache, a runtime `config()->set`) has no page to send people to. That is a
 * clear 403, not a 500 from `route()`.
 */
class ElevatedSessionRoutesTest extends TestCase
{
    #[Test]
    public function elevated_sessions_on_without_the_confirmation_route_refuse_instead_of_crashing(): void
    {
        Mail::fake();
        $user = $this->makeUser();
        config()->set('statamic.users.elevated_sessions_enabled', true);

        $this->actingAs($user)->withSession(['statamic_elevated_session' => now()->subHours(3)->timestamp])
            ->post(route('statamic.accounts.deletion.request'))
            ->assertForbidden();
        $this->actingAs($user)->get(route('statamic.accounts.export'))->assertForbidden();

        $this->assertNull(Accounts::deletion()->pending($user));
    }
}
