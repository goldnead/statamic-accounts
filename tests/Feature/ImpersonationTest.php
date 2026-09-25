<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Services\Impersonation;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\Activity\Facades\Activity;
use Goldnead\IdentityContracts\Facades\IdentityContext;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\ImpersonationStarted;
use Statamic\Facades\User;

class ImpersonationTest extends TestCase
{
    protected static ?object $seen = null;

    protected function defineRoutes($router): void
    {
        $router->middleware('web')->get('/who', function () {
            self::$seen = IdentityContext::current();

            return 'ok';
        });
    }

    protected function setUp(): void
    {
        require_once __DIR__.'/../Fakes/siblings.php';
        Activity::$recorded = [];

        parent::setUp();
    }

    #[Test]
    public function without_the_permission_it_is_refused_and_nothing_changes(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $this->actingAs($admin)
            ->post(cp_route('accounts.customers.impersonate', $customer->id()))
            ->assertForbidden();

        $this->assertSame('admin@example.com', User::current()->email());
        $this->assertFalse(session()->has(Impersonation::SESSION_KEY));
        $this->assertSame([], Activity::$recorded);
    }

    #[Test]
    public function with_the_permission_the_admin_becomes_the_customer_and_it_is_recorded(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'impersonate']);

        $this->actingAs($admin)
            ->post(cp_route('accounts.customers.impersonate', $customer->id()))
            // A customer without CP access lands on the site.
            ->assertRedirect('/');

        $this->assertSame('sina@example.com', User::current()->email());
        $this->assertSame((string) $admin->id(), (string) session(Impersonation::SESSION_KEY));

        $entry = collect(Activity::$recorded)->firstWhere('type', 'accounts.impersonation_started');
        $this->assertNotNull($entry, 'Every impersonation is on record.');
        $this->assertSame((string) $admin->id(), $entry['attributes']['properties']['impersonator_id']);
        $this->assertSame((string) $customer->id(), $entry['attributes']['properties']['user_id']);
        // The admin is the actor, as an Identity: the ledger takes nothing else.
        $this->assertSame((string) $admin->id(), $entry['attributes']['actor']->userId ?? $entry['attributes']['actor']->id);
    }

    #[Test]
    public function without_the_activity_addon_the_record_goes_to_the_log(): void
    {
        config()->set('accounts.integrations.activity', false);
        Log::spy();

        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'impersonate']);

        $this->actingAs($admin)->post(cp_route('accounts.customers.impersonate', $customer->id()));

        Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'statamic-accounts: accounts.impersonation_started'
            && $context['impersonator_id'] === (string) $admin->id());
    }

    #[Test]
    public function while_impersonating_the_identity_names_the_admin(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'impersonate']);

        $this->actingAs($customer)
            ->withSession([Impersonation::SESSION_KEY => (string) $admin->id()])
            ->get('/who')
            ->assertOk();

        $seen = self::$seen;
        $this->assertNotNull($seen);
        $this->assertSame((string) $customer->id(), $seen->userId ?? $seen->id);
        $this->assertSame((string) $admin->id(), $seen->meta['impersonated_by']);

        // Outside the request, nothing stays pinned.
        $this->assertArrayNotHasKey('impersonated_by', IdentityContext::current()->meta);
    }

    #[Test]
    public function what_happens_during_an_impersonation_names_the_admin_as_actor(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com');

        $this->actingAs($customer);
        session([Impersonation::SESSION_KEY => (string) $admin->id()]);
        // As the middleware pins it during the request.
        IdentityContext::setCurrent(IdentityContext::resolve($customer)->withMeta(['impersonated_by' => (string) $admin->id()]));

        Accounts::verification()->markVerified($customer);

        IdentityContext::setCurrent(null);

        $entry = collect(Activity::$recorded)->firstWhere('type', 'accounts.email_verified');
        $this->assertNotNull($entry);
        $actor = $entry['attributes']['actor'];
        $this->assertSame((string) $admin->id(), $actor->userId ?? $actor->id);
        $this->assertSame((string) $customer->id(), $actor->meta['acting_as']);
    }

    #[Test]
    public function core_impersonation_from_the_user_listing_is_recorded_too(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['impersonate']);

        ImpersonationStarted::dispatch($admin, $customer);

        $this->assertNotNull(collect(Activity::$recorded)->firstWhere('type', 'accounts.impersonation_started'));
    }
}
