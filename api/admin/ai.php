<?php

/**
 * ИИ-функции админки.
 *
 * GET  ?action=status                       — настроен ли ИИ
 * GET  ?action=review&sessionId=...         — последняя ИИ-оценка практики
 * GET  ?action=drafts&status=pending        — черновики
 * GET  ?action=sources                      — загруженные методички
 * GET  ?action=variants                     — варианты теста (A, B, C и созданные генерацией) с готовностью
 * POST action=review          {sessionId}   — запросить ИИ-оценку практики
 * POST action=source          multipart: file | text, title
 * POST action=generate_questions {sourceId, variant, count, topic, template?: 'A'} — template A: 20 вопросов по образцу варианта A
 * POST action=generate_practical {pool, count, topic, sourceId?}
 * POST action=generate_complexity {count, topic}  — задание 3: текст → схема → ответы калькулятора
 * GET  ?action=job&id=...                  — статус фоновой задачи (queued|running|done|failed)
 *
 * review и generate_* выполняются фоновой задачей: ответ {jobId}, страница опрашивает action=job.
 * POST action=approve_draft   {id, edited?}
 * POST action=reject_draft    {id}
 */

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../../lib/ai_jobs.php';

header('Content-Type: application/json; charset=utf-8');

function aiRespond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function aiStatusPayload(): array
{
    return [
        'grader' => aiEnabled('grader'),
        'generator' => aiEnabled('generator'),
        'graderModel' => aiConfig('grader')['model'],
        'calculator' => calculatorEnabled(),
        'bpmnBuilder' => cctMcpEnabled(),
        'generatorModel' => aiConfig('generator')['model'],
    ];
}

/** Создаёт фоновую задачу и сразу отвечает её id. Без возможности запускать процессы выполняет её здесь же. */
function aiStartJob(PDO $pdo, string $action, array $params, string $logAction, string $logDetails = ''): never
{
    $purpose = $action === 'review' ? 'grader' : 'generator';
    if (!aiEnabled($purpose)) {
        throw new AiNotConfiguredException('ИИ не настроен: задайте AI_BASE_URL и AI_MODEL в .env');
    }
    $jobId = aiJobCreate($pdo, $action, $params);
    writeAdminLog($logAction, trim('job=' . $jobId . '; ' . $logDetails, '; '));
    if (!aiJobSpawn($jobId)) {
        aiJobRun($pdo, $jobId);
    }
    aiRespond(['success' => true, 'jobId' => $jobId, 'job' => aiJobGet($pdo, $jobId)]);
}

try {
    $pdo = getDb();
    ensureAiSchema($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'status';
        switch ($action) {
            case 'status':
                aiRespond(['success' => true, 'ai' => aiStatusPayload()]);
            case 'review':
                aiRespond(['success' => true, 'review' => aiLatestReview($pdo, trim((string)($_GET['sessionId'] ?? '')))]);
            case 'drafts':
                $status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : 'pending';
                aiRespond(['success' => true, 'drafts' => aiListDrafts($pdo, $status)]);
            case 'sources':
                aiRespond(['success' => true, 'sources' => aiListSources($pdo)]);
            case 'variants':
                aiRespond(['success' => true, 'variants' => examVariants($pdo), 'size' => EXAM_VARIANT_SIZE]);
            case 'job':
                $job = aiJobGet($pdo, (int)($_GET['id'] ?? 0));
                if (!$job) {
                    aiRespond(['success' => false, 'message' => 'Задача не найдена'], 404);
                }
                $payload = ['success' => true, 'job' => $job];
                if ($job['status'] === 'done' && str_starts_with($job['action'], 'generate_')) {
                    $payload['drafts'] = aiListDrafts($pdo);
                }
                aiRespond($payload);
        }
        aiRespond(['success' => false, 'message' => 'Неизвестное действие'], 400);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        aiRespond(['success' => false, 'message' => 'Метод не поддерживается'], 405);
    }

    $isJson = str_contains((string)($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json');
    $input = $isJson ? (json_decode((string)file_get_contents('php://input'), true) ?: []) : $_POST;

    if (!csrfVerify($input['csrf_token'] ?? null)) {
        aiRespond(['success' => false, 'message' => 'Ошибка безопасности. Обновите страницу.'], 403);
    }

    // Генерация и проверка могут занимать до пары минут.
    // Сессию отпускаем сразу, иначе другие вкладки админа ждут конца генерации.
    session_write_close();
    set_time_limit(900);
    $action = (string)($input['action'] ?? '');

    switch ($action) {
        case 'review':
            $sessionId = trim((string)($input['sessionId'] ?? ''));
            if ($sessionId === '') {
                throw new InvalidArgumentException('Не указан участник');
            }
            aiStartJob($pdo, 'review', ['sessionId' => $sessionId], 'ai_practical_review', 'sessionId=' . $sessionId);

        case 'source':
            $title = trim((string)($input['title'] ?? ''));
            $text = trim((string)($input['text'] ?? ''));
            $file = $_FILES['file'] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                    throw new InvalidArgumentException('Файл больше допустимого размера (' . ini_get('upload_max_filesize') . '). Нужно поднять upload_max_filesize на сервере.');
                }
                if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 30_000_000 || !is_uploaded_file($file['tmp_name'])) {
                    throw new InvalidArgumentException('Файл не загружен или больше 30 МБ');
                }
                $text = aiExtractSourceText($file['tmp_name'], (string)$file['name']);
                $title = $title !== '' ? $title : pathinfo((string)$file['name'], PATHINFO_FILENAME);
            }
            $id = aiSaveSource($pdo, $title, $text);
            writeAdminLog('ai_source_saved', 'id=' . $id);
            aiRespond(['success' => true, 'id' => $id, 'sources' => aiListSources($pdo)]);

        case 'generate_questions':
            $variant = (string)($input['variant'] ?? 'all');
            if (!in_array($variant, ['A', 'B', 'C', 'all'], true)) {
                throw new InvalidArgumentException('Неизвестный вариант теста');
            }
            if ((int)($input['sourceId'] ?? 0) <= 0) {
                throw new InvalidArgumentException('Сначала загрузите методичку');
            }
            aiStartJob($pdo, 'generate_questions', [
                'sourceId' => (int)$input['sourceId'], 'variant' => $variant,
                'template' => ($input['template'] ?? '') === 'A' ? 'A' : '',
                'fillVariant' => isExamVariantCode((string)($input['fillVariant'] ?? '')) ? (string)$input['fillVariant'] : '',
                'count' => (int)($input['count'] ?? 5), 'topic' => mb_substr(trim((string)($input['topic'] ?? '')), 0, 300),
            ], 'ai_questions_generated');

        case 'generate_practical':
            $pool = (string)($input['pool'] ?? 'second');
            if (!in_array($pool, ['first', 'second'], true)) {
                throw new InvalidArgumentException('Неизвестный пул заданий');
            }
            aiStartJob($pdo, 'generate_practical', [
                'pool' => $pool, 'count' => (int)($input['count'] ?? 2), 'sourceId' => (int)($input['sourceId'] ?? 0),
                'topic' => mb_substr(trim((string)($input['topic'] ?? '')), 0, 300),
            ], 'ai_practical_generated');

        case 'generate_complexity':
            if (!cctMcpEnabled() || !calculatorEnabled()) {
                throw new InvalidArgumentException('Для задания 3 нужны сервис сборки схем (CCT_MCP_URL) и калькулятор (CALCULATOR_URL)');
            }
            aiStartJob($pdo, 'generate_complexity', [
                'count' => (int)($input['count'] ?? 1), 'topic' => mb_substr(trim((string)($input['topic'] ?? '')), 0, 300),
            ], 'ai_complexity_generated');

        case 'approve_draft':
            $edited = is_array($input['edited'] ?? null) ? $input['edited'] : [];
            $publishedId = aiApproveDraft($pdo, (int)($input['id'] ?? 0), $edited);
            writeAdminLog('ai_draft_approved', 'id=' . (int)$input['id'] . '; published=' . $publishedId);
            aiRespond(['success' => true, 'publishedId' => $publishedId, 'drafts' => aiListDrafts($pdo)]);

        case 'reject_draft':
            aiRejectDraft($pdo, (int)($input['id'] ?? 0));
            writeAdminLog('ai_draft_rejected', 'id=' . (int)$input['id']);
            aiRespond(['success' => true, 'drafts' => aiListDrafts($pdo)]);
    }

    aiRespond(['success' => false, 'message' => 'Неизвестное действие'], 400);
} catch (AiNotConfiguredException $e) {
    aiRespond(['success' => false, 'notConfigured' => true, 'message' => $e->getMessage()], 503);
} catch (AiRequestException | BpmnServiceException | InvalidArgumentException $e) {
    aiRespond(['success' => false, 'message' => $e->getMessage()], $e instanceof InvalidArgumentException ? 400 : 502);
} catch (Throwable $e) {
    logError($e, 'api/admin/ai.php');
    aiRespond(['success' => false, 'message' => 'Произошла ошибка сервера'], 500);
}
