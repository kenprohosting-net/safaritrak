<?php

return static function (PDO $db): void {
    $columnStmt = $db->prepare(
        'SELECT COUNT(*)
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "rental_vehicles"
           AND COLUMN_NAME = ?'
    );
    foreach ([
        'pickup_lat' => 'DECIMAL(10, 7) NULL AFTER description',
        'pickup_lng' => 'DECIMAL(10, 7) NULL AFTER pickup_lat',
    ] as $column => $definition) {
        $columnStmt->execute([$column]);
        if ((int) $columnStmt->fetchColumn() === 0) {
            $db->exec('ALTER TABLE rental_vehicles ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
};
