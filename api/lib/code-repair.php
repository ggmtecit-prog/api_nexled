<?php

require_once dirname(__FILE__) . "/code-explorer.php";

const CODE_REPAIR_DAM_PRODUCT_ROLE_LIMIT = 200;
const CODE_REPAIR_DAM_SHARED_ROLE_LIMIT = 300;
const CODE_REPAIR_MATCH_LIMIT = 8;
const CODE_REPAIR_BLOCKER_DEFINITIONS = [
    "invalid_luminos_combination" => [
        "title" => "Configurator identity missing",
        "summary" => "The reference identity does not resolve to a live Luminos product.",
        "source_key" => "luminos",
        "repair_mode" => "inspect_only",
    ],
    "unsupported_datasheet_runtime" => [
        "title" => "Datasheet runtime unsupported",
        "summary" => "The family runtime is not supported by the current datasheet generator.",
        "source_key" => "runtime",
        "repair_mode" => "inspect_only",
    ],
    "missing_header_data" => [
        "title" => "Header data missing",
        "summary" => "The datasheet header is missing a product image or description text.",
        "source_key" => "header",
        "repair_mode" => "asset_or_metadata",
    ],
    "missing_technical_drawing" => [
        "title" => "Technical drawing missing",
        "summary" => "No active drawing asset was found for this reference.",
        "source_key" => "technical_drawing",
        "repair_mode" => "asset",
    ],
    "missing_color_graph" => [
        "title" => "Color graph missing",
        "summary" => "No active color graph is available for the resolved LED.",
        "source_key" => "color_graph",
        "repair_mode" => "asset",
    ],
    "missing_lens_diagram" => [
        "title" => "Lens diagram missing",
        "summary" => "This lens requires a diagram, but no active lens diagram was found.",
        "source_key" => "lens_diagram",
        "repair_mode" => "asset",
    ],
    "missing_finish_image" => [
        "title" => "Finish image missing",
        "summary" => "No active finish image resolves for this reference.",
        "source_key" => "finish_image",
        "repair_mode" => "asset",
    ],
];

function buildCodeRepairResponse(string $reference, string $lang = CODE_EXPLORER_DEFAULT_LANG): array {
    $lang = getCodeExplorerLanguage($lang);
    $parts = decodeReference($reference);
    $familyCode = (string) ($parts["family"] ?? "");
    $familyMeta = getCodeExplorerFamilyMeta(intval($familyCode)) ?? [
        "code" => $familyCode,
        "name" => $familyCode,
    ];
    $options = getCodeExplorerFamilyOptions(intval($familyCode));
    $identities = getCodeExplorerLuminosIdentities($familyCode);
    $identityMap = [];

    foreach ($identities as $identityData) {
        $identityMap[(string) ($identityData["identity"] ?? "")] = $identityData;
    }

    $identity = (string) ($parts["identity"] ?? "");
    $identityData = $identityMap[$identity] ?? null;
    $defaultProductType = getProductType($reference);
    $productType = (string) ($identityData["product_type"] ?? $defaultProductType ?? "");
    $productId = $identityData !== null
        ? resolveCodeExplorerProductId($familyCode, $identityData, (string) ($parts["cap"] ?? ""))
        : null;
    $isConfiguratorValid = $identityData !== null
        && is_string($productId)
        && trim($productId) !== "";
    $ledId = (string) ($identityData["led_id"] ?? "");
    $description = (string) ($identityData["description"] ?? "");
    $segmentLookups = getCodeExplorerSegmentLabelLookups($options);
    $row = [
        "reference" => $reference,
        "identity" => $identity,
        "description" => $description,
        "product_type" => $productType,
        "product_id" => $productId,
        "segments" => [
            "family" => $parts["family"] ?? "",
            "size" => $parts["size"] ?? "",
            "color" => $parts["color"] ?? "",
            "cri" => $parts["cri"] ?? "",
            "series" => $parts["series"] ?? "",
            "lens" => $parts["lens"] ?? "",
            "finish" => $parts["finish"] ?? "",
            "cap" => $parts["cap"] ?? "",
            "option" => $parts["option"] ?? "",
        ],
        "segment_labels" => [
            "size" => $segmentLookups["size"][$parts["size"] ?? ""] ?? ($parts["size"] ?? ""),
            "color" => $segmentLookups["color"][$parts["color"] ?? ""] ?? ($parts["color"] ?? ""),
            "cri" => $segmentLookups["cri"][$parts["cri"] ?? ""] ?? ($parts["cri"] ?? ""),
            "series" => $segmentLookups["series"][$parts["series"] ?? ""] ?? ($parts["series"] ?? ""),
            "lens" => resolveCodeExplorerOptionLabel($options["lens"], $parts["lens"] ?? ""),
            "finish" => resolveCodeExplorerOptionLabel($options["finish"], $parts["finish"] ?? ""),
            "cap" => resolveCodeExplorerOptionLabel($options["cap"], $parts["cap"] ?? ""),
            "option" => resolveCodeExplorerOptionLabel($options["option"], $parts["option"] ?? ""),
        ],
    ];
    $row["legacy_description"] = buildCodeExplorerLegacyDescription($row);

    $runtimeConfig = buildCodeRepairRuntimeConfig($reference, $options, $lang);
    $runtimeSupported = isDatasheetRuntimeSupported(
        $productType !== "" ? $productType : $defaultProductType,
        $familyCode
    );
    $inspection = buildCodeExplorerPdfSpecsResponse(
        $familyMeta["code"],
        $familyMeta["name"],
        $options,
        $identities,
        $reference,
        $lang
    );

    if ($isConfiguratorValid) {
        $validatorCache = [];
        $readiness = getCodeExplorerDatasheetReadiness(
            $reference,
            (string) $productId,
            $productType,
            $ledId,
            $options,
            $validatorCache
        );
    } else {
        $readiness = [
            "datasheet_ready" => false,
            "failure_reason" => "invalid_luminos_combination",
        ];
    }

    $sourceMap = [
        "luminos" => buildCodeRepairLuminosSourceMap(
            $identity,
            $identityData,
            $productType,
            $productId,
            $ledId,
            $description
        ),
        "runtime" => buildCodeRepairRuntimeSourceMap($familyCode, $productType, $runtimeSupported),
        "header" => buildCodeRepairHeaderSourceMap(
            $familyCode,
            $productType,
            $productId,
            $reference,
            $ledId,
            $runtimeConfig
        ),
        "technical_drawing" => buildCodeRepairTechnicalDrawingSourceMap(
            $familyCode,
            $productType,
            $productId,
            $reference,
            $runtimeConfig
        ),
        "color_graph" => buildCodeRepairColorGraphSourceMap($ledId, $lang),
        "lens_diagram" => buildCodeRepairLensDiagramSourceMap(
            $familyCode,
            $productType,
            $productId,
            $reference
        ),
        "finish_image" => buildCodeRepairFinishImageSourceMap(
            $familyCode,
            $productType,
            $productId,
            $reference,
            $runtimeConfig,
            (string) ($row["segment_labels"]["finish"] ?? "")
        ),
    ];

    $blockers = buildCodeRepairBlockers(
        $sourceMap,
        $isConfiguratorValid,
        $runtimeSupported,
        (string) ($readiness["failure_reason"] ?? "")
    );
    $topBlocker = (string) ($readiness["failure_reason"] ?? "");

    if ($topBlocker === "" && $blockers !== []) {
        $topBlocker = (string) ($blockers[0]["code"] ?? "");
    }

    return [
        "reference" => $reference,
        "lang" => $lang,
        "family" => [
            "code" => $familyMeta["code"],
            "name" => $familyMeta["name"],
        ],
        "summary" => [
            "reference" => $reference,
            "identity" => $identity,
            "description" => $description,
            "legacy_description" => $row["legacy_description"],
            "family_name" => $familyMeta["name"],
            "product_type" => $productType,
            "product_id" => (string) ($productId ?? ""),
            "led_id" => $ledId,
            "configurator_valid" => $isConfiguratorValid,
            "datasheet_ready" => (bool) ($readiness["datasheet_ready"] ?? false),
            "top_blocker" => $topBlocker,
            "header_description" => (string) ($inspection["summary"]["header_description"] ?? ""),
            "finish_name" => (string) ($inspection["summary"]["finish_name"] ?? ""),
            "ip_rating" => (string) ($inspection["summary"]["ip_rating"] ?? ""),
            "color_graph_label" => (string) ($inspection["summary"]["color_graph_label"] ?? ""),
        ],
        "segments" => $row["segments"],
        "segment_labels" => $row["segment_labels"],
        "validation" => [
            "configurator_valid" => $isConfiguratorValid,
            "datasheet_ready" => (bool) ($readiness["datasheet_ready"] ?? false),
            "failure_reason" => $topBlocker !== "" ? $topBlocker : null,
            "blockers" => $blockers,
            "runtime_supported" => $runtimeSupported,
        ],
        "database_checks" => buildCodeRepairDatabaseChecks(
            $row,
            $identityData,
            $productId,
            $ledId,
            $inspection,
            $sourceMap
        ),
        "source_map" => $sourceMap,
        "characteristics" => $inspection["characteristics"] ?? [],
        "dimensions" => $inspection["dimensions"] ?? [],
        "editable_fields" => [
            "header_description_html" => (string) ($sourceMap["header"]["description_html"] ?? ""),
            "header_description_text" => (string) ($sourceMap["header"]["description_text"] ?? ""),
            "header_description_fragments" => $sourceMap["header"]["description_fragments"] ?? [],
            "finish_name" => (string) ($row["segment_labels"]["finish"] ?? ""),
            "purpose_code" => (string) ($runtimeConfig["purpose"] ?? "0"),
            "language" => $lang,
        ],
    ];
}

function buildCodeRepairLuminosSourceMap(
    string $identity,
    ?array $identityData,
    string $productType,
    ?string $productId,
    string $ledId,
    string $description
): array {
    $isValid = $identityData !== null
        && is_string($productId)
        && trim($productId) !== "";

    return [
        "required" => true,
        "status" => $isValid ? "present" : "missing",
        "active" => [
            "source_type" => $isValid ? "database" : "missing",
            "identity" => $identity,
            "product_id" => (string) ($productId ?? ""),
            "led_id" => $ledId,
            "description" => $description,
        ],
        "dynamic_ids" => $identityData["dynamic_ids"] ?? [],
        "product_type" => $productType,
        "lookup" => [
            "identity" => $identity,
        ],
    ];
}

function buildCodeRepairRuntimeSourceMap(string $familyCode, string $productType, bool $supported): array {
    return [
        "required" => true,
        "status" => $supported ? "present" : "blocked",
        "supported" => $supported,
        "family_code" => $familyCode,
        "product_type" => $productType,
    ];
}

function buildCodeRepairHeaderSourceMap(
    string $familyCode,
    string $productType,
    ?string $productId,
    string $reference,
    string $ledId,
    array $config
): array {
    if ($productType === "" || !is_string($productId) || trim($productId) === "" || $ledId === "") {
        return buildCodeRepairUnavailableSourceMap(true, "Missing product or LED context.");
    }

    $parts = decodeReference($reference);
    $header = getProductHeader($productType, $productId, $reference, $ledId, $config);
    $lookup = getCodeRepairProductImageLookup($productType, $productId, $parts, $config);
    $descriptionHtml =
        getProductDescriptionText($productId, $familyCode, $config["lang"]) .
        getLedDescriptionText($ledId, $familyCode, $config["lang"]) .
        getPurposeText((string) ($config["purpose"] ?? "0"), $config["lang"]) .
        getEnergyClassText($config["lang"]);
    $descriptionText = getCodeRepairPlainText($descriptionHtml);
    $imagePath = is_string($header["image"] ?? null) ? trim((string) $header["image"]) : null;
    $damLookup = buildCodeRepairProductDamLookup(
        $familyCode,
        "packshot",
        $productId,
        $lookup["candidates"],
        $lookup["preferred_formats"] ?? []
    );

    return [
        "required" => true,
        "status" => ($imagePath !== null && $descriptionText !== "") ? "present" : "missing",
        "checks" => [
            "image_present" => $imagePath !== null,
            "description_present" => $descriptionText !== "",
        ],
        "active" => buildCodeRepairResolvedAssetPayload($imagePath),
        "description_html" => $descriptionHtml,
        "description_text" => $descriptionText,
        "description_fragments" => [
            "product_html" => getProductDescriptionText($productId, $familyCode, $config["lang"]),
            "led_html" => getLedDescriptionText($ledId, $familyCode, $config["lang"]),
            "purpose_html" => getPurposeText((string) ($config["purpose"] ?? "0"), $config["lang"]),
            "energy_class_html" => getEnergyClassText($config["lang"]),
        ],
        "lookup" => [
            "dam_role" => "packshot",
            "candidates" => $lookup["candidates"],
            "local" => buildCodeRepairLocalLookup($lookup["local_checks"]),
            "dam" => $damLookup,
        ],
    ];
}

function buildCodeRepairTechnicalDrawingSourceMap(
    string $familyCode,
    string $productType,
    ?string $productId,
    string $reference,
    array $config
): array {
    if ($productType === "" || !is_string($productId) || trim($productId) === "") {
        return buildCodeRepairUnavailableSourceMap(true, "Missing product context.");
    }

    $lookup = getCodeRepairTechnicalDrawingLookup($productType, $productId, $reference, $config);
    $drawingPath = getCodeExplorerTechnicalDrawingPath($productType, $reference, $productId, $config);

    return [
        "required" => true,
        "status" => $drawingPath !== null ? "present" : "missing",
        "active" => buildCodeRepairResolvedAssetPayload($drawingPath),
        "lookup" => [
            "dam_role" => "drawing",
            "candidates" => $lookup["candidates"],
            "local" => buildCodeRepairLocalLookup($lookup["local_checks"]),
            "dam" => buildCodeRepairProductDamLookup(
                $familyCode,
                "drawing",
                $productId,
                $lookup["candidates"],
                $lookup["preferred_formats"] ?? []
            ),
        ],
    ];
}

function buildCodeRepairColorGraphSourceMap(string $ledId, string $lang): array {
    if ($ledId === "") {
        return buildCodeRepairUnavailableSourceMap(true, "Missing LED context.");
    }

    $lookup = getCodeRepairColorGraphLookup($ledId);
    $colorGraph = getColorGraph($ledId, $lang);
    $imagePath = is_array($colorGraph) ? (string) ($colorGraph["image"] ?? "") : "";

    return [
        "required" => true,
        "status" => $imagePath !== "" ? "present" : "missing",
        "active" => buildCodeRepairResolvedAssetPayload($imagePath !== "" ? $imagePath : null),
        "label" => is_array($colorGraph) ? (string) ($colorGraph["label"] ?? "") : "",
        "lookup" => [
            "dam_role" => "temperature",
            "candidates" => $lookup["candidates"],
            "local" => buildCodeRepairLocalLookup($lookup["local_checks"]),
            "dam" => buildCodeRepairSharedDamLookup(
                "temperature",
                $lookup["candidates"],
                $lookup["preferred_formats"]
            ),
        ],
    ];
}

function buildCodeRepairLensDiagramSourceMap(
    string $familyCode,
    string $productType,
    ?string $productId,
    string $reference
): array {
    $parts = decodeReference($reference);
    $lensCode = (string) ($parts["lens"] ?? "");

    if ($lensCode === "" || $lensCode === "0") {
        return [
            "required" => false,
            "status" => "not_required",
            "active" => buildCodeRepairResolvedAssetPayload(null, false),
        ];
    }

    if ($productType === "" || !is_string($productId) || trim($productId) === "") {
        return buildCodeRepairUnavailableSourceMap(true, "Missing product context.");
    }

    $lookup = getCodeRepairLensDiagramLookup($productId, $reference);
    $diagram = getLensDiagram($productId, $reference);
    $diagramPath = is_array($diagram) ? (string) ($diagram["diagram"] ?? "") : "";
    $illuminancePath = is_array($diagram) ? (string) ($diagram["illuminance"] ?? "") : "";

    return [
        "required" => true,
        "status" => $diagramPath !== "" ? "present" : "missing",
        "active" => [
            "diagram" => buildCodeRepairResolvedAssetPayload($diagramPath !== "" ? $diagramPath : null),
            "illuminance" => buildCodeRepairResolvedAssetPayload($illuminancePath !== "" ? $illuminancePath : null, false),
        ],
        "lookup" => [
            "diagram" => [
                "dam_role" => "diagram",
                "candidates" => $lookup["diagram"]["candidates"],
                "local" => buildCodeRepairLocalLookup($lookup["diagram"]["local_checks"]),
                "dam" => buildCodeRepairProductDamLookup(
                    $familyCode,
                    "diagram",
                    $productId,
                    $lookup["diagram"]["candidates"],
                    $lookup["diagram"]["preferred_formats"] ?? []
                ),
            ],
            "illuminance" => [
                "dam_role" => "diagram-inv",
                "candidates" => $lookup["illuminance"]["candidates"],
                "local" => buildCodeRepairLocalLookup($lookup["illuminance"]["local_checks"]),
                "dam" => buildCodeRepairProductDamLookup(
                    $familyCode,
                    "diagram-inv",
                    $productId,
                    $lookup["illuminance"]["candidates"],
                    $lookup["illuminance"]["preferred_formats"] ?? []
                ),
            ],
        ],
    ];
}

function buildCodeRepairFinishImageSourceMap(
    string $familyCode,
    string $productType,
    ?string $productId,
    string $reference,
    array $config,
    string $finishName
): array {
    if ($productType === "" || !is_string($productId) || trim($productId) === "") {
        return buildCodeRepairUnavailableSourceMap(true, "Missing product context.");
    }

    $lookup = getCodeRepairFinishImageLookup($productType, $productId, $reference, $config);
    $finishPath = getCodeExplorerFinishImagePath($productType, $productId, $reference, $config);
    $status = "missing";

    if ($finishPath !== null) {
        $status = isFinishPlaceholderImage($finishPath) ? "placeholder" : "present";
    }

    return [
        "required" => true,
        "status" => $status,
        "active" => buildCodeRepairResolvedAssetPayload($finishPath),
        "finish_name" => $finishName,
        "lookup" => [
            "dam_role" => "finish",
            "candidates" => $lookup["candidates"],
            "local" => buildCodeRepairLocalLookup($lookup["local_checks"]),
            "dam" => buildCodeRepairProductDamLookup(
                $familyCode,
                "finish",
                $productId,
                $lookup["candidates"],
                $lookup["preferred_formats"] ?? []
            ),
        ],
    ];
}

function buildCodeRepairBlockers(
    array $sourceMap,
    bool $isConfiguratorValid,
    bool $runtimeSupported,
    string $topFailureReason
): array {
    $codes = [];

    if (!$isConfiguratorValid) {
        $codes[] = "invalid_luminos_combination";
    } else {
        if (!$runtimeSupported) {
            $codes[] = "unsupported_datasheet_runtime";
        }

        $headerChecks = $sourceMap["header"]["checks"] ?? [];
        if (
            !($headerChecks["image_present"] ?? false)
            || !($headerChecks["description_present"] ?? false)
        ) {
            $codes[] = "missing_header_data";
        }

        if (($sourceMap["technical_drawing"]["status"] ?? "") !== "present") {
            $codes[] = "missing_technical_drawing";
        }

        if (($sourceMap["color_graph"]["status"] ?? "") !== "present") {
            $codes[] = "missing_color_graph";
        }

        if (($sourceMap["lens_diagram"]["required"] ?? false) && ($sourceMap["lens_diagram"]["status"] ?? "") !== "present") {
            $codes[] = "missing_lens_diagram";
        }

        if (!in_array($sourceMap["finish_image"]["status"] ?? "", ["present"], true)) {
            $codes[] = "missing_finish_image";
        }
    }

    $order = array_keys(CODE_REPAIR_BLOCKER_DEFINITIONS);
    $codes = array_values(array_unique($codes));

    usort($codes, static function (string $left, string $right) use ($topFailureReason, $order): int {
        if ($left === $topFailureReason) {
            return -1;
        }

        if ($right === $topFailureReason) {
            return 1;
        }

        $leftIndex = array_search($left, $order, true);
        $rightIndex = array_search($right, $order, true);
        return intval($leftIndex) <=> intval($rightIndex);
    });

    return array_values(array_map(static function (string $code) use ($sourceMap): array {
        $meta = CODE_REPAIR_BLOCKER_DEFINITIONS[$code] ?? [
            "title" => $code,
            "summary" => $code,
            "source_key" => "",
            "repair_mode" => "inspect_only",
        ];
        $sourceKey = (string) ($meta["source_key"] ?? "");

        return [
            "code" => $code,
            "title" => $meta["title"],
            "summary" => $meta["summary"],
            "source_key" => $sourceKey,
            "repair_mode" => $meta["repair_mode"],
            "current_status" => $sourceKey !== "" ? ($sourceMap[$sourceKey]["status"] ?? "") : "",
        ];
    }, $codes));
}

function buildCodeRepairDatabaseChecks(
    array $row,
    ?array $identityData,
    ?string $productId,
    string $ledId,
    array $inspection,
    array $sourceMap
): array {
    $identity = (string) ($row["identity"] ?? "");
    $description = (string) ($row["description"] ?? "");
    $resolvedProductId = is_string($productId) ? trim($productId) : "";
    $resolvedLedId = trim($ledId);
    $hasIdentity = $identityData !== null;
    $hasProductContext = $hasIdentity && $resolvedProductId !== "";
    $hasLedContext = $hasIdentity && $resolvedLedId !== "";
    $headerText = (string) ($sourceMap["header"]["description_text"] ?? "");
    $headerStatus = (string) ($sourceMap["header"]["status"] ?? "");
    $characteristics = is_array($inspection["characteristics"] ?? null) ? $inspection["characteristics"] : [];
    $dimensions = is_array($inspection["dimensions"] ?? null) ? $inspection["dimensions"] : [];
    $ipRating = (string) ($inspection["summary"]["ip_rating"] ?? "");
    $colorGraphLabel = (string) ($inspection["summary"]["color_graph_label"] ?? "");
    $colorGraphStatus = (string) ($sourceMap["color_graph"]["status"] ?? "");

    return [
        [
            "key" => "luminos_identity",
            "source" => "luminos",
            "status" => $hasIdentity ? "present" : "missing",
            "blocking" => true,
            "value" => $identity,
        ],
        [
            "key" => "product_id",
            "source" => "luminos",
            "status" => !$hasIdentity ? "unavailable" : ($resolvedProductId !== "" ? "present" : "missing"),
            "blocking" => true,
            "value" => $resolvedProductId,
        ],
        [
            "key" => "led_id",
            "source" => "luminos",
            "status" => !$hasIdentity ? "unavailable" : ($resolvedLedId !== "" ? "present" : "missing"),
            "blocking" => true,
            "value" => $resolvedLedId,
        ],
        [
            "key" => "luminos_description",
            "source" => "luminos",
            "status" => !$hasIdentity ? "unavailable" : ($description !== "" ? "present" : "missing"),
            "blocking" => false,
            "value" => $description,
        ],
        [
            "key" => "header_description",
            "source" => "product_database",
            "status" => $headerStatus === "unavailable" ? "unavailable" : ($headerText !== "" ? "present" : "missing"),
            "blocking" => true,
            "value" => $headerText,
        ],
        [
            "key" => "characteristics",
            "source" => "product_database",
            "status" => !$hasProductContext ? "unavailable" : (count($characteristics) > 0 ? "present" : "missing"),
            "blocking" => false,
            "count" => count($characteristics),
        ],
        [
            "key" => "dimensions",
            "source" => "product_database",
            "status" => !$hasProductContext ? "unavailable" : (count($dimensions) > 0 ? "present" : "missing"),
            "blocking" => false,
            "count" => count($dimensions),
        ],
        [
            "key" => "ip_rating",
            "source" => "product_database",
            "status" => !$hasProductContext ? "unavailable" : ($ipRating !== "" ? "present" : "missing"),
            "blocking" => false,
            "value" => $ipRating,
        ],
        [
            "key" => "color_graph_label",
            "source" => "led_database",
            "status" => $colorGraphStatus === "unavailable" || !$hasLedContext ? "unavailable" : ($colorGraphLabel !== "" ? "present" : "missing"),
            "blocking" => false,
            "value" => $colorGraphLabel,
        ],
    ];
}

function buildCodeRepairRuntimeConfig(string $reference, array $options, string $lang): array {
    $parts = decodeReference($reference);
    $config = [
        "lens" => resolveCodeExplorerOptionLabel($options["lens"], $parts["lens"] ?? ""),
        "finish" => resolveCodeExplorerOptionLabel($options["finish"], $parts["finish"] ?? ""),
        "connector_cable" => "0",
        "cable_type" => "branco",
        "end_cap" => "0",
        "purpose" => "0",
        "lang" => $lang,
        "extra_length" => 0,
        "option" => $parts["option"] ?? "0",
        "cable_length" => 0,
        "gasket" => 5,
    ];

    return normalizeBarAssetConfig($reference, $config);
}

function buildCodeRepairUnavailableSourceMap(bool $required, string $message): array {
    return [
        "required" => $required,
        "status" => "unavailable",
        "message" => $message,
        "active" => buildCodeRepairResolvedAssetPayload(null, $required),
    ];
}

function buildCodeRepairResolvedAssetPayload(?string $path, bool $required = true): array {
    $normalizedPath = is_string($path) ? trim($path) : "";

    if ($normalizedPath === "") {
        return [
            "path" => null,
            "source_type" => $required ? "missing" : "not_required",
            "is_remote" => false,
        ];
    }

    if (isFinishPlaceholderImage($normalizedPath)) {
        return [
            "path" => $normalizedPath,
            "source_type" => "placeholder",
            "is_remote" => false,
        ];
    }

    return [
        "path" => $normalizedPath,
        "source_type" => isRemoteAssetUrl($normalizedPath) ? "dam" : "local",
        "is_remote" => isRemoteAssetUrl($normalizedPath),
    ];
}

function buildCodeRepairLocalLookup(array $checks): array {
    $resolvedChecks = [];
    $firstFound = null;

    foreach ($checks as $check) {
        $basePath = (string) ($check["base_path"] ?? "");
        $foundPath = $basePath !== "" ? getCodeRepairExistingImagePath($basePath) : null;

        if ($firstFound === null && $foundPath !== null) {
            $firstFound = $foundPath;
        }

        $resolvedChecks[] = [
            "candidate" => (string) ($check["candidate"] ?? ""),
            "base_path" => $basePath,
            "found_path" => $foundPath,
        ];
    }

    return [
        "first_found_path" => $firstFound,
        "checks" => $resolvedChecks,
    ];
}

function buildCodeRepairProductDamLookup(
    string $familyCode,
    string $role,
    string $productId,
    array $filenameCandidates,
    array $preferredFormats = []
): array {
    $assets = fetchCodeRepairDamProductAssets($familyCode, $role);
    $slugCandidates = buildProductSlugCandidates($productId);
    $stemCandidates = normalizeCodeRepairCandidateStems($filenameCandidates);
    $scoredAssets = [];

    foreach ($assets as $asset) {
        [$score, $reasons] = getCodeRepairProductDamScore($asset, $slugCandidates, $stemCandidates, $preferredFormats);
        $asset["score"] = $score;
        $asset["match_reasons"] = $reasons;
        $scoredAssets[] = $asset;
    }

    usort($scoredAssets, static function (array $left, array $right): int {
        if (($right["score"] ?? 0) !== ($left["score"] ?? 0)) {
            return intval($right["score"] ?? 0) <=> intval($left["score"] ?? 0);
        }

        return intval($left["id"] ?? 0) <=> intval($right["id"] ?? 0);
    });

    return [
        "total_assets" => count($assets),
        "matched_asset" => getCodeRepairBestMatchedAsset($scoredAssets),
        "top_assets" => array_slice($scoredAssets, 0, CODE_REPAIR_MATCH_LIMIT),
    ];
}

function buildCodeRepairSharedDamLookup(string $role, array $filenameCandidates, array $preferredFormats = []): array {
    $assets = fetchCodeRepairDamSharedAssets($role);
    $stemCandidates = normalizeCodeRepairCandidateStems($filenameCandidates);
    $scoredAssets = [];

    foreach ($assets as $asset) {
        [$score, $reasons] = getCodeRepairSharedDamScore($asset, $stemCandidates, $preferredFormats);
        $asset["score"] = $score;
        $asset["match_reasons"] = $reasons;
        $scoredAssets[] = $asset;
    }

    usort($scoredAssets, static function (array $left, array $right): int {
        if (($right["score"] ?? 0) !== ($left["score"] ?? 0)) {
            return intval($right["score"] ?? 0) <=> intval($left["score"] ?? 0);
        }

        return intval($left["id"] ?? 0) <=> intval($right["id"] ?? 0);
    });

    return [
        "total_assets" => count($assets),
        "matched_asset" => getCodeRepairBestMatchedAsset($scoredAssets),
        "top_assets" => array_slice($scoredAssets, 0, CODE_REPAIR_MATCH_LIMIT),
    ];
}

function getCodeRepairBestMatchedAsset(array $scoredAssets): ?array {
    foreach ($scoredAssets as $asset) {
        if (intval($asset["score"] ?? 0) > 0) {
            return $asset;
        }
    }

    return null;
}

function fetchCodeRepairDamProductAssets(string $familyCode, string $role): array {
    static $cache = [];
    $cacheKey = $familyCode . "|" . $role;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $con = connectDBDam();
    $stmt = mysqli_prepare(
        $con,
        "SELECT a.`id`,a.`folder_id`,a.`display_name`,a.`filename`,a.`public_id`,a.`resource_type`,a.`format`,a.`secure_url`,a.`thumbnail_url`,a.`kind`,l.`id` AS `link_id`,l.`role`,l.`sort_order`,l.`product_code`,l.`family_code`
         FROM `dam_asset_links` l
         JOIN `dam_assets` a ON a.`id` = l.`asset_id`
         WHERE a.`resource_type` = 'image'
           AND l.`family_code` = ?
           AND l.`role` = ?
         ORDER BY l.`sort_order` ASC, a.`id` DESC
         LIMIT ?"
    );

    if (!$stmt) {
        closeDB($con);
        return $cache[$cacheKey] = [];
    }

    $limit = CODE_REPAIR_DAM_PRODUCT_ROLE_LIMIT;
    mysqli_stmt_bind_param($stmt, "ssi", $familyCode, $role, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $assets = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $assets[] = [
            "id" => isset($row["id"]) ? intval($row["id"]) : 0,
            "filename" => (string) ($row["filename"] ?? ""),
            "display_name" => (string) ($row["display_name"] ?? ""),
            "public_id" => (string) ($row["public_id"] ?? ""),
            "secure_url" => (string) ($row["secure_url"] ?? ""),
            "thumbnail_url" => (string) ($row["thumbnail_url"] ?? ""),
            "format" => (string) ($row["format"] ?? ""),
            "kind" => (string) ($row["kind"] ?? ""),
            "folder_id" => (string) ($row["folder_id"] ?? ""),
            "link_id" => isset($row["link_id"]) ? intval($row["link_id"]) : null,
            "link_role" => (string) ($row["role"] ?? ""),
            "link_sort_order" => isset($row["sort_order"]) ? intval($row["sort_order"]) : 0,
            "link_product_code" => (string) ($row["product_code"] ?? ""),
            "link_family_code" => (string) ($row["family_code"] ?? ""),
        ];
    }

    mysqli_stmt_close($stmt);
    closeDB($con);

    return $cache[$cacheKey] = $assets;
}

function fetchCodeRepairDamSharedAssets(string $role): array {
    static $cache = [];

    if (isset($cache[$role])) {
        return $cache[$role];
    }

    $con = connectDBDam();
    $stmt = mysqli_prepare(
        $con,
        "SELECT `id`,`folder_id`,`display_name`,`filename`,`public_id`,`resource_type`,`format`,`secure_url`,`thumbnail_url`,`kind`
         FROM `dam_assets`
         WHERE `resource_type` = 'image'
           AND `kind` = ?
         ORDER BY `id` DESC
         LIMIT ?"
    );

    if (!$stmt) {
        closeDB($con);
        return $cache[$role] = [];
    }

    $limit = CODE_REPAIR_DAM_SHARED_ROLE_LIMIT;
    mysqli_stmt_bind_param($stmt, "si", $role, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $assets = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $assets[] = [
            "id" => isset($row["id"]) ? intval($row["id"]) : 0,
            "filename" => (string) ($row["filename"] ?? ""),
            "display_name" => (string) ($row["display_name"] ?? ""),
            "public_id" => (string) ($row["public_id"] ?? ""),
            "secure_url" => (string) ($row["secure_url"] ?? ""),
            "thumbnail_url" => (string) ($row["thumbnail_url"] ?? ""),
            "format" => (string) ($row["format"] ?? ""),
            "kind" => (string) ($row["kind"] ?? ""),
            "folder_id" => (string) ($row["folder_id"] ?? ""),
        ];
    }

    mysqli_stmt_close($stmt);
    closeDB($con);

    return $cache[$role] = $assets;
}

function getCodeRepairProductDamScore(array $asset, array $slugCandidates, array $stemCandidates, array $preferredFormats = []): array {
    $score = 0;
    $reasons = [];
    $filename = nexledNormalizeAssetStem((string) ($asset["filename"] ?? ""));
    $displayName = nexledNormalizeAssetStem((string) ($asset["display_name"] ?? ""));
    $publicId = nexledNormalizeAssetStem((string) ($asset["public_id"] ?? ""));
    $productCode = nexledNormalizeAssetStem((string) ($asset["link_product_code"] ?? ""));
    $format = strtolower(trim((string) ($asset["format"] ?? "")));

    if ($productCode !== "" && in_array($productCode, $slugCandidates, true)) {
        $score += 40;
        $reasons[] = "product_code";
    }

    foreach ($stemCandidates as $stemCandidate) {
        if ($stemCandidate === "") {
            continue;
        }

        if ($filename === $stemCandidate) {
            $score += 60;
            $reasons[] = "filename_exact";
            break;
        }

        if (str_ends_with($filename, "-" . $stemCandidate) || str_ends_with($filename, $stemCandidate)) {
            $score += 55;
            $reasons[] = "filename_suffix";
            break;
        }

        if ($displayName === $stemCandidate) {
            $score += 50;
            $reasons[] = "display_exact";
            break;
        }

        if (str_ends_with($displayName, "-" . $stemCandidate) || str_ends_with($displayName, $stemCandidate)) {
            $score += 45;
            $reasons[] = "display_suffix";
            break;
        }

        if (str_contains($publicId, strtolower($stemCandidate))) {
            $score += 20;
            $reasons[] = "public_id";
            break;
        }
    }

    foreach ($preferredFormats as $index => $preferredFormat) {
        if ($format === strtolower(trim((string) $preferredFormat))) {
            $score += max(1, 12 - $index);
            $reasons[] = "format";
            break;
        }
    }

    return [$score, array_values(array_unique($reasons))];
}

function getCodeRepairSharedDamScore(array $asset, array $stemCandidates, array $preferredFormats = []): array {
    $score = 0;
    $reasons = [];
    $filename = nexledNormalizeAssetStem((string) ($asset["filename"] ?? ""));
    $displayName = nexledNormalizeAssetStem((string) ($asset["display_name"] ?? ""));
    $publicId = strtolower((string) ($asset["public_id"] ?? ""));
    $format = strtolower(trim((string) ($asset["format"] ?? "")));

    foreach ($stemCandidates as $stemCandidate) {
        if ($stemCandidate === "") {
            continue;
        }

        if ($filename === $stemCandidate) {
            $score += 80;
            $reasons[] = "filename_exact";
            break;
        }

        if ($displayName === $stemCandidate) {
            $score += 70;
            $reasons[] = "display_exact";
            break;
        }

        if (str_contains($publicId, strtolower($stemCandidate))) {
            $score += 30;
            $reasons[] = "public_id";
            break;
        }
    }

    foreach ($preferredFormats as $index => $preferredFormat) {
        if ($format === strtolower(trim((string) $preferredFormat))) {
            $score += max(1, 12 - $index);
            $reasons[] = "format";
            break;
        }
    }

    return [$score, array_values(array_unique($reasons))];
}

function normalizeCodeRepairCandidateStems(array $candidates): array {
    $normalized = array_map(
        static fn($candidate): string => nexledNormalizeAssetStem((string) $candidate),
        $candidates
    );

    return array_values(array_filter(array_unique($normalized)));
}

function getCodeRepairExistingImagePath(string $basePath): ?string {
    foreach ([".png", ".jpg", ".jpeg", ".svg", ".webp", ".gif"] as $extension) {
        $path = $basePath . $extension;

        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function getCodeRepairPlainText(string $html): string {
    return trim((string) preg_replace(
        '/\s+/',
        ' ',
        strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", $html))
    ));
}

function getCodeRepairProductImageLookup(string $productType, string $productId, array $parts, array $config): array {
    $family = (string) ($parts["family"] ?? "");
    $size = (string) ($parts["size"] ?? "");
    $series = (string) ($parts["series"] ?? "");
    $cap = (string) ($parts["cap"] ?? "");
    $lens = strtolower((string) ($config["lens"] ?? ""));
    $finish = strtolower((string) ($config["finish"] ?? ""));
    $connectorCable = (string) ($config["connector_cable"] ?? "0");
    $cableType = (string) ($config["cable_type"] ?? "branco");
    $endCap = (string) ($config["end_cap"] ?? "0");
    $candidates = [];
    $folder = "";

    switch ($productType) {
        case "barra":
            $folder = $lens === "clear"
                ? IMAGES_BASE_PATH . "/img/$family/produto/$lens/$series/"
                : IMAGES_BASE_PATH . "/img/$family/produto/$lens/";
            $candidates = [
                str_replace("+", "_", "{$finish}_{$connectorCable}_{$cableType}_{$endCap}"),
                str_replace("+", "_", "{$finish}_{$cap}"),
            ];

            if ($family === "32") {
                $candidates = ["{$connectorCable}_{$cableType}_{$endCap}"];
            }

            if ($family === "58") {
                $candidates = [str_replace("+", "_", "{$finish}_{$endCap}")];
            }
            break;
        case "downlight":
            $folder = IMAGES_BASE_PATH . "/img/$family/produto/";
            $candidates = ["{$size}_{$lens}"];
            break;
        case "shelf":
            $cleanFinish = str_replace("+", "_", $finish);
            $folder = IMAGES_BASE_PATH . "/img/$family/produto/";
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}_{$endCap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$size}_{$lens}",
                $size,
            ];
            break;
        case "tubular":
            $cleanFinish = str_replace("+", "_", $finish);
            $folder = IMAGES_BASE_PATH . "/img/$family/produto/";
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$size}_{$lens}",
                $size,
            ];
            break;
        case "dynamic":
            $subtype = explode("/", $productId)[1] ?? "";
            $cleanFinish = str_replace("+", "", $finish);
            $folder = IMAGES_BASE_PATH . "/img/$family/$subtype/produto/";
            $candidates = ["{$size}_{$cleanFinish}"];
            break;
    }

    return [
        "candidates" => array_values(array_filter(array_unique($candidates))),
        "preferred_formats" => ["png", "jpg", "jpeg", "webp", "svg"],
        "local_checks" => buildCodeRepairLocalChecksFromFolder($folder, $candidates),
    ];
}

function getCodeRepairTechnicalDrawingLookup(string $productType, string $productId, string $reference, array $config): array {
    $parts = decodeReference($reference);
    $family = (string) ($parts["family"] ?? "");
    $size = (string) ($parts["size"] ?? "");
    $cap = (string) ($parts["cap"] ?? "");
    $connectorCable = (string) ($config["connector_cable"] ?? "0");
    $endCap = (string) ($config["end_cap"] ?? "0");
    $folder = "";
    $candidates = [];

    if ($productType === "barra") {
        $folder = IMAGES_BASE_PATH . "/img/$family/desenhos/";
        $candidates = [
            "{$cap}_{$connectorCable}_{$endCap}",
            "{$cap}_{$endCap}",
            "{$connectorCable}_{$endCap}",
            $cap,
        ];
    } elseif ($productType === "dynamic") {
        $subtype = explode("/", $productId)[1] ?? "";
        $folder = IMAGES_BASE_PATH . "/img/$family/$subtype/desenhos/";
        $candidates = [$size];
    } else {
        $folder = IMAGES_BASE_PATH . "/img/$family/desenhos/";
        $candidates = [$size];
    }

    return [
        "candidates" => array_values(array_filter(array_unique($candidates))),
        "preferred_formats" => ["svg", "png", "jpg", "jpeg"],
        "local_checks" => buildCodeRepairLocalChecksFromFolder($folder, $candidates),
    ];
}

function getCodeRepairColorGraphLookup(string $ledId): array {
    $aliasLedId = resolveColorGraphAlias($ledId);
    $candidates = [$ledId];
    $localChecks = [
        [
            "candidate" => $ledId,
            "base_path" => IMAGES_BASE_PATH . "/img/temperaturas/" . $ledId,
        ],
    ];

    if ($aliasLedId !== $ledId) {
        $candidates[] = $aliasLedId;
        $localChecks[] = [
            "candidate" => $aliasLedId,
            "base_path" => IMAGES_BASE_PATH . "/img/temperaturas/" . $aliasLedId,
        ];
    }

    return [
        "candidates" => array_values(array_filter(array_unique($candidates))),
        "preferred_formats" => ["svg", "png"],
        "local_checks" => $localChecks,
    ];
}

function getCodeRepairLensDiagramLookup(string $productId, string $reference): array {
    $parts = decodeReference($reference);
    $family = (string) ($parts["family"] ?? "");
    $lens = (string) ($parts["lens"] ?? "");
    $base = $family === "48"
        ? IMAGES_BASE_PATH . "/img/$family/" . (explode("/", $productId)[1] ?? "") . "/diagramas/"
        : IMAGES_BASE_PATH . "/img/$family/diagramas/";

    return [
        "diagram" => [
            "candidates" => [$lens],
            "preferred_formats" => ["svg", "png", "jpg", "jpeg"],
            "local_checks" => buildCodeRepairLocalChecksFromFolder($base, [$lens]),
        ],
        "illuminance" => [
            "candidates" => [$lens],
            "preferred_formats" => ["svg", "png", "jpg", "jpeg"],
            "local_checks" => buildCodeRepairLocalChecksFromFolder($base . "i/", [$lens]),
        ],
    ];
}

function getCodeRepairFinishImageLookup(string $productType, string $productId, string $reference, array $config): array {
    $parts = decodeReference($reference);
    $family = (string) ($parts["family"] ?? "");
    $size = (string) ($parts["size"] ?? "");
    $series = (string) ($parts["series"] ?? "");
    $cap = (string) ($parts["cap"] ?? "");
    $lens = strtolower((string) ($config["lens"] ?? ""));
    $finish = strtolower((string) ($config["finish"] ?? ""));
    $endCap = (string) ($config["end_cap"] ?? "0");
    $folder = "";
    $candidates = [];

    switch ($productType) {
        case "barra":
            $folder = $lens === "clear"
                ? IMAGES_BASE_PATH . "/img/$family/acabamentos/$lens/$series/"
                : IMAGES_BASE_PATH . "/img/$family/acabamentos/$lens/";
            $candidates = [
                str_replace("+", "_", "{$finish}_{$cap}"),
                str_replace("+", "_", "{$finish}_{$endCap}"),
            ];
            break;
        case "dynamic":
            $subtype = explode("/", $productId)[1] ?? "";
            $cleanFinish = str_replace("+", "", $finish);
            $folder = IMAGES_BASE_PATH . "/img/$family/$subtype/acabamentos/";
            $candidates = ["{$size}_{$cleanFinish}"];
            break;
        default:
            $folder = IMAGES_BASE_PATH . "/img/$family/acabamentos/";
            $candidates = ["{$size}_{$lens}_{$finish}"];
            break;
    }

    return [
        "candidates" => array_values(array_filter(array_unique($candidates))),
        "preferred_formats" => ["png", "jpg", "jpeg", "webp", "svg"],
        "local_checks" => buildCodeRepairLocalChecksFromFolder($folder, $candidates),
    ];
}

function buildCodeRepairLocalChecksFromFolder(string $folder, array $candidates): array {
    $checks = [];

    foreach (array_values(array_filter(array_unique($candidates))) as $candidate) {
        $checks[] = [
            "candidate" => (string) $candidate,
            "base_path" => $folder . $candidate,
        ];
    }

    return $checks;
}
