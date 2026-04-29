# DAM Implementation Guide

This file contains everything needed to implement Phase 1 of the DAM restructure.
Read `DAM_ROADMAP.md` first for context on WHY these changes exist.

---

## Current State (verified 2026-04-16)

### Database: `nexled_dam`

- `dam_assets` table: 3 test assets (safe to drop)
- `dam_folders` table: 22 folders with old structure (`00_brand`, `10_products/families/...`, `60_configurator`)
- `dam_asset_links` table: **DOES NOT EXIST** (needs creating)

### Current `dam_assets` columns (OLD — to be replaced)

```
id, folder_id, display_name, filename, public_id, asset_folder, resource_type,
format, mime_type, bytes, width, height, duration_ms, secure_url, thumbnail_url,
kind, scope, family_code, product_code, product_slug, locale, version, tags, metadata,
created_at, updated_at
```

Problems: `product_code`, `family_code`, `product_slug`, `scope`, `locale`, `version`, `asset_folder`, `duration_ms`, `metadata` are on the asset itself. They should be on a linking table instead.

### Current `dam_folders` columns (OLD — to be replaced)

```
id (auto), folder_id, parent_id, name, path, scope (ENUM), kind (ENUM), is_system,
can_upload, can_create_children, sort_order, metadata, created_at, updated_at
```

Problem: scope ENUM is `brand, products, support, store, website, eprel, configurator, archive` — too complex. New structure only needs `root`, `datasheet`, `media`.

### Files involved

| File | Action |
|---|---|
| `api/endpoints/dam.php` | **REWRITE** (1907 lines → ~900 lines) |
| `api/endpoints/assets.php` | **DELETE** (fully replaced by dam.php) |
| `api/index.php` | **EDIT** (remove `assets` case from router) |
| `api/bootstrap.php` | **NO CHANGES** (`connectDBDam()` works fine) |
| `api/lib/cloudinary.php` | **NO CHANGES** (works fine) |

---

## Step 1 — SQL Migration

Run this SQL against the `nexled_dam` database. It drops the old tables and creates the new ones.

**IMPORTANT**: Only 3 test assets exist. Safe to drop and recreate.

```sql
-- ============================================================
-- DAM RESTRUCTURE MIGRATION
-- Run against: nexled_dam
-- ============================================================

-- 1. Drop old tables
DROP TABLE IF EXISTS `dam_assets`;
DROP TABLE IF EXISTS `dam_folders`;

-- 2. Create new dam_folders
CREATE TABLE `dam_folders` (
    `folder_id`           VARCHAR(255) NOT NULL,
    `parent_id`           VARCHAR(255) NULL,
    `name`                VARCHAR(80)  NOT NULL,
    `path`                VARCHAR(255) NOT NULL,
    `scope`               VARCHAR(32)  NOT NULL DEFAULT 'root',
    `kind`                VARCHAR(16)  NOT NULL DEFAULT 'system',
    `is_system`           TINYINT(1)   NOT NULL DEFAULT 1,
    `can_upload`          TINYINT(1)   NOT NULL DEFAULT 0,
    `can_create_children` TINYINT(1)   NOT NULL DEFAULT 0,
    `sort_order`          INT          NOT NULL DEFAULT 0,
    `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`folder_id`),
    UNIQUE KEY `uq_path` (`path`),
    INDEX `idx_parent` (`parent_id`),
    INDEX `idx_scope` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create new dam_assets (simplified — no product/family columns)
CREATE TABLE `dam_assets` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `filename`        VARCHAR(255) NOT NULL,
    `display_name`    VARCHAR(255) NOT NULL,
    `public_id`       VARCHAR(255) NOT NULL,
    `folder_id`       VARCHAR(255) NOT NULL,
    `resource_type`   VARCHAR(20)  NOT NULL DEFAULT 'image',
    `format`          VARCHAR(20)  NULL,
    `mime_type`       VARCHAR(100) NULL,
    `bytes`           INT          NULL,
    `width`           INT          NULL,
    `height`          INT          NULL,
    `secure_url`      VARCHAR(500) NOT NULL,
    `thumbnail_url`   VARCHAR(500) NULL,
    `kind`            VARCHAR(64)  NOT NULL,
    `tags`            JSON         NULL,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_public_id` (`public_id`),
    INDEX `idx_kind` (`kind`),
    INDEX `idx_folder` (`folder_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create dam_asset_links (many-to-many: asset <-> product/family)
CREATE TABLE `dam_asset_links` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `asset_id`        INT          NOT NULL,
    `product_code`    VARCHAR(64)  NULL,
    `family_code`     VARCHAR(20)  NULL,
    `role`            VARCHAR(64)  NOT NULL,
    `sort_order`      INT          DEFAULT 0,
    `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (`asset_id`) REFERENCES `dam_assets`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uq_link` (`asset_id`, `product_code`, `family_code`, `role`),
    INDEX `idx_product` (`product_code`, `role`),
    INDEX `idx_family` (`family_code`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Seed folder structure
-- Root
INSERT INTO `dam_folders` (`folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`) VALUES
('nexled', NULL, 'nexled', 'nexled', 'root', 'system', 1, 0, 1, 0);

-- Datasheet group (internal — PDF generator components)
INSERT INTO `dam_folders` (`folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`) VALUES
('nexled/datasheet',                        'nexled',                           'datasheet',       'nexled/datasheet',                        'datasheet', 'system', 1, 0, 1, 10),
('nexled/datasheet/packshots',              'nexled/datasheet',                 'packshots',       'nexled/datasheet/packshots',               'datasheet', 'system', 1, 0, 1, 10),
('nexled/datasheet/packshots/20deg',        'nexled/datasheet/packshots',       '20deg',           'nexled/datasheet/packshots/20deg',         'datasheet', 'system', 1, 1, 0, 10),
('nexled/datasheet/packshots/45deg',        'nexled/datasheet/packshots',       '45deg',           'nexled/datasheet/packshots/45deg',         'datasheet', 'system', 1, 1, 0, 20),
('nexled/datasheet/packshots/2x55deg-lf',   'nexled/datasheet/packshots',       '2x55deg-lf',     'nexled/datasheet/packshots/2x55deg-lf',    'datasheet', 'system', 1, 1, 0, 30),
('nexled/datasheet/packshots/40deg',        'nexled/datasheet/packshots',       '40deg',           'nexled/datasheet/packshots/40deg',         'datasheet', 'system', 1, 1, 0, 40),
('nexled/datasheet/packshots/frost',        'nexled/datasheet/packshots',       'frost',           'nexled/datasheet/packshots/frost',         'datasheet', 'system', 1, 1, 0, 50),
('nexled/datasheet/packshots/frostc',       'nexled/datasheet/packshots',       'frostc',          'nexled/datasheet/packshots/frostc',        'datasheet', 'system', 1, 1, 0, 60),
('nexled/datasheet/packshots/clear',        'nexled/datasheet/packshots',       'clear',           'nexled/datasheet/packshots/clear',         'datasheet', 'system', 1, 1, 0, 70),
('nexled/datasheet/packshots/generic',      'nexled/datasheet/packshots',       'generic',         'nexled/datasheet/packshots/generic',       'datasheet', 'system', 1, 1, 0, 80),
('nexled/datasheet/packshots/clear-1',      'nexled/datasheet/packshots',       'clear-1',         'nexled/datasheet/packshots/clear-1',       'datasheet', 'system', 1, 1, 0, 90),
('nexled/datasheet/packshots/clear-2',      'nexled/datasheet/packshots',       'clear-2',         'nexled/datasheet/packshots/clear-2',       'datasheet', 'system', 1, 1, 0, 100),
('nexled/datasheet/packshots/clear-4',      'nexled/datasheet/packshots',       'clear-4',         'nexled/datasheet/packshots/clear-4',       'datasheet', 'system', 1, 1, 0, 110),
('nexled/datasheet/packshots/clear-5',      'nexled/datasheet/packshots',       'clear-5',         'nexled/datasheet/packshots/clear-5',       'datasheet', 'system', 1, 1, 0, 120),
('nexled/datasheet/packshots/clear-6',      'nexled/datasheet/packshots',       'clear-6',         'nexled/datasheet/packshots/clear-6',       'datasheet', 'system', 1, 1, 0, 130),
('nexled/datasheet/finishes',               'nexled/datasheet',                 'finishes',        'nexled/datasheet/finishes',                'datasheet', 'system', 1, 0, 1, 20),
('nexled/datasheet/finishes/20deg',         'nexled/datasheet/finishes',        '20deg',           'nexled/datasheet/finishes/20deg',          'datasheet', 'system', 1, 1, 0, 10),
('nexled/datasheet/finishes/45deg',         'nexled/datasheet/finishes',        '45deg',           'nexled/datasheet/finishes/45deg',          'datasheet', 'system', 1, 1, 0, 20),
('nexled/datasheet/finishes/2x55deg-lf',    'nexled/datasheet/finishes',        '2x55deg-lf',     'nexled/datasheet/finishes/2x55deg-lf',     'datasheet', 'system', 1, 1, 0, 30),
('nexled/datasheet/finishes/40deg',         'nexled/datasheet/finishes',        '40deg',           'nexled/datasheet/finishes/40deg',          'datasheet', 'system', 1, 1, 0, 40),
('nexled/datasheet/finishes/frost',         'nexled/datasheet/finishes',        'frost',           'nexled/datasheet/finishes/frost',          'datasheet', 'system', 1, 1, 0, 50),
('nexled/datasheet/finishes/frostc',        'nexled/datasheet/finishes',        'frostc',          'nexled/datasheet/finishes/frostc',         'datasheet', 'system', 1, 1, 0, 60),
('nexled/datasheet/finishes/clear',         'nexled/datasheet/finishes',        'clear',           'nexled/datasheet/finishes/clear',          'datasheet', 'system', 1, 1, 0, 70),
('nexled/datasheet/finishes/generic',       'nexled/datasheet/finishes',        'generic',         'nexled/datasheet/finishes/generic',        'datasheet', 'system', 1, 1, 0, 80),
('nexled/datasheet/finishes/clear-1',       'nexled/datasheet/finishes',        'clear-1',         'nexled/datasheet/finishes/clear-1',        'datasheet', 'system', 1, 1, 0, 90),
('nexled/datasheet/finishes/clear-2',       'nexled/datasheet/finishes',        'clear-2',         'nexled/datasheet/finishes/clear-2',        'datasheet', 'system', 1, 1, 0, 100),
('nexled/datasheet/finishes/clear-4',       'nexled/datasheet/finishes',        'clear-4',         'nexled/datasheet/finishes/clear-4',        'datasheet', 'system', 1, 1, 0, 110),
('nexled/datasheet/finishes/clear-5',       'nexled/datasheet/finishes',        'clear-5',         'nexled/datasheet/finishes/clear-5',        'datasheet', 'system', 1, 1, 0, 120),
('nexled/datasheet/finishes/clear-6',       'nexled/datasheet/finishes',        'clear-6',         'nexled/datasheet/finishes/clear-6',        'datasheet', 'system', 1, 1, 0, 130),
('nexled/datasheet/drawings',               'nexled/datasheet',                 'drawings',        'nexled/datasheet/drawings',                'datasheet', 'system', 1, 1, 0, 30),
('nexled/datasheet/diagrams',               'nexled/datasheet',                 'diagrams',        'nexled/datasheet/diagrams',                'datasheet', 'system', 1, 1, 1, 40),
('nexled/datasheet/diagrams/inverted',      'nexled/datasheet/diagrams',        'inverted',        'nexled/datasheet/diagrams/inverted',       'datasheet', 'system', 1, 1, 0, 10),
('nexled/datasheet/mounting',               'nexled/datasheet',                 'mounting',        'nexled/datasheet/mounting',                'datasheet', 'system', 1, 1, 0, 50),
('nexled/datasheet/connectors',             'nexled/datasheet',                 'connectors',      'nexled/datasheet/connectors',              'datasheet', 'system', 1, 1, 0, 60),
('nexled/datasheet/temperatures',           'nexled/datasheet',                 'temperatures',    'nexled/datasheet/temperatures',            'datasheet', 'system', 1, 1, 0, 70),
('nexled/datasheet/energy-labels',          'nexled/datasheet',                 'energy-labels',   'nexled/datasheet/energy-labels',           'datasheet', 'system', 1, 1, 1, 80),
('nexled/datasheet/energy-labels/right',    'nexled/datasheet/energy-labels',   'right',           'nexled/datasheet/energy-labels/right',     'datasheet', 'system', 1, 1, 0, 10),
('nexled/datasheet/icons',                  'nexled/datasheet',                 'icons',           'nexled/datasheet/icons',                   'datasheet', 'system', 1, 1, 0, 90),
('nexled/datasheet/logos',                  'nexled/datasheet',                 'logos',           'nexled/datasheet/logos',                   'datasheet', 'system', 1, 1, 0, 100),
('nexled/datasheet/power-supplies',         'nexled/datasheet',                 'power-supplies',  'nexled/datasheet/power-supplies',          'datasheet', 'system', 1, 1, 0, 110);

-- Media group (external — public-facing images)
INSERT INTO `dam_folders` (`folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`) VALUES
('nexled/media',                            'nexled',                           'media',           'nexled/media',                             'media', 'system', 1, 0, 1, 20),
('nexled/media/products',                   'nexled/media',                     'products',        'nexled/media/products',                    'media', 'system', 1, 1, 1, 10),
('nexled/media/lifestyle',                  'nexled/media',                     'lifestyle',       'nexled/media/lifestyle',                   'media', 'system', 1, 1, 0, 20),
('nexled/media/datasheets',                 'nexled/media',                     'datasheets',      'nexled/media/datasheets',                  'media', 'system', 1, 1, 0, 30),
('nexled/media/eprel',                      'nexled/media',                     'eprel',           'nexled/media/eprel',                       'media', 'system', 1, 0, 1, 40),
('nexled/media/eprel/labels',               'nexled/media/eprel',              'labels',          'nexled/media/eprel/labels',                'media', 'system', 1, 1, 0, 10),
('nexled/media/eprel/fiches',               'nexled/media/eprel',              'fiches',          'nexled/media/eprel/fiches',                'media', 'system', 1, 1, 0, 20),
('nexled/media/brand',                      'nexled/media',                     'brand',           'nexled/media/brand',                       'media', 'system', 1, 0, 1, 50),
('nexled/media/brand/logos',                'nexled/media/brand',              'logos',           'nexled/media/brand/logos',                 'media', 'system', 1, 1, 0, 10),
('nexled/media/brand/guidelines',           'nexled/media/brand',              'guidelines',      'nexled/media/brand/guidelines',            'media', 'system', 1, 1, 0, 20),
('nexled/media/brand/presentations',        'nexled/media/brand',              'presentations',   'nexled/media/brand/presentations',         'media', 'system', 1, 1, 0, 30),
('nexled/media/store',                      'nexled/media',                     'store',           'nexled/media/store',                       'media', 'system', 1, 0, 1, 60),
('nexled/media/store/hero',                 'nexled/media/store',              'hero',            'nexled/media/store/hero',                  'media', 'system', 1, 1, 0, 10),
('nexled/media/store/banners',              'nexled/media/store',              'banners',         'nexled/media/store/banners',               'media', 'system', 1, 1, 0, 20),
('nexled/media/store/categories',           'nexled/media/store',              'categories',      'nexled/media/store/categories',            'media', 'system', 1, 1, 0, 30),
('nexled/media/support',                    'nexled/media',                     'support',         'nexled/media/support',                     'media', 'system', 1, 0, 1, 70),
('nexled/media/support/repair-guides',      'nexled/media/support',            'repair-guides',   'nexled/media/support/repair-guides',       'media', 'system', 1, 1, 0, 10),
('nexled/media/support/page-assets',        'nexled/media/support',            'page-assets',     'nexled/media/support/page-assets',         'media', 'system', 1, 1, 0, 20),
('nexled/media/website',                    'nexled/media',                     'website',         'nexled/media/website',                     'media', 'system', 1, 0, 1, 80),
('nexled/media/website/hub',                'nexled/media/website',            'hub',             'nexled/media/website/hub',                 'media', 'system', 1, 1, 0, 10),
('nexled/media/website/landing-pages',      'nexled/media/website',            'landing-pages',   'nexled/media/website/landing-pages',       'media', 'system', 1, 1, 0, 20);
```

After running: **49 folders**, **0 assets**, **0 links**.

---

## Step 2 — Rewrite `api/endpoints/dam.php`

### What to KEEP from old dam.php (copy as-is)

These utility functions work perfectly and should be kept unchanged:

```
damRespondSuccess()          — line 640
damRespondError()            — line 649
damRequireMethod()           — line 609
damMethodNotAllowed()        — line 617
damGetJsonBody()             — line 624
damPrepareOrFail()           — line 1024
damExecuteOrFail()           — line 1036
damBindParams()              — line 1044
damDecodeJsonColumn()        — line 1058
damValidateFolderId()        — line 690
damClampInt()                — line 662
damOptionalPositiveInt()     — line 671
damRequirePositiveInt()      — line 680
damNormalizeName()           — line 728
damNormalizeFamilyCode()     — line 735
damNormalizeProductCode()    — line 744
damSafeFilename()            — line 772
damSanitizePublicIdSegment() — line 787
damBuildPublicId()           — line 812
damDetectResourceType()      — line 975
damParseTagsInput()          — line 990
damMapFolderRow()            — line 1355
damFetchFolderById()         — line 1067
damFetchChildFolders()       — line 1097
damBuildFolderTree()         — line 1210
damFetchFoldersForCloudinarySync() — line 1135
damNextFolderSortOrder()     — line 1403
damDeleteFolderRecord()      — line 1420
damInsertFolder()            — line 1543
damEnsureFolder()            — line 1565
```

### What to DELETE from old dam.php

Remove entirely — these are part of the old 44-kind/8-scope system:

```
DAM_ALLOWED_SCOPES constant
DAM_ALLOWED_KINDS constant (44 values)
damValidateScope()
damValidateKind()
damValidateOptionalKind()
damInferKindFromFolderPath()        — massive 150-line switch
damBuildPublicIdPrefix()
damNormalizeLocale()
damNormalizeVersion()
damResolveTarget() action
damResolveTargetDescriptor()
damResolveProductTarget()
damResolveSupportRepairGuideTarget()
damResolveEprelTarget()
damFindFamilyFolderByCode()
damEnsureFamilyFolderByCode()
damFamilyFolderNameByCode()
damCurrentFolderKeepSet()
damAnalyzePruneFolders()
damBuildPruneSummary()
damPruneFolders() action
damFetchFoldersForPrune()
```

### What to CHANGE in old dam.php

#### Constants — replace old with new

```php
const DAM_ROOT_FOLDER_ID = "nexled";
const DAM_TREE_DEFAULT_DEPTH = 2;
const DAM_TREE_MAX_DEPTH = 4;
const DAM_LIST_DEFAULT_LIMIT = 60;
const DAM_LIST_MAX_LIMIT = 200;

// Replace 44 DAM_ALLOWED_KINDS with this flat list
const DAM_ALLOWED_ROLES = [
    // Datasheet (internal — PDF components)
    "packshot",
    "finish",
    "drawing",
    "diagram",
    "diagram-inv",
    "mounting",
    "connector",
    "temperature",
    "energy-label",
    "icon",
    "logo",
    "power-supply",
    // Media (external — public-facing)
    "product-photo",
    "lifestyle",
    "datasheet-pdf",
    "eprel-label",
    "eprel-fiche",
    "brand-logo",
    "brand-asset",
    "hero",
    "banner",
    "category",
    "support-asset",
    "web-asset",
];
```

#### Router switch — new actions

```php
$action = $_GET["action"] ?? null;
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

switch ($action) {
    case "tree":
        damRequireMethod(["GET"]);
        damTree();
        break;
    case "list":
        damRequireMethod(["GET"]);
        damListContents();
        break;
    case "asset":
        if ($method === "GET") { damGetAsset(); break; }
        if ($method === "DELETE") { damDeleteAsset(); break; }
        damMethodNotAllowed(["GET", "DELETE"]);
        break;
    case "create-folder":
        damRequireMethod(["POST"]);
        damCreateFolder();
        break;
    case "sync-folders":
        damRequireMethod(["POST"]);
        damSyncFolders();
        break;
    case "upload":
        damRequireMethod(["POST"]);
        damUploadAsset();
        break;

    // ---- NEW ACTIONS ----
    case "product-assets":
        damRequireMethod(["GET"]);
        damProductAssets();
        break;
    case "link":
        damRequireMethod(["POST"]);
        damLinkAsset();
        break;
    case "unlink":
        damRequireMethod(["DELETE"]);
        damUnlinkAsset();
        break;

    default:
        damRespondError(400, "invalid_action", "Invalid or missing action.");
}
```

#### `damUploadAsset()` — simplified version

Remove all scope/kind inference, resolve-target, family_code/product_code/locale/version handling.
New version only needs: `folder_id`, `kind`, `tags` (optional).

```php
function damUploadAsset(): void {
    if (empty($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        damRespondError(400, "invalid_metadata", "No file uploaded or upload error.");
    }

    $folderId = damValidateFolderId($_POST["folder_id"] ?? null);
    if ($folderId === null) {
        damRespondError(400, "invalid_folder", "Missing or invalid folder_id.");
    }

    $con = connectDBDam();
    $folder = damFetchFolderById($con, $folderId);

    if ($folder === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $folderId]);
    }

    if (!$folder["can_upload"]) {
        closeDB($con);
        damRespondError(409, "invalid_folder", "Folder does not allow uploads.", ["id" => $folderId]);
    }

    $kind = damValidateRole($_POST["kind"] ?? null);
    if ($kind === null) {
        // Try to infer from folder path
        $kind = damInferRoleFromFolder($folder["id"]);
    }
    if ($kind === null) {
        closeDB($con);
        damRespondError(400, "invalid_kind", "Missing or invalid kind. Use one of: " . implode(", ", DAM_ALLOWED_ROLES));
    }

    $file = $_FILES["file"];
    $originalFilename = damSafeFilename($file["name"] ?? "asset");
    $displayName = substr($originalFilename, 0, 255);
    $baseName = pathinfo($displayName, PATHINFO_FILENAME);
    $publicId = damBuildPublicId($folder["path"], $baseName);
    $tags = damParseTagsInput($_POST["tags"] ?? null);
    $resourceType = damDetectResourceType($displayName, $file["type"] ?? "");
    $format = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));

    if ($tags === null) {
        closeDB($con);
        damRespondError(400, "invalid_metadata", "Tags must be valid JSON array.");
    }

    // Check duplicate filename in same folder
    $dupStmt = damPrepareOrFail($con,
        "SELECT `id` FROM `dam_assets` WHERE `folder_id` = ? AND `filename` = ? LIMIT 1"
    );
    $dupParams = [$folder["id"], $displayName];
    damBindParams($dupStmt, "ss", $dupParams);
    damExecuteOrFail($dupStmt, $con);
    $dupResult = mysqli_stmt_get_result($dupStmt);
    $dup = $dupResult ? mysqli_fetch_assoc($dupResult) : null;
    mysqli_stmt_close($dupStmt);

    if ($dup !== null) {
        closeDB($con);
        damRespondError(409, "duplicate", "Asset with same filename already exists in folder.", ["id" => (int)$dup["id"]]);
    }

    // Upload to Cloudinary
    $uploadOutcome = cloudinaryUploadDetailed(
        $file["tmp_name"], $publicId, $resourceType,
        ["asset_folder" => $folder["path"], "display_name" => $displayName]
    );

    if (!($uploadOutcome["ok"] ?? false)) {
        closeDB($con);
        damRespondError(500, "cloudinary_upload_failed",
            (string)($uploadOutcome["error"] ?? "Cloudinary upload failed."),
            ["http_code" => $uploadOutcome["http_code"] ?? null]
        );
    }

    $uploadResult = $uploadOutcome["data"] ?? null;
    $secureUrl = $uploadResult["secure_url"] ?? null;

    if (!is_string($secureUrl) || $secureUrl === "") {
        closeDB($con);
        damRespondError(500, "cloudinary_upload_failed", "Cloudinary response missing secure_url.");
    }

    $storedPublicId = (string)($uploadResult["public_id"] ?? $publicId);
    $storedResourceType = in_array($uploadResult["resource_type"] ?? "", ["image", "raw", "video"], true)
        ? $uploadResult["resource_type"] : $resourceType;
    $storedFormat = (string)($uploadResult["format"] ?? $format);
    $bytes = isset($uploadResult["bytes"]) ? (int)$uploadResult["bytes"] : null;
    $width = isset($uploadResult["width"]) ? (int)$uploadResult["width"] : null;
    $height = isset($uploadResult["height"]) ? (int)$uploadResult["height"] : null;
    $thumbnailUrl = $storedResourceType === "image" ? $secureUrl : null;
    $mimeType = trim((string)($file["type"] ?? ""));
    $tagsJson = $tags === [] ? null : json_encode($tags);

    $stmt = damPrepareOrFail($con,
        "INSERT INTO `dam_assets`
        (`filename`, `display_name`, `public_id`, `folder_id`, `resource_type`, `format`,
         `mime_type`, `bytes`, `width`, `height`, `secure_url`, `thumbnail_url`, `kind`, `tags`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $insertParams = [
        $displayName, $displayName, $storedPublicId, $folder["id"],
        $storedResourceType, $storedFormat, $mimeType !== "" ? $mimeType : null,
        $bytes, $width, $height, $secureUrl, $thumbnailUrl, $kind, $tagsJson,
    ];
    damBindParams($stmt, "sssssssiiiissss", $insertParams);
    damExecuteOrFail($stmt, $con);
    $assetId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    $asset = damFetchAssetById($con, $assetId);
    closeDB($con);

    damRespondSuccess(["asset" => $asset], 201);
}
```

#### `damFetchAssets()` — simplified columns

```php
function damFetchAssets($con, string $folderId, string $query, ?string $kind, ?int $cursor, int $limit): array {
    $sql = "SELECT `id`, `folder_id`, `display_name`, `filename`, `public_id`,
                   `resource_type`, `format`, `mime_type`, `bytes`, `width`, `height`,
                   `secure_url`, `thumbnail_url`, `kind`, `tags`, `created_at`, `updated_at`
            FROM `dam_assets`
            WHERE `folder_id` = ?";
    $types = "s";
    $params = [$folderId];

    if ($query !== "") {
        $sql .= " AND (`display_name` LIKE ? OR `filename` LIKE ?)";
        $like = "%" . substr($query, 0, 120) . "%";
        $types .= "ss";
        $params[] = $like;
        $params[] = $like;
    }

    if ($kind !== null) {
        $sql .= " AND `kind` = ?";
        $types .= "s";
        $params[] = $kind;
    }

    if ($cursor !== null) {
        $sql .= " AND `id` < ?";
        $types .= "i";
        $params[] = $cursor;
    }

    $sql .= " ORDER BY `id` DESC LIMIT " . ($limit + 1);

    $stmt = damPrepareOrFail($con, $sql);
    damBindParams($stmt, $types, $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $assets = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $assets[] = damMapAssetRow($row);
        }
    }
    mysqli_stmt_close($stmt);

    $hasMore = count($assets) > $limit;
    $nextCursor = null;
    if ($hasMore) {
        $nextRow = array_pop($assets);
        $nextCursor = $nextRow["id"];
    }

    return [$assets, $hasMore, $nextCursor];
}
```

#### `damFetchAssetById()` — simplified columns

```php
function damFetchAssetById($con, int $assetId): ?array {
    $stmt = damPrepareOrFail($con,
        "SELECT `id`, `folder_id`, `display_name`, `filename`, `public_id`,
                `resource_type`, `format`, `mime_type`, `bytes`, `width`, `height`,
                `secure_url`, `thumbnail_url`, `kind`, `tags`, `created_at`, `updated_at`
         FROM `dam_assets`
         WHERE `id` = ?
         LIMIT 1"
    );
    $params = [$assetId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ? damMapAssetRow($row) : null;
}
```

#### `damMapAssetRow()` — simplified

```php
function damMapAssetRow(array $row): array {
    return [
        "id" => (int)$row["id"],
        "folder_id" => $row["folder_id"],
        "filename" => $row["filename"],
        "display_name" => $row["display_name"],
        "public_id" => $row["public_id"],
        "resource_type" => $row["resource_type"],
        "format" => $row["format"],
        "mime_type" => $row["mime_type"],
        "bytes" => isset($row["bytes"]) ? (int)$row["bytes"] : null,
        "width" => isset($row["width"]) ? (int)$row["width"] : null,
        "height" => isset($row["height"]) ? (int)$row["height"] : null,
        "secure_url" => $row["secure_url"],
        "thumbnail_url" => $row["thumbnail_url"],
        "kind" => $row["kind"],
        "tags" => damDecodeJsonColumn($row["tags"] ?? null, []),
        "created_at" => $row["created_at"] ?? null,
        "updated_at" => $row["updated_at"] ?? null,
    ];
}
```

#### `damListContents()` — update filter param

Replace `kind` filter with `role` param name (same concept, different name for clarity).
Remove `resource_type` filter (rarely used).

```php
function damListContents(): void {
    $folderId = damValidateFolderId($_GET["folder_id"] ?? null);
    if ($folderId === null) {
        damRespondError(400, "invalid_folder", "Missing or invalid folder_id.");
    }

    $limit = damClampInt($_GET["limit"] ?? DAM_LIST_DEFAULT_LIMIT, 1, DAM_LIST_MAX_LIMIT, DAM_LIST_DEFAULT_LIMIT);
    $cursor = damOptionalPositiveInt($_GET["cursor"] ?? null);
    $query = trim((string)($_GET["q"] ?? ""));
    $kind = damValidateRole($_GET["role"] ?? null);

    $con = connectDBDam();
    $folder = damFetchFolderById($con, $folderId);
    if ($folder === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $folderId]);
    }

    $folders = damFetchChildFolders($con, $folderId);
    [$assets, $hasMore, $nextCursor] = damFetchAssets($con, $folderId, $query, $kind, $cursor, $limit);
    closeDB($con);

    damRespondSuccess([
        "folder" => $folder,
        "folders" => $folders,
        "assets" => $assets,
        "page" => [
            "cursor" => $cursor,
            "next_cursor" => $nextCursor,
            "limit" => $limit,
            "has_more" => $hasMore,
        ],
    ]);
}
```

#### `damDeleteAsset()` — also delete links

```php
function damDeleteAsset(): void {
    $assetId = damRequirePositiveInt($_GET["id"] ?? null, "Missing or invalid asset id.");
    $con = connectDBDam();
    $asset = damFetchAssetById($con, $assetId);

    if ($asset === null) {
        closeDB($con);
        damRespondError(404, "asset_not_found", "Asset not found.", ["id" => $assetId]);
    }

    if (!cloudinaryDelete($asset["public_id"], $asset["resource_type"])) {
        closeDB($con);
        damRespondError(500, "cloudinary_delete_failed", "Cloudinary delete failed.");
    }

    // Links are deleted automatically via ON DELETE CASCADE
    $stmt = damPrepareOrFail($con, "DELETE FROM `dam_assets` WHERE `id` = ?");
    $params = [$assetId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
    closeDB($con);

    damRespondSuccess(["deleted" => true, "id" => $assetId]);
}
```

### NEW functions to ADD

#### `damValidateRole()` — replaces damValidateKind

```php
function damValidateRole(?string $role): ?string {
    if (!is_string($role) || $role === "") return null;
    return in_array($role, DAM_ALLOWED_ROLES, true) ? $role : null;
}
```

#### `damInferRoleFromFolder()` — simple path-based inference

```php
function damInferRoleFromFolder(string $folderId): ?string {
    $map = [
        "packshots"      => "packshot",
        "finishes"       => "finish",
        "drawings"       => "drawing",
        "diagrams"       => "diagram",
        "inverted"       => "diagram-inv",
        "mounting"       => "mounting",
        "connectors"     => "connector",
        "temperatures"   => "temperature",
        "energy-labels"  => "energy-label",
        "icons"          => "icon",
        "logos"          => "logo",
        "power-supplies" => "power-supply",
        "products"       => "product-photo",
        "lifestyle"      => "lifestyle",
        "datasheets"     => "datasheet-pdf",
        "labels"         => "eprel-label",
        "fiches"         => "eprel-fiche",
        "hero"           => "hero",
        "banners"        => "banner",
        "categories"     => "category",
        "repair-guides"  => "support-asset",
        "page-assets"    => "support-asset",
        "hub"            => "web-asset",
        "landing-pages"  => "web-asset",
        "guidelines"     => "brand-asset",
        "presentations"  => "brand-asset",
    ];

    // Check folder name and parent segments
    $segments = explode("/", $folderId);
    for ($i = count($segments) - 1; $i >= 0; $i--) {
        if (isset($map[$segments[$i]])) {
            return $map[$segments[$i]];
        }
    }

    // Special: brand/logos -> brand-logo (not just logo)
    if (str_contains($folderId, "media/brand/logos")) return "brand-logo";

    return null;
}
```

#### `damProductAssets()` — NEW action (the main query endpoint)

```php
function damProductAssets(): void {
    $productCode = damNormalizeProductCode($_GET["product_code"] ?? null);
    $familyCode = damNormalizeFamilyCode($_GET["family_code"] ?? null);
    $role = damValidateRole($_GET["role"] ?? null);
    $format = isset($_GET["format"]) ? strtolower(trim($_GET["format"])) : null;

    if ($productCode === null && $familyCode === null) {
        damRespondError(400, "invalid_metadata", "Provide product_code or family_code.");
    }

    $con = connectDBDam();

    // Build query: join dam_asset_links with dam_assets
    $sql = "SELECT a.`id`, a.`folder_id`, a.`display_name`, a.`filename`, a.`public_id`,
                   a.`resource_type`, a.`format`, a.`mime_type`, a.`bytes`, a.`width`, a.`height`,
                   a.`secure_url`, a.`thumbnail_url`, a.`kind`, a.`tags`,
                   a.`created_at`, a.`updated_at`,
                   l.`role`, l.`sort_order`
            FROM `dam_asset_links` l
            JOIN `dam_assets` a ON a.`id` = l.`asset_id`
            WHERE 1=1";
    $types = "";
    $params = [];

    if ($productCode !== null) {
        $sql .= " AND l.`product_code` = ?";
        $types .= "s";
        $params[] = $productCode;
    }

    if ($familyCode !== null) {
        $sql .= " AND l.`family_code` = ?";
        $types .= "s";
        $params[] = $familyCode;
    }

    if ($role !== null) {
        $sql .= " AND l.`role` = ?";
        $types .= "s";
        $params[] = $role;
    }

    if ($format !== null && $format !== "") {
        $sql .= " AND a.`format` = ?";
        $types .= "s";
        $params[] = $format;
    }

    $sql .= " ORDER BY l.`role` ASC, l.`sort_order` ASC, a.`id` ASC";

    $stmt = damPrepareOrFail($con, $sql);
    if ($types !== "") {
        damBindParams($stmt, $types, $params);
    }
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);

    $assets = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $asset = damMapAssetRow($row);
            $asset["link_role"] = $row["role"];
            $asset["link_sort_order"] = (int)$row["sort_order"];
            $assets[] = $asset;
        }
    }
    mysqli_stmt_close($stmt);
    closeDB($con);

    damRespondSuccess([
        "product_code" => $productCode,
        "family_code" => $familyCode,
        "role" => $role,
        "format" => $format,
        "assets" => $assets,
    ]);
}
```

#### `damLinkAsset()` — NEW action

```php
function damLinkAsset(): void {
    $body = damGetJsonBody();

    $assetId = damOptionalPositiveInt($body["asset_id"] ?? null);
    if ($assetId === null) {
        damRespondError(400, "invalid_metadata", "Missing or invalid asset_id.");
    }

    $role = damValidateRole($body["role"] ?? null);
    if ($role === null) {
        damRespondError(400, "invalid_metadata", "Missing or invalid role. Use one of: " . implode(", ", DAM_ALLOWED_ROLES));
    }

    $productCode = damNormalizeProductCode($body["product_code"] ?? null);
    $familyCode = damNormalizeFamilyCode($body["family_code"] ?? null);
    $sortOrder = (int)($body["sort_order"] ?? 0);

    // At least one of product_code or family_code should be set (or both null for global assets)
    // All-null link is valid for global assets like icons, logos

    $con = connectDBDam();

    // Verify asset exists
    $asset = damFetchAssetById($con, $assetId);
    if ($asset === null) {
        closeDB($con);
        damRespondError(404, "asset_not_found", "Asset not found.", ["id" => $assetId]);
    }

    $stmt = damPrepareOrFail($con,
        "INSERT INTO `dam_asset_links` (`asset_id`, `product_code`, `family_code`, `role`, `sort_order`)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE `sort_order` = VALUES(`sort_order`)"
    );
    $insertParams = [$assetId, $productCode, $familyCode, $role, $sortOrder];
    damBindParams($stmt, "isssi", $insertParams);
    damExecuteOrFail($stmt, $con);
    $linkId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);
    closeDB($con);

    damRespondSuccess([
        "id" => $linkId,
        "asset_id" => $assetId,
        "product_code" => $productCode,
        "family_code" => $familyCode,
        "role" => $role,
        "sort_order" => $sortOrder,
    ], 201);
}
```

#### `damUnlinkAsset()` — NEW action

```php
function damUnlinkAsset(): void {
    $linkId = damRequirePositiveInt($_GET["id"] ?? null, "Missing or invalid link id.");

    $con = connectDBDam();
    $stmt = damPrepareOrFail($con, "SELECT `id` FROM `dam_asset_links` WHERE `id` = ? LIMIT 1");
    $params = [$linkId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if ($row === null) {
        closeDB($con);
        damRespondError(404, "link_not_found", "Link not found.", ["id" => $linkId]);
    }

    $stmt = damPrepareOrFail($con, "DELETE FROM `dam_asset_links` WHERE `id` = ?");
    $params = [$linkId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
    closeDB($con);

    damRespondSuccess(["deleted" => true, "id" => $linkId]);
}
```

#### `damCreateFolder()` — simplified (remove cloudinary sync from create)

Keep the existing `damCreateFolder()` mostly as-is, but remove the `scope` parameter requirement. The scope should be inherited from the parent folder. The existing code already does `$parent["scope"]` — just keep that. Also remove the `damValidateScope()` call since that function is deleted.

The existing `damCreateFolder()` at line 210 works correctly — the only change needed is that the `scope` value should come from the parent folder (which it already does via `$parent["scope"]`).

---

## Step 3 — Update `api/index.php`

Remove the `assets` case from the router switch:

**Delete these lines** (lines 68-70):

```php
    case "assets":
        require "./endpoints/assets.php";
        break;
```

Also update the route comment at the top (line 33) — remove:
```php
// Route: /api/?endpoint=assets&action=get|upload|delete
```

And update the dam route comment to:
```php
// Route: /api/?endpoint=dam&action=tree|list|asset|create-folder|sync-folders|upload|product-assets|link|unlink
```

---

## Step 4 — Delete `api/endpoints/assets.php`

Delete the file entirely. It's 230 lines that are fully replaced by dam.php.

---

## API Reference After Changes

### Browse folders

```
GET /api/?endpoint=dam&action=tree[&root=nexled&depth=2]
GET /api/?endpoint=dam&action=list&folder_id=nexled/datasheet/packshots/45deg[&role=packshot&q=alu&limit=60&cursor=123]
```

### Get/delete single asset

```
GET    /api/?endpoint=dam&action=asset&id=123
DELETE /api/?endpoint=dam&action=asset&id=123
```

### Upload

```
POST /api/?endpoint=dam&action=upload
  multipart: file (binary), folder_id (string), kind (string, optional — inferred from folder)
  optional: tags (JSON array)
```

### Link / unlink

```
POST   /api/?endpoint=dam&action=link
  JSON body: { "asset_id": 1, "family_code": "11", "role": "packshot" }
  Optional: product_code, sort_order

DELETE /api/?endpoint=dam&action=unlink&id=5
```

### Query linked assets

```
GET /api/?endpoint=dam&action=product-assets&family_code=11
GET /api/?endpoint=dam&action=product-assets&family_code=11&role=packshot
GET /api/?endpoint=dam&action=product-assets&family_code=11&role=temperature&format=png
GET /api/?endpoint=dam&action=product-assets&product_code=PRO-TRACK-50W&role=product-photo
```

### Manage folders

```
POST /api/?endpoint=dam&action=create-folder
  JSON body: { "parent_id": "nexled/media/products", "name": "my-folder" }

POST /api/?endpoint=dam&action=sync-folders
  JSON body: { "root_id": "nexled" }
  (Creates all DB folders in Cloudinary)
```

---

## Workflow Example: Upload a packshot and link to families

```bash
# 1. Upload image to the 45deg packshot folder
curl -X POST "https://api.example.com/api/?endpoint=dam&action=upload" \
  -F "file=@alu_16.png" \
  -F "folder_id=nexled/datasheet/packshots/45deg" \
  -F "kind=packshot"
# Returns: { "ok": true, "data": { "asset": { "id": 42, ... } } }

# 2. Link to family 11
curl -X POST "https://api.example.com/api/?endpoint=dam&action=link" \
  -H "Content-Type: application/json" \
  -d '{"asset_id": 42, "family_code": "11", "role": "packshot"}'

# 3. Link same image to family 55 (no re-upload!)
curl -X POST "https://api.example.com/api/?endpoint=dam&action=link" \
  -H "Content-Type: application/json" \
  -d '{"asset_id": 42, "family_code": "55", "role": "packshot"}'

# 4. Query: get all packshots for family 11
curl "https://api.example.com/api/?endpoint=dam&action=product-assets&family_code=11&role=packshot"
```

---

## Files to NOT touch

| File | Reason |
|---|---|
| `api/bootstrap.php` | `connectDBDam()` works perfectly |
| `api/lib/cloudinary.php` | All cloudinary helpers work perfectly |
| `api/lib/pdf-layout.php` | Still reads from `appdatasheets/img/` — DO NOT change until Phase 2 |
| `api/lib/product-header.php` | Same — DO NOT change until Phase 2 |
| `appdatasheets/img/*` | Keep all local images — PDF generator depends on them |

---

## Validation Checklist After Implementation

1. Run the SQL migration — should create 3 tables and 49 folders
2. `GET /api/?endpoint=dam&action=tree` — should return the new folder tree
3. `GET /api/?endpoint=dam&action=list&folder_id=nexled/datasheet/packshots/45deg` — should return empty assets
4. Upload a test image to `nexled/datasheet/packshots/45deg`
5. Link it to family 11 with role `packshot`
6. `GET /api/?endpoint=dam&action=product-assets&family_code=11&role=packshot` — should return the image
7. Link same image to family 55
8. Query family 55 — should return same image
9. Unlink from family 55
10. Delete the test image — link should cascade delete
11. `GET /api/?endpoint=dam&action=product-assets&family_code=11` — should return empty
12. `GET /api/?endpoint=health` — DAM database should show `ok: true`
13. `POST /api/?endpoint=dam&action=sync-folders` — should sync all 49 folders to Cloudinary
