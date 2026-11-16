#!/usr/bin/env sh

set -eu

cd /var/www/html

if [ "$(id -u)" = "0" ]; then
    mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs

    chown -R www-data:www-data bootstrap/cache storage
    chmod -R ug+rwX bootstrap/cache storage
fi

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
