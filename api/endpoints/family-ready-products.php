<?php

require_once dirname(__FILE__) . "/../lib/code-explorer.php";

$family = validateFamily($_GET["family"] ?? null);

if ($family === 0) {
    respondError(400, "invalid_family", "Missing or invalid family parameter", ["family" => $_GET["family"] ?? null]);
}

$familyMeta = getCodeExplorerFamilyMeta($family);

if ($familyMeta === null) {
    respondError(400, "unknown_family", "Unknown family", ["family" => $family]);
}

$page = getCodeExplorerPage($_GET["page"] ?? null);
$pageSize = getCodeExplorerPageSize($_GET["page_size"] ?? null);
$options = getCodeExplorerFamilyOptions($family);
$identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);
$filters = getFamilyReadyFilters($_GET, $options, getFamilyReadyProductsBaseRows(
    $familyMeta["code"],
    $familyMeta["name"],
    $options,
    $identities
));

respondJson(
    buildFamilyReadyProductsResponse(
        $familyMeta["code"],
        $familyMeta["name"],
        $options,
        $identities,
        $page,
        $pageSize,
        $filters
    )
);
