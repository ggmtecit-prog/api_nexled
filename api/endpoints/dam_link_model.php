<?php
require_once dirname(__FILE__, 2) . "/lib/cloudinary.php";
const DAM_ROOT_FOLDER_ID = "nexled";
const DAM_TREE_DEFAULT_DEPTH = 2;
const DAM_TREE_MAX_DEPTH = 4;
const DAM_LIST_DEFAULT_LIMIT = 60;
const DAM_LIST_MAX_LIMIT = 200;
const DAM_ALLOWED_ROLES = ["packshot","finish","drawing","diagram","diagram-inv","mounting","connector","temperature","energy-label","icon","logo","power-supply","product-photo","lifestyle","datasheet-pdf","eprel-label","eprel-fiche","brand-logo","brand-asset","hero","banner","category","support-asset","web-asset"];
$action = $_GET["action"] ?? null;
$method = $_SERVER["REQUEST_METHOD"] ?? "GET";
switch ($action) {
    case "tree": damRequireMethod(["GET"]); damTree(); break;
    case "list": damRequireMethod(["GET"]); damListContents(); break;
    case "asset":
        if ($method === "GET") { damGetAsset(); break; }
        if ($method === "DELETE") { damDeleteAsset(); break; }
        damMethodNotAllowed(["GET", "DELETE"]);
        break;
    case "create-folder": damRequireMethod(["POST"]); damCreateFolder(); break;
    case "sync-folders": damRequireMethod(["POST"]); damSyncFolders(); break;
    case "upload": damRequireMethod(["POST"]); damUploadAsset(); break;
    case "product-assets": damRequireMethod(["GET"]); damProductAssets(); break;
    case "link": damRequireMethod(["POST"]); damLinkAsset(); break;
    case "unlink": damRequireMethod(["DELETE"]); damUnlinkAsset(); break;
    default: damRespondError(400, "invalid_action", "Invalid or missing action.");
}
function damTree(): void {
    $root = damValidateFolderId($_GET["root"] ?? DAM_ROOT_FOLDER_ID);
    $depth = damClampInt($_GET["depth"] ?? DAM_TREE_DEFAULT_DEPTH, 1, DAM_TREE_MAX_DEPTH, DAM_TREE_DEFAULT_DEPTH);
    if ($root === null) damRespondError(400, "invalid_folder", "Invalid root folder.");
    $con = connectDBDam();
    $rootFolder = damFetchFolderById($con, $root);
    if ($rootFolder === null) { closeDB($con); damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $root]); }
    $folders = damBuildFolderTree($con, $root, $depth);
    closeDB($con);
    damRespondSuccess(["root" => $root, "folders" => $folders]);
}
function damListContents(): void {
    $folderId = damValidateFolderId($_GET["folder_id"] ?? null);
    if ($folderId === null) damRespondError(400, "invalid_folder", "Missing or invalid folder_id.");
    $limit = damClampInt($_GET["limit"] ?? DAM_LIST_DEFAULT_LIMIT, 1, DAM_LIST_MAX_LIMIT, DAM_LIST_DEFAULT_LIMIT);
    $cursor = damOptionalPositiveInt($_GET["cursor"] ?? null);
    $query = trim((string) ($_GET["q"] ?? ""));
    $globalSearch = damNormalizeBool($_GET["global"] ?? null);
    $role = damValidateRole($_GET["role"] ?? null);
    if (isset($_GET["role"]) && trim((string) $_GET["role"]) !== "" && $role === null) damRespondError(400, "invalid_metadata", "Invalid role filter.");
    $con = connectDBDam();
    $folder = damFetchFolderById($con, $folderId);
    if ($folder === null) { closeDB($con); damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $folderId]); }
    $searchScope = ($globalSearch && $query !== "") ? "global" : "folder";
    if ($searchScope === "global") {
        $folders = damSearchFolders($con, $query, $limit);
        [$assets, $hasMore, $nextCursor] = damSearchAssets($con, $query, $role, $cursor, $limit);
    } else {
        $folders = damFetchChildFolders($con, $folderId);
        [$assets, $hasMore, $nextCursor] = damFetchAssets($con, $folderId, $query, $role, $cursor, $limit);
    }
    closeDB($con);
    damRespondSuccess([
        "folder" => $folder,
        "folders" => $folders,
        "assets" => $assets,
        "search_scope" => $searchScope,
        "query" => $query,
        "page" => ["cursor" => $cursor, "next_cursor" => $nextCursor, "limit" => $limit, "has_more" => $hasMore],
    ]);
}
function damGetAsset(): void {
    $assetId = damRequirePositiveInt($_GET["id"] ?? null, "Missing or invalid asset id.");
    $con = connectDBDam();
    $asset = damFetchAssetById($con, $assetId);
    if ($asset === null) { closeDB($con); damRespondError(404, "asset_not_found", "Asset not found.", ["id" => $assetId]); }
    $links = damFetchLinksByAssetId($con, $assetId);
    closeDB($con);
    damRespondSuccess(["asset" => $asset, "links" => $links]);
}
function damDeleteAsset(): void {
    $assetId = damRequirePositiveInt($_GET["id"] ?? null, "Missing or invalid asset id.");
    $con = connectDBDam();
    $asset = damFetchAssetById($con, $assetId);
    if ($asset === null) { closeDB($con); damRespondError(404, "asset_not_found", "Asset not found.", ["id" => $assetId]); }
    if (!cloudinaryDelete($asset["public_id"], $asset["resource_type"])) { closeDB($con); damRespondError(500, "cloudinary_delete_failed", "Cloudinary delete failed."); }
    $stmt = damPrepareOrFail($con, "DELETE FROM `dam_assets` WHERE `id` = ?");
    $params = [$assetId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
    closeDB($con);
    damRespondSuccess(["deleted" => true, "id" => $assetId]);
}
function damCreateFolder(): void {
    $body = damGetJsonBody();
    $parentId = damValidateFolderId($body["parent_id"] ?? null);
    $name = damNormalizeName($body["name"] ?? "");
    if ($parentId === null) damRespondError(400, "invalid_folder", "Missing or invalid parent_id.");
    if ($name === "") damRespondError(400, "invalid_metadata", "Folder name is required.");
    $con = connectDBDam();
    $parent = damFetchFolderById($con, $parentId);
    if ($parent === null) { closeDB($con); damRespondError(404, "folder_not_found", "Parent folder not found.", ["id" => $parentId]); }
    if (!$parent["can_create_children"]) { closeDB($con); damRespondError(409, "invalid_folder", "Folder does not allow child creation.", ["id" => $parentId]); }
    $folderId = $parent["id"] . "/" . $name;
    $existing = damFetchFolderById($con, $folderId);
    if ($existing !== null) { closeDB($con); damRespondError(409, "invalid_folder", "Folder already exists.", ["id" => $folderId]); }
    $folder = damInsertFolder($con, $parent["id"], $name, $parent["scope"], "custom", 0, 1, 1, damNextFolderSortOrder($con, $parent["id"]));
    closeDB($con);
    damRespondSuccess(["folder" => $folder], 201);
}
function damSyncFolders(): void {
    $body = damGetJsonBody();
    $rootId = damValidateFolderId($body["root_id"] ?? DAM_ROOT_FOLDER_ID);
    if ($rootId === null) damRespondError(400, "invalid_folder", "Invalid root_id.");
    $con = connectDBDam();
    $rootFolder = damFetchFolderById($con, $rootId);
    if ($rootFolder === null) { closeDB($con); damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $rootId]); }
    $folders = damFetchFoldersForCloudinarySync($con, $rootId);
    closeDB($con);
    $results = [];
    $createdCount = 0;
    $existingCount = 0;
    $failedCount = 0;
    foreach ($folders as $folder) {
        $syncResult = cloudinaryCreateFolderDetailed($folder["path"]);
        if ($syncResult["ok"] ?? false) {
            if ($syncResult["created"] ?? false) $createdCount += 1; else $existingCount += 1;
        } else {
            $failedCount += 1;
        }
        $results[] = ["folder_id" => $folder["id"], "path" => $folder["path"], "ok" => (bool) ($syncResult["ok"] ?? false), "created" => (bool) ($syncResult["created"] ?? false), "already_exists" => (bool) ($syncResult["already_exists"] ?? false), "http_code" => $syncResult["http_code"] ?? null, "error" => $syncResult["error"] ?? null];
    }
    damRespondSuccess(["root_id" => $rootId, "summary" => ["total" => count($results), "created" => $createdCount, "already_exists" => $existingCount, "failed" => $failedCount], "folders" => $results]);
}
function damUploadAsset(): void {
    if (empty($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) damRespondError(400, "invalid_metadata", "No file uploaded or upload error.");
    $folderId = damValidateFolderId($_POST["folder_id"] ?? null);
    if ($folderId === null) damRespondError(400, "invalid_folder", "Missing or invalid folder_id.");
    $con = connectDBDam();
    $folder = damFetchFolderById($con, $folderId);
    if ($folder === null) { closeDB($con); damRespondError(404, "folder_not_found", "Folder not found.", ["id" => $folderId]); }
    if (!$folder["can_upload"]) { closeDB($con); damRespondError(409, "invalid_folder", "Folder does not allow uploads.", ["id" => $folderId]); }
    $isCustomDatasheetUpload = damIsCustomDatasheetFolderId((string) ($folder["id"] ?? ""));
    $providedKindRaw = trim((string) ($_POST["kind"] ?? ""));
    $providedKind = damValidateRole($providedKindRaw !== "" ? $providedKindRaw : null);
    if ($providedKindRaw !== "" && $providedKind === null) { closeDB($con); damRespondError(400, "invalid_metadata", "Invalid role. Use one of: " . implode(", ", DAM_ALLOWED_ROLES)); }
    $inferredKind = damInferRoleFromFolder($folder["id"]);
    $kind = $providedKind ?? $inferredKind;
    if ($kind === null) { closeDB($con); damRespondError(400, "invalid_metadata", "Unable to infer role from folder. Provide kind explicitly."); }
    if ($providedKind !== null && $inferredKind !== null && $providedKind !== $inferredKind) { closeDB($con); damRespondError(400, "invalid_metadata", "Provided role does not match folder role.", ["provided" => $providedKind, "expected" => $inferredKind]); }
    $file = $_FILES["file"];
    $originalFilename = damSafeFilename($file["name"] ?? "asset");
    $displayName = substr($originalFilename, 0, 255);
    // Keep extension signal in the Cloudinary public_id so PNG/SVG pairs can coexist.
    $publicId = damBuildPublicId($folder["path"], $displayName);
    $tags = damParseTagsInput($_POST["tags"] ?? null);
    $resourceType = damDetectResourceType($displayName, (string) ($file["type"] ?? ""));
    $format = strtolower(pathinfo($displayName, PATHINFO_EXTENSION));
    if ($tags === null) { closeDB($con); damRespondError(400, "invalid_metadata", "Tags must be valid JSON array."); }
    if ($isCustomDatasheetUpload) {
        $tags = array_values(array_unique(array_merge($tags, ["custom-datasheet", "isolated-upload", "no-product-link"])));
    }
    $duplicateStmt = damPrepareOrFail($con, "SELECT `id` FROM `dam_assets` WHERE `folder_id` = ? AND `filename` = ? LIMIT 1");
    $duplicateParams = [$folder["id"], $displayName];
    damBindParams($duplicateStmt, "ss", $duplicateParams);
    damExecuteOrFail($duplicateStmt, $con);
    $duplicateResult = mysqli_stmt_get_result($duplicateStmt);
    $duplicate = $duplicateResult ? mysqli_fetch_assoc($duplicateResult) : null;
    mysqli_stmt_close($duplicateStmt);
    if ($duplicate !== null) { closeDB($con); damRespondError(409, "invalid_metadata", "Asset with same filename already exists in folder.", ["id" => (int) $duplicate["id"]]); }
    $uploadOutcome = cloudinaryUploadDetailed($file["tmp_name"], $publicId, $resourceType, [
        "asset_folder" => $folder["path"],
        "display_name" => $displayName,
        "overwrite" => false,
        "tags" => $tags,
    ]);
    if (!($uploadOutcome["ok"] ?? false)) { closeDB($con); damRespondError(500, "cloudinary_upload_failed", (string) ($uploadOutcome["error"] ?? "Cloudinary upload failed."), ["http_code" => $uploadOutcome["http_code"] ?? null]); }
    $uploadResult = $uploadOutcome["data"] ?? null;
    $secureUrl = $uploadResult["secure_url"] ?? null;
    if (!is_string($secureUrl) || $secureUrl === "") { closeDB($con); damRespondError(500, "cloudinary_upload_failed", "Cloudinary response missing secure_url."); }
    $storedPublicId = (string) ($uploadResult["public_id"] ?? $publicId);
    $storedResourceType = (string) ($uploadResult["resource_type"] ?? $resourceType);
    $storedFormat = (string) ($uploadResult["format"] ?? $format);
    $bytes = isset($uploadResult["bytes"]) ? (int) $uploadResult["bytes"] : null;
    $width = isset($uploadResult["width"]) ? (int) $uploadResult["width"] : null;
    $height = isset($uploadResult["height"]) ? (int) $uploadResult["height"] : null;
    $thumbnailUrl = $storedResourceType === "image" ? $secureUrl : null;
    $mimeType = trim((string) ($file["type"] ?? ""));
    $tagsJson = $tags === [] ? null : json_encode($tags);
    $stmt = damPrepareOrFail($con, "INSERT INTO `dam_assets` (`filename`,`display_name`,`public_id`,`folder_id`,`resource_type`,`format`,`mime_type`,`bytes`,`width`,`height`,`secure_url`,`thumbnail_url`,`kind`,`tags`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $insertParams = [$displayName, $displayName, $storedPublicId, $folder["id"], $storedResourceType, $storedFormat, $mimeType !== "" ? $mimeType : null, $bytes, $width, $height, $secureUrl, $thumbnailUrl, $kind, $tagsJson];
    damBindParams($stmt, "sssssssiiissss", $insertParams);
    damExecuteOrFail($stmt, $con);
    $assetId = (int) mysqli_insert_id($con);
    mysqli_stmt_close($stmt);
    $asset = damFetchAssetById($con, $assetId);
    closeDB($con);
    damRespondSuccess(["asset" => $asset], 201);
}
function damProductAssets(): void {
    $productCode = damNormalizeProductCode($_GET["product_code"] ?? null);
    $familyCode = damNormalizeFamilyCode($_GET["family_code"] ?? null);
    $role = damValidateRole($_GET["role"] ?? null);
    $format = isset($_GET["format"]) ? strtolower(trim((string) $_GET["format"])) : null;
    if ($productCode === null && $familyCode === null) damRespondError(400, "invalid_metadata", "Provide product_code or family_code.");
    if (isset($_GET["role"]) && trim((string) $_GET["role"]) !== "" && $role === null) damRespondError(400, "invalid_metadata", "Invalid role filter.");
    $con = connectDBDam();
    $sql = "SELECT a.`id`,a.`folder_id`,a.`display_name`,a.`filename`,a.`public_id`,a.`resource_type`,a.`format`,a.`mime_type`,a.`bytes`,a.`width`,a.`height`,a.`secure_url`,a.`thumbnail_url`,a.`kind`,a.`tags`,a.`created_at`,a.`updated_at`,l.`id` AS `link_id`,l.`role`,l.`sort_order`,l.`product_code`,l.`family_code` FROM `dam_asset_links` l JOIN `dam_assets` a ON a.`id` = l.`asset_id` WHERE 1=1";
    $types = "";
    $params = [];
    if ($productCode !== null) { $sql .= " AND l.`product_code` = ?"; $types .= "s"; $params[] = $productCode; }
    if ($familyCode !== null) { $sql .= " AND l.`family_code` = ?"; $types .= "s"; $params[] = $familyCode; }
    if ($role !== null) { $sql .= " AND l.`role` = ?"; $types .= "s"; $params[] = $role; }
    if ($format !== null && $format !== "") { $sql .= " AND a.`format` = ?"; $types .= "s"; $params[] = $format; }
    $sql .= " ORDER BY l.`role` ASC, l.`sort_order` ASC, a.`id` ASC";
    $stmt = damPrepareOrFail($con, $sql);
    if ($types !== "") damBindParams($stmt, $types, $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $assets = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $asset = damMapAssetRow($row);
            $asset["link_id"] = isset($row["link_id"]) ? (int) $row["link_id"] : null;
            $asset["link_role"] = $row["role"] ?? null;
            $asset["link_sort_order"] = isset($row["sort_order"]) ? (int) $row["sort_order"] : 0;
            $asset["link_product_code"] = $row["product_code"] ?? null;
            $asset["link_family_code"] = $row["family_code"] ?? null;
            $assets[] = $asset;
        }
    }
    mysqli_stmt_close($stmt);
    closeDB($con);
    damRespondSuccess(["product_code" => $productCode, "family_code" => $familyCode, "role" => $role, "format" => $format, "assets" => $assets]);
}
function damLinkAsset(): void {
    $body = damGetJsonBody();
    $assetId = damOptionalPositiveInt($body["asset_id"] ?? null);
    if ($assetId === null) damRespondError(400, "invalid_metadata", "Missing or invalid asset_id.");
    $role = damValidateRole($body["role"] ?? null);
    if ($role === null) damRespondError(400, "invalid_metadata", "Missing or invalid role. Use one of: " . implode(", ", DAM_ALLOWED_ROLES));
    $productCode = damNormalizeProductCode($body["product_code"] ?? null);
    $familyCode = damNormalizeFamilyCode($body["family_code"] ?? null);
    $sortOrder = (int) ($body["sort_order"] ?? 0);
    $con = connectDBDam();
    $asset = damFetchAssetById($con, $assetId);
    if ($asset === null) { closeDB($con); damRespondError(404, "asset_not_found", "Asset not found.", ["id" => $assetId]); }
    if (damIsCustomDatasheetFolderId((string) ($asset["folder_id"] ?? ""))) {
        closeDB($con);
        damRespondError(409, "custom_asset_link_forbidden", "Custom datasheet uploads cannot be linked to products or families.", ["id" => $assetId]);
    }
    $existingStmt = damPrepareOrFail($con, "SELECT `id` FROM `dam_asset_links` WHERE `asset_id` = ? AND `role` = ? AND `product_code` <=> ? AND `family_code` <=> ? LIMIT 1");
    $existingParams = [$assetId, $role, $productCode, $familyCode];
    damBindParams($existingStmt, "isss", $existingParams);
    damExecuteOrFail($existingStmt, $con);
    $existingResult = mysqli_stmt_get_result($existingStmt);
    $existingRow = $existingResult ? mysqli_fetch_assoc($existingResult) : null;
    mysqli_stmt_close($existingStmt);
    $status = 201;
    if ($existingRow !== null) {
        $linkId = (int) $existingRow["id"];
        $updateStmt = damPrepareOrFail($con, "UPDATE `dam_asset_links` SET `sort_order` = ? WHERE `id` = ?");
        $updateParams = [$sortOrder, $linkId];
        damBindParams($updateStmt, "ii", $updateParams);
        damExecuteOrFail($updateStmt, $con);
        mysqli_stmt_close($updateStmt);
        $status = 200;
    } else {
        $insertStmt = damPrepareOrFail($con, "INSERT INTO `dam_asset_links` (`asset_id`,`product_code`,`family_code`,`role`,`sort_order`) VALUES (?, ?, ?, ?, ?)");
        $insertParams = [$assetId, $productCode, $familyCode, $role, $sortOrder];
        damBindParams($insertStmt, "isssi", $insertParams);
        damExecuteOrFail($insertStmt, $con);
        $linkId = (int) mysqli_insert_id($con);
        mysqli_stmt_close($insertStmt);
    }
    closeDB($con);
    damRespondSuccess(["id" => $linkId, "asset_id" => $assetId, "product_code" => $productCode, "family_code" => $familyCode, "role" => $role, "sort_order" => $sortOrder], $status);
}
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
    if ($row === null) { closeDB($con); damRespondError(404, "link_not_found", "Link not found.", ["id" => $linkId]); }
    $stmt = damPrepareOrFail($con, "DELETE FROM `dam_asset_links` WHERE `id` = ?");
    $params = [$linkId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
    closeDB($con);
    damRespondSuccess(["deleted" => true, "id" => $linkId]);
}
function damRequireMethod(array $allowedMethods): void { $method = $_SERVER["REQUEST_METHOD"] ?? "GET"; if (!in_array($method, $allowedMethods, true)) damMethodNotAllowed($allowedMethods); }
function damMethodNotAllowed(array $allowedMethods): void { header("Allow: " . implode(", ", $allowedMethods)); damRespondError(405, "method_not_allowed", "Method not allowed.", ["allowed" => $allowedMethods]); }
function damGetJsonBody(): array {
    $raw = file_get_contents("php://input");
    if (!is_string($raw) || trim($raw) === "") damRespondError(400, "invalid_metadata", "Missing request body.");
    $data = json_decode($raw, true);
    if (!is_array($data)) damRespondError(400, "invalid_metadata", "Invalid JSON body.");
    return $data;
}
function damRespondSuccess(array $data, int $status = 200): void { http_response_code($status); echo json_encode(["ok" => true, "data" => $data]); exit(); }
function damRespondError(int $status, string $code, string $message, array $details = []): void {
    http_response_code($status);
    echo json_encode(["ok" => false, "error" => ["code" => $code, "message" => $message, "details" => (object) $details]]);
    exit();
}
function damClampInt($value, int $min, int $max, int $default): int { if (!is_numeric($value)) return $default; $intValue = (int) $value; return max($min, min($max, $intValue)); }
function damNormalizeBool($value): bool {
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return (int) $value === 1;
    if (!is_string($value)) return false;
    $normalized = strtolower(trim($value));
    return in_array($normalized, ["1", "true", "yes", "on"], true);
}
function damOptionalPositiveInt($value): ?int { if ($value === null || $value === "" || !is_numeric($value)) return null; $intValue = (int) $value; return $intValue > 0 ? $intValue : null; }
function damRequirePositiveInt($value, string $message): int { $intValue = damOptionalPositiveInt($value); if ($intValue === null) damRespondError(400, "invalid_metadata", $message); return $intValue; }
function damValidateFolderId($folderId): ?string {
    if (!is_string($folderId)) return null;
    $folderId = trim($folderId);
    if ($folderId === "" || str_contains($folderId, "..") || str_contains($folderId, "//")) return null;
    return preg_match("/^nexled(?:\/[A-Za-z0-9][A-Za-z0-9_-]*)*$/", $folderId) === 1 ? $folderId : null;
}
function damValidateRole(?string $role): ?string { if (!is_string($role) || trim($role) === "") return null; $role = trim($role); return in_array($role, DAM_ALLOWED_ROLES, true) ? $role : null; }
function damNormalizeName($value): string { $value = strtolower(trim((string) $value)); $value = preg_replace("/[^a-z0-9]+/", "-", $value) ?? ""; $value = trim($value, "-"); return substr($value, 0, 80); }
function damNormalizeFamilyCode($value): ?string { if (!is_string($value) && !is_numeric($value)) return null; $familyCode = preg_replace("/[^0-9]/", "", (string) $value) ?? ""; return $familyCode !== "" ? $familyCode : null; }
function damNormalizeProductCode($value): ?string { if (!is_string($value) && !is_numeric($value)) return null; $productCode = preg_replace("/[^a-zA-Z0-9_-]/", "", (string) $value) ?? ""; return $productCode !== "" ? substr($productCode, 0, 64) : null; }
function damIsCustomDatasheetFolderId(string $folderId): bool {
    $normalized = trim(str_replace("\\", "/", $folderId), "/");
    return $normalized === "nexled/media/custom-datasheet"
        || str_starts_with($normalized, "nexled/media/custom-datasheet/");
}
function damSafeFilename(string $filename): string {
    $filename = trim($filename);
    if ($filename === "") return "asset";
    $filename = preg_replace("/[\\\\\\/]+/", "-", $filename) ?? "asset";
    $filename = preg_replace("/[^a-zA-Z0-9._ -]/", "-", $filename) ?? "asset";
    $filename = preg_replace("/\\s+/", "-", $filename) ?? "asset";
    $filename = trim($filename, "-.");
    return $filename !== "" ? substr($filename, 0, 255) : "asset";
}
function damSanitizePublicIdSegment(string $value): string { $value = strtolower(trim($value)); $value = preg_replace("/[^a-z0-9]+/", "_", $value) ?? ""; $value = trim($value, "_"); return $value !== "" ? $value : "asset"; }
function damBuildPublicId(string $folderPath, string $assetName): string {
    $segments = array_values(array_filter(explode("/", $folderPath)));
    $tail = array_slice($segments, -4);
    $flatTail = implode("_", array_map("damSanitizePublicIdSegment", $tail));
    $base = damSanitizePublicIdSegment($assetName);
    $hash = substr(sha1($folderPath . "|" . $assetName), 0, 8);
    return substr("dam_" . trim($flatTail . "_" . $base . "_" . $hash, "_"), 0, 255);
}
function damInferRoleFromFolder(string $folderId): ?string {
    if (str_contains($folderId, "media/brand/logos")) return "brand-logo";
    $map = ["packshots" => "packshot","finishes" => "finish","drawings" => "drawing","diagrams" => "diagram","inverted" => "diagram-inv","mounting" => "mounting","connectors" => "connector","temperatures" => "temperature","energy-labels" => "energy-label","icons" => "icon","logos" => "logo","power-supplies" => "power-supply","products" => "product-photo","lifestyle" => "lifestyle","datasheets" => "datasheet-pdf","labels" => "eprel-label","fiches" => "eprel-fiche","guidelines" => "brand-asset","presentations" => "brand-asset","hero" => "hero","banners" => "banner","categories" => "category","repair-guides" => "support-asset","page-assets" => "support-asset","hub" => "web-asset","landing-pages" => "web-asset"];
    $segments = explode("/", $folderId);
    for ($i = count($segments) - 1; $i >= 0; $i--) if (isset($map[$segments[$i]])) return $map[$segments[$i]];
    return null;
}
function damDetectResourceType(string $filename, string $mimeType): string { $mimeType = strtolower(trim($mimeType)); $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION)); if (strpos($mimeType, "image/") === 0 || in_array($extension, ["jpg","jpeg","png","webp","gif","svg","avif"], true)) return "image"; if (strpos($mimeType, "video/") === 0 || in_array($extension, ["mp4","mov","webm","avi","mkv"], true)) return "video"; return "raw"; }
function damParseTagsInput($raw): ?array {
    if ($raw === null || $raw === "") return [];
    if (is_array($raw)) return array_values(array_map("strval", $raw));
    if (!is_string($raw)) return null;
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) return null;
    $tags = [];
    foreach ($decoded as $tag) if (is_string($tag) && trim($tag) !== "") $tags[] = substr(trim($tag), 0, 64);
    return $tags;
}
function damPrepareOrFail($con, string $sql) { $stmt = mysqli_prepare($con, $sql); if ($stmt === false) damRespondError(500, "database_error", "Database query preparation failed.", ["mysql" => mysqli_error($con)]); return $stmt; }
function damExecuteOrFail($stmt, $con): void { if (!mysqli_stmt_execute($stmt)) damRespondError(500, "database_error", "Database query failed.", ["mysql" => mysqli_error($con)]); }
function damBindParams($stmt, string $types, array &$params): void { if ($types === "") return; $bindArgs = [$stmt, $types]; foreach ($params as $key => &$value) $bindArgs[] = &$value; call_user_func_array("mysqli_stmt_bind_param", $bindArgs); }
function damDecodeJsonColumn($value, $fallback) { if (!is_string($value) || trim($value) === "") return $fallback; $decoded = json_decode($value, true); return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback; }
function damFetchFolderById($con, string $folderId): ?array {
    $stmt = damPrepareOrFail($con, "SELECT `folder_id`,`parent_id`,`name`,`path`,`scope`,`kind`,`can_upload`,`can_create_children`,`created_at`,`updated_at`,(SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,(SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count` FROM `dam_folders` WHERE `folder_id` = ? LIMIT 1");
    $params = [$folderId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ? damMapFolderRow($row) : null;
}
function damFetchChildFolders($con, string $parentId): array {
    $stmt = damPrepareOrFail($con, "SELECT `folder_id`,`parent_id`,`name`,`path`,`scope`,`kind`,`can_upload`,`can_create_children`,`created_at`,`updated_at`,(SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,(SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count` FROM `dam_folders` WHERE `parent_id` = ? ORDER BY `sort_order` ASC, `name` ASC");
    $params = [$parentId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $folders = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) { $folder = damMapFolderRow($row); $folder["children"] = []; $folders[] = $folder; }
    mysqli_stmt_close($stmt);
    return $folders;
}
function damFetchFoldersForCloudinarySync($con, string $rootId): array {
    $likePath = $rootId . "/%";
    $stmt = damPrepareOrFail($con, "SELECT `folder_id`,`parent_id`,`name`,`path`,`scope`,`kind`,`can_upload`,`can_create_children`,`created_at`,`updated_at`,0 AS `folder_count`,0 AS `asset_count` FROM `dam_folders` WHERE `folder_id` = ? OR `path` LIKE ? ORDER BY LENGTH(`path`) ASC, `path` ASC");
    $params = [$rootId, $likePath];
    damBindParams($stmt, "ss", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $folders = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $folders[] = damMapFolderRow($row);
    mysqli_stmt_close($stmt);
    return $folders;
}
function damBuildFolderTree($con, string $parentId, int $depth): array { $children = damFetchChildFolders($con, $parentId); if ($depth <= 1) return $children; foreach ($children as $index => $child) $children[$index]["children"] = damBuildFolderTree($con, $child["id"], $depth - 1); return $children; }
function damFetchAssets($con, string $folderId, string $query, ?string $kind, ?int $cursor, int $limit): array {
    $sql = "SELECT `id`,`folder_id`,`display_name`,`filename`,`public_id`,`resource_type`,`format`,`mime_type`,`bytes`,`width`,`height`,`secure_url`,`thumbnail_url`,`kind`,`tags`,`created_at`,`updated_at` FROM `dam_assets` WHERE `folder_id` = ?";
    $types = "s";
    $params = [$folderId];
    if ($query !== "") { $sql .= " AND (`display_name` LIKE ? OR `filename` LIKE ?)"; $like = "%" . substr($query, 0, 120) . "%"; $types .= "ss"; $params[] = $like; $params[] = $like; }
    if ($kind !== null) { $sql .= " AND `kind` = ?"; $types .= "s"; $params[] = $kind; }
    if ($cursor !== null) { $sql .= " AND `id` < ?"; $types .= "i"; $params[] = $cursor; }
    $sql .= " ORDER BY `id` DESC LIMIT " . ($limit + 1);
    $stmt = damPrepareOrFail($con, $sql);
    damBindParams($stmt, $types, $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $assets = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $assets[] = damMapAssetRow($row);
    mysqli_stmt_close($stmt);
    $hasMore = count($assets) > $limit;
    $nextCursor = null;
    if ($hasMore) { $nextRow = array_pop($assets); $nextCursor = $nextRow["id"]; }
    return [$assets, $hasMore, $nextCursor];
}
function damSearchFolders($con, string $query, int $limit): array {
    $needle = trim($query);
    if ($needle === "") return [];
    $like = "%" . substr($needle, 0, 120) . "%";
    $stmt = damPrepareOrFail($con, "SELECT `folder_id`,`parent_id`,`name`,`path`,`scope`,`kind`,`can_upload`,`can_create_children`,`created_at`,`updated_at`,(SELECT COUNT(*) FROM `dam_folders` AS `children` WHERE `children`.`parent_id` = `dam_folders`.`folder_id`) AS `folder_count`,(SELECT COUNT(*) FROM `dam_assets` AS `assets` WHERE `assets`.`folder_id` = `dam_folders`.`folder_id`) AS `asset_count` FROM `dam_folders` WHERE (`name` LIKE ? OR `path` LIKE ?) ORDER BY CASE WHEN `name` LIKE ? THEN 0 ELSE 1 END, LENGTH(`path`) ASC, `path` ASC LIMIT ?");
    $startsWith = substr($needle, 0, 120) . "%";
    $searchLimit = max(1, min($limit, 80));
    $params = [$like, $like, $startsWith, $searchLimit];
    damBindParams($stmt, "sssi", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $folders = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) { $folder = damMapFolderRow($row); $folder["children"] = []; $folders[] = $folder; }
    mysqli_stmt_close($stmt);
    return $folders;
}
function damSearchAssets($con, string $query, ?string $kind, ?int $cursor, int $limit): array {
    $needle = trim($query);
    if ($needle === "") return [[], false, null];
    $sql = "SELECT a.`id`,a.`folder_id`,a.`display_name`,a.`filename`,a.`public_id`,a.`resource_type`,a.`format`,a.`mime_type`,a.`bytes`,a.`width`,a.`height`,a.`secure_url`,a.`thumbnail_url`,a.`kind`,a.`tags`,a.`created_at`,a.`updated_at`,f.`path` AS `folder_path`,f.`name` AS `folder_name` FROM `dam_assets` a INNER JOIN `dam_folders` f ON f.`folder_id` = a.`folder_id` WHERE (`display_name` LIKE ? OR `filename` LIKE ? OR f.`path` LIKE ?)";
    $like = "%" . substr($needle, 0, 120) . "%";
    $types = "sss";
    $params = [$like, $like, $like];
    if ($kind !== null) { $sql .= " AND a.`kind` = ?"; $types .= "s"; $params[] = $kind; }
    if ($cursor !== null) { $sql .= " AND a.`id` < ?"; $types .= "i"; $params[] = $cursor; }
    $sql .= " ORDER BY a.`id` DESC LIMIT " . ($limit + 1);
    $stmt = damPrepareOrFail($con, $sql);
    damBindParams($stmt, $types, $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $assets = [];
    if ($result) while ($row = mysqli_fetch_assoc($result)) $assets[] = damMapAssetRow($row);
    mysqli_stmt_close($stmt);
    $hasMore = count($assets) > $limit;
    $nextCursor = null;
    if ($hasMore) { $nextRow = array_pop($assets); $nextCursor = $nextRow["id"]; }
    return [$assets, $hasMore, $nextCursor];
}
function damFetchAssetById($con, int $assetId): ?array {
    $stmt = damPrepareOrFail($con, "SELECT `id`,`folder_id`,`display_name`,`filename`,`public_id`,`resource_type`,`format`,`mime_type`,`bytes`,`width`,`height`,`secure_url`,`thumbnail_url`,`kind`,`tags`,`created_at`,`updated_at` FROM `dam_assets` WHERE `id` = ? LIMIT 1");
    $params = [$assetId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return $row ? damMapAssetRow($row) : null;
}
function damFetchLinksByAssetId($con, int $assetId): array {
    $stmt = damPrepareOrFail($con, "SELECT `id`,`role`,`sort_order`,`product_code`,`family_code`,`created_at` FROM `dam_asset_links` WHERE `asset_id` = ? ORDER BY `role` ASC, `sort_order` ASC, `id` ASC");
    $params = [$assetId];
    damBindParams($stmt, "i", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $links = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $links[] = [
                "id" => isset($row["id"]) ? (int) $row["id"] : null,
                "role" => $row["role"] ?? null,
                "sort_order" => isset($row["sort_order"]) ? (int) $row["sort_order"] : 0,
                "product_code" => $row["product_code"] ?? null,
                "family_code" => $row["family_code"] ?? null,
                "created_at" => $row["created_at"] ?? null,
            ];
        }
    }
    mysqli_stmt_close($stmt);
    return $links;
}
function damMapFolderRow(array $row): array { return ["id" => $row["folder_id"], "name" => $row["name"], "parent_id" => $row["parent_id"], "path" => $row["path"], "kind" => $row["kind"], "scope" => $row["scope"], "asset_count" => isset($row["asset_count"]) ? (int) $row["asset_count"] : 0, "folder_count" => isset($row["folder_count"]) ? (int) $row["folder_count"] : 0, "can_upload" => !empty($row["can_upload"]), "can_create_children" => !empty($row["can_create_children"]), "created_at" => $row["created_at"] ?? null, "updated_at" => $row["updated_at"] ?? null]; }
function damMapAssetRow(array $row): array { return ["id" => (int) $row["id"], "folder_id" => $row["folder_id"], "filename" => $row["filename"], "display_name" => $row["display_name"], "public_id" => $row["public_id"], "asset_folder" => $row["folder_id"], "folder_path" => $row["folder_path"] ?? ($row["folder_id"] ?? null), "folder_name" => $row["folder_name"] ?? null, "resource_type" => $row["resource_type"], "format" => $row["format"], "mime_type" => $row["mime_type"], "bytes" => isset($row["bytes"]) ? (int) $row["bytes"] : null, "width" => isset($row["width"]) ? (int) $row["width"] : null, "height" => isset($row["height"]) ? (int) $row["height"] : null, "secure_url" => $row["secure_url"], "thumbnail_url" => $row["thumbnail_url"], "kind" => $row["kind"], "tags" => damDecodeJsonColumn($row["tags"] ?? null, []), "created_at" => $row["created_at"] ?? null, "updated_at" => $row["updated_at"] ?? null]; }
function damNextFolderSortOrder($con, string $parentId): int {
    $stmt = damPrepareOrFail($con, "SELECT COALESCE(MAX(`sort_order`), 0) + 10 AS `next_sort_order` FROM `dam_folders` WHERE `parent_id` = ?");
    $params = [$parentId];
    damBindParams($stmt, "s", $params);
    damExecuteOrFail($stmt, $con);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    return isset($row["next_sort_order"]) ? (int) $row["next_sort_order"] : 10;
}
function damDeleteFolderRecord($con, string $folderId): void { $stmt = damPrepareOrFail($con, "DELETE FROM `dam_folders` WHERE `folder_id` = ? LIMIT 1"); $params = [$folderId]; damBindParams($stmt, "s", $params); damExecuteOrFail($stmt, $con); mysqli_stmt_close($stmt); }
function damInsertFolder($con, string $parentId, string $name, string $scope, string $kind, int $isSystem, int $canUpload, int $canCreateChildren, int $sortOrder): array {
    $folderId = $parentId . "/" . $name;
    $stmt = damPrepareOrFail($con, "INSERT INTO `dam_folders` (`folder_id`,`parent_id`,`name`,`path`,`scope`,`kind`,`is_system`,`can_upload`,`can_create_children`,`sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $params = [$folderId, $parentId, $name, $folderId, $scope, $kind, $isSystem, $canUpload, $canCreateChildren, $sortOrder];
    damBindParams($stmt, "ssssssiiii", $params);
    damExecuteOrFail($stmt, $con);
    mysqli_stmt_close($stmt);
    $folder = damFetchFolderById($con, $folderId);
    if ($folder === null) damRespondError(500, "database_error", "Folder creation failed.");
    return $folder;
}
function damEnsureFolder($con, string $parentId, string $name, string $scope, int $canUpload, int $canCreateChildren): array {
    $folderId = $parentId . "/" . $name;
    $existing = damFetchFolderById($con, $folderId);
    if ($existing !== null) return $existing;
    return damInsertFolder($con, $parentId, $name, $scope, "system", 1, $canUpload, $canCreateChildren, damNextFolderSortOrder($con, $parentId));
}
