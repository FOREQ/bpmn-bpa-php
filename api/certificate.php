<?php

require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/certificate.php';
require_once __DIR__ . '/../lib/error_logger.php';

function respondError(string $message, int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sessionId = trim($_GET['sessionId'] ?? '');

if ($sessionId === '') {
    respondError('sessionId не передан', 400);
}

// Скачать сертификат может участник своей сессии или администратор
$isOwner = !empty($_SESSION['student_session_id'])
    && $_SESSION['student_session_id'] === $sessionId;

if (!$isOwner && !isAdminLoggedIn()) {
    respondError('Доступ запрещен. Войдите в личный кабинет.', 403);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("SELECT * FROM Participant WHERE sessionId = :sessionId LIMIT 1");
    $stmt->execute([':sessionId' => $sessionId]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        respondError('Участник не найден', 404);
    }

    if (empty($participant['practicalGradedAt'])) {
        respondError('Сертификат будет доступен после проверки практического задания', 400);
    }

    ensureCertificateColumns($pdo);

    $token = $participant['certificateToken'] ?? null;
    $filePath = $token ? certificateFilePath($token) : null;

    if (!$token || !is_file($filePath)) {
        $certificate = generateCertificate($pdo, $participant);
        $filePath = $certificate['filePath'];
        $number = $certificate['number'];
    } else {
        $number = $participant['certificateNumber'];
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="certificate-' . $number . '.pdf"');
    header('Content-Length: ' . filesize($filePath));

    readfile($filePath);
    exit;
} catch (Throwable $e) {
    logError($e, 'api/certificate.php');
    respondError('Произошла ошибка сервера', 500);
}
