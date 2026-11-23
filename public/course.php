<?php
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/managed_courses.php';
$base = mcBase();
$logoSrc = $base . '/assets/logo.svg/logo-black.png';
$navBase = $base . '/public/';
$error = '';
$success = false;
$course = null;
try { $course = mcCourse(getDb(), mcId($_GET['id'] ?? '')); }
catch (InvalidArgumentException $e) { http_response_code(404); }
if ($course && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrfVerify($_POST['csrf'] ?? null)) throw new InvalidArgumentException(mcUi('Обновите страницу и повторите запись.', 'Бетті жаңартып, қайта тіркеліңіз.'));
        if (($_POST['website'] ?? '') !== '' || time() - ($_SESSION['mc_last_signup'] ?? 0) < 60) {
            throw new InvalidArgumentException(mcUi('Подождите минуту перед повторной записью.', 'Қайта тіркелмес бұрын бір минут күтіңіз.'));
        }
        mcAddPeople(getDb(), $course['id'], [$_POST], true, mcUploadedPdf('resume', 2 * 1024 * 1024));
        $_SESSION['mc_last_signup'] = time();
        $_SESSION['mc_signup_success'] = $course['id'];
        header('Location: ' . $base . '/courses/' . $course['id'], true, 303);
        exit;
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Course signup failed: ' . get_class($e)); $error = mcUi('Не удалось отправить заявку. Повторите позже.', 'Өтінімді жіберу мүмкін болмады. Кейін қайталап көріңіз.'); }
}
if ($course && ($_SESSION['mc_signup_success'] ?? '') === $course['id']) { $success = true; unset($_SESSION['mc_signup_success']); }
$count = 0;
if ($course) { $q = getDb()->prepare('SELECT COUNT(*) FROM managed_course_person WHERE course_id = ?'); $q->execute([$course['id']]); $count = (int)$q->fetchColumn(); }
?>
<!doctype html><html lang="<?= mcEscape(i18nLocale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= mcEscape($course ? mcLabel($course, 'title') : mcUi('Курс не найден', 'Курс табылмады')) ?></title><link rel="stylesheet" href="<?= $base ?>/public/style.css"><link rel="stylesheet" href="<?= $base ?>/public/managed_courses.css"></head><body>
<?php require __DIR__ . '/_header.php'; ?>
<main class="mc-main">
<?php if (!$course): ?><h1><?= mcUi('Курс не найден', 'Курс табылмады') ?></h1><p><?= mcUi('Проверьте ссылку или обратитесь к организатору.', 'Сілтемені тексеріңіз немесе ұйымдастырушыға хабарласыңыз.') ?></p>
<?php else: $open = (int)$course['is_open'] && !(int)$course['archived']; ?>
<h1 class="mc-heading"><?= mcEscape(mcLabel($course, 'title')) ?></h1>
<div class="mc-actions"><span class="mc-badge" data-open="<?= $open ? 1 : 0 ?>"><?= $open ? mcUi('Набор открыт', 'Қабылдау ашық') : mcUi('Набор закрыт', 'Қабылдау жабық') ?></span><span><?= mcUi('Участников: ', 'Қатысушылар: ') . $count ?></span></div>
<section class="mc-panel"><h2><?= mcUi('О курсе', 'Курс туралы') ?></h2><p class="mc-description"><?= mcEscape(mcLabel($course, 'description')) ?></p>
<div class="mc-grid"><div><h3><?= mcUi('Уровни сертификатов', 'Сертификат деңгейлері') ?></h3><ul><?php foreach (mcLevels($course) as $level): ?><li><?= mcEscape($level) ?></li><?php endforeach; ?></ul></div><div><h3><?= mcUi('Период обучения', 'Оқу кезеңі') ?></h3><p><?= mcUi('Начало: ', 'Басталуы: ') . mcEscape($course['start_date'] ?: '—') ?></p><p><?= mcUi('Окончание: ', 'Аяқталуы: ') . mcEscape($course['end_date'] ?: '—') ?></p><p><?= mcUi('Город: ', 'Қала: ') . mcEscape($course['city']) ?></p></div></div>
<?php if ($course['presentation_id']): ?><a class="btn btn-secondary" href="<?= $base ?>/public/course_file.php?id=<?= $course['presentation_id'] ?>"><?= mcUi('Скачать презентацию PDF', 'PDF презентациясын жүктеу') ?></a><?php endif; ?></section>
<?php if ($success): ?><div class="mc-notice" role="status"><?= mcUi('Заявка принята. Организатор курса проверит её и свяжется с вами по указанным контактам.', 'Өтінім қабылданды. Курс ұйымдастырушысы оны қарап, көрсетілген байланыстар арқылы хабарласады.') ?></div><?php endif; ?>
<?php if ($error): ?><div class="mc-notice mc-error" role="alert"><?= mcEscape($error) ?></div><?php endif; ?>
<?php if ($open && !$success): ?><section class="mc-panel"><h2><?= mcUi('Записаться на курс', 'Курсқа жазылу') ?></h2><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= mcEscape(csrfToken()) ?>"><div style="display:none" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div>
<div class="mc-grid"><?php foreach (['full_name' => ['ФИО *', 'Аты-жөні *', 160], 'email' => ['Email *', 'Email *', 254], 'phone' => ['Телефон *', 'Телефон *', 50], 'organization' => ['Организация', 'Ұйым', 250], 'position' => ['Должность', 'Лауазым', 250]] as $field => $label): ?><label><?= mcUi($label[0], $label[1]) ?><input name="<?= $field ?>" maxlength="<?= $label[2] ?>" value="<?= mcEscape($error ? ($_POST[$field] ?? '') : '') ?>" <?= in_array($field, ['full_name', 'email', 'phone'], true) ? 'required' : '' ?> <?= $field === 'email' ? 'type="email"' : '' ?> <?= $field === 'phone' ? 'type="tel"' : '' ?>></label><?php endforeach; ?>
<label><?= mcUi('Резюме (PDF, до 2 МБ)', 'Түйіндеме (PDF, 2 МБ дейін)') ?><?= (int)$course['resume_required'] ? ' *' : '' ?><input type="file" name="resume" accept="application/pdf,.pdf" <?= (int)$course['resume_required'] ? 'required' : '' ?>></label></div>
<p class="mc-muted"><?= mcUi('Контакты и резюме доступны только организатору курса. Заявка не создаёт учётную запись для тестирования.', 'Байланыстар мен түйіндеме тек курс ұйымдастырушысына қолжетімді. Өтінім тестілеу үшін есептік жазба құрмайды.') ?></p>
<button class="btn btn-primary"><?= mcUi('Записаться на курс', 'Курсқа жазылу') ?></button></form></section>
<?php elseif (!$open): ?><div class="mc-notice"><?= mcUi('Запись на этот курс закрыта. По вопросам участия обратитесь к организатору.', 'Бұл курсқа тіркелу жабық. Қатысу сұрақтары бойынша ұйымдастырушыға хабарласыңыз.') ?></div><?php endif; endif; ?>
</main><script src="<?= $base ?>/public/managed_courses.js" defer></script></body></html>
