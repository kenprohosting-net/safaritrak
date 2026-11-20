<?php

return static function (PDO $db): void {
    $db->exec(
        'ALTER TABLE notifications
         MODIFY type ENUM(
            "journey_started", "journey_completed", "arrival", "route_deviation",
            "new_message", "location_share", "sos_alert", "contact_request",
            "group_invite", "welcome", "school_trip_access"
         ) NOT NULL'
    );
    $db->exec(
        'CREATE TABLE IF NOT EXISTS organization_child_access (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id INT UNSIGNED NOT NULL,
            child_user_id INT UNSIGNED NOT NULL,
            guardian_user_id INT UNSIGNED NOT NULL,
            invited_by INT UNSIGNED NOT NULL,
            status ENUM("pending", "active", "revoked") NOT NULL DEFAULT "pending",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            responded_at DATETIME NULL,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (child_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (guardian_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_organization_child_guardian (organization_id, child_user_id, guardian_user_id),
            INDEX idx_child_access_guardian (guardian_user_id, status, created_at)
        ) ENGINE=InnoDB'
    );
};
