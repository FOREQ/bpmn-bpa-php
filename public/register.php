<?php

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/security.php';

$activeNav = 'register';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Регистрация участника</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/_header.php'; ?>

<section style="position: relative; overflow: hidden;">
    <div class="decor-hex" style="top: 120px; right: calc(50% - 400px); width: 60px; height: 52px;">
        <div class="decor-hex-outer"></div>
        <div class="decor-hex-inner"></div>
    </div>
    <div class="decor-dots" style="bottom: 60px; left: calc(50% - 400px); width: 48px; height: 48px;"></div>

    <div class="auth-shell">
        <a href="index.php" class="top-nav-link" style="display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-weight:700;font-size:14px;">← На главную</a>

        <div class="auth-card">
            <span class="eyebrow">Регистрация участника</span>
            <h1>Подать заявку на участие</h1>
            <p>
                Заполните данные для участия в курсе «Практическое применение методики реинжиниринга
                бизнес-процессов государственных органов». После проверки администратором временный
                пароль придёт на почту и будет действовать 3 дня.
            </p>

            <div class="date-box" id="todayDate"></div>

            <form id="registerForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

                <label>ФИО</label>
                <input name="fullName" required placeholder="Введите ФИО">

                <label>Email</label>
                <input name="email" type="email" required placeholder="example@mail.com">

                <label>Телефон</label>
                <input name="phone" required placeholder="+77001234567">

                <label>Организация</label>
                <input name="organization" required placeholder="Название организации">

                <button type="submit" id="submitBtn" class="btn btn-primary btn-block">Отправить заявку</button>
            </form>

            <div id="message" class="message"></div>
        </div>

        <p class="auth-footer">
            Уже получили временный пароль?
            <a href="student_login.php">Войти в личный кабинет</a>
        </p>
    </div>
</section>

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
