<?php

function st_current_consent_versions(): array {
    return [
        'terms' => '2026-08-04',
        'privacy' => '2026-10-15',
    ];
}

function st_record_consent(PDO $database, int $userId): void {
    $insert = $database->prepare(
        'INSERT INTO privacy_consents (user_id, consent_type, document_version)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE consent_type = VALUES(consent_type)'
    );

    foreach (st_current_consent_versions() as $type => $version) {
        $insert->execute([$userId, $type, $version]);
    }
}
