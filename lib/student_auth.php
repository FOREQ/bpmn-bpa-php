<?php

/*
 * Проверка пароля участника при входе.
 *
 * Участник может войти:
 *  - постоянным паролем (passwordHash), который он задал через сброс пароля;
 *  - временным паролем (tempPasswordHash), пока не истек tempPasswordExpiresAt.
 *
 * Возвращает одно из значений:
 *  'permanent'    — подошел постоянный пароль;
 *  'temp'         — подошел действующий временный пароль;
 *  'temp_expired' — подошел временный пароль, но срок его действия истек;
 *  'invalid'      — пароль не подошел.
 */
function verifyStudentPassword(array $participant, string $password, ?int $now = null): string
{
    $now = $now ?? time();

    $passwordHash = (string)($participant['passwordHash'] ?? '');

    if ($passwordHash !== '' && password_verify($password, $passwordHash)) {
        return 'permanent';
    }

    $tempPasswordHash = (string)($participant['tempPasswordHash'] ?? '');

    if ($tempPasswordHash === '' || !password_verify($password, $tempPasswordHash)) {
        return 'invalid';
    }

    $expiresAt = strtotime((string)($participant['tempPasswordExpiresAt'] ?? ''));

    if ($expiresAt === false || $now > $expiresAt) {
        return 'temp_expired';
    }

    return 'temp';
}
