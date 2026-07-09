<?php

/**
 * Импорт исторических сертификатов из CSV-файла.
 *
 * Использование:
 *   php database/import_legacy.php path/to/file.csv
 *
 * Excel-файл нужно предварительно сохранить как CSV
 * (Файл -> Сохранить как -> CSV UTF-8).
 *
 * Первая строка файла — заголовки. Распознаются такие колонки
 * (регистр и лишние пробелы не важны):
 *   ФИО / fullName / имя
 *   Номер / номер сертификата / certificateNumber / number
 *   Токен / token / certificateToken / код
 *   Дата / дата завершения / completionDate / date
 *   Курс / course
 *   Уровень / level / категория
 * Прочие колонки сохраняются в поле extra (JSON) и не теряются.
 *
 * Повторный запуск не создаёт дублей: строки сопоставляются по номеру
 * сертификата (или токену) и обновляются.
 */

if (PHP_SAPI !== 'cli') {
    exit("Скрипт запускается только из командной строки\n");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/certificate.php';

$file = $argv[1] ?? '';

if ($file === '' || !is_file($file)) {
    exit("Использование: php database/import_legacy.php path/to/file.csv\n");
}

function normalizeHeader(string $header): string
{
    $header = mb_strtolower(trim($header));
    $header = preg_replace('/\s+/u', ' ', $header);
    // BOM в первом заголовке CSV UTF-8
    return str_replace("\u{FEFF}", '', $header);
}

function mapHeader(string $header): ?string
{
    $map = [
        'fullName' => ['фио', 'ф.и.о.', 'ф.и.о', 'имя', 'fullname', 'full name', 'фио участника'],
        'certificateNumber' => ['номер', 'номер сертификата', '№', '№ сертификата', 'certificatenumber', 'number', 'cert number'],
        'certificateToken' => ['токен', 'token', 'certificatetoken', 'код', 'qr', 'qr код', 'qr-код'],
        'completionDate' => ['дата', 'дата завершения', 'дата выдачи', 'completiondate', 'date'],
        'course' => ['курс', 'course', 'название курса'],
        'level' => ['уровень', 'level', 'категория'],
    ];

    foreach ($map as $field => $aliases) {
        if (in_array($header, $aliases, true)) {
            return $field;
        }
    }

    return null;
}

function generateLegacyToken(PDO $pdo): string
{
    $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    do {
        $token = '';

        for ($i = 0; $i < 12; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $stmt = $pdo->prepare("
            SELECT
                (SELECT COUNT(*) FROM Participant WHERE certificateToken = :t1) +
                (SELECT COUNT(*) FROM LegacyCertificate WHERE certificateToken = :t2)
        ");
        $stmt->execute([':t1' => $token, ':t2' => $token]);
    } while ((int)$stmt->fetchColumn() > 0);

    return $token;
}

$handle = fopen($file, 'r');

if (!$handle) {
    exit("Не удалось открыть файл: {$file}\n");
}

$headers = fgetcsv($handle);

if (!$headers) {
    exit("Файл пуст или не является CSV\n");
}

// Определяем разделитель: если вся строка попала в одну ячейку с ';' — пробуем ;
if (count($headers) === 1 && str_contains($headers[0], ';')) {
    rewind($handle);
    $headers = fgetcsv($handle, 0, ';');
    $delimiter = ';';
} else {
    $delimiter = ',';
}

$columnMap = [];
$extraColumns = [];

foreach ($headers as $index => $header) {
    $normalized = normalizeHeader((string)$header);
    $field = mapHeader($normalized);

    if ($field !== null) {
        $columnMap[$index] = $field;
    } else {
        $extraColumns[$index] = trim((string)$header);
    }
}

if (!in_array('fullName', $columnMap, true)) {
    fclose($handle);
    exit("В файле не найдена колонка с ФИО. Распознанные заголовки: "
        . implode(', ', array_map('strval', $headers)) . "\n");
}

$pdo = getDb();
ensureCertificateColumns($pdo);
ensureLegacyCertificateTable($pdo);

$inserted = 0;
$updated = 0;
$skipped = 0;
$line = 1;

while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
    $line++;

    if (count($row) === 1 && trim((string)$row[0]) === '') {
        continue; // пустая строка
    }

    $record = [
        'fullName' => null,
        'certificateNumber' => null,
        'certificateToken' => null,
        'completionDate' => null,
        'course' => null,
        'level' => null,
    ];
    $extra = [];

    foreach ($row as $index => $value) {
        $value = trim((string)$value);

        if (isset($columnMap[$index])) {
            $record[$columnMap[$index]] = $value !== '' ? $value : null;
        } elseif (isset($extraColumns[$index]) && $value !== '') {
            $extra[$extraColumns[$index]] = $value;
        }
    }

    if (empty($record['fullName'])) {
        echo "строка {$line}: пропущена (нет ФИО)\n";
        $skipped++;
        continue;
    }

    // Ищем существующую запись по номеру, затем по токену
    $existing = null;

    if (!empty($record['certificateNumber'])) {
        $stmt = $pdo->prepare("SELECT id FROM LegacyCertificate WHERE certificateNumber = :n LIMIT 1");
        $stmt->execute([':n' => $record['certificateNumber']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (!$existing && !empty($record['certificateToken'])) {
        $stmt = $pdo->prepare("SELECT id FROM LegacyCertificate WHERE certificateToken = :t LIMIT 1");
        $stmt->execute([':t' => $record['certificateToken']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if (empty($record['certificateToken']) && !$existing) {
        $record['certificateToken'] = generateLegacyToken($pdo);
    }

    $extraJson = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE LegacyCertificate
            SET fullName = :fullName,
                certificateNumber = COALESCE(:certificateNumber, certificateNumber),
                certificateToken = COALESCE(:certificateToken, certificateToken),
                completionDate = :completionDate,
                course = :course,
                level = :level,
                extra = :extra
            WHERE id = :id
        ");
        $stmt->execute([
            ':fullName' => $record['fullName'],
            ':certificateNumber' => $record['certificateNumber'],
            ':certificateToken' => $record['certificateToken'],
            ':completionDate' => $record['completionDate'],
            ':course' => $record['course'],
            ':level' => $record['level'],
            ':extra' => $extraJson,
            ':id' => $existing['id'],
        ]);
        $updated++;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO LegacyCertificate
                (certificateNumber, certificateToken, fullName, course, completionDate, level, extra)
            VALUES
                (:certificateNumber, :certificateToken, :fullName, :course, :completionDate, :level, :extra)
        ");
        $stmt->execute([
            ':certificateNumber' => $record['certificateNumber'],
            ':certificateToken' => $record['certificateToken'],
            ':fullName' => $record['fullName'],
            ':course' => $record['course'],
            ':completionDate' => $record['completionDate'],
            ':level' => $record['level'],
            ':extra' => $extraJson,
        ]);
        $inserted++;
    }
}

fclose($handle);

echo "Готово: добавлено {$inserted}, обновлено {$updated}, пропущено {$skipped}\n";
