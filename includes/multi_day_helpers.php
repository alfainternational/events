<?php
/**
 * Multi-Day Booking Helper Functions
 * معالجة بيانات الحجوزات متعددة الأيام
 */

/**
 * معالجة بيانات نموذج الحجز المرن
 * @param array $post بيانات POST
 * @return array ['booking_type', 'unified_timing', 'event_days', 'first_date', 'last_date', 'first_time', 'last_time']
 */
function process_multi_day_booking($post) {
    $bookingType = $post['bookingType'] ?? 'single_day';
    $result = [
        'booking_type' => $bookingType,
        'unified_timing' => true,
        'event_days' => [],
        'first_date' => null,
        'last_date' => null,
        'first_time' => null,
        'last_time' => null
    ];
    
    if ($bookingType === 'single_day') {
        // يوم واحد
        $date = $post['singleDate'] ?? '';
        $startTime = $post['singleStartTime'] ?? '';
        $endTime = $post['singleEndTime'] ?? '';
        
        $result['event_days'][] = [
            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
        
        $result['first_date'] = $date;
        $result['last_date'] = $date;
        $result['first_time'] = $startTime;
        $result['last_time'] = $endTime;
        
    } elseif ($bookingType === 'multiple_days') {
        // أيام متعددة
        $daysType = $post['daysType'] ?? 'consecutive';
        $timingType = $post['timingType'] ?? 'unified';
        
        $result['unified_timing'] = ($timingType === 'unified');
        
        // جمع التواريخ
        $dates = [];
        
        if ($daysType === 'consecutive') {
            // أيام متتالية
            $startDate = $post['consecutiveStartDate'] ?? '';
            $endDate = $post['consecutiveEndDate'] ?? '';
            
            if ($startDate && $endDate) {
                $dates = get_date_range($startDate, $endDate);
            }
        } else {
            // أيام متباعدة
            $separateDates = $post['separateDate'] ?? [];
            if (is_array($separateDates)) {
                $dates = array_filter($separateDates);
                sort($dates);
            }
        }
        
        // معالجة الأوقات
        if ($timingType === 'unified') {
            // نفس الوقت لجميع الأيام
            $startTime = $post['unifiedStartTime'] ?? '';
            $endTime = $post['unifiedEndTime'] ?? '';
            
            foreach ($dates as $date) {
                $result['event_days'][] = [
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime
                ];
            }
            
            $result['first_time'] = $startTime;
            $result['last_time'] = $endTime;
            
        } else {
            // وقت مختلف لكل يوم
            $dayTimes = $post['dayTime'] ?? [];
            
            foreach ($dayTimes as $dayTime) {
                if (isset($dayTime['date'], $dayTime['start'], $dayTime['end'])) {
                    $result['event_days'][] = [
                        'date' => $dayTime['date'],
                        'start_time' => $dayTime['start'],
                        'end_time' => $dayTime['end']
                    ];
                }
            }
            
            // استخدام أول وآخر وقت
            if (!empty($result['event_days'])) {
                $result['first_time'] = $result['event_days'][0]['start_time'];
                $result['last_time'] = $result['event_days'][count($result['event_days']) - 1]['end_time'];
            }
        }
        
        // تحديد أول وآخر تاريخ
        if (!empty($dates)) {
            $result['first_date'] = $dates[0];
            $result['last_date'] = $dates[count($dates) - 1];
        }
    }
    
    return $result;
}

/**
 * إنشاء نطاق تواريخ بين تاريخين
 */
function get_date_range($start_date, $end_date) {
    $dates = [];
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    
    while ($current <= $end) {
        $dates[] = $current->format('Y-m-d');
        $current->modify('+1 day');
    }
    
    return $dates;
}

/**
 * التحقق من أن جميع أيام الفعالية الداخلية ليست يوم الجمعة
 */
function validate_no_friday_in_days($event_days, $location_type) {
    if ($location_type !== 'internal') {
        return true;
    }
    
    foreach ($event_days as $day) {
        $date = new DateTime($day['date']);
        // 5 = الجمعة
        if ($date->format('w') == 5) {
            return false;
        }
    }
    
    return true;
}

/**
 * التحقق من أن جميع ا

لأوقات ضمن النطاق المسموح للفعاليات الداخلية
 */
function validate_internal_times($event_days, $location_type) {
    if ($location_type !== 'internal') {
        return true;
    }
    
    foreach ($event_days as $day) {
        if (!validate_internal_event_time($day['start_time'], $day['end_time'])) {
            return false;
        }
    }
    
    return true;
}

/**
 * التحقق من التعارضات للأيام المتعددة
 */
function check_multi_day_conflicts($event_days, $hall_id, $custom_hall_name, $pdo, $exclude_event_id = null) {
    foreach ($event_days as $day) {
        $sql = "SELECT id FROM events e
                WHERE e.status != 'rejected'
                AND JSON_CONTAINS(e.event_days_json, JSON_QUOTE(?), '$.*.date')
                AND (e.hall_id = ? OR (e.custom_hall_name = ? AND e.custom_hall_name IS NOT NULL))";
        
        $params = [$day['date'], $hall_id, $custom_hall_name];
        
        if ($exclude_event_id) {
            $sql .= " AND e.id != ?";
            $params[] = $exclude_event_id;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($stmt->fetch()) {
            return ['conflict' => true, 'date' => $day['date']];
        }
    }
    
    return ['conflict' => false];
}
?>
