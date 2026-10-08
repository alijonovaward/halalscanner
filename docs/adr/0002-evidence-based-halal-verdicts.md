# ADR 0002: Evidence-Based Halal Verdicts

Дата: 2026-07-14
Статус: accepted

## Context

The highest-risk product error is a false `halal` result. The app must not treat missing data, weak sources, packaging claims, user reports, or unknown ingredient origin as sufficient grounds for `halal`.

The domain also requires traceability: every verdict must have an explanation, source, date, and rule or fact that influenced the result.

## Decision

Halal Scanner will use an evidence-based verdict model.

A result can be `halal` only when the selected madhhab has sufficient positive evidence and there is no unresolved blocking risk.

The following are not sufficient by themselves to produce `halal`:

- unknown product barcode;
- unreadable or low-confidence OCR;
- product label saying "halal" without verified certificate;
- user-submitted claim;
- manufacturer claim without trusted review;
- ingredient name with unknown origin;
- process-dependent claim such as `istihalah` without process evidence;
- conflicting trusted sources.

Each result must include:

- public verdict;
- reason codes when cautious or blocked;
- explanation;
- source references;
- last verification date where available;
- confidence or evidence strength;
- review flags where needed.

Facts that influence verdicts must be linked to source metadata:

- source type;
- trust tier;
- checked date;
- review status;
- change history or reason for revision.

## Consequences

Positive:

- Reduces false `halal` risk.
- Makes verdicts explainable and reviewable.
- Supports offline-first results without hiding source quality.
- Gives editors and scholars a clear review path.

Tradeoffs:

- Some products with likely-halal ingredients will still show `unknown` or `mushbooh` until enough evidence exists.
- Data modeling is heavier than a simple ingredient blacklist.
- More UI work is required to explain why the app is cautious.

## Links

- `docs/constitution.md`
- `docs/spec.md` FR-10, FR-11, FR-12, FR-25, FR-27, FR-28, FR-29, FR-30
- `features/verdict-statuses.feature`
- `features/certificates-and-evidence.feature`
- `features/ingredients-and-processes.feature`
- `docs/architecture.md`
