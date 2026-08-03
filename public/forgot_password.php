<?php

$activeNav = 'login';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Сброс пароля</title>
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
        <a href="student_login.php" style="display:inline-flex;align-items:center;gap:6px;color:var(--muted);text-decoration:none;font-weight:700;font-size:14px;">← Назад ко входу</a>

        <div class="auth-card">
            <span class="eyebrow">Личный кабинет</span>
            <h1>Сброс пароля</h1>

            <p>
                Введите email, указанный при регистрации.
                После подтверждения администратором вы сможете установить новый пароль.
            </p>

            <form id="resetForm">
                <label>Email</label>
                <input type="email" id="email" placeholder="example@mail.com" required>

                <button type="submit" class="btn btn-primary btn-block">Отправить запрос</button>
            </form>

            <div id="message" class="message"></div>

            <div id="newPasswordBlock" style="display:none; margin-top:20px;">
                <label>Новый пароль</label>
                <input id="newPassword" type="password" placeholder="Новый пароль">

                <label>Повторите пароль</label>
                <input id="newPassword2" type="password" placeholder="Повторите пароль">

                <button id="savePasswordBtn" type="button" class="btn btn-primary btn-block">
                    Сохранить новый пароль
                </button>
            </div>
        </div>
    </div>
</section>

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
