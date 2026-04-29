# Code Validity Explorer

## Goal

Create one separate internal page that shows which full 17-character Tecit codes current system allows, without changing existing endpoint behavior.

## Locked Rules

- New UI lives on its own page: `configurator/code-explorer.html`
- Existing API endpoints stay behaviorally unchanged
- Same Railway API service stays source of truth
- One new read-only endpoint allowed: `GET /api/?endpoint=code-explorer`

## Validity Levels

### Configurator-valid

Code is allowed when:

- family exists
- first 10 characters resolve in `Luminos`
- suffix segments come from family option lists in `tecit_referencias`

### Datasheet-ready

Code is configurator-valid and also passes non-rendering datasheet checks for default export context.

Default context used by explorer:

- `lang = pt`
- `purpose = 0`
- `connector_cable = 0`
- `cable_type = branco`
- `end_cap = 0`
- `extra_length = 0`
- `cable_length = 0`
- `gasket = 5`
- optional fixing / driver / connection cable off

## Data Sources

- `Luminos` -> first 10-char identity truth + base description + product ID
- `tecit_referencias` -> family option lists for suffix generation
- current local PDF assets / support JSON -> datasheet readiness checks

## Endpoint

`GET /api/?endpoint=code-explorer&family=11&page=1&page_size=100&search=&status=all`

### Filters

- `family` required
- `page` default `1`
- `page_size` default `100`, max `250`
- `search` matches:
  - full reference
  - identity
  - description
  - product ID
  - failure reason
- `status`:
  - `all`
  - `configurator_valid`
  - `datasheet_ready`
  - `datasheet_blocked`

### Row Shape

```json
{
  "reference": "11037581110010100",
  "identity": "1103758111",
  "description": "Barra LED 24V",
  "product_type": "barra",
  "product_id": "BarraPink/24v/40/3s",
  "segments": {
    "family": "11",
    "size": "0375",
    "color": "81",
    "cri": "1",
    "series": "1",
    "lens": "0",
    "finish": "01",
    "cap": "01",
    "option": "00"
  },
  "configurator_valid": true,
  "datasheet_ready": false,
  "failure_reason": "missing_finish_image"
}
```

## Family 48 Rule

Dynamic family `48` must resolve `product_id` with same cap-aware split used by current dynamic logic.

## Page Sections

1. Header
2. Tecit code logic explainer
3. Datasheet source / readiness context note
4. Filters
5. Summary cards
6. Results table
7. Detail view
8. Pagination

## Current Readiness Reasons

- `invalid_luminos_combination`
- `missing_header_data`
- `missing_color_graph`
- `missing_lens_diagram`
- `missing_technical_drawing`
- `missing_finish_image`
- `missing_fixing_data`
- `missing_power_supply_data`
- `missing_connection_cable_data`
- `unsupported_datasheet_runtime`

## Known Limits

- v1 builds rows live per family, not from precomputed snapshot
- v1 does not render PDFs
- v1 readiness reflects default export context, not every optional accessory combination
- families can be in 3 different states:
  - fully supported runtime
  - recognized but runtime not mapped yet
  - not mapped at all
