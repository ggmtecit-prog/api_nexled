<?php

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

require_once dirname(__FILE__, 2) . "/lib/code-explorer.php";

function buildSpectralPngBinary(string $imagePath): ?string {
    $resolvedPath = getPdfSafeAssetPath($imagePath);

    if (!is_string($resolvedPath) || trim($resolvedPath) === "" || !is_file($resolvedPath)) {
        return null;
    }

    $extension = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));

    if ($extension === "png") {
        $bytes = @file_get_contents($resolvedPath);
        return is_string($bytes) && $bytes !== "" ? $bytes : null;
    }

    if (!function_exists("imagecreatefromstring") || !function_exists("imagepng")) {
        return null;
    }

    $sourceBytes = @file_get_contents($resolvedPath);

    if (!is_string($sourceBytes) || $sourceBytes === "") {
        return null;
    }

    $image = @imagecreatefromstring($sourceBytes);

    if ($image === false) {
        return null;
    }

    ob_start();
    imagepng($image);
    $pngBytes = ob_get_clean();
    imagedestroy($image);

    return is_string($pngBytes) && $pngBytes !== "" ? $pngBytes : null;
}

$reference = validateReference($_GET["reference"] ?? "");

if (strlen($reference) !== REFERENCE_LENGTH_FULL) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid reference parameter"]);
    exit();
}

$familyCode = substr($reference, 0, REFERENCE_LENGTH_FAMILY);
$familyMeta = getCodeExplorerFamilyMeta($familyCode);

if ($familyMeta === null) {
    http_response_code(404);
    echo json_encode(["error" => "Unknown family for reference"]);
    exit();
}

$options = getCodeExplorerFamilyOptions($familyMeta["code"]);
$identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);
$pdfSpecs = buildCodeExplorerPdfSpecsResponse(
    $familyMeta["code"],
    $familyMeta["name"],
    $options,
    $identities,
    $reference
);

if (($pdfSpecs["configurator_valid"] ?? false) !== true) {
    http_response_code(404);
    echo json_encode([
        "error" => "Product not found in database for reference: " . $reference,
    ]);
    exit();
}

if (($pdfSpecs["datasheet_ready"] ?? false) !== true) {
    http_response_code(422);
    echo json_encode([
        "error" => "Reference is not datasheet-ready",
        "reference" => $reference,
        "failure_reason" => $pdfSpecs["failure_reason"] ?? null,
    ]);
    exit();
}

$ledId = trim((string) ($pdfSpecs["summary"]["led_id"] ?? ""));
$colorGraph = $ledId !== "" ? getColorGraph($ledId, CODE_EXPLORER_DEFAULT_LANG) : null;

if ($colorGraph === null || !is_string($colorGraph["image"] ?? null) || trim((string) $colorGraph["image"]) === "") {
    http_response_code(422);
    echo json_encode([
        "error" => "Spectral image not available for reference",
        "reference" => $reference,
    ]);
    exit();
}

$pngBinary = buildSpectralPngBinary((string) $colorGraph["image"]);

if ($pngBinary === null) {
    http_response_code(500);
    echo json_encode([
        "error" => "Spectral image PNG conversion unavailable",
        "reference" => $reference,
    ]);
    exit();
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (!headers_sent()) {
    header("Content-Type: image/png");
    header('Content-Disposition: attachment; filename="' . buildFamilyReadySpectralFileName($reference) . '"');
}

echo $pngBinary;
