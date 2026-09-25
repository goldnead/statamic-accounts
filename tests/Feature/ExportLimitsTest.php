<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;

/**
 * `export.throttle` limits the customer's own download; `export.enabled`
 * switches only that download off. The Control Panel export stays, because
 * an admin answering a request under Art. 15 GDPR has to be able to hand
 * out the data whatever the site offers customers.
 */
class ExportLimitsTest extends TestCase
{
    protected function tearDown(): void
    {
        RateLimiter::clear('accounts-export');

        parent::tearDown();
    }

    #[Test]
    public function the_customer_export_is_throttled_by_the_setting(): void
    {
        config()->set('accounts.export.throttle', '2,60');
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('statamic.accounts.export'))->assertOk();
        $this->actingAs($user)->get(route('statamic.accounts.export'))->assertOk();
        $this->actingAs($user)->get(route('statamic.accounts.export'))->assertStatus(429);

        // Per person: someone else still gets theirs.
        $this->actingAs($this->makeUser('other@example.com'))->get(route('statamic.accounts.export'))->assertOk();

        // The window is the second number, in minutes.
        $this->travel(61)->minutes();
        $this->actingAs($user)->get(route('statamic.accounts.export'))->assertOk();
    }

    #[Test]
    public function switching_the_customer_export_off_leaves_the_admin_export(): void
    {
        config()->set('accounts.export.enabled', false);
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'export account data']);

        $this->actingAs($customer)->get(route('statamic.accounts.export'))->assertNotFound();

        $this->actingAs($admin)->get(cp_route('accounts.customers.export', $customer->id()))->assertOk();
        $this->actingAs($admin)
            ->get(cp_route('accounts.customers.show', $customer->id()))
            ->assertInertia(fn ($page) => $page->where('can.export', true));
    }
}
