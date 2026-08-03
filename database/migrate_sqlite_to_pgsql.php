<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this script from the command line.\n");
    exit(1);
}

$sqlitePath = getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/database.sqlite');

if (!is_file($sqlitePath)) {
    fwrite(STDERR, "SQLite database not found: {$sqlitePath}\n");
    exit(1);
}

$requiredExtensions = ['pdo_sqlite', 'pdo_pgsql'];

foreach ($requiredExtensions as $extension) {
    if (!extension_loaded($extension)) {
        fwrite(STDERR, "Required PHP extension is not enabled: {$extension}\n");
        exit(1);
    }
}

$pgHost = getenv('DB_HOST') ?: '127.0.0.1';
$pgPort = getenv('DB_PORT') ?: '5432';
$pgName = getenv('DB_NAME') ?: 'bpmn';
$pgUser = getenv('DB_USER') ?: 'postgres';
$pgPassword = getenv('DB_PASSWORD') ?: '';

$sqlite = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pgsql = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $pgHost, $pgPort, $pgName),
    $pgUser,
    $pgPassword,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$pgsql->exec((string) file_get_contents(__DIR__ . '/init_pgsql.sql'));

function pgIdentifier(string $identifier): string
{
    $identifier = strtolower($identifier);

    if (!preg_match('/^[a-z_][a-z0-9_]*$/', $identifier)) {
        throw new RuntimeException("Unsafe database identifier: {$identifier}");
    }

    return '"' . $identifier . '"';
}

function hasNonBlankFields(array $row, array $requiredColumns): bool
{
    foreach ($requiredColumns as $column) {
        $value = $row[$column] ?? null;

        if (!is_scalar($value) || trim((string) $value) === '') {
            return false;
        }
    }

    return true;
}

function migrateTable(PDO $sqlite, PDO $pgsql, string $table, array $requiredColumns = []): array
{
    $sourceColumns = $sqlite->query('PRAGMA table_info(' . $table . ')')->fetchAll();

    if ($sourceColumns === []) {
        return ['copied' => 0, 'skipped' => 0];
    }

    $columns = array_column($sourceColumns, 'name');
    $targetColumns = array_map('strtolower', $columns);
    $typeQuery = $pgsql->prepare(
        'SELECT column_name, data_type, is_nullable FROM information_schema.columns '
        . 'WHERE table_schema = current_schema() AND table_name = :table'
    );
    $typeQuery->execute(['table' => strtolower($table)]);
    $targetMetadata = [];

    foreach ($typeQuery->fetchAll() as $columnInfo) {
        $targetMetadata[$columnInfo['column_name']] = $columnInfo;
    }

    $emptyAsNullTypes = [
        'bigint',
        'boolean',
        'date',
        'double precision',
        'integer',
        'numeric',
        'real',
        'smallint',
        'timestamp with time zone',
        'timestamp without time zone',
    ];
    $quotedColumns = array_map('pgIdentifier', $targetColumns);
    $placeholders = array_map(static fn(string $column): string => ':' . $column, $targetColumns);
    $updates = [];

    foreach ($quotedColumns as $quotedColumn) {
        if ($quotedColumn !== '"id"') {
            $updates[] = $quotedColumn . ' = EXCLUDED.' . $quotedColumn;
        }
    }

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT ("id") DO UPDATE SET %s',
        pgIdentifier($table),
        implode(', ', $quotedColumns),
        implode(', ', $placeholders),
        implode(', ', $updates)
    );
    $insert = $pgsql->prepare($sql);
    $rows = $sqlite->query('SELECT * FROM ' . $table);
    $count = 0;
    $skipped = 0;

    while ($row = $rows->fetch()) {
        if (!hasNonBlankFields($row, $requiredColumns)) {
            $skipped++;
            continue;
        }

        $params = [];

        foreach ($columns as $index => $column) {
            $targetColumn = $targetColumns[$index];
            $value = $row[$column];
            $metadata = $targetMetadata[$targetColumn] ?? [];
            $targetType = $metadata['data_type'] ?? '';

            if (
                ($value === '' || $value === null)
                && in_array($targetType, $emptyAsNullTypes, true)
            ) {
                if (($metadata['is_nullable'] ?? 'YES') === 'YES') {
                    $value = null;
                } elseif ($targetType === 'date') {
                    $value = date('Y-m-d');
                } elseif (str_starts_with($targetType, 'timestamp')) {
                    $value = date('Y-m-d H:i:s');
                } elseif ($targetType === 'boolean') {
                    $value = false;
                } else {
                    $value = 0;
                }
            }

            $params[$targetColumn] = $value;
        }

        $insert->execute($params);
        $count++;
    }

    return ['copied' => $count, 'skipped' => $skipped];
}

$pgsql->beginTransaction();

try {
    $participantResult = migrateTable(
        $sqlite,
        $pgsql,
        'Participant',
        ['id', 'sessionId', 'fullName', 'email', 'phone', 'organization', 'variantId']
    );
    $legacyResult = migrateTable($sqlite, $pgsql, 'LegacyCertificate', ['id', 'fullName']);

    $pgsql->exec(
        "SELECT setval(pg_get_serial_sequence('legacycertificate', 'id'), "
        . "COALESCE((SELECT MAX(id) FROM legacycertificate), 1), "
        . "EXISTS(SELECT 1 FROM legacycertificate))"
    );

    $pgsql->commit();
} catch (Throwable $error) {
    $pgsql->rollBack();
    throw $error;
}

echo "Migration completed.\n";
echo "Participant rows copied: {$participantResult['copied']}\n";
echo "Participant rows skipped as invalid: {$participantResult['skipped']}\n";
echo "LegacyCertificate rows copied: {$legacyResult['copied']}\n";
echo "LegacyCertificate rows skipped as invalid: {$legacyResult['skipped']}\n";
