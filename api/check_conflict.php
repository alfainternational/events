<?php
/**
 * API: Check Time Conflict
 * التحقق من تداخل الأوقات في الوقت الفعلي (Live Checking)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/multi_day_helpers.php';

// التحقق من أنه طلب POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// قراءة البيانات
$input = json_decode(file_get_contents('php://input'), true);

$location_type = $input['location_type'] ?? 'internal';
$hall_id = $input['hall_id'] ?? null;
$custom_hall_name = $input['custom_hall_name'] ?? null;
$event_days = $input['event_days'] ?? [];
$exclude_event_id = $input['exclude_event_id'] ?? null; // لتجاهل الحدث عند التعديل

// التحقق من البيانات
if (empty($event_days)) {
    echo json_encode([
        'success' => true,
        'conflict' => false,
        'message' => 'لا توجد أيام للتحقق منها'
    ]);
    exit;
}

// للفعاليات الخارجية، لا يوجد تداخل
if ($location_type === 'external') {
    echo json_encode([
        'success' => true,
        'conflict' => false,
        'message' => 'الفعاليات الخارجية لا تحتاج للتحقق من التداخل'
    ]);
    exit;
}

try {
    $conflicts = [];

    foreach ($event_days as $day) {
        $date = $day['date'];
        $start_time = $day['start_time'];
        $end_time = $day['end_time'];

        // بناء الاستعلام
        $sql = "SELECT
                    e.id,
                    e.title,
                    e.start_time,
                    e.end_time,
                    e.start_date,
                    e.end_date,
                    e.booking_type,
                    e.event_days_json,
                    h.name as hall_name
                FROM events e
                LEFT JOIN halls h ON e.hall_id = h.id
                WHERE e.status = 'approved'
                  AND e.deleted_at IS NULL
                  AND e.location_type = 'internal'
                  AND (
                      (e.booking_type = 'single' AND ? BETWEEN e.start_date AND e.end_date)
                      OR (e.booking_type IN ('consecutive', 'non_consecutive'))
                  )";

        $params = [$date];

        // إضافة شرط القاعة
        if (!empty($hall_id)) {
            $sql .= " AND e.hall_id = ?";
            $params[] = $hall_id;
        } elseif (!empty($custom_hall_name)) {
            $sql .= " AND e.custom_hall_name = ?";
            $params[] = $custom_hall_name;
        }

        // استثناء الحدث الحالي عند التعديل
        if ($exclude_event_id) {
            $sql .= " AND e.id != ?";
            $params[] = $exclude_event_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $existing_events = $stmt->fetchAll();

        // التحقق من كل حدث موجود
        foreach ($existing_events as $existing) {
            $has_conflict = false;
            $conflict_time = '';

            if ($existing['booking_type'] === 'single') {
                // حجز يوم واحد أو متتالي - التحقق من التداخل المباشر
                if (check_time_overlap($start_time, $end_time, $existing['start_time'], $existing['end_time'])) {
                    $has_conflict = true;
                    $conflict_time = $existing['start_time'] . ' - ' . $existing['end_time'];
                }
            } else {
                // حجز أيام متعددة - التحقق من JSON
                $existing_days = json_decode($existing['event_days_json'], true);

                if (is_array($existing_days)) {
                    foreach ($existing_days as $existing_day) {
                        if ($existing_day['date'] === $date) {
                            if (check_time_overlap(
                                $start_time,
                                $end_time,
                                $existing_day['start_time'],
                                $existing_day['end_time']
                            )) {
                                $has_conflict = true;
                                $conflict_time = $existing_day['start_time'] . ' - ' . $existing_day['end_time'];
                                break;
                            }
                        }
                    }
                }
            }

            if ($has_conflict) {
                $conflicts[] = [
                    'date' => $date,
                    'date_formatted' => format_date_arabic($date),
                    'requested_time' => $start_time . ' - ' . $end_time,
                    'conflict_time' => $conflict_time,
                    'conflicting_event' => [
                        'id' => $existing['id'],
                        'title' => $existing['title'],
                        'hall_name' => $existing['hall_name'] ?? $existing['custom_hall_name'] ?? 'غير محدد'
                    ]
                ];
            }
        }
    }

    // إعداد الاستجابة
    if (empty($conflicts)) {
        echo json_encode([
            'success' => true,
            'conflict' => false,
            'message' => '✓ جميع الأوقات متاحة',
            'available_slots' => get_available_slots($event_days, $hall_id, $custom_hall_name, $exclude_event_id, $pdo)
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'conflict' => true,
            'message' => '⚠ يوجد تداخل في الحجوزات',
            'conflicts' => $conflicts,
            'suggestions' => generate_time_suggestions($conflicts, $location_type)
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (PDOException $e) {
    error_log("Error in check_conflict.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => 'حدث خطأ أثناء التحقق من التداخل'
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * دالة للحصول على الفترات المتاحة
 */
function get_available_slots($event_days, $hall_id, $custom_hall_name, $exclude_event_id, $pdo) {
    $available = [];

    foreach ($event_days as $day) {
        $date = $day['date'];

        // جلب جميع الحجوزات في هذا اليوم
        $sql = "SELECT start_time, end_time, event_days_json, booking_type
                FROM events
                WHERE status = 'approved'
                  AND deleted_at IS NULL
                  AND location_type = 'internal'
                  AND (
                      (booking_type = 'single' AND ? BETWEEN start_date AND end_date)
                      OR booking_type IN ('consecutive', 'non_consecutive')
                  )";

        $params = [$date];

        if (!empty($hall_id)) {
            $sql .= " AND hall_id = ?";
            $params[] = $hall_id;
        } elseif (!empty($custom_hall_name)) {
            $sql .= " AND custom_hall_name = ?";
            $params[] = $custom_hall_name;
        }

        if ($exclude_event_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_event_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();

        // تجميع جميع الفترات المحجوزة
        $booked_slots = [];
        foreach ($bookings as $booking) {
            if ($booking['booking_type'] === 'single') {
                $booked_slots[] = [
                    'start' => $booking['start_time'],
                    'end' => $booking['end_time']
                ];
            } else {
                $days = json_decode($booking['event_days_json'], true);
                if (is_array($days)) {
                    foreach ($days as $d) {
                        if ($d['date'] === $date) {
                            $booked_slots[] = [
                                'start' => $d['start_time'],
                                'end' => $d['end_time']
                            ];
                        }
                    }
                }
            }
        }

        // حساب الفترات المتاحة (8 صباحاً - 4 مساءً)
        $available[$date] = calculate_free_slots('08:00:00', '16:00:00', $booked_slots);
    }

    return $available;
}

/**
 * حساب الفترات الفارغة
 */
function calculate_free_slots($day_start, $day_end, $booked_slots) {
    if (empty($booked_slots)) {
        return [['start' => $day_start, 'end' => $day_end]];
    }

    // ترتيب الحجوزات حسب وقت البدء
    usort($booked_slots, function($a, $b) {
        return strcmp($a['start'], $b['start']);
    });

    $free_slots = [];
    $current_time = $day_start;

    foreach ($booked_slots as $slot) {
        if ($current_time < $slot['start']) {
            $free_slots[] = [
                'start' => $current_time,
                'end' => $slot['start']
            ];
        }
        if ($slot['end'] > $current_time) {
            $current_time = $slot['end'];
        }
    }

    // إضافة الفترة الأخيرة إن وجدت
    if ($current_time < $day_end) {
        $free_slots[] = [
            'start' => $current_time,
            'end' => $day_end
        ];
    }

    return $free_slots;
}

/**
 * توليد اقتراحات بديلة
 */
function generate_time_suggestions($conflicts, $location_type) {
    $suggestions = [];

    if ($location_type === 'internal') {
        $suggestions[] = 'يمكنك اختيار وقت مختلف في نفس اليوم';
        $suggestions[] = 'أو اختيار يوم آخر متاح';
        $suggestions[] = 'أو اختيار قاعة أخرى';
    }

    return $suggestions;
}

/**
 * تنسيق التاريخ بالعربية
 */
function format_date_arabic($date) {
    $dayNames = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    $monthNames = [
        1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
        'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'
    ];

    $timestamp = strtotime($date);
    $dayOfWeek = $dayNames[date('w', $timestamp)];
    $day = date('j', $timestamp);
    $month = $monthNames[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);

    return "$dayOfWeek $day $month $year";
}
?>
