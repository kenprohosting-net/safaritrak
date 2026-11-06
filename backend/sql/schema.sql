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
    sos_alert_notifications TINYINT(1) NOT NULL DEFAULT 1,
    product_update_emails TINYINT(1) NOT NULL DEFAULT 0,
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

CREATE TABLE trusted_contact_invites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    invite_name VARCHAR(120) NOT NULL,
    relationship VARCHAR(60) NULL,
    recipient_email VARCHAR(160) NULL,
    expires_at DATETIME NOT NULL,
    claimed_user_id INT UNSIGNED NULL,
    accepted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (claimed_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_contact_invite_token_hash (token_hash),
    INDEX idx_contact_invites_owner (owner_id, created_at),
    INDEX idx_contact_invites_expiration (expires_at)
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
    covered_km DECIMAL(7, 2) NOT NULL DEFAULT 0,
    status ENUM('planned', 'active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
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

CREATE TABLE journey_share_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journey_id INT UNSIGNED NOT NULL,
    owner_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_journey_share_link_token_hash (token_hash),
    INDEX idx_journey_share_links_owner (owner_id, expires_at)
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

CREATE TABLE journey_locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journey_id INT UNSIGNED NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (journey_id) REFERENCES journeys(id) ON DELETE CASCADE,
    INDEX idx_journey_locations_journey (journey_id, recorded_at)
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
    member_type ENUM('student', 'guardian', 'staff') NOT NULL DEFAULT 'staff',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_org_traveler (organization_id, user_id)
) ENGINE=InnoDB;

CREATE TABLE organization_profiles (
    organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
    account_type ENUM('school', 'car_hire') NOT NULL DEFAULT 'school',
    review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE organization_invites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    invited_email VARCHAR(160) NULL,
    member_type ENUM('student', 'guardian', 'staff') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_organization_invite_token (token_hash),
    INDEX idx_organization_invites_org (organization_id, expires_at)
) ENGINE=InnoDB;

CREATE TABLE organization_membership_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    invite_id BIGINT UNSIGNED NULL,
    member_type ENUM('student', 'guardian', 'staff') NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invite_id) REFERENCES organization_invites(id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_organization_membership_request (organization_id, user_id),
    INDEX idx_organization_membership_requests (organization_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE persistent_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    organization_id INT UNSIGNED NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(500) NULL,
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_persistent_groups_org (organization_id, status)
) ENGINE=InnoDB;

CREATE TABLE persistent_group_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES persistent_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_persistent_group_member (group_id, user_id),
    INDEX idx_persistent_group_user (user_id, group_id)
) ENGINE=InnoDB;

CREATE TABLE persistent_group_invites (
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
) ENGINE=InnoDB;

CREATE TABLE persistent_group_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_id BIGINT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES persistent_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_persistent_group_messages (group_id, created_at, id)
) ENGINE=InnoDB;

CREATE TABLE rental_vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    make VARCHAR(80) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year SMALLINT UNSIGNED NULL,
    category VARCHAR(60) NOT NULL,
    seats TINYINT UNSIGNED NOT NULL DEFAULT 4,
    daily_rate DECIMAL(10, 2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    registration_number VARCHAR(32) NOT NULL,
    description VARCHAR(500) NULL,
    status ENUM('available', 'unavailable', 'maintenance') NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_rental_vehicle_registration (organization_id, registration_number),
    INDEX idx_rental_vehicles_available (organization_id, status)
) ENGINE=InnoDB;

CREATE TABLE rental_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    pickup_at DATETIME NOT NULL,
    return_at DATETIME NOT NULL,
    quoted_daily_rate DECIMAL(10, 2) NOT NULL,
    status ENUM('requested', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'requested',
    customer_note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_rental_bookings_customer (customer_id, created_at),
    INDEX idx_rental_bookings_vehicle_dates (vehicle_id, status, pickup_at, return_at)
) ENGINE=InnoDB;

CREATE TABLE platform_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'staff') NOT NULL DEFAULT 'staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_platform_admin_user (user_id)
) ENGINE=InnoDB;