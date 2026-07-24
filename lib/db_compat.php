<?php

/**
 * Совместимость camelCase-колонок с PostgreSQL.
 *
 * Весь проект написан в расчёте на то, что имена колонок возвращаются
 * из БД ровно в том регистре, в котором написаны в коде (fullName,
 * sessionId, certificateNumber и т.д.) — так работает SQLite.
 *
 * PostgreSQL всегда приводит неэкранированные идентификаторы к нижнему
 * регистру — и при создании таблицы, и при каждом запросе. Экранировать
 * кавычками каждое упоминание колонки в каждом файле проекта — слишком
 * инвазивно и хрупко. Вместо этого: таблицы в Postgres создаются с
 * обычными (не экранированными) именами колонок — Postgres сам сложит
 * их в нижний регистр, — а на выходе из PDO подменяем ключи массива
 * обратно на канонический camelCase по таблице соответствия ниже.
 *
 * Для SQLite ничего из этого не подключается — там всё работает как раньше.
 */

/**
 * Канонические имена колонок (как их ожидает PHP-код) для каждой таблицы.
 * lowercase-версия автоматически используется как ключ соответствия.
 */
function dbCanonicalColumns(): array
{
    return [
        'id', 'sessionId', 'fullName', 'email', 'phone', 'organization',
        'variantId', 'questionOrder', 'optionOrder', 'practicalTaskIds', 'complexityVariantId',
        'passwordHash', 'accountStatus', 'tempPasswordHash', 'tempPasswordExpiresAt',
        'approvedAt', 'rejectedAt', 'rejectionReason', 'mustChangePassword',
        'failedLoginAttempts', 'accountLockedUntil', 'lastLoginAt',
        'passwordResetRequested', 'passwordResetAllowed', 'passwordResetRequestedAt', 'passwordResetAllowedAt',
        'answers', 'score', 'total', 'percent', 'status', 'submittedAt',
        'practicalAnswers', 'practicalSubmittedAt', 'practicalPreviousScore', 'practicalNewScore',
        'practicalMetricsScore', 'practicalScoreTotal', 'practicalGradedAt',
        'certificateNumber', 'certificateToken', 'certificateGeneratedAt', 'certificateEmailedAt',
        'createdAt', 'updatedAt',
        // LegacyCertificate
        'course', 'completionDate', 'level', 'extra',
    ];
}

function dbLowercaseToCanonicalMap(): array
{
    static $map = null;

    if ($map === null) {
        $map = [];

        foreach (dbCanonicalColumns() as $column) {
            $map[strtolower($column)] = $column;
        }
    }

    return $map;
}

function dbRemapRow(array $row): array
{
    $map = dbLowercaseToCanonicalMap();
    $result = [];

    foreach ($row as $key => $value) {
        if (is_int($key)) {
            $result[$key] = $value;
            continue;
        }

        $result[$map[$key] ?? $key] = $value;
    }

    return $result;
}

class CamelCasePDOStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $effectiveMode = $mode === PDO::FETCH_DEFAULT ? PDO::FETCH_ASSOC : $mode;
        $row = parent::fetch($effectiveMode, $cursorOrientation, $cursorOffset);

        if (is_array($row) && $effectiveMode === PDO::FETCH_ASSOC) {
            return dbRemapRow($row);
        }

        return $row;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $effectiveMode = $mode === PDO::FETCH_DEFAULT ? PDO::FETCH_ASSOC : $mode;
        $rows = parent::fetchAll($effectiveMode, ...$args);

        if ($effectiveMode !== PDO::FETCH_ASSOC) {
            return $rows;
        }

        return array_map(
            fn($row) => is_array($row) ? dbRemapRow($row) : $row,
            $rows
        );
    }
}
