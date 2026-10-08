# Backend Docker and PostgreSQL Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Produce a portable, production-oriented Docker Compose stack for the existing Laravel backend, with PostgreSQL persistence, repeatable migrations, containerized tests, and a provider-neutral HTTPS staging overlay.

**Architecture:** Build immutable PHP-FPM and Nginx images from one multi-stage Dockerfile. Compose orchestrates `web`, `app`, `worker`, one-shot `migrate`, and PostgreSQL services; test-only services reuse dedicated Dockerfile targets. A staging overlay adds Caddy TLS termination without exposing PostgreSQL.

**Tech Stack:** Laravel 13, PHP 8.5 FPM Alpine, Node.js 22 Alpine, Composer 2, Nginx unprivileged Alpine, PostgreSQL 17 Alpine, Caddy 2 Alpine, Docker Compose v2.

**Spec:** `docs/superpowers/specs/2026-09-25-container-android-staging-design.md`

## Global Constraints

- Preserve local non-Docker SQLite development; Docker uses PostgreSQL through Laravel's existing `pgsql` connection.
- Use PHP 8.5, Node.js 22, Composer 2, and PostgreSQL 17.
- Do not add Redis; database-backed queue, cache, and sessions use PostgreSQL.
- Local Docker binds the web service to `127.0.0.1:8000` by default.
- PostgreSQL must have no host port mapping.
- Secrets enter at runtime only; never use secret-valued Docker build arguments or copy an environment file into an image.
- Runtime logs go to stderr/stdout and `APP_DEBUG=false` is mandatory for staging.
- The existing `/up` route is the HTTP health endpoint.
- The Nginx request limit must admit Laravel's 8 MiB ingredient image while rejecting materially larger requests; use 10 MiB.
- Do not migrate the current SQLite file, local sessions, or local test user into PostgreSQL automatically.

## Review Focus

- Missing `APP_KEY` or `DB_PASSWORD` must make Compose interpolation fail before a partial stack starts (Task 3 contract test and `docker compose config` check).
- PostgreSQL startup delay must be absorbed by `service_healthy`, and migration failure must block `app` and `worker` (Task 3 contract test and cold-start smoke test).
- Empty named storage volumes must remain writable by the non-root PHP runtime (Task 2 image test and Task 5 cold-start upload-directory check).
- Nginx and PHP upload limits must agree so an 8 MiB application-valid image is not rejected at the proxy (Tasks 2 and 3 contract tests).
- PostgreSQL data must survive a normal Compose stop/start while an explicitly isolated test stack can be removed safely afterward (Task 5 persistence test).

---

### Task 1: Checkpoint the approved browser baseline

**Files:**
- Commit existing modifications: `backend/package.json`, `backend/package-lock.json`, `backend/resources/css/app.css`, `backend/resources/js/app.js`, `backend/resources/js/barcode-scanner.js`, `backend/resources/js/ingredient-analysis-workflow.js`, `backend/resources/views/welcome.blade.php`, `backend/tests/Feature/Web/ScannerPageTest.php`, `backend/tests/JavaScript/barcode-scanner.test.js`, `backend/tests/JavaScript/ingredient-analysis-workflow.test.js`

**Interfaces:**
- Consumes: the already approved browser camera scanning and automatic ingredient-photo workflow.
- Produces: a clean feature checkpoint that Docker work can build without mixing unrelated application changes into infrastructure commits.

- [ ] **Step 1: Re-run the complete baseline verification**

Run from `backend/`:

```bash
php artisan test --compact
node --test tests/JavaScript/*.test.js
npm run build
./vendor/bin/pint --format agent
composer validate --no-check-publish
npm audit --audit-level=high
```

Expected: 68 PHP tests and 11 JavaScript tests pass, Vite completes a production build, Pint reports success, Composer validates, and npm reports no high-severity vulnerability.

- [ ] **Step 2: Confirm the checkpoint contains only the approved browser work**

Run from the repository root:

```bash
git status --short
git diff --check
git diff -- backend/package.json backend/resources/js/app.js backend/resources/views/welcome.blade.php
```

Expected: only the listed browser feature files are uncommitted, and `git diff --check` has no output.

- [ ] **Step 3: Commit the browser feature checkpoint**

```bash
git add \
  backend/package.json \
  backend/package-lock.json \
  backend/resources/css/app.css \
  backend/resources/js/app.js \
  backend/resources/js/barcode-scanner.js \
  backend/resources/js/ingredient-analysis-workflow.js \
  backend/resources/views/welcome.blade.php \
  backend/tests/Feature/Web/ScannerPageTest.php \
  backend/tests/JavaScript/barcode-scanner.test.js \
  backend/tests/JavaScript/ingredient-analysis-workflow.test.js
git commit -m "feat: add camera scanning and automatic photo analysis"
```

Expected: the commit contains only the approved baseline files.

---

### Task 2: Build immutable application, test, and web image targets

**Files:**
- Create: `backend/tests/Feature/Infrastructure/DockerConfigurationTest.php`
- Create: `backend/Dockerfile`
- Create: `backend/.dockerignore`
- Create: `backend/docker/php/opcache.ini`
- Create: `backend/docker/php/uploads.ini`

**Interfaces:**
- Consumes: `backend/composer.lock`, `backend/package-lock.json`, Laravel's existing `public/`, `resources/`, and test suites.
- Produces: Docker targets named `php-test`, `js-test`, `runtime`, and `web`; the next task refers to those names verbatim.

- [ ] **Step 1: Write the failing image-contract tests**

Create `backend/tests/Feature/Infrastructure/DockerConfigurationTest.php`:

```php
<?php

namespace Tests\Feature\Infrastructure;

use Tests\TestCase;

class DockerConfigurationTest extends TestCase
{
    public function test_dockerfile_defines_reproducible_build_test_and_runtime_targets(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertIsString($dockerfile);
        $this->assertStringContainsString('FROM node:22-alpine AS node-deps', $dockerfile);
        $this->assertStringContainsString('FROM node-deps AS js-test', $dockerfile);
        $this->assertStringContainsString('FROM node-deps AS frontend', $dockerfile);
        $this->assertStringContainsString('FROM php:8.5-fpm-alpine AS php-base', $dockerfile);
        $this->assertStringContainsString('FROM php-base AS php-test', $dockerfile);
        $this->assertStringContainsString('FROM php-base AS runtime', $dockerfile);
        $this->assertStringContainsString('FROM nginxinc/nginx-unprivileged:', $dockerfile);
        $this->assertStringContainsString('AS web', $dockerfile);
        $this->assertStringContainsString('pdo_pgsql', $dockerfile);
        $this->assertStringContainsString('USER www-data', $dockerfile);
        $this->assertStringNotContainsString('GEMINI_API_KEY', $dockerfile);
        $this->assertStringNotContainsString('DB_PASSWORD', $dockerfile);
    }

    public function test_docker_context_excludes_local_dependencies_databases_and_secrets(): void
    {
        $dockerignore = file_get_contents(base_path('.dockerignore'));

        $this->assertIsString($dockerignore);
        $this->assertStringContainsString(".env\n", $dockerignore);
        $this->assertStringContainsString("vendor\n", $dockerignore);
        $this->assertStringContainsString("node_modules\n", $dockerignore);
        $this->assertStringContainsString("database/*.sqlite\n", $dockerignore);
        $this->assertStringContainsString("public/build\n", $dockerignore);
    }

    public function test_proxy_and_php_upload_limits_cover_the_eight_mebibyte_application_limit(): void
    {
        $uploads = file_get_contents(base_path('docker/php/uploads.ini'));

        $this->assertIsString($uploads);
        $this->assertStringContainsString('upload_max_filesize=9M', $uploads);
        $this->assertStringContainsString('post_max_size=10M', $uploads);
    }
}
```

- [ ] **Step 2: Run the focused test and verify the red phase**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
```

Expected: FAIL because `Dockerfile`, `.dockerignore`, and the PHP INI files do not exist.

- [ ] **Step 3: Add the Docker build context rules**

Create `backend/.dockerignore`:

```dockerignore
.git
.env
.env.*
!.env.example
!.env.docker.example
node_modules
vendor
public/build
public/hot
public/css/filament
public/fonts/filament
public/js/filament
bootstrap/cache/*.php
database/*.sqlite
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
```

- [ ] **Step 4: Add PHP production settings**

Create `backend/docker/php/opcache.ini`:

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
```

Create `backend/docker/php/uploads.ini`:

```ini
memory_limit=256M
upload_max_filesize=9M
post_max_size=10M
max_execution_time=30
```

- [ ] **Step 5: Add the multi-stage Dockerfile**

Create `backend/Dockerfile`:

```dockerfile
# syntax=docker/dockerfile:1.7

FROM node:22-alpine AS node-deps
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY public ./public
COPY resources ./resources
COPY tests/JavaScript ./tests/JavaScript
COPY vite.config.js ./

FROM node-deps AS js-test
CMD ["sh", "-lc", "node --test tests/JavaScript/*.test.js"]

FROM node-deps AS frontend
RUN npm run build

FROM php:8.5-fpm-alpine AS php-base
WORKDIR /var/www/html

RUN set -eux; \
    apk add --no-cache curl icu-libs libpq libxml2 libzip oniguruma sqlite-libs; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        icu-dev \
        libxml2-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        sqlite-dev; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        curl \
        dom \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        zip; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

FROM php-base AS php-test
COPY . .
RUN composer install --no-interaction --prefer-dist --no-progress
CMD ["php", "artisan", "test", "--compact"]

FROM php-base AS vendor
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

FROM php-base AS runtime
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=vendor /var/www/html/bootstrap/cache ./bootstrap/cache
COPY --from=vendor /var/www/html/public ./public
COPY --from=frontend /var/www/html/public/build ./public/build

RUN rm -f public/hot; \
    mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM nginxinc/nginx-unprivileged:1.29-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime --chown=nginx:nginx /var/www/html/public /var/www/html/public
EXPOSE 8080
```

The final `web` target intentionally references the Nginx file created in Task 3. Until that task lands, validate the PHP and JavaScript targets only.

- [ ] **Step 6: Run the focused test and image-contained test suites**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
docker build --target php-test -t halalscan-php-test .
docker run --rm halalscan-php-test
docker build --target js-test -t halalscan-js-test .
docker run --rm halalscan-js-test
docker build --target runtime -t halalscan-runtime .
```

Expected: the infrastructure test passes, both containerized test suites pass, and the non-root PHP runtime image builds successfully.

- [ ] **Step 7: Verify writable paths in an empty runtime container**

```bash
docker run --rm halalscan-runtime sh -lc 'test -w storage && test -w storage/framework/views && test -w bootstrap/cache'
```

Expected: exit code 0.

- [ ] **Step 8: Commit the image targets**

```bash
git add \
  backend/.dockerignore \
  backend/Dockerfile \
  backend/docker/php/opcache.ini \
  backend/docker/php/uploads.ini \
  backend/tests/Feature/Infrastructure/DockerConfigurationTest.php
git commit -m "build: add Laravel container image targets"
```

---

### Task 3: Orchestrate Laravel and PostgreSQL with Compose

**Files:**
- Modify: `backend/tests/Feature/Infrastructure/DockerConfigurationTest.php`
- Create: `backend/docker/nginx/default.conf`
- Create: `backend/compose.yaml`
- Create: `backend/.env.docker.example`
- Modify: `backend/.gitignore`

**Interfaces:**
- Consumes: Docker targets `runtime`, `web`, `php-test`, and `js-test` from Task 2; Laravel `/up`; existing database migrations.
- Produces: Compose services `web`, `app`, `worker`, `migrate`, `db`, `php-tests`, and `js-tests`, plus named volumes `postgres_data` and `storage_data`.

- [ ] **Step 1: Extend the contract test for Compose, PostgreSQL, and Nginx**

Add this import to the test file:

```php
use Symfony\Component\Yaml\Yaml;
```

Add these methods to `DockerConfigurationTest`:

```php
public function test_compose_wires_postgres_migrations_runtime_worker_and_tests(): void
{
    $compose = Yaml::parseFile(base_path('compose.yaml'));
    $services = $compose['services'];

    $this->assertEqualsCanonicalizing(
        ['web', 'app', 'worker', 'migrate', 'db', 'php-tests', 'js-tests'],
        array_keys($services),
    );
    $this->assertSame('postgres:17-alpine', $services['db']['image']);
    $this->assertArrayNotHasKey('ports', $services['db']);
    $this->assertSame(
        'service_healthy',
        $services['migrate']['depends_on']['db']['condition'],
    );
    $this->assertSame(
        'service_completed_successfully',
        $services['app']['depends_on']['migrate']['condition'],
    );
    $this->assertSame(
        'service_completed_successfully',
        $services['worker']['depends_on']['migrate']['condition'],
    );
    $this->assertContains('queue:work', $services['worker']['command']);
    $this->assertSame(['test'], $services['php-tests']['profiles']);
    $this->assertSame(['test'], $services['js-tests']['profiles']);
    $this->assertStringContainsString(
        '127.0.0.1',
        $services['web']['ports'][0],
    );
}

public function test_runtime_environment_requires_keys_and_uses_database_backed_laravel_services(): void
{
    $compose = file_get_contents(base_path('compose.yaml'));

    $this->assertIsString($compose);
    $this->assertStringContainsString('${APP_KEY:?', $compose);
    $this->assertStringContainsString('${DB_PASSWORD:?', $compose);
    $this->assertStringContainsString('DB_CONNECTION: pgsql', $compose);
    $this->assertStringContainsString('SESSION_DRIVER: database', $compose);
    $this->assertStringContainsString('CACHE_STORE: database', $compose);
    $this->assertStringContainsString('QUEUE_CONNECTION: database', $compose);
}

public function test_nginx_only_executes_the_front_controller_and_accepts_ten_mebibytes(): void
{
    $nginx = file_get_contents(base_path('docker/nginx/default.conf'));

    $this->assertIsString($nginx);
    $this->assertStringContainsString('client_max_body_size 10m;', $nginx);
    $this->assertStringContainsString('location = /index.php', $nginx);
    $this->assertStringContainsString('fastcgi_pass app:9000;', $nginx);
    $this->assertStringNotContainsString('location ~ \\.php$', $nginx);
}

public function test_docker_environment_template_contains_no_secret_values(): void
{
    $environment = file_get_contents(base_path('.env.docker.example'));

    $this->assertIsString($environment);
    $this->assertStringContainsString("APP_KEY=\n", $environment);
    $this->assertStringContainsString("DB_PASSWORD=\n", $environment);
    $this->assertStringContainsString("GEMINI_API_KEY=\n", $environment);
    $this->assertStringNotContainsString('base64:', $environment);
}
```

- [ ] **Step 2: Run the focused test and verify the new red phase**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
```

Expected: FAIL because `compose.yaml`, `.env.docker.example`, and the Nginx configuration do not exist.

- [ ] **Step 3: Add the Nginx front-controller configuration**

Create `backend/docker/nginx/default.conf`:

```nginx
server {
    listen 8080;
    server_name _;
    root /var/www/html/public;
    index index.php;

    charset utf-8;
    client_max_body_size 10m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/html/public/index.php;
        fastcgi_param DOCUMENT_ROOT /var/www/html/public;
        fastcgi_pass app:9000;
        fastcgi_read_timeout 30s;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 4: Add the Docker environment template and ignore the local copy**

Create `backend/.env.docker.example`:

```dotenv
APP_NAME=HalalScan
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=8000

LOG_CHANNEL=stderr
LOG_LEVEL=debug

DB_DATABASE=halalscan
DB_USERNAME=halalscan
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

GEMINI_API_KEY=
GEMINI_MODEL=gemini-flash-latest
GEMINI_TIMEOUT=20
GEMINI_CACHE_TTL=86400

APP_DOMAIN=
ACME_EMAIL=
```

Append this entry to `backend/.gitignore`:

```gitignore
/.env.docker
/.env.staging
```

- [ ] **Step 5: Add the base Compose stack**

Create `backend/compose.yaml`:

```yaml
name: halalscan

x-app-build: &app-build
  context: .
  dockerfile: Dockerfile
  target: runtime

x-app-environment: &app-environment
  APP_NAME: ${APP_NAME:-HalalScan}
  APP_ENV: ${APP_ENV:-local}
  APP_KEY: ${APP_KEY:?Set APP_KEY in the Compose environment file}
  APP_DEBUG: ${APP_DEBUG:-false}
  APP_URL: ${APP_URL:-http://localhost:8000}
  LOG_CHANNEL: ${LOG_CHANNEL:-stderr}
  LOG_LEVEL: ${LOG_LEVEL:-info}
  DB_CONNECTION: pgsql
  DB_HOST: db
  DB_PORT: 5432
  DB_DATABASE: ${DB_DATABASE:-halalscan}
  DB_USERNAME: ${DB_USERNAME:-halalscan}
  DB_PASSWORD: ${DB_PASSWORD:?Set DB_PASSWORD in the Compose environment file}
  SESSION_DRIVER: database
  CACHE_STORE: database
  QUEUE_CONNECTION: database
  FILESYSTEM_DISK: local
  GEMINI_API_KEY: ${GEMINI_API_KEY:-}
  GEMINI_MODEL: ${GEMINI_MODEL:-gemini-flash-latest}
  GEMINI_TIMEOUT: ${GEMINI_TIMEOUT:-20}
  GEMINI_CACHE_TTL: ${GEMINI_CACHE_TTL:-86400}

services:
  db:
    image: postgres:17-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE:-halalscan}
      POSTGRES_USER: ${DB_USERNAME:-halalscan}
      POSTGRES_PASSWORD: ${DB_PASSWORD:?Set DB_PASSWORD in the Compose environment file}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U $${POSTGRES_USER} -d $${POSTGRES_DB}"]
      interval: 5s
      timeout: 5s
      retries: 12
      start_period: 10s

  migrate:
    build: *app-build
    environment: *app-environment
    depends_on:
      db:
        condition: service_healthy
    command: ["php", "artisan", "migrate", "--force", "--no-interaction"]
    restart: "no"
    volumes:
      - storage_data:/var/www/html/storage

  app:
    build: *app-build
    restart: unless-stopped
    environment: *app-environment
    depends_on:
      migrate:
        condition: service_completed_successfully
    volumes:
      - storage_data:/var/www/html/storage
    healthcheck:
      test: ["CMD", "php-fpm", "-t"]
      interval: 10s
      timeout: 5s
      retries: 3

  worker:
    build: *app-build
    restart: unless-stopped
    environment: *app-environment
    depends_on:
      migrate:
        condition: service_completed_successfully
    command:
      - php
      - artisan
      - queue:work
      - --sleep=3
      - --tries=3
      - --timeout=60
      - --max-time=3600
    volumes:
      - storage_data:/var/www/html/storage

  web:
    build:
      context: .
      dockerfile: Dockerfile
      target: web
    restart: unless-stopped
    depends_on:
      app:
        condition: service_started
    ports:
      - "${APP_BIND_ADDRESS:-127.0.0.1}:${APP_PORT:-8000}:8080"
    healthcheck:
      test: ["CMD-SHELL", "wget -q -O /dev/null http://127.0.0.1:8080/up || exit 1"]
      interval: 10s
      timeout: 5s
      retries: 6
      start_period: 10s

  php-tests:
    profiles: ["test"]
    build:
      context: .
      dockerfile: Dockerfile
      target: php-test
    environment:
      APP_ENV: testing
      APP_KEY: base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
    command: ["php", "artisan", "test", "--compact"]

  js-tests:
    profiles: ["test"]
    build:
      context: .
      dockerfile: Dockerfile
      target: js-test

volumes:
  postgres_data:
  storage_data:
```

- [ ] **Step 6: Run the focused tests and validate Compose interpolation**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='halalscan-compose-contract' \
docker compose config --quiet
```

Expected: the PHP test passes and Compose exits 0 without printing a configuration error.

- [ ] **Step 7: Build the complete web target and run both Compose test services**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='halalscan-compose-contract' \
docker compose build web app worker migrate

APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='halalscan-compose-contract' \
docker compose --profile test run --rm php-tests

APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='halalscan-compose-contract' \
docker compose --profile test run --rm js-tests
```

Expected: all images build, the PHP suite passes, and the JavaScript suite passes.

- [ ] **Step 8: Commit the local Compose stack**

```bash
git add \
  backend/.env.docker.example \
  backend/.gitignore \
  backend/compose.yaml \
  backend/docker/nginx/default.conf \
  backend/tests/Feature/Infrastructure/DockerConfigurationTest.php
git commit -m "build: orchestrate Laravel with PostgreSQL"
```

---

### Task 4: Add the provider-neutral HTTPS staging overlay

**Files:**
- Modify: `backend/tests/Feature/Infrastructure/DockerConfigurationTest.php`
- Create: `backend/compose.staging.yaml`
- Create: `backend/docker/caddy/Caddyfile`

**Interfaces:**
- Consumes: healthy `web` service on port 8080 from Task 3 and runtime variables `APP_DOMAIN`, `ACME_EMAIL`, `APP_KEY`, and `DB_PASSWORD`.
- Produces: Caddy service `caddy`, named volumes `caddy_data` and `caddy_config`, and staging overrides that force HTTPS URLs and disable Laravel debug output.

- [ ] **Step 1: Add the failing staging contract tests**

Add these methods to `DockerConfigurationTest`:

```php
public function test_staging_overlay_adds_tls_and_forces_safe_laravel_settings(): void
{
    $staging = Yaml::parseFile(base_path('compose.staging.yaml'));
    $services = $staging['services'];

    $this->assertSame('caddy:2-alpine', $services['caddy']['image']);
    $this->assertContains('80:80', $services['caddy']['ports']);
    $this->assertContains('443:443', $services['caddy']['ports']);
    $this->assertContains('443:443/udp', $services['caddy']['ports']);
    $this->assertSame('staging', $services['app']['environment']['APP_ENV']);
    $this->assertSame('false', $services['app']['environment']['APP_DEBUG']);
    $this->assertSame('true', $services['app']['environment']['SESSION_SECURE_COOKIE']);
    $this->assertStringStartsWith(
        'https://',
        $services['app']['environment']['APP_URL'],
    );
}

public function test_caddy_proxies_only_the_configured_domain_to_the_web_service(): void
{
    $caddyfile = file_get_contents(base_path('docker/caddy/Caddyfile'));

    $this->assertIsString($caddyfile);
    $this->assertStringContainsString('email {$ACME_EMAIL}', $caddyfile);
    $this->assertStringContainsString('{$APP_DOMAIN}', $caddyfile);
    $this->assertStringContainsString('reverse_proxy web:8080', $caddyfile);
}
```

- [ ] **Step 2: Run the focused test and verify the red phase**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
```

Expected: FAIL because the staging overlay and Caddyfile do not exist.

- [ ] **Step 3: Add the Caddy configuration**

Create `backend/docker/caddy/Caddyfile`:

```caddyfile
{
    email {$ACME_EMAIL}
}

{$APP_DOMAIN} {
    encode zstd gzip
    reverse_proxy web:8080
}
```

- [ ] **Step 4: Add the staging Compose overlay**

Create `backend/compose.staging.yaml`:

```yaml
x-staging-environment: &staging-environment
  APP_ENV: staging
  APP_DEBUG: "false"
  APP_URL: https://${APP_DOMAIN:?Set APP_DOMAIN in the staging environment file}
  SESSION_SECURE_COOKIE: "true"

services:
  migrate:
    environment: *staging-environment

  app:
    environment: *staging-environment

  worker:
    environment: *staging-environment

  caddy:
    image: caddy:2-alpine
    restart: unless-stopped
    depends_on:
      web:
        condition: service_healthy
    environment:
      APP_DOMAIN: ${APP_DOMAIN:?Set APP_DOMAIN in the staging environment file}
      ACME_EMAIL: ${ACME_EMAIL:?Set ACME_EMAIL in the staging environment file}
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    volumes:
      - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config

volumes:
  caddy_data:
  caddy_config:
```

Compose merges the staging environment keys into the base application environment. The base web port stays bound to loopback only, while Caddy is the sole public listener.

- [ ] **Step 5: Run the focused tests and validate the merged Compose model**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='halalscan-staging-contract' \
APP_DOMAIN='staging.example.com' \
ACME_EMAIL='ops@example.com' \
docker compose -f compose.yaml -f compose.staging.yaml config --quiet
```

Expected: the PHP test passes and the merged Compose model validates without publishing a PostgreSQL port.

- [ ] **Step 6: Inspect the merged staging ports and application environment**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
DB_PASSWORD='halalscan-staging-contract' \
APP_DOMAIN='staging.example.com' \
ACME_EMAIL='ops@example.com' \
docker compose -f compose.yaml -f compose.staging.yaml config
```

Expected: Caddy publishes 80/443, web remains bound to `127.0.0.1:8000`, db has no published port, and app/worker/migrate show `APP_DEBUG: "false"` and an HTTPS `APP_URL`.

- [ ] **Step 7: Commit the staging overlay**

```bash
git add \
  backend/compose.staging.yaml \
  backend/docker/caddy/Caddyfile \
  backend/tests/Feature/Infrastructure/DockerConfigurationTest.php
git commit -m "build: add portable HTTPS staging overlay"
```

---

### Task 5: Add the operator runbook and execute end-to-end verification

**Files:**
- Modify: `backend/tests/Feature/Infrastructure/DockerConfigurationTest.php`
- Replace: `backend/README.md`
- Create: `backend/docs/docker.md`

**Interfaces:**
- Consumes: the base and staging Compose files from Tasks 3 and 4.
- Produces: exact local, test, backup, staging, logging, shutdown, and isolated cleanup commands for operators.

- [ ] **Step 1: Add the failing documentation contract test**

Add this method to `DockerConfigurationTest`:

```php
public function test_docker_runbook_documents_start_test_backup_and_shutdown_commands(): void
{
    $runbook = file_get_contents(base_path('docs/docker.md'));

    $this->assertIsString($runbook);
    $this->assertStringContainsString('docker compose --env-file .env.docker up -d --build', $runbook);
    $this->assertStringContainsString('docker compose --profile test run --rm php-tests', $runbook);
    $this->assertStringContainsString('docker compose --profile test run --rm js-tests', $runbook);
    $this->assertStringContainsString('pg_dump', $runbook);
    $this->assertStringContainsString('compose.staging.yaml', $runbook);
    $this->assertStringContainsString('docker compose --env-file .env.docker down', $runbook);
    $this->assertStringContainsString('down --volumes', $runbook);
}
```

- [ ] **Step 2: Run the focused test and verify the red phase**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
```

Expected: FAIL because `backend/docs/docker.md` does not exist.

- [ ] **Step 3: Replace the framework README with the project entrypoint**

Replace `backend/README.md` with:

```markdown
# HalalScan backend

Laravel backend and responsive web client for product barcode lookup and preliminary ingredient analysis.

## Local development

Install PHP and Node dependencies, configure `.env`, then run:

```bash
composer run dev
```

## Docker

The production-oriented local stack uses Nginx, PHP-FPM, a queue worker, and PostgreSQL. See [docs/docker.md](docs/docker.md) for setup, tests, backups, and staging deployment.

## Verification

```bash
php artisan test --compact
node --test tests/JavaScript/*.test.js
npm run build
./vendor/bin/pint --format agent
```
```

- [ ] **Step 4: Write the Docker operator runbook**

Create `backend/docs/docker.md` with these sections and exact commands:

```markdown
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

Open `http://127.0.0.1:8000` after the web health check passes.

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
```

- [ ] **Step 5: Run the focused test and all host-side quality gates**

```bash
php artisan test --compact tests/Feature/Infrastructure/DockerConfigurationTest.php
php artisan test --compact
node --test tests/JavaScript/*.test.js
npm run build
./vendor/bin/pint --format agent
composer validate --no-check-publish
npm audit --audit-level=high
```

Expected: all commands exit 0.

- [ ] **Step 6: Create an ignored local test environment**

Create `backend/.env.docker` from `.env.docker.example` with these disposable local-only values and keep `GEMINI_API_KEY` empty:

```dotenv
APP_NAME=HalalScan
APP_ENV=local
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=8000
LOG_CHANNEL=stderr
LOG_LEVEL=debug
DB_DATABASE=halalscan
DB_USERNAME=halalscan
DB_PASSWORD=halalscan-local-test-password
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
GEMINI_API_KEY=
GEMINI_MODEL=gemini-flash-latest
GEMINI_TIMEOUT=20
GEMINI_CACHE_TTL=86400
APP_DOMAIN=
ACME_EMAIL=
```

- [ ] **Step 7: Start a uniquely named cold stack and verify service health**

```bash
COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker up -d --build

COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker ps

curl --fail http://127.0.0.1:8000/up
curl --fail http://127.0.0.1:8000/
```

Expected: db, app, and web are healthy; migrate exited 0; worker is running; both HTTP checks return success.

- [ ] **Step 8: Verify migrations and named-volume persistence across stop/start**

```bash
before_count="$(COMPOSE_PROJECT_NAME=halalscan_docker_test docker compose --env-file .env.docker exec -T db psql -U halalscan -d halalscan -tAc 'SELECT count(*) FROM migrations;')"

COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker stop

COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker start

after_count="$(COMPOSE_PROJECT_NAME=halalscan_docker_test docker compose --env-file .env.docker exec -T db psql -U halalscan -d halalscan -tAc 'SELECT count(*) FROM migrations;')"

test "$before_count" = "$after_count"
test "$after_count" -gt 0
curl --fail http://127.0.0.1:8000/up
```

Expected: migration count is non-zero and unchanged, and `/up` recovers after restart.

- [ ] **Step 9: Verify the production images contain no test secret**

```bash
app_image="$(COMPOSE_PROJECT_NAME=halalscan_docker_test docker compose --env-file .env.docker images -q app)"

if docker history --no-trunc "$app_image" | rg -F 'halalscan-local-test-password'; then
  exit 1
fi

if docker image inspect "$app_image" | rg -F 'halalscan-local-test-password'; then
  exit 1
fi
```

Expected: both searches return no match.

- [ ] **Step 10: Run both test targets through Compose**

```bash
COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker --profile test run --rm php-tests

COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker --profile test run --rm js-tests
```

Expected: the complete PHP and JavaScript suites pass inside their Docker targets.

- [ ] **Step 11: Remove only the isolated verification stack**

```bash
COMPOSE_PROJECT_NAME=halalscan_docker_test \
docker compose --env-file .env.docker down --volumes
```

Expected: only containers, networks, and named volumes carrying the `halalscan_docker_test` project label are removed. The non-Docker SQLite database remains untouched.

- [ ] **Step 12: Commit the runbook and final infrastructure test**

```bash
git add \
  backend/README.md \
  backend/docs/docker.md \
  backend/tests/Feature/Infrastructure/DockerConfigurationTest.php
git commit -m "docs: add Docker operations runbook"
```

- [ ] **Step 13: Inspect the completed Docker subproject**

```bash
git status --short
git log --oneline -6
git diff --check HEAD~5..HEAD
```

Expected: the worktree is clean except for the intentionally ignored `.env.docker`, the five implementation commits are visible, and the diff check has no output.
