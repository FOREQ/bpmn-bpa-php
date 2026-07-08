<?php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/error_logger.php';
require_once __DIR__ . '/../lib/student_logger.php';

header('Content-Type: application/json; charset=utf-8');

const STUDENT_MAX_LOGIN_ATTEMPTS = 5;
const STUDENT_LOCK_SECONDS = 600;

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

$email = trim($input['email'] ?? '');
$password = trim($input['password'] ?? '');

if ($email === '' || $password === '') {
    respondJson([
        'success' => false,
        'message' => 'Введите email и временный пароль'
    ], 400);
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("
        SELECT *
        FROM Participant
        WHERE email = :email
        LIMIT 1
    ");

    $stmt->execute([
        ':email' => $email
    ]);

    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$participant) {
        writeStudentLog(
            'student_login_failed',
            'email=' . $email . '; reason=user_not_found'
        );

        respondJson([
            'success' => false,
            'message' => 'Пользователь с таким email не найден'
        ], 404);
    }

    $accountStatus = $participant['accountStatus'] ?? 'pending';

    if ($accountStatus === 'pending') {
        respondJson([
            'success' => false,
            'message' => 'Ваша заявка еще ожидает подтверждения администратором.'
        ], 403);
    }

    if ($accountStatus === 'rejected') {
        respondJson([
            'success' => false,
            'message' => 'Ваша заявка была отклонена. Доступ к тестированию закрыт.'
        ], 403);
    }

    if ($accountStatus !== 'approved') {
        respondJson([
            'success' => false,
            'message' => 'Ваш аккаунт не подтвержден.'
        ], 403);
    }

    if (!empty($participant['accountLockedUntil'])) {
        $lockedUntil = strtotime($participant['accountLockedUntil']);

        if ($lockedUntil !== false && time() < $lockedUntil) {
            $secondsLeft = $lockedUntil - time();
            $minutesLeft = ceil($secondsLeft / 60);

            writeStudentLog(
                'student_login_blocked',
                'email=' . $email . '; minutes_left=' . $minutesLeft
            );

            respondJson([
                'success' => false,
                'message' => 'Слишком много неверных попыток входа. Попробуйте снова через ' . $minutesLeft . ' мин.'
            ], 423);
        }
    }

    if (empty($participant['tempPasswordHash']) || empty($participant['tempPasswordExpiresAt'])) {
        respondJson([
            'success' => false,
            'message' => 'Временный пароль не найден. Обратитесь к администратору.'
        ], 401);
    }

    $expiresAt = strtotime($participant['tempPasswordExpiresAt']);

    if ($expiresAt === false || time() > $expiresAt) {
        writeStudentLog(
            'student_login_failed',
            'email=' . $email . '; reason=temp_password_expired'
        );

        respondJson([
            'success' => false,
            'message' => 'Срок действия временного пароля истек.'
        ], 401);
    }

    if (!password_verify($password, $participant['tempPasswordHash'])) {
        $failedAttempts = (int)($participant['failedLoginAttempts'] ?? 0) + 1;

        if ($failedAttempts >= STUDENT_MAX_LOGIN_ATTEMPTS) {
            $lockedUntilDate = date('Y-m-d H:i:s', time() + STUDENT_LOCK_SECONDS);

            $stmt = $pdo->prepare("
                UPDATE Participant
                SET failedLoginAttempts = :failedLoginAttempts,
                    accountLockedUntil = :accountLockedUntil,
                    updatedAt = CURRENT_TIMESTAMP
                WHERE sessionId = :sessionId
            ");

            $stmt->execute([
                ':failedLoginAttempts' => $failedAttempts,
                ':accountLockedUntil' => $lockedUntilDate,
                ':sessionId' => $participant['sessionId']
            ]);

            writeStudentLog(
                'student_login_locked',
                'email=' . $email . '; attempts=' . $failedAttempts
            );

            respondJson([
                'success' => false,
                'message' => 'Слишком много неверных попыток входа. Аккаунт временно заблокирован на 10 минут.'
            ], 423);
        }

        $stmt = $pdo->prepare("
            UPDATE Participant
            SET failedLoginAttempts = :failedLoginAttempts,
                updatedAt = CURRENT_TIMESTAMP
            WHERE sessionId = :sessionId
        ");

        $stmt->execute([
            ':failedLoginAttempts' => $failedAttempts,
            ':sessionId' => $participant['sessionId']
        ]);

        writeStudentLog(
            'student_login_failed',
            'email=' . $email . '; reason=wrong_temp_password; attempts=' . $failedAttempts
        );

        respondJson([
            'success' => false,
            'message' => 'Неверный email или временный пароль. Осталось попыток: ' . (STUDENT_MAX_LOGIN_ATTEMPTS - $failedAttempts)
        ], 401);
    }

    $stmt = $pdo->prepare("
        UPDATE Participant
        SET lastLoginAt = CURRENT_TIMESTAMP,
            failedLoginAttempts = 0,
            accountLockedUntil = NULL,
            updatedAt = CURRENT_TIMESTAMP
        WHERE sessionId = :sessionId
    ");

    $stmt->execute([
        ':sessionId' => $participant['sessionId']
    ]);

    session_regenerate_id(true);

    $_SESSION['student_session_id'] = $participant['sessionId'];

    writeStudentLog(
        'student_login_success_temp_password',
        'email=' . $email . '; sessionId=' . $participant['sessionId']
    );

    respondJson([
        'success' => true,
        'message' => 'Вход выполнен',
        'redirect' => 'student_dashboard.php'
    ]);

} catch (Throwable $e) {
    logError($e, 'api/student_login.php');

    respondJson([
        'success' => false,
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ], 500);
}