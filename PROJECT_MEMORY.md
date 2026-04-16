# PROJECT_MEMORY

Last Updated: 2026-04-16
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
  - `api/endpoints/assets.php` for older asset flow
  - `api/endpoints/dam.php` for newer DAM tree/list/upload flow

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

## Current Endpoints and Pages

### API Endpoints

| Endpoint | Purpose | Current State |
|---|---|---|
| `families` | List product families | Stable, used by frontend |
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

### Current Family Runtime Snapshot

Current code/runtime support is split into 3 states:

- datasheet runtime supported:
  - `11`, `32`, `49`, `55`, `58`, `60`, `29`, `30`, `48`
- family recognized, but datasheet runtime not mapped yet:
  - `01`, `05`
- family documented/researched, but not yet mapped in live runtime:
  - `31`, `40`

Important:

- family recognized != PDF supported
- code-valid != datasheet-ready

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
- Tubular (`01`, `05`) families are recognized by API/code explorer, but datasheet runtime still stops honestly instead of inventing data

### Explorer / DAM

- separate code explorer page exists
- explorer has a valid-only mode and an invalid-combo mode
- invalid full-family matrix mode currently does not scale for large families
- DAM backend and contract docs exist
- DAM UI page exists, but DAM is still an active workstream, not finished truth

## Current Known Gaps / Problems

- `api/README.md` and `api/PLAN.md` are useful as historical baseline, but they are partially outdated
- datasheet parity with official/legacy PDFs is still incomplete
- old configurator family-specific UI logic is not fully restored
- old missing-data validation sweep is not fully restored
- code explorer does not scale for full invalid-family matrix on large families
- some documented families are still code-recognized only, with no datasheet runtime yet:
  - `01`, `05`
- some researched families still are not mapped in live runtime:
  - `31`, `40`
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

### 3. API Runtime Parity vs Old App

- use old `appdatasheets/` as behavior baseline
- patch current API where legacy family behavior matters
- current family `32` BT patch is example of this workflow

### 4. Explorer Scalability

- current valid-only mode is useful
- current invalid full-family matrix explodes combinatorially on large families
- likely next design is narrower drill-down rather than brute-force full family matrix

### 5. DAM Future Work

- align `dam.html` with `dam` endpoint contract
- finalize folder/asset model
- make DAM authoritative where appropriate without breaking current datasheet pipeline

## Recommended Next Steps

Ordered recommendation:

1. continue family-by-family parity work instead of broad generic rewrites
2. finish runtime coverage for documented families:
   - complete `49` Shelf asset/data intake and gold-sample compare
   - import/map real Shelf assets into local legacy tree or DAM family `49`
   - `01`
   - `05`
3. restore old missing-data validator behavior
4. build page-template parity starting from priority families
5. keep documenting only the families being actively patched
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
4. the family doc relevant to the current task
5. then the narrow implementation files for that subsystem

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

### Active Planning / Active Current-State Docs

- [api/NEXT_STEPS_DATASHEET_PARITY.md](api/NEXT_STEPS_DATASHEET_PARITY.md)
- [api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md](api/OFFICIAL_DATASHEET_LAYOUT_SPEC.md)
- [api/CODE_VALIDITY_EXPLORER_PLAN.md](api/CODE_VALIDITY_EXPLORER_PLAN.md)
- [api/DAM_API_CONTRACT.md](api/DAM_API_CONTRACT.md)
- [api/DAM_FOLDER_STRUCTURE.md](api/DAM_FOLDER_STRUCTURE.md)

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
