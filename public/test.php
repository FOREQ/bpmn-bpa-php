<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/csrf.php';

$courseTitle = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';
$activeNav = 'login';
$isKazakh = i18nLocale() === 'kk';
$testUi = [
    'answered' => $isKazakh ? 'Жауап берілді' : 'Заполнено',
    'submittedSuccessfully' => $isKazakh ? 'Тест сәтті жіберілді.' : 'Тест отправлен успешно.',
    'alreadySubmitted' => $isKazakh ? 'Тест бұрын жіберілген.' : 'Тест уже был отправлен.',
    'score' => $isKazakh ? 'Ұпай' : 'Баллы',
    'outOf' => $isKazakh ? '/' : 'из',
    'percent' => $isKazakh ? 'Пайыз' : 'Процент',
    'status' => $isKazakh ? 'Мәртебе' : 'Статус',
    'passed' => $isKazakh ? 'Тест тапсырылды' : 'Тест сдан',
    'failed' => $isKazakh ? 'Тест тапсырылмады' : 'Тест не сдан',
    'goToPractical' => $isKazakh ? 'Практикалық тапсырмаға өту' : 'Перейти к практическому заданию',
];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Теоретический тест | Реинжиниринг бизнес-процессов</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body class="assessment-page theory-page">

<?php require __DIR__ . '/_header.php'; ?>

<section class="assessment-section" style="position: relative;">
    <div class="decor-hex" style="top: 140px; right: calc(50% - 590px); width: 56px; height: 49px;">
        <div class="decor-hex-outer"></div>
        <div class="decor-hex-inner"></div>
    </div>
    <div class="decor-dots" style="bottom: 40px; left: calc(50% - 590px); width: 48px; height: 48px;"></div>

    <div class="container container-860">
        <div class="top-nav">
            <a href="student_dashboard.php">← В личный кабинет</a>
        </div>

        <h1>Теоретический тест</h1>

        <details class="participant-details" data-participant-details open>
        <summary><?= $isKazakh ? 'Курс және қатысушы' : 'Курс и участник' ?></summary>
        <p class="hint">
            Курс: «<?= htmlspecialchars($courseTitle) ?>»
        </p>

        <div id="participantInfo"></div>
        </details>

        <div id="progressBox" class="progress-box" style="display:none;">
            <div class="progress-top">
                <span id="progressText">Заполнено: 0/0</span>
                <span id="progressPercent">0%</span>
            </div>
            <div class="progress">
                <div id="progressBar"></div>
            </div>
        </div>

        <div id="message" class="message" role="status" aria-live="polite"></div>
        <form id="testForm"></form>
    </div>
</section>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-viewer.production.min.js"></script>
<script src="bpmn_static.js"></script>
<script>
    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('sessionId');
    const pageLocale = <?= json_encode(i18nLocale()) ?>;
    const csrfToken = '<?= htmlspecialchars(csrfToken()) ?>';
    const courseTitle = <?= json_encode($courseTitle, JSON_UNESCAPED_UNICODE) ?>;
    const testUi = <?= json_encode($testUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const participantInfo = document.getElementById('participantInfo');
    const testForm = document.getElementById('testForm');
    const message = document.getElementById('message');

    const progressBox = document.getElementById('progressBox');
    const progressText = document.getElementById('progressText');
    const progressPercent = document.getElementById('progressPercent');
    const progressBar = document.getElementById('progressBar');

    let loadedTest = null;
    let currentQuestionIndex = 0;

    const answersStorageKey = 'testAnswers_' + sessionId;
    const positionStorageKey = 'testPosition_' + sessionId;

    function restoreCurrentQuestionIndex() {
        if (!loadedTest) {
            return;
        }

        const savedPosition = Number.parseInt(localStorage.getItem(positionStorageKey), 10);
        const questionsCount = loadedTest.test.questions.length;

        if (Number.isInteger(savedPosition) && savedPosition >= 0 && savedPosition < questionsCount) {
            currentQuestionIndex = savedPosition;
        }
    }

    function saveCurrentQuestionIndex() {
        if (!loadedTest) {
            return;
        }

        localStorage.setItem(positionStorageKey, String(currentQuestionIndex));
    }

    function escapeHtml(text) {
        return String(text ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showMessage(text, type = 'success') {
        message.innerHTML = text;
        message.className = 'message ' + type;
        message.style.display = 'block';
    }

    function isCompletedStatus(status) {
        return status === 'passed' || status === 'failed';
    }

    function showTestResult(result, alreadySubmitted = false) {
        const statusText = result.status === 'passed' ? testUi.passed : testUi.failed;
        const heading = alreadySubmitted ? testUi.alreadySubmitted : testUi.submittedSuccessfully;

        progressBox.style.display = 'none';
        testForm.innerHTML = '';

        showMessage(`
            <b>${escapeHtml(heading)}</b><br>
            ${escapeHtml(testUi.score)}: <b>${escapeHtml(result.score)}</b>
            ${escapeHtml(testUi.outOf)} <b>${escapeHtml(result.total)}</b><br>
            ${escapeHtml(testUi.percent)}: <b>${escapeHtml(result.percent)}%</b><br>
            ${escapeHtml(testUi.status)}: <b>${escapeHtml(statusText)}</b><br><br>
            <a href="practical.php?sessionId=${encodeURIComponent(sessionId)}">
                ${escapeHtml(testUi.goToPractical)}
            </a>
        `, 'success');
    }

    async function loadTest() {
        if (!sessionId) {
            showMessage('sessionId не передан в ссылке', 'error');
            return;
        }

        try {
            const response = await fetch('../api/test.php?sessionId=' + encodeURIComponent(sessionId) + '&lang=' + encodeURIComponent(pageLocale));
            const json = await response.json();

            if (!json.success) {
                showMessage(json.message || 'Ошибка загрузки теста', 'error');
                return;
            }

            loadedTest = json;
            restoreCurrentQuestionIndex();

            participantInfo.innerHTML = `
                <div class="info-strip">
                    <div class="info-strip-cell">
                        <div class="info-strip-label">Участник</div>
                        <div class="info-strip-value">${escapeHtml(json.participant.fullName)}</div>
                    </div>
                    <div class="info-strip-cell">
                        <div class="info-strip-label">Организация</div>
                        <div class="info-strip-value">${escapeHtml(json.participant.organization)}</div>
                    </div>
                    <div class="info-strip-cell" style="border-right:none;">
                        <div class="info-strip-label">Вариант теста</div>
                        <div class="info-strip-value">${escapeHtml(json.test.variantId)}</div>
                    </div>
                </div>
            `;

            if (isCompletedStatus(json.participant.status)) {
                localStorage.removeItem(answersStorageKey);
                localStorage.removeItem(positionStorageKey);
                showTestResult(json.participant, true);
                return;
            }

            progressBox.style.display = 'block';

            renderCurrentQuestion();
            updateProgress();
        } catch (error) {
            showMessage('Ошибка соединения с сервером', 'error');
        }
    }

    function renderCurrentQuestion() {
        if (!loadedTest) {
            return;
        }

        const questions = loadedTest.test.questions;
        const question = questions[currentQuestionIndex];

        let optionsHtml = '';

        question.options.forEach(option => {
            optionsHtml += `
                <label>
                    <input type="radio" name="${escapeHtml(question.id)}" value="${escapeHtml(option.id)}" aria-label="${escapeHtml(option.text || '<?= $isKazakh ? 'Жауап нұсқасы' : 'Вариант ответа' ?>')}">
                    <span class="option-content">${escapeHtml(option.text || '')}
                    ${option.image ? `<img src="${escapeHtml(option.image)}" class="option-image" alt="<?= $isKazakh ? 'Жауап нұсқасының сызбасы' : 'Схема варианта ответа' ?>">` : ''}</span>
                </label>
            `;
        });

        testForm.innerHTML = `
            <div class="question">
                <div class="question-eyebrow">Вопрос ${currentQuestionIndex + 1} из ${questions.length}</div>
                <p tabindex="-1" class="question-title">${escapeHtml(question.text)}</p>
                ${question.image || question.options.some(option => option.image) ? '<div class="diagram-zoom-hint"><?= $isKazakh ? 'Үлкейту үшін сызбаны басыңыз.' : 'Нажмите на схему, чтобы увеличить.' ?></div>' : ''}
                ${question.image ? `<img class="question-image" src="${escapeHtml(question.image)}" alt="Изображение к вопросу">` : ''}
                <div class="question-options">
                    ${optionsHtml}
                </div>
            </div>

            <div class="buttons-row">
                <button type="button" id="prevBtn" class="btn btn-secondary">Назад</button>
                <button type="button" id="nextBtn" class="btn btn-primary">Далее</button>
                <button type="submit" id="submitBtn" class="btn btn-primary">Отправить ответы</button>
            </div>
        `;

        restoreAnswersFromLocalStorage();

        testForm.querySelectorAll('input[type="radio"]').forEach(input => {
            input.addEventListener('change', () => {
                saveAnswersToLocalStorage();
                updateProgress();
                if (message.classList.contains('error')) message.textContent = '';
            });
        });

        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const submitBtn = document.getElementById('submitBtn');

        prevBtn.disabled = currentQuestionIndex === 0;

        if (currentQuestionIndex === questions.length - 1) {
            nextBtn.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
        } else {
            nextBtn.style.display = 'inline-flex';
            submitBtn.style.display = 'none';
        }

        prevBtn.addEventListener('click', () => {
            if (currentQuestionIndex > 0) {
                currentQuestionIndex--;
                saveCurrentQuestionIndex();
                renderCurrentQuestion();
                updateProgress();
                focusCurrentQuestion();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentQuestionIndex < questions.length - 1) {
                currentQuestionIndex++;
                saveCurrentQuestionIndex();
                renderCurrentQuestion();
                updateProgress();
                focusCurrentQuestion();
            }
        });

        updateProgress();
    }

    function focusCurrentQuestion() {
        const title = testForm.querySelector('.question-title');
        title?.focus({ preventScroll: true });
        testForm.scrollIntoView({ block: 'start', behavior: 'auto' });
    }

    function saveAnswersToLocalStorage() {
        if (!loadedTest) {
            return;
        }

        const saved = localStorage.getItem(answersStorageKey);
        const answers = saved ? JSON.parse(saved) : {};

        loadedTest.test.questions.forEach(question => {
            const selected = testForm.querySelector(`input[name="${question.id}"]:checked`);

            if (selected) {
                answers[question.id] = selected.value;
            }
        });

        localStorage.setItem(answersStorageKey, JSON.stringify(answers));
    }

    function restoreAnswersFromLocalStorage() {
        const saved = localStorage.getItem(answersStorageKey);

        if (!saved) {
            return;
        }

        try {
            const answers = JSON.parse(saved);

            Object.keys(answers).forEach(questionId => {
                const optionId = answers[questionId];

                const input = testForm.querySelector(
                    `input[name="${questionId}"][value="${optionId}"]`
                );

                if (input) {
                    input.checked = true;
                }
            });
        } catch (error) {
            console.warn('Не удалось восстановить ответы теста', error);
        }
    }

    function getSavedAnswers() {
        const saved = localStorage.getItem(answersStorageKey);

        if (!saved) {
            return {};
        }

        try {
            return JSON.parse(saved);
        } catch (error) {
            return {};
        }
    }

    function updateProgress() {
        if (!loadedTest) {
            return;
        }

        const total = loadedTest.test.questions.length;
        const answers = getSavedAnswers();
        const answered = Object.keys(answers).length;
        const percent = total > 0 ? Math.round((answered / total) * 100) : 0;

        progressText.textContent = `${testUi.answered}: ${answered}/${total}`;
        progressPercent.textContent = percent + '%';
        progressBar.style.width = percent + '%';
    }

    testForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!loadedTest) {
            showMessage('Тест еще не загружен', 'error');
            return;
        }

        saveAnswersToLocalStorage();

        const answers = getSavedAnswers();

        if (Object.keys(answers).length !== loadedTest.test.questions.length) {
            showMessage('Ответьте на все вопросы', 'error');

            const firstMissingIndex = loadedTest.test.questions.findIndex(question => !answers[question.id]);

            if (firstMissingIndex !== -1) {
                currentQuestionIndex = firstMissingIndex;
                saveCurrentQuestionIndex();
                renderCurrentQuestion();
                updateProgress();
                focusCurrentQuestion();
            }

            message.scrollIntoView({ block: 'start', behavior: 'auto' });
            return;
        }

        try {
            const response = await fetch('../api/submit_test.php?lang=' + encodeURIComponent(pageLocale), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    sessionId: sessionId,
                    answers: answers
                })
            });

            const json = await response.json();

            if (!json.success) {
                if (json.result && isCompletedStatus(json.result.status)) {
                    localStorage.removeItem(answersStorageKey);
                    localStorage.removeItem(positionStorageKey);
                    showTestResult(json.result, true);
                    return;
                }

                showMessage(json.message || 'Ошибка отправки теста', 'error');
                return;
            }

            localStorage.removeItem(answersStorageKey);
            localStorage.removeItem(positionStorageKey);

            showTestResult(json.result);
        } catch (error) {
            showMessage('Ошибка соединения с сервером', 'error');
        }
    });

    window.addEventListener('beforeunload', saveCurrentQuestionIndex);

    loadTest();
</script>
</body>
</html>
