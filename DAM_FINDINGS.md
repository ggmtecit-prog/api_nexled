# DAM + API Findings

Date: 2026-04-20
Scope: review of current DAM link-model implementation and API routing.

## State verified

- `api/endpoints/dam.php` — 52-byte wrapper, forwards to `dam_link_model.php`.
- `api/endpoints/dam_link_model.php` — 452 lines, full link-model implementation.
- `api/endpoints/assets.php` — removed.
- `api/lib/images.php` — runtime routing: 7 primary families use DAM, rest use local `appdatasheets/`.
- Database (`nexled_dam`):
  - 725 assets
  - 638 asset-product links
  - 62 folders (two-group structure: `datasheet/` + `media/`)
  - 0 global (family + product both null) links
  - 202 orphan assets (no links at all)

## What works well

1. **Thin wrapper pattern** — `dam.php` stays as the router entry, real code lives in `dam_link_model.php`. Old routes keep working.
2. **Link model is live** — same image can belong to many products without duplication. 725 assets, 638 links proves it.
3. **Runtime routing is gradual** — `images.php` only sends the 7 primary families to DAM. Everything else still reads local files. Safe fallback.
4. **Cutover was clean** — `assets.php` fully removed, router cleaned, no dead routes.
5. **Folder structure matches the plan** — `datasheet/` vs `media/` groups respected, lens folders expanded as needed (20deg, 40deg, 45deg, frost, frostc, clear, clear-1..6, generic).

## Concerns

### 1. 202 orphan assets

Assets uploaded but linked to no product. Invisible to the app. Two options:

- **Link them** if they belong somewhere.
- **Delete them** if they were test uploads.

Neither costs anything — orphans just clutter the DAM.

### 2. Zero global links

Icons, logos, energy-labels, power-supply images should be linked once for everyone (family + product both null). Right now they are re-linked per product.

Impact: same icon appears multiple times in the link table. Works, but wastes rows and makes "change the icon once" harder.

### 3. Code density in `dam_link_model.php`

452 lines crammed into single-line functions (caveman style). Works today. Hard to scan when something breaks.

Not urgent. Only worth splitting if a bug appears or a new developer needs to read it.

## Recommended next steps

Small, surgical, no new features. In order:

1. **Audit the 202 orphans** — list them, decide link-or-delete per asset.
2. **Promote shared assets to global** — icons, logos, energy-labels should be family+product null. One link, not many.
3. **Leave code density alone** — only touch if broken.

## Non-goals

- Don't switch the PDF generator.
- Don't delete `appdatasheets/img` yet — non-primary families still use it.
- Don't touch `images.php` runtime routing — it's working.
