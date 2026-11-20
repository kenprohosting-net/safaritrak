<?php

return static function (PDO $db): void {
    foreach ([
        'meeting_point_label' => 'VARCHAR(160) NULL',
        'meeting_point_lat' => 'DECIMAL(10, 7) NULL',
        'meeting_point_lng' => 'DECIMAL(10, 7) NULL',
    ] as $column => $definition) {
        $columnStmt = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "group_journeys" AND COLUMN_NAME = ?'
        );
        $columnStmt->execute([$column]);
        if ((int) $columnStmt->fetchColumn() === 0) {
            $db->exec('ALTER TABLE group_journeys ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    $db->exec(
        'CREATE TABLE IF NOT EXISTS group_member_positions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_journey_id INT UNSIGNED NOT NULL,
            group_member_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            lat DECIMAL(10, 7) NOT NULL,
            lng DECIMAL(10, 7) NOT NULL,
            speed_kmh DECIMAL(6, 2) NULL,
            heading DECIMAL(6, 2) NULL,
            accuracy DECIMAL(7, 2) NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_group_member_position (group_journey_id, group_member_id),
            INDEX idx_group_member_position_user (user_id),
            FOREIGN KEY (group_journey_id) REFERENCES group_journeys(id) ON DELETE CASCADE,
            FOREIGN KEY (group_member_id) REFERENCES group_members(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS group_member_route_points (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_journey_id INT UNSIGNED NOT NULL,
            group_member_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            lat DECIMAL(10, 7) NOT NULL,
            lng DECIMAL(10, 7) NOT NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_journey_id) REFERENCES group_journeys(id) ON DELETE CASCADE,
            FOREIGN KEY (group_member_id) REFERENCES group_members(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_group_member_route (group_journey_id, group_member_id, recorded_at, id)
        ) ENGINE=InnoDB'
    );

    $db->exec(
        'INSERT INTO group_member_positions
            (group_journey_id, group_member_id, user_id, lat, lng, updated_at)
         SELECT gm.group_journey_id, gm.id, gm.user_id, gm.last_lat, gm.last_lng, NOW()
         FROM group_members gm
         JOIN group_journeys gj ON gj.id = gm.group_journey_id
         WHERE gm.status = "confirmed" AND gm.last_lat IS NOT NULL AND gm.last_lng IS NOT NULL
           AND gj.status = "active"
         ON DUPLICATE KEY UPDATE lat = VALUES(lat), lng = VALUES(lng), updated_at = VALUES(updated_at)'
    );
};
