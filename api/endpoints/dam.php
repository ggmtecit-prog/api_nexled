<?php

require_once dirname(__FILE__, 2) . "/lib/cloudinary.php";

const DAM_ROOT_FOLDER_ID = "nexled";
const DAM_TREE_DEFAULT_DEPTH = 2;
const DAM_TREE_MAX_DEPTH = 4;
const DAM_LIST_DEFAULT_LIMIT = 60;
const DAM_LIST_MAX_LIMIT = 200;
const DAM_ALLOWED_SCOPES = ["brand", "products", "support", "store", "website", "eprel", "configurator", "archive"];
const DAM_ALLOWED_KINDS = [
    "brand_logo",
    "brand_guideline",
    "brand_presentation",
    "campaign_asset",
    "product_media_packshot",
    "product_media_lifestyle",
    "product_media_thumbnail",
    "technical_drawing",
    "technical_diagram",
    "technical_finish",
    "technical_mounting",
    "technical_wiring",
    "document_manual",
    "document_installation",
    "document_report",
    "document_warning",
    "document_certificate",
    "support_repair_guide",
    "support_page_asset",
    "store_hero",
    "store_category",
    "store_collection",
    "store_merchandising",
    "website_hub_asset",
    "website_landing_asset",
    "eprel_label",
    "eprel_fiche",
    "eprel_zip",
    "configurator_ui_asset",
    "configurator_placeholder",
    "configurator_import",
    "archived_asset",
];
const DAM_ALLOWED_RESOURCE_TYPES = ["image", "raw", "video"];

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
        if ($method === "GET") {
            damGetAsset();
            break;
        }

        if ($method === "DELETE") {
            damDeleteAsset();
            break;
        }

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
    case "prune-folders":
        damRequireMethod(["POST"]);
        damPruneFolders();
        break;
    case "resolve-target":
        damRequireMethod(["POST"]);
        damResolveTarget();
        break;
    case "upload":
        damRequireMethod(["POST"]);
        damUploadAsset();
        break;
    default:
        damRespondError(400, "invalid_action", "Invalid or missing action.");
}

function damTree(): void {
    $root = damValidateFolderId($_GET["root"] ?? DAM_ROOT_FOLDER_ID);
    $depth = damClampInt($_GET["depth"] ?? DAM_TREE_DEFAULT_DEPTH, 1, DAM_TREE_MAX_DEPTH, DAM_TREE_DEFAULT_DEPTH);

    if ($root === null) {
        damRespondError(400, "invalid_folder", "Invalid root folder.");
    }

    $con = connectDBDam();
    $rootFolder = damFetchFolderById($con, $root);

    if ($rootFolder === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $root]);
    }

    $folders = damBuildFolderTree($con, $root, $depth);
    closeDB($con);

    damRespondSuccess([
        "root" => $root,
        "folders" => $folders,
    ]);
}

function damListContents(): void {
    $folderId = damValidateFolderId($_GET["folder_id"] ?? null);

    if ($folderId === null) {
        damRespondError(400, "invalid_folder", "Missing or invalid folder_id.");
    }

    $limit = damClampInt($_GET["limit"] ?? DAM_LIST_DEFAULT_LIMIT, 1, DAM_LIST_MAX_LIMIT, DAM_LIST_DEFAULT_LIMIT);
    $cursor = damOptionalPositiveInt($_GET["cursor"] ?? null);
    $query = trim((string) ($_GET["q"] ?? ""));
    $kind = damValidateOptionalKind($_GET["kind"] ?? null);
    $resourceType = damValidateOptionalResourceType($_GET["resource_type"] ?? null);

    if (isset($_GET["kind"]) && $kind === null) {
        damRespondError(400, "invalid_kind", "Invalid kind filter.");
    }

    if (isset($_GET["resource_type"]) && $resourceType === null) {
        damRespondError(400, "invalid_metadata", "Invalid resource_type filter.");
    }

    $con = connectDBDam();
    $folder = damFetchFolderById($con, $folderId);

    if ($folder === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $folderId]);
    }

    $folders = damFetchChildFolders($con, $folderId);
    [$assets, $hasMore, $nextCursor] = damFetchAssets($con, $folderId, $query, $kind, $resourceType, $cursor, $limit);
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

function damGetAsset(): void {
    $assetId = damRequirePositiveInt($_GET["id"] ?? null, "Missing or invalid asset id.");
    $con = connectDBDam();
    $asset = damFetchAssetById($con, $assetId);
    closeDB($con);

    if ($asset === null) {
        damRespondError(404, "asset_not_found", "Asset not found.", ["id" => $assetId]);
    }

    damRespondSuccess([
        "asset" => $asset,
    ]);
}

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

    $stmt = damPrepareOrFail($con, "DELETE FROM `dam_assets` WHERE `id` = ?");
    $params = [$assetId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
    closeDB($con);

    damRespondSuccess([
        "deleted" => true,
        "id" => $assetId,
    ]);
}

function damCreateFolder(): void {
    $body = damGetJsonBody();
    $parentId = damValidateFolderId($body["parent_id"] ?? null);
    $name = damNormalizeName($body["name"] ?? "");

    if ($parentId === null) {
        damRespondError(400, "invalid_folder", "Missing or invalid parent_id.");
    }

    if ($name === "") {
        damRespondError(400, "invalid_metadata", "Folder name is required.");
    }

    $con = connectDBDam();
    $parent = damFetchFolderById($con, $parentId);

    if ($parent === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Parent folder not found.", ["id" => $parentId]);
    }

    if (!$parent["can_create_children"]) {
        closeDB($con);
        damRespondError(409, "invalid_folder", "Folder does not allow child creation.", ["id" => $parentId]);
    }

    $folderId = $parent["id"] . "/" . $name;
    $existing = damFetchFolderById($con, $folderId);

    if ($existing !== null) {
        closeDB($con);
        damRespondError(409, "invalid_folder", "Folder already exists.", ["id" => $folderId]);
    }

    $folder = damInsertFolder(
        $con,
        $parent["id"],
        $name,
        $parent["scope"],
        "custom",
        0,
        1,
        1,
        damNextFolderSortOrder($con, $parent["id"])
    );

    $syncResult = cloudinaryCreateFolderDetailed($folder["path"]);

    if (!($syncResult["ok"] ?? false)) {
        damDeleteFolderRecord($con, $folder["id"]);
        closeDB($con);
        damRespondError(
            502,
            "cloudinary_folder_failed",
            (string) ($syncResult["error"] ?? "Cloudinary folder creation failed."),
            [
                "folder_id" => $folder["id"],
                "http_code" => $syncResult["http_code"] ?? null,
            ]
        );
    }

    closeDB($con);

    damRespondSuccess([
        "folder" => $folder,
        "cloudinary" => [
            "created" => (bool) ($syncResult["created"] ?? false),
            "already_exists" => (bool) ($syncResult["already_exists"] ?? false),
        ],
    ], 201);
}

function damSyncFolders(): void {
    $body = damGetJsonBody();
    $rootId = damValidateFolderId($body["root_id"] ?? DAM_ROOT_FOLDER_ID);

    if ($rootId === null) {
        damRespondError(400, "invalid_folder", "Invalid root_id.");
    }

    $con = connectDBDam();
    $rootFolder = damFetchFolderById($con, $rootId);

    if ($rootFolder === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $rootId]);
    }

    $folders = damFetchFoldersForCloudinarySync($con, $rootId);
    closeDB($con);

    $results = [];
    $createdCount = 0;
    $existingCount = 0;
    $failedCount = 0;

    foreach ($folders as $folder) {
        $syncResult = cloudinaryCreateFolderDetailed($folder["path"]);

        if ($syncResult["ok"] ?? false) {
            if ($syncResult["created"] ?? false) {
                $createdCount += 1;
            } else {
                $existingCount += 1;
            }
        } else {
            $failedCount += 1;
        }

        $results[] = [
            "folder_id" => $folder["id"],
            "path" => $folder["path"],
            "ok" => (bool) ($syncResult["ok"] ?? false),
            "created" => (bool) ($syncResult["created"] ?? false),
            "already_exists" => (bool) ($syncResult["already_exists"] ?? false),
            "http_code" => $syncResult["http_code"] ?? null,
            "error" => $syncResult["error"] ?? null,
        ];
    }

    damRespondSuccess([
        "root_id" => $rootId,
        "summary" => [
            "total" => count($results),
            "created" => $createdCount,
            "already_exists" => $existingCount,
            "failed" => $failedCount,
        ],
        "folders" => $results,
    ]);
}

function damPruneFolders(): void {
    $body = damGetJsonBody();
    $rootId = damValidateFolderId($body["root_id"] ?? DAM_ROOT_FOLDER_ID);
    $dryRun = !empty($body["dry_run"]);

    if ($rootId === null) {
        damRespondError(400, "invalid_folder", "Invalid root_id.");
    }

    $con = connectDBDam();
    $rootFolder = damFetchFolderById($con, $rootId);

    if ($rootFolder === null) {
        closeDB($con);
        damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $rootId]);
    }

    $folderRows = damFetchFoldersForPrune($con, $rootId);
    $analysis = damAnalyzePruneFolders($folderRows, damCurrentFolderKeepSet());

    if ($dryRun) {
        closeDB($con);
        damRespondSuccess([
            "root_id" => $rootId,
            "dry_run" => true,
            "keep" => $analysis["keep"],
            "delete" => $analysis["delete"],
            "blocked" => $analysis["blocked"],
            "summary" => damBuildPruneSummary($analysis["delete"], $analysis["blocked"], 0, 0, 0),
        ]);
    }

    $results = [];
    $deletedCount = 0;
    $cloudinaryDeletedCount = 0;
    $cloudinaryMissingCount = 0;

    foreach ($analysis["delete"] as $folder) {
        $cloudinaryResult = cloudinaryDeleteFolderDetailed($folder["path"]);

        if (!($cloudinaryResult["ok"] ?? false)) {
            $results[] = [
                "folder_id" => $folder["id"],
                "path" => $folder["path"],
                "deleted" => false,
                "cloudinary_deleted" => false,
                "cloudinary_missing" => false,
                "error" => $cloudinaryResult["error"] ?? "Cloudinary folder deletion failed.",
                "http_code" => $cloudinaryResult["http_code"] ?? null,
            ];
            continue;
        }

        if ($cloudinaryResult["deleted"] ?? false) {
            $cloudinaryDeletedCount += 1;
        } elseif ($cloudinaryResult["already_missing"] ?? false) {
            $cloudinaryMissingCount += 1;
        }

        damDeleteFolderRecord($con, $folder["id"]);
        $deletedCount += 1;

        $results[] = [
            "folder_id" => $folder["id"],
            "path" => $folder["path"],
            "deleted" => true,
            "cloudinary_deleted" => (bool) ($cloudinaryResult["deleted"] ?? false),
            "cloudinary_missing" => (bool) ($cloudinaryResult["already_missing"] ?? false),
            "error" => null,
            "http_code" => $cloudinaryResult["http_code"] ?? null,
        ];
    }

    closeDB($con);

    damRespondSuccess([
        "root_id" => $rootId,
        "dry_run" => false,
        "keep" => $analysis["keep"],
        "delete" => $results,
        "blocked" => $analysis["blocked"],
        "summary" => damBuildPruneSummary($analysis["delete"], $analysis["blocked"], $deletedCount, $cloudinaryDeletedCount, $cloudinaryMissingCount),
    ]);
}

function damResolveTarget(): void {
    $body = damGetJsonBody();
    $con = connectDBDam();
    $target = damResolveTargetDescriptor($con, $body, false);
    closeDB($con);

    damRespondSuccess([
        "folder_id" => $target["id"],
        "asset_folder" => $target["path"],
        "public_id_prefix" => damBuildPublicIdPrefix(
            $target["path"],
            $body["kind"] ?? null,
            $body["family_code"] ?? null,
            $body["product_slug"] ?? null
        ),
    ]);
}

function damUploadAsset(): void {
    if (empty($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        damRespondError(400, "invalid_metadata", "No file uploaded or upload error.");
    }

    $con = connectDBDam();
    $folderId = damValidateFolderId($_POST["folder_id"] ?? null);

    if ($folderId !== null) {
        $target = damFetchFolderById($con, $folderId);

        if ($target === null) {
            closeDB($con);
            damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $folderId]);
        }
    } else {
        $target = damResolveTargetDescriptor($con, $_POST, true);
    }

    if (!$target["can_upload"]) {
        closeDB($con);
        damRespondError(409, "invalid_folder", "Folder does not allow uploads.", ["id" => $target["id"]]);
    }

    $kind = damValidateKind($_POST["kind"] ?? null);
    if ($kind === null) {
        $kind = damInferKindFromFolderPath($target["path"], $target["scope"]);
    }
    $scope = damValidateScope($_POST["scope"] ?? $target["scope"]);

    if ($kind === null) {
        closeDB($con);
        damRespondError(400, "invalid_kind", "Invalid kind.");
    }

    if ($scope === null) {
        closeDB($con);
        damRespondError(400, "invalid_scope", "Invalid scope.");
    }

    $file = $_FILES["file"];
    $originalFilename = damSafeFilename($file["name"] ?? "asset");
    $displayName = substr($originalFilename, 0, 255);
    $baseName = pathinfo($displayName, PATHINFO_FILENAME);
    $publicId = damBuildPublicId($target["path"], $baseName);
    $tags = damParseTagsInput($_POST["tags"] ?? null);
    $resourceType = damDetectResourceType($displayName, $file["type"] ?? "");
    $format = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));

    if ($tags === null) {
        closeDB($con);
        damRespondError(400, "invalid_metadata", "Tags must be valid JSON array.");
    }

    $duplicateStmt = damPrepareOrFail(
        $con,
        "SELECT `id` FROM `dam_assets` WHERE `folder_id` = ? AND `filename` = ? LIMIT 1"
    );
    $duplicateParams = [$target["id"], $displayName];
    damBindParams($duplicateStmt, "ss", $duplicateParams);
    damExecuteOrFail($duplicateStmt, $con);
    $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
    $duplicate = $duplicateResult ? mysqli_fetch_assoc($duplicateResult) : null;
    mysqli_stmt_close($duplicateStmt);

    if ($duplicate !== null) {
        closeDB($con);
        damRespondError(409, "invalid_metadata", "Asset with same filename already exists in folder.", [
            "id" => (int) $duplicate["id"],
        ]);
    }

    $uploadOutcome = cloudinaryUploadDetailed(
        $file["tmp_name"],
        $publicId,
        $resourceType,
        [
            "asset_folder" => $target["path"],
            "display_name" => $displayName,
        ]
    );

    if (!($uploadOutcome["ok"] ?? false)) {
        closeDB($con);
        damRespondError(
            500,
            "cloudinary_upload_failed",
            (string) ($uploadOutcome["error"] ?? "Cloudinary upload failed."),
            [
                "http_code" => $uploadOutcome["http_code"] ?? null,
            ]
        );
    }

    $uploadResult = $uploadOutcome["data"] ?? null;

    $secureUrl = $uploadResult["secure_url"] ?? null;

    if (!is_string($secureUrl) || $secureUrl === "") {
        closeDB($con);
        damRespondError(500, "cloudinary_upload_failed", "Cloudinary response missing secure_url.");
    }

    $assetFolder = (string) ($uploadResult["asset_folder"] ?? $target["path"]);
    $storedPublicId = (string) ($uploadResult["public_id"] ?? $publicId);
    $storedResourceType = damValidateOptionalResourceType($uploadResult["resource_type"] ?? $resourceType) ?? $resourceType;
    $storedFormat = (string) ($uploadResult["format"] ?? $format);
    $bytes = isset($uploadResult["bytes"]) ? (int) $uploadResult["bytes"] : null;
    $width = isset($uploadResult["width"]) ? (int) $uploadResult["width"] : null;
    $height = isset($uploadResult["height"]) ? (int) $uploadResult["height"] : null;
    $durationMs = isset($uploadResult["duration"]) ? (int) round(((float) $uploadResult["duration"]) * 1000) : null;
    $thumbnailUrl = $storedResourceType === "image" ? $secureUrl : null;
    $familyCode = damNormalizeFamilyCode($_POST["family_code"] ?? null);
    $productCode = damNormalizeProductCode($_POST["product_code"] ?? null);
    $productSlug = damNormalizeName($_POST["product_slug"] ?? "");
    $locale = damNormalizeLocale($_POST["locale"] ?? null);
    $version = damNormalizeVersion($_POST["version"] ?? null);
    $mimeType = trim((string) ($file["type"] ?? ""));
    $tagsJson = $tags === [] ? null : json_encode($tags);

    $stmt = damPrepareOrFail(
        $con,
        "INSERT INTO `dam_assets`
        (`folder_id`, `display_name`, `filename`, `public_id`, `asset_folder`, `resource_type`, `format`, `mime_type`, `bytes`, `width`, `height`, `duration_ms`, `secure_url`, `thumbnail_url`, `kind`, `scope`, `family_code`, `product_code`, `product_slug`, `locale`, `version`, `tags`, `metadata`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"
    );
    $insertParams = [
        $target["id"],
        $displayName,
        $displayName,
        $storedPublicId,
        $assetFolder,
        $storedResourceType,
        $storedFormat,
        $mimeType !== "" ? $mimeType : null,
        $bytes,
        $width,
        $height,
        $durationMs,
        $secureUrl,
        $thumbnailUrl,
        $kind,
        $scope,
        $familyCode,
        $productCode,
        $productSlug !== "" ? $productSlug : null,
        $locale,
        $version,
        $tagsJson,
    ];
    damBindParams($stmt, "ssssssssiiiissssssssss", $insertParams);
    damExecuteOrFail($stmt, $con);
    $assetId = (int) mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    $asset = damFetchAssetById($con, $assetId);
    closeDB($con);

    damRespondSuccess([
        "asset" => $asset,
    ], 201);
}

function damRequireMethod(array $allowedMethods): void {
    $method = $_SERVER["REQUEST_METHOD"] ?? "GET";

    if (!in_array($method, $allowedMethods, true)) {
        damMethodNotAllowed($allowedMethods);
    }
}

function damMethodNotAllowed(array $allowedMethods): void {
    header("Allow: " . implode(", ", $allowedMethods));
    damRespondError(405, "method_not_allowed", "Method not allowed.", [
        "allowed" => $allowedMethods,
    ]);
}

function damGetJsonBody(): array {
    $raw = file_get_contents("php://input");

    if (!is_string($raw) || trim($raw) === "") {
        damRespondError(400, "invalid_metadata", "Missing request body.");
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        damRespondError(400, "invalid_metadata", "Invalid JSON body.");
    }

    return $data;
}

function damRespondSuccess(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode([
        "ok" => true,
        "data" => $data,
    ]);
    exit();
}

function damRespondError(int $status, string $code, string $message, array $details = []): void {
    http_response_code($status);
    echo json_encode([
        "ok" => false,
        "error" => [
            "code" => $code,
            "message" => $message,
            "details" => (object) $details,
        ],
    ]);
    exit();
}

function damClampInt($value, int $min, int $max, int $default): int {
    if (!is_numeric($value)) {
        return $default;
    }

    $intValue = (int) $value;
    return max($min, min($max, $intValue));
}

function damOptionalPositiveInt($value): ?int {
    if ($value === null || $value === "" || !is_numeric($value)) {
        return null;
    }

    $intValue = (int) $value;
    return $intValue > 0 ? $intValue : null;
}

function damRequirePositiveInt($value, string $message): int {
    $intValue = damOptionalPositiveInt($value);

    if ($intValue === null) {
        damRespondError(400, "invalid_metadata", $message);
    }

    return $intValue;
}

function damValidateFolderId($folderId): ?string {
    if (!is_string($folderId)) {
        return null;
    }

    $folderId = trim($folderId);

    if ($folderId === "" || str_contains($folderId, "..") || str_contains($folderId, "//")) {
        return null;
    }

    return preg_match("/^nexled(?:\/[a-z0-9][a-z0-9_-]*)*$/", $folderId) === 1 ? $folderId : null;
}

function damValidateScope($scope): ?string {
    return is_string($scope) && in_array($scope, DAM_ALLOWED_SCOPES, true) ? $scope : null;
}

function damValidateKind($kind): ?string {
    return is_string($kind) && in_array($kind, DAM_ALLOWED_KINDS, true) ? $kind : null;
}

function damValidateOptionalKind($kind): ?string {
    if ($kind === null || $kind === "") {
        return null;
    }

    return damValidateKind($kind);
}

function damValidateOptionalResourceType($resourceType): ?string {
    if ($resourceType === null || $resourceType === "") {
        return null;
    }

    return is_string($resourceType) && in_array($resourceType, DAM_ALLOWED_RESOURCE_TYPES, true) ? $resourceType : null;
}

function damNormalizeName($value): string {
    $value = strtolower(trim((string) $value));
    $value = preg_replace("/[^a-z0-9]+/", "-", $value) ?? "";
    $value = trim($value, "-");
    return substr($value, 0, 80);
}

function damNormalizeFamilyCode($value): ?string {
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }

    $familyCode = preg_replace("/[^0-9]/", "", (string) $value) ?? "";
    return $familyCode !== "" ? $familyCode : null;
}

function damNormalizeProductCode($value): ?string {
    if (!is_string($value) && !is_numeric($value)) {
        return null;
    }

    $productCode = preg_replace("/[^a-zA-Z0-9_-]/", "", (string) $value) ?? "";
    return $productCode !== "" ? substr($productCode, 0, 64) : null;
}

function damNormalizeLocale($value): ?string {
    if (!is_string($value)) {
        return null;
    }

    $locale = strtolower(trim($value));
    $locale = preg_replace("/[^a-z0-9_-]/", "", $locale) ?? "";
    return $locale !== "" ? substr($locale, 0, 16) : null;
}

function damNormalizeVersion($value): ?string {
    if (!is_string($value)) {
        return null;
    }

    $version = preg_replace("/[^a-zA-Z0-9_-]/", "", trim($value)) ?? "";
    return $version !== "" ? substr($version, 0, 64) : null;
}

function damSafeFilename(string $filename): string {
    $filename = trim($filename);

    if ($filename === "") {
        return "asset";
    }

    $filename = preg_replace("/[\\\\\\/]+/", "-", $filename) ?? "asset";
    $filename = preg_replace("/[^a-zA-Z0-9._ -]/", "-", $filename) ?? "asset";
    $filename = preg_replace("/\\s+/", "-", $filename) ?? "asset";
    $filename = trim($filename, "-.");

    return $filename !== "" ? substr($filename, 0, 255) : "asset";
}

function damSanitizePublicIdSegment(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace("/[^a-z0-9]+/", "_", $value) ?? "";
    $value = trim($value, "_");
    return $value !== "" ? $value : "asset";
}

function damBuildPublicIdPrefix(string $folderPath, $kind, $familyCode, $productSlug): string {
    $parts = ["dam"];

    if (is_string($familyCode) && trim($familyCode) !== "") {
        $parts[] = damSanitizePublicIdSegment($familyCode);
    }

    if (is_string($productSlug) && trim($productSlug) !== "") {
        $parts[] = damSanitizePublicIdSegment($productSlug);
    }

    if (is_string($kind) && trim($kind) !== "") {
        $parts[] = damSanitizePublicIdSegment($kind);
    }

    return substr(implode("_", array_filter($parts)), 0, 200);
}

function damBuildPublicId(string $folderPath, string $baseName): string {
    $segments = array_values(array_filter(explode("/", $folderPath)));
    $tail = array_slice($segments, -4);
    $flatTail = implode("_", array_map("damSanitizePublicIdSegment", $tail));
    $base = damSanitizePublicIdSegment($baseName);
    $hash = substr(sha1($folderPath . "|" . $base), 0, 8);
    return substr("dam_" . trim($flatTail . "_" . $base . "_" . $hash, "_"), 0, 255);
}

function damInferKindFromFolderPath(string $folderPath, string $scope): ?string {
    if ($scope === "brand") {
        if (str_contains($folderPath, "/logos")) {
            return "brand_logo";
        }

        if (str_contains($folderPath, "/guidelines")) {
            return "brand_guideline";
        }

        if (str_contains($folderPath, "/presentations")) {
            return "brand_presentation";
        }

        if (str_contains($folderPath, "/campaigns")) {
            return "campaign_asset";
        }
    }

    if ($scope === "store") {
        if (str_contains($folderPath, "/hero")) {
            return "store_hero";
        }

        if (str_contains($folderPath, "/categories")) {
            return "store_category";
        }

        if (str_contains($folderPath, "/collections")) {
            return "store_collection";
        }

        if (str_contains($folderPath, "/merchandising")) {
            return "store_merchandising";
        }

        if (str_contains($folderPath, "/campaigns")) {
            return "campaign_asset";
        }
    }

    if ($scope === "website") {
        if (str_contains($folderPath, "/hub")) {
            return "website_hub_asset";
        }

        if (str_contains($folderPath, "/landing-pages")) {
            return "website_landing_asset";
        }

        if (str_contains($folderPath, "/campaigns")) {
            return "campaign_asset";
        }
    }

    if ($scope === "support") {
        if (str_contains($folderPath, "/repair-guides")) {
            return "support_repair_guide";
        }

        if (str_contains($folderPath, "/page-assets")) {
            return "support_page_asset";
        }
    }

    if ($scope === "configurator") {
        if (str_contains($folderPath, "/ui-assets")) {
            return "configurator_ui_asset";
        }

        if (str_contains($folderPath, "/placeholders")) {
            return "configurator_placeholder";
        }

        if (str_contains($folderPath, "/imports")) {
            return "configurator_import";
        }
    }

    if ($scope === "archive") {
        return "archived_asset";
    }

    if ($scope === "eprel") {
        if (str_contains($folderPath, "/labels")) {
            return "eprel_label";
        }

        if (str_contains($folderPath, "/fiches")) {
            return "eprel_fiche";
        }

        if (str_contains($folderPath, "/zip-packages")) {
            return "eprel_zip";
        }
    }

    if ($scope === "products") {
        if (str_contains($folderPath, "/media/packshots")) {
            return "product_media_packshot";
        }

        if (str_contains($folderPath, "/media/lifestyle")) {
            return "product_media_lifestyle";
        }

        if (str_contains($folderPath, "/media/thumbnails")) {
            return "product_media_thumbnail";
        }

        if (str_contains($folderPath, "/technical/drawings")) {
            return "technical_drawing";
        }

        if (str_contains($folderPath, "/technical/diagrams")) {
            return "technical_diagram";
        }

        if (str_contains($folderPath, "/technical/finishes")) {
            return "technical_finish";
        }

        if (str_contains($folderPath, "/technical/mounting")) {
            return "technical_mounting";
        }

        if (str_contains($folderPath, "/technical/wiring")) {
            return "technical_wiring";
        }

        if (str_contains($folderPath, "/documents/manuals")) {
            return "document_manual";
        }

        if (str_contains($folderPath, "/documents/installation")) {
            return "document_installation";
        }

        if (str_contains($folderPath, "/documents/reports")) {
            return "document_report";
        }

        if (str_contains($folderPath, "/documents/warnings")) {
            return "document_warning";
        }

        if (str_contains($folderPath, "/documents/certificates")) {
            return "document_certificate";
        }
    }

    return null;
}

function damDetectResourceType(string $filename, string $mimeType): string {
    $mimeType = strtolower(trim($mimeType));
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (str_starts_with($mimeType, "image/") || in_array($extension, ["jpg", "jpeg", "png", "webp", "gif", "svg", "avif"], true)) {
        return "image";
    }

    if (str_starts_with($mimeType, "video/") || in_array($extension, ["mp4", "mov", "webm", "avi", "mkv"], true)) {
        return "video";
    }

    return "raw";
}

function damParseTagsInput($raw): ?array {
    if ($raw === null || $raw === "") {
        return [];
    }

    if (is_array($raw)) {
        return array_values(array_map("strval", $raw));
    }

    if (!is_string($raw)) {
        return null;
    }

    $decoded = json_decode($raw, true);

    if (!is_array($decoded)) {
        return null;
    }

    $tags = [];

    foreach ($decoded as $tag) {
        if (is_string($tag)) {
            $trimmed = trim($tag);

            if ($trimmed !== "") {
                $tags[] = substr($trimmed, 0, 64);
            }
        }
    }

    return $tags;
}

function damPrepareOrFail($con, string $sql) {
    $stmt = mysqli_prepare($con, $sql);

    if ($stmt === false) {
        damRespondError(500, "database_error", "Database query preparation failed.", [
            "mysql" => mysqli_error($con),
        ]);
    }

    return $stmt;
}

function damExecuteOrFail($stmt, $con): void {
    if (!mysqli_stmt_execute($stmt)) {
        damRespondError(500, "database_error", "Database query failed.", [
            "mysql" => mysqli_error($con),
        ]);
    }
}

function damBindParams($stmt, string $types, array &$params): void {
    if ($types === "") {
        return;
    }

    $bindArgs = [$stmt, $types];

    foreach ($params as $key => &$value) {
        $bindArgs[] = &$value;
    }

    call_user_func_array("mysqli_stmt_bind_param", $bindArgs);
}

function damDecodeJsonColumn($value, $fallback) {
    if (!is_string($value) || trim($value) === "") {
        return $fallback;
    }

    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
}

function damFetchFolderById($con, string $folderId): ?array {
    $stmt = damPrepareOrFail(
        $con,
        "SELECT
            `folder_id`,
            `parent_id`,
            `name`,
            `path`,
            `scope`,
            `kind`,
            `can_upload`,
            `can_create_children`,
            `created_at`,
            `updated_at`,
            (SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,
            (SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count`
        FROM `dam_folders`
        WHERE `folder_id` = ?
        LIMIT 1"
    );
    $params = [$folderId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ? damMapFolderRow($row) : null;
}

function damFetchChildFolders($con, string $parentId): array {
    $stmt = damPrepareOrFail(
        $con,
        "SELECT
            `folder_id`,
            `parent_id`,
            `name`,
            `path`,
            `scope`,
            `kind`,
            `can_upload`,
            `can_create_children`,
            `created_at`,
            `updated_at`,
            (SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,
            (SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count`
        FROM `dam_folders`
        WHERE `parent_id` = ?
        ORDER BY `sort_order` ASC, `name` ASC"
    );
    $params = [$parentId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $folders = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $folder = damMapFolderRow($row);
            $folder["children"] = [];
            $folders[] = $folder;
        }
    }

    mysqli_stmt_close($stmt);
    return $folders;
}

function damFetchFoldersForCloudinarySync($con, string $rootId): array {
    $likePath = $rootId . "/%";
    $stmt = damPrepareOrFail(
        $con,
        "SELECT
            `folder_id`,
            `parent_id`,
            `name`,
            `path`,
            `scope`,
            `kind`,
            `can_upload`,
            `can_create_children`,
            `created_at`,
            `updated_at`,
            0 AS `folder_count`,
            0 AS `asset_count`
        FROM `dam_folders`
        WHERE `folder_id` = ? OR `path` LIKE ?
        ORDER BY LENGTH(`path`) ASC, `path` ASC"
    );
    $params = [$rootId, $likePath];
    damBindParams($stmt, "ss", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $folders = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $folders[] = damMapFolderRow($row);
        }
    }

    mysqli_stmt_close($stmt);
    return $folders;
}

function damFetchFoldersForPrune($con, string $rootId): array {
    $stmt = damPrepareOrFail(
        $con,
        "SELECT
            `folder_id`,
            `parent_id`,
            `name`,
            `path`,
            `scope`,
            `kind`,
            `is_system`,
            `can_upload`,
            `can_create_children`,
            `created_at`,
            `updated_at`,
            (SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,
            (SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count`
        FROM `dam_folders`
        WHERE `folder_id` = ?
           OR `folder_id` LIKE CONCAT(?, '/%')
        ORDER BY LENGTH(`path`) DESC, `path` ASC"
    );
    $params = [$rootId, $rootId];
    damBindParams($stmt, "ss", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $folders = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $folders[] = damMapFolderRow($row);
        }
    }

    mysqli_stmt_close($stmt);
    return $folders;
}

function damBuildFolderTree($con, string $parentId, int $depth): array {
    $children = damFetchChildFolders($con, $parentId);

    if ($depth <= 1) {
        return $children;
    }

    foreach ($children as $index => $child) {
        $children[$index]["children"] = damBuildFolderTree($con, $child["id"], $depth - 1);
    }

    return $children;
}

function damFetchAssets($con, string $folderId, string $query, ?string $kind, ?string $resourceType, ?int $cursor, int $limit): array {
    $sql = "SELECT
        `id`,
        `folder_id`,
        `display_name`,
        `filename`,
        `public_id`,
        `asset_folder`,
        `resource_type`,
        `format`,
        `mime_type`,
        `bytes`,
        `width`,
        `height`,
        `duration_ms`,
        `secure_url`,
        `thumbnail_url`,
        `kind`,
        `scope`,
        `family_code`,
        `product_code`,
        `product_slug`,
        `locale`,
        `version`,
        `tags`,
        `metadata`,
        `created_at`,
        `updated_at`
    FROM `dam_assets`
    WHERE `folder_id` = ?";
    $types = "s";
    $params = [$folderId];

    if ($query !== "") {
        $sql .= " AND (`display_name` LIKE ? OR `filename` LIKE ? OR `public_id` LIKE ?)";
        $like = "%" . substr($query, 0, 120) . "%";
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($kind !== null) {
        $sql .= " AND `kind` = ?";
        $types .= "s";
        $params[] = $kind;
    }

    if ($resourceType !== null) {
        $sql .= " AND `resource_type` = ?";
        $types .= "s";
        $params[] = $resourceType;
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

function damFetchAssetById($con, int $assetId): ?array {
    $stmt = damPrepareOrFail(
        $con,
        "SELECT
            `id`,
            `folder_id`,
            `display_name`,
            `filename`,
            `public_id`,
            `asset_folder`,
            `resource_type`,
            `format`,
            `mime_type`,
            `bytes`,
            `width`,
            `height`,
            `duration_ms`,
            `secure_url`,
            `thumbnail_url`,
            `kind`,
            `scope`,
            `family_code`,
            `product_code`,
            `product_slug`,
            `locale`,
            `version`,
            `tags`,
            `metadata`,
            `created_at`,
            `updated_at`
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

function damMapFolderRow(array $row): array {
    return [
        "id" => $row["folder_id"],
        "name" => $row["name"],
        "parent_id" => $row["parent_id"],
        "path" => $row["path"],
        "kind" => $row["kind"],
        "scope" => $row["scope"],
        "asset_count" => isset($row["asset_count"]) ? (int) $row["asset_count"] : 0,
        "folder_count" => isset($row["folder_count"]) ? (int) $row["folder_count"] : 0,
        "can_upload" => !empty($row["can_upload"]),
        "can_create_children" => !empty($row["can_create_children"]),
        "created_at" => $row["created_at"] ?? null,
        "updated_at" => $row["updated_at"] ?? null,
    ];
}

function damMapAssetRow(array $row): array {
    return [
        "id" => (int) $row["id"],
        "folder_id" => $row["folder_id"],
        "filename" => $row["filename"],
        "display_name" => $row["display_name"],
        "public_id" => $row["public_id"],
        "asset_folder" => $row["asset_folder"],
        "resource_type" => $row["resource_type"],
        "format" => $row["format"],
        "mime_type" => $row["mime_type"],
        "bytes" => isset($row["bytes"]) ? (int) $row["bytes"] : null,
        "width" => isset($row["width"]) ? (int) $row["width"] : null,
        "height" => isset($row["height"]) ? (int) $row["height"] : null,
        "duration_ms" => isset($row["duration_ms"]) ? (int) $row["duration_ms"] : null,
        "secure_url" => $row["secure_url"],
        "thumbnail_url" => $row["thumbnail_url"],
        "kind" => $row["kind"],
        "scope" => $row["scope"],
        "family_code" => $row["family_code"],
        "product_code" => $row["product_code"],
        "product_slug" => $row["product_slug"],
        "locale" => $row["locale"],
        "version" => $row["version"],
        "tags" => damDecodeJsonColumn($row["tags"] ?? null, []),
        "metadata" => damDecodeJsonColumn($row["metadata"] ?? null, null),
        "created_at" => $row["created_at"] ?? null,
        "updated_at" => $row["updated_at"] ?? null,
    ];
}

function damNextFolderSortOrder($con, string $parentId): int {
    $stmt = damPrepareOrFail(
        $con,
        "SELECT COALESCE(MAX(`sort_order`), 0) + 10 AS `next_sort_order`
        FROM `dam_folders`
        WHERE `parent_id` = ?"
    );
    $params = [$parentId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return isset($row["next_sort_order"]) ? (int) $row["next_sort_order"] : 10;
}

function damDeleteFolderRecord($con, string $folderId): void {
    $stmt = damPrepareOrFail($con, "DELETE FROM `dam_folders` WHERE `folder_id` = ? LIMIT 1");
    $params = [$folderId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
}

function damCurrentFolderKeepSet(): array {
    return [
        "nexled",
        "nexled/00_brand",
        "nexled/00_brand/logos",
        "nexled/10_products",
        "nexled/10_products/shared",
        "nexled/10_products/shared/temperatures",
        "nexled/10_products/shared/icons",
        "nexled/10_products/shared/power-supplies",
        "nexled/10_products/shared/energy-labels",
        "nexled/10_products/families",
        "nexled/10_products/families/11_barra-t5",
        "nexled/10_products/families/29_downlight",
        "nexled/10_products/families/30_downlight",
        "nexled/10_products/families/32_barra-bt",
        "nexled/10_products/families/48_dynamic",
        "nexled/10_products/families/55_barra",
        "nexled/10_products/families/58_barra-hot",
        "nexled/60_configurator",
        "nexled/60_configurator/ui-assets",
        "nexled/60_configurator/placeholders",
        "nexled/60_configurator/imports",
    ];
}

function damAnalyzePruneFolders(array $folders, array $keepSet): array {
    $foldersById = [];
    $childrenByParent = [];

    foreach ($folders as $folder) {
        $foldersById[$folder["id"]] = $folder;
        $parentId = $folder["parent_id"] ?? "";

        if (!isset($childrenByParent[$parentId])) {
            $childrenByParent[$parentId] = [];
        }

        $childrenByParent[$parentId][] = $folder["id"];
    }

    $subtreeAssetCounts = [];
    $computeSubtreeAssets = function (string $folderId) use (&$computeSubtreeAssets, &$subtreeAssetCounts, $foldersById, $childrenByParent): int {
        if (isset($subtreeAssetCounts[$folderId])) {
            return $subtreeAssetCounts[$folderId];
        }

        $assetCount = (int) ($foldersById[$folderId]["asset_count"] ?? 0);

        foreach ($childrenByParent[$folderId] ?? [] as $childId) {
            $assetCount += $computeSubtreeAssets($childId);
        }

        $subtreeAssetCounts[$folderId] = $assetCount;
        return $assetCount;
    };

    foreach (array_keys($foldersById) as $folderId) {
        $computeSubtreeAssets($folderId);
    }

    $keep = [];
    $delete = [];
    $blocked = [];

    foreach ($folders as $folder) {
        if (in_array($folder["id"], $keepSet, true)) {
            $keep[] = [
                "folder_id" => $folder["id"],
                "path" => $folder["path"],
                "reason" => "keep_set",
            ];
            continue;
        }

        $subtreeAssetCount = $subtreeAssetCounts[$folder["id"]] ?? 0;

        if ($subtreeAssetCount > 0) {
            $blocked[] = [
                "folder_id" => $folder["id"],
                "path" => $folder["path"],
                "reason" => "subtree_has_assets",
                "asset_count" => $subtreeAssetCount,
            ];
            continue;
        }

        $delete[] = [
            "id" => $folder["id"],
            "path" => $folder["path"],
            "reason" => "outside_keep_set",
        ];
    }

    return [
        "keep" => $keep,
        "delete" => $delete,
        "blocked" => $blocked,
    ];
}

function damBuildPruneSummary(array $deleteList, array $blockedList, int $deletedCount, int $cloudinaryDeletedCount, int $cloudinaryMissingCount): array {
    return [
        "delete_candidates" => count($deleteList),
        "blocked" => count($blockedList),
        "deleted" => $deletedCount,
        "cloudinary_deleted" => $cloudinaryDeletedCount,
        "cloudinary_missing" => $cloudinaryMissingCount,
    ];
}

function damInsertFolder($con, string $parentId, string $name, string $scope, string $kind, int $isSystem, int $canUpload, int $canCreateChildren, int $sortOrder): array {
    $folderId = $parentId . "/" . $name;
    $stmt = damPrepareOrFail(
        $con,
        "INSERT INTO `dam_folders`
        (`folder_id`, `parent_id`, `name`, `path`, `scope`, `kind`, `is_system`, `can_upload`, `can_create_children`, `sort_order`, `metadata`)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)"
    );
    $params = [$folderId, $parentId, $name, $folderId, $scope, $kind, $isSystem, $canUpload, $canCreateChildren, $sortOrder];
    damBindParams($stmt, "ssssssiiii", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);

    $folder = damFetchFolderById($con, $folderId);

    if ($folder === null) {
        damRespondError(500, "database_error", "Folder creation failed.");
    }

    return $folder;
}

function damEnsureFolder($con, string $parentId, string $name, string $scope, int $canUpload, int $canCreateChildren): array {
    $folderId = $parentId . "/" . $name;
    $existing = damFetchFolderById($con, $folderId);

    if ($existing !== null) {
        return $existing;
    }

    return damInsertFolder($con, $parentId, $name, $scope, "system", 1, $canUpload, $canCreateChildren, damNextFolderSortOrder($con, $parentId));
}

function damResolveTargetDescriptor($con, array $payload, bool $ensureFolders): array {
    $explicitFolderId = damValidateFolderId($payload["folder_id"] ?? null);

    if ($explicitFolderId !== null) {
        $folder = damFetchFolderById($con, $explicitFolderId);

        if ($folder === null) {
            damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $explicitFolderId]);
        }

        return $folder;
    }

    $scope = damValidateScope($payload["scope"] ?? null);
    $kind = damValidateKind($payload["kind"] ?? null);

    if ($scope === null) {
        damRespondError(400, "invalid_scope", "Invalid scope.");
    }

    if ($kind === null) {
        damRespondError(400, "invalid_kind", "Invalid kind.");
    }

    $fixedTargets = [
        "brand" => [
            "brand_logo" => "nexled/00_brand/logos",
            "brand_guideline" => "nexled/00_brand/guidelines",
            "brand_presentation" => "nexled/00_brand/presentations",
            "campaign_asset" => "nexled/00_brand/campaigns",
        ],
        "support" => [
            "support_page_asset" => "nexled/20_support/page-assets",
        ],
        "store" => [
            "store_hero" => "nexled/30_store/hero",
            "store_category" => "nexled/30_store/categories",
            "store_collection" => "nexled/30_store/collections",
            "store_merchandising" => "nexled/30_store/merchandising",
            "campaign_asset" => "nexled/30_store/campaigns",
        ],
        "website" => [
            "website_hub_asset" => "nexled/40_website/hub",
            "website_landing_asset" => "nexled/40_website/landing-pages",
            "campaign_asset" => "nexled/40_website/campaigns",
        ],
        "configurator" => [
            "configurator_ui_asset" => "nexled/60_configurator/ui-assets",
            "configurator_placeholder" => "nexled/60_configurator/placeholders",
            "configurator_import" => "nexled/60_configurator/imports",
        ],
        "archive" => [
            "archived_asset" => "nexled/90_archive/replaced-assets",
            "campaign_asset" => "nexled/90_archive/retired-campaigns",
        ],
    ];

    if (isset($fixedTargets[$scope][$kind])) {
        $folder = damFetchFolderById($con, $fixedTargets[$scope][$kind]);

        if ($folder === null) {
            damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $fixedTargets[$scope][$kind]]);
        }

        return $folder;
    }

    if ($scope === "products") {
        return damResolveProductTarget($con, $payload, $kind, $ensureFolders);
    }

    if ($scope === "support" && $kind === "support_repair_guide") {
        return damResolveSupportRepairGuideTarget($con, $payload, $ensureFolders);
    }

    if ($scope === "eprel") {
        return damResolveEprelTarget($con, $payload, $kind, $ensureFolders);
    }

    damRespondError(400, "invalid_metadata", "No DAM target mapping exists for this scope and kind.");
}

function damResolveProductTarget($con, array $payload, string $kind, bool $ensureFolders): array {
    $familyCode = damNormalizeFamilyCode($payload["family_code"] ?? null);
    $productSlug = damNormalizeName($payload["product_slug"] ?? "");

    if ($familyCode === null) {
        damRespondError(400, "invalid_metadata", "family_code is required for product assets.");
    }

    if ($productSlug === "") {
        damRespondError(400, "invalid_metadata", "product_slug is required for product assets.");
    }

    $familyFolder = damFindFamilyFolderByCode($con, $familyCode);

    if ($familyFolder === null) {
        $familyFolder = damEnsureFamilyFolderByCode($con, $familyCode);
    }

    if ($familyFolder === null) {
        damRespondError(404, "folder_not_found", "Family folder not found.", ["family_code" => $familyCode]);
    }

    $leafPath = match ($kind) {
        "product_media_packshot" => ["media", "packshots"],
        "product_media_lifestyle" => ["media", "lifestyle"],
        "product_media_thumbnail" => ["media", "thumbnails"],
        "technical_drawing" => ["technical", "drawings"],
        "technical_diagram" => ["technical", "diagrams"],
        "technical_finish" => ["technical", "finishes"],
        "technical_mounting" => ["technical", "mounting"],
        "technical_wiring" => ["technical", "wiring"],
        "document_manual" => ["documents", "manuals"],
        "document_installation" => ["documents", "installation"],
        "document_report" => ["documents", "reports"],
        "document_warning" => ["documents", "warnings"],
        "document_certificate" => ["documents", "certificates"],
        default => null,
    };

    if ($leafPath === null) {
        damRespondError(400, "invalid_metadata", "No product DAM target mapping exists for this kind.");
    }

    if (!$ensureFolders) {
        $computedId = $familyFolder["id"] . "/" . $productSlug . "/" . $leafPath[0] . "/" . $leafPath[1];
        return [
            "id" => $computedId,
            "name" => $leafPath[1],
            "parent_id" => $familyFolder["id"] . "/" . $productSlug . "/" . $leafPath[0],
            "path" => $computedId,
            "kind" => "system",
            "scope" => "products",
            "asset_count" => 0,
            "folder_count" => 0,
            "can_upload" => true,
            "can_create_children" => false,
            "created_at" => null,
            "updated_at" => null,
        ];
    }

    $productFolder = damEnsureFolder($con, $familyFolder["id"], $productSlug, "products", 0, 1);
    $sectionFolder = damEnsureFolder($con, $productFolder["id"], $leafPath[0], "products", 0, 1);
    return damEnsureFolder($con, $sectionFolder["id"], $leafPath[1], "products", 1, 0);
}

function damResolveSupportRepairGuideTarget($con, array $payload, bool $ensureFolders): array {
    $familyCode = damNormalizeFamilyCode($payload["family_code"] ?? null);
    $productSlug = damNormalizeName($payload["product_slug"] ?? "");

    if ($familyCode === null || $productSlug === "") {
        damRespondError(400, "invalid_metadata", "family_code and product_slug are required for repair guides.");
    }

    $familyFolder = damFindFamilyFolderByCode($con, $familyCode);

    if ($familyFolder === null) {
        $familyFolder = damEnsureFamilyFolderByCode($con, $familyCode);
    }

    if ($familyFolder === null) {
        damRespondError(404, "folder_not_found", "Family folder not found.", ["family_code" => $familyCode]);
    }

    $baseFolder = damFetchFolderById($con, "nexled/20_support/repair-guides");

    if ($baseFolder === null) {
        damRespondError(404, "folder_not_found", "Support repair-guides folder not found.");
    }

    if (!$ensureFolders) {
        $computedId = $baseFolder["id"] . "/" . $familyFolder["name"] . "/" . $productSlug;
        return [
            "id" => $computedId,
            "name" => $productSlug,
            "parent_id" => $baseFolder["id"] . "/" . $familyFolder["name"],
            "path" => $computedId,
            "kind" => "system",
            "scope" => "support",
            "asset_count" => 0,
            "folder_count" => 0,
            "can_upload" => true,
            "can_create_children" => false,
            "created_at" => null,
            "updated_at" => null,
        ];
    }

    $familySupportFolder = damEnsureFolder($con, $baseFolder["id"], $familyFolder["name"], "support", 0, 1);
    return damEnsureFolder($con, $familySupportFolder["id"], $productSlug, "support", 1, 0);
}

function damResolveEprelTarget($con, array $payload, string $kind, bool $ensureFolders): array {
    $productSlug = damNormalizeName($payload["product_slug"] ?? "");
    $locale = damNormalizeLocale($payload["locale"] ?? null);
    $version = damNormalizeVersion($payload["version"] ?? null);

    if ($kind === "eprel_zip") {
        if ($productSlug === "" || $version === null) {
            damRespondError(400, "invalid_metadata", "product_slug and version are required for EPREL zip packages.");
        }

        $baseFolder = damFetchFolderById($con, "nexled/50_eprel/zip-packages");

        if ($baseFolder === null) {
            damRespondError(404, "folder_not_found", "EPREL zip-packages folder not found.");
        }

        if (!$ensureFolders) {
            $computedId = $baseFolder["id"] . "/" . $productSlug . "/" . $version;
            return [
                "id" => $computedId,
                "name" => $version,
                "parent_id" => $baseFolder["id"] . "/" . $productSlug,
                "path" => $computedId,
                "kind" => "system",
                "scope" => "eprel",
                "asset_count" => 0,
                "folder_count" => 0,
                "can_upload" => true,
                "can_create_children" => false,
                "created_at" => null,
                "updated_at" => null,
            ];
        }

        $productFolder = damEnsureFolder($con, $baseFolder["id"], $productSlug, "eprel", 0, 1);
        return damEnsureFolder($con, $productFolder["id"], $version, "eprel", 1, 0);
    }

    if ($productSlug === "" || $locale === null || $version === null) {
        damRespondError(400, "invalid_metadata", "product_slug, locale, and version are required for EPREL label and fiche assets.");
    }

    $basePath = $kind === "eprel_label" ? "nexled/50_eprel/labels" : "nexled/50_eprel/fiches";
    $baseFolder = damFetchFolderById($con, $basePath);

    if ($baseFolder === null) {
        damRespondError(404, "folder_not_found", "EPREL base folder not found.", ["id" => $basePath]);
    }

    if (!$ensureFolders) {
        $computedId = $baseFolder["id"] . "/" . $productSlug . "/" . $locale . "/" . $version;
        return [
            "id" => $computedId,
            "name" => $version,
            "parent_id" => $baseFolder["id"] . "/" . $productSlug . "/" . $locale,
            "path" => $computedId,
            "kind" => "system",
            "scope" => "eprel",
            "asset_count" => 0,
            "folder_count" => 0,
            "can_upload" => true,
            "can_create_children" => false,
            "created_at" => null,
            "updated_at" => null,
        ];
    }

    $productFolder = damEnsureFolder($con, $baseFolder["id"], $productSlug, "eprel", 0, 1);
    $localeFolder = damEnsureFolder($con, $productFolder["id"], $locale, "eprel", 0, 1);
    return damEnsureFolder($con, $localeFolder["id"], $version, "eprel", 1, 0);
}

function damFindFamilyFolderByCode($con, string $familyCode): ?array {
    $stmt = damPrepareOrFail(
        $con,
        "SELECT
            `folder_id`,
            `parent_id`,
            `name`,
            `path`,
            `scope`,
            `kind`,
            `can_upload`,
            `can_create_children`,
            `created_at`,
            `updated_at`,
            (SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,
            (SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count`
        FROM `dam_folders`
        WHERE `parent_id` = 'nexled/10_products/families'
          AND LEFT(`name`, CHAR_LENGTH(?) + 1) = CONCAT(?, '_')
        ORDER BY `sort_order` ASC, `name` ASC
        LIMIT 1"
    );
    $params = [$familyCode, $familyCode];
    damBindParams($stmt, "ss", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ? damMapFolderRow($row) : null;
}

function damFamilyFolderNameByCode(string $familyCode): ?string {
    return match ($familyCode) {
        "11" => "11_barra-t5",
        "29" => "29_downlight",
        "30" => "30_downlight",
        "32" => "32_barra-bt",
        "48" => "48_dynamic",
        "49" => "49_shelfled",
        "55" => "55_barra",
        "58" => "58_barra-hot",
        default => null,
    };
}

function damEnsureFamilyFolderByCode($con, string $familyCode): ?array {
    $folderName = damFamilyFolderNameByCode($familyCode);

    if ($folderName === null) {
        return null;
    }

    $baseFolder = damFetchFolderById($con, "nexled/10_products/families");

    if ($baseFolder === null) {
        return null;
    }

    return damEnsureFolder($con, $baseFolder["id"], $folderName, "products", 0, 1);
}
