<?php
/**
 * Bootstrap - تهيئة النظام الشاملة
 */

// تعريف المسارات
define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOGS_PATH', STORAGE_PATH . '/logs');

// تحميل الإعدادات
$config = require CONFIG_PATH . '/app.php';

// ضبط المنطقة الزمنية
date_default_timezone_set($config['app']['timezone']);

// ضبط عرض الأخطاء
if ($config['app']['env'] === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// تهيئة معالج الأخطاء
require_once APP_PATH . '/Core/ErrorHandler.php';
$errorHandler = new ErrorHandler(
    LOGS_PATH,
    $config['app']['debug']
);

// تهيئة Logger
require_once APP_PATH . '/Core/Logger.php';

// الاتصال بقاعدة البيانات
try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        $config['database']['host'],
        $config['database']['dbname'],
        $config['database']['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $config['database']['username'],
        $config['database']['password'],
        $config['database']['options']
    );
    
    // تسجيل النجاح
    log_debug('Database connection established');
    
} catch (PDOException $e) {
    log_critical('Database connection failed', ['error' => $e->getMessage()]);
    
    if ($config['app']['debug']) {
        die('Database connection failed: ' . $e->getMessage());
    } else {
        die('حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.');
    }
}

// تهيئة الجلسة
require_once BASE_PATH . '/includes/init.php';

// تحميل الدوال المساعدة
require_once BASE_PATH . '/includes/messages.php';
require_once BASE_PATH . '/includes/csrf.php';

// جعل المتغيرات عالمية
$GLOBALS['config'] = $config;
$GLOBALS['pdo'] = $pdo;

/**
 * دالة مساعدة للحصول على الإعدادات
 */
function config($key, $default = null) {
    $keys = explode('.', $key);
    $value = $GLOBALS['config'];
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * دالة مساعدة للحصول على قاعدة البيانات
 */
function db() {
    return $GLOBALS['pdo'];
}

/**
 * تحميل Environment Variables من ملف .env (إن وجد)
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // تجاهل التعليقات
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // إزالة علامات الاقتباس
            $value = trim($value, '"\'');
            
            if (!array_key_exists($key, $_ENV)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// تحميل .env
loadEnv(BASE_PATH . '/.env');

// تسجيل بدء التطبيق
log_info(' Application bootstrap completed', [
    'env' => $config['app']['env'],
    'debug' => $config['app']['debug']
]);
?>
