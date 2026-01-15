-- نظام تتبع الإصدارات - Event Versioning

-- جدول إصدارات الفعاليات
CREATE TABLE IF NOT EXISTS event_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    version_number INT NOT NULL,
    data_snapshot TEXT NOT NULL COMMENT 'JSON snapshot of all event data',
    changed_by INT NULL COMMENT 'User ID who made the change',
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_type ENUM('create', 'update', 'approve', 'reject', 'restore') DEFAULT 'update',
    change_note TEXT NULL,
    INDEX idx_event_id (event_id),
    INDEX idx_version (event_id, version_number),
    INDEX idx_changed_at (changed_at),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
