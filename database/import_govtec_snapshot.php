<?php

/**
 * Import a CSV snapshot from govtec.kz/admin into the PostgreSQL legacy table.
 *
 * Usage:
 *   php database/import_govtec_snapshot.php path/to/file.csv --dry-run
 *   php database/import_govtec_snapshot.php path/to/file.csv
 *
 * Missing source tokens stay NULL. Revoked certificates are retained for the
 * historical record, but have no lookup token in this portal.
 */

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require_once __DIR__ . '/../config/db.php';

$file = $argv[1] ?? '';
$dryRun = in_array('--dry-run', $argv, true);

if ($file === '' || !is_file($file) || dbDriver() !== 'pgsql') {
    exit("Usage: DB_DRIVER=pgsql php database/import_govtec_snapshot.php file.csv [--dry-run]\n");
}

$handle = fopen($file, 'r');
if ($handle === false) {
    throw new RuntimeException('Cannot open CSV');
}

$headers = fgetcsv($handle);
$headers[0] = str_replace("\u{FEFF}", '', $headers[0] ?? '');
$required = ['number', 'fullName', 'course', 'completionDate', 'status', 'certificateToken', 'certificateUrl'];
if ($headers !== $required) {
    throw new RuntimeException('Unexpected CSV columns');
}

$rows = [];
$numbers = [];
$tokens = [];
$line = 1;

while (($values = fgetcsv($handle)) !== false) {
    $line++;
    if (count($values) === 1 && trim($values[0]) === '') {
        continue;
    }
    if (count($values) !== count($headers)) {
        throw new RuntimeException("Invalid column count on line {$line}");
    }
    $row = array_combine($headers, array_map('trim', $values));
    if ($row['number'] === '' || $row['fullName'] === '' || $row['course'] === ''
        || $row['completionDate'] === '' || !in_array($row['status'], ['АКТИВЕН', 'ОТОЗВАН'], true)) {
        throw new RuntimeException("Invalid certificate on line {$line}");
    }
    if (isset($numbers[$row['number']])) {
        throw new RuntimeException("Duplicate certificate number on line {$line}");
    }
    $numbers[$row['number']] = true;

    $token = $row['certificateToken'];
    $url = $row['certificateUrl'];
    if ($token !== '') {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $token)
            || $url !== 'https://govtec.kz/certificate/' . $token
            || isset($tokens[$token])) {
            throw new RuntimeException("Invalid or duplicate certificate token on line {$line}");
        }
        $tokens[$token] = true;
    } elseif ($url !== '') {
        throw new RuntimeException("URL without token on line {$line}");
    }
    $rows[] = $row;
}
fclose($handle);

if (!$rows) {
    throw new RuntimeException('CSV has no certificates');
}

$pdo = getDb();
$pdo->beginTransaction();

try {
    $current = [];
    $currentTokens = [];
    foreach ($pdo->query('SELECT certificatenumber, certificatetoken FROM legacycertificate') as $row) {
        $current[$row['certificatenumber']] = $row['certificatetoken'];
        if ($row['certificatetoken'] !== null) {
            $currentTokens[$row['certificatetoken']] = $row['certificatenumber'];
        }
    }
    $participantNumbers = [];
    $participantTokens = [];
    foreach ($pdo->query('SELECT certificatenumber, certificatetoken FROM participant') as $row) {
        if ($row['certificatenumber'] !== null) {
            $participantNumbers[$row['certificatenumber']] = true;
        }
        if ($row['certificatetoken'] !== null) {
            $participantTokens[$row['certificatetoken']] = true;
        }
    }

    $upsert = $pdo->prepare('
        INSERT INTO legacycertificate
            (certificatenumber, certificatetoken, fullname, course, completiondate, level, extra)
        VALUES (?, ?, ?, ?, ?, NULL, ?)
        ON CONFLICT (certificatenumber) DO UPDATE SET
            certificatetoken = CASE
                WHEN EXCLUDED.extra::jsonb ->> \'sourceStatus\' = \'ОТОЗВАН\' THEN NULL
                ELSE COALESCE(EXCLUDED.certificatetoken, legacycertificate.certificatetoken)
            END,
            fullname = EXCLUDED.fullname,
            course = EXCLUDED.course,
            completiondate = EXCLUDED.completiondate,
            extra = EXCLUDED.extra
    ');

    $inserted = 0;
    $updated = 0;
    $activeWithToken = 0;
    $withoutToken = 0;
    $revoked = 0;

    foreach ($rows as $row) {
        $number = $row['number'];
        $sourceToken = $row['certificateToken'] ?: null;
        $publicToken = $row['status'] === 'АКТИВЕН' ? $sourceToken : null;

        if (isset($participantNumbers[$number]) || ($sourceToken !== null && isset($participantTokens[$sourceToken]))) {
            throw new RuntimeException("Certificate conflicts with Participant: {$number}");
        }
        if ($sourceToken !== null && isset($currentTokens[$sourceToken]) && $currentTokens[$sourceToken] !== $number) {
            throw new RuntimeException("Token belongs to another legacy certificate: {$number}");
        }
        if (array_key_exists($number, $current) && $current[$number] !== null
            && $publicToken !== null && $current[$number] !== $publicToken) {
            throw new RuntimeException("Existing certificate has another token: {$number}");
        }

        $extra = json_encode([
            'source' => 'govtec.kz/admin',
            'sourceStatus' => $row['status'],
            'sourceCertificateUrl' => $row['certificateUrl'] ?: null,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $upsert->execute([
            $number,
            $publicToken,
            $row['fullName'],
            $row['course'],
            $row['completionDate'],
            $extra,
        ]);

        array_key_exists($number, $current) ? $updated++ : $inserted++;
        if ($row['status'] === 'ОТОЗВАН') {
            $revoked++;
        } elseif ($publicToken !== null) {
            $activeWithToken++;
        } else {
            $withoutToken++;
        }
    }

    if ($dryRun) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }

    echo json_encode([
        'dryRun' => $dryRun,
        'rows' => count($rows),
        'inserted' => $inserted,
        'updated' => $updated,
        'activeWithToken' => $activeWithToken,
        'activeWithoutToken' => $withoutToken,
        'revoked' => $revoked,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
