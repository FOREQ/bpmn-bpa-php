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
        .ai-page { max-width: 1080px; }
        .ai-page h1 { font-size: 1.6rem; margin: 0 0 16px; }
        .ai-off { background: var(--status-waiting-bg); color: var(--status-waiting-text); border-radius: var(--radius-md); padding: 10px 14px; margin-bottom: 16px; font-size: .95rem; }
        .ai-box { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); padding: 16px; }
        .ai-tabs { display: flex; gap: 4px; margin: -4px 0 14px; overflow-x: auto; scrollbar-width: none; }
        .ai-tabs button { padding: 8px 14px; border-radius: 999px; background: transparent; color: var(--muted); font-weight: 700; font-size: .95rem; white-space: nowrap; }
        .ai-tabs button[aria-selected=true] { background: var(--accent-soft-bg); color: var(--accent); }
        .ai-form { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .ai-form[hidden] { display: none; }
        .ai-form > * { flex: 1 1 180px; min-width: 0; }
        .ai-form .ai-narrow { flex: 0 1 110px; }
        .ai-form .btn { flex: 0 0 auto; }
        .ai-page input[type=text], .ai-page input[type=number], .ai-page select, .ai-page textarea {
            width: 100%; box-sizing: border-box; font: inherit; font-size: 15px; padding: 10px 12px;
            border: 1px solid var(--line); border-radius: var(--radius-sm); background: #fff; color: var(--ink); }
        .ai-page textarea { resize: vertical; }
        .ai-page input[type=file] { font-size: 14px; }
        .ai-page .btn { padding: 10px 20px; box-shadow: none; }
        .ai-page .btn-primary:hover { transform: none; }
        .ai-note { font-size: .9rem; color: var(--muted); margin: 10px 0 0; }
        .ai-note:empty { display: none; }
        .ai-note.err { color: var(--status-failed-text); }
        .ai-sources { list-style: none; padding: 0; margin: 12px 0 0; font-size: .9rem; color: var(--muted); }
        .ai-sources li { padding: 4px 0; border-top: 1px solid var(--line-soft); }

        .ai-groups { margin-top: 24px; display: grid; gap: 10px; }
        .ai-group { background: #fff; border: 1px solid var(--line); border-radius: var(--radius-lg); }
        .ai-group > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
        .ai-group > summary::-webkit-details-marker { display: none; }
        .ai-group > summary::after { content: '›'; margin-left: auto; color: var(--faint); font-size: 1.4rem; line-height: 1; transition: transform .15s; }
        .ai-group[open] > summary::after { transform: rotate(90deg); }
        .ai-group-title { font-weight: 800; white-space: nowrap; }
        .ai-progress { flex: 0 1 140px; height: 6px; border-radius: 3px; background: var(--line-soft); overflow: hidden; }
        .ai-progress i { display: block; height: 100%; background: var(--accent); }
        .ai-group.done .ai-progress i { background: var(--status-passed-text); }
        .ai-group-meta { color: var(--muted); font-size: .9rem; white-space: nowrap; }
        .ai-pill { font-size: .8rem; font-weight: 700; border-radius: 999px; padding: 2px 10px; background: var(--status-passed-bg); color: var(--status-passed-text); white-space: nowrap; }
        .ai-group-body { padding: 0 16px 8px; }
        .ai-group-bar { display: flex; flex-wrap: wrap; gap: 8px; padding-bottom: 8px; }
        .ai-link { background: none; padding: 4px 0; color: var(--accent); font-weight: 700; font-size: .9rem; }
        .ai-link:hover { text-decoration: underline; }

        .ai-draft { border-top: 1px solid var(--line-soft); padding: 14px 0; }
        .ai-draft-row { display: flex; gap: 12px; align-items: flex-start; }
        .ai-draft-main { flex: 1; min-width: 0; }
        .ai-num { color: var(--faint); font-size: .85rem; font-weight: 700; margin-right: 6px; }
        .ai-q { font-weight: 600; margin: 4px 0 0; }
        .ai-draft-body { margin-top: 8px; }
        .ai-icons { display: flex; gap: 4px; flex: 0 0 auto; }
        .ai-icons button { width: 36px; height: 36px; padding: 0; border-radius: 50%; background: var(--line-soft); color: var(--muted); font-size: 16px; font-weight: 700; }
        .ai-icons [data-approve] { background: var(--status-passed-bg); color: var(--status-passed-text); }
        .ai-icons [data-reject]:hover { background: var(--status-failed-bg); color: var(--status-failed-text); }
        .ai-icons [data-edit][aria-pressed=true] { background: var(--accent-soft-bg); color: var(--accent); }
        .ai-answers-view { list-style: none; padding: 0; margin: 0; display: grid; gap: 4px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ai-answers-view li { margin: 0; }
        .ai-answers-view label { margin: 0; font-weight: 400; display: flex; gap: 8px; align-items: center; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--line-soft); cursor: pointer; font-size: .95rem; }
        .ai-answers-view input { margin: 0; accent-color: var(--status-passed-text); }
        .ai-answers-view label:has(input:checked) { border-color: var(--status-passed-text); background: var(--status-passed-bg); }
        .ai-schemes { display: grid; gap: 8px; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .ai-scheme { margin: 0; position: relative; border: 1px solid var(--line); border-radius: var(--radius-sm); cursor: pointer; overflow: hidden; }
        .ai-scheme input { position: absolute; top: 6px; left: 6px; margin: 0; z-index: 2; accent-color: var(--status-passed-text); }
        .ai-scheme:has(input:checked) { border-color: var(--status-passed-text); box-shadow: 0 0 0 2px var(--status-passed-bg); }
        .ai-bpmn-small { height: 140px; }
        .ai-stem { height: 160px; border: 1px solid var(--line-soft); border-radius: var(--radius-sm); margin-bottom: 8px; }
        .ai-bpmn { height: 320px; border: 1px solid var(--line-soft); border-radius: var(--radius-sm); margin: 8px 0; }
        .ai-page .bjs-powered-by { transform: scale(.6); transform-origin: right bottom; opacity: .5; }
        .ai-desc { color: var(--muted); font-size: .95rem; margin: 0 0 6px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .ai-kv { font-size: .9rem; color: var(--muted); display: flex; flex-wrap: wrap; gap: 4px 14px; }
        .ai-kv b { color: var(--ink); }
        .ai-edit { display: none; margin-top: 10px; gap: 8px; }
        .ai-draft.editing .ai-edit { display: grid; }
        .ai-edit-cols { display: grid; gap: 8px 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .ai-edit-cols > div { display: grid; gap: 6px; align-content: start; }
        .ai-edit small { color: var(--muted); font-weight: 700; }
        .ai-draft .ai-note { margin-top: 6px; }
        .ai-warn { color: var(--status-waiting-text); font-size: .9rem; }
        .ai-empty { color: var(--muted); text-align: center; padding: 24px 0; }
        .ai-muted { color: var(--faint); font-size: .85rem; margin-top: 12px; }

        @media (max-width: 640px) {
            .ai-page h1 { font-size: 1.3rem; }
            .ai-box { padding: 12px; }
            .ai-tabs { flex-wrap: wrap; }
            .ai-tabs button { padding: 6px 10px; font-size: .88rem; }
            .ai-form > *, .ai-form .ai-narrow, .ai-form .btn { flex: 1 1 100%; }
            .ai-group > summary { flex-wrap: wrap; gap: 6px 10px; padding: 12px; }
            .ai-progress { flex: 1 1 80px; }
            .ai-group-body { padding: 0 12px 6px; }
            .ai-answers-view, .ai-edit-cols { grid-template-columns: 1fr; }
            .ai-schemes { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .ai-bpmn-small { height: 110px; }
            .ai-bpmn { height: 240px; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/_header.php'; ?>

<main class="container container-wide ai-page">
    <a class="content-back" href="admin.php">← Админка</a>
    <h1>ИИ-генерация</h1>
    <?php if (!$generatorReady): ?>
        <div class="ai-off">ИИ не подключён: нужны AI_BASE_URL, AI_MODEL и AI_API_KEY на сервере.</div>
    <?php endif; ?>

    <section class="ai-box">
        <div class="ai-tabs" role="tablist">
            <button type="button" role="tab" data-tab="questionsForm" aria-selected="true">Вопросы</button>
            <button type="button" role="tab" data-tab="practicalForm" aria-selected="false">Задания 1–2</button>
            <button type="button" role="tab" data-tab="complexityForm" aria-selected="false">Задание 3</button>
            <button type="button" role="tab" data-tab="sourceForm" aria-selected="false">Методички</button>
        </div>

        <form id="questionsForm" class="ai-form">
            <select id="qSource" name="sourceId" aria-label="Методичка" required></select>
            <select id="qTemplate" name="template" aria-label="Как составлять">
                <option value="A">Новый вариант по образцу A</option>
                <option value="">Свободно, в существующий вариант</option>
            </select>
            <select id="qVariant" name="variant" aria-label="Вариант" class="ai-narrow" hidden>
                <option value="all">Все</option>
                <option value="A">A</option>
                <option value="B">B</option>
                <option value="C">C</option>
            </select>
            <input id="qCount" type="number" name="count" min="1" max="20" value="20" aria-label="Количество" class="ai-narrow" hidden>
            <input id="qTopic" type="text" name="topic" maxlength="300" placeholder="Тема (необязательно)" aria-label="Тема">
            <button class="btn btn-primary" type="submit" <?= $generatorReady ? '' : 'disabled' ?>>Создать</button>
        </form>

        <form id="practicalForm" class="ai-form" hidden>
            <select id="pPool" name="pool" aria-label="Задание">
                <option value="first">Задание 1, бытовое</option>
                <option value="second" selected>Задание 2, госорган</option>
            </select>
            <input id="pCount" type="number" name="count" min="1" max="5" value="2" aria-label="Количество" class="ai-narrow">
            <input id="pTopic" type="text" name="topic" maxlength="300" placeholder="Тема (необязательно)" aria-label="Тема">
            <select id="pSource" name="sourceId" aria-label="Методичка"></select>
            <button class="btn btn-primary" type="submit" <?= $generatorReady ? '' : 'disabled' ?>>Создать</button>
        </form>

        <form id="complexityForm" class="ai-form" hidden>
            <input id="cCount" type="number" name="count" min="1" max="3" value="1" aria-label="Количество" class="ai-narrow">
            <input id="cTopic" type="text" name="topic" maxlength="300" placeholder="Тема (необязательно)" aria-label="Тема">
            <button class="btn btn-primary" type="submit" <?= $schemesReady ? '' : 'disabled' ?>>Создать</button>
            <?php if (!$schemesReady): ?>
                <p class="ai-warn" style="flex-basis:100%;margin:0">Нужны CCT_MCP_URL и CALCULATOR_URL на сервере.</p>
            <?php endif; ?>
        </form>

        <form id="sourceForm" class="ai-form" enctype="multipart/form-data" hidden>
            <input type="hidden" name="action" value="source">
            <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
            <input id="sourceTitle" type="text" name="title" maxlength="200" placeholder="Название" aria-label="Название">
            <input id="sourceFile" type="file" name="file" accept=".pdf,.docx,.txt,.md" aria-label="Файл">
            <button class="btn btn-primary" type="submit">Загрузить</button>
            <textarea id="sourceText" name="text" rows="2" placeholder="…или вставьте текст" aria-label="Текст" style="flex-basis:100%"></textarea>
            <ul id="sourceList" class="ai-sources" style="flex-basis:100%"></ul>
        </form>

        <p class="ai-note" id="genMsg" role="status"></p>
    </section>

    <div id="groups" class="ai-groups"><p class="ai-empty">Загрузка…</p></div>
    <p class="ai-muted">Одобренное попадает в экзамен и в раздел <a href="content.php">«Вопросы и задания»</a>.</p>
</main>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-navigated-viewer.development.js"></script>
<script>
    const csrfToken = <?= json_encode(csrfToken()) ?>;
    const api = '../api/admin/ai.php';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const answerLabels = {contacts: 'Контакты', incomingDocuments: 'Входящие док.', generatedDocuments: 'Порождаемые док.',
        managers: 'Руководители', steps: 'Шаги', businessRoles: 'Бизнес-роли', handoffs: 'Передачи', complexity: 'Сложность'};
    const state = {drafts: [], variants: [], size: 20, open: new Set()};
    const diagrams = {};
    let busy = false;

    function note(text, isError) {
        const el = document.getElementById('genMsg');
        el.textContent = text || '';
        el.classList.toggle('err', !!isError);
    }

    async function postJson(body) {
        const response = await fetch(api, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(Object.assign({csrf_token: csrfToken}, body))
        });
        return response.json();
    }

    // Вкладки формы генерации.
    document.querySelector('.ai-tabs').addEventListener('click', event => {
        const tab = event.target.closest('[data-tab]');
        if (!tab) return;
        document.querySelectorAll('.ai-tabs [data-tab]').forEach(t => {
            const on = t === tab;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            document.getElementById(t.dataset.tab).hidden = !on;
        });
    });

    const qTemplate = document.getElementById('qTemplate');
    const syncTemplate = () => {
        const free = qTemplate.value === '';
        document.getElementById('qVariant').hidden = !free;
        document.getElementById('qCount').hidden = !free;
    };
    qTemplate.addEventListener('change', syncTemplate);
    syncTemplate();

    function renderSources(sources) {
        document.getElementById('sourceList').innerHTML = sources
            .map(s => '<li>' + escapeHtml(s.title) + ' · ' + Math.round(s.size / 1024) + ' КБ</li>').join('');
        const options = sources.map(s => '<option value="' + s.id + '">' + escapeHtml(s.title) + '</option>').join('');
        document.getElementById('qSource').innerHTML = options || '<option value="">Нет методички — загрузите</option>';
        document.getElementById('pSource').innerHTML = '<option value="0">Без методички</option>' + options;
    }

    // --- Черновики ---------------------------------------------------------

    function radio(d, i) {
        return `<input type="radio" name="correct_${d.id}" value="${i}" ${Number(d.payload.correct_index) === i ? 'checked' : ''}>`;
    }

    function questionHtml(d) {
        const p = d.payload || {};
        const num = p.slot ? `<span class="ai-num">№${Number(p.slot)}</span>` : '';
        let answers;
        if (p.format === 'image_options') {
            answers = '<div class="ai-schemes">' + (p.option_xmls || []).map((xml, i) => {
                diagrams[`bpmn_${d.id}_o${i}`] = xml;
                return `<label class="ai-scheme" aria-label="Схема ${i + 1}">${radio(d, i)}<div id="bpmn_${d.id}_o${i}" class="ai-bpmn-small"></div></label>`;
            }).join('') + '</div>';
        } else {
            answers = '<ul class="ai-answers-view">' + [0, 1, 2, 3].map(i =>
                `<li><label>${radio(d, i)}<span data-view="options_ru" data-index="${i}">${escapeHtml((p.options_ru || [])[i])}</span></label></li>`).join('') + '</ul>';
        }
        if (p.stem_xml) diagrams[`bpmn_${d.id}_stem`] = p.stem_xml;
        const textOptions = p.format === 'image_options' ? '' : [0, 1, 2, 3].map(i => `
            <input type="text" data-field="options_ru" data-index="${i}" value="${escapeHtml((p.options_ru || [])[i])}" aria-label="Ответ ${i + 1} RU">`).join('');
        const textOptionsKk = p.format === 'image_options' ? '' : [0, 1, 2, 3].map(i => `
            <input type="text" data-field="options_kk" data-index="${i}" value="${escapeHtml((p.options_kk || [])[i])}" aria-label="Ответ ${i + 1} KZ">`).join('');
        const targetSelect = p.slot ? '' : `<select data-field="target" aria-label="Вариант">${['all', 'A', 'B', 'C'].concat(state.variants.filter(v => !v.builtin).map(v => v.code))
            .map(v => `<option value="${v}" ${d.target === v ? 'selected' : ''}>${v === 'all' ? 'Все варианты' : 'Вариант ' + v}</option>`).join('')}</select>`;
        return {
            head: `<p class="ai-q" title="${escapeHtml(p.slot_topic || '')}">${num}<span data-view="question_ru">${escapeHtml(p.question_ru)}</span></p>`,
            view: `${p.stem_xml ? `<div id="bpmn_${d.id}_stem" class="ai-stem"></div>` : ''}${answers}`,
            edit: `<div class="ai-edit-cols">
                    <div><small>RU</small><textarea data-field="question_ru" rows="2">${escapeHtml(p.question_ru)}</textarea>${textOptions}</div>
                    <div><small>KZ</small><textarea data-field="question_kk" rows="2">${escapeHtml(p.question_kk)}</textarea>${textOptionsKk}</div>
                </div>
                ${p.explanation ? '<p class="ai-note">ИИ: ' + escapeHtml(p.explanation) + '</p>' : ''}${targetSelect}`
        };
    }

    function practicalHtml(d) {
        const p = d.payload || {};
        if (p.reference_xml) diagrams['bpmn_' + d.id] = p.reference_xml;
        const issues = (p.reference_issues || []).length ? `<p class="ai-warn">Замечания к эталону: ${p.reference_issues.map(escapeHtml).join('; ')}</p>` : '';
        return {
            head: `<p class="ai-q" data-view="title_ru">${escapeHtml(p.title_ru)}</p>`,
            view: `<p class="ai-desc" data-view="description_ru">${escapeHtml(p.description_ru)}</p>
                ${p.reference_xml ? `<div id="bpmn_${d.id}" class="ai-bpmn"></div>` : ''}
                ${p.reference_error ? '<p class="ai-warn">Эталон не построен: ' + escapeHtml(p.reference_error) + '</p>' : ''}${issues}`,
            edit: `<div class="ai-edit-cols">
                    <div><small>RU</small><input type="text" data-field="title_ru" value="${escapeHtml(p.title_ru)}">
                        <textarea data-field="description_ru" rows="6">${escapeHtml(p.description_ru)}</textarea></div>
                    <div><small>KZ</small><input type="text" data-field="title_kk" value="${escapeHtml(p.title_kk)}">
                        <textarea data-field="description_kk" rows="6">${escapeHtml(p.description_kk)}</textarea></div>
                </div>
                <small>Эталонное решение (участник не видит)</small>
                <textarea data-field="solution_notes" rows="4">${escapeHtml(p.solution_notes)}</textarea>
                <select data-field="target" aria-label="Задание">
                    <option value="first" ${d.target === 'first' ? 'selected' : ''}>Задание 1</option>
                    <option value="second" ${d.target === 'second' ? 'selected' : ''}>Задание 2</option>
                </select>`
        };
    }

    function complexityHtml(d) {
        const p = d.payload || {};
        diagrams['bpmn_' + d.id] = p.xml;
        const kv = Object.entries(p.answers || {}).map(([k, v]) => `<span>${escapeHtml(answerLabels[k] || k)}: <b>${escapeHtml(v)}</b></span>`).join('');
        const issues = (p.issues || []).length ? `<p class="ai-warn">Замечания калькулятора: ${p.issues.map(escapeHtml).join('; ')}</p>` : '';
        return {
            head: `<p class="ai-q">${escapeHtml(p.title)}</p>`,
            view: `<p class="ai-desc">${escapeHtml(p.description)}</p>
                <div id="bpmn_${d.id}" class="ai-bpmn"></div><div class="ai-kv">${kv}</div>${issues}`,
            edit: ''
        };
    }

    function draftHtml(d) {
        const parts = d.kind === 'question' ? questionHtml(d) : d.kind === 'complexity' ? complexityHtml(d) : practicalHtml(d);
        return `<article class="ai-draft" data-id="${d.id}">
            <div class="ai-draft-row">
                <div class="ai-draft-main">${parts.head}</div>
                <div class="ai-icons">
                    <button type="button" data-approve="${d.id}" title="Одобрить" aria-label="Одобрить">✓</button>
                    ${parts.edit ? `<button type="button" data-edit aria-pressed="false" title="Править" aria-label="Править">✎</button>` : ''}
                    <button type="button" data-reject="${d.id}" title="Отклонить" aria-label="Отклонить">✕</button>
                </div>
            </div>
            <div class="ai-draft-body">${parts.view}</div>
            ${parts.edit ? `<div class="ai-edit">${parts.edit}</div>` : ''}
            <p class="ai-note err"></p>
        </article>`;
    }

    // Группы: созданные варианты (D, E…), затем прочие черновики по назначению.
    function buildGroups() {
        const groups = [];
        const byKey = {};
        const add = (key, g) => { byKey[key] = Object.assign({key: key, drafts: []}, g); groups.push(byKey[key]); };
        state.variants.filter(v => !v.builtin).slice().reverse().forEach(v => add('v:' + v.code, {title: 'Вариант ' + v.code, variant: v}));
        state.drafts.forEach(d => {
            let key, title;
            if (d.kind === 'question') { key = 'v:' + d.target; title = d.target === 'all' ? 'Вопросы для всех вариантов' : 'Вариант ' + d.target; }
            else if (d.kind === 'complexity') { key = 'complexity'; title = 'Задание 3'; }
            else { key = 'p:' + d.target; title = d.target === 'first' ? 'Задание 1' : 'Задание 2'; }
            if (!byKey[key]) add(key, {title: title});
            byKey[key].drafts.push(d);
        });
        groups.forEach(g => g.drafts.sort((a, b) => (a.payload.slot || 0) - (b.payload.slot || 0) || a.id - b.id));
        return groups.filter(g => g.drafts.length || g.variant);
    }

    function groupHeadHtml(g) {
        const v = g.variant;
        if (!v) return `<span class="ai-group-title">${escapeHtml(g.title)}</span><span class="ai-group-meta">${g.drafts.length} на проверке</span>`;
        const pct = Math.min(100, Math.round(v.approved / state.size * 100));
        const meta = v.active ? '<span class="ai-pill">выдаётся</span>'
            : `<span class="ai-group-meta">${v.approved}/${state.size}${g.drafts.length ? ' · ждут ' + g.drafts.length : ''}</span>`;
        return `<span class="ai-group-title">${escapeHtml(g.title)}</span><span class="ai-progress"><i style="width:${pct}%"></i></span>${meta}`;
    }

    function render() {
        Object.keys(diagrams).forEach(k => delete diagrams[k]);
        const groups = buildGroups();
        const box = document.getElementById('groups');
        if (!groups.length) {
            box.innerHTML = '<p class="ai-empty">Черновиков нет</p>';
            return;
        }
        if (!state.open.size) {
            const first = groups.find(g => g.drafts.length);
            if (first) state.open.add(first.key);
        }
        box.innerHTML = groups.map(g => {
            const v = g.variant;
            const missing = v && !v.active ? state.size - v.approved - g.drafts.length : 0;
            const bar = [
                g.drafts.length > 1 ? `<button type="button" class="ai-link" data-approve-all>Одобрить все (${g.drafts.length})</button>` : '',
                missing > 0 ? `<button type="button" class="ai-link" data-fill="${escapeHtml(v.code)}">Догенерировать ${missing}</button>` : ''
            ].join('');
            const body = state.open.has(g.key) ? g.drafts.map(draftHtml).join('') : '';
            return `<details class="ai-group ${v && v.active ? 'done' : ''}" data-key="${escapeHtml(g.key)}" ${state.open.has(g.key) ? 'open' : ''}>
                <summary>${groupHeadHtml(g)}</summary>
                <div class="ai-group-body">${bar ? `<div class="ai-group-bar">${bar}</div>` : ''}${body}</div>
            </details>`;
        }).join('');
        renderDiagrams();
    }

    async function renderDiagrams() {
        if (typeof BpmnJS === 'undefined') return;
        for (const [id, xml] of Object.entries(diagrams)) {
            const container = document.getElementById(id);
            if (!container || !xml || container.dataset.rendered) continue;
            container.dataset.rendered = '1';
            try {
                const viewer = new BpmnJS({container: container});
                await viewer.importXML(xml);
                const canvas = viewer.get('canvas');
                canvas.zoom('fit-viewport', 'auto');
                // Один-два элемента не растягиваем на всю рамку.
                canvas.zoom(Math.min(1, canvas.zoom()) * 0.85, 'auto');
            } catch (e) {
                container.textContent = 'Не удалось показать схему';
            }
        }
    }

    // Раскрытие группы — рисуем её черновики только тогда (схемы тяжёлые).
    document.getElementById('groups').addEventListener('toggle', event => {
        const g = event.target;
        if (!g.matches || !g.matches('details.ai-group')) return;
        const was = state.open.has(g.dataset.key);
        if (g.open && !was) {
            state.open.add(g.dataset.key);
            const group = buildGroups().find(x => x.key === g.dataset.key);
            const body = g.querySelector('.ai-group-body');
            if (group && !body.querySelector('.ai-draft')) {
                body.insertAdjacentHTML('beforeend', group.drafts.map(draftHtml).join(''));
                renderDiagrams();
            }
        }
        if (!g.open && was) state.open.delete(g.dataset.key);
    }, true);

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
        if (correct) edited.correct_index = Number(correct.value);
        return edited;
    }

    // Правка текста сразу видна в карточке.
    document.getElementById('groups').addEventListener('input', event => {
        const f = event.target.dataset.field;
        if (!f) return;
        const sel = '[data-view="' + f + '"]' + (event.target.dataset.index !== undefined ? '[data-index="' + event.target.dataset.index + '"]' : '');
        const view = event.target.closest('.ai-draft').querySelector(sel);
        if (view) view.textContent = event.target.value;
    });

    async function act(article, approve) {
        const id = Number(article.dataset.id);
        const json = approve
            ? await postJson({action: 'approve_draft', id: id, edited: collectEdits(article)})
            : await postJson({action: 'reject_draft', id: id});
        if (!json.success) throw new Error(json.message || 'Ошибка');
        return json;
    }

    document.getElementById('groups').addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit]');
        if (edit) {
            const on = edit.closest('.ai-draft').classList.toggle('editing');
            edit.setAttribute('aria-pressed', on ? 'true' : 'false');
            return;
        }
        const fill = event.target.closest('[data-fill]');
        if (fill) return fillVariant(fill);
        const all = event.target.closest('[data-approve-all]');
        const one = event.target.closest('[data-approve], [data-reject]');
        if (!all && !one) return;
        const button = all || one;
        button.disabled = true;
        const articles = all ? [...button.closest('.ai-group').querySelectorAll('.ai-draft')] : [button.closest('.ai-draft')];
        let json = null;
        for (const article of articles) {
            try {
                json = await act(article, all || one.hasAttribute('data-approve'));
            } catch (e) {
                article.querySelector('.ai-note').textContent = e.message || 'Не удалось связаться с сервером';
                button.disabled = false;
                break;
            }
        }
        if (json) await reloadAll(json.drafts);
    });

    // --- Генерация (фоновая задача на сервере) ----------------------------

    async function waitJob(jobId, label) {
        const started = Date.now();
        for (;;) {
            await new Promise(r => setTimeout(r, 3000));
            let json;
            try {
                json = await fetch(api + '?action=job&id=' + encodeURIComponent(jobId)).then(r => r.json());
            } catch (e) {
                continue; // сеть моргнула, пробуем дальше
            }
            if (!json.success || json.job.status === 'done' || json.job.status === 'failed') return json;
            const sec = Math.round((Date.now() - started) / 1000);
            note(label + '… ' + Math.floor(sec / 60) + ':' + String(sec % 60).padStart(2, '0'));
        }
    }

    function resultText(result) {
        const errors = (result.errors || []).map(e => (e.match(/^Слот (\d+)/) || [])[1]).filter(Boolean);
        return (result.variant ? 'Вариант ' + result.variant + ': ' : '') + 'черновиков ' + (result.created || 0)
            + (errors.length ? '. Не получились №' + errors.join(', ') : (result.errors || []).length ? '. Ошибки: ' + result.errors.join('; ') : '');
    }

    async function runJob(body, label, button) {
        if (busy) return;
        busy = true;
        if (button) button.disabled = true;
        note(label + '…');
        try {
            const start = await postJson(body);
            if (!start.success) return note(start.message || 'Ошибка', true);
            const json = (start.job && (start.job.status === 'done' || start.job.status === 'failed'))
                ? {success: true, job: start.job} : await waitJob(start.jobId, label);
            if (!json.success || json.job.status === 'failed') return note(json.message || json.job.error || 'Ошибка', true);
            const result = json.job.result || {};
            note(resultText(result));
            if (result.variant) { state.open.clear(); state.open.add('v:' + result.variant); }
            await reloadAll();
        } catch (e) {
            note('Не удалось связаться с сервером', true);
        } finally {
            busy = false;
            if (button) button.disabled = false;
        }
    }

    function submitGenerate(formId, action, label) {
        const form = document.getElementById(formId);
        form.addEventListener('submit', event => {
            event.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            runJob(Object.assign({action: action}, data, {count: Number(data.count || 0), sourceId: Number(data.sourceId || 0)}),
                label, form.querySelector('button[type=submit]'));
        });
    }
    submitGenerate('questionsForm', 'generate_questions', 'Генерирую вопросы');
    submitGenerate('practicalForm', 'generate_practical', 'Генерирую задания');
    submitGenerate('complexityForm', 'generate_complexity', 'Генерирую задание 3');

    function fillVariant(button) {
        const source = Number(document.getElementById('qSource').value || 0);
        if (!source) return note('Сначала загрузите методичку', true);
        runJob({action: 'generate_questions', template: 'A', fillVariant: button.dataset.fill, sourceId: source},
            'Дополняю вариант ' + button.dataset.fill, button);
    }

    document.getElementById('sourceForm').addEventListener('submit', async event => {
        event.preventDefault();
        const form = event.target;
        note('Загрузка…');
        try {
            const json = await fetch(api, {method: 'POST', body: new FormData(form)}).then(r => r.json());
            if (!json.success) return note(json.message || 'Ошибка', true);
            form.reset();
            note('Методичка сохранена');
            renderSources(json.sources);
        } catch (e) {
            note('Не удалось загрузить файл', true);
        }
    });

    async function reloadAll(drafts) {
        const [variants, sources, list] = await Promise.all([
            fetch(api + '?action=variants').then(r => r.json()).catch(() => ({})),
            fetch(api + '?action=sources').then(r => r.json()).catch(() => ({})),
            drafts ? Promise.resolve({drafts: drafts}) : fetch(api + '?action=drafts').then(r => r.json()).catch(() => ({}))
        ]);
        state.variants = variants.variants || [];
        state.size = variants.size || 20;
        state.drafts = list.drafts || [];
        renderSources(sources.sources || []);
        render();
    }
    reloadAll();
</script>
</body>
</html>
