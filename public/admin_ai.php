<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/ai_client.php';
require_once __DIR__ . '/../lib/bpmn_services.php';

$activeNav = 'admin';
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$generatorReady = aiEnabled('generator');
$schemesReady = $generatorReady && cctMcpEnabled() && calculatorEnabled();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ИИ: генерация заданий | BPMN</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .ai-grid { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
        .ai-card { background: #fff; border: 1px solid #e2e6ee; border-radius: 12px; padding: 16px; }
        .ai-card h2 { margin-top: 0; font-size: 1.15rem; }
        .ai-card label { display: block; margin: 10px 0 4px; font-weight: 600; }
        .ai-card input[type=text], .ai-card input[type=number], .ai-card select, .ai-card textarea,
        .ai-draft textarea, .ai-draft input, .ai-draft select { width: 100%; box-sizing: border-box; font: inherit; padding: 8px; border: 1px solid #ccd3df; border-radius: 8px; }
        .ai-card .btn, .ai-draft .btn { margin-top: 12px; }
        .ai-status { padding: 12px 16px; border-radius: 10px; margin: 16px 0; }
        .ai-status.ok { background: #e8f6ec; }
        .ai-status.off { background: #fff4e0; }
        .ai-drafts { margin-top: 28px; }
        .ai-draft { background: #fff; border: 1px solid #e2e6ee; border-radius: 12px; padding: 16px; margin-bottom: 14px; }
        .ai-draft-head { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; align-items: center; }
        .ai-badge { background: #eef1f7; border-radius: 999px; padding: 2px 10px; font-size: .85rem; }
        .ai-options { display: grid; gap: 6px; }
        .ai-option { display: flex; gap: 8px; align-items: center; }
        .ai-option input[type=radio] { width: auto; }
        .ai-two { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .ai-msg { margin-top: 8px; }
        .ai-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .ai-bpmn-small { height: 190px; border: 1px solid #e2e6ee; border-radius: 10px; background: #fff; margin: 6px 0; }
        .ai-fragments { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
        .ai-fragment { border: 1px solid #e2e6ee; border-radius: 10px; padding: 6px 8px; font-weight: 600; cursor: pointer; }
        .ai-fragment input[type=radio] { width: auto; margin-right: 6px; }
        .ai-fragment.correct { border-color: #2e7d32; box-shadow: 0 0 0 2px rgba(46,125,50,.15); }
        .ai-bpmn { height: 360px; border: 1px solid #e2e6ee; border-radius: 10px; background: #fff; margin: 8px 0; }
        .ai-answers { border-collapse: collapse; margin: 8px 0; }
        .ai-answers td { border-bottom: 1px solid #eef1f7; padding: 4px 10px 4px 0; }
        .ai-warn { color: #9a5b00; }
    </style>
</head>
<body>
<?php require __DIR__ . '/_header.php'; ?>

<main class="container container-wide">
    <a class="content-back" href="admin.php">← Назад в админку</a>
    <h1>ИИ: генерация вопросов и заданий</h1>
    <p>ИИ создаёт <b>черновики</b>. В экзамен попадает только то, что вы проверили и одобрили ниже.
        Одобренные материалы появляются в разделе <a href="content.php">«Вопросы и задания»</a>.</p>

    <div class="ai-status <?= $generatorReady ? 'ok' : 'off' ?>">
        <?php if ($generatorReady): ?>
            ИИ подключён. Модель для генерации: <b><?= $h(aiConfig('generator')['model']) ?></b>.
        <?php else: ?>
            ИИ пока не подключён. Задайте на сервере переменные <code>AI_BASE_URL</code>, <code>AI_MODEL</code> и
            <code>AI_API_KEY</code> (см. <code>.env.example</code>) и перезапустите приложение.
            Методичку можно загрузить уже сейчас.
        <?php endif; ?>
    </div>

    <div class="ai-grid">
        <section class="ai-card">
            <h2>1. Методичка</h2>
            <form id="sourceForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="source">
                <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
                <label for="sourceTitle">Название</label>
                <input id="sourceTitle" type="text" name="title" maxlength="200" placeholder="Методика реинжиниринга, 2026">
                <label for="sourceFile">Файл .pdf, .docx, .txt или .md</label>
                <input id="sourceFile" type="file" name="file" accept=".pdf,.docx,.txt,.md">
                <label for="sourceText">…или вставьте текст</label>
                <textarea id="sourceText" name="text" rows="4"></textarea>
                <button class="btn btn-primary" type="submit">Загрузить</button>
                <div class="ai-msg" id="sourceMsg"></div>
            </form>
            <label for="sourceList">Загруженные методички</label>
            <ul id="sourceList" class="small"><li>Загрузка…</li></ul>
        </section>

        <section class="ai-card">
            <h2>2. Теоретические вопросы</h2>
            <form id="questionsForm">
                <label for="qSource">Методичка</label>
                <select id="qSource" name="sourceId" required></select>
                <label for="qTemplate">Как составлять</label>
                <select id="qTemplate" name="template">
                    <option value="A">По образцу варианта A: 20 вопросов тех же тем и форматов, со схемами</option>
                    <option value="">Свободно по методичке (только текст)</option>
                </select>
                <p class="small" id="qNewVariantNote">Будет создан новый вариант теста (D, E, F…). Студентам он начнёт выдаваться, когда вы одобрите все 20 вопросов.</p>
                <div id="qVariantBox">
                <label for="qVariant">Вариант теста</label>
                <select id="qVariant" name="variant">
                    <option value="all">Все варианты</option>
                    <option value="A">Вариант A</option>
                    <option value="B">Вариант B</option>
                    <option value="C">Вариант C</option>
                </select>
                </div>
                <div id="qCountBox">
                <label for="qCount">Количество (1–20, полный набор — 20)</label>
                <input id="qCount" type="number" name="count" min="1" max="20" value="20">
                </div>
                <label for="qTopic">Тема или акцент <small>· необязательно</small></label>
                <input id="qTopic" type="text" name="topic" maxlength="300" placeholder="шлюзы, события, параметры оптимизации">
                <button class="btn btn-primary" type="submit" <?= $generatorReady ? '' : 'disabled' ?>>Сгенерировать</button>
                <div class="ai-msg" id="questionsMsg"></div>
            </form>
            <label>Варианты теста</label>
            <ul id="variantList" class="small"><li>Загрузка…</li></ul>
        </section>

        <section class="ai-card">
            <h2>3. Практические задания</h2>
            <form id="practicalForm">
                <label for="pPool">Какое задание</label>
                <select id="pPool" name="pool">
                    <option value="first">Первое (простое, бытовое)</option>
                    <option value="second" selected>Второе (процесс госоргана)</option>
                </select>
                <label for="pCount">Количество (1–5)</label>
                <input id="pCount" type="number" name="count" min="1" max="5" value="2">
                <label for="pTopic">Тематика <small>· необязательно</small></label>
                <input id="pTopic" type="text" name="topic" maxlength="300" placeholder="госзакупки, кадры, обращения граждан">
                <label for="pSource">Опираться на методичку <small>· необязательно</small></label>
                <select id="pSource" name="sourceId"></select>
                <button class="btn btn-primary" type="submit" <?= $generatorReady ? '' : 'disabled' ?>>Сгенерировать</button>
                <div class="ai-msg" id="practicalMsg"></div>
            </form>
            <p class="small">Если подключена сборка схем, к каждому заданию сразу строится эталон .bpmn.</p>
        </section>

        <section class="ai-card">
            <h2>4. Задание 3: расчёт сложности</h2>
            <p class="small">ИИ пишет короткий процесс, сервис строит по нему схему, калькулятор считает правильные ответы.
                Участник видит схему без возможности её менять, ответы проверяются автоматически.</p>
            <form id="complexityForm">
                <label for="cCount">Количество (1–3)</label>
                <input id="cCount" type="number" name="count" min="1" max="3" value="1">
                <label for="cTopic">Тематика <small>· необязательно</small></label>
                <input id="cTopic" type="text" name="topic" maxlength="300" placeholder="документооборот, кадры">
                <button class="btn btn-primary" type="submit" <?= $schemesReady ? '' : 'disabled' ?>>Сгенерировать</button>
                <?php if (!$schemesReady): ?>
                    <p class="small ai-warn">Нужны переменные CCT_MCP_URL (сборка схем) и CALCULATOR_URL (калькулятор).</p>
                <?php endif; ?>
                <div class="ai-msg" id="complexityMsg"></div>
            </form>
        </section>
    </div>

    <section class="ai-drafts">
        <h2>Черновики на проверку</h2>
        <div id="drafts">Загрузка…</div>
    </section>
</main>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-navigated-viewer.development.js"></script>
<script>
    const csrfToken = <?= json_encode(csrfToken()) ?>;
    const api = '../api/admin/ai.php';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const variantNames = {all: 'Все варианты', A: 'Вариант A', B: 'Вариант B', C: 'Вариант C'};
    const poolNames = {first: 'Первое задание', second: 'Второе задание'};
    const kindNames = {question: 'Вопрос', practical: 'Практическое задание', complexity: 'Задание 3: расчёт сложности'};
    const answerLabels = {contacts: 'Непосредственные контакты', incomingDocuments: 'Входящие документы',
        generatedDocuments: 'Порождаемые документы', managers: 'Руководители', steps: 'Шаги', businessRoles: 'Бизнес-роли',
        handoffs: 'Передачи', complexity: 'Сложность процесса'};
    const diagrams = {};
    const viewers = {};

    function issuesHtml(list, title) {
        return list && list.length
            ? '<p class="small ai-warn"><b>' + title + ':</b></p><ul class="small">' + list.map(i => '<li>' + escapeHtml(i) + '</li>').join('') + '</ul>'
            : '';
    }

    function message(id, text, ok) {
        document.getElementById(id).innerHTML = '<p style="color:' + (ok ? 'green' : 'red') + ';">' + escapeHtml(text) + '</p>';
    }

    async function postJson(body) {
        const response = await fetch(api, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.assign({csrf_token: csrfToken}, body))
        });
        return response.json();
    }

    function renderSources(sources) {
        document.getElementById('sourceList').innerHTML = sources.length
            ? sources.map(s => '<li>' + escapeHtml(s.title) + ' · ' + Math.round(s.size / 1024) + ' КБ · ' + escapeHtml(s.createdAt) + '</li>').join('')
            : '<li>Пока нет</li>';
        const options = sources.map(s => '<option value="' + s.id + '">' + escapeHtml(s.title) + '</option>').join('');
        document.getElementById('qSource').innerHTML = options || '<option value="">Сначала загрузите методичку</option>';
        document.getElementById('pSource').innerHTML = '<option value="0">Не использовать</option>' + options;
    }

    function draftHtml(d) {
        const p = d.payload || {};
        if (d.kind === 'complexity') {
            diagrams['bpmn_' + d.id] = p.xml;
            const rows = Object.entries(p.answers || {}).map(([k, v]) => `<tr><td>${escapeHtml(answerLabels[k] || k)}</td><td><b>${escapeHtml(v)}</b></td></tr>`).join('');
            return `
                <p><b>${escapeHtml(p.title)}</b></p>
                <p class="small">${escapeHtml(p.description)}</p>
                <div id="bpmn_${d.id}" class="ai-bpmn"></div>
                <p class="small"><b>Правильные ответы (калькулятор):</b></p>
                <table class="ai-answers">${rows}</table>
                ${issuesHtml(p.issues, 'Замечания калькулятора к схеме')}`;
        }
        if (d.kind === 'question' && p.format === 'image_options') {
            const fragments = (p.option_xmls || []).map((xml, i) => {
                diagrams['bpmn_' + d.id + '_o' + i] = xml;
                return `<label class="ai-fragment ${Number(p.correct_index) === i ? 'correct' : ''}">
                    <input type="radio" name="correct_${d.id}" value="${i}" ${Number(p.correct_index) === i ? 'checked' : ''}> Вариант ${i + 1}
                    <div id="bpmn_${d.id}_o${i}" class="ai-bpmn-small" data-svg="option" data-index="${i}"></div></label>`;
            }).join('');
            return `
                ${p.slot ? '<p class="small">Слот ' + Number(p.slot) + ' образца A: ' + escapeHtml(p.slot_topic || '') + '</p>' : ''}
                <div class="ai-two">
                    <div><label>Вопрос (RU)</label><textarea data-field="question_ru" rows="3">${escapeHtml(p.question_ru)}</textarea></div>
                    <div><label>Вопрос (KZ)</label><textarea data-field="question_kk" rows="3">${escapeHtml(p.question_kk)}</textarea></div>
                </div>
                <label>Ответы-схемы, отметьте правильный</label>
                <div class="ai-fragments">${fragments}</div>
                ${p.explanation ? '<p class="small"><b>Обоснование ИИ:</b> ' + escapeHtml(p.explanation) + '</p>' : ''}
                <label>Вариант теста</label>
                <select data-field="target">${Object.entries(variantNames).map(([v, n]) => `<option value="${v}" ${d.target === v ? 'selected' : ''}>${n}</option>`).join('')}</select>`;
        }
        if (d.kind === 'question') {
            if (p.stem_xml) {
                diagrams['bpmn_' + d.id + '_stem'] = p.stem_xml;
            }
            const options = [0, 1, 2, 3].map(i => `
                <div class="ai-option">
                    <input type="radio" name="correct_${d.id}" value="${i}" ${Number(p.correct_index) === i ? 'checked' : ''} aria-label="Правильный ответ ${i + 1}">
                    <input type="text" data-field="options_ru" data-index="${i}" value="${escapeHtml((p.options_ru || [])[i])}">
                </div>`).join('');
            const optionsKk = [0, 1, 2, 3].map(i => `
                <div class="ai-option"><span>${i + 1}.</span>
                    <input type="text" data-field="options_kk" data-index="${i}" value="${escapeHtml((p.options_kk || [])[i])}">
                </div>`).join('');
            return `
                ${p.slot ? '<p class="small">Слот ' + Number(p.slot) + ' образца A: ' + escapeHtml(p.slot_topic || '') + '</p>' : ''}
                ${p.stem_xml ? `<label>Схема в вопросе</label><div id="bpmn_${d.id}_stem" class="ai-bpmn-small" data-svg="stem"></div>` : ''}
                <div class="ai-two">
                    <div><label>Вопрос (RU)</label><textarea data-field="question_ru" rows="3">${escapeHtml(p.question_ru)}</textarea>
                        <label>Ответы (RU), отметьте правильный</label><div class="ai-options">${options}</div></div>
                    <div><label>Вопрос (KZ)</label><textarea data-field="question_kk" rows="3">${escapeHtml(p.question_kk)}</textarea>
                        <label>Ответы (KZ)</label><div class="ai-options">${optionsKk}</div></div>
                </div>
                ${p.explanation ? '<p class="small"><b>Обоснование ИИ:</b> ' + escapeHtml(p.explanation) + '</p>' : ''}
                <label>Вариант теста</label>
                <select data-field="target">${Object.entries(variantNames).map(([v, n]) => `<option value="${v}" ${d.target === v ? 'selected' : ''}>${n}</option>`).join('')}</select>`;
        }
        return `
            <div class="ai-two">
                <div><label>Название (RU)</label><input type="text" data-field="title_ru" value="${escapeHtml(p.title_ru)}">
                    <label>Описание (RU)</label><textarea data-field="description_ru" rows="6">${escapeHtml(p.description_ru)}</textarea></div>
                <div><label>Название (KZ)</label><input type="text" data-field="title_kk" value="${escapeHtml(p.title_kk)}">
                    <label>Описание (KZ)</label><textarea data-field="description_kk" rows="6">${escapeHtml(p.description_kk)}</textarea></div>
            </div>
            ${p.reference_xml ? (diagrams['bpmn_' + d.id] = p.reference_xml, `<label>Эталонная схема (построена автоматически, участник её не видит)</label><div id="bpmn_${d.id}" class="ai-bpmn"></div>`) : ''}
            ${p.reference_error ? '<p class="small ai-warn">Эталон не построен: ' + escapeHtml(p.reference_error) + '</p>' : ''}
            ${issuesHtml(p.reference_issues, 'Замечания калькулятора к эталону')}
            <label>Эталонное решение словами (для ИИ-проверки, участник его не видит)</label>
            <textarea data-field="solution_notes" rows="5">${escapeHtml(p.solution_notes)}</textarea>
            <label>Пул</label>
            <select data-field="target">${Object.entries(poolNames).map(([v, n]) => `<option value="${v}" ${d.target === v ? 'selected' : ''}>${n}</option>`).join('')}</select>`;
    }

    function renderDrafts(drafts) {
        const box = document.getElementById('drafts');
        if (!drafts.length) {
            box.innerHTML = '<p>Черновиков нет.</p>';
            return;
        }
        box.innerHTML = drafts.map(d => `
            <article class="ai-draft" data-id="${d.id}" data-kind="${d.kind}">
                <div class="ai-draft-head">
                    <span class="ai-badge">${kindNames[d.kind] || d.kind}${d.kind === 'complexity' ? '' : ' · ' + escapeHtml(d.kind === 'question' ? variantNames[d.target] : poolNames[d.target])}</span>
                    <span class="small">${escapeHtml(d.createdAt)}</span>
                </div>
                ${draftHtml(d)}
                <div class="ai-actions">
                    <button class="btn btn-primary" type="button" data-approve="${d.id}">Одобрить и добавить в экзамен</button>
                    <button class="btn btn-secondary" type="button" data-reject="${d.id}">Отклонить</button>
                </div>
                <div class="ai-msg"></div>
            </article>`).join('');
        renderDiagrams();
    }

    async function renderDiagrams() {
        if (typeof BpmnJS === 'undefined') {
            return;
        }
        for (const [id, xml] of Object.entries(diagrams)) {
            const container = document.getElementById(id);
            if (!container || !xml || container.dataset.rendered) {
                continue;
            }
            container.dataset.rendered = '1';
            try {
                const viewer = new BpmnJS({container: container});
                await viewer.importXML(xml);
                viewer.get('canvas').zoom('fit-viewport');
                viewers[id] = viewer;
            } catch (e) {
                container.textContent = 'Не удалось показать схему';
            }
        }
    }

    // Схемы вопросов сохраняются картинками SVG: участник видит их так же, как прежние PNG.
    async function collectSvgs(article) {
        const boxes = article.querySelectorAll('[data-svg]');
        if (!boxes.length) {
            return null;
        }
        const svgs = {options: []};
        for (const box of boxes) {
            const viewer = viewers[box.id];
            if (!viewer) {
                throw new Error('Схемы ещё не отрисованы, подождите пару секунд');
            }
            const {svg} = await viewer.saveSVG();
            if (box.dataset.svg === 'stem') {
                svgs.stem = svg;
            } else {
                svgs.options[Number(box.dataset.index)] = svg;
            }
        }
        return svgs;
    }

    function collectEdits(article) {
        const edited = {};
        article.querySelectorAll('[data-field]').forEach(el => {
            const field = el.dataset.field;
            if (el.dataset.index !== undefined) {
                edited[field] = edited[field] || [];
                edited[field][Number(el.dataset.index)] = el.value;
            } else {
                edited[field] = el.value;
            }
        });
        const correct = article.querySelector('input[type=radio]:checked');
        if (correct) {
            edited.correct_index = Number(correct.value);
        }
        return edited;
    }

    // Подсветка выбранной правильной схемы.
    document.getElementById('drafts').addEventListener('change', event => {
        if (event.target.matches('.ai-fragment input[type=radio]')) {
            event.target.closest('.ai-fragments').querySelectorAll('.ai-fragment').forEach(el => el.classList.toggle('correct', el.contains(event.target)));
        }
    });

    document.getElementById('drafts').addEventListener('click', async event => {
        const approve = event.target.closest('[data-approve]');
        const reject = event.target.closest('[data-reject]');
        if (!approve && !reject) {
            return;
        }
        const article = event.target.closest('.ai-draft');
        const msg = article.querySelector('.ai-msg');
        event.target.disabled = true;
        let json;
        try {
            if (approve) {
                const edited = collectEdits(article);
                const svgs = await collectSvgs(article);
                if (svgs) {
                    edited.svgs = svgs;
                }
                json = await postJson({action: 'approve_draft', id: Number(approve.dataset.approve), edited: edited});
            } else {
                json = await postJson({action: 'reject_draft', id: Number(reject.dataset.reject)});
            }
        } catch (e) {
            json = {success: false, message: e.message || 'Не удалось связаться с сервером'};
        }
        if (!json.success) {
            event.target.disabled = false;
            msg.innerHTML = '<p style="color:red;">' + escapeHtml(json.message || 'Ошибка') + '</p>';
            return;
        }
        renderDrafts(json.drafts);
        loadVariants();
    });

    document.getElementById('sourceForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.target;
        message('sourceMsg', 'Загрузка…', true);
        const response = await fetch(api, {method: 'POST', body: new FormData(form)});
        const json = await response.json();
        if (!json.success) {
            message('sourceMsg', json.message || 'Ошибка', false);
            return;
        }
        form.reset();
        message('sourceMsg', 'Методичка сохранена', true);
        renderSources(json.sources);
    });

    // Генерация идёт фоновой задачей на сервере: запускаем и опрашиваем статус.
    async function waitJob(jobId, onTick) {
        const started = Date.now();
        for (;;) {
            await new Promise(r => setTimeout(r, 3000));
            let json;
            try {
                json = await fetch(api + '?action=job&id=' + encodeURIComponent(jobId)).then(r => r.json());
            } catch (e) {
                continue; // сеть моргнула, пробуем дальше
            }
            if (!json.success) return json;
            if (json.job.status === 'done' || json.job.status === 'failed') return json;
            if (onTick) onTick(json.job, Math.round((Date.now() - started) / 1000));
        }
    }

    async function generate(formId, msgId, action) {
        const form = document.getElementById(formId);
        const button = form.querySelector('button[type=submit]');
        const data = Object.fromEntries(new FormData(form).entries());
        button.disabled = true;
        message(msgId, 'Задача отправлена ИИ…', true);
        try {
            const start = await postJson(Object.assign({action: action}, data, {count: Number(data.count), sourceId: Number(data.sourceId || 0)}));
            if (!start.success) {
                message(msgId, start.message || 'Ошибка', false);
                return;
            }
            const json = (start.job && (start.job.status === 'done' || start.job.status === 'failed'))
                ? {success: true, job: start.job}
                : await waitJob(start.jobId, (job, sec) => message(msgId,
                    (job.status === 'queued' ? 'В очереди' : 'ИИ генерирует') + ', прошло ' + sec + ' с. Обычно 1–3 минуты, страницу можно не держать открытой.', true));
            if (!json.success || json.job.status === 'failed') {
                message(msgId, json.message || json.job.error || 'Ошибка', false);
                return;
            }
            const result = json.job.result || {};
            message(msgId, (result.variant ? 'Создан вариант ' + result.variant + '. ' : '') + 'Создано черновиков: ' + (result.created || 0) + '. Проверьте их ниже.'
                + (result.errors && result.errors.length ? ' Не получились: ' + result.errors.join('; ') : ''), true);
            await loadVariants();
            const drafts = json.drafts || (await fetch(api + '?action=drafts').then(r => r.json())).drafts || [];
            renderDrafts(drafts);
        } catch (e) {
            message(msgId, 'Не удалось связаться с сервером', false);
        } finally {
            button.disabled = false;
        }
    }

    const qTemplate = document.getElementById('qTemplate');
    const syncTemplate = () => {
        const byTemplate = qTemplate.value !== '';
        document.getElementById('qCountBox').style.display = byTemplate ? 'none' : '';
        document.getElementById('qVariantBox').style.display = byTemplate ? 'none' : '';
        document.getElementById('qNewVariantNote').style.display = byTemplate ? '' : 'none';
    };
    qTemplate.addEventListener('change', syncTemplate);
    syncTemplate();

    document.getElementById('questionsForm').addEventListener('submit', e => { e.preventDefault(); generate('questionsForm', 'questionsMsg', 'generate_questions'); });
    document.getElementById('practicalForm').addEventListener('submit', e => { e.preventDefault(); generate('practicalForm', 'practicalMsg', 'generate_practical'); });
    document.getElementById('complexityForm').addEventListener('submit', e => { e.preventDefault(); generate('complexityForm', 'complexityMsg', 'generate_complexity'); });

    // Варианты теста: встроенные A, B, C и созданные генерацией (D, E…), с готовностью.
    async function loadVariants() {
        let json;
        try {
            json = await fetch(api + '?action=variants').then(r => r.json());
        } catch (e) {
            return;
        }
        const variants = json.variants || [];
        variants.forEach(v => { variantNames[v.code] = 'Вариант ' + v.code; });
        document.getElementById('variantList').innerHTML = variants.map(v => {
            if (v.builtin) {
                return '<li><b>' + escapeHtml(v.code) + '</b> — встроенный' + (v.approved ? ', добавлено вопросов: ' + v.approved : '') + '</li>';
            }
            const state = v.active
                ? '<span style="color:green">выдаётся студентам</span>'
                : 'пока не выдаётся: одобрено ' + v.approved + ' из ' + json.size + (v.pending ? ', ждут проверки ' + v.pending : '');
            const fill = !v.active && v.approved + v.pending < json.size
                ? ` <button type="button" class="btn btn-secondary" data-fill="${escapeHtml(v.code)}">Догенерировать недостающие</button>` : '';
            return '<li><b>' + escapeHtml(v.code) + '</b> — ' + escapeHtml(v.title || 'создан генерацией') + ' · ' + state + fill + '</li>';
        }).join('');
    }

    // Дополнить неполный вариант недостающими вопросами (если часть отклонили или не получилась).
    document.getElementById('variantList').addEventListener('click', async event => {
        const button = event.target.closest('[data-fill]');
        if (!button) {
            return;
        }
        const source = document.getElementById('qSource').value;
        if (!source) {
            message('questionsMsg', 'Сначала загрузите методичку', false);
            return;
        }
        button.disabled = true;
        const form = document.getElementById('questionsForm');
        const submit = form.querySelector('button[type=submit]');
        submit.disabled = true;
        try {
            const start = await postJson({action: 'generate_questions', template: 'A', fillVariant: button.dataset.fill, sourceId: Number(source)});
            if (!start.success) {
                message('questionsMsg', start.message || 'Ошибка', false);
                return;
            }
            const json = await waitJob(start.jobId, (job, sec) => message('questionsMsg', 'Дополняю вариант ' + button.dataset.fill + ', прошло ' + sec + ' с…', true));
            if (!json.success || json.job.status === 'failed') {
                message('questionsMsg', json.message || json.job.error || 'Ошибка', false);
                return;
            }
            const result = json.job.result || {};
            message('questionsMsg', 'Вариант ' + button.dataset.fill + ': добавлено черновиков ' + (result.created || 0) + '.'
                + (result.errors && result.errors.length ? ' Не получились: ' + result.errors.join('; ') : ''), true);
            await reloadAll();
        } catch (e) {
            message('questionsMsg', 'Не удалось связаться с сервером', false);
        } finally {
            submit.disabled = false;
        }
    });

    async function reloadAll() {
        await loadVariants();
        const [sources, drafts] = await Promise.all([
            fetch(api + '?action=sources').then(r => r.json()),
            fetch(api + '?action=drafts').then(r => r.json())
        ]);
        renderSources(sources.sources || []);
        renderDrafts(drafts.drafts || []);
    }
    reloadAll();
</script>
</body>
</html>
