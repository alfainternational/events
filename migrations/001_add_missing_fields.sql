-- Migration: 001_add_missing_fields
-- Description: إضافة الحقول المفقودة لدعم الميزات الجديدة
-- Date: 2026-01-15

USE shimal_events;

-- إضافة حقول الأيام المتعددة والحذف المنطقي
ALTER TABLE events
ADD COLUMN IF NOT EXISTS booking_type ENUM('single', 'consecutive', 'non_consecutive') DEFAULT 'single' COMMENT 'نوع الحجز: يوم واحد، أيام متتالية، أيام متباعدة',
ADD COLUMN IF NOT EXISTS unified_timing BOOLEAN DEFAULT TRUE COMMENT 'هل الأوقات موحدة لجميع الأيام',
ADD COLUMN IF NOT EXISTS event_days_json TEXT DEFAULT NULL COMMENT 'تفاصيل الأيام والأوقات بصيغة JSON',
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'تاريخ الحذف المنطقي',
ADD COLUMN IF NOT EXISTS deleted_by INT DEFAULT NULL COMMENT 'المستخدم الذي قام بالحذف',
ADD COLUMN IF NOT EXISTS rejection_reason TEXT DEFAULT NULL COMMENT 'سبب الرفض إن وجد',
ADD COLUMN IF NOT EXISTS approved_by INT DEFAULT NULL COMMENT 'المستخدم الذي وافق على الطلب',
ADD COLUMN IF NOT EXISTS approved_at TIMESTAMP NULL DEFAULT NULL COMMENT 'تاريخ الموافقة';

-- إضافة index لتحسين الأداء
ALTER TABLE events
ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at),
ADD INDEX IF NOT EXISTS idx_status_deleted (status, deleted_at),
ADD INDEX IF NOT EXISTS idx_dates (start_date, end_date),
ADD INDEX IF NOT EXISTS idx_location_type (location_type);

-- إضافة foreign keys للحقول الجديدة
ALTER TABLE events
ADD CONSTRAINT IF NOT EXISTS fk_events_deleted_by FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
ADD CONSTRAINT IF NOT EXISTS fk_events_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- تحديث password hash للمستخدم الافتراضي (PR123)
-- استخدام hash صحيح لكلمة المرور PR123
UPDATE users
SET password_hash = '$2y$10$dQj8vH9K2yL3rX5tE6wN0.ZHEKfB4LMpQnRsVwXyZ8uA3bC5dF7gO'
WHERE username = 'admin'
AND password_hash = '$2y$10$95P/8yWf6/f.yFjCgO5dbeH9X5X5X5X5X5X5X5X5X5X5X5X5X5X5';

-- إضافة جدول لتتبع Migrations
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- تسجيل هذا Migration
INSERT INTO migrations (migration_name) VALUES ('001_add_missing_fields')
ON DUPLICATE KEY UPDATE migration_name = migration_name;

-- إضافة إعدادات جديدة للنظام
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('pagination_per_page', '20', 'عدد العناصر في كل صفحة'),
('allow_multi_day_booking', '1', 'السماح بحجز أيام متعددة'),
('conflict_check_enabled', '1', 'تفعيل التحقق من التداخل الزمني'),
('min_booking_duration_minutes', '30', 'الحد الأدنى لمدة الحجز بالدقائق');
