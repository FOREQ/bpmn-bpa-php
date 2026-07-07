<?php

require_once __DIR__ . '/../lib/security.php';

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>DGSC | BPMN/BPA тестирование</title>

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
            font-family: Arial, sans-serif;
            color: white;
            background: #0f172a;
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
            color: white;
            text-decoration: none;
        }

        .site-logo {
            width: 110px;
            height: auto;
            display: block;
        }

        .site-brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }

        .site-brand-title,
        .site-brand-subtitle {
            color: white;
            font-weight: 900;
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
            font-weight: 900;
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
            grid-template-columns: 1.08fr 0.92fr;
            gap: 48px;
            align-items: center;
            padding: 40px 0 70px;
        }

        .content h1 {
            margin: 0;
            font-size: 58px;
            line-height: 1.08;
            font-weight: 900;
            max-width: 780px;
        }

        .content p {
            margin-top: 24px;
            max-width: 670px;
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
            font-weight: 900;
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
            grid-template-columns: 54px 1fr;
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
            font-weight: 900;
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
        }

        .step span {
            display: block;
            margin-top: 6px;
            color: #cbd5e1;
            line-height: 1.5;
        }

        @media (max-width: 900px) {
            .container {
                grid-template-columns: 1fr;
            }

            .content h1 {
                font-size: 40px;
            }

            .content p {
                font-size: 18px;
            }

            .landing-header {
                flex-direction: column;
                gap: 18px;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>
<section class="hero">
    <div class="grid-bg"></div>

    <header class="landing-header">
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
    </header>

    <div class="container">
        <div class="content">

            <h1>Электронное тестирование по основам BPA и нотации BPMN</h1>

            <p>
                Зарегистрируйтесь после обучения, получите индивидуальный вариант теста
                и выполните практическое задание с BPMN-моделлером.
            </p>

            <div class="buttons">
                <a class="btn btn-primary" href="register.php">Начать тест →</a>
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
                            <b>Зарегистрируйтесь</b>
                            <span>Заполните ФИО, email, телефон и организацию.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">2</div>
                        <div>
                            <small>Шаг 2</small>
                            <b>Пройдите тест</b>
                            <span>Система назначит вариант A, B или C и сохранит результат.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">3</div>
                        <div>
                            <small>Шаг 3</small>
                            <b>Выполните практику</b>
                            <span>Постройте BPMN-схемы и заполните расчет сложности процесса.</span>
                        </div>
                    </div>

                    <div class="step">
                        <div class="step-icon">4</div>
                        <div>
                            <small>Шаг 4</small>
                            <b>Получите результат</b>
                            <span>После отправки система сохранит данные, а администратор проверит практику.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>