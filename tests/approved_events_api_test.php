<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/reporting_api.php';

function expect_same($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function expect_throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}

expect_same(
    ['2026-09-01', '2026-09-30'],
    activity_report_validate_range('2026-09-01', '2026-09-30'),
    'valid monthly bounds must be accepted'
);
expect_throws(
    fn () => activity_report_validate_range('2026-09-31', '2026-09-01'),
    'invalid or reversed bounds must be rejected'
);

$rows = [[
    'id' => 17,
    'title' => 'زيارة تعريفية',
    'organizing_dept' => 'إدارة العلاقات العامة',
    'requester_email' => 'private@example.com',
    'requester_mobile' => '0500000000',
    'notes' => 'private note',
    'start_date' => '2026-09-15',
    'end_date' => '2026-10-01',
    'start_time' => '09:00:00',
    'end_time' => '11:00:00',
    'location_type' => 'external',
    'hall_name' => null,
    'custom_hall_name' => null,
    'external_address' => 'المدرسة الرابعة عشرة',
    'event_days_json' => json_encode([
        ['date' => '2026-09-16', 'start_time' => '09:00', 'end_time' => '11:00'],
        ['date' => '2026-10-01', 'start_time' => '10:00', 'end_time' => '12:00'],
    ], JSON_UNESCAPED_UNICODE),
    'updated_at' => '2026-09-10 12:30:00',
]];

$events = activity_report_normalize_rows($rows, '2026-09-01', '2026-09-30');
expect_same(1, count($events), 'overlapping approved event must be returned once');
expect_same('17', $events[0]['id'], 'event id must be stable and textual');
expect_same('external', $events[0]['location_type'], 'location type must be preserved');
expect_same('المدرسة الرابعة عشرة', $events[0]['location'], 'public venue must be exposed');
expect_same([['date' => '2026-09-16', 'start_time' => '09:00', 'end_time' => '11:00']], $events[0]['days'], 'only in-range event days may be exposed');
expect_same(false, array_key_exists('requester_email', $events[0]), 'requester email must never be exposed');
expect_same(false, array_key_exists('notes', $events[0]), 'private notes must never be exposed');

echo "approved events API helpers: OK\n";
