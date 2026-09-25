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
    'fields' => [
        'verification_enabled' => ['label' => 'Require confirmation', 'description' => 'Off: the accounts.verified middleware lets everyone through and no notice is shown.'],
        'verification_send_on_register' => ['label' => 'Send after registration', 'description' => 'Sends the link right after the registration form.'],
        'verification_expire_minutes' => ['label' => 'Confirmation link valid for (minutes)', 'description' => 'After that the link is refused and a new one has to be requested.'],
        'verification_notice_url' => ['label' => 'Page for unconfirmed accounts', 'description' => 'Where the accounts.verified middleware sends a signed-in user whose address is not confirmed yet.'],
        'email_change_expire_minutes' => ['label' => 'Change link valid for (minutes)', 'description' => 'How long the link to a new address works.'],
        'email_change_notify_old_address' => ['label' => 'Tell the old address', 'description' => 'Sends a notice to the previous address once the new one is confirmed.'],
        'deletion_grace_days' => ['label' => 'Grace period (days)', 'description' => 'The account is deleted this many days after the request. 0 deletes on the next daily run.'],
        'deletion_logout' => ['label' => 'Sign out after the request', 'description' => 'Off: the customer stays signed in and can withdraw the request in the account.'],
        'export_enabled' => ['label' => 'Customers may download their data', 'description' => 'Off: the export link answers 404. The export in the Control Panel stays.'],
        'mail_templates_verify_email' => ['label' => 'Confirm address', 'description' => 'Slug of the confirmation mail.'],
        'mail_templates_confirm_email_change' => ['label' => 'Confirm new address', 'description' => 'Slug of the mail to the new address.'],
        'mail_templates_email_changed' => ['label' => 'Address changed', 'description' => 'Slug of the notice to the old address.'],
        'mail_templates_deletion_scheduled' => ['label' => 'Deletion scheduled', 'description' => 'Slug of the mail with the link to withdraw.'],
        'mail_templates_account_deleted' => ['label' => 'Account deleted', 'description' => 'Slug of the last mail.'],
    ],
];
