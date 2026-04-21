# EPREL Family Filter Plan

Status:
- Planning + implementation guide
- Created on 2026-04-21

Audience:
- Central API engineers
- EPREL engineers
- AI agents continuing family import work

Purpose:
- add real filter support to `family-ready-products`
- let EPREL choose filters in its UI while Central API stays the source of truth
- avoid local EPREL-side fake filtering or theoretical family generation

## Short Decision

Build:
- `GET /api/?endpoint=family-ready-filters&family=01`
- filtered `GET /api/?endpoint=family-ready-products&family=01...`

Do not build:
- EPREL-side local filtering rules
- option filter in v1

Reason:
- current ready-family logic is grounded at base-combo level:
  - `identity + lens + finish + cap`
- `option` is expanded later
- so `option` is not yet honest enough as a ready-family filter

## Source Of Truth

When filter behavior and docs disagree, prefer:

1. old runtime behavior in `appdatasheets/`
2. live DB truth
3. current API implementation
4. this plan

## Supported V1 Filters

Only filters backed by real ready-family data:

- `product_type`
- `size`
- `color`
- `cri`
- `series`
- `lens`
- `finish`
- `cap`

Excluded in v1:

- `option`

Why excluded:
- current `family-ready-products` validates readiness using ready base combos first
- then expands those base combos into full references with option codes
- so option truth is not strict enough yet for a real family-ready filter

## Contract

### Ready rows endpoint

Endpoint:
- `GET /api/?endpoint=family-ready-products&family=01&page=1&page_size=100`

Now also accepts filter params:
- `product_type`
- `size`
- `color`
- `cri`
- `series`
- `lens`
- `finish`
- `cap`

Format:
- comma-separated values

Example:
- `GET /api/?endpoint=family-ready-products&family=01&size=0180,0544&color=25&lens=1&page=1&page_size=100`

### Filter options endpoint

Endpoint:
- `GET /api/?endpoint=family-ready-filters&family=01`

This endpoint should accept the same filter params as above.

Purpose:
- EPREL can load available filter choices for a family
- EPREL can re-request the filter endpoint after user selections change
- API remains the real filter authority

## Match Logic

Use:
- `AND` between different filter keys
- `OR` within one filter key

Example:
- `size=0180,0544&color=25&lens=1`

Meaning:
- (`size = 0180` OR `size = 0544`)
- AND `color = 25`
- AND `lens = 1`

## Value Format

Request values must be:
- raw codes

Examples:
- `size=0180`
- `color=25`
- `finish=01`
- `cap=02`

Response should include:
- `value`
- `label`
- `count`

Labels come from Central API option tables / runtime labels.

## Count Rule

Filter counts must be based on real ready-family rows.

Recommended facet behavior:
- when counting one filter, apply all the other current filters first
- then count rows for each value of the target filter

This makes EPREL UI narrower and more useful as the user refines the family.

## Implementation Plan

### Phase 1. Ready-family cache truth

Goal:
- cache enough segment data to support honest filtering

Needed cache/base-row fields:
- `identity`
- `description`
- `product_type`
- `product_id`
- `led_id`
- `size`
- `color`
- `cri`
- `series`
- `lens`
- `finish`
- `cap`

Action:
- bump family-ready cache version

### Phase 2. Shared filter parser

Goal:
- parse family-ready filters once, reuse in both endpoints

Rules:
- multi-select comma-separated values
- raw code matching
- normalize numeric codes with left padding where segment length requires it

### Phase 3. Filtered ready rows

Goal:
- let `family-ready-products` return only filtered ready rows

Rules:
- apply filters to ready base rows first
- then expand filtered base rows into full references with option codes

### Phase 4. Available filter endpoint

Goal:
- provide EPREL UI with real choices + counts

Response shape:

```json
{
  "family": {
    "code": "01",
    "name": "T8 AC"
  },
  "applied_filters": {
    "size": ["0180"],
    "lens": ["1"]
  },
  "available_filters": {
    "size": [
      { "value": "0180", "label": "180", "count": 3200 }
    ],
    "color": [
      { "value": "25", "label": "CW503", "count": 1200 }
    ]
  }
}
```

## EPREL Rule

EPREL should:
- load filter options from Central API
- let user choose filters in EPREL UI
- send the chosen filters back to Central API
- use the same filter params for:
  - preview
  - full build

EPREL must not:
- filter family rows locally on guessed logic
- create its own family combinations
- treat dropdown options as real products

## Safe V1 Scope

Start with:
- family `01`
- family `05`

Then:
- verify bigger families like `11`

## Acceptance

Done means:
- `family-ready-products` accepts real filter params
- `family-ready-filters` returns real values + labels + counts
- same filters work for preview and full build
- EPREL can narrow large families without local fake filtering
- option filter is intentionally absent until truth is stronger
