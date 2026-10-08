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
