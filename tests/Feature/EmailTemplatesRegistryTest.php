<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\AccountDeletionRequested;
use Goldnead\Accounts\Events\EmailVerificationSent;
use Goldnead\Accounts\Support\MailTemplates;
use Goldnead\Accounts\Tests\Fakes\EmailTemplatesRegistry;
use Goldnead\Accounts\Tests\TestCase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;

/**
 * The five mails announce themselves to email-templates' registry, so the
 * Control Panel there says "Accounts: …" next to each template, lists its
 * placeholders and previews it with examples.
 */
class EmailTemplatesRegistryTest extends TestCase
{
    protected EmailTemplatesRegistry $registry;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->registry = new EmailTemplatesRegistry;
        // Two core mails, as email-templates registers them itself.
        $this->registry->register(['slug' => 'core-verify-email', 'addon' => 'Statamic', 'title' => 'E-Mail-Adresse bestätigen', 'trigger' => fn () => 'Ein Konto bestätigt seine Adresse']);
        $this->registry->register(['slug' => 'core-password-reset', 'addon' => 'Statamic', 'title' => 'Passwort zurücksetzen', 'trigger' => 'Jemand fordert ein neues Passwort an']);

        $app->instance('email-templates.registry', $this->registry);
    }

    #[Test]
    public function the_five_mails_are_registered_with_occasion_event_placeholders_and_defaults(): void
    {
        app()->setLocale('de');

        $mine = array_filter($this->registry->definitions, fn ($d) => ($d['addon'] ?? null) === 'Accounts');

        $this->assertEqualsCanonicalizing(
            array_map(fn ($key) => MailTemplates::slug($key), MailTemplates::keys()),
            array_keys($mine),
        );

        $verify = $this->registry->find('accounts-verify-email');
        $this->assertSame(EmailVerificationSent::class, $verify['event']);
        $this->assertSame('Konto wird angelegt oder Bestätigungslink erneut angefordert', ($verify['trigger'])());
        $this->assertArrayHasKey('action_url', $verify['placeholders']);
        $this->assertArrayHasKey('user.name', $verify['placeholders']);
        $this->assertSame('Bitte bestätige deine E-Mail-Adresse', ($verify['defaults'])()['subject']);

        $deletion = $this->registry->find('accounts-deletion-scheduled');
        $this->assertSame(AccountDeletionRequested::class, $deletion['event']);
        $this->assertArrayHasKey('scheduled_for', $deletion['placeholders']);
        $this->assertStringContainsString('§ 147 AO', ($deletion['defaults'])()['body']);
    }

    #[Test]
    public function the_wiring_screen_lists_the_core_mails_too(): void
    {
        $admin = $this->cpUser('admin@example.com', ['view accounts']);

        $this->actingAs($admin)
            ->get(cp_route('accounts.wiring'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('coreMails', 2)
                ->where('coreMails.0.slug', 'core-verify-email')
                ->where('coreMails.0.trigger', 'Ein Konto bestätigt seine Adresse')
                ->has('coreMails.0.enabled'));
    }
}
