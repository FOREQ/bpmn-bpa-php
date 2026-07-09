<?php

/**
 * Публичный эндпойнт проверки подлинности сертификата.
 *
 * GET /api/verify_certificate.php?token=<токен из QR-кода>
 *
 * Не требует авторизации. Отдаёт только неконфиденциальные данные
 * (ФИО, курс, дату, уровень, номер сертификата) — без email, телефона,
 * организации и прочих персональных данных участника.
 * Ищет и среди новых сертификатов (участники системы), и среди
 * импортированных исторических (LegacyCertificate).
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/certificate.php';
require_once __DIR__ . '/../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

function respondJson(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondJson([
        'valid' => false,
        'message' => 'Метод не поддерживается'
    ], 405);
}

$token = trim($_GET['token'] ?? '');

if ($token === '' || !preg_match('/^[a-zA-Z0-9]{6,32}$/', $token)) {
    respondJson([
        'valid' => false,
        'message' => 'Некорректный токен сертификата'
    ], 400);
}

try {
    $pdo = getDb();

    $certificate = findCertificateByToken($pdo, $token);

    if (!$certificate) {
        respondJson([
            'valid' => false,
            'message' => 'Сертификат с таким номером не найден'
        ], 404);
    }

    respondJson(array_merge(['valid' => true], $certificate));
} catch (Throwable $e) {
    logError($e, 'api/verify_certificate.php');

    respondJson([
        'valid' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}
