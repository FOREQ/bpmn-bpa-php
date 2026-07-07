<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/error_logger.php';
require_once __DIR__ . '/../../lib/student_logger.php';

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

if (!csrfVerify($input['csrf_token'] ?? '')) {
    respondJson([
        'success' => false,
        'message' => 'Ошибка безопасности'
    ], 403);
}

$sessionId = trim($input['sessionId'] ?? '');

if ($sessionId === '') {
    respondJson([
        'success' => false,
        'message' => 'sessionId не передан'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET passwordResetRequested = 0,
            passwordResetAllowed = 1,
            passwordResetAllowedAt = CURRENT_TIMESTAMP,
            failedLoginAttempts = 0,
            accountLockedUntil = NULL,
            updatedAt = CURRENT_TIMESTAMP
        WHERE sessionId = :sessionId
    ");

    $stmt->execute([
        ':sessionId' => $sessionId
    ]);

    writeStudentLog(
        'password_reset_allowed_by_admin',
        'sessionId=' . $sessionId
    );

    writeAdminLog(
        'admin_allowed_password_reset',
        'sessionId=' . $sessionId
    );

    respondJson([
        'success' => true,
        'message' => 'Сброс пароля разрешен'
    ]);
} catch (Throwable $e) {
    logError($e, 'api/admin/allow_password_reset.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}