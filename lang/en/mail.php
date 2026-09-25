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
    'placeholders' => [
        'user_name' => 'Name (the address when there is none)',
        'user_email' => 'Email address of the account',
        'site_name' => 'Name of the site',
        'action_url' => 'Signed link (confirm or withdraw)',
        'expires_in_hours' => 'Link lifetime in hours',
        'new_email' => 'New address',
        'old_email' => 'Previous address',
        'scheduled_for' => 'Date of deletion',
        'grace_days' => 'Grace period in days',
        'reasons_list' => 'What stands in the way (ready-made list)',
        'link_days' => 'Link lifetime in days',
        'changed_at' => 'When it was changed',
    ],
    'verify_email' => [
        'title' => 'Accounts: Confirm email address',
        'trigger' => 'Account created, or the confirmation link requested again',
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
        'trigger' => 'New address entered, sent to the new address',
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
        'trigger' => 'New address confirmed, sent to the old address',
        'subject' => 'Your email address was changed',
        'preview' => 'Your account now uses {{ new_email }}.',
        'description' => 'Sent to the old address once the new one is confirmed. Placeholders: user.name, new_email, old_email.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} now uses {{ new_email }}. {{ old_email }} will no longer receive account mail.</p>'
            .'<p>If this was not you, reply to this mail right away.</p>',
    ],
    'email_change_requested' => [
        'title' => 'Accounts: Change of address requested, notice to the current address',
        'trigger' => 'New address entered, sent to the current address',
        'subject' => 'A new email address was entered for your account',
        'preview' => 'It only applies once the link sent to {{ new_email }} is opened.',
        'description' => 'Sent to the current address as soon as a new one is entered, before it is confirmed. Placeholders: user.name, new_email, old_email.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>{{ new_email }} was entered as the new address of your account at {{ site_name }}. It only applies once somebody opens the link sent there. Until then {{ old_email }} stays your address.</p>'
            .'<p>If this was not you, sign in, change your password and withdraw the change in your account, or reply to this mail.</p>',
    ],
    'password_changed' => [
        'title' => 'Accounts: Password changed',
        'trigger' => 'The account\'s password was changed',
        'subject' => 'Your password was changed',
        'preview' => 'The password of your account at {{ site_name }} is new.',
        'description' => 'Sent to the account\'s address once its password is changed: in the profile, in the Control Panel or through a reset link. Switched off with password_change.notify. Placeholders: user.name, user.email, changed_at.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>the password of your account at {{ site_name }} was changed on {{ changed_at }}.</p>'
            .'<p>If this was not you, reset your password through "Forgot password" right away and reply to this mail.</p>',
    ],
    'deletion_scheduled' => [
        'title' => 'Accounts: Deletion scheduled',
        'trigger' => 'Deletion of the account requested',
        'subject' => 'Your account will be deleted on {{ scheduled_for }}',
        'preview' => 'Until then you can change your mind.',
        'description' => 'Sent when a deletion is requested. Placeholders: user.name, scheduled_for, grace_days, action_url (withdraw).',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} is scheduled for deletion. On {{ scheduled_for }} we delete it.</p>'
            .'<p><strong>What we delete:</strong> your account, your access grants, your team memberships, your CRM contact with its notes and history, your notifications and preferences. Activity log entries are anonymised.</p>'
            .'<p><strong>What stays:</strong> invoices and payment records with name and address. We have to keep them for ten years (§ 147 AO, § 14b UStG). After that they are deleted.</p>'
            .'<p>Until {{ scheduled_for }} you can withdraw the request:</p>'
            .$button('Keep my account'),
    ],
    'deletion_blocked' => [
        'title' => 'Accounts: Deletion blocked',
        'trigger' => 'Deletion due, but something stands in the way',
        'subject' => 'We could not delete your account yet',
        'preview' => 'Something still stands in the way.',
        'description' => 'Sent once when a due deletion is blocked. Placeholders: user.name, reasons_list (ready-made list), action_url (withdraw).',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>you asked us to delete your account at {{ site_name }}. The grace period is over, but we cannot delete it yet:</p>'
            .'{{ reasons_list }}'
            .'<p>Once that is sorted, we delete your account on the next daily run, without you having to do anything.</p>'
            .'<p>If you would rather keep your account:</p>'
            .$button('Keep my account')
            .'<p>The button works for {{ link_days }} days. After that you can withdraw the request in your account.</p>',
    ],
    'account_deleted' => [
        'title' => 'Accounts: Account deleted',
        'trigger' => 'Grace period over, account deleted',
        'subject' => 'Your account is deleted',
        'preview' => 'The grace period has ended.',
        'description' => 'Last mail after deletion. Placeholders: user.name, user.email.',
        'body' => '<p>Hi {{ user.name }},</p>'
            .'<p>your account at {{ site_name }} is now deleted, with your access grants, team memberships, CRM contact and notifications.</p>'
            .'<p>Invoices and payment records are kept for ten years for legal reasons (§ 147 AO, § 14b UStG). This is the last mail you get from your account.</p>'
            .'<p>Thank you for having been here.</p>',
    ],
];
