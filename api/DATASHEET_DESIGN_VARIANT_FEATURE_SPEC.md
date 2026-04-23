# Datasheet Design Variant Feature Spec

Status: draft

Purpose: define feature that lets user choose between current datasheet design and new datasheet design without changing product truth, validation, or family support rules.

This document covers:
- product goal
- scope boundaries
- API contract
- configurator UI contract
- render architecture
- acceptance criteria

Related docs:
- [README.md](./README.md)
- [PLAN.md](./PLAN.md)
- [NEXT_STEPS_DATASHEET_PARITY.md](./NEXT_STEPS_DATASHEET_PARITY.md)
- [OFFICIAL_DATASHEET_LAYOUT_SPEC.md](./OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [CUSTOM_DATASHEET_FEATURE_SPEC.md](./CUSTOM_DATASHEET_FEATURE_SPEC.md)
- [SHOWCASE_PDF_FEATURE_SPEC.md](./SHOWCASE_PDF_FEATURE_SPEC.md)

## 1. Summary

Project currently has one exact-product datasheet render design.

User need:
- keep current design available
- add new design
- let user choose which design to export

Recommended product model:
- one datasheet product
- one exact Tecit reference
- one shared data snapshot
- two render variants

Recommended variant keys:
- `classic`
  - current design
- `modern`
  - new design

Recommended configurator labels:
- `Current`
- `New`

Important:
- choice is design choice, not data choice
- choice must not change business rules
- choice must not weaken current official datasheet flow

## 2. Current Runtime Reality

Current exact-product datasheet runtime already has clean separation between:
- request parsing
- data snapshot build
- PDF rendering

Current flow:
1. request enters `datasheet`
2. runtime resolves exact product and section data
3. layout builder assembles HTML
4. TCPDF renders final PDF

Relevant runtime files:
- [lib/pdf-engine.php](./lib/pdf-engine.php)
- [lib/pdf-layout.php](./lib/pdf-layout.php)
- [lib/product-header.php](./lib/product-header.php)
- [lib/sections.php](./lib/sections.php)
- [lib/images.php](./lib/images.php)

Important current constraint:
- render still depends on legacy `appdatasheets/tcpdf` header/footer class and CSS source

That means design variants are feasible, but header/footer and stylesheet loading may also need variant-aware branching if new design differs enough.

## 3. Goals

- Keep current datasheet design fully available.
- Add new datasheet design as opt-in export variant.
- Reuse same product truth, validation, and section fetchers.
- Keep exact same family support matrix in V1.
- Keep same data readiness checks in V1.
- Avoid duplicating whole datasheet engine.
- Make design choice explicit in UI and API.

## 4. Non-goals

- Replacing current datasheet design.
- Changing code validity rules.
- Changing datasheet readiness rules.
- Changing family support rules.
- Adding design variants to showcase PDFs in V1.
- Building full WYSIWYG layout editor.
- Letting each family pick completely unrelated design keys in V1.

## 5. Product Positioning

This feature is not new PDF product like `showcase` or `custom`.

It is:
- same exact-product datasheet flow
- same reference
- same technical truth
- different render presentation

So product split remains:
- `datasheet`
- `showcase`
- `custom`

Design variant is separate selector inside exact datasheet flow.

## 6. Core Product Model

Datasheet render output should become:

- `datasheet + classic`
- `datasheet + modern`

Not:
- separate `datasheet-v2` endpoint
- separate product-specific API tree

Why:
- keeps API simpler
- keeps default backward-compatible
- keeps QA diff easy
- avoids data-logic fork

## 7. Variant Naming Rule

Recommended API values:
- `classic`
- `modern`

Recommended defaults:
- if field omitted: use `classic`
- if unsupported value supplied: return explicit `unsupported_design_variant` error

Reason:
- backward compatibility for old clients
- clear validation for new clients

## 8. Data Truth Rule

Design variant must not affect:
- reference validation
- product lookup
- family runtime mapping
- datasheet readiness checks
- technical numeric values
- asset resolution logic except where layout needs different placement

Design variant may affect:
- section order
- page rhythm
- typography
- spacing
- image emphasis
- optional presentation wrappers
- footer/header arrangement

## 9. V1 Scope

V1 exact scope:
- `POST /api/?endpoint=datasheet`
- `GET /api/?endpoint=file-datasheet`
- configurator official datasheet mode

Deferred from V1:
- `custom-datasheet-preview`
- `custom-datasheet-pdf`
- `showcase-preview`
- `showcase-pdf`

Reason:
- exact datasheet path is smallest safe slice
- custom path already has its own validation contract
- showcase is separate grouped-PDF system

## 10. Canonical API Contract

## 10.1 Datasheet request

Recommended optional field:
- `design_variant`

Example:

```json
{
  "referencia": "11037581110010100",
  "descricao": "LED Barra 24V 10 WW273 HE Clear",
  "idioma": "pt",
  "empresa": "0",
  "lente": "Clear",
  "acabamento": "Alu",
  "opcao": "00",
  "conectorcabo": "0",
  "tipocabo": "branco",
  "tampa": "0",
  "vedante": "5",
  "acrescimo": "0",
  "ip": "0",
  "fixacao": "0",
  "fonte": "0",
  "caboligacao": "0",
  "conectorligacao": "0",
  "tamanhocaboligacao": "0",
  "finalidade": "0",
  "design_variant": "modern"
}
```

## 10.2 File datasheet request

Recommended optional query param:
- `design_variant`

Example:

```text
GET /api/?endpoint=file-datasheet&reference=11037581110010100&design_variant=modern
```

## 10.3 Error shape

Recommended error:

```json
{
  "error": "Unsupported datasheet design variant",
  "error_code": "unsupported_design_variant",
  "design_variant": "future-x"
}
```

## 11. Configurator UI Contract

Current configurator already has output-mode switch:
- `Official`
- `Showcase`
- `Custom`

New selector should be separate from output mode.

Recommended V1 UI behavior:
- show design selector only when output mode is `Official`
- hide it for `Showcase`
- hide it for `Custom` in V1

Recommended selector labels:
- `Current`
- `New`

Recommended helper text:
- `Choose datasheet design. Product data stays same.`

## 12. Render Architecture Rule

Best architecture:
1. build one official render context
2. store selected `design_variant` in context
3. route to variant-specific layout builder
4. load variant-specific CSS if needed
5. render via same PDF engine

Avoid:
- duplicate full data-fetch pipeline
- new endpoint per design
- generic deep merge of unrelated layout config

## 13. Recommended Backend Shape

Recommended shape inside render layer:

- `buildPdfLayoutClassic(array $data): string`
- `buildPdfLayoutModern(array $data): string`
- `buildPdfLayoutForVariant(array $data, string $designVariant): string`

Recommended CSS shape:
- current CSS becomes `classic` source
- new CSS becomes `modern` source

Recommended context shape:

```php
[
    "data" => [...],
    "reference" => "...",
    "product_id" => "...",
    "description" => "...",
    "document_title" => "...",
    "company" => "...",
    "lang" => "pt",
    "design_variant" => "classic",
]
```

## 14. Header/Footer Rule

Current runtime uses legacy `NEXLEDPDF` header/footer behavior.

V1 rule:
- if modern design can live inside current header/footer shell, keep same PDF class
- if modern design needs different footer/header arrangement, add variant-aware branching in runtime

Do not:
- break classic footer behavior
- remove current legal footer content without explicit approval

## 15. Family Support Rule

V1 support inherits current exact datasheet support only.

That means:
- if family works in current `datasheet`, it may export in `classic`
- if same family layout assets are compatible, it may export in `modern`

Conservative V1 rollout option:
- gate `modern` by family allowlist first
- keep `classic` for all supported families

If chosen, API should return honest error for unsupported families:
- `design_variant_not_supported_for_family`

## 16. QA Rule

Classic design must remain baseline.

Meaning:
- omitted `design_variant` must behave exactly like today
- `design_variant=classic` must behave exactly like today

Modern design must be compared against approved samples, but classic must be regression-checked against current outputs.

## 17. Acceptance Criteria

Feature is ready for V1 when:

- current datasheet still exports with no request changes
- explicit `design_variant=classic` matches current behavior
- explicit `design_variant=modern` produces valid PDF
- configurator exposes design selector in official datasheet mode
- file-datasheet endpoint accepts same variant choice
- unsupported variant values fail clearly
- showcase mode remains unchanged
- custom mode remains unchanged in V1

## 18. Deferred Follow-up

After V1 exact datasheet rollout, next safe extensions are:
- allow same `design_variant` in custom datasheet endpoints
- optionally allow same selector in custom UI mode
- later decide whether showcase PDFs need their own variant model

Important:
- custom datasheet validator currently rejects unknown fields
- if design variant is added there later, that contract must be updated intentionally

## 19. Recommended Decision

Best V1 decision:
- API value: `classic` and `modern`
- default: `classic`
- scope: `datasheet` and `file-datasheet` only
- custom/showcase: defer

This gives smallest safe change with clear user value and low regression risk.
