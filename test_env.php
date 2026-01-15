<?php
/**
 * ملف اختبار لتشخيص مشكلة الخطأ 500
 * افتح: https://events.cartnec.com/test_env.php
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>تشخيص النظام</h1>";
echo "<hr>";

// 1. معلومات PHP
echo "<h2>✓ PHP يعمل بشكل صحيح</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Server: " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<hr>";

// 2. التحقق من وجود .env
echo "<h2>2. التحقق من ملف .env</h2>";
$env_path = __DIR__ . '/.env';
if (file_exists($env_path)) {
    echo "<p style='color:green'>✓ ملف .env موجود</p>";
    echo "<p>المسار: $env_path</p>";

    // التحقق من الأذونات
    if (is_readable($env_path)) {
        echo "<p style='color:green'>✓ الملف قابل للقراءة</p>";
    } else {
        echo "<p style='color:red'>✗ الملف غير قابل للقراءة - مشكلة أذونات!</p>";
        echo "<p>الأذونات الحالية: " . substr(sprintf('%o', fileperms($env_path)), -4) . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ ملف .env غير موجود!</p>";
    echo "<p>المسار المتوقع: $env_path</p>";
    echo "<p><strong>الحل:</strong> قم برفع ملف .env إلى المسار أعلاه</p>";
}
echo "<hr>";

// 3. التحقق من env_loader.php
echo "<h2>3. التحقق من env_loader.php</h2>";
$env_loader_path = __DIR__ . '/includes/env_loader.php';
if (file_exists($env_loader_path)) {
    echo "<p style='color:green'>✓ env_loader.php موجود</p>";

    try {
        require_once $env_loader_path;
        echo "<p style='color:green'>✓ تم تحميل env_loader.php بنجاح</p>";

        // اختبار قراءة متغير
        $db_name = env('DB_NAME', 'NOT_FOUND');
        echo "<p>DB_NAME = <strong>$db_name</strong></p>";

    } catch (Exception $e) {
        echo "<p style='color:red'>✗ خطأ في تحميل env_loader.php</p>";
        echo "<p>الخطأ: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color:red'>✗ env_loader.php غير موجود</p>";
}
echo "<hr>";

// 4. التحقق من security_headers.php
echo "<h2>4. التحقق من security_headers.php</h2>";
$security_headers_path = __DIR__ . '/includes/security_headers.php';
if (file_exists($security_headers_path)) {
    echo "<p style='color:green'>✓ security_headers.php موجود</p>";
} else {
    echo "<p style='color:red'>✗ security_headers.php غير موجود</p>";
}
echo "<hr>";

// 5. التحقق من قاعدة البيانات
echo "<h2>5. التحقق من اتصال قاعدة البيانات</h2>";
try {
    // محاولة الاتصال بدون env
    $host = 'localhost';
    $db   = 'u947172334_events';
    $user = 'u947172334_events';
    $pass = 'U947172334_events';

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "<p style='color:green'>✓ الاتصال بقاعدة البيانات ناجح</p>";
    echo "<p>Database: $db</p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>✗ فشل الاتصال بقاعدة البيانات</p>";
    echo "<p>الخطأ: " . $e->getMessage() . "</p>";
}
echo "<hr>";

// 6. معلومات المسارات
echo "<h2>6. معلومات المسارات</h2>";
echo "<p><strong>Document Root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";
echo "<p><strong>Script Filename:</strong> " . $_SERVER['SCRIPT_FILENAME'] . "</p>";
echo "<p><strong>__DIR__:</strong> " . __DIR__ . "</p>";
echo "<hr>";

// 7. الملفات المطلوبة
echo "<h2>7. الملفات المطلوبة</h2>";
$required_files = [
    '.env',
    'includes/env_loader.php',
    'includes/security_headers.php',
    'includes/db.php',
    'includes/init.php'
];

foreach ($required_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    $exists = file_exists($full_path);
    $color = $exists ? 'green' : 'red';
    $icon = $exists ? '✓' : '✗';
    echo "<p style='color:$color'>$icon $file</p>";
}

echo "<hr>";
echo "<h2>✅ انتهى التشخيص</h2>";
echo "<p><strong>التاريخ:</strong> " . date('Y-m-d H:i:s') . "</p>";
?>
