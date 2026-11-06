<?php

return static function (PDO $db): void {
    $columnExists = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $columnExists->execute(['journeys', 'covered_km']);
    if ((int) $columnExists->fetchColumn() === 0) {
        $db->exec(
            'ALTER TABLE journeys
             ADD COLUMN covered_km DECIMAL(7, 2) NOT NULL DEFAULT 0'
        );
    }

    $tableExists = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $tableExists->execute(['journey_locations']);
    if ((int) $tableExists->fetchColumn() === 0) {
        $db->exec(
            'CREATE TABLE journey_locations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                journey_id INT UNSIGNED NOT NULL,
                latitude DECIMAL(10, 7) NOT NULL,
                longitude DECIMAL(10, 7) NOT NULL,
                recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
                INDEX idx_journey_locations_journey (journey_id, recorded_at)
            ) ENGINE=InnoDB'
        );
    }

    foreach (['id', 'journey_id', 'latitude', 'longitude', 'recorded_at'] as $column) {
        $columnExists->execute(['journey_locations', $column]);
        if ((int) $columnExists->fetchColumn() !== 1) {
            throw new RuntimeException(
                'The existing journey_locations table does not match the expected location history structure.'
            );
        }
    }
};
