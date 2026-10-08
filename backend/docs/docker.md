# Docker operations

## Local configuration

Copy `.env.docker.example` to the ignored `.env.docker` file. Set `APP_KEY` to a Laravel-compatible `base64:` key, set a non-empty `DB_PASSWORD`, and add `GEMINI_API_KEY` when real AI calls are required.

Generate values without printing them into repository files:

```bash
php artisan key:generate --show
openssl rand -hex 24
```

## Start

```bash
docker compose --env-file .env.docker up -d --build
docker compose --env-file .env.docker ps
curl --fail http://127.0.0.1:8000/up
```

Open `http://127.0.0.1:8000` after the web health check passes. To test from a phone on the same trusted LAN, set `APP_BIND_ADDRESS=0.0.0.0`, set `APP_URL` to the computer's LAN address, and use that address on the phone. Return to `127.0.0.1` when LAN access is no longer needed.

## Tests

```bash
docker compose --env-file .env.docker --profile test run --rm php-tests
docker compose --env-file .env.docker --profile test run --rm js-tests
```

## Logs and status

```bash
docker compose --env-file .env.docker ps
docker compose --env-file .env.docker logs --tail=200 app worker web db
```

## PostgreSQL backup

```bash
docker compose --env-file .env.docker exec -T db \
  sh -lc 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --format=custom' \
  > halalscan-postgres.dump
```

The database identifiers are read inside the PostgreSQL container. Keep the resulting dump outside the repository.

## Stop without deleting data

```bash
docker compose --env-file .env.docker down
```

## Remove an isolated local stack

The next command permanently deletes the Compose project's PostgreSQL and storage volumes. Use it only for a disposable project name whose data is no longer needed:

```bash
COMPOSE_PROJECT_NAME=halalscan_disposable \
docker compose --env-file .env.docker down --volumes
```

## Staging configuration check

Create an ignored `.env.staging` from `.env.docker.example`. Set `APP_ENV=staging`, `APP_DEBUG=false`, an HTTPS `APP_URL`, `APP_DOMAIN`, `ACME_EMAIL`, fresh Laravel and PostgreSQL credentials, and the staging Gemini key.

Validate before changing a server:

```bash
docker compose \
  -f compose.yaml \
  -f compose.staging.yaml \
  --env-file .env.staging \
  config --quiet
```

After DNS points to the VPS, start staging:

```bash
docker compose \
  -f compose.yaml \
  -f compose.staging.yaml \
  --env-file .env.staging \
  up -d --build
```

Confirm `/up`, the homepage, and the three API routes before building the Android staging APK.
