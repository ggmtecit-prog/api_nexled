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
$includeInvalid = getCodeExplorerIncludeInvalid($_GET["include_invalid"] ?? false);
$hasTargetedReferenceSearch = isCodeExplorerTargetedReferenceSearch($search, $familyMeta["code"]);

$options = getCodeExplorerFamilyOptions($family);
$segmentFilters = getCodeExplorerSegmentFilters($_GET, $options);
$filteredOptions = getCodeExplorerFilteredOptions($options, $segmentFilters);
$identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);
$filteredIdentities = getCodeExplorerFilteredIdentities($identities, $segmentFilters);
$validMatrixSize = getCodeExplorerValidMatrixSize($filteredOptions, $filteredIdentities);
$identityMatrixSize = getCodeExplorerIdentityMatrixSize($filteredOptions);
$suffixMatrixSize = getCodeExplorerSuffixMatrixSize($filteredOptions);
$fullMatrixSize = getCodeExplorerFullMatrixSize($filteredOptions);

if ($hasTargetedReferenceSearch) {
    echo json_encode(
        buildCodeExplorerTargetedSearchResponse(
            $familyMeta["code"],
            $familyMeta["name"],
            $filteredOptions,
            $filteredIdentities,
            $search,
            $statusFilter,
            $page,
            $pageSize,
            $includeInvalid,
            $segmentFilters
        )
    );
    exit();
}

if ($includeInvalid && !hasCodeExplorerSegmentDrillDown($segmentFilters)) {
    http_response_code(400);
    echo json_encode([
        "error" => "Invalid exploration requires at least one drill-down filter or a targeted full-code search.",
        "reason" => "invalid_filters_required",
        "family" => $familyMeta,
        "valid_matrix_size" => $validMatrixSize,
        "identity_matrix_size" => $identityMatrixSize,
        "suffix_matrix_size" => $suffixMatrixSize,
        "full_matrix_size" => $fullMatrixSize,
        "message" => "Turn off invalid combinations, search for a specific full code prefix, or narrow the family by segment filters such as size, color, CRI, or series.",
    ]);
    exit();
}

if ($includeInvalid && $fullMatrixSize > CODE_EXPLORER_MAX_FULL_MATRIX_ROWS) {
    http_response_code(400);
    echo json_encode([
        "error" => "Full family code matrix is too large for one request.",
        "reason" => "family_matrix_too_large",
        "family" => $familyMeta,
        "valid_matrix_size" => $validMatrixSize,
        "identity_matrix_size" => $identityMatrixSize,
        "suffix_matrix_size" => $suffixMatrixSize,
        "full_matrix_size" => $fullMatrixSize,
        "max_supported_rows" => CODE_EXPLORER_MAX_FULL_MATRIX_ROWS,
        "message" => "Turn off invalid combinations for faster valid-only mode, or use identity-first drill-down. Example family 11 currently expands to billions of full codes.",
    ]);
    exit();
}

echo json_encode(
    buildCodeExplorerResponse(
        $familyMeta["code"],
        $familyMeta["name"],
        $filteredOptions,
        $filteredIdentities,
        $search,
        $statusFilter,
        $page,
        $pageSize,
        $includeInvalid,
        $segmentFilters
    )
);
