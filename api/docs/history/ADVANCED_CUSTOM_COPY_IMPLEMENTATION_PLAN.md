# Advanced Custom Copy Implementation Plan

Status: draft

Purpose: phased implementation plan for the `Advanced Copy Editing` extension inside `Custom Datasheet`.

Related docs:
- [ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md](./ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md)
- [ADVANCED_CUSTOM_COPY_EDITABLE_MATRIX.md](./ADVANCED_CUSTOM_COPY_EDITABLE_MATRIX.md)
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md](./CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md)

## 1. Implementation Principles

- Extend `Custom Datasheet`; do not fork it into a second product.
- Keep official datasheet layout and technical truth unchanged.
- Load editable text from real runtime snapshot, not hardcoded frontend strings.
- Use explicit field mapping, never generic recursive merge.
- Keep advanced mode optional behind one UI toggle.

## 2. Deliverables

Backend:
- preview snapshot extension for editable copy
- advanced copy request normalizer
- advanced copy validator
- override merge layer for section copy
- render support for approved copy slots

Frontend:
- toggle in `Custom` tab
- section-based text editors
- snapshot load flow
- reset/default logic
- preview summary updates

Docs:
- feature spec
- implementation plan
- editable matrix

## 3. Proposed File Plan

Update:
- [api/lib/custom-datasheet/request.php](./lib/custom-datasheet/request.php)
- [api/lib/custom-datasheet/validation.php](./lib/custom-datasheet/validation.php)
- [api/lib/custom-datasheet/render.php](./lib/custom-datasheet/render.php)
- [api/endpoints/custom-datasheet-preview.php](./endpoints/custom-datasheet-preview.php)
- [api/endpoints/custom-datasheet-pdf.php](./endpoints/custom-datasheet-pdf.php)
- [configurator/configurator.html](../configurator/configurator.html)
- [configurator/script.js](../configurator/script.js)
- [configurator/locales/en.js](../configurator/locales/en.js)
- [configurator/locales/pt.js](../configurator/locales/pt.js)

Optional helper split if needed:
- `api/lib/custom-datasheet/copy-snapshot.php`
- `api/lib/custom-datasheet/copy-overrides.php`

## 4. Architecture

Target flow:

1. base exact-product request
2. build official custom preview snapshot
3. derive editable copy snapshot
4. frontend edits approved section text
5. backend validates `copy_overrides`
6. backend merges only approved copy slots
7. render same custom/official datasheet layout

## 5. Phase Plan

## Phase 0: Freeze editable fields

Goal:
- lock exact V1 editable section map

Tasks:
- finalize [ADVANCED_CUSTOM_COPY_EDITABLE_MATRIX.md](./ADVANCED_CUSTOM_COPY_EDITABLE_MATRIX.md)
- confirm V1 section set
- confirm max lengths per field
- confirm naming for UI toggle and payload keys

Verify:
- every editable field maps to one real render slot
- no technical truth field marked editable

## Phase 1: Preview snapshot extension

Goal:
- backend preview returns real editable copy snapshot

Tasks:
- extend `custom-datasheet-preview` response
- extract current copy from resolved datasheet context
- include only sections that exist for current family/reference
- return canonical `editable_copy` object

Verify:
- valid product preview returns snapshot
- missing family sections do not appear
- toggle-off frontend can ignore new payload safely

## Phase 2: Request normalization

Goal:
- accept advanced copy request shape cleanly

Tasks:
- add `copy_mode`
- add `copy_overrides`
- normalize section keys
- normalize nested field values
- reject unknown sections and unknown fields

Verify:
- valid nested payload normalizes consistently
- empty advanced payload behaves like no-op
- unknown fields fail clearly

## Phase 3: Validation

Goal:
- block bad copy payloads before render

Tasks:
- validate `copy_mode`
- validate per-section field allowlist
- validate plain-text only
- validate length caps
- reject unavailable sections for current family/snapshot

Verify:
- unsupported section fails with clear error
- too-long field fails with clear error
- non-string field fails with clear error

## Phase 4: Copy snapshot extractor

Goal:
- derive stable editable text from official runtime context

Tasks:
- inspect current datasheet context structure from [api/lib/pdf-engine.php](./lib/pdf-engine.php)
- map existing rendered copy slots
- create one extractor that returns canonical snapshot fields
- keep extractor deterministic and family-safe

Important:
- do not scrape raw HTML output if avoidable
- prefer extracting from pre-render context

Verify:
- same product returns same snapshot on repeated calls
- returned values match current rendered text

## Phase 5: Copy merge layer

Goal:
- apply advanced text safely to resolved datasheet context

Tasks:
- create explicit per-slot merge map
- inject only approved values into existing context nodes
- keep old `text_overrides` support intact
- define precedence:
  - advanced section copy override
  - then old small text override if still relevant
  - then default official value

Recommended precedence:
- `copy_overrides.footer.note` should override `text_overrides.footer_note` only if explicitly provided

Verify:
- no-op advanced request leaves render unchanged
- changed copy mutates only intended slots
- numeric/technical values remain identical

## Phase 6: Frontend toggle and editors

Goal:
- expose feature cleanly in `Custom` tab

Tasks:
- add toggle
- add hidden advanced editor container
- when toggle turns on, fetch/use preview snapshot
- render one editor block per available editable section
- add reset-to-default behavior
- keep current simple custom controls still visible or logically grouped

Recommended UX:
- simple overrides stay first
- advanced copy appears below
- unavailable sections hidden automatically

Verify:
- toggle off keeps current custom UX unchanged
- toggle on shows populated textareas
- section values persist in state while editing

## Phase 7: Preview summary updates

Goal:
- make advanced edits visible in preview summary

Tasks:
- count edited advanced fields
- show which sections are edited
- show validation error toast if advanced payload invalid

Optional V1.5:
- show "using default" vs "customized" badge per section

Verify:
- summary counts update after edits
- reset restores counts to zero

## Phase 8: PDF render support

Goal:
- final custom PDF honors advanced copy

Tasks:
- wire advanced copy path in `custom-datasheet-pdf`
- ensure same footer markers stay
- verify line wrapping in all updated copy slots
- keep image overrides and visibility toggles compatible

Verify:
- generated PDF reflects edited copy
- official/custom/simple paths still render
- no broken layout on long but valid text

## Phase 9: Family QA

Goal:
- prove advanced copy works across current custom-supported families

Priority QA groups:
- tubular
- barra
- downlight
- dynamic
- shelf

Checks:
- snapshot fields available only when section exists
- rendered text lands in right slot
- no overlap or clipping
- unsupported section edit rejected cleanly

## 6. Main Technical Risks

## 6.1 Snapshot source ambiguity

Risk:
- some current copy may be assembled late in render flow

Mitigation:
- extract from resolved render context, not final PDF HTML where possible

## 6.2 Layout overflow

Risk:
- long section text may push blocks too far

Mitigation:
- strict max lengths
- field-specific limits
- family QA per renderer group

## 6.3 Field drift

Risk:
- future renderer refactor changes copy slot names

Mitigation:
- one canonical extractor and one canonical merge map
- no duplicated field-name logic between preview and render

## 7. Suggested Rollout

Wave 1:
- toggle
- preview snapshot
- `header`, `characteristics`, `option_codes`, `footer`

Wave 2:
- `luminotechnical`, `drawing`, `finish`

Wave 3:
- extra family-specific slots only if really needed

This is safer than shipping every possible section in one pass.

## 8. Acceptance Criteria

- `Custom` mode still works unchanged when advanced toggle is off
- preview returns editable copy snapshot for valid product
- advanced request validates strictly
- edited copy renders in approved slots only
- no technical truth field becomes editable
- PDFs stay visually stable across supported families
