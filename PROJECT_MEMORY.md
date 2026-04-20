# PROJECT_MEMORY

Last Updated: 2026-04-20
Status: Active canonical hub
Audience: AI agents + engineers resuming work

> This file summarizes the current truth of the repo.
> Narrow docs may contain deeper detail, older assumptions, or family-specific research.
> When sources conflict, follow the **Source of Truth Rules** in this file.

## Project Summary

`api_nexled` is the consolidation project for NexLed/Tecit product logic.

The repo currently has 3 main layers:

- `api/`: the live PHP API, deployed online, responsible for product data, code decoding, datasheet generation, explorer logic, and DAM endpoints
- `configurator/`: NexLed-branded frontend workspace with multiple internal pages that consume the API
- `appdatasheets/`: the old working legacy app, kept as runtime reference and parity baseline, not as the future architecture

There is also a growing documentation/research layer in `api/*.md`, root-level `.md` files, and `READING_DOCUMENTS/` that records datasheet layout findings, code masks, DAM design, and parity work.

Current mission:

- consolidate product/data logic into the API
- make new datasheet generation match old `appdatasheets/` behavior and official NexLed PDFs
- provide clean NexLed-branded internal tools on top of the live API

## Source of Truth Rules

When project sources disagree, use this order:

1. old runtime behavior in `appdatasheets/`
2. live DB truth
3. current API implementation
4. official/reference PDFs
5. narrow documentation files

Important interpretation rules:

- images do **not** validate code validity
- images validate **datasheet readiness** only
- code validity comes from `Luminos` identity truth plus family option logic from `tecit_referencias`
- official catalog/PDF masks are strong clues, but they are not automatically full runtime truth
- `appdatasheets/` is reference-only for behavior parity and should not be treated as the future architecture

## Current Architecture

### Runtime / Hosting

- API is deployed online on Railway
- main live API base used by the configurator is:
  - `https://apinexled-production.up.railway.app/api`
- frontend pages are served separately from the API runtime
- the API is online, but the project is not fully cloud-native yet

### Main Backend Subsystems

- Router:
  - `api/index.php`
- Runtime bootstrap/env/database wiring:
  - `api/bootstrap.php`
- Auth:
  - `api/auth-check.php`
- Datasheet/PDF engine:
  - `api/lib/pdf-engine.php`
  - `api/lib/pdf-layout.php`
  - supporting libs under `api/lib/`
- Code explorer:
  - `api/endpoints/code-explorer.php`
  - `api/lib/code-explorer.php`
- DAM:
  - `api/endpoints/dam.php` thin wrapper for the current link-model DAM backend
  - `api/endpoints/dam_link_model.php` with `tree`, `list`, `asset`, `create-folder`, `sync-folders`, `upload`, `product-assets`, `link`, `unlink`
  - `api/endpoints/assets.php` removed by DAM link-model cutover

### Databases

Primary product databases:

- `tecit_referencias`
  - families, sizes, colors, CRI, series, lenses, finishes, caps, options
- `tecit_lampadas`
  - `Luminos` identities, descriptions, luminotechnical data, characteristics
- `info_nexled_2024`
  - some extended product/support data used by parts of the datasheet pipeline

DAM database:

- `nexled_dam`
  - used by DAM folder/asset metadata when configured

### Asset / File Reality

The project still depends on bundled local support assets inside the repo:

- `appdatasheets/img/`
- `api/json/`

This means:

- API is live online
- but datasheet generation still depends on repo-bundled images/JSON support files
- DAM is not yet the complete source of truth for datasheet assets

### Auth / Env Model

- API requests require `X-API-Key`
- Railway runtime uses env-based DB/auth/cloudinary config
- local fallback still exists through `appdatasheets/config.php` for legacy/dev cases
- secrets must stay out of docs and tracked memory files
- current DAM/Cloudinary truth:
  - assets verified in cloud `dofqiejpw`
  - if Railway uses another cloud name, deterministic DAM URLs 404
  - `.env.railway` must stay aligned with the real DAM asset cloud

## Current Endpoints and Pages

### API Endpoints

| Endpoint | Purpose | Current State |
|---|---|---|
| `families` | List product families | Stable, now also returns runtime class + Luminos coverage metadata |
| `options` | Load family dropdown options | Stable, used by configurator/explorer |
| `reference` | Resolve description / Luminos combination validity | Stable, Luminos hard validation restored |
| `decode-reference` | Decode full Tecit code into segments | Stable, matches live normalized code shape |
| `datasheet` | Generate PDF datasheet | Live, functional, parity still incomplete |
| `health` | Service/database status | Live, used by frontend badges and checks |
| `svg-diagnostics` | Debug resolved SVG/PNG asset readiness | Debug/support endpoint |
| `code-explorer` | Explore generated code space by family | Live, valid-only mode useful; unsupported runtimes surfaced honestly |
| `assets` | Older coarse asset endpoint | Exists, transitional |
| `dam` | Newer DAM tree/list/upload-style endpoint | Exists, active design/workstream |

### Frontend Pages

| Page | Purpose | Current State |
|---|---|---|
| `configurator/index.html` | Workspace home page | Live internal landing page |
| `configurator/configurator.html` | Main product configurator | Live, connected to Railway API |
| `configurator/code-explorer.html` | Separate code explorer tool | Live, valid-only mode useful, invalid full-family mode limited |
| `configurator/dam.html` | DAM UI | Exists, still evolving against DAM backend contract |

## Reference Code Logic

### Normalized Current Code Shape

Current live/new system uses this normalized shape:

`[family 2][size 4][color 2][cri 1][series 1][lens 1][finish 2][cap 2][option 2]`

Interpretation:

- `family`: product family code
- `size`: product length/size block
- `color + cri + series`: the first 10 characters must resolve against `Luminos`
- `lens + finish + cap + option`: family option suffixes

### Important Logic Rule

The first 10 characters are the critical identity block.

That means:

- if the first 10 chars do not exist in `Luminos`, the code is not valid
- a valid code can still fail datasheet generation later if assets/data are missing

### Family-Specific Mask Warning

Catalog PDFs and family studies show that some families behave as if they have more constrained or family-specific masks.

Those masks are useful for:

- catalog explanation
- family documentation
- parity research

But they are not automatically stronger than old runtime + DB truth.

Relevant family/mask docs:

- [api/BARRAS_CODE_MASK_MATRIX.md](api/BARRAS_CODE_MASK_MATRIX.md)
- [api/BARRAS_FAMILY_11.md](api/BARRAS_FAMILY_11.md)
- [api/BARRAS_FAMILY_11_SUBLINES.md](api/BARRAS_FAMILY_11_SUBLINES.md)
- [api/BARRAS_FAMILY_32.md](api/BARRAS_FAMILY_32.md)
- [api/BARRAS_CODE_LOGIC_FINDINGS.md](api/BARRAS_CODE_LOGIC_FINDINGS.md)
- [api/SHELF_CODE_MASK_MATRIX.md](api/SHELF_CODE_MASK_MATRIX.md)
- [api/SHELF_FAMILY_49.md](api/SHELF_FAMILY_49.md)
- [api/DOWNLIGHTS_CODE_MASK_MATRIX.md](api/DOWNLIGHTS_CODE_MASK_MATRIX.md)
- [api/DOWNLIGHTS_FAMILY_29.md](api/DOWNLIGHTS_FAMILY_29.md)
- [api/DOWNLIGHTS_FAMILY_30.md](api/DOWNLIGHTS_FAMILY_30.md)
- [api/TUBULARES_CODE_MASK_MATRIX.md](api/TUBULARES_CODE_MASK_MATRIX.md)
- [api/TUBULARES_FAMILY_01.md](api/TUBULARES_FAMILY_01.md)
- [api/TUBULARES_FAMILY_05.md](api/TUBULARES_FAMILY_05.md)
- [api/T8_FAMILY_ONBOARDING_PLAN.md](api/T8_FAMILY_ONBOARDING_PLAN.md)
- [api/T8_ASSET_INVENTORY.md](api/T8_ASSET_INVENTORY.md)
- [api/PRODUCT_ONBOARDING_MEMORY.md](api/PRODUCT_ONBOARDING_MEMORY.md)
- [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md)

### Current Family Runtime Snapshot

Current code/runtime support is split into 3 states:

- datasheet runtime supported:
  - `01`, `04`, `05`, `06`, `07`, `09`, `10`, `11`, `29`, `30`, `31`, `32`, `40`, `48`, `49`, `55`, `56`, `58`, `59`, `60`
- family recognized by runtime class, but datasheet runtime not mapped yet:
  - tubular-like: `02`, `03`, `08`
  - spot-like: `12` to `21`
  - decor-like: `22`, `43`, `51`
  - dynamic/projector-like: `23`, `41`, `46`, `57`
  - highbay-like: `24`, `47`
  - downlight-like: `26`, `27`, `28`, `33`, `34`
  - luminaire/panel/canopy-like: `25`, `35`, `36`, `37`, `38`, `39`, `50`, `52`, `53`, `54`
- family selectable in dropdown, but with no current `Luminos` identities:
  - examples include `02`, `03`, `08`, `12` to `21`, `23` to `28`, `33`, `34`, `39`, `41`, `42`, `45`, `46`, `47`, `51`, `54`, `57`

Important:

- family recognized != PDF supported
- code-valid != datasheet-ready
- dropdown family list is broader than the set of families that currently have `Luminos` identities
- `/api/?endpoint=families` now returns `product_type`, `datasheet_runtime_supported`, `luminos_identity_count`, and `has_luminos_identities`
- T8 rollout is currently scoped to `01 = T8 AC` only
- `02 = T8 VC` and `03 = T8 CC` are legacy and out of current onboarding scope
- family `01` proven working refs now include:
  - `01018025111010100`
  - `01054425121010100`
  - `01054491111010100`
- plain Pink `01054481111010100` remains blocked honestly until `3014PINK` graph truth is recovered
- family `05` proven base T5 VC refs now include:
  - `05025725111010100`
  - `05025727111010100`
  - `05025732111010100`
- family `05` special-branch truth currently is:
  - Pink HE row proven in live `Luminos`
  - base ECO not proven
  - plain Pink not proven
- durable onboarding lessons now live in:
  - [api/PRODUCT_ONBOARDING_MEMORY.md](api/PRODUCT_ONBOARDING_MEMORY.md)

## Datasheet Pipeline

### High-Level Flow

1. frontend sends product state to `datasheet`
2. API validates/sanitizes input
3. reference is decoded into segments
4. code/product identity is resolved from DB
5. data is gathered from DB + JSON + image assets + form inputs
6. layout HTML is built
7. TCPDF renders final PDF

### Main Data Sources

- DB lookups:
  - `tecit_referencias`
  - `tecit_lampadas`
  - some `info_nexled_2024`
- JSON support files:
  - `api/json/`
- local image assets:
  - `appdatasheets/img/`
- live user/configurator selections:
  - language
  - company
  - fixing
  - cable/connector states
  - purpose
  - etc.

### Important Separation

- **Code-valid**
  - the code is allowed by `Luminos` + family options
- **Datasheet-ready**
  - the code is valid and the needed supporting assets/data exist

This separation is essential. Many debugging mistakes come from mixing these two concepts.

### EPREL / Central API Rule

Keep this split hard:

- Central API = truth
- EPREL = workflow/import client

Meaning:

- Central API should decide which full refs are real and ready
- EPREL should page through those rows and import them
- EPREL should not brute-force family combinations on its own

### Current Parity Gap

Current generator is still mostly section-based.

Legacy/official datasheets behave more like page-template programs.

So the big parity gap is not only visual. It is architectural:

- source parity
- validation parity
- page/program parity

Relevant parity docs:

- [api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md](api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [api/NEXT_STEPS_DATASHEET_PARITY.md](api/NEXT_STEPS_DATASHEET_PARITY.md)

## Major Work Already Done

### Backend / Security / Runtime

- API endpoints were rebuilt around a central router
- API key auth and env-driven runtime config were added
- Railway deployment was set up and the API now runs online
- CORS/auth/bootstrap/runtime issues were fixed for browser use

### Configurator

- standalone NexLed-branded configurator page exists
- configurator now uses Railway API, not localhost
- Tecit code decode/load flow exists
- Tecit code help modal and code logic guidance were added

### Code Validity

- decode-reference was normalized to current live code shape
- `Luminos` hard validation was restored
- invalid `Luminos` combinations now surface as true validation failures, not soft warnings

### PDF / Assets

- datasheet generation works online for many cases
- PDF asset handling now prefers source SVG again where available, to preserve original size/quality
- family `32` BT asset parity was patched:
  - connector inference from cap
  - correct hero image resolution
  - correct drawing resolution
  - correct finish image lookup
- family `60` is now recognized as `barra` in runtime
- configurator can inject decoded families that are missing from the loaded dropdown list
- Shelf (`49`) family is now a real datasheet runtime with strict asset blocking instead of fake fallback support
- Shelf header/drawing/finish/diagram lookup now checks DAM product assets when local legacy files are missing
- Tubular family `01` is now a real datasheet runtime with strict asset blocking
- Tubular family `01` product/finish asset lookup now checks DAM product assets when local legacy files are missing
- Tubular family `05` is now a real datasheet runtime with strict asset blocking
- Tubular family `05` product/finish asset lookup now checks DAM product assets when local legacy files are missing
- family `05` base T5 VC branch is now proven end-to-end with DAM-backed assets and working sample refs
- Barra family `31` is now a real datasheet runtime with strict asset/data blocking
- Barra family `40` is now a real datasheet runtime with strict asset/data blocking

### Explorer / DAM

- separate code explorer page exists
- explorer has a valid-only mode and an invalid-combo mode
- invalid full-family matrix mode currently does not scale for large families
- DAM backend link-model cutover is in place:
  - `dam.php` routes to `dam_link_model.php`
  - `assets.php` route/file removed
  - UI can upload, link, unlink, and inspect asset links
- DAM shared datasheet assets are imported:
  - icons, energy labels, temperatures, logos, power supplies
- DAM family pilot completed for family `11`:
  - packshots imported and linked by lens
  - finishes imported and linked by lens
  - technical drawings imported and linked with variant stems
  - diagrams + inverted diagrams imported and linked
  - mounting assets imported and linked
  - connector cable assets imported and linked with filename stems
- DAM UI page exists, but DAM is still an active workstream, not finished truth

## Current Known Gaps / Problems

- `api/README.md` and `api/PLAN.md` are useful as historical baseline, but they are partially outdated
- datasheet parity with official/legacy PDFs is still incomplete
- old configurator family-specific UI logic is not fully restored
- old missing-data validation sweep is not fully restored
- code explorer does not scale for full invalid-family matrix on large families
- DAM is not yet the complete source of truth for datasheet assets
- the repo contains many narrow research docs; they are useful, but they are not all canonical
- the API is live, but datasheet generation still depends on bundled repo files and legacy-style asset organization

## Active Workstreams

### 1. Datasheet Parity

- restore old validation behavior
- map visible fields to exact sources
- move from generic section builder toward family/page template parity

### 2. Family-by-Family Code Logic / Runtime Coverage

- document family masks and runtime truth per family
- avoid fake universal rules when family-specific behavior exists
- current documentation now covers:
  - Barra
  - Shelf
  - Downlights
  - Tubulares
- current runtime parity is strongest for:
  - `11`
  - `32`
  - `29`
  - `30`
  - `01`
  - `05`

### 3. API Runtime Parity vs Old App

- use old `appdatasheets/` as behavior baseline
- patch current API where legacy family behavior matters
- current family `32` BT patch is example of this workflow

### 4. Explorer Scalability

- current valid-only mode is useful
- current invalid full-family matrix explodes combinatorially on large families
- likely next design is narrower drill-down rather than brute-force full family matrix

### 5. EPREL Family Import

- EPREL needs real family import, not synthetic family search
- Central API already has reusable readiness logic, but not yet a clean ready-only family import contract
- next shared-logic goal is:
  - Central API exposes ready-only family rows
  - EPREL imports those rows page by page
### 6. DAM Future Work

- Phase 1 cutover is done locally:
  - schema/backend/UI link flow working
  - shared datasheet assets imported
  - family `11` packshot/finish pilot imported
  - family `11` drawings/diagrams/mounting imported
  - family `11` connectors imported
  - family `55` packshot/finish dedupe rollout imported
  - family `55` technical assets imported:
    - drawings `44`
    - diagrams `10`
    - inverted diagrams `10`
    - mounting `13`
    - connectors `15`
  - family `55` technical rollout reused existing DAM assets entirely (`92` reused, `0` new uploads)
  - DAM lens folder model expanded for downlight-style shared buckets:
    - `nexled/datasheet/packshots/clear`
    - `nexled/datasheet/packshots/frostc`
    - `nexled/datasheet/finishes/clear`
    - `nexled/datasheet/finishes/frostc`
  - family `29` flat downlight rollout imported:
    - packshots `6`
    - finishes `6`
    - drawings `6`
    - diagrams `4`
    - inverted diagrams `2`
  - family `29` technical rollout reused existing diagram assets partially (`2` reused, `10` uploaded)
  - family `30` flat downlight rollout imported:
    - packshots `8`
    - finishes `8`
    - drawings `8`
    - diagrams `4`
    - inverted diagrams `4`
  - family `30` technical rollout was corrected after detecting bad cross-family filename reuse in shared technical folders
  - technical importer now uploads family-prefixed filenames for family-specific roles:
    - drawings
    - diagrams
    - inverted diagrams
    - mounting
    - connectors
  - earlier roadmap families were reimported safely with prefixed technical filenames:
    - `11`
    - `29`
    - `30`
    - `55`
  - family `32` imported:
    - packshots `14`
    - finishes `2`
    - drawings `10`
    - diagrams `4`
    - mounting `5`
    - connectors `8`
  - DAM lens folder model expanded again for remaining datasheet families:
    - `nexled/datasheet/packshots/generic`
    - `nexled/datasheet/finishes/generic`
    - `nexled/datasheet/packshots/clear-2`
    - `nexled/datasheet/finishes/clear-2`
    - `nexled/datasheet/packshots/clear-4`
    - `nexled/datasheet/finishes/clear-4`
  - family `58` imported:
    - packshots `2`
    - finishes `2`
    - drawings `2`
    - diagrams `2`
    - inverted diagrams `2`
    - mounting `2`
  - family `48` imported through subtype-safe dynamic importer:
    - packshots `10` linked by subtype product_code
    - finishes `10` linked by subtype product_code
    - drawings `20` linked by subtype product_code
    - diagrams `24` linked by subtype product_code
    - inverted diagrams `24` linked by subtype product_code
  - roadmap family rollout is now complete for:
    - `11`
    - `29`
    - `30`
    - `32`
    - `48`
    - `55`
    - `58`
  - current DAM totals after full roadmap rollout:
    - assets `724`
    - links `638`
  - DAM UI auth/error state was fixed:
    - local dev auth override restored through `api/auth.php`
    - `configurator/dam.js` now shows real API/auth errors instead of fake endless "Loading folders..."
  - datasheet runtime/PDF cutover is now live for DAM roadmap scope:
    - rollout families `11`, `29`, `30`, `32`, `48`, `55`, `58` resolve product assets from DAM first and do not fall back to local `appdatasheets/img/`
    - shared datasheet assets now resolve from DAM first and do not fall back to local `appdatasheets/img/`:
      - logos
      - icons
      - energy labels
      - temperatures
      - power supplies
    - remote DAM assets are cached/rasterized for TCPDF through `api/lib/images.php`
  - smoke script added:
    - `scripts/smoke-dam-datasheets.ps1`
    - generates sample PDFs for families `11`, `29`, `30`, `32`, `48`, `55`, `58`
  - smoke results after DAM-first runtime cutover:
    - sample PDFs generated successfully for all 7 roadmap families
    - output files live under `output/pdf/`
    - shared assets like energy labels, logos, and power-supply imagery resolved from DAM during smoke
  - important runtime truth now:
    - DAM is authoritative for shared datasheet assets and for roadmap rollout families listed above
    - local `appdatasheets/img/` is still kept on disk, but runtime dependency remains only for non-rollout families/out-of-scope legacy cases
  - remaining validation gap:
    - this environment could generate PDFs, but it could not visually render/export pages because Poppler/ImageMagick/Ghostscript/MuPDF/PyMuPDF tooling was unavailable
    - manual visual PDF comparison is still needed before declaring full parity
  - family `49` Shelf rollout was attempted next and is blocked by missing asset source:
    - valid Shelf references decode correctly and resolve to real DB products
    - sample Shelf datasheet run fails with `Missing required data: product image`
    - `dam_asset_links` count for family `49` is `0`
    - no Shelf-like rows were found in `dam_assets`
    - no `appdatasheets/img/49` tree exists in this repo
    - sibling legacy app `C:\\xampp\\htdocs\\appDatasheets\\img` also has no `49` folder
    - result: Shelf is blocked by missing real image files, not by runtime logic
  - Batch 2 tubular families `01` and `05` were checked next:
    - valid references decode correctly and resolve to real DB products
    - family `05` still fits the old blocked-by-missing-assets pattern
    - family `01` now has first curated local runtime assets under `appdatasheets/img/01/...`
    - local sample `01018025111010100` now generates a real PDF successfully
    - one PDF-path bug was fixed during this work:
      - local SVG assets must be passed to TCPDF as absolute paths, not inline base64 blobs
    - `dam_asset_links` count for family `01` is still `0`
    - result: family `01` has one working seeded sample path, while `05` is still blocked by missing real assets
  - wider legacy asset inventory now looks conclusive:
    - `C:\\xampp\\htdocs\\appDatasheets\\img` contains only family folders `11`, `29`, `30`, `32`, `48`, `55`, `58` plus shared asset folders
    - candidate next families like `31`, `40`, and `60` also have no local image folders there
    - result: all remaining non-rollout families currently appear blocked by missing real source images on this machine

- next DAM step:
  - family rollout slice is complete for roadmap scope
  - next work is visual parity validation on generated PDFs and remaining non-rollout families
  - Batch 1 family `49` is deferred until real Shelf assets are recovered
  - family `05` base T5 VC no longer needs asset recovery for the proven branch
  - next T5 work is special-branch truth:
    - Pink HE candidate onboarding
    - ECO remains blocked until live `Luminos` proof exists
  - next active DAM rollout cannot proceed until an external source of real family images is recovered
  - do not delete `appdatasheets/img/` yet

## Recommended Next Steps

Ordered recommendation:

1. continue family-by-family parity work instead of broad generic rewrites
2. finish runtime coverage for documented families:
   - complete `49` Shelf asset/data intake and gold-sample compare
   - import/map real Shelf assets into local legacy tree or DAM family `49`
   - import/map real T8 assets into local legacy tree or DAM family `01`
   - import/map real T5 assets into local legacy tree or DAM family `05`
   - restore real bar size profiles and assets for families `31` and `40`
3. restore old missing-data validator behavior
4. build page-template parity starting from priority families
5. keep documenting only the families being actively patched
6. build a clean Central API truth endpoint for EPREL family import:
   - real rows only
   - ready rows only
   - paginated
   - no synthetic family matrix
6. redesign code explorer invalid mode around narrower slices/drill-down
7. continue DAM only after PDF/API parity priorities are stable

Best immediate engineering path remains:

- family-by-family parity
- restore old validations
- page-template rebuild
- explorer narrowing strategy

Primary roadmap doc:

- [api/NEXT_STEPS_DATASHEET_PARITY.md](api/NEXT_STEPS_DATASHEET_PARITY.md)

## Important Files to Read First

For a new AI session, start here:

1. `PROJECT_MEMORY.md`
2. [api/NEXT_STEPS_DATASHEET_PARITY.md](api/NEXT_STEPS_DATASHEET_PARITY.md)
3. [api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md](api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
4. [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md) for EPREL/import work
5. the family doc relevant to the current task
6. then the narrow implementation files for that subsystem

Best family docs to start with for current Barra work:

- [api/BARRAS_FAMILY_11.md](api/BARRAS_FAMILY_11.md)
- [api/BARRAS_FAMILY_11_SUBLINES.md](api/BARRAS_FAMILY_11_SUBLINES.md)
- [api/BARRAS_FAMILY_32.md](api/BARRAS_FAMILY_32.md)

Best non-Barra docs to start with for current expansion work:

- [api/SHELF_FAMILY_49.md](api/SHELF_FAMILY_49.md)
- [api/DOWNLIGHTS_FAMILY_29.md](api/DOWNLIGHTS_FAMILY_29.md)
- [api/DOWNLIGHTS_FAMILY_30.md](api/DOWNLIGHTS_FAMILY_30.md)
- [api/TUBULARES_FAMILY_01.md](api/TUBULARES_FAMILY_01.md)
- [api/TUBULARES_FAMILY_05.md](api/TUBULARES_FAMILY_05.md)

## Doc Map

### Canonical Context

- `PROJECT_MEMORY.md`
- [api/EPREL_SHARED_LOGIC.md](api/EPREL_SHARED_LOGIC.md)

### Active Planning / Active Current-State Docs

- [api/NEXT_STEPS_DATASHEET_PARITY.md](api/NEXT_STEPS_DATASHEET_PARITY.md)
- [api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md](api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [api/CODE_VALIDITY_EXPLORER_PLAN.md](api/CODE_VALIDITY_EXPLORER_PLAN.md)
- [EPREL_NEXT_AI_PROMPT.md](EPREL_NEXT_AI_PROMPT.md)
- [DAM_CUTOVER_CHECKLIST.md](DAM_CUTOVER_CHECKLIST.md)
- [DAM_IMPLEMENTATION_GUIDE.md](DAM_IMPLEMENTATION_GUIDE.md)
- [DAM_ROADMAP.md](DAM_ROADMAP.md)
- [api/DAM_API_CONTRACT.md](api/DAM_API_CONTRACT.md)
  - legacy DAM contract before link-model cutover
- [api/DAM_FOLDER_STRUCTURE.md](api/DAM_FOLDER_STRUCTURE.md)
  - legacy DAM folder/model spec before link-model cutover

### Family Logic Research

- [api/BARRAS_CODE_MASK_MATRIX.md](api/BARRAS_CODE_MASK_MATRIX.md)
- [api/BARRAS_CODE_LOGIC_FINDINGS.md](api/BARRAS_CODE_LOGIC_FINDINGS.md)
- [api/BARRAS_FAMILY_11.md](api/BARRAS_FAMILY_11.md)
- [api/BARRAS_FAMILY_11_SUBLINES.md](api/BARRAS_FAMILY_11_SUBLINES.md)
- [api/BARRAS_FAMILY_32.md](api/BARRAS_FAMILY_32.md)
- [api/BARRAS_FAMILY_31.md](api/BARRAS_FAMILY_31.md)
- [api/BARRAS_FAMILY_40.md](api/BARRAS_FAMILY_40.md)
- [api/BARRAS_FAMILY_55.md](api/BARRAS_FAMILY_55.md)
- [api/BARRAS_FAMILY_58.md](api/BARRAS_FAMILY_58.md)
- [api/BARRAS_FAMILY_60.md](api/BARRAS_FAMILY_60.md)
- [api/SHELF_CODE_LOGIC_FINDINGS.md](api/SHELF_CODE_LOGIC_FINDINGS.md)
- [api/SHELF_CODE_MASK_MATRIX.md](api/SHELF_CODE_MASK_MATRIX.md)
- [api/SHELF_FAMILY_49.md](api/SHELF_FAMILY_49.md)
- [api/DOWNLIGHTS_CODE_LOGIC_FINDINGS.md](api/DOWNLIGHTS_CODE_LOGIC_FINDINGS.md)
- [api/DOWNLIGHTS_CODE_MASK_MATRIX.md](api/DOWNLIGHTS_CODE_MASK_MATRIX.md)
- [api/DOWNLIGHTS_FAMILY_29.md](api/DOWNLIGHTS_FAMILY_29.md)
- [api/DOWNLIGHTS_FAMILY_30.md](api/DOWNLIGHTS_FAMILY_30.md)
- [api/TUBULARES_CODE_LOGIC_FINDINGS.md](api/TUBULARES_CODE_LOGIC_FINDINGS.md)
- [api/TUBULARES_CODE_MASK_MATRIX.md](api/TUBULARES_CODE_MASK_MATRIX.md)
- [api/TUBULARES_FAMILY_01.md](api/TUBULARES_FAMILY_01.md)
- [api/TUBULARES_FAMILY_05.md](api/TUBULARES_FAMILY_05.md)
- [api/ACESSORIOS_LOGIC_FINDINGS.md](api/ACESSORIOS_LOGIC_FINDINGS.md)

### Historical / Partial / Outdated Baselines

- [api/README.md](api/README.md)
  - historical baseline, not full current truth
- [api/PLAN.md](api/PLAN.md)
  - historical plan, partially outdated
- [api/PROMPT.md](api/PROMPT.md)
  - old continuation prompt, useful for origin context, not current full truth

### Frontend / UI-System Local Docs

- `configurator/LANGUAGE_PLAN.md`
- `configurator/DAM_FEATURES.md`
- `configurator/UI_SYSTEM/...`

These are useful for frontend/tooling context, but they are not the main repo memory source.

## Maintenance Rules

- after any major behavior change, update:
  - current architecture
  - major work done
  - known gaps
  - recommended next steps
- do not put secrets, live keys, or passwords in this file
- do not duplicate giant narrow specs unless summary is needed for orientation
- prefer summary + links for deep-dive areas
- keep this file as the canonical hub, not as a full copy of every narrow doc
- if a narrow doc becomes stale, mark it in the Doc Map rather than silently treating it as truth

## Practical Mental Model

If resuming work, think about the repo like this:

- `appdatasheets/` tells you how the old system behaved
- DBs tell you what product/code truth exists
- `api/` is the new execution engine
- `configurator/` is the internal UI surface on top of that engine
- current challenge is not “make something work”
- current challenge is “make new system behave like old truth while staying cleaner and more maintainable”
