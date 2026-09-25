<?php

return [
    'addon_name' => 'Accounts',
    'nav' => 'Accounts',
    'nav_customers' => 'Customers',
    'nav_wiring' => 'Wiring',
    'customer_overview' => 'Customer overview',

    'permission_group' => 'Accounts',
    'permission_view' => 'View customers',
    'permission_manage' => 'Manage verification and deletion',
    'permission_export' => 'Export personal data',
    'permission_settings' => 'Manage accounts settings',

    'column_verified' => 'Email confirmed',
    'column_deletion' => 'Deletion on',

    'section_account' => 'Account',
    'section_payments' => 'Payments',
    'section_entitlements' => 'Access',
    'section_leadhub' => 'CRM contact',
    'section_notifications' => 'Notifications',
    'section_teams' => 'Teams',

    'email_invalid' => 'That is not a valid email address.',
    'email_unchanged' => 'That is already your address.',
    'email_taken' => 'Another account already uses this address.',
    'link_invalid' => 'This link is no longer valid. Request a new one.',

    'verification_sent' => 'We sent you a link to confirm your address.',
    'verification_required' => 'Please confirm your email address first.',
    'already_verified' => 'This address is already confirmed.',
    'email_verified' => 'Your email address is confirmed.',
    'email_change_sent' => 'We sent a link to your new address. The change takes effect once you open it.',
    'email_change_cancelled' => 'The change of address was withdrawn.',
    'email_changed' => 'Your email address was changed.',
    'deletion_scheduled' => 'Your account is scheduled for deletion.',
    'deletion_cancelled' => 'The deletion was withdrawn. Your account stays.',
    'impersonate_denied' => 'You may not sign in as this user.',
    'impersonation_locked' => 'While impersonating, the address, the deletion and the data export cannot be changed. That is the person\'s own decision.',

    'section_invoices' => 'Invoices',
    'section_activity' => 'Activity log',

    'blocker_subscription' => 'Your subscription ":product" is still running. Cancel it first, then you can delete your account.',
    'blocker_subscription_portal' => 'Your subscription ":product" is still running. Cancel it first in the customer portal (:url), then you can delete your account.',
    'blocker_subscription_cancel_failed' => 'Your subscription ":product" could not be cancelled automatically. Please cancel it yourself or write to us.',
    'blocker_team_owner' => 'You are the only owner of ":team", which has :count other members. Transfer the ownership first.',

    'retained_payments' => 'Payments and subscriptions are kept with name and address for ten years (§ 147 AO, § 14b UStG).',
    'retained_invoices' => 'Invoices are kept with name and address for ten years (§ 147 AO, § 14b UStG).',
    'activity_note' => 'Log entries under this user id are anonymised. Entries of other people (an admin, say) that mention this person are beyond the reach of Activity\'s anonymisation.',

    'purged' => '{0} No account was due for deletion.|{1} Deleted :count account.|[2,*] Deleted :count accounts.',
];
