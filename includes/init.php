<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false, // Set to true in production with HTTPS
    'cookie_samesite' => 'Lax',
    'use_strict_mode' => true,
    'sid_length' => 48,
    'sid_bits_per_character' => 6
]);

// Session Hijacking Prevention
// Regenerate session ID periodically and on privilege escalation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// User Agent Validation
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
} elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
    // Possible session hijacking
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['security_warning'] = 'جلستك انتهت لأسباب أمنية. يرجى تسجيل الدخول مرة أخرى.';
}

// IP Validation (Optional - can cause issues with dynamic IPs)
// Uncomment if you want strict IP checking
/*
if (!isset($_SESSION['ip_address'])) {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
} elseif ($_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
    session_unset();
    session_destroy();
    session_start();
}
*/

require_once 'includes/db.php';
require_once 'includes/csrf.php';
require_once 'includes/validation.php';
require_once 'includes/messages.php';
require_once 'includes/helpers.php';
require_once 'includes/rate_limiter.php';
require_once 'includes/audit.php';
?>
