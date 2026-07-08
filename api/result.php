<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/practical_data.php';
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

    $taskIds = $participant['practicalTaskIds']
        ? json_decode($participant['practicalTaskIds'], true)
        : [];

    $tasks = [];

    foreach ($taskIds as $taskId) {
        $task = getPracticalTask($taskId);

        if ($task) {
            $tasks[] = $task;
        }
    }

    $practicalScoreTotal = $participant['practicalScoreTotal'];

    respondJson([
        'success' => true,
        'participant' => [
            'sessionId' => $participant['sessionId'],
            'fullName' => $participant['fullName'],
            'email' => $participant['email'],
            'phone' => $participant['phone'],
            'organization' => $participant['organization'],
            'variantId' => $participant['variantId'],
            'createdAt' => $participant['createdAt'],
        ],
        'testResult' => [
            'score' => $participant['score'],
            'total' => $participant['total'],
            'percent' => $participant['percent'],
            'status' => $participant['status'],
            'submittedAt' => $participant['submittedAt'],
        ],
        'practical' => [
            'tasks' => $tasks,
            'complexityVariantId' => $participant['complexityVariantId'],
            'submittedAt' => $participant['practicalSubmittedAt'],
            'isSubmitted' => $participant['practicalSubmittedAt'] !== null,
            'scores' => [
                'previousTaskScore' => $participant['practicalPreviousScore'],
                'newTaskScore' => $participant['practicalNewScore'],
                'metricsScore' => $participant['practicalMetricsScore'],
                'total' => $practicalScoreTotal,
                'gradedAt' => $participant['practicalGradedAt'],
            ],
            'isGraded' => $participant['practicalGradedAt'] !== null,
        ]
    ]);
} catch (Throwable $e) {
    logError($e, 'api/result.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}