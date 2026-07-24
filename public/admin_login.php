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
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход администратора</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <div class="site-header-inner">
        <a href="index.php" class="site-brand">
            <img src="../assets/logo.svg/logo-transparent.png" alt="DGSC" class="site-logo">

            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <nav class="site-nav">
    <a href="index.php">Главная</a>
    <a href="register.php">Регистрация</a>
    <a href="student_login.php">Войти</a>
    <a href="admin_login.php">Админ</a>
</nav>
    </div>
</header>
<div class="admin-login-card admin-auth-card">
    <div class="top-nav">
    <a href="index.php">← На главную</a>
</div>
    <h1>Вход администратора</h1>

    <form method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

    <label>Логин</label>
        <input type="text" name="login" required >

        <label>Пароль</label>
        <input type="password" name="password" required >

        <button type="submit" class="primary-btn">Войти</button>
    </form>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p class="admin-login-links">
 
</p>
</div>
</body>
</html>
