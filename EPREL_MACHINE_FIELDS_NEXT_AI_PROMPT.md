# EPREL AI Handoff: Machine Fields + Bulk Build Contract

You are working on the **EPREL app** side.

Central API repo:
- `C:\xampp\htdocs\api_nexled`

EPREL repo:
- separate system/client

This file is the **one-source handoff** for the latest Central API updates needed by EPREL bulk build.

Use this file as:
- update note
- contract doc
- implementation plan

Do not guess.
Do not derive fake products.
Do not scrape PDF text if the API already gives machine fields.

## Core Rule

- Central API = truth
- EPREL = workflow/import client

EPREL must:
- call Central API
- import real rows
- map machine fields into EPREL XML/build models

EPREL must **not**:
- generate theoretical family combinations
- guess missing readiness
- scrape descriptive text for machine fields when API exposes them

## What Just Changed In Central API

Central API now exposes a grouped machine-readable object:

- `eprel_fields`

This object is now available in:

1. `family-ready-products`
2. exact `pdf_specs`

That means EPREL no longer needs to scrape:
- `characteristics[]`
- PDF text
- localized labels

for these machine fields.

## New Machine Fields Now Exposed

For each real ready product/reference, Central API now exposes:

- `energy_class`
- `luminous_flux`
- `chrom_x`
- `chrom_y`
- `r9`
- `cri_min`
- `cri_max`

Grouped shape:

```json
"eprel_fields": {
  "energy_class": "D",
  "luminous_flux": 317,
  "chrom_x": 0.0,
  "chrom_y": 0.0,
  "r9": 0,
  "cri_min": 0,
  "cri_max": 0
}
```

Important:
- these are **real upstream values only**
- no fake defaults were added in API logic
- values come from proven internal sources:
  - luminotechnical logic
  - LED table lookup by `led_id`

## Fields Still Missing Upstream

These were **not** added because upstream proof does not exist:

- `on_market_date`
- `survival`
- `lumen_maint`

EPREL must treat these as:
- still missing
- not provided by Central API

Do **not** fake them in EPREL.

## Field Note

- `tech_lum_flux` does not need its own Central API source if EPREL can map it from:
  - `eprel_fields.luminous_flux`

So EPREL should:
- use `luminous_flux` as truth
- derive alias only in EPREL model mapping if XML schema needs a different field name

## Current Endpoint Contract

### 1. Ready family rows

`GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

Rows already include:
- `reference`
- `identity`
- `description`
- `product_type`
- `product_id`
- `led_id`
- `configurator_valid`
- `datasheet_ready`
- `pdf_file_name`
- `pdf_url`
- `spectral_file_name`
- `spectral_url`
- `eprel_fields`

Example row shape:

```json
{
  "reference": "01018025111010100",
  "identity": "0101802511",
  "description": "LLED T8 26 x 228mm CW503",
  "product_type": "tubular",
  "product_id": "T8/PC/22/3s",
  "led_id": "CW503",
  "configurator_valid": true,
  "datasheet_ready": true,
  "eprel_fields": {
    "energy_class": "D",
    "luminous_flux": 317,
    "chrom_x": 0.0,
    "chrom_y": 0.0,
    "r9": 0,
    "cri_min": 0,
    "cri_max": 0
  },
  "pdf_file_name": "01018025111010100.pdf",
  "pdf_url": "https://.../api/?endpoint=file-datasheet&reference=01018025111010100",
  "spectral_file_name": "01018025111010100.png",
  "spectral_url": "https://.../api/?endpoint=file-spectral&reference=01018025111010100"
}
```

### 2. Exact reference debug/inspection

`GET /api/?endpoint=code-explorer&family=01&action=pdf_specs&reference=01018025111010100`

This now also includes:
- `eprel_fields`

Use this for:
- debug
- contract inspection
- exact-reference investigation

Do **not** use `pdf_specs` as the main EPREL family import source.

Main import source remains:
- `family-ready-products`

### 3. Files

Still use:

- `pdf_url`
- `spectral_url`

Do not guess:
- filenames
- DAM paths
- Cloudinary paths

## Auth

Use:

- header: `X-API-Key: <key>`

EPREL should call Central API server-side with that header.

## Filters Still Work

EPREL can still narrow rows with:

- `product_type`
- `size`
- `color`
- `cri`
- `series`
- `lens`
- `finish`
- `cap`

And filter discovery still comes from:

- `GET /api/?endpoint=family-ready-filters&family=01`

Rules:
- raw codes
- comma-separated multi-select
- `AND` between different filters
- `OR` inside same filter

## How EPREL Should Use The New Fields

### Preview flow

EPREL should:
1. request `family-ready-filters`
2. let user choose filters
3. request filtered `family-ready-products`
4. show ready rows
5. include machine-field presence in preview/debug if useful

### Build flow

For each imported row:
1. store current file refs
   - `pdf_url`
   - `spectral_url`
2. store `eprel_fields`
3. map `eprel_fields` into EPREL XML/build model
4. keep missing fields missing if API does not provide them

### Mapping rule

EPREL should map:

- `eprel_fields.energy_class`
- `eprel_fields.luminous_flux`
- `eprel_fields.chrom_x`
- `eprel_fields.chrom_y`
- `eprel_fields.r9`
- `eprel_fields.cri_min`
- `eprel_fields.cri_max`

If EPREL XML schema wants renamed fields:
- map from these API fields in EPREL adapter layer
- do not ask Central API to emit duplicate aliases unless really needed later

## Important Caveat

There is an existing Central API performance/cache issue on some very large families after cache regeneration.

Observed:
- smaller ready family checks work
- exact `pdf_specs` checks work
- some large `family-ready-products` rebuilds can still time out locally after cache bump

Meaning for EPREL:
- API contract is updated
- machine fields are implemented
- if a very large family preview/build is slow, that is a separate Central API cache/performance follow-up
- do not “fix” this in EPREL by inventing local filtering or local product generation

## Proven Test Signals From Central API Side

Confirmed on exact `pdf_specs`:
- family `01`
  - `01018025111010100`
- family `05`
  - `05025725111010100`
- family `29`
  - `29012022191010000`

Confirmed on `family-ready-products` row contract:
- family `29`

## What EPREL AI Should Build Next

### Phase 1. Contract consumption

Update EPREL Central API client so `family-ready-products` row parsing includes:
- `eprel_fields`

### Phase 2. Internal model mapping

Map `eprel_fields` into EPREL build/model layer.

Goal:
- XML builder uses structured machine fields
- no text scraping fallback for these fields

### Phase 3. Preview/debug UI

Optional but useful:
- show `eprel_fields` in build preview/debug panel
- especially when validating why XML passes/fails

### Phase 4. Missing-field handling

For still-missing upstream fields:
- `on_market_date`
- `survival`
- `lumen_maint`

EPREL should:
- leave them unresolved/missing
- surface clear validation state if XML still requires them

Do not:
- invent placeholders
- copy values from unrelated fields

### Phase 5. XML validation pass

After mapping:
1. run one-family filtered import
2. build staged ZIP/XML
3. verify that the new machine fields are coming from API payload, not text parsing

## Acceptance Criteria For EPREL Side

Done means:

1. EPREL reads `eprel_fields` from `family-ready-products`
2. EPREL uses the same fields from `pdf_specs` for exact-reference debug if needed
3. EPREL maps:
   - `energy_class`
   - `luminous_flux`
   - `chrom_x`
   - `chrom_y`
   - `r9`
   - `cri_min`
   - `cri_max`
4. EPREL does not scrape those fields from text anymore
5. EPREL does not fake:
   - `on_market_date`
   - `survival`
   - `lumen_maint`
6. build flow still uses:
   - `pdf_url`
   - `spectral_url`
7. EPREL remains a client/workflow layer, not a product-truth engine

## Final Rule

If Central API provides machine truth:
- EPREL should consume it directly

If Central API does not provide a field:
- EPREL should treat it as missing
- not guess

That is the correct split.
