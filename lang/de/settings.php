<?php

return [
    'groups' => [
        'verification' => [
            'title' => 'E-Mail-Adresse',
            'description' => 'Die Adresse nach der Registrierung und bei einer Änderung bestätigen lassen.',
        ],
        'deletion' => [
            'title' => 'Löschen und Export',
            'description' => 'Wie lange eine Löschung zurückgezogen werden kann, und ob Kunden ihre Daten herunterladen dürfen.',
        ],
        'mail' => [
            'title' => 'Mail-Vorlagen',
            'description' => 'Slugs in Email Templates. Ein Slug ohne Eintrag verschickt den Text, den das Addon mitbringt.',
        ],
    ],
    'options' => [
        'active_subscriptions' => ['block' => 'Löschung blockieren', 'cancel' => 'Abos kündigen'],
    ],
    'fields' => [
        'verification_enabled' => ['label' => 'Bestätigung verlangen', 'description' => 'Aus: die Middleware accounts.verified lässt alle durch, kein Hinweis erscheint.'],
        'verification_send_on_register' => ['label' => 'Nach der Registrierung senden', 'description' => 'Schickt den Link direkt nach dem Registrierungsformular.'],
        'verification_expire_minutes' => ['label' => 'Bestätigungslink gilt (Minuten)', 'description' => 'Danach wird der Link abgelehnt und ein neuer muss angefordert werden.'],
        'verification_notice_url' => ['label' => 'Seite für unbestätigte Konten', 'description' => 'Wohin die Middleware accounts.verified angemeldete Nutzer ohne bestätigte Adresse schickt.'],
        'email_change_expire_minutes' => ['label' => 'Änderungslink gilt (Minuten)', 'description' => 'Wie lange der Link an eine neue Adresse funktioniert.'],
        'email_change_notify_old_address' => ['label' => 'Alte Adresse informieren', 'description' => 'Schickt einen Hinweis an die bisherige Adresse, sobald die neue bestätigt ist.'],
        'deletion_grace_days' => ['label' => 'Frist (Tage)', 'description' => 'So viele Tage nach dem Antrag wird das Konto gelöscht. Mindestens 1, damit der Link zum Zurückziehen geöffnet werden kann.'],
        'deletion_active_subscriptions' => ['label' => 'Laufende Abos', 'description' => 'Blockieren: die Löschung wird abgelehnt, mit Link zum Kundenportal. Kündigen: laufende Abos werden beim Antrag über Payments gekündigt.'],
        'deletion_portal_url' => ['label' => 'Adresse des Kundenportals', 'description' => 'Wohin der Hinweis bei einem laufenden Abo verlinkt. Leer: das Kundenportal von Payments.'],
        'deletion_logout' => ['label' => 'Nach dem Antrag abmelden', 'description' => 'Aus: die Person bleibt angemeldet und kann den Antrag im Konto zurückziehen.'],
        'export_enabled' => ['label' => 'Kunden dürfen ihre Daten herunterladen', 'description' => 'Aus: der Export-Link antwortet mit 404. Der Export im Control Panel bleibt.'],
        'mail_templates_verify_email' => ['label' => 'Adresse bestätigen', 'description' => 'Slug der Bestätigungsmail.'],
        'mail_templates_confirm_email_change' => ['label' => 'Neue Adresse bestätigen', 'description' => 'Slug der Mail an die neue Adresse.'],
        'mail_templates_email_changed' => ['label' => 'Adresse geändert', 'description' => 'Slug des Hinweises an die alte Adresse.'],
        'mail_templates_deletion_scheduled' => ['label' => 'Löschung vorgemerkt', 'description' => 'Slug der Mail mit dem Link zum Zurückziehen.'],
        'mail_templates_account_deleted' => ['label' => 'Konto gelöscht', 'description' => 'Slug der letzten Mail.'],
    ],
];
