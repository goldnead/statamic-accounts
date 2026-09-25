<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Events\EmailVerified;
use Goldnead\Accounts\Facades\Accounts;
use Goldnead\Accounts\Integrations\Automations\AutomationsBridge;
use Goldnead\Accounts\Integrations\WebhookManager\WebhookManagerBridge;
use Goldnead\Accounts\Mail\AccountMail;
use Goldnead\Accounts\Support\EventCatalog;
use Goldnead\Accounts\Tests\TestCase;
use Goldnead\Activity\Facades\Activity;
use Goldnead\EmailTemplates\Facades\EmailTemplates;
use Goldnead\StatamicAutomations\Facades\Automations;
use Goldnead\WebhookManager\Facades\WebhookManager;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

/**
 * Both trigger bridges and the activity record, against stand-ins of the
 * siblings loaded before the application boots, so the registration in the
 * provider's booted callback sees them as it would the installed packages.
 */
class BridgesTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__.'/../Fakes/siblings.php';

        Automations::$registered = [];
        Automations::$dispatched = [];
        WebhookManager::$registered = [];
        WebhookManager::$fired = [];
        Activity::$recorded = [];

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The real webhook-manager binds this in its own boot.
        $app->instance('webhook-manager', new \stdClass);
    }

    #[Test]
    public function every_event_is_registered_as_an_automations_trigger_once(): void
    {
        $this->assertTrue(app(AutomationsBridge::class)->registered());

        $handles = array_column(EventCatalog::all(), 'handle');
        $this->assertEqualsCanonicalizing($handles, array_keys(Automations::$registered));
        $this->assertSame(EmailVerified::class, Automations::$registered['accounts.email.verified']['event']);
        $this->assertArrayHasKey('account', Automations::$registered['accounts.email.verified']['definition']['output_schema']);
    }

    #[Test]
    public function every_event_is_registered_as_a_webhook_trigger(): void
    {
        $this->assertTrue(app(WebhookManagerBridge::class)->registered());
        $this->assertEqualsCanonicalizing(array_column(EventCatalog::all(), 'handle'), array_keys(WebhookManager::$registered));
        $this->assertSame('user', WebhookManager::$registered['accounts.deleted']['config']['source_type']);
    }

    #[Test]
    public function firing_an_event_fires_both_triggers_with_the_payload_and_no_secret(): void
    {
        Mail::fake();
        $user = $this->makeUser();

        Accounts::emailChange()->request($user, 'neu@example.com');

        $automation = collect(Automations::$dispatched)->firstWhere('handle', 'accounts.email_change.requested');
        $webhook = collect(WebhookManager::$fired)->firstWhere('handle', 'accounts.email_change.requested');

        $this->assertNotNull($automation);
        $this->assertNotNull($webhook);
        $this->assertSame('neu@example.com', $automation['context']['account']['new_email']);
        $this->assertSame((string) $user->id(), $webhook['payload']['reference']);
        $this->assertSame('sina@example.com', $webhook['payload']['email']);

        // The confirmation link is the key to the account; it must not travel.
        $this->assertStringNotContainsString('signature', json_encode($webhook['payload']));
        $this->assertStringNotContainsString('signature', json_encode($automation['context']));
    }

    #[Test]
    public function the_bridges_can_be_switched_off(): void
    {
        config()->set('accounts.integrations.automations', false);

        $this->assertFalse(app(AutomationsBridge::class)->available());
    }

    #[Test]
    public function account_facts_land_in_the_activity_ledger(): void
    {
        $user = $this->makeUser();
        Accounts::verification()->markVerified($user);

        $entry = collect(Activity::$recorded)->firstWhere('type', 'accounts.email_verified');

        $this->assertNotNull($entry);
        $this->assertSame((string) $user->id(), $entry['attributes']['properties']['user_id']);
    }

    #[Test]
    public function a_mail_uses_the_email_templates_entry_when_there_is_one(): void
    {
        Mail::fake();

        EmailTemplates::$templates['accounts-verify-email'] = (object) [
            'subject' => 'Willkommen {{ user.name }}',
            'body' => '<p>Eigener Text: <a href="{{ action_url }}">hier</a> für {{ user.email }}</p>',
            'source' => 'entry',
        ];

        $user = $this->makeUser();
        Accounts::verification()->send($user);

        Mail::assertSent(AccountMail::class, function ($mail) {
            return $mail->subject === 'Willkommen Sina Sänger'
                && str_contains($mail->htmlBody, 'Eigener Text')
                && str_contains($mail->htmlBody, 'für sina@example.com')
                && str_contains($mail->htmlBody, '&signature=');
        });

        EmailTemplates::$templates = [];
    }
}
