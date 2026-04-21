<?php

require_once dirname(__FILE__) . "/../lib/code-explorer.php";

$family = validateFamily($_GET["family"] ?? null);

if ($family === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid family parameter"]);
    exit();
}

$familyMeta = getCodeExplorerFamilyMeta($family);

if ($familyMeta === null) {
    http_response_code(400);
    echo json_encode(["error" => "Unknown family"]);
    exit();
}

$options = getCodeExplorerFamilyOptions($family);
$identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);
$filters = getFamilyReadyFilters($_GET, $options, getFamilyReadyProductsBaseRows(
    $familyMeta["code"],
    $familyMeta["name"],
    $options,
    $identities
));

echo json_encode(
    buildFamilyReadyFiltersResponse(
        $familyMeta["code"],
        $familyMeta["name"],
        $options,
        $identities,
        $filters
    )
);
