<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$defaultBaseUrl = "http://127.0.0.1:8099/api/";
$defaultApiKey = "7b8edd27a16f60bf7a1c92b8ceb40cda474588d24491140c130418153053063b";

$options = getopt("", ["base-url::", "api-key::", "dry-run"]);
$baseUrl = rtrim((string) ($options["base-url"] ?? $defaultBaseUrl), "/");
$apiKey = (string) ($options["api-key"] ?? $defaultApiKey);
$dryRun = array_key_exists("dry-run", $options);

$batches = [
    [
        "label" => "icons",
        "folder_id" => "nexled/datasheet/icons",
        "source_dir" => $root . "/appdatasheets/img/icones",
    ],
    [
        "label" => "energy-labels",
        "folder_id" => "nexled/datasheet/energy-labels",
        "source_dir" => $root . "/appdatasheets/img/classe-energetica",
        "pattern" => "*.svg",
    ],
    [
        "label" => "energy-labels-right",
        "folder_id" => "nexled/datasheet/energy-labels/right",
        "source_dir" => $root . "/appdatasheets/img/classe-energetica/right",
        "pattern" => "*.svg",
    ],
    [
        "label" => "temperatures",
        "folder_id" => "nexled/datasheet/temperatures",
        "source_dir" => $root . "/appdatasheets/img/temperaturas",
    ],
    [
        "label" => "logos",
        "folder_id" => "nexled/datasheet/logos",
        "source_dir" => $root . "/appdatasheets/img/logos",
    ],
    [
        "label" => "power-supplies",
        "folder_id" => "nexled/datasheet/power-supplies",
        "source_dir" => $root . "/appdatasheets/img/fontes",
    ],
];

$summary = [
    "planned" => 0,
    "uploaded" => 0,
    "skipped" => 0,
    "failed" => 0,
];

foreach ($batches as $batch) {
    $files = collectFiles((string) $batch["source_dir"], (string) ($batch["pattern"] ?? "*"));
    $summary["planned"] += count($files);

    echo "== {$batch['label']} -> {$batch['folder_id']} (" . count($files) . " files)\n";

    foreach ($files as $filePath) {
        $filename = basename($filePath);
        echo " - {$filename}";

        if ($dryRun) {
            echo " [dry-run]\n";
            continue;
        }

        $result = uploadDamAsset($baseUrl, $apiKey, (string) $batch["folder_id"], $filePath);

        if ($result["status"] === "uploaded") {
            $summary["uploaded"] += 1;
            echo " [uploaded]\n";
            continue;
        }

        if ($result["status"] === "skipped") {
            $summary["skipped"] += 1;
            echo " [skipped: {$result['message']}]\n";
            continue;
        }

        $summary["failed"] += 1;
        echo " [failed: {$result['message']}]\n";
    }
}

echo "\nSummary\n";
echo " planned: {$summary['planned']}\n";
echo " uploaded: {$summary['uploaded']}\n";
echo " skipped: {$summary['skipped']}\n";
echo " failed: {$summary['failed']}\n";

exit($summary["failed"] > 0 ? 1 : 0);

function collectFiles(string $directory, string $pattern): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = glob($directory . DIRECTORY_SEPARATOR . $pattern) ?: [];
    $files = array_values(array_filter($files, "is_file"));
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
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

    if (($payload["ok"] ?? false) === true && ($httpCode === 200 || $httpCode === 201)) {
        return [
            "status" => "uploaded",
            "message" => "ok",
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
