<?php

namespace Goldnead\Accounts\Integrations\EmailTemplates;

use Goldnead\Accounts\Support\EventCatalog;
use Goldnead\Accounts\Support\MailTemplates;

/**
 * Announces the five account mails to email-templates' registry
 * (`email-templates.registry`, statamic-email-templates fc0df26): who sends
 * them, on which occasion, after which event, which placeholders they know
 * with an example each, and the shipped text.
 *
 * Plain arrays and closures only, so nothing from the sibling is loaded; a
 * site without it, or with an older version without the registry, is
 * untouched.
 */
class RegistersTemplates
{
    public const BINDING = 'email-templates.registry';

    /**
     * Placeholders each mail knows, beyond `user.name`, `user.email` and
     * `site_name`, with an example for the preview.
     *
     * @var array<string, array<string, string|int>>
     */
    protected const PLACEHOLDERS = [
        'verify_email' => ['action_url' => 'https://example.com/!/statamic-accounts/verify/…', 'expires_in_hours' => 24],
        'confirm_email_change' => ['action_url' => 'https://example.com/!/statamic-accounts/email/confirm/…', 'new_email' => 'maria.neu@example.com', 'old_email' => 'maria.beispiel@example.com', 'expires_in_hours' => 24],
        'email_changed' => ['new_email' => 'maria.neu@example.com', 'old_email' => 'maria.beispiel@example.com'],
        'deletion_scheduled' => ['action_url' => 'https://example.com/!/statamic-accounts/deletion/cancel/…', 'scheduled_for' => '9. Oktober 2026', 'grace_days' => 14],
        'deletion_blocked' => ['action_url' => 'https://example.com/!/statamic-accounts/deletion/cancel/…', 'reasons_list' => '<ul><li>Dein Abo „Chor-Abo“ läuft noch.</li></ul>'],
        'account_deleted' => [],
    ];

    public static function register(): void
    {
        if (! app()->bound(self::BINDING)) {
            return;
        }

        $registry = app(self::BINDING);

        if (! is_object($registry) || ! method_exists($registry, 'register')) {
            return;
        }

        $events = array_flip(array_filter(EventCatalog::EVENTS));

        foreach (MailTemplates::keys() as $key) {
            $slug = MailTemplates::slug($key);

            if ($slug === '') {
                continue;
            }

            $registry->register([
                'slug' => $slug,
                'addon' => 'Accounts',
                'title' => fn () => __('accounts::mail.'.$key.'.title'),
                'trigger' => fn () => __('accounts::mail.'.$key.'.trigger'),
                'event' => $events[$key] ?? null,
                'placeholders' => static::placeholders($key),
                'defaults' => function () use ($key) {
                    $default = MailTemplates::defaultFor($key);
                    unset($default['slug']);

                    return $default;
                },
            ]);
        }
    }

    /**
     * @return array<string, array{label: \Closure, example: mixed}>
     */
    protected static function placeholders(string $key): array
    {
        $examples = array_merge([
            'user.name' => 'Maria Beispiel',
            'user.email' => 'maria.beispiel@example.com',
            'site_name' => (string) config('app.name'),
        ], self::PLACEHOLDERS[$key] ?? []);

        $placeholders = [];

        foreach ($examples as $name => $example) {
            $placeholders[$name] = [
                'label' => fn () => __('accounts::mail.placeholders.'.str_replace('.', '_', $name)),
                'example' => $example,
            ];
        }

        return $placeholders;
    }
}
