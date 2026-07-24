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
            <img src="../assets/logo.svg/logo-transparent.png" alt="DGSC" class="site-logo">

            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <nav class="site-nav">
            <a href="index.php">Главная</a>
            <a href="register.php" class="active">Регистрация</a>
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

    <p>
        Заполните данные для подачи заявки на участие в курсе
        «Практическое применение методики реинжиниринга бизнес-процессов государственных органов».
    </p>

    <p class="hint">
        После отправки заявки администратор проверит данные.
        Если заявка будет подтверждена, временный пароль для входа будет отправлен на вашу почту.
        Временный пароль действует 3 дня.
    </p>

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

        <button type="submit" id="submitBtn">
            Отправить заявку
        </button>
    </form>

    <p class="account-link">
        Уже получили временный пароль?
        <a href="student_login.php">Войти в личный кабинет</a>
    </p>

    <div id="message" class="message"></div>
</div>

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
        submitBtn.textContent = 'Отправка заявки...';

        const data = {
            csrf_token: form.csrf_token.value,
            fullName: form.fullName.value.trim(),
            email: form.email.value.trim(),
            phone: form.phone.value.trim(),
            organization: form.organization.value.trim()
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
                message.textContent = json.message || 'Ошибка отправки заявки';
                message.classList.add('error');
                message.style.display = 'block';

                submitBtn.disabled = false;
                submitBtn.textContent = 'Отправить заявку';

                return;
            }

            form.reset();

            message.innerHTML = `
                <b>Заявка успешно отправлена!</b><br>
                Дождитесь подтверждения администратора.
                После подтверждения временный пароль будет отправлен на вашу почту.
            `;
            message.classList.add('success');
            message.style.display = 'block';

            submitBtn.disabled = false;
            submitBtn.textContent = 'Отправить заявку';

        } catch (error) {
            message.textContent = 'Ошибка соединения с сервером';
            message.classList.add('error');
            message.style.display = 'block';

            submitBtn.disabled = false;
            submitBtn.textContent = 'Отправить заявку';
        }
    });
</script>
</body>
</html>
