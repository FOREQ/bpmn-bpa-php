<?php

require_once __DIR__ . '/../lib/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/db.php';
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
$reason = trim($_POST['reason'] ?? 'Заявка отклонена администратором');

if (!$participantId) {
    respondJson([
        'success' => false,
        'message' => 'ID участника не найден'
    ], 400);
}

if ($reason === '') {
    $reason = 'Заявка отклонена администратором';
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("
        SELECT id, sessionId, fullName, email
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

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET accountStatus = 'rejected',
            rejectedAt = CURRENT_TIMESTAMP,
            rejectionReason = :rejectionReason,
            approvedAt = NULL,
            tempPasswordHash = NULL,
            tempPasswordExpiresAt = NULL,
            failedLoginAttempts = 0,
            accountLockedUntil = NULL,
            updatedAt = CURRENT_TIMESTAMP
        WHERE id = :id
    ");

    $stmt->execute([
        ':rejectionReason' => $reason,
        ':id' => $participant['id']
    ]);

    $emailSent = sendRejectionEmail(
        $participant['email'],
        $participant['fullName'],
        $reason
    );

    if (!$emailSent) {
        respondJson([
            'success' => false,
            'message' => 'Заявка отклонена, но письмо не отправилось. Проверьте настройки почты.'
        ], 500);
    }

    respondJson([
        'success' => true,
        'message' => 'Заявка отклонена. Сообщение отправлено на почту участника.'
    ]);

} catch (Throwable $e) {
    error_log('Reject student error: ' . $e->getMessage());

    respondJson([
        'success' => false,
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ], 500);
}