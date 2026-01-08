<?php
// إعدادات قاعدة البيانات
$host = 'localhost';
$db   = 'shimal_events';
$user = 'root'; // افتراضي لـ XAMPP
$pass = '';     // افتراضي لـ XAMPP
$charset = 'utf8mb4';

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
     die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>
