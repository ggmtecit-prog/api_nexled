# DAM Roadmap - Asset Organization & Migration

## The Problem

Current system stores 735 image files locally in `appdatasheets/img/`.
Only 498 are unique — **233 files (32%) are exact duplicates** across families that share the same LED module.

### How images work in this project

Each product image is determined by a **lens + finish** combination.
The same filename (e.g. `alu_16.png`) appears in many folders, but:

- **Different lens = different image** (the product looks different with a 20° lens vs a frost lens)
- **Same lens + same finish + different family = identical image** (families 11 and 55 share the same LED module, so the photos are byte-for-byte identical)

Example — `alu_16.png` in family 11:

```
11/produto/20°/alu_16.png      <- unique (20° lens + finish 16)
11/produto/45°/alu_16.png      <- unique (45° lens + finish 16)
11/produto/frost/alu_16.png    <- unique (frost lens + finish 16)
11/produto/clear/1/alu_16.png  <- unique (clear type 1 + finish 16)
```

All four are **different images** — each lens changes how the product looks.

But the same lens+finish across families IS duplicated:

```
11/produto/45°/alu_16.png  =  55/produto/45°/alu_16.png   (identical, verified by md5)
11/produto/20°/alu_16.png  =  55/produto/20°/alu_16.png   (identical, verified by md5)
11/produto/frost/alu_16.png = 55/produto/frost/alu_16.png  (identical, verified by md5)
```

The same applies to `acabamentos/` (finish close-ups) — different lens = different image, but same lens across families = duplicate.

### Why this is a problem

- The current `dam.php` endpoint ties one asset to one product (`product_code` column on `dam_assets`). This doesn't match reality — many products share the same images.
- When a lens+finish image needs updating, you have to find and replace it in every family that uses it.
- Adding a new family that shares the same LED module means copying all images again.

---

## The Fix: Upload Once, Link Many

### Core Concept

Separate "what the file is" from "which products use it":

```
dam_assets (the file)              dam_asset_links (the connections)
+--------------------------+       +--------------------------------+
| id: 1                    |       | asset_id: 1                    |
| filename: alu_16.png     |<------| family_code: 11                |
| folder: packshots/45deg  |       | role: packshot                 |
| url: https://cdn/...     |       +--------------------------------+
|                          |<------| asset_id: 1                    |
| (NO product_code here)   |       | family_code: 55                |
|                          |       | role: packshot                 |
+--------------------------+       +--------------------------------+
```

One image. Two families. Change the image once, both update.

---

## Two-Group Folder Structure

The DAM is split into two top-level groups that serve different purposes:

### Why two groups?

| | `datasheet/` | `media/` |
|---|---|---|
| **Purpose** | Components that build a PDF | Standalone images for people/websites |
| **Used by** | PDF generator (code) | Amazon, store, website, support |
| **Image quality** | Optimized for PDF (exact sizes) | Optimized for web (multiple sizes) |
| **Naming** | Must follow code conventions (`alu_16.png`) | Can be descriptive (`pro-track-black.jpg`) |
| **Managed by** | Developer / automated | Designer / marketing |
| **Risk if changed** | High — wrong name breaks PDF | Low — just a display image |

If you mix them, someone uploading a new Amazon photo could accidentally rename or replace a PDF component and break datasheet generation. Keeping them separate protects both workflows.

**Images can still be shared between groups through links.** If the same photo is used in both a datasheet AND the store, it lives in one place and gets linked with different roles.

### Full folder structure

```
nexled/
|
|-- datasheet/                      <- INTERNAL: PDF generator components
|   |-- packshots/                  <- product photos used in datasheets
|   |   |-- 20deg/
|   |   |-- 45deg/
|   |   |-- 2x55deg-lf/
|   |   |-- 40deg/
|   |   |-- frost/
|   |   |-- clear-1/
|   |   |-- clear-5/
|   |   `-- clear-6/
|   |-- finishes/                   <- finish/material close-ups
|   |   |-- 20deg/
|   |   |-- 45deg/
|   |   |-- 2x55deg-lf/
|   |   |-- 40deg/
|   |   |-- frost/
|   |   |-- clear-1/
|   |   |-- clear-5/
|   |   `-- clear-6/
|   |-- drawings/                   <- technical dimension drawings (SVG)
|   |-- diagrams/                   <- wiring/connection diagrams (SVG)
|   |   `-- inverted/               <- inverted color variants
|   |-- mounting/                   <- fixing/mounting instructions (SVG)
|   |-- connectors/                 <- connection cable images (PNG)
|   |-- temperatures/               <- color temperature spectrum charts
|   |-- energy-labels/              <- energy class icons (A-G)
|   |   `-- right/                  <- right-aligned variants
|   |-- icons/                      <- certification icons (CE, IP65, RoHS...)
|   |-- logos/                      <- brand logos for PDF header/footer
|   `-- power-supplies/             <- power supply drawings
|
`-- media/                          <- EXTERNAL: public-facing images
    |-- products/                   <- product photos for store, Amazon, website
    |-- lifestyle/                  <- environment/ambient photos
    |-- datasheets/                 <- generated PDF files (output)
    |-- eprel/
    |   |-- labels/
    |   `-- fiches/
    |-- brand/
    |   |-- logos/                  <- logos for web use (different sizes/formats)
    |   |-- guidelines/
    |   `-- presentations/
    |-- store/
    |   |-- hero/
    |   |-- banners/
    |   `-- categories/
    |-- support/
    |   |-- repair-guides/
    |   `-- page-assets/
    `-- website/
        |-- hub/
        `-- landing-pages/
```

---

## File Format Handling

### The situation

Some assets exist in multiple formats (e.g. `CW573HE.svg` + `CW573HE.png`).
Different consumers need different formats:

| Consumer | Needs | Why |
|---|---|---|
| PDF generator | PNG | PHP image libraries work best with rasterized images |
| Website / store | SVG or WebP | Scales perfectly, smaller file size |
| Amazon | JPG / PNG | Amazon requires specific formats |
| Print / download | SVG or high-res PNG | Maximum quality |

### How it works

Upload **all format versions** as separate assets in the same folder.
For example, `nexled/datasheet/temperatures/` would contain:

```
CW573HE.svg    <- vector version
CW573HE.png    <- rasterized version
NW403HE.svg    <- vector version
NW403HE.png    <- rasterized version
```

Both are separate rows in `dam_assets`, with different `format` values (`svg` vs `png`).
Both get linked to the same families/products.

When calling the API, the consumer asks for the format it needs:

```
# PDF generator needs PNG
GET /api/?endpoint=dam&action=product-assets&family_code=11&role=temperature&format=png

# Website needs SVG
GET /api/?endpoint=dam&action=product-assets&family_code=11&role=temperature&format=svg
```

No format parameter = returns all formats (consumer picks what it wants).

### Which folders have multiple formats

| Folder | Formats | Notes |
|---|---|---|
| `datasheet/temperatures/` | SVG + PNG | Every chart has both |
| `datasheet/drawings/` | SVG + PNG | SVGs with rasterized copies for PDF |
| `datasheet/diagrams/` | SVG + PNG | Same |
| `datasheet/icons/` | SVG only | Vector icons, no PNG needed |
| `datasheet/energy-labels/` | SVG only | Vector badges |
| `datasheet/packshots/` | PNG only | Product photos |
| `datasheet/finishes/` | PNG only | Finish close-ups |
| `datasheet/connectors/` | PNG only | Cable photos |
| `media/products/` | JPG + PNG + WebP | Multiple sizes for different platforms |

---

## Database Changes

### 1. Simplify `dam_assets` table

Remove product-specific columns. An asset is just a file with metadata:

```sql
CREATE TABLE dam_assets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    filename        VARCHAR(255) NOT NULL,
    display_name    VARCHAR(255) NOT NULL,
    public_id       VARCHAR(255) NOT NULL,
    folder_id       VARCHAR(255) NOT NULL,
    resource_type   VARCHAR(20)  NOT NULL DEFAULT 'image',
    format          VARCHAR(20)  NULL,
    mime_type       VARCHAR(100) NULL,
    bytes           INT          NULL,
    width           INT          NULL,
    height          INT          NULL,
    secure_url      VARCHAR(500) NOT NULL,
    thumbnail_url   VARCHAR(500) NULL,
    kind            VARCHAR(64)  NOT NULL,
    tags            JSON         NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_public_id (public_id),
    INDEX idx_kind (kind),
    INDEX idx_folder (folder_id)
);
```

**Removed from current schema**: `product_code`, `family_code`, `product_slug`, `scope`, `locale`, `version`, `asset_folder`, `duration_ms`, `metadata`

### 2. New `dam_asset_links` table

Many-to-many: which assets are used by which products/families:

```sql
CREATE TABLE dam_asset_links (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    asset_id        INT          NOT NULL,
    product_code    VARCHAR(64)  NULL,
    family_code     VARCHAR(20)  NULL,
    role            VARCHAR(64)  NOT NULL,
    sort_order      INT          DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (asset_id) REFERENCES dam_assets(id) ON DELETE CASCADE,
    UNIQUE KEY uq_link (asset_id, product_code, family_code, role),
    INDEX idx_product (product_code, role),
    INDEX idx_family (family_code, role)
);
```

**Linking examples**:

| Scenario | product_code | family_code | role |
|---|---|---|---|
| Datasheet packshot (45° + alu_16), families 11 and 55 | NULL | 11 | packshot |
| Same image, second link | NULL | 55 | packshot |
| Technical drawing for specific product | PRO-TRACK-50W | 11 | drawing |
| Wiring diagram shared by whole family | NULL | 11 | diagram |
| Certification icon used globally | NULL | NULL | icon |
| Amazon product photo | PRO-TRACK-50W-BK | NULL | product-photo |
| Store hero image (no product link) | NULL | NULL | hero |

### 3. Simplify `dam_folders` table

Only the type-based buckets + lens subfolders:

```
nexled
nexled/datasheet
nexled/datasheet/packshots
nexled/datasheet/packshots/20deg
nexled/datasheet/packshots/45deg
nexled/datasheet/packshots/2x55deg-lf
nexled/datasheet/packshots/40deg
nexled/datasheet/packshots/frost
nexled/datasheet/packshots/frostc
nexled/datasheet/packshots/clear
nexled/datasheet/packshots/generic
nexled/datasheet/packshots/clear-1
nexled/datasheet/packshots/clear-2
nexled/datasheet/packshots/clear-4
nexled/datasheet/packshots/clear-5
nexled/datasheet/packshots/clear-6
nexled/datasheet/finishes
nexled/datasheet/finishes/20deg
nexled/datasheet/finishes/45deg
nexled/datasheet/finishes/2x55deg-lf
nexled/datasheet/finishes/40deg
nexled/datasheet/finishes/frost
nexled/datasheet/finishes/frostc
nexled/datasheet/finishes/clear
nexled/datasheet/finishes/generic
nexled/datasheet/finishes/clear-1
nexled/datasheet/finishes/clear-2
nexled/datasheet/finishes/clear-4
nexled/datasheet/finishes/clear-5
nexled/datasheet/finishes/clear-6
nexled/datasheet/drawings
nexled/datasheet/diagrams
nexled/datasheet/diagrams/inverted
nexled/datasheet/mounting
nexled/datasheet/connectors
nexled/datasheet/temperatures
nexled/datasheet/energy-labels
nexled/datasheet/energy-labels/right
nexled/datasheet/icons
nexled/datasheet/logos
nexled/datasheet/power-supplies
nexled/media
nexled/media/products
nexled/media/lifestyle
nexled/media/datasheets
nexled/media/eprel
nexled/media/eprel/labels
nexled/media/eprel/fiches
nexled/media/brand
nexled/media/brand/logos
nexled/media/brand/guidelines
nexled/media/brand/presentations
nexled/media/store
nexled/media/store/hero
nexled/media/store/banners
nexled/media/store/categories
nexled/media/support
nexled/media/support/repair-guides
nexled/media/support/page-assets
nexled/media/website
nexled/media/website/hub
nexled/media/website/landing-pages
```

---

## Simplified Asset Kinds

Replace 44 kinds with clear roles, split by group:

### Datasheet roles (internal)

| Role | What it is | Folder |
|---|---|---|
| `packshot` | Product photo with lens+finish | `datasheet/packshots/{lens}/` |
| `finish` | Finish/material close-up | `datasheet/finishes/{lens}/` |
| `drawing` | Technical drawing with dimensions | `datasheet/drawings/` |
| `diagram` | Wiring/connection diagram | `datasheet/diagrams/` |
| `diagram-inv` | Inverted color diagram | `datasheet/diagrams/inverted/` |
| `mounting` | Fixing/mounting instruction | `datasheet/mounting/` |
| `connector` | Connection cable image | `datasheet/connectors/` |
| `temperature` | Color temperature chart | `datasheet/temperatures/` |
| `energy-label` | Energy class icon | `datasheet/energy-labels/` |
| `icon` | Certification/IP icon | `datasheet/icons/` |
| `logo` | Brand logo for PDF | `datasheet/logos/` |
| `power-supply` | Power supply drawing | `datasheet/power-supplies/` |

### Media roles (external)

| Role | What it is | Folder |
|---|---|---|
| `product-photo` | Product photo for store/Amazon/web | `media/products/` |
| `lifestyle` | Environment/ambient photo | `media/lifestyle/` |
| `datasheet-pdf` | Generated PDF file | `media/datasheets/` |
| `eprel-label` | EPREL energy label | `media/eprel/labels/` |
| `eprel-fiche` | EPREL product fiche | `media/eprel/fiches/` |
| `brand-logo` | Logo for web use | `media/brand/logos/` |
| `brand-asset` | Guideline or presentation | `media/brand/guidelines/` |
| `hero` | Store hero image | `media/store/hero/` |
| `banner` | Store/website banner | `media/store/banners/` |
| `category` | Store category image | `media/store/categories/` |
| `support-asset` | Support page image | `media/support/` |
| `web-asset` | Website hub/landing image | `media/website/` |

---

## API Changes

### Simplified endpoints

```
GET  /api/?endpoint=dam&action=list&folder_id=nexled/datasheet/packshots/45deg
     Browse a folder

GET  /api/?endpoint=dam&action=product-assets&product_code=XXX
     Get all assets linked to a product (follows links)

GET  /api/?endpoint=dam&action=product-assets&family_code=11
     Get all assets linked to a family

GET  /api/?endpoint=dam&action=product-assets&family_code=11&role=packshot
     Get only packshots for a family

GET  /api/?endpoint=dam&action=product-assets&family_code=11&role=temperature&format=png
     Get only PNG temperature charts for a family

POST /api/?endpoint=dam&action=upload
     Upload a file to a folder (same as now, simplified)

POST /api/?endpoint=dam&action=link
     body: { "asset_id": 1, "family_code": "11", "role": "packshot" }

DELETE /api/?endpoint=dam&action=unlink&id=5
     Remove a link (image stays, just disconnected)
```

Optional filters on `product-assets`:
- `role` — filter by role (packshot, temperature, drawing, etc.)
- `format` — filter by file format (png, svg, jpg, webp, pdf)
- No filters = returns everything linked to that product/family

### Delete these endpoints

- `assets.php` — fully replaced by `dam.php`
- All folder-resolve/target logic in `dam.php` — not needed with flat structure

### Keep from current `dam.php`

- Upload to Cloudinary (works well)
- Delete from Cloudinary (works well)
- Folder tree/list (just fewer folders)
- Create folder (rarely needed now)

---

## Migration Plan — Two Phases

The migration is split into two independent phases. **Phase 1 (media) comes first** because it's a clean slate with no existing system to break. **Phase 2 (datasheet) comes after** because it's more complex and touches the working PDF generator.

### Why this order?

| | Phase 1 — Media | Phase 2 — Datasheet |
|---|---|---|
| Risk | Low (new assets, new pages) | High (touches working PDF) |
| Complexity | Simple uploads | Deduplication by lens × finish |
| Existing system | None to migrate | 735 local files to migrate |
| Business impact if broken | Hero image missing | Datasheets break (sales impact) |
| Good for learning | Yes — simple workflow | No — complex migration |

Finish Phase 1 end-to-end before starting Phase 2. Learn the DAM on low-risk assets first.

---

## PHASE 1 — Media (Public-facing images)

Goal: get the DAM running with simple, public images. Connect live pages.

### Step 1.1 — Set up the database

Create `nexled_dam` database (locally and on Railway when ready).
Run the new `dam_assets` + `dam_asset_links` + `dam_folders` schema.
Seed only the `media/` folder rows for now.

### Step 1.2 — Simplify `dam.php`

Rewrite with the linking model:
- Remove 44 kinds, use ~20 clear roles
- Add `link` / `unlink` actions
- Add `product-assets` query with `role` and `format` filters
- Delete `assets.php` endpoint

### Step 1.3 — Upload media assets (as needed)

No bulk migration — upload as each page needs them:

| Folder | For what | Priority |
|---|---|---|
| `nexled/media/brand/logos/` | Web-optimized NexLed logos | First |
| `nexled/media/store/hero/` | Store homepage hero images | As store is built |
| `nexled/media/products/` | Amazon listings, store product pages | As products go live |
| `nexled/media/support/` | Support page graphics | As support pages are built |
| `nexled/media/eprel/labels/` | EPREL energy labels | As products need compliance |
| `nexled/media/eprel/fiches/` | EPREL product fiches | As products need compliance |

### Step 1.4 — Connect live pages

Each project fetches its own images:

```
Store / Amazon:
  GET /api/?endpoint=dam&action=product-assets&product_code=PRO-TRACK-50W&role=product-photo

Store hero section:
  GET /api/?endpoint=dam&action=list&folder_id=nexled/media/store/hero

Support page:
  GET /api/?endpoint=dam&action=product-assets&family_code=11&role=support-asset
```

### Phase 1 done when

- DAM database is running (local + Railway)
- `dam.php` uses the linking model
- At least one live page (e.g. store hero) is pulling from the DAM
- You feel comfortable with the upload / link / fetch workflow

---

## PHASE 2 — Datasheet (Internal PDF components)

Goal: migrate the 735 local files to the DAM and eventually switch the PDF generator.

Do NOT start until Phase 1 is stable. Do NOT touch the PDF generator during upload.

### Step 2.1 — Add datasheet folders

Seed the `datasheet/` folder rows in `dam_folders`.
No code changes — `dam.php` already handles any folder.

### Step 2.2 — Upload datasheet shared assets first

Used across all families and all products:

| Folder | Files | What |
|---|---|---|
| `nexled/datasheet/icons` | 19 SVGs | CE, IP ratings, RoHS, indoor/outdoor... |
| `nexled/datasheet/energy-labels` | 14 SVGs | Energy class A-G (normal + right) |
| `nexled/datasheet/temperatures` | ~40 files | Color temperature charts (PNG + SVG pairs) |
| `nexled/datasheet/logos` | 5 PNGs | NexLed, TECIT, client logos |
| `nexled/datasheet/power-supplies` | 2 files | 30W supply drawing |

These are truly global — used by every datasheet. Upload once, link to everything (or leave unlinked — global assets don't need links).

### Step 2.3 — Upload packshots and finishes (deduplicated by lens)

For each lens type (20°, 45°, frost, clear, etc.):
1. Upload each unique image ONCE to the correct lens subfolder
2. Create links to every family that uses it

Example for `alu_16.png` with 45° lens:
- Upload to `nexled/datasheet/packshots/45deg/alu_16.png` (one file)
- Link to family 11 (role: packshot)
- Link to family 55 (role: packshot)

Same for finishes. Start with ONE family (family 11) to validate the process.

### Step 2.4 — Upload technical assets per family

For each family (11, 29, 30, 32, 48, 55, 58):

| Type | Upload to | Link to |
|---|---|---|
| Technical drawings | `nexled/datasheet/drawings/` | family + product variant |
| Wiring diagrams | `nexled/datasheet/diagrams/` | family |
| Inverted diagrams | `nexled/datasheet/diagrams/inverted/` | family |
| Mounting SVGs | `nexled/datasheet/mounting/` | family |
| Connector images | `nexled/datasheet/connectors/` | family |

One family at a time. Verify before moving to the next.

### Step 2.5 — Switch PDF generator to DAM URLs

Only after ALL datasheet assets are uploaded and verified:

Currently `api/lib/pdf-layout.php` and `api/lib/product-header.php` reference `appdatasheets/img/` directly.

Two sub-phases:
1. **Resolver**: add a helper that checks DAM first, falls back to local file
2. **Full switch**: once confident, remove local fallback

See `NEXT_STEPS_DATASHEET_PARITY.md` — do not use DAM as PDF source until migration is explicit and tested.

### Step 2.6 — Delete `appdatasheets/img/`

Only when PDF generator has been running on DAM URLs for weeks with no issues.

### Phase 2 done when

- All 735 local files are migrated (deduplicated to ~498)
- PDF generator uses DAM URLs exclusively
- `appdatasheets/img/` is deleted
- Every datasheet generates identically to the pre-migration version

---

## Current Local Folder -> New DAM Folder Mapping

| Local path | DAM folder | Role |
|---|---|---|
| `img/{family}/produto/{lens}/` | `nexled/datasheet/packshots/{lens}/` | packshot |
| `img/{family}/acabamentos/{lens}/` | `nexled/datasheet/finishes/{lens}/` | finish |
| `img/{family}/produto/*.png` with flat lens token (`0120_clear.png`, `0120_frostc.png`) | `nexled/datasheet/packshots/{clear|frostc|...}/` | packshot |
| `img/{family}/acabamentos/*.png` with flat lens token (`0120_clear_br.png`) | `nexled/datasheet/finishes/{clear|frostc|...}/` | finish |
| `img/48/{subtype}/produto/*.png` with no lens token | `nexled/datasheet/packshots/generic/` | packshot |
| `img/48/{subtype}/acabamentos/*.png` with no lens token | `nexled/datasheet/finishes/generic/` | finish |
| `img/{family}/desenhos/` | `nexled/datasheet/drawings/` | drawing |
| `img/{family}/diagramas/` | `nexled/datasheet/diagrams/` | diagram |
| `img/{family}/diagramas/i/` | `nexled/datasheet/diagrams/inverted/` | diagram-inv |
| `img/{family}/fixacao/` | `nexled/datasheet/mounting/` | mounting |
| `img/{family}/ligacao/` | `nexled/datasheet/connectors/` | connector |
| `img/temperaturas/` | `nexled/datasheet/temperatures/` | temperature |
| `img/classe-energetica/` | `nexled/datasheet/energy-labels/` | energy-label |
| `img/classe-energetica/right/` | `nexled/datasheet/energy-labels/right/` | energy-label |
| `img/icones/` | `nexled/datasheet/icons/` | icon |
| `img/logos/` | `nexled/datasheet/logos/` | logo |
| `img/fontes/` | `nexled/datasheet/power-supplies/` | power-supply |
| `img/placeholders/` | `nexled/datasheet/packshots/` (or skip) | packshot |

---

## File Counts After Migration

| What | Before (local) | After (Cloudinary) |
|---|---|---|
| Total files | 735 | ~498 (deduplicated) |
| Packshots + finishes | 400+ (many duplicates) | ~280 unique (per lens+finish) |
| Icons/energy/logos | 38 files | 38 files (no change) |
| Temperature charts | ~40 files | ~40 files (no change) |
| Technical drawings | ~80 SVGs | ~80 SVGs |
| Diagrams/mounting/connectors | ~60 files | ~60 files |

---

## Code Changes Summary

| File | Action |
|---|---|
| `api/endpoints/dam.php` | Simplify: remove 44 kinds, add link/unlink actions, add product-assets query |
| `api/endpoints/assets.php` | Delete entirely |
| `api/index.php` | Remove `assets` case from router |
| `api/bootstrap.php` | Keep `connectDBDam()` as-is |
| `api/lib/cloudinary.php` | Keep as-is (works well) |
| `appdatasheets/img/` | Keep for now (PDF generator still reads from here) |

---

## Priority Order

1. **Now**: Create database + seed folders
2. **Now**: Simplify `dam.php` (link model, flat roles)
3. **Now**: Upload datasheet shared assets (icons, labels, logos, temperatures)
4. **Soon**: Upload deduplicated datasheet packshots + finishes by lens, link to families
5. **Soon**: Upload datasheet drawings, diagrams, mounting per family
6. **Soon**: Start uploading media assets for live pages (store, Amazon, support)
7. **Later**: Switch PDF generator from local files to DAM URLs
8. **Later**: Delete `appdatasheets/img/` when PDF uses DAM

---

## What NOT to Do

- Do not delete `appdatasheets/img/` yet — PDF generator depends on it
- Do not create per-product or per-family folders in Cloudinary — type-based folders with lens subfolders only
- Do not duplicate images across families — upload once, link many
- Do not try to migrate all 7 families at once — start with one (family 11)
- Do not change the PDF generator and DAM structure at the same time
- Do not mix datasheet components with marketing images — keep them in separate groups
