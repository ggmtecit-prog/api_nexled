# Next AI Prompt: EPREL Family Filter Integration

You are working across two NexLed systems:

- Central API repo: `C:\xampp\htdocs\api_nexled`
- EPREL tool repo: separate system/client

Your job:
- make EPREL consume the new Central API family-filter feature correctly

Hard split:

- Central API = truth
- EPREL = workflow + UI

Do not mix them.

## Read First

1. [PROJECT_MEMORY.md](PROJECT_MEMORY.md)
2. [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md)
3. [api/EPREL_FAMILY_FILTER_PLAN.md](api/EPREL_FAMILY_FILTER_PLAN.md)
4. [EPREL_NEXT_AI_PROMPT.md](EPREL_NEXT_AI_PROMPT.md)

Then inspect Central API files:

1. [api/index.php](api/index.php)
2. [api/endpoints/family-ready-products.php](api/endpoints/family-ready-products.php)
3. [api/endpoints/family-ready-filters.php](api/endpoints/family-ready-filters.php)
4. [api/lib/code-explorer.php](api/lib/code-explorer.php)

## What Changed In Central API

Central API now supports:

- `GET /api/?endpoint=family-ready-filters&family=01`
- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

And `family-ready-products` now accepts real filter params.

Current supported filter keys:

- `product_type`
- `size`
- `color`
- `cri`
- `series`
- `lens`
- `finish`
- `cap`

Not supported in v1:

- `option`

Reason:
- Central API ready-family truth is still grounded at base-combo level:
  - `identity + lens + finish + cap`
- so `option` is intentionally excluded for now

## New Stability Update

Central API `family-ready-filters` had a bug where large-family requests could leak:

- PHP fatal HTML
- plain text
- invalid JSON

This is now fixed in Central API.

EPREL must now assume this contract:

- success = valid JSON
- failure = valid JSON

Failure shape:

```json
{
  "error": {
    "code": "SOME_ERROR_CODE",
    "message": "Human-readable message"
  }
}
```

Important:

- EPREL must not add a workaround for broken raw output
- EPREL should parse Central API filter responses as JSON always
- if API returns `error`, handle it as normal backend/API failure

## Filter Contract

### Request format

Use raw codes/values.

Multi-select format:
- comma-separated

Examples:

- `size=0180,0544`
- `color=25`
- `lens=1`
- `cap=01,02`

Match logic:

- `AND` between different filter keys
- `OR` inside one filter key

Example:

`GET /api/?endpoint=family-ready-products&family=01&size=0180,0544&color=25&lens=1&page=1&page_size=100`

Meaning:

- (`size = 0180` OR `size = 0544`)
- AND `color = 25`
- AND `lens = 1`

## Filter Endpoint Contract

Request:

- `GET /api/?endpoint=family-ready-filters&family=01`
- `GET /api/?endpoint=family-ready-filters&family=01&color=25&lens=1`

Auth:

- `X-API-Key: <key>`

Response shape:

```json
{
  "family": {
    "code": "01",
    "name": "T8 AC"
  },
  "applied_filters": {
    "color": ["25"],
    "lens": ["1"]
  },
  "summary": {
    "total_ready_products": 520
  },
  "available_filters": {
    "size": [
      { "value": "0180", "label": "180", "count": 104 },
      { "value": "0544", "label": "544", "count": 104 }
    ],
    "color": [
      { "value": "25", "label": "503", "count": 520 }
    ],
    "cap": [
      { "value": "01", "label": "Fixo", "count": 156 }
    ]
  }
}
```

Failure example:

```json
{
  "error": {
    "code": "UNKNOWN_FAMILY",
    "message": "Unknown family."
  }
}
```

Count rule:

- counts are based on real ready-family rows
- when counting one filter, Central API applies the other current filters first

That means:
- EPREL should trust these counts
- EPREL should not recompute them locally

## Ready Products Contract

Request:

- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`
- `GET /api/?endpoint=family-ready-products&family=01&size=0180,0544&color=25&lens=1&page=1&page_size=100`

Auth:

- `X-API-Key: <key>`

Response shape:

```json
{
  "family": {
    "code": "01",
    "name": "T8 AC"
  },
  "applied_filters": {
    "size": ["0180", "0544"],
    "color": ["25"],
    "lens": ["1"]
  },
  "summary": {
    "total_ready_products": 208
  },
  "pagination": {
    "page": 1,
    "page_size": 100,
    "total_pages": 3,
    "total_rows": 208
  },
  "rows": [
    {
      "reference": "01018025111010100",
      "identity": "0101802511",
      "description": "LLED T8 26 × 228mm CW503",
      "product_type": "tubular",
      "product_id": "T8/PC/22/3s",
      "led_id": "CW503",
      "configurator_valid": true,
      "datasheet_ready": true,
      "pdf_file_name": "01018025111010100.pdf",
      "pdf_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-datasheet&reference=01018025111010100",
      "spectral_file_name": "01018025111010100.png",
      "spectral_url": "https://apinexled-production.up.railway.app/api/?endpoint=file-spectral&reference=01018025111010100"
    }
  ]
}
```

## What EPREL Must Build

### 1. Backend proxy routes

Recommended EPREL backend routes:

- `GET /api/database/families`
- `GET /api/database/family-filters?family=01`
- `GET /api/database/family-preview?family=01&page=1&page_size=100`
- `POST /api/database/family-models`

Suggested mapping:

- `families`
  - proxy Central API `families`
- `family-filters`
  - proxy Central API `family-ready-filters`
  - pass selected filter params through unchanged
- `family-preview`
  - proxy Central API `family-ready-products`
  - pass selected filter params through unchanged
- `family-models`
  - page through `family-ready-products`
  - pass same selected filter params through unchanged
  - download:
    - `pdf_url`
    - `spectral_url`

### 2. UI flow

EPREL frontend should:

1. load family list
2. let user pick one family
3. load real family filters from Central API
4. render filter UI from API response
5. when user changes filters:
   - request `family-ready-filters` again
   - request `family-ready-products` again
6. preview filtered ready rows
7. start full build using the same selected filters

### 3. Build rule

EPREL must use the exact same selected filters for:

- preview
- full build/import

Do not let preview and build drift.

## What EPREL Must Not Do

1. do not invent local filter logic
2. do not guess counts
3. do not brute-force family combinations locally
4. do not expose Central API key in browser
5. do not add `option` filter locally
6. do not treat dropdown options as real products

## Safe Test Path

Start with family:

- `01`

Then:

- `05`

Safe example filter test:

- family `01`
- `color=25`
- `lens=1`

Then narrower:

- family `01`
- `size=0180,0544`
- `color=25`
- `lens=1`

## Acceptance

Done means:

1. EPREL can load real filter choices from Central API
2. EPREL can preview filtered ready rows from Central API
3. EPREL full build uses the same selected filters
4. no EPREL-side fake filtering exists
5. no `option` filter is shown in EPREL
6. browser never sees Central API secret

## If Something Looks Wrong

Check in this order:

1. Central API `family-ready-filters` response
2. Central API `family-ready-products` response
3. applied filter values returned by Central API
4. EPREL backend proxy mapping
5. EPREL UI state reuse between preview and build

If counts look strange:

- do not fix it in EPREL with local filtering
- inspect Central API response first

If filter load fails:

1. confirm EPREL backend received JSON
2. inspect `error.code`
3. surface a clean UI error state
4. do not try to recover by inventing local filters
