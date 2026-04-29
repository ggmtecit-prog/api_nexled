# EPREL Family Asset Delivery Plan

Status:
- Planning document
- Created on 2026-04-21
- No runtime changes in this document

Audience:
- Central API engineers
- EPREL engineers
- AI agents continuing the feature work

Purpose:
- turn the EPREL bulk-build asset requirement into a concrete Central API implementation plan
- compare Option A vs Option B
- lock the recommended first implementation path

## Short Decision

Recommended first implementation:
- **Option A**

Meaning:
- enrich `family-ready-products` rows with file refs
- add Central API download endpoints for:
  - technical PDF
  - spectral image

Reason:
- smallest change from current live API
- best fit for current EPREL page-by-page import flow
- least EPREL wiring
- fastest path to a working end-to-end ZIP build

Option B is still valid later, but should be treated as:
- performance optimization
- separation refactor
- v2 path if page rows become too heavy

## Current Truth

What Central API already has:
- `family-ready-products`
- real ready rows built from:
  - real `Luminos` identities
  - existing `configurator_valid`
  - existing `datasheet_ready`

What Central API does **not** have yet:
- downloadable technical PDF endpoint per ready reference
- downloadable spectral image endpoint per ready reference
- asset file refs in `family-ready-products`

What EPREL needs to finish ZIP build:
- product data
- technical PDF file
- spectral image file

So current gap is:
- **file delivery**, not ready-row selection

## Option Comparison

### Option A

Enrich:
- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

Each row gets:
- `pdf_file_name`
- `pdf_url`
- `spectral_file_name`
- `spectral_url`

#### Pros
- simplest EPREL integration
- one page response contains everything needed to stage page rows
- no second metadata merge step
- best fit for current EPREL importer direction

#### Cons
- heavier response rows
- asset URL fields appear even when caller only wants product rows

### Option B

Keep ready rows light.
Add:
- `POST /api/?endpoint=family-ready-assets`

Input:
- `references: []`

Output:
- file refs for those exact references only

#### Pros
- cleaner separation
- lighter `family-ready-products`
- better if more asset types are added later

#### Cons
- extra request per page
- more EPREL-side wiring
- more moving parts for first rollout

## Recommendation

Implement now:
- **Option A**

Keep in reserve:
- **Option B**

Trigger for later migration to B:
- family page payload becomes materially heavy
- asset generation becomes expensive
- more downstream consumers need row data without file refs

## Architecture Rule

Central API owns:
- which ready references exist
- which files belong to each ready reference
- file delivery contract

EPREL owns:
- page import flow
- local staging folders
- retry/resume
- final XML + ZIP assembly

EPREL must **not**:
- guess DAM paths
- guess filenames
- build datasheets itself
- derive spectral file identity by convention unless Central API officially guarantees it

## Recommended API Contract

### 1. Enriched ready-products rows

Endpoint:
- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

Auth:
- `X-API-Key: <key>`

Recommended row shape:

```json
{
  "reference": "01018002111010100",
  "identity": "0101800211",
  "description": "T8 LED 23 cm",
  "product_type": "tubular",
  "product_id": "T8LED/23/3s",
  "led_id": "3528XN",
  "configurator_valid": true,
  "datasheet_ready": true,
  "pdf_file_name": "01018002111010100.pdf",
  "pdf_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-datasheet&reference=01018002111010100",
  "spectral_file_name": "01018002111010100.png",
  "spectral_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-spectral&reference=01018002111010100"
}
```

### 2. PDF download endpoint

Endpoint:
- `GET /api/?endpoint=file-datasheet&reference=01018002111010100`

Auth:
- `X-API-Key: <key>`

Behavior:
- validate reference
- ensure reference is real
- ensure reference is `configurator_valid`
- ensure reference is `datasheet_ready`
- return PDF bytes

Headers:
- `Content-Type: application/pdf`
- `Content-Disposition: attachment; filename="01018002111010100.pdf"`

Error shape:

```json
{
  "error": "Reference is not datasheet-ready"
}
```

### 3. Spectral download endpoint

Endpoint:
- `GET /api/?endpoint=file-spectral&reference=01018002111010100`

Auth:
- `X-API-Key: <key>`

Behavior:
- validate reference
- ensure reference is real
- ensure reference is `datasheet_ready`
- resolve exact spectral graph through Central API truth
- return image bytes

Recommended normalization:
- always return `PNG`

Reason:
- stable ZIP behavior
- simpler EPREL handling
- avoids SVG-vs-PNG logic in EPREL

Headers:
- `Content-Type: image/png`
- `Content-Disposition: attachment; filename="01018002111010100.png"`

Error shape:

```json
{
  "error": "Spectral image not available for reference"
}
```

## What Central API Should Not Expose

Do **not** make EPREL depend on:
- DAM public ids
- Cloudinary public ids
- raw internal DAM URLs
- guessed local asset paths

Preferred rule:
- Central API returns Central API URLs
- Central API hides DAM/storage internals

## Concrete Implementation Plan

### Phase 1. Contract extension in ready rows

Goal:
- make `family-ready-products` row contract EPREL-usable

Tasks:
- extend row assembler in `api/lib/code-explorer.php`
- add:
  - `pdf_file_name`
  - `pdf_url`
  - `spectral_file_name`
  - `spectral_url`
- keep those fields present only on rows already proven ready

Notes:
- URL generation should be cheap string assembly
- no per-row live PDF generation during list call

### Phase 2. Datasheet file endpoint

Goal:
- make one reference downloadable as technical PDF

Tasks:
- add route in `api/index.php`
- create `api/endpoints/file-datasheet.php`
- reuse existing datasheet generation logic
- ensure endpoint returns bytes, not JSON, on success

Rules:
- must fail honest for not-ready references
- should not bypass current readiness truth

### Phase 3. Spectral file endpoint

Goal:
- make one reference downloadable as spectral image

Tasks:
- add route in `api/index.php`
- create `api/endpoints/file-spectral.php`
- resolve graph using same source of truth as datasheet readiness
- normalize output to PNG

Rules:
- no filename guessing in EPREL
- no raw DAM contract leaking to EPREL

### Phase 4. Shared helper extraction

Goal:
- avoid duplicated row/asset truth logic

Tasks:
- factor helper(s) for:
  - file names
  - asset endpoint URLs
  - exact spectral graph resolution
- keep helpers under Central API lib layer

Suggested direction:
- one small helper for public asset contract
- do not over-abstract

### Phase 5. Test with safe families

Start with:
- `01`
- `05`

Reason:
- already proven in current datasheet-ready work
- smaller and safer than giant family `11`

Recommended first refs:
- `01018025111010100`
- `01054425121010100`
- `01054491111010100`
- `05025725111010100`
- `05025727111010100`
- `05025732111010100`

### Phase 6. EPREL consumption

After API contract is live:
- EPREL fetches `family-ready-products`
- EPREL downloads `pdf_url`
- EPREL downloads `spectral_url`
- EPREL stores staged files locally
- EPREL sets:
  - `pdf_name`
  - `img_name`

## Performance Notes

Safe approach for first rollout:
- list endpoint only emits URL strings
- file bytes are downloaded only when EPREL actually stages that page

Avoid:
- generating every family PDF eagerly in one list call
- HEAD/byte validation on every asset URL during row pagination
- one extra metadata request per product

## Error Behavior To Lock

### Ready-products row call

If family has no ready rows:
- return valid empty `rows`
- valid `summary`
- valid `pagination`

### File endpoints

If reference is invalid:
- `400`

If reference is not found / not real:
- `404`

If reference is real but not ready:
- `409` or `422`

Recommended:
- use `422` for not-ready asset/file requests

If file generation/resolution crashes internally:
- `500`

## Acceptance Criteria

This feature is done when:
- `family-ready-products` returns ready rows plus file refs
- `file-datasheet` returns real PDF bytes for ready refs
- `file-spectral` returns real PNG bytes for ready refs
- EPREL can stage:
  - row data
  - PDF file
  - spectral image
- EPREL can set valid:
  - `pdf_name`
  - `img_name`
- final ZIP can be built without attachment gaps for proven ready references

## Final Recommendation

Build now:
- Option A
- with Central API-hosted download endpoints

Do later only if needed:
- Option B bulk asset endpoint

Short rule:
- Central API gives both:
  - ready rows
  - ready file refs
- EPREL downloads and stages them

