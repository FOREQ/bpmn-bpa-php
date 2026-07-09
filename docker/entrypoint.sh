#!/usr/bin/env sh
set -eu

mkdir -p database logs

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if [ ! -f database/database.sqlite ]; then
    php -r '$db = new PDO("sqlite:database/database.sqlite"); $sql = file_get_contents("database/init.sql"); $db->exec($sql); echo "Database created\n";'
fi

exec "$@"
