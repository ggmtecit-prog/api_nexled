<?php

require_once dirname(__FILE__) . "/../lib/images.php";
require_once dirname(__FILE__) . "/../lib/reference-decoder.php";
require_once dirname(__FILE__) . "/../lib/luminotechnical.php";
require_once dirname(__FILE__) . "/../lib/technical-drawing.php";
require_once dirname(__FILE__) . "/../lib/sections.php";

$reference = validateReference($_GET["ref"] ?? "11007502111010100");
$lang = validateLang($_GET["lang"] ?? "pt");

if ($reference === "") {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid ref parameter"]);
    exit();
}

$parts = decodeReference($reference);
$productType = getProductType($reference);

if ($productType === null) {
    http_response_code(422);
    echo json_encode([
        "error" => "Unknown product family",
        "reference" => $reference,
    ]);
    exit();
}

$productId = $productType === "dynamic"
    ? getProductIdDynamic($reference, $parts["cap"])
    : getProductId($reference);

if ($productId === null) {
    http_response_code(404);
    echo json_encode([
        "error" => "Product not found",
        "reference" => $reference,
    ]);
    exit();
}

$lumino = getLuminotechnicalData($productId, $reference, $lang);

if ($lumino === null) {
    http_response_code(422);
    echo json_encode([
        "error" => "Luminotechnical data not found",
        "reference" => $reference,
        "product_id" => $productId,
    ]);
    exit();
}

$config = [
    "lens" => resolveDebugLensLabel($parts["lens"]),
    "finish" => resolveDebugFinishLabel($parts["finish"]),
    "connector_cable" => preg_replace("/[^a-zA-Z0-9]/", "", $_GET["connector_cable"] ?? "0"),
    "cable_type" => preg_replace("/[^a-zA-Z0-9]/", "", $_GET["cable_type"] ?? "branco"),
    "end_cap" => preg_replace("/[^a-zA-Z0-9]/", "", $_GET["end_cap"] ?? "0"),
    "purpose" => preg_replace("/[^a-zA-Z0-9]/", "", $_GET["purpose"] ?? "0"),
    "lang" => $lang,
    "extra_length" => intval($_GET["extra_length"] ?? 0),
    "option" => preg_replace("/[^a-zA-Z0-9]/", "", $_GET["option"] ?? "0"),
    "cable_length" => floatval($_GET["cable_length"] ?? 0),
    "gasket" => floatval($_GET["gasket"] ?? 5),
];

$sizesFile = getBarSizesFile($reference);
$drawing = getTechnicalDrawing($productType, $reference, $productId, $sizesFile, $config);
$colorGraph = getColorGraph($lumino["led_id"], $lang);
$lensDiagram = getLensDiagram($productId, $reference);

$assetReport = [
    "drawing" => buildSvgDiagnosticEntry($drawing["drawing"] ?? null),
    "color_graph" => buildSvgDiagnosticEntry($colorGraph["image"] ?? null),
    "lens_diagram" => buildSvgDiagnosticEntry($lensDiagram["diagram"] ?? null),
    "lens_illuminance" => buildSvgDiagnosticEntry($lensDiagram["illuminance"] ?? null),
];

echo json_encode([
    "reference" => $reference,
    "product_type" => $productType,
    "product_id" => $productId,
    "led_id" => $lumino["led_id"],
    "segments" => [
        "family" => $parts["family"],
        "size" => $parts["size"],
        "color" => $parts["color"],
        "cri" => $parts["cri"],
        "series" => $parts["series"],
        "lens" => $parts["lens"],
        "finish" => $parts["finish"],
        "cap" => $parts["cap"],
        "option" => $parts["option"],
    ],
    "commands" => [
        "rsvg_convert" => function_exists("findSystemCommand") ? findSystemCommand("rsvg-convert") : null,
        "magick" => function_exists("findSystemCommand") ? findSystemCommand("magick") : null,
        "convert" => function_exists("findSystemCommand") ? findSystemCommand("convert") : null,
    ],
    "cache" => [
        "raster_path" => defined("PDF_RASTER_CACHE_PATH") ? PDF_RASTER_CACHE_PATH : null,
        "raster_exists" => defined("PDF_RASTER_CACHE_PATH") ? is_dir(PDF_RASTER_CACHE_PATH) : false,
        "remote_path" => defined("PDF_REMOTE_CACHE_PATH") ? PDF_REMOTE_CACHE_PATH : null,
        "remote_exists" => defined("PDF_REMOTE_CACHE_PATH") ? is_dir(PDF_REMOTE_CACHE_PATH) : false,
    ],
    "gd_info" => [
        "imagecreatefrompng" => function_exists("imagecreatefrompng"),
        "imageistruecolor" => function_exists("imageistruecolor"),
        "imagecreatetruecolor" => function_exists("imagecreatetruecolor"),
        "imagepng" => function_exists("imagepng"),
    ],
    "remote_fetch_test" => buildRemoteFetchTest($drawing["drawing"] ?? null),
    "to_pdf_asset_src_test" => buildToPdfAssetSrcTest($drawing["drawing"] ?? null),
    "color_graph_data" => [
        "label" => $colorGraph["label"] ?? null,
        "image" => $colorGraph["image"] ?? null,
    ],
    "raw_assets" => [
        "drawing" => $drawing["drawing"] ?? null,
        "color_graph" => $colorGraph["image"] ?? null,
        "lens_diagram" => $lensDiagram["diagram"] ?? null,
        "lens_illuminance" => $lensDiagram["illuminance"] ?? null,
    ],
    "assets" => $assetReport,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

function buildSvgDiagnosticEntry(?string $path): array {
    $entry = [
        "path" => $path,
        "exists" => false,
        "extension" => null,
        "sibling_png" => null,
        "sibling_png_exists" => false,
        "renderable_path" => null,
        "renderable_extension" => null,
        "renderable_exists" => false,
        "rasterized" => false,
    ];

    if (!is_string($path) || trim($path) === "") {
        return $entry;
    }

    $entry["exists"] = is_file($path);
    $entry["extension"] = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if ($entry["extension"] === "svg") {
        $entry["sibling_png"] = substr($path, 0, -4) . ".png";
        $entry["sibling_png_exists"] = is_file($entry["sibling_png"]);
    }

    if (function_exists("getPdfRenderableImagePath")) {
        $renderablePath = getPdfRenderableImagePath($path);
        $entry["renderable_path"] = $renderablePath;
        $entry["renderable_extension"] = strtolower(pathinfo($renderablePath, PATHINFO_EXTENSION));
        $entry["renderable_exists"] = is_file($renderablePath);
        $entry["rasterized"] = $renderablePath !== $path;
    }

    return $entry;
}

function resolveDebugLensLabel(string $lensCode): string {
    return match ($lensCode) {
        "1" => "Clear",
        "2" => "Frost",
        "3" => "FrostC",
        "4" => "45°E LF",
        "5" => "45°D LF",
        "6" => "2x55° LF",
        "7" => "45°",
        "8" => "20°",
        "9" => "40°",
        default => "Nada",
    };
}

function buildToPdfAssetSrcTest(?string $rawUrl): array {
    if (!is_string($rawUrl) || trim($rawUrl) === "") {
        return ["skipped" => true];
    }

    $result = ["raw_url" => $rawUrl];

    $safePath = function_exists("getPdfSafeAssetPath") ? getPdfSafeAssetPath($rawUrl) : null;
    $result["safe_path"] = $safePath;
    $result["safe_path_is_url"] = is_string($safePath) && (bool) preg_match("#^(https?:)?//#i", $safePath);
    $result["safe_path_is_file"] = is_string($safePath) && is_file($safePath);
    $result["safe_path_size"] = (is_string($safePath) && is_file($safePath)) ? filesize($safePath) : null;

    if (is_string($safePath) && is_file($safePath)) {
        $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
        $result["safe_path_ext"] = $ext;

        if ($ext !== "svg") {
            $contents = file_get_contents($safePath);
            $result["file_readable"] = ($contents !== false);
            $result["file_content_bytes"] = is_string($contents) ? strlen($contents) : 0;
            $result["would_produce"] = "data:" . ($ext === "png" ? "image/png" : "image/jpeg") . ";base64,[" . (is_string($contents) ? strlen(base64_encode($contents)) : 0) . " chars]";
        }
    }

    return $result;
}

function buildRemoteFetchTest(?string $rawUrl): array {
    if (!is_string($rawUrl) || trim($rawUrl) === "") {
        return ["skipped" => true];
    }
    $transformedUrl = function_exists("getCloudinaryRasterizedUrl") ? getCloudinaryRasterizedUrl($rawUrl) : $rawUrl;
    $cachedPath = function_exists("cacheRemoteAssetForPdf") ? cacheRemoteAssetForPdf($transformedUrl) : null;
    return [
        "raw_url" => $rawUrl,
        "transformed_url" => $transformedUrl,
        "cached_path" => $cachedPath,
        "cached_file_exists" => $cachedPath !== null && is_file($cachedPath),
        "cached_file_size" => ($cachedPath !== null && is_file($cachedPath)) ? filesize($cachedPath) : null,
        "safe_path" => function_exists("getPdfSafeAssetPath") ? getPdfSafeAssetPath($rawUrl) : null,
    ];
}

function resolveDebugFinishLabel(string $finishCode): string {
    return match ($finishCode) {
        "01", "1" => "Alu",
        "02", "2" => "Alu BR",
        "03", "3" => "Alu CT",
        "04", "4" => "Alu PT",
        "05", "5" => "Alu+M",
        "06", "6" => "Alu BR+M",
        "07", "7" => "Alu+FM",
        default => $finishCode,
    };
}
