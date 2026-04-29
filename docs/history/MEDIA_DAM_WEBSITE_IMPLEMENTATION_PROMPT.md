You are working inside the NexLed DAM/API codebase.

Your job is to implement the DAM-backed "media" asset flow for website/store/support surfaces without breaking the existing datasheet DAM flow.

Read these files first, in this order:

1. `PROJECT_MEMORY.md`
2. `DAM_ROADMAP.md`
3. `DAM_IMPLEMENTATION_GUIDE.md`
4. `api/sql/dam_schema.sql`
5. `api/endpoints/dam_link_model.php`
6. `configurator/dam.html`
7. `configurator/dam.js`

Current repo truth:

- DAM link-model is already live.
- `api/endpoints/assets.php` is dead.
- `api/endpoints/dam.php` routes into `api/endpoints/dam_link_model.php`.
- DAM has two top-level roots:
  - `nexled/datasheet`
  - `nexled/media`
- Datasheet DAM flow is already working for the intended datasheet families:
  - `11`, `29`, `30`, `32`, `48`, `55`, `58`
- Remaining non-rollout families are not the blocker for this task.
- For this task, focus on `media`, not datasheet PDF logic.

What "media" means in this repo:

- Public-facing assets for websites, store pages, support pages, EPREL, and brand usage.
- Media folders are seeded in `api/sql/dam_schema.sql` under:
  - `nexled/media/products`
  - `nexled/media/lifestyle`
  - `nexled/media/datasheets`
  - `nexled/media/eprel/labels`
  - `nexled/media/eprel/fiches`
  - `nexled/media/brand/logos`
  - `nexled/media/brand/guidelines`
  - `nexled/media/brand/presentations`
  - `nexled/media/store/hero`
  - `nexled/media/store/banners`
  - `nexled/media/store/categories`
  - `nexled/media/support/repair-guides`
  - `nexled/media/support/page-assets`
  - `nexled/media/website/hub`
  - `nexled/media/website/landing-pages`

Current DAM data model:

- `dam_folders`
  - canonical DAM folder tree
- `dam_assets`
  - actual uploaded file records
  - important fields:
    - `id`
    - `folder_id`
    - `filename`
    - `display_name`
    - `public_id`
    - `resource_type`
    - `format`
    - `mime_type`
    - `bytes`
    - `width`
    - `height`
    - `secure_url`
    - `thumbnail_url`
    - `kind`
    - `tags`
- `dam_asset_links`
  - optional mapping from asset -> product/family/role
  - important fields:
    - `asset_id`
    - `product_code`
    - `family_code`
    - `role`
    - `sort_order`

Important DAM roles already supported by API:

- datasheet roles:
  - `packshot`
  - `finish`
  - `drawing`
  - `diagram`
  - `diagram-inv`
  - `mounting`
  - `connector`
  - `temperature`
  - `energy-label`
  - `icon`
  - `logo`
  - `power-supply`
- media roles:
  - `product-photo`
  - `lifestyle`
  - `datasheet-pdf`
  - `eprel-label`
  - `eprel-fiche`
  - `brand-logo`
  - `brand-asset`
  - `hero`
  - `banner`
  - `category`
  - `support-asset`
  - `web-asset`

Current API actions available in `api/endpoints/dam_link_model.php`:

- `GET /api/?endpoint=dam&action=tree`
- `GET /api/?endpoint=dam&action=list&folder_id=...`
- `GET /api/?endpoint=dam&action=asset&id=...`
- `DELETE /api/?endpoint=dam&action=asset&id=...`
- `POST /api/?endpoint=dam&action=create-folder`
- `POST /api/?endpoint=dam&action=sync-folders`
- `POST /api/?endpoint=dam&action=upload`
- `GET /api/?endpoint=dam&action=product-assets`
- `POST /api/?endpoint=dam&action=link`
- `DELETE /api/?endpoint=dam&action=unlink&id=...`

How media should be consumed:

1. Folder-driven public/shared media

Use `action=list` when the page needs assets from a known shared folder.

Examples:

- store homepage hero:
  - `folder_id=nexled/media/store/hero`
- store banners:
  - `folder_id=nexled/media/store/banners`
- store categories:
  - `folder_id=nexled/media/store/categories`
- website hub assets:
  - `folder_id=nexled/media/website/hub`
- website landing pages:
  - `folder_id=nexled/media/website/landing-pages`
- support page assets:
  - `folder_id=nexled/media/support/page-assets`
- brand logos:
  - `folder_id=nexled/media/brand/logos`

2. Product/family-bound media

Use `action=product-assets` when the asset is tied to a specific product or family via `dam_asset_links`.

Examples:

- product page main photo:
  - `GET /api/?endpoint=dam&action=product-assets&product_code=PRO-TRACK-50W&role=product-photo`
- lifestyle images for a product:
  - `GET /api/?endpoint=dam&action=product-assets&product_code=PRO-TRACK-50W&role=lifestyle`
- family-level support asset:
  - `GET /api/?endpoint=dam&action=product-assets&family_code=11&role=support-asset`
- EPREL label:
  - `GET /api/?endpoint=dam&action=product-assets&product_code=PRO-TRACK-50W&role=eprel-label`

3. Upload/link workflow

When adding new media:

- upload into correct `nexled/media/...` folder via `action=upload`
- if asset must be product/family scoped, then create link via `action=link`
- do not create per-product or per-family folders under media
- keep media folder tree type-based, not entity-based

Media folder -> expected role:

- `nexled/media/products` -> `product-photo`
- `nexled/media/lifestyle` -> `lifestyle`
- `nexled/media/datasheets` -> `datasheet-pdf`
- `nexled/media/eprel/labels` -> `eprel-label`
- `nexled/media/eprel/fiches` -> `eprel-fiche`
- `nexled/media/brand/logos` -> `brand-logo`
- `nexled/media/brand/guidelines` -> `brand-asset`
- `nexled/media/brand/presentations` -> `brand-asset`
- `nexled/media/store/hero` -> `hero`
- `nexled/media/store/banners` -> `banner`
- `nexled/media/store/categories` -> `category`
- `nexled/media/support/repair-guides` -> `support-asset`
- `nexled/media/support/page-assets` -> `support-asset`
- `nexled/media/website/hub` -> `web-asset`
- `nexled/media/website/landing-pages` -> `web-asset`

Important implementation rules:

- Do not reintroduce old DAM structure like `00_brand`, `10_products`, `60_configurator`.
- Do not revive `assets.php`.
- Do not add old scope/kind enum logic back into DAM.
- Do not create per-product or per-family media folders.
- Do not touch datasheet/PDF DAM resolver logic unless the website task absolutely requires shared helper reuse.
- Do not block media implementation on out-of-scope/legacy families.
- Do not assume missing families need image rollout for this task.

Very important auth rule:

- DAM API currently uses API-key auth.
- Do not expose private DAM API keys in public browser JavaScript for live websites unless the owner explicitly accepts that risk.
- Preferred pattern for public websites:
  - fetch DAM API server-side
  - or create backend proxy/helper endpoint
  - then render returned `secure_url`/`thumbnail_url` into the page

Current UI/admin truth:

- `configurator/dam.html` and `configurator/dam.js` are the internal admin/browser for DAM.
- They already know the live DAM roots:
  - `nexled/datasheet`
  - `nexled/media`
- Use them as reference for how the DAM contract behaves.

What to inspect in code before changing anything:

1. Where the target website currently gets images from.
2. Whether target site is server-rendered PHP, WordPress, custom JS, or store app.
3. Whether target image is:
   - shared folder asset
   - product-linked asset
   - family-linked asset
4. Whether the target page should load:
   - original `secure_url`
   - smaller `thumbnail_url`
   - or first item from a role-filtered result set

Implementation objective:

- Replace website/store/support media image sourcing with DAM-backed sourcing.
- Use the existing DAM contract.
- Keep data model centered on:
  - folder-based media organization
  - optional product/family linking via `dam_asset_links`
- Return/render Cloudinary `secure_url` values as the final media source.

Suggested implementation order:

1. Identify target website/app surface.
2. Identify current hardcoded/local image source.
3. Decide per image whether source should be:
   - `list(folder_id=...)`
   - or `product-assets(product_code/family_code + role)`
4. Implement server-side DAM fetch helper for that website/app.
5. Render DAM `secure_url` in target page.
6. Add graceful empty-state/fallback behavior if DAM returns nothing.
7. Verify page with real DAM data.
8. Keep changes minimal and local to the consumer app unless DAM contract itself is truly missing something.

Acceptance criteria:

- Website media comes from DAM, not hardcoded local files.
- Uses current DAM folder tree and role model.
- No old DAM architecture reintroduced.
- No client-side public API-key leak unless explicitly approved.
- Datasheet DAM flow remains untouched and working.
- Implementation is grounded in current repo code, not roadmap theory alone.

If DAM contract seems insufficient:

- Prefer a small, explicit extension to current `dam_link_model.php`
- Keep link-model architecture intact
- Document exactly why the extension is needed
- Do not redesign DAM again

Final deliverable expected from you:

- code changes
- short summary of what media surfaces now read from DAM
- exact endpoints/folders/roles used
- any open gaps, if DAM data is still missing
