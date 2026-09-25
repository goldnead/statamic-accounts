<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Support\Labels;
use Goldnead\Accounts\Tests\Concerns\SeedsSiblingTables;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;

class CpTest extends TestCase
{
    use SeedsSiblingTables;

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function routes(): array
    {
        return [
            'index' => ['get', 'accounts.index', 'view accounts'],
            'wiring' => ['get', 'accounts.wiring', 'view accounts'],
            'show' => ['get', 'accounts.customers.show', 'view accounts'],
            'export' => ['get', 'accounts.customers.export', 'export account data'],
            'resend' => ['post', 'accounts.customers.verification.resend', 'manage accounts'],
            'mark' => ['post', 'accounts.customers.verification.mark', 'manage accounts'],
            'schedule' => ['post', 'accounts.customers.deletion.schedule', 'delete users'],
            'cancel' => ['delete', 'accounts.customers.deletion.cancel', 'manage accounts'],
        ];
    }

    #[Test]
    #[DataProvider('routes')]
    public function every_route_is_closed_without_its_permission(string $method, string $route, string $permission): void
    {
        Mail::fake();
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $url = str_starts_with($route, 'accounts.customers.') ? cp_route($route, $customer->id()) : cp_route($route);

        // JSON, because the CP turns a refused HTML request into a redirect
        // with an error toast; the JSON answer shows the 403 itself.
        $response = $method === 'get' && $permission === 'view accounts'
            ? $this->actingAs($admin)->get($url)
            : $this->actingAs($admin)->{$method.'Json'}($url);

        if ($permission === 'view accounts') {
            $response->assertSuccessful();
        } else {
            $response->assertForbidden();
        }
    }

    #[Test]
    public function a_cp_user_without_view_accounts_sees_nothing(): void
    {
        $admin = $this->cpUser('admin@example.com');

        $this->actingAs($admin)->getJson(cp_route('accounts.index'))->assertForbidden();
        $this->actingAs($admin)->getJson(cp_route('accounts.wiring'))->assertForbidden();
    }

    #[Test]
    public function the_customer_overview_brings_every_installed_section(): void
    {
        $this->createSiblingTables();
        $customer = $this->makeUser();
        $this->seedSiblingRows($customer);
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'manage accounts', 'export account data']);

        $this->actingAs($admin)
            ->get(cp_route('accounts.customers.show', $customer->id()))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('accounts::Customers/Show')
                ->where('overview.account.email', 'sina@example.com')
                ->where('overview.account.verified', false)
                ->where('overview.payments.installed', true)
                ->has('overview.payments.rows', 1)
                ->where('overview.payments.rows.0.amount', '149,00 EUR')
                ->has('overview.subscriptions.rows', 1)
                ->has('overview.entitlements.rows', 2)
                ->where('overview.teams.rows.0.team', 'Kammerchor Nord')
                ->where('can.manage', true)
                ->where('can.export', true)
                ->where('can.impersonate', false));
    }

    #[Test]
    public function codes_arrive_translated(): void
    {
        require_once __DIR__.'/../Fakes/siblings.php';
        app()->setLocale('de');
        $this->createSiblingTables();
        $customer = $this->makeUser();
        $this->seedSiblingRows($customer);
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $this->actingAs($admin)
            ->get(cp_route('accounts.customers.show', $customer->id()))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('overview.payments.rows.0.status', 'paid')
                ->where('overview.payments.rows.0.status_label', 'Bezahlt')
                ->where('overview.subscriptions.rows.0.status_label', 'Aktiv')
                ->where('overview.subscriptions.rows.0.interval_label', 'monatlich')
                ->where('overview.entitlements.rows.0.source_label', 'Kauf')
                ->where('overview.teams.rows.0.role_label', 'Inhaber:in')
                ->where('overview.activity.rows.0.label', 'E-Mail bestätigt')
                ->where('overview.activity.rows.1.label', 'Kauf abgeschlossen')
                // The column headings stay strings: the label tables live
                // apart from them (they once shared the key `status` and the
                // heading printed as a whole array).
                ->where('t.status', 'Status')
                ->where('t.role', 'Rolle')
                ->missing('t.labels'));

        // A type nobody translated reads as words, not as a code. (The
        // request above reset the locale.)
        app()->setLocale('de');
        $this->assertSame('Booking: Slot moved', Labels::activity('booking.slot_moved'));
        $this->assertSame('alle 3 Monate', Labels::interval('3 months'));
        $this->assertSame('jährlich', Labels::interval('year'));
    }

    #[Test]
    public function without_the_sibling_tables_the_sections_say_not_installed(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        // Marker classes may be loaded by an earlier test; the tables decide.
        $this->actingAs($admin)
            ->get(cp_route('accounts.customers.show', $customer->id()))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('overview.payments.installed', false)
                ->where('overview.teams.installed', false));
    }

    #[Test]
    public function an_admin_schedules_and_withdraws_a_deletion(): void
    {
        Mail::fake();
        $customer = $this->makeUser();
        // `delete` is the UserPolicy ability behind core's `delete users`.
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'manage accounts', 'delete']);

        $this->actingAs($admin)->post(cp_route('accounts.customers.deletion.schedule', $customer->id()))->assertRedirect();
        $this->assertNotNull(Accounts::deletion()->pending($customer));

        $this->actingAs($admin)->delete(cp_route('accounts.customers.deletion.cancel', $customer->id()))->assertRedirect();
        $this->assertNull(Accounts::deletion()->pending($customer));
    }

    #[Test]
    public function an_admin_marks_an_address_as_confirmed(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'manage accounts']);

        $this->actingAs($admin)->post(cp_route('accounts.customers.verification.mark', $customer->id()))->assertRedirect();

        $this->assertTrue(Accounts::verification()->isVerified(User::find($customer->id())));
    }

    #[Test]
    public function the_admin_export_is_a_download(): void
    {
        $customer = $this->makeUser();
        $admin = $this->cpUser('admin@example.com', ['view accounts', 'export account data']);

        $response = $this->actingAs($admin)->get(cp_route('accounts.customers.export', $customer->id()));

        $response->assertOk();
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function the_wiring_screen_lists_every_event_and_its_template(): void
    {
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $this->actingAs($admin)
            ->get(cp_route('accounts.wiring'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('accounts::Wiring')
                ->has('events', 8)
                ->where('events.0.handle', 'accounts.verification.sent')
                ->where('events.0.template.slug', 'accounts-verify-email')
                ->where('events.1.template', null)
                ->has('integrations.automations')
                ->has('contributors'));
    }

    #[Test]
    public function an_unknown_user_is_a_404(): void
    {
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $this->actingAs($admin)->get(cp_route('accounts.customers.show', 'nobody'))->assertNotFound();
    }
}
