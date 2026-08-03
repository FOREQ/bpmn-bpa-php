<?php

$appTimezone = getenv('APP_TIMEZONE') ?: 'Asia/Qyzylorda';

if (!date_default_timezone_set($appTimezone)) {
    throw new RuntimeException('Invalid APP_TIMEZONE: ' . $appTimezone);
}

require_once __DIR__ . '/../lib/i18n.php';
require_once __DIR__ . '/../lib/db_compat.php';

function getDb(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $config = require __DIR__ . '/database.php';
    $driver = $config['driver'];

    if (!in_array($driver, ['sqlite', 'pgsql'], true)) {
        throw new InvalidArgumentException(
            sprintf('Unsupported DB_DRIVER "%s". Use "sqlite" or "pgsql".', $driver)
        );
    }

    if ($driver === 'pgsql') {
        $c = $config['pgsql'];

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $c['host'],
            $c['port'],
            $c['database']
        );

        $pdo = new PDO($dsn, $c['username'], $c['password']);
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [CamelCasePDOStatement::class]);
    } else {
        $dbPath = $config['sqlite']['path'];

        $pdo = new PDO('sqlite:' . $dbPath);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $pdo;
}

function dbDriver(): string
{
    $config = require __DIR__ . '/database.php';

    return $config['driver'];
}
