<?php

require_once __DIR__ . '/../lib/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['student_session_id'])) {
    header('Location: student_login.php');
    exit;
}

$pdo = getDb();

$stmt = $pdo->prepare("
    SELECT *
    FROM Participant
    WHERE sessionId = :sessionId
    LIMIT 1
");

$stmt->execute([
    ':sessionId' => $_SESSION['student_session_id']
]);

$participant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$participant) {
    session_destroy();
    header('Location: student_login.php');
    exit;
}

$accountStatus = $participant['accountStatus'] ?? 'pending';

if ($accountStatus !== 'approved') {
    session_destroy();
    header('Location: student_login.php');
    exit;
}

if (empty($participant['tempPasswordExpiresAt'])) {
    session_destroy();
    header('Location: student_login.php');
    exit;
}

$tempPasswordExpiresAt = strtotime($participant['tempPasswordExpiresAt']);

if ($tempPasswordExpiresAt === false || time() > $tempPasswordExpiresAt) {
    session_destroy();
    header('Location: student_login.php?expired=1');
    exit;
}

$courseTitle = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';

$sessionId = $participant['sessionId'];

$testSubmitted = !empty($participant['submittedAt']);
$practicalSubmitted = !empty($participant['practicalSubmittedAt']);
$practicalGraded = !empty($participant['practicalGradedAt']);

$testScore = $participant['score'] ?? 0;
$testTotal = $participant['total'] ?? 20;
$testPercent = $participant['percent'] ?? 0;
$testStatus = $participant['status'] ?? null;

$practicalTotal = $participant['practicalScoreTotal'] ?? 0;

$overallTotal = round((($testPercent ?: 0) / 100) * 20) + (int)$practicalTotal;

if (!$testSubmitted) {
    $mainActionText = 'Начать теоретический тест';
    $mainActionLink = 'test.php?sessionId=' . urlencode($sessionId);
} elseif ($testSubmitted && !$practicalSubmitted) {
    $mainActionText = 'Перейти к практическому заданию';
    $mainActionLink = 'practical.php?sessionId=' . urlencode($sessionId);
} else {
    $mainActionText = 'Посмотреть результаты';
    $mainActionLink = 'result.php?sessionId=' . urlencode($sessionId);
}

function statusBadge(string $text, string $class): string
{
    return '<span class="status ' . htmlspecialchars($class) . '">' . htmlspecialchars($text) . '</span>';
}

function certificateLabel(int $overallTotal, bool $practicalGraded): string
{
    if (!$practicalGraded) {
        return 'После проверки практики';
    }

    if ($overallTotal >= 46) {
        return 'Сертификат A';
    }

    if ($overallTotal >= 38) {
        return 'Сертификат B';
    }

    if ($overallTotal >= 26) {
        return 'Сертификат C';
    }

    return 'Прослушал курс';
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Личный кабинет участника</title>
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
            <a href="register.php">Регистрация</a>
            <a href="student_login.php" class="active">Войти</a>
            <a href="admin_login.php">Админ</a>
        </nav>
    </div>
</header>

<div class="container">
    <div class="top-nav">
        <a href="index.php">← На главную</a>
    </div>

    <h1>Личный кабинет участника</h1>

    <p class="hint">
        Курс: «<?= htmlspecialchars($courseTitle) ?>»
    </p>

    <div class="rp-card">
        <div class="rp-hero <?= $testStatus === 'passed' ? 'passed' : '' ?>">
            <p class="rp-label">Кабинет участника</p>

            <h2 class="rp-title">
                <?= htmlspecialchars($participant['fullName'] ?? '') ?>
            </h2>

            <p class="hint" style="color: #ffffff; margin-top: 12px;">
                Доступ активен до:
                <b><?= htmlspecialchars($participant['tempPasswordExpiresAt'] ?? '—') ?></b>
            </p>
        </div>

        <div class="rp-grid">
            <div class="rp-metric">
                <div class="rp-metric-label">Название курса</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($courseTitle) ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">ФИО</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['fullName'] ?? '') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Email</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['email'] ?? '') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Организация</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['organization'] ?? '') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Вариант теста</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['variantId'] ?? '') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Статус доступа</div>
                <div class="rp-metric-value">
                    <?= statusBadge('Подтвержден', 'passed') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Временный пароль действует до</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['tempPasswordExpiresAt'] ?? '—') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Статус теста</div>
                <div class="rp-metric-value">
                    <?php
                    if (!$testSubmitted) {
                        echo statusBadge('Не сдан', 'waiting');
                    } elseif ($testStatus === 'passed') {
                        echo statusBadge('Сдан', 'passed');
                    } else {
                        echo statusBadge('Не пройден', 'failed');
                    }
                    ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Баллы теста</div>
                <div class="rp-metric-value">
                    <?= $testSubmitted
                        ? htmlspecialchars($testScore . ' из ' . $testTotal)
                        : 'Еще не сдан'
                    ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Процент теста</div>
                <div class="rp-metric-value">
                    <?= $testSubmitted ? htmlspecialchars($testPercent . '%') : '—' ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Практическое задание</div>
                <div class="rp-metric-value">
                    <?php
                    if (!$practicalSubmitted) {
                        echo statusBadge('Не отправлено', 'waiting');
                    } elseif (!$practicalGraded) {
                        echo statusBadge('Ожидает проверки', 'waiting');
                    } else {
                        echo statusBadge('Проверено', 'passed');
                    }
                    ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Баллы практики</div>
                <div class="rp-metric-value">
                    <?= $practicalGraded
                        ? htmlspecialchars($practicalTotal . ' из 30')
                        : 'Еще не оценено'
                    ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Общий результат</div>
                <div class="rp-metric-value">
                    <?= $practicalGraded
                        ? htmlspecialchars($overallTotal . ' из 50')
                        : 'После проверки практики'
                    ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Итог</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars(certificateLabel((int)$overallTotal, $practicalGraded)) ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Последний вход</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['lastLoginAt'] ?? '—') ?>
                </div>
            </div>

            <div class="rp-metric">
                <div class="rp-metric-label">Дата регистрации</div>
                <div class="rp-metric-value">
                    <?= htmlspecialchars($participant['createdAt'] ?? '—') ?>
                </div>
            </div>
        </div>

        <div style="padding: 0 28px 28px;">
            <a class="button" href="<?= htmlspecialchars($mainActionLink) ?>">
                <?= htmlspecialchars($mainActionText) ?>
            </a>

            <?php if ($practicalGraded): ?>
                <a
                    class="button"
                    href="../api/certificate.php?sessionId=<?= urlencode($sessionId) ?>"
                    style="margin-left: 12px;"
                >
                    Скачать сертификат
                </a>
            <?php endif; ?>

            <a
                class="button"
                href="student_logout.php"
                style="margin-left: 12px; background: #ffffff; color: var(--ink); border: 1px solid var(--line);"
            >
                Выйти
            </a>
        </div>
    </div>
</div>

</body>
</html>
