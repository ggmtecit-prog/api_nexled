<?php

require_once dirname(__FILE__) . "/../lib/code-repair.php";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

$reference = strtoupper(validateReference($_GET["reference"] ?? ""));

if ($reference === "" || !hasFullReferenceLength($reference)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid reference parameter"]);
    exit();
}

$family = validateFamily(substr($reference, 0, REFERENCE_LENGTH_FAMILY));
$familyMeta = getCodeExplorerFamilyMeta($family);

if ($familyMeta === null) {
    http_response_code(400);
    echo json_encode(["error" => "Unknown family"]);
    exit();
}

$lang = getCodeExplorerLanguage($_GET["lang"] ?? CODE_EXPLORER_DEFAULT_LANG);

echo json_encode(buildCodeRepairResponse($reference, $lang));
