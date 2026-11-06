<?php

return static function (PDO $db): void {
    $columns = [
        'sos_alert_notifications' => 'TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_sos_on_silence',
        'product_update_emails' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER sos_alert_notifications',
    ];

    $exists = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );

    foreach ($columns as $column => $definition) {
        $exists->execute(['users', $column]);
        if ((int) $exists->fetchColumn() === 0) {
            $db->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
        }
    }

    foreach ($columns as $column => $_definition) {
        $exists->execute(['users', $column]);
        if ((int) $exists->fetchColumn() !== 1) {
            throw new RuntimeException(
                "The database does not have the expected user notification preference {$column}."
            );
        }
    }
};
