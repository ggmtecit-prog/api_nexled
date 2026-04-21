# Next AI Prompt: EPREL Family Import

You are working across two NexLed systems:

- Central API repo: `C:\xampp\htdocs\api_nexled`
- EPREL tool repo: separate system/client

Your job is to make EPREL consume the new Central API family-import feature correctly.

Hard split:

- Central API = truth
- EPREL = workflow

Do not mix them.

## Read First

1. [PROJECT_MEMORY.md](PROJECT_MEMORY.md)
2. [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md)
3. [api/EPREL_FAMILY_ASSET_DELIVERY_PLAN.md](api/EPREL_FAMILY_ASSET_DELIVERY_PLAN.md)
4. [api/PRODUCT_ONBOARDING_MEMORY.md](api/PRODUCT_ONBOARDING_MEMORY.md)
5. [api/DAM_API_CONTRACT.md](api/DAM_API_CONTRACT.md)

Then inspect Central API files:

1. [api/index.php](api/index.php)
2. [api/endpoints/family-ready-products.php](api/endpoints/family-ready-products.php)
3. [api/lib/code-explorer.php](api/lib/code-explorer.php)
4. [api/endpoints/file-datasheet.php](api/endpoints/file-datasheet.php)
5. [api/endpoints/file-spectral.php](api/endpoints/file-spectral.php)
6. [api/lib/pdf-engine.php](api/lib/pdf-engine.php)
7. [api/endpoints/families.php](api/endpoints/families.php)

## What Changed In Central API

Central API now has a live endpoint:

- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

And live file endpoints:

- `GET /api/?endpoint=file-datasheet&reference=01018025111010100`
- `GET /api/?endpoint=file-spectral&reference=01018025111010100`

It returns only rows where:

- `configurator_valid = true`
- `datasheet_ready = true`

It does not use the old giant synthetic family-wide explorer flow as the final import source.

Current endpoint behavior:

1. start from real `Luminos` identities
2. evaluate ready base combos:
   - `identity + lens + finish + cap`
3. expand those ready base combos into full references with unique option codes
4. paginate the final ready rows
5. cache ready base combos per family in:
   - `output/family-ready-products/<family>.json`

Current asset rule:

- ready rows now carry file refs for EPREL
- Central API returns Central API URLs
- EPREL must not know DAM/Cloudinary internals

This means:

- first request for a big family can be slower
- repeated page requests should be much faster

## Use This, Not That

Use:

- `family-ready-products`

Do not use for family import:

- family-wide `code-explorer`
- local EPREL-side code generation
- local EPREL-side readiness guessing

`code-explorer` remains analysis tooling only.

## Endpoint Contract

### Central API request

Example:

- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

Auth:

- `X-API-Key: <key>`

### Central API response

```json
{
  "family": {
    "code": "01",
    "name": "T8 AC"
  },
  "summary": {
    "total_ready_products": 32038
  },
  "pagination": {
    "page": 1,
    "page_size": 100,
    "total_pages": 321,
    "total_rows": 32038
  },
  "rows": [
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
  ]
}
```

### File endpoint contract

PDF:

- `GET /api/?endpoint=file-datasheet&reference=<17-char-ref>`
- auth: `X-API-Key`
- success:
  - `200`
  - `Content-Type: application/pdf`
  - `Content-Disposition: attachment; filename="<reference>.pdf"`

Spectral image:

- `GET /api/?endpoint=file-spectral&reference=<17-char-ref>`
- auth: `X-API-Key`
- success:
  - `200`
  - `Content-Type: image/png`
  - `Content-Disposition: attachment; filename="<reference>.png"`

Important:

- `file-spectral` always returns PNG bytes
- EPREL should store exact filenames from:
  - `pdf_file_name`
  - `spectral_file_name`
- EPREL should not invent names

## What EPREL Must Build

### 1. Server-side adapter

EPREL backend should call Central API server-side only.

Browser must never receive Central API secret.

Recommended EPREL backend routes:

- `GET /api/database/families`
- `GET /api/database/family-preview?family=01&page=1&page_size=100`
- `POST /api/database/family-models`

Suggested mapping:

- `families`
  - proxy Central API `families`
- `family-preview`
  - call Central API `family-ready-products`
  - return preview rows + summary + pagination
- `family-models`
  - page through `family-ready-products`
  - download:
    - `pdf_url`
    - `spectral_url`
  - map Central API rows into EPREL `models`
  - set:
    - `pdf_name`
    - `img_name`
  - feed current ZIP generator

### 2. UI flow

EPREL frontend should:

1. load family list
2. let user pick one family
3. preview ready rows
4. show count:
   - total ready products
5. let user import/build ZIP
6. fetch page by page until done
7. for each page:
   - download PDF files
   - download spectral images
   - stage files locally

### 3. Resume / retry

EPREL should:

- store current page progress
- allow retry from failed page
- avoid re-importing already saved rows when resuming

### 4. Mapping layer

EPREL must map Central API product rows into EPREL `models`.

Do not ask Central API to become EPREL-specific.

Keep:

- EPREL field map
- EPREL ZIP generation
- EPREL upload flow
- EPREL local job staging

inside EPREL repo.

## What EPREL Must Not Do

1. do not brute-force family combinations locally
2. do not guess missing suffixes
3. do not treat dropdown options as real products
4. do not import rows that are only `configurator_valid`
5. do not rebuild NexLed validation logic
6. do not expose Central API key in browser
7. do not guess DAM paths or Cloudinary URLs
8. do not guess `pdf_name` or `img_name`

## Safe Starting Family

Start with:

- `01`

Then:

- `05`

Only after that:

- larger families like `11`

Reason:

- small families are safer to validate first
- big families can have expensive first-cache build

Safe file-flow test refs:

- `01018025111010100`
- `01054425121010100`
- `01054491111010100`
- `05025725111010100`
- `05025727111010100`
- `05025732111010100`

## Acceptance Criteria

This feature is done when:

1. EPREL can preview one family from Central API
2. preview uses `family-ready-products`, not `code-explorer`
3. EPREL imports page by page
4. imported rows are only ready rows
5. EPREL downloads PDF + spectral file for each imported row
6. staged models contain valid:
   - `pdf_name`
   - `img_name`
7. ZIP/model build uses imported rows only
8. empty family returns valid empty state
9. browser never sees Central API secret

## If Something Fails

Check in this order:

1. Central API route works
2. Central API auth/header works
3. EPREL backend proxy works
4. pagination loop works
5. file downloads work
6. EPREL mapping into `models` works

If product counts look too high or weird:

- do not "fix" it in EPREL by filtering on guesses
- inspect Central API family response first

If files are missing:

1. test `pdf_url` directly
2. test `spectral_url` directly
3. inspect Central API `failure_reason` truth
4. do not guess replacement files in EPREL

## User-Facing Summary Style

Keep updates short.

Say:

- what changed
- what was verified
- what is still blocked

Do not add noise.
