<?php

return static function (PDO $db): void {
    $db->exec(
        'ALTER TABLE persistent_group_members
         ADD COLUMN sharing_location TINYINT(1) NOT NULL DEFAULT 0,
         ADD COLUMN last_lat DECIMAL(10, 7) NULL,
         ADD COLUMN last_lng DECIMAL(10, 7) NULL,
         ADD COLUMN location_updated_at DATETIME NULL,
         ADD INDEX idx_persistent_group_locations (group_id, sharing_location, location_updated_at)'
    );
    $db->exec(
        'ALTER TABLE persistent_group_invites
         ADD COLUMN invited_user_id INT UNSIGNED NULL AFTER invited_email,
         ADD CONSTRAINT fk_persistent_group_invited_user
             FOREIGN KEY (invited_user_id) REFERENCES users(id) ON DELETE CASCADE,
         ADD INDEX idx_persistent_group_invited_user (invited_user_id, accepted_at)'
    );
};
