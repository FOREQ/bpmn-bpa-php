<?php

/**
 * Публичный эндпойнт проверки подлинности сертификата.
 *
 * Используется страницей проверки на govtec.kz (или другом сайте):
 * govtec.kz/certificate/<token> -> запрашивает
 * https://<домен>/api/verify_certificate.php?token=<token>
 *
 * Не требует авторизации. Отдаёт только неконфиденциальные данные
 * (ФИО, курс, дату, уровень, номер сертификата) — без email, телефона,
 * организации и прочих персональных данных участника.
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

    $stmt = $pdo->prepare("
        SELECT fullName, certificateNumber, certificateGeneratedAt,
               practicalGradedAt, percent, practicalScoreTotal
        FROM Participant
        WHERE certificateToken = :token
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant || empty($participant['certificateNumber'])) {
        respondJson([
            'valid' => false,
            'message' => 'Сертификат с таким номером не найден'
        ], 404);
    }

    $score = certificateOverallScore($participant);
    $level = certificateLevel($score['percent']);

    $completionDate = $participant['practicalGradedAt']
        ? date('d.m.Y', strtotime($participant['practicalGradedAt']))
        : null;

    respondJson([
        'valid' => true,
        'certificateNumber' => $participant['certificateNumber'],
        'fullName' => $participant['fullName'],
        'course' => CERTIFICATE_COURSE_NAME,
        'completionDate' => $completionDate,
        'level' => $level['text'],
        'totalScore' => $score['total'],
        'percent' => $score['percent'],
    ]);
} catch (Throwable $e) {
    logError($e, 'api/verify_certificate.php');

    respondJson([
        'valid' => false,
        'message' => 'Произошла ошибка сервера'
    ], 500);
}
