<?php

return [
    'verification_sent' => ['label' => 'Bestätigungslink verschickt', 'description' => 'Ein Link zum Bestätigen der Adresse ging an das Konto.'],
    'email_verified' => ['label' => 'E-Mail bestätigt', 'description' => 'Der Link wurde geöffnet, oder jemand im CP hat die Adresse als bestätigt markiert.'],
    'email_change_requested' => ['label' => 'Adressänderung angefragt', 'description' => 'Eine neue Adresse wurde eingetragen. Sie gilt, sobald ihr Link geöffnet ist.'],
    'email_changed' => ['label' => 'Adresse geändert', 'description' => 'Die neue Adresse ist bestätigt und ersetzt die alte.'],
    'deletion_requested' => ['label' => 'Löschung vorgemerkt', 'description' => 'Das Konto wird nach der Frist gelöscht, wenn der Antrag nicht zurückgezogen wird.'],
    'deletion_blocked' => ['label' => 'Löschung blockiert', 'description' => 'Die Löschung war fällig, aber etwas steht im Weg (laufendes Abo, Team mit Mitgliedern). Die Person bekommt eine Mail mit den Gründen.'],
    'deletion_cancelled' => ['label' => 'Löschung zurückgezogen', 'description' => 'Eine vorgemerkte oder blockierte Löschung wurde zurückgezogen, vor oder nach Ablauf der Frist.'],
    'deleted' => ['label' => 'Konto gelöscht', 'description' => 'Die Frist ist abgelaufen, das Konto ist gelöscht.'],
    'data_exported' => ['label' => 'Daten exportiert', 'description' => 'Eine Kopie der personenbezogenen Daten wurde heruntergeladen.'],
];
