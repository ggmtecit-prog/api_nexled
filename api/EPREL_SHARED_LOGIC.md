# EPREL / Central API Shared Logic

Purpose:
- lock one shared rule set for Central API and EPREL
- stop both projects from drifting into different family-import logic
- keep family import truthful, fast, and based on real products only

Audience:
- AI agents
- engineers working on Central API
- engineers working on EPREL import flows

Last Updated:
- 2026-04-20

## Core Split

Central API owns truth.

EPREL owns workflow.

Meaning:

- Central API decides:
  - which families exist
  - which identities exist in `Luminos`
  - which full references are `configurator_valid`
  - which full references are `datasheet_ready`
  - which metadata belongs to each real product
- EPREL decides:
  - how to import page by page
  - how to cache imported rows
  - how to resume/retry jobs
  - how to present family import UI to the user

EPREL must not recreate NexLed code-valid logic by itself.

## Shared Definitions

- `identity`
  - first 10 chars
  - must exist in `Luminos`
- `configurator_valid`
  - real code passes family/option validation
- `datasheet_ready`
  - valid code plus real data/assets exist to generate PDF
- `family import`
  - import only real full references for one family
  - not synthetic combinations

## Hard Rules

1. No giant theoretical family matrix.
2. No cartesian product over all dropdown options in EPREL.
3. No fake products.
4. No fake readiness.
5. No image existence used as code-valid proof.
6. Family import must prefer real DB truth over docs or guesses.

## Source Of Truth Order

When sources disagree:

1. old runtime behavior in `appdatasheets/`
2. live DB truth
3. current API implementation
4. official/reference PDFs
5. narrow docs

## What Central API Must Provide

Central API should expose clean read endpoints for EPREL.

Minimum contract direction:

1. `families`
   - real family list
   - family metadata
2. `family-ready-products`
   - live
   - only real full references
   - only `configurator_valid = true`
   - only `datasheet_ready = true`
   - paginated
   - built from ready base combos, then expanded into full refs page by page
   - stores per-family ready-base cache under `output/family-ready-products/`
3. `family-ready-details`
   - optional later bulk hydrate endpoint
   - returns details for exact refs only

Important:

- family import endpoint must start from real `Luminos` identities
- it must not expose giant synthetic search behavior
- it must not assume all suffix combinations are real

## What EPREL Must Do

EPREL should:

- request one family at a time
- page through Central API results
- store imported rows
- allow retry/resume
- show honest empty state when a family has zero ready products

EPREL should not:

- brute-force family references locally
- guess missing suffixes
- decide readiness on its own
- import rows that are only `configurator_valid` but not `datasheet_ready`

## Current API Reality

What already exists:

- `families`
- `options`
- `reference`
- `decode-reference`
- `datasheet`
- `code-explorer`
- `family-ready-products`

What `code-explorer` already gives:

- status filtering
- `configurator_valid`
- `datasheet_ready`
- `failure_reason`

What `code-explorer` does not safely guarantee for EPREL family import:

- real ready-only family list without synthetic suffix expansion

So:

- current readiness logic is reusable
- current family-wide explorer output is not the final EPREL import contract
- EPREL should prefer `family-ready-products`, not raw family-wide `code-explorer`

## Implementation Rule For New Central API Family Import

Current `family-ready-products` rule:

1. start from real identities
2. evaluate readiness at base-combo level:
   - `identity + lens + finish + cap`
   - current readiness logic uses one default option code for that check
3. expand only ready base combos into full refs with unique option codes
4. reuse existing readiness checks
5. return only rows where:
   - `configurator_valid = true`
   - `datasheet_ready = true`
6. paginate
7. cache ready base combos so repeated page requests stay fast
8. return empty rows safely when a family has no ready products

## Known Risk

Some families have:

- dropdown presence
- runtime class
- maybe even docs

but no real live `Luminos` identities.

Those families must not appear as importable ready families just because options exist.

## Safe Mental Model

- Central API = source of truth
- EPREL = importer client
- `code-explorer` = internal analysis tool, not final family-import contract

## Best Next Work

1. let EPREL consume `family-ready-products` page by page
2. verify families/import counts against real business expectations
3. add `family-ready-details` only if EPREL hydration needs bulk speed
4. do not fall back to family-wide synthetic `code-explorer` import
