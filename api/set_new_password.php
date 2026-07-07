<?php

require_once __DIR__ . '/../config/db.php';
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
$password = trim($input['password'] ?? '');
$passwordConfirm = trim($input['passwordConfirm'] ?? '');

if ($email === '' || $password === '' || $passwordConfirm === '') {
    respondJson([
        'success' => false,
        'message' => 'Заполните все поля'
    ], 400);
}

if ($password !== $passwordConfirm) {
    respondJson([
        'success' => false,
        'message' => 'Пароли не совпадают'
    ], 400);
}

if (mb_strlen($password) < 8) {
    respondJson([
        'success' => false,
        'message' => 'Пароль должен содержать минимум 8 символов'
    ], 400);
}

if (!preg_match('/[A-ZА-Я]/u', $password)) {
    respondJson([
        'success' => false,
        'message' => 'Пароль должен содержать хотя бы одну заглавную букву'
    ], 400);
}

if (!preg_match('/[a-zа-я]/u', $password)) {
    respondJson([
        'success' => false,
        'message' => 'Пароль должен содержать хотя бы одну строчную букву'
    ], 400);
}

if (!preg_match('/\d/', $password)) {
    respondJson([
        'success' => false,
        'message' => 'Пароль должен содержать хотя бы одну цифру'
    ], 400);
}

if (!preg_match('/[^a-zA-Zа-яА-Я0-9]/u', $password)) {
    respondJson([
        'success' => false,
        'message' => 'Пароль должен содержать хотя бы один специальный символ'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("
        SELECT sessionId, passwordResetAllowed
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

    if ((int)$participant['passwordResetAllowed'] !== 1) {
        respondJson([
            'success' => false,
            'message' => 'Сброс пароля не разрешен администратором'
        ], 403);
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET passwordHash = :passwordHash,
            passwordResetRequested = 0,
            passwordResetAllowed = 0,
            passwordResetRequestedAt = NULL,
            passwordResetAllowedAt = NULL,
            failedLoginAttempts = 0,
            accountLockedUntil = NULL,
            updatedAt = CURRENT_TIMESTAMP
        WHERE email = :email
    ");

    $stmt->execute([
        ':passwordHash' => $passwordHash,
        ':email' => $email
    ]);

    writeStudentLog(
        'password_changed_after_admin_reset',
        'email=' . $email . '; sessionId=' . $participant['sessionId']
    );

    respondJson([
        'success' => true,
        'message' => 'Пароль успешно изменен'
    ]);
} catch (Throwable $e) {
    logError($e, 'api/set_new_password.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}