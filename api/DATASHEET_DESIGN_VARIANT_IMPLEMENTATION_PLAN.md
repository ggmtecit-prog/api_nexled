# Datasheet Design Variant Implementation Plan

Status: draft

Purpose: phased implementation plan for selectable datasheet design variants.

Related spec:
- [DATASHEET_DESIGN_VARIANT_FEATURE_SPEC.md](./DATASHEET_DESIGN_VARIANT_FEATURE_SPEC.md)
- [NEXT_STEPS_DATASHEET_PARITY.md](./NEXT_STEPS_DATASHEET_PARITY.md)
- [OFFICIAL_DATASHEET_LAYOUT_SPEC.md](./OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)

## 1. Implementation Principles

- Keep current datasheet output unchanged by default.
- Reuse same data snapshot and validation pipeline.
- Add design selection at render layer, not product-truth layer.
- Scope V1 to exact datasheet endpoints only.
- Avoid touching showcase flow in V1.
- Avoid touching custom datasheet flow in V1 unless later needed.

## 2. Deliverables

Backend deliverables:
- request validation for `design_variant`
- render-context support for `design_variant`
- classic renderer wrapper around current layout
- modern renderer
- variant dispatcher
- file-datasheet support for same selector

Frontend deliverables:
- design selector in official datasheet mode
- request payload support
- default current design behavior

QA deliverables:
- classic regression check
- modern smoke check
- family support note if modern rollout is partial

Documentation deliverables:
- feature spec
- implementation plan

## 3. Recommended Scope Split

## V1

Implement:
- `datasheet`
- `file-datasheet`
- configurator official mode selector

Do not implement yet:
- `custom-datasheet-preview`
- `custom-datasheet-pdf`
- `showcase-preview`
- `showcase-pdf`

Reason:
- smallest safe slice
- no need to widen custom validator yet
- no need to widen grouped-PDF system yet

## 4. Expected File Changes

Update:
- [index.php](./index.php) only if shared helper imports move
- [lib/validate.php](./lib/validate.php)
- [lib/pdf-engine.php](./lib/pdf-engine.php)
- [lib/pdf-layout.php](./lib/pdf-layout.php) or split layout files
- [endpoints/file-datasheet.php](./endpoints/file-datasheet.php)
- [../configurator/configurator.html](../configurator/configurator.html)
- [../configurator/script.js](../configurator/script.js)
- [../configurator/locales/en.js](../configurator/locales/en.js)
- [../configurator/locales/pt.js](../configurator/locales/pt.js)

Potential new backend files if split is cleaner:
- `api/lib/pdf-layout-classic.php`
- `api/lib/pdf-layout-modern.php`

Potential new CSS assets if needed:
- `appdatasheets/style/datasheet-classic.css`
- `appdatasheets/style/datasheet-modern.css`

## 5. Phase Plan

## Phase 0: Lock contract

Goal:
- freeze variant names and scope before coding

Tasks:
- confirm API values:
  - `classic`
  - `modern`
- confirm default:
  - omitted -> `classic`
- confirm V1 endpoints:
  - `datasheet`
  - `file-datasheet`
- confirm custom/showcase deferred

Verify:
- spec accepted
- no ambiguous naming remains

## Phase 1: Request validation

Goal:
- normalize and validate `design_variant`

Tasks:
- add helper in [lib/validate.php](./lib/validate.php):
  - `validateDesignVariant(string $value): string`
  - or equivalent nullable validator
- keep omitted field mapped to `classic`
- reject unsupported explicit values

Recommended accepted values:
- `classic`
- `modern`

Recommended errors:
- `unsupported_design_variant`

Verify:
- old requests still pass
- invalid variant fails clearly

## Phase 2: Render context threading

Goal:
- carry design choice through core runtime

Tasks:
- read `design_variant` in [buildDatasheetRenderContext()](./lib/pdf-engine.php)
- add normalized value to returned context
- keep current `footer_marker`, `company`, `lang`, `data` shape unchanged

Verify:
- render context contains stable `design_variant`
- no other runtime data changes

## Phase 3: Renderer split

Goal:
- isolate current layout as `classic`
- add clean hook for `modern`

Recommended implementation:
- rename current master layout builder to `buildPdfLayoutClassic`
- add new dispatcher:
  - `buildPdfLayoutForVariant(array $data, string $designVariant): string`
- have dispatcher call:
  - `buildPdfLayoutClassic(...)`
  - `buildPdfLayoutModern(...)`

Two valid structure options:

Option A:
- keep both functions in current [pdf-layout.php](./lib/pdf-layout.php)

Option B:
- split into dedicated files:
  - `pdf-layout-classic.php`
  - `pdf-layout-modern.php`
  - keep `pdf-layout.php` as dispatcher

Recommended choice:
- Option B if modern design differs a lot
- Option A if modern design reuses most section builders

Verify:
- classic output still renders
- modern path callable even if initially minimal scaffold

## Phase 4: Variant CSS support

Goal:
- allow design-specific styling without breaking current CSS

Tasks:
- extract CSS loading into helper in [pdf-engine.php](./lib/pdf-engine.php)
- load classic CSS for `classic`
- load modern CSS for `modern`

Recommended helper:
- `getDatasheetCssForVariant(string $designVariant): string`

Rule:
- classic CSS must remain byte-for-byte current source if possible

Verify:
- classic render unchanged
- modern render gets dedicated stylesheet

## Phase 5: Header/footer branching

Goal:
- support modern design only if current TCPDF wrapper not enough

Tasks:
- evaluate whether modern design can reuse current `NEXLEDPDF`
- if yes: no extra class split
- if no: add variant-aware branching around title/footer globals or PDF subclass selection

Constraint:
- current runtime still relies on legacy header/footer class and legal footer rhythm

Verify:
- classic header/footer unchanged
- modern header/footer legal content still present unless explicitly redesigned

## Phase 6: File endpoint support

Goal:
- keep alternative exact-product entrypoint in sync

Tasks:
- read optional `design_variant` query param in [file-datasheet.php](./endpoints/file-datasheet.php)
- pass normalized value into payload before `buildDatasheetPdfBinary(...)`

Verify:
- same reference can export classic and modern from file endpoint
- invalid variant query fails clearly

## Phase 7: Configurator UI selector

Goal:
- expose design choice in official datasheet mode

Tasks:
- add selector near current output panel controls in [../configurator/configurator.html](../configurator/configurator.html)
- keep existing output mode segmented control unchanged
- show selector only when output mode is `datasheet`
- add text in locale files
- wire selected value into [buildDatasheetRequestBody()](../configurator/script.js)

Recommended UI labels:
- `Current`
- `New`

Recommended payload field:
- `design_variant`

Verify:
- selector does not appear in showcase mode
- selector does not appear in custom mode in V1
- generate request includes chosen variant

## Phase 8: Modern design implementation

Goal:
- build real new layout

Tasks:
- implement modern HTML assembly
- reuse same section-level data where possible
- only add new section wrappers/order where needed
- avoid editing current section fetchers unless data hole is real

Important:
- if modern design changes page order heavily, do it inside modern renderer only
- do not leak modern ordering into classic renderer

Verify:
- modern PDF opens correctly
- sections appear in intended order
- images still resolve through current PDF-safe path logic

## Phase 9: Regression and gold-sample compare

Goal:
- prove classic did not drift

Classic checks:
- omitted variant matches current behavior
- explicit `classic` matches omitted variant
- same sample references still export

Modern checks:
- sample references export without runtime errors
- layout stable across at least:
  - one barra
  - one downlight
  - one tubular

Recommended sample sources:
- family samples already used in parity work
- existing known-good refs from `PROJECT_MEMORY.md`

## 6. Suggested Backend API Shape

## 6.1 Datasheet POST

Request:

```json
{
  "referencia": "11037581110010100",
  "descricao": "LED Barra 24V 10 WW273 HE Clear",
  "idioma": "pt",
  "empresa": "0",
  "design_variant": "modern"
}
```

Behavior:
- no field -> classic
- `classic` -> current layout
- `modern` -> new layout

## 6.2 File datasheet GET

Example:

```text
GET /api/?endpoint=file-datasheet&reference=11037581110010100&design_variant=modern
```

## 7. Suggested Frontend State

Recommended state key in [script.js](../configurator/script.js):

```js
let datasheetDesignVariant = "classic";
```

Or derived directly from control:

```js
function getSelectedDatasheetDesignVariant() {
    return document.querySelector('input[name="datasheet-design-variant"]:checked')?.value || "classic";
}
```

Recommended request addition:

```js
design_variant: getSelectedDatasheetDesignVariant(),
```

## 8. Main Risks

### Risk 1: classic drift

Problem:
- current layout accidentally changes while splitting renderer

Mitigation:
- isolate current builder first
- keep classic default
- compare sample outputs before and after split

### Risk 2: header/footer mismatch

Problem:
- modern body layout may clash with legacy TCPDF header/footer class

Mitigation:
- treat header/footer as separate decision point
- branch only if needed

### Risk 3: scope sprawl

Problem:
- team tries to support showcase and custom in same pass

Mitigation:
- exact datasheet only in V1
- note follow-up phases separately

### Risk 4: family-specific layout gaps

Problem:
- some families may need modern layout exceptions

Mitigation:
- start with renderer shared by all
- gate `modern` behind family allowlist if needed

## 9. Phase Acceptance Criteria

Implementation ready for V1 when:

- `datasheet` accepts optional `design_variant`
- `file-datasheet` accepts optional `design_variant`
- omitted field stays classic
- configurator sends chosen variant in official mode
- classic output remains current baseline
- modern output renders valid PDF
- showcase flow unchanged
- custom flow unchanged

## 10. Recommended Build Order

Best order:
1. contract
2. validation helper
3. render context field
4. classic renderer extraction
5. modern renderer scaffold
6. file endpoint support
7. configurator selector
8. modern design build
9. regression compare

This order keeps current output safest while opening clean path for new design.
