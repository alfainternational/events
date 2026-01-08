<?php
/**
 * Configuration Management System
 * جميع الإعدادات في مكان واحد
 */

return [
    'app' => [
        'name' => 'نظام إدارة الفعاليات',
        'short_name' => 'الفعاليات',
        'version' => '2.0.0',
        'env' => 'development', // production, development, testing
        'debug' => true,
        'timezone' => 'Asia/Riyadh',
        'locale' => 'ar',
        'fallback_locale' => 'en',
    ],
    
    'database' => [
        'host' => 'localhost',
        'dbname' => 'shimal_events',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    
    'session' => [
        'lifetime' => 120, // minutes
        'cookie_httponly' => true,
        'cookie_secure' => false, // true in production with HTTPS
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
        'regenerate_interval' => 1800, // 30 minutes
    ],
    
    'security' => [
        'csrf_token_name' => 'csrf_token',
        'csrf_token_length' => 32,
        'password_min_length' => 8,
        'password_require_upper' => true,
        'password_require_lower' => true,
        'password_require_number' => true,
        'password_require_special' => true,
    ],
    
    'rate_limiting' => [
        'login' => [
            'max_attempts' => 5,
            'window_minutes' => 15,
        ],
        'booking' => [
            'max_attempts' => 3,
            'window_minutes' => 60,
        ],
    ],
    
    'email' => [
        'enabled' => false,
        'from_address' => 'noreply@shmial.edu.sa',
        'from_name' => 'نظام الفعاليات - كلية الشمال',
        'admin_email' => 'admin@shmial.edu.sa',
        'queue_enabled' => true,
        'queue_batch_size' => 20,
    ],
    
    'features' => [
        'edit_token_enabled' => true,
        'edit_deadline_hours' => 24,
        'soft_deletes' => true,
        'audit_logging' => true,
        'event_versioning' => true,
        'email_notifications' => false,
        'pwa_enabled' => true,
        'dark_mode' => true,
        'auto_save' => true,
    ],
    
    'paths' => [
        'base' => __DIR__ . '/../',
        'app' => __DIR__ . '/../app/',
        'includes' => __DIR__ . '/../includes/',
        'uploads' => __DIR__ . '/../uploads/',
        'logs' => __DIR__ . '/../storage/logs/',
        'cache' => __DIR__ . '/../storage/cache/',
    ],
    
    'logging' => [
        'enabled' => true,
        'level' => 'debug', // debug, info, warning, error
        'file' => 'app.log',
        'max_files' => 30,
    ],
    
    'cache' => [
        'enabled' => false,
        'driver' => 'file', // file, redis, memcached
        'ttl' => 3600, // seconds
    ],
    
    'pagination' => [
        'default_per_page' => 20,
        'max_per_page' => 100,
    ],
    
    'upload' => [
        'max_size' => 5242880, // 5MB in bytes
        'allowed_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'],
        'path' => 'uploads/',
    ],
];
?>
