CREATE TABLE IF NOT EXISTS user_notification_settings (
    user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
    preferences_json JSON NULL,
    preview_mode ENUM('full','sender','hidden') NOT NULL DEFAULT 'full',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_notification_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
