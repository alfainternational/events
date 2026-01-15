-- Migration 007: Admin Features and Settings Management
-- هذا الملف يضيف ميزات إدارة الأدمنز والإعدادات المتقدمة

USE shimal_events;

-- 1. تحديث جدول المستخدمين
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS is_super_admin BOOLEAN DEFAULT FALSE COMMENT 'المدير الرئيسي للنظام',
ADD COLUMN IF NOT EXISTS can_be_deleted BOOLEAN DEFAULT TRUE COMMENT 'هل يمكن حذف هذا المستخدم',
ADD COLUMN IF NOT EXISTS last_password_change TIMESTAMP NULL DEFAULT NULL,
ADD INDEX idx_email (email);

-- 2. جدول رموز إعادة تعيين كلمة المرور
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. إضافة إعدادات البريد الإلكتروني في جدول system_settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('email_enabled', 'false', 'تفعيل نظام البريد الإلكتروني'),
('email_from_address', 'noreply@shmial.edu.sa', 'عنوان البريد الإلكتروني للمرسل'),
('email_from_name', 'نظام الفعاليات - كلية الشمال', 'اسم المرسل'),
('email_admin_email', 'admin@shmial.edu.sa', 'البريد الإلكتروني للمدير')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 4. إضافة إعدادات Cron Jobs
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('cron_enabled', 'false', 'تفعيل المهام المجدولة'),
('cron_interval_minutes', '30', 'تكرار تشغيل Cron بالدقائق'),
('cron_batch_size', '20', 'حجم دفعة الإرسال في كل مرة')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 5. تحديث المستخدم الأول ليكون Super Admin
UPDATE users 
SET is_super_admin = TRUE, 
    can_be_deleted = FALSE,
    email = 'admin@shmial.edu.sa'
WHERE id = 1;

-- 6. التأكد من وجود الدور super_admin في جدول roles
INSERT IGNORE INTO roles (id, name, display_name, description) VALUES
(1, 'super_admin', 'مدير النظام', 'صلاحيات كاملة على النظام');

-- 7. إضافة صلاحيات جديدة للإعدادات
INSERT IGNORE INTO permissions (name, display_name, description, module) VALUES
('manage_email_settings', 'إدارة إعدادات البريد', 'التحكم في إعدادات البريد الإلكتروني', 'system'),
('manage_cron_settings', 'إدارة إعدادات المهام المجدولة', 'التحكم في إعدادات Cron', 'system'),
('create_admins', 'إنشاء مديرين', 'القدرة على إنشاء مستخدمين أدمن جدد', 'users'),
('delete_super_admin', 'حذف المدير الرئيسي', 'صلاحية خطرة: حذف المدير الرئيسي', 'users');

-- 8. منح جميع الصلاحيات للسوبر أدمن
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- 9. منع الأدمن العادي من حذف المستخدمين أو إنشاء أدمنز
DELETE FROM role_permissions 
WHERE role_id = 2 
AND permission_id IN (
    SELECT id FROM permissions 
    WHERE name IN ('delete_users', 'create_admins', 'delete_super_admin', 'manage_email_settings', 'manage_cron_settings')
);

-- 10. التحقق من البيانات
SELECT 'Migration 007 completed successfully' AS status;
