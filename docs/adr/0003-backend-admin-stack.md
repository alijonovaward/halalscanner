# ADR 0003: Backend And Admin Stack

Дата: 2026-07-14
Статус: accepted

## Context

Halal Scanner needs a backend for verified product data, rule management, source/evidence tracking, certificate review, user reports, offline snapshot generation, and a future mobile API.

The admin workflow is not a generic CMS. It must support editor review, scholar review, source freshness, certificate trust, reason-code debugging, and publication of verified data snapshots.

The owner selected:

- Laravel for backend;
- Filament for admin panel;
- Laravel Boost for Laravel-aware AI-assisted development.

## Decision

Use Laravel 13 as the backend application framework.

Use Filament 5 as the admin panel layer for internal editor/scholar workflows.

Use Laravel Boost as a development-only tool after implementation begins. Boost is not a production runtime dependency and must be installed with Composer as a dev dependency.

The halal rule engine must not be implemented inside Filament Resources. It must live in a domain layer that can be tested independently and called by:

- API controllers;
- console/import jobs;
- snapshot builders;
- Filament admin actions and read-only debug views.

Filament is responsible for admin workflows:

- CRUD resources for products, ingredients, rules, evidence, certificates, certification bodies, and user reports;
- tables and filters for review queues;
- forms for editor-entered facts;
- infolists for read-only verdict/debug views;
- actions for approve, reject, mark stale, request scholar review, and publish snapshot;
- notifications and widgets for operational visibility.

The mobile API is separate from Filament. Filament is not the mobile app API.

## Consequences

Positive:

- Laravel gives a mature backend foundation, queues, scheduling, validation, database migrations, testing, and API structure.
- Filament fits the admin/review workflow and reduces custom admin UI work.
- Boost can help keep Laravel code aligned with current Laravel ecosystem conventions during implementation.
- The domain layer remains testable outside the admin panel.

Tradeoffs:

- We must enforce boundaries so Filament does not become the rule engine.
- The mobile API still needs explicit design and tests.
- Boost-generated local agent files should be treated as development tooling and not as product logic.

## Implementation Notes

Initial backend modules after `go`:

- `Domain/Halal` for verdicts, rules, evidence, reason codes, and aggregation.
- `Domain/Catalog` for products and ingredients.
- `Domain/Certification` for bodies and certificates.
- `Domain/Review` for user reports and editor/scholar review state.
- `Domain/Boycott` isolated from halal verdict computation.
- `Admin/Filament` or Filament Resources as adapter/UI layer.
- `Api` controllers for mobile-facing endpoints.
- queued jobs for imports, snapshot builds, certificate expiry checks, and source freshness checks.

## References

- Laravel Boost docs: `https://laravel.com/docs/13.x/boost`
- Filament docs: `https://filamentphp.com/docs`

## Links

- `docs/architecture.md`
- `docs/tasks.md`
- `docs/adr/0001-public-verdicts-and-internal-fiqh-classification.md`
- `docs/adr/0002-evidence-based-halal-verdicts.md`
