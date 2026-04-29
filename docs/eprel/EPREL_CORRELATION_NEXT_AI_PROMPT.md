# EPREL TecIt <-> EPREL Correlation: API Continuation Prompt

I need you to continue the TecIt <-> EPREL correlation feature on the **API project** side.

Project:

`C:\xampp\htdocs\api_nexled`

Context:

- EPREL app side is already implemented in:
  - `C:\xampp\htdocs\epreltools_newd`
- This EPREL-side feature now lives in:
  - [search.html](</C:/xampp/htdocs/epreltools_newd/search.html>)
  - [js/search.js](</C:/xampp/htdocs/epreltools_newd/js/search.js>)
- EPREL side now supports:
  - upload XML or text-based PDF
  - load TecIt codes from Nexled database by family
  - extract full TecIt codes
  - search official EPREL data by `modelIdentifier`
  - build a reviewed correlation list
  - save matched rows into a **local draft payload**

Important:

- EPREL side is **not** the final source of truth
- final mapping storage must live in the **API project**
- other projects must be able to read/use this mapping later

## Read First

Before coding, read these project-context files:

1. [PROJECT_MEMORY.md](PROJECT_MEMORY.md)
2. [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md)
3. [api/index.php](api/index.php)
4. [api/bootstrap.php](api/bootstrap.php)
5. [api/auth-check.php](api/auth-check.php)
6. [api/lib/validate.php](api/lib/validate.php)
7. recent EPREL-facing endpoint examples:
   - [api/endpoints/family-ready-products.php](api/endpoints/family-ready-products.php)
   - [api/endpoints/family-ready-filters.php](api/endpoints/family-ready-filters.php)

This feature must follow **this repo's** real API patterns, not generic assumptions.

## Current API Project Reality

Important repo context:

- Central API routes through:
  - `api/index.php`
- auth is:
  - `X-API-Key`
- bootstrap/runtime DB wiring is:
  - `api/bootstrap.php`
- endpoint style is:
  - `GET /api/?endpoint=...`
  - `POST /api/?endpoint=...`
- recent EPREL-facing work already established a hard rule:
  - API must return **clean JSON only**
  - no leaked PHP warnings
  - no raw HTML
  - no plain-text DB errors
- the repo currently has no general migration framework visible beyond:
  - [api/sql/dam_schema.sql](api/sql/dam_schema.sql)
- this means if a new storage table is needed, setup/migration notes must be explicit

Also important:

- do not make this feature depend on `appdatasheets/`
- this is Central API persistence, not legacy datasheet parity work
- keep EPREL/client workflow separate from Central API truth ownership

## What EPREL Side Already Does

Local EPREL routes already implemented:

- `POST /api/correlation/extract`
- `POST /api/correlation/database-codes`
- `POST /api/correlation/match`
- `POST /api/correlation/save`

Important current save behavior:

- `POST /api/correlation/save` currently saves a **local draft JSON payload only**
- this is temporary
- it exists so the EPREL workflow is already usable before API persistence is finished

## Locked Decisions

These are already decided and must be respected:

- TecIt canonical key = **full TecIt code**
- EPREL saved key = **EPREL registration number**
- final mapping storage owner = **API project**
- EPREL matching source = **official EPREL data**
- EPREL search logic currently searches by:
  - `modelIdentifier`
- PDF scope in V1 = **text extraction only**
- no OCR
- API must stay JSON-stable on success and failure
- EPREL app will later replace local draft save with a Central API persistence call

## What I Need From The API Project

I need the API project to become the real persistence layer for this mapping.

## Required API Goal

Store and expose mappings like:

```json
{
  "tecit_code": "01018032111010100",
  "eprel_registration_number": "1234567"
}
```

Optional metadata is good too:

```json
{
  "tecit_code": "01018032111010100",
  "eprel_registration_number": "1234567",
  "source_type": "file",
  "source_name": "catalog.xml",
  "created_at": "2026-04-22T17:30:00+01:00"
}
```

## What Needs To Be Implemented

### 1. Save endpoint

Add an API endpoint that receives confirmed mappings from EPREL side and stores them.

Recommended:

- `POST /api/?endpoint=eprel-code-mappings-save`

Input:

```json
{
  "mappings": [
    {
      "tecit_code": "01018032111010100",
      "eprel_registration_number": "1234567",
      "source_type": "file",
      "source_name": "catalog.xml"
    }
  ]
}
```

Behavior:

- validate payload
- validate TecIt code format
- validate EPREL registration number format
- upsert mappings
- return summary

Example response:

```json
{
  "saved": 12,
  "updated": 3,
  "skipped": 0
}
```

### 2. Read endpoint

Add an API endpoint to read stored mappings later.

Recommended:

- `GET /api/?endpoint=eprel-code-mappings`

Support at least:

- exact TecIt code lookup
- exact EPREL registration number lookup

Example query shapes:

- `GET /api/?endpoint=eprel-code-mappings&tecit_code=01018032111010100`
- `GET /api/?endpoint=eprel-code-mappings&eprel_registration_number=1234567`

### 3. Batch lookup endpoint

This is strongly recommended.

Add a batch read route for many TecIt codes at once.

Recommended:

- `POST /api/?endpoint=eprel-code-mappings-batch`

Input:

```json
{
  "tecit_codes": [
    "01018032111010100",
    "01018025111010100"
  ]
}
```

### 4. Storage design

Use the API/database project as source of truth.

Requirements:

- unique by `tecit_code`
- one stable mapped EPREL registration number per TecIt code
- allow update if mapping is corrected later
- timestamps recommended

Project-aware note:

- if you choose DB storage, document exactly which database/table is used
- if you choose file storage first, document exact file path and locking/update rules
- do not invent hidden persistence
- make setup explicit so another machine/deploy can reproduce it

## Validation Rules

### TecIt code

Current EPREL-side implementation assumes:

- full TecIt code
- numeric
- 17 digits

So API should validate the same unless the real source-of-truth format is broader.

If broader, tell us explicitly.

### EPREL registration number

Validate as:

- digits only
- non-empty

## Important Rules

- do not store guessed matches automatically
- do not invent fuzzy logic on API side
- only store mappings explicitly confirmed by EPREL side
- do not make EPREL app the final owner of this data
- keep API response JSON clean and stable
- follow the same API contract discipline as recent EPREL filter endpoints

## Expected Error Shape

Even on failure, API should return JSON like:

```json
{
  "error": {
    "code": "SOME_ERROR_CODE",
    "message": "Human-readable message"
  }
}
```

Do not return:

- PHP warnings
- HTML error pages
- plain text DB errors
- partial output before JSON

## What I Want Back

Please return:

1. code changes
2. exact endpoint contract
3. exact storage model / table / file / DB structure used
4. sample success responses
5. sample validation error responses
6. any migration/setup notes

## Important Handoff Context

EPREL side is already done enough to switch later with a small change:

- current local EPREL save route just writes a draft file
- later we will replace that internal draft save with a real call to your new API endpoint

So your work should focus on:

- persistence
- read access
- stable JSON contract
- implementation that matches this repo's actual routing/auth/bootstrap style

Not on:

- EPREL search UI
- XML/PDF extraction UI
- browser-side matching UI
