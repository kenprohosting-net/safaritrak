<?php

return static function (PDO $db): void {
    $indexes = [
        'messages' => [
            'idx_messages_unread_receiver' => 'receiver_id, read_at, sender_id',
        ],
        'notifications' => [
            'idx_notifications_unread' => 'user_id, is_read',
        ],
    ];

    $indexExists = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND INDEX_NAME = ?'
    );

    foreach ($indexes as $table => $tableIndexes) {
        foreach ($tableIndexes as $name => $columns) {
            $indexExists->execute([$table, $name]);
            if ((int) $indexExists->fetchColumn() === 0) {
                $db->exec(
                    sprintf('ALTER TABLE `%s` ADD INDEX `%s` (%s)', $table, $name, $columns)
                );
            }
        }
    }
};
