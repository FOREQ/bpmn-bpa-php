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

$navBase = $publicBase;
$logoSrc = $assetsBase . 'logo.svg/logo-black.png';

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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@700;800&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($publicBase) ?>style.css">
</head>
<body>

<?php require __DIR__ . '/_header.php'; ?>

<section style="position: relative; overflow: hidden;">
    <div class="decor-hex" style="top: 130px; right: calc(50% - 480px); width: 56px; height: 49px;">
        <div class="decor-hex-outer"></div>
        <div class="decor-hex-inner"></div>
    </div>
    <div class="decor-dots" style="bottom: 40px; left: calc(50% - 480px); width: 48px; height: 48px;"></div>

    <div class="verify-shell">
        <p class="verify-lead">По QR-коду с сертификата</p>
        <h1>Проверка сертификата</h1>

        <?php if ($certificate): ?>
            <div class="verify-card">
                <div class="verify-hero">
                    <p class="verify-hero-label">Результат проверки</p>
                    <h2>✓ Сертификат подтверждён</h2>
                </div>

                <div class="verify-rows">
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
                </div>

                <p class="verify-footnote">
                    Данный сертификат выдан Центром Поддержки Цифрового Правительства
                    и подтверждён в официальной системе проверки.
                </p>
            </div>
        <?php else: ?>
            <div class="verify-card">
                <div class="verify-hero not-found">
                    <p class="verify-hero-label">Результат проверки</p>
                    <h2>✗ Сертификат не найден</h2>
                </div>

                <p class="verify-footnote">
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
        <?php endif; ?>
    </div>
</section>

</body>
</html>
