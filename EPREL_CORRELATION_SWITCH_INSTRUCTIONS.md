# EPREL Correlation Switch Instructions

Purpose:
- replace EPREL local draft-save as final truth
- move confirmed TecIt `<->` EPREL registration persistence to Central API
- keep EPREL as workflow/UI only

Project split:
- Central API = truth owner
- EPREL app = extract, match, review, save confirmed rows through API

## What changes now

Old EPREL behavior:
- reviewed matches were saved only to a local draft payload/file
- local draft acted like temporary truth

New required behavior:
- reviewed matches must be saved to Central API
- Central API stores them in dedicated DB:
  - `nexled_eprel`
  - table:
    - `eprel_code_mappings`

Important:
- EPREL can still keep a local temporary draft for UI/session convenience
- but local draft is no longer the source of truth

## Central API endpoints to use

Auth:
- send `X-API-Key`

Base pattern:
- `GET /api/?endpoint=...`
- `POST /api/?endpoint=...`

### 1. Save confirmed mappings

`POST /api/?endpoint=eprel-code-mappings-save`

Request:

```json
{
  "mappings": [
    {
      "tecit_code": "01018025111010100",
      "eprel_registration_number": "1234567",
      "source_type": "file",
      "source_name": "catalog.xml"
    }
  ]
}
```

Success response:

```json
{
  "saved": 1,
  "updated": 0,
  "skipped": 0,
  "total_received": 1
}
```

Meaning:
- `saved`
  - new row inserted
- `updated`
  - existing TecIt code changed to new confirmed registration
- `skipped`
  - identical mapping already existed

### 2. Exact read

By TecIt:

`GET /api/?endpoint=eprel-code-mappings&tecit_code=01018025111010100`

By EPREL registration:

`GET /api/?endpoint=eprel-code-mappings&eprel_registration_number=1234567`

Success response:

```json
{
  "count": 1,
  "rows": [
    {
      "tecit_code": "01018025111010100",
      "eprel_registration_number": "1234567",
      "source_type": "file",
      "source_name": "catalog.xml",
      "created_at": "2026-04-22 17:08:57",
      "updated_at": "2026-04-22 17:08:57"
    }
  ]
}
```

### 3. Batch read

`POST /api/?endpoint=eprel-code-mappings-batch`

Request:

```json
{
  "tecit_codes": [
    "01018025111010100",
    "05025725111010100"
  ]
}
```

Success response:

```json
{
  "requested_count": 2,
  "found_count": 1,
  "missing_tecit_codes": [
    "05025725111010100"
  ],
  "rows": [
    {
      "tecit_code": "01018025111010100",
      "eprel_registration_number": "1234567",
      "source_type": "file",
      "source_name": "catalog.xml",
      "created_at": "2026-04-22 17:08:57",
      "updated_at": "2026-04-22 17:08:57"
    }
  ]
}
```

## Exact EPREL-side switch

Best switch path:

### Step 1. Keep EPREL review flow

Do not remove:
- extract
- database code load
- match
- user review/confirmation UI

Keep these as they are.

### Step 2. Change only final save behavior

Current EPREL route:
- `POST /api/correlation/save`

Change its behavior from:
- save local draft JSON only

To:
- build `mappings` payload from user-confirmed rows
- call Central API `eprel-code-mappings-save`
- return Central API summary back to EPREL UI

Best practical implementation:
- EPREL backend route stays same for frontend simplicity
- but inside it becomes a server-side proxy/adapter to Central API

So frontend can still call:
- `POST /api/correlation/save`

But backend should now:
1. validate reviewed rows locally enough to shape payload
2. send confirmed mappings to Central API
3. return Central API JSON result

### Step 3. Send only confirmed rows

Do not send:
- unmatched rows
- uncertain rows
- fuzzy candidates
- empty registration numbers

Only send reviewed/accepted mappings.

### Step 4. Use stable source metadata

Recommended mapping metadata:
- `source_type`
  - `file`
  - `manual`
  - `xml`
  - `pdf_text`
- `source_name`
  - original uploaded filename
  - or clear human label if manual

This metadata is optional, but good for audit trail.

### Step 5. Stop treating local draft as truth

Allowed:
- local draft/session state for UI convenience
- temporary autosave while user reviews

Not allowed:
- assuming local draft = final persisted truth
- using local draft as shared cross-project mapping source

### Step 6. Add re-check/read flow where useful

Use Central API read endpoints to:
- preload already-known mappings for TecIt codes
- avoid duplicate review
- rehydrate previous saved truth

Best place:
- after database codes are loaded
- before or during match table assembly

Recommended endpoint for that:
- `POST /api/?endpoint=eprel-code-mappings-batch`

## Validation rules EPREL must respect

Central API already validates, but EPREL should shape data correctly before send.

TecIt code:
- numeric only
- exactly `17` digits

EPREL registration number:
- digits only
- non-empty

Do not:
- trim into other formats
- insert spaces
- send display labels instead of raw numbers

## Error contract

Central API returns JSON only.

Error shape:

```json
{
  "error": {
    "code": "SOME_ERROR_CODE",
    "message": "Human-readable message"
  }
}
```

Known examples:
- `INVALID_JSON_BODY`
- `INVALID_PAYLOAD`
- `EMPTY_MAPPINGS`
- `INVALID_TECIT_CODE`
- `INVALID_EPREL_REGISTRATION_NUMBER`
- `DATABASE_ERROR`

EPREL should:
- show friendly UI message
- keep raw API error in logs/debug panel
- not try to guess recovery data

## Recommended EPREL backend adapter shape

Suggested EPREL server-side route behavior:

### `POST /api/correlation/save`

Input from EPREL UI:
- reviewed rows
- source metadata

Backend should:
1. filter to confirmed mappings only
2. map into Central API request shape:

```json
{
  "mappings": [
    {
      "tecit_code": "01018025111010100",
      "eprel_registration_number": "1234567",
      "source_type": "xml",
      "source_name": "catalog.xml"
    }
  ]
}
```

3. call Central API with `X-API-Key`
4. return Central API response to UI

Success return to UI can stay close to:

```json
{
  "ok": true,
  "saved": 12,
  "updated": 3,
  "skipped": 0,
  "total_received": 15
}
```

Or UI can consume Central API response directly if simpler.

## Best follow-up EPREL improvements

After save-switch is done:

1. preload known mappings with batch endpoint
2. visually mark:
- already saved
- changed
- new
3. allow exact re-check by TecIt or EPREL number
4. keep local session cache only as convenience layer

## Hard rules

Do:
- keep Central API as truth
- keep EPREL as workflow
- send only confirmed rows
- preserve raw numeric identifiers

Do not:
- save final truth only in EPREL local file/db
- invent missing registration numbers
- fuzzy-save partial matches
- let frontend call Central API directly if API key must stay server-side

## Acceptance criteria

Done means:
- EPREL review UI still works
- clicking save persists confirmed mappings in Central API
- repeated save of same mapping returns `skipped`
- changed mapping returns `updated`
- EPREL can batch-read saved mappings later
- local draft is no longer treated as final truth

## Read next

API-side reference docs:
- [api/EPREL_CODE_MAPPINGS_API.md](C:/xampp/htdocs/api_nexled/api/EPREL_CODE_MAPPINGS_API.md)
- [EPREL_CORRELATION_NEXT_AI_PROMPT.md](C:/xampp/htdocs/api_nexled/EPREL_CORRELATION_NEXT_AI_PROMPT.md)
