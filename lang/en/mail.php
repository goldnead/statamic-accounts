<?php

/*
 * The shipped mails. Placeholders in `{{ … }}` as in email-templates:
 * `user.name`, `user.email`, `action_url`, `new_email`, `old_email`,
 * `scheduled_for`, `grace_days`, `expires_in_hours`, `site_name`.
 * For other wording, create an entry in email-templates with the same slug
 * (or import these with `php please email-templates:import`).
 */

$button = fn (string $label) => '<p style="margin:24px 0;"><a href="{{ action_url }}" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;">'.$label.'</a></p>';

return [
    'verify_email' => [
        'title' => 'Accounts: Confirm email address',
        'subject' => 'Please confirm your email address',
        'preview' => 'One click and your account at {{ site_name }} is ready.',
        'description' => 'Sent after registration and on "send again". Placeholders: user.name, user.email, action_url, expires_in_hours.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>please confirm that {{ user.email }} is your address.</p>'
            .$button('Confirm address')
            .'<p>The link is valid for {{ expires_in_hours }} hours. If you did not create an account at {{ site_name }}, ignore this mail.</p>',
    ],
    'confirm_email_change' => [
        'title' => 'Accounts: Confirm new email address',
        'subject' => 'Confirm your new email address',
        'preview' => 'Your new address takes effect after this click.',
        'description' => 'Sent to the new address. Placeholders: user.name, new_email, old_email, action_url, expires_in_hours.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} is to use {{ new_email }} instead of {{ old_email }}.</p>'
            .$button('Confirm new address')
            .'<p>Nothing changes until you open the link. It is valid for {{ expires_in_hours }} hours.</p>',
    ],
    'email_changed' => [
        'title' => 'Accounts: Notice to the old address',
        'subject' => 'Your email address was changed',
        'preview' => 'Your account now uses {{ new_email }}.',
        'description' => 'Sent to the old address once the new one is confirmed. Placeholders: user.name, new_email, old_email.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} now uses {{ new_email }}. {{ old_email }} will no longer receive account mail.</p>'
            .'<p>If this was not you, reply to this mail right away.</p>',
    ],
    'deletion_scheduled' => [
        'title' => 'Accounts: Deletion scheduled',
        'subject' => 'Your account will be deleted on {{ scheduled_for }}',
        'preview' => 'Until then you can change your mind.',
        'description' => 'Sent when a deletion is requested. Placeholders: user.name, scheduled_for, grace_days, action_url (withdraw).',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} is scheduled for deletion. On {{ scheduled_for }} we delete it along with the data attached to it.</p>'
            .'<p>Until then you can withdraw the request:</p>'
            .$button('Keep my account'),
    ],
    'account_deleted' => [
        'title' => 'Accounts: Account deleted',
        'subject' => 'Your account is deleted',
        'preview' => 'The grace period has ended.',
        'description' => 'Last mail after deletion. Placeholders: user.name, user.email.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} is now deleted. Thank you for having been here.</p>',
    ],
];
