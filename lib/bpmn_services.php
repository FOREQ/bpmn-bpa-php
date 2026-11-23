<?php

/**
 * Внешние сервисы для работы со схемами (вызываются по HTTP, код не копируется):
 *
 *   BPMN-Calculator (FOREQ/BPMN-Calculator, backend/server.py)
 *     CALCULATOR_URL=http://calculator-api:3001
 *     POST /upload/ (multipart file) → {analyze: {CC,CO,CS,DC,DP,S,R,M,T,Q}, issues: {...}}
 *
 *   cct-mcp «BPMN-инструменты» (FOREQ/try_yernaz, src/cctbpmn/mcp)
 *     CCT_MCP_URL=http://cct-mcp:8765/mcp, CCT_MCP_TOKEN=...
 *     JSON-RPC tools/call: validate_ir, layout {ir, return_xml: true}
 */

require_once __DIR__ . '/ai_client.php';

final class BpmnServiceException extends RuntimeException
{
}

/** Веса показателей сложности (как в BPMN-Calculator, backend/server.py calculate_complexity). */
const CALCULATOR_WEIGHTS = [
    'CC' => 0.25, 'CO' => 0.05, 'CS' => 0.05, 'DC' => 0.20, 'DP' => 0.07,
    'S' => 0.10, 'R' => 0.06, 'M' => 0.15, 'T' => 0.04, 'Q' => 0.03,
];

/** Поля расчёта сложности в практическом задании → коды калькулятора. */
const COMPLEXITY_FIELD_CODES = [
    'contacts' => 'CC',
    'incomingDocuments' => 'DC',
    'generatedDocuments' => 'DP',
    'steps' => 'S',
    'businessRoles' => 'R',
    'managers' => 'M',
    'handoffs' => 'T',
];

const ISSUE_LABELS = [
    'gateway_conditions' => 'Условия у шлюзов',
    'process_gaps' => 'Разрывы процесса',
    'multi_incoming' => 'Несколько входов в задачу',
    'gateway_sync' => 'Синхронизация шлюзов',
    'unnamed_events' => 'События без названия',
    'document_binding' => 'Привязка документов',
    'unnamed_gateways' => 'Шлюзы без названия',
    'xor_event_gateway' => 'Нужен событийный шлюз вместо XOR',
    'orphan_elements' => 'Несвязанные элементы',
    'unnamed_tasks' => 'Задачи без названия',
    'untyped_tasks' => 'Задачи без типа',
    'lanes_pools' => 'Дорожки и пулы',
    'terminology' => 'Терминология (рекомендация)',
];

function calculatorEnabled(): bool
{
    return aiEnv('CALCULATOR_URL') !== '';
}

function cctMcpEnabled(): bool
{
    return aiEnv('CCT_MCP_URL') !== '';
}

/** @return array{analyze: array<string,int>, issues: array<string,array>} */
function calculatorAnalyze(string $xml): array
{
    $base = rtrim(aiEnv('CALCULATOR_URL'), '/');
    if ($base === '') {
        throw new BpmnServiceException('Калькулятор не настроен: задайте CALCULATOR_URL');
    }

    $boundary = '----bpmn' . bin2hex(random_bytes(8));
    $body = "--{$boundary}\r\n"
        . "Content-Disposition: form-data; name=\"file\"; filename=\"diagram.bpmn\"\r\n"
        . "Content-Type: application/xml\r\n\r\n"
        . $xml . "\r\n--{$boundary}--\r\n";

    try {
        [$status, $response] = aiHttpPost($base . '/upload/', ['Content-Type: multipart/form-data; boundary=' . $boundary], $body, 60);
    } catch (Throwable $e) {
        throw new BpmnServiceException('Калькулятор недоступен: ' . $e->getMessage());
    }
    $json = json_decode($response, true);
    if ($status !== 200 || !is_array($json) || !isset($json['analyze'])) {
        throw new BpmnServiceException('Калькулятор вернул ошибку HTTP ' . $status);
    }

    $analyze = [];
    foreach (CALCULATOR_WEIGHTS as $code => $_) {
        $analyze[$code] = (int)($json['analyze'][$code] ?? 0);
    }

    return ['analyze' => $analyze, 'issues' => is_array($json['issues'] ?? null) ? $json['issues'] : []];
}

/** Сложность по тем же правилам, что в калькуляторе; $codes — какие показатели учитывать. */
function complexityScore(array $analyze, ?array $codes = null): float
{
    $sum = 0.0;
    foreach (CALCULATOR_WEIGHTS as $code => $weight) {
        if ($codes === null || in_array($code, $codes, true)) {
            $sum += ($analyze[$code] ?? 0) * $weight;
        }
    }

    return round($sum, 2);
}

/** Правильные ответы для задания на расчёт сложности по показателям калькулятора. */
function complexityAnswers(array $analyze, array $fields): array
{
    $answers = [];
    $codes = [];
    foreach ($fields as $field) {
        $code = COMPLEXITY_FIELD_CODES[$field['id']] ?? null;
        if ($code !== null) {
            $answers[$field['id']] = $analyze[$code] ?? 0;
            $codes[] = $code;
        }
    }
    $answers['complexity'] = complexityScore($analyze, $codes);

    return $answers;
}

/** Плоский список замечаний калькулятора для показа и для ИИ. */
function calculatorIssueList(array $issues, bool $withRecommendations = false): array
{
    $list = [];
    foreach ($issues as $key => $items) {
        if (!$withRecommendations && $key === 'terminology') {
            continue;
        }
        if (!is_array($items) || !$items) {
            continue;
        }
        $label = ISSUE_LABELS[$key] ?? $key;
        foreach (array_slice($items, 0, 10) as $item) {
            $text = is_array($item)
                ? ($item['message'] ?? $item['text'] ?? (($item['name'] ?? '') !== '' ? $item['name'] : ('элемент ' . ($item['id'] ?? '?'))))
                : (string)$item;
            $list[] = $label . ': ' . mb_substr((string)$text, 0, 300);
        }
    }

    return $list;
}

/** Вызов инструмента cct-mcp. Возвращает result (content, structuredContent, isError). */
function cctMcpCall(string $tool, array $arguments, int $timeout = 300): array
{
    $url = aiEnv('CCT_MCP_URL');
    if ($url === '') {
        throw new BpmnServiceException('Сборка схем не настроена: задайте CCT_MCP_URL');
    }

    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    $token = aiEnv('CCT_MCP_TOKEN');
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $payload = json_encode([
        'jsonrpc' => '2.0',
        'id' => random_int(1, PHP_INT_MAX),
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => $arguments],
    ], JSON_UNESCAPED_UNICODE);

    try {
        [$status, $response] = aiHttpPost($url, $headers, $payload, $timeout);
    } catch (Throwable $e) {
        throw new BpmnServiceException('Сервис сборки схем недоступен: ' . $e->getMessage());
    }
    $json = json_decode($response, true);
    if ($status !== 200 || !is_array($json)) {
        throw new BpmnServiceException('Сервис сборки схем вернул ошибку HTTP ' . $status);
    }
    if (isset($json['error'])) {
        throw new BpmnServiceException('Сервис сборки схем: ' . ($json['error']['message'] ?? 'ошибка'));
    }

    return (array)($json['result'] ?? []);
}

function cctMcpText(array $result): string
{
    $parts = [];
    foreach ((array)($result['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text') {
            $parts[] = (string)$block['text'];
        }
    }

    return implode("\n", $parts);
}

/** @return array{errors: string, warnings: string, ok: bool} */
function cctValidateIr(array $ir): array
{
    $result = cctMcpCall('validate_ir', ['ir' => $ir], 60);
    $data = (array)($result['structuredContent'] ?? []);
    $errors = (array)($data['errors'] ?? []);

    return [
        'ok' => empty($result['isError']) && !$errors,
        'errors' => $errors ? implode("\n", array_map('strval', $errors)) : (!empty($result['isError']) ? cctMcpText($result) : ''),
        'warnings' => implode("\n", array_map('strval', (array)($data['warnings'] ?? []))),
    ];
}

function cctLayout(array $ir): string
{
    $result = cctMcpCall('layout', ['ir' => $ir, 'return_xml' => true]);
    if (!empty($result['isError'])) {
        throw new BpmnServiceException('Схема не собрана: ' . mb_substr(cctMcpText($result), 0, 1000));
    }
    $xml = (string)($result['structuredContent']['bpmn_xml'] ?? '');
    if ($xml === '') {
        foreach ((array)($result['content'] ?? []) as $block) {
            if (str_starts_with(ltrim((string)($block['text'] ?? '')), '<?xml')) {
                $xml = (string)$block['text'];
            }
        }
    }
    if ($xml === '') {
        throw new BpmnServiceException('Сервис сборки не вернул BPMN XML');
    }

    return $xml;
}
