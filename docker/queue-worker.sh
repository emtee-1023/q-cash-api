#!/usr/bin/env sh

set -eu

cd /var/www/html

until php artisan migrate:status >/dev/null 2>&1; do
    echo "Waiting for database migrations before starting the queue worker..."
    sleep 5
done

exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
