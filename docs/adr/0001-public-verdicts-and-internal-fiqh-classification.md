# ADR 0001: Public Verdicts And Internal Fiqh Classification

Дата: 2026-07-14
Статус: accepted

## Context

Halal Scanner must give users a simple result in a store scenario, but the underlying religious domain has more nuance than four words. The product must support madhhab differences and classifications such as `makruh_tanzihi` and `makruh_tahrimi` without confusing users or weakening the "unknown is not halal" principle.

The constitution defines public states as:

- `halal`
- `haram`
- `mushbooh`
- `unknown`

The book notes from Mohammad Hashim Kamali reinforce that `makruh` is not the same as `haram`, and that `haram` should not be used casually when the evidence is not strong.

## Decision

MVP will expose only four public halal verdict states:

- `halal`
- `haram`
- `mushbooh`
- `unknown`

`makruh` will not be a fifth public verdict state in MVP.

The rule engine may store internal fiqh classifications, including:

- `makruh_tanzihi`
- `makruh_tahrimi`

These classifications can affect:

- reason codes;
- explanation text;
- warning strength;
- whether a result becomes `mushbooh`;
- whether a result can remain `halal` with a caution note.

`makruh_tanzihi` can be shown as a caution only when the item otherwise has sufficient halal evidence.

`makruh_tahrimi` should normally produce a cautious result such as `mushbooh` unless an expert-approved rule gives a different treatment. It must not be silently displayed as plain `halal`, and it must not be displayed as confident `haram` without a separate strong basis.

## Consequences

Positive:

- User-facing results stay simple.
- The rule engine keeps religious nuance.
- `haram` remains reserved for strong evidence.
- Future madhhab-specific behavior is possible without changing the public status model.

Tradeoffs:

- The UI must explain `makruh` cases carefully without adding a new status.
- Tests must verify both public verdict and internal reason codes.
- Some advanced users may expect to see `makruh` as a first-class label; this can be revisited after MVP.

## Links

- `docs/constitution.md`
- `docs/spec.md` FR-01, FR-25, FR-26, FR-27
- `features/makruh-and-madhhab.feature`
- `docs/book-notes-parameters-halal-haram.md`
