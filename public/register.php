<?php

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/security.php';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация участника</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
        <a href="index.php" class="site-brand">
            <img src="../assets/logo.svg/logo.svg.png" alt="DGSC" class="site-logo">

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

<div class="container">
    <div class="top-nav">
        <a href="index.php">← На главную</a>
    </div>

    <h1>Регистрация участника</h1>

    <p>Заполните данные для прохождения тестирования по BPA и BPMN.</p>
    <p class="hint">Дата прохождения фиксируется автоматически при создании сессии тестирования.</p>
    <div class="date-box" id="todayDate"></div>

<form id="registerForm" class="card register-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
    <label>ФИО</label>
    <input name="fullName" required placeholder="Введите ФИО">

    <label>Email</label>
    <input name="email" type="email" required placeholder="example@mail.com">

    <label>Телефон</label>
    <input name="phone" required placeholder="+77001234567">

    <label>Организация</label>
    <input name="organization" required placeholder="Название организации">

    <label>Пароль</label>
    <input name="password" type="password" required placeholder="Минимум 8 символов, буквы, цифра и спецсимвол">

    <label>Повторите пароль</label>
    <input name="passwordConfirm" type="password" required placeholder="Повторите пароль">

    <button type="submit" id="submitBtn">Начать тест</button>
</form>

<p class="account-link">
    Уже есть аккаунт?
    <a href="student_login.php">Войти в личный кабинет</a>
</p>

<div id="message" class="message"></div>

<script>
    const form = document.getElementById('registerForm');
    const message = document.getElementById('message');
    const submitBtn = document.getElementById('submitBtn');

    document.getElementById('todayDate').innerText =
        new Date().toLocaleDateString('ru-RU', {
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        message.style.display = 'none';
        message.className = 'message';

        submitBtn.disabled = true;
        submitBtn.textContent = 'Регистрация...';

   const data = {
    csrf_token: form.csrf_token.value,
    fullName: form.fullName.value.trim(),
    email: form.email.value.trim(),
    phone: form.phone.value.trim(),
    organization: form.organization.value.trim(),
    password: form.password.value.trim(),
    passwordConfirm: form.passwordConfirm.value.trim()
};

        try {
            const response = await fetch('../api/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const json = await response.json();

            if (!json.success) {
                message.textContent = json.message || 'Ошибка регистрации';
                message.classList.add('error');
                message.style.display = 'block';

                submitBtn.disabled = false;
                submitBtn.textContent = 'Начать тест';

                return;
            }

            setTimeout(() => {
                window.location.href = 'test.php?sessionId=' + encodeURIComponent(json.sessionId);
            }, 1000);
        } catch (error) {
            message.textContent = 'Ошибка соединения с сервером';
            message.classList.add('error');
            message.style.display = 'block';

            submitBtn.disabled = false;
            submitBtn.textContent = 'Начать тест';
        }
    });
</script>
</body>
</html>