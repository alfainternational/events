-- Session Security & Rate Limiting Tables

-- جدول تتبع معدل الطلبات (Rate Limiting)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(100) NOT NULL COMMENT 'IP address or user identifier',
    action VARCHAR(50) NOT NULL COMMENT 'login, booking, etc',
    attempts INT DEFAULT 1,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL COMMENT 'When the block expires',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_action (identifier, action),
    INDEX idx_blocked_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول سجل المراجعة (Audit Log)
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'NULL for anonymous actions',
    action VARCHAR(50) NOT NULL COMMENT 'approve, reject, delete, update, etc',
    resource_type VARCHAR(50) NOT NULL COMMENT 'event, user, setting, etc',
    resource_id INT NULL,
    old_value TEXT NULL COMMENT 'JSON of old data',
    new_value TEXT NULL COMMENT 'JSON of new data',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- تحديث: إضافة عمود session_id لجدول users (للتحكم بالجلسات)
ALTER TABLE users ADD COLUMN IF NOT EXISTS session_id VARCHAR(64) NULL AFTER password_hash;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL AFTER session_id;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_ip VARCHAR(45) NULL AFTER last_login;

-- إضافة indexes للأداء
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_status_date (status, start_date);
ALTER TABLE events ADD INDEX IF NOT EXISTS idx_location_type (location_type);
