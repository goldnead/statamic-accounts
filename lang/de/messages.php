<?php

return [
    'addon_name' => 'Konten',
    'nav' => 'Konten',
    'nav_customers' => 'Kunden',
    'nav_wiring' => 'Verdrahtung',
    'customer_overview' => 'Kundenansicht',

    'permission_group' => 'Konten',
    'permission_view' => 'Kunden ansehen',
    'permission_manage' => 'Bestätigung und Löschung verwalten',
    'permission_export' => 'Personenbezogene Daten exportieren',
    'permission_settings' => 'Konten-Einstellungen verwalten',

    'column_verified' => 'E-Mail bestätigt',
    'column_deletion' => 'Löschung am',

    'section_account' => 'Konto',
    'section_payments' => 'Zahlungen',
    'section_entitlements' => 'Zugänge',
    'section_leadhub' => 'CRM-Kontakt',
    'section_notifications' => 'Benachrichtigungen',
    'section_teams' => 'Teams',

    'email_invalid' => 'Das ist keine gültige E-Mail-Adresse.',
    'email_unchanged' => 'Das ist schon deine Adresse.',
    'email_taken' => 'Ein anderes Konto nutzt diese Adresse bereits.',
    'link_invalid' => 'Dieser Link gilt nicht mehr. Fordere einen neuen an.',

    'verification_sent' => 'Wir haben dir einen Link geschickt, mit dem du deine Adresse bestätigst.',
    'verification_required' => 'Bitte bestätige zuerst deine E-Mail-Adresse.',
    'already_verified' => 'Diese Adresse ist schon bestätigt.',
    'email_verified' => 'Deine E-Mail-Adresse ist bestätigt.',
    'email_change_sent' => 'Wir haben einen Link an deine neue Adresse geschickt. Sie gilt, sobald du ihn öffnest.',
    'email_change_cancelled' => 'Die Adressänderung ist zurückgezogen.',
    'email_changed' => 'Deine E-Mail-Adresse wurde geändert.',
    'deletion_scheduled' => 'Dein Konto ist zur Löschung vorgemerkt.',
    'deletion_cancelled' => 'Die Löschung ist zurückgezogen. Dein Konto bleibt.',
    'impersonate_denied' => 'Du darfst dich nicht als diese Person anmelden.',
    'impersonation_locked' => 'Während eines Identitätswechsels lassen sich Adresse, Löschung und Datenexport nicht ändern. Das entscheidet die Person selbst.',

    'elevation_unavailable' => 'Diese Aktion braucht eine Bestätigung, aber die Bestätigungsseite ist nicht eingerichtet. Bitte melde dich beim Betreiber.',
    'section_invoices' => 'Rechnungen',
    'section_activity' => 'Protokoll',

    'blocker_subscription' => 'Dein Abo „:product“ läuft noch. Kündige es zuerst, dann kannst du dein Konto löschen.',
    'blocker_subscription_portal' => 'Dein Abo „:product“ läuft noch. Kündige es zuerst im Kundenportal (:url), dann kannst du dein Konto löschen.',
    'blocker_subscription_cancel_failed' => 'Dein Abo „:product“ ließ sich nicht automatisch kündigen. Kündige es bitte selbst oder schreib uns.',
    'blocker_subscription_admin' => 'Das Konto hat ein laufendes Abo „:product“. Es muss erst gekündigt werden.',
    'blocker_subscription_portal_admin' => 'Das Konto hat ein laufendes Abo „:product“. Die Person kann es im Kundenportal kündigen: :url',
    'blocker_subscription_cancel_failed_admin' => 'Das Abo „:product“ ließ sich nicht automatisch kündigen.',
    'blocker_team_owner_admin' => 'Das Konto hält „:team“ allein, und das Team hat :count weitere Mitglieder. Die Inhaberschaft muss erst übertragen werden.',
    'blocker_team_owner' => 'Du bist die einzige Inhaberin oder der einzige Inhaber von „:team“ mit :count weiteren Mitgliedern. Übertrage die Inhaberschaft zuerst.',

    'retained_payments' => 'Zahlungen und Abos bleiben mit Name und Adresse zehn Jahre gespeichert (§ 147 AO, § 14b UStG).',
    'retained_invoices' => 'Rechnungen bleiben mit Name und Adresse zehn Jahre gespeichert (§ 147 AO, § 14b UStG).',
    'activity_note' => 'Einträge im Protokoll unter dieser Nutzer-ID sind anonymisiert. Einträge anderer Personen (etwa eines Admins), die diese Person erwähnen, erreicht die Anonymisierung von Activity nicht.',

    'purged' => '{0} Kein Konto war zur Löschung fällig.|{1} :count Konto gelöscht.|[2,*] :count Konten gelöscht.',
];
