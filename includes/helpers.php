<?php
/**
 * دوال مساعدة للنظام
 */

/**
 * توليد رمز تعديل عشوائي فريد (8 خانات)
 * @return string
 */
function generate_edit_token() {
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $token = '';
    for ($i = 0; $i < 8; $i++) {
        $token .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $token;
}

/**
 * التحقق من إمكانية تعديل الفعالية حسب الوقت المتبقي
 * @param string $event_start_date تاريخ بدء الفعالية
 * @param string $event_start_time وقت بدء الفعالية
 * @param PDO $pdo اتصال قاعدة البيانات
 * @return array ['can_edit' => bool, 'message' => string]
 */
function can_edit_event($event_start_date, $event_start_time, $pdo) {
    // الحصول على إعداد الحد الأدنى للساعات قبل الفعالية
    $deadline_hours = (int)get_system_setting('edit_deadline_hours', 1, $pdo);
    
    // حساب وقت بدء الفعالية
    $event_start = strtotime($event_start_date . ' ' . $event_start_time);
    
    // حساب الوقت الحالي
    $now = time();
    
    // حساب الفرق بالساعات
    $hours_diff = ($event_start - $now) / 3600;
    
    if ($hours_diff < $deadline_hours) {
        return [
            'can_edit' => false,
            'message' => "عذراً، لا يمكن التعديل على الطلب. الوقت المتبقي قبل الفعالية أقل من $deadline_hours ساعة."
        ];
    }
    
    return [
        'can_edit' => true,
        'message' => ''
    ];
}

/**
 * الحصول على قيمة إعداد من النظام
 * @param string $key مفتاح الإعداد
 * @param mixed $default القيمة الافتراضية
 * @param PDO $pdo اتصال قاعدة البيانات
 * @return mixed
 */
function get_system_setting($key, $default, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    return $result ? $result['setting_value'] : $default;
}

/**
 * تحديث قيمة إعداد في النظام
 * @param string $key مفتاح الإعداد
 * @param mixed $value القيمة الجديدة
 * @param PDO $pdo اتصال قاعدة البيانات
 * @return bool
 */
function update_system_setting($key, $value, $pdo) {
    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    return $stmt->execute([$value, $key]);
}
?>
