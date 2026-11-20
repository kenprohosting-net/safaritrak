<?php

return static function (PDO $db): void {
    $db->exec(
        'CREATE TABLE IF NOT EXISTS saved_locations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(160) NOT NULL,
            category VARCHAR(40) NOT NULL DEFAULT "other",
            address VARCHAR(500) NULL,
            lat DECIMAL(10, 7) NOT NULL,
            lng DECIMAL(10, 7) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_saved_locations_user (user_id, created_at)
        ) ENGINE=InnoDB'
    );
};
