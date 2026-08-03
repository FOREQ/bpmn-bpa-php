<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/test_data.php';
require_once __DIR__ . '/../lib/test_service.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function storedTestResult(array $participant): array
{
    return [
        'score' => is_numeric($participant['score'] ?? null) ? (int)$participant['score'] : 0,
        'total' => is_numeric($participant['total'] ?? null) ? (int)$participant['total'] : 0,
        'percent' => is_numeric($participant['percent'] ?? null) ? (float)$participant['percent'] : 0,
        'status' => (string)($participant['status'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson([
        'success' => false,
        'message' => 'Метод не поддерживается'
    ], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respondJson([
        'success' => false,
        'message' => 'Некорректный JSON'
    ], 400);
}

if (!csrfVerify($input['csrf_token'] ?? '')) {
    respondJson([
        'success' => false,
        'message' => 'Ошибка безопасности. Обновите страницу и попробуйте снова.'
    ], 403);
}

$sessionId = trim($input['sessionId'] ?? '');
$answers = $input['answers'] ?? null;

if ($sessionId === '') {
    respondJson([
        'success' => false,
        'message' => 'sessionId не передан'
    ], 400);
}

if (!is_array($answers)) {
    respondJson([
        'success' => false,
        'message' => 'Ответы не переданы'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("SELECT * FROM Participant WHERE sessionId = :sessionId LIMIT 1");
    $stmt->execute([
        ':sessionId' => $sessionId
    ]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        respondJson([
            'success' => false,
            'message' => 'Участник не найден'
        ], 404);
    }

    if (in_array($participant['status'] ?? null, ['passed', 'failed'], true)) {
        respondJson([
            'success' => false,
            'message' => 'Тест уже был отправлен',
            'result' => storedTestResult($participant),
        ], 409);
    }

    $variantId = $participant['variantId'];
    $variant = $TEST_DATA[$variantId] ?? null;

    if (!$variant) {
        respondJson([
            'success' => false,
            'message' => 'Вариант теста не найден'
        ], 500);
    }

    foreach ($variant as $question) {
        if (!array_key_exists((string)$question['id'], $answers)) {
            respondJson([
                'success' => false,
                'message' => 'Ответьте на все вопросы'
            ], 400);
        }
    }

    $grade = gradeAnswers($variant, $answers);

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET
            answers = :answers,
            score = :score,
            total = :total,
            percent = :percent,
            status = :status,
            submittedAt = CURRENT_TIMESTAMP,
            updatedAt = CURRENT_TIMESTAMP
        WHERE sessionId = :sessionId
          AND (status IS NULL OR status NOT IN ('passed', 'failed'))
    ");

    $stmt->execute([
        ':answers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
        ':score' => $grade['score'],
        ':total' => $grade['total'],
        ':percent' => $grade['percent'],
        ':status' => $grade['status'],
        ':sessionId' => $sessionId
    ]);

    if ($stmt->rowCount() !== 1) {
        $stmt = $pdo->prepare("
            SELECT score, total, percent, status
            FROM Participant
            WHERE sessionId = :sessionId
            LIMIT 1
        ");
        $stmt->execute([':sessionId' => $sessionId]);
        $storedParticipant = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        respondJson([
            'success' => false,
            'message' => 'Тест уже был отправлен',
            'result' => storedTestResult($storedParticipant),
        ], 409);
    }

    respondJson([
        'success' => true,
        'message' => 'Тест отправлен',
        'result' => $grade
    ]);
} catch (Throwable $e) {
    logError($e, 'api/submit_test.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}
