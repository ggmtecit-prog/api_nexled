# DAM Cutover Checklist

Last Updated: 2026-04-16
Status: Planning baseline before Phase 1 rewrite

This file defines safe cutover order for DAM restructure described in:

- `DAM_IMPLEMENTATION_GUIDE.md` for exact implementation target
- `DAM_ROADMAP.md` for phase order and rationale

Use this file before touching DB schema or rewriting `api/endpoints/dam.php`.

## Goal

Replace old DAM model with new link-based model without breaking:

- current DAM UI
- direct DAM SQL fallbacks used by datasheet runtime
- router/docs that still point at legacy DAM endpoints

## Current Truth

Repo still uses old DAM shape.

Old-model core files:

- `api/endpoints/dam.php`
- `api/index.php`
- `api/endpoints/assets.php`
- `api/sql/dam_schema.sql`
- `api/DAM_API_CONTRACT.md`
- `api/DAM_FOLDER_STRUCTURE.md`
- `configurator/DAM_FEATURES.md`

Root implementation target:

- `DAM_IMPLEMENTATION_GUIDE.md`
- `DAM_ROADMAP.md`

## Do Not Do First

Do **not** run DB migration first.

Why:

- old `dam.php` still writes old columns on `dam_assets`
- `api/lib/images.php` still queries old `dam_assets` columns directly
- DAM UI still assumes old root tree and old copy/policy
- checked-in SQL schema still creates old tables/columns

DB-first migration would leave app in mixed state:

- backend writes fail
- PDF fallback queries fail
- DAM UI becomes misleading even if tree/list still respond

## Old-Schema Dependencies To Remove Or Adapt

### 1. Backend core cutover group

These must move together in one backend slice:

- `api/endpoints/dam.php`
  - remove old `scope`/`kind` model
  - remove `resolve-target`
  - remove `prune-folders`
  - add `product-assets`
  - add `link`
  - add `unlink`
  - simplify upload/list/get/delete logic to new schema
- `api/index.php`
  - remove `assets` route
  - update route comments
- `api/endpoints/assets.php`
  - delete after `dam.php` replacement is live
- `api/sql/dam_schema.sql`
  - replace old schema with guide SQL shape
  - add `dam_asset_links`
  - replace old folder seed tree

### 2. Runtime consumer cutover group

These are not part of DAM UI, but they already depend on old DAM DB shape:

- `api/lib/images.php`
  - current fallback query reads `family_code`, `product_slug`, and old asset rows
  - must be rewritten to new link model before or with DB cutover
  - this is current highest-risk hidden dependency outside `dam.php`

### 3. Frontend DAM UI alignment group

These do not block backend rewrite by themselves, but must be aligned right after backend cutover:

- `configurator/dam.js`
  - default root is still `nexled/00_brand`
  - asset details panel still reads `asset_folder`
  - UI still assumes old top-level tree labels/shape
  - upload flow already sends `folder_id`, which is compatible with new plan
- `configurator/dam.html`
  - folder policy copy still describes old active branches
  - current copy still says keep `00_brand`, `10_products`, `60_configurator`
- `configurator/locales/en.js`
- `configurator/locales/pt.js`
  - likely need copy updates after backend/UI model changes

### 4. Stale docs after cutover

These describe old DAM contract and should not drive implementation:

- `api/DAM_API_CONTRACT.md`
- `api/DAM_FOLDER_STRUCTURE.md`
- `configurator/DAM_FEATURES.md`

Keep for history only until rewritten.

## New Source Of Truth

For Phase 1 cutover, use this order:

1. `PROJECT_MEMORY.md`
2. `DAM_CUTOVER_CHECKLIST.md`
3. `DAM_IMPLEMENTATION_GUIDE.md`
4. `DAM_ROADMAP.md`

Interpretation:

- `DAM_IMPLEMENTATION_GUIDE.md` = exact implementation target
- `DAM_ROADMAP.md` = phase rationale and what not to touch yet
- old `api/DAM_*` docs = legacy contract, not implementation authority

## Safe Execution Order

### Step 1. Planning lock

- mark guide/checklist as current DAM cutover truth
- treat old `api/DAM_*` docs as stale until rewritten

### Step 2. Backend cutover

Single coherent change set:

- replace `api/sql/dam_schema.sql`
- rewrite `api/endpoints/dam.php`
- update `api/index.php`
- delete `api/endpoints/assets.php`

### Step 3. Runtime consumer patch

- rewrite `api/lib/images.php` DAM fallback to new link query model
- verify no other direct SQL readers still expect old DAM columns

### Step 4. DAM UI alignment

- update `configurator/dam.js`
- update `configurator/dam.html`
- update locale copy if needed

### Step 5. Validation

Run in this order:

1. schema creates `dam_folders`, `dam_assets`, `dam_asset_links`
2. `GET /api/?endpoint=dam&action=tree`
3. `GET /api/?endpoint=dam&action=list&folder_id=<new-folder>`
4. upload test asset
5. link asset
6. query `product-assets`
7. unlink asset
8. delete asset
9. confirm `api/lib/images.php` fallback still works for any DAM-backed family assets
10. confirm DAM UI no longer points users at old folder model

## Explicit Non-Goals For This Cutover

Do **not** do these in same slice:

- switch PDF generator fully to DAM
- delete or move `appdatasheets/img/`
- bulk-migrate datasheet corpus
- redesign DAM UI beyond what new backend requires
- start Phase 2 datasheet asset migration

## Ready-To-Start File Set

When actual cutover begins, start with these files only:

- `api/sql/dam_schema.sql`
- `api/endpoints/dam.php`
- `api/index.php`
- `api/endpoints/assets.php`
- `api/lib/images.php`
- `configurator/dam.js`
- `configurator/dam.html`

Then update docs:

- `PROJECT_MEMORY.md`
- `api/DAM_API_CONTRACT.md`
- `api/DAM_FOLDER_STRUCTURE.md`
- `configurator/DAM_FEATURES.md`

## Exit Condition For This Planning Step

Planning step is done when:

- hidden old-schema dependencies are listed
- cutover order is explicit
- DB-first migration risk is documented
- next implementation slice has bounded file set
