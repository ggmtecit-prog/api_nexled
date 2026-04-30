# Showcase PDF Implementation Plan

Status: draft

Purpose: phased implementation plan for the new showcase PDF feature across API and configurator.

Related spec:
- [SHOWCASE_PDF_FEATURE_SPEC.md](./SHOWCASE_PDF_FEATURE_SPEC.md)

## 1. Implementation Principles

- Do not change current `datasheet` behavior.
- Build showcase PDF as a separate product path.
- Reuse truth and fetchers from the current API where safe.
- Do not fake variant combinations.
- Implement renderer groups incrementally, but design the architecture for all families now.

## 2. Expected Deliverables

Backend deliverables:
- preview endpoint
- PDF endpoint
- normalized request parser
- variant query service
- family capability registry
- shared showcase data assembler
- family-group renderers

Frontend deliverables:
- export type switch
- showcase controls
- preview workflow
- showcase PDF generation flow
- optional advanced shorthand pattern input

Documentation deliverables:
- feature spec
- implementation plan
- family capability matrix
- sample request payloads per renderer group

## 3. Rollout Strategy

Architecture target:
- all families

Delivery strategy:
1. design once for all families
2. ship renderer groups in a safe order
3. keep unsupported groups blocked honestly until mapped

Recommended renderer order:
1. `downlight`
2. `barra`
3. `tubular`
4. `shelf`
5. `dynamic`
6. `spot`
7. `decor`
8. `highbay`
9. `luminaire`
10. `panel`
11. `canopy`

Reason:
- downlight already has a strong official showcase-style sample
- barra has more option and accessory complexity
- tubular and shelf can reuse some simpler grouped logic
- dynamic likely needs the most family-specific template work

## 4. Proposed File Plan

## 4.1 API endpoints

Add:
- `api/endpoints/showcase-preview.php`
- `api/endpoints/showcase-pdf.php`

Update:
- [api/index.php](./index.php)

## 4.2 Showcase backend modules

Add:
- `api/lib/showcase/request.php`
- `api/lib/showcase/pattern.php`
- `api/lib/showcase/preview.php`
- `api/lib/showcase/query.php`
- `api/lib/showcase/assembler.php`
- `api/lib/showcase/sections.php`
- `api/lib/showcase/render.php`
- `api/lib/showcase/renderers/downlight.php`
- `api/lib/showcase/renderers/barra.php`
- `api/lib/showcase/renderers/tubular.php`
- `api/lib/showcase/renderers/shelf.php`
- `api/lib/showcase/renderers/dynamic.php`
- `api/lib/showcase/renderers/spot.php`
- `api/lib/showcase/renderers/decor.php`
- `api/lib/showcase/renderers/highbay.php`
- `api/lib/showcase/renderers/luminaire.php`
- `api/lib/showcase/renderers/panel.php`
- `api/lib/showcase/renderers/canopy.php`

Update:
- [api/lib/family-registry.php](./lib/family-registry.php)

Potential shared reuse:
- [api/lib/reference-decoder.php](./lib/reference-decoder.php)
- [api/lib/code-explorer.php](./lib/code-explorer.php)
- [api/lib/sections.php](./lib/sections.php)
- [api/lib/images.php](./lib/images.php)

## 4.3 Configurator files

Update:
- [configurator/configurator.html](../configurator/configurator.html)
- [configurator/script.js](../configurator/script.js)
- `configurator/locales/en.js`
- `configurator/locales/pt.js`

Optional later:
- dedicated showcase help modal
- advanced pattern builder UI

## 5. Phase Plan

## Phase 0: Freeze contract and sample set

Goal:
- no code yet beyond scaffolding decisions

Tasks:
- finalize [SHOWCASE_PDF_FEATURE_SPEC.md](./SHOWCASE_PDF_FEATURE_SPEC.md)
- choose one gold showcase sample per renderer group
- define supported section names and segment names
- define initial backend limits

Verify:
- one stable canonical request example per renderer group exists
- family renderer names are final

## Phase 1: Routing and registry scaffolding

Goal:
- API recognizes showcase feature without rendering logic yet

Tasks:
- add `showcase-preview` route
- add `showcase-pdf` route
- extend family registry with showcase fields:
  - `showcase_supported`
  - `showcase_renderer`
  - `showcase_sections`
  - `showcase_expandable_segments`
  - `showcase_defaults`
- return clear unsupported errors

Verify:
- endpoints respond with structured JSON stub or explicit unsupported message
- no impact on current `datasheet`, `reference`, or `decode-reference`

## Phase 2: Request normalization and preview

Goal:
- normalize user input before any render logic

Tasks:
- create request validator
- create pattern parser
- normalize:
  - family
  - locked segments
  - expanded segments
  - sections
  - filters
- reject conflicts between shorthand and explicit fields
- implement preview endpoint with:
  - normalized request
  - family info
  - variant count
  - estimated pages
  - warnings

Verify:
- preview returns correct shape
- invalid requests fail with specific error codes
- shorthand pattern `29012032291XXYYZZ` normalizes correctly

## Phase 3: Variant query and readiness filtering

Goal:
- query real valid showcase variants

Tasks:
- build variant query service from real data truth
- use Luminos identities first
- apply suffix combinations from references DB
- integrate readiness filtering, preferably reusing logic from `code-explorer`
- add stable sort logic

Verify:
- no impossible combinations appear
- preview counts stay stable across repeated calls
- `datasheet_ready_only` removes blocked variants

## Phase 4: Shared section assembly

Goal:
- produce renderer-ready section data, independent of final HTML layout

Tasks:
- create common DTOs for:
  - overview
  - luminotechnical rows
  - spectra groups
  - drawing groups
  - lens diagram groups
  - finish groups
  - option code legends
  - accessories
- dedupe shared assets:
  - same graph asset
  - same drawing asset
  - same finish asset
- define stable grouping labels

Verify:
- assembler output can be inspected without generating PDF
- identical assets are grouped rather than repeated blindly

## Phase 5: Renderer implementation by family group

Goal:
- build family-aware page templates

### Phase 5.1 Downlight renderer

Tasks:
- implement downlight template
- support:
  - overview
  - luminotechnical matrix
  - spectra
  - technical drawings
  - lens diagrams
  - option code legend
- match official catalog rhythm where practical

Verify:
- sample downlight showcase request generates correct page grouping

### Phase 5.2 Barra renderer

Tasks:
- implement barra template
- support stronger option-code and accessory coverage
- support fixing, power supply, and connection cable catalog sections where relevant

Verify:
- bar sample requests render grouped option and accessory pages without breaking current datasheet logic

### Phase 5.3 Tubular renderer

Tasks:
- implement tubular template
- support simpler grouped spectra and drawing flows

### Phase 5.4 Shelf renderer

Tasks:
- implement shelf template
- support family-specific visual grouping rules

### Phase 5.5 Dynamic renderer

Tasks:
- implement dynamic template
- support subtype-aware grouping where needed

### Phase 5.6 Remaining product-type renderers

Tasks:
- implement `spot`
- implement `decor`
- implement `highbay`
- implement `luminaire`
- implement `panel`
- implement `canopy`
- if any group is still under-specified, keep it blocked explicitly in registry until a mapped template exists

Verify for Phase 5 overall:
- each renderer group has at least one approved sample output
- unsupported sections fail clearly instead of silently disappearing

## Phase 6: PDF endpoint and download behavior

Goal:
- render final binary PDF from normalized showcase request

Tasks:
- wire renderer selection by family registry
- build output filename
- enforce hard page and variant limits
- return JSON errors on render failure

Verify:
- endpoint returns binary PDF on success
- endpoint returns JSON on validation or render failure

## Phase 7: Configurator showcase mode

Goal:
- expose showcase export in the frontend without disturbing current technical export

Tasks:
- add export type switch:
  - `Technical Datasheet`
  - `Showcase PDF`
- add showcase-specific controls:
  - base reference or family scope
  - locked segment controls
  - expanded segment toggles
  - section toggles
  - preview summary
  - advanced shorthand pattern field
- call `showcase-preview` before `showcase-pdf`
- show warnings and request size in UI

Verify:
- current datasheet flow still works unchanged
- showcase controls build canonical request correctly
- user can see variant count before generating PDF

## Phase 8: QA and rollout hardening

Goal:
- make feature safe to ship incrementally

Tasks:
- keep one regression sample for current datasheet flow per major family group
- keep one showcase sample request per renderer group
- verify:
  - no fake references leak into normal decode flow
  - unsupported families return explicit errors
  - estimate and final render stay reasonably aligned
- document known limitations per renderer group

Verify:
- no regression in current configurator PDF downloads
- showcase preview and render stay consistent

## 6. Preview Endpoint Recommendation

This endpoint is strongly recommended, not optional in practice.

Reason:
- showcase requests can explode in size
- frontend needs count and page estimate before render
- preview is the right place to normalize shorthand patterns

Preview should return:
- normalized request
- supported sections for the family
- filtered section list
- variant count
- estimated pages
- warnings

## 7. Pattern Feature Plan

The shorthand code pattern is included in scope, but not as Phase 1 render-critical work.

Recommended order:
1. build canonical JSON request path first
2. make preview and render work from normalized JSON
3. add shorthand parser as thin normalization layer

Reason:
- parser alone is not the feature
- parser without normalized backend model creates technical debt fast

Initial supported shorthand example:
- `29012032291XXYYZZ`

Out of scope for initial parser:
- generic early-segment wildcards
- mixed shorthand and partial hand-edited payloads
- treating shorthand as a real reference in current decode endpoint

## 8. Risks

## Risk 1: Too much generic logic

Problem:
- trying to force one renderer for all family groups

Mitigation:
- use shared section DTOs
- use family-group renderers

## Risk 2: Invalid variant explosion

Problem:
- too many combinations

Mitigation:
- Luminos-first truth
- readiness filters
- hard limits
- preview endpoint

## Risk 3: Datasheet regression

Problem:
- showcase work accidentally changes exact datasheet flow

Mitigation:
- separate endpoints
- separate runtime tree
- regression checks on current datasheet paths

## Risk 4: UI confusion

Problem:
- users may not understand the difference between technical and showcase PDF

Mitigation:
- explicit export mode switch
- separate labels and help text

## 9. Acceptance Criteria by Milestone

Phase 2 complete when:
- preview accepts canonical JSON
- preview rejects invalid requests clearly
- shorthand parser normalizes known examples

Phase 5 complete when:
- each shipped renderer group produces one approved sample PDF

Phase 7 complete when:
- configurator can generate both technical and showcase PDFs from separate flows

Feature complete when:
- all supported family groups have a mapped showcase renderer
- preview and render contracts are stable
- current datasheet flow remains unchanged

## 10. Immediate Next Steps

When implementation starts, do this order:
1. extend family registry with showcase metadata
2. add endpoint routing stubs
3. implement request normalization
4. implement preview
5. implement downlight renderer first
6. add configurator showcase mode after backend preview is stable
