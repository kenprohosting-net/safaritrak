<?php

return static function (PDO $db): void {
    $columnStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "persistent_group_members"
           AND COLUMN_NAME = "last_read_message_id"'
    );
    $columnStmt->execute();
    if ((int) $columnStmt->fetchColumn() === 0) {
        $db->exec(
            'ALTER TABLE persistent_group_members
             ADD COLUMN last_read_message_id BIGINT UNSIGNED NULL AFTER joined_at'
        );
    }
};
