<?php

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

require_once dirname(__FILE__, 2) . "/lib/code-explorer.php";
require_once dirname(__FILE__, 2) . "/lib/pdf-engine.php";

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

$payload = buildCodeExplorerDefaultDatasheetPayload($pdfSpecs);

try {
    $binary = buildDatasheetPdfBinary($payload);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header("Content-Type: application/pdf");
        header('Content-Disposition: attachment; filename="' . buildFamilyReadyPdfFileName($reference) . '"');
    }

    echo $binary;
} catch (DatasheetRequestException $error) {
    respondDatasheetJsonError($error->statusCode, $error->payload);
} catch (\Throwable $error) {
    respondDatasheetJsonError(500, [
        "error" => "Datasheet file endpoint internal error",
        "detail" => $error->getMessage(),
        "reference" => $reference,
    ]);
}
