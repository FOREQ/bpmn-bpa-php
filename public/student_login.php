<?php

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/security.php';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход участника</title>
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
            <a href="student_login.php" class="active">Войти</a>
            <a href="admin_login.php">Админ</a>
        </nav>
    </div>
</header>

<div class="admin-login-card student-login-card">
    <div class="top-nav">
        <a href="index.php">← На главную</a>
    </div>

    <h1>Вход участника</h1>

    <p class="hint">
        Введите email и временный пароль, который был отправлен на вашу почту после подтверждения заявки
        на курс «Практическое применение методики реинжиниринга бизнес-процессов государственных органов».
    </p>

    <form id="loginForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

        <label>Email</label>
        <input name="email" type="email" required placeholder="example@mail.com">

        <label>Временный пароль</label>
        <input name="password" type="password" required placeholder="Введите временный пароль">

        <button type="submit" id="submitBtn" class="primary-btn">
            Войти
        </button>
    </form>

    <div id="message" class="message"></div>

    <div class="student-auth-links">
        <p class="register-question">
            Еще нет доступа?
            <a href="register.php">Подать заявку на участие</a>
        </p>
    </div>
</div>

<script>
    const form = document.getElementById('loginForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');

    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('expired') === '1') {
        message.textContent = 'Срок действия временного пароля истек. Обратитесь к администратору.';
        message.classList.add('error');
        message.style.display = 'block';
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        message.style.display = 'none';
        message.className = 'message';

        submitBtn.disabled = true;
        submitBtn.textContent = 'Вход...';

        const data = {
            csrf_token: form.csrf_token.value,
            email: form.email.value.trim(),
            password: form.password.value.trim()
        };

        try {
            const response = await fetch('../api/student_login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const json = await response.json();

            if (!json.success) {
                message.textContent = json.message || 'Ошибка входа';
                message.classList.add('error');
                message.style.display = 'block';

                submitBtn.disabled = false;
                submitBtn.textContent = 'Войти';

                return;
            }

            window.location.href = json.redirect || 'student_dashboard.php';

        } catch (error) {
            message.textContent = 'Ошибка соединения с сервером';
            message.classList.add('error');
            message.style.display = 'block';

            submitBtn.disabled = false;
            submitBtn.textContent = 'Войти';
        }
    });
</script>
</body>
</html>
