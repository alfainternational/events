<?php
/**
 * Database Connection
 * اتصال آمن بقاعدة البيانات باستخدام متغيرات البيئة
 */

// تحميل متغيرات البيئة
require_once __DIR__ . '/env_loader.php';

// إعدادات قاعدة البيانات من ملف .env
$host = env('DB_HOST', 'localhost');
$db   = env('DB_NAME', 'u947172334_events');
$user = env('DB_USERNAME', 'u947172334_events');
$pass = env('DB_PASSWORD', 'U947172334_events');
$charset = env('DB_CHARSET', 'utf8mb4');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // في بيئة التطوير نظهر الخطأ، في الإنتاج نسجل في اللوج فقط
     if (env('APP_DEBUG', true)) {
         die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
     } else {
         error_log("Database connection failed: " . $e->getMessage());
         die("حدث خطأ في الاتصال بقاعدة البيانات. يرجى المحاولة لاحقاً.");
     }
}
?>
