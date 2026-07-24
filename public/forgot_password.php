<?php require_once __DIR__ . '/../lib/i18n.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сброс пароля</title>
    <link rel="stylesheet" href="style.css">

    <style>
    .forgot-password-card .primary-btn {
        background: linear-gradient(90deg, #183b59, #ef4444) !important;
        color: #ffffff !important;
        width: 100% !important;
        height: 52px !important;
        border: none !important;
        border-radius: 10px !important;
        font-family: Arial, sans-serif !important;
        font-size: 16px !important;
        font-weight: 800 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
    }

    .forgot-password-card .primary-btn:hover {
        opacity: 0.96 !important;
        transform: translateY(-1px) !important;
        box-shadow: 0 10px 22px rgba(24, 59, 89, 0.22) !important;
    }
</style>
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

<div class="admin-login-card forgot-password-card">
    <div class="top-nav">
        <a href="student_login.php">← Назад ко входу</a>
    </div>

    <h1>Сброс пароля</h1>

    <p>
        Введите email, указанный при регистрации.
        После подтверждения администратором вы сможете установить новый пароль.
    </p>

    <form id="resetForm">
        <label>Email</label>
        <input type="email" id="email" placeholder="example@mail.com" required>

        <button type="submit" class="primary-btn">Отправить запрос</button>
    </form>

    <div id="message" class="message"></div>

    <div id="newPasswordBlock" style="display:none; margin-top:20px;">
        <label>Новый пароль</label>
        <input id="newPassword" type="password" placeholder="Новый пароль">

        <label>Повторите пароль</label>
        <input id="newPassword2" type="password" placeholder="Повторите пароль">

      <button id="savePasswordBtn" type="button" class="primary-btn">
    Сохранить новый пароль
</button>
    </div>
</div>

<script>
const resetForm = document.getElementById('resetForm');
const message = document.getElementById('message');
const newPasswordBlock = document.getElementById('newPasswordBlock');
const savePasswordBtn = document.getElementById('savePasswordBtn');

resetForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const email = document.getElementById('email').value.trim();

    const response = await fetch('../api/forgot_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            email: email
        })
    });

    const json = await response.json();

    message.innerHTML = json.message;
    message.style.display = 'block';

    if (json.success) {
        startChecking(email);
    }
});

function startChecking(email) {
    const timer = setInterval(async function () {
        const response = await fetch(
            '../api/check_password_reset.php?email=' + encodeURIComponent(email)
        );

        const json = await response.json();

        if (json.success && json.resetAllowed) {
            clearInterval(timer);

            message.innerHTML = 'Администратор разрешил смену пароля.';
            message.style.display = 'block';

            newPasswordBlock.style.display = 'block';
        }
    }, 3000);
}

savePasswordBtn.addEventListener('click', async function () {
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('newPassword').value;
    const passwordConfirm = document.getElementById('newPassword2').value;

    const response = await fetch('../api/set_new_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            email: email,
            password: password,
            passwordConfirm: passwordConfirm
        })
    });

    const json = await response.json();

    message.innerHTML = json.message;
    message.style.display = 'block';

    if (json.success) {
        setTimeout(function () {
            window.location.href = 'student_login.php';
        }, 1500);
    }
});
</script>

</body>
</html>
