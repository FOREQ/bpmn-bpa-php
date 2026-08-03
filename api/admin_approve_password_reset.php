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

$pdo = null;

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

    $isPasswordResend = ($participant['accountStatus'] ?? '') === 'approved';

    $tempPassword = generateTemporaryPassword(10);
    $tempPasswordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+3 days'));

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET accountStatus = 'approved',
            tempPasswordHash = :tempPasswordHash,
            tempPasswordExpiresAt = :tempPasswordExpiresAt,
            approvedAt = CASE
                WHEN accountStatus = 'approved' THEN approvedAt
                ELSE CURRENT_TIMESTAMP
            END,
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
        $pdo->rollBack();

        respondJson([
            'success' => false,
            'message' => $isPasswordResend
                ? 'Письмо не отправилось. Новый пароль не был сохранён, прежний пароль продолжает действовать.'
                : 'Письмо не отправилось, поэтому заявка не была подтверждена. Проверьте подключение к почтовому серверу и попробуйте снова.'
        ], 502);
    }

    $pdo->commit();

    respondJson([
        'success' => true,
        'message' => $isPasswordResend
            ? 'Новый временный пароль отправлен на почту участника.'
            : 'Заявка подтверждена. Временный пароль отправлен на почту участника.',
        'expiresAt' => $expiresAt
    ]);

} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Approve student error: ' . $e->getMessage());

    respondJson([
        'success' => false,
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ], 500);
}
