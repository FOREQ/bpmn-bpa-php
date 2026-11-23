<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/test_data.php';
require_once __DIR__ . '/../../lib/content_bank.php';
require_once __DIR__ . '/../../lib/test_service.php';
require_once __DIR__ . '/../../lib/practical_data.php';
require_once __DIR__ . '/../../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function previewResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function previewText(string $ru, string $kk): string
{
    return i18nLocale() === 'kk' ? $kk : $ru;
}

// Keep a few independent previews so opening another tab does not invalidate this one.
$previews = $_SESSION['admin_test_previews'] ?? [];
if (!is_array($previews)) {
    $previews = [];
}
foreach ($previews as $key => $preview) {
    if (!is_array($preview) || (int)($preview['created'] ?? 0) < time() - 3600) {
        unset($previews[$key]);
    }
}
$_SESSION['admin_test_previews'] = $previews;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $variantId = (string)($_GET['variant'] ?? '');
    if (!in_array($variantId, ['A', 'B', 'C'], true)) {
        previewResponse(['success' => false, 'message' => previewText('Выберите вариант A, B или C', 'A, B немесе C нұсқасын таңдаңыз')], 400);
    }

    try {
        $pdo = getDb();
        $variant = testVariantWithCustom($pdo, $variantId);
        if ($variant === null) {
            throw new RuntimeException('Test variant missing');
        }
        $assigned = assignedTestQuestions($variant);
        $taskIds = $variantId === 'A' ? ['family-2', '101'] : randomPracticalTaskIds($pdo);
        $complexityId = $variantId === 'A' ? '1' : randomComplexityVariantId();
        $tasks = array_map(static fn(string $id): ?array => getPracticalTask($id, $pdo), $taskIds);
        $complexityVariant = getComplexityVariant($complexityId);
        if (in_array(null, $tasks, true) || $complexityVariant === null) {
            throw new RuntimeException('Practical preview task missing');
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['admin_test_previews'] = array_slice($previews, -4, null, true);
        $_SESSION['admin_test_previews'][$token] = [
            'variant' => $variantId,
            'question_ids' => questionOrder($assigned),
            'created' => time(),
        ];

        previewResponse([
            'success' => true,
            'token' => $token,
            'variantId' => $variantId,
            'questions' => publicQuestions($assigned, questionOrder($assigned), optionOrder($assigned)),
            'practical' => ['tasks' => $tasks, 'complexityVariant' => $complexityVariant],
        ]);
    } catch (Throwable $e) {
        logError($e, 'api/admin/test_preview.php GET');
        previewResponse(['success' => false, 'message' => previewText('Не удалось загрузить пробный тест', 'Сынақ тестін жүктеу мүмкін болмады')], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    previewResponse(['success' => false, 'message' => previewText('Метод не поддерживается', 'Бұл әдіске қолдау көрсетілмейді')], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || !csrfVerify($input['csrf_token'] ?? null)) {
    previewResponse(['success' => false, 'message' => previewText('Ошибка безопасности. Обновите страницу.', 'Қауіпсіздік қатесі. Бетті жаңартыңыз.')], 403);
}

$token = (string)($input['token'] ?? '');
$answers = $input['answers'] ?? null;
if (!preg_match('/^[a-f0-9]{32}$/', $token) || !isset($previews[$token])) {
    previewResponse(['success' => false, 'message' => previewText('Пробный тест устарел. Начните заново.', 'Сынақ тестінің мерзімі өтті. Қайта бастаңыз.')], 410);
}
if (!is_array($answers)) {
    previewResponse(['success' => false, 'message' => previewText('Ответы не переданы', 'Жауаптар берілмеді')], 400);
}

try {
    $preview = $previews[$token];
    $variant = testVariantWithCustom(getDb(), (string)$preview['variant']);
    $byId = [];
    foreach ($variant ?? [] as $question) {
        $byId[(string)$question['id']] = $question;
    }

    $assigned = [];
    foreach ($preview['question_ids'] as $id) {
        $id = (string)$id;
        if (!isset($byId[$id])) {
            throw new RuntimeException('Assigned preview question missing');
        }
        $question = $byId[$id];
        $answer = $answers[$id] ?? null;
        if (!is_scalar($answer) || !preg_match('/^[0-9]+$/', (string)$answer)
            || !array_key_exists((int)$answer, $question['options'])) {
            previewResponse(['success' => false, 'message' => previewText('Ответьте на все вопросы', 'Барлық сұраққа жауап беріңіз')], 400);
        }
        $assigned[] = $question;
    }

    $result = gradeAnswers($assigned, $answers);
    $review = [];
    foreach ($assigned as $index => $question) {
        $id = (string)$question['id'];
        if ((int)$answers[$id] === (int)$question['correct']) {
            continue;
        }
        $correct = $question['options'][(int)$question['correct']];
        $review[] = [
            'number' => $index + 1,
            'question' => $question['question'],
            'correct' => is_array($correct) ? ($correct['text'] ?? '') : $correct,
        ];
    }
    unset($_SESSION['admin_test_previews'][$token]);
    previewResponse(['success' => true, 'result' => $result, 'review' => $review]);
} catch (Throwable $e) {
    logError($e, 'api/admin/test_preview.php POST');
    previewResponse(['success' => false, 'message' => previewText('Не удалось проверить пробный тест', 'Сынақ тестін тексеру мүмкін болмады')], 500);
}
