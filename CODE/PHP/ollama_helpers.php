<?php
/**
 * Ollama integration helpers for NexGen.
 *
 * Optimized for qwen3:4b-instruct on CPU-first development machines:
 * - thinking disabled by default
 * - shorter generations
 * - one shared HTTP transport for generate/chat/tool calls
 * - keep-alive enabled to avoid reloading the model for every question
 */

if (!defined('OLLAMA_HOST')) {
    define('OLLAMA_HOST', 'http://127.0.0.1:11434');
}
if (!defined('OLLAMA_MODEL')) {
    define('OLLAMA_MODEL', 'qwen3:4b-instruct');
}
if (!defined('OLLAMA_TIMEOUT')) {
    define('OLLAMA_TIMEOUT', 45);
}

$GLOBALS['OLLAMA_LAST_ERROR'] = null;

function ollama_set_last_error(?string $message): void {
    $GLOBALS['OLLAMA_LAST_ERROR'] = $message;
}

function ollama_last_error(): ?string {
    return $GLOBALS['OLLAMA_LAST_ERROR'] ?? null;
}

/**
 * Low-level POST to the Ollama REST API.
 * Returns decoded JSON as an array, or null on failure.
 */
function ollama_post($path, array $payload) {
    ollama_set_last_error(null);
    $url = rtrim(OLLAMA_HOST, '/') . $path;

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        $message = 'Could not encode request JSON: ' . json_last_error_msg();
        ollama_set_last_error($message);
        error_log('[Ollama] ' . $message);
        return null;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $errorNo = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($errorNo !== 0) {
        $message = "cURL error ({$errorNo}): {$curlError}";
        ollama_set_last_error($message);
        error_log('[Ollama] ' . $message);
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $message = "HTTP {$httpCode}: " . substr((string)$response, 0, 1000);
        ollama_set_last_error($message);
        error_log('[Ollama] ' . $message);
        return null;
    }

    $decoded = json_decode((string)$response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        $message = 'Invalid JSON response: ' . json_last_error_msg();
        ollama_set_last_error($message);
        error_log('[Ollama] ' . $message);
        return null;
    }

    return $decoded;
}

/**
 * Single-turn completion.
 */
function ollama_generate($prompt, array $options = []) {
    $payload = [
        'model' => $options['model'] ?? OLLAMA_MODEL,
        'prompt' => $prompt,
        'stream' => false,
        'think' => $options['think'] ?? false,
        'keep_alive' => $options['keep_alive'] ?? '30m',
        'options' => [
            'temperature' => $options['temperature'] ?? 0.2,
            'num_predict' => $options['num_predict'] ?? 96,
        ],
    ];

    if (!empty($options['system'])) {
        $payload['system'] = $options['system'];
    }
    if (!empty($options['format'])) {
        $payload['format'] = $options['format'];
    }
    if (isset($options['num_ctx'])) {
        $payload['options']['num_ctx'] = (int)$options['num_ctx'];
    }

    $result = ollama_post('/api/generate', $payload);
    if (!$result || !array_key_exists('response', $result)) {
        return null;
    }

    $answer = trim((string)$result['response']);
    if (!empty($options['return_thinking'])) {
        return [
            'answer' => $answer,
            'thinking' => isset($result['thinking']) ? trim((string)$result['thinking']) : null,
        ];
    }

    return $answer;
}

/**
 * Raw multi-turn chat. Returns the full Ollama response so callers can inspect
 * message.tool_calls, message.content and timing metadata.
 */
function ollama_chat_raw(array $messages, array $tools = [], array $options = []): ?array {
    $payload = [
        'model' => $options['model'] ?? OLLAMA_MODEL,
        'messages' => array_values($messages),
        'stream' => false,
        'think' => $options['think'] ?? false,
        'keep_alive' => $options['keep_alive'] ?? '30m',
        'options' => [
            'temperature' => $options['temperature'] ?? 0.1,
            'num_predict' => $options['num_predict'] ?? 96,
        ],
    ];

    if (!empty($tools)) {
        $payload['tools'] = array_values($tools);
    }
    if (!empty($options['format'])) {
        $payload['format'] = $options['format'];
    }
    if (isset($options['num_ctx'])) {
        $payload['options']['num_ctx'] = (int)$options['num_ctx'];
    }

    $result = ollama_post('/api/chat', $payload);
    if (!$result || !isset($result['message']) || !is_array($result['message'])) {
        if ($result) {
            error_log('[Ollama] Chat response was missing message: ' . substr(json_encode($result), 0, 1000));
        }
        return null;
    }

    return $result;
}

/**
 * Backward-compatible plain chat helper.
 */
function ollama_chat(array $messages, array $options = []) {
    $tools = [];
    if (!empty($options['tools']) && is_array($options['tools'])) {
        $tools = $options['tools'];
    }

    $result = ollama_chat_raw($messages, $tools, $options);
    if (!$result) {
        return null;
    }

    $message = $result['message'];
    $answer = trim((string)($message['content'] ?? ''));

    if (!empty($options['return_thinking'])) {
        return [
            'answer' => $answer,
            'thinking' => isset($message['thinking']) ? trim((string)$message['thinking']) : null,
        ];
    }

    return $answer;
}