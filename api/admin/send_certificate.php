<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/certificate.php';
require_once __DIR__ . '/../../lib/mailer.php';
require_once __DIR__ . '/../../lib/error_logger.php';

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
        'message' => 'Ошибка безопасности. Обновите страницу и попробуйте снова.'
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

    $stmt = $pdo->prepare("SELECT * FROM Participant WHERE sessionId = :sessionId LIMIT 1");
    $stmt->execute([':sessionId' => $sessionId]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        respondJson([
            'success' => false,
            'message' => 'Участник не найден'
        ], 404);
    }

    if (empty($participant['practicalGradedAt'])) {
        respondJson([
            'success' => false,
            'message' => 'Сначала выставьте оценку за практическое задание'
        ], 400);
    }

    $certificate = generateCertificate($pdo, $participant);

    $emailSent = sendCertificateEmail(
        $participant['email'],
        $participant['fullName'],
        $certificate['number'],
        $certificate['levelText'],
        $certificate['total'],
        $certificate['percent'],
        $certificate['verifyUrl'],
        $certificate['filePath']
    );

    if (!$emailSent) {
        respondJson([
            'success' => false,
            'message' => 'Не удалось отправить письмо. Проверьте настройки почты.'
        ], 500);
    }

    $pdo->prepare("
        UPDATE Participant
        SET certificateEmailedAt = CURRENT_TIMESTAMP
        WHERE sessionId = :sessionId
    ")->execute([':sessionId' => $sessionId]);

    writeAdminLog(
        'admin_certificate_emailed',
        'sessionId=' . $sessionId . '; number=' . $certificate['number'] . '; manual=1'
    );

    respondJson([
        'success' => true,
        'message' => 'Сертификат отправлен на ' . $participant['email'],
        'certificate' => [
            'number' => $certificate['number'],
            'levelText' => $certificate['levelText'],
            'percent' => $certificate['percent']
        ]
    ]);
} catch (Throwable $e) {
    logError($e, 'api/admin/send_certificate.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}
