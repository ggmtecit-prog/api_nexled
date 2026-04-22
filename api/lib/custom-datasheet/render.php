<?php

require_once dirname(__FILE__) . "/../pdf-engine.php";
require_once dirname(__FILE__) . "/assets.php";

function buildCustomDatasheetPreviewSnapshot(array $normalizedRequest): array {
    $baseRequest = $normalizedRequest["base_request"] ?? [];
    $custom = $normalizedRequest["custom"] ?? [];

    if (!is_array($baseRequest)) {
        throwDatasheetRequestError(400, [
            "error" => "Custom datasheet request requires a valid base_request payload.",
            "error_code" => "custom_datasheet_invalid_request",
        ]);
    }

    if (!is_array($custom)) {
        throwDatasheetRequestError(400, [
            "error" => "Custom datasheet request requires a valid custom payload.",
            "error_code" => "custom_datasheet_invalid_request",
        ]);
    }

    $context = buildDatasheetRenderContext($baseRequest);
    $fieldSnapshot = buildCustomDatasheetFieldSnapshot($normalizedRequest, $context);
    assertCustomDatasheetCopyOverridesAvailable($context, $custom);
    $context = applyCustomDatasheetOverridesToContext($context, $custom);

    return [
        "context" => $context,
        "field_snapshot" => $fieldSnapshot,
        "editable_copy" => buildCustomDatasheetEditableCopySnapshot($context),
    ];
}

function buildCustomDatasheetPdfBinary(array $normalizedRequest): string {
    $preview = buildCustomDatasheetPreviewSnapshot($normalizedRequest);
    $context = $preview["context"] ?? [];

    return renderDatasheetPdfBinaryFromContext($context);
}

function applyCustomDatasheetOverridesToContext(array $context, array $custom): array {
    $textOverrides = is_array($custom["text_overrides"] ?? null) ? $custom["text_overrides"] : [];
    $assetOverrides = is_array($custom["asset_overrides"] ?? null) ? $custom["asset_overrides"] : [];
    $fieldOverrides = is_array($custom["field_overrides"] ?? null) ? $custom["field_overrides"] : [];
    $copyOverrides = is_array($custom["copy_overrides"] ?? null) ? $custom["copy_overrides"] : [];
    $sectionVisibility = is_array($custom["section_visibility"] ?? null) ? $custom["section_visibility"] : [];
    $footer = is_array($custom["footer"] ?? null) ? $custom["footer"] : [];

    if (isset($textOverrides["document_title"])) {
        $context["document_title"] = (string) $textOverrides["document_title"];
    }

    if (isset($textOverrides["header_copy"])) {
        $context["data"]["header"]["description"] = (string) $textOverrides["header_copy"];
    }

    if (isset($textOverrides["footer_note"])) {
        $context["footer_note"] = (string) $textOverrides["footer_note"];
    }

    if (isset($footer["marker"])) {
        $context["footer_marker"] = (string) $footer["marker"];
    }

    applyCustomDatasheetAssetOverrides($context, $assetOverrides);
    applyCustomDatasheetSectionVisibility($context, $sectionVisibility);
    applyCustomDatasheetCopyOverrides($context, $copyOverrides);
    applyCustomDatasheetFieldOverrides($context, $fieldOverrides);

    return $context;
}

function buildCustomDatasheetFieldSnapshot(array $normalizedRequest, array $context): array {
    $baseRequest = is_array($normalizedRequest["base_request"] ?? null) ? $normalizedRequest["base_request"] : [];
    $reference = (string) ($context["reference"] ?? $normalizedRequest["base_reference"] ?? "");
    $parts = decodeReference($reference);
    $data = is_array($context["data"] ?? null) ? $context["data"] : [];

    $snapshot = [
        "display_reference" => $reference,
        "display_description" => (string) ($data["description"] ?? $context["description"] ?? $baseRequest["descricao"] ?? ""),
        "display_size" => (string) ($parts["size"] ?? ""),
        "display_color" => (string) ($parts["color"] ?? ""),
        "display_cri" => (string) ($parts["cri"] ?? ""),
        "display_series" => (string) ($parts["series"] ?? ""),
        "display_lens_name" => (string) ($data["lens_name"] ?? $baseRequest["lente"] ?? ""),
        "display_finish_name" => (string) ($data["finish"]["finish_name"] ?? $baseRequest["acabamento"] ?? ""),
        "display_cap" => (string) ($parts["cap"] ?? $baseRequest["tampa"] ?? ""),
        "display_option_code" => (string) ($parts["option"] ?? $baseRequest["opcao"] ?? ""),
        "display_connector_cable" => (string) ($baseRequest["conectorcabo"] ?? ""),
        "display_cable_type" => (string) ($baseRequest["tipocabo"] ?? ""),
        "display_extra_length" => (string) ($baseRequest["acrescimo"] ?? ""),
        "display_end_cap" => (string) ($baseRequest["tampa"] ?? ""),
        "display_gasket" => (string) ($baseRequest["vedante"] ?? ""),
        "display_ip" => (string) ($baseRequest["ip"] ?? $data["ip_rating"] ?? ""),
        "display_fixing" => (string) ($baseRequest["fixacao"] ?? ""),
        "display_power_supply" => (string) ($baseRequest["fonte"] ?? ""),
        "display_connection_cable" => (string) ($baseRequest["caboligacao"] ?? ""),
        "display_connection_connector" => (string) ($baseRequest["conectorligacao"] ?? ""),
        "display_connection_cable_length" => (string) ($baseRequest["tamanhocaboligacao"] ?? ""),
        "display_purpose" => (string) ($baseRequest["finalidade"] ?? ""),
        "display_company" => (string) ($baseRequest["empresa"] ?? $context["company"] ?? ""),
        "display_language" => (string) ($baseRequest["idioma"] ?? $context["lang"] ?? ""),
        "display_flux" => (string) ($data["luminotechnical"]["flux"] ?? ""),
        "display_efficacy" => (string) ($data["luminotechnical"]["efficacy"] ?? ""),
        "display_cct" => (string) ($data["luminotechnical"]["cct"] ?? ""),
        "display_color_label" => (string) ($data["luminotechnical"]["color_label"] ?? ""),
        "display_cri_label" => (string) ($data["luminotechnical"]["cri"] ?? ""),
        "fixing_name" => (string) ($data["fixing"]["name"] ?? ""),
        "power_supply_description" => (string) ($data["power_supply"]["description"] ?? ""),
        "connection_cable_description" => (string) ($data["connection_cable"]["description"] ?? ""),
    ];

    foreach (range("A", "J") as $dimension) {
        $snapshot["drawing_dimension_" . $dimension] = (string) ($data["drawing"][$dimension] ?? "");
    }

    $allowedFields = getFamilyCustomDatasheetAllowedFields($normalizedRequest["family"] ?? "")["field_overrides"] ?? [];
    $filtered = [];

    foreach ($allowedFields as $field) {
        if (!is_string($field)) {
            continue;
        }

        $filtered[$field] = isset($snapshot[$field]) ? trim((string) $snapshot[$field]) : "";
    }

    return $filtered;
}

function buildCustomDatasheetEditableCopySnapshot(array $context): array {
    $data = is_array($context["data"] ?? null) ? $context["data"] : [];
    $snapshot = [];

    if (isset($data["header"])) {
        $snapshot["header"] = [
            "intro" => trim((string) ($data["header"]["description"] ?? "")),
        ];
    }

    if (isset($data["characteristics"])) {
        $snapshot["characteristics"] = [
            "intro" => trim((string) ($data["characteristics_intro"] ?? "")),
        ];
    }

    if (isset($data["luminotechnical"])) {
        $snapshot["luminotechnical"] = [
            "intro" => trim((string) ($data["luminotechnical_intro"] ?? "")),
        ];
    }

    if (isset($data["drawing"])) {
        $snapshot["drawing"] = [
            "intro" => trim((string) ($data["drawing_intro"] ?? "")),
        ];
    }

    if (!empty($data["color_graph"])) {
        $snapshot["color_graph"] = [
            "intro" => trim((string) ($data["color_graph_intro"] ?? "")),
        ];
    }

    if (!empty($data["lens_diagram"])) {
        $snapshot["lens_diagram"] = [
            "intro" => trim((string) ($data["lens_diagram_intro"] ?? "")),
        ];
    }

    if (isset($data["finish"])) {
        $snapshot["finish"] = [
            "intro" => trim((string) ($data["finish_intro"] ?? "")),
        ];
    }

    if (isset($data["fixing"])) {
        $snapshot["fixing"] = [
            "intro" => trim((string) ($data["fixing_intro"] ?? "")),
        ];
    }

    if (isset($data["power_supply"])) {
        $snapshot["power_supply"] = [
            "intro" => trim((string) ($data["power_supply_intro"] ?? $data["power_supply"]["description"] ?? "")),
        ];
    }

    if (isset($data["connection_cable"])) {
        $snapshot["connection_cable"] = [
            "intro" => trim((string) ($data["connection_cable_intro"] ?? $data["connection_cable"]["description"] ?? "")),
        ];
    }

    $snapshot["footer"] = [
        "note" => trim((string) ($context["footer_note"] ?? "")),
    ];

    return $snapshot;
}

function assertCustomDatasheetCopyOverridesAvailable(array $context, array $custom): void {
    $copyOverrides = is_array($custom["copy_overrides"] ?? null) ? $custom["copy_overrides"] : [];

    if ($copyOverrides === []) {
        return;
    }

    $availableSections = array_keys(buildCustomDatasheetEditableCopySnapshot($context));

    foreach ($copyOverrides as $section => $sectionOverrides) {
        if (!is_array($sectionOverrides)) {
            continue;
        }

        if (!in_array($section, $availableSections, true)) {
            throwDatasheetRequestError(422, [
                "error" => "Advanced custom copy section is not available for this product.",
                "error_code" => "custom_datasheet_copy_section_unavailable",
                "section" => $section,
            ]);
        }
    }
}

function getCustomDatasheetFieldSummaryLabelMap(string $lang): array {
    $pt = [
        "display_size" => "Medida",
        "display_color" => "Cor",
        "display_cri" => "CRI",
        "display_series" => "Série",
        "display_cap" => "Tampa / base",
        "display_option_code" => "Código da opção",
        "display_connector_cable" => "Conector do cabo",
        "display_cable_type" => "Tipo de cabo",
        "display_extra_length" => "Comprimento adicional",
        "display_end_cap" => "Tampa",
        "display_gasket" => "Vedante",
        "display_ip" => "Classificação IP",
        "display_fixing" => "Fixação",
        "display_power_supply" => "Fonte de alimentação",
        "display_connection_cable" => "Cabo de ligação",
        "display_connection_connector" => "Conector de ligação",
        "display_connection_cable_length" => "Comprimento do cabo de ligação",
        "display_purpose" => "Finalidade",
        "display_company" => "Empresa",
        "display_language" => "Idioma",
        "fixing_name" => "Nome da fixação",
        "power_supply_description" => "Descrição da fonte",
        "connection_cable_description" => "Descrição do cabo de ligação",
    ];
    $en = [
        "display_size" => "Size",
        "display_color" => "Color",
        "display_cri" => "CRI",
        "display_series" => "Series",
        "display_cap" => "Cap / base",
        "display_option_code" => "Option code",
        "display_connector_cable" => "Cable connector",
        "display_cable_type" => "Cable type",
        "display_extra_length" => "Extra length",
        "display_end_cap" => "End cap",
        "display_gasket" => "Gasket",
        "display_ip" => "IP rating",
        "display_fixing" => "Fixing",
        "display_power_supply" => "Power supply",
        "display_connection_cable" => "Connection cable",
        "display_connection_connector" => "Connection connector",
        "display_connection_cable_length" => "Connection cable length",
        "display_purpose" => "Purpose",
        "display_company" => "Company",
        "display_language" => "Language",
        "fixing_name" => "Fixing name",
        "power_supply_description" => "Power supply description",
        "connection_cable_description" => "Connection cable description",
    ];

    return $lang === "pt" ? $pt : $en;
}

function applyCustomDatasheetFieldOverrides(array &$context, array $fieldOverrides): void {
    if ($fieldOverrides === []) {
        return;
    }

    $summaryLabels = getCustomDatasheetFieldSummaryLabelMap((string) ($context["lang"] ?? "pt"));
    $summaryRows = [];

    foreach ($fieldOverrides as $field => $value) {
        $text = trim((string) $value);

        if ($text === "") {
            continue;
        }

        switch ($field) {
            case "display_reference":
                setCustomDatasheetNestedValue($context, ["data", "display_reference"], $text);
                break;
            case "display_description":
                $context["document_title"] = $text;
                $context["description"] = $text;
                setCustomDatasheetNestedValue($context, ["data", "description"], $text);
                break;
            case "display_lens_name":
                setCustomDatasheetNestedValue($context, ["data", "lens_name"], $text);
                break;
            case "display_finish_name":
                setCustomDatasheetNestedValue($context, ["data", "finish", "finish_name"], $text);
                break;
            case "display_flux":
                setCustomDatasheetNestedValue($context, ["data", "luminotechnical", "flux"], $text);
                break;
            case "display_efficacy":
                setCustomDatasheetNestedValue($context, ["data", "luminotechnical", "efficacy"], $text);
                break;
            case "display_cct":
                setCustomDatasheetNestedValue($context, ["data", "luminotechnical", "cct"], $text);
                break;
            case "display_color_label":
                setCustomDatasheetNestedValue($context, ["data", "luminotechnical", "color_label"], $text);
                break;
            case "display_cri_label":
                setCustomDatasheetNestedValue($context, ["data", "luminotechnical", "cri"], $text);
                break;
            case "fixing_name":
                if (isset($context["data"]["fixing"])) {
                    setCustomDatasheetNestedValue($context, ["data", "fixing", "name"], $text);
                    break;
                }
                $summaryRows[] = ["label" => $summaryLabels[$field] ?? $field, "value" => $text];
                break;
            case "power_supply_description":
                if (isset($context["data"]["power_supply"])) {
                    setCustomDatasheetNestedValue($context, ["data", "power_supply", "description"], $text);
                    break;
                }
                $summaryRows[] = ["label" => $summaryLabels[$field] ?? $field, "value" => $text];
                break;
            case "connection_cable_description":
                if (isset($context["data"]["connection_cable"])) {
                    setCustomDatasheetNestedValue($context, ["data", "connection_cable", "description"], $text);
                    break;
                }
                $summaryRows[] = ["label" => $summaryLabels[$field] ?? $field, "value" => $text];
                break;
            default:
                if (str_starts_with($field, "drawing_dimension_")) {
                    $dimension = strtoupper(substr($field, -1));
                    setCustomDatasheetNestedValue($context, ["data", "drawing", $dimension], $text);
                    break;
                }

                $summaryRows[] = [
                    "label" => $summaryLabels[$field] ?? $field,
                    "value" => $text,
                ];
                break;
        }
    }

    if ($summaryRows !== []) {
        setCustomDatasheetNestedValue($context, ["data", "custom_field_summary"], $summaryRows);
    }
}

function applyCustomDatasheetAssetOverrides(array &$context, array $assetOverrides): void {
    $assetTargets = [
        "header_image" => ["data", "header", "image"],
        "drawing_image" => ["data", "drawing", "drawing"],
        "finish_image" => ["data", "finish", "image"],
    ];

    foreach ($assetTargets as $field => $path) {
        if (!isset($assetOverrides[$field]) || !is_array($assetOverrides[$field])) {
            continue;
        }

        $resolvedAsset = resolveCustomDatasheetAssetOverride($assetOverrides[$field]);

        if (!is_string($resolvedAsset) || trim($resolvedAsset) === "") {
            throwDatasheetRequestError(422, [
                "error" => "Custom datasheet asset override could not be resolved.",
                "error_code" => "custom_datasheet_asset_missing",
                "field" => $field,
            ]);
        }

        setCustomDatasheetNestedValue($context, $path, $resolvedAsset);
    }
}

function applyCustomDatasheetSectionVisibility(array &$context, array $sectionVisibility): void {
    $sectionTargets = [
        "fixing" => "fixing",
        "power_supply" => "power_supply",
        "connection_cable" => "connection_cable",
    ];

    foreach ($sectionTargets as $field => $dataKey) {
        if (($sectionVisibility[$field] ?? true) !== false) {
            continue;
        }

        unset($context["data"][$dataKey]);
    }
}

function applyCustomDatasheetCopyOverrides(array &$context, array $copyOverrides): void {
    foreach ($copyOverrides as $section => $sectionOverrides) {
        if (!is_array($sectionOverrides)) {
            continue;
        }

        if ($section === "footer" && isset($sectionOverrides["note"])) {
            $context["footer_note"] = (string) $sectionOverrides["note"];
            continue;
        }

        if (!isset($sectionOverrides["intro"])) {
            continue;
        }

        $text = (string) $sectionOverrides["intro"];

        switch ($section) {
            case "header":
                setCustomDatasheetNestedValue($context, ["data", "header", "description"], $text);
                break;
            case "characteristics":
                setCustomDatasheetNestedValue($context, ["data", "characteristics_intro"], $text);
                break;
            case "luminotechnical":
                setCustomDatasheetNestedValue($context, ["data", "luminotechnical_intro"], $text);
                break;
            case "drawing":
                setCustomDatasheetNestedValue($context, ["data", "drawing_intro"], $text);
                break;
            case "color_graph":
                if (!empty($context["data"]["color_graph"])) {
                    setCustomDatasheetNestedValue($context, ["data", "color_graph_intro"], $text);
                }
                break;
            case "lens_diagram":
                if (!empty($context["data"]["lens_diagram"])) {
                    setCustomDatasheetNestedValue($context, ["data", "lens_diagram_intro"], $text);
                }
                break;
            case "finish":
                setCustomDatasheetNestedValue($context, ["data", "finish_intro"], $text);
                break;
            case "fixing":
                if (isset($context["data"]["fixing"])) {
                    setCustomDatasheetNestedValue($context, ["data", "fixing_intro"], $text);
                }
                break;
            case "power_supply":
                if (isset($context["data"]["power_supply"])) {
                    setCustomDatasheetNestedValue($context, ["data", "power_supply_intro"], $text);
                }
                break;
            case "connection_cable":
                if (isset($context["data"]["connection_cable"])) {
                    setCustomDatasheetNestedValue($context, ["data", "connection_cable_intro"], $text);
                }
                break;
        }
    }
}

function setCustomDatasheetNestedValue(array &$root, array $path, mixed $value): void {
    $cursor = &$root;
    $lastIndex = count($path) - 1;

    foreach ($path as $index => $segment) {
        if ($index === $lastIndex) {
            $cursor[$segment] = $value;
            return;
        }

        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }

        $cursor = &$cursor[$segment];
    }
}
