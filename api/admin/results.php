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

            accountStatus,
            tempPasswordExpiresAt,
            approvedAt,
            rejectedAt,
            rejectionReason,

            createdAt,
            updatedAt
        FROM Participant
        ORDER BY createdAt DESC
    ");

    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($participants as &$participant) {
        if (empty($participant['accountStatus'])) {
            $participant['accountStatus'] = 'pending';
        }

        $participant['score'] = is_numeric($participant['score'] ?? null)
            ? (int)$participant['score']
            : null;

        $participant['total'] = is_numeric($participant['total'] ?? null)
            ? (int)$participant['total']
            : null;

        $participant['percent'] = is_numeric($participant['percent'] ?? null)
            ? (float)$participant['percent']
            : null;

        $participant['practicalScoreTotal'] = is_numeric($participant['practicalScoreTotal'] ?? null)
            ? (int)$participant['practicalScoreTotal']
            : null;
    }

    unset($participant);

    respondJson([
        'success' => true,
        'participants' => $participants
    ]);

} catch (Throwable $e) {
    logError($e, 'api/admin/results.php');

    respondJson([
        'success' => false,
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ], 500);
}