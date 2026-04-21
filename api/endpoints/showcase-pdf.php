<?php

require_once dirname(__FILE__) . "/../lib/showcase/request.php";

header("Content-Type: application/json");

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

http_response_code(501);
echo json_encode([
    "error" => "Showcase PDF runtime not implemented yet",
    "error_code" => "showcase_not_implemented",
    "family" => $family,
    "normalized_request" => $normalizedRequest,
    "showcase_renderer" => getFamilyShowcaseRenderer($family),
    "showcase_status" => getFamilyShowcaseStatus($family),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
