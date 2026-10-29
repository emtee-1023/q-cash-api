# syntax=docker/dockerfile:1.7

ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-fpm-bookworm AS php-base

SHELL ["/bin/bash", "-o", "pipefail", "-c"]

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

FROM php-base AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        nginx \
        supervisor \
    && rm -rf /var/lib/apt/lists/* \
        /etc/nginx/sites-enabled/default \
        /var/www/html

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .

RUN mkdir -p \
        bootstrap/cache \
        storage/app/private \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data bootstrap/cache storage \
    && chmod -R ug+rwX bootstrap/cache storage

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/php/production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint
COPY docker/queue-worker.sh /usr/local/bin/queue-worker
COPY docker/scheduler.sh /usr/local/bin/scheduler

RUN chmod +x /usr/local/bin/docker-entrypoint /usr/local/bin/queue-worker /usr/local/bin/scheduler

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1/up >/dev/null || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
