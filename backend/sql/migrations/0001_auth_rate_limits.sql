CREATE TABLE IF NOT EXISTS auth_rate_limits (
    bucket VARCHAR(40) NOT NULL,
    key_hash CHAR(64) NOT NULL,
    window_started_at DATETIME NOT NULL,
    attempts INT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket, key_hash),
    INDEX idx_auth_rate_limits_window (window_started_at)
) ENGINE=InnoDB;
