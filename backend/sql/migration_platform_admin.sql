CREATE TABLE platform_admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'staff') NOT NULL DEFAULT 'staff',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_platform_admin_user (user_id)
) ENGINE=InnoDB;

ALTER TABLE users
    ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;

ALTER TABLE organizations
    ADD COLUMN is_suspended TINYINT(1) NOT NULL DEFAULT 0 AFTER name;
