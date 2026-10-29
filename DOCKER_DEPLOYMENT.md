# QCash API Docker Deployment

This repository contains a production-oriented Docker setup for the QCash Laravel API.

The application is served by Nginx + PHP-FPM inside a single container. The same container also runs the
Laravel queue worker and scheduler under Supervisor. SQLite data and Laravel storage are persisted in Docker
volumes.

## Requirements

- Docker Engine 24+ with the Compose plugin
- An `.env` file on the host with the application configuration (never committed to Git)

The image is built with PHP 8.3 and installs the extensions required by this application: `bcmath`,
`intl`, `pcntl`, `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`/`simplexml`, `curl`, `openssl`, `ctype`,
`fileinfo`, `tokenizer`, and OPcache. Composer production dependencies are installed in a separate build
stage, so development dependencies are not included in the final image.

## Environment configuration

Docker Compose reads `.env` from the project directory and injects it into the container at runtime.
The `.env` file is excluded by `.dockerignore` and is not baked into the image.

Create or update `.env` before starting the container:

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

DB_CONNECTION=sqlite
DB_DATABASE=/data/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

QCASH_ML_API_URL=http://host.docker.internal:3004
```

Important variables:

| Variable | Purpose | Default |
| --- | --- | --- |
| `APP_PORT` | Host port exposed by Docker Compose | `3005` |
| `QCASH_IMAGE` | Image name used by Compose | `qcash-api` |
| `QCASH_IMAGE_TAG` | Image tag used by Compose | `latest` |
| `APP_KEY` | Laravel encryption key; required | none |
| `APP_URL` | Public application URL | `http://localhost:3005` |
| `DB_CONNECTION` | Database driver; keep `sqlite` for this project | `sqlite` |
| `DB_DATABASE` | SQLite file path inside the container | `/data/database.sqlite` |
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

Check container health and status:

```bash
docker compose ps
docker inspect --format='{{.State.Health.Status}}' "$(docker compose ps -q app)"
```

## Run migrations

Migrations are intentionally not run automatically. Run them manually after the container is healthy:

```bash
docker compose exec -u www-data app php artisan migrate --force
```

Use `-u www-data` so files created by Artisan (including the SQLite database and storage files) remain
owned by the same user that PHP-FPM uses.

The SQLite database is stored in the `qcash-database` volume at `/data/database.sqlite`. Laravel storage
is stored in the `qcash-storage` volume. Both volumes survive `docker compose down`; they are only removed
by `docker compose down -v`.

## View logs

Container output, including Nginx, PHP-FPM, queue worker, and scheduler output:

```bash
docker compose logs -f app
```

If `LOG_CHANNEL` is set to `single` or `stack`, Laravel file logs are also available in the persistent
storage volume:

```bash
docker compose exec app tail -f storage/logs/laravel.log
```

## Stop and restart

```bash
docker compose stop          # stop the container
docker compose start         # start it again
docker compose restart       # restart it
docker compose down          # remove the container and network, keep volumes
```

The service uses `restart: unless-stopped`, so Docker restarts it automatically after crashes or host
reboots, unless it was explicitly stopped.

## Database and cache notes

The application is configured for SQLite and database-backed cache, sessions, and queues. No Redis or
external database service is required. The queue worker and scheduler are managed by Supervisor inside the
application container and wait for the migration table to exist before starting.

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
docker compose -f compose.prod.yml exec -u www-data app php artisan migrate --force
```

Verify the deployment:

```bash
docker compose -f compose.prod.yml ps
docker inspect --format='{{.State.Health.Status}}' "$(docker compose -f compose.prod.yml ps -q app)"
curl http://localhost:3005/up
```

The image name and tag are controlled entirely by `QCASH_IMAGE` and `QCASH_IMAGE_TAG`, so a future
GitHub Actions workflow can publish an immutable SHA tag and the server can deploy it without rebuilding.

## Files

```text
Dockerfile                          Multi-stage production image
.dockerignore                       Files excluded from the build context
compose.yml                         Local source-build Compose file
compose.prod.yml                    Prebuilt-image Compose file
docker/entrypoint.sh                Runtime preparation and process hand-off
docker/nginx/default.conf           Nginx virtual host
docker/php/production.ini           PHP production settings
docker/php/fpm-pool.conf            PHP-FPM environment handling
docker/supervisor/supervisord.conf  PHP-FPM, Nginx, queue, and scheduler processes
docker/queue-worker.sh              Waits for migrations, then starts the queue worker
docker/scheduler.sh                 Waits for migrations, then starts the scheduler
```
