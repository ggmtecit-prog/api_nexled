# NexLed DAM API Contract

Exact API contract for DAM v2.

Goal: connect [dam.html](c:/xampp/htdocs/api_nexled/configurator/dam.html) to Cloudinary-backed DAM with safe folder control, file control, and metadata-driven placement.

This contract replaces the current coarse `endpoint=assets` model for DAM UI work.

## Design Rules

- Keep current API entry style:
  - `/api/?endpoint=dam&action=...`
- Use HTTP method semantics where possible.
- Do not accept arbitrary client folder paths for uploads.
- DAM browser may read folder ids returned by API.
- Upload and move operations must validate destination against DAM structure rules.
- Cloudinary `asset_folder` is canonical storage location.
- Local database remains source of truth for app metadata and empty folders.
- DAM metadata lives in separate database: `nexled_dam`.
- The API Cloudinary config must point to the same cloud that really stores DAM media.
- Current DAM asset cloud used by this repo is `dofqiejpw`.
- Pointing Railway/API to another cloud name causes deterministic DAM URLs to 404 even when local DAM metadata looks correct.
- Product databases remain untouched:
  - `tecit_referencias`
  - `tecit_lampadas`
  - `info_nexled_2024`

## Cloudinary Config Invariant

These values must stay aligned:

- `CLOUDINARY_CLOUD_NAME`
- `CLOUDINARY_URL`
- `DAM_CLOUDINARY_URL`
- `CLOUDINARY_ADMIN_URL`
- `DAM_CLOUDINARY_ADMIN_URL`

If one environment uses a different cloud name than the one where DAM assets were uploaded, the API may still build valid-looking deterministic URLs, but TCPDF will receive a Cloudinary `404 Resource not found` HTML page instead of an image.

## Auth

Every request must include:

- Header: `X-API-Key: <key>`

## Content Types

- `GET`: query string only
- `POST` and `PATCH`: `application/json`
- `POST upload`: `multipart/form-data`
- Responses: `application/json`

## Response Shape

Success:

```json
{
  "ok": true,
  "data": {}
}
```

Error:

```json
{
  "ok": false,
  "error": {
    "code": "invalid_folder",
    "message": "Folder does not exist.",
    "details": {}
  }
}
```

## Core Resource Model

### Folder

```json
{
  "id": "nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings",
  "name": "drawings",
  "parent_id": "nexled/10_products/families/29_downlight/square-downlight-200/technical",
  "path": "nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings",
  "kind": "system",
  "scope": "products",
  "asset_count": 12,
  "folder_count": 0,
  "can_upload": true,
  "can_create_children": true,
  "created_at": "2026-04-13T09:00:00Z",
  "updated_at": "2026-04-13T09:00:00Z"
}
```

### Asset

```json
{
  "id": 182,
  "filename": "cutout-drawing.svg",
  "display_name": "cutout-drawing.svg",
  "public_id": "dam_29_square-downlight-200_cutout-drawing",
  "asset_folder": "nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings",
  "resource_type": "image",
  "format": "svg",
  "bytes": 45122,
  "width": 1200,
  "height": 800,
  "secure_url": "https://res.cloudinary.com/.../cutout-drawing.svg",
  "thumbnail_url": "https://res.cloudinary.com/.../w_320/...svg",
  "kind": "technical_drawing",
  "scope": "products",
  "family_code": "29",
  "product_slug": "square-downlight-200",
  "locale": null,
  "version": null,
  "tags": ["downlight", "cutout"],
  "created_at": "2026-04-13T09:00:00Z",
  "updated_at": "2026-04-13T09:00:00Z"
}
```

## Supported `scope` Values

- `brand`
- `products`
- `support`
- `store`
- `website`
- `eprel`
- `configurator`
- `archive`

## Supported `kind` Values

- `brand_logo`
- `brand_guideline`
- `brand_presentation`
- `campaign_asset`
- `product_media_packshot`
- `product_media_lifestyle`
- `product_media_thumbnail`
- `technical_drawing`
- `technical_diagram`
- `technical_finish`
- `technical_mounting`
- `technical_wiring`
- `document_manual`
- `document_installation`
- `document_report`
- `document_warning`
- `document_certificate`
- `support_repair_guide`
- `support_page_asset`
- `store_hero`
- `store_category`
- `store_collection`
- `store_merchandising`
- `website_hub_asset`
- `website_landing_asset`
- `eprel_label`
- `eprel_fiche`
- `eprel_zip`
- `configurator_ui_asset`
- `configurator_placeholder`
- `configurator_import`
- `archived_asset`

## Endpoint Contract

### 1. Get DAM Tree

`GET /api/?endpoint=dam&action=tree`

Purpose:

- Return folder tree roots and first children for sidebar navigation.

Query params:

- `depth` optional, default `2`, max `4`
- `root` optional, default `nexled`

Response:

```json
{
  "ok": true,
  "data": {
    "root": "nexled",
    "folders": [
      {
        "id": "nexled/00_brand",
        "name": "00_brand",
        "parent_id": "nexled",
        "path": "nexled/00_brand",
        "kind": "system",
        "scope": "brand",
        "asset_count": 14,
        "folder_count": 5,
        "can_upload": true,
        "can_create_children": true,
        "children": []
      }
    ]
  }
}
```

### 2. List Folder Contents

`GET /api/?endpoint=dam&action=list&folder_id=<id>`

Purpose:

- Return folders and assets inside one folder.

Query params:

- `folder_id` required
- `cursor` optional
- `limit` optional, default `60`, max `200`
- `q` optional search term
- `kind` optional
- `resource_type` optional: `image`, `raw`, `video`

Response:

```json
{
  "ok": true,
  "data": {
    "folder": {
      "id": "nexled/00_brand/logos",
      "name": "logos",
      "path": "nexled/00_brand/logos",
      "scope": "brand"
    },
    "folders": [],
    "assets": [],
    "page": {
      "cursor": null,
      "next_cursor": null,
      "limit": 60,
      "has_more": false
    }
  }
}
```

### 3. Get Asset Details

`GET /api/?endpoint=dam&action=asset&id=<asset_id>`

Purpose:

- Return one asset for preview panel.

### 4. Create Folder

`POST /api/?endpoint=dam&action=create-folder`

Purpose:

- Create one child folder under allowed parent.
- Needed for DAM UI folder control.

Body:

```json
{
  "parent_id": "nexled/30_store/collections",
  "name": "summer-2026"
}
```

Rules:

- `name` must be kebab-case after normalization.
- parent must exist.
- parent must allow child creation.
- no arbitrary full path input.

Response:

```json
{
  "ok": true,
  "data": {
    "folder": {
      "id": "nexled/30_store/collections/summer-2026",
      "name": "summer-2026",
      "parent_id": "nexled/30_store/collections",
      "path": "nexled/30_store/collections/summer-2026",
      "scope": "store",
      "kind": "custom"
    }
  }
}
```

### 5. Resolve Upload Target

`POST /api/?endpoint=dam&action=resolve-target`

Purpose:

- Validate metadata and resolve final DAM folder before upload.
- Frontend can call this before showing upload confirmation.

Body:

```json
{
  "scope": "products",
  "kind": "technical_drawing",
  "family_code": "29",
  "product_slug": "square-downlight-200",
  "locale": null,
  "version": null
}
```

Response:

```json
{
  "ok": true,
  "data": {
    "folder_id": "nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings",
    "asset_folder": "nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings",
    "public_id_prefix": "dam_29_square-downlight-200_technical-drawing"
  }
}
```

### 6. Upload Asset

`POST /api/?endpoint=dam&action=upload`

Purpose:

- Upload file to Cloudinary.
- Save metadata row locally.

Content type:

- `multipart/form-data`

Fields:

- `file` required
- `scope` required
- `kind` required
- `family_code` optional
- `product_slug` optional
- `locale` optional
- `version` optional
- `tags` optional JSON array string
- `replace_asset_id` optional

Rules:

- server resolves `asset_folder`
- server generates stable `public_id`
- client cannot send raw `asset_folder`

Response:

```json
{
  "ok": true,
  "data": {
    "asset": {
      "id": 182,
      "filename": "cutout-drawing.svg",
      "asset_folder": "nexled/10_products/families/29_downlight/square-downlight-200/technical/drawings",
      "secure_url": "https://res.cloudinary.com/.../cutout-drawing.svg"
    }
  }
}
```

### 7. Rename Asset

`PATCH /api/?endpoint=dam&action=rename-asset&id=<asset_id>`

Purpose:

- Rename display name and, when allowed, sync Cloudinary public id.

Body:

```json
{
  "display_name": "cutout-drawing-v2.svg"
}
```

Note:

- If Cloudinary public id rename is risky, phase 1 may rename only local display name.

### 8. Move Asset

`POST /api/?endpoint=dam&action=move-asset`

Purpose:

- Move one asset to another valid folder.

Body:

```json
{
  "asset_id": 182,
  "destination_folder_id": "nexled/90_archive/replaced-assets"
}
```

Rules:

- destination must exist
- destination must pass scope rules
- Cloudinary `asset_folder` must be updated
- local row must be updated

### 9. Delete Asset

`DELETE /api/?endpoint=dam&action=asset&id=<asset_id>`

Purpose:

- Delete asset from Cloudinary and local metadata table.

Response:

```json
{
  "ok": true,
  "data": {
    "deleted": true,
    "id": 182
  }
}
```

### 10. Rename Folder

`PATCH /api/?endpoint=dam&action=rename-folder&id=<folder_id>`

Purpose:

- Rename folder label and path.

Body:

```json
{
  "name": "summer-2026-approved"
}
```

Rules:

- only allowed for `kind=custom`
- system folders cannot be renamed
- backend must move descendants logically

### 11. Delete Folder

`DELETE /api/?endpoint=dam&action=folder&id=<folder_id>`

Purpose:

- Delete empty folder or archive non-empty folder by policy.

Rules:

- system folders cannot be deleted
- default phase 1 behavior:
  - reject non-empty folders with `409`

## Validation Rules

### Folder Id

- Must start with `nexled/`
- Must match registered DAM tree or validated custom folder row
- No `..`
- No duplicate slashes

### Name

- Normalize to lower-case kebab-case
- Max `80` chars

### Upload Rules

- `scope` required
- `kind` required
- `family_code` required for product family assets
- `product_slug` required for product-specific product assets
- `locale` required for locale-sensitive EPREL assets
- `version` required for stored EPREL releases

## Error Codes

- `invalid_action`
- `invalid_folder`
- `folder_not_found`
- `asset_not_found`
- `invalid_scope`
- `invalid_kind`
- `invalid_metadata`
- `folder_not_empty`
- `cloudinary_upload_failed`
- `cloudinary_delete_failed`
- `cloudinary_move_failed`
- `database_error`
- `method_not_allowed`

## DB Tables Needed

Reference migration:

- [sql/dam_schema.sql](c:/xampp/htdocs/api_nexled/api/sql/dam_schema.sql)

Database target:

- `nexled_dam`

Recommended runtime env vars:

- `DAM_DB_HOST`
- `DAM_DB_PORT`
- `DAM_DB_NAME=nexled_dam`
- `DAM_DB_USER`
- `DAM_DB_PASS`

Future bootstrap function:

- `connectDBDam()`

### `dam_folders`

Purpose:

- Track canonical folders, custom folders, empty folders, and folder metadata.

Exact columns:

- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `folder_id VARCHAR(255) NOT NULL UNIQUE`
- `parent_id VARCHAR(255) NULL`
- `name VARCHAR(80) NOT NULL`
- `path VARCHAR(255) NOT NULL UNIQUE`
- `scope ENUM('brand','products','support','store','website','eprel','configurator','archive') NOT NULL`
- `kind ENUM('system','custom') NOT NULL DEFAULT 'custom'`
- `is_system TINYINT(1) NOT NULL DEFAULT 0`
- `can_upload TINYINT(1) NOT NULL DEFAULT 1`
- `can_create_children TINYINT(1) NOT NULL DEFAULT 1`
- `sort_order INT NOT NULL DEFAULT 0`
- `metadata JSON NULL`
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`

Indexes and constraints:

- unique `uniq_dam_folders_folder_id (folder_id)`
- unique `uniq_dam_folders_path (path)`
- index `idx_dam_folders_parent_id (parent_id)`
- index `idx_dam_folders_scope (scope)`
- foreign key `fk_dam_folders_parent parent_id -> dam_folders.folder_id`

Notes:

- `folder_id` is canonical app id and matches API `folder_id`
- `path` mirrors Cloudinary `asset_folder` path shape
- root row is `nexled`
- system folders are seeded
- custom folders live under allowed seeded parents only

### `dam_assets`

Purpose:

- Track app metadata for Cloudinary assets.

Exact columns:

- `id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`
- `folder_id VARCHAR(255) NOT NULL`
- `display_name VARCHAR(255) NOT NULL`
- `filename VARCHAR(255) NOT NULL`
- `public_id VARCHAR(255) NOT NULL UNIQUE`
- `asset_folder VARCHAR(255) NOT NULL`
- `resource_type ENUM('image','raw','video') NOT NULL`
- `format VARCHAR(32) NOT NULL`
- `mime_type VARCHAR(128) NULL`
- `bytes BIGINT UNSIGNED NULL`
- `width INT UNSIGNED NULL`
- `height INT UNSIGNED NULL`
- `duration_ms INT UNSIGNED NULL`
- `secure_url VARCHAR(1024) NOT NULL`
- `thumbnail_url VARCHAR(1024) NULL`
- `kind VARCHAR(64) NOT NULL`
- `scope ENUM('brand','products','support','store','website','eprel','configurator','archive') NOT NULL`
- `family_code VARCHAR(16) NULL`
- `product_code VARCHAR(64) NULL`
- `product_slug VARCHAR(128) NULL`
- `locale VARCHAR(16) NULL`
- `version VARCHAR(64) NULL`
- `tags JSON NULL`
- `metadata JSON NULL`
- `created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`

Indexes and constraints:

- unique `uniq_dam_assets_public_id (public_id)`
- unique `uniq_dam_assets_folder_filename (folder_id, filename)`
- index `idx_dam_assets_scope_kind (scope, kind)`
- index `idx_dam_assets_family_code (family_code)`
- index `idx_dam_assets_product_code (product_code)`
- index `idx_dam_assets_product_slug (product_slug)`
- index `idx_dam_assets_locale (locale)`
- index `idx_dam_assets_version (version)`
- foreign key `fk_dam_assets_folder folder_id -> dam_folders.folder_id`

Notes:

- `public_id` is Cloudinary canonical asset id
- `asset_folder` must equal current Cloudinary folder path
- local row is metadata mirror, not file source of truth
- move operation updates both `folder_id` and `asset_folder`

### Seed Strategy

- seed root `nexled`
- seed top groups:
  - `00_brand`
  - `10_products`
  - `20_support`
  - `30_store`
  - `40_website`
  - `50_eprel`
  - `60_configurator`
  - `90_archive`
- seed fixed children from DAM structure doc
- family roots may be seeded from known product families

### Relationship Rules

- one folder has many assets
- one folder has many child folders
- asset must always belong to one valid folder
- deleting non-empty folder must be blocked in phase 1

## Phase 1 Build Scope

Implement first:

1. `tree`
2. `list`
3. `asset`
4. `create-folder`
5. `upload`
6. `delete asset`

Delay:

- rename folder
- move folder
- rename public ids
- bulk actions
- audit log
- usage map

## Why This Contract

- Fits current API router style
- Works with Cloudinary dynamic folders
- Safe for DAM UI
- Avoids arbitrary client paths
- Supports folder tree and real file manager behavior
- Leaves room for richer metadata later
