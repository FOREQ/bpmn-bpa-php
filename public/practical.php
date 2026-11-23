<?php

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/security.php';

$courseTitle = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';
$activeNav = 'login';
$isKazakh = i18nLocale() === 'kk';
$practicalUi = [
    'draftSaved' => $isKazakh ? 'Браузерде сақталды ✓' : 'Сохранено в браузере ✓',
    'draftFailed' => $isKazakh ? 'Жобаны сақтау мүмкін болмады. Жұмысты жібермейінше бетті жаппаңыз.' : 'Не удалось сохранить черновик. Не закрывайте страницу до отправки работы.',
    'stepLabel' => $isKazakh ? '{total} қадамның {current}-қадамы' : 'Шаг {current} из {total}',
    'testResult' => $isKazakh ? 'Тест нәтижесі' : 'Результат теста',
    'scoreSeparator' => $isKazakh ? '/' : 'из',
    'submittedTitle' => $isKazakh ? 'Практикалық жұмыс жіберілді' : 'Практическая работа отправлена',
    'submittedText' => $isKazakh
        ? 'Жауаптарыңыз сақталды және әкімшінің тексеруін күтуде.'
        : 'Ваши ответы сохранены и ожидают проверки администратором.',
    'submittedAt' => $isKazakh ? 'Жіберілген уақыты' : 'Дата отправки',
    'goToResult' => $isKazakh ? 'Нәтижеге өту' : 'Перейти к результату',
];

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Практическое задание | Реинжиниринг бизнес-процессов</title>

    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body class="assessment-page practice-page">

<?php require __DIR__ . '/_header.php'; ?>

<section class="assessment-section" style="position: relative;">
<div class="decor-hex" style="top: 130px; right: calc(50% - 590px); width: 56px; height: 49px;">
    <div class="decor-hex-outer"></div>
    <div class="decor-hex-inner"></div>
</div>
<div class="decor-dots" style="bottom: 40px; left: calc(50% - 590px); width: 48px; height: 48px;"></div>

<div class="container container-practical">
    <div class="top-nav">
        <a href="student_dashboard.php">← В личный кабинет</a>
    </div>

    <h1>Практическое задание</h1>

    <details class="participant-details" data-participant-details open>
    <summary><?= $isKazakh ? 'Курс және қатысушы' : 'Курс и участник' ?></summary>
    <p class="hint">
        Курс: «<?= htmlspecialchars($courseTitle) ?>»
    </p>

    <div id="participantInfo"></div>
    </details>

    <div id="practicalProgress" class="progress-box">
        <div class="progress-top">
            <span id="stepText">Шаг 1 из 3</span>
            <span id="progressPercent">33%</span>
        </div>
        <div class="progress">
            <div id="progressBar"></div>
        </div>
    </div>

    <div id="message" class="message" role="status" aria-live="polite"></div>
    <form id="practicalForm" class="practical-form">
        <div id="tasksContainer"></div>

        <div id="complexityStep" class="complexity-step practical-card step-hidden">
            <h2>Расчет сложности процесса</h2>
            <p class="hint">
                Заполните показатели сложности бизнес-процесса согласно предложенной диаграмме.
            </p>

            <div id="complexityImage"></div>
            <div id="complexityContainer"></div>
        </div>

        <div class="step-buttons practical-buttons">
            <button type="button" id="prevStepBtn" class="back-btn">Назад</button>
            <button type="button" id="saveDraftBtn" class="draft-btn" aria-live="polite">Сохранить черновик</button>
            <button type="button" id="nextStepBtn" class="next-btn">Далее</button>
            <button type="submit" id="submitBtn" class="finish-btn" aria-label="<?= $isKazakh ? 'Практикалық тапсырманы аяқтау' : 'Завершить практическое задание' ?>"><span class="full-button-label">Завершить практическое задание</span><span class="mobile-button-label" aria-hidden="true"><?= $isKazakh ? 'Аяқтау' : 'Завершить' ?></span></button>
        </div>
    </form>

</div>
</section>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-modeler.development.js"></script>

<script>
    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('sessionId');
    const pageLocale = <?= json_encode(i18nLocale()) ?>;
    const csrfToken = '<?= htmlspecialchars(csrfToken()) ?>';
    const courseTitle = <?= json_encode($courseTitle, JSON_UNESCAPED_UNICODE) ?>;
    const practicalUi = <?= json_encode($practicalUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const participantInfo = document.getElementById('participantInfo');
    const practicalForm = document.getElementById('practicalForm');
    const practicalProgress = document.getElementById('practicalProgress');
    const tasksContainer = document.getElementById('tasksContainer');
    const complexityImage = document.getElementById('complexityImage');
    const complexityContainer = document.getElementById('complexityContainer');
    const complexityStep = document.getElementById('complexityStep');
    const message = document.getElementById('message');

    const stepText = document.getElementById('stepText');
    const progressPercent = document.getElementById('progressPercent');
    const progressBar = document.getElementById('progressBar');

    const prevStepBtn = document.getElementById('prevStepBtn');
    const nextStepBtn = document.getElementById('nextStepBtn');
    const saveDraftBtn = document.getElementById('saveDraftBtn');
    const submitBtn = document.getElementById('submitBtn');

    let loadedPractical = null;
    let bpmnModelers = {};
    let currentStep = 0;

    const totalSteps = 3;
    const draftKey = 'practicalDraft_' + sessionId;

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
        if (type === 'error') message.scrollIntoView({ block: 'start', behavior: 'auto' });
    }

    function showSubmittedState(submittedAt = '') {
        localStorage.removeItem(draftKey);
        clearTimeout(autoSaveTimer);
        practicalProgress.style.display = 'none';
        practicalForm.style.display = 'none';

        const dateLine = submittedAt
            ? `<br>${escapeHtml(practicalUi.submittedAt)}: ${escapeHtml(submittedAt)}`
            : '';

        showMessage(`
            <strong>${escapeHtml(practicalUi.submittedTitle)}</strong><br>
            ${escapeHtml(practicalUi.submittedText)}${dateLine}<br><br>
            <a href="result.php?sessionId=${encodeURIComponent(sessionId)}">
                ${escapeHtml(practicalUi.goToResult)}
            </a>
        `, 'success');
    }

    const initialDiagram = `<bpmn:definitions xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  id="Definitions_1" targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:collaboration id="Collaboration_1">
    <bpmn:participant id="Participant_1" processRef="Process_1" />
  </bpmn:collaboration>
  <bpmn:process id="Process_1" isExecutable="false" />
  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Collaboration_1">
      <bpmndi:BPMNShape id="Participant_1_di" bpmnElement="Participant_1" isHorizontal="true">
        <dc:Bounds x="100" y="100" width="900" height="360" />
      </bpmndi:BPMNShape>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

    async function loadPractical() {
        if (!sessionId) {
            showMessage('sessionId не передан в ссылке', 'error');
            return;
        }

        try {
            const response = await fetch('../api/practical.php?sessionId=' + encodeURIComponent(sessionId) + '&lang=' + encodeURIComponent(pageLocale));
            const json = await response.json();

            if (!json.success) {
                showMessage(json.message || 'Ошибка загрузки практического задания', 'error');
                return;
            }

            loadedPractical = json;

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
                        <div class="info-strip-label">${escapeHtml(practicalUi.testResult)}</div>
                        <div class="info-strip-value">${json.participant.score === null || json.participant.score === undefined
                            ? '—'
                            : `${escapeHtml(json.participant.score)} ${escapeHtml(practicalUi.scoreSeparator)} ${escapeHtml(json.participant.total)}, ${escapeHtml(json.participant.percent)}%`}</div>
                    </div>
                </div>
            `;

            if (json.practical.submittedAt) {
                showSubmittedState(json.practical.submittedAt);
                return;
            }

            await renderTasks(json.practical.tasks);
            renderComplexity(json.practical.complexityVariant);
            await restoreDraft();
            updateStepView();

        } catch (error) {
            console.error(error);
            showMessage('Ошибка соединения с сервером', 'error');
        }
    }

    async function renderTasks(tasks) {
        tasksContainer.innerHTML = '';
        bpmnModelers = {};
        const initializations = [];

        tasks.forEach((task, index) => {
            const block = document.createElement('div');
            block.className = 'task practical-card';
            block.id = `step_task_${index}`;

            const modelerId = `bpmnModeler_${task.id}`;

            block.innerHTML = `
                <div class="practical-card-header">
                    <p class="practical-task-title">Практическая задача ${index + 1}</p>
                    <h2>Постройте BPMN-диаграмму бизнес-процесса</h2>
                    <p class="practical-description">${escapeHtml(task.description)}</p>
                </div>

                <div class="bpmn-modeler-title">BPMN-моделлер:</div>
                <div id="${modelerId}" class="bpmn-container"></div>
            `;

            tasksContainer.appendChild(block);
            initializations.push(initBpmnModeler(task.id, modelerId));
        });
        await Promise.all(initializations);
    }

    async function initBpmnModeler(taskId, modelerId) {
        const modeler = new BpmnJS({
            container: '#' + modelerId
        });

        bpmnModelers[taskId] = modeler;

        try {
            await modeler.importXML(initialDiagram);

            fitModeler(modeler);
            window.PortalMobile?.attachModeler(document.getElementById(modelerId), modeler);

            const eventBus = modeler.get('eventBus');

            eventBus.on('commandStack.changed', () => {
                autoSaveDraft();
            });

        } catch (error) {
            console.error(error);
            showMessage('Ошибка загрузки BPMN-моделлера для задания ' + taskId, 'error');
        }
    }

    /*
     * Схема для расчёта сложности: настоящий BPMN, но только для чтения.
     * Модель рендерится во временном контейнере и вставляется как векторный SVG:
     * элементы нельзя двигать или менять, текст остаётся чётким при увеличении.
     */
    async function renderStaticBpmn(file) {
        const box = document.createElement('div');
        box.className = 'complexity-image-box';
        box.innerHTML = '<p><b>Диаграмма для расчета сложности:</b></p><div class="complexity-bpmn-static">Загрузка схемы…</div>';
        complexityImage.appendChild(box);
        const target = box.querySelector('.complexity-bpmn-static');
        const holder = document.createElement('div');
        holder.style.cssText = 'position:absolute;left:-10000px;top:0;width:1200px;height:800px;';
        document.body.appendChild(holder);
        try {
            const response = await fetch('../assets/complexity/' + encodeURIComponent(file));
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const renderer = new BpmnJS({ container: holder });
            await renderer.importXML(await response.text());
            const { svg } = await renderer.saveSVG();
            renderer.destroy();
            target.innerHTML = svg;
            const svgElement = target.querySelector('svg');
            if (svgElement) {
                svgElement.removeAttribute('width');
                svgElement.removeAttribute('height');
                svgElement.setAttribute('role', 'img');
                svgElement.setAttribute('aria-label', 'Диаграмма для расчета сложности');
            }
        } catch (error) {
            console.error(error);
            target.textContent = 'Не удалось загрузить схему. Обновите страницу.';
        } finally {
            holder.remove();
        }
    }

    function renderComplexity(complexityVariant) {
        complexityImage.innerHTML = '';
        complexityContainer.innerHTML = '';

        if (complexityVariant.image) {
            complexityImage.innerHTML = `
                <div class="complexity-image-box">
                    <p><b>Диаграмма для расчета сложности:</b></p>
                    <div class="diagram-zoom-hint"><?= $isKazakh ? 'Үлкейту үшін сызбаны басыңыз.' : 'Нажмите на схему, чтобы увеличить.' ?></div>
                    <img
                        src="../assets/complexity/${escapeHtml(complexityVariant.image)}"
                        alt="Диаграмма расчета сложности"
                    >
                </div>
            `;
        }

        if (complexityVariant.bpmn) {
            renderStaticBpmn(complexityVariant.bpmn);
        }

        complexityVariant.fields.forEach(field => {
            const row = document.createElement('div');
            row.className = 'field-row';

            row.innerHTML = `
                <label for="complexity_${escapeHtml(field.id)}">${escapeHtml(field.label)} (${escapeHtml(field.weight)})</label>
                <input id="complexity_${escapeHtml(field.id)}" name="complexity_${escapeHtml(field.id)}" inputmode="${field.id === 'complexity' ? 'decimal' : 'numeric'}" required placeholder="Введите ответ">
            `;

            complexityContainer.appendChild(row);
        });

        practicalForm.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', autoSaveDraft);
        });
    }

    function updateStepView() {
        if (!loadedPractical) {
            return;
        }

        loadedPractical.practical.tasks.forEach((task, index) => {
            const block = document.getElementById(`step_task_${index}`);

            if (block) {
                block.classList.toggle('step-hidden', currentStep !== index);
            }
        });

        complexityStep.classList.toggle('step-hidden', currentStep !== 2);

        const percent = Math.round(((currentStep + 1) / totalSteps) * 100);

        stepText.innerText = practicalUi.stepLabel.replace('{current}', currentStep + 1).replace('{total}', totalSteps);
        progressPercent.innerText = percent + '%';
        progressBar.style.width = percent + '%';

        prevStepBtn.disabled = currentStep === 0;
        nextStepBtn.style.display = currentStep < totalSteps - 1 ? 'inline-block' : 'none';
        submitBtn.style.display = currentStep === totalSteps - 1 ? 'inline-block' : 'none';

        setTimeout(() => {
            resizeCurrentModeler();
        }, 100);
    }

    function fitModeler(modeler) {
        const canvas = modeler.get('canvas');
        const size = canvas.getSize();
        if (size.width > 0 && size.height > 0) {
            canvas.resized();
            canvas.zoom('fit-viewport', 'auto');
        }
    }

    function resizeCurrentModeler() {
        if (!loadedPractical || currentStep >= 2) {
            return;
        }

        const task = loadedPractical.practical.tasks[currentStep];
        const modeler = bpmnModelers[task.id];

        if (modeler) {
            modeler.get('canvas').resized();
        }
    }

    prevStepBtn.addEventListener('click', () => {
        if (currentStep > 0) {
            currentStep--;
            updateStepView();
            autoSaveDraft();
            practicalForm.scrollIntoView({ block: 'start', behavior: 'auto' });
        }
    });

    nextStepBtn.addEventListener('click', () => {
        if (currentStep < totalSteps - 1) {
            currentStep++;
            updateStepView();
            autoSaveDraft();
            practicalForm.scrollIntoView({ block: 'start', behavior: 'auto' });
        }
    });

    const draftButtonLabel = saveDraftBtn.textContent;
    let draftLabelTimer;
    saveDraftBtn.addEventListener('click', async () => {
        try {
            await saveDraft();
            showMessage('Черновик сохранен в браузере.', 'success');
            clearTimeout(draftLabelTimer);
            saveDraftBtn.textContent = practicalUi.draftSaved;
            draftLabelTimer = setTimeout(() => { saveDraftBtn.textContent = draftButtonLabel; }, 2500);
        } catch (error) {
            showMessage(practicalUi.draftFailed, 'error');
        }
    });

    async function collectAnswers() {
        const answers = {
            diagrams: {},
            complexity: {}
        };

        for (const task of loadedPractical.practical.tasks) {
            const modeler = bpmnModelers[task.id];

            if (!modeler) {
                throw new Error('BPMN-моделлер не найден для задания ' + task.id);
            }

            const result = await modeler.saveXML({
                format: true
            });

            answers.diagrams[task.id] = {
                taskId: task.id,
                xml: result.xml
            };
        }

        loadedPractical.practical.complexityVariant.fields.forEach(field => {
            const input = practicalForm.querySelector(`[name="complexity_${field.id}"]`);
            answers.complexity[field.id] = input ? input.value.trim() : '';
        });

        return answers;
    }

    async function saveDraft() {
        if (!loadedPractical) {
            return;
        }

        const answers = await collectAnswers();

        localStorage.setItem(draftKey, JSON.stringify({
            answers: answers,
            currentStep: currentStep,
            savedAt: new Date().toISOString()
        }));

        await fetch('../api/practical.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                sessionId: sessionId,
                answers: answers,
                complete: false
            })
        });
    }

    let autoSaveTimer = null;

    function autoSaveDraft() {
        clearTimeout(autoSaveTimer);

        autoSaveTimer = setTimeout(async () => {
            try {
                if (loadedPractical) {
                    await saveDraft();
                }
            } catch (e) {
                console.warn('Не удалось автосохранить черновик', e);
            }
        }, 700);
    }

    async function restoreDraft() {
        const saved = localStorage.getItem(draftKey);

        if (!saved || !loadedPractical) {
            return;
        }

        try {
            const draft = JSON.parse(saved);

            if (draft.currentStep !== undefined) {
                currentStep = Number(draft.currentStep) || 0;
            }

            const answers = draft.answers || {};

            if (answers.complexity) {
                Object.keys(answers.complexity).forEach(fieldId => {
                    const input = practicalForm.querySelector(`[name="complexity_${fieldId}"]`);

                    if (input) {
                        input.value = answers.complexity[fieldId];
                    }
                });
            }

            if (answers.diagrams) {
                for (const task of loadedPractical.practical.tasks) {
                    const diagram = answers.diagrams[task.id];

                    if (diagram && diagram.xml && bpmnModelers[task.id]) {
                        await bpmnModelers[task.id].importXML(diagram.xml);

                        fitModeler(bpmnModelers[task.id]);
                    }
                }
            }

        } catch (error) {
            console.warn('Не удалось восстановить черновик', error);
        }
    }

    practicalForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!loadedPractical) {
            showMessage('Практическое задание еще не загружено', 'error');
            return;
        }

        try {
            const answers = await collectAnswers();

            for (const task of loadedPractical.practical.tasks) {
                const diagram = answers.diagrams[task.id];
                const taskIndex = loadedPractical.practical.tasks.findIndex(item => item.id === task.id) + 1;

                if (!diagram || !diagram.xml || diagram.xml.trim() === '') {
                    showMessage(
                        `Не удалось сохранить BPMN-схему для практической задачи ${taskIndex}. Попробуйте обновить страницу и повторить еще раз.`,
                        'error'
                    );

                    currentStep = taskIndex - 1;
                    updateStepView();

                    return;
                }

                if (!isDiagramChanged(diagram.xml)) {
                    showMessage(
                        `Внесите изменения в BPMN-схему для практической задачи ${taskIndex}. Нельзя отправить полностью стандартную схему.`,
                        'error'
                    );

                    currentStep = taskIndex - 1;
                    updateStepView();

                    return;
                }

                if (!hasBpmnProcessContent(diagram.xml)) {
                    showMessage(
                        `BPMN-схема для практической задачи ${taskIndex} заполнена недостаточно. В схеме должны быть минимум стартовое событие, задача и конечное событие.`,
                        'error'
                    );

                    currentStep = taskIndex - 1;
                    updateStepView();

                    return;
                }
            }

            for (const field of loadedPractical.practical.complexityVariant.fields) {
                const value = answers.complexity[field.id];

                if (!value || value.trim() === '') {
                    showMessage(
                        `Заполните поле расчета сложности: ${escapeHtml(field.label)}`,
                        'error'
                    );

                    currentStep = 2;
                    updateStepView();

                    return;
                }
            }

            const response = await fetch('../api/practical.php?lang=' + encodeURIComponent(pageLocale), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    sessionId: sessionId,
                    answers: answers,
                    complete: true
                })
            });

            const json = await response.json();

            if (!json.success) {
                if (json.alreadySubmitted) {
                    showSubmittedState(json.practical?.submittedAt || '');
                    return;
                }

                showMessage(json.message || 'Ошибка сохранения практического задания', 'error');
                return;
            }

            showSubmittedState(json.practical?.submittedAt || '');

        } catch (error) {
            console.error(error);
            showMessage('Ошибка сохранения BPMN XML', 'error');
        }
    });

    function hasBpmnProcessContent(xml) {
        const tasks =
            (xml.match(/bpmn:task/g) || []).length +
            (xml.match(/bpmn:userTask/g) || []).length +
            (xml.match(/bpmn:serviceTask/g) || []).length +
            (xml.match(/bpmn:manualTask/g) || []).length +
            (xml.match(/bpmn:sendTask/g) || []).length +
            (xml.match(/bpmn:receiveTask/g) || []).length +
            (xml.match(/bpmn:scriptTask/g) || []).length;

        const startEvents = (xml.match(/bpmn:startEvent/g) || []).length;
        const endEvents = (xml.match(/bpmn:endEvent/g) || []).length;

        return tasks >= 1 && startEvents >= 1 && endEvents >= 1;
    }

    function normalizeBpmnXml(xml) {
        return String(xml || '')
            .replace(/\s+/g, '')
            .replace(/id="[^"]+"/g, 'id=""');
    }

    function isDiagramChanged(xml) {
        const current = normalizeBpmnXml(xml);
        const initial = normalizeBpmnXml(initialDiagram);

        return current !== initial;
    }

    loadPractical();
</script>
</body>
</html>
