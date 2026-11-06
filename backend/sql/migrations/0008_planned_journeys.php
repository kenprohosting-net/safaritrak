<?php

return static function (PDO $db): void {
    $columnStmt = $db->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "journeys" AND COLUMN_NAME = "status"'
    );
    $columnStmt->execute();
    $columnType = $columnStmt->fetchColumn();
    if (!is_string($columnType)) {
        throw new RuntimeException('The journeys.status column is missing; planned trips cannot be enabled.');
    }
    if (str_contains($columnType, "'planned'")) {
        return;
    }
    foreach (["'active'", "'completed'", "'cancelled'"] as $existingValue) {
        if (!str_contains($columnType, $existingValue)) {
            throw new RuntimeException('The journeys.status values differ from the expected baseline.');
        }
    }

    $db->exec(
        "ALTER TABLE journeys
         MODIFY status ENUM('planned', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'active'"
    );
};
