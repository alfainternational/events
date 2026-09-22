<?php
declare(strict_types=1);

function nvidia_allowed_origin(string $origin): bool
{
    return in_array($origin, [
        'https://shamal-content-dashboard.web.app',
        'https://shamal-content-dashboard.firebaseapp.com',
        'http://localhost:5000',
        'http://127.0.0.1:5000',
    ], true);
}

function nvidia_validate_request(string $origin, string $authorization, string $rawBody): array
{
    if (!nvidia_allowed_origin($origin)) {
        throw new InvalidArgumentException('origin_not_allowed');
    }
    if (!preg_match('/^Bearer ([A-Za-z0-9._-]{20,512})$/D', $authorization, $keyMatch)) {
        throw new InvalidArgumentException('invalid_key');
    }
    if (strlen($rawBody) > 20000) {
        throw new InvalidArgumentException('request_too_large');
    }
    $data = json_decode($rawBody, true);
    if (!is_array($data) || array_is_list($data)) {
        throw new InvalidArgumentException('invalid_request');
    }
    $allowed = ['model', 'temperature', 'max_tokens', 'messages', 'reasoning_effort'];
    if (array_diff(array_keys($data), $allowed)) {
        throw new InvalidArgumentException('invalid_request');
    }
    $model = $data['model'] ?? null;
    if (!is_string($model) || !preg_match('/^[A-Za-z0-9._-]{1,64}\/[A-Za-z0-9._-]{1,128}$/D', $model)) {
        throw new InvalidArgumentException('invalid_model');
    }
    $messages = $data['messages'] ?? null;
    if (!is_array($messages) || count($messages) !== 2) {
        throw new InvalidArgumentException('invalid_messages');
    }
    foreach (['system', 'user'] as $index => $role) {
        $message = $messages[$index] ?? null;
        if (!is_array($message) || count($message) !== 2 || array_diff(array_keys($message), ['role', 'content'])
            || ($message['role'] ?? null) !== $role
            || !is_string($message['content'])
            || trim($message['content']) === ''
            || strlen($message['content']) > 12000) {
            throw new InvalidArgumentException('invalid_messages');
        }
    }
    $temperature = $data['temperature'] ?? 0.6;
    $tokens = $data['max_tokens'] ?? 1200;
    if (!is_numeric($temperature) || $temperature < 0 || $temperature > 1
        || !is_int($tokens) || $tokens < 1 || $tokens > 4096) {
        throw new InvalidArgumentException('invalid_parameters');
    }
    $body = [
        'model' => $model,
        'messages' => $messages,
        'temperature' => (float) $temperature,
        'max_tokens' => $tokens,
        'stream' => false,
    ];
    if (isset($data['reasoning_effort'])) {
        if ($model !== 'nvidia/nemotron-3-super-120b-a12b'
            || !in_array($data['reasoning_effort'], ['none', 'low', 'high'], true)) {
            throw new InvalidArgumentException('invalid_parameters');
        }
        $body['reasoning_effort'] = $data['reasoning_effort'];
    }
    return ['key' => $keyMatch[1], 'body' => $body];
}

function nvidia_public_response(int $status, string $rawBody): array
{
    if ($status === 401 || $status === 403) {
        return [401, ['error' => ['message' => 'authentication invalid']]];
    }
    if ($status === 429) {
        return [429, ['error' => ['message' => 'rate limit']]];
    }
    if ($status === 402) {
        return [402, ['error' => ['message' => 'insufficient balance']]];
    }
    if ($status === 404 || $status === 422) {
        return [422, ['error' => ['message' => 'model unavailable']]];
    }
    if ($status !== 200 || strlen($rawBody) > 1048576) {
        return [502, ['error' => ['message' => 'provider unavailable']]];
    }
    $payload = json_decode($rawBody, true);
    $content = $payload['choices'][0]['message']['content'] ?? null;
    if (!is_string($content) || trim($content) === '') {
        return [502, ['error' => ['message' => 'empty provider response']]];
    }
    return [200, ['choices' => [['message' => ['content' => trim($content)]]]]];
}
