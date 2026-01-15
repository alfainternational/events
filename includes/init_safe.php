<?php
/**
 * Application Initialization - Safe Version
 * نسخة آمنة بدون security_headers للاختبار
 */

// تفعيل عرض الأخطاء مؤقتاً للتشخيص
ini_set('display_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة مع إعدادات أمان محسّنة
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false,
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
    'sid_length' => 48,
    'sid_bits_per_character' => 6
]);

// Session Hijacking Prevention
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// User Agent Validation
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
} elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['security_warning'] = 'جلستك انتهت لأسباب أمنية. يرجى تسجيل الدخول مرة أخرى.';
}

// تحميل الملفات الأساسية
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/rate_limiter.php';
require_once __DIR__ . '/audit.php';
?>
