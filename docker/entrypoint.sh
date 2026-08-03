#!/usr/bin/env sh
set -eu

mkdir -p database logs

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ "${DB_DRIVER:-sqlite}" = "pgsql" ]; then
    attempt=1
    until php -r 'require "config/db.php"; $pdo = getDb(); $pdo->exec(file_get_contents("database/init_pgsql.sql")); echo "PostgreSQL schema ensured\n";'; do
        if [ "$attempt" -ge 30 ]; then
            echo "PostgreSQL is still unavailable after 30 attempts" >&2
            exit 1
        fi

        echo "PostgreSQL is not ready yet (attempt $attempt/30); retrying in 2 seconds..." >&2
        attempt=$((attempt + 1))
        sleep 2
    done
elif [ ! -f database/database.sqlite ]; then
    php -r '$db = new PDO("sqlite:database/database.sqlite"); $sql = file_get_contents("database/init.sql"); $db->exec($sql); echo "Database created\n";'
fi

exec "$@"
