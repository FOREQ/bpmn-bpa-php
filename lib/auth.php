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

const ADMIN_MAX_LOGIN_ATTEMPTS = 5;
const ADMIN_LOCK_SECONDS = 600; // 10 минут

function isAdminLoggedIn(): bool
{
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin(): void
{
    $timeoutSeconds = 20* 60;

    if (!isAdminLoggedIn()) {
        header('Location: admin_login.php');
        exit;
    }

    if (isset($_SESSION['admin_last_activity'])) {
        $inactiveTime = time() - $_SESSION['admin_last_activity'];

        if ($inactiveTime > $timeoutSeconds) {
            logoutAdmin();
            header('Location: admin_login.php?timeout=1');
            exit;
        }
    }

    $_SESSION['admin_last_activity'] = time();
}

function getAdminLoginError(): string
{
    if (!empty($_SESSION['admin_locked_until']) && time() < $_SESSION['admin_locked_until']) {
        $secondsLeft = $_SESSION['admin_locked_until'] - time();
        $minutesLeft = ceil($secondsLeft / 60);

        return 'Слишком много неверных попыток входа. Попробуйте снова через ' . $minutesLeft . ' мин.';
    }

    return 'Неверный логин или пароль';
}

function loginAdmin(string $login, string $password): bool
{
    if (!empty($_SESSION['admin_locked_until']) && time() < $_SESSION['admin_locked_until']) {
        return false;
    }

    if (!empty($_SESSION['admin_locked_until']) && time() >= $_SESSION['admin_locked_until']) {
        unset($_SESSION['admin_locked_until']);
        $_SESSION['admin_failed_attempts'] = 0;
    }

    $adminLogin = 'admin';

    $adminPasswordHash = '$2y$10$t7/fjTyLCYNulyMs3EBIR.TqHCMb4ctE3mtPp2KJ3nf9iH3DrKyom';

  if ($login === $adminLogin && password_verify($password, $adminPasswordHash)) {
    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
$_SESSION['admin_login'] = $login;
$_SESSION['admin_last_activity'] = time();

$_SESSION['admin_failed_attempts'] = 0;
unset($_SESSION['admin_locked_until']);

writeAdminLog('admin_login_success');

return true;
    }

    $_SESSION['admin_failed_attempts'] = ($_SESSION['admin_failed_attempts'] ?? 0) + 1;

writeAdminLog(
    'admin_login_failed',
    'login=' . $login . '; attempts=' . $_SESSION['admin_failed_attempts']
);

if ($_SESSION['admin_failed_attempts'] >= ADMIN_MAX_LOGIN_ATTEMPTS) {
    $_SESSION['admin_locked_until'] = time() + ADMIN_LOCK_SECONDS;

    writeAdminLog(
        'admin_login_locked',
        'login=' . $login . '; locked_seconds=' . ADMIN_LOCK_SECONDS
    );
}

return false;
}

function writeAdminLog(string $action, string $details = ''): void
{
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/admin.log';

    $date = date('Y-m-d H:i:s');
    $admin = $_SESSION['admin_login'] ?? 'unknown';

    $line = '[' . $date . '] ' . $action . ' | admin=' . $admin;

    if ($details !== '') {
        $line .= ' | ' . $details;
    }

    $line .= PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function logoutAdmin(): void
{
    if (function_exists('writeAdminLog')) {
        writeAdminLog('admin_logout');
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}