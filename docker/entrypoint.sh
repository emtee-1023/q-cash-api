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

    if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ] && [ "${DB_DATABASE}" != ":memory:" ]; then
        mkdir -p "$(dirname "${DB_DATABASE}")"
        touch "${DB_DATABASE}"
        chown www-data:www-data "$(dirname "${DB_DATABASE}")" "${DB_DATABASE}"
    fi

    chown -R www-data:www-data bootstrap/cache storage
    chmod -R ug+rwX bootstrap/cache storage
fi

php artisan storage:link >/dev/null 2>&1 || true

exec "$@"
