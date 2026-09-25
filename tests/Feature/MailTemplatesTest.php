<?php

namespace Goldnead\Accounts\Tests\Feature;

use Goldnead\Accounts\Integrations\EmailTemplates\DefaultTemplateSource;
use Goldnead\Accounts\Support\MailTemplates;
use Goldnead\Accounts\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MailTemplatesTest extends TestCase
{
    #[Test]
    public function every_mail_has_a_german_and_an_english_default(): void
    {
        foreach (['de', 'en'] as $locale) {
            app()->setLocale($locale);

            foreach (MailTemplates::keys() as $key) {
                $default = MailTemplates::defaultFor($key);

                $this->assertStringNotContainsString('accounts::', $default['subject'].$default['body'], "{$locale}.{$key}");
                $this->assertNotSame('', $default['slug']);
                $this->assertStringNotContainsString('—', $default['subject'].$default['body']);
            }
        }
    }

    #[Test]
    public function the_default_is_rendered_with_escaped_values_and_a_raw_link(): void
    {
        app()->setLocale('de');

        $rendered = app(MailTemplates::class)->render('verify_email', [
            'user' => ['name' => '<script>x</script>', 'email' => 'a@b.c'],
            'action_url' => 'https://example.com/v?a=1&signature=abc',
            'expires_in_hours' => 24,
        ]);

        $this->assertSame('Bitte bestätige deine E-Mail-Adresse', $rendered['subject']);
        $this->assertStringContainsString('&lt;script&gt;', $rendered['html']);
        $this->assertStringContainsString('href="https://example.com/v?a=1&signature=abc"', $rendered['html']);
        $this->assertStringContainsString('24 Stunden', $rendered['html']);
        $this->assertStringContainsString('<!DOCTYPE html>', $rendered['html']);
    }

    #[Test]
    public function a_nameless_account_is_greeted_by_its_address(): void
    {
        app()->setLocale('de');

        $rendered = app(MailTemplates::class)->render('account_deleted', ['user' => ['name' => null, 'email' => 'a@b.c']]);

        $this->assertStringContainsString('Hallo a@b.c,', $rendered['html']);
    }

    #[Test]
    public function the_defaults_are_offered_to_the_email_templates_import(): void
    {
        require_once __DIR__.'/../Fakes/email-templates-contracts.php';

        $templates = (new DefaultTemplateSource)->all();

        $this->assertCount(count(MailTemplates::keys()), $templates);
        $this->assertSame('accounts-verify-email', $templates[0]->slug);
        $this->assertStringContainsString('{{ action_url }}', $templates[0]->body);
    }
}
