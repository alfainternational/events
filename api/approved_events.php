<?php
declare(strict_types=1);

$allowedOrigins = [
    'https://shamal-content-dashboard.web.app',
    'https://shamal-content-dashboard.firebaseapp.com',
    'http://localhost:5000',
    'http://127.0.0.1:5000',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Accept');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/reporting_api.php';

try {
    [$from, $to] = activity_report_validate_range(
        (string) ($_GET['date_from'] ?? ''),
        (string) ($_GET['date_to'] ?? '')
    );
    $eventsTable = activity_report_table($pdo, ['ev_events', 'events']);
    $hallsTable = activity_report_table($pdo, ['ev_halls', 'halls']);
    $sql = "SELECT e.id, e.title, e.organizing_dept, e.start_date, e.end_date,
                   e.start_time, e.end_time, e.location_type, e.custom_hall_name,
                   e.external_address, e.event_days_json, e.updated_at, h.name AS hall_name
            FROM {$eventsTable} e
            LEFT JOIN {$hallsTable} h ON e.hall_id = h.id
            WHERE e.status = 'approved'
              AND e.deleted_at IS NULL
              AND e.start_date <= ?
              AND e.end_date >= ?
            ORDER BY e.start_date ASC, e.id ASC";
    $statement = $pdo->prepare($sql);
    $statement->execute([$to, $from]);
    $events = activity_report_normalize_rows($statement->fetchAll(), $from, $to);
    echo json_encode([
        'success' => true,
        'source' => 'events.cartnec.com',
        'date_from' => $from,
        'date_to' => $to,
        'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Riyadh')))->format(DateTimeInterface::ATOM),
        'count' => count($events),
        'events' => $events,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_date_range', 'message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    error_log('approved events API: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'source_unavailable', 'message' => 'تعذر جلب الفعاليات حاليًا.'], JSON_UNESCAPED_UNICODE);
}
