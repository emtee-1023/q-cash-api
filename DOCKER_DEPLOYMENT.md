# QCash API Docker Deployment

This repository contains a production-oriented Docker setup for the QCash Laravel API.

Docker Compose manages two services:

- `api` — Nginx + PHP-FPM serving the Laravel application. The same container runs the Laravel
  queue worker and scheduler under Supervisor.
- `db` — PostgreSQL 16 with its data persisted in a Docker volume.

Laravel storage and the PostgreSQL data directory are persisted in Docker volumes.

## Requirements

- Docker Engine 24+ with the Compose plugin
- An `.env` file on the host with the application configuration (never committed to Git)

The image is built with PHP 8.3 and installs the extensions required by this application: `bcmath`,
`intl`, `pcntl`, `pdo_pgsql`, `pgsql`, `mbstring`, `xml`/`simplexml`, `curl`, `openssl`, `ctype`,
`fileinfo`, `tokenizer`, and OPcache. Composer dependencies are installed in a separate build
stage, so the final image does not run Composer at runtime.

## Environment configuration

Docker Compose reads `.env` from the project directory and injects it into the container at runtime.
The `.env` file is excluded by `.dockerignore` and is not baked into the image.

Create or update `.env` before starting the containers:

```bash
cp .env.example .env
```

Generate an `APP_KEY` once, outside the container, and store it in `.env`:

```bash
php artisan key:generate --show
```

The container never runs `key:generate`, so an existing `APP_KEY` is never replaced or regenerated.
For a production deployment, use values similar to:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com
APP_KEY=base64:...

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=q_cash
DB_USERNAME=qcash
DB_PASSWORD=change-me

POSTGRES_DB=q_cash
POSTGRES_USER=qcash
POSTGRES_PASSWORD=change-me

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

QCASH_ML_API_URL=http://host.docker.internal:3004
```

Compose overrides `DB_HOST` with the `db` service name and maps `POSTGRES_DB`, `POSTGRES_USER`, and
`POSTGRES_PASSWORD` onto the application's `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD`. This keeps
the database container credentials and the application connection settings in sync. The
`POSTGRES_PASSWORD` value is required; Compose stops with an error if it is missing.

Important variables:

| Variable | Purpose | Default |
| --- | --- | --- |
| `APP_PORT` | Host port exposed by Docker Compose | `3005` |
| `QCASH_IMAGE` | Image name used by Compose | `qcash-api` |
| `QCASH_IMAGE_TAG` | Image tag used by Compose | `latest` |
| `APP_KEY` | Laravel encryption key; required | none |
| `APP_URL` | Public application URL | `http://localhost:3005` |
| `DB_CONNECTION` | Database driver | `pgsql` |
| `DB_HOST` | Database host inside the Compose network | `db` |
| `DB_PORT` | Database port | `5432` |
| `DB_DATABASE` | Database name; follows `POSTGRES_DB` in Compose | `q_cash` |
| `DB_USERNAME` | Database user; follows `POSTGRES_USER` in Compose | `qcash` |
| `DB_PASSWORD` | Database password; follows `POSTGRES_PASSWORD` in Compose | none |
| `POSTGRES_DB` | Database created by the `db` service | `q_cash` |
| `POSTGRES_USER` | Superuser created by the `db` service | `qcash` |
| `POSTGRES_PASSWORD` | Password for `POSTGRES_USER`; required | none |
| `LOG_CHANNEL` | `stderr` for `docker compose logs`, or `single`/`stack` for file logs | `stderr` |
| `QCASH_ML_API_URL` | Base URL of the separately running Python ML API | empty |

`QCASH_ML_API_URL` is read through `config('services.ml_api.url')`. The ML hostname/address is not
hardcoded. When the ML API runs on the Docker host, use `http://host.docker.internal:3004`; the Compose
files add the Linux `host-gateway` mapping needed for that hostname. If the ML service is reached through
another address, set that address in `QCASH_ML_API_URL`.

## Build the image

Build locally from source:

```bash
docker compose build
```

Or build a tagged image directly:

```bash
docker build -t qcash-api:local .
```

## Run locally

```bash
docker compose up -d
```

Compose starts `db` first and waits for its health check before starting `api`.

The API is available at:

```text
http://localhost:3005
```

The Laravel health endpoint is available at:

```bash
curl http://localhost:3005/up
```

To use a different host port:

```bash
APP_PORT=3006 docker compose up -d
```

Check service health and status:

```bash
docker compose ps
docker inspect --format='{{.State.Health.Status}}' "$(docker compose ps -q api)"
docker inspect --format='{{.State.Health.Status}}' "$(docker compose ps -q db)"
```

The database is not published to the host. To inspect it, open a shell inside the `db` service:

```bash
docker compose exec db psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"
```

## Run migrations

Migrations are intentionally not run automatically. Run them manually after the containers are healthy:

```bash
docker compose exec -u www-data api php artisan migrate --force
```

Use `-u www-data` so files created by Artisan (including storage files) remain owned by the same user
that PHP-FPM uses. Database files are owned by the PostgreSQL container.

PostgreSQL data is stored in the `qcash-db` volume at `/var/lib/postgresql/data`. Laravel storage is
stored in the `qcash-storage` volume. Both volumes survive `docker compose down`; they are only removed
by `docker compose down -v`.

## View logs

Container output, including Nginx, PHP-FPM, queue worker, scheduler, and PostgreSQL output:

```bash
docker compose logs -f api db
```

If `LOG_CHANNEL` is set to `single` or `stack`, Laravel file logs are also available in the persistent
storage volume:

```bash
docker compose exec api tail -f storage/logs/laravel.log
```

## Stop and restart

```bash
docker compose stop          # stop the containers
docker compose start         # start them again
docker compose restart       # restart them
docker compose down          # remove the containers and network, keep volumes
```

Both services use `restart: unless-stopped`, so Docker restarts them automatically after crashes or host
reboots, unless they were explicitly stopped.

## Database and cache notes

The application uses PostgreSQL for data, cache, sessions, and queues. No Redis or external database
service is required. The queue worker and scheduler are managed by Supervisor inside the `api` container
and wait for the migration table to exist before starting.

If you do not want them running, remove or disable the `queue-worker` and `scheduler` programs in
`docker/supervisor/supervisord.conf`.

## Future: deploy a prebuilt image from GitHub Container Registry

`compose.prod.yml` deploys a prebuilt image and has no `build:` section, so the Ubuntu server does not need
the source tree or a source build.

Build and publish an image from a machine or CI runner:

```bash
echo "$GITHUB_TOKEN" | docker login ghcr.io -u <github-user> --password-stdin

docker buildx build \
  --platform linux/amd64 \
  -t ghcr.io/<github-owner>/qcash-api:latest \
  -t ghcr.io/<github-owner>/qcash-api:sha-$(git rev-parse --short HEAD) \
  --push .
```

On the Ubuntu homelab, for example in `/opt/qcash/api`:

```bash
cd /opt/qcash/api

# .env must exist in this directory and must not be committed.
export QCASH_IMAGE=ghcr.io/<github-owner>/qcash-api
export QCASH_IMAGE_TAG=sha-abcdef1

docker compose -f compose.prod.yml pull
docker compose -f compose.prod.yml up -d
docker compose -f compose.prod.yml exec -u www-data api php artisan migrate --force
```

Verify the deployment:

```bash
docker compose -f compose.prod.yml ps
docker inspect --format='{{.State.Health.Status}}' "$(docker compose -f compose.prod.yml ps -q api)"
docker inspect --format='{{.State.Health.Status}}' "$(docker compose -f compose.prod.yml ps -q db)"
curl http://localhost:3005/up
```

The image name and tag are controlled entirely by `QCASH_IMAGE` and `QCASH_IMAGE_TAG`, so a future
GitHub Actions workflow can publish an immutable SHA tag and the server can deploy it without rebuilding.

## Files

```text
Dockerfile                          Multi-stage production image
.dockerignore                       Files excluded from the build context
compose.yml                         Local source-build Compose file (api + db)
compose.prod.yml                    Prebuilt-image Compose file (api + db)
docker/entrypoint.sh                Runtime preparation and process hand-off
docker/nginx/default.conf           Nginx virtual host
docker/php/production.ini           PHP production settings
docker/php/fpm-pool.conf            PHP-FPM environment handling
docker/supervisor/supervisord.conf  PHP-FPM, Nginx, queue, and scheduler processes
docker/queue-worker.sh              Waits for migrations, then starts the queue worker
docker/scheduler.sh                 Waits for migrations, then starts the scheduler
```
