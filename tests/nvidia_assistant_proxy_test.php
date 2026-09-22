<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/nvidia_assistant_proxy.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function rejected(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    check(false, $message);
}

$key = 'nvapi-' . str_repeat('x', 40);
$request = [
    'model' => 'nvidia/nemotron-3-super-120b-a12b',
    'temperature' => 0.6,
    'max_tokens' => 1200,
    'messages' => [
        ['role' => 'system', 'content' => 'اكتب بوضوح'],
        ['role' => 'user', 'content' => 'اكتب نصًا قصيرًا'],
    ],
    'reasoning_effort' => 'none',
];
$body = json_encode($request, JSON_UNESCAPED_UNICODE);
$validated = nvidia_validate_request('https://shamal-content-dashboard.web.app', 'Bearer ' . $key, $body);
check($validated['key'] === $key, 'bearer key was not extracted');
check($validated['body']['model'] === $request['model'], 'model was not retained');
check(count($validated['body']['messages']) === 2, 'messages were not retained');
$reordered = $request;
$reordered['messages'] = [
    ['content' => 'اكتب بوضوح', 'role' => 'system'],
    ['content' => 'اكتب نصًا قصيرًا', 'role' => 'user'],
];
check(nvidia_validate_request('https://shamal-content-dashboard.web.app', 'Bearer ' . $key, json_encode($reordered))['body']['model'] === $request['model'], 'message key order must not affect validity');

rejected(fn () => nvidia_validate_request('https://other.example', 'Bearer ' . $key, $body), 'foreign origin accepted');
rejected(fn () => nvidia_validate_request('https://shamal-content-dashboard.web.app', '', $body), 'missing key accepted');
rejected(fn () => nvidia_validate_request('https://shamal-content-dashboard.web.app', 'Bearer short', $body), 'short key accepted');
rejected(fn () => nvidia_validate_request('https://shamal-content-dashboard.web.app', 'Bearer ' . $key, json_encode($request + ['url' => 'https://other.example'])), 'alternate destination accepted');
$wrongMessages = $request;
$wrongMessages['messages'][] = ['role' => 'user', 'content' => 'extra'];
rejected(fn () => nvidia_validate_request('https://shamal-content-dashboard.web.app', 'Bearer ' . $key, json_encode($wrongMessages)), 'extra message accepted');
rejected(fn () => nvidia_validate_request('https://shamal-content-dashboard.web.app', 'Bearer ' . $key, str_repeat('x', 25000)), 'oversized request accepted');

[$status, $response] = nvidia_public_response(200, json_encode(['choices' => [['message' => ['content' => ' نص مقترح ']]]], JSON_UNESCAPED_UNICODE));
check($status === 200 && $response['choices'][0]['message']['content'] === 'نص مقترح', 'valid answer was not normalized');
[$status, $response] = nvidia_public_response(401, json_encode(['error' => ['message' => 'secret: ' . $key]]));
check($status === 401 && !str_contains(json_encode($response), $key), 'upstream error leaked key');
[$status, $response] = nvidia_public_response(422, json_encode(['error' => ['message' => 'secret: ' . $key]]));
check($status === 422 && $response['error']['message'] === 'model unavailable', 'invalid upstream model must have a safe actionable error');

echo "NVIDIA proxy helpers: OK\n";
