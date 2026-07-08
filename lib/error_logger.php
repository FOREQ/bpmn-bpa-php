<?php

function logError(Throwable $e, string $context = ''): void
{
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/errors.log';

    $date = date('Y-m-d H:i:s');

    $line = '[' . $date . ']';

    if ($context !== '') {
        $line .= ' [' . $context . ']';
    }

    $line .= ' ' . $e->getMessage();
    $line .= ' | file: ' . $e->getFile();
    $line .= ' | line: ' . $e->getLine();
    $line .= PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}