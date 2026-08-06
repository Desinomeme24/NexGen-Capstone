<?php
/**
 * Ollama integration helpers for the NexGen chatbot.
 * Talks to a local Ollama server (default: http://127.0.0.1:11434) running
 * a pulled model (default: qwen3:8b).
 *
 * To override the defaults, add this to config.php (before this file is
 * required, or anywhere earlier in the request):
 *
 *   define('OLLAMA_HOST', 'http://127.0.0.1:11434');
 *   define('OLLAMA_MODEL', 'qwen3:8b');
 *   define('OLLAMA_TIMEOUT', 25);
 *
 * Qwen3 models are "thinking" models: they can emit an internal reasoning
 * trace before the final answer. Control this per-call with the 'think'
 * option on ollama_generate()/ollama_chat() (see below). Ollama returns the
 * reasoning separately in $result['response']['thinking'] (generate) or
 * $result['message']['thinking'] (chat) when thinking is enabled — it is
 * NOT mixed into the visible response text.
 */

if (!defined('OLLAMA_HOST')) {
    define('OLLAMA_HOST', 'http://127.0.0.1:11434');
}
if (!defined('OLLAMA_MODEL')) {
    define('OLLAMA_MODEL', 'qwen3:8b');
}
if (!defined('OLLAMA_TIMEOUT')) {
    // Generation itself is usually fast, but the FIRST request after Ollama
    // has been idle has to load the model into RAM first, which can take
    // 20-30s depending on the machine (qwen3:8b is larger than llama3.2:3b,
    // so cold starts may run a bit longer). 60s covers a cold start; once
    // the model is warm, replies come back in a couple of seconds.
    define('OLLAMA_TIMEOUT', 60);
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
 *   - think        (bool)   Qwen3 thinking mode. true = model reasons before
 *                           answering (slower, often more accurate on math/
 *                           logic); false = skip reasoning (faster, matches
 *                           old llama3.2:3b-style instant replies). Omit to
 *                           use the model's own default.
 *
 * Returns the trimmed response text, or null on failure. When 'think' is
 * enabled, pass return_thinking = true in $options to instead get back
 * ['answer' => ..., 'thinking' => ...].
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

    if (array_key_exists('think', $options)) {
        $payload['think'] = (bool)$options['think'];
    }

    $result = ollama_post('/api/generate', $payload);

    if (!$result || !isset($result['response'])) {
        return null;
    }

    $answer = trim($result['response']);

    if (!empty($options['return_thinking'])) {
        return [
            'answer' => $answer,
            'thinking' => isset($result['thinking']) ? trim($result['thinking']) : null,
        ];
    }

    return $answer;
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

    if (array_key_exists('think', $options)) {
        $payload['think'] = (bool)$options['think'];
    }

    $result = ollama_post('/api/chat', $payload);

    if (!$result || !isset($result['message']['content'])) {
        return null;
    }

    $answer = trim($result['message']['content']);

    if (!empty($options['return_thinking'])) {
        return [
            'answer' => $answer,
            'thinking' => isset($result['message']['thinking']) ? trim($result['message']['thinking']) : null,
        ];
    }

    return $answer;
}