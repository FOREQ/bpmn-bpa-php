<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $rows = getDb()->query("
        SELECT id, certificateNumber, certificateToken, fullName,
               course, completionDate, level
        FROM LegacyCertificate
        ORDER BY id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($rows),
        'certificates' => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    logError($e, 'api/admin/legacy_certificates.php');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Не удалось загрузить сертификаты'], JSON_UNESCAPED_UNICODE);
}
