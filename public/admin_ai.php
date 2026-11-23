<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/ai_client.php';

$activeNav = 'admin';
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$generatorReady = aiEnabled('generator');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ИИ: генерация заданий | BPMN</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
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
                <label for="sourceFile">Файл .docx, .txt или .md</label>
                <input id="sourceFile" type="file" name="file" accept=".docx,.txt,.md">
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
                <label for="qVariant">Вариант теста</label>
                <select id="qVariant" name="variant">
                    <option value="all">Все варианты</option>
                    <option value="A">Вариант A</option>
                    <option value="B">Вариант B</option>
                    <option value="C">Вариант C</option>
                </select>
                <label for="qCount">Количество (1–10)</label>
                <input id="qCount" type="number" name="count" min="1" max="10" value="5">
                <label for="qTopic">Тема или акцент <small>· необязательно</small></label>
                <input id="qTopic" type="text" name="topic" maxlength="300" placeholder="шлюзы, события, параметры оптимизации">
                <button class="btn btn-primary" type="submit" <?= $generatorReady ? '' : 'disabled' ?>>Сгенерировать</button>
                <div class="ai-msg" id="questionsMsg"></div>
            </form>
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
        </section>
    </div>

    <section class="ai-drafts">
        <h2>Черновики на проверку</h2>
        <div id="drafts">Загрузка…</div>
    </section>
</main>

<script>
    const csrfToken = <?= json_encode(csrfToken()) ?>;
    const api = '../api/admin/ai.php';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const variantNames = {all: 'Все варианты', A: 'Вариант A', B: 'Вариант B', C: 'Вариант C'};
    const poolNames = {first: 'Первое задание', second: 'Второе задание'};

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
        if (d.kind === 'question') {
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
            <label>Эталонное решение (для ИИ-проверки, участник его не видит)</label>
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
                    <span class="ai-badge">${d.kind === 'question' ? 'Вопрос' : 'Практическое задание'} · ${escapeHtml(d.kind === 'question' ? variantNames[d.target] : poolNames[d.target])}</span>
                    <span class="small">${escapeHtml(d.createdAt)}</span>
                </div>
                ${draftHtml(d)}
                <div class="ai-actions">
                    <button class="btn btn-primary" type="button" data-approve="${d.id}">Одобрить и добавить в экзамен</button>
                    <button class="btn btn-secondary" type="button" data-reject="${d.id}">Отклонить</button>
                </div>
                <div class="ai-msg"></div>
            </article>`).join('');
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

    document.getElementById('drafts').addEventListener('click', async event => {
        const approve = event.target.closest('[data-approve]');
        const reject = event.target.closest('[data-reject]');
        if (!approve && !reject) {
            return;
        }
        const article = event.target.closest('.ai-draft');
        const msg = article.querySelector('.ai-msg');
        event.target.disabled = true;
        const json = approve
            ? await postJson({action: 'approve_draft', id: Number(approve.dataset.approve), edited: collectEdits(article)})
            : await postJson({action: 'reject_draft', id: Number(reject.dataset.reject)});
        if (!json.success) {
            event.target.disabled = false;
            msg.innerHTML = '<p style="color:red;">' + escapeHtml(json.message || 'Ошибка') + '</p>';
            return;
        }
        renderDrafts(json.drafts);
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

    async function generate(formId, msgId, action) {
        const form = document.getElementById(formId);
        const button = form.querySelector('button[type=submit]');
        const data = Object.fromEntries(new FormData(form).entries());
        button.disabled = true;
        message(msgId, 'ИИ генерирует, это может занять 1–2 минуты…', true);
        try {
            const json = await postJson(Object.assign({action: action}, data, {count: Number(data.count), sourceId: Number(data.sourceId || 0)}));
            if (!json.success) {
                message(msgId, json.message || 'Ошибка', false);
                return;
            }
            message(msgId, 'Создано черновиков: ' + json.created + '. Проверьте их ниже.', true);
            renderDrafts(json.drafts);
        } catch (e) {
            message(msgId, 'Не удалось связаться с сервером', false);
        } finally {
            button.disabled = false;
        }
    }

    document.getElementById('questionsForm').addEventListener('submit', e => { e.preventDefault(); generate('questionsForm', 'questionsMsg', 'generate_questions'); });
    document.getElementById('practicalForm').addEventListener('submit', e => { e.preventDefault(); generate('practicalForm', 'practicalMsg', 'generate_practical'); });

    (async () => {
        const [sources, drafts] = await Promise.all([
            fetch(api + '?action=sources').then(r => r.json()),
            fetch(api + '?action=drafts').then(r => r.json())
        ]);
        renderSources(sources.sources || []);
        renderDrafts(drafts.drafts || []);
    })();
</script>
</body>
</html>
