<?php

return [
    'verification_sent' => ['label' => 'Confirmation link sent', 'description' => 'A link to confirm the address went to the account.'],
    'email_verified' => ['label' => 'Email confirmed', 'description' => 'The customer opened the confirmation link, or an admin marked the address as confirmed.'],
    'email_change_requested' => ['label' => 'Address change requested', 'description' => 'A new address was entered. It becomes active once its link is opened.'],
    'email_changed' => ['label' => 'Address changed', 'description' => 'The new address was confirmed and replaced the old one.'],
    'password_changed' => ['label' => 'Password changed', 'description' => 'The account\'s password was changed, whichever way. The payload carries no password.'],
    'deletion_requested' => ['label' => 'Deletion scheduled', 'description' => 'The account will be deleted after the grace period unless the request is withdrawn.'],
    'deletion_blocked' => ['label' => 'Deletion blocked', 'description' => 'The deletion was due, but something stands in the way (a running subscription, a team with members). The person gets a mail with the reasons.'],
    'deletion_cancelled' => ['label' => 'Deletion withdrawn', 'description' => 'A scheduled or blocked deletion was withdrawn, before or after the grace period ended.'],
    'deleted' => ['label' => 'Account deleted', 'description' => 'The grace period ended and the account was deleted.'],
    'data_exported' => ['label' => 'Personal data exported', 'description' => 'A copy of the account\'s personal data was downloaded.'],
];
