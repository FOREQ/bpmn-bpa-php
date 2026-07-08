<?php

require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/security.php';

$courseTitle = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Практическое задание | Реинжиниринг бизнес-процессов</title>

    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">
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
            <a href="student_login.php" class="active">Войти</a>
            <a href="admin_login.php">Админ</a>
        </nav>
    </div>
</header>

<div class="container">
    <div class="top-nav">
        <a href="student_dashboard.php">← В личный кабинет</a>
    </div>

    <h1>Практическое задание</h1>

    <p class="hint">
        Курс: «<?= htmlspecialchars($courseTitle) ?>»
    </p>

    <div id="participantInfo"></div>

    <div class="progress-box">
        <div class="progress-top">
            <span id="stepText">Шаг 1 из 3</span>
            <span id="progressPercent">33%</span>
        </div>
        <div class="progress">
            <div id="progressBar"></div>
        </div>
    </div>

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
            <button type="button" id="prevStepBtn" class="secondary-btn back-btn">Назад</button>
            <button type="button" id="saveDraftBtn" class="secondary-btn draft-btn">Сохранить черновик</button>
            <button type="button" id="nextStepBtn" class="next-btn">Далее</button>
            <button type="submit" id="submitBtn" class="finish-btn">Завершить практическое задание</button>
        </div>
    </form>

    <div id="message" class="message"></div>
</div>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-modeler.development.js"></script>

<script>
    const params = new URLSearchParams(window.location.search);
    const sessionId = params.get('sessionId');
    const csrfToken = '<?= htmlspecialchars(csrfToken()) ?>';
    const courseTitle = <?= json_encode($courseTitle, JSON_UNESCAPED_UNICODE) ?>;

    const participantInfo = document.getElementById('participantInfo');
    const practicalForm = document.getElementById('practicalForm');
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
    }

    const initialDiagram = `<bpmn:definitions xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xmlns:bpmn="http://www.omg.org/spec/BPMN/20100524/MODEL"
                  xmlns:bpmndi="http://www.omg.org/spec/BPMN/20100524/DI"
                  xmlns:dc="http://www.omg.org/spec/DD/20100524/DC"
                  xmlns:di="http://www.omg.org/spec/DD/20100524/DI"
                  id="Definitions_1"
                  targetNamespace="http://bpmn.io/schema/bpmn">
  <bpmn:process id="Process_1" isExecutable="false">
    <bpmn:startEvent id="StartEvent_1" name="Старт">
      <bpmn:outgoing>Flow_1</bpmn:outgoing>
    </bpmn:startEvent>
    <bpmn:task id="Task_1" name="Задача">
      <bpmn:incoming>Flow_1</bpmn:incoming>
      <bpmn:outgoing>Flow_2</bpmn:outgoing>
    </bpmn:task>
    <bpmn:endEvent id="EndEvent_1" name="Конец">
      <bpmn:incoming>Flow_2</bpmn:incoming>
    </bpmn:endEvent>
    <bpmn:sequenceFlow id="Flow_1" sourceRef="StartEvent_1" targetRef="Task_1" />
    <bpmn:sequenceFlow id="Flow_2" sourceRef="Task_1" targetRef="EndEvent_1" />
  </bpmn:process>

  <bpmndi:BPMNDiagram id="BPMNDiagram_1">
    <bpmndi:BPMNPlane id="BPMNPlane_1" bpmnElement="Process_1">
      <bpmndi:BPMNShape id="StartEvent_1_di" bpmnElement="StartEvent_1">
        <dc:Bounds x="160" y="180" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="Task_1_di" bpmnElement="Task_1">
        <dc:Bounds x="260" y="160" width="120" height="80" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNShape id="EndEvent_1_di" bpmnElement="EndEvent_1">
        <dc:Bounds x="460" y="180" width="36" height="36" />
      </bpmndi:BPMNShape>
      <bpmndi:BPMNEdge id="Flow_1_di" bpmnElement="Flow_1">
        <di:waypoint x="196" y="198" />
        <di:waypoint x="260" y="200" />
      </bpmndi:BPMNEdge>
      <bpmndi:BPMNEdge id="Flow_2_di" bpmnElement="Flow_2">
        <di:waypoint x="380" y="200" />
        <di:waypoint x="460" y="198" />
      </bpmndi:BPMNEdge>
    </bpmndi:BPMNPlane>
  </bpmndi:BPMNDiagram>
</bpmn:definitions>`;

    async function loadPractical() {
        if (!sessionId) {
            showMessage('sessionId не передан в ссылке', 'error');
            return;
        }

        try {
            const response = await fetch('../api/practical.php?sessionId=' + encodeURIComponent(sessionId));
            const json = await response.json();

            if (!json.success) {
                showMessage(json.message || 'Ошибка загрузки практического задания', 'error');
                return;
            }

            loadedPractical = json;

            participantInfo.innerHTML = `
                <div class="card">
                    <p><b>Курс:</b><br>${escapeHtml(courseTitle)}</p>
                    <p><b>Участник:</b> ${escapeHtml(json.participant.fullName)}</p>
                    <p><b>Организация:</b> ${escapeHtml(json.participant.organization)}</p>
                    <p><b>Результат теоретического теста:</b> ${escapeHtml(json.participant.score)} из ${escapeHtml(json.participant.total)}, ${escapeHtml(json.participant.percent)}%</p>
                </div>
            `;

            renderTasks(json.practical.tasks);
            renderComplexity(json.practical.complexityVariant);

            setTimeout(() => {
                restoreDraft();
                updateStepView();
            }, 500);

        } catch (error) {
            console.error(error);
            showMessage('Ошибка соединения с сервером', 'error');
        }
    }

    function renderTasks(tasks) {
        tasksContainer.innerHTML = '';
        bpmnModelers = {};

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
            initBpmnModeler(task.id, modelerId);
        });
    }

    async function initBpmnModeler(taskId, modelerId) {
        const modeler = new BpmnJS({
            container: '#' + modelerId
        });

        bpmnModelers[taskId] = modeler;

        try {
            await modeler.importXML(initialDiagram);

            const canvas = modeler.get('canvas');
            canvas.zoom('fit-viewport');

            const eventBus = modeler.get('eventBus');

            eventBus.on('commandStack.changed', () => {
                autoSaveDraft();
            });

        } catch (error) {
            console.error(error);
            showMessage('Ошибка загрузки BPMN-моделлера для задания ' + taskId, 'error');
        }
    }

    function renderComplexity(complexityVariant) {
        complexityImage.innerHTML = '';
        complexityContainer.innerHTML = '';

        if (complexityVariant.image) {
            complexityImage.innerHTML = `
                <div class="complexity-image-box">
                    <p><b>Диаграмма для расчета сложности:</b></p>
                    <img
                        src="../assets/complexity/${escapeHtml(complexityVariant.image)}"
                        alt="Диаграмма расчета сложности"
                    >
                </div>
            `;
        }

        complexityVariant.fields.forEach(field => {
            const row = document.createElement('div');
            row.className = 'field-row';

            row.innerHTML = `
                <label>${escapeHtml(field.label)} (${escapeHtml(field.weight)})</label>
                <input name="complexity_${escapeHtml(field.id)}" required placeholder="Введите ответ">
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

        stepText.innerText = `Шаг ${currentStep + 1} из ${totalSteps}`;
        progressPercent.innerText = percent + '%';
        progressBar.style.width = percent + '%';

        prevStepBtn.disabled = currentStep === 0;
        nextStepBtn.style.display = currentStep < totalSteps - 1 ? 'inline-block' : 'none';
        submitBtn.style.display = currentStep === totalSteps - 1 ? 'inline-block' : 'none';

        setTimeout(() => {
            resizeCurrentModeler();
        }, 100);
    }

    function resizeCurrentModeler() {
        if (!loadedPractical || currentStep >= 2) {
            return;
        }

        const task = loadedPractical.practical.tasks[currentStep];
        const modeler = bpmnModelers[task.id];

        if (modeler) {
            const canvas = modeler.get('canvas');
            canvas.resized();
            canvas.zoom('fit-viewport');
        }
    }

    prevStepBtn.addEventListener('click', () => {
        if (currentStep > 0) {
            currentStep--;
            updateStepView();
        }
    });

    nextStepBtn.addEventListener('click', () => {
        if (currentStep < totalSteps - 1) {
            currentStep++;
            updateStepView();
        }
    });

    saveDraftBtn.addEventListener('click', async () => {
        await saveDraft();
        showMessage('Черновик сохранен в браузере.', 'success');
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

                        const canvas = bpmnModelers[task.id].get('canvas');
                        canvas.zoom('fit-viewport');
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

            const response = await fetch('../api/practical.php', {
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
                showMessage(json.message || 'Ошибка сохранения практического задания', 'error');
                return;
            }

            localStorage.removeItem(draftKey);

            showMessage(`
                Практическое задание сохранено успешно.<br><br>
                <a href="result.php?sessionId=${encodeURIComponent(sessionId)}">
                    Перейти к результату
                </a>
            `, 'success');

            practicalForm.querySelectorAll('input, button').forEach(el => el.disabled = true);

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