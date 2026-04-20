# T8 Family Onboarding Plan

Purpose:
- define safest path to make T8 products configurator-ready and datasheet-ready
- avoid fake data, fake runtime support, and guesswork
- separate code-valid work from asset-readiness work

Scope:
- T8 cluster only
- family `01` = `T8 AC`
- family `02` = `T8 VC`
- family `03` = `T8 CC`

## Assumptions

Current best assumptions:

1. `new_data_img/T8` contains real usable product assets, but may include duplicates and legacy variants
2. assets alone do **not** make a family valid
3. `Luminos` + family option tables determine code validity
4. local/legacy assets or DAM assets determine datasheet readiness
5. missing official PDF documentation is **not** a blocker for functional support

## Truth Order

Use this order when sources disagree:

1. old runtime behavior
2. DB truth
3. current API/runtime
4. catalog/reference PDFs
5. new incoming asset folder naming

## Current Known State

### Family coverage

- `01` already has real `Luminos` identities and already enters tubular datasheet runtime
- `02` currently shows no `Luminos` identities
- `03` currently shows no `Luminos` identities

So:

- `01` can become fully working now
- `02` and `03` may still be selectable in dropdown, but cannot be treated as fully valid until DB truth exists

### Asset coverage

`new_data_img/T8` appears to include:

- product images
- finish images
- technical drawings
- lens diagrams
- pink variant assets
- old and 2025 variants

Current repo does **not** have a visible `appdatasheets/img/01` tree.

Meaning:

- T8 runtime support for `01` exists
- T8 asset wiring still needs to be built

## Success Criteria

Family onboarding is done only when all are true:

1. family selectable in configurator
2. options load correctly
3. full code validates against `Luminos`
4. datasheet runtime exists
5. required assets resolve from real files
6. PDF blocks honestly when required data is missing
7. one gold sample renders without fake data

## Work Plan

### Phase 1 - DB Truth Audit

Goal:
- prove exactly what `01/02/03` mean in current DB

Tasks:
- verify `Familias` rows for `01/02/03`
- verify `Luminos` counts for each family
- collect sample `Luminos.ref` and `Luminos.ID`
- verify `options` output for each family

Expected result:
- `01` confirmed code-valid family
- `02/03` either confirmed valid or explicitly marked DB-empty

Verification:
- sample valid refs exist for each supported family
- if no `Luminos`, family must not be treated as code-valid

### Phase 2 - Asset Inventory

Goal:
- turn `new_data_img/T8` into a clean source list for onboarding

Tasks:
- inventory files by section:
  - product image
  - finish image
  - technical drawing
  - lens diagram
  - special variants like pink
- identify duplicates
- identify likely obsolete variants
- normalize candidate target names

Recommended output:
- `api/T8_ASSET_INVENTORY.md`

Verification:
- every required section has at least one candidate real asset

### Phase 3 - Family 01 Asset Mapping

Goal:
- make `01` datasheet-ready using real T8 assets

Tasks:
- map product images
- map finish images
- map technical drawings
- map lens diagrams
- patch tubular lookup logic only where naming mismatch requires it

Preferred order:
1. local legacy path mapping
2. DAM fallback mapping

Rule:
- minimum code only
- no speculative asset abstraction

Verification:
- one real `01` code resolves all required assets

### Phase 4 - Strict Validation

Goal:
- keep T8 truthful

Required checks:
- product image
- technical drawing
- finish image
- color graph
- lens diagram when lens != `0`

Behavior:
- if required asset missing -> block PDF
- no fake values
- no fake image placeholders for technical sections

Verification:
- incomplete T8 code returns honest `422`
- complete T8 code renders PDF

### Phase 5 - Configurator Readiness

Goal:
- make `01` usable end-to-end in frontend

Tasks:
- verify family appears correctly
- verify options load
- verify decode-reference fills form
- verify generate flow only succeeds when datasheet-ready

For `02/03`:
- if still no `Luminos`, show truthful unsupported/not-valid state
- do not fake support from asset presence alone

Verification:
- `01` works end-to-end
- `02/03` status is explicit and honest

### Phase 6 - Gold Sample

Goal:
- lock one T8 sample as baseline

Tasks:
- choose one real `01` reference
- generate new PDF
- compare with old app where possible
- freeze as T8 gold sample

Verification:
- same product meaning
- same core sections
- no invented data

## Recommended Immediate Execution

Best next steps:

1. audit DB truth for `01/02/03`
2. inventory `new_data_img/T8`
3. build `T8_ASSET_INVENTORY.md`
4. patch `01` only first

## Important Warnings

Do not:

- assume all T8 families are valid because T8 assets exist
- enable `02/03` runtime without `Luminos` truth
- import random duplicate assets without mapping
- generate PDFs with fake technical data

## Decision Rule

If family has:

- `Luminos` identities + options + runtime + assets -> fully supported
- `Luminos` identities + runtime but missing assets -> supported but blocked honest
- no `Luminos` identities -> selectable only, not code-valid yet
