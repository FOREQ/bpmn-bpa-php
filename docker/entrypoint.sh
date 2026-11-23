#!/usr/bin/env sh
set -eu

mkdir -p database logs

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ "${DB_DRIVER:-sqlite}" = "pgsql" ]; then
    php -r 'require "config/db.php"; $pdo = getDb(); $pdo->exec(file_get_contents("database/init_pgsql.sql")); echo "PostgreSQL schema ensured\n";'
elif [ ! -f database/database.sqlite ]; then
    php -r '$db = new PDO("sqlite:database/database.sqlite"); $sql = file_get_contents("database/init.sql"); $db->exec($sql); echo "Database created\n";'
fi

exec "$@"
