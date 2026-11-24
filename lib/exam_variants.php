<?php

/**
 * Варианты теста. A, B, C — встроенные (lib/test_data.php). Каждая генерация «по образцу A»
 * создаёт новый вариант D, E, F… (таблица exam_variant). Студентам он выдаётся только когда
 * одобрены все 20 вопросов, иначе тест получился бы короче.
 */

const EXAM_BUILTIN_VARIANTS = ['A', 'B', 'C'];
const EXAM_VARIANT_SIZE = 20;

function isExamVariantCode(string $code): bool
{
    return (bool)preg_match('/^[A-Z]{1,2}$/', $code);
}

function ensureExamVariantSchema(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $pg = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    $ts = $pg ? 'TIMESTAMP' : 'DATETIME';
    $pdo->exec("CREATE TABLE IF NOT EXISTS exam_variant (
        code TEXT PRIMARY KEY,
        title TEXT,
        created_at $ts NOT NULL DEFAULT CURRENT_TIMESTAMP)");

    // Раньше custom_question.variant разрешал только A, B, C, all — снимаем это ограничение.
    if ($pg) {
        $pdo->exec('ALTER TABLE custom_question DROP CONSTRAINT IF EXISTS custom_question_variant_check');
    } else {
        $sql = (string)$pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'custom_question'")->fetchColumn();
        if (str_contains($sql, "variant IN ('A', 'B', 'C', 'all')")) {
            $columns = array_column($pdo->query('PRAGMA table_info(custom_question)')->fetchAll(PDO::FETCH_ASSOC), 'name');
            $list = implode(', ', $columns);
            $pdo->beginTransaction();
            try {
                $pdo->exec('ALTER TABLE custom_question RENAME TO custom_question_old');
                // $sql — исходный CREATE TABLE custom_question, прочитанный до переименования.
                $pdo->exec(str_replace("CHECK (variant IN ('A', 'B', 'C', 'all'))", "CHECK (variant <> '')", $sql));
                $pdo->exec("INSERT INTO custom_question ($list) SELECT $list FROM custom_question_old");
                $pdo->exec('DROP TABLE custom_question_old');
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
    $checked = true;
}

/** Следующий свободный код после A, B, C: D…Z, затем AA, AB… */
function examNextVariantCode(array $taken): string
{
    $taken = array_flip(array_merge(EXAM_BUILTIN_VARIANTS, $taken));
    foreach (range('D', 'Z') as $code) {
        if (!isset($taken[$code])) {
            return $code;
        }
    }
    foreach (range('A', 'Z') as $first) {
        foreach (range('A', 'Z') as $second) {
            if (!isset($taken[$first . $second])) {
                return $first . $second;
            }
        }
    }
    throw new RuntimeException('Закончились коды вариантов');
}

/** Резервирует новый вариант (две генерации одновременно получат разные коды). */
function examReserveVariant(PDO $pdo, string $title): string
{
    ensureExamVariantSchema($pdo);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $taken = $pdo->query('SELECT code FROM exam_variant')->fetchAll(PDO::FETCH_COLUMN);
        $code = examNextVariantCode($taken);
        try {
            $pdo->prepare('INSERT INTO exam_variant (code, title) VALUES (:c, :t)')->execute([':c' => $code, ':t' => mb_substr($title, 0, 200)]);

            return $code;
        } catch (PDOException) {
            // Код успели занять — берём следующий.
        }
    }
    throw new RuntimeException('Не удалось создать вариант теста');
}

function examCustomVariantExists(PDO $pdo, string $code): bool
{
    if (!isExamVariantCode($code) || in_array($code, EXAM_BUILTIN_VARIANTS, true)) {
        return false;
    }
    ensureExamVariantSchema($pdo);
    $stmt = $pdo->prepare('SELECT 1 FROM exam_variant WHERE code = :c');
    $stmt->execute([':c' => $code]);

    return (bool)$stmt->fetchColumn();
}

/**
 * Все варианты с готовностью.
 * @return list<array{code:string,title:string,builtin:bool,approved:int,pending:int,active:bool,createdAt:?string}>
 */
function examVariants(PDO $pdo): array
{
    ensureExamVariantSchema($pdo);
    $approved = [];
    foreach ($pdo->query('SELECT variant, COUNT(*) AS n FROM custom_question GROUP BY variant')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $approved[$row['variant']] = (int)$row['n'];
    }
    $pending = [];
    $hasDrafts = (bool)$pdo->query(
        $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? "SELECT to_regclass('ai_draft') IS NOT NULL"
            : "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'ai_draft'"
    )->fetchColumn();
    if ($hasDrafts) {
        foreach ($pdo->query("SELECT target, COUNT(*) AS n FROM ai_draft WHERE kind = 'question' AND status = 'pending' GROUP BY target")->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pending[$row['target']] = (int)$row['n'];
        }
    }

    $list = [];
    foreach (EXAM_BUILTIN_VARIANTS as $code) {
        $list[] = ['code' => $code, 'title' => 'Встроенный', 'builtin' => true, 'approved' => $approved[$code] ?? 0,
            'pending' => $pending[$code] ?? 0, 'active' => true, 'createdAt' => null];
    }
    foreach ($pdo->query('SELECT code, title, created_at FROM exam_variant ORDER BY created_at, code')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $count = $approved[$row['code']] ?? 0;
        $list[] = ['code' => $row['code'], 'title' => (string)$row['title'], 'builtin' => false, 'approved' => $count,
            'pending' => $pending[$row['code']] ?? 0, 'active' => $count >= EXAM_VARIANT_SIZE, 'createdAt' => $row['created_at']];
    }

    return $list;
}

/** Коды вариантов, которые можно выдавать студентам. */
function examActiveVariantCodes(PDO $pdo): array
{
    try {
        return array_values(array_map(static fn($v) => $v['code'], array_filter(examVariants($pdo), static fn($v) => $v['active'])));
    } catch (Throwable) {
        return EXAM_BUILTIN_VARIANTS;
    }
}
