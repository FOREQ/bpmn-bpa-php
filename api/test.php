<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/test_data.php';
require_once __DIR__ . '/../lib/test_service.php';
require_once __DIR__ . '/../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson([
        'success' => false,
        'message' => 'Метод не поддерживается'
    ], 405);
}

$sessionId = trim($_GET['sessionId'] ?? '');

if ($sessionId === '') {
    respondJson([
        'success' => false,
        'message' => 'sessionId не передан'
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

    $variantId = $participant['variantId'];
    $variant = $TEST_DATA[$variantId] ?? null;

    if (!$variant) {
        respondJson([
            'success' => false,
            'message' => 'Вариант теста не найден'
        ], 500);
    }

    $questionOrder = json_decode($participant['questionOrder'], true);
    $optionOrder = json_decode($participant['optionOrder'], true);

    $questions = publicQuestions($variant, $questionOrder, $optionOrder);

    respondJson([
        'success' => true,
        'participant' => [
            'sessionId' => $participant['sessionId'],
            'fullName' => $participant['fullName'],
            'organization' => $participant['organization'],
            'variantId' => $participant['variantId'],
            'status' => $participant['status'],
            'score' => $participant['score'],
            'total' => $participant['total'],
            'percent' => $participant['percent'],
        ],
        'test' => [
    'variantId' => $variantId,
    'title' => 'Теоретический тест по BPA и BPMN',
    'questions' => $questions
        ]
    ]);
} catch (Throwable $e) {
    logError($e, 'api/test.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}