# Showcase PDF Feature Spec

Status: draft

Purpose: define the new "showcase PDF" product before implementation.

This document covers:
- product goal
- API contract
- configurator mode
- data model
- family renderer strategy
- optional shorthand showcase pattern such as `29012032291XXYYZZ`

Related docs:
- [README.md](./README.md)
- [NEXT_STEPS_DATASHEET_PARITY.md](./NEXT_STEPS_DATASHEET_PARITY.md)
- [OFFICIAL_DATASHEET_LAYOUT_SPEC.md](./OFFICIAL_DATASHEET_LAYOUT_SPEC.md)

## 1. Summary

The project currently supports one core PDF flow:

- real Tecit reference
- exact product lookup
- exact datasheet PDF

The new feature is a second PDF product:

- showcase PDF
- based on one family plus locked and expanded segments
- can display multiple valid variants inside one PDF

Examples:
- all luminotechnical rows for one size
- all color spectra for one product line
- all finish or option code legends
- grouped technical drawings or finish galleries

The showcase PDF is separate from the technical datasheet runtime. It must not weaken or complicate the existing `datasheet` endpoint flow.

## 2. Product Positioning

### Existing product

Technical datasheet:
- input: one real 17-char reference
- output: one exact technical PDF
- strict asset and data validation

### New product

Showcase PDF:
- input: one family plus request rules
- output: one multi-variant catalog-style PDF
- may show groups, matrices, legends, and repeated sections

## 3. Goals

- Support all families through a common architecture.
- Reuse current data truth from the API instead of inventing a parallel source.
- Keep current datasheet runtime untouched.
- Allow the configurator to export either a technical datasheet or a showcase PDF.
- Support explicit UI configuration first.
- Support optional shorthand showcase patterns later, without overloading normal Tecit code logic.

## 4. Non-goals

- Replacing the current `datasheet` endpoint.
- Treating showcase patterns as valid Tecit product references.
- Blindly generating full cartesian products of every dropdown for every family.
- Forcing one generic page layout for all product families.

## 5. Core Concepts

### 5.1 Locked segments

Locked segments stay fixed in the showcase request.

Example:
- family = `29`
- size = `0120`
- series = `9`

### 5.2 Expanded segments

Expanded segments tell the runtime which parts may vary across the showcased variants.

Example:
- expand `color`
- expand `cri`
- expand `finish`

### 5.3 Sections

Sections control what content appears in the final PDF.

Initial canonical section names:
- `overview`
- `luminotechnical`
- `spectra`
- `technical_drawings`
- `lens_diagrams`
- `finish_gallery`
- `option_codes`
- `accessories`
- `power_supplies`
- `connection_cables`

### 5.4 Filters

Filters constrain the valid variants used by the PDF.

Initial filter set:
- `datasheet_ready_only`
- `max_variants`
- `max_pages`
- `sort_by`

## 6. Canonical Segment Model

Showcase PDF uses the same segment structure as live Tecit references.

| Segment | Length | Meaning |
| --- | ---: | --- |
| `family` | 2 | family code |
| `size` | 4 | size code |
| `color` | 2 | LED color code |
| `cri` | 1 | CRI code |
| `series` | 1 | series code |
| `lens` | 1 | lens code |
| `finish` | 2 | body finish code |
| `cap` | 2 | cap or base code |
| `option` | 2 | option code |

Rules:
- `family` is always locked.
- `locked` and `expanded` apply to the remaining segments.
- expanded segments must still obey family capability rules.

## 7. Canonical Request Model

The backend canonical request format is JSON. The configurator may use advanced UI controls or shorthand pattern parsing, but the server should normalize everything into this structure.

```json
{
  "family": "29",
  "lang": "pt",
  "company": "0",
  "base_reference": "29012032291010100",
  "locked": {
    "size": "0120",
    "series": "9",
    "lens": "1"
  },
  "expanded": ["color", "cri", "finish", "cap", "option"],
  "sections": [
    "overview",
    "luminotechnical",
    "spectra",
    "technical_drawings",
    "lens_diagrams",
    "finish_gallery",
    "option_codes"
  ],
  "filters": {
    "datasheet_ready_only": true,
    "max_variants": 80,
    "max_pages": 30,
    "sort_by": "reference"
  }
}
```

Rules:
- `base_reference` is optional but recommended when the user starts from a real live configuration.
- `locked` may be empty only if the family renderer explicitly supports broad family-level showcase exports.
- `expanded` must not contain `family`.
- `sections` must be validated against family showcase capabilities.

## 8. API Contract

## 8.1 Preview endpoint

Recommended endpoint:

- `POST /api/?endpoint=showcase-preview`

Purpose:
- validate request
- normalize pattern or UI payload
- return matching variant count
- return estimated page count
- return warnings before full render

Request content type:
- `application/json`

Response shape:

```json
{
  "ok": true,
  "data": {
    "normalized_request": {},
    "family": {
      "code": "29",
      "name": "Downlight redondo"
    },
    "variant_count": 16,
    "estimated_pages": 5,
    "sections": ["overview", "luminotechnical", "spectra", "option_codes"],
    "warnings": []
  }
}
```

## 8.2 Render endpoint

Required endpoint:

- `POST /api/?endpoint=showcase-pdf`

Purpose:
- generate and return showcase PDF

Request content type:
- `application/json`

Success response:
- binary PDF download

Error response:

```json
{
  "error": "Showcase request is too large",
  "error_code": "showcase_limit_exceeded",
  "detail": "Matched 184 variants but max_variants is 80."
}
```

## 8.3 Endpoint separation

Rules:
- `showcase-preview` and `showcase-pdf` are separate from `reference`, `decode-reference`, and `datasheet`.
- the existing `datasheet` endpoint continues to accept only real exact product requests.
- a showcase request must never silently fall through to the technical datasheet runtime.

## 9. Optional Shorthand Showcase Pattern

## 9.1 Purpose

This is optional convenience input for advanced users.

It is not a real Tecit code.
It is not accepted by the normal datasheet flow.

It is only accepted inside showcase mode.

## 9.2 Initial shorthand form

Example:

```txt
29012032291XXYYZZ
```

Meaning:
- lock:
  - family = `29`
  - size = `0120`
  - color = `32`
  - cri = `2`
  - series = `9`
  - lens = `1`
- expand:
  - finish = `XX`
  - cap = `YY`
  - option = `ZZ`

Normalized request:

```json
{
  "family": "29",
  "locked": {
    "size": "0120",
    "color": "32",
    "cri": "2",
    "series": "9",
    "lens": "1"
  },
  "expanded": ["finish", "cap", "option"]
}
```

## 9.3 Rules

- shorthand is parsed only when the user selected showcase export mode.
- shorthand must be normalized before validation.
- shorthand is optional sugar, not the canonical API contract.
- if shorthand conflicts with explicit `locked` or `expanded` fields, the request must fail clearly.

## 9.4 Future extensions

Possible later syntax:
- generic wildcard tokens for earlier segments
- UI-assisted token builder
- named preset patterns

These are not part of the initial implementation.

## 10. Variant Truth and Selection

Showcase PDFs must only use real valid combinations.

Primary truth sources:
- `tecit_lampadas.Luminos` for real identity truth
- `tecit_referencias` for selectable suffix options
- current readiness logic from [code-explorer.php](./lib/code-explorer.php)
- current family and reference helpers from [reference-decoder.php](./lib/reference-decoder.php)

Rules:
- do not build blind cartesian products and assume they exist
- use real Luminos identities first
- apply family option tables after identity truth is known
- optionally filter to datasheet-ready variants only
- stable ordering must be deterministic

Recommended default sort:
- `reference`

## 11. Family Capability Registry

The family registry should be extended to support showcase features.

Recommended new fields:
- `showcase_supported`
- `showcase_renderer`
- `showcase_sections`
- `showcase_expandable_segments`
- `showcase_defaults`

Example shape:

```php
[
    "showcase_supported" => true,
    "showcase_renderer" => "downlight",
    "showcase_sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes"],
    "showcase_expandable_segments" => ["color", "cri", "lens", "finish", "cap", "option"],
]
```

Renderer groups:
- `barra`
- `downlight`
- `tubular`
- `shelf`
- `dynamic`
- `spot`
- `decor`
- `highbay`
- `luminaire`
- `panel`
- `canopy`

Reason:
- page structure differs too much between family groups
- official PDFs are page-template driven, not pure section stacks

Implementation note:
- the architecture must support all renderer groups in the registry
- rollout may still ship the most understood groups first and block the rest honestly until mapped

## 12. Section Semantics

## 12.1 `overview`

Purpose:
- title
- family or product line intro
- hero image or grouped image
- high-level characteristics

## 12.2 `luminotechnical`

Purpose:
- multi-row matrix of valid references
- may span multiple pages

Expected content:
- full reference or showcase code row
- description
- flux
- efficacy
- color
- CCT
- CRI
- lens label when relevant

## 12.3 `spectra`

Purpose:
- one or more color graph blocks

Rules:
- dedupe identical graph assets
- group by LED identity when possible
- stable order by color then CRI

## 12.4 `technical_drawings`

Purpose:
- one or more drawing blocks or grouped dimension tables

Rules:
- dedupe identical drawings when multiple variants resolve to same drawing

## 12.5 `lens_diagrams`

Purpose:
- one or more lens diagram blocks

Rules:
- only include when the family and locked request actually support lens variation

## 12.6 `finish_gallery`

Purpose:
- show finish variants with code labels and image samples

## 12.7 `option_codes`

Purpose:
- explain suffix code groups such as `XX`, `YY`, `ZZ`

Rules:
- use family-specific labels and examples
- this section may exist even when no full finish gallery is requested

## 12.8 `accessories`

Purpose:
- fixing, accessory, support, cap, or mechanical catalog pages when the family supports them

## 12.9 `power_supplies`

Purpose:
- optional catalog pages for matching power supply variants

## 12.10 `connection_cables`

Purpose:
- optional catalog pages for cable and connector variants

## 13. Layout Strategy

The showcase product must not use the current single-stack datasheet layout as its core architecture.

Recommended strategy:
- shared normalized section data
- family-group renderers
- page templates per family group

Why:
- downlight showcase layout is not the same as barra showcase layout
- accessories and option legends differ by family
- page order is part of the product, not just a styling detail

## 14. Configurator UI Mode

The configurator should expose two export products:

- `Technical Datasheet`
- `Showcase PDF`

When `Showcase PDF` is selected, the UI should show:
- base family selector
- optional base reference
- locked segment controls
- expanded segment toggles
- section toggles
- preview summary
- estimated variant and page counts
- optional advanced shorthand pattern field

Rules:
- the current generate button behavior must remain unchanged for `Technical Datasheet`
- showcase mode should build a dedicated request payload
- preview should run before final render when the request is large

## 15. Limits and Safeguards

Initial guardrails:
- default `datasheet_ready_only = true`
- default `max_variants = 80`
- default `max_pages = 30`
- hard backend upper limit must exist even if the frontend fails to enforce it

Required failure cases:
- zero matching variants
- unsupported family renderer
- unsupported section for that family
- request exceeds limits
- ambiguous or conflicting shorthand pattern

## 16. Error Model

Recommended error codes:
- `showcase_unsupported_family`
- `showcase_invalid_request`
- `showcase_invalid_pattern`
- `showcase_conflicting_pattern_and_fields`
- `showcase_no_matching_variants`
- `showcase_limit_exceeded`
- `showcase_no_supported_sections`
- `showcase_render_failed`

## 17. Output Naming

Recommended filename pattern:

```txt
showcase_{family}_{scope}_{lang}.pdf
```

Examples:
- `showcase_29_0120-ww303-options_pt.pdf`
- `showcase_11_0375-all-finish_pt.pdf`

## 18. Acceptance Criteria

The feature is ready only when:
- current `datasheet` flow is unchanged
- configurator can switch between technical and showcase exports
- showcase preview returns sane counts and warnings
- showcase PDF uses only valid combinations
- no invalid fake references leak into normal datasheet logic
- renderer output is family-aware, not one generic stack
- shorthand pattern support, if enabled, normalizes correctly

## 19. Implementation Notes

- Reuse current section fetchers where possible.
- Reuse current readiness logic where possible.
- Keep shared data assembly separate from HTML layout code.
- Store family-specific showcase configuration in registry, not scattered conditionals.
- Prefer introducing new files under `api/lib/showcase/` rather than growing the current datasheet engine.
