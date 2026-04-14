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

$page = getCodeExplorerPage($_GET["page"] ?? null);
$pageSize = getCodeExplorerPageSize($_GET["page_size"] ?? null);
$search = sanitizeCodeExplorerSearch($_GET["search"] ?? "");
$statusFilter = getCodeExplorerStatusFilter($_GET["status"] ?? CODE_EXPLORER_STATUS_ALL);

$options = getCodeExplorerFamilyOptions($family);
$identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);

echo json_encode(
    buildCodeExplorerResponse(
        $familyMeta["code"],
        $familyMeta["name"],
        $options,
        $identities,
        $search,
        $statusFilter,
        $page,
        $pageSize
    )
);
