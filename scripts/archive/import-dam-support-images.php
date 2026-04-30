<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/api/bootstrap.php";

$root = dirname(__DIR__);
$defaultBaseUrl = "http://localhost/api_nexled/api";
$defaultApiKey = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";
$defaultSourceRoot = $root . "/DAM_UPLOAD_SUPPORT_IMAGES";
$defaultOutputPath = $root . "/output/support-dam-images-map.json";

$options = getopt("", ["base-url::", "api-key::", "source::", "output::", "dry-run"]);
$baseUrl = rtrim((string) ($options["base-url"] ?? $defaultBaseUrl), "/");
$apiKey = (string) ($options["api-key"] ?? $defaultApiKey);
$sourceRoot = (string) ($options["source"] ?? $defaultSourceRoot);
$outputPath = (string) ($options["output"] ?? $defaultOutputPath);
$dryRun = array_key_exists("dry-run", $options);

if (!is_dir($sourceRoot)) {
    fwrite(STDERR, "Source folder not found: {$sourceRoot}\n");
    exit(1);
}

$manifest = buildSupportImportManifest($sourceRoot);
if ($manifest["entries"] === []) {
    fwrite(STDERR, "No matching support images found.\n");
    exit(1);
}

$con = connectDBDam();
ensureSupportFolderTree($con, $manifest["folders"]);

$mapping = [];
$summary = [
    "planned" => count($manifest["entries"]),
    "uploaded" => 0,
    "reused" => 0,
    "failed" => 0,
];

foreach ($manifest["entries"] as $entry) {
    $key = $entry["key"];
    $folderId = $entry["folder_id"];
    $filename = basename($entry["source_path"]);

    $existingAsset = damFindAssetByFolderAndFilename($con, $folderId, $filename);
    if ($existingAsset !== null) {
        $mapping[$key] = (string) $existingAsset["secure_url"];
        $summary["reused"] += 1;
        continue;
    }

    if ($dryRun) {
        $mapping[$key] = "";
        continue;
    }

    $uploadResult = uploadDamAsset($baseUrl, $apiKey, $folderId, $entry["source_path"]);
    if ($uploadResult["status"] === "uploaded") {
        $mapping[$key] = (string) $uploadResult["secure_url"];
        $summary["uploaded"] += 1;
        continue;
    }

    if ($uploadResult["status"] === "skipped") {
        $existingAsset = damFindAssetByFolderAndFilename($con, $folderId, $filename);
        if ($existingAsset !== null) {
            $mapping[$key] = (string) $existingAsset["secure_url"];
            $summary["reused"] += 1;
            continue;
        }
    }

    $existingAsset = damFindAssetByFolderAndFilename($con, $folderId, $filename);
    if ($existingAsset !== null) {
        $mapping[$key] = (string) $existingAsset["secure_url"];
        $summary["reused"] += 1;
        continue;
    }

    $summary["failed"] += 1;
    fwrite(STDERR, "[failed] {$key}: {$uploadResult['message']}\n");
}

closeDB($con);

ksort($mapping, SORT_NATURAL | SORT_FLAG_CASE);
$json = json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    fwrite(STDERR, "Failed to encode mapping JSON.\n");
    exit(1);
}

if (!$dryRun) {
    $outputDirectory = dirname($outputPath);
    if (!is_dir($outputDirectory)) {
        mkdir($outputDirectory, 0777, true);
    }

    file_put_contents($outputPath, $json . PHP_EOL);
}

fwrite(STDERR, "planned={$summary['planned']} uploaded={$summary['uploaded']} reused={$summary['reused']} failed={$summary['failed']}\n");
echo $json . PHP_EOL;

exit($summary["failed"] > 0 ? 1 : 0);

function buildSupportImportManifest(string $sourceRoot): array
{
    $entries = [];

    $rootFiles = [
        "DL_Q.webp",
        "DL_R.webp",
        "led_bar.webp",
    ];

    foreach ($rootFiles as $filename) {
        $absolutePath = $sourceRoot . DIRECTORY_SEPARATOR . $filename;
        if (is_file($absolutePath)) {
            $entries[] = [
                "key" => $filename,
                "source_path" => $absolutePath,
                "folder_id" => "nexled/media/support/page-assets/products",
            ];
        }
    }

    $partsFiles = [
        "img/parts/cabo-asqc2-branco.webp",
        "img/screwdriver.webp",
    ];

    foreach ($partsFiles as $relativePath) {
        $absolutePath = $sourceRoot . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $relativePath);
        if (is_file($absolutePath)) {
            $entries[] = [
                "key" => $relativePath,
                "source_path" => $absolutePath,
                "folder_id" => "nexled/media/support/repair-guides/parts",
            ];
        }
    }

    $stepsRoot = $sourceRoot . DIRECTORY_SEPARATOR . "img" . DIRECTORY_SEPARATOR . "photos_step";
    if (is_dir($stepsRoot)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($stepsRoot, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();
            if (!isSupportedImageFilename($filename)) {
                continue;
            }

            $relativeFromSteps = str_replace("\\", "/", $iterator->getSubPathname());
            $key = "img/photos_step/" . $relativeFromSteps;
            $relativeDirectory = trim(str_replace("\\", "/", $iterator->getSubPath()), "/");
            $folderId = "nexled/media/support/repair-guides/steps";
            if ($relativeDirectory !== "") {
                $folderId .= "/" . $relativeDirectory;
            }

            $entries[] = [
                "key" => $key,
                "source_path" => $fileInfo->getPathname(),
                "folder_id" => $folderId,
            ];
        }
    }

    usort($entries, static function (array $left, array $right): int {
        return strnatcasecmp($left["key"], $right["key"]);
    });

    return [
        "entries" => $entries,
        "folders" => buildSupportFolderDefinitions($entries),
    ];
}

function isSupportedImageFilename(string $filename): bool
{
    $normalized = strtolower(trim($filename));
    if ($normalized === "" || $normalized === "favicon.svg") {
        return false;
    }

    $extension = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
    return in_array($extension, ["webp", "png", "jpg", "jpeg", "gif", "svg", "avif"], true);
}

function buildSupportFolderDefinitions(array $entries): array
{
    $definitions = [];

    foreach ($entries as $entry) {
        $folderId = (string) $entry["folder_id"];
        $segments = explode("/", $folderId);

        for ($index = 0; $index < count($segments); $index += 1) {
            $currentFolderId = implode("/", array_slice($segments, 0, $index + 1));
            if ($currentFolderId === "nexled" || $currentFolderId === "nexled/media" || $currentFolderId === "nexled/media/support") {
                continue;
            }

            if (!isset($definitions[$currentFolderId])) {
                $parentId = implode("/", array_slice($segments, 0, $index));
                $definitions[$currentFolderId] = [
                    "id" => $currentFolderId,
                    "parent_id" => $parentId,
                    "name" => $segments[$index],
                    "scope" => "media",
                    "can_upload" => false,
                    "can_create_children" => false,
                ];
            }

            if ($currentFolderId === $folderId) {
                $definitions[$currentFolderId]["can_upload"] = true;
            } else {
                $definitions[$currentFolderId]["can_create_children"] = true;
            }
        }
    }

    $definitions["nexled/media/support/page-assets"] = [
        "id" => "nexled/media/support/page-assets",
        "parent_id" => "nexled/media/support",
        "name" => "page-assets",
        "scope" => "media",
        "can_upload" => true,
        "can_create_children" => true,
    ];

    $definitions["nexled/media/support/repair-guides"] = [
        "id" => "nexled/media/support/repair-guides",
        "parent_id" => "nexled/media/support",
        "name" => "repair-guides",
        "scope" => "media",
        "can_upload" => true,
        "can_create_children" => true,
    ];

    uasort($definitions, static function (array $left, array $right): int {
        $depthCompare = substr_count($left["id"], "/") <=> substr_count($right["id"], "/");
        if ($depthCompare !== 0) {
            return $depthCompare;
        }

        return strnatcasecmp($left["id"], $right["id"]);
    });

    return array_values($definitions);
}

function ensureSupportFolderTree(mysqli $con, array $folders): void
{
    foreach ($folders as $folder) {
        ensureSupportFolder(
            $con,
            (string) $folder["id"],
            (string) $folder["parent_id"],
            (string) $folder["name"],
            (string) $folder["scope"],
            !empty($folder["can_upload"]),
            !empty($folder["can_create_children"])
        );
    }
}

function ensureSupportFolder(mysqli $con, string $folderId, string $parentId, string $name, string $scope, bool $canUpload, bool $canCreateChildren): void
{
    $stmt = mysqli_prepare($con, "SELECT `can_upload`,`can_create_children` FROM `dam_folders` WHERE `folder_id` = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException("Failed to prepare folder lookup.");
    }

    mysqli_stmt_bind_param($stmt, "s", $folderId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    if (is_array($existing)) {
        $nextCanUpload = ((int) ($existing["can_upload"] ?? 0)) || $canUpload ? 1 : 0;
        $nextCanCreateChildren = ((int) ($existing["can_create_children"] ?? 0)) || $canCreateChildren ? 1 : 0;

        $updateStmt = mysqli_prepare($con, "UPDATE `dam_folders` SET `can_upload` = ?, `can_create_children` = ? WHERE `folder_id` = ? LIMIT 1");
        if (!$updateStmt) {
            throw new RuntimeException("Failed to prepare folder update.");
        }

        mysqli_stmt_bind_param($updateStmt, "iis", $nextCanUpload, $nextCanCreateChildren, $folderId);
        mysqli_stmt_execute($updateStmt);
        mysqli_stmt_close($updateStmt);
        return;
    }

    $sortOrder = nextSupportFolderSortOrder($con, $parentId);
    $kind = "system";
    $isSystem = 1;
    $path = $folderId;
    $uploadFlag = $canUpload ? 1 : 0;
    $childrenFlag = $canCreateChildren ? 1 : 0;

    $insertStmt = mysqli_prepare(
        $con,
        "INSERT INTO `dam_folders` (`folder_id`,`parent_id`,`name`,`path`,`scope`,`kind`,`is_system`,`can_upload`,`can_create_children`,`sort_order`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$insertStmt) {
        throw new RuntimeException("Failed to prepare folder insert.");
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        "ssssssiiii",
        $folderId,
        $parentId,
        $name,
        $path,
        $scope,
        $kind,
        $isSystem,
        $uploadFlag,
        $childrenFlag,
        $sortOrder
    );
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);
}

function nextSupportFolderSortOrder(mysqli $con, string $parentId): int
{
    $stmt = mysqli_prepare($con, "SELECT COALESCE(MAX(`sort_order`), 0) + 10 AS `next_sort_order` FROM `dam_folders` WHERE `parent_id` = ?");
    if (!$stmt) {
        throw new RuntimeException("Failed to prepare folder sort-order query.");
    }

    mysqli_stmt_bind_param($stmt, "s", $parentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return isset($row["next_sort_order"]) ? (int) $row["next_sort_order"] : 10;
}

function damFindAssetByFolderAndFilename(mysqli $con, string $folderId, string $filename): ?array
{
    $stmt = mysqli_prepare($con, "SELECT `secure_url` FROM `dam_assets` WHERE `folder_id` = ? AND `filename` = ? LIMIT 1");
    if (!$stmt) {
        throw new RuntimeException("Failed to prepare asset lookup.");
    }

    mysqli_stmt_bind_param($stmt, "ss", $folderId, $filename);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return is_array($row) ? $row : null;
}

function uploadDamAsset(string $baseUrl, string $apiKey, string $folderId, string $filePath): array
{
    $url = $baseUrl . "/?endpoint=dam&action=upload";
    $ch = curl_init($url);

    $postFields = [
        "folder_id" => $folderId,
        "file" => new CURLFile($filePath),
    ];

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-API-Key: " . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawResponse = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($rawResponse === false) {
        return [
            "status" => "failed",
            "message" => $curlError !== "" ? $curlError : "curl failed",
        ];
    }

    $payload = json_decode($rawResponse, true);
    $errorMessage = trim((string) ($payload["error"]["message"] ?? ""));
    $secureUrl = trim((string) ($payload["data"]["asset"]["secure_url"] ?? ""));

    if (($payload["ok"] ?? false) === true && ($httpCode === 200 || $httpCode === 201) && $secureUrl !== "") {
        return [
            "status" => "uploaded",
            "message" => "ok",
            "secure_url" => $secureUrl,
        ];
    }

    if ($httpCode === 409 && $errorMessage !== "") {
        return [
            "status" => "skipped",
            "message" => $errorMessage,
        ];
    }

    return [
        "status" => "failed",
        "message" => $errorMessage !== "" ? $errorMessage : ("HTTP " . $httpCode),
    ];
}
