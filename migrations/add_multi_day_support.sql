-- Migration: Add Multi-Day Support to Events
-- تاريخ الإنشاء: 2026-01-07

-- إضافة حقول جديدة لجدول events
ALTER TABLE events
ADD COLUMN booking_type ENUM('single_day', 'consecutive', 'separate') DEFAULT 'consecutive' AFTER status,
ADD COLUMN unified_timing BOOLEAN DEFAULT TRUE AFTER booking_type,
ADD COLUMN event_days_json TEXT NULL AFTER unified_timing;

-- ملاحظة: الحقول القديمة start_date, end_date, start_time, end_time 
-- سيتم الاحتفاظ بها للتوافقية مع البيانات الحالية
-- يمكن حذفها لاحقاً بعد migration البيانات

-- إنشاء index للأداء
CREATE INDEX idx_booking_type ON events(booking_type);
