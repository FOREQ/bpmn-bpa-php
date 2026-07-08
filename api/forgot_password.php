<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/error_logger.php';
require_once __DIR__ . '/../lib/student_logger.php';

header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
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

$email = trim($input['email'] ?? '');

if ($email === '') {
    respondJson([
        'success' => false,
        'message' => 'Введите email'
    ], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondJson([
        'success' => false,
        'message' => 'Некорректный email'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("SELECT id, email FROM Participant WHERE email = :email LIMIT 1");
    $stmt->execute([
        ':email' => $email
    ]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        writeStudentLog(
            'password_reset_request_failed',
            'email=' . $email . '; reason=user_not_found'
        );

        respondJson([
            'success' => false,
            'message' => 'Пользователь с таким email не найден'
        ], 404);
    }

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET passwordResetRequested = 1,
            passwordResetAllowed = 0,
            passwordResetRequestedAt = CURRENT_TIMESTAMP,
            passwordResetAllowedAt = NULL,
            updatedAt = CURRENT_TIMESTAMP
        WHERE email = :email
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    writeStudentLog(
        'password_reset_requested',
        'email=' . $email
    );

    respondJson([
        'success' => true,
        'message' => 'Запрос отправлен администратору'
    ]);
} catch (Throwable $e) {
    logError($e, 'api/forgot_password.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}