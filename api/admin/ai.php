<?php

/**
 * ИИ-функции админки.
 *
 * GET  ?action=status                       — настроен ли ИИ
 * GET  ?action=review&sessionId=...         — последняя ИИ-оценка практики
 * GET  ?action=drafts&status=pending        — черновики
 * GET  ?action=sources                      — загруженные методички
 * POST action=review          {sessionId}   — запросить ИИ-оценку практики
 * POST action=source          multipart: file | text, title
 * POST action=generate_questions {sourceId, variant, count, topic}
 * POST action=generate_practical {pool, count, topic, sourceId?}
 * POST action=approve_draft   {id, edited?}
 * POST action=reject_draft    {id}
 */

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../../lib/ai_service.php';

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
        'generatorModel' => aiConfig('generator')['model'],
    ];
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
    set_time_limit(300);
    $action = (string)($input['action'] ?? '');

    switch ($action) {
        case 'review':
            $sessionId = trim((string)($input['sessionId'] ?? ''));
            $stmt = $pdo->prepare('SELECT * FROM Participant WHERE sessionId = :s LIMIT 1');
            $stmt->execute([':s' => $sessionId]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$participant || empty($participant['practicalSubmittedAt'])) {
                aiRespond(['success' => false, 'message' => 'Практика участника не найдена или не сдана'], 404);
            }
            $review = aiReviewPractical($pdo, $participant);
            writeAdminLog('ai_practical_review', 'sessionId=' . $sessionId . '; suggested=' . ($review['previousTaskScore'] + $review['newTaskScore']));
            aiRespond(['success' => true, 'review' => $review]);

        case 'source':
            $title = trim((string)($input['title'] ?? ''));
            $text = trim((string)($input['text'] ?? ''));
            $file = $_FILES['file'] ?? null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
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
            $ids = aiGenerateQuestions($pdo, (int)($input['sourceId'] ?? 0), $variant,
                (int)($input['count'] ?? 5), mb_substr(trim((string)($input['topic'] ?? '')), 0, 300));
            writeAdminLog('ai_questions_generated', 'count=' . count($ids));
            aiRespond(['success' => true, 'created' => count($ids), 'drafts' => aiListDrafts($pdo)]);

        case 'generate_practical':
            $pool = (string)($input['pool'] ?? 'second');
            if (!in_array($pool, ['first', 'second'], true)) {
                throw new InvalidArgumentException('Неизвестный пул заданий');
            }
            $sourceId = (int)($input['sourceId'] ?? 0);
            $ids = aiGeneratePracticalTasks($pdo, $pool, (int)($input['count'] ?? 2),
                mb_substr(trim((string)($input['topic'] ?? '')), 0, 300), $sourceId > 0 ? $sourceId : null);
            writeAdminLog('ai_practical_generated', 'count=' . count($ids));
            aiRespond(['success' => true, 'created' => count($ids), 'drafts' => aiListDrafts($pdo)]);

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
} catch (AiRequestException | InvalidArgumentException $e) {
    aiRespond(['success' => false, 'message' => $e->getMessage()], $e instanceof AiRequestException ? 502 : 400);
} catch (Throwable $e) {
    logError($e, 'api/admin/ai.php');
    aiRespond(['success' => false, 'message' => 'Произошла ошибка сервера'], 500);
}
