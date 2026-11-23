<?php
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/managed_courses.php';
$db = getDb();
$activeNav = 'admin';
$base = mcBase();
$here = $base . '/public/admin_courses.php';
$courseId = isset($_GET['course']) ? mcId($_GET['course']) : null;
$error = '';
$notice = $_SESSION['mc_notice'] ?? '';
unset($_SESSION['mc_notice']);
$mode = $_GET['mode'] ?? 'list';
$editPerson = null;
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$importPreview = null;
if ($post) {
    try {
        if (!csrfVerify($_POST['csrf'] ?? null)) throw new InvalidArgumentException(mcUi('Сессия устарела. Обновите страницу.', 'Сессия аяқталды. Бетті жаңартыңыз.'));
        $action = $_POST['action'] ?? '';
        if ($action === 'save_course') {
            $id = ($_POST['id'] ?? '') !== '' ? mcId($_POST['id']) : null;
            $saved = mcSaveCourse($db, $_POST, $id, mcUploadedPdf('presentation', 2 * 1024 * 1024));
            $_SESSION['mc_notice'] = mcUi('Курс сохранён.', 'Курс сақталды.');
            $redirect = $here;
        } elseif (in_array($action, ['open', 'close', 'archive', 'restore'], true)) {
            $id = mcId($_POST['id'] ?? '');
            mcCourseState($db, $id, $action);
            $_SESSION['mc_notice'] = mcUi('Настройки курса сохранены.', 'Курс параметрлері сақталды.');
            $redirect = $here . ($action === 'archive' ? '?archive=1' : '');
        } elseif ($action === 'add_person' || $action === 'add_bulk') {
            if (!$courseId) throw new InvalidArgumentException('Выберите курс.');
            $rows = [$_POST];
            if ($action === 'add_bulk') {
                $text = mcText($_POST['names'] ?? '', 'список ФИО', 34000, true);
                $rows = array_map(fn($name) => ['full_name' => trim($name)], array_filter(explode("\n", $text), fn($s) => trim($s) !== ''));
            }
            $count = mcAddPeople($db, $courseId, $rows, false, $action === 'add_person' ? mcUploadedPdf('resume', 2 * 1024 * 1024) : null);
            $_SESSION['mc_notice'] = mcUi('Добавлено участников: ', 'Қосылған қатысушылар: ') . $count;
            $redirect = $here . '?course=' . $courseId;
        } elseif ($action === 'edit_person') {
            if (!$courseId) throw new InvalidArgumentException('Выберите курс.');
            mcEditPerson($db, $courseId, mcId($_POST['person_id'] ?? ''), $_POST);
            $_SESSION['mc_notice'] = mcUi('Данные участника сохранены.', 'Қатысушы деректері сақталды.');
            $redirect = $here . '?course=' . $courseId;
        } elseif ($action === 'issue') {
            if (!$courseId) throw new InvalidArgumentException('Выберите курс.');
            mcIssueCertificate($db, $courseId, mcId($_POST['person_id'] ?? ''), $_POST);
            $_SESSION['mc_notice'] = mcUi('Сертификат зарегистрирован. PDF с QR-кодом готов к скачиванию.', 'Сертификат тіркелді. QR-коды бар PDF жүктеуге дайын.');
            $redirect = $here . '?course=' . $courseId;
        } elseif ($action === 'preview_import') {
            if (!$courseId) throw new InvalidArgumentException('Выберите курс.');
            unset($_SESSION['mc_import']);
            $upload = $_FILES['manifest'] ?? null;
            if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 1024 * 1024 || !is_uploaded_file($upload['tmp_name'])) throw new InvalidArgumentException('Выберите JSON-файл из генератора размером до 1 МБ.');
            $records = offlineCertificateRecords(file_get_contents($upload['tmp_name']));
            $importPreview = mcImportPlan($db, $courseId, $records);
            $_SESSION['mc_import'] = ['id' => bin2hex(random_bytes(24)), 'course_id' => $courseId, 'expires' => time() + 900, 'records' => $records];
        } elseif ($action === 'register_import') {
            $preview = $_SESSION['mc_import'] ?? null;
            if (!$courseId || !is_array($preview) || $preview['course_id'] !== $courseId || $preview['expires'] < time()
                || !is_string($_POST['preview_id'] ?? null) || !hash_equals($preview['id'], $_POST['preview_id'])) throw new InvalidArgumentException('Предпросмотр устарел. Проверьте файл заново.');
            $count = mcImportCertificates($db, $courseId, $preview['records']);
            unset($_SESSION['mc_import']);
            $_SESSION['mc_notice'] = mcUi('Сертификаты зарегистрированы. Добавлено в курс: ', 'Сертификаттар тіркелді. Курсқа қосылды: ') . $count;
            $redirect = $here . '?course=' . $courseId;
        } else { throw new InvalidArgumentException('Неизвестное действие.'); }
        if ($action !== 'preview_import') {
            writeAdminLog('managed_course_' . $action, 'courseId=' . ($courseId ?? $saved ?? $id ?? ''));
            header('Location: ' . $redirect, true, 303);
            exit;
        }
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Managed course operation failed: ' . get_class($e)); $error = mcUi('Не удалось сохранить. Данные не удалены. Обновите страницу и повторите.', 'Сақтау мүмкін болмады. Деректер жойылған жоқ. Қайта көріңіз.'); }
}
$course = $courseId ? mcCourse($db, $courseId) : null;
$editor = null;
if ($mode === 'new') $editor = ['id' => '', 'title_ru' => '', 'title_kk' => '', 'description_ru' => '', 'description_kk' => '', 'levels' => json_encode(['Прослушал(а) курс', 'Категория C — от 51% до 75%', 'Категория B — от 76% до 90%', 'Категория A — от 91% до 100%'], JSON_UNESCAPED_UNICODE), 'city' => 'Астана', 'start_date' => '', 'end_date' => '', 'is_open' => 0, 'resume_required' => 1];
if ($mode === 'edit') $editor = mcCourse($db, mcId($_GET['id'] ?? ''));
if ($course && isset($_GET['person'])) $editPerson = mcPerson($db, $courseId, mcId($_GET['person']));
function mcCsrf(): void { echo '<input type="hidden" name="csrf" value="' . mcEscape(csrfToken()) . '">'; }
function mcStateForm(string $id, string $action, string $text): void { ?>
<form method="post"><?php mcCsrf(); ?><input type="hidden" name="id" value="<?= mcEscape($id) ?>"><button class="btn btn-secondary" name="action" value="<?= mcEscape($action) ?>"><?= mcEscape($text) ?></button></form>
<?php }
$statusLabels = ['pending' => mcUi('Заявка', 'Өтінім'), 'approved' => mcUi('Подтверждён', 'Расталды'), 'rejected' => mcUi('Отклонён', 'Қабылданбады'), 'completed' => mcUi('Завершил', 'Аяқтады')];
?>
<!doctype html><html lang="<?= mcEscape(i18nLocale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= mcUi('Управление курсами', 'Курстарды басқару') ?></title><link rel="stylesheet" href="<?= $base ?>/public/style.css"><link rel="stylesheet" href="<?= $base ?>/public/managed_courses.css"></head><body>
<?php require __DIR__ . '/_header.php'; ?>
<main class="mc-main">
<a class="btn btn-secondary" href="<?= $base ?>/admin"><?= mcUi('Результаты и прежние сертификаты', 'Нәтижелер және бұрынғы сертификаттар') ?></a>
<h1><?= mcUi('Управление курсами', 'Курстарды басқару') ?></h1>
<?php if ($notice): ?><div class="mc-notice" role="status"><?= mcEscape($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-notice mc-error" role="alert"><?= mcEscape($error) ?></div><?php endif; ?>
<?php if ($editor):
    $form = $editor;
    if ($error && ($_POST['action'] ?? '') === 'save_course') $form = array_replace($form, $_POST, ['levels' => json_encode(explode("\n", $_POST['levels'] ?? ''), JSON_UNESCAPED_UNICODE), 'resume_required' => isset($_POST['resume_required']) ? 1 : 0]); ?>
<section class="mc-panel"><h2><?= $editor['id'] ? mcUi('Изменить курс', 'Курсты өзгерту') : mcUi('Создать курс', 'Курс құру') ?></h2>
<form method="post" enctype="multipart/form-data"><?php mcCsrf(); ?><input type="hidden" name="action" value="save_course"><input type="hidden" name="id" value="<?= mcEscape($editor['id']) ?>">
<div class="mc-grid">
<?php foreach (['title_ru' => ['Название курса (RU) *', 'Курс атауы (RU) *', 350], 'title_kk' => ['Название курса (KK)', 'Курс атауы (KK)', 350]] as $field => $label): ?>
<label><?= mcUi($label[0], $label[1]) ?><input name="<?= $field ?>" maxlength="<?= $label[2] ?>" value="<?= mcEscape($form[$field]) ?>" <?= $field === 'title_ru' ? 'required' : '' ?>></label><?php endforeach; ?>
<?php foreach (['description_ru' => ['Описание (RU) *', 'Сипаттама (RU) *'], 'description_kk' => ['Описание (KK)', 'Сипаттама (KK)']] as $field => $label): ?>
<label><?= mcUi(...$label) ?><textarea name="<?= $field ?>" maxlength="6000" <?= $field === 'description_ru' ? 'required' : '' ?>><?= mcEscape($form[$field]) ?></textarea></label><?php endforeach; ?>
<label class="mc-wide"><?= mcUi('Уровни сертификатов — каждый с новой строки *', 'Сертификат деңгейлері — әрқайсысы жаңа жолдан *') ?><textarea name="levels" maxlength="3000" required><?= mcEscape(implode("\n", mcLevels($form))) ?></textarea></label>
<label><?= mcUi('Дата начала', 'Басталу күні') ?><input type="date" name="start_date" min="1900-01-01" max="2100-12-31" value="<?= mcEscape($form['start_date']) ?>"></label>
<label><?= mcUi('Дата окончания', 'Аяқталу күні') ?><input type="date" name="end_date" min="1900-01-01" max="2100-12-31" value="<?= mcEscape($form['end_date']) ?>"></label>
<label><?= mcUi('Город *', 'Қала *') ?><input name="city" maxlength="50" value="<?= mcEscape($form['city']) ?>" required></label>
<label><?= mcUi('Статус набора', 'Қабылдау мәртебесі') ?><select name="is_open"><option value="0" <?= !$form['is_open'] ? 'selected' : '' ?>><?= mcUi('Набор закрыт', 'Қабылдау жабық') ?></option><option value="1" <?= $form['is_open'] ? 'selected' : '' ?>><?= mcUi('Набор открыт', 'Қабылдау ашық') ?></option></select></label>
<label class="mc-check"><input type="checkbox" name="resume_required" value="1" <?= $form['resume_required'] ? 'checked' : '' ?>><?= mcUi('Резюме обязательно при записи по ссылке', 'Сілтеме арқылы тіркелгенде түйіндеме міндетті') ?></label>
<label><?= mcUi('Презентация курса (PDF, до 2 МБ)', 'Курс презентациясы (PDF, 2 МБ дейін)') ?><input type="file" name="presentation" accept="application/pdf,.pdf"></label>
</div><p class="mc-muted"><?= mcUi('Можно указать даты прошедшего обучения. Изменение курса сохраняет данные уже выданных сертификатов.', 'Өткен оқу күндерін көрсетуге болады. Курс өзгерсе де, берілген сертификаттар сақталады.') ?></p>
<div class="mc-actions"><button class="btn btn-primary"><?= mcUi('Сохранить курс', 'Курсты сақтау') ?></button><a class="btn btn-secondary" href="<?= $here ?>"><?= mcUi('Отмена', 'Бас тарту') ?></a></div></form></section>
<?php elseif ($course): ?>
<div class="mc-toolbar"><h2 class="mc-heading"><?= mcEscape(mcLabel($course, 'title')) ?></h2><a class="btn btn-secondary" href="<?= $here ?>"><?= mcUi('К списку курсов', 'Курстар тізіміне') ?></a></div>
<div class="mc-actions"><a class="btn btn-secondary" href="<?= $base ?>/courses/<?= $courseId ?>" target="_blank" rel="noopener"><?= mcUi('Ссылка на курс', 'Курс сілтемесі') ?></a><a class="btn btn-secondary" href="<?= $base ?>/public/admin_course_certificate.php?course=<?= $courseId ?>&amp;export=csv"><?= mcUi('Скачать список CSV', 'CSV тізімін жүктеу') ?></a><a class="btn btn-primary" href="<?= $base ?>/public/admin_course_certificate.php?course=<?= $courseId ?>&amp;export=zip"><?= mcUi('Все сертификаты ZIP', 'Барлық сертификаттар ZIP') ?></a></div>
<?php if (!(int)$course['archived']): ?>
<section class="mc-panel"><h2><?= $editPerson ? mcUi('Изменить участника', 'Қатысушыны өзгерту') : mcUi('Добавить участника', 'Қатысушы қосу') ?></h2>
<?php $personForm = $editPerson ?? []; if ($error && in_array($_POST['action'] ?? '', ['add_person', 'edit_person'], true)) $personForm = $_POST; ?>
<form method="post" enctype="multipart/form-data"><?php mcCsrf(); ?><input type="hidden" name="action" value="<?= $editPerson ? 'edit_person' : 'add_person' ?>">
<?php if ($editPerson): ?><input type="hidden" name="person_id" value="<?= mcEscape($editPerson['id']) ?>"><?php endif; ?>
<div class="mc-grid">
<?php foreach (['full_name' => ['ФИО *', 'Аты-жөні *', 160], 'email' => ['Email', 'Email', 254], 'phone' => ['Телефон', 'Телефон', 50], 'organization' => ['Организация', 'Ұйым', 250], 'position' => ['Должность', 'Лауазым', 250]] as $field => $label): ?><label><?= mcUi($label[0], $label[1]) ?><input name="<?= $field ?>" maxlength="<?= $label[2] ?>" value="<?= mcEscape($personForm[$field] ?? '') ?>" <?= $field === 'full_name' ? 'required' : '' ?> <?= $field === 'email' ? 'type="email"' : '' ?>></label><?php endforeach; ?>
<?php if ($editPerson): ?><label><?= mcUi('Статус', 'Мәртебе') ?><select name="status"><?php foreach ($statusLabels as $key => $label): ?><option value="<?= $key ?>" <?= ($personForm['status'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<?php else: ?><label><?= mcUi('Резюме (PDF, до 2 МБ; необязательно)', 'Түйіндеме (PDF, 2 МБ дейін; міндетті емес)') ?><input type="file" name="resume" accept="application/pdf,.pdf"></label><?php endif; ?>
</div><div class="mc-actions" style="margin-top:20px"><button class="btn btn-primary"><?= $editPerson ? mcUi('Сохранить участника', 'Қатысушыны сақтау') : mcUi('Добавить участника', 'Қатысушы қосу') ?></button><?php if ($editPerson): ?><a class="btn btn-secondary" href="<?= $here ?>?course=<?= $courseId ?>"><?= mcUi('Отмена', 'Бас тарту') ?></a><?php endif; ?></div></form>
<?php if (!$editPerson): ?><details <?= $error && ($_POST['action'] ?? '') === 'add_bulk' ? 'open' : '' ?>><summary><?= mcUi('Добавить список людей', 'Қатысушылар тізімін қосу') ?></summary><form method="post"><?php mcCsrf(); ?><input type="hidden" name="action" value="add_bulk"><label><?= mcUi('ФИО — каждое с новой строки, до 200 человек', 'Аты-жөні — әрқайсысы жаңа жолдан, 200 адамға дейін') ?><textarea name="names" maxlength="34000" required><?= mcEscape($error ? ($_POST['names'] ?? '') : '') ?></textarea></label><p class="mc-muted"><?= mcUi('Весь список проверяется перед сохранением. Если найдётся ошибка или повтор, никто из списка не будет добавлен.', 'Тізім сақталмас бұрын тексеріледі. Қате немесе қайталау болса, ешкім қосылмайды.') ?></p><button class="btn btn-primary"><?= mcUi('Добавить список', 'Тізімді қосу') ?></button></form></details><?php endif; ?>
</section><?php endif; ?>
<?php if (!(int)$course['archived']): ?><section class="mc-panel"><details <?= $importPreview || ($error && str_contains($_POST['action'] ?? '', 'import')) ? 'open' : '' ?>><summary><?= mcUi('Загрузить участников и сертификаты из офлайн-генератора', 'Офлайн генератордан қатысушылар мен сертификаттарды жүктеу') ?></summary><p class="mc-muted"><?= mcUi('Выберите файл «Регистрация_сертификатов.json». Название курса должно совпадать. Существующие номера и QR сохраняются. Сначала проверьте список, затем подтвердите регистрацию.', '«Регистрация_сертификатов.json» файлын таңдаңыз. Курс атауы сәйкес болуы тиіс. Бұрынғы нөмірлер мен QR сақталады. Алдымен тізімді тексеріп, тіркеуді растаңыз.') ?></p><form method="post" enctype="multipart/form-data"><?php mcCsrf(); ?><input type="hidden" name="action" value="preview_import"><label><?= mcUi('Файл регистрации (JSON, до 1 МБ)', 'Тіркеу файлы (JSON, 1 МБ дейін)') ?><input name="manifest" type="file" accept=".json,application/json" required></label><button class="btn btn-secondary"><?= mcUi('Проверить и показать', 'Тексеру және көрсету') ?></button></form>
<?php if ($importPreview): ?><p><?= mcUi('Новых сертификатов: ', 'Жаңа сертификаттар: ') . $importPreview['counts']['new'] ?>; <?= mcUi('уже зарегистрировано: ', 'тіркелген: ') . $importPreview['counts']['existing'] ?>; <?= mcUi('конфликтов: ', 'қайшылықтар: ') . $importPreview['counts']['conflict'] ?></p><div class="mc-table-wrap"><table><thead><tr><th><?= mcUi('ФИО', 'Аты-жөні') ?></th><th><?= mcUi('Номер', 'Нөмір') ?></th><th><?= mcUi('Результат', 'Нәтиже') ?></th></tr></thead><tbody><?php foreach ($importPreview['rows'] as $row): ?><tr><td><?= mcEscape($row['fullName']) ?></td><td><?= mcEscape($row['certificateNumber']) ?></td><td><?= mcEscape($row['reason']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if (!$importPreview['counts']['conflict']): ?><form method="post"><?php mcCsrf(); ?><input type="hidden" name="action" value="register_import"><input type="hidden" name="preview_id" value="<?= mcEscape($_SESSION['mc_import']['id']) ?>"><button class="btn btn-primary"><?= mcUi('Подтвердить регистрацию в курсе', 'Курсқа тіркеуді растау') ?></button></form><?php endif; endif; ?></details></section><?php endif; ?>
<section class="mc-panel"><h2><?= mcUi('Участники', 'Қатысушылар') ?></h2><div class="mc-table-wrap"><table><thead><tr><?php foreach ([mcUi('ФИО / организация', 'Аты-жөні / ұйым'), 'Email / ' . mcUi('телефон', 'телефон'), mcUi('Статус', 'Мәртебе'), mcUi('Резюме', 'Түйіндеме'), mcUi('Сертификат / действия', 'Сертификат / әрекеттер')] as $label): ?><th><?= $label ?></th><?php endforeach; ?></tr></thead><tbody>
<?php $q = $db->prepare('SELECT * FROM managed_course_person WHERE course_id = ? ORDER BY created_at, id'); $q->execute([$courseId]); $people = $q->fetchAll(PDO::FETCH_ASSOC);
foreach ($people as $person): ?><tr><td><strong><?= mcEscape($person['full_name']) ?></strong><div class="mc-muted"><?= mcEscape($person['organization']) ?><br><?= mcEscape($person['position']) ?></div></td><td><?= mcEscape($person['email']) ?><br><?= mcEscape($person['phone']) ?></td><td><span class="mc-badge"><?= $statusLabels[$person['status']] ?></span></td><td><?php if ($person['resume_id']): ?><a href="<?= $base ?>/public/course_file.php?id=<?= mcEscape($person['resume_id']) ?>"><?= mcUi('Скачать', 'Жүктеу') ?></a><?php else: ?>—<?php endif; ?></td><td>
<?php if ($person['certificate_token']): ?><p><?= mcEscape($person['certificate_number']) ?></p><div class="mc-actions"><a class="btn btn-primary" href="<?= $base ?>/public/admin_course_certificate.php?course=<?= $courseId ?>&amp;person=<?= $person['id'] ?>"><?= mcUi('Скачать PDF', 'PDF жүктеу') ?></a><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= $base ?>/certificate/<?= $person['certificate_token'] ?>"><?= mcUi('Проверить QR', 'QR тексеру') ?></a></div>
<?php elseif (!(int)$course['archived']): ?><a href="<?= $here ?>?course=<?= $courseId ?>&amp;person=<?= $person['id'] ?>"><?= mcUi('Изменить / подтвердить', 'Өзгерту / растау') ?></a><details <?= $error && ($_POST['person_id'] ?? '') === $person['id'] && ($_POST['action'] ?? '') === 'issue' ? 'open' : '' ?>><summary><?= mcUi('Выдать сертификат', 'Сертификат беру') ?></summary><form method="post"><?php mcCsrf(); ?><input type="hidden" name="action" value="issue"><input type="hidden" name="person_id" value="<?= $person['id'] ?>"><label><?= mcUi('Уровень', 'Деңгей') ?><select name="level" required><?php foreach (mcLevels($course) as $level): ?><option value="<?= mcEscape($level) ?>"><?= mcEscape($level) ?></option><?php endforeach; ?></select></label><label><?= mcUi('Дата завершения *', 'Аяқталу күні *') ?><input type="date" name="completion_date" value="<?= mcEscape($course['end_date'] ?: date('Y-m-d')) ?>" min="1900-01-01" max="2100-12-31" required></label><label><?= mcUi('Номер (необязательно, иначе автоматически)', 'Нөмір (міндетті емес, әйтпесе автоматты)') ?><input name="certificate_number" maxlength="70"></label><p class="mc-muted"><?= mcUi('Проверьте ФИО, уровень и дату. После выдачи номер, QR и данные сертификата сохраняются.', 'Аты-жөні, деңгейі мен күнін тексеріңіз. Берілгеннен кейін нөмір, QR және деректер сақталады.') ?></p><button class="btn btn-primary"><?= mcUi('Зарегистрировать и выдать', 'Тіркеу және беру') ?></button></form></details><?php endif; ?>
</td></tr><?php endforeach; ?><?php if (!$people): ?><tr><td colspan="5" class="mc-empty"><?= mcUi('Пока нет участников. Добавьте людей или отправьте ссылку на курс.', 'Қатысушылар жоқ. Адамдарды қосыңыз немесе курс сілтемесін жіберіңіз.') ?></td></tr><?php endif; ?></tbody></table></div></section>
<?php else: $archived = isset($_GET['archive']) ? 1 : 0; ?>
<p class="mc-lead"><?= mcUi('Создавайте курсы, добавляйте участников и выдавайте сертификаты с QR-кодом. Для прошедшего очного обучения можно указать прежние даты.', 'Курстар құрыңыз, қатысушыларды қосып, QR-коды бар сертификаттар беріңіз. Өткен офлайн оқу күндерін көрсетуге болады.') ?></p>
<div class="mc-toolbar"><div class="mc-actions"><a class="btn btn-primary" href="<?= $here ?>?mode=new"><?= mcUi('Создать курс', 'Курс құру') ?></a><a class="btn btn-secondary" href="<?= $here . ($archived ? '' : '?archive=1') ?>"><?= $archived ? mcUi('Действующие курсы', 'Белсенді курстар') : mcUi('Архив курсов', 'Курстар мұрағаты') ?></a></div><a href="<?= $base ?>/public/admin_certificates.php"><?= mcUi('Загрузить сертификаты из офлайн-генератора', 'Офлайн генератор сертификаттарын жүктеу') ?></a></div>
<?php if (isset($_GET['confirm_archive'])): $toArchive = mcCourse($db, mcId($_GET['confirm_archive'])); ?><div class="mc-confirm"><p><?= mcUi('Перенести в архив курс', 'Курсты мұрағатқа көшіру') ?> «<?= mcEscape(mcLabel($toArchive, 'title')) ?>»? <?= mcUi('Участники и выданные сертификаты сохранятся. Курс можно восстановить.', 'Қатысушылар мен берілген сертификаттар сақталады. Курсты қалпына келтіруге болады.') ?></p><div class="mc-actions"><?php mcStateForm($toArchive['id'], 'archive', mcUi('Подтвердить архивирование', 'Мұрағаттауды растау')); ?><a href="<?= $here ?>"><?= mcUi('Отмена', 'Бас тарту') ?></a></div></div><?php endif; ?>
<section class="mc-panel"><h2><?= $archived ? mcUi('Архив курсов', 'Курстар мұрағаты') : mcUi('Курсы', 'Курстар') ?></h2><div class="mc-table-wrap"><table><thead><tr><?php foreach ([mcUi('Название', 'Атауы'), mcUi('Уровни сертификатов', 'Сертификат деңгейлері'), mcUi('Набор', 'Қабылдау'), mcUi('Участников', 'Қатысушылар'), mcUi('Создан', 'Құрылған'), mcUi('Действия', 'Әрекеттер')] as $label): ?><th><?= $label ?></th><?php endforeach; ?></tr></thead><tbody>
<?php $q = $db->prepare('SELECT c.*, (SELECT COUNT(*) FROM managed_course_person p WHERE p.course_id = c.id) AS person_count FROM managed_course c WHERE archived = ? ORDER BY created_at DESC, id'); $q->execute([$archived]); $courses = $q->fetchAll(PDO::FETCH_ASSOC);
foreach ($courses as $item): ?><tr><td><strong><?= mcEscape(mcLabel($item, 'title')) ?></strong><div class="mc-muted"><?= mcEscape($item['start_date']) ?> — <?= mcEscape($item['end_date']) ?></div></td><td><ul><?php foreach (mcLevels($item) as $level): ?><li><?= mcEscape($level) ?></li><?php endforeach; ?></ul></td><td><span class="mc-badge" data-open="<?= (int)$item['is_open'] ?>"><?= (int)$item['is_open'] ? mcUi('Открыт', 'Ашық') : mcUi('Закрыт', 'Жабық') ?></span></td><td><?= (int)$item['person_count'] ?></td><td><?= mcEscape(substr($item['created_at'], 0, 10)) ?></td><td><div class="mc-actions"><a class="btn btn-secondary" target="_blank" rel="noopener" href="<?= $base ?>/courses/<?= $item['id'] ?>"><?= mcUi('Ссылка', 'Сілтеме') ?></a><a class="btn btn-secondary" href="<?= $here ?>?course=<?= $item['id'] ?>"><?= mcUi('Участники', 'Қатысушылар') ?></a>
<?php if ($archived): mcStateForm($item['id'], 'restore', mcUi('Восстановить', 'Қалпына келтіру')); else: ?><a class="btn btn-secondary" href="<?= $here ?>?mode=edit&amp;id=<?= $item['id'] ?>"><?= mcUi('Изменить', 'Өзгерту') ?></a><?php mcStateForm($item['id'], (int)$item['is_open'] ? 'close' : 'open', (int)$item['is_open'] ? mcUi('Закрыть', 'Жабу') : mcUi('Открыть', 'Ашу')); ?><a class="btn btn-secondary" href="<?= $here ?>?confirm_archive=<?= $item['id'] ?>"><?= mcUi('В архив', 'Мұрағатқа') ?></a><?php endif; ?></div></td></tr><?php endforeach; ?>
<?php if (!$courses): ?><tr><td colspan="6" class="mc-empty"><?= $archived ? mcUi('Архив пуст.', 'Мұрағат бос.') : mcUi('Создайте первый курс. Прежние участники и сертификаты остаются в разделе результатов.', 'Бірінші курсты құрыңыз. Бұрынғы қатысушылар мен сертификаттар нәтижелер бөлімінде сақталған.') ?></td></tr><?php endif; ?>
</tbody></table></div></section><?php endif; ?>
</main><script src="<?= $base ?>/public/managed_courses.js" defer></script></body></html>
