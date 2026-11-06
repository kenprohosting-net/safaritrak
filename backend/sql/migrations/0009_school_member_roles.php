<?php

return static function (PDO $db): void {
    $columnStmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = "organization_travelers"
           AND COLUMN_NAME = "member_type"'
    );
    $columnStmt->execute();
    if ((int) $columnStmt->fetchColumn() === 0) {
        $db->exec(
            'ALTER TABLE organization_travelers
             ADD COLUMN member_type ENUM("student", "guardian", "staff") NOT NULL DEFAULT "staff"
             AFTER status'
        );
    }

    $db->exec(
        'UPDATE organization_travelers ot
         JOIN organization_membership_requests r
           ON r.organization_id = ot.organization_id
          AND r.user_id = ot.user_id
          AND r.status = "approved"
         SET ot.member_type = r.member_type'
    );
};
