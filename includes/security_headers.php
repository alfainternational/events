<?php
/**
 * Security Headers
 * إضافة العناوين الأمنية لحماية التطبيق من الثغرات الشائعة
 */

// منع عرض الصفحة في إطار خارجي (Clickjacking Protection)
header("X-Frame-Options: SAMEORIGIN");

// منع MIME type sniffing
header("X-Content-Type-Options: nosniff");

// تفعيل XSS Protection في المتصفحات القديمة
header("X-XSS-Protection: 1; mode=block");

// Referrer Policy - التحكم في معلومات الإحالة
header("Referrer-Policy: strict-origin-when-cross-origin");

// Content Security Policy (CSP)
// ملاحظة: يمكن تخصيصها حسب احتياجات المشروع
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
    "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com https://cdnjs.cloudflare.com",
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
    "img-src 'self' data: https:",
    "connect-src 'self'",
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'"
];
header("Content-Security-Policy: " . implode("; ", $csp));

// Permissions Policy (Feature Policy)
// تحديد المميزات المسموح باستخدامها
$permissions = [
    "geolocation=()",
    "microphone=()",
    "camera=()",
    "payment=()",
    "usb=()",
    "magnetometer=()",
    "gyroscope=()",
    "accelerometer=()"
];
header("Permissions-Policy: " . implode(", ", $permissions));

// HTTPS Enforcement (في بيئة الإنتاج فقط)
if (env('APP_ENV') === 'production' && env('COOKIE_SECURE', false)) {
    // Strict Transport Security - إجبار استخدام HTTPS
    // max-age=31536000 = سنة واحدة
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");

    // التحقق من أن الطلب قادم عبر HTTPS
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        // إعادة التوجيه إلى HTTPS
        $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirect_url", true, 301);
        exit();
    }
}

// إضافة Cache-Control headers للصفحات الديناميكية
// منع التخزين المؤقت للصفحات الحساسة
if (!defined('ALLOW_CACHE') || !ALLOW_CACHE) {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
}

// تعيين نوع المحتوى وال encoding
header("Content-Type: text/html; charset=UTF-8");

/**
 * دالة لإضافة CSP nonce للسكربتات inline
 * استخدام: <script nonce="<?php echo csp_nonce(); ?>">
 */
function csp_nonce() {
    static $nonce = null;

    if ($nonce === null) {
        $nonce = base64_encode(random_bytes(16));
        // تحديث CSP header لإضافة nonce
        // ملاحظة: هذا سيتطلب تعديل header السابق
    }

    return $nonce;
}

/**
 * دالة لتنظيف Output وإضافة أمان إضافي
 */
function secure_output($text) {
    return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>
