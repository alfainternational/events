<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/nvidia_assistant_proxy.php';

$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if (nvidia_allowed_origin($origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!nvidia_allowed_origin($origin)) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'origin not allowed']]);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'method not allowed']]);
    exit;
}

$authorization = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($authorization === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strcasecmp($name, 'Authorization') === 0) {
            $authorization = (string) $value;
            break;
        }
    }
}
try {
    $request = nvidia_validate_request($origin, $authorization, (string) file_get_contents('php://input'));
} catch (InvalidArgumentException $exception) {
    http_response_code($exception->getMessage() === 'invalid_key' ? 401 : 400);
    echo json_encode(['error' => ['message' => $exception->getMessage()]]);
    exit;
}

$handle = curl_init('https://integrate.api.nvidia.com/v1/chat/completions');
if ($handle === false) {
    http_response_code(502);
    echo json_encode(['error' => ['message' => 'provider unavailable']]);
    exit;
}
$received = '';
curl_setopt_array($handle, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($request['body'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $request['key'],
        'Accept: application/json',
        'Content-Type: application/json',
    ],
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$received): int {
        if (strlen($received) + strlen($chunk) > 1048576) {
            return 0;
        }
        $received .= $chunk;
        return strlen($chunk);
    },
]);
$ok = curl_exec($handle);
$upstreamStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
curl_close($handle);
if ($ok === false) {
    http_response_code(502);
    echo json_encode(['error' => ['message' => 'provider unavailable']]);
    exit;
}
[$status, $payload] = nvidia_public_response($upstreamStatus, $received);
http_response_code($status);
echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
