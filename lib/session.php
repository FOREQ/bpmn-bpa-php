<?php

/**
 * Настраивает доступную папку для PHP-сессий.
 *
 * XAMPP часто указывает session.save_path на C:\\xampp\\tmp, куда у
 * обычного пользователя Windows может не быть прав. Сначала используем
 * папку внутри проекта, затем системную временную папку. Путь можно задать
 * явно через SESSION_SAVE_PATH.
 */
function configureAppSessionPath(): string
{
    static $configuredPath = null;

    if ($configuredPath !== null) {
        return $configuredPath;
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        return session_save_path();
    }

    $explicitPath = trim((string)(getenv('SESSION_SAVE_PATH') ?: ''));
    $candidates = $explicitPath !== ''
        ? [$explicitPath]
        : [
            __DIR__ . '/../database/sessions',
            rtrim(sys_get_temp_dir(), '\\/') . DIRECTORY_SEPARATOR . 'bpmn-bpa-php-sessions',
        ];

    foreach ($candidates as $candidate) {
        if (!is_dir($candidate) && !@mkdir($candidate, 0775, true) && !is_dir($candidate)) {
            continue;
        }

        if (!is_writable($candidate)) {
            continue;
        }

        session_save_path($candidate);
        $configuredPath = $candidate;

        return $configuredPath;
    }

    throw new RuntimeException(
        'Не удалось создать доступную папку для PHP-сессий. '
        . 'Задайте SESSION_SAVE_PATH на папку с правом записи.'
    );
}

function startAppSession(array $cookieParams = []): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    configureAppSessionPath();

    $basePath = rtrim(trim((string)(getenv('APP_BASE_PATH') ?: '')), '/');
    if ($basePath !== '') {
        session_name('bpmn_bpa_session');
    }
    $secureCookie = filter_var(getenv('SESSION_COOKIE_SECURE'), FILTER_VALIDATE_BOOLEAN)
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    $defaultCookieParams = [
        'lifetime' => 0,
        'path' => $basePath !== '' ? $basePath . '/' : '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Strict',
    ];

    session_set_cookie_params(array_replace($defaultCookieParams, $cookieParams));

    if (!session_start()) {
        throw new RuntimeException('Не удалось запустить PHP-сессию.');
    }
}
