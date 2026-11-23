<?php

/**
 * Фоновый исполнитель задач ИИ. Запускается из api/admin/ai.php:
 *   php scripts/ai_worker.php <jobId>
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/error_logger.php';
require_once __DIR__ . '/../lib/ai_jobs.php';

set_time_limit(0);
$id = (int)($argv[1] ?? 0);
if ($id <= 0) {
    fwrite(STDERR, "usage: php scripts/ai_worker.php <jobId>\n");
    exit(1);
}

aiJobRun(getDb(), $id);
