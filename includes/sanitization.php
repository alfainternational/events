<?php
/**
 * دوال إضافية للتحقق من المدخلات (Input Sanitization)
 */

/**
 * تنظيف HTML مع السماح بتنسيق أساسي
 */
function sanitize_html($input, $allowedTags = '<p><br><b><i><u><strong><em>') {
    // إزالة النصوص البرمجية الخطيرة
    $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
    $input = preg_replace('/<script\b[^>]*>/is', '', $input);
    $input = preg_replace('/on\w+\s*=\s*["\'].*?["\']/i', '', $input); // Remove event handlers
    
    // السماح بتاجات محددة فقط
    return strip_tags($input, $allowedTags);
}

/**
 * التحقق من صيغة التاريخ
 */
function validate_date_format($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * التحقق من صيغة الوقت
 */
function validate_time_format($time, $format = 'H:i') {
    $t = DateTime::createFromFormat($format, $time);
    return $t && $t->format($format) === $time;
}

/**
 * التحقق من URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * تنظيف اسم الملف
 */
function sanitize_filename($filename) {
    // إزالة الأحرف غير الآمنة
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    // منع directory traversal
    $filename = str_replace(['..', '/', '\\'], '_', $filename);
    return $filename;
}

/**
 * التحقق من امتداد الملف المسموح
 */
function validate_file_extension($filename, $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowedExtensions);
}

/**
 * تحديد حجم الملف (بالبايت)
 */
function validate_file_size($fileSize, $maxSizeMB = 5) {
    $maxSizeBytes = $maxSizeMB * 1024 * 1024;
    return $fileSize <= $maxSizeBytes;
}

/**
 * التحقق من JSON
 */
function validate_json($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * تنظيف رقم الهاتف (إزالة كل شيء إلا الأرقام و +)
 */
function sanitize_phone($phone) {
    return preg_replace('/[^0-9+]/', '', $phone);
}

/**
 * التحقق من IPv4
 */
function validate_ipv4($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * التحقق من النطاق الرقمي
 */
function validate_number_range($number, $min, $max) {
    return is_numeric($number) && $number >= $min && $number <= $max;
}

/**
 * تحويل النص العربي الآمن
 */
function sanitize_arabic_text($text) {
    // إزالة الأحرف غير العربية والإنجليزية والأرقام والمسافات
    // يسمح بالعربية (0600-06FF)، الإنجليزية (A-Za-z)، الأرقام (0-9)، والمسافة والترقيم
    return preg_replace('/[^\p{Arabic}\p{L}\p{N}\s\.,!?؛،]/u', '', $text);
}

/**
 * التحقق من قوة كلمة المرور
 */
function validate_password_strength($password, $minLength = 8) {
    $errors = [];
    
    if (strlen($password) < $minLength) {
        $errors[] = "كلمة المرور يجب أن تكون على الأقل {$minLength} أحرف";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على حرف كبير واحد على الأقل";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على حرف صغير واحد على الأقل";
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على رقم واحد على الأقل";
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "كلمة المرور يجب أن تحتوي على رمز خاص واحد على الأقل";
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * التحقق من MIME type للملف
 */
function validate_mime_type($filepath, $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf']) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    
    return in_array($mimeType, $allowedTypes);
}

/**
 * التحقق من وجود SQL  Injection patterns (إضافي للـ prepared statements)
 */
function detect_sql_injection($input) {
    $patterns = [
        '/(\bUNION\b.*\bSELECT\b)/i',
        '/(\bDROP\b.*\bTABLE\b)/i',
        '/(\bINSERT\b.*\bINTO\b)/i',
        '/(\bDELETE\b.*\bFROM\b)/i',
        '/(\bUPDATE\b.*\bSET\b)/i',
        '/(--|#|\/\*|\*\/)/i' // SQL comments
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}

/**
 * التحقق من XSS patterns
 */
function detect_xss($input) {
    $patterns = [
        '/<script\b/i',
        '/javascript:/i',
        '/on\w+\s*=/i',
        '/<iframe\b/i',
        '/<object\b/i',
        '/<embed\b/i'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }
    
    return false;
}
?>
