# Container, Android MVP, and Staging Design

## Goal

Package the existing HalalScan Laravel application as a portable Docker deployment, add a fast Android MVP that reuses the hosted web interface while providing native barcode scanning, and define a provider-neutral path to a later HTTPS staging deployment.

Success means a developer can start the backend and PostgreSQL with Docker Compose, produce an installable debug APK, and later deploy the same images and Android client configuration to a Linux VPS without changing application business logic.

## Scope and delivery order

The work is split into three independently testable subprojects and must be delivered in this order:

1. Containerize the Laravel backend and run it against PostgreSQL.
2. Add the Android Capacitor shell and native barcode integration.
3. Deploy the existing images to a staging VPS after a host and domain are available.

Each subproject receives its own implementation plan and completion gate. Docker containerization is planned and implemented first; Android work begins only after the containerized backend passes its acceptance checks, and live staging deployment begins only after infrastructure inputs exist.

The currently implemented browser barcode and automatic ingredient-photo flows remain the functional baseline. They are checkpointed before container or Android work begins.

## Selected approach

HalalScan will use a multi-container Docker Compose stack and a thin, online-only Capacitor Android client. The Android application will not duplicate the Laravel frontend. It will load the existing responsive interface from a configured server and replace browser barcode capture with a native scanner when the Capacitor bridge is present.

This approach was selected over a single all-in-one container because separate web, application, worker, and database services are easier to operate and diagnose. It was selected over a native Kotlin or separate mobile SPA implementation because reusing the current interface produces a testable APK substantially sooner.

## Repository structure

- `backend/` remains the Laravel application and owns the web interface, API, Docker image definitions, and Compose configuration.
- `mobile/` is a separate Capacitor 8 project that owns Android build configuration, the native barcode plugin, the local connection-error screen, and the generated Android project.
- `docs/superpowers/` contains the approved design and implementation plans.

The backend and mobile projects keep separate Node dependency manifests. The backend bundle includes the JavaScript interface used to call the Capacitor barcode plugin, while the mobile project includes and registers the corresponding native Android plugin.

## Backend container architecture

The base Compose stack contains these services:

- `web`: an unprivileged Nginx container that serves Laravel public assets and forwards PHP requests to `app`;
- `app`: the production PHP 8.5 FPM Laravel runtime;
- `worker`: the same application image running `php artisan queue:work` with restart-safe options;
- `migrate`: a one-shot service that runs `php artisan migrate --force` after PostgreSQL becomes healthy;
- `db`: PostgreSQL 17 on an internal Docker network.

`app` and `worker` start only after the migration service completes successfully. PostgreSQL is never published to the host by the staging configuration. Named volumes persist PostgreSQL data and Laravel writable storage.

The Laravel image uses multi-stage builds:

1. Node 22 runs `npm ci` and `npm run build`.
2. Composer 2 installs optimized production dependencies without development packages.
3. A PHP 8.5 FPM runtime receives the application, vendor directory, and compiled frontend assets.

The runtime includes the PHP extensions required by Laravel and PostgreSQL. Application code and dependencies are immutable image layers; only Laravel storage and runtime cache directories are writable.

Local Compose exposes the web service on `http://localhost:8000`. A provider-neutral staging overlay adds a TLS reverse proxy after `APP_DOMAIN` and `ACME_EMAIL` are supplied. The staging overlay exposes only ports 80 and 443.

## Configuration and secrets

A committed environment template documents all required settings without values for secrets. At minimum, runtime configuration includes:

- Laravel application environment, URL, key, and debug setting;
- PostgreSQL database name, user, and password;
- Gemini API key, model, timeout, and cache duration;
- mail and logging settings needed by Laravel;
- staging domain and ACME email for the TLS proxy.

The Gemini key, Laravel key, database password, and signing credentials are never copied into an image or committed. Production and staging run with `APP_DEBUG=false`. Docker logs go to stdout and stderr. Database, queue, cache, and session connections use PostgreSQL; Redis is intentionally excluded from the MVP.

The current local SQLite database remains available for non-Docker development. Docker starts PostgreSQL from Laravel migrations rather than mutating or replacing the SQLite file. Existing transient sessions and the local test user are not migrated automatically. Product-candidate import remains an explicit application command when staging data is needed.

## Android application architecture

The Android client uses Capacitor 8 with:

- application name `HalalScan`;
- application identifier `uz.halalscan.app`;
- minimum Android SDK 26;
- the official `@capacitor/barcode-scanner` plugin configured to use ML Kit;
- a build-time `server.url` for the internal test environment;
- a bundled `server.errorPath` page for startup connectivity failures.

On startup, Capacitor loads the configured backend URL. If that navigation fails, Capacitor displays the bundled error page with a retry action. The configured server URL is the only remote application origin; unrelated links open outside the application instead of receiving access to the native bridge.

Debug builds may opt into cleartext HTTP so a physical phone can connect to a Laravel instance on the same Wi-Fi network or an emulator can use its host alias. Staging builds reject cleartext traffic and require HTTPS.

Capacitor documents remote `server.url` and extra WebView navigation as development-oriented rather than production deployment features. This design therefore limits the remote-hosted client to internal debug and staging distribution. A future Google Play release must replace it with packaged web assets or a separate native/mobile frontend and receive its own design review.

The first deliverable is a debug APK. Google Play publication, production app signing, and production release distribution are separate future work.

## Barcode integration

The browser barcode module gains a platform adapter with one public operation: request a product barcode. Its behavior is:

1. In a Capacitor Android runtime, call the native barcode plugin with the rear camera, adaptive orientation, ML Kit, and retail barcode hints.
2. Accept EAN-13, EAN-8, UPC-A, UPC-E, Code 128, and ITF values that normalize to 8–14 digits.
3. Return the normalized value to the existing page, fill the barcode field, and submit the existing catalog form automatically.
4. Treat user cancellation as a neutral result without an error banner.
5. If the native bridge or plugin is unavailable, use the existing secure live-camera or captured-photo browser fallback.

The backend catalog endpoint and response format do not change.

## Ingredient-photo integration

The Android client reuses the existing file capture and upload controls. A selected image follows the current flow:

1. Browser-side preparation reduces an oversized image.
2. Laravel sends the validated image to Gemini for transcription without storing it.
3. The recognized text is placed in the editable textarea.
4. Laravel performs the existing automatic ingredient analysis.
5. A failed analysis leaves recognized text available for correction and retry.

The Gemini credential remains server-side. No image or provider credential is embedded in the APK.

## Failure handling

- PostgreSQL unavailable: dependent services remain unhealthy and migration fails visibly instead of serving a partially initialized application.
- Migration failure: `app` and `worker` do not start; the one-shot service exposes the failing Artisan output.
- Backend unavailable at Android startup: show the bundled retry page.
- Backend becomes unavailable after navigation: display the web request error and allow the user to retry without clearing entered text where the current flow supports it.
- Barcode scan cancelled: close the native scanner and leave the current input unchanged.
- Camera permission denied or scanner failure: show a concise Russian error and keep manual barcode entry available.
- Invalid or unsupported barcode: do not submit it; ask for another scan or manual entry.
- Gemini unavailable: preserve recognized or manually entered text and show the existing retry guidance.
- TLS or navigation to an unapproved host: reject the navigation rather than weakening WebView security.

## Security boundaries

- Staging Android builds communicate only over HTTPS.
- The remote application origin is limited to the configured HalalScan host; no wildcard navigation rule is allowed.
- PostgreSQL is reachable only from the Compose network.
- Secrets enter containers through runtime environment configuration and are excluded from build arguments, image layers, logs, and the repository.
- Existing AI endpoint throttles remain enabled. Authentication is not added to the public scanning flow in this MVP.
- Uploaded ingredient images are validated, processed in request memory, and not persisted.
- Nginx enforces a request-body limit compatible with the Laravel image validation limit.

## Staging deployment

The staging deployment targets any current Linux VPS capable of running Docker Engine and the Compose plugin. Provider-specific infrastructure is outside this design.

Deployment inputs are the VPS address, DNS record, domain, ACME email, Laravel key, PostgreSQL credentials, and Gemini key. Once supplied, deployment consists of:

1. installing Docker and checking out or transferring the release;
2. creating the staging environment file outside version control;
3. building or pulling the immutable images;
4. starting the staging Compose overlay;
5. confirming the database migration, `/up`, homepage, and three API routes;
6. building a staging APK with the final HTTPS URL;
7. installing the APK on test devices and running the acceptance scenarios.

PostgreSQL data uses a named volume. Before destructive upgrades, the operator creates a logical PostgreSQL backup. Automated off-host backups are deferred until a hosting provider and retention requirements are known.

## Testing and acceptance

### Backend and Docker

- Existing PHP and JavaScript tests pass before and after containerization.
- `docker compose config` validates the base and staging configurations.
- Production images build from a clean checkout without local `vendor` or `node_modules` directories.
- Containerized PHPUnit and JavaScript tests pass against their intended test configuration.
- The stack starts from an empty PostgreSQL volume, migrations complete once, and `/up` returns HTTP 200.
- The homepage and catalog, image-recognition, and ingredient-analysis routes remain reachable.
- PostgreSQL data survives a Compose stop and start.
- The worker connects to the same application and database configuration as the web runtime.
- Image history and build metadata contain no configured secrets. Runtime environment inspection remains restricted to trusted Docker-host administrators.

### Android

- JavaScript unit tests cover native-platform selection, successful scan normalization, cancellation, plugin failure, and browser fallback.
- Gradle unit tests and Android lint pass.
- `assembleDebug` produces an installable APK.
- An emulator verifies startup, backend connectivity, manual barcode entry, offline startup, and retry.
- A physical Android device verifies rear-camera permission, successful retail-barcode scanning, cancellation, permission denial, an unknown product, ingredient photo capture, OCR, automatic analysis, manual correction, and repeat analysis.

### Staging

- HTTPS is valid without a browser certificate warning.
- The Android staging build refuses HTTP and unapproved hosts.
- Container health checks are green after a cold start and after a VPS reboot.
- A complete product lookup and ingredient-photo flow succeeds from the staging APK.

## Non-goals

This MVP does not include user authentication for the scanner, packaged production mobile web assets, a separate mobile UI, offline catalog or AI processing, Google Play publication, production Android signing, push notifications, analytics, iOS packaging, Redis, horizontal scaling, or automated off-host backups.

## Deliverables

1. A checkpoint commit for the currently approved browser scanner and automatic photo-analysis work.
2. A production-oriented Laravel Dockerfile, Nginx configuration, base Compose stack, staging overlay, environment templates, and operating instructions.
3. A Capacitor Android project, native barcode adapter, tests, and debug APK.
4. A repeatable staging deployment procedure ready for VPS and domain values when they become available.
