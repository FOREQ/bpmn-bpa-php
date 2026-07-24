<?php

require_once __DIR__ . '/../lib/security.php';

$activeNav = 'index';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DGSC | Реинжиниринг бизнес-процессов</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/_header.php'; ?>

<section style="position: relative; overflow: hidden;">
    <div class="decor-hex" style="top: -16px; right: calc(50% - 590px); width: 76px; height: 66px;">
        <div class="decor-hex-outer"></div>
        <div class="decor-hex-inner"></div>
    </div>
    <div class="decor-dots" style="bottom: 24px; left: calc(50% - 590px); width: 56px; height: 56px;"></div>

    <div class="hero-layout">
        <div class="hero-content">
            <span class="eyebrow">Государственная программа</span>
            <h1>Практическое применение методики реинжиниринга бизнес-процессов государственных органов</h1>
            <p>
                Подайте заявку на участие, дождитесь подтверждения администратора,
                получите временный пароль на почту и пройдите тестирование с практическим заданием по BPMN-моделированию.
            </p>

            <div class="hero-buttons">
                <a class="btn btn-primary" href="register.php">Подать заявку →</a>
                <a class="btn btn-secondary" href="student_login.php">Войти участнику</a>
            </div>
            <a class="btn-text hero-admin-link" href="admin_login.php">Войти как администратор →</a>
        </div>

        <div class="instruction-card">
            <div class="instruction-header">
                <h2>Инструкция</h2>
            </div>

            <div class="instruction-list">
                <div class="instruction-step">
                    <div class="instruction-step-number">1</div>
                    <div>
                        <div class="instruction-step-label">Шаг 1</div>
                        <div class="instruction-step-title">Подайте заявку</div>
                        <div class="instruction-step-desc">Заполните ФИО, email, телефон и организацию.</div>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="instruction-step-number">2</div>
                    <div>
                        <div class="instruction-step-label">Шаг 2</div>
                        <div class="instruction-step-title">Дождитесь подтверждения</div>
                        <div class="instruction-step-desc">Администратор проверит заявку и подтвердит доступ к тестированию.</div>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="instruction-step-number">3</div>
                    <div>
                        <div class="instruction-step-label">Шаг 3</div>
                        <div class="instruction-step-title">Получите временный пароль</div>
                        <div class="instruction-step-desc">После подтверждения заявки временный пароль будет отправлен на вашу почту и будет действовать 3 дня.</div>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="instruction-step-number">4</div>
                    <div>
                        <div class="instruction-step-label">Шаг 4</div>
                        <div class="instruction-step-title">Войдите в личный кабинет</div>
                        <div class="instruction-step-desc">Используйте email и временный пароль для входа в систему.</div>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="instruction-step-number">5</div>
                    <div>
                        <div class="instruction-step-label">Шаг 5</div>
                        <div class="instruction-step-title">Пройдите тест и практику</div>
                        <div class="instruction-step-desc">Система назначит индивидуальный вариант теста, а затем откроет практическое задание.</div>
                    </div>
                </div>

                <div class="instruction-step">
                    <div class="instruction-step-number">6</div>
                    <div>
                        <div class="instruction-step-label">Шаг 6</div>
                        <div class="instruction-step-title">Получите результат</div>
                        <div class="instruction-step-desc">После проверки практического задания администратором итоговый результат будет отображен в личном кабинете.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>
