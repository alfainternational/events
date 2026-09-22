<?php
declare(strict_types=1);

function activity_report_date(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Riyadh'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException('صيغة التاريخ غير صحيحة.');
    }
    return $date;
}

function activity_report_validate_range(string $from, string $to): array
{
    $start = activity_report_date($from);
    $end = activity_report_date($to);
    if ($start > $end || $start->diff($end)->days > 366) {
        throw new InvalidArgumentException('نطاق التاريخ غير صحيح.');
    }
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

function activity_report_time($value): string
{
    return substr(trim((string) ($value ?? '')), 0, 5);
}

function activity_report_days(array $row, string $from, string $to): array
{
    $days = [];
    $decoded = json_decode((string) ($row['event_days_json'] ?? ''), true);
    if (is_array($decoded) && count($decoded) > 0) {
        foreach ($decoded as $day) {
            $date = (string) ($day['date'] ?? '');
            if ($date < $from || $date > $to) {
                continue;
            }
            $days[] = [
                'date' => $date,
                'start_time' => activity_report_time($day['start_time'] ?? $row['start_time'] ?? ''),
                'end_time' => activity_report_time($day['end_time'] ?? $row['end_time'] ?? ''),
            ];
        }
    } else {
        $start = max((string) ($row['start_date'] ?? ''), $from);
        $end = min((string) ($row['end_date'] ?? $row['start_date'] ?? ''), $to);
        if ($start <= $end) {
            $cursor = activity_report_date($start);
            $last = activity_report_date($end);
            while ($cursor <= $last) {
                $days[] = [
                    'date' => $cursor->format('Y-m-d'),
                    'start_time' => activity_report_time($row['start_time'] ?? ''),
                    'end_time' => activity_report_time($row['end_time'] ?? ''),
                ];
                $cursor = $cursor->modify('+1 day');
            }
        }
    }
    usort($days, fn (array $left, array $right): int => strcmp($left['date'] . $left['start_time'], $right['date'] . $right['start_time']));
    return $days;
}

function activity_report_normalize_rows(array $rows, string $from, string $to): array
{
    activity_report_validate_range($from, $to);
    $events = [];
    foreach ($rows as $row) {
        $days = activity_report_days($row, $from, $to);
        if (count($days) === 0) {
            continue;
        }
        $internal = ($row['location_type'] ?? '') === 'internal';
        $location = $internal
            ? ($row['custom_hall_name'] ?: ($row['hall_name'] ?: 'داخل الكلية'))
            : ($row['external_address'] ?: 'موقع خارجي');
        $events[] = [
            'id' => (string) $row['id'],
            'title' => trim((string) $row['title']),
            'organizing_dept' => trim((string) ($row['organizing_dept'] ?? '')),
            'location_type' => $internal ? 'internal' : 'external',
            'location' => trim((string) $location),
            'start_date' => $days[0]['date'],
            'end_date' => $days[count($days) - 1]['date'],
            'days' => $days,
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    usort($events, fn (array $left, array $right): int => strcmp($left['start_date'] . $left['title'], $right['start_date'] . $right['title']));
    return $events;
}

function activity_report_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $statement->execute([$table]);
    return (int) $statement->fetchColumn() > 0;
}

function activity_report_table(PDO $pdo, array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (activity_report_table_exists($pdo, $candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException('تعذر العثور على جدول الفعاليات.');
}
