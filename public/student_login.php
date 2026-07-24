<?php

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/security.php';

$activeNav = 'login';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход участника</title>
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
            <span class="eyebrow">Личный кабинет</span>
            <h1>Вход участника</h1>
            <p>
                Введите email и временный пароль, который был отправлен на вашу почту после подтверждения заявки
                на курс «Практическое применение методики реинжиниринга бизнес-процессов государственных органов».
            </p>

            <form id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <label>Email</label>
                <input name="email" type="email" required placeholder="example@mail.com">

                <label>Временный пароль</label>
                <input name="password" type="password" required placeholder="Введите временный пароль">

                <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Войти</button>
            </form>

            <div id="message" class="message"></div>
        </div>

        <p class="auth-footer">
            Ещё нет доступа?
            <a href="register.php">Подать заявку на участие</a>
        </p>
    </div>
</section>

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
