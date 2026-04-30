# Advanced Custom Field Overrides Implementation Plan

Status: draft

Purpose: phased implementation plan for `Advanced Field Overrides` inside `Custom Datasheet`.

Related docs:
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_FEATURE_SPEC.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_MATRIX.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_MATRIX.md)
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md](./CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md)
- [ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md](./ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md)

## 1. Implementation principles

- Extend `Custom Datasheet`; do not fork product.
- Keep `base_request` fully real and valid.
- Treat overrides as display-layer changes only.
- Use explicit field mapping, never recursive generic merge.
- Prefer current visible runtime values as defaults.
- Do not touch official datasheet endpoint or behavior.

## 2. Deliverables

Backend:
- preview snapshot for field defaults
- request normalizer for `field_overrides`
- validator for allowed field keys and lengths
- merge layer from `field_overrides` into render context

Frontend:
- advanced field overrides mode/panel
- per-field toggle + input
- preview-driven field defaults
- reset/default behavior
- summary counts

Docs:
- feature spec
- implementation plan
- field matrix

## 3. Proposed file plan

Update:
- [api/lib/custom-datasheet/request.php](./lib/custom-datasheet/request.php)
- [api/lib/custom-datasheet/validation.php](./lib/custom-datasheet/validation.php)
- [api/lib/custom-datasheet/render.php](./lib/custom-datasheet/render.php)
- [api/lib/family-registry.php](./lib/family-registry.php)
- [api/endpoints/custom-datasheet-preview.php](./endpoints/custom-datasheet-preview.php)
- [api/endpoints/custom-datasheet-pdf.php](./endpoints/custom-datasheet-pdf.php)
- [configurator/configurator.html](../configurator/configurator.html)
- [configurator/script.js](../configurator/script.js)
- [configurator/locales/en.js](../configurator/locales/en.js)
- [configurator/locales/pt.js](../configurator/locales/pt.js)

Optional helper split if code grows:
- `api/lib/custom-datasheet/field-overrides.php`
- `api/lib/custom-datasheet/field-snapshot.php`

## 4. Architecture

Target flow:

1. exact base product request
2. official custom preview snapshot
3. extract field snapshot from render context
4. frontend shows base value for each overridable field
5. user toggles selected fields to override mode
6. user enters custom display values
7. backend validates `field_overrides`
8. backend merges overrides into render context
9. same custom datasheet layout renders

## 5. Phase plan

## Phase 0: Freeze V1 field map

Goal:
- lock exact fields for first implementation

Tasks:
- finalize [ADVANCED_CUSTOM_FIELD_OVERRIDES_MATRIX.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_MATRIX.md)
- define internal keys
- define per-field max lengths
- define unavailable-field behavior

Verify:
- every V1 field maps to one exact render slot
- no V1 field changes base product lookup

## Phase 1: Extend registry metadata

Goal:
- expose allowed field override keys per family

Tasks:
- add `field_overrides` allowlist to `custom_datasheet_allowed_fields`
- add defaults bucket to `custom_datasheet_defaults`
- keep family-level override capability centralized

Verify:
- families endpoint returns field override metadata
- old clients still work if they ignore new metadata

## Phase 2: Request normalization

Goal:
- accept `field_overrides` cleanly

Tasks:
- add `field_overrides` to allowed custom keys
- normalize null/empty payload to `[]`
- trim values
- preserve strings only

Verify:
- valid payload normalizes consistently
- empty payload behaves as no-op
- unknown top-level group fails clearly

## Phase 3: Validation

Goal:
- block bad field override payloads before render

Tasks:
- add allowlist validator
- add plain-text validator
- add per-field length caps
- add unavailable-field validator for optional sections

Verify:
- unsupported field fails with clear error
- non-scalar value fails
- too-long field fails

## Phase 4: Field snapshot extractor

Goal:
- backend preview returns current visible values for each overridable field

Tasks:
- inspect resolved custom render context
- extract one canonical `field_snapshot`
- include only fields visible for current product
- return clean strings already ready for UI

Verify:
- same product returns stable snapshot
- snapshot values match currently rendered PDF content

## Phase 5: Merge layer

Goal:
- apply field overrides safely to resolved render context

Tasks:
- create explicit field-to-context map
- apply overrides after official context exists
- define precedence against existing copy/text overrides

Recommended precedence:
- `field_overrides`
- then `copy_overrides`
- then `text_overrides`
- then official value

Verify:
- no-op request leaves PDF unchanged
- overridden fields update only intended slots
- base technical lookup remains unchanged

## Phase 6: Preview response extension

Goal:
- frontend can render dynamic advanced field editor

Tasks:
- extend preview response with:
  - `field_snapshot`
  - `allowed_field_overrides`
  - `applied_fields.field_overrides`
- keep response backward compatible

Verify:
- preview consumers without new UI do not break
- new UI has enough data to build controls

## Phase 7: Frontend UI

Goal:
- expose fast field editing in Custom advanced flow

Tasks:
- add advanced field overrides panel
- render grouped fields from snapshot
- each field gets:
  - label
  - current value preview
  - override toggle
  - input shown on demand
- keep image override and copy override panels intact

Recommended UX:
- group by PDF section
- collapsed by default
- show customized count in heading

Verify:
- toggle off = no override submitted
- toggle on = input shown and value stored
- reset clears overrides and hides inputs

## Phase 8: Request builder

Goal:
- frontend sends `field_overrides`

Tasks:
- extend `buildCustomRequestBody()`
- collect only enabled override inputs
- avoid sending unchanged defaults

Verify:
- request payload small and deterministic
- only changed fields sent

## Phase 9: Preview + summary state

Goal:
- make state understandable before PDF generation

Tasks:
- show overridden field count
- surface field validation errors in toast + preview message
- show unavailable-field rejection clearly

Verify:
- count updates as user edits
- invalid field value blocks generate cleanly

## Phase 10: Render QA

Goal:
- verify PDF still stable with display overrides

Tasks:
- test representative families
- test optional sections present/absent
- test field precedence against copy overrides
- test long but valid values

Verify:
- official PDF unchanged
- custom PDF shows overridden values
- no broken DB lookups or missing images caused by field overrides

## 6. Suggested V1 field set

Start with:
- `display_reference`
- `display_description`
- `display_size`
- `display_color`
- `display_cri`
- `display_series`
- `display_lens_name`
- `display_finish_name`
- `display_cap`
- `display_option_code`
- `display_flux`
- `display_efficacy`
- `display_cct`
- `display_color_label`
- `display_cri_label`
- `drawing_dimension_A` through `drawing_dimension_J`
- `fixing_name`
- `power_supply_description`
- `connection_cable_description`

Do not start with:
- `language`
- `company`
- any hidden internal asset lookup key

## 7. Risks

### Risk 1: field meaning drift

Problem:
- UI label says `Size`
- real render slot is not a single size field in all families

Mitigation:
- field matrix must define exact render slot, not only user-facing label

### Risk 2: precedence confusion

Problem:
- same text could be edited by simple text overrides, advanced copy, and field overrides

Mitigation:
- lock one explicit precedence order
- document it in code comments and docs

### Risk 3: long values break layout

Problem:
- field overrides can overflow tables

Mitigation:
- conservative length caps
- test with longest allowed values

## 8. Acceptance criteria

Feature done when:

- preview returns `field_snapshot`
- UI renders field toggles and text inputs
- custom request accepts `field_overrides`
- backend validates and merges correctly
- custom PDF shows overridden display values
- official product truth and official PDFs remain unchanged

