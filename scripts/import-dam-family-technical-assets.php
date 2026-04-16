<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$defaultBaseUrl = "http://127.0.0.1:8099/api/";
$defaultApiKey = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";

$options = getopt("", ["family::", "base-url::", "api-key::", "labels::", "dry-run"]);
$familyCode = preg_replace("/[^0-9]/", "", (string) ($options["family"] ?? "11")) ?? "";
$baseUrl = rtrim((string) ($options["base-url"] ?? $defaultBaseUrl), "/");
$apiKey = (string) ($options["api-key"] ?? $defaultApiKey);
$labels = parseLabelsOption($options["labels"] ?? null);
$dryRun = array_key_exists("dry-run", $options);

if ($familyCode === "") {
    fwrite(STDERR, "Missing or invalid --family value.\n");
    exit(1);
}

$batches = [
    [
        "label" => "drawings",
        "role" => "drawing",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/desenhos",
        "folder_id" => "nexled/datasheet/drawings",
        "product_code_mode" => "stem",
    ],
    [
        "label" => "diagrams",
        "role" => "diagram",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/diagramas",
        "folder_id" => "nexled/datasheet/diagrams",
        "product_code_mode" => "none",
    ],
    [
        "label" => "diagram-inv",
        "role" => "diagram-inv",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/diagramas/i",
        "folder_id" => "nexled/datasheet/diagrams/inverted",
        "product_code_mode" => "none",
    ],
    [
        "label" => "mounting",
        "role" => "mounting",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/fixacao",
        "folder_id" => "nexled/datasheet/mounting",
        "product_code_mode" => "none",
    ],
    [
        "label" => "connectors",
        "role" => "connector",
        "source_dir" => $root . "/appdatasheets/img/{$familyCode}/ligacao",
        "folder_id" => "nexled/datasheet/connectors",
        "product_code_mode" => "stem",
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
    if ($labels !== [] && !in_array((string) $batch["label"], $labels, true)) {
        continue;
    }

    $entries = collectDirectFiles(
        $familyCode,
        (string) $batch["source_dir"],
        (string) $batch["folder_id"],
        (string) $batch["role"],
        (string) $batch["product_code_mode"]
    );
    $summary["planned"] += count($entries);

    echo "== {$batch['label']} -> {$batch['folder_id']} (" . count($entries) . " files)\n";

    foreach ($entries as $entry) {
        echo " - {$entry['filename']} => {$entry['folder_id']}";
        if ($entry["upload_filename"] !== $entry["filename"]) {
            echo " as {$entry['upload_filename']}";
        }

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

function parseLabelsOption($raw): array
{
    if (!is_string($raw) || trim($raw) === "") {
        return [];
    }

    $labels = array_values(array_filter(array_map(
        static fn(string $value): string => trim(strtolower($value)),
        explode(",", $raw)
    )));

    return array_values(array_unique($labels));
}

function collectDirectFiles(string $familyCode, string $directory, string $folderId, string $role, string $productCodeMode): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = glob($directory . DIRECTORY_SEPARATOR . "*") ?: [];
    $files = array_values(array_filter($files, "is_file"));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    $entries = [];

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        $stem = pathinfo($filename, PATHINFO_FILENAME);

        $entries[] = [
            "file_path" => $filePath,
            "filename" => $filename,
            "upload_filename" => buildTechnicalUploadFilename($familyCode, $filename),
            "folder_id" => $folderId,
            "role" => $role,
            "product_code" => $productCodeMode === "stem" ? normalizeProductCode($stem) : null,
        ];
    }

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

function buildTechnicalUploadFilename(string $familyCode, string $filename): string
{
    return $familyCode . "_" . $filename;
}

function uploadOrReuseDamAsset(string $baseUrl, string $apiKey, string $folderId, string $filePath, string $filename): array
{
    $url = $baseUrl . "/?endpoint=dam&action=upload";
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        "folder_id" => $folderId,
        "file" => new CURLFile($filePath, null, $filename),
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

function linkDamAsset(string $baseUrl, string $apiKey, int $assetId, string $familyCode, string $role, ?string $productCode): array
{
    $url = $baseUrl . "/?endpoint=dam&action=link";
    $ch = curl_init($url);

    $payload = [
        "asset_id" => $assetId,
        "family_code" => $familyCode,
        "role" => $role,
    ];

    if ($productCode !== null && $productCode !== "") {
        $payload["product_code"] = $productCode;
    }

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-API-Key: " . $apiKey,
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
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

    $response = json_decode($rawResponse, true);

    if (($response["ok"] ?? false) === true && ($httpCode === 200 || $httpCode === 201)) {
        return [
            "status" => "linked",
            "message" => "ok",
        ];
    }

    $errorMessage = trim((string) ($response["error"]["message"] ?? ""));

    return [
        "status" => "failed",
        "message" => $errorMessage !== "" ? $errorMessage : ("HTTP " . $httpCode),
    ];
}
