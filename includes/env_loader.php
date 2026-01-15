<?php
/**
 * Environment Variables Loader
 * تحميل متغيرات البيئة من ملف .env
 */

class EnvLoader {
    private static $loaded = false;
    private static $env = [];

    /**
     * تحميل ملف .env
     */
    public static function load($file_path = null) {
        if (self::$loaded) {
            return;
        }

        if ($file_path === null) {
            $file_path = __DIR__ . '/../.env';
        }

        if (!file_exists($file_path)) {
            throw new Exception('.env file not found at: ' . $file_path);
        }

        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // تجاهل التعليقات والأسطر الفارغة
            if (strpos(trim($line), '#') === 0 || trim($line) === '') {
                continue;
            }

            // فصل المفتاح والقيمة
            list($key, $value) = explode('=', $line, 2);

            $key = trim($key);
            $value = trim($value);

            // إزالة علامات الاقتباس إن وجدت
            $value = trim($value, '"\'');

            // حفظ في المصفوفة
            self::$env[$key] = $value;

            // تعيين في متغيرات البيئة
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * الحصول على قيمة متغير البيئة
     */
    public static function get($key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }

        if (array_key_exists($key, self::$env)) {
            return self::$env[$key];
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return $default;
    }

    /**
     * التحقق من وجود متغير
     */
    public static function has($key) {
        if (!self::$loaded) {
            self::load();
        }

        return array_key_exists($key, self::$env) ||
               array_key_exists($key, $_ENV) ||
               getenv($key) !== false;
    }
}

/**
 * Helper function للوصول السريع
 */
function env($key, $default = null) {
    return EnvLoader::get($key, $default);
}

// تحميل .env تلقائياً
try {
    EnvLoader::load();
} catch (Exception $e) {
    // في حالة عدم وجود .env، نستخدم القيم الافتراضية
    error_log('Warning: ' . $e->getMessage());
}
?>
