<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$defaultBaseUrl = "http://127.0.0.1:8099/api/";
$defaultApiKey = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";

$options = getopt("", ["family::", "base-url::", "api-key::", "dry-run"]);
$familyCode = preg_replace("/[^0-9]/", "", (string) ($options["family"] ?? "11")) ?? "";
$baseUrl = rtrim((string) ($options["base-url"] ?? $defaultBaseUrl), "/");
$apiKey = (string) ($options["api-key"] ?? $defaultApiKey);
$dryRun = array_key_exists("dry-run", $options);

if ($familyCode === "") {
    fwrite(STDERR, "Missing or invalid --family value.\n");
    exit(1);
}

$lensMap = [
    "20Â°" => "20deg",
    "45Â°" => "45deg",
    "2x55Â°lf" => "2x55deg-lf",
    "40Â°" => "40deg",
    "frost" => "frost",
    "frostc" => "frostc",
    "generic" => "generic",
    "clear" => "clear",
    "clear/1" => "clear-1",
    "clear/2" => "clear-2",
    "clear/4" => "clear-4",
    "clear/5" => "clear-5",
    "clear/6" => "clear-6",
];

$batches = [
    [
        "label" => "packshots",
        "role" => "packshot",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/produto",
        "folder_prefix" => "nexled/datasheet/packshots",
    ],
    [
        "label" => "finishes",
        "role" => "finish",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/acabamentos",
        "folder_prefix" => "nexled/datasheet/finishes",
    ],
];

$summary = [
    "planned" => 0,
    "uploaded" => 0,
    "reused" => 0,
    "linked" => 0,
    "failed" => 0,
];

foreach ($batches as $batch) {
    $entries = collectFamilyEntries(
        (string) $batch["source_dir"],
        (string) $batch["folder_prefix"],
        (string) $batch["role"],
        $lensMap
    );
    $summary["planned"] += count($entries);

    echo "== {$batch['label']} -> {$batch['folder_prefix']} (" . count($entries) . " files)\n";

    foreach ($entries as $entry) {
        echo " - {$entry['relative_path']} => {$entry['folder_id']}";

        if ($dryRun) {
            echo " [dry-run]\n";
            continue;
        }

        $uploadResult = uploadOrReuseDamAsset($baseUrl, $apiKey, $entry["folder_id"], $entry["file_path"], $entry["filename"]);

        if ($uploadResult["status"] === "failed") {
            $summary["failed"] += 1;
            echo " [failed upload: {$uploadResult['message']}]\n";
            continue;
        }

        if ($uploadResult["status"] === "uploaded") {
            $summary["uploaded"] += 1;
        } else {
            $summary["reused"] += 1;
        }

        $linkResult = linkDamAsset($baseUrl, $apiKey, (int) $uploadResult["asset_id"], $familyCode, $entry["role"]);

        if ($linkResult["status"] !== "linked") {
            $summary["failed"] += 1;
            echo " [failed link: {$linkResult['message']}]\n";
            continue;
        }

        $summary["linked"] += 1;
        echo $uploadResult["status"] === "uploaded" ? " [uploaded + linked]\n" : " [reused + linked]\n";
    }
}

echo "\nSummary\n";
echo " family: {$familyCode}\n";
echo " planned: {$summary['planned']}\n";
echo " uploaded: {$summary['uploaded']}\n";
echo " reused: {$summary['reused']}\n";
echo " linked: {$summary['linked']}\n";
echo " failed: {$summary['failed']}\n";

exit($summary["failed"] > 0 ? 1 : 0);

function collectFamilyEntries(string $directory, string $folderPrefix, string $role, array $lensMap): array
{
    if (!is_dir($directory)) {
        return [];
    }

    if (!directoryHasChildDirectories($directory)) {
        return collectFlatFamilyEntries($directory, $folderPrefix, $role, $lensMap);
    }

    return collectNestedFamilyEntries($directory, $folderPrefix, $role, $lensMap);
}

function directoryHasChildDirectories(string $directory): bool
{
    $directories = glob($directory . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR) ?: [];
    return $directories !== [];
}

function collectNestedFamilyEntries(string $directory, string $folderPrefix, string $role, array $lensMap): array
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    $entries = [];

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $filePath = $fileInfo->getPathname();
        $relativePath = normalizeSeparators(substr($filePath, strlen($directory) + 1));
        $lensKey = detectNestedLensKey($relativePath);

        if ($lensKey === null || !isset($lensMap[$lensKey])) {
            throw new RuntimeException("Unknown lens mapping for {$relativePath}");
        }

        $entries[] = [
            "file_path" => $filePath,
            "filename" => $fileInfo->getFilename(),
            "relative_path" => $relativePath,
            "folder_id" => $folderPrefix . "/" . $lensMap[$lensKey],
            "role" => $role,
        ];
    }

    usort($entries, static function (array $left, array $right): int {
        return strcmp($left["relative_path"], $right["relative_path"]);
    });

    return $entries;
}

function collectFlatFamilyEntries(string $directory, string $folderPrefix, string $role, array $lensMap): array
{
    $files = glob($directory . DIRECTORY_SEPARATOR . "*") ?: [];
    $files = array_values(array_filter($files, "is_file"));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $entries = [];

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        $stem = (string) pathinfo($filename, PATHINFO_FILENAME);
        $lensKey = detectFlatLensKey($stem);

        if ($lensKey === null || !isset($lensMap[$lensKey])) {
            throw new RuntimeException("Unknown flat lens mapping for {$filename}");
        }

        $entries[] = [
            "file_path" => $filePath,
            "filename" => $filename,
            "relative_path" => $filename,
            "folder_id" => $folderPrefix . "/" . $lensMap[$lensKey],
            "role" => $role,
        ];
    }

    return $entries;
}

function normalizeSeparators(string $path): string
{
    return str_replace("\\", "/", $path);
}

function detectNestedLensKey(string $relativePath): ?string
{
    $segments = array_values(array_filter(explode("/", normalizeSeparators($relativePath))));
    if (count($segments) < 2) {
        return null;
    }

    if ($segments[0] === "clear" && isset($segments[1])) {
        return "clear/" . $segments[1];
    }

    return $segments[0];
}

function detectFlatLensKey(string $stem): ?string
{
    $segments = array_values(array_filter(explode("_", strtolower(trim($stem)))));

    if (count($segments) < 2) {
        return null;
    }

    return $segments[1];
}

function uploadOrReuseDamAsset(string $baseUrl, string $apiKey, string $folderId, string $filePath, string $filename): array
{
    $url = $baseUrl . "/?endpoint=dam&action=upload";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "folder_id" => $folderId,
        "file" => new CURLFile($filePath),
    ]);
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
    $assetId = (int) ($payload["data"]["asset"]["id"] ?? 0);

    if (($payload["ok"] ?? false) === true && ($httpCode === 200 || $httpCode === 201) && $assetId > 0) {
        return [
            "status" => "uploaded",
            "asset_id" => $assetId,
            "message" => "ok",
        ];
    }

    if ($httpCode === 409) {
        $existingId = (int) ($payload["error"]["details"]["id"] ?? 0);

        if ($existingId <= 0) {
            $existingId = findDamAssetIdByFilename($baseUrl, $apiKey, $folderId, $filename);
        }

        if ($existingId > 0) {
            return [
                "status" => "reused",
                "asset_id" => $existingId,
                "message" => "already exists",
            ];
        }
    }

    $errorMessage = trim((string) ($payload["error"]["message"] ?? ""));

    return [
        "status" => "failed",
        "message" => $errorMessage !== "" ? $errorMessage : ("HTTP " . $httpCode),
    ];
}

function findDamAssetIdByFilename(string $baseUrl, string $apiKey, string $folderId, string $filename): int
{
    $query = http_build_query([
        "endpoint" => "dam",
        "action" => "list",
        "folder_id" => $folderId,
        "q" => $filename,
        "limit" => 200,
    ]);

    $url = $baseUrl . "/?" . $query;
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-API-Key: " . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $rawResponse = curl_exec($ch);
    curl_close($ch);

    if ($rawResponse === false || !is_string($rawResponse)) {
        return 0;
    }

    $payload = json_decode($rawResponse, true);
    $assets = $payload["data"]["assets"] ?? [];

    if (!is_array($assets)) {
        return 0;
    }

    foreach ($assets as $asset) {
        if ((string) ($asset["filename"] ?? "") === $filename) {
            return (int) ($asset["id"] ?? 0);
        }
    }

    return 0;
}

function linkDamAsset(string $baseUrl, string $apiKey, int $assetId, string $familyCode, string $role): array
{
    $url = $baseUrl . "/?endpoint=dam&action=link";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-API-Key: " . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "asset_id" => $assetId,
        "family_code" => $familyCode,
        "role" => $role,
    ], JSON_UNESCAPED_SLASHES));
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

    if (($payload["ok"] ?? false) === true && ($httpCode === 200 || $httpCode === 201)) {
        return [
            "status" => "linked",
            "message" => "ok",
        ];
    }

    $errorMessage = trim((string) ($payload["error"]["message"] ?? ""));

    return [
        "status" => "failed",
        "message" => $errorMessage !== "" ? $errorMessage : ("HTTP " . $httpCode),
    ];
}
