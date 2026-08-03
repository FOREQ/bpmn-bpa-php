<?php

require_once __DIR__ . '/../lib/security.php';

$activeNav = 'login';
$isKazakh = i18nLocale() === 'kk';
$courseTitle = $isKazakh
    ? 'Мемлекеттік органдардың бизнес-процестерін реинжинирингтеу әдістемесін практикалық қолдану'
    : 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';
$pageTitle = $isKazakh
    ? 'Қатысушы нәтижесі | Бизнес-процестер реинжинирингі'
    : 'Результат участника | Реинжиниринг бизнес-процессов';
$backLabel = $isKazakh ? '← Жеке кабинетке' : '← В личный кабинет';
$resultTitle = $isKazakh ? 'Қатысушы нәтижесі' : 'Результат участника';
$courseLabel = $isKazakh ? 'Курс' : 'Курс';
$loadingLabel = $isKazakh ? 'Нәтиже жүктелуде...' : 'Загрузка результата...';
$resultUi = [
    'statusPassed' => $isKazakh ? 'Өтті' : 'Пройден',
    'statusFailed' => $isKazakh ? 'Өтпеді' : 'Не пройден',
    'statusIncomplete' => $isKazakh ? 'Аяқталмаған' : 'Не завершен',
    'afterPracticalReview' => $isKazakh ? 'Практикалық жұмыс тексерілгеннен кейін' : 'После проверки практики',
    'certificate' => $isKazakh ? 'Сертификат' : 'Сертификат',
    'courseCompleted' => $isKazakh ? 'Курс тыңдалды' : 'Курс прослушан',
    'missingSession' => $isKazakh ? 'Сілтемеде sessionId көрсетілмеген' : 'sessionId не передан в ссылке',
    'loadError' => $isKazakh ? 'Нәтижені жүктеу қатесі' : 'Ошибка загрузки результата',
    'connectionError' => $isKazakh ? 'Сервермен байланысу қатесі' : 'Ошибка соединения с сервером',
    'practicalNotSubmitted' => $isKazakh ? 'Практикалық жұмыс жіберілмеген' : 'Практика не отправлена',
    'practicalWaiting' => $isKazakh ? 'Практикалық жұмыс тексеруді күтуде' : 'Практика ожидает проверки',
    'practicalChecked' => $isKazakh ? 'Практикалық жұмыс тексерілді' : 'Практика проверена',
    'task' => $isKazakh ? 'тапсырма' : 'Задача',
    'participantResult' => $isKazakh ? 'Қатысушы нәтижесі' : 'Результат участника',
    'testPassed' => $isKazakh ? 'Теориялық тест тапсырылды' : 'Теоретический тест сдан',
    'testFailed' => $isKazakh ? 'Теориялық тест тапсырылмады' : 'Теоретический тест не сдан',
    'courseName' => $isKazakh ? 'Курс атауы' : 'Название курса',
    'fullName' => $isKazakh ? 'ТАӘ' : 'ФИО',
    'organization' => $isKazakh ? 'Ұйым' : 'Организация',
    'testVariant' => $isKazakh ? 'Тест нұсқасы' : 'Вариант теста',
    'testSubmittedAt' => $isKazakh ? 'Тест жіберілген күн' : 'Дата отправки теста',
    'correctAnswers' => $isKazakh ? 'Дұрыс жауаптар' : 'Правильных ответов',
    'percent' => $isKazakh ? 'Пайыз' : 'Процент',
    'passingThreshold' => $isKazakh ? 'Өту шегі' : 'Порог прохождения',
    'testStatus' => $isKazakh ? 'Тест мәртебесі' : 'Статус теста',
    'practicalAssignment' => $isKazakh ? 'Практикалық тапсырма' : 'Практическое задание',
    'practicalStatus' => $isKazakh ? 'Практикалық жұмыс мәртебесі' : 'Статус практики',
    'practicalSubmittedAt' => $isKazakh ? 'Практикалық жұмыс жіберілген күн' : 'Дата отправки практики',
    'complexityVariant' => $isKazakh ? 'Күрделілік нұсқасы' : 'Вариант сложности',
    'firstTask' => $isKazakh ? 'Бірінші тапсырма' : 'Первое задание',
    'secondTask' => $isKazakh ? 'Екінші тапсырма' : 'Второе задание',
    'complexityCalculation' => $isKazakh ? 'Күрделілікті есептеу' : 'Расчёт сложности',
    'practicalTotal' => $isKazakh ? 'Практикалық жұмыс қорытындысы' : 'Итог практики',
    'checkedAt' => $isKazakh ? 'Тексерілген күн' : 'Дата проверки',
    'notSubmitted' => $isKazakh ? 'Жіберілмеген' : 'Не отправлено',
    'notGraded' => $isKazakh ? 'Әлі бағаланбаған' : 'Еще не оценено',
    'notChecked' => $isKazakh ? 'Әлі тексерілмеген' : 'Еще не проверено',
    'practicalTasks' => $isKazakh ? 'Практикалық тапсырмалар' : 'Практические задачи',
    'finalResult' => $isKazakh ? 'Қорытынды нәтиже' : 'Итоговый результат',
    'testPoints' => $isKazakh ? 'Теориялық тест ұпайы' : 'Баллы за теоретический тест',
    'practicalPoints' => $isKazakh ? 'Практикалық тапсырма ұпайы' : 'Баллы за практическое задание',
    'overallResult' => $isKazakh ? 'Жалпы нәтиже' : 'Общий результат',
    'outcome' => $isKazakh ? 'Қорытынды' : 'Итог',
    'scoreSeparator' => $isKazakh ? '/' : 'из',
    'dashboard' => $isKazakh ? 'Жеке кабинет' : 'Личный кабинет',
    'home' => $isKazakh ? 'Басты бетке' : 'На главную',
    'downloadCertificate' => $isKazakh ? 'Сертификатты жүктеу' : 'Скачать сертификат',
];

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/_header.php'; ?>

<section style="position: relative; overflow: hidden;">
    <div class="decor-hex" style="top: 130px; right: calc(50% - 590px); width: 56px; height: 49px;">
        <div class="decor-hex-outer"></div>
        <div class="decor-hex-inner"></div>
    </div>
    <div class="decor-dots" style="bottom: 40px; left: calc(50% - 590px); width: 48px; height: 48px;"></div>

    <div class="container container-1040">
        <div class="top-nav">
            <a href="student_dashboard.php"><?= htmlspecialchars($backLabel) ?></a>
        </div>

        <h1><?= htmlspecialchars($resultTitle) ?></h1>

        <p class="hint">
            <?= htmlspecialchars($courseLabel) ?>: «<?= htmlspecialchars($courseTitle) ?>»
        </p>

        <div id="content"><?= htmlspecialchars($loadingLabel) ?></div>
    </div>
</section>

<script>
    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('sessionId');
    const content = document.getElementById('content');
    const courseTitle = <?= json_encode($courseTitle, JSON_UNESCAPED_UNICODE) ?>;
    const resultUi = <?= json_encode($resultUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    function statusText(status) {
        if (status === 'passed') return resultUi.statusPassed;
        if (status === 'failed') return resultUi.statusFailed;
        return resultUi.statusIncomplete;
    }

    function statusClass(status) {
        if (status === 'passed') return 'passed';
        if (status === 'failed') return 'failed';
        return 'waiting';
    }

    function metric(label, value, extraClass = '') {
        return `
            <div class="rp-metric">
                <div class="rp-metric-label">${label}</div>
                <div class="rp-metric-value ${extraClass}">${value}</div>
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

    function certificateLabel(total, isGraded) {
        if (!isGraded) {
            return resultUi.afterPracticalReview;
        }

        total = Number(total) || 0;

        if (total >= 46) {
            return resultUi.certificate + ' A';
        }

        if (total >= 38) {
            return resultUi.certificate + ' B';
        }

        if (total >= 26) {
            return resultUi.certificate + ' C';
        }

        return resultUi.courseCompleted;
    }

    async function loadResult() {
        if (!sessionId) {
            content.innerHTML = '<div class="error">' + escapeHtml(resultUi.missingSession) + '</div>';
            return;
        }

        try {
            const response = await fetch('../api/result.php?sessionId=' + encodeURIComponent(sessionId));
            const json = await response.json();

            if (!json.success) {
                content.innerHTML = '<div class="error">' + escapeHtml(json.message || resultUi.loadError) + '</div>';
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
                practicalStatus = '<span class="status waiting">' + escapeHtml(resultUi.practicalNotSubmitted) + '</span>';
            } else if (!practical.isGraded) {
                practicalStatus = '<span class="status waiting">' + escapeHtml(resultUi.practicalWaiting) + '</span>';
            } else {
                practicalStatus = '<span class="status passed">' + escapeHtml(resultUi.practicalChecked) + '</span>';
            }

            let tasksHtml = '';

            practical.tasks.forEach((task, index) => {
                tasksHtml += `
                    <li>
                        <b>${resultUi.task === 'тапсырма' ? `${index + 1}-${escapeHtml(resultUi.task)}` : `${escapeHtml(resultUi.task)} ${index + 1}`}:</b>
                        ${escapeHtml(task.description)}
                    </li>
                `;
            });

            const testPoints = Math.round(((Number(test.percent) || 0) / 100) * 20);
            const practicalPoints = Number(practical.scores.total) || 0;
            const overallTotal = testPoints + practicalPoints;

            const finalLabel = certificateLabel(overallTotal, practical.isGraded);

            content.innerHTML = `
                <div class="rp-card">
                    <div class="rp-hero ${test.status === 'passed' ? 'passed' : ''}">
                        <p class="rp-label">${escapeHtml(resultUi.participantResult)}</p>
                        <h2 class="rp-title">${escapeHtml(test.status === 'passed' ? resultUi.testPassed : resultUi.testFailed)}</h2>
                    </div>

                    <div class="rp-grid">
                        ${metric(escapeHtml(resultUi.courseName), escapeHtml(courseTitle))}
                        ${metric(escapeHtml(resultUi.fullName), escapeHtml(participant.fullName))}
                        ${metric(escapeHtml(resultUi.organization), escapeHtml(participant.organization))}
                        ${metric(escapeHtml(resultUi.testVariant), escapeHtml(participant.variantId))}
                        ${metric(escapeHtml(resultUi.testSubmittedAt), escapeHtml(test.submittedAt || '-'))}
                        ${metric(escapeHtml(resultUi.correctAnswers), escapeHtml(test.score + ' ' + resultUi.scoreSeparator + ' ' + test.total))}
                        ${metric(escapeHtml(resultUi.percent), escapeHtml(test.percent + '%'))}
                        ${metric(escapeHtml(resultUi.passingThreshold), '70%')}
                        ${metric(escapeHtml(resultUi.testStatus), `<span class="status ${statusClass(test.status)}">${escapeHtml(statusText(test.status))}</span>`)}
                    </div>
                </div>

                <div class="rp-section">
                    <div class="rp-section-header">
                        <h2>${escapeHtml(resultUi.practicalAssignment)}</h2>
                    </div>

                    <div class="rp-grid">
                        ${metric(escapeHtml(resultUi.practicalStatus), practicalStatus)}
                        ${metric(escapeHtml(resultUi.practicalSubmittedAt), escapeHtml(practical.submittedAt || resultUi.notSubmitted))}
                        ${metric(escapeHtml(resultUi.complexityVariant), escapeHtml(practical.complexityVariantId))}
                        ${metric(escapeHtml(resultUi.firstTask), escapeHtml(practical.scores.previousTaskScore ?? resultUi.notGraded))}
                        ${metric(escapeHtml(resultUi.secondTask), escapeHtml(practical.scores.newTaskScore ?? resultUi.notGraded))}
                        ${metric(escapeHtml(resultUi.complexityCalculation), escapeHtml(practical.scores.metricsScore ?? resultUi.notGraded))}
                        ${metric(escapeHtml(resultUi.practicalTotal), escapeHtml(practical.scores.total ?? resultUi.notGraded))}
                        ${metric(escapeHtml(resultUi.checkedAt), escapeHtml(practical.scores.gradedAt || resultUi.notChecked))}
                    </div>

                    <div class="rp-tasks">
                        <h3>${escapeHtml(resultUi.practicalTasks)}</h3>
                        <ul>${tasksHtml}</ul>
                    </div>
                </div>

                <div class="rp-section">
                    <div class="rp-section-header">
                        <h2>${escapeHtml(resultUi.finalResult)}</h2>
                    </div>

                    <div class="rp-grid">
                        ${metric(escapeHtml(resultUi.testPoints), escapeHtml(testPoints + ' ' + resultUi.scoreSeparator + ' 20'), 'emphasis')}
                        ${metric(escapeHtml(resultUi.practicalPoints), practical.isGraded ? escapeHtml(practicalPoints + ' ' + resultUi.scoreSeparator + ' 30') : escapeHtml(resultUi.afterPracticalReview), 'emphasis')}
                        ${metric(escapeHtml(resultUi.overallResult), practical.isGraded ? escapeHtml(overallTotal + ' ' + resultUi.scoreSeparator + ' 50') : escapeHtml(resultUi.afterPracticalReview), 'emphasis')}
                        ${metric(escapeHtml(resultUi.outcome), escapeHtml(finalLabel), 'emphasis accent')}
                    </div>
                </div>

                <div class="rp-actions">
                    <a href="student_dashboard.php" class="rp-home">
                        ${escapeHtml(resultUi.dashboard)}
                    </a>

                    <a href="index.php" class="rp-home">
                        ${escapeHtml(resultUi.home)}
                    </a>

                    ${practical.isGraded ? `
                        <a href="../api/certificate.php?sessionId=${encodeURIComponent(sessionId)}" class="rp-home primary">
                            ${escapeHtml(resultUi.downloadCertificate)}
                        </a>
                    ` : ''}
                </div>
            `;
        } catch (error) {
            content.innerHTML = '<div class="error">' + escapeHtml(resultUi.connectionError) + '</div>';
        }
    }

    loadResult();
</script>
</body>
</html>
