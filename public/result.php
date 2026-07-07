<?php

require_once __DIR__ . '/../lib/security.php';

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Результат участника</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div class="site-header-inner">
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
    </div>
</header>

<div class="container">
    <div class="top-nav">
        <a href="index.php">← На главную</a>
    </div>

    <h1>Результат участника</h1>

    <div id="content">Загрузка результата...</div>
</div>

<script>
    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('sessionId');
    const content = document.getElementById('content');

    function statusText(status) {
        if (status === 'passed') return 'Пройден';
        if (status === 'failed') return 'Не пройден';
        return 'Не завершен';
    }

    function statusClass(status) {
        if (status === 'passed') return 'passed';
        if (status === 'failed') return 'failed';
        return 'waiting';
    }

    function metric(label, value) {
        return `
            <div class="rp-metric">
                <div class="rp-metric-label">${label}</div>
                <div class="rp-metric-value">${value}</div>
            </div>
        `;
    }

function escapeHtml(text) {
    return String(text ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

    async function loadResult() {
        if (!sessionId) {
            content.innerHTML = '<div class="error">sessionId не передан в ссылке</div>';
            return;
        }

        try {
            const response = await fetch('../api/result.php?sessionId=' + encodeURIComponent(sessionId));
            const json = await response.json();

            if (!json.success) {
                content.innerHTML = '<div class="error">' + (json.message || 'Ошибка загрузки результата') + '</div>';
                return;
            }

            const participant = json.participant;
            const test = json.testResult;
            const practical = json.practical;

            if (test.submittedAt && !practical.isSubmitted) {
                window.location.href = 'practical.php?sessionId=' + encodeURIComponent(sessionId);
                return;
            }

            let practicalStatus = '';

            if (!practical.isSubmitted) {
                practicalStatus = '<span class="status waiting">Практика не отправлена</span>';
            } else if (!practical.isGraded) {
                practicalStatus = '<span class="status waiting">Практика ожидает проверки</span>';
            } else {
                practicalStatus = '<span class="status passed">Практика проверена</span>';
            }

            let tasksHtml = '';

            practical.tasks.forEach((task, index) => {
                tasksHtml += `
                    <li>
                        <b>Задача ${index + 1}:</b>
                       ${escapeHtml(task.description)}
                    </li>
                `;
            });

            content.innerHTML = `
                <div class="rp-card">
                    <div class="rp-hero ${test.status === 'passed' ? 'passed' : ''}">
                        <p class="rp-label">Результат тестирования</p>
                        <h2 class="rp-title">${test.status === 'passed' ? 'Тест сдан' : 'Тест не сдан'}</h2>
                    </div>

                    <div class="rp-grid">
                        ${metric('ФИО', escapeHtml(participant.fullName))}
                        ${metric('Организация', escapeHtml(participant.organization))}
                        ${metric('Вариант', escapeHtml(participant.variantId))}
                        ${metric('Дата', escapeHtml(test.submittedAt || '-'))}
                        ${metric('Правильных ответов', test.score + ' из ' + test.total)}
                        ${metric('Процент', test.percent + '%')}
                        ${metric('Порог', '70%')}
                        ${metric('Статус', `<span class="status ${statusClass(test.status)}">${statusText(test.status)}</span>`)}
                    </div>
                </div>

                <div class="rp-section">
                    <div class="rp-section-header">
                        <h2>Практический экзамен отправлен</h2>
                    </div>

                    <div class="rp-grid">
                        ${metric('Статус практики', practicalStatus)}
                       ${metric('Дата отправки', escapeHtml(practical.submittedAt || 'Не отправлено'))}
                        ${metric('Вариант сложности', escapeHtml(practical.complexityVariantId))}
                        ${metric('Первое задание', practical.scores.previousTaskScore ?? 'Еще не оценено')}
                        ${metric('Второе задание', practical.scores.newTaskScore ?? 'Еще не оценено')}
                        ${metric('Расчет сложности', practical.scores.metricsScore ?? 'Еще не оценено')}
                        ${metric('Итог практики', practical.scores.total ?? 'Еще не оценено')}
                        ${metric('Дата проверки', escapeHtml(practical.scores.gradedAt || 'Еще не проверено'))}
                    </div>

                    <div class="rp-tasks">
                        <h3>Задания</h3>
                        <ul>${tasksHtml}</ul>
                    </div>
                </div>

                <div class="rp-actions">
    <a href="index.php" class="rp-home">На главную</a>

    <a href="student_dashboard.php" class="rp-home">
        Личный кабинет
    </a>

    ${practical.isGraded ? `
        <a href="../api/certificate.php?sessionId=${encodeURIComponent(sessionId)}" class="rp-home">
            Скачать сертификат
        </a>
    ` : ''}
</div>
            `;
        } catch (error) {
            content.innerHTML = '<div class="error">Ошибка соединения с сервером</div>';
        }
    }

    loadResult();
</script>
</body>
</html>