CREATE TABLE IF NOT EXISTS journey_share_links (
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
