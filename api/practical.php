<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/practical_data.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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

        $taskIds = json_decode($participant['practicalTaskIds'] ?? '[]', true);
        if (!is_array($taskIds)) {
            $taskIds = [];
        }

        $tasks = [];

        foreach ($taskIds as $taskId) {
            $task = getPracticalTask($taskId);

            if ($task) {
                $tasks[] = $task;
            }
        }

        $complexityVariant = getComplexityVariant($participant['complexityVariantId']);

        respondJson([
            'success' => true,
            'participant' => [
                'sessionId' => $participant['sessionId'],
                'fullName' => $participant['fullName'],
                'organization' => $participant['organization'],
                'status' => $participant['status'],
                'score' => $participant['score'],
                'total' => $participant['total'],
                'percent' => $participant['percent']
            ],
            'practical' => [
                'tasks' => $tasks,
                'complexityVariant' => $complexityVariant,
                'answers' => $participant['practicalAnswers']
                    ? json_decode($participant['practicalAnswers'], true)
                    : null,
                'submittedAt' => $participant['practicalSubmittedAt']
            ]
        ]);
    } catch (Throwable $e) {
    logError($e, 'api/practical.php GET');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $complete = (bool)($input['complete'] ?? true);

    if ($sessionId === '') {
        respondJson([
            'success' => false,
            'message' => 'sessionId не передан'
        ], 400);
    }

    if (!is_array($answers)) {
        respondJson([
            'success' => false,
            'message' => 'Ответы практики не переданы'
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

        if ($complete) {
            $stmt = $pdo->prepare("
                UPDATE Participant
                SET
                    practicalAnswers = :practicalAnswers,
                    practicalSubmittedAt = CURRENT_TIMESTAMP,
                    updatedAt = CURRENT_TIMESTAMP
                WHERE sessionId = :sessionId
            ");
        } else {
            $stmt = $pdo->prepare("
                UPDATE Participant
                SET
                    practicalAnswers = :practicalAnswers,
                    updatedAt = CURRENT_TIMESTAMP
                WHERE sessionId = :sessionId
            ");
        }

        $stmt->execute([
            ':practicalAnswers' => json_encode($answers, JSON_UNESCAPED_UNICODE),
            ':sessionId' => $sessionId
        ]);

        respondJson([
            'success' => true,
            'message' => $complete
                ? 'Практическое задание отправлено'
                : 'Черновик практического задания сохранен',
            'complete' => $complete
        ]);
    } catch (Throwable $e) {
    logError($e, 'api/practical.php POST');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}
}

respondJson([
    'success' => false,
    'message' => 'Метод не поддерживается'
], 405);
