# Next Steps - Datasheet Parity Roadmap

## Goal

Make new Railway API datasheet generator produce same functional result as old `appdatasheets` project:

- same validation logic
- same field sourcing
- same page order
- same section order
- same required/missing-data behavior
- same visible output structure as official NexLed PDFs

Internal code can be cleaner. Functional drift is not acceptable.

Related docs:

- [OFFICIAL_DATASHEET_LAYOUT_SPEC.md](./OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [PLAN.md](./PLAN.md)

---

## Current State

What already works:

- API is online on Railway
- configurator talks to online API, not localhost
- Tecit code decode exists
- Luminos validation was restored as hard validation
- PDF generation works for many cases
- SVG assets are preferred again where possible, so original size/quality is preserved
- family logic docs now exist for:
  - Barra
  - Shelf
  - Downlights
  - Tubulares
- family `60` is recognized by current runtime
- families `49`, `01`, `05` are recognized by API/explorer, but datasheet runtime is still intentionally unsupported

What still does not match official datasheets:

- some info comes from wrong source or is formatted wrong
- page/program structure still differs from official PDFs
- old missing-data validator sweep is not fully restored
- some family-specific UI logic from old configurator is missing
- some dependent option logic from old configurator is missing
- current generator still behaves too generically in some places

---

## Main Problem

Current generator is still mostly **section-based**.

Official old datasheets are effectively **page-template based**.

That means parity will not come from small cosmetic fixes only. It needs:

1. source parity
2. validation parity
3. page-template parity

---

## Priority Order

### Phase 1 - Lock Gold Samples

Choose one official PDF per family as gold reference:

- Barra
- Downlight
- Dynamic

These files become source of truth for:

- page count
- field order
- section order
- footer content
- visual placement

### Phase 2 - Build Field Source Matrix

For each visible field in each gold PDF, document:

- page number
- section name
- visible label
- visible value
- exact source
- fallback behavior
- whether field is required

Possible sources:

- `Luminos`
- `tecit_lampadas`
- `tecit_referencias`
- `info_nexled_2024`
- `api/json`
- `appdatasheets/img`
- live configurator form state

Output doc recommended:

- `api/BARRA_PDF_DATA_MATRIX.md`
- later `api/DOWNLIGHT_PDF_DATA_MATRIX.md`
- later `api/DYNAMIC_PDF_DATA_MATRIX.md`

### Phase 3 - Restore Old Validation Logic

Rebuild old `Em falta` validation behavior from `appdatasheets/funcoes/funcoesDatasheet.php`.

Must validate at least:

- header/product info
- color graph
- lens diagram
- technical drawing
- finish image
- fixing data
- power supply data
- connection cable data
- IP/icon support

Goal:

- critical missing data must block export
- non-critical visuals may fallback only when explicitly allowed

Important:

- current generator skips too much data silently
- old project surfaced missing-data state much more aggressively

### Phase 4 - Rebuild Layout by Family Template

Start with **Barra only**.

Do not rebuild all families at once.

Implementation order:

1. Barra page 1
2. Barra page 2
3. Barra page 3
4. Barra accessory pages

Then repeat same strategy for:

5. Downlight
6. Dynamic

Recommended architecture:

- keep shared fetchers
- create page-template builders per family
- avoid one giant generic layout builder

Example future structure:

- `api/lib/pdf-pages/barra.php`
- `api/lib/pdf-pages/downlight.php`
- `api/lib/pdf-pages/dynamic.php`

### Phase 5 - Restore Old Configurator Logic

Bring back old frontend behavior from `appdatasheets/script.js`.

Still missing or partially missing:

- family-specific field exceptions
- option-driven cable autofill logic
- stronger export gating when validation state is bad
- parity in hidden/shown fields by family

Examples from old app:

- bars had special handling for:
  - extra length
  - end cap
  - gasket
  - connection cable
  - fixing
- downlights had different power supply handling
- some option descriptions auto-filled dependent cable fields

### Phase 6 - Regression Compare Every Iteration

After each family page-template change:

- generate new PDF
- compare against official sample
- verify:
  - page count
  - section order
  - field presence
  - field values
  - images
  - footer/version
  - page breaks

Do not move to next family until current one is acceptable.

---

## Recommended Immediate Work

### Step 1

Finish one documented-but-unimplemented family runtime.

Best current pick:

- family `49` Shelf

Why:

- docs now exist
- family is recognized in API
- datasheet runtime still not mapped, so next gap is clear

### Step 2

Create `BARRA_PDF_DATA_MATRIX.md`.

This should still list every field visible in official Barra PDF and map each one to exact source.

### Step 3

Trace old Barra builder behavior in:

- `appdatasheets/funcoes/estruturaDatasheet.php`
- `appdatasheets/funcoes/funcoesDatasheet.php`

Identify:

- old page boundaries
- old render order
- old conditional sections
- old required fields

### Step 4

Implement first official Barra page template in new API.

Do not touch Downlight or Dynamic until Barra page 1 is stable.

---

## Acceptance Criteria

For one family to be considered done:

- official and new PDF have same page count
- same core fields appear
- same values appear
- missing-data rules match old behavior
- visual order is equivalent
- images render in correct sections
- invalid Luminos combinations are blocked
- configurator does not allow export from bad state

---

## Things To Avoid

Do not:

- keep adding random one-off PDF patches without reference sample
- try to solve all families in one generic template
- hide missing required data silently
- use DAM as PDF asset source yet unless migration is explicit
- change business meaning while improving code structure

---

## Best Next Action

Start now with:

1. `BARRA_PDF_DATA_MATRIX.md`
2. page 1 Barra official template rebuild

That is highest-value path. It reduces guessing and gives solid base for all later families.
