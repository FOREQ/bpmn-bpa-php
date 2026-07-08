<?php

require_once __DIR__ . '/../lib/helpers.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/test_data.php';
require_once __DIR__ . '/../lib/test_service.php';
require_once __DIR__ . '/../lib/practical_data.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/error_logger.php';

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

$fullName = trim($input['fullName'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$organization = trim($input['organization'] ?? '');

if ($fullName === '' || $email === '' || $phone === '' || $organization === '') {
    respondJson([
        'success' => false,
        'message' => 'Заполните все обязательные поля'
    ], 400);
}

if (!preg_match('/^[A-Za-zА-Яа-яЁёІіҢңҒғҚқҮүҰұӨөҺһӘә\s-]+$/u', $fullName)) {
    respondJson([
        'success' => false,
        'message' => 'ФИО должно содержать только буквы'
    ], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondJson([
        'success' => false,
        'message' => 'Некорректный email'
    ], 400);
}

if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
    respondJson([
        'success' => false,
        'message' => 'Телефон должен содержать только цифры и может начинаться с +'
    ], 400);
}

if (!preg_match('/^[A-Za-zА-Яа-яЁёІіҢңҒғҚқҮүҰұӨөҺһӘә0-9\s.,()-]+$/u', $organization)) {
    respondJson([
        'success' => false,
        'message' => 'Некорректное название организации'
    ], 400);
}

if (mb_strlen($fullName) > 100) {
    respondJson([
        'success' => false,
        'message' => 'ФИО не должно превышать 100 символов'
    ], 400);
}

if (mb_strlen($email) > 100) {
    respondJson([
        'success' => false,
        'message' => 'Email слишком длинный'
    ], 400);
}

if (mb_strlen($phone) > 20) {
    respondJson([
        'success' => false,
        'message' => 'Телефон не должен превышать 20 символов'
    ], 400);
}

if (mb_strlen($organization) > 120) {
    respondJson([
        'success' => false,
        'message' => 'Название организации не должно превышать 120 символов'
    ], 400);
}

try {
    $pdo = getDb();

    $checkStmt = $pdo->prepare("
        SELECT id
        FROM Participant
        WHERE email = :email
        LIMIT 1
    ");

    $checkStmt->execute([
        ':email' => $email
    ]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        respondJson([
            'success' => false,
            'message' => 'Пользователь с таким email уже зарегистрирован'
        ], 400);
    }

    $variantId = randomVariantId();
    $variant = $TEST_DATA[$variantId] ?? null;

    if (!$variant) {
        respondJson([
            'success' => false,
            'message' => 'Вариант теста не найден'
        ], 500);
    }

    $id = createSessionId();
    $sessionId = createSessionId();

    $questionOrder = questionOrder($variant);
    $optionOrder = optionOrder($variant);

    $practicalTaskIds = randomPracticalTaskIds();
    $complexityVariantId = randomComplexityVariantId();

    /*
     * Участник НЕ создает пароль при регистрации.
     * Регистрация = заявка на доступ.
     * Пароль будет создан автоматически после подтверждения администратором.
     *
     * passwordHash оставляем только как техническую заглушку,
     * если поле в базе обязательное.
     */
    $technicalPasswordHash = password_hash(createSessionId(), PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO Participant (
            id,
            sessionId,
            fullName,
            email,
            phone,
            organization,
            variantId,
            questionOrder,
            optionOrder,
            practicalTaskIds,
            complexityVariantId,
            passwordHash,
            accountStatus,
            tempPasswordHash,
            tempPasswordExpiresAt,
            approvedAt,
            rejectedAt,
            rejectionReason,
            failedLoginAttempts,
            accountLockedUntil,
            lastLoginAt,
            createdAt,
            updatedAt
        ) VALUES (
            :id,
            :sessionId,
            :fullName,
            :email,
            :phone,
            :organization,
            :variantId,
            :questionOrder,
            :optionOrder,
            :practicalTaskIds,
            :complexityVariantId,
            :passwordHash,
            'pending',
            NULL,
            NULL,
            NULL,
            NULL,
            NULL,
            0,
            NULL,
            NULL,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute([
        ':id' => $id,
        ':sessionId' => $sessionId,
        ':fullName' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':organization' => $organization,
        ':variantId' => $variantId,
        ':questionOrder' => json_encode($questionOrder, JSON_UNESCAPED_UNICODE),
        ':optionOrder' => json_encode($optionOrder, JSON_UNESCAPED_UNICODE),
        ':practicalTaskIds' => json_encode($practicalTaskIds, JSON_UNESCAPED_UNICODE),
        ':complexityVariantId' => $complexityVariantId,
        ':passwordHash' => $technicalPasswordHash
    ]);

    respondJson([
        'success' => true,
        'message' => 'Заявка успешно отправлена. После подтверждения администратором временный пароль будет отправлен на вашу почту.',
        'accountStatus' => 'pending'
    ]);

} catch (Throwable $e) {
    logError($e, 'api/register.php');

    respondJson([
        'success' => false,
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ], 500);
}