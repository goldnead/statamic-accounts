<?php

namespace Goldnead\Accounts\Support;

use Goldnead\Accounts\Events\AccountDeleted;
use Goldnead\Accounts\Events\AccountDeletionBlocked;
use Goldnead\Accounts\Events\AccountDeletionCancelled;
use Goldnead\Accounts\Events\AccountDeletionRequested;
use Goldnead\Accounts\Events\AccountEvent;
use Goldnead\Accounts\Events\EmailChanged;
use Goldnead\Accounts\Events\EmailChangeRequested;
use Goldnead\Accounts\Events\EmailVerificationSent;
use Goldnead\Accounts\Events\EmailVerified;
use Goldnead\Accounts\Events\PersonalDataExported;

/**
 * The one list of what this addon announces.
 *
 * Both bridges register from it and the "Wiring" screen shows it, so a new
 * event added here is a trigger in automations, a trigger in webhook-manager
 * and a row in the Control Panel at once. `AccountDeleting` is deliberately
 * missing: it is a synchronous hook for listeners in the same request, and a
 * webhook sent from it would announce a deletion that a later listener may
 * still stop.
 */
class EventCatalog
{
    /**
     * Event class => the mail template key it sends (see `accounts.mail.templates`), or null.
     *
     * @var array<class-string<AccountEvent>, string|null>
     */
    public const EVENTS = [
        EmailVerificationSent::class => 'verify_email',
        EmailVerified::class => null,
        EmailChangeRequested::class => 'confirm_email_change',
        EmailChanged::class => 'email_changed',
        AccountDeletionRequested::class => 'deletion_scheduled',
        AccountDeletionCancelled::class => null,
        AccountDeletionBlocked::class => 'deletion_blocked',
        AccountDeleted::class => 'account_deleted',
        PersonalDataExported::class => null,
    ];

    /**
     * Payload keys each event adds to the shared `user_id`, `email`, `name`.
     *
     * @var array<class-string<AccountEvent>, array<string, string>>
     */
    protected const EXTRA_FIELDS = [
        EmailChangeRequested::class => ['new_email' => 'string'],
        EmailChanged::class => ['old_email' => 'string'],
        AccountDeletionRequested::class => ['scheduled_for' => 'datetime'],
        AccountDeletionBlocked::class => ['reasons' => 'integer'],
        PersonalDataExported::class => ['sections' => 'array', 'requested_by' => 'string'],
    ];

    /**
     * @return list<array{class: class-string<AccountEvent>, handle: string, label: string, description: string, template_key: string|null, template: string|null, fields: array<string, string>}>
     */
    public static function all(): array
    {
        $rows = [];

        foreach (self::EVENTS as $class => $templateKey) {
            $handle = $class::handle();
            $key = str_replace(['accounts.', '.'], ['', '_'], $handle);

            $rows[] = [
                'class' => $class,
                'handle' => $handle,
                'label' => __('accounts::events.'.$key.'.label'),
                'description' => __('accounts::events.'.$key.'.description'),
                'template_key' => $templateKey,
                'template' => $templateKey === null ? null : (string) config('accounts.mail.templates.'.$templateKey),
                'fields' => array_merge(
                    ['user_id' => 'string', 'email' => 'string', 'name' => 'string'],
                    self::EXTRA_FIELDS[$class] ?? [],
                ),
            ];
        }

        return $rows;
    }
}
