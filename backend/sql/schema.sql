CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    username VARCHAR(40) NOT NULL UNIQUE,
    email VARCHAR(160) NOT NULL UNIQUE,
    email_verified_at DATETIME NULL,
    phone VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_suspended TINYINT(1) NOT NULL DEFAULT 0,
    home_address VARCHAR(255) NULL,
    avatar_path VARCHAR(255) NULL,
    show_history_to_contacts TINYINT(1) NOT NULL DEFAULT 0,
    allow_group_journeys TINYINT(1) NOT NULL DEFAULT 1,
    discoverable_by_phone TINYINT(1) NOT NULL DEFAULT 1,
    route_deviation_alerts TINYINT(1) NOT NULL DEFAULT 1,
    arrival_notifications TINYINT(1) NOT NULL DEFAULT 1,
    auto_sos_on_silence TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_email_verifications_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_resets_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE sessions (
    id VARCHAR(64) PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    validator_hash VARCHAR(64) NOT NULL,
    user_agent VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    last_active_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sessions_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE trusted_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    contact_user_id INT UNSIGNED NULL,
    invite_name VARCHAR(120) NOT NULL,
    invite_phone VARCHAR(20) NOT NULL,
    relationship VARCHAR(60) NULL,
    status ENUM('pending', 'confirmed', 'declined') NOT NULL DEFAULT 'pending',
    share_live_location TINYINT(1) NOT NULL DEFAULT 1,
    journey_alerts TINYINT(1) NOT NULL DEFAULT 1,
    sos_alerts TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (contact_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_trusted_contacts_owner (owner_id),
    INDEX idx_trusted_contacts_contact_user (contact_user_id)
) ENGINE=InnoDB;

CREATE TABLE journeys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    start_label VARCHAR(160) NOT NULL,
    start_lat DECIMAL(10, 7) NULL,
    start_lng DECIMAL(10, 7) NULL,
    end_label VARCHAR(160) NOT NULL,
    end_lat DECIMAL(10, 7) NULL,
    end_lng DECIMAL(10, 7) NULL,
    transport_mode ENUM('car', 'bus', 'motorbike', 'walking') NOT NULL DEFAULT 'car',
    note VARCHAR(500) NULL,
    distance_km DECIMAL(7, 2) NULL,
    status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    route_deviation_alert TINYINT(1) NOT NULL DEFAULT 1,
    planned_departure_at DATETIME NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_journeys_user (user_id),
    INDEX idx_journeys_status (status)
) ENGINE=InnoDB;

CREATE TABLE journey_shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journey_id INT UNSIGNED NOT NULL,
    trusted_contact_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
    FOREIGN KEY (trusted_contact_id) REFERENCES trusted_contacts(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_journey_contact (journey_id, trusted_contact_id)
) ENGINE=InnoDB;

CREATE TABLE journey_positions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journey_id INT UNSIGNED NOT NULL,
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(10, 7) NOT NULL,
    speed_kmh DECIMAL(5, 2) NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
    INDEX idx_journey_positions_journey (journey_id, recorded_at)
) ENGINE=InnoDB;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_thread (sender_id, receiver_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('journey_started', 'journey_completed', 'arrival', 'route_deviation', 'new_message', 'location_share', 'sos_alert', 'contact_request', 'group_invite', 'welcome') NOT NULL,
    title VARCHAR(160) NOT NULL,
    body VARCHAR(500) NULL,
    related_journey_id INT UNSIGNED NULL,
    related_user_id INT UNSIGNED NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_journey_id) REFERENCES journeys(id) ON DELETE SET NULL,
    FOREIGN KEY (related_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_notifications_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE sos_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    journey_id INT UNSIGNED NULL,
    lat DECIMAL(10, 7) NULL,
    lng DECIMAL(10, 7) NULL,
    status ENUM('active', 'resolved') NOT NULL DEFAULT 'active',
    resolved_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sos_alerts_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE group_journeys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizer_id INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    destination_label VARCHAR(160) NOT NULL,
    destination_lat DECIMAL(10, 7) NULL,
    destination_lng DECIMAL(10, 7) NULL,
    distance_km DECIMAL(7, 2) NULL,
    departure_at DATETIME NULL,
    status ENUM('upcoming', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_group_journeys_organizer (organizer_id)
) ENGINE=InnoDB;

CREATE TABLE group_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_journey_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    invite_name VARCHAR(120) NULL,
    invite_phone VARCHAR(20) NULL,
    status ENUM('invited', 'confirmed', 'declined') NOT NULL DEFAULT 'invited',
    last_lat DECIMAL(10, 7) NULL,
    last_lng DECIMAL(10, 7) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_journey_id) REFERENCES group_journeys(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_group_members_group (group_journey_id)
) ENGINE=InnoDB;

CREATE TABLE organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    is_suspended TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE organization_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin') NOT NULL DEFAULT 'admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_org_admin (organization_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE organization_travelers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'deactivated') NOT NULL DEFAULT 'active',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_org_traveler (organization_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE platform_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'staff') NOT NULL DEFAULT 'staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_platform_admin_user (user_id)
) ENGINE=InnoDB;