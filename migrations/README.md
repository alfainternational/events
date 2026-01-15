# Database Migrations

نظام إدارة تحديثات قاعدة البيانات بشكل منظم وآمن.

## كيفية الاستخدام

### تشغيل Migrations

```bash
cd /home/user/events/migrations
php run_migrations.php
```

### إنشاء Migration جديد

1. أنشئ ملف SQL جديد بالتنسيق: `XXX_description.sql`
   - XXX = رقم تسلسلي (مثل 001, 002, 003)
   - description = وصف مختصر بالإنجليزية

2. مثال:
```sql
-- Migration: 002_add_notifications_table
-- Description: إضافة جدول الإشعارات
-- Date: 2026-01-15

USE shimal_events;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- تسجيل Migration
INSERT INTO migrations (migration_name) VALUES ('002_add_notifications_table')
ON DUPLICATE KEY UPDATE migration_name = migration_name;
```

## Migrations المتوفرة

### 001_add_missing_fields.sql
- إضافة حقول booking_type, unified_timing, event_days_json
- إضافة حقول الحذف المنطقي (deleted_at, deleted_by)
- إضافة حقول الموافقة (approved_by, approved_at, rejection_reason)
- إضافة indexes لتحسين الأداء
- تحديث password hash الافتراضي
- إضافة إعدادات النظام الجديدة

## ملاحظات مهمة

⚠️ **قبل تشغيل Migrations على الموقع الحي:**
1. خذ نسخة احتياطية من قاعدة البيانات
2. اختبر على بيئة تطوير أولاً
3. راجع كل migration قبل التنفيذ

✅ **Migrations آمنة:**
- تستخدم transactions (rollback عند الخطأ)
- لا تحذف بيانات موجودة
- تستخدم IF NOT EXISTS لتجنب التكرار
- تسجل كل migration في جدول migrations

## نسخة احتياطية سريعة

```bash
# Backup
mysqldump -u username -p shimal_events > backup_$(date +%Y%m%d_%H%M%S).sql

# Restore (إذا احتجت)
mysql -u username -p shimal_events < backup_20260115_120000.sql
```
