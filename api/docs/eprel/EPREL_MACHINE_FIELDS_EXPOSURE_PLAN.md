# EPREL Machine Fields Exposure Plan

Purpose:
- expose real upstream machine-readable fields needed for EPREL XML
- keep Central API as the source of truth
- avoid EPREL scraping PDF text or localized characteristics labels

Audience:
- AI agents
- Central API engineers
- EPREL integration engineers

Last Updated:
- 2026-04-21

## Scope

This plan is only for fields already confirmed by code/DB investigation.

Confirmed upstream and should be exposed:
- `energy_class`
- `luminous_flux`
- `chrom_x`
- `chrom_y`
- `r9`
- `cri_min`
- `cri_max`

Confirmed missing upstream and must stay out:
- `on_market_date`
- `survival`
- `lumen_maint`

Field note:
- `tech_lum_flux` does not need a separate upstream source if EPREL can derive it from `luminous_flux`

## Current Truth

What already exists in the API project:

- `energy_class`
  - computed by luminotechnical logic
- `luminous_flux`
  - computed by luminotechnical logic as `flux`
- `chrom_x`
  - exists upstream in `tecit_lampadas.Led.CIEx`
- `chrom_y`
  - exists upstream in `tecit_lampadas.Led.CIEy`
- `r9`
  - exists upstream in `tecit_lampadas.Led.criR9`
- `cri_min`
  - exists upstream in `tecit_lampadas.Led.crimin`
- `cri_max`
  - exists upstream in `tecit_lampadas.Led.crimax`

What current EPREL-facing contract already exposes:

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

What current EPREL-facing contract does **not** expose cleanly:

- `energy_class`
- `luminous_flux`
- `chrom_x`
- `chrom_y`
- `r9`
- `cri_min`
- `cri_max`

## Best Contract Shape

Best result is:
- expose the machine fields in `family-ready-products`
- mirror the same machine fields in `pdf_specs`

Why:
- `family-ready-products` is the actual EPREL bulk-build source
- `pdf_specs` should stay consistent for exact-reference debug/inspection
- EPREL should not need per-reference text scraping

Recommended shape:

```json
{
  "reference": "01018025111010100",
  "identity": "0101802511",
  "description": "T8 ...",
  "product_type": "tubular",
  "product_id": "T8/PC/22/3s",
  "led_id": "CW503",
  "configurator_valid": true,
  "datasheet_ready": true,
  "pdf_file_name": "01018025111010100.pdf",
  "pdf_url": "https://.../api/?endpoint=file-datasheet&reference=01018025111010100",
  "spectral_file_name": "01018025111010100.png",
  "spectral_url": "https://.../api/?endpoint=file-spectral&reference=01018025111010100",
  "eprel_fields": {
    "energy_class": "C",
    "luminous_flux": 420,
    "chrom_x": 0.345,
    "chrom_y": 0.358,
    "r9": 12,
    "cri_min": 80,
    "cri_max": 84
  }
}
```

## Field Source Map

### 1. energy_class

Source:
- computed by `getLuminotechnicalData()`

Current project location:
- `api/lib/luminotechnical.php`

Machine output rule:
- string
- example: `"C"`

### 2. luminous_flux

Source:
- computed by `getLuminotechnicalData()`
- current internal field name is `flux`

Current project location:
- `api/lib/luminotechnical.php`

Machine output rule:
- integer
- expose as `luminous_flux`
- do not expose only as `flux` in EPREL contract

### 3. chrom_x

Source:
- `tecit_lampadas.Led.CIEx`
- keyed by `led_id`

Machine output rule:
- float

### 4. chrom_y

Source:
- `tecit_lampadas.Led.CIEy`
- keyed by `led_id`

Machine output rule:
- float

### 5. r9

Source:
- `tecit_lampadas.Led.criR9`
- keyed by `led_id`

Machine output rule:
- integer when present

### 6. cri_min

Source:
- `tecit_lampadas.Led.crimin`
- keyed by `led_id`

Machine output rule:
- integer when present

### 7. cri_max

Source:
- `tecit_lampadas.Led.crimax`
- keyed by `led_id`

Machine output rule:
- integer when present

## Hard Rules

1. Do not invent missing upstream data.
2. Do not guess defaults for missing LED fields.
3. Keep fields machine-readable.
4. Do not bury these fields only in:
   - description text
   - PDF text
   - `characteristics[]`
   - localized labels
5. Preserve current EPREL contract fields.
6. Missing upstream fields stay out of this implementation:
   - `on_market_date`
   - `survival`
   - `lumen_maint`

## Best Implementation Direction

### Option C

Expose the same grouped object in both:
- `family-ready-products`
- `pdf_specs`

Why this is best:
- bulk EPREL flow uses `family-ready-products`
- exact debug flow uses `pdf_specs`
- one stable object avoids drift

## Implementation Phases

### Phase 1. Add a reusable builder

Add one Central API helper that returns:

```json
{
  "energy_class": "...",
  "luminous_flux": 0,
  "chrom_x": 0.0,
  "chrom_y": 0.0,
  "r9": 0,
  "cri_min": 0,
  "cri_max": 0
}
```

Builder inputs should be:
- `product_id`
- `reference`
- `led_id`

Best source chain:
1. luminotechnical logic for:
   - `energy_class`
   - `luminous_flux`
2. `Led` table lookup by `led_id` for:
   - `chrom_x`
   - `chrom_y`
   - `r9`
   - `cri_min`
   - `cri_max`

### Phase 2. Add to `family-ready-products`

Each ready row should gain:
- `eprel_fields`

Rows must keep all current fields unchanged.

### Phase 3. Add to `pdf_specs`

Exact-reference response should also gain:
- `eprel_fields`

### Phase 4. Keep cache truth safe

`family-ready-products` currently uses ready-base caching.

Best safe direction:
- compute `eprel_fields` at ready-base row level when possible
- inherit same object into expanded full references

Reason:
- these fields are identity/LED-driven, not option-driven in the current proven model
- avoids repeated heavy recalculation on every expanded option row

### Phase 5. Validate types

Target types:
- `energy_class` => string
- `luminous_flux` => integer
- `chrom_x` => float
- `chrom_y` => float
- `r9` => integer or null
- `cri_min` => integer or null
- `cri_max` => integer or null

No localized formatting.
No commas in decimals.
No embedded units.

## Acceptance Criteria

Done means:

1. `family-ready-products` rows include `eprel_fields`
2. `pdf_specs` includes the same `eprel_fields`
3. values come from real upstream logic/data only
4. `energy_class` and `luminous_flux` are not scraped from text
5. `chrom_x`, `chrom_y`, `r9`, `cri_min`, `cri_max` come from LED upstream truth
6. missing upstream fields are still absent:
   - `on_market_date`
   - `survival`
   - `lumen_maint`
7. current EPREL fields stay unchanged

## Safe First Test

Use already proven references first:

- `01018025111010100`
- `01054425121010100`
- `01054491111010100`
- `05025725111010100`
- `05025727111010100`
- `05025732111010100`

Verify:
- row still appears in `family-ready-products`
- `pdf_file_name` / `pdf_url` unchanged
- `spectral_file_name` / `spectral_url` unchanged
- `eprel_fields.energy_class` present
- `eprel_fields.luminous_flux` present
- LED-driven fields present when upstream LED row has them

## Non-Goals

This task does **not**:
- add `on_market_date`
- add `survival`
- add `lumen_maint`
- derive XML-only fake values
- change EPREL-side mapping logic yet
