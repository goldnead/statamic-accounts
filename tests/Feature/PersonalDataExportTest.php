<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Contracts\ContributesPersonalData;
use Goldnead\Accounts\Events\PersonalDataExported;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Tests\Concerns\SeedsSiblingTables;
use Goldnead\Accounts\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Statamic\Contracts\Auth\User;
use ZipArchive;

class PersonalDataExportTest extends TestCase
{
    use SeedsSiblingTables;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSiblingTables();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function unzip(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $files[$name] = json_decode((string) $zip->getFromIndex($i), true);
        }

        $zip->close();

        return $files;
    }

    #[Test]
    public function the_export_holds_one_file_per_registered_addon(): void
    {
        Event::fake([PersonalDataExported::class]);

        $user = $this->makeUser();
        $this->seedSiblingRows($user);

        Accounts::contributeData(new class implements ContributesPersonalData
        {
            public function key(): string
            {
                return 'courses';
            }

            public function label(): string
            {
                return 'Kurse';
            }

            public function available(): bool
            {
                return true;
            }

            public function collect(User $user): array
            {
                return ['progress' => [['lesson' => 'Atmung', 'done' => true]]];
            }
        });

        $file = Accounts::export()->build($user);
        $files = $this->unzip($file['path']);

        $this->assertStringEndsWith('.zip', $file['filename']);
        $this->assertEqualsCanonicalizing(
            ['manifest.json', 'account.json', 'payments.json', 'entitlements.json', 'leadhub.json', 'notifications.json', 'teams.json', 'courses.json'],
            array_keys($files),
        );

        // Only this person's rows, matched case-insensitively.
        $this->assertCount(1, $files['payments.json']['payments']);
        $this->assertSame('chorleitung-kurs', $files['payments.json']['payments'][0]['product']);
        $this->assertSame(['coupon' => 'HERBST'], $files['payments.json']['payments'][0]['meta']);
        $this->assertArrayNotHasKey('ip_hash', $files['payments.json']['payments'][0]);
        $this->assertCount(1, $files['payments.json']['payment_items']);
        $this->assertCount(1, $files['payments.json']['subscriptions']);

        // Grants under the address and under the user, not the team with the same id.
        $this->assertEqualsCanonicalizing(['chorleitung-kurs', 'probenraum'], array_column($files['entitlements.json']['entitlements'], 'product_slug'));

        $this->assertCount(1, $files['leadhub.json']['contacts']);
        $this->assertCount(1, $files['leadhub.json']['events']);
        $this->assertCount(1, $files['notifications.json']['notifications']);
        $this->assertCount(1, $files['notifications.json']['preferences']);
        $this->assertSame('Kammerchor Nord', $files['teams.json']['memberships'][0]['team']);
        $this->assertSame([['lesson' => 'Atmung', 'done' => true]], $files['courses.json']['progress']);

        // The account without its secrets.
        $this->assertSame('sina@example.com', $files['account.json']['email']);
        $this->assertArrayNotHasKey('password_hash', $files['account.json']['data']);
        $this->assertArrayNotHasKey('password', $files['account.json']['data']);

        $this->assertSame([], $files['manifest.json']['failed']);
        Event::assertDispatched(PersonalDataExported::class, fn ($e) => in_array('courses', $e->sections, true));

        @unlink($file['path']);
    }

    #[Test]
    public function an_addon_that_is_not_installed_is_left_out(): void
    {
        $user = $this->makeUser();
        \Illuminate\Support\Facades\Schema::drop('notification_items');

        $keys = array_keys(Accounts::export()->collect($user)['sections']);

        $this->assertNotContains('notifications', $keys);
        $this->assertContains('payments', $keys);
    }

    #[Test]
    public function a_contributor_that_fails_is_named_in_the_manifest(): void
    {
        $user = $this->makeUser();

        Accounts::contributeData(new class implements ContributesPersonalData
        {
            public function key(): string
            {
                return 'broken';
            }

            public function label(): string
            {
                return 'Kaputt';
            }

            public function available(): bool
            {
                return true;
            }

            public function collect(User $user): array
            {
                throw new RuntimeException('Tabelle fehlt');
            }
        });

        $export = Accounts::export()->collect($user);

        $this->assertSame(['broken' => 'Kaputt'], $export['manifest']['failed']);
        $this->assertArrayNotHasKey('broken', $export['sections']);
    }

    #[Test]
    public function a_customer_downloads_their_own_data_and_a_guest_gets_nothing(): void
    {
        $this->get(route('statamic.accounts.export'))->assertForbidden();

        $user = $this->makeUser();
        $response = $this->actingAs($user)->get(route('statamic.accounts.export'));

        $response->assertOk();
        $this->assertStringContainsString('attachment; filename=personal-data-sina-example-com-', (string) $response->headers->get('content-disposition'));
    }

    #[Test]
    public function the_export_can_be_switched_off_for_customers(): void
    {
        config()->set('accounts.export.enabled', false);

        $this->actingAs($this->makeUser())->get(route('statamic.accounts.export'))->assertNotFound();
    }
}
