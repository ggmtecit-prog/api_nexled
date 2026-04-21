<?php

require_once dirname(__FILE__) . "/../lib/showcase/request.php";
require_once dirname(__FILE__) . "/../lib/showcase/preview.php";
require_once dirname(__FILE__) . "/../lib/showcase/assembler.php";
require_once dirname(__FILE__) . "/../lib/showcase/render.php";

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
        "error_code" => "showcase_invalid_request",
    ]);
    exit();
}

$normalization = normalizeShowcaseRequest($input);

if (($normalization["ok"] ?? false) !== true) {
    http_response_code((int) ($normalization["status_code"] ?? 400));
    echo json_encode($normalization["error"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$normalizedRequest = $normalization["data"];
$family = $normalizedRequest["family"];
$familyEntry = getFamilyRegistryEntry($family);

if ($familyEntry === null || !isFamilyShowcaseSupported($family)) {
    respondShowcasePdfJsonError(422, [
        "error" => "Showcase PDF is not mapped yet for this family",
        "error_code" => "showcase_unsupported_family",
        "family" => $family,
        "showcase_status" => $familyEntry["showcase_status"] ?? "blocked_until_mapped",
    ]);
}

if (!isFamilyShowcaseRuntimeImplemented($family)) {
    respondShowcasePdfJsonError(501, [
        "error" => "Showcase PDF runtime not implemented yet for this family",
        "error_code" => "showcase_not_implemented",
        "family" => $family,
        "showcase_renderer" => getFamilyShowcaseRenderer($family),
        "showcase_status" => getFamilyShowcaseStatus($family),
    ]);
}

$warnings = $normalization["warnings"] ?? [];
$preview = buildShowcasePreview($normalizedRequest, $warnings);

if (($preview["ok"] ?? false) !== true) {
    respondShowcasePdfJsonError((int) ($preview["status_code"] ?? 422), $preview["error"]);
}

$assembled = assembleShowcasePayload($normalizedRequest);

if (($assembled["ok"] ?? false) !== true) {
    respondShowcasePdfJsonError((int) ($assembled["status_code"] ?? 422), $assembled["error"]);
}

$rendered = renderShowcasePdfBinary($normalizedRequest, $assembled["data"]);

if (($rendered["ok"] ?? false) !== true) {
    respondShowcasePdfJsonError((int) ($rendered["status_code"] ?? 500), $rendered["error"]);
}

$filename = $rendered["data"]["filename"];
$content = $rendered["data"]["content"];

while (ob_get_level() > 0) {
    ob_end_clean();
}

if (!headers_sent()) {
    header("Content-Type: application/pdf");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Content-Length: " . strlen($content));
}

echo $content;
exit();

function respondShowcasePdfJsonError(int $statusCode, array $payload): void {
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
