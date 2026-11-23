<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/db.php';

const TARGET_COURSE = 'Практическое применение методики реинжиниринга бизнес-процессов государственных органов';
const EXPECTED_TOTAL = 209;
const EXPECTED_KEEP = 157;
const EXPECTED_REMOVE = 52;

$apply = ($argv[1] ?? '') === '--apply';
$pdo = getDb();
$pdo->beginTransaction();

try {
    $pdo->exec('LOCK TABLE LegacyCertificate IN SHARE ROW EXCLUSIVE MODE');
    $rows = $pdo->query('SELECT * FROM LegacyCertificate ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $keep = count(array_filter($rows, static fn(array $row): bool => ($row['course'] ?? '') === TARGET_COURSE));
    $remove = count($rows) - $keep;

    printf("total=%d keep=%d remove=%d\n", count($rows), $keep, $remove);

    if (count($rows) !== EXPECTED_TOTAL || $keep !== EXPECTED_KEEP || $remove !== EXPECTED_REMOVE) {
        throw new RuntimeException('Состав таблицы изменился: очистка остановлена.');
    }

    if (!$apply) {
        $pdo->rollBack();
        exit;
    }

    $backupPath = __DIR__ . '/legacy_before_cleanup_20260925.json';
    $backup = fopen($backupPath, 'x');
    if ($backup === false) {
        throw new RuntimeException('Не удалось создать новую резервную копию.');
    }

    chmod($backupPath, 0600);
    $payload = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    if (fwrite($backup, $payload) !== strlen($payload)) {
        fclose($backup);
        throw new RuntimeException('Резервная копия записана не полностью.');
    }
    fclose($backup);

    $stmt = $pdo->prepare('DELETE FROM LegacyCertificate WHERE course IS DISTINCT FROM :course');
    $stmt->execute([':course' => TARGET_COURSE]);

    $remaining = (int)$pdo->query('SELECT COUNT(*) FROM LegacyCertificate')->fetchColumn();
    if ($stmt->rowCount() !== EXPECTED_REMOVE || $remaining !== EXPECTED_KEEP) {
        throw new RuntimeException('Итоговые количества не совпали: изменения отменены.');
    }

    $pdo->commit();
    printf("deleted=%d remaining=%d backup=%s sha256=%s\n", $stmt->rowCount(), $remaining, $backupPath, hash_file('sha256', $backupPath));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
