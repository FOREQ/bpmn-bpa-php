<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/csrf.php';

$activeNav = 'admin';
$labels = require __DIR__ . '/../lang/content_editor.php';
$ui = $labels[i18nLocale()];
$h = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?= $h(i18nLocale()) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= $h($ui['pageTitle']) ?> | BPMN</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<?php require __DIR__ . '/_header.php'; ?>

<main class="container container-wide content-editor">
    <a class="content-back" href="admin.php">← <?= $h($ui['back']) ?></a>
    <a class="content-back content-preview-link" href="admin_test.php"><?= $h($ui['tryTest']) ?> →</a>
    <div class="admin-eyebrow"><?= $h($ui['eyebrow']) ?></div>
    <h1><?= $h($ui['heading']) ?></h1>
    <p class="content-intro"><?= $h($ui['intro']) ?></p>
    <p class="content-scope-note"><?= $h($ui['assignmentNote']) ?></p>

    <div class="content-tabs" role="tablist" aria-label="<?= $h($ui['heading']) ?>">
        <button type="button" role="tab" id="questionTab" aria-controls="questionPanel" aria-selected="true" class="active" data-editor-tab="question"><?= $h($ui['questionTab']) ?></button>
        <button type="button" role="tab" id="practicalTab" aria-controls="practicalPanel" aria-selected="false" tabindex="-1" data-editor-tab="practical"><?= $h($ui['practicalTab']) ?></button>
    </div>

    <section id="questionPanel" role="tabpanel" aria-labelledby="questionTab" class="content-panel">
        <div class="content-panel-heading">
            <span class="content-step-number">01</span>
            <div><h2><?= $h($ui['newQuestion']) ?></h2><p><?= $h($ui['answerHint']) ?></p></div>
        </div>
        <form id="questionForm" class="content-workspace" enctype="multipart/form-data">
            <input type="hidden" name="kind" value="question">
            <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
            <div class="editor-fields">
                <fieldset class="content-fieldset">
                    <legend><?= $h($ui['placement']) ?></legend>
                    <label for="variant"><?= $h($ui['variant']) ?></label>
                    <select id="variant" name="variant" required>
                        <option value="all"><?= $h($ui['variantAll']) ?></option>
                        <?php foreach (['A', 'B', 'C'] as $variant): ?>
                            <option value="<?= $variant ?>"><?= $h($ui['variantNamed']) ?> <?= $variant ?></option>
                        <?php endforeach; ?>
                    </select>
                </fieldset>

                <fieldset class="content-fieldset">
                    <legend><?= $h($ui['languageContent']) ?></legend>
                    <div class="content-language-grid">
                        <label for="questionRu"><span class="content-language-tag">RU</span> <?= $h($ui['questionRu']) ?>
                            <textarea id="questionRu" name="question" rows="4" maxlength="4000" required></textarea>
                        </label>
                        <label for="questionKk"><span class="content-language-tag">KZ</span> <?= $h($ui['questionKk']) ?>
                            <textarea id="questionKk" name="question_kk" rows="4" maxlength="4000" required></textarea>
                        </label>
                    </div>
                    <label for="questionImage"><?= $h($ui['questionImage']) ?> <small>· <?= $h($ui['optional']) ?> · <?= $h($ui['fileHint']) ?></small>
                        <input id="questionImage" type="file" name="question_image" accept="image/png,image/jpeg,image/webp">
                    </label>
                </fieldset>

                <fieldset class="content-fieldset">
                    <legend><?= $h($ui['answers']) ?></legend>
                    <p class="content-help"><?= $h($ui['answerHint']) ?></p>
                    <?php for ($i = 0; $i < 4; $i++): ?>
                        <div class="content-answer-row">
                            <div class="content-answer-head"><span class="content-answer-index"><?= $i + 1 ?></span>
                                <label class="content-correct-choice"><input type="radio" name="correct" value="<?= $i ?>" <?= $i === 0 ? 'checked' : '' ?>> <?= $h($ui['correct']) ?></label>
                            </div>
                            <div class="content-language-grid">
                                <label for="optionRu<?= $i ?>"><span class="content-language-tag">RU</span> <?= $h($ui['answerRu']) ?>
                                    <input id="optionRu<?= $i ?>" type="text" name="option_<?= $i ?>" maxlength="1200" required>
                                </label>
                                <label for="optionKk<?= $i ?>"><span class="content-language-tag">KZ</span> <?= $h($ui['answerKk']) ?>
                                    <input id="optionKk<?= $i ?>" type="text" name="option_kk_<?= $i ?>" maxlength="1200" required>
                                </label>
                            </div>
                            <label class="content-file-label" for="optionImage<?= $i ?>"><?= $h($ui['answerImage']) ?> <small>· <?= $h($ui['optional']) ?></small>
                                <input id="optionImage<?= $i ?>" type="file" name="option_image_<?= $i ?>" accept="image/png,image/jpeg,image/webp">
                            </label>
                        </div>
                    <?php endfor; ?>
                </fieldset>
                <button class="btn btn-primary content-preview-button" type="submit"><?= $h($ui['previewAction']) ?> →</button>
            </div>

            <aside class="editor-preview">
                <div class="editor-preview-heading">
                    <div><span class="admin-eyebrow"><?= $h($ui['previewTitle']) ?></span><p><?= $h($ui['previewHint']) ?></p></div>
                    <div class="content-language-switch" role="group" aria-label="<?= $h($ui['language']) ?>">
                        <button type="button" data-preview-lang="ru">RU</button>
                        <button type="button" data-preview-lang="kk">KZ</button>
                    </div>
                </div>
                <div id="questionPreview" class="content-preview-body"></div>
                <div class="content-review-actions" hidden>
                    <p><?= $h($ui['reviewQuestion']) ?></p>
                    <div class="content-review-buttons">
                        <button type="button" class="btn btn-secondary" data-edit><?= $h($ui['edit']) ?></button>
                        <button type="button" class="btn btn-primary" data-save><?= $h($ui['saveQuestion']) ?></button>
                    </div>
                </div>
                <p id="questionMessage" class="content-editor-message" role="status" tabindex="-1"></p>
            </aside>
        </form>
    </section>

    <section id="practicalPanel" role="tabpanel" aria-labelledby="practicalTab" class="content-panel" hidden>
        <div class="content-panel-heading">
            <span class="content-step-number">02</span>
            <div><h2><?= $h($ui['newPractical']) ?></h2><p><?= $h($ui['assignmentNote']) ?></p></div>
        </div>
        <form id="practicalForm" class="content-workspace" enctype="multipart/form-data">
            <input type="hidden" name="kind" value="practical">
            <input type="hidden" name="csrf_token" value="<?= $h(csrfToken()) ?>">
            <div class="editor-fields">
                <fieldset class="content-fieldset">
                    <legend><?= $h($ui['placement']) ?></legend>
                    <label for="practicalPool"><?= $h($ui['pool']) ?></label>
                    <select name="pool" id="practicalPool" required>
                        <option value="first"><?= $h($ui['firstPool']) ?></option>
                        <option value="second"><?= $h($ui['secondPool']) ?></option>
                    </select>
                </fieldset>

                <fieldset class="content-fieldset">
                    <legend><?= $h($ui['languageContent']) ?></legend>
                    <div class="content-language-grid">
                        <label for="titleRu"><span class="content-language-tag">RU</span> <?= $h($ui['titleRu']) ?> <small>· <?= $h($ui['optional']) ?></small>
                            <input id="titleRu" type="text" name="title" maxlength="240">
                        </label>
                        <label for="titleKk"><span class="content-language-tag">KZ</span> <?= $h($ui['titleKk']) ?> <small>· <?= $h($ui['optional']) ?></small>
                            <input id="titleKk" type="text" name="title_kk" maxlength="240">
                        </label>
                        <label for="descriptionRu"><span class="content-language-tag">RU</span> <?= $h($ui['descriptionRu']) ?>
                            <textarea id="descriptionRu" name="description" rows="9" maxlength="16000" minlength="20" required></textarea>
                        </label>
                        <label for="descriptionKk"><span class="content-language-tag">KZ</span> <?= $h($ui['descriptionKk']) ?>
                            <textarea id="descriptionKk" name="description_kk" rows="9" maxlength="16000" minlength="20" required></textarea>
                        </label>
                    </div>
                </fieldset>

                <fieldset id="referenceFieldset" class="content-fieldset" hidden>
                    <legend><?= $h($ui['files']) ?></legend>
                    <label for="referenceBpmn"><?= $h($ui['reference']) ?> <small>· <?= $h($ui['optional']) ?></small>
                        <input type="file" name="reference_bpmn" id="referenceBpmn" accept=".bpmn,.xml,application/xml,text/xml">
                    </label>
                    <p class="content-help"><?= $h($ui['referenceHint']) ?></p>
                </fieldset>
                <button class="btn btn-primary content-preview-button" type="submit"><?= $h($ui['previewAction']) ?> →</button>
            </div>

            <aside class="editor-preview">
                <div class="editor-preview-heading">
                    <div><span class="admin-eyebrow"><?= $h($ui['previewTitle']) ?></span><p><?= $h($ui['previewHint']) ?></p></div>
                    <div class="content-language-switch" role="group" aria-label="<?= $h($ui['language']) ?>">
                        <button type="button" data-preview-lang="ru">RU</button>
                        <button type="button" data-preview-lang="kk">KZ</button>
                    </div>
                </div>
                <div id="practicalPreview" class="content-preview-body"></div>
                <div class="content-review-actions" hidden>
                    <p><?= $h($ui['reviewPractical']) ?></p>
                    <div class="content-review-buttons">
                        <button type="button" class="btn btn-secondary" data-edit><?= $h($ui['edit']) ?></button>
                        <button type="button" class="btn btn-primary" data-save><?= $h($ui['savePractical']) ?></button>
                    </div>
                </div>
                <p id="practicalMessage" class="content-editor-message" role="status" tabindex="-1"></p>
            </aside>
        </form>
    </section>

    <section class="content-library" aria-labelledby="libraryHeading">
        <div class="content-library-heading"><div><div class="admin-eyebrow"><?= $h($ui['eyebrow']) ?></div><h2 id="libraryHeading"><?= $h($ui['library']) ?></h2><p><?= $h($ui['libraryHint']) ?></p></div>
            <div id="libraryCounts" class="content-counts"></div>
        </div>
        <div class="content-library-tools">
            <label for="contentSearch"><?= $h($ui['search']) ?><input id="contentSearch" type="search"></label>
            <div class="content-filters" role="group" aria-label="<?= $h($ui['library']) ?>">
                <button type="button" class="active" data-filter="all" aria-pressed="true"><?= $h($ui['filterAll']) ?></button>
                <?php foreach (['A', 'B', 'C'] as $variant): ?>
                    <button type="button" data-filter="<?= $variant ?>" aria-label="<?= $h($ui['variantNamed'] . ' ' . $variant) ?>" aria-pressed="false"><?= $variant ?></button>
                <?php endforeach; ?>
                <button type="button" data-filter="practical" aria-pressed="false"><?= $h($ui['filterPractical']) ?></button>
            </div>
        </div>
        <div id="contentList" class="content-library-list" aria-live="polite"><div class="admin-empty"><?= $h($ui['loading']) ?></div></div>
    </section>
</main>

<script>
    const ui = <?= json_encode($ui, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const pageLocale = <?= json_encode(i18nLocale()) ?>;
    const endpoint = '../api/admin/content.php';
    const forms = { question: document.getElementById('questionForm'), practical: document.getElementById('practicalForm') };
    let previewLocale = pageLocale;
    let libraryData = { questions: [], tasks: [] };
    let libraryFilter = 'all';
    const fileUrls = new Map();

    function escapeHtml(value) {
        return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }
    function field(form, name) { return form.elements.namedItem(name); }
    function value(form, name) { return field(form, name)?.value.trim() || ''; }
    function fileUrl(input) {
        const file = input?.files?.[0];
        const cached = fileUrls.get(input);
        if (cached && cached.file === file) return cached.url;
        if (cached?.url) URL.revokeObjectURL(cached.url);
        const url = file ? URL.createObjectURL(file) : null;
        fileUrls.set(input, { file, url });
        return url;
    }
    function imageMarkup(input) {
        const url = fileUrl(input);
        return url ? `<img class="content-preview-image" src="${url}" alt="">` : '';
    }
    function setTab(kind) {
        for (const name of ['question', 'practical']) {
            const active = name === kind;
            const button = document.getElementById(name + 'Tab');
            document.getElementById(name + 'Panel').hidden = !active;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', String(active));
            button.tabIndex = active ? 0 : -1;
        }
    }
    function setPreviewLocale(locale) {
        previewLocale = locale;
        document.querySelectorAll('[data-preview-lang]').forEach(button => {
            const active = button.dataset.previewLang === locale;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', String(active));
        });
        renderPreviews();
    }
    function updateReferenceInput() {
        const second = value(forms.practical, 'pool') === 'second';
        const input = field(forms.practical, 'reference_bpmn');
        input.disabled = !second;
        if (!second) input.value = '';
        document.getElementById('referenceFieldset').hidden = !second;
    }
    function validateTitlePair() {
        const russian = field(forms.practical, 'title');
        const kazakh = field(forms.practical, 'title_kk');
        russian.setCustomValidity(!russian.value.trim() && kazakh.value.trim() ? ui.titlePairError : '');
        kazakh.setCustomValidity(!kazakh.value.trim() && russian.value.trim() ? ui.titlePairError : '');
    }
    function renderQuestionPreview() {
        const form = forms.question;
        const suffix = previewLocale === 'kk' ? '_kk' : '';
        const prompt = value(form, 'question' + suffix);
        const correct = Number(field(form, 'correct')?.value || 0);
        const options = Array.from({ length: 4 }, (_, index) => {
            const answer = value(form, 'option' + suffix + '_' + index);
            return `<li class="${index === correct ? 'is-correct' : ''}"><span>${String.fromCharCode(65 + index)}</span><div>${escapeHtml(answer || ui.notFilled)}${imageMarkup(field(form, 'option_image_' + index))}</div></li>`;
        }).join('');
        document.getElementById('questionPreview').innerHTML = `
            <div class="content-preview-meta">${escapeHtml(value(form, 'variant') === 'all' ? ui.variantAll : ui.variantNamed + ' ' + value(form, 'variant'))}</div>
            <h3>${escapeHtml(prompt || ui.emptyQuestion)}</h3>
            ${imageMarkup(field(form, 'question_image'))}
            <ol class="content-preview-options">${options}</ol>`;
    }
    function renderPracticalPreview() {
        const form = forms.practical;
        const suffix = previewLocale === 'kk' ? '_kk' : '';
        const title = value(form, 'title' + suffix);
        const description = value(form, 'description' + suffix);
        const reference = field(form, 'reference_bpmn')?.files?.[0]?.name;
        document.getElementById('practicalPreview').innerHTML = `
            <div class="content-preview-meta">${escapeHtml(value(form, 'pool') === 'first' ? ui.firstPool : ui.secondPool)}</div>
            <h3>${escapeHtml(title || ui.newPractical)}</h3>
            <p class="content-preview-description">${escapeHtml(description || ui.emptyPractical)}</p>
            ${reference ? `<p class="content-preview-file">${escapeHtml(ui.reference)}: ${escapeHtml(reference)}</p>` : ''}`;
    }
    function renderPreviews() { renderQuestionPreview(); renderPracticalPreview(); }
    function setReview(form, reviewing) {
        form.classList.toggle('is-reviewing', reviewing);
        form.querySelector('.content-review-actions').hidden = !reviewing;
        if (reviewing) form.querySelector('.editor-preview').scrollIntoView({ block: 'start' });
    }
    async function saveContent(form) {
        const button = form.querySelector('[data-save]');
        const message = form.querySelector('.content-editor-message');
        button.disabled = true;
        message.textContent = ui.saving;
        message.className = 'content-editor-message';
        try {
            const response = await fetch(endpoint, { method: 'POST', body: new FormData(form) });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || ui.saveError);
            message.textContent = data.message || ui.saved;
            message.classList.add('success');
            form.reset();
            setReview(form, false);
            updateReferenceInput();
            renderPreviews();
            await loadContent();
        } catch (error) {
            message.textContent = error.message || ui.saveError;
            message.classList.add('error');
            setReview(form, false);
            message.focus();
        } finally { button.disabled = false; }
    }
    function renderLibrary() {
        const count = document.getElementById('libraryCounts');
        const search = document.getElementById('contentSearch').value.trim().toLocaleLowerCase();
        const items = [
            ...libraryData.questions.map(row => ({ kind: 'question', row })),
            ...libraryData.tasks.map(row => ({ kind: 'practical', row }))
        ].filter(item => {
            if (libraryFilter === 'practical' && item.kind !== 'practical') return false;
            if (['A', 'B', 'C'].includes(libraryFilter)
                && ![libraryFilter, 'all'].includes(item.row.variant)) return false;
            return !search || JSON.stringify(item.row).toLocaleLowerCase().includes(search);
        });
        const questionCount = items.filter(item => item.kind === 'question').length;
        const taskCount = items.length - questionCount;
        count.innerHTML = `<span>${escapeHtml(ui.questionsCount)} <b>${questionCount}</b></span><span>${escapeHtml(ui.tasksCount)} <b>${taskCount}</b></span>`;
        const list = document.getElementById('contentList');
        if (!items.length) {
            list.innerHTML = `<div class="admin-empty">${escapeHtml(search ? ui.emptySearch : ui.emptyLibrary)}</div>`;
            return;
        }
        list.innerHTML = items.map(({ kind, row }) => {
            if (kind === 'question') {
                const text = pageLocale === 'kk' ? (row.question_text_kk || row.question_text) : row.question_text;
                const options = pageLocale === 'kk' ? row.options_kk : row.options;
                const correct = options[Number(row.correct_index)];
                const correctText = typeof correct === 'string' ? correct : correct?.text;
                const questionImage = row.image_path
                    ? `<img class="content-library-image" src="${escapeHtml(row.image_path)}" alt="${escapeHtml(ui.questionImage)}" loading="lazy">` : '';
                const answerList = options.map((option, index) => {
                    const answerText = typeof option === 'string' ? option : option?.text;
                    const answerImage = typeof option === 'object' && option?.image
                        ? `<img class="content-library-image" src="${escapeHtml(option.image)}" alt="${escapeHtml(ui.answerImage)} ${index + 1}" loading="lazy">` : '';
                    return `<li${index === Number(row.correct_index) ? ' class="is-correct"' : ''}>${escapeHtml(answerText || '—')}${answerImage}</li>`;
                }).join('');
                const builtIn = row.source === 'builtin';
                const identifier = builtIn ? row.id : 100000 + Number(row.id);
                const origin = builtIn ? ui.originalQuestion : (row.question_text_kk ? 'RU / KZ' : 'RU');
                return `<article class="content-library-card"><span class="content-library-type">${escapeHtml(ui.questionTab)} · ${escapeHtml(row.variant === 'all' ? ui.variantAll : ui.variantNamed + ' ' + row.variant)}</span><h3>${escapeHtml(text)}</h3>${questionImage}<p>${escapeHtml(ui.correctAnswer)}: <strong>${escapeHtml(correctText || '—')}</strong></p><details class="content-library-details"><summary>${escapeHtml(ui.showAnswers)}</summary><ol>${answerList}</ol></details><span class="content-library-id">#${escapeHtml(identifier)} · ${escapeHtml(origin)}</span></article>`;
            }
            const title = pageLocale === 'kk' ? (row.title_kk || row.title) : row.title;
            const description = pageLocale === 'kk' ? (row.description_kk || row.description) : row.description;
            const poolLabel = row.pool === 'first' ? ui.firstPool : row.pool === 'second' ? ui.secondPool : ui.complexityPool;
            const origin = row.source === 'builtin' ? ui.originalTask : (row.description_kk ? 'RU / KZ' : 'RU');
            const variant = row.variant ? ` · ${escapeHtml(ui.variantNamed + ' ' + row.variant)}` : '';
            const diagram = row.image_path
                ? `<a href="${escapeHtml(row.image_path)}" target="_blank" rel="noopener noreferrer"><img class="content-library-diagram" src="${escapeHtml(row.image_path)}" alt="${escapeHtml(ui.complexityDiagram)}" loading="lazy"></a>` : '';
            const fields = Array.isArray(row.fields)
                ? `<p class="content-library-field-label">${escapeHtml(ui.complexityFields)}</p><ul class="content-library-fields">${row.fields.map(field => `<li>${escapeHtml(field.label)} (${escapeHtml(field.weight)})</li>`).join('')}</ul>` : '';
            const reference = row.reference_file
                ? `<p class="content-reference-badge"><a href="../assets/reference_bpmn/${encodeURIComponent(row.reference_file)}" download>${escapeHtml(ui.referenceDownload)}</a></p>` : '';
            return `<article class="content-library-card"><span class="content-library-type">${escapeHtml(poolLabel)}${variant}</span><h3>${escapeHtml(title || ui.noTitle)}</h3><p>${escapeHtml(description)}</p>${diagram}${fields}${reference}<span class="content-library-id">#${escapeHtml(row.id)} · ${escapeHtml(origin)}</span></article>`;
        }).join('');
    }
    async function loadContent() {
        try {
            const response = await fetch(endpoint);
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || ui.loadError);
            libraryData = data;
            renderLibrary();
        } catch (error) {
            document.getElementById('contentList').innerHTML = `<div class="admin-empty">${escapeHtml(error.message || ui.loadError)}</div>`;
        }
    }

    document.querySelectorAll('[data-editor-tab]').forEach(button => {
        button.addEventListener('click', () => setTab(button.dataset.editorTab));
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 'question' : event.key === 'End' ? 'practical'
                : button.dataset.editorTab === 'question' ? 'practical' : 'question';
            setTab(next);
            document.getElementById(next + 'Tab').focus();
        });
    });
    document.querySelectorAll('[data-preview-lang]').forEach(button => button.addEventListener('click', () => setPreviewLocale(button.dataset.previewLang)));
    for (const form of Object.values(forms)) {
        form.addEventListener('input', renderPreviews);
        form.addEventListener('change', renderPreviews);
        form.addEventListener('submit', event => { event.preventDefault(); setReview(form, true); });
        form.querySelector('[data-edit]').addEventListener('click', () => setReview(form, false));
        form.querySelector('[data-save]').addEventListener('click', () => saveContent(form));
    }
    forms.practical.addEventListener('input', validateTitlePair);
    document.getElementById('practicalPool').addEventListener('change', () => { updateReferenceInput(); renderPreviews(); });
    document.getElementById('contentSearch').addEventListener('input', renderLibrary);
    document.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
        libraryFilter = button.dataset.filter;
        document.querySelectorAll('[data-filter]').forEach(item => {
            const active = item === button;
            item.classList.toggle('active', active);
            item.setAttribute('aria-pressed', String(active));
        });
        renderLibrary();
    }));
    updateReferenceInput();
    validateTitlePair();
    setPreviewLocale(previewLocale);
    loadContent();
</script>
</body>
</html>
