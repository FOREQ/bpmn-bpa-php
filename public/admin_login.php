<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/csrf.php';

$error = '';
if (isset($_GET['timeout'])) {
    $error = 'Сессия администратора завершена из-за бездействия. Войдите снова.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!csrfVerify($csrfToken)) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (loginAdmin($login, $password)) {
            header('Location: admin.php');
            exit;
        }

        $error = getAdminLoginError();
    }
}

$activeNav = 'admin';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход администратора</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/_header.php'; ?>

<section style="position: relative; overflow: hidden;">
    <div class="decor-hex" style="top: 130px; right: calc(50% - 400px); width: 56px; height: 49px;">
        <div class="decor-hex-outer"></div>
        <div class="decor-hex-inner"></div>
    </div>
    <div class="decor-dots" style="bottom: 70px; left: calc(50% - 400px); width: 48px; height: 48px;"></div>

    <div class="auth-shell narrow">
        <a href="index.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-weight:700;font-size:14px;">← На главную</a>

        <div class="auth-card">
            <span class="eyebrow">Администрирование</span>
            <h1>Вход администратора</h1>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <label>Логин</label>
                <input type="text" name="login" required>

                <label>Пароль</label>
                <input type="password" name="password" required>

                <button type="submit" class="btn btn-primary btn-block">Войти</button>
            </form>

            <?php if ($error): ?>
                <div class="message error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    </div>
</section>

</body>
</html>