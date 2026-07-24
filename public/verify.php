<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/certificate.php';
require_once __DIR__ . '/../lib/error_logger.php';

$token = trim($_GET['token'] ?? '');

/*
 * Страница доступна двумя путями:
 *   /certificate/<токен>        (красивый URL через router.php или .htaccess)
 *   /public/verify.php?token=…  (прямой)
 * Относительные ссылки на стили/логотип при /certificate/… ломаются,
 * поэтому вычисляем базовый префикс проекта из фактического URL
 * (учитывает и запуск в подпапке XAMPP: /bpmn-bpa-php/certificate/…).
 */
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^(.*)/certificate/[a-zA-Z0-9]+/?$#', $requestPath, $m)) {
    $basePrefix = $m[1];
} elseif (preg_match('#^(.*)/public(/|$)#', $requestPath, $m)) {
    $basePrefix = $m[1];
} else {
    $basePrefix = '';
}

$publicBase = $basePrefix . '/public/';
$assetsBase = $basePrefix . '/assets/';

$certificate = null;
$lookupError = false;

if ($token !== '' && preg_match('/^[a-zA-Z0-9]{6,32}$/', $token)) {
    try {
        $certificate = findCertificateByToken(getDb(), $token);
    } catch (Throwable $e) {
        logError($e, 'public/verify.php');
        $lookupError = true;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Проверка сертификата | Реинжиниринг бизнес-процессов</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($publicBase) ?>style.css">

    <style>
        .verify-card {
            max-width: 720px;
            margin: 32px auto;
            background: #ffffff;
            border: 1px solid #d8e4ea;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(24, 59, 89, 0.08);
        }

        .verify-hero {
            padding: 32px 36px;
            color: #ffffff;
            background: linear-gradient(120deg, #991b1b, #b91c1c);
        }

        .verify-hero.valid {
            background: linear-gradient(120deg, #14532d, #166534);
        }

        .verify-hero-label {
            margin: 0 0 6px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            opacity: 0.85;
        }

        .verify-hero-title {
            margin: 0;
            color: #ffffff !important;
            font-size: 28px;
            font-weight: 900;
        }

        .verify-body {
            padding: 28px 36px 36px;
        }

        .verify-row {
            padding: 14px 0;
            border-bottom: 1px solid #e8eff4;
        }

        .verify-row:last-child {
            border-bottom: none;
        }

        .verify-row-label {
            margin-bottom: 6px;
            color: #64748b;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .verify-row-value {
            color: #183b59;
            font-size: 16px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .verify-note {
            margin-top: 20px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }
    </style>
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
        <a href="<?= htmlspecialchars($publicBase) ?>index.php" class="site-brand">
            <img src="<?= htmlspecialchars($assetsBase) ?>logo.svg/logo-transparent.png" alt="DGSC" class="site-logo">

            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <nav class="site-nav">
            <a href="<?= htmlspecialchars($publicBase) ?>index.php">Главная</a>
            <a href="<?= htmlspecialchars($publicBase) ?>register.php">Регистрация</a>
            <a href="<?= htmlspecialchars($publicBase) ?>student_login.php">Войти</a>
            <a href="<?= htmlspecialchars($publicBase) ?>admin_login.php">Админ</a>
        </nav>
    </div>
</header>

<div class="container">
    <h1>Проверка сертификата</h1>

    <?php if ($certificate): ?>
        <div class="verify-card">
            <div class="verify-hero valid">
                <p class="verify-hero-label">Результат проверки</p>
                <h2 class="verify-hero-title">✓ Сертификат подтверждён</h2>
            </div>

            <div class="verify-body">
                <div class="verify-row">
                    <div class="verify-row-label">Номер сертификата</div>
                    <div class="verify-row-value">
                        <?= htmlspecialchars($certificate['certificateNumber'] ?? '—') ?>
                    </div>
                </div>

                <div class="verify-row">
                    <div class="verify-row-label">ФИО</div>
                    <div class="verify-row-value">
                        <?= htmlspecialchars($certificate['fullName'] ?? '—') ?>
                    </div>
                </div>

                <div class="verify-row">
                    <div class="verify-row-label">Курс</div>
                    <div class="verify-row-value">
                        <?= htmlspecialchars($certificate['course'] ?? '—') ?>
                    </div>
                </div>

                <?php if (!empty($certificate['completionDate'])): ?>
                    <div class="verify-row">
                        <div class="verify-row-label">Дата завершения</div>
                        <div class="verify-row-value">
                            <?= htmlspecialchars($certificate['completionDate']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($certificate['level'])): ?>
                    <div class="verify-row">
                        <div class="verify-row-label">Уровень</div>
                        <div class="verify-row-value">
                            <?= htmlspecialchars($certificate['level']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="verify-note">
                    Данный сертификат выдан Центром Поддержки Цифрового Правительства
                    и подтверждён в официальной системе проверки.
                </p>
            </div>
        </div>
    <?php else: ?>
        <div class="verify-card">
            <div class="verify-hero">
                <p class="verify-hero-label">Результат проверки</p>
                <h2 class="verify-hero-title">✗ Сертификат не найден</h2>
            </div>

            <div class="verify-body">
                <p class="verify-note">
                    <?php if ($lookupError): ?>
                        Произошла ошибка при проверке. Попробуйте обновить страницу позже.
                    <?php elseif ($token === ''): ?>
                        В ссылке не указан код сертификата. Отсканируйте QR-код
                        на сертификате ещё раз или проверьте правильность ссылки.
                    <?php else: ?>
                        Сертификат с таким кодом не найден в системе.
                        Проверьте правильность ссылки или отсканируйте QR-код ещё раз.
                        Если вы уверены, что сертификат подлинный, свяжитесь
                        с Центром Поддержки Цифрового Правительства.
                    <?php endif; ?>
                </p>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
