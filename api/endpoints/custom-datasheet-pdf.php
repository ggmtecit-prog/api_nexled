<?php

require_once dirname(__FILE__) . "/../lib/custom-datasheet/request.php";
require_once dirname(__FILE__) . "/../lib/custom-datasheet/render.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    respondCustomDatasheetJsonError(400, [
        "error" => "Invalid or missing JSON body",
        "error_code" => "custom_datasheet_invalid_request",
    ]);
}

$normalization = normalizeCustomDatasheetRequest($input);

if (($normalization["ok"] ?? false) !== true) {
    respondCustomDatasheetJsonError((int) ($normalization["status_code"] ?? 400), $normalization["error"]);
}

$normalizedRequest = $normalization["data"];
$family = $normalizedRequest["family"];
$familyEntry = getFamilyRegistryEntry($family);

if ($familyEntry === null || !isFamilyCustomDatasheetSupported($family)) {
    respondCustomDatasheetJsonError(422, [
        "error" => "Custom datasheet is not available for this family",
        "error_code" => "custom_datasheet_unsupported_family",
        "family" => $family,
        "custom_datasheet_status" => $familyEntry["custom_datasheet_status"] ?? "blocked_until_datasheet_runtime",
    ]);
}

if (!isFamilyCustomDatasheetRuntimeImplemented($family)) {
    respondCustomDatasheetJsonError(501, [
        "error" => "Custom datasheet runtime not implemented yet for this family",
        "error_code" => "custom_datasheet_not_implemented",
        "family" => $family,
        "custom_datasheet_status" => getFamilyCustomDatasheetStatus($family),
        "allowed_fields" => getFamilyCustomDatasheetAllowedFields($family),
    ]);
}

try {
    $binary = buildCustomDatasheetPdfBinary($normalizedRequest);
    $fileReference = preg_replace("/[^a-zA-Z0-9_-]/", "", (string) ($normalizedRequest["base_reference"] ?? ""));

    if ($fileReference === "") {
        $fileReference = "custom-datasheet";
    } else {
        $fileReference = "custom-" . $fileReference;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header("Content-Type: application/pdf");
        header('Content-Disposition: attachment; filename="' . $fileReference . '.pdf"');
    }

    echo $binary;
    exit();
} catch (DatasheetRequestException $error) {
    respondCustomDatasheetJsonError($error->statusCode, $error->payload);
} catch (\Throwable $error) {
    respondCustomDatasheetJsonError(500, [
        "error" => "Custom datasheet internal error",
        "error_code" => "custom_datasheet_internal_error",
        "detail" => $error->getMessage(),
        "family" => $family,
    ]);
}

function respondCustomDatasheetJsonError(int $statusCode, array $payload): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header("Content-Type: application/json");
    }

    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
