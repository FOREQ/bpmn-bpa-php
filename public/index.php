<?php

require_once __DIR__ . '/../lib/security.php';

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DGSC | Реинжиниринг бизнес-процессов</title>

    <style>
        :root {
            --blue: #2ab7f6;
            --red: #ef4444;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", "Noto Sans", Arial, sans-serif;
            color: white;
            background: #0f172a;
        }

        button,
        input,
        select,
        textarea {
            font: inherit;
        }

        .hero {
            min-height: 100vh;
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at 78% 12%, rgba(239, 68, 68, 0.34), transparent 30%),
                linear-gradient(135deg, #0f172a 0%, #1e293b 44%, #2563eb 68%, #ef4444 100%);
        }

        .grid-bg {
            position: absolute;
            inset: 0;
            opacity: 0.18;
            background-image:
                linear-gradient(rgba(255,255,255,0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.08) 1px, transparent 1px);
            background-size: 46px 46px;
        }

        .landing-header {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            padding: 24px 0;
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .site-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-right: 32px;
            flex-shrink: 0;
            color: white;
            text-decoration: none;
        }

        .site-logo {
            width: 110px;
            height: auto;
            display: block;
            mix-blend-mode: normal;
        }

        .site-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .site-brand-title,
        .site-brand-subtitle {
            color: white;
            font-weight: 800;
            font-size: 16px;
        }

        .site-nav {
            display: flex;
            gap: 28px;
            align-items: center;
        }

        .site-nav a {
            color: white;
            text-decoration: none;
            font-weight: 800;
        }

        .site-nav a:hover,
        .site-nav a.active {
            color: var(--red);
        }

        .container {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
            min-height: calc(100vh - 92px);
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(0, 0.92fr);
            gap: clamp(24px, 4vw, 48px);
            align-items: center;
            padding: 40px 0 70px;
        }

        .content,
        .panel {
            min-width: 0;
        }

        .content h1 {
            margin: 0;
            font-size: clamp(38px, 4.2vw, 52px);
            line-height: 1.08;
            font-weight: 800;
            max-width: 100%;
            overflow-wrap: anywhere;
        }

        .content p {
            margin-top: 24px;
            max-width: 710px;
            color: #e2e8f0;
            font-size: 20px;
            line-height: 1.7;
        }

        .buttons {
            margin-top: 36px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 16px 26px;
            border-radius: 10px;
            font-weight: 800;
            text-decoration: none;
            transition: 0.2s;
        }

        .btn-primary {
            color: white;
            background: linear-gradient(90deg, var(--blue), var(--red));
            box-shadow: 0 0 28px rgba(42, 183, 246, 0.35);
        }

        .btn-secondary {
            color: white;
            border: 1px solid rgba(255,255,255,0.24);
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .panel {
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(16px);
            border-radius: 18px;
            padding: 14px;
            box-shadow: 0 0 40px rgba(42, 183, 246, 0.18);
        }

        .panel-inner {
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(15, 23, 42, 0.75);
            border-radius: 14px;
            overflow: hidden;
        }

        .panel-title {
            padding: 22px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
        }

        .panel-title h2 {
            margin: 0;
            font-size: 26px;
        }

        .steps {
            padding: 14px;
            display: grid;
            gap: 12px;
        }

        .step {
            display: grid;
            grid-template-columns: 54px minmax(0, 1fr);
            gap: 16px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            padding: 18px;
            border-radius: 12px;
        }

        .step-icon {
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--blue), var(--red));
            font-size: 24px;
            font-weight: 800;
        }

        .step small {
            color: #a5f3fc;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .step b {
            display: block;
            margin-top: 6px;
            font-size: 18px;
            overflow-wrap: anywhere;
        }

        .step span {
            display: block;
            margin-top: 6px;
            color: #cbd5e1;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        @media (max-width: 1100px) and (min-width: 901px) {
            .content h1 {
                font-size: clamp(36px, 4.15vw, 44px);
            }

            .content p {
                font-size: 18px;
                line-height: 1.6;
            }

            .step {
                grid-template-columns: 44px minmax(0, 1fr);
                gap: 12px;
                padding: 14px;
            }

            .step-icon {
                width: 44px;
                height: 44px;
                font-size: 21px;
            }

            .step b {
                font-size: 16px;
            }
        }

        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
            }

            .content h1 {
                font-size: clamp(32px, 8vw, 40px);
            }

            .content p {
                font-size: 18px;
            }

            .landing-header {
                flex-direction: column;
                gap: 18px;
                align-items: flex-start;
            }

            .site-brand {
                margin-right: 0;
            }

            .site-nav {
                flex-wrap: wrap;
                gap: 16px;
            }

            .landing-header .i18n-switcher {
                margin-left: 0 !important;
            }
        }
    </style>
</head>

<body>
<section class="hero">
    <div class="grid-bg"></div>

    <header class="landing-header">
        <a href="index.php" class="site-brand">
            <img src="../assets/logo.svg/logo-transparent.png" alt="DGSC" class="site-logo">

            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <nav class="site-nav">
            <a href="index.php" class="active">Главная</a>
            <a href="register.php">Регистрация</a>
            <a href="student_login.php">Войти</a>
            <a href="admin_login.php">Админ</a>
        </nav>
    </header>

    <div class="container">
        <div class="content">

            <h1>
                Практическое применение методики реинжиниринга бизнес-процессов государственных органов
            </h1>

            <p>
                Подайте заявку на участие, дождитесь подтверждения администратора,
                получите временный пароль на почту и пройдите тестирование с практическим заданием по BPMN-моделированию.
            </p>

            <div class="buttons">
                <a class="btn btn-primary" href="register.php">Подать заявку →</a>
                <a class="btn btn-secondary" href="student_login.php">Войти участнику</a>
                <a class="btn btn-secondary" href="admin_login.php">Войти как администратор</a>
            </div>
        </div>

        <div class="panel">
            <div class="panel-inner">
                <div class="panel-title">
                    <h2>Инструкция</h2>
                </div>

                <div class="steps">
                    <div class="step">
                        <div class="step-icon">1</div>
                        <div>
                            <small>Шаг 1</small>
                            <b>Подайте заявку</b>
                            <span>Заполните ФИО, email, телефон и организацию.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">2</div>
                        <div>
                            <small>Шаг 2</small>
                            <b>Дождитесь подтверждения</b>
                            <span>Администратор проверит заявку и подтвердит доступ к тестированию.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">3</div>
                        <div>
                            <small>Шаг 3</small>
                            <b>Получите временный пароль</b>
                            <span>После подтверждения заявки временный пароль будет отправлен на вашу почту и будет действовать 3 дня.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">4</div>
                        <div>
                            <small>Шаг 4</small>
                            <b>Войдите в личный кабинет</b>
                            <span>Используйте email и временный пароль для входа в систему.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">5</div>
                        <div>
                            <small>Шаг 5</small>
                            <b>Пройдите тест и практику</b>
                            <span>Система назначит индивидуальный вариант теста, а затем откроет практическое задание.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">6</div>
                        <div>
                            <small>Шаг 6</small>
                            <b>Получите результат</b>
                            <span>После проверки практического задания администратором итоговый результат будет отображен в личном кабинете.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>
