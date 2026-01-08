CREATE DATABASE IF NOT EXISTS shimal_events DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE shimal_events;
SET NAMES utf8mb4;

-- جدول المستخدمين (المشرفين)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('admin', 'editor') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- جدول القاعات (لتنظيم الموارد)
CREATE TABLE IF NOT EXISTS halls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('theater', 'hall', 'lab') NOT NULL,
    capacity INT
) ENGINE=InnoDB;

-- جدول إعدادات النظام
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- جدول الفعاليات المحدث
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    organizing_dept TEXT NOT NULL,
    related_depts TEXT,
    requester_mobile VARCHAR(20) NOT NULL,
    requester_email VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    location_type ENUM('internal', 'external') NOT NULL,
    
    -- حقول الداخلية
    hall_id INT DEFAULT NULL,
    custom_hall_name VARCHAR(255) DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    req_audio BOOLEAN DEFAULT FALSE,
    req_catering BOOLEAN DEFAULT FALSE,
    req_security BOOLEAN DEFAULT FALSE,
    req_media BOOLEAN DEFAULT FALSE,
    req_projector BOOLEAN DEFAULT FALSE,
    
    -- حقول الخارجية
    external_address TEXT DEFAULT NULL,
    req_transport BOOLEAN DEFAULT FALSE,
    mkt_brochures INT DEFAULT 0,
    mkt_gifts INT DEFAULT 0,
    mkt_tools INT DEFAULT 0,
    estimated_budget INT DEFAULT 0,
    guest_list TEXT,
    
    attendees_expected INT DEFAULT 0,
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    edit_token VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL,
    INDEX idx_edit_token (edit_token)
) ENGINE=InnoDB;

-- إدخال بيانات تجريبية للقاعات
INSERT IGNORE INTO halls (id, name, type, capacity) VALUES 
(1, 'المسرح (قاعة الدلما رحمها الله)', 'theater', 300);

-- إضافة مستخدم افتراضي (كلمة المرور: PR123)
INSERT IGNORE INTO users (username, password_hash, full_name) VALUES 
('admin', '$2y$10$95P/8yWf6/f.yFjCgO5dbeH9X5X5X5X5X5X5X5X5X5X5X5X5X5X5', 'مسؤول العلاقات العامة');

-- إضافة إعدادات النظام الافتراضية
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES 
('edit_deadline_hours', '1', 'عدد الساعات قبل بدء الفعالية التي يُمنع فيها التعديل');
