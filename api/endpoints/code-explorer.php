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

$action = trim(strtolower((string) ($_GET["action"] ?? "")));

if ($action === "pdf_specs") {
    $reference = sanitizeCodeExplorerSearch($_GET["reference"] ?? "");

    if (strlen($reference) !== REFERENCE_LENGTH_FULL || !str_starts_with($reference, $familyMeta["code"])) {
        http_response_code(400);
        echo json_encode(["error" => "Missing or invalid reference parameter"]);
        exit();
    }

    $lang = getCodeExplorerLanguage($_GET["lang"] ?? CODE_EXPLORER_DEFAULT_LANG);
    $options = getCodeExplorerFamilyOptions($family);
    $identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);

    echo json_encode(
        buildCodeExplorerPdfSpecsResponse(
            $familyMeta["code"],
            $familyMeta["name"],
            $options,
            $identities,
            $reference,
            $lang
        )
    );
    exit();
}

$page = getCodeExplorerPage($_GET["page"] ?? null);
$pageSize = getCodeExplorerPageSize($_GET["page_size"] ?? null);
$mode = getCodeExplorerMode($_GET["mode"] ?? CODE_EXPLORER_MODE_FILTERS);
$search = sanitizeCodeExplorerSearch($_GET["search"] ?? "");
$searchType = getCodeExplorerSearchType($_GET["search_type"] ?? CODE_EXPLORER_SEARCH_TYPE_CODE);
$statusFilter = getCodeExplorerStatusFilter($_GET["status"] ?? CODE_EXPLORER_STATUS_ALL);
$includeInvalid = getCodeExplorerIncludeInvalid($_GET["include_invalid"] ?? false);
$identityPreview = getCodeExplorerIncludeInvalid($_GET["identity_preview"] ?? false);
$hasTargetedReferenceSearch = $searchType === CODE_EXPLORER_SEARCH_TYPE_CODE
    && isCodeExplorerTargetedReferenceSearch($search, $familyMeta["code"]);

$options = getCodeExplorerFamilyOptions($family);
$segmentFilters = getCodeExplorerSegmentFilters($_GET, $options);
$filteredOptions = getCodeExplorerFilteredOptions($options, $segmentFilters);
$identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);
$filteredIdentities = getCodeExplorerFilteredIdentities($identities, $segmentFilters);

if ($mode === CODE_EXPLORER_MODE_SEARCH && $hasTargetedReferenceSearch) {
    if ($identityPreview) {
        echo json_encode(
            buildCodeExplorerTargetedPreviewResponse(
                $familyMeta["code"],
                $familyMeta["name"],
                $filteredOptions,
                $filteredIdentities,
                $search,
                $statusFilter,
                $pageSize,
                $includeInvalid,
                $segmentFilters
            )
        );
        exit();
    }

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

if ($mode === CODE_EXPLORER_MODE_SEARCH) {
    echo json_encode(
        buildCodeExplorerResponse(
            $familyMeta["code"],
            $familyMeta["name"],
            $filteredOptions,
            $filteredIdentities,
            $search,
            $searchType,
            $statusFilter,
            $page,
            $pageSize,
            $includeInvalid,
            $segmentFilters
        )
    );
    exit();
}

echo json_encode(
    buildCodeExplorerIdentityChunkResponse(
        $familyMeta["code"],
        $familyMeta["name"],
        $filteredOptions,
        $filteredIdentities,
        "",
        $statusFilter,
        $page,
        $pageSize,
        $includeInvalid,
        $segmentFilters
    )
);
