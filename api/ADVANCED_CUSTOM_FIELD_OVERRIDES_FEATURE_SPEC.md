# Advanced Custom Field Overrides Feature Spec

Status: draft

Purpose: define the `Advanced Field Overrides` extension inside `Custom Datasheet`.

This document covers:
- product goal
- why direct free-text replacement of configurator dropdowns is unsafe
- safe `display override` model
- API contract
- UI behavior
- render and validation rules

Related docs:
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md](./CUSTOM_DATASHEET_IMPLEMENTATION_PLAN.md)
- [ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md](./ADVANCED_CUSTOM_COPY_FEATURE_SPEC.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_IMPLEMENTATION_PLAN.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_IMPLEMENTATION_PLAN.md)
- [ADVANCED_CUSTOM_FIELD_OVERRIDES_MATRIX.md](./ADVANCED_CUSTOM_FIELD_OVERRIDES_MATRIX.md)

## 1. Summary

The user wants `Custom > Advanced Editing` to make PDF editing faster:

- keep one exact real base product
- let the user toggle any visible field into a text input
- let the custom PDF show that typed value

This must not change how the base product is resolved.

So this feature is not:
- free-text replacement of the real product lookup fields

This feature is:
- a display-only override layer on top of a real resolved custom datasheet

Simple rule:

- `base_request` finds the real product
- `field_overrides` changes what the custom PDF displays

## 2. Why direct dropdown replacement is unsafe

The current configurator dropdowns do real work:

- they build the 17-char reference
- they determine DB lookups
- they determine image lookup
- they determine technical drawing calculations
- they determine optional section fetches

Relevant runtime:
- [configurator/script.js](../configurator/script.js)
- [api/lib/pdf-engine.php](./lib/pdf-engine.php)
- [api/lib/product-header.php](./lib/product-header.php)
- [api/lib/technical-drawing.php](./lib/technical-drawing.php)
- [api/lib/sections.php](./lib/sections.php)

If `Size`, `Lens`, `Finish`, or `Option` become arbitrary free text in the base request:

- product resolution can fail
- wrong images can be fetched
- wrong dimensions can be calculated
- optional sections can disappear or mismatch

So the feature must not replace `base_request`.

## 3. Correct mental model

The feature should work like this:

1. user selects a real product as usual
2. backend builds the normal custom datasheet snapshot
3. user enables `Advanced Field Overrides`
4. user toggles individual visible fields into text inputs
5. backend merges those typed values into render context
6. only the custom PDF display changes

Official product truth remains unchanged.

## 4. Product positioning

This is an extension of `Custom Datasheet`.

It is not:
- a new fourth PDF product
- a replacement for `Advanced Copy Editing`

It sits next to existing custom tools:

- image overrides
- text overrides
- advanced copy editing
- section visibility

Recommended `Custom` editing ladder:

- `Basic Editing`
  - title
  - header copy
  - footer note
  - image overrides
- `Advanced Copy Editing`
  - section-level copy editors
- `Advanced Field Overrides`
  - field-level display editors

## 5. Goals

- Let internal team change visible PDF values faster than editing many copy blocks.
- Keep real product lookup stable.
- Keep official product data untouched.
- Make field editing explicit and reversible.
- Support field-by-field toggle workflow.
- Keep UI understandable: default value vs overridden value.

## 6. Non-goals

- Writing arbitrary free text into the real reference builder.
- Editing database truth.
- Replacing official product images.
- Editing hidden internal values that never appear in PDF.
- Generic recursive override of the whole render context.

## 7. Canonical model

New custom payload branch:

```json
{
  "base_request": {
    "referencia": "11037581110010100",
    "idioma": "pt",
    "empresa": "0"
  },
  "custom": {
    "mode": "custom",
    "copy_mode": "advanced",
    "field_overrides": {
      "display_size": "125",
      "display_color": "Warm White 3100K",
      "display_cri": "CRI 95",
      "display_series": "Custom 700mA",
      "display_lens_name": "Optic X",
      "display_finish_name": "Branco mate",
      "display_option_code": "DIM + DALI",
      "drawing_dimension_A": "127",
      "drawing_dimension_B": "68",
      "power_supply_description": "Driver prepared for customer test bench."
    }
  }
}
```

Important:
- these are not base product lookup values
- these are final display values only

## 8. Naming rule

Use `field_overrides`, not `raw_override` or `free_edit`.

Reason:
- makes intent explicit
- matches current custom override naming
- implies targeted, validated behavior

## 9. Override categories

Field overrides should be grouped by visible PDF area.

### 9.1 Core display

- document title
- reference
- description

### 9.2 Header / top-section display

- size display
- color display
- cri display
- series display
- lens display
- finish display
- cap display
- option display
- purpose display
- company display label if needed

### 9.3 Luminotechnical display

- flux
- efficacy
- cct
- color label
- cri label
- lens name

### 9.4 Characteristics display

- selected characteristic values
- optionally selected labels later

### 9.5 Drawing display

- dimensions A-J

### 9.6 Finish / lens section display

- finish name
- lens name

### 9.7 Optional section display

- fixing name
- power supply description
- connection cable description

## 10. Important constraint: not every configurator field maps 1:1

Some configurator inputs map cleanly to one visible PDF slot.

Examples:
- `lens` -> visible lens name
- `finish` -> visible finish name
- `power supply` -> visible section description

Some inputs do not map cleanly to one single visible slot.

Examples:
- `language`
- `company`
- `purpose`
- `size`
- `series`
- `cap`
- `option`

Those may influence:
- composed header paragraphs
- image lookups
- drawing calculations
- internal JSON text

So V1 should expose only fields with clear display targets.

If business still wants literal labels for every configurator field, the UI may show them as friendly names, but backend must map them to exact render slots.

## 11. UI behavior

Recommended UX:

For each advanced field:
- show current default value
- show a toggle:
  - `Use base`
  - `Override`
- when `Override` is enabled:
  - reveal text input
  - prefill with current visible value

Example:

- `Size`
  - default: `120`
  - toggle on
  - text field appears
  - user types `125`

Generated custom PDF should show `125`.

The base product remains the original selected product.

## 12. Preview requirements

`custom-datasheet-preview` should return:

- current visible field snapshot
- allowed field list
- active overridden field count
- validation messages

Preview must be able to populate the advanced field inputs with current default values.

## 13. Validation rules

### 13.1 General

- accept only known field keys
- plain text only
- trim whitespace
- normalize line breaks
- reject nested objects and arrays
- reject HTML

### 13.2 Per-field

- each field has explicit max length
- numeric-looking fields may still be plain text
- dimensions should remain strings so `127 mm` is allowed if business wants it

### 13.3 Availability

- only allow fields that exist for the resolved product
- do not allow `fixing_name` override if fixing section does not exist
- same for power supply / connection cable

## 14. Merge rules

Precedence should be:

1. `field_overrides`
2. `copy_overrides`
3. `text_overrides`
4. official resolved value

Reason:
- field override is most specific
- copy override is section-level
- text override is broad legacy custom layer

## 15. Render rules

The override merge must happen after:

1. official datasheet context resolved
2. image overrides resolved
3. section visibility decided

Then field overrides are applied to explicit render slots.

Do not:
- mutate DB lookups
- regenerate images from text
- rewrite reference-decoder logic

## 16. Safety rules

- official datasheet flow untouched
- custom datasheet official base remains reproducible
- image overrides remain isolated custom-only assets
- field override never updates product records
- field override never updates DAM links

## 17. Suggested backend file surface

Update:
- [api/lib/custom-datasheet/request.php](./lib/custom-datasheet/request.php)
- [api/lib/custom-datasheet/validation.php](./lib/custom-datasheet/validation.php)
- [api/lib/custom-datasheet/render.php](./lib/custom-datasheet/render.php)
- [api/endpoints/custom-datasheet-preview.php](./endpoints/custom-datasheet-preview.php)
- [api/endpoints/custom-datasheet-pdf.php](./endpoints/custom-datasheet-pdf.php)

Optional helper split:
- `api/lib/custom-datasheet/field-overrides.php`

## 18. Suggested frontend file surface

Update:
- [configurator/configurator.html](../configurator/configurator.html)
- [configurator/script.js](../configurator/script.js)
- [configurator/locales/en.js](../configurator/locales/en.js)
- [configurator/locales/pt.js](../configurator/locales/pt.js)

## 19. Recommended rollout

V1:
- support field overrides for clearly mapped visible fields only
- keep real base request mandatory
- ship preview snapshot + PDF render

V2:
- broaden field map where needed
- add per-field reset/default badges
- maybe allow grouped `override all visible values in this section`

## 20. Acceptance criteria

Feature is done when:

- user can choose a real product
- user can enable advanced field override mode
- user can toggle fields into text inputs
- preview returns current visible values
- custom PDF renders using overridden display values
- official PDF remains unchanged
- invalid field keys fail cleanly
- unsupported section fields fail cleanly

