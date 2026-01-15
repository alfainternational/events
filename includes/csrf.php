<?php
/**
 * نظام حماية CSRF (Cross-Site Request Forgery)
 */

/**
 * توليد رمز CSRF جديد وتخزينه في الجلسة
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * التحقق من صحة رمز CSRF
 * @param string $token الرمز المرسل من النموذج
 * @return bool
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * طباعة حقل CSRF مخفي للنماذج
 */
function csrf_field() {
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * التحقق من CSRF من البيانات المرسلة
 * @throws Exception إذا كان الرمز غير صحيح
 */
function verify_csrf() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    if (!validate_csrf_token($token)) {
        http_response_code(403);
        die('خطأ في التحقق من الأمان (CSRF Token Invalid). يرجى المحاولة مرة أخرى.');
    }
}
?>
