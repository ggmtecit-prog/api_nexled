<?php

require_once dirname(__FILE__) . "/../lib/showcase/request.php";
require_once dirname(__FILE__) . "/../lib/showcase/preview.php";
require_once dirname(__FILE__) . "/../lib/showcase/assembler.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
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
    http_response_code(422);
    echo json_encode([
        "error" => "Showcase PDF is not mapped yet for this family",
        "error_code" => "showcase_unsupported_family",
        "family" => $family,
        "showcase_status" => $familyEntry["showcase_status"] ?? "blocked_until_mapped",
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$warnings = $normalization["warnings"] ?? [];
$preview = buildShowcasePreview($normalizedRequest, $warnings);

if (($preview["ok"] ?? false) !== true) {
    http_response_code((int) ($preview["status_code"] ?? 422));
    echo json_encode($preview["error"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

$inspectSections = isShowcasePreviewInspectSectionsRequested($input["inspect_sections"] ?? false);
$assembledShowcase = null;

if ($inspectSections) {
    $assembled = assembleShowcasePayload($normalizedRequest);

    if (($assembled["ok"] ?? false) !== true) {
        http_response_code((int) ($assembled["status_code"] ?? 422));
        echo json_encode($assembled["error"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }

    $assembledShowcase = $assembled["data"];
}

echo json_encode([
    "ok" => true,
    "data" => [
        "implemented" => true,
        "family" => [
            "code" => $family,
            "name" => $familyEntry["name"] ?? "",
        ],
        "normalized_request" => $normalizedRequest,
        "sections" => $normalizedRequest["sections"],
        "showcase" => [
            "renderer" => getFamilyShowcaseRenderer($family),
            "status" => getFamilyShowcaseStatus($family),
            "runtime_implemented" => isFamilyShowcaseRuntimeImplemented($family),
            "supported_sections" => getFamilyShowcaseSections($family),
            "expandable_segments" => getFamilyShowcaseExpandableSegments($family),
        ],
        "variant_count" => $preview["data"]["variant_count"],
        "estimated_pages" => $preview["data"]["estimated_pages"],
        "warnings" => $preview["data"]["warnings"],
        "counts" => $preview["data"]["counts"],
        "assembled_showcase" => $assembledShowcase,
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

function isShowcasePreviewInspectSectionsRequested(mixed $value): bool {
    if (is_bool($value)) {
        return $value;
    }

    $normalized = strtolower(trim((string) $value));
    return in_array($normalized, ["1", "true", "yes", "on"], true);
}
