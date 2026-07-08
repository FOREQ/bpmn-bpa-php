<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$email = trim($_GET['email'] ?? '');

if ($email === '') {
    respondJson([
        'success' => false,
        'message' => 'Email не передан'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("
        SELECT passwordResetAllowed
        FROM Participant
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        respondJson([
            'success' => false,
            'message' => 'Пользователь не найден'
        ], 404);
    }

    respondJson([
        'success' => true,
        'resetAllowed' => (int)$participant['passwordResetAllowed'] === 1
    ]);
} catch (Throwable $e) {
    logError($e, 'api/check_password_reset.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}