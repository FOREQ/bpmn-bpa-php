<?php

/**
 * Настройки подключения к базе данных.
 *
 * По умолчанию используется локальный SQLite-файл (как раньше) — так
 * проект продолжает работать «из коробки» через XAMPP без какой-либо
 * дополнительной настройки.
 *
 * Чтобы подключиться к PostgreSQL (прод), задайте переменные окружения
 * (например, в docker-compose.yml через `environment:`):
 *
 *   DB_DRIVER=pgsql
 *   DB_HOST=...
 *   DB_PORT=5432
 *   DB_NAME=...
 *   DB_USER=...
 *   DB_PASSWORD=...
 */

return [
    'driver' => getenv('DB_DRIVER') ?: 'sqlite',

    'sqlite' => [
        'path' => getenv('DB_SQLITE_PATH') ?: (__DIR__ . '/../database/database.sqlite'),
    ],

    'pgsql' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '5432',
        'database' => getenv('DB_NAME') ?: 'bpmn',
        'username' => getenv('DB_USER') ?: 'postgres',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
];
