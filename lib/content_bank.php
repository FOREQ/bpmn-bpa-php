<?php

/** Дополнительные материалы курса, созданные администратором. */

require_once __DIR__ . '/i18n.php';

function ensureCustomContentSchema(PDO $pdo): void
{
    static $checked = false;
    if ($checked || $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_question (
        id INTEGER PRIMARY KEY AUTOINCREMENT, variant TEXT NOT NULL,
        question_text TEXT NOT NULL, options_json TEXT NOT NULL,
        correct_index INTEGER NOT NULL, image_path TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_practical_task (
        id INTEGER PRIMARY KEY AUTOINCREMENT, pool TEXT NOT NULL,
        title TEXT, description TEXT NOT NULL, reference_file TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP)");

    foreach (['custom_question' => ['question_text_kk', 'options_json_kk'],
              'custom_practical_task' => ['title_kk', 'description_kk']] as $table => $columns) {
        $existing = array_column($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($columns as $column) {
            if (!in_array($column, $existing, true)) {
                $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' TEXT');
            }
        }
    }
    $checked = true;
}

function testVariantWithCustom(PDO $pdo, string $variantId): ?array
{
    global $TEST_DATA;

    ensureCustomContentSchema($pdo);

    if (isset($TEST_DATA[$variantId])) {
        $questions = $TEST_DATA[$variantId];
    } else {
        // Сгенерированный вариант (D, E…): все вопросы — из custom_question.
        require_once __DIR__ . '/exam_variants.php';
        if (!examCustomVariantExists($pdo, $variantId)) {
            return null;
        }
        $questions = [];
    }
    $stmt = $pdo->prepare("SELECT id, question_text, question_text_kk, options_json, options_json_kk,
        correct_index, image_path
        FROM custom_question WHERE variant = :variant OR variant = 'all' ORDER BY id");
    $stmt->execute([':variant' => $variantId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $kazakh = i18nLocale() === 'kk' && !empty($row['question_text_kk']) && !empty($row['options_json_kk']);
        $options = json_decode($kazakh ? $row['options_json_kk'] : $row['options_json'], true, 512, JSON_THROW_ON_ERROR);
        $questions[] = [
            'id' => 100000 + (int)$row['id'],
            'question' => $kazakh ? $row['question_text_kk'] : $row['question_text'],
            'options' => $options,
            'correct' => (int)$row['correct_index'],
            'image' => $row['image_path'] ?: null,
        ];
    }

    return $questions;
}

/** Keep the certificate's test weight at 20 points when the question bank grows. */
function assignedTestQuestions(array $questions): array
{
    $custom = [];
    $builtIn = [];
    foreach ($questions as $question) {
        if ((int)$question['id'] >= 100000) {
            $custom[] = $question;
        } else {
            $builtIn[] = $question;
        }
    }

    shuffle($custom);
    shuffle($builtIn);
    $selected = array_slice(array_merge($custom, $builtIn), 0, 20);
    shuffle($selected);

    return $selected;
}

function customPracticalTasks(PDO $pdo, string $pool): array
{
    ensureCustomContentSchema($pdo);
    $stmt = $pdo->prepare('SELECT id, title, title_kk, description, description_kk, reference_file FROM custom_practical_task WHERE pool = :pool ORDER BY id');
    $stmt->execute([':pool' => $pool]);

    return array_map(static fn(array $row): array => [
        'id' => 'custom-' . $row['id'],
        'title' => i18nLocale() === 'kk' && !empty($row['title_kk']) ? $row['title_kk'] : ($row['title'] ?: ''),
        'description' => i18nLocale() === 'kk' && !empty($row['description_kk']) ? $row['description_kk'] : $row['description'],
        'referenceBpmn' => $row['reference_file'] ?: null,
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

function customPracticalTask(PDO $pdo, string $id): ?array
{
    ensureCustomContentSchema($pdo);
    if (!preg_match('/^custom-([1-9][0-9]*)$/', $id, $matches)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, title, title_kk, description, description_kk, reference_file FROM custom_practical_task WHERE id = :id');
    $stmt->execute([':id' => $matches[1]]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? [
        'id' => 'custom-' . $row['id'],
        'title' => i18nLocale() === 'kk' && !empty($row['title_kk']) ? $row['title_kk'] : ($row['title'] ?: ''),
        'description' => i18nLocale() === 'kk' && !empty($row['description_kk']) ? $row['description_kk'] : $row['description'],
        'referenceBpmn' => $row['reference_file'] ?: null,
    ] : null;
}
