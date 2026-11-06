CREATE TABLE IF NOT EXISTS trusted_contact_invites (
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
