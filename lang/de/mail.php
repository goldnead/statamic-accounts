<?php

/*
 * Die mitgelieferten Mails. Platzhalter in `{{ … }}` wie in email-templates:
 * `user.name`, `user.email`, `action_url`, `new_email`, `old_email`,
 * `scheduled_for`, `grace_days`, `expires_in_hours`, `site_name`.
 * Wer andere Worte will, legt in email-templates einen Eintrag mit demselben
 * Slug an (oder importiert diese mit `php please email-templates:import`).
 */

$button = fn (string $label) => '<p style="margin:24px 0;"><a href="{{ action_url }}" style="display:inline-block;background:#18181b;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:6px;">'.$label.'</a></p>';

return [
    'placeholders' => [
        'user_name' => 'Name (sonst die Adresse)',
        'user_email' => 'E-Mail-Adresse des Kontos',
        'site_name' => 'Name der Website',
        'action_url' => 'Signierter Link (bestätigen oder zurückziehen)',
        'expires_in_hours' => 'Gültigkeit des Links in Stunden',
        'new_email' => 'Neue Adresse',
        'old_email' => 'Bisherige Adresse',
        'scheduled_for' => 'Datum der Löschung',
        'grace_days' => 'Frist in Tagen',
        'reasons_list' => 'Was der Löschung im Weg steht (fertige Liste)',
    ],
    'verify_email' => [
        'title' => 'Konten: E-Mail-Adresse bestätigen',
        'trigger' => 'Konto wird angelegt oder Bestätigungslink erneut angefordert',
        'subject' => 'Bitte bestätige deine E-Mail-Adresse',
        'preview' => 'Ein Klick, dann ist dein Konto bei {{ site_name }} bereit.',
        'description' => 'Geht nach der Registrierung und bei „Erneut senden“ raus. Platzhalter: user.name, user.email, action_url, expires_in_hours.',
        'body' => '<p>Hallo {{ user.name }},</p>'
            .'<p>bitte bestätige, dass {{ user.email }} deine Adresse ist.</p>'
            .$button('Adresse bestätigen')
            .'<p>Der Link gilt {{ expires_in_hours }} Stunden. Wenn du kein Konto bei {{ site_name }} angelegt hast, kannst du diese Mail ignorieren.</p>',
    ],
    'confirm_email_change' => [
        'title' => 'Konten: Neue E-Mail-Adresse bestätigen',
        'trigger' => 'Neue Adresse eingetragen, geht an die neue Adresse',
        'subject' => 'Bestätige deine neue E-Mail-Adresse',
        'preview' => 'Deine neue Adresse gilt erst nach diesem Klick.',
        'description' => 'Geht an die neue Adresse. Platzhalter: user.name, new_email, old_email, action_url, expires_in_hours.',
        'body' => '<p>Hallo {{ user.name }},</p>'
            .'<p>für dein Konto bei {{ site_name }} soll künftig {{ new_email }} gelten statt {{ old_email }}.</p>'
            .$button('Neue Adresse bestätigen')
            .'<p>Bis du den Link öffnest, bleibt alles, wie es ist. Der Link gilt {{ expires_in_hours }} Stunden.</p>',
    ],
    'email_changed' => [
        'title' => 'Konten: Hinweis an die alte Adresse',
        'trigger' => 'Neue Adresse bestätigt, geht an die alte Adresse',
        'subject' => 'Deine E-Mail-Adresse wurde geändert',
        'preview' => 'Dein Konto läuft jetzt über {{ new_email }}.',
        'description' => 'Geht an die alte Adresse, nachdem die neue bestätigt ist. Platzhalter: user.name, new_email, old_email.',
        'body' => '<p>Hallo {{ user.name }},</p>'
            .'<p>dein Konto bei {{ site_name }} läuft ab jetzt über {{ new_email }}. An {{ old_email }} kommen keine Kontomails mehr.</p>'
            .'<p>Warst du das nicht, antworte bitte sofort auf diese Mail.</p>',
    ],
    'deletion_scheduled' => [
        'title' => 'Konten: Löschung vorgemerkt',
        'trigger' => 'Löschung des Kontos beantragt',
        'subject' => 'Dein Konto wird am {{ scheduled_for }} gelöscht',
        'preview' => 'Bis dahin kannst du es dir anders überlegen.',
        'description' => 'Geht raus, sobald eine Löschung beantragt ist. Platzhalter: user.name, scheduled_for, grace_days, action_url (zurückziehen).',
        'body' => '<p>Hallo {{ user.name }},</p>'
            .'<p>dein Konto bei {{ site_name }} ist zur Löschung vorgemerkt. Am {{ scheduled_for }} löschen wir es.</p>'
            .'<p><strong>Was wir löschen:</strong> dein Konto, deine Zugänge, deine Mitgliedschaften in Teams, deinen Kontakt im CRM mit Notizen und Verlauf, deine Benachrichtigungen und Einstellungen. Einträge im Protokoll werden anonymisiert.</p>'
            .'<p><strong>Was bleibt:</strong> Rechnungen und Zahlungsbelege mit Name und Adresse. Die müssen wir zehn Jahre aufbewahren (§ 147 AO, § 14b UStG). Danach werden sie gelöscht.</p>'
            .'<p>Bis zum {{ scheduled_for }} kannst du den Antrag zurückziehen:</p>'
            .$button('Konto behalten'),
    ],
    'deletion_blocked' => [
        'title' => 'Konten: Löschung blockiert',
        'trigger' => 'Löschung fällig, aber etwas steht im Weg',
        'subject' => 'Wir konnten dein Konto noch nicht löschen',
        'preview' => 'Es steht noch etwas im Weg.',
        'description' => 'Geht einmal raus, wenn eine fällige Löschung blockiert ist. Platzhalter: user.name, reasons_list (fertige Liste), action_url (zurückziehen).',
        'body' => '<p>Hallo {{ user.name }},</p>'
            .'<p>du hast die Löschung deines Kontos bei {{ site_name }} beantragt. Die Frist ist um, aber wir können es noch nicht löschen:</p>'
            .'{{ reasons_list }}'
            .'<p>Sobald das erledigt ist, löschen wir dein Konto beim nächsten täglichen Lauf, ohne dass du noch etwas tun musst.</p>'
            .'<p>Wenn du dein Konto doch behalten willst:</p>'
            .$button('Konto behalten'),
    ],
    'account_deleted' => [
        'title' => 'Konten: Konto gelöscht',
        'trigger' => 'Frist abgelaufen, Konto gelöscht',
        'subject' => 'Dein Konto ist gelöscht',
        'preview' => 'Die Frist ist abgelaufen.',
        'description' => 'Letzte Mail nach der Löschung. Platzhalter: user.name, user.email.',
        'body' => '<p>Hallo {{ user.name }},</p>'
            .'<p>dein Konto bei {{ site_name }} ist jetzt gelöscht, mit deinen Zugängen, Team-Mitgliedschaften, deinem CRM-Kontakt und deinen Benachrichtigungen.</p>'
            .'<p>Rechnungen und Zahlungsbelege bewahren wir aus gesetzlichen Gründen zehn Jahre auf (§ 147 AO, § 14b UStG). Diese Mail ist die letzte, die du von deinem Konto bekommst.</p>'
            .'<p>Danke, dass du da warst.</p>',
    ],
];
