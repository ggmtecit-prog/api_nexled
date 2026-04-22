# EPREL Code Mappings API

Purpose:
- persist confirmed TecIt <-> EPREL registration mappings in Central API
- keep mapping truth out of the EPREL client app
- expose stable JSON endpoints for save, exact read, and batch read

Storage owner:
- Central API project

Current storage model:
- dedicated database:
  - `nexled_eprel`
- table:
  - `eprel_code_mappings`

Schema file:
- [api/sql/eprel_code_mappings_schema.sql](api/sql/eprel_code_mappings_schema.sql)

Runtime connection:
- dedicated bootstrap connection:
  - `connectDBEprelMappings()`
- env keys supported:
  - `EPREL_MAP_DB_NAME`
  - `EPREL_MAP_DB_HOST`
  - `EPREL_MAP_DB_PORT`
  - `EPREL_MAP_DB_USER`
  - `EPREL_MAP_DB_PASS`
- local fallback database name:
  - `nexled_eprel`

## Data shape

Stored mapping row:

```json
{
  "tecit_code": "01018025111010100",
  "eprel_registration_number": "1234567",
  "source_type": "file",
  "source_name": "catalog.xml",
  "created_at": "2026-04-22 17:08:57",
  "updated_at": "2026-04-22 17:08:57"
}
```

Rules:
- `tecit_code` is unique
- one stable EPREL registration number per TecIt code
- updates are allowed when mapping is corrected later
- EPREL registration number is indexed, not unique

## Validation rules

TecIt code:
- numeric only
- exactly `17` digits

EPREL registration number:
- digits only
- non-empty

Source metadata:
- optional
- `source_type` max length `32`
- `source_name` max length `255`

## Endpoints

### Save mappings

`POST /api/?endpoint=eprel-code-mappings-save`

Auth:
- `X-API-Key`

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

Success:

```json
{
  "saved": 1,
  "updated": 0,
  "skipped": 0,
  "total_received": 1
}
```

Behavior:
- inserts new mapping rows
- updates existing rows if confirmed values changed
- skips existing identical rows

### Exact read

`GET /api/?endpoint=eprel-code-mappings&tecit_code=01018025111010100`

or

`GET /api/?endpoint=eprel-code-mappings&eprel_registration_number=1234567`

Auth:
- `X-API-Key`

Success:

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

### Batch read

`POST /api/?endpoint=eprel-code-mappings-batch`

Auth:
- `X-API-Key`

Request:

```json
{
  "tecit_codes": [
    "01018025111010100",
    "05025725111010100"
  ]
}
```

Success:

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

## Error contract

These endpoints return JSON only.

Error shape:

```json
{
  "error": {
    "code": "SOME_ERROR_CODE",
    "message": "Human-readable message"
  }
}
```

Some current error codes:
- `METHOD_NOT_ALLOWED`
- `INVALID_JSON_BODY`
- `INVALID_PAYLOAD`
- `EMPTY_MAPPINGS`
- `EMPTY_TECIT_CODES`
- `INVALID_TECIT_CODE`
- `INVALID_EPREL_REGISTRATION_NUMBER`
- `MISSING_LOOKUP_PARAMETER`
- `DATABASE_ERROR`

## Scope boundary

These endpoints store only:
- explicit confirmed mappings

They do not:
- guess matches
- run fuzzy logic
- search EPREL
- extract XML/PDF content
- own EPREL UI workflow

That remains EPREL-app work.
