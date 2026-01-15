<?php
/**
 * API: Get Booked Dates
 * الحصول على الأيام المحجوزة لعرضها في Calendar
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

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
$month = $input['month'] ?? date('Y-m'); // Format: YYYY-MM

// التحقق من صحة الشهر
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid month format']);
    exit;
}

try {
    // بناء الاستعلام
    $sql = "SELECT
                e.id,
                e.title,
                e.start_date,
                e.end_date,
                e.start_time,
                e.end_time,
                e.location_type,
                e.hall_id,
                e.custom_hall_name,
                e.booking_type,
                e.unified_timing,
                e.event_days_json
            FROM events e
            WHERE e.status = 'approved'
              AND e.deleted_at IS NULL
              AND e.location_type = ?
              AND (
                  DATE_FORMAT(e.start_date, '%Y-%m') = ?
                  OR DATE_FORMAT(e.end_date, '%Y-%m') = ?
                  OR (e.start_date < CONCAT(?, '-01') AND e.end_date >= LAST_DAY(CONCAT(?, '-01')))
              )";

    $params = [$location_type, $month, $month, $month, $month];

    // إضافة شرط القاعة للفعاليات الداخلية
    if ($location_type === 'internal') {
        if (!empty($hall_id)) {
            $sql .= " AND e.hall_id = ?";
            $params[] = $hall_id;
        } elseif (!empty($custom_hall_name)) {
            $sql .= " AND e.custom_hall_name = ?";
            $params[] = $custom_hall_name;
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    // تنظيم البيانات حسب التاريخ
    $booked_dates = [];

    foreach ($events as $event) {
        // معالجة حسب نوع الحجز
        if ($event['booking_type'] === 'single' || empty($event['event_days_json'])) {
            // حجز يوم واحد
            $dates = [];
            $current = new DateTime($event['start_date']);
            $end = new DateTime($event['end_date']);

            while ($current <= $end) {
                $dates[] = $current->format('Y-m-d');
                $current->modify('+1 day');
            }

            foreach ($dates as $date) {
                if (!isset($booked_dates[$date])) {
                    $booked_dates[$date] = [];
                }

                $booked_dates[$date][] = [
                    'event_id' => $event['id'],
                    'title' => $event['title'],
                    'start_time' => $event['start_time'],
                    'end_time' => $event['end_time'],
                    'booking_type' => 'single'
                ];
            }
        } else {
            // حجز أيام متعددة
            $event_days = json_decode($event['event_days_json'], true);

            if (is_array($event_days)) {
                foreach ($event_days as $day) {
                    $date = $day['date'];

                    if (!isset($booked_dates[$date])) {
                        $booked_dates[$date] = [];
                    }

                    $booked_dates[$date][] = [
                        'event_id' => $event['id'],
                        'title' => $event['title'],
                        'start_time' => $day['start_time'],
                        'end_time' => $day['end_time'],
                        'booking_type' => $event['booking_type']
                    ];
                }
            }
        }
    }

    // الاستجابة
    echo json_encode([
        'success' => true,
        'booked_dates' => $booked_dates,
        'month' => $month,
        'total_events' => count($events)
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error in get_booked_dates.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => 'حدث خطأ أثناء جلب البيانات'
    ], JSON_UNESCAPED_UNICODE);
}
?>
