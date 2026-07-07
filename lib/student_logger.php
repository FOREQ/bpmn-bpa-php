<?php

function writeStudentLog(string $action, string $details = ''): void
{
    $logDir = __DIR__ . '/../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $logFile = $logDir . '/student.log';

    $date = date('Y-m-d H:i:s');

    $line = '[' . $date . '] ' . $action;

    if ($details !== '') {
        $line .= ' | ' . $details;
    }

    $line .= PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}