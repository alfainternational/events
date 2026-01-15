<?php
/**
 * Multi-Day Booking Helper Functions
 * ?????? ?????? ???????? ?????? ??????
 */

/**
 * ?????? ?????? ????? ????? ?????
 * @param array $post ?????? POST
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
        // ??? ????
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
        // ???? ??????
        $daysType = $post['daysType'] ?? 'consecutive';
        $timingType = $post['timingType'] ?? 'unified';
        
        $result['unified_timing'] = ($timingType === 'unified');
        
        // ??? ????????
        $dates = [];
        
        if ($daysType === 'consecutive') {
            // ???? ???????
            $startDate = $post['consecutiveStartDate'] ?? '';
            $endDate = $post['consecutiveEndDate'] ?? '';
            
            if ($startDate && $endDate) {
                $dates = get_date_range($startDate, $endDate);
            }
        } else {
            // ???? ???????
            $separateDates = $post['separateDate'] ?? [];
            if (is_array($separateDates)) {
                $dates = array_filter($separateDates);
                sort($dates);
            }
        }
        
        // ?????? ???????
        if ($timingType === 'unified') {
            // ??? ????? ????? ??????
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
            // ??? ????? ??? ???
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
            
            // ??????? ??? ???? ???
            if (!empty($result['event_days'])) {
                $result['first_time'] = $result['event_days'][0]['start_time'];
                $result['last_time'] = $result['event_days'][count($result['event_days']) - 1]['end_time'];
            }
        }
        
        // ????? ??? ???? ?????
        if (!empty($dates)) {
            $result['first_date'] = $dates[0];
            $result['last_date'] = $dates[count($dates) - 1];
        }
    }
    
    return $result;
}

/**
 * ????? ???? ?????? ??? ???????
 */
function get_date_range($start_date, $end_date) {
    $dates = [];
    try {
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }
    } catch (Exception $e) {
        // Silent fail
    }
    
    return $dates;
}

/**
 * ?????? ?? ?? ???? ???? ???????? ???????? ???? ??? ??????
 */
function validate_no_friday_in_days($event_days, $location_type) {
    if ($location_type !== 'internal') {
        return true;
    }
    
    foreach ($event_days as $day) {
        $date = new DateTime($day['date']);
        if ($date->format('w') == 5) {
            return false;
        }
    }
    
    return true;
}

/**
 * ?????? ?? ?? ???? ??????? ??? ?????? ??????? ????????? ????????
 */
function validate_internal_times($event_days, $location_type) {
    if ($location_type !== 'internal') {
        return true;
    }
    
    foreach ($event_days as $day) {
        if (function_exists('validate_internal_event_time')) {
            if (!validate_internal_event_time($day['start_time'], $day['end_time'])) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * ?????? ?? ????????? ?????? ???????? ???? ?????? (????? ??????)
 */
function check_multi_day_conflicts($event_days, $hall_id, $custom_hall_name, $pdo, $exclude_event_id = null) {
    foreach ($event_days as $new_day) {
        $new_start = $new_day['start_time'];
        $new_end = $new_day['end_time'];
        $date = $new_day['date'];

        // ????? ?? ????????? ?? ??? ????? ???????
        $sql = "SELECT id, start_time, end_time, event_days_json FROM events 
                WHERE status != 'rejected'
                AND (hall_id = ? OR (custom_hall_name = ? AND custom_hall_name IS NOT NULL AND custom_hall_name != ''))
                AND (
                    (start_date <= ? AND end_date >= ?)
                )";
        
        $params = [$hall_id, $custom_hall_name, $date, $date];
        if ($exclude_event_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_event_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existing_events = $stmt->fetchAll();

        foreach ($existing_events as $event) {
            $days = json_decode($event['event_days_json'] ?? '', true);
            if ($days && is_array($days)) {
                foreach ($days as $day) {
                    if (($day['date'] ?? '') == $date) {
                        if (check_time_overlap($new_start, $new_end, $day['start_time'] ?? '', $day['end_time'] ?? '')) {
                            return ['conflict' => true, 'date' => $date, 'time' => ($day['start_time'] ?? '') . ' - ' . ($day['end_time'] ?? '')];
                        }
                    }
                }
            } else {
                // ????? ?? ???????? ???????
                if (check_time_overlap($new_start, $new_end, $event['start_time'] ?? '', $event['end_time'] ?? '')) {
                    return ['conflict' => true, 'date' => $date, 'time' => ($event['start_time'] ?? '') . ' - ' . ($event['end_time'] ?? '')];
                }
            }
        }
    }
    return ['conflict' => false];
}

/**
 * ?????? ?? ????? ?????
 */
function check_time_overlap($s1, $e1, $s2, $e2) {
    if (empty($s1) || empty($e1) || empty($s2) || empty($e2)) return false;
    return ($s1 < $e2 && $e1 > $s2);
}

/**
 * ?????? ?? ?? ??? ????? ?? ??? ?? 30 ?????
 */
function validate_duration_30min($event_days) {
    foreach ($event_days as $day) {
        if (empty($day['start_time']) || empty($day['end_time'])) continue;
        
        $start = strtotime($day['start_time']);
        $end = strtotime($day['end_time']);
        
        if ($start === false || $end === false) continue;
        
        if (($end - $start) < 1800) { // 30 ?????
            return false;
        }
    }
    return true;
}

