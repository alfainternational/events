-- RBAC System - Role-Based Access Control

-- جدول الأدوار
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول الصلاحيات
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(150) NOT NULL,
    description TEXT,
    module VARCHAR(50) NOT NULL COMMENT 'events, users, settings, etc',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ربط الأدوار بالصلاحيات
CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ربط المستخدمين بالأدوار
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج الأدوار الأساسية
INSERT INTO roles (name, display_name, description) VALUES
('super_admin', 'مدير النظام', 'صلاحيات كاملة على النظام'),
('admin', 'أدمن', 'إدارة الفعاليات والمستخدمين'),
('coordinator', 'منسق', 'الموافقة والرفض على الطلبات'),
('viewer', 'مشاهد', 'عرض الفعاليات فقط');

-- إدراج الصلاحيات
INSERT INTO permissions (name, display_name, description, module) VALUES
-- Events
('view_events', 'عرض الفعاليات', 'القدرة على عرض جميع الفعاليات', 'events'),
('create_events', 'إنشاء فعاليات', 'القدرة على إنشاء فعاليات جديدة', 'events'),
('edit_events', 'تعديل الفعاليات', 'القدرة على تعديل الفعاليات', 'events'),
('delete_events', 'حذف الفعاليات', 'القدرة على حذف الفعاليات', 'events'),
('approve_events', 'الموافقة على الفعاليات', 'القدرة على الموافقة', 'events'),
('reject_events', 'رفض الفعاليات', 'القدرة على رفض الطلبات', 'events'),
('restore_events', 'استعادة الفعاليات', 'استعادة من سلة المحذوفات', 'events'),

-- Users
('view_users', 'عرض المستخدمين', 'القدرة على عرض المستخدمين', 'users'),
('create_users', 'إنشاء مستخدمين', 'القدرة على إضافة مستخدمين', 'users'),
('edit_users', 'تعديل المستخدمين', 'القدرة على تعديل بيانات المستخدمين', 'users'),
('delete_users', 'حذف المستخدمين', 'القدرة على حذف المستخدمين', 'users'),
('assign_roles', 'تعيين الأدوار', 'القدرة على تعيين الأدوار للمستخدمين', 'users'),

-- System
('view_audit_logs', 'عرض سجل المراجعة', 'عرض سجل جميع العمليات', 'system'),
('view_statistics', 'عرض الإحصائيات', 'عرض لوحة الإحصائيات', 'system'),
('export_reports', 'تصدير التقارير', 'تصدير تقارير CSV/Excel/PDF', 'system'),
('manage_settings', 'إدارة الإعدادات', 'تعديل إعدادات النظام', 'system'),
('manage_email_templates', 'إدارة قوالب البريد', 'تعديل قوالب البريد الإلكتروني', 'system');

-- ربط الأدوار بالصلاحيات

-- Super Admin: جميع الصلاحيات
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Admin: معظم الصلاحيات
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name NOT IN ('delete_users', 'assign_roles');

-- Coordinator: صلاحيات إدارة الفعاليات
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE module = 'events' AND name NOT IN ('delete_events');

-- Viewer: عرض فقط
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN ('view_events', 'view_statistics');

-- تعيين الدور الأول للمستخدم الافتراضي
INSERT IGNORE INTO user_roles (user_id, role_id, assigned_by)
SELECT id, 1, NULL FROM users LIMIT 1;
