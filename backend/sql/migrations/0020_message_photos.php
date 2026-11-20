<?php

return static function (PDO $db): void {
    foreach (['messages', 'persistent_group_messages'] as $table) {
        $columns = [
            'attachment_path' => 'VARCHAR(255) NULL',
            'trip_label' => 'VARCHAR(120) NULL',
        ];
        foreach ($columns as $column => $definition) {
            $columnStmt = $db->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ?
                   AND COLUMN_NAME = ?'
            );
            $columnStmt->execute([$table, $column]);
            if ((int) $columnStmt->fetchColumn() === 0) {
                $db->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
            }
        }
    }
};
