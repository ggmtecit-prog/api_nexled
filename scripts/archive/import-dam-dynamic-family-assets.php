<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$defaultBaseUrl = "http://127.0.0.1:8099/api/";
$defaultApiKey = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";

$options = getopt("", ["family::", "base-url::", "api-key::", "dry-run"]);
$familyCode = preg_replace("/[^0-9]/", "", (string) ($options["family"] ?? "48")) ?? "";
$baseUrl = rtrim((string) ($options["base-url"] ?? $defaultBaseUrl), "/");
$apiKey = (string) ($options["api-key"] ?? $defaultApiKey);
$dryRun = array_key_exists("dry-run", $options);

if ($familyCode === "") {
    fwrite(STDERR, "Missing or invalid --family value.\n");
    exit(1);
}

$familyRoot = $root . "/appdatasheets/img/{$familyCode}";
$subtypes = collectSubtypeDirectories($familyRoot);

$batches = [
    [
        "label" => "packshots",
        "role" => "packshot",
        "folder_id" => "nexled/datasheet/packshots/generic",
        "relative_parts" => ["produto"],
    ],
    [
        "label" => "finishes",
        "role" => "finish",
        "folder_id" => "nexled/datasheet/finishes/generic",
        "relative_parts" => ["acabamentos"],
    ],
    [
        "label" => "drawings",
        "role" => "drawing",
        "folder_id" => "nexled/datasheet/drawings",
        "relative_parts" => ["desenhos"],
    ],
    [
        "label" => "diagrams",
        "role" => "diagram",
        "folder_id" => "nexled/datasheet/diagrams",
        "relative_parts" => ["diagramas"],
    ],
    [
        "label" => "diagram-inv",
        "role" => "diagram-inv",
        "folder_id" => "nexled/datasheet/diagrams/inverted",
        "relative_parts" => ["diagramas", "i"],
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
    $entries = collectDynamicEntries(
        $familyCode,
        $familyRoot,
        $subtypes,
        (string) $batch["folder_id"],
        (string) $batch["role"],
        (array) $batch["relative_parts"]
    );
    $summary["planned"] += count($entries);

    echo "== {$batch['label']} -> {$batch['folder_id']} (" . count($entries) . " files)\n";

    foreach ($entries as $entry) {
        echo " - {$entry['relative_path']} => {$entry['folder_id']} as {$entry['upload_filename']}";

        if ($dryRun) {
            echo " [dry-run]\n";
            continue;
        }

        $uploadResult = uploadOrReuseDamAsset(
            $baseUrl,
            $apiKey,
            $entry["folder_id"],
            $entry["file_path"],
            $entry["upload_filename"]
        );

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

        $linkResult = linkDamAsset(
            $baseUrl,
            $apiKey,
            (int) $uploadResult["asset_id"],
            $familyCode,
            $entry["role"],
            $entry["product_code"]
        );

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

function collectSubtypeDirectories(string $familyRoot): array
{
    if (!is_dir($familyRoot)) {
        return [];
    }

    $directories = glob($familyRoot . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR) ?: [];
    $subtypes = array_map("basename", $directories);
    sort($subtypes, SORT_NATURAL | SORT_FLAG_CASE);

    return $subtypes;
}

function collectDynamicEntries(
    string $familyCode,
    string $familyRoot,
    array $subtypes,
    string $folderId,
    string $role,
    array $relativeParts
): array {
    $entries = [];

    foreach ($subtypes as $subtype) {
        $directory = $familyRoot . "/" . $subtype . "/" . implode("/", $relativeParts);

        if (!is_dir($directory)) {
            continue;
        }

        $files = glob($directory . DIRECTORY_SEPARATOR . "*") ?: [];
        $files = array_values(array_filter($files, "is_file"));
        sort($files, SORT_NATURAL | SORT_FLAG_CASE);

        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $entries[] = [
                "file_path" => $filePath,
                "filename" => $filename,
                "relative_path" => $subtype . "/" . implode("/", $relativeParts) . "/" . $filename,
                "folder_id" => $folderId,
                "role" => $role,
                "subtype" => $subtype,
                "product_code" => normalizeProductCode($familyCode . "-" . $subtype),
                "hash" => md5_file($filePath) ?: "",
                "upload_filename" => $filename,
            ];
        }
    }

    $grouped = [];

    foreach ($entries as $index => $entry) {
        $grouped[$entry["filename"]][] = $index;
    }

    foreach ($grouped as $filename => $indexes) {
        if (count($indexes) <= 1) {
            continue;
        }

        $hashes = [];
        foreach ($indexes as $index) {
            $hashes[] = $entries[$index]["hash"];
        }

        $hashes = array_values(array_unique(array_filter($hashes)));

        if (count($hashes) <= 1) {
            continue;
        }

        foreach ($indexes as $index) {
            $entries[$index]["upload_filename"] = $entries[$index]["subtype"] . "_" . $filename;
        }
    }

    usort($entries, static function (array $left, array $right): int {
        return strcmp($left["relative_path"], $right["relative_path"]);
    });

    return $entries;
}

function normalizeProductCode(string $value): ?string
{
    $value = trim($value);
    if ($value === "") {
        return null;
    }

    $value = preg_replace("/[^a-zA-Z0-9_-]/", "", $value) ?? "";
    return $value !== "" ? substr($value, 0, 64) : null;
}

function uploadOrReuseDamAsset(
    string $baseUrl,
    string $apiKey,
    string $folderId,
    string $filePath,
    string $uploadFilename
): array {
    $url = $baseUrl . "/?endpoint=dam&action=upload";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "folder_id" => $folderId,
        "file" => new CURLFile($filePath, null, $uploadFilename),
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
            $existingId = findDamAssetIdByFilename($baseUrl, $apiKey, $folderId, $uploadFilename);
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

function linkDamAsset(
    string $baseUrl,
    string $apiKey,
    int $assetId,
    string $familyCode,
    string $role,
    ?string $productCode
): array {
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
        "product_code" => $productCode,
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
