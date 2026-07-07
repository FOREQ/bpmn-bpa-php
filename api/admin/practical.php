<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/practical_data.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/error_logger.php';

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

        $complexityVariant = getComplexityVariant($participant['complexityVariantId']);

        respondJson([
            'success' => true,
            'participant' => [
                'sessionId' => $participant['sessionId'],
                'fullName' => $participant['fullName'],
                'email' => $participant['email'],
                'phone' => $participant['phone'],
                'organization' => $participant['organization'],
                'variantId' => $participant['variantId'],
                'testScore' => $participant['score'],
                'testTotal' => $participant['total'],
                'testPercent' => $participant['percent'],
                'testStatus' => $participant['status'],
            ],
            'practical' => [
                'tasks' => $tasks,
                'complexityVariant' => $complexityVariant,
                'answers' => $participant['practicalAnswers']
                    ? json_decode($participant['practicalAnswers'], true)
                    : null,
                'submittedAt' => $participant['practicalSubmittedAt'],
                'scores' => [
                    'previousTaskScore' => $participant['practicalPreviousScore'],
                    'newTaskScore' => $participant['practicalNewScore'],
                    'metricsScore' => $participant['practicalMetricsScore'],
                    'total' => $participant['practicalScoreTotal'],
                    'gradedAt' => $participant['practicalGradedAt'],
                ]
            ]
        ]);
    } catch (Throwable $e) {
    logError($e, 'api/admin/practical.php GET');

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

    $previousTaskScore = $input['previousTaskScore'] ?? null;
    $newTaskScore = $input['newTaskScore'] ?? null;
    $metricsScore = $input['metricsScore'] ?? null;

    if ($sessionId === '') {
        respondJson([
            'success' => false,
            'message' => 'sessionId не передан'
        ], 400);
    }

    if (!is_numeric($previousTaskScore) || !is_numeric($newTaskScore) || !is_numeric($metricsScore)) {
        respondJson([
            'success' => false,
            'message' => 'Все баллы должны быть числами'
        ], 400);
    }

    $previousTaskScore = (int)$previousTaskScore;
    $newTaskScore = (int)$newTaskScore;
    $metricsScore = (int)$metricsScore;

    if ($previousTaskScore < 0 || $previousTaskScore > 10) {
        respondJson([
            'success' => false,
            'message' => 'Баллы за первое задание должны быть от 0 до 10'
        ], 400);
    }

    if ($newTaskScore < 0 || $newTaskScore > 15) {
        respondJson([
            'success' => false,
            'message' => 'Баллы за второе задание должны быть от 0 до 15'
        ], 400);
    }

    if ($metricsScore < 0 || $metricsScore > 5) {
        respondJson([
            'success' => false,
            'message' => 'Баллы за расчет сложности должны быть от 0 до 5'
        ], 400);
    }

    $total = $previousTaskScore + $newTaskScore + $metricsScore;

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

        $stmt = $pdo->prepare("
            UPDATE Participant
            SET
                practicalPreviousScore = :previousTaskScore,
                practicalNewScore = :newTaskScore,
                practicalMetricsScore = :metricsScore,
                practicalScoreTotal = :total,
                practicalGradedAt = CURRENT_TIMESTAMP,
                updatedAt = CURRENT_TIMESTAMP
            WHERE sessionId = :sessionId
        ");

        $stmt->execute([
            ':previousTaskScore' => $previousTaskScore,
            ':newTaskScore' => $newTaskScore,
            ':metricsScore' => $metricsScore,
            ':total' => $total,
            ':sessionId' => $sessionId
        ]);
writeAdminLog(
    'admin_practical_score_saved',
    'sessionId=' . $sessionId . '; total=' . $total
);
        respondJson([
            'success' => true,
            'message' => 'Оценка практики сохранена',
            'scores' => [
                'previousTaskScore' => $previousTaskScore,
                'newTaskScore' => $newTaskScore,
                'metricsScore' => $metricsScore,
                'total' => $total
            ]
        ]);
    } catch (Throwable $e) {
    logError($e, 'api/admin/practical.php POST');

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