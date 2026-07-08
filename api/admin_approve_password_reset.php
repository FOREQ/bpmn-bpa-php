<?php

require_once __DIR__ . '/../lib/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/mailer.php';

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

$participantId = $_POST['participant_id'] ?? $_POST['id'] ?? null;

if (!$participantId) {
    respondJson([
        'success' => false,
        'message' => 'ID участника не найден'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("
        SELECT id, sessionId, fullName, email, accountStatus
        FROM Participant
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $participantId
    ]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        respondJson([
            'success' => false,
            'message' => 'Участник не найден'
        ], 404);
    }

    if (empty($participant['email'])) {
        respondJson([
            'success' => false,
            'message' => 'Email участника не указан'
        ], 400);
    }

    $tempPassword = generateTemporaryPassword(10);
    $tempPasswordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET accountStatus = 'approved',
            tempPasswordHash = :tempPasswordHash,
            tempPasswordExpiresAt = :tempPasswordExpiresAt,
            approvedAt = CURRENT_TIMESTAMP,
            rejectedAt = NULL,
            rejectionReason = NULL,
            failedLoginAttempts = 0,
            accountLockedUntil = NULL,
            updatedAt = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':tempPasswordHash' => $tempPasswordHash,
        ':tempPasswordExpiresAt' => $expiresAt,
        ':id' => $participant['id']
    ]);

    $emailSent = sendTemporaryPasswordEmail(
        $participant['email'],
        $participant['fullName'],
        $tempPassword,
        $expiresAt
    );

    if (!$emailSent) {
        respondJson([
            'success' => false,
            'message' => 'Заявка подтверждена, временный пароль создан, но письмо не отправилось. Проверьте настройки почты.'
        ], 500);
    }

    respondJson([
        'success' => true,
        'message' => 'Заявка подтверждена. Временный пароль отправлен на почту участника.',
        'expiresAt' => $expiresAt
    ]);

} catch (Throwable $e) {
    error_log('Approve student error: ' . $e->getMessage());

    respondJson([
        'success' => false,
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ], 500);
}