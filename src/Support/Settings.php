<?php

namespace Goldnead\Accounts\Support;

use Goldnead\BrandContext\Contracts\ProvidesSettings;

/**
 * The values an operator may change in the Control Panel, through the
 * settings layer of goldnead/statamic-brand-context. Registered only when
 * that addon is installed; without it everything stays in `config/`.
 *
 * Not here, and why: `integrations.*` is read while booting (the bridges
 * register once), and `verification.field` names a column or YAML key whose
 * change would make every confirmed account look unconfirmed.
 */
class Settings implements ProvidesSettings
{
    /**
     * Stays forever: it is stored in `brand_settings.namespace` on every row.
     */
    public static function settingsNamespace(): string
    {
        return 'accounts';
    }

    public static function settingsConfigPath(): string
    {
        return 'accounts';
    }

    public static function settingsPermission(): string
    {
        return 'manage accounts settings';
    }

    /**
     * @return array<int, array{title: string, description: string, fields: array<int, array<string, mixed>>}>
     */
    public static function settingsGroups(): array
    {
        return [
            [
                'title' => __('accounts::settings.groups.verification.title'),
                'description' => __('accounts::settings.groups.verification.description'),
                'fields' => [
                    static::field('verification.enabled', 'boolean'),
                    static::field('verification.send_on_register', 'boolean'),
                    static::field('verification.mail', 'select', ['options' => [
                        'auto' => __('accounts::settings.options.verification_mail.auto'),
                        'accounts' => __('accounts::settings.options.verification_mail.accounts'),
                        'laravel' => __('accounts::settings.options.verification_mail.laravel'),
                    ]]),
                    static::field('verification.expire_minutes', 'integer', ['min' => 5]),
                    static::field('verification.notice_url', 'string'),
                    static::field('email_change.expire_minutes', 'integer', ['min' => 5]),
                    static::field('email_change.notify_old_address', 'boolean'),
                    static::field('password_change.notify', 'boolean'),
                ],
            ],
            [
                'title' => __('accounts::settings.groups.deletion.title'),
                'description' => __('accounts::settings.groups.deletion.description'),
                'fields' => [
                    static::field('deletion.grace_days', 'integer', ['min' => 1]),
                    static::field('deletion.active_subscriptions', 'select', ['options' => [
                        'block' => __('accounts::settings.options.active_subscriptions.block'),
                        'cancel' => __('accounts::settings.options.active_subscriptions.cancel'),
                    ]]),
                    static::field('deletion.portal_url', 'string', ['nullable' => true]),
                    static::field('deletion.logout', 'boolean'),
                    static::field('export.enabled', 'boolean'),
                ],
            ],
            [
                'title' => __('accounts::settings.groups.mail.title'),
                'description' => __('accounts::settings.groups.mail.description'),
                'fields' => array_map(
                    fn (string $key) => static::field('mail.templates.'.$key, 'string'),
                    MailTemplates::keys(),
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected static function field(string $key, string $type, array $extra = []): array
    {
        $handle = str_replace('.', '_', $key);

        return array_merge([
            'key' => $key,
            'type' => $type,
            'label' => __("accounts::settings.fields.{$handle}.label"),
            'description' => __("accounts::settings.fields.{$handle}.description"),
            'nullable' => false,
        ], $extra);
    }
}
