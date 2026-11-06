CREATE TABLE IF NOT EXISTS privacy_consents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    consent_type ENUM('terms', 'privacy') NOT NULL,
    document_version VARCHAR(32) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uniq_privacy_consent_version (user_id, consent_type, document_version),
    INDEX idx_privacy_consents_user (user_id, accepted_at)
) ENGINE=InnoDB;
