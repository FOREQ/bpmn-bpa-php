<?php

require_once __DIR__ . '/../../lib/auth.php';
requireAdmin();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/error_logger.php';

try {
$pdo = getDb();

$stmt = $pdo->query("
    SELECT
        fullName,
        email,
        phone,
        organization,
        variantId,
        score,
        total,
        percent,
        status,
        submittedAt,
        practicalSubmittedAt,
        practicalPreviousScore,
        practicalNewScore,
        practicalMetricsScore,
        practicalScoreTotal,
        practicalGradedAt,
        createdAt
    FROM Participant
    ORDER BY createdAt DESC
");

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'results_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputs($output, "\xEF\xBB\xBF");

fputcsv($output, [
    'ФИО',
    'Email',
    'Телефон',
    'Организация',
    'Вариант теста',
    'Баллы теста',
    'Всего вопросов',
    'Процент',
    'Статус',
    'Дата сдачи теста',
    'Дата сдачи практики',
    'Балл за первое задание',
    'Балл за второе задание',
    'Балл за расчет сложности',
    'Итог практики',
    'Дата проверки практики',
    'Дата регистрации'
], ';');

foreach ($rows as $row) {
    fputcsv($output, [
        $row['fullName'],
        $row['email'],
        $row['phone'],
        $row['organization'],
        $row['variantId'],
        $row['score'],
        $row['total'],
        $row['percent'],
        $row['status'],
        $row['submittedAt'],
        $row['practicalSubmittedAt'],
        $row['practicalPreviousScore'],
        $row['practicalNewScore'],
        $row['practicalMetricsScore'],
        $row['practicalScoreTotal'],
        $row['practicalGradedAt'],
        $row['createdAt'],
    ], ';');
}

fclose($output);
exit;

} catch (Throwable $e) {
    logError($e, 'api/admin/export.php');

    http_response_code(500);
    echo 'Произошла ошибка сервера';
    exit;
}