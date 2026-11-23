<?php

/** Import offline certificates without changing already issued numbers or QR tokens. */
const OFFLINE_CERTIFICATE_VERIFY_BASE = 'https://dtc.govtec.kz/bpmn/certificate/';

function offlineCertificateText(mixed $value, string $label, int $max): string
{
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
        throw new InvalidArgumentException('Некорректное поле: ' . $label);
    }
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/u', $value)) {
        throw new InvalidArgumentException('Проверьте поле: ' . $label);
    }
    return $value;
}

function offlineCertificateLevel(?int $test, ?int $practical): string
{
    if ($test === null || $practical === null) {
        return 'Прослушал(а) курс';
    }
    $percent = ($test + $practical) * 2;
    if ($percent >= 91) return 'Категории A - от 91% до 100% правильных ответов';
    if ($percent >= 76) return 'Категории B - от 76% до 90% правильных ответов';
    if ($percent >= 51) return 'Категории C - от 51% до 75% правильных ответов';
    return 'Прослушал курс - до 50% правильных ответов';
}

function offlineCertificateRecords(string $json): array
{
    if (strlen($json) > 1024 * 1024) {
        throw new InvalidArgumentException('Максимальный размер файла — 1 МБ.');
    }
    try {
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new InvalidArgumentException('Не удалось прочитать JSON-файл регистрации.');
    }
    if (!is_array($data) || ($data['format'] ?? '') !== 'bpmn-offline-certificates'
        || ($data['version'] ?? null) !== 1 || ($data['verifyBaseUrl'] ?? '') !== OFFLINE_CERTIFICATE_VERIFY_BASE
        || !is_array($data['records'] ?? null) || !array_is_list($data['records'])
        || count($data['records']) < 1 || count($data['records']) > 200 || !is_array($data['settings'] ?? null)) {
        throw new InvalidArgumentException('Выберите файл регистрации из офлайн-генератора (1–200 сертификатов).');
    }
    $settings = $data['settings'];
    $course = offlineCertificateText($settings['course'] ?? null, 'название курса', 350);
    $city = offlineCertificateText($settings['city'] ?? null, 'город', 50);
    $date = offlineCertificateText($settings['date'] ?? null, 'дата завершения', 10);
    if (!preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $parts)
        || !checkdate((int)$parts[2], (int)$parts[1], (int)$parts[3])
        || (int)$parts[3] < 1900 || (int)$parts[3] > 2100) {
        throw new InvalidArgumentException('В файле некорректная дата завершения обучения.');
    }
    $records = [];
    $numbers = [];
    $tokens = [];
    foreach ($data['records'] as $index => $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Некорректная строка ' . ($index + 1));
        $name = offlineCertificateText($row['fullName'] ?? null, 'ФИО, строка ' . ($index + 1), 160);
        $number = offlineCertificateText($row['certificateNumber'] ?? null, 'номер, строка ' . ($index + 1), 70);
        $token = $row['certificateToken'] ?? null;
        if (!is_string($token) || !preg_match('/^[a-f0-9]{32}$/D', $token)
            || ($row['verifyUrl'] ?? '') !== OFFLINE_CERTIFICATE_VERIFY_BASE . $token) {
            throw new InvalidArgumentException('Некорректный QR-код в строке ' . ($index + 1));
        }
        if (isset($numbers[$number]) || isset($tokens[$token])) {
            throw new InvalidArgumentException('В файле повторяются номера или QR-коды.');
        }
        $numbers[$number] = true;
        $tokens[$token] = true;
        if (($row['course'] ?? '') !== $course || ($row['city'] ?? '') !== $city
            || ($row['completionDate'] ?? '') !== $date) {
            throw new InvalidArgumentException('Данные курса и даты не совпадают в строке ' . ($index + 1));
        }
        foreach (['testPoints' => 20, 'practicalPoints' => 30] as $field => $max) {
            if (!array_key_exists($field, $row) || ($row[$field] !== null
                && (!is_int($row[$field]) || $row[$field] < 0 || $row[$field] > $max))) {
                throw new InvalidArgumentException('Некорректные баллы в строке ' . ($index + 1));
            }
        }
        $level = offlineCertificateLevel($row['testPoints'], $row['practicalPoints']);
        if (($row['level'] ?? '') !== $level) {
            throw new InvalidArgumentException('Категория не соответствует баллам в строке ' . ($index + 1));
        }
        $records[] = [
            'fullName' => $name, 'certificateNumber' => $number, 'certificateToken' => $token,
            'completionDate' => $date, 'course' => $course, 'level' => $level,
            'extra' => json_encode(['source' => 'offline-generator-v1', 'city' => $city,
                'testPoints' => $row['testPoints'], 'practicalPoints' => $row['practicalPoints']],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ];
    }
    return $records;
}

function offlineCertificateImportPlan(PDO $pdo, array $records): array
{
    $current = $pdo->prepare('SELECT certificateNumber FROM Participant WHERE certificateNumber = :n OR certificateToken = :t');
    $legacy = $pdo->prepare('SELECT fullName, certificateNumber, certificateToken, completionDate, course, level, extra
        FROM LegacyCertificate WHERE certificateNumber = :n OR certificateToken = :t');
    $plan = [];
    $counts = ['new' => 0, 'existing' => 0, 'conflict' => 0];
    foreach ($records as $record) {
        $args = [':n' => $record['certificateNumber'], ':t' => $record['certificateToken']];
        $current->execute($args);
        $legacy->execute($args);
        $matches = $legacy->fetchAll(PDO::FETCH_ASSOC);
        $action = 'new';
        $reason = 'Будет зарегистрирован';
        if ($current->fetchColumn() !== false) {
            $action = 'conflict';
            $reason = 'Номер или QR уже занят сертификатом участника портала';
        } elseif ($matches) {
            $same = count($matches) === 1;
            if ($same) {
                // Raw PDO on PostgreSQL returns lowercase names; the normal app remaps them.
                $match = array_change_key_case($matches[0], CASE_LOWER);
                foreach (['fullName', 'certificateNumber', 'certificateToken', 'completionDate', 'course', 'level'] as $field) {
                    if ((string)($match[strtolower($field)] ?? '') !== $record[$field]) $same = false;
                }
                $oldExtra = json_decode((string)($match['extra'] ?? ''), true);
                if (is_array($oldExtra) && ($oldExtra['source'] ?? '') === 'offline-generator-v1'
                    && $oldExtra !== json_decode($record['extra'], true)) $same = false;
            }
            $action = $same ? 'existing' : 'conflict';
            $reason = $same ? 'Уже зарегистрирован, повтор не добавляется' : 'Номер или QR уже зарегистрирован с другими данными';
        }
        $counts[$action]++;
        $plan[] = ['fullName' => $record['fullName'], 'certificateNumber' => $record['certificateNumber'],
            'course' => $record['course'], 'completionDate' => $record['completionDate'],
            'level' => $record['level'], 'action' => $action, 'reason' => $reason];
    }
    return ['counts' => $counts, 'rows' => $plan];
}

function offlineCertificateRegister(PDO $pdo, array $records): array
{
    $pdo->beginTransaction();
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $pdo->exec('LOCK TABLE LegacyCertificate IN SHARE ROW EXCLUSIVE MODE');
        }
        $plan = offlineCertificateImportPlan($pdo, $records);
        if ($plan['counts']['conflict'] > 0) {
            throw new InvalidArgumentException('Есть конфликты. Ни один сертификат не добавлен. Проверьте файл заново.');
        }
        $insert = $pdo->prepare('INSERT INTO LegacyCertificate
            (fullName, certificateNumber, certificateToken, completionDate, course, level, extra)
            VALUES (:fullName, :certificateNumber, :certificateToken, :completionDate, :course, :level, :extra)');
        foreach ($records as $index => $record) {
            if ($plan['rows'][$index]['action'] === 'new') $insert->execute($record);
        }
        $pdo->commit();
        return ['inserted' => $plan['counts']['new'], 'existing' => $plan['counts']['existing']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
