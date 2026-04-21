# EPREL Family Import Roadmap

Status:
- Planning document
- Verified against this repo and the live EPREL project on 2026-04-20
- Not implemented yet

Purpose:
- define the safest path to add "import whole family from central API/database" into the live EPREL tool
- let a user choose a family, fetch all usable products for that family, and build the current EPREL ZIP package from real data
- keep product truth in the central API instead of duplicating DB logic inside the EPREL project

Important scope note:
- this repo contains the central API and related runtime/docs
- this repo does **not** contain the live EPREL project source code
- EPREL UI/backend details below come from observed live behavior, not from local files in this repo

---

## One-Line Feature

User picks a family -> EPREL backend asks the central API for all exportable products in that family -> API returns generic product bundles plus blocking issues -> EPREL maps them into `models` and the existing ZIP flow builds the package.

---

## Why This Feature Exists

Today the EPREL flow is product-by-product and depends on manual input/search.
That does not scale when the goal is:

- upload every product in a family
- build one ZIP package for the whole family batch
- avoid retyping data that already exists in the Nexled databases
- keep the same truth across EPREL, configurator, website, and internal tools

The missing piece is a batch export path from the central API into the EPREL tool.

---

## Current State

## Central API repo (`api_nexled`)

Current live API already has:

- `families`
- `options`
- `reference`
- `decode-reference`
- `datasheet`
- `health`
- `code-explorer`
- `dam`

Important facts already true in this repo:

- `families` returns runtime metadata:
  - `codigo`
  - `nome`
  - `product_type`
  - `datasheet_runtime_supported`
  - `luminos_identity_count`
  - `has_luminos_identities`
- `family-registry.php` already marks which families are actually supported by the current datasheet runtime
- `code-explorer` already knows how to enumerate family combinations and distinguish:
  - configurator-valid
  - configurator-invalid
  - datasheet-ready
  - datasheet-blocked
- datasheet/product libs already exist and should be reused instead of rewriting SQL from scratch:
  - `api/lib/reference-decoder.php`
  - `api/lib/luminotechnical.php`
  - `api/lib/characteristics.php`
  - `api/lib/product-header.php`
  - `api/lib/technical-drawing.php`
  - `api/lib/sections.php`
  - `api/lib/images.php`

Important architectural implication:

- the central API already has the best base for product enumeration and enrichment
- the EPREL project should **not** duplicate this logic locally

## Live EPREL project (external, not in this repo)

Observed behavior from the live app:

- frontend is a separate project
- frontend talks to its own same-origin backend under `/api`
- ZIP generation already exists
- EPREL compliance/upload flow already exists
- there are disabled affordances for importing/building from database
- current frontend/backend contract uses `success/data/error`
- current central API mostly uses `ok/data/error` or plain JSON/PDF responses

Important architectural implication:

- do **not** connect browser code directly to the Railway API
- use a thin EPREL backend adapter
- let that adapter translate contracts, auth, and errors

---

## Problem Definition

The feature sounds simple, but there are really 4 different problems inside it:

1. Family enumeration
   We need the full list of products that belong to one family.

2. Product validity
   We only want products that are real according to current DB truth, not fake combinations.

3. EPREL mapping
   We need Nexled product truth transformed into the exact model shape that the EPREL ZIP generator expects.

4. File readiness
   Even if DB fields exist, the batch still fails if required files are missing.

If we solve only item 1, the feature will create broken ZIP batches.
If we solve all 4, the feature becomes reliable.

---

## Goal

Deliver a batch family import flow with these properties:

- user selects a family
- UI shows how many products are ready vs blocked
- API returns only real products based on DB truth
- API returns a stable generic product bundle that the EPREL project can translate
- blocked products are reported with explicit reasons
- ZIP generation stays in the EPREL project
- EPREL upload/compliance flow stays unchanged

---

## Non-Goals

This phase should **not** try to do the following:

- replace the live EPREL upload/compliance flow
- move ZIP generation into the central API
- expose the central API directly to the browser
- invent product data when mandatory values are missing
- onboard every unsupported family immediately
- create new abstraction layers before the mapping audit proves they are needed

---

## Success Criteria

The feature is done only when all of these are true:

1. User can choose a family from a controlled list.
2. Preview shows counts for:
   - total candidates
   - exportable products
   - blocked products
3. Export batch includes only products that pass strict validation.
4. Every exported EPREL model is built from the generic API bundle plus the EPREL field map.
5. Missing fields/files are returned explicitly per product.
6. No browser-side secret is needed.
7. Phase 1 works end-to-end for the first pilot family.

---

## Recommended First Pilot

Start with family `01` only.

Reason:

- `01` is already marked as `datasheet_runtime_supported`
- `01` already has a dedicated onboarding document in this repo
- `01` already has a real asset/runtime path
- `02` and `03` are legacy and explicitly out of scope in current T8 onboarding docs

Phase 1 meaning:

- first successful family import should be `01 = T8 AC`
- do not promise all families until the pilot proves enumeration, mapping, and file readiness

---

## Product Decision Summary

These decisions keep the implementation small and safe:

1. Use existing `families` endpoint for the family picker.
2. Reuse `code-explorer` logic for family enumeration.
3. Export only strict, real products by default.
4. Return blocked products in preview mode, not in final ZIP mode.
5. Keep the EPREL backend as a thin adapter.
6. Keep ZIP generation in the EPREL project.
7. Keep the API generic and keep the EPREL field map inside the EPREL project.

---

## High-Level Architecture

```text
User
  -> EPREL UI
  -> EPREL backend adapter
  -> central API (`api_nexled`)
  -> product DBs + runtime libs + DAM/file lookups
  -> EPREL backend adapter
  -> existing XML/ZIP generator
  -> ZIP download / EPREL upload flow
```

Why this is the right split:

- central API already knows product truth
- EPREL project already knows its own ZIP flow
- this avoids contract leakage and duplicated SQL

---

## End-to-End User Flow

### Phase 1 user flow

1. User opens EPREL page.
2. User chooses "Import family from database".
3. UI loads family list from the EPREL backend adapter.
4. User selects family `01`.
5. UI requests a preview.
6. Preview shows:
   - family name
   - total products found
   - ready products
   - blocked products
   - main blocking reasons
7. User clicks "Build ZIP".
8. EPREL backend requests strict family products from the central API.
9. EPREL backend applies the EPREL field map and sends translated `models` into the existing ZIP generator.
10. ZIP is created.
11. Existing EPREL upload flow continues unchanged.

### Preview mode behavior

Preview mode should be honest.

It should show:

- how many products are real
- how many are exportable
- why some are blocked

It should **not** silently hide failures.

### Build mode behavior

Build mode should be strict.

It should:

- include only exportable products
- fail fast if the batch has zero exportable products
- return a usable report when products were skipped

---

## Core Technical Decision: Reuse Code Explorer

Family batch export needs one hard thing first:

- enumerate all real product references for a family

This repo already has the best building block for that:

- `api/endpoints/code-explorer.php`
- `api/lib/code-explorer.php`

Why reuse it:

- it already understands family options
- it already understands `Luminos` identities
- it already distinguishes valid vs invalid combinations
- it already tracks datasheet readiness

What not to do:

- do not write a separate EPREL-only family enumeration engine
- do not loop over raw family tables in the EPREL project

Recommended rule:

- preview mode may include blocked rows
- strict export mode should use the equivalent of `datasheet_ready`

This is the most important architecture choice in the whole feature.

---

## Proposed Central API Contract

## Endpoint strategy

Keep existing endpoints untouched and add one new generic endpoint family:

- `GET /api/?endpoint=product-bundle&action=family-preview&family={code}`
- `GET /api/?endpoint=product-bundle&action=family-products&family={code}`

Why a dedicated endpoint is better than overloading others:

- the API still needs a clean batch contract
- the payload shape is batch-oriented
- it keeps the feature explicit and easy to reason about
- it stays generic enough for more than one consumer

## Why not expose DB-ish raw data

The API should not return a raw dump of SQL columns.

It should return:

- normalized product truth
- stable generic field keys
- blocking issues
- file readiness info

Important boundary rule:

- API responsibility = connect to DB/runtime and return normalized product data
- EPREL responsibility = keep the EPREL field map and translate API data into the EPREL `models` shape

That keeps the API reusable across other projects.

### `family-preview`

Purpose:

- safe preview before ZIP generation
- returns summary + sample rows + blocking reasons

Suggested query params:

- `family` required
- `limit` optional for preview row count
- `include_blocked=1` optional, default `1`

Suggested response shape:

```json
{
  "ok": true,
  "data": {
    "family": {
      "codigo": "01",
      "nome": "T8 AC",
      "product_type": "tubular",
      "datasheet_runtime_supported": true
    },
    "summary": {
      "candidate_count": 0,
      "valid_count": 0,
      "ready_count": 0,
      "blocked_count": 0
    },
    "products": [
      {
        "reference": "<full_reference>",
        "identity_ref": "<identity_reference>",
        "description": "<description>",
        "status": "ready",
        "missing_fields": [],
        "missing_files": [],
        "warnings": []
      }
    ],
    "issues_by_type": [
      {
        "code": "missing_fiche_pdf",
        "count": 0
      }
    ]
  }
}
```

### `family-products`

Purpose:

- return the full strict generic product bundle for one family

Suggested query params:

- `family` required
- `strict=1` default
- `include_blocked=0` default

Suggested response shape:

```json
{
  "ok": true,
  "data": {
    "family": {
      "codigo": "01",
      "nome": "T8 AC"
    },
    "summary": {
      "ready_count": 0,
      "blocked_count": 0
    },
    "products": [
      {
        "reference": "<full_reference>",
        "description": "<description>",
        "fields": {
          "power_w": 0,
          "luminous_flux_lm": 0,
          "energy_class": "E",
          "cct_k": 3000,
          "cri": 80,
          "beam_angle_deg": 120,
          "ip_rating": "IP65"
        },
        "files": {
          "product_image": "<file_ref_or_null>",
          "label_pdf": "<file_ref_or_null>",
          "fiche_pdf": "<file_ref_or_null>"
        },
        "validation": {
          "status": "ready",
          "blocking_reasons": []
        }
      }
    ],
    "blocked": []
  }
}
```

Important note:

- the API returns generic products, not EPREL-shaped models
- the EPREL project must translate `products[].fields` and `products[].files` into the exact `models` shape expected by its live ZIP generator
- because that generator lives outside this repo, the first implementation step is a contract audit of the current EPREL `models` object and field names

---

## EPREL Backend Adapter Contract

The EPREL project should keep its own frontend/backend contract.

Recommended adapter behavior:

- EPREL frontend calls local `/api/...`
- EPREL backend calls central API with `X-API-Key`
- EPREL backend translates response from `ok/data/error` to `success/data/error`
- EPREL backend isolates Railway URL, auth, timeouts, and retries

Recommended external routes in the EPREL project:

- `GET /api/database/families`
- `GET /api/database/family-preview?family=01`
- `POST /api/database/family-zip`

Suggested phase 1 flow:

- `/api/database/families` proxies central `families` and filters to supported rows
- `/api/database/family-preview` proxies central `product-bundle family-preview`
- `/api/database/family-zip` fetches central `family-products`, applies the EPREL field map locally, then passes `models` into the existing ZIP generator

Why a local EPREL adapter is mandatory:

- browser must not hold the central API key
- EPREL app already has its own contract
- future EPREL-specific fixes should not force central API contract drift

---

## Data Pipeline Design

The overall feature should have 4 stages.

### Stage 1: enumerate product candidates

Input:

- family code

Source:

- `code-explorer` logic

Output:

- full references or normalized product candidates for that family

Rule:

- no raw SQL loops in a separate EPREL-only implementation

### Stage 2: enrich each product

For each candidate product, collect:

- decoded reference segments
- `Luminos` identity data
- textual description
- luminotechnical values
- characteristics/spec values
- datasheet/file readiness signals

Preferred source of truth order:

1. existing runtime libs in this repo
2. live DB truth

### Stage 3: build generic API product bundle

Convert normalized product truth into a stable generic product bundle with:

- stable field keys
- file refs
- validation issues

This stage is still API-owned because it is generic, not EPREL-specific.

### Stage 4: map generic product bundle to EPREL model

Convert the generic API product bundle into:

- exact EPREL `models` shape
- XML-ready values
- ZIP-ready file refs

This stage is EPREL-owned.
This is where the EPREL field map belongs.

---

## Recommended Data Model Returned By The Central API

Even if the final `models` payload must match the live EPREL generator exactly, the internal builder should think in this normalized shape first:

| Group | Purpose | Examples |
|---|---|---|
| `identity` | what product this is | family code, full reference, identity ref, description |
| `specs` | normalized technical truth | flux, power, CCT, CRI, beam angle, voltage |
| `dimensions` | physical data | length, width, height, diameter |
| `commercial` | labels for export | family name, product type, model label |
| `files` | assets/documents | label, fiche, image, optional PDF refs |
| `validation` | readiness state | status, warnings, blocking reasons |

Reason:

- easier to debug
- easier to preview
- easier to adapt if EPREL model shape changes later
- reusable by website/store/internal tools without EPREL coupling

---

## Mapping Strategy

Before coding, create one EPREL field-by-field mapping matrix inside the EPREL project.

Minimum required columns:

| Column | Meaning |
|---|---|
| `eprel_field` | exact field name expected by the live EPREL generator |
| `api_key` | normalized field key or file key from the central API response |
| `required` | yes/no |
| `transform` | formatting or unit conversion rule |
| `blocking_if_missing` | yes/no |
| `notes` | edge cases |

### Recommended mapping groups

Use this as the initial audit checklist.

| Group | Typical source | Blocking if missing |
|---|---|---|
| Brand/supplier identity | static config in EPREL or generic API field | yes |
| Model identifier/reference | generic API identity fields | yes |
| Family/product naming | generic API identity/commercial fields | yes |
| Luminotechnical values | generic API `fields.*` | usually yes |
| Electrical values | generic API `fields.*` | usually yes |
| Mechanical values | generic API `fields.*` | depends on EPREL field |
| Control/dimming booleans | generic API `fields.*` | depends |
| Label/Fiche document refs | generic API `files.*` or EPREL local storage | yes for final ZIP |
| Optional marketing fields | generic API field or EPREL static/manual | no |

### Hard rule

Do not hide missing data inside the EPREL map or inside PHP defaults.

If a required field is missing:

- preview should report it
- strict export should block that product

### API-side rule

The central API may still use DB queries, runtime helpers, and calculations internally.
But it should expose the result as generic stable keys like:

- `fields.power_w`
- `fields.luminous_flux_lm`
- `fields.energy_class`
- `fields.beam_angle_deg`
- `fields.ip_rating`
- `files.product_image`
- `files.label_pdf`
- `files.fiche_pdf`

Not raw SQL names like:

- `Luminos.potencia`
- `caracteristicas.valor_pt`
- `angulos_lente.beam`

---

## File and Document Readiness

The batch flow is not just data.
It also needs the files required by the current EPREL ZIP process.

Because the live EPREL project is external to this repo, the exact required file set must be audited first.

Most likely required categories are:

- XML payload data
- energy label file
- fiche file
- optional supporting image/document refs depending on the current ZIP builder

### Recommended resolution order

For each required file class:

1. current EPREL project canonical storage, if that is already the live truth
2. DAM-backed canonical ref, if EPREL is ready to consume DAM refs
3. block export if neither source resolves

### Why this matters

If the API returns data without file readiness, the ZIP step will fail later and the user will not know why.

---

## Validation Model

Each product should end in one state:

| State | Meaning | Included in strict export |
|---|---|---|
| `ready` | all required fields/files resolved | yes |
| `blocked_data` | missing mandatory EPREL field | no |
| `blocked_file` | missing required file/doc | no |
| `blocked_runtime` | family/product not supported by current runtime | no |
| `blocked_unknown` | unexpected internal failure | no |

Each blocked row should also include machine-readable reasons, for example:

- `missing_luminos_identity`
- `family_runtime_not_supported`
- `missing_required_eprel_field`
- `missing_label_file`
- `missing_fiche_file`
- `missing_dimension_data`

This makes the preview actually usable.

---

## Family Selection Rules

The family picker should not show all families equally.

Recommended behavior:

- default list = families where:
  - `datasheet_runtime_supported = true`
  - `has_luminos_identities = true`
- optionally allow an admin toggle to show unsupported families for audit/debug

Reason:

- avoids false user expectation
- keeps phase 1 honest

For the first release, it is acceptable to hard-focus the EPREL family import UI on:

- `01`

Then expand after the pilot is proven.

---

## Implementation Plan

## Phase 0 - Contract Audit

Goal:

- lock the exact shape expected by the live EPREL ZIP generator

Tasks:

- inspect current live EPREL `models` object shape
- list required fields vs optional fields
- list required files in the ZIP package
- confirm current ZIP generator failure behavior

Artifacts:

- final EPREL field mapping matrix
- final file requirement matrix

Exit criteria:

- no ambiguous field names remain

## Phase 1 - Central API Preview Endpoint

Goal:

- expose family-level preview and issue reporting from this repo

Tasks in this repo:

- add `product-bundle` route to `api/index.php`
- add `api/endpoints/product-bundle.php`
- add one implementation lib, preferably `api/lib/product-bundle.php`
- reuse `code-explorer` logic for enumeration
- return preview summary + blocking reasons

Exit criteria:

- `family-preview` works for family `01`
- preview counts are stable and explain failures

## Phase 2 - Central API Generic Family Products Endpoint

Goal:

- return full strict generic product bundles for one family

Tasks in this repo:

- build generic field bundle layer
- return stable `fields` and `files` keys
- include blocked list separately

Exit criteria:

- central API can return strict generic product bundles for `01`

## Phase 3 - EPREL Backend Adapter

Goal:

- connect live EPREL backend to the central API without exposing secrets

Tasks in EPREL project:

- create proxy routes
- add central API auth header
- translate error envelope
- add the EPREL field map
- translate generic API products into current `models`
- feed translated `models` into current ZIP generator

Exit criteria:

- EPREL backend can build a ZIP from central API output

## Phase 4 - EPREL UI

Goal:

- expose a simple batch family import flow

Tasks in EPREL project:

- enable family picker UI
- add preview step
- add build button
- display ready vs blocked counts
- display failure reasons

Exit criteria:

- user can pick `01`, preview, and build a ZIP

## Phase 5 - Pilot Validation

Goal:

- prove one real family works end-to-end

Recommended pilot:

- family `01`

Checks:

- preview count is believable
- ZIP count matches exportable count
- random sample of products in ZIP matches expected truth
- blocked products are correctly reported

Exit criteria:

- first production-safe family import release

## Phase 6 - Expansion

Goal:

- widen to more supported families after pilot confidence exists

Rules:

- expand only to families already marked `datasheet_runtime_supported`
- do not onboard unsupported families by wishful thinking

---

## Files Likely To Change In This Repo

Phase 1 and Phase 2 should likely touch only:

- `api/index.php`
- `api/endpoints/product-bundle.php` (new)
- `api/lib/product-bundle.php` (new)
- `api/README.md`
- this roadmap file

Possible reuse points:

- `api/lib/code-explorer.php`
- `api/lib/reference-decoder.php`
- `api/lib/luminotechnical.php`
- `api/lib/characteristics.php`
- `api/lib/product-header.php`
- `api/lib/technical-drawing.php`
- `api/lib/sections.php`
- `api/lib/images.php`
- `api/lib/family-registry.php`

Rule:

- keep changes surgical
- do not refactor unrelated endpoints

---

## Files Likely To Change In The External EPREL Project

Exact paths depend on that project, but likely touch points are:

- EPREL page with disabled database import affordance
- EPREL page or modal that handles ZIP generation
- frontend API wrapper
- backend route layer under `/api`
- current XML/ZIP generator integration point

Recommended mindset:

- minimal UI
- thin backend adapter
- reuse current ZIP generator

---

## Error Handling Rules

The feature should fail honestly.

### Central API errors

Use explicit HTTP status codes:

- `400` invalid request
- `404` unknown endpoint/action
- `409` family recognized but not exportable in current runtime
- `422` family exists but batch has blocking validation issues
- `500` internal processing error

### EPREL adapter behavior

The EPREL backend should:

- preserve machine-readable blocking details
- normalize them to its frontend contract
- avoid collapsing everything into one generic error message

---

## Security Rules

These are non-negotiable:

- browser must not call central API directly
- browser must not receive the central API key
- EPREL backend should use its own server-side key
- create a distinct key for the EPREL consumer if one does not already exist

Optional but recommended:

- log family code requested
- log exportable count and blocked count
- log batch failures by reason

---

## Performance Rules

Do not optimize early, but do not ignore scale either.

Expected batch cost drivers:

- family enumeration size
- per-product enrichment
- file readiness checks

Phase 1 rule:

- no caching unless runtime proves necessary

If performance later becomes a problem:

- cache only preview summaries
- keep strict generic product export fresh

---

## If Current DB Truth Is Not Enough

Do not create extra API tables immediately.

First:

- finish the EPREL field audit
- prove which fields are truly missing

Only if required fields cannot be derived from current truth, add one small EPREL-side override layer.

Recommended smallest possible design in the EPREL project:

```json
{
  "01037581110010100": {
    "supplier_name": "Nexled",
    "some_missing_eprel_field": "manual value"
  }
}
```

Why this shape:

- tiny
- explicit
- product-scoped
- avoids polluting the central API with EPREL-only exceptions

Do not create this override layer unless the audit proves it is necessary.

---

## Risks

## Risk 1 - Unknown live EPREL model shape

Problem:

- this repo does not contain the current live generator code

Mitigation:

- Phase 0 contract audit before implementation

## Risk 2 - Family count explosion

Problem:

- some families may expand into many product combinations

Mitigation:

- preview first
- strict export only for ready rows
- pilot on `01`

## Risk 3 - Missing EPREL files

Problem:

- DB fields may exist while labels/fiches/docs do not

Mitigation:

- file readiness must be part of preview and strict validation

## Risk 4 - Mapping drift between projects

Problem:

- API and EPREL may drift if generic API keys are unstable or if EPREL starts reading raw DB-ish fields

Mitigation:

- keep mapping ownership in the EPREL project
- keep the central API response generic and stable
- do not let EPREL depend on raw DB column names

## Risk 5 - Unsupported families shown too early

Problem:

- user selects a family that current runtime cannot actually export

Mitigation:

- filter family picker by supported/runtime-ready metadata

---

## Open Questions

These must be answered before implementation starts:

1. What is the exact `models` payload shape expected by the current EPREL ZIP generator?
2. Which fields are mandatory vs optional in that generator?
3. Which files are mandatory in the ZIP package?
4. Are label and fiche files currently local to the EPREL project, or should they come from DAM/central refs?
5. Should strict export include only `datasheet_ready`, or can some `datasheet_blocked` rows still be EPREL-valid?
6. Do we need one ZIP per family or optional chunking for very large families?
7. Should phase 1 include only `01`, or all `datasheet_runtime_supported` families after the audit?

---

## Definition Of Done

The feature can be called done when:

- central API exposes `family-preview`
- central API exposes `family-models`
- family picker works in the EPREL app
- preview shows ready/blocked counts
- build ZIP works from family import
- blocked products are explained
- pilot family `01` works end-to-end
- docs are updated after implementation with the final contract

---

## Recommended Next Step

Before writing code, do exactly this:

1. inspect the live EPREL ZIP generator payload shape
2. write the final EPREL field mapping matrix
3. implement the central API preview endpoint
4. validate with family `01`
5. only then connect the EPREL backend/UI

That is the shortest safe path.
