<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/csrf.php';

$courseTitle = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель | Реинжиниринг бизнес-процессов</title>

    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/diagram-js.css">
    <link rel="stylesheet" href="https://unpkg.com/bpmn-js@17.9.1/dist/assets/bpmn-font/css/bpmn.css">
    <link rel="stylesheet" href="style.css">

    <style>
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: auto;
        }

        .admin-table th,
        .admin-table td {
            padding: 18px 20px !important;
            vertical-align: top !important;
            height: auto !important;
            min-height: 0 !important;
        }

        .admin-table thead tr,
        .admin-table tbody tr {
            height: auto !important;
            min-height: 0 !important;
        }

        .admin-table-wrap {
            overflow-x: auto;
        }

        .admin-action-btn {
            min-width: 150px;
            padding: 12px 16px;
            border-radius: 10px;
            border: none;
            background: #173d5c;
            color: #ffffff;
            font-weight: 700;
            cursor: pointer;
            font-size: 15px;
        }

        .admin-action-btn:hover {
            opacity: 0.9;
        }

        .admin-action-btn.danger {
            background: #991b1b;
        }

        .small {
            font-size: 14px;
            color: #48637a;
        }

        .email-cell,
        .organization-cell {
            word-break: break-word;
        }

        .course-title-box {
            margin-top: 10px;
            color: #48637a;
            font-size: 15px;
            line-height: 1.5;
            max-width: 760px;
        }
        .admin-participants-list {
    display: grid;
    gap: 16px;
    margin-top: 24px;
}

.admin-participant-card {
    display: grid;
    grid-template-columns: 1.4fr 1fr 1fr 1fr 1fr 1fr 180px;
    gap: 18px;
    align-items: start;

    padding: 20px;
    border: 1px solid #d8e4ea;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 6px 18px rgba(24, 59, 89, 0.06);
}

.admin-participant-col {
    min-width: 0;
}

.admin-participant-label {
    margin-bottom: 8px;
    color: #64748b;
    font-size: 12px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.admin-participant-value {
    color: #183b59;
    font-size: 15px;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.admin-empty {
    padding: 24px;
    border: 1px solid #d8e4ea;
    border-radius: 14px;
    background: #ffffff;
    color: #64748b;
    font-weight: 700;
}

.admin-participant-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.admin-participant-actions .admin-action-btn {
    width: 100% !important;
    min-width: 0 !important;
}

@media (max-width: 1200px) {
    .admin-participant-card {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .admin-participant-card {
        grid-template-columns: 1fr;
    }
}
    </style>
</head>
<body>
<header class="site-header">
    <div class="site-header-inner">
        <a href="index.php" class="site-brand">
            <img src="../assets/logo.svg/logo-transparent.png" alt="DGSC" class="site-logo">

            <span class="site-brand-text">
                <span class="site-brand-title">Центр Поддержки</span>
                <span class="site-brand-subtitle">Цифрового Правительства</span>
            </span>
        </a>

        <nav class="site-nav">
            <a href="index.php">Главная</a>
            <a href="register.php">Регистрация</a>
            <a href="student_login.php">Войти</a>
            <a href="admin_login.php" class="active">Админ</a>
        </nav>
    </div>
</header>

<div class="container">
    <div class="admin-card">
        <div class="admin-header">
            <div>
                <div class="admin-label">Администрирование</div>
                <h1 class="admin-title">Результаты участников</h1>

                <div class="course-title-box">
                    Курс: «<?= htmlspecialchars($courseTitle) ?>»
                </div>
            </div>

            <div class="admin-actions">
                <a class="admin-btn primary" href="../api/admin/export.php">Скачать CSV</a>
                <button class="admin-btn secondary" id="refreshBtn" type="button">Обновить список</button>
                <a class="admin-btn secondary" href="logout.php">Выйти из админки</a>
            </div>
        </div>

       <div id="participantsBody" class="admin-participants-list">
    <div class="admin-empty">Загрузка...</div>
</div>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <div class="admin-modal-top">
            <div>
                <div class="admin-label">Практическая работа</div>
                <h2 class="admin-modal-title">Проверка практического задания</h2>
                <p class="small">
                    Курс: «<?= htmlspecialchars($courseTitle) ?>»
                </p>
            </div>

            <button id="closeModal" class="admin-modal-close" type="button">Закрыть</button>
        </div>

        <div id="practicalContent">Загрузка...</div>
    </div>
</div>

<script src="https://unpkg.com/bpmn-js@17.9.1/dist/bpmn-viewer.development.js"></script>

<script>
    const participantsBody = document.getElementById('participantsBody');
    const modal = document.getElementById('modal');
    const practicalContent = document.getElementById('practicalContent');
    const csrfToken = '<?= htmlspecialchars(csrfToken()) ?>';

    let currentViewers = [];

    function escapeHtml(text) {
        return String(text ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function testStatus(status) {
        if (status === 'passed') return '<span class="status passed">Пройден</span>';
        if (status === 'failed') return '<span class="status failed">Не пройден</span>';
        return '<span class="status waiting">Не сдан</span>';
    }

    function testStatusText(status) {
        if (status === 'passed') return 'Пройден';
        if (status === 'failed') return 'Не пройден';
        return 'Не сдан';
    }

    function accountStatus(row) {
        const status = row.accountStatus || 'pending';

        if (status === 'approved') {
            return `
                <span class="status passed">Подтверждена</span><br>
                <span class="small">Доступ до:</span><br>
                <span class="small">${row.tempPasswordExpiresAt ? escapeHtml(row.tempPasswordExpiresAt) : '—'}</span>
            `;
        }

        if (status === 'rejected') {
            return `
                <span class="status failed">Отклонена</span><br>
                <span class="small">${row.rejectionReason ? escapeHtml(row.rejectionReason) : 'Без причины'}</span>
            `;
        }

        return '<span class="status waiting">Ожидает подтверждения</span>';
    }

    function practicalStatus(row) {
        if (!row.practicalSubmittedAt) {
            return '<span class="status waiting">Не отправлена</span>';
        }

        if (!row.practicalGradedAt) {
            return '<span class="status waiting">Ожидает проверки</span>';
        }

        return '<span class="status passed">Проверена: ' + escapeHtml(row.practicalScoreTotal) + ' / 30</span>';
    }

    function overallGrade(percent, practicalScore) {
        if (percent === null && practicalScore === null) {
            return '<span class="small">—</span>';
        }

        const testPoints = Math.round(((Number(percent) || 0) / 100) * 20);
        const practicalPoints = Number(practicalScore) || 0;
        const total = testPoints + practicalPoints;

        let label = 'Прослушал курс';
        let className = 'waiting';
        let letter = '';

        if (total >= 46) {
            label = 'Сертификат A';
            className = 'passed';
            letter = 'A';
        } else if (total >= 38) {
            label = 'Сертификат B';
            className = 'passed';
            letter = 'B';
        } else if (total >= 26) {
            label = 'Сертификат C';
            className = 'waiting';
            letter = 'C';
        }

        return `
            <span class="status ${className}">
                ${letter ? letter + ' — ' : ''}${label}
            </span><br>
            <span class="small">${total} / 50</span>
        `;
    }

    function actionButtons(row) {
        const status = row.accountStatus || 'pending';
        const participantId = escapeHtml(row.id || '');
        const sessionId = escapeHtml(row.sessionId || '');

        if (status === 'pending') {
            return `
                <button class="admin-action-btn" onclick="approveStudent('${participantId}')" type="button">
                    Подтвердить заявку
                </button>

                <br><br>

                <button class="admin-action-btn danger" onclick="rejectStudent('${participantId}')" type="button">
                    Отклонить заявку
                </button>
            `;
        }

        if (status === 'rejected') {
            return '<span class="small">Заявка отклонена</span>';
        }

        return `
            <button class="admin-action-btn" onclick="openPractical('${sessionId}')" type="button">
                Проверить практику
            </button>
        `;
    }

    async function loadParticipants() {
        try {
            const response = await fetch('../api/admin/results.php');
            const json = await response.json();

           if (!json.success) {
    participantsBody.innerHTML = `
        <div class="admin-empty">
            Ошибка загрузки: ${escapeHtml(json.message || '')}
        </div>
    `;
    return;
}

if (json.participants.length === 0) {
    participantsBody.innerHTML = `
        <div class="admin-empty">
            Участников пока нет
        </div>
    `;
    return;
}

participantsBody.innerHTML = '';

json.participants.forEach(row => {
    const card = document.createElement('div');
    card.className = 'admin-participant-card';

    card.innerHTML = `
        <div class="admin-participant-col">
            <div class="admin-participant-label">Участник</div>
            <div class="admin-participant-value">
                <b>${escapeHtml(row.fullName)}</b><br>
                <span class="small">${escapeHtml(row.email)}</span><br>
                <span class="small">${escapeHtml(row.phone)}</span>
            </div>
        </div>

        <div class="admin-participant-col">
            <div class="admin-participant-label">Организация</div>
            <div class="admin-participant-value">
                ${escapeHtml(row.organization)}
            </div>
        </div>

        <div class="admin-participant-col">
            <div class="admin-participant-label">Статус заявки</div>
            <div class="admin-participant-value">
                ${accountStatus(row)}
            </div>
        </div>

        <div class="admin-participant-col">
            <div class="admin-participant-label">Тест</div>
            <div class="admin-participant-value">
                Вариант: <b>${escapeHtml(row.variantId)}</b><br>
                Баллы: ${row.score ?? '-'} / ${row.total ?? '-'}<br>
                Процент: ${row.percent ?? '-'}%<br>
                ${testStatus(row.status)}
            </div>
        </div>

        <div class="admin-participant-col">
            <div class="admin-participant-label">Практика</div>
            <div class="admin-participant-value">
                ${practicalStatus(row)}
            </div>
        </div>

        <div class="admin-participant-col">
            <div class="admin-participant-label">Итог</div>
            <div class="admin-participant-value">
                ${overallGrade(row.percent, row.practicalScoreTotal)}
            </div>
        </div>

        <div class="admin-participant-col">
            <div class="admin-participant-label">Действие</div>
            <div class="admin-participant-actions">
                ${actionButtons(row)}
            </div>

            <div class="admin-participant-label" style="margin-top: 14px;">Дата</div>
            <div class="admin-participant-value">
                Регистрация:<br>
                <span class="small">${escapeHtml(row.createdAt)}</span><br>
                Обновлено:<br>
                <span class="small">${escapeHtml(row.updatedAt)}</span>
            </div>
        </div>
    `;

    participantsBody.appendChild(card);
});
        } catch (e) {
            console.error(e);
            participantsBody.innerHTML = '<tr><td colspan="8">Ошибка соединения с сервером</td></tr>';
        }
    }

    async function approveStudent(participantId) {
        if (!participantId) {
            alert('ID участника не найден');
            return;
        }

        if (!confirm('Подтвердить заявку и отправить временный пароль на почту?')) {
            return;
        }

        const formData = new FormData();
        formData.append('participant_id', participantId);

        try {
            const response = await fetch('../api/admin_approve_password_reset.php', {
                method: 'POST',
                body: formData
            });

            const json = await response.json();

            if (!json.success) {
                alert(json.message || 'Ошибка подтверждения заявки');
                return;
            }

            alert(json.message || 'Заявка подтверждена. Временный пароль отправлен на почту.');
            await loadParticipants();

        } catch (e) {
            console.error(e);
            alert('Ошибка соединения с сервером');
        }
    }

    async function rejectStudent(participantId) {
        if (!participantId) {
            alert('ID участника не найден');
            return;
        }

        const reason = prompt('Введите причину отклонения заявки:', 'Заявка отклонена администратором');

        if (reason === null) {
            return;
        }

        const formData = new FormData();
        formData.append('participant_id', participantId);
        formData.append('reason', reason);

        try {
            const response = await fetch('../api/admin_reject_student.php', {
                method: 'POST',
                body: formData
            });

            const json = await response.json();

            if (!json.success) {
                alert(json.message || 'Ошибка отклонения заявки');
                return;
            }

            alert(json.message || 'Заявка отклонена. Уведомление отправлено на почту.');
            await loadParticipants();

        } catch (e) {
            console.error(e);
            alert('Ошибка соединения с сервером');
        }
    }

    async function openPractical(sessionId) {
        modal.style.display = 'block';
        practicalContent.innerHTML = 'Загрузка...';

        currentViewers.forEach(viewer => {
            try {
                viewer.destroy();
            } catch (e) {}
        });

        currentViewers = [];

        const response = await fetch('../api/admin/practical.php?sessionId=' + encodeURIComponent(sessionId));
        const json = await response.json();

        if (!json.success) {
            practicalContent.innerHTML = 'Ошибка загрузки практики';
            return;
        }

        const p = json.participant;
        const practical = json.practical;

        let tasksHtml = '';

        practical.tasks.forEach((task, index) => {
            const answer = practical.answers?.diagrams?.[task.id]?.xml || 'Ответ не найден';

            const viewerId = 'viewer_' + task.id;
            const xmlId = 'xml_' + task.id;
            const referenceViewerId = 'reference_' + task.id;
            const hasReference = task.referenceBpmn && index === 1;

            tasksHtml += `
                <section class="admin-review-task">
                    <h3>Задача ${index + 1}</h3>
                    <p>${escapeHtml(task.description)}</p>

                    <b>BPMN-схема участника:</b>
                    <div id="${viewerId}" class="bpmn-viewer-box"></div>

                    ${
                        hasReference
                            ? `
                                <h3 class="reference-title">Эталонная BPMN-схема</h3>
                                <div id="${referenceViewerId}" class="bpmn-viewer-box"></div>
                            `
                            : ``
                    }

                    <button class="xml-toggle" onclick="toggleXml('${xmlId}')" type="button">
                        Показать / скрыть BPMN XML участника
                    </button>

                    <pre id="${xmlId}">${escapeHtml(answer)}</pre>
                </section>
            `;
        });

        let complexityHtml = '';

        if (practical.complexityVariant) {
            if (practical.complexityVariant.image) {
                complexityHtml += `
                    <div class="admin-complexity-image">
                        <p><b>Диаграмма для расчета сложности:</b></p>
                        <img
                            src="../assets/complexity/${practical.complexityVariant.image}"
                            alt="Диаграмма сложности"
                        >
                    </div>
                `;
            }

            if (practical.complexityVariant.fields) {
                practical.complexityVariant.fields.forEach(field => {
                    const value = practical.answers?.complexity?.[field.id] ?? '—';

                    complexityHtml += `
                        <p>
                            <b>${field.label} (${field.weight}):</b>
                            ${escapeHtml(String(value))}
                        </p>
                    `;
                });
            }
        }

        practicalContent.innerHTML = `
            <div class="admin-info-box">
                <p><b>Участник:</b><br>${escapeHtml(p.fullName)}</p>
                <p><b>Организация:</b><br>${escapeHtml(p.organization)}</p>
                <p><b>Курс:</b><br>Практическое применение методики реинжиниринга бизнес-процессов государственных органов</p>
                <p><b>Тест:</b><br>${p.testScore} / ${p.testTotal}, ${p.testPercent}% (${testStatusText(p.testStatus)})</p>
            </div>

            ${tasksHtml}

            <section class="admin-review-task">
                <h3>Расчет сложности</h3>
                ${complexityHtml}
            </section>

            <section class="admin-review-task">
                <h3>Оценка практики</h3>

                <div class="score-row">
                    <label for="previousTaskScore">Первое задание, максимум 10:</label>
                    <input id="previousTaskScore" type="number" min="0" max="10" value="${practical.scores.previousTaskScore ?? 0}">
                </div>

                <div class="score-row">
                    <label for="newTaskScore">Второе задание, максимум 15:</label>
                    <input id="newTaskScore" type="number" min="0" max="15" value="${practical.scores.newTaskScore ?? 0}">
                </div>

                <div class="score-row">
                    <label for="metricsScore">Расчет сложности, максимум 5:</label>
                    <input id="metricsScore" type="number" min="0" max="5" value="${practical.scores.metricsScore ?? 0}">
                </div>

                <button class="admin-action-btn" onclick="saveScores('${p.sessionId}')" type="button">
                    Сохранить оценку
                </button>

                <div id="scoreMessage"></div>
            </section>

            <section class="admin-review-task" id="certificateSection" style="display:none;">
                <h3>Сертификат</h3>

                <div id="certificateInfo"></div>

                <a
                    id="certificateDownload"
                    class="admin-action-btn"
                    href="#"
                    style="display:inline-block; text-decoration:none; margin-right:8px;"
                >
                    Скачать сертификат
                </a>

                <button
                    class="admin-action-btn"
                    onclick="resendCertificate('${p.sessionId}')"
                    type="button"
                >
                    Отправить на email
                </button>

                <div id="certificateMessage"></div>
            </section>
        `;

        renderCertificateSection(p.sessionId, json.certificate);

        practical.tasks.forEach((task, index) => {
            const answer = practical.answers?.diagrams?.[task.id]?.xml || '';

            if (answer.trim() !== '') {
                renderBpmnViewer('viewer_' + task.id, answer);
            } else {
                const viewerBox = document.getElementById('viewer_' + task.id);

                if (viewerBox) {
                    viewerBox.innerHTML = '<p style="padding:16px;">Ответ не найден</p>';
                }
            }

            const hasReference = task.referenceBpmn && index === 1;

            if (hasReference) {
                renderReferenceBpmn(
                    'reference_' + task.id,
                    '../assets/reference_bpmn/' + task.referenceBpmn
                );
            }
        });
    }

    async function renderBpmnViewer(containerId, xml) {
        const viewer = new BpmnJS({
            container: '#' + containerId
        });

        currentViewers.push(viewer);

        try {
            await viewer.importXML(xml);
            const canvas = viewer.get('canvas');
            canvas.zoom('fit-viewport');
        } catch (error) {
            console.error(error);

            const box = document.getElementById(containerId);
            if (box) {
                box.innerHTML = '<p style="padding:16px;color:#991b1b;">Не удалось отобразить BPMN-схему. XML можно посмотреть ниже.</p>';
            }
        }
    }

    async function renderReferenceBpmn(containerId, filePath) {
        const viewer = new BpmnJS({
            container: '#' + containerId
        });

        currentViewers.push(viewer);

        try {
            const response = await fetch(filePath);

            if (!response.ok) {
                throw new Error('Файл эталонной BPMN-схемы не найден');
            }

            const xml = await response.text();

            await viewer.importXML(xml);

            const canvas = viewer.get('canvas');
            canvas.zoom('fit-viewport');
        } catch (error) {
            console.error(error);

            const box = document.getElementById(containerId);
            if (box) {
                box.innerHTML = '<p style="padding:16px;color:#991b1b;">Не удалось загрузить эталонную BPMN-схему.</p>';
            }
        }
    }

    function toggleXml(id) {
        const block = document.getElementById(id);

        if (!block) {
            return;
        }

        block.style.display = block.style.display === 'block' ? 'none' : 'block';
    }

    async function saveScores(sessionId) {
        const data = {
            csrf_token: csrfToken,
            sessionId: sessionId,
            previousTaskScore: Number(document.getElementById('previousTaskScore').value),
            newTaskScore: Number(document.getElementById('newTaskScore').value),
            metricsScore: Number(document.getElementById('metricsScore').value)
        };

        const response = await fetch('../api/admin/practical.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });

        const json = await response.json();
        const scoreMessage = document.getElementById('scoreMessage');

        if (!json.success) {
            scoreMessage.innerHTML = '<p style="color:red;">' + escapeHtml(json.message || 'Ошибка сохранения') + '</p>';
            return;
        }

        let message = '<p style="color:green;">Оценка сохранена. Итог: ' + json.scores.total + ' / 30</p>';

        if (json.certificate) {
            if (json.certificate.error) {
                message += '<p style="color:red;">' + escapeHtml(json.certificate.error) + '</p>';
            } else {
                message += '<p style="color:green;">Сертификат ' + escapeHtml(json.certificate.number)
                    + (json.certificate.emailSentNow
                        ? ' сформирован и отправлен участнику на email.'
                        : ' сформирован.' + (json.certificate.emailed ? '' : ' Письмо не отправлено.'))
                    + '</p>';

                renderCertificateSection(sessionId, {
                    number: json.certificate.number,
                    generatedAt: 'только что',
                    emailedAt: json.certificate.emailSentNow ? 'только что' : null
                });
            }
        }

        scoreMessage.innerHTML = message;
        await loadParticipants();
    }

    function renderCertificateSection(sessionId, certificate) {
        const section = document.getElementById('certificateSection');
        const info = document.getElementById('certificateInfo');
        const download = document.getElementById('certificateDownload');

        if (!section || !certificate || !certificate.number) {
            return;
        }

        section.style.display = 'block';

        info.innerHTML = `
            <p><b>Номер:</b> ${escapeHtml(certificate.number)}</p>
            <p><b>Сформирован:</b> ${escapeHtml(certificate.generatedAt || '—')}</p>
            <p><b>Отправлен на email:</b> ${escapeHtml(certificate.emailedAt || 'нет')}</p>
        `;

        download.href = '../api/certificate.php?sessionId=' + encodeURIComponent(sessionId);
    }

    async function resendCertificate(sessionId) {
        const certificateMessage = document.getElementById('certificateMessage');
        certificateMessage.innerHTML = '<p>Отправка...</p>';

        const response = await fetch('../api/admin/send_certificate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                csrf_token: csrfToken,
                sessionId: sessionId
            })
        });

        const json = await response.json();

        if (!json.success) {
            certificateMessage.innerHTML = '<p style="color:red;">' + escapeHtml(json.message || 'Ошибка отправки') + '</p>';
            return;
        }

        certificateMessage.innerHTML = '<p style="color:green;">' + escapeHtml(json.message) + '</p>';

        renderCertificateSection(sessionId, {
            number: json.certificate.number,
            generatedAt: 'только что',
            emailedAt: 'только что'
        });
    }

    document.getElementById('refreshBtn').addEventListener('click', loadParticipants);

    document.getElementById('closeModal').addEventListener('click', () => {
        modal.style.display = 'none';

        currentViewers.forEach(viewer => {
            try {
                viewer.destroy();
            } catch (e) {}
        });

        currentViewers = [];
    });

    loadParticipants();

    let adminIdleTimer;

    function resetAdminIdleTimer() {
        clearTimeout(adminIdleTimer);

        adminIdleTimer = setTimeout(() => {
            window.location.href = 'logout.php';
        }, 20 * 60 * 1000);
    }

    ['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(eventName => {
        document.addEventListener(eventName, resetAdminIdleTimer);
    });

    resetAdminIdleTimer();
</script>
</body>
</html>
