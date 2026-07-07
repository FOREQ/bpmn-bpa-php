<?php

require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/csrf.php';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>

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
    <a href="student_login.php">Войти</a>
    <a href="admin_login.php">Админ</a>
</nav>
    </div>
</header>
<div class="container">
    <div class="admin-card">
        <div class="admin-header">
            <div>
                <div class="admin-label">Администрирование</div>
                <h1 class="admin-title">Результаты участников</h1>
            </div>

            <div class="admin-actions">
                <a class="admin-btn primary" href="../api/admin/export.php">Скачать CSV</a>
                <button class="admin-btn secondary" id="refreshBtn" type="button">Обновить список</button>
                <a class="admin-btn secondary" href="logout.php">Выйти из админки</a>
            </div>
        </div>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>Участник</th>
                    <th>Организация</th>
                    <th>Тест</th>
                    <th>Практика</th>
                    <th>Итог</th>
                    <th>Дата</th>
                    <th>Действие</th>
                </tr>
                </thead>
                <tbody id="participantsBody">
                <tr>
                    <td colspan="7">Загрузка...</td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="modal" class="modal">
    <div class="modal-content">
        <div class="admin-modal-top">
            <div>
                <div class="admin-label">Практическая работа</div>
                <h2 class="admin-modal-title">Проверка практики</h2>
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

    function practicalStatus(row) {
        if (!row.practicalSubmittedAt) {
            return '<span class="status waiting">Не отправлена</span>';
        }

        if (!row.practicalGradedAt) {
            return '<span class="status waiting">Ожидает проверки</span>';
        }

        return '<span class="status passed">Проверена: ' + row.practicalScoreTotal + ' / 30</span>';
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

    async function loadParticipants() {
        const response = await fetch('../api/admin/results.php');
        const json = await response.json();

        if (!json.success) {
            participantsBody.innerHTML = '<tr><td colspan="7">Ошибка загрузки</td></tr>';
            return;
        }

        if (json.participants.length === 0) {
            participantsBody.innerHTML = '<tr><td colspan="7">Участников пока нет</td></tr>';
            return;
        }

        participantsBody.innerHTML = '';

        json.participants.forEach(row => {
            const tr = document.createElement('tr');

          tr.innerHTML = `
    <td>
        <b>${escapeHtml(row.fullName)}</b><br>
        <span class="small email-cell" title="${escapeHtml(row.email)}">
            ${escapeHtml(row.email)}
        </span><br>
        <span class="small">${escapeHtml(row.phone)}</span>
    </td>

    <td>
        <span class="organization-cell" title="${escapeHtml(row.organization)}">
            ${escapeHtml(row.organization)}
        </span>
    </td>

    <td>
        Вариант: <b>${escapeHtml(row.variantId)}</b><br>
        Баллы: ${row.score ?? '-'} / ${row.total ?? '-'}<br>
        Процент: ${row.percent ?? '-'}%<br>
        ${testStatus(row.status)}
    </td>

    <td>${practicalStatus(row)}</td>

    <td>${overallGrade(row.percent, row.practicalScoreTotal)}</td>

    <td>
        Регистрация:<br>
        <span class="small">${escapeHtml(row.createdAt)}</span><br>
        Обновлено:<br>
        <span class="small">${escapeHtml(row.updatedAt)}</span>
    </td>

    <td>
       <button class="admin-action-btn" onclick="openPractical('${escapeHtml(row.sessionId)}')" type="button">
    Проверить практику
</button>

${Number(row.passwordResetRequested) === 1 ? `
    <br><br>
    <button class="admin-action-btn" onclick="allowPasswordReset('${escapeHtml(row.sessionId)}')" type="button">
        Сбросить пароль
    </button>
` : ''}
    </td>
`;
            participantsBody.appendChild(tr);
        });
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

                <button class="admin-action-btn" onclick="saveScores('${p.sessionId}')" type="button">Сохранить оценку</button>

                <div id="scoreMessage"></div>
            </section>
        `;

        practical.tasks.forEach((task, index) => {
            const answer = practical.answers?.diagrams?.[task.id]?.xml || '';

            if (answer.trim() !== '') {
                renderBpmnViewer('viewer_' + task.id, answer);
            } else {
                const viewerBox = document.getElementById('viewer_' + task.id);

                if (viewerBox) {
                    viewerBox.innerHTML =
                        '<p style="padding:16px;">Ответ не найден</p>';
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

async function allowPasswordReset(sessionId) {
    if (!confirm('Разрешить пользователю установить новый пароль?')) {
        return;
    }

    const response = await fetch('../api/admin/allow_password_reset.php', {
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
        alert(json.message || 'Ошибка сброса пароля');
        return;
    }

    alert('Сброс пароля разрешен');
    await loadParticipants();
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
            scoreMessage.innerHTML = '<p style="color:red;">' + (json.message || 'Ошибка сохранения') + '</p>';
            return;
        }

        scoreMessage.innerHTML = '<p style="color:green;">Оценка сохранена. Итог: ' + json.scores.total + ' / 30</p>';
        await loadParticipants();
    }

    function escapeHtml(text) {
        return String(text)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
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
    }, 20 * 60 * 1000); // 20 минут
}

['click', 'mousemove', 'keydown', 'scroll', 'touchstart'].forEach(eventName => {
    document.addEventListener(eventName, resetAdminIdleTimer);
});

resetAdminIdleTimer();
</script>
</body>
</html>
