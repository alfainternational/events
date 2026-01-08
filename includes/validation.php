<?php
/**
 * نظام التحقق من المدخلات (Server-side Validation)
 */

class ValidationErrors {
    private $errors = [];
    
    public function add($field, $message) {
        $this->errors[$field] = $message;
    }
    
    public function has($field) {
        return isset($this->errors[$field]);
    }
    
    public function get($field) {
        return $this->errors[$field] ?? null;
    }
    
    public function all() {
        return $this->errors;
    }
    
    public function hasAny() {
        return !empty($this->errors);
    }
    
    public function first() {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}

/**
 * فحص صحة البريد الإلكتروني
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * فحص صحة رقم الجوال السعودي (يبدأ بـ 05 ويتكون من 10 أرقام)
 */
function validate_saudi_mobile($mobile) {
    $mobile = preg_replace('/[^0-9]/', '', $mobile);
    return preg_match('/^05[0-9]{8}$/', $mobile);
}

/**
 * فحص أن تاريخ الانتهاء بعد تاريخ البدء
 */
function validate_date_range($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    return $end >= $start;
}

/**
 * فحص أن وقت الانتهاء بعد وقت البدء (في نفس اليوم)
 */
function validate_time_range($start_time, $end_time) {
    $start = strtotime($start_time);
    $end = strtotime($end_time);
    return $end > $start;
}

/**
 * فحص أن الرقم موجب
 */
function validate_positive_number($number) {
    return is_numeric($number) && $number >= 0;
}

/**
 * فحص أن الأوقات للفعاليات الداخلية ضمن النطاق المسموح (8 صباحاً - 4 مساءً)
 */
function validate_internal_event_time($start_time, $end_time) {
    $start_hour = (int)date('H', strtotime($start_time));
    $start_minute = (int)date('i', strtotime($start_time));
    $end_hour = (int)date('H', strtotime($end_time));
    $end_minute = (int)date('i', strtotime($end_time));
    
    // تحويل إلى دقائق لسهولة المقارنة
    $start_minutes = $start_hour * 60 + $start_minute;
    $end_minutes = $end_hour * 60 + $end_minute;
    
    // من 8:00 صباحاً (480 دقيقة) إلى 4:00 مساءً (960 دقيقة)
    $min_time = 8 * 60;  // 8:00 AM
    $max_time = 16 * 60; // 4:00 PM
    
    return ($start_minutes >= $min_time && $end_minutes <= $max_time);
}

/**
 * فحص أن التواريخ لا تحتوي على يوم الجمعة للفعاليات الداخلية
 */
function validate_no_friday_for_internal($start_date, $end_date) {
    $start = strtotime($start_date);
    $end = strtotime($end_date);
    
    // التحقق من كل يوم في النطاق
    for ($date = $start; $date <= $end; $date = strtotime('+1 day', $date)) {
        // 5 = الجمعة في PHP (0 = الأحد، 6 = السبت)
        if (date('w', $date) == 5) {
            return false;
        }
    }
    
    return true;
}

/**
 * تنظيف النص من HTML و JavaScript
 */
function sanitize_text($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

/**
 * فحص أن النص ليس فارغاً
 */
function validate_required($value) {
    return !empty(trim($value));
}

/**
 * فحص شامل لبيانات نموذج الحجز
 */
function validate_booking_data($data, $location_type) {
    $errors = new ValidationErrors();
    
    // التحقق من الحقول الأساسية
    if (!validate_required($data['title'] ?? '')) {
        $errors->add('title', 'عنوان الفعالية مطلوب');
    }
    
    if (!validate_required($data['organizing_dept'] ?? '')) {
        $errors->add('organizing_dept', 'الجهة المنظمة مطلوبة');
    }
    
    // التحقق من رقم الجوال
    if (!validate_required($data['requester_mobile'] ?? '')) {
        $errors->add('requester_mobile', 'رقم الجوال مطلوب');
    } elseif (!validate_saudi_mobile($data['requester_mobile'])) {
        $errors->add('requester_mobile', 'رقم الجوال غير صحيح (يجب أن يبدأ بـ 05 ويتكون من 10 أرقام)');
    }
    
    // التحقق من البريد الإلكتروني (اختياري ولكن إذا أدخل يجب أن يكون صحيحاً)
    if (!empty($data['requester_email']) && !validate_email($data['requester_email'])) {
        $errors->add('requester_email', 'البريد الإلكتروني غير صحيح');
    }
    
    // التحقق من التواريخ
    if (!validate_required($data['startDate'] ?? '')) {
        $errors->add('startDate', 'تاريخ البدء مطلوب');
    }
    
    if (!validate_required($data['endDate'] ?? '')) {
        $errors->add('endDate', 'تاريخ الانتهاء مطلوب');
    }
    
    if (isset($data['startDate']) && isset($data['endDate']) && 
        !validate_date_range($data['startDate'], $data['endDate'])) {
        $errors->add('endDate', 'تاريخ الانتهاء يجب أن يكون بعد أو يساوي تاريخ البدء');
    }
    
    // التحقق من الأوقات
    if (!validate_required($data['startTime'] ?? '')) {
        $errors->add('startTime', 'وقت البدء مطلوب');
    }
    
    if (!validate_required($data['endTime'] ?? '')) {
        $errors->add('endTime', 'وقت الانتهاء مطلوب');
    }
    
    if (isset($data['startTime']) && isset($data['endTime']) && 
        !validate_time_range($data['startTime'], $data['endTime'])) {
        $errors->add('endTime', 'وقت الانتهاء يجب أن يكون بعد وقت البدء');
    }
    
    // التحقق حسب نوع الفعالية
    if ($location_type === 'internal') {
        // التحقق من قيود الوقت للفعاليات الداخلية (8 صباحاً - 4 مساءً)
        if (isset($data['startTime']) && isset($data['endTime']) && 
            !validate_internal_event_time($data['startTime'], $data['endTime'])) {
            $errors->add('time_range', 'أوقات الفعاليات الداخلية يجب أن تكون من الساعة 8 صباحاً حتى 4 مساءً فقط');
        }
        
        // التحقق من عدم اختيار يوم الجمعة
        if (isset($data['startDate']) && isset($data['endDate']) && 
            !validate_no_friday_for_internal($data['startDate'], $data['endDate'])) {
            $errors->add('friday_restriction', 'لا يمكن حجز فعاليات داخلية يوم الجمعة');
        }
        
        if (isset($data['attendees_internal']) && !validate_positive_number($data['attendees_internal'])) {
            $errors->add('attendees_internal', 'عدد الحضور يجب أن يكون رقماً موجباً');
        }
    } else {
        if (!validate_required($data['extAddress'] ?? '')) {
            $errors->add('extAddress', 'عنوان الفعالية الخارجية مطلوب');
        }
        
        if (isset($data['estimated_budget']) && !validate_positive_number($data['estimated_budget'])) {
            $errors->add('estimated_budget', 'الميزانية يجب أن تكون رقماً موجباً');
        }
    }
    
    return $errors;
}
?>
