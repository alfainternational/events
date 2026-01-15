-- Email Notifications System Tables

-- جدول طابور البريد الإلكتروني
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(255) NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT NOT NULL,
    html_body TEXT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_attempt TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول قوالب البريد
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(50) NOT NULL UNIQUE,
    subject VARCHAR(500) NOT NULL,
    body_text TEXT NOT NULL,
    body_html TEXT NULL,
    variables TEXT NULL COMMENT 'JSON array of available variables',
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- إدراج قوالب افتراضية
INSERT INTO email_templates (template_key, subject, body_text, variables, description) VALUES
('event_approved', 'تم قبول طلب الفعالية: {{title}}', 
'عزيزي {{requester_name}},\n\nنود إعلامك بأنه تم قبول طلب الفعالية التالي:\n\nاسم الفعالية: {{title}}\nالجهة المنظمة: {{organizing_dept}}\nالتاريخ: {{start_date}}\n\nشكراً لك.', 
'["title", "organizing_dept", "start_date", "requester_name"]',
'رسالة عند قبول الطلب'),

('event_rejected', 'طلب الفعالية مرفوض: {{title}}',
'عزيزي {{requester_name}},\n\nنأسف لإبلاغك بأنه تم رفض طلب الفعالية التالي:\n\nاسم الفعالية: {{title}}\nالجهة المنظمة: {{organizing_dept}}\n\nللاستفسار يرجى التواصل مع قسم العلاقات العامة.\n\nشكراً لك.',
'["title", "organizing_dept", "requester_name"]',
'رسالة عند رفض الطلب'),

('event_modified', 'تم تعديل طلب الفعالية: {{title}}',
'مرحباً,\n\nنود إعلامك بأنه تم تعديل الطلب التالي من قبل مقدم الطلب:\n\nاسم الفعالية: {{title}}\nالجهة المنظمة: {{organizing_dept}}\nالتاريخ الجديد: {{start_date}}\n\nيرجى مراجعة التعديلات والموافقة عليها.',
'["title", "organizing_dept", "start_date"]',
'رسالة للأدمن عند تعديل الطلب');

-- جدول إعدادات البريد
ALTER TABLE system_settings 
ADD COLUMN IF NOT EXISTS setting_group VARCHAR(50) DEFAULT NULL AFTER setting_key;

INSERT IGNORE INTO system_settings (setting_key, setting_value, description, setting_group) VALUES
('email_enabled', '0', 'تفعيل نظام البريد الإلكتروني', 'email'),
('email_from_address', 'noreply@shmial.edu.sa', 'عنوان المرسل', 'email'),
('email_from_name', 'نظام الفعاليات - كلية الشمال', 'اسم المرسل', 'email'),
('admin_email', 'admin@shmial.edu.sa', 'بريد الأدمن لتلقي الإشعارات', 'email');
