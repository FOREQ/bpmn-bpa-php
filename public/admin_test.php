<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/csrf.php';

$activeNav = 'admin';
$labels = require __DIR__ . '/../lang/admin_test_preview.php';
$ui = $labels[i18nLocale()];
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $h(i18nLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $h($ui['pageTitle']) ?> | BPMN</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="assessment-page">
<?php require __DIR__ . '/_header.php'; ?>

<main class="container container-860 admin-test-preview">
    <a class="content-back" href="admin.php">← <?= $h($ui['back']) ?></a>
    <h1><?= $h($ui['heading']) ?></h1>
    <p class="hint"><?= $h($ui['intro']) ?></p>

    <form id="previewStartForm" class="admin-test-start">
        <label for="previewVariant"><?= $h($ui['variant']) ?></label>
        <select id="previewVariant" name="variant">
            <option value="A">A</option>
            <option value="B">B</option>
            <option value="C">C</option>
        </select>
        <button id="startButton" class="btn btn-primary" type="submit"><?= $h($ui['start']) ?></button>
    </form>

    <section id="previewQuiz" hidden aria-label="<?= $h($ui['pageTitle']) ?>">
        <div class="info-strip">
            <div class="info-strip-cell">
                <div class="info-strip-label"><?= $h($ui['variant']) ?></div>
                <div class="info-strip-value" id="currentVariant"></div>
            </div>
            <div class="info-strip-cell">
                <div class="info-strip-label"><?= $h($ui['answered']) ?></div>
                <div class="info-strip-value" id="answeredCount"></div>
            </div>
        </div>
        <form id="previewTestForm"></form>
    </section>

    <section id="previewResult" class="admin-test-result" hidden aria-live="polite"></section>
    <section id="previewPractical" hidden aria-label="<?= $h($ui['practicalHeading']) ?>">
        <h2><?= $h($ui['practicalHeading']) ?></h2>
        <div class="progress-box"><div class="progress-top"><span id="practicalProgressText"></span><span id="practicalProgressPercent"></span></div><div class="progress"><div id="practicalProgressBar"></div></div></div>
        <div id="practicalPanels"></div>
        <div class="buttons-row admin-test-practical-actions">
            <button id="practicePrev" class="btn btn-secondary" type="button"><?= $h($ui['previous']) ?></button>
            <button id="practiceNext" class="btn btn-primary" type="button"><?= $h($ui['next']) ?></button>
            <button id="practiceFinish" class="btn btn-primary" type="button" hidden><?= $h($ui['finishPractical']) ?></button>
        </div>
    </section>
    <p id="previewMessage" class="content-editor-message" role="status" aria-live="polite"></p>
</main>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-modeler.development.js"></script>
<script>
    const ui = <?= json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const pageLocale = <?= json_encode(i18nLocale()) ?>;
    const csrfToken = <?= json_encode(csrfToken()) ?>;
    const endpoint = '../api/admin/test_preview.php';
    const startForm = document.getElementById('previewStartForm');
    const testForm = document.getElementById('previewTestForm');
    const quiz = document.getElementById('previewQuiz');
    const resultBox = document.getElementById('previewResult');
    const practicalBox = document.getElementById('previewPractical');
    const message = document.getElementById('previewMessage');
    let preview = null;
    let currentIndex = 0;
    let answers = {};
    let practicalIndex = 0;
    let practicalModelers = [];
    let practicalBaselineXml = [];

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

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function showMessage(value, error = false) {
        message.textContent = value;
        message.className = 'content-editor-message' + (error ? ' error' : '');
    }

    function resetPractical() {
        practicalModelers.forEach(modeler => modeler.destroy());
        practicalModelers = [];
        practicalBaselineXml = [];
        practicalIndex = 0;
        practicalBox.hidden = true;
        document.getElementById('practicalPanels').innerHTML = '';
    }

    function updatePracticalStep() {
        document.querySelectorAll('#practicalPanels > .practical-card').forEach((panel, index) => {
            panel.hidden = index !== practicalIndex;
        });
        document.getElementById('practicalProgressText').textContent = `${ui.step} ${practicalIndex + 1} ${ui.of} 3`;
        document.getElementById('practicalProgressPercent').textContent = `${Math.round((practicalIndex + 1) / 3 * 100)}%`;
        document.getElementById('practicalProgressBar').style.width = `${Math.round((practicalIndex + 1) / 3 * 100)}%`;
        document.getElementById('practicePrev').textContent = practicalIndex === 0 ? ui.backToResult : ui.previous;
        document.getElementById('practiceNext').hidden = practicalIndex === 2;
        document.getElementById('practiceFinish').hidden = practicalIndex !== 2;
        if (practicalIndex < 2 && practicalModelers[practicalIndex]) {
            const visibleModeler = practicalModelers[practicalIndex];
            setTimeout(() => {
                const canvas = visibleModeler.get('canvas');
                canvas.resized();
            }, 100);
        }
    }

    async function renderPractical() {
        if (typeof BpmnJS !== 'function') {
            showMessage(ui.modelerError, true);
            return;
        }
        resultBox.hidden = true;
        practicalBox.hidden = false;
        if (practicalModelers.length) {
            updatePracticalStep();
            showMessage('');
            return;
        }

        const tasks = preview.practical.tasks;
        const complexity = preview.practical.complexityVariant;
        const panels = tasks.map((task, index) => `
            <section class="practical-card" ${index ? 'hidden' : ''}>
                <div class="practical-card-header">
                    <p class="practical-task-title">${escapeHtml(ui.practicalTask)} ${index + 1}</p>
                    <h2>${escapeHtml(ui.buildDiagram)}</h2>
                    <p class="practical-description">${escapeHtml(task.description)}</p>
                </div>
                <div class="bpmn-modeler-title">${escapeHtml(ui.modeler)}</div>
                <div id="previewModeler${index}" class="bpmn-container"></div>
            </section>
        `).join('');
        const fields = complexity.fields.map(field => `
            <div class="field-row">
                <label for="previewComplexity_${escapeHtml(field.id)}">${escapeHtml(field.label)} (${escapeHtml(field.weight)})</label>
                <input id="previewComplexity_${escapeHtml(field.id)}" type="text" inputmode="${field.id === 'complexity' ? 'decimal' : 'numeric'}" placeholder="${escapeHtml(ui.answerPlaceholder)}" required>
            </div>
        `).join('');
        document.getElementById('practicalPanels').innerHTML = panels + `
            <section class="practical-card complexity-step" hidden>
                <h2>${escapeHtml(ui.complexityTitle)}</h2>
                <p>${escapeHtml(ui.complexityHint)}</p>
                <div class="complexity-image-box"><img src="../assets/complexity/${encodeURIComponent(complexity.image)}" alt="${escapeHtml(ui.complexityImage)}"></div>
                ${fields}
            </section>
        `;
        try {
            for (let index = 0; index < tasks.length; index++) {
                const modeler = new BpmnJS({ container: `#previewModeler${index}` });
                practicalModelers.push(modeler);
                await modeler.importXML(initialDiagram);
                window.PortalMobile?.attachModeler(document.getElementById(`previewModeler${index}`), modeler);
                const { xml } = await modeler.saveXML({ format: true });
                practicalBaselineXml.push(xml);
            }
            practicalIndex = 0;
            updatePracticalStep();
            showMessage('');
        } catch (error) {
            resetPractical();
            resultBox.hidden = false;
            showMessage(ui.modelerError, true);
        }
    }

    function normalizeBpmnXml(xml) {
        return String(xml || '').replace(/\s+/g, '').replace(/id="[^"]+"/g, 'id=""');
    }

    function hasBpmnProcessContent(xml) {
        const taskCount = (xml.match(/bpmn:(?:task|userTask|serviceTask|manualTask|sendTask|receiveTask|scriptTask)/g) || []).length;
        return taskCount >= 1 && xml.includes('bpmn:startEvent') && xml.includes('bpmn:endEvent');
    }

    async function finishPractical() {
        const button = document.getElementById('practiceFinish');
        button.disabled = true;
        try {
            for (let index = 0; index < practicalModelers.length; index++) {
                const { xml } = await practicalModelers[index].saveXML({ format: true });
                if (normalizeBpmnXml(xml) === normalizeBpmnXml(practicalBaselineXml[index])) {
                    practicalIndex = index;
                    updatePracticalStep();
                    showMessage(`${ui.diagramUnchanged} ${index + 1}.`, true);
                    return;
                }
                if (!hasBpmnProcessContent(xml)) {
                    practicalIndex = index;
                    updatePracticalStep();
                    showMessage(`${ui.diagramIncomplete} ${index + 1}.`, true);
                    return;
                }
            }
            for (const field of preview.practical.complexityVariant.fields) {
                const input = document.getElementById(`previewComplexity_${field.id}`);
                if (!input.value.trim()) {
                    practicalIndex = 2;
                    updatePracticalStep();
                    showMessage(`${ui.fieldMissing}: ${field.label}.`, true);
                    input.focus();
                    return;
                }
            }
            practicalBox.hidden = true;
            resultBox.hidden = false;
            resultBox.querySelector('#goPractical').hidden = true;
            resultBox.insertAdjacentHTML('beforeend', `<p class="admin-test-practical-done">${escapeHtml(ui.practicalDone)}</p>`);
            showMessage('');
        } catch (error) {
            showMessage(ui.modelerError, true);
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('practicePrev').addEventListener('click', () => {
        if (practicalIndex === 0) {
            practicalBox.hidden = true;
            resultBox.hidden = false;
        } else {
            practicalIndex--;
            updatePracticalStep();
        }
        showMessage('');
    });
    document.getElementById('practiceNext').addEventListener('click', () => {
        if (practicalIndex < 2) {
            practicalIndex++;
            updatePracticalStep();
            showMessage('');
        }
    });
    document.getElementById('practiceFinish').addEventListener('click', finishPractical);

    function updateProgress() {
        const total = preview.questions.length;
        const answered = preview.questions.filter(question => Object.hasOwn(answers, String(question.id))).length;
        document.getElementById('answeredCount').textContent = `${answered}/${total}`;
    }

    function renderQuestion() {
        const questions = preview.questions;
        const question = questions[currentIndex];
        const selected = answers[String(question.id)];
        const options = question.options.map((option, index) => `
            <label>
                <input type="radio" name="preview-answer" aria-label="${escapeHtml(option.text || ui.optionImage + ' ' + (index + 1))}" value="${escapeHtml(option.id)}" ${String(option.id) === String(selected) ? 'checked' : ''}>
                <span class="option-content"><span>${escapeHtml(option.text || '')}</span>
                ${option.image ? `<img src="${escapeHtml(option.image)}" class="option-image" alt="${escapeHtml(ui.optionImage)} ${index + 1}">` : ''}
                </span>
            </label>
        `).join('');
        testForm.innerHTML = `
            <div class="question">
                <div class="question-eyebrow">${escapeHtml(ui.question)} ${currentIndex + 1} ${escapeHtml(ui.of)} ${questions.length}</div>
                <p tabindex="-1" class="question-title">${escapeHtml(question.text)}</p>
                ${question.image ? `<img class="question-image" src="${escapeHtml(question.image)}" alt="${escapeHtml(ui.questionImage)}">` : ''}
                <div class="question-options">${options}</div>
            </div>
            <div class="buttons-row">
                <button class="btn btn-secondary" id="prevBtn" type="button" ${currentIndex === 0 ? 'disabled' : ''}>${escapeHtml(ui.previous)}</button>
                <button class="btn btn-primary" id="nextBtn" type="button" ${currentIndex === questions.length - 1 ? 'hidden' : ''}>${escapeHtml(ui.next)}</button>
                <button class="btn btn-primary" id="submitBtn" type="submit" ${currentIndex === questions.length - 1 ? '' : 'hidden'}>${escapeHtml(ui.submit)}</button>
            </div>
        `;
        testForm.querySelectorAll('input[name="preview-answer"]').forEach(input => input.addEventListener('change', () => {
            answers[String(question.id)] = input.value;
            showMessage('');
            updateProgress();
        }));
        testForm.querySelector('#prevBtn').addEventListener('click', () => {
            if (currentIndex > 0) { currentIndex--; renderQuestion(); }
        });
        testForm.querySelector('#nextBtn').addEventListener('click', () => {
            if (currentIndex < questions.length - 1) { currentIndex++; renderQuestion(); }
        });
        updateProgress();
        testForm.querySelector('.question-title').focus();
    }

    async function startPreview() {
        const button = document.getElementById('startButton');
        button.disabled = true;
        showMessage(ui.loading);
        try {
            const variant = document.getElementById('previewVariant').value;
            const response = await fetch(`${endpoint}?variant=${encodeURIComponent(variant)}&lang=${encodeURIComponent(pageLocale)}`, { cache: 'no-store' });
            const data = await response.json();
            if (!response.ok || !data.success || !Array.isArray(data.questions) || data.questions.length !== 20
                || !Array.isArray(data.practical?.tasks) || data.practical.tasks.length !== 2
                || !Array.isArray(data.practical?.complexityVariant?.fields)) {
                throw new Error(data.message || ui.loadError);
            }
            resetPractical();
            preview = data;
            answers = {};
            currentIndex = 0;
            document.getElementById('currentVariant').textContent = data.variantId;
            startForm.hidden = true;
            resultBox.hidden = true;
            quiz.hidden = false;
            showMessage('');
            renderQuestion();
        } catch (error) {
            showMessage(error.message || ui.loadError, true);
        } finally {
            button.disabled = false;
        }
    }

    startForm.addEventListener('submit', event => {
        event.preventDefault();
        startPreview();
    });

    testForm.addEventListener('submit', async event => {
        event.preventDefault();
        const firstMissing = preview.questions.findIndex(question => !Object.hasOwn(answers, String(question.id)));
        if (firstMissing !== -1) {
            currentIndex = firstMissing;
            renderQuestion();
            showMessage(ui.missing, true);
            return;
        }

        const button = document.getElementById('submitBtn');
        button.disabled = true;
        showMessage(ui.checking);
        try {
            const response = await fetch(`${endpoint}?lang=${encodeURIComponent(pageLocale)}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrfToken, token: preview.token, answers }),
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || ui.checkError);
            const grade = data.result;
            const mistakes = data.review.map(item => `<li><strong>${escapeHtml(ui.question)} ${item.number}:</strong> ${escapeHtml(item.question)}<br>${escapeHtml(ui.correct)}: <strong>${escapeHtml(item.correct)}</strong></li>`).join('');
            resultBox.innerHTML = `
                <h2>${escapeHtml(ui.result)}</h2>
                <div class="info-strip">
                    <div class="info-strip-cell"><div class="info-strip-label">${escapeHtml(ui.score)}</div><div class="info-strip-value">${grade.score} ${escapeHtml(ui.of)} ${grade.total}</div></div>
                    <div class="info-strip-cell"><div class="info-strip-label">${escapeHtml(ui.percent)}</div><div class="info-strip-value">${grade.percent}%</div></div>
                    <div class="info-strip-cell"><div class="info-strip-label">${escapeHtml(ui.status)}</div><div class="info-strip-value">${escapeHtml(grade.status === 'passed' ? ui.passed : ui.failed)}</div></div>
                </div>
                <button type="button" class="btn btn-primary" id="goPractical">${escapeHtml(ui.goToPractical)}</button>
                <button type="button" class="btn btn-secondary" id="restartPreview">${escapeHtml(ui.restart)}</button>
                ${mistakes ? `<details class="admin-test-review"><summary>${escapeHtml(ui.mistakes)} (${data.review.length})</summary><ol class="admin-test-mistakes">${mistakes}</ol></details>` : `<p>${escapeHtml(ui.noMistakes)}</p>`}
            `;
            resultBox.querySelector('#goPractical').addEventListener('click', renderPractical);
            resultBox.querySelector('#restartPreview').addEventListener('click', () => {
                resetPractical();
                resultBox.hidden = true;
                startForm.hidden = false;
                showMessage('');
            });
            quiz.hidden = true;
            resultBox.hidden = false;
            showMessage('');
        } catch (error) {
            showMessage(error.message || ui.checkError, true);
            button.disabled = false;
        }
    });
</script>
</body>
</html>
