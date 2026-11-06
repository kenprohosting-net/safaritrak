<?php

return static function (PDO $db): void {
    $statements = [
        'CREATE TABLE IF NOT EXISTS organization_profiles (
            organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
            account_type ENUM("school", "car_hire") NOT NULL DEFAULT "school",
            review_status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "approved",
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS organization_invites (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id INT UNSIGNED NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            invited_email VARCHAR(160) NULL,
            member_type ENUM("student", "guardian", "staff") NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            accepted_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_organization_invite_token (token_hash),
            INDEX idx_organization_invites_org (organization_id, expires_at)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS organization_membership_requests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            invite_id BIGINT UNSIGNED NULL,
            member_type ENUM("student", "guardian", "staff") NOT NULL,
            status ENUM("pending", "approved", "rejected") NOT NULL DEFAULT "pending",
            reviewed_by INT UNSIGNED NULL,
            reviewed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (invite_id) REFERENCES organization_invites(id) ON DELETE SET NULL,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_organization_membership_request (organization_id, user_id),
            INDEX idx_organization_membership_requests (organization_id, status, created_at)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS persistent_groups (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            owner_user_id INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NULL,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(500) NULL,
            status ENUM("active", "archived") NOT NULL DEFAULT "active",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            INDEX idx_persistent_groups_org (organization_id, status)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS persistent_group_members (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            role ENUM("owner", "admin", "member") NOT NULL DEFAULT "member",
            joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES persistent_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_persistent_group_member (group_id, user_id),
            INDEX idx_persistent_group_user (user_id, group_id)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS persistent_group_invites (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT UNSIGNED NOT NULL,
            created_by INT UNSIGNED NOT NULL,
            invited_email VARCHAR(160) NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            accepted_by INT UNSIGNED NULL,
            accepted_at DATETIME NULL,
            revoked_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES persistent_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (accepted_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_persistent_group_invite_token (token_hash),
            INDEX idx_persistent_group_invites (group_id, expires_at)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS persistent_group_messages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            group_id BIGINT UNSIGNED NOT NULL,
            sender_id INT UNSIGNED NOT NULL,
            body VARCHAR(2000) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (group_id) REFERENCES persistent_groups(id) ON DELETE CASCADE,
            FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_persistent_group_messages (group_id, created_at, id)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS rental_vehicles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            organization_id INT UNSIGNED NOT NULL,
            make VARCHAR(80) NOT NULL,
            model VARCHAR(100) NOT NULL,
            year SMALLINT UNSIGNED NULL,
            category VARCHAR(60) NOT NULL,
            seats TINYINT UNSIGNED NOT NULL DEFAULT 4,
            daily_rate DECIMAL(10, 2) NOT NULL,
            currency CHAR(3) NOT NULL DEFAULT "KES",
            registration_number VARCHAR(32) NOT NULL,
            description VARCHAR(500) NULL,
            status ENUM("available", "unavailable", "maintenance") NOT NULL DEFAULT "available",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_rental_vehicle_registration (organization_id, registration_number),
            INDEX idx_rental_vehicles_available (organization_id, status)
        ) ENGINE=InnoDB',
        'CREATE TABLE IF NOT EXISTS rental_bookings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            vehicle_id BIGINT UNSIGNED NOT NULL,
            customer_id INT UNSIGNED NOT NULL,
            pickup_at DATETIME NOT NULL,
            return_at DATETIME NOT NULL,
            quoted_daily_rate DECIMAL(10, 2) NOT NULL,
            status ENUM("requested", "approved", "rejected", "cancelled", "completed") NOT NULL DEFAULT "requested",
            customer_note VARCHAR(500) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_rental_bookings_customer (customer_id, created_at),
            INDEX idx_rental_bookings_vehicle_dates (vehicle_id, status, pickup_at, return_at)
        ) ENGINE=InnoDB',
    ];

    foreach ($statements as $statement) {
        $db->exec($statement);
    }

    $db->exec(
        'INSERT INTO organization_profiles (organization_id, account_type, review_status)
         SELECT o.id, "school", "approved"
         FROM organizations o
         LEFT JOIN organization_profiles p ON p.organization_id = o.id
         WHERE p.organization_id IS NULL'
    );
};
