-- أسبوع 2: تحسين قاعدة البيانات

-- إضافة عمود deleted_at للحذف المنطقي (Soft Delete)
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL DEFAULT NULL;

-- إضافة فهرس للبحث في السجلات غير المحذوفة
ALTER TABLE events 
ADD INDEX IF NOT EXISTS idx_not_deleted (deleted_at);

-- تحسين فهارس الأداء
ALTER TABLE events 
ADD INDEX IF NOT EXISTS idx_status_date (status, start_date) USING BTREE;

ALTER TABLE events 
ADD INDEX IF NOT EXISTS idx_location (location_type, start_date);

ALTER TABLE audit_log 
ADD INDEX IF NOT EXISTS idx_created (created_at DESC);

-- التحقق من الفهارس الحالية
-- SHOW INDEX FROM events;
-- SHOW INDEX FROM audit_log;

-- إعدادات الأداء (اختياري - للقراءة فقط)
-- يمكن تفعيلها في my.ini/my.cnf:
/*
[mysqld]
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_size = 32M
query_cache_type = 1
*/
