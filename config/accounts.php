<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email verification
    |--------------------------------------------------------------------------
    |
    | `field` is where the moment of verification is stored on the user. The
    | default is the column Laravel's own users table already has, so an
    | Eloquent user repository needs no migration. A file-based user gets it
    | as an ordinary YAML key.
    |
    | `notice_url` is where the `accounts.verified` middleware sends a signed-in
    | user whose address is not confirmed yet. Put `{{ accounts:verify_notice }}`
    | on that page.
    |
    */

    'verification' => [
        'enabled' => true,
        'field' => 'email_verified_at',

        // Which mail confirms the address. `auto`: Laravel's VerifyEmail
        // (email-templates: `core-verify-email`) for an Eloquent model that
        // implements MustVerifyEmail on a site with a `verification.verify`
        // route, this addon's mail for everyone else. `accounts` or
        // `laravel` to force one.
        'mail' => 'auto',
        'send_on_register' => true,
        'expire_minutes' => 60 * 24,
        'notice_url' => '/',
        'redirect' => '/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Changing the email address
    |--------------------------------------------------------------------------
    |
    | The new address becomes active only once the link sent to it is opened.
    | Until then the old one stays in place and keeps working for login.
    |
    */

    'email_change' => [
        'expire_minutes' => 60 * 24,
        'notify_old_address' => true,
        'redirect' => '/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Deleting the account
    |--------------------------------------------------------------------------
    |
    | A request only schedules the deletion. The account stays usable and the
    | request can be withdrawn until `grace_days` have passed; after that the
    | daily `accounts:purge` run deletes it.
    |
    */

    'deletion' => [
        // At least 1: the withdraw link needs time to be opened.
        'grace_days' => 14,
        'logout' => false,
        'redirect' => '/',

        // A subscription that still charges: `block` refuses the deletion
        // and points to the payments customer portal; `cancel` cancels it
        // through payments when the deletion is requested (and again when
        // it is due, should a new one have started).
        'active_subscriptions' => 'block',

        // Where the blocker message sends people to cancel. Empty: the
        // payments portal (`statamic-payments.portal.request`) when present.
        'portal_url' => '',
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal data export
    |--------------------------------------------------------------------------
    */

    'export' => [
        'enabled' => true,
        'throttle' => '3,60',
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail templates
    |--------------------------------------------------------------------------
    |
    | Slugs in `goldnead/statamic-email-templates`. A slug that has no entry
    | there falls back to the default text shipped with this addon, so every
    | mail goes out even before anybody opened the Control Panel. Import the
    | defaults as editable entries with `php please email-templates:import`.
    |
    */

    'mail' => [
        'templates' => [
            'verify_email' => 'accounts-verify-email',
            'confirm_email_change' => 'accounts-confirm-email-change',
            'email_changed' => 'accounts-email-changed',
            'deletion_scheduled' => 'accounts-deletion-scheduled',
            'account_deleted' => 'accounts-account-deleted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Impersonation
    |--------------------------------------------------------------------------
    |
    | The switch itself is Statamic's (`statamic.users.impersonate`). This is
    | only where the customer overview's "Sign in as" lands for a user without
    | Control Panel access.
    |
    */

    'impersonation' => [
        'redirect' => '/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sibling addons
    |--------------------------------------------------------------------------
    |
    | Each bridge also needs the sibling to be installed. Read while booting,
    | so a change here takes effect on the next deploy.
    |
    */

    'integrations' => [
        'automations' => true,
        'webhook_manager' => true,
        'activity' => true,
    ],

];
