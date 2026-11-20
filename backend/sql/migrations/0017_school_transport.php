<?php

return static function (PDO $db): void {
    $db->exec(
        'ALTER TABLE notifications
         MODIFY type ENUM(
            "journey_started", "journey_completed", "arrival", "route_deviation",
            "new_message", "location_share", "sos_alert", "contact_request",
            "group_invite", "welcome", "school_trip_access", "school_transport_broadcast"
         ) NOT NULL'
    );

    $statements = [
        'CREATE TABLE IF NOT EXISTS school_transport_profiles (
            school_id INT UNSIGNED NOT NULL PRIMARY KEY,
            origin_name VARCHAR(120) NOT NULL,
            origin_lat DECIMAL(10, 7) NOT NULL,
            origin_lng DECIMAL(10, 7) NOT NULL,
            updated_by INT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS school_buses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            school_id INT UNSIGNED NOT NULL,
            bus_number VARCHAR(32) NOT NULL,
            route_name VARCHAR(120) NOT NULL,
            driver_user_id INT UNSIGNED NULL,
            driver_name VARCHAR(120) NOT NULL,
            driver_phone VARCHAR(20) NULL,
            status ENUM("active", "inactive") NOT NULL DEFAULT "active",
            created_by INT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_school_bus_number (school_id, bus_number),
            FOREIGN KEY (school_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (driver_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_school_buses_school_status (school_id, status, id)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS school_bus_stops (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bus_id BIGINT UNSIGNED NOT NULL,
            stop_name VARCHAR(120) NOT NULL,
            lat DECIMAL(10, 7) NOT NULL,
            lng DECIMAL(10, 7) NOT NULL,
            stop_order SMALLINT UNSIGNED NOT NULL,
            stop_type ENUM("pickup", "dropoff", "waypoint") NOT NULL DEFAULT "waypoint",
            FOREIGN KEY (bus_id) REFERENCES school_buses(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_school_bus_stop_order (bus_id, stop_order),
            INDEX idx_school_bus_stops_route (bus_id, stop_order)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS school_verifications (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            school_id INT UNSIGNED NOT NULL,
            role ENUM("school_admin", "verified_parent") NOT NULL,
            student_user_id INT UNSIGNED NULL,
            student_name VARCHAR(120) NULL,
            student_grade VARCHAR(40) NULL,
            assigned_bus_id BIGINT UNSIGNED NULL,
            home_dropoff_label VARCHAR(120) NULL,
            home_dropoff_lat DECIMAL(10, 7) NULL,
            home_dropoff_lng DECIMAL(10, 7) NULL,
            route_service_consent TINYINT(1) NOT NULL DEFAULT 0,
            route_service_consent_at DATETIME NULL,
            verification_status ENUM("pending", "verified", "revoked") NOT NULL DEFAULT "pending",
            verified_by INT UNSIGNED NULL,
            verified_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_school_verification_subject (user_id, school_id, student_user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (school_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_bus_id) REFERENCES school_buses(id) ON DELETE SET NULL,
            FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_school_verification_scope (school_id, role, verification_status),
            INDEX idx_school_verification_bus (assigned_bus_id, verification_status)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS school_bus_trips (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bus_id BIGINT UNSIGNED NOT NULL,
            started_by INT UNSIGNED NOT NULL,
            status ENUM("en_route", "arrived", "cancelled") NOT NULL DEFAULT "en_route",
            broadcast_active TINYINT(1) NOT NULL DEFAULT 0,
            driver_note VARCHAR(500) NULL,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            broadcast_started_at DATETIME NULL,
            completed_at DATETIME NULL,
            FOREIGN KEY (bus_id) REFERENCES school_buses(id) ON DELETE CASCADE,
            FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_school_bus_trips_latest (bus_id, started_at, id),
            INDEX idx_school_bus_trips_broadcast (bus_id, status, broadcast_active)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS school_bus_positions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trip_id BIGINT UNSIGNED NOT NULL,
            lat DECIMAL(10, 7) NOT NULL,
            lng DECIMAL(10, 7) NOT NULL,
            speed_kmh DECIMAL(6, 2) NULL,
            accuracy_meters DECIMAL(7, 2) NULL,
            recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES school_bus_trips(id) ON DELETE CASCADE,
            INDEX idx_school_bus_positions_latest (trip_id, recorded_at, id)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS school_student_trip_status (
            trip_id BIGINT UNSIGNED NOT NULL,
            verification_id BIGINT UNSIGNED NOT NULL,
            status ENUM("on_board", "approaching", "dropped_off") NOT NULL DEFAULT "on_board",
            status_note VARCHAR(300) NULL,
            updated_by INT UNSIGNED NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (trip_id, verification_id),
            FOREIGN KEY (trip_id) REFERENCES school_bus_trips(id) ON DELETE CASCADE,
            FOREIGN KEY (verification_id) REFERENCES school_verifications(id) ON DELETE CASCADE,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB',
    ];

    foreach ($statements as $statement) {
        $db->exec($statement);
    }

    $db->exec(
        'INSERT IGNORE INTO school_verifications
            (user_id, school_id, role, verification_status, verified_at)
         SELECT oa.user_id, oa.organization_id, "school_admin", "verified", UTC_TIMESTAMP()
         FROM organization_admins oa
         JOIN organizations o ON o.id = oa.organization_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id
           AND p.account_type = "school" AND p.review_status = "approved"'
    );
    $db->exec(
        'INSERT IGNORE INTO school_verifications
            (user_id, school_id, role, verification_status, verified_at)
         SELECT ot.user_id, ot.organization_id, "school_admin", "verified", UTC_TIMESTAMP()
         FROM organization_travelers ot
         JOIN organizations o ON o.id = ot.organization_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id
           AND p.account_type = "school" AND p.review_status = "approved"
         WHERE ot.status = "active" AND ot.member_type = "staff"'
    );
    $db->exec(
        'INSERT IGNORE INTO school_verifications
            (user_id, school_id, role, student_user_id, student_name, verification_status, verified_at)
         SELECT ca.guardian_user_id, ca.organization_id, "verified_parent",
                ca.child_user_id, child.full_name, "verified", UTC_TIMESTAMP()
         FROM organization_child_access ca
         JOIN organizations o ON o.id = ca.organization_id AND o.is_suspended = 0
         JOIN organization_profiles p ON p.organization_id = o.id
           AND p.account_type = "school" AND p.review_status = "approved"
         JOIN organization_travelers student
           ON student.organization_id = ca.organization_id
          AND student.user_id = ca.child_user_id
          AND student.status = "active" AND student.member_type = "student"
         JOIN organization_travelers guardian
           ON guardian.organization_id = ca.organization_id
          AND guardian.user_id = ca.guardian_user_id
          AND guardian.status = "active" AND guardian.member_type = "guardian"
         JOIN users child ON child.id = ca.child_user_id
         WHERE ca.status = "active"'
    );
};
