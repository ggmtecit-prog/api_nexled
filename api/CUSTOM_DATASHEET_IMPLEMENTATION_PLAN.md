# Custom Datasheet Implementation Plan

Status: draft

Purpose: phased implementation plan for the new custom datasheet feature across API and configurator.

Related spec:
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [CUSTOM_DATASHEET_OVERRIDE_MATRIX.md](./CUSTOM_DATASHEET_OVERRIDE_MATRIX.md)

## 1. Implementation Principles

- Do not change current `datasheet` behavior.
- Build custom datasheet as a separate product path.
- Reuse the official datasheet runtime and data fetchers where safe.
- Apply overrides through an explicit whitelist merge.
- Prefer controlled asset IDs over raw remote URLs.
- Keep technical truth immutable in V1.

## 2. Expected Deliverables

Backend deliverables:
- preview endpoint
- PDF endpoint
- custom request normalizer
- custom override validator
- asset override resolver
- datasheet override merge service
- footer marker support

Frontend deliverables:
- third output mode: `Custom`
- custom override controls
- asset replacement workflow
- preview validation workflow
- custom PDF generation flow

Documentation deliverables:
- feature spec
- implementation plan
- override matrix
- example request payloads

## 3. Product Scope

Architecture target:
- all families already supported by official datasheet runtime

Delivery strategy:
1. build one generic custom overlay architecture
2. reuse official datasheet layout
3. ship safe V1 overrides first
4. grow override coverage later without destabilizing the core PDF

## 4. Proposed File Plan

## 4.1 API endpoints

Add:
- `api/endpoints/custom-datasheet-preview.php`
- `api/endpoints/custom-datasheet-pdf.php`

Update:
- [api/index.php](./index.php)
- [api/lib/family-registry.php](./lib/family-registry.php)

## 4.2 Custom backend modules

Add:
- `api/lib/custom-datasheet/request.php`
- `api/lib/custom-datasheet/validation.php`
- `api/lib/custom-datasheet/assets.php`
- `api/lib/custom-datasheet/overrides.php`
- `api/lib/custom-datasheet/preview.php`
- `api/lib/custom-datasheet/render.php`

Optional split if needed:
- `api/lib/custom-datasheet/normalizers.php`
- `api/lib/custom-datasheet/errors.php`
- `api/lib/custom-datasheet/markers.php`

Potential shared reuse:
- [api/lib/pdf-engine.php](./lib/pdf-engine.php)
- [api/lib/pdf-layout.php](./lib/pdf-layout.php)
- [api/lib/product-header.php](./lib/product-header.php)
- [api/lib/sections.php](./lib/sections.php)
- [api/lib/images.php](./lib/images.php)

## 4.3 Configurator files

Update:
- [configurator/configurator.html](../configurator/configurator.html)
- [configurator/script.js](../configurator/script.js)
- [configurator/locales/en.js](../configurator/locales/en.js)
- [configurator/locales/pt.js](../configurator/locales/pt.js)

Potential later additions:
- asset chooser modal
- saved custom preset panel
- custom diff summary panel

## 5. Recommended Architecture

Best architecture:

1. `base request`
2. `official datasheet data build`
3. `custom override validation`
4. `override merge`
5. `same PDF layout render`
6. `custom footer marker`

Avoid:
- duplicating the whole datasheet engine
- letting user JSON directly mutate the render data tree
- mixing custom behavior into the existing `datasheet` endpoint

## 6. Phase Plan

## Phase 0: Freeze contract and allowed fields

Goal:
- lock the feature shape before code

Tasks:
- finalize [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- finalize [CUSTOM_DATASHEET_OVERRIDE_MATRIX.md](./CUSTOM_DATASHEET_OVERRIDE_MATRIX.md)
- confirm V1 override whitelist
- confirm footer marker string
- confirm V1 asset-source rules

Verify:
- one stable request example exists
- every allowed override has a validation rule
- forbidden edits are explicitly documented

## Phase 1: Route and capability scaffolding

Goal:
- API recognizes custom datasheet feature before real override logic

Tasks:
- add `custom-datasheet-preview` route
- add `custom-datasheet-pdf` route
- extend family registry with custom fields:
  - `custom_datasheet_supported`
  - `custom_datasheet_runtime_implemented`
  - `custom_datasheet_allowed_fields`
- return honest unsupported errors where official datasheet runtime is not available

Verify:
- endpoints exist
- unsupported families fail clearly
- normal `datasheet` path unaffected

## Phase 2: Request normalization

Goal:
- canonical request shape exists before render logic

Tasks:
- normalize `base_request`
- normalize `custom.mode`
- normalize text overrides
- normalize asset overrides
- normalize section visibility
- normalize footer settings
- reject unknown override fields

Verify:
- valid request normalizes consistently
- invalid keys fail with `custom_datasheet_unsupported_field`
- empty custom payload behaves like no-op custom request

## Phase 3: Override validation

Goal:
- block dangerous or unreliable requests before render

Tasks:
- enforce exact base reference
- enforce datasheet-ready family
- validate text lengths
- validate text format as plain text
- validate allowed section visibility targets
- validate asset override source schema
- reject remote URLs in V1

Verify:
- oversized text fails clearly
- forbidden fields fail clearly
- invalid asset payloads fail clearly

## Phase 4: Asset override resolver

Goal:
- custom image references resolve through the same PDF-safe image pipeline

Tasks:
- resolve DAM asset IDs
- resolve local asset keys if supported
- convert resolved assets to PDF-safe paths using current image utilities
- reuse rasterization / remote-cache helpers from [images.php](./lib/images.php)

Verify:
- valid replacement image resolves to TCPDF-safe path
- missing asset fails before render
- SVG override paths rasterize or fallback correctly like official flow

## Phase 5: Base snapshot and override merge

Goal:
- build official datasheet data, then apply only safe changes

Tasks:
- expose or wrap official datasheet snapshot build from [pdf-engine.php](./lib/pdf-engine.php)
- create explicit field-level merge function
- allow text override merge only on whitelisted fields
- allow image override merge only on whitelisted section keys
- allow optional section removal only where approved
- preserve technical truth fields untouched

Recommended implementation shape:
- extract official snapshot build into reusable helper
- keep final official render path intact
- let custom runtime call same helper, then merge overrides

Verify:
- custom request with no overrides matches official snapshot
- custom request with approved overrides mutates only approved fields
- numeric technical data stays unchanged

## Phase 6: Custom render runtime

Goal:
- generate real PDF from custom data

Tasks:
- implement `custom-datasheet-pdf` render orchestrator
- call base snapshot builder
- apply overrides
- inject custom footer marker
- produce binary PDF download

Important:
- use same layout unless a future requirement proves otherwise
- do not fork layout templates in V1

Verify:
- generated custom PDF opens correctly
- footer includes marker
- official datasheet output remains unchanged

## Phase 7: Preview endpoint

Goal:
- give UI a safe pre-render validation path

Tasks:
- report resolved text overrides
- report resolved image overrides
- report hidden sections
- return warnings for weak requests
- return blocking errors before render

Recommended warnings:
- image override missing but section still visible
- all custom fields empty
- custom note very long

Verify:
- preview returns enough info to gate generate button
- UI can distinguish warning vs blocking error

## Phase 8: Configurator custom mode UI

Goal:
- expose feature cleanly in the second panel

Tasks:
- extend segmented control:
  - `Datasheet`
  - `Showcase`
  - `Custom`
- keep shared base product controls:
  - reference
  - description
  - language
  - company
- add custom-only panels:
  - text overrides
  - image overrides
  - optional sections
  - reset customizations
- wire preview and generate flows to custom endpoints
- block custom mode when base reference is invalid or unresolved

Verify:
- switching modes does not corrupt datasheet or showcase state
- custom mode requires exact product context
- generate button only enables after successful custom preview

## Phase 9: Asset chooser UX

Goal:
- make image replacement practical

Minimal V1 option:
- text input for DAM asset ID or controlled asset key

Better V1.5 option:
- asset picker modal from DAM explorer

Future option:
- integrated asset browser with thumbnails

Verify:
- team can pick replacement assets without guessing raw file paths

## Phase 10: Regression and QA

Goal:
- prove custom mode does not destabilize existing products

Regression checklist:
- official datasheet for known code remains identical
- showcase flow unchanged
- custom flow generates only when valid
- invalid custom asset fails clearly
- forbidden technical edits are blocked
- footer marker appears only in custom PDFs

Recommended gold samples:
- one downlight
- one barra
- one tubular
- one shelf or dynamic if supported

## 7. Suggested Backend API Shapes

## 7.1 Preview request

```json
{
  "base_request": {
    "referencia": "29012032291010100",
    "descricao": "Downlight 120 R 200",
    "idioma": "pt",
    "empresa": "0"
  },
  "custom": {
    "mode": "custom",
    "text_overrides": {
      "document_title": "Customer Edition",
      "header_copy": "Prepared for presentation."
    },
    "asset_overrides": {
      "header_image": {
        "source": "dam",
        "asset_id": "asset_123"
      }
    },
    "section_visibility": {
      "power_supply": false
    },
    "footer": {
      "marker": "customPDF"
    }
  }
}
```

## 7.2 Preview response

```json
{
  "ok": true,
  "data": {
    "normalized_request": {},
    "base_reference": "29012032291010100",
    "applied_fields": {
      "text": ["document_title", "header_copy"],
      "assets": ["header_image"],
      "hidden_sections": ["power_supply"]
    },
    "warnings": []
  }
}
```

## 8. Suggested UI Structure

## 8.1 Second panel mode switch

Use current segmented control pattern.

Tabs:
- `Datasheet`
- `Showcase`
- `Custom`

## 8.2 Custom panel content

Recommended order:

1. base reference
2. base description
3. custom text
4. custom images
5. optional sections
6. reset customizations
7. generate custom PDF

## 9. Suggested State Model in Frontend

Recommended new state object in [configurator/script.js](../configurator/script.js):

```js
const customDatasheetState = {
  textOverrides: {
    documentTitle: "",
    headerCopy: "",
    footerNote: ""
  },
  assetOverrides: {
    headerImage: null,
    drawingImage: null,
    finishImage: null
  },
  sectionVisibility: {
    fixing: true,
    powerSupply: true,
    connectionCable: true
  },
  preview: {
    pending: false,
    ok: false,
    signature: ""
  }
};
```

## 10. Suggested Error Codes

- `custom_datasheet_invalid_request`
- `custom_datasheet_invalid_override`
- `custom_datasheet_asset_not_found`
- `custom_datasheet_asset_unusable`
- `custom_datasheet_text_too_long`
- `custom_datasheet_unsupported_field`
- `custom_datasheet_section_forbidden`
- `custom_datasheet_base_datasheet_failed`

## 11. Main Risks

### Risk 1: official datasheet drift

If custom runtime mutates the official engine instead of layering on top, official PDFs may change.

Mitigation:
- separate endpoints
- separate preview
- explicit merge layer

### Risk 2: weak asset source model

If remote URLs are accepted too early, PDF reliability drops.

Mitigation:
- DAM/local controlled asset IDs only in V1

### Risk 3: too-broad text editing

If all text becomes editable immediately, PDFs stop being trustworthy and QA becomes expensive.

Mitigation:
- whitelist text overrides
- expand only after V1 proves stable

### Risk 4: UI overload

If custom panel tries to expose everything at once, users get confused.

Mitigation:
- ship small V1 field set
- hide advanced options until needed

## 12. Phase Acceptance Criteria

Feature is ready for V1 when:

- custom mode exists in configurator
- preview endpoint validates requests
- custom PDF endpoint returns binary PDF
- footer marker appears in custom PDFs
- at least three image override targets work
- at least three text override targets work
- optional section hiding works
- official datasheet flow remains unchanged
