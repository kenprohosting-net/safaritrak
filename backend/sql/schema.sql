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

CREATE TABLE saved_locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    category VARCHAR(40) NOT NULL DEFAULT 'other',
    address VARCHAR(500) NULL,
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(10, 7) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_saved_locations_user (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id INT UNSIGNED NOT NULL,
    receiver_id INT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    attachment_path VARCHAR(255) NULL,
    trip_label VARCHAR(120) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_messages_thread (sender_id, receiver_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('journey_started', 'journey_completed', 'arrival', 'route_deviation', 'new_message', 'location_share', 'sos_alert', 'contact_request', 'group_invite', 'welcome', 'school_trip_access', 'school_transport_broadcast') NOT NULL,
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
    meeting_point_label VARCHAR(160) NULL,
    meeting_point_lat DECIMAL(10, 7) NULL,
    meeting_point_lng DECIMAL(10, 7) NULL,
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

CREATE TABLE group_member_positions (
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
) ENGINE=InnoDB;

CREATE TABLE group_member_route_points (
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

CREATE TABLE organization_child_access (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    child_user_id INT UNSIGNED NOT NULL,
    guardian_user_id INT UNSIGNED NOT NULL,
    invited_by INT UNSIGNED NOT NULL,
    status ENUM('pending', 'active', 'revoked') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (child_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (guardian_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_organization_child_guardian (organization_id, child_user_id, guardian_user_id),
    INDEX idx_child_access_guardian (guardian_user_id, status, created_at)
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

CREATE TABLE school_transport_profiles (
    school_id INT UNSIGNED NOT NULL PRIMARY KEY,
    origin_name VARCHAR(120) NOT NULL,
    origin_lat DECIMAL(10, 7) NOT NULL,
    origin_lng DECIMAL(10, 7) NOT NULL,
    updated_by INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (school_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE school_buses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id INT UNSIGNED NOT NULL,
    bus_number VARCHAR(32) NOT NULL,
    route_name VARCHAR(120) NOT NULL,
    driver_user_id INT UNSIGNED NULL,
    driver_name VARCHAR(120) NOT NULL,
    driver_phone VARCHAR(20) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_school_bus_number (school_id, bus_number),
    FOREIGN KEY (school_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_school_buses_school_status (school_id, status, id)
) ENGINE=InnoDB;

CREATE TABLE school_bus_stops (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bus_id BIGINT UNSIGNED NOT NULL,
    stop_name VARCHAR(120) NOT NULL,
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(10, 7) NOT NULL,
    stop_order SMALLINT UNSIGNED NOT NULL,
    stop_type ENUM('pickup', 'dropoff', 'waypoint') NOT NULL DEFAULT 'waypoint',
    FOREIGN KEY (bus_id) REFERENCES school_buses(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_school_bus_stop_order (bus_id, stop_order),
    INDEX idx_school_bus_stops_route (bus_id, stop_order)
) ENGINE=InnoDB;

CREATE TABLE school_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    school_id INT UNSIGNED NOT NULL,
    role ENUM('school_admin', 'verified_parent') NOT NULL,
    student_user_id INT UNSIGNED NULL,
    student_name VARCHAR(120) NULL,
    student_grade VARCHAR(40) NULL,
    assigned_bus_id BIGINT UNSIGNED NULL,
    home_dropoff_label VARCHAR(120) NULL,
    home_dropoff_lat DECIMAL(10, 7) NULL,
    home_dropoff_lng DECIMAL(10, 7) NULL,
    route_service_consent TINYINT(1) NOT NULL DEFAULT 0,
    route_service_consent_at DATETIME NULL,
    verification_status ENUM('pending', 'verified', 'revoked') NOT NULL DEFAULT 'pending',
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
) ENGINE=InnoDB;

CREATE TABLE school_bus_trips (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    bus_id BIGINT UNSIGNED NOT NULL,
    started_by INT UNSIGNED NOT NULL,
    status ENUM('en_route', 'arrived', 'cancelled') NOT NULL DEFAULT 'en_route',
    broadcast_active TINYINT(1) NOT NULL DEFAULT 0,
    driver_note VARCHAR(500) NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    broadcast_started_at DATETIME NULL,
    completed_at DATETIME NULL,
    FOREIGN KEY (bus_id) REFERENCES school_buses(id) ON DELETE CASCADE,
    FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_school_bus_trips_latest (bus_id, started_at, id),
    INDEX idx_school_bus_trips_broadcast (bus_id, status, broadcast_active)
) ENGINE=InnoDB;

CREATE TABLE school_bus_positions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trip_id BIGINT UNSIGNED NOT NULL,
    lat DECIMAL(10, 7) NOT NULL,
    lng DECIMAL(10, 7) NOT NULL,
    speed_kmh DECIMAL(6, 2) NULL,
    accuracy_meters DECIMAL(7, 2) NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES school_bus_trips(id) ON DELETE CASCADE,
    INDEX idx_school_bus_positions_latest (trip_id, recorded_at, id)
) ENGINE=InnoDB;

CREATE TABLE school_student_trip_status (
    trip_id BIGINT UNSIGNED NOT NULL,
    verification_id BIGINT UNSIGNED NOT NULL,
    status ENUM('on_board', 'approaching', 'dropped_off') NOT NULL DEFAULT 'on_board',
    status_note VARCHAR(300) NULL,
    updated_by INT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (trip_id, verification_id),
    FOREIGN KEY (trip_id) REFERENCES school_bus_trips(id) ON DELETE CASCADE,
    FOREIGN KEY (verification_id) REFERENCES school_verifications(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
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
    last_read_message_id BIGINT UNSIGNED NULL,
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
    attachment_path VARCHAR(255) NULL,
    trip_label VARCHAR(120) NULL,
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
    daily_rate DECIMAL(10, 2) NULL,
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

CREATE TABLE rental_company_profiles (
    organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
    logo_url VARCHAR(500) NULL,
    cover_photo_url VARCHAR(500) NULL,
    description VARCHAR(2000) NULL,
    established_year SMALLINT UNSIGNED NULL,
    business_email VARCHAR(160) NULL,
    official_phone VARCHAR(32) NULL,
    whatsapp VARCHAR(32) NULL,
    website VARCHAR(500) NULL,
    social_links TEXT NULL,
    office_location VARCHAR(500) NULL,
    town VARCHAR(120) NULL,
    regions_served TEXT NULL,
    operating_hours VARCHAR(500) NULL,
    office_lat DECIMAL(10, 7) NULL,
    office_lng DECIMAL(10, 7) NULL,
    coverage_description VARCHAR(1000) NULL,
    pickup_locations TEXT NULL,
    vehicle_delivery TINYINT(1) NOT NULL DEFAULT 0,
    airport_pickup TINYINT(1) NOT NULL DEFAULT 0,
    services TEXT NULL,
    minimum_driver_age TINYINT UNSIGNED NULL,
    valid_licence_required TINYINT(1) NOT NULL DEFAULT 1,
    minimum_driving_experience TINYINT UNSIGNED NULL,
    identification_requirements VARCHAR(500) NULL,
    booking_notice VARCHAR(200) NULL,
    minimum_rental_days SMALLINT UNSIGNED NULL,
    maximum_rental_days SMALLINT UNSIGNED NULL,
    fuel_return_policy VARCHAR(500) NULL,
    mileage_restrictions VARCHAR(500) NULL,
    late_return_policy VARCHAR(500) NULL,
    cancellation_policy VARCHAR(1000) NULL,
    refund_policy VARCHAR(1000) NULL,
    cross_border_available TINYINT(1) NOT NULL DEFAULT 0,
    gps_tracking_available TINYINT(1) NOT NULL DEFAULT 0,
    customer_trip_sharing TINYINT(1) NOT NULL DEFAULT 0,
    roadside_assistance TINYINT(1) NOT NULL DEFAULT 0,
    emergency_number VARCHAR(32) NULL,
    breakdown_assistance TINYINT(1) NOT NULL DEFAULT 0,
    registered_drivers TINYINT(1) NOT NULL DEFAULT 0,
    live_trip_monitoring TINYINT(1) NOT NULL DEFAULT 0,
    emergency_arrangements VARCHAR(1000) NULL,
    response_information VARCHAR(500) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rental_vehicle_details (
    vehicle_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    transmission ENUM('automatic', 'manual') NULL,
    fuel_type ENUM('petrol', 'diesel', 'hybrid', 'electric') NULL,
    air_conditioning TINYINT(1) NULL,
    luggage_capacity TINYINT UNSIGNED NULL,
    weekly_rate DECIMAL(10, 2) NULL,
    monthly_rate DECIMAL(10, 2) NULL,
    chauffeur_rate DECIMAL(10, 2) NULL,
    self_drive_rate DECIMAL(10, 2) NULL,
    security_deposit DECIMAL(10, 2) NULL,
    extra_mileage_charge DECIMAL(10, 2) NULL,
    fuel_policy VARCHAR(500) NULL,
    additional_charges VARCHAR(1000) NULL,
    negotiable TINYINT(1) NOT NULL DEFAULT 0,
    quotation_required TINYINT(1) NOT NULL DEFAULT 0,
    driver_option ENUM('self_drive', 'chauffeur', 'both') NOT NULL DEFAULT 'both',
    services TEXT NULL,
    minimum_driver_age TINYINT UNSIGNED NULL,
    valid_licence_required TINYINT(1) NOT NULL DEFAULT 1,
    minimum_driving_experience TINYINT UNSIGNED NULL,
    identification_requirements VARCHAR(500) NULL,
    booking_notice VARCHAR(200) NULL,
    minimum_rental_days SMALLINT UNSIGNED NULL,
    maximum_rental_days SMALLINT UNSIGNED NULL,
    fuel_return_policy VARCHAR(500) NULL,
    mileage_restrictions VARCHAR(500) NULL,
    late_return_policy VARCHAR(500) NULL,
    cancellation_policy VARCHAR(1000) NULL,
    refund_policy VARCHAR(1000) NULL,
    cross_border_available TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rental_vehicle_photos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    photo_type ENUM('exterior', 'interior') NOT NULL,
    photo_url VARCHAR(1000) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
    INDEX idx_rental_vehicle_photos (vehicle_id, photo_type, sort_order)
) ENGINE=InnoDB;

CREATE TABLE rental_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    pickup_at DATETIME NOT NULL,
    return_at DATETIME NOT NULL,
    quoted_daily_rate DECIMAL(10, 2) NULL,
    status ENUM('requested', 'approved', 'responded', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'requested',
    customer_note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_rental_bookings_customer (customer_id, created_at),
    INDEX idx_rental_bookings_vehicle_dates (vehicle_id, status, pickup_at, return_at)
) ENGINE=InnoDB;

CREATE TABLE rental_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    vehicle_id BIGINT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    review_text VARCHAR(1000) NULL,
    status ENUM('published', 'hidden') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES rental_bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (vehicle_id) REFERENCES rental_vehicles(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_rental_review_booking (booking_id),
    INDEX idx_rental_reviews_vehicle (vehicle_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE platform_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'staff') NOT NULL DEFAULT 'staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_platform_admin_user (user_id)
) ENGINE=InnoDB;