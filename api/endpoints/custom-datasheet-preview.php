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
    http_response_code(400);
    echo json_encode([
        "error" => "Invalid or missing JSON body",
        "error_code" => "custom_datasheet_invalid_request",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$normalization = normalizeCustomDatasheetRequest($input);

if (($normalization["ok"] ?? false) !== true) {
    http_response_code((int) ($normalization["status_code"] ?? 400));
    echo json_encode($normalization["error"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$normalizedRequest = $normalization["data"];
$family = $normalizedRequest["family"];
$familyEntry = getFamilyRegistryEntry($family);

if ($familyEntry === null || !isFamilyCustomDatasheetSupported($family)) {
    http_response_code(422);
    echo json_encode([
        "error" => "Custom datasheet is not available for this family",
        "error_code" => "custom_datasheet_unsupported_family",
        "family" => $family,
        "custom_datasheet_status" => $familyEntry["custom_datasheet_status"] ?? "blocked_until_datasheet_runtime",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

try {
    $warnings = $normalization["warnings"] ?? [];
    $custom = $normalizedRequest["custom"] ?? [];
    $textOverrides = array_keys($custom["text_overrides"] ?? []);
    $assetOverrides = array_keys($custom["asset_overrides"] ?? []);
    $fieldOverrides = array_keys($custom["field_overrides"] ?? []);
    $advancedCopySections = array_keys($custom["copy_overrides"] ?? []);
    $hiddenSections = array_keys(array_filter(
        $custom["section_visibility"] ?? [],
        static fn(mixed $value): bool => $value === false
    ));
    $previewSnapshot = buildCustomDatasheetPreviewSnapshot($normalizedRequest);
    $editableCopy = $previewSnapshot["editable_copy"] ?? [];
    $fieldSnapshot = $previewSnapshot["field_snapshot"] ?? [];

    echo json_encode([
        "ok" => true,
        "data" => [
            "implemented" => true,
            "family" => [
                "code" => $family,
                "name" => $familyEntry["name"] ?? "",
            ],
            "base_reference" => $normalizedRequest["base_reference"],
            "normalized_request" => $normalizedRequest,
            "custom_datasheet" => [
                "status" => getFamilyCustomDatasheetStatus($family),
                "runtime_implemented" => isFamilyCustomDatasheetRuntimeImplemented($family),
                "allowed_fields" => getFamilyCustomDatasheetAllowedFields($family),
                "defaults" => getFamilyCustomDatasheetDefaults($family),
            ],
            "applied_fields" => [
                "text" => $textOverrides,
                "assets" => $assetOverrides,
                "field_overrides" => $fieldOverrides,
                "advanced_copy_sections" => $advancedCopySections,
                "hidden_sections" => $hiddenSections,
                "footer_marker" => $custom["footer"]["marker"] ?? "CustPDF",
            ],
            "field_snapshot" => $fieldSnapshot,
            "editable_copy" => $editableCopy,
            "warnings" => $warnings,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (DatasheetRequestException $error) {
    http_response_code($error->statusCode);
    echo json_encode($error->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
} catch (\Throwable $error) {
    http_response_code(500);
    echo json_encode([
        "error" => "Custom datasheet preview internal error",
        "error_code" => "custom_datasheet_preview_internal_error",
        "detail" => $error->getMessage(),
        "family" => $family,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
