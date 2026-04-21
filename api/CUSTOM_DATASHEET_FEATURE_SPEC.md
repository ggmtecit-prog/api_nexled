# Custom Datasheet Feature Spec

Status: draft

Purpose: define the new "custom datasheet" product before implementation.

This document covers:
- product goal
- API contract
- configurator mode
- override model
- asset and text rules
- render and validation guardrails

Related docs:
- [README.md](./README.md)
- [OFFICIAL_DATASHEET_LAYOUT_SPEC.md](./OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [SHOWCASE_PDF_FEATURE_SPEC.md](./SHOWCASE_PDF_FEATURE_SPEC.md)
- [SHOWCASE_PDF_IMPLEMENTATION_PLAN.md](./SHOWCASE_PDF_IMPLEMENTATION_PLAN.md)

## 1. Summary

The project currently has two PDF products:

- `datasheet`
  - one exact Tecit reference
  - one exact technical PDF
- `showcase`
  - grouped family-level PDF
  - multiple valid variants

The new feature is a third PDF product:

- `custom datasheet`
  - one exact Tecit reference
  - based on the official datasheet data for that product
  - allows approved text and image overrides
  - preserves the official datasheet structure

The custom datasheet is for internal sales, marketing, and customer-specific PDF preparation.

It must not weaken the current official datasheet flow.

## 2. Product Positioning

### Existing product: official datasheet

Characteristics:
- exact 17-char reference
- strict technical truth
- strict asset requirements
- no editable content beyond current configurator fields

### New product: custom datasheet

Characteristics:
- exact 17-char reference
- starts from the same official datasheet data
- applies a safe override layer on top
- keeps the same page rhythm and layout language
- clearly marked as custom in the footer

## 3. Goals

- Keep official datasheet generation unchanged and trustworthy.
- Allow the internal team to prepare customer-specific PDF variants.
- Reuse the current datasheet runtime and section fetchers where safe.
- Support controlled image replacement.
- Support controlled copy replacement.
- Preserve technical integrity for core numeric and compliance data.
- Expose a clear `Custom` mode in the configurator output panel.

## 4. Non-goals

- Replacing the current `datasheet` endpoint.
- Letting users freely edit all technical values.
- Accepting arbitrary remote URLs as first-version image sources.
- Creating a second fully separate layout engine unless required later.
- Turning custom datasheet into a family-level catalog flow.

## 5. Core Product Model

Custom datasheet is:

- one exact product
- one official base datasheet payload
- one validated override payload
- one final rendered PDF

It is not:

- a family-level grouped PDF
- a free-form document editor
- an editable replacement for the product database

## 6. Core Concepts

### 6.1 Base request

The feature starts from the same exact product-level request as the current datasheet flow.

That means:
- one real `referencia`
- language
- company logo
- optional current datasheet selections already used by runtime

### 6.2 Official base snapshot

The server must first build the normal official datasheet data snapshot:

- product header image and text
- characteristics
- luminotechnical data
- technical drawing
- color graph
- lens diagram
- finish image
- optional fixing/power/cable sections

This is the truth layer.

### 6.3 Override payload

After the official snapshot exists, the runtime applies a whitelist-based override layer.

This override layer may change:
- selected text fields
- selected image fields
- selected optional sections
- custom footer note and marker

### 6.4 Marker

Every custom datasheet must be visibly marked so the internal team can distinguish it from the official PDF.

Recommended footer marker:
- `customPDF`

## 7. Why this must be a separate product path

If custom behavior is bolted directly into `datasheet`, the official flow becomes harder to trust.

Separate product path gives:
- clearer UI
- clearer audit trail
- safer validation
- easier rollback
- easier QA

Recommended product switch in configurator output panel:

- `Datasheet`
- `Showcase`
- `Custom`

## 8. Current Runtime Reality

The current datasheet runtime already has a clean base for this feature.

Relevant runtime:
- [api/lib/pdf-engine.php](./lib/pdf-engine.php)
- [api/lib/pdf-layout.php](./lib/pdf-layout.php)
- [api/lib/product-header.php](./lib/product-header.php)
- [api/lib/sections.php](./lib/sections.php)
- [api/lib/images.php](./lib/images.php)

Current truth sources:
- SQL / Luminos tables
- JSON description files
- DAM assets
- legacy local asset fallback

That means the best implementation path is:
1. resolve official datasheet data
2. apply validated overrides
3. render the same datasheet layout

## 9. Canonical Request Model

The custom datasheet request should be JSON and should not overload the current `datasheet` endpoint body with hidden semantics.

Recommended canonical shape:

```json
{
  "base_request": {
    "referencia": "29012032291010100",
    "descricao": "Downlight 120 R 200",
    "idioma": "pt",
    "empresa": "0",
    "lente": "Clear",
    "acabamento": "White",
    "opcao": "00",
    "conectorcabo": "0",
    "tipocabo": "0",
    "tampa": "00",
    "vedante": 5,
    "acrescimo": 0,
    "ip": "0",
    "fixacao": "0",
    "fonte": "0",
    "caboligacao": "0",
    "conectorligacao": "0",
    "tamanhocaboligacao": 0,
    "finalidade": "0"
  },
  "custom": {
    "mode": "custom",
    "text_overrides": {
      "document_title": "Downlight 120 R 200",
      "header_copy": "Customer-specific opening copy.",
      "footer_note": "Prepared for internal presentation."
    },
    "asset_overrides": {
      "header_image": {
        "source": "dam",
        "asset_id": "asset_123"
      },
      "drawing_image": {
        "source": "dam",
        "asset_id": "asset_456"
      },
      "finish_image": {
        "source": "dam",
        "asset_id": "asset_789"
      }
    },
    "section_visibility": {
      "fixing": false,
      "power_supply": false,
      "connection_cable": false
    },
    "footer": {
      "marker": "customPDF"
    }
  }
}
```

## 10. API Contract

## 10.1 Preview endpoint

Recommended endpoint:

- `POST /api/?endpoint=custom-datasheet-preview`

Purpose:
- validate base request
- validate override payload
- confirm required assets resolve
- return normalized request summary
- return warnings before full render

Response shape:

```json
{
  "ok": true,
  "data": {
    "base_reference": "29012032291010100",
    "normalized_request": {},
    "resolved_overrides": {
      "text_overrides": ["document_title", "header_copy"],
      "asset_overrides": ["header_image", "drawing_image"],
      "hidden_sections": ["power_supply"]
    },
    "warnings": []
  }
}
```

## 10.2 Render endpoint

Required endpoint:

- `POST /api/?endpoint=custom-datasheet-pdf`

Purpose:
- generate and return the custom datasheet PDF

Success response:
- binary PDF

Error response:

```json
{
  "error": "Custom datasheet override is invalid",
  "error_code": "custom_datasheet_invalid_override",
  "detail": "Asset override header_image could not be resolved."
}
```

## 10.3 Endpoint separation

Rules:
- `custom-datasheet-preview` and `custom-datasheet-pdf` are separate from `datasheet`
- official `datasheet` remains clean and unchanged
- `custom` must never silently mutate the normal `datasheet` path

## 11. Override Categories

## 11.1 Text overrides

Text overrides are intended for sales and customer-specific copy, not for technical truth.

Recommended V1 text override fields:
- `document_title`
- `header_copy`
- `footer_note`

Recommended future V2 fields:
- section intro copy
- option-code intro copy
- section subtitle copy

Deferred future V3:
- editable text snapshot for most non-technical labels

## 11.2 Image overrides

Recommended V1 image override fields:
- `header_image`
- `drawing_image`
- `finish_image`

Recommended future V2 fields:
- `color_graph_image`
- `lens_diagram_image`
- `fixing_image`
- `fixing_render_image`
- `power_supply_image`
- `power_supply_drawing_image`
- `connection_cable_image`

## 11.3 Section visibility

Recommended V1:
- hide optional sections only

Examples:
- hide `fixing`
- hide `power_supply`
- hide `connection_cable`

Do not allow hiding core sections in V1:
- header
- characteristics
- luminotechnical
- technical drawing
- finish

## 11.4 Footer customization

Required:
- custom marker

Optional:
- one short custom footer note

Do not allow overriding the legal base footer copy in V1.

## 12. Allowed vs forbidden edits

## 12.1 Allowed in V1

- document title override
- header marketing copy override
- footer note override
- approved image replacements
- optional section visibility toggles

## 12.2 Forbidden in V1

- free editing of luminotechnical numeric values
- energy class override
- characteristics table numeric value override
- raw HTML injection
- legal/compliance footer replacement
- arbitrary remote image URLs

## 13. Asset source rules

Best V1 rule:
- only approved asset references from DAM or controlled local asset registry

Recommended asset reference model:

```json
{
  "source": "dam",
  "asset_id": "asset_123"
}
```

or

```json
{
  "source": "local",
  "asset_key": "custom/downlight/customer-a/header-01"
}
```

Do not accept:
- `https://random-site/image.png`
- arbitrary untrusted file paths

Reason:
- PDF reliability
- cache consistency
- security
- easier QA

## 14. Sanitization rules

Text override rules:
- plain text only in V1
- trim whitespace
- normalize line breaks
- escape HTML
- enforce max lengths

Suggested limits:
- `document_title`: 120 chars
- `header_copy`: 1200 chars
- `footer_note`: 160 chars

Image override rules:
- resolver must confirm asset exists
- resolver must return a PDF-safe path through the same image safety pipeline

## 15. Merge strategy

Recommended server pipeline:

1. validate base request
2. validate exact reference
3. build official datasheet data snapshot
4. validate custom override payload
5. resolve override assets
6. apply whitelist merge to datasheet data
7. render PDF through custom endpoint
8. mark footer as `customPDF`

Important:
- override merge must be explicit field-by-field
- no generic recursive merge of arbitrary user JSON into datasheet data

## 16. UI Contract

## 16.1 Output mode switch

Second panel top segmented control should become:

- `Datasheet`
- `Showcase`
- `Custom`

## 16.2 Custom mode behavior

Custom mode must still require:
- one exact valid product reference
- one resolved base product

Custom mode is product-level, not family-level.

## 16.3 Custom mode UI blocks

Recommended blocks:

### Block A: Base Product

Shared with datasheet:
- live reference
- description
- language
- company

### Block B: Custom Text

Inputs:
- document title
- header copy
- footer note

### Block C: Custom Images

Inputs:
- header image
- drawing image
- finish image

Each item needs:
- current asset preview or filename
- replace action
- reset to default action

### Block D: Optional Sections

Toggles:
- fixing
- power supply
- connection cable

### Block E: Reset

Action:
- clear all custom overrides

## 16.4 Preview guidance

Even if V1 does not render a full visual preview, the UI should still show:
- which fields are overridden
- which assets are replaced
- which optional sections are hidden

## 17. Footer marker rule

All custom datasheets must include a visible internal marker in footer text.

Recommended string:
- `customPDF`

This marker:
- must not appear in official datasheets
- must be appended through the custom runtime only

## 18. Family support policy

Initial support rule:
- custom datasheet is only available where official datasheet runtime already works

This means custom mode inherits the existing datasheet family support matrix.

No separate custom family renderer is required in V1 if the same official layout is reused.

## 19. Error handling

Recommended error codes:
- `custom_datasheet_invalid_request`
- `custom_datasheet_invalid_override`
- `custom_datasheet_asset_not_found`
- `custom_datasheet_text_too_long`
- `custom_datasheet_unsupported_field`
- `custom_datasheet_base_datasheet_failed`

## 20. Versioning

## V1

- separate custom mode
- separate preview endpoint
- separate render endpoint
- text overrides:
  - document title
  - header copy
  - footer note
- image overrides:
  - header image
  - drawing image
  - finish image
- optional section visibility
- footer marker

## V2

- more image override targets
- more section intro copy
- better preview and asset picker UX

## V3

- broader non-technical editable text snapshot
- import/export JSON preset support
- saved custom PDF templates

## 21. Acceptance criteria

Feature is ready for V1 when:

- official `datasheet` output is unchanged
- custom mode is visible in configurator
- user can generate one custom PDF from one exact reference
- approved text overrides appear in PDF
- approved image overrides appear in PDF
- forbidden fields are blocked with explicit errors
- footer marker clearly identifies custom PDFs
- stale or invalid custom assets fail clearly before render
