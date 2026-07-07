<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/error_logger.php';


header('Content-Type: application/json; charset=utf-8');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson([
        'success' => false,
        'message' => 'Метод не поддерживается'
    ], 405);
}

try {
    $pdo = getDb();

    $stmt = $pdo->query("
        SELECT
            id,
            sessionId,
            fullName,
            email,
            phone,
            organization,
            variantId,
            score,
            total,
            percent,
            status,
            submittedAt,
            practicalSubmittedAt,
            practicalPreviousScore,
            practicalNewScore,
            practicalMetricsScore,
          practicalScoreTotal,
practicalGradedAt,
passwordResetRequested,
passwordResetAllowed,
passwordResetRequestedAt,
passwordResetAllowedAt,
createdAt,
updatedAt
        FROM Participant
        ORDER BY createdAt DESC
    ");

    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    respondJson([
        'success' => true,
        'participants' => $participants
    ]);

} catch (Throwable $e) {
    logError($e, 'api/admin/results.php');

    respondJson([
        'success' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}