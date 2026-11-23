<?php

/**
 * Минимальный клиент для LLM по HTTP.
 *
 * Все настройки берутся из переменных окружения (см. .env.example),
 * ключи в коде и в базе не хранятся.
 *
 * Поддерживаются два формата API:
 *   - openai    — любой OpenAI-совместимый endpoint /chat/completions
 *                 (OpenAI, Azure-прокси, vLLM, Ollama, LM Studio, LiteLLM и т.п.)
 *   - anthropic — Anthropic Messages API (/v1/messages)
 *
 * Для разных задач можно выдать разные ключи и модели:
 *   AI_GRADER_*    — проверка практических заданий
 *   AI_GENERATOR_* — генерация вопросов и заданий
 * Если они не заданы, используются общие AI_*.
 */

final class AiNotConfiguredException extends RuntimeException
{
}

final class AiRequestException extends RuntimeException
{
}

/** Жёсткий потолок ответа: длинные ответы тормозят общий GPU (сайт ЦЦТ на той же модели). */
const AI_MAX_TOKENS_CAP = 20000;

function aiEnv(string $name, string $default = ''): string
{
    $value = getenv($name);

    return $value === false ? $default : trim((string)$value);
}

/** @return array{style:string,base_url:string,api_key:string,model:string,timeout:int,temperature:float,max_tokens:int,json_mode:bool,thinking:bool} */
function aiConfig(string $purpose = 'default'): array
{
    $prefix = match ($purpose) {
        'grader' => 'AI_GRADER_',
        'generator' => 'AI_GENERATOR_',
        default => 'AI_',
    };

    $pick = static function (string $key, string $default = '') use ($prefix): string {
        $specific = $prefix !== 'AI_' ? aiEnv($prefix . $key) : '';

        return $specific !== '' ? $specific : aiEnv('AI_' . $key, $default);
    };

    $style = strtolower($pick('API_STYLE', 'openai'));
    if (!in_array($style, ['openai', 'anthropic'], true)) {
        $style = 'openai';
    }

    return [
        'style' => $style,
        'base_url' => rtrim($pick('BASE_URL'), '/'),
        'api_key' => $pick('API_KEY'),
        'model' => $pick('MODEL'),
        'timeout' => max(5, (int)$pick('TIMEOUT', match ($purpose) { 'generator' => '300', 'grader' => '240', default => '120' })),
        'temperature' => (float)$pick('TEMPERATURE', $purpose === 'grader' ? '0.1' : '0.7'),
        'max_tokens' => min(AI_MAX_TOKENS_CAP, max(256, (int)$pick('MAX_TOKENS', match ($purpose) { 'generator' => '16000', 'grader' => '8000', default => '4000' }))),
        'json_mode' => aiFlag($pick('JSON_MODE', '0')),
        // Рассуждения модели (Qwen3 и т.п.) по умолчанию выключены: они съедают лимит токенов.
        'thinking' => aiFlag($pick('THINKING', '0')),
    ];
}

function aiFlag(string $value): bool
{
    return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
}

function aiEnabled(string $purpose = 'default'): bool
{
    $config = aiConfig($purpose);

    return $config['base_url'] !== '' && $config['model'] !== '';
}

/**
 * Отправляет диалог в модель и возвращает текст ответа.
 *
 * @param array<int,array{role:string,content:string}> $messages
 */
function aiChat(array $messages, string $purpose = 'default', array $overrides = []): string
{
    $config = array_merge(aiConfig($purpose), $overrides);

    if ($config['base_url'] === '' || $config['model'] === '') {
        throw new AiNotConfiguredException('ИИ не настроен: задайте AI_BASE_URL и AI_MODEL в .env');
    }

    if ($config['style'] === 'anthropic') {
        $system = [];
        $chat = [];
        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system[] = $message['content'];
            } else {
                $chat[] = ['role' => $message['role'], 'content' => $message['content']];
            }
        }
        $url = $config['base_url'] . '/messages';
        $payload = [
            'model' => $config['model'],
            'max_tokens' => $config['max_tokens'],
            'temperature' => $config['temperature'],
            'messages' => $chat,
        ];
        if ($system) {
            $payload['system'] = implode("\n\n", $system);
        }
        $headers = [
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01',
        ];
        if ($config['api_key'] !== '') {
            $headers[] = 'x-api-key: ' . $config['api_key'];
        }
    } else {
        $url = $config['base_url'] . '/chat/completions';
        $payload = [
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => $config['temperature'],
            'max_tokens' => $config['max_tokens'],
        ];
        if ($config['json_mode']) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        // vLLM/LiteLLM: включает или выключает режим рассуждений в шаблоне чата Qwen.
        if (!aiFlag(aiEnv('AI_NO_TEMPLATE_KWARGS', '0'))) {
            $payload['chat_template_kwargs'] = ['enable_thinking' => (bool)$config['thinking']];
        }
        $headers = ['Content-Type: application/json'];
        if ($config['api_key'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $config['api_key'];
        }
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $slot = aiAcquireSlot();
    try {
        [$status, $body] = aiSendWithRetry($url, $headers, $encoded, (int)$config['timeout']);
    } finally {
        aiReleaseSlot($slot);
    }

    $json = json_decode($body, true);
    if (in_array($status, [502, 503, 504], true)) {
        throw new AiRequestException('ИИ временно недоступен (нет связи с сервером модели). Повторите через несколько минут.');
    }
    if ($status === 429) {
        throw new AiRequestException('ИИ занят другими запросами. Повторите через минуту.');
    }
    if ($status < 200 || $status >= 300 || !is_array($json)) {
        $detail = is_array($json) ? ($json['error']['message'] ?? $json['message'] ?? '') : '';
        throw new AiRequestException('ИИ вернул ошибку HTTP ' . $status . ($detail !== '' ? ': ' . mb_substr((string)$detail, 0, 300) : ''));
    }

    if ($config['style'] === 'anthropic') {
        $text = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        $finish = (string)($json['stop_reason'] ?? '');
        $truncated = $finish === 'max_tokens';
    } else {
        $text = (string)($json['choices'][0]['message']['content'] ?? '');
        $finish = (string)($json['choices'][0]['finish_reason'] ?? '');
        $truncated = $finish === 'length';
    }

    // Блок рассуждений, если модель всё же его выдала, ответом не считается.
    $visible = trim(preg_replace('#<think>.*?(</think>|$)#s', '', $text) ?? $text);
    if ($visible === '') {
        if ($truncated) {
            throw new AiRequestException('ИИ не уложился в лимит ответа (' . $config['max_tokens']
                . ' токенов) и ничего не вернул. Повторите или уменьшите объём задания.');
        }
        throw new AiRequestException('ИИ вернул пустой ответ');
    }

    return $text;
}

/** Запрос к ИИ с повтором при 429 (лимит параллельных запросов ключа). */
function aiSendWithRetry(string $url, array $headers, string $body, int $timeout): array
{
    $attempts = max(1, (int)aiEnv('AI_RETRY_429', '3') + 1);
    for ($i = 1; ; $i++) {
        try {
            $response = aiHttpPost($url, $headers, $body, $timeout);
        } catch (AiRequestException $e) {
            throw new AiRequestException('ИИ временно недоступен: ' . $e->getMessage());
        }
        if ($response[0] !== 429 || $i >= $attempts) {
            return $response;
        }
        usleep((int)(min(20, 2 ** $i + random_int(0, 1000) / 1000) * 1_000_000));
    }
}

/**
 * Семафор на весь портал: не больше AI_MAX_PARALLEL (по умолчанию 2) одновременных запросов к ИИ.
 * Слоты — файлы с flock; блокировка снимается сама, если процесс PHP упал.
 *
 * @return resource
 */
function aiAcquireSlot()
{
    $slots = max(1, (int)aiEnv('AI_MAX_PARALLEL', '2'));
    $wait = max(0, (int)aiEnv('AI_QUEUE_WAIT', '60'));
    $dir = aiEnv('AI_SLOTS_DIR', rtrim(sys_get_temp_dir(), '/') . '/bpmn_ai_slots');
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new AiRequestException('Не удалось создать папку очереди ИИ: ' . $dir);
    }

    $deadline = microtime(true) + $wait;
    do {
        for ($n = 1; $n <= $slots; $n++) {
            $handle = @fopen($dir . '/slot' . $n . '.lock', 'c');
            if ($handle === false) {
                continue;
            }
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                return $handle;
            }
            fclose($handle);
        }
        usleep(500_000);
    } while (microtime(true) < $deadline);

    throw new AiRequestException('ИИ занят другими запросами, повторите позже.');
}

/** @param resource $handle */
function aiReleaseSlot($handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** @return array{0:int,1:string} */
function aiHttpPost(string $url, array $headers, string $body, int $timeout): array
{
    if (!preg_match('#^https?://#i', $url)) {
        throw new AiNotConfiguredException('AI_BASE_URL должен начинаться с http:// или https://');
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new AiRequestException('Не удалось подключиться к ИИ: ' . $error);
        }

        return [$status, (string)$response];
    }

    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $body,
        'timeout' => $timeout,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new AiRequestException('Не удалось подключиться к ИИ');
    }
    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int)$m[1];
        }
    }

    return [$status, $response];
}

/** Достаёт JSON из ответа модели (в том числе из ```json ... ``` блоков). */
function aiExtractJson(string $text): array
{
    // Модели с рассуждениями (Qwen3, DeepSeek-R1 и т.п.) могут вернуть блок <think>…</think> перед ответом.
    $text = trim(preg_replace('#<think>.*?</think>#s', '', $text) ?? $text);
    if (preg_match('/```(?:json)?\s*(.*?)```/s', $text, $m)) {
        $text = trim($m[1]);
    }

    $decoded = json_decode($text, true);
    if (!is_array($decoded)) {
        $start = strcspn($text, '{[');
        $endObject = strrpos($text, '}');
        $endArray = strrpos($text, ']');
        $end = max($endObject === false ? -1 : $endObject, $endArray === false ? -1 : $endArray);
        if ($start < strlen($text) && $end > $start) {
            $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
        }
    }

    if (!is_array($decoded)) {
        throw new AiRequestException('ИИ вернул ответ не в формате JSON');
    }

    return $decoded;
}

function aiChatJson(array $messages, string $purpose = 'default'): array
{
    $text = aiChat($messages, $purpose);

    try {
        return aiExtractJson($text);
    } catch (AiRequestException) {
        // Одна повторная попытка с явной просьбой вернуть только JSON.
        $messages[] = ['role' => 'assistant', 'content' => $text];
        $messages[] = ['role' => 'user', 'content' => 'Верни только валидный JSON без пояснений и без markdown.'];

        return aiExtractJson(aiChat($messages, $purpose));
    }
}
