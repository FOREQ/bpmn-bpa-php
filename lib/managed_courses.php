<?php
/** Independent course registry. Existing Participant and issued certificate rows are never rewritten. */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/legacy_certificate_import.php';

function managedCoursesInstall(PDO $db): void
{
    $db->exec("CREATE TABLE IF NOT EXISTS managed_course (
        id TEXT PRIMARY KEY, title_ru TEXT NOT NULL, title_kk TEXT NOT NULL DEFAULT '',
        description_ru TEXT NOT NULL, description_kk TEXT NOT NULL DEFAULT '', levels TEXT NOT NULL,
        start_date TEXT NOT NULL DEFAULT '', end_date TEXT NOT NULL DEFAULT '', city TEXT NOT NULL,
        is_open INTEGER NOT NULL DEFAULT 0, archived INTEGER NOT NULL DEFAULT 0,
        resume_required INTEGER NOT NULL DEFAULT 1, presentation_id TEXT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE TABLE IF NOT EXISTS managed_course_person (
        id TEXT PRIMARY KEY, course_id TEXT NOT NULL REFERENCES managed_course(id),
        full_name TEXT NOT NULL, email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '',
        organization TEXT NOT NULL DEFAULT '', position TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'pending', resume_id TEXT,
        certificate_token TEXT UNIQUE, certificate_number TEXT UNIQUE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $db->exec('CREATE INDEX IF NOT EXISTS managed_course_person_course ON managed_course_person(course_id)');
    $db->exec("CREATE TABLE IF NOT EXISTS managed_course_file (
        id TEXT PRIMARY KEY, course_id TEXT NOT NULL REFERENCES managed_course(id),
        kind TEXT NOT NULL, name TEXT NOT NULL, payload TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $db->exec("CREATE UNIQUE INDEX IF NOT EXISTS managed_course_person_email ON managed_course_person(course_id, email) WHERE email <> ''");
}

function mcText(mixed $value, string $label, int $max, bool $required = false): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException('Проверьте поле: ' . $label);
    $value = trim(str_replace("\r\n", "\n", $value));
    if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', $value)) {
        throw new InvalidArgumentException('Проверьте поле: ' . $label);
    }
    return $value;
}

function mcId(mixed $id): string
{
    if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Некорректная ссылка.');
    return $id;
}

function mcDate(mixed $value, bool $required = false): string
{
    $date = mcText($value, 'дата', 10, $required);
    if ($date !== '' && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $p)
        || !checkdate((int)$p[2], (int)$p[3], (int)$p[1]) || (int)$p[1] < 1900 || (int)$p[1] > 2100)) {
        throw new InvalidArgumentException('Укажите корректную дату.');
    }
    return $date;
}

function mcCourse(PDO $db, string $id, bool $lock = false): array
{
    $q = $db->prepare('SELECT * FROM managed_course WHERE id = ?' . ($lock && $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? ' FOR UPDATE' : ''));
    $q->execute([mcId($id)]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Курс не найден.');
    return $row;
}

function mcPerson(PDO $db, string $courseId, string $id): array
{
    $q = $db->prepare('SELECT * FROM managed_course_person WHERE course_id = ? AND id = ?');
    $q->execute([mcId($courseId), mcId($id)]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Участник не найден.');
    return $row;
}

function mcLevels(array $course): array { return json_decode($course['levels'], true, 16, JSON_THROW_ON_ERROR); }

function mcSaveCourse(PDO $db, array $input, ?string $id = null, ?array $presentation = null): string
{
    $values = [];
    foreach (['title_ru' => 350, 'title_kk' => 350, 'description_ru' => 6000, 'description_kk' => 6000, 'city' => 50] as $field => $limit) {
        $values[$field] = mcText($input[$field] ?? '', $field, $limit, in_array($field, ['title_ru', 'description_ru', 'city'], true));
    }
    $levelsText = mcText($input['levels'] ?? '', 'уровни сертификатов', 3000, true);
    $levels = array_values(array_unique(array_filter(array_map('trim', explode("\n", $levelsText)), fn($v) => $v !== '')));
    if (count($levels) < 1 || count($levels) > 20) throw new InvalidArgumentException('Укажите от 1 до 20 уровней.');
    foreach ($levels as $level) if (mb_strlen($level) > 200) throw new InvalidArgumentException('Уровень сертификата: максимум 200 символов.');
    $values['levels'] = json_encode($levels, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $values['start_date'] = mcDate($input['start_date'] ?? '');
    $values['end_date'] = mcDate($input['end_date'] ?? '');
    if ($values['start_date'] !== '' && $values['end_date'] !== '' && $values['end_date'] < $values['start_date']) {
        throw new InvalidArgumentException('Окончание курса не может быть раньше начала.');
    }
    $values['is_open'] = ($input['is_open'] ?? '0') === '1' ? 1 : 0;
    $values['resume_required'] = isset($input['resume_required']) ? 1 : 0;
    $db->beginTransaction();
    try {
        if ($id !== null) {
            $old = mcCourse($db, $id, true);
            if ((int)$old['archived']) throw new InvalidArgumentException('Сначала восстановите курс из архива.');
            $values['presentation_id'] = $old['presentation_id'];
            $sets = implode(', ', array_map(fn($key) => "$key = :$key", array_keys($values)));
            $db->prepare("UPDATE managed_course SET $sets WHERE id = :id")->execute($values + ['id' => $id]);
        } else {
            $id = bin2hex(random_bytes(16));
            $keys = implode(', ', array_keys($values));
            $placeholders = ':' . implode(', :', array_keys($values));
            $db->prepare("INSERT INTO managed_course ($keys, id) VALUES ($placeholders, :id)")->execute($values + ['id' => $id]);
        }
        if ($presentation) {
            $fileId = mcStoreFile($db, $id, 'presentation', $presentation);
            $db->prepare('UPDATE managed_course SET presentation_id = ? WHERE id = ?')->execute([$fileId, $id]);
        }
        $db->commit();
        return $id;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function mcCourseState(PDO $db, string $id, string $action): void
{
    $db->beginTransaction();
    try {
        $course = mcCourse($db, $id, true);
        switch ($action) {
            case 'archive': $sql = 'archived = 1, is_open = 0'; break;
            case 'restore': $sql = 'archived = 0, is_open = 0'; break;
            case 'open':
            case 'close':
                if ((int)$course['archived']) throw new InvalidArgumentException('Курс находится в архиве.');
                $sql = 'is_open = ' . ($action === 'open' ? '1' : '0'); break;
            default: throw new InvalidArgumentException('Неизвестное действие.');
        }
        $db->prepare("UPDATE managed_course SET $sql WHERE id = ?")->execute([$id]);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function mcUploadedPdf(string $field, int $maxBytes): ?array
{
    $file = $_FILES[$field] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if (($file['error'] ?? -1) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $maxBytes || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Файл не загружен. Выберите PDF допустимого размера.');
    }
    $bytes = file_get_contents($file['tmp_name']);
    if (!$bytes || strlen($bytes) > $maxBytes || !str_starts_with($bytes, '%PDF-')
        || !str_contains(substr($bytes, -2048), '%%EOF')) throw new InvalidArgumentException('Выберите корректный PDF-файл.');
    return ['name' => 'document.pdf', 'payload' => base64_encode($bytes)];
}

function mcStoreFile(PDO $db, string $courseId, string $kind, array $file): string
{
    $id = bin2hex(random_bytes(16));
    $db->prepare('INSERT INTO managed_course_file (id, course_id, kind, name, payload) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, $courseId, $kind, $file['name'], $file['payload']]);
    return $id;
}

function mcPersonValues(array $input, bool $public): array
{
    $values = [];
    foreach (['full_name' => 160, 'email' => 254, 'phone' => 50, 'organization' => 250, 'position' => 250] as $key => $limit) {
        $values[$key] = mcText($input[$key] ?? '', $key, $limit, $key === 'full_name' || ($public && in_array($key, ['email', 'phone'], true)));
        if (str_contains($values[$key], "\n")) throw new InvalidArgumentException('Поле должно быть в одну строку: ' . $key);
    }
    $values['email'] = mb_strtolower($values['email']);
    if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Проверьте email.');
    return $values;
}

function mcAddPeople(PDO $db, string $courseId, array $inputs, bool $public = false, ?array $resume = null): int
{
    if (count($inputs) < 1 || count($inputs) > 200 || ($public && count($inputs) !== 1)) throw new InvalidArgumentException('Можно добавить 1–200 участников.');
    $rows = array_map(fn($v) => mcPersonValues($v, $public), $inputs);
    $db->beginTransaction();
    try {
        $course = mcCourse($db, $courseId, true);
        if ((int)$course['archived'] || ($public && !(int)$course['is_open'])) throw new InvalidArgumentException('Набор на этот курс закрыт.');
        if ($public && (int)$course['resume_required'] && !$resume) throw new InvalidArgumentException('Приложите резюме в PDF.');
        $duplicate = $db->prepare('SELECT id FROM managed_course_person WHERE course_id = ? AND (email = ? AND email <> \'\' OR (full_name = ? AND email = \'\'))');
        $insert = $db->prepare('INSERT INTO managed_course_person (id, course_id, full_name, email, phone, organization, position, resume_id) VALUES (:id, :course_id, :full_name, :email, :phone, :organization, :position, :resume_id)');
        foreach ($rows as $row) {
            $duplicate->execute([$courseId, $row['email'], $row['full_name']]);
            if ($duplicate->fetchColumn() !== false) throw new InvalidArgumentException('Участник уже добавлен в этот курс. Проверьте список.');
            $resumeId = $resume ? mcStoreFile($db, $courseId, 'resume', $resume) : null;
            $insert->execute($row + ['id' => bin2hex(random_bytes(16)), 'course_id' => $courseId, 'resume_id' => $resumeId]);
        }
        $db->commit();
        return count($rows);
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function mcEditPerson(PDO $db, string $courseId, string $personId, array $input): void
{
    $values = mcPersonValues($input, false);
    $status = $input['status'] ?? '';
    if (!in_array($status, ['pending', 'approved', 'rejected', 'completed'], true)) throw new InvalidArgumentException('Некорректный статус.');
    $db->beginTransaction();
    try {
        $course = mcCourse($db, $courseId, true);
        if ((int)$course['archived']) throw new InvalidArgumentException('Сначала восстановите курс.');
        $person = mcPerson($db, $courseId, $personId);
        if ($person['certificate_token']) throw new InvalidArgumentException('Сертификат уже выдан. Его данные сохраняются без изменений.');
        $duplicate = $db->prepare("SELECT id FROM managed_course_person WHERE course_id = ? AND id <> ? AND email = ? AND email <> ''");
        $duplicate->execute([$courseId, $personId, $values['email']]);
        if ($duplicate->fetchColumn() !== false) throw new InvalidArgumentException('Этот email уже есть в курсе.');
        $db->prepare('UPDATE managed_course_person SET full_name = :full_name, email = :email, phone = :phone, organization = :organization, position = :position, status = :status WHERE id = :id AND course_id = :course_id')
            ->execute($values + ['status' => $status, 'id' => $personId, 'course_id' => $courseId]);
        $db->commit();
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function mcIssueCertificate(PDO $db, string $courseId, string $personId, array $input): string
{
    $date = mcDate($input['completion_date'] ?? '', true);
    $date = substr($date, 8, 2) . '.' . substr($date, 5, 2) . '.' . substr($date, 0, 4);
    $level = mcText($input['level'] ?? '', 'уровень сертификата', 200, true);
    $number = mcText($input['certificate_number'] ?? '', 'номер сертификата', 70);
    $db->beginTransaction();
    try {
        $course = mcCourse($db, $courseId, true);
        $person = mcPerson($db, $courseId, $personId);
        if ($person['certificate_token']) { $db->commit(); return $person['certificate_token']; }
        if ((int)$course['archived']) throw new InvalidArgumentException('Сначала восстановите курс.');
        if ($person['status'] === 'rejected') throw new InvalidArgumentException('Заявка отклонена. Сначала измените статус участника.');
        if (!in_array($level, mcLevels($course), true)) throw new InvalidArgumentException('Выберите уровень из настроек курса.');
        // Same lock as offline import prevents simultaneous allocation of an existing number/token.
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') $db->exec('LOCK TABLE LegacyCertificate IN SHARE ROW EXCLUSIVE MODE');
        $collision = $db->prepare('SELECT certificateNumber FROM Participant WHERE certificateNumber = ? OR certificateToken = ? UNION ALL SELECT certificateNumber FROM LegacyCertificate WHERE certificateNumber = ? OR certificateToken = ?');
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $token = bin2hex(random_bytes(16));
            $allocated = $number !== '' ? $number : 'CERT-' . substr($date, -4) . '-' . strtoupper(bin2hex(random_bytes(4)));
            $collision->execute([$allocated, $token, $allocated, $token]);
            if ($collision->fetchColumn() === false) break;
            if ($number !== '') throw new InvalidArgumentException('Такой номер сертификата уже зарегистрирован.');
            if ($attempt === 19) throw new RuntimeException('Не удалось выделить номер.');
        }
        $extra = json_encode(['source' => 'managed-course-v1', 'course_id' => $courseId, 'person_id' => $personId, 'city' => $course['city']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $db->prepare('INSERT INTO LegacyCertificate (fullName, certificateNumber, certificateToken, completionDate, course, level, extra) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$person['full_name'], $allocated, $token, $date, $course['title_ru'], $level, $extra]);
        $db->prepare("UPDATE managed_course_person SET status = 'completed', certificate_token = ?, certificate_number = ? WHERE id = ? AND course_id = ?")
            ->execute([$token, $allocated, $personId, $courseId]);
        $db->commit();
        return $token;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function mcCertificateRow(PDO $db, string $courseId, string $personId): array
{
    $person = mcPerson($db, $courseId, $personId);
    if (!$person['certificate_token']) throw new InvalidArgumentException('Сначала выдайте сертификат.');
    $q = $db->prepare('SELECT fullName, certificateNumber, certificateToken, completionDate, course, level, extra FROM LegacyCertificate WHERE certificateToken = ?');
    $q->execute([$person['certificate_token']]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new InvalidArgumentException('Сертификат не найден.');
    return array_change_key_case($row, CASE_LOWER);
}

function mcImportPlan(PDO $db, string $courseId, array $records): array
{
    $course = mcCourse($db, $courseId);
    if ((int)$course['archived']) throw new InvalidArgumentException('Сначала восстановите курс из архива.');
    foreach ($records as $record) if ($record['course'] !== $course['title_ru']) {
        throw new InvalidArgumentException('Название курса в файле должно совпадать с названием выбранного курса (RU).');
    }
    $plan = offlineCertificateImportPlan($db, $records);
    $find = $db->prepare('SELECT * FROM managed_course_person WHERE certificate_token = ? OR (course_id = ? AND full_name = ?)');
    $seenNames = [];
    foreach ($records as $index => $record) {
        $find->execute([$record['certificateToken'], $courseId, $record['fullName']]);
        $matches = $find->fetchAll(PDO::FETCH_ASSOC);
        $conflict = isset($seenNames[$record['fullName']]) || count($matches) > 1;
        $seenNames[$record['fullName']] = true;
        if ($matches) {
            $person = $matches[0];
            $conflict = $conflict || $person['course_id'] !== $courseId
                || ($person['certificate_token'] !== null && $person['certificate_token'] !== $record['certificateToken'])
                || $person['full_name'] !== $record['fullName'] || $person['status'] === 'rejected';
        }
        if ($conflict && $plan['rows'][$index]['action'] !== 'conflict') {
            $plan['counts'][$plan['rows'][$index]['action']]--;
            $plan['counts']['conflict']++;
            $plan['rows'][$index]['action'] = 'conflict';
            $plan['rows'][$index]['reason'] = 'Проверьте совпадение ФИО, курса и сертификата участника';
        }
    }
    return $plan;
}

function mcImportCertificates(PDO $db, string $courseId, array $records): int
{
    $db->beginTransaction();
    try {
        mcCourse($db, $courseId, true);
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') $db->exec('LOCK TABLE LegacyCertificate IN SHARE ROW EXCLUSIVE MODE');
        $plan = mcImportPlan($db, $courseId, $records);
        if ($plan['counts']['conflict']) throw new InvalidArgumentException('Есть конфликты. Ни одна запись не добавлена. Проверьте файл.');
        $insert = $db->prepare('INSERT INTO LegacyCertificate (fullName, certificateNumber, certificateToken, completionDate, course, level, extra) VALUES (:fullName, :certificateNumber, :certificateToken, :completionDate, :course, :level, :extra)');
        $find = $db->prepare('SELECT * FROM managed_course_person WHERE course_id = ? AND full_name = ?');
        $added = 0;
        foreach ($records as $index => $record) {
            if ($plan['rows'][$index]['action'] === 'new') $insert->execute($record);
            $find->execute([$courseId, $record['fullName']]);
            $person = $find->fetch(PDO::FETCH_ASSOC);
            if (!$person) {
                $db->prepare("INSERT INTO managed_course_person (id, course_id, full_name, status, certificate_token, certificate_number) VALUES (?, ?, ?, 'completed', ?, ?)")
                    ->execute([bin2hex(random_bytes(16)), $courseId, $record['fullName'], $record['certificateToken'], $record['certificateNumber']]);
                $added++;
            } elseif ($person['certificate_token'] === null) {
                $db->prepare("UPDATE managed_course_person SET status = 'completed', certificate_token = ?, certificate_number = ? WHERE id = ? AND certificate_token IS NULL")
                    ->execute([$record['certificateToken'], $record['certificateNumber'], $person['id']]);
                $added++;
            }
        }
        $db->commit();
        return $added;
    } catch (Throwable $e) { $db->rollBack(); throw $e; }
}

function mcBase(): string { return rtrim((string)(getenv('APP_BASE_PATH') ?: ''), '/'); }
function mcEscape(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mcLabel(array $course, string $field): string { return i18nLocale() === 'kk' && $course[$field . '_kk'] !== '' ? $course[$field . '_kk'] : $course[$field . '_ru']; }
function mcUi(string $ru, string $kk): string { return i18nLocale() === 'kk' ? $kk : $ru; }
