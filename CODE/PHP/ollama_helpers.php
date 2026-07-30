<?php
/**
 * Ollama integration helpers for the NexGen chatbot.
 * Talks to a local Ollama server (default: http://127.0.0.1:11434) running
 * a pulled model (default: llama3.2:3b).
 *
 * To override the defaults, add this to config.php (before this file is
 * required, or anywhere earlier in the request):
 *
 *   define('OLLAMA_HOST', 'http://127.0.0.1:11434');
 *   define('OLLAMA_MODEL', 'llama3.2:3b');
 *   define('OLLAMA_TIMEOUT', 25);
 */

if (!defined('OLLAMA_HOST')) {
    define('OLLAMA_HOST', 'http://127.0.0.1:11434');
}
if (!defined('OLLAMA_MODEL')) {
    define('OLLAMA_MODEL', 'llama3.2:3b');
}
if (!defined('OLLAMA_TIMEOUT')) {
    // Keep this short-ish so the AJAX chat request doesn't hang the UI if
    // Ollama is slow or unreachable. 3B is fast, so 25s is generous.
    define('OLLAMA_TIMEOUT', 25);
}

/**
 * Low-level POST to the Ollama REST API.
 * Returns the decoded JSON body as an array, or null on any failure.
 * Never throws — callers should treat null as "AI unavailable" and fall
 * back to a canned reply.
 */
function ollama_post($path, array $payload) {
    $url = rtrim(OLLAMA_HOST, '/') . $path;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT => OLLAMA_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $response = curl_exec($ch);
    $errorNo = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($errorNo !== 0) {
        error_log("[Ollama] cURL error ({$errorNo}): {$curlError}");
        return null;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("[Ollama] HTTP {$httpCode}: " . substr((string)$response, 0, 500));
        return null;
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('[Ollama] Invalid JSON response: ' . json_last_error_msg());
        return null;
    }

    return $decoded;
}

/**
 * Single-turn completion (used for RAG answers and forecast narration).
 *
 * $options:
 *   - model        (string) override OLLAMA_MODEL
 *   - system       (string) system prompt
 *   - temperature  (float)  default 0.3
 *   - num_predict  (int)    max tokens to generate, default 350
 *
 * Returns the trimmed response text, or null on failure.
 */
function ollama_generate($prompt, array $options = []) {
    $payload = [
        'model' => $options['model'] ?? OLLAMA_MODEL,
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => $options['temperature'] ?? 0.3,
            'num_predict' => $options['num_predict'] ?? 350,
        ],
    ];

    if (!empty($options['system'])) {
        $payload['system'] = $options['system'];
    }

    $result = ollama_post('/api/generate', $payload);

    if (!$result || !isset($result['response'])) {
        return null;
    }

    return trim($result['response']);
}

/**
 * Multi-turn chat completion, in case you want to add conversation-style
 * prompting later (e.g. multi-step forecasting dialogue).
 *
 * $messages = [['role' => 'system'|'user'|'assistant', 'content' => '...'], ...]
 * Returns the trimmed assistant reply text, or null on failure.
 */
function ollama_chat(array $messages, array $options = []) {
    $payload = [
        'model' => $options['model'] ?? OLLAMA_MODEL,
        'messages' => $messages,
        'stream' => false,
        'options' => [
            'temperature' => $options['temperature'] ?? 0.3,
            'num_predict' => $options['num_predict'] ?? 350,
        ],
    ];

    $result = ollama_post('/api/chat', $payload);

    if (!$result || !isset($result['message']['content'])) {
        return null;
    }

    return trim($result['message']['content']);
}