<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/content_bank.php';
require_once __DIR__ . '/../../lib/test_data.php';
require_once __DIR__ . '/../../lib/practical_data.php';
require_once __DIR__ . '/../../lib/error_logger.php';

header('Content-Type: application/json; charset=utf-8');

function contentResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function contentLength(string $value, int $maximum): bool
{
    return $value !== '' && strlen($value) <= $maximum;
}

function contentText(string $ru, string $kk): string
{
    return i18nLocale() === 'kk' ? $kk : $ru;
}

function storeQuestionImage(string $field, array &$storedFiles): ?string
{
    $file = $_FILES[$field] ?? null;
    if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2_000_000 || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException(contentText('Изображение не загружено или превышает 2 МБ', 'Сурет жүктелмеді немесе көлемі 2 МБ-тан асады'));
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException(contentText('Допустимы только изображения PNG, JPEG и WebP', 'Тек PNG, JPEG және WebP суреттерін жүктеуге болады'));
    }

    $directory = __DIR__ . '/../../assets/custom_questions';
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        throw new RuntimeException('Could not create image directory');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $path = $directory . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Could not store image');
    }
    chmod($path, 0644);
    $storedFiles[] = $path;

    return '../assets/custom_questions/' . $name;
}

function storeReferenceBpmn(array &$storedFiles): ?string
{
    $file = $_FILES['reference_bpmn'] ?? null;
    if ($file === null || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2_000_000 || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException(contentText('Файл BPMN не загружен или превышает 2 МБ', 'BPMN файлы жүктелмеді немесе көлемі 2 МБ-тан асады'));
    }

    $xml = file_get_contents($file['tmp_name']);
    if ($xml === false || stripos($xml, '<!DOCTYPE') !== false || stripos($xml, '<!ENTITY') !== false) {
        throw new InvalidArgumentException(contentText('Некорректный файл BPMN', 'BPMN файлы дұрыс емес'));
    }
    $document = new DOMDocument();
    if (!@$document->loadXML($xml, LIBXML_NONET) || $document->documentElement?->localName !== 'definitions'
        || $document->documentElement?->namespaceURI !== 'http://www.omg.org/spec/BPMN/20100524/MODEL') {
        throw new InvalidArgumentException(contentText('Файл должен содержать BPMN-диаграмму', 'Файлда BPMN-диаграмма болуы керек'));
    }

    $directory = __DIR__ . '/../../assets/reference_bpmn';
    $name = 'custom-' . bin2hex(random_bytes(16)) . '.bpmn';
    $path = $directory . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $path)) {
        throw new RuntimeException('Could not store BPMN diagram');
    }
    chmod($path, 0644);
    $storedFiles[] = $path;

    return $name;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $pdo = getDb();
        ensureCustomContentSchema($pdo);
        $customQuestions = $pdo->query('SELECT id, variant, question_text, question_text_kk, options_json, options_json_kk, correct_index, image_path, created_at FROM custom_question ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($customQuestions as &$question) {
            $question['source'] = 'custom';
            $question['options'] = json_decode($question['options_json'], true, 512, JSON_THROW_ON_ERROR);
            $question['options_kk'] = $question['options_json_kk']
                ? json_decode($question['options_json_kk'], true, 512, JSON_THROW_ON_ERROR)
                : $question['options'];
            unset($question['options_json'], $question['options_json_kk']);
        }
        unset($question);
        $questions = array_map(static fn(array $question): array => [
            'id' => (int)$question['id'],
            'source' => 'builtin',
            'variant' => 'A',
            'question_text' => $question['question'],
            'question_text_kk' => null,
            'options' => $question['options'],
            'options_kk' => $question['options'],
            'correct_index' => (int)$question['correct'],
            'image_path' => $question['image'] ?? null,
        ], $TEST_DATA['A']);
        array_push($questions, ...$customQuestions);
        $firstTask = getPracticalTask('family-2');
        $secondTask = getPracticalTask('101');
        $complexityTask = getComplexityVariant('1');
        // These are the three examples shown for a participant with test variant A.
        // Assignment still draws practical tasks independently of the test variant.
        $originalTasks = [
            [
                'id' => $firstTask['id'], 'source' => 'builtin', 'variant' => 'A', 'pool' => 'first',
                'title' => 'Задача 1', 'title_kk' => '1-тапсырма',
                'description' => $firstTask['description'], 'description_kk' => null,
                'reference_file' => null,
            ],
            [
                'id' => $secondTask['id'], 'source' => 'builtin', 'variant' => 'A', 'pool' => 'second',
                'title' => 'Задача 2', 'title_kk' => '2-тапсырма',
                'description' => $secondTask['description'], 'description_kk' => null,
                'reference_file' => $secondTask['referenceBpmn'],
            ],
            [
                'id' => 'complexity-1', 'source' => 'builtin', 'variant' => 'A', 'pool' => 'complexity',
                'title' => 'Расчёт сложности', 'title_kk' => 'Күрделілікті есептеу',
                'description' => 'Заполните показатели сложности бизнес-процесса согласно диаграмме.',
                'description_kk' => 'Диаграммаға сәйкес бизнес-процестің күрделілік көрсеткіштерін толтырыңыз.',
                'reference_file' => null,
                'image_path' => '../assets/complexity/' . $complexityTask['image'],
                'fields' => $complexityTask['fields'],
            ],
        ];
        $customTasks = $pdo->query('SELECT id, pool, title, title_kk, description, description_kk, reference_file, created_at FROM custom_practical_task ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $tasks = array_merge($originalTasks, $customTasks);
        contentResponse(['success' => true, 'questions' => $questions, 'tasks' => $tasks]);
    } catch (Throwable $e) {
        logError($e, 'api/admin/content.php GET');
        contentResponse(['success' => false, 'message' => contentText('Не удалось загрузить материалы', 'Материалдарды жүктеу мүмкін болмады')], 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    contentResponse(['success' => false, 'message' => contentText('Метод не поддерживается', 'Бұл әдіске қолдау көрсетілмейді')], 405);
}

if (!csrfVerify($_POST['csrf_token'] ?? null)) {
    contentResponse(['success' => false, 'message' => contentText('Ошибка безопасности. Обновите страницу.', 'Қауіпсіздік қатесі. Бетті жаңартыңыз.')], 403);
}

$kind = $_POST['kind'] ?? '';
if (!in_array($kind, ['question', 'practical'], true)) {
    contentResponse(['success' => false, 'message' => contentText('Неизвестный тип материала', 'Материал түрі белгісіз')], 400);
}

$storedFiles = [];
$pdo = null;

try {
    $pdo = getDb();
    ensureCustomContentSchema($pdo);
    $pdo->beginTransaction();

    if ($kind === 'question') {
        $variant = trim((string)($_POST['variant'] ?? ''));
        $questionText = trim((string)($_POST['question'] ?? ''));
        $questionTextKk = trim((string)($_POST['question_kk'] ?? ''));
        $correct = filter_var($_POST['correct'] ?? null, FILTER_VALIDATE_INT);
        if (!in_array($variant, ['A', 'B', 'C', 'all'], true)
            || !contentLength($questionText, 4000)
            || !contentLength($questionTextKk, 4000)
            || $correct === false || $correct < 0 || $correct > 3) {
            throw new InvalidArgumentException(contentText('Заполните вариант, вопрос на двух языках и правильный ответ', 'Нұсқаны, сұрақты екі тілде және дұрыс жауапты толтырыңыз'));
        }

        $image = storeQuestionImage('question_image', $storedFiles);
        $options = [];
        $optionsKk = [];
        for ($index = 0; $index < 4; $index++) {
            $text = trim((string)($_POST['option_' . $index] ?? ''));
            $textKk = trim((string)($_POST['option_kk_' . $index] ?? ''));
            if (!contentLength($text, 1200) || !contentLength($textKk, 1200)) {
                throw new InvalidArgumentException(contentText('Заполните четыре ответа на двух языках', 'Төрт жауапты екі тілде толтырыңыз'));
            }
            $optionImage = storeQuestionImage('option_image_' . $index, $storedFiles);
            $options[] = $optionImage ? ['text' => $text, 'image' => $optionImage] : $text;
            $optionsKk[] = $optionImage ? ['text' => $textKk, 'image' => $optionImage] : $textKk;
        }

        $sql = 'INSERT INTO custom_question (variant, question_text, question_text_kk, options_json, options_json_kk, correct_index, image_path)
            VALUES (:variant, :question, :question_kk, :options, :options_kk, :correct, :image)';
        if (dbDriver() === 'pgsql') {
            $sql .= ' RETURNING id';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':variant' => $variant,
            ':question' => $questionText,
            ':question_kk' => $questionTextKk,
            ':options' => json_encode($options, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':options_kk' => json_encode($optionsKk, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ':correct' => $correct,
            ':image' => $image,
        ]);
        $id = dbDriver() === 'pgsql' ? (int)$stmt->fetchColumn() : (int)$pdo->lastInsertId();
        writeAdminLog('custom_question_created', 'id=' . $id . '; variant=' . $variant);
    } else {
        $pool = trim((string)($_POST['pool'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        $titleKk = trim((string)($_POST['title_kk'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $descriptionKk = trim((string)($_POST['description_kk'] ?? ''));
        if (($title === '') !== ($titleKk === '')) {
            throw new InvalidArgumentException(contentText('Укажите название на обоих языках или оставьте оба поля пустыми', 'Атауды екі тілде жазыңыз немесе екі өрісті де бос қалдырыңыз'));
        }
        if (!in_array($pool, ['first', 'second'], true)
            || strlen($title) > 240 || strlen($titleKk) > 240
            || !contentLength($description, 16000) || !contentLength($descriptionKk, 16000)
            || strlen($description) < 20 || strlen($descriptionKk) < 20) {
            throw new InvalidArgumentException(contentText('Заполните тип и описание задания на двух языках', 'Тапсырма түрі мен сипаттамасын екі тілде толтырыңыз'));
        }
        if ($pool === 'first' && isset($_FILES['reference_bpmn']) && $_FILES['reference_bpmn']['error'] !== UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException(contentText('Эталонная BPMN-схема доступна для второго задания', 'Үлгі BPMN-схема тек екінші тапсырмаға тіркеледі'));
        }
        $reference = storeReferenceBpmn($storedFiles);

        $sql = 'INSERT INTO custom_practical_task (pool, title, title_kk, description, description_kk, reference_file)
            VALUES (:pool, :title, :title_kk, :description, :description_kk, :reference)';
        if (dbDriver() === 'pgsql') {
            $sql .= ' RETURNING id';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':pool' => $pool,
            ':title' => $title,
            ':title_kk' => $titleKk,
            ':description' => $description,
            ':description_kk' => $descriptionKk,
            ':reference' => $reference,
        ]);
        $id = dbDriver() === 'pgsql' ? (int)$stmt->fetchColumn() : (int)$pdo->lastInsertId();
        writeAdminLog('custom_practical_created', 'id=' . $id . '; pool=' . $pool);
    }

    $pdo->commit();
    contentResponse(['success' => true, 'id' => $id, 'message' => contentText('Материал добавлен для новых участников', 'Материал жаңа қатысушылар үшін қосылды')]);
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($storedFiles as $path) {
        @unlink($path);
    }
    if ($e instanceof InvalidArgumentException) {
        contentResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
    logError($e, 'api/admin/content.php POST');
    contentResponse(['success' => false, 'message' => contentText('Не удалось сохранить материал', 'Материалды сақтау мүмкін болмады')], 500);
}
