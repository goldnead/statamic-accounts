<?php

return [
    'groups' => [
        'verification' => [
            'title' => 'Email address',
            'description' => 'Confirming the address after registration and when it changes.',
        ],
        'deletion' => [
            'title' => 'Deletion and export',
            'description' => 'How long a deletion can be withdrawn, and whether customers may download their data.',
        ],
        'mail' => [
            'title' => 'Mail templates',
            'description' => 'Slugs in Email Templates. A slug without an entry sends the text shipped with the addon.',
        ],
    ],
    'options' => [
        'active_subscriptions' => ['block' => 'Block the deletion', 'cancel' => 'Cancel the subscriptions'],
        'verification_mail' => ['auto' => 'Automatic', 'accounts' => 'Always the Accounts mail', 'laravel' => 'Always Laravel\'s VerifyEmail'],
    ],
    'fields' => [
        'verification_enabled' => ['label' => 'Require confirmation', 'description' => 'Off: the accounts.verified middleware lets everyone through and no notice is shown.'],
        'verification_send_on_register' => ['label' => 'Send after registration', 'description' => 'Sends the link right after the registration form.'],
        'verification_expire_minutes' => ['label' => 'Confirmation link valid for (minutes)', 'description' => 'After that the link is refused and a new one has to be requested.'],
        'verification_notice_url' => ['label' => 'Page for unconfirmed accounts', 'description' => 'Where the accounts.verified middleware sends a signed-in user whose address is not confirmed yet.'],
        'email_change_expire_minutes' => ['label' => 'Change link valid for (minutes)', 'description' => 'How long the link to a new address works.'],
        'email_change_notify_old_address' => ['label' => 'Tell the old address', 'description' => 'Sends a notice to the previous address when a new one is entered, and again once the new one is confirmed.'],
        'password_change_notify' => ['label' => 'Announce password changes', 'description' => 'Sends "Your password was changed" to the account\'s address, whether it was changed in the profile, in the Control Panel or through a reset link.'],
        'verification_mail' => ['label' => 'Confirmation mail', 'description' => 'Automatic: Laravel\'s VerifyEmail (template core-verify-email) for user models with MustVerifyEmail, the Accounts mail for everyone else. Never both.'],
        'deletion_grace_days' => ['label' => 'Grace period (days)', 'description' => 'The account is deleted this many days after the request. At least 1, so the withdraw link can be opened.'],
        'deletion_active_subscriptions' => ['label' => 'Running subscriptions', 'description' => 'Block: the deletion is refused, with a link to the customer portal. Cancel: running subscriptions are cancelled through Payments when the deletion is requested.'],
        'deletion_portal_url' => ['label' => 'Customer portal address', 'description' => 'Where the running-subscription notice links to. Empty: the Payments customer portal.'],
        'deletion_logout' => ['label' => 'Sign out after the request', 'description' => 'Off: the customer stays signed in and can withdraw the request in the account.'],
        'export_enabled' => ['label' => 'Customers may download their data', 'description' => 'Off: the export link answers 404. The export in the Control Panel stays.'],
        'mail_templates_verify_email' => ['label' => 'Confirm address', 'description' => 'Slug of the confirmation mail.'],
        'mail_templates_confirm_email_change' => ['label' => 'Confirm new address', 'description' => 'Slug of the mail to the new address.'],
        'mail_templates_email_changed' => ['label' => 'Address changed', 'description' => 'Slug of the notice to the old address.'],
        'mail_templates_email_change_requested' => ['label' => 'Change of address requested', 'description' => 'Slug of the notice to the current address once a new one is entered.'],
        'mail_templates_password_changed' => ['label' => 'Password changed', 'description' => 'Slug of the "Your password was changed" mail.'],
        'mail_templates_deletion_scheduled' => ['label' => 'Deletion scheduled', 'description' => 'Slug of the mail with the link to withdraw.'],
        'mail_templates_deletion_blocked' => ['label' => 'Deletion blocked', 'description' => 'Slug of the mail when a due deletion is blocked.'],
        'mail_templates_account_deleted' => ['label' => 'Account deleted', 'description' => 'Slug of the last mail.'],
    ],
];
