<?php

/**
 * Family Registry
 *
 * Canonical runtime map for every family code exposed by the configurator
 * dropdown. The registry is intentionally conservative:
 * - `product_type` reflects the best current runtime class inferred from
 *   family name, Luminos sample IDs, and legacy docs.
 * - `datasheet_runtime_supported` is only true when the current API can route
 *   the family through a real datasheet runtime without inventing data.
 * - `bar_sizes_file` is only set when a real size profile is known.
 * - showcase metadata is derived separately so the API can expose the new
 *   showcase PDF capability without polluting the exact datasheet runtime.
 *
 * This lets the API distinguish:
 * - family recognized by class
 * - family fully supported for datasheet runtime
 * - family still pending a dedicated runtime or asset mapping
 */

function getFamilyRegistry(): array {
    static $registry = null;

    if ($registry !== null) {
        return $registry;
    }

    $baseRegistry = [
        "01" => ["name" => "T8 AC",              "product_type" => "tubular",  "datasheet_runtime_supported" => true],
        "02" => ["name" => "T8 VC",              "product_type" => "tubular",  "datasheet_runtime_supported" => false],
        "03" => ["name" => "T8 CC",              "product_type" => "tubular",  "datasheet_runtime_supported" => false],
        "04" => ["name" => "T5 CC",              "product_type" => "tubular",  "datasheet_runtime_supported" => true],
        "05" => ["name" => "T5 VC",              "product_type" => "tubular",  "datasheet_runtime_supported" => true],
        "06" => ["name" => "T5 AC",              "product_type" => "tubular",  "datasheet_runtime_supported" => true],
        "07" => ["name" => "PLL",                "product_type" => "tubular",  "datasheet_runtime_supported" => true],
        "08" => ["name" => "PLC",                "product_type" => "tubular",  "datasheet_runtime_supported" => false],
        "09" => ["name" => "S14",                "product_type" => "tubular",  "datasheet_runtime_supported" => true],
        "10" => ["name" => "Barra CC",           "product_type" => "barra",    "datasheet_runtime_supported" => true,  "bar_sizes_file" => "barras",     "strict_bar_validation" => true],
        "11" => ["name" => "Barra 24V",          "product_type" => "barra",    "datasheet_runtime_supported" => true,  "bar_sizes_file" => "barras"],
        "12" => ["name" => "Spot",               "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "13" => ["name" => "AR111",              "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "14" => ["name" => "AR111 CC",           "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "15" => ["name" => "AR111 COB",          "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "16" => ["name" => "PAR 30",             "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "17" => ["name" => "PAR 30 CC",          "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "18" => ["name" => "PAR 30 COB",         "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "19" => ["name" => "PAR 38",             "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "20" => ["name" => "PAR 38 CC",          "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "21" => ["name" => "PAR 38 COB",         "product_type" => "spot",     "datasheet_runtime_supported" => false],
        "22" => ["name" => "Decoracao",          "product_type" => "decor",    "datasheet_runtime_supported" => false],
        "23" => ["name" => "Projetores",         "product_type" => "dynamic",  "datasheet_runtime_supported" => false],
        "24" => ["name" => "Campanulas",         "product_type" => "highbay",  "datasheet_runtime_supported" => false],
        "25" => ["name" => "Luminarias",         "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "26" => ["name" => "Retrofit redondo",   "product_type" => "downlight","datasheet_runtime_supported" => false],
        "27" => ["name" => "Retrofit quadrado",  "product_type" => "downlight","datasheet_runtime_supported" => false],
        "28" => ["name" => "Retrofit spot",      "product_type" => "downlight","datasheet_runtime_supported" => false],
        "29" => ["name" => "Downlight redondo",  "product_type" => "downlight","datasheet_runtime_supported" => true],
        "30" => ["name" => "Downlight quadrado", "product_type" => "downlight","datasheet_runtime_supported" => true],
        "31" => ["name" => "Barra RGB 24V VC",   "product_type" => "barra",    "datasheet_runtime_supported" => true,  "strict_bar_validation" => true],
        "32" => ["name" => "Barra 24V T",        "product_type" => "barra",    "datasheet_runtime_supported" => true,  "bar_sizes_file" => "barras_bt"],
        "33" => ["name" => "DL Quadrado COB",    "product_type" => "downlight","datasheet_runtime_supported" => false],
        "34" => ["name" => "DL Redondo COB",     "product_type" => "downlight","datasheet_runtime_supported" => false],
        "35" => ["name" => "Armadura emb",       "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "36" => ["name" => "Armadura ext",       "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "37" => ["name" => "Painel",             "product_type" => "panel",    "datasheet_runtime_supported" => false],
        "38" => ["name" => "Painel Embutir",     "product_type" => "panel",    "datasheet_runtime_supported" => false],
        "39" => ["name" => "Retrofit armadura",  "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "40" => ["name" => "Barra 24V CCT",      "product_type" => "barra",    "datasheet_runtime_supported" => true,  "strict_bar_validation" => true],
        "41" => ["name" => "Projetor CCT",       "product_type" => "dynamic",  "datasheet_runtime_supported" => false],
        "42" => ["name" => "BT CCT",             "product_type" => "barra",    "datasheet_runtime_supported" => false],
        "43" => ["name" => "Decoracao2",         "product_type" => "decor",    "datasheet_runtime_supported" => false],
        "45" => ["name" => "BT45 24V",           "product_type" => "barra",    "datasheet_runtime_supported" => false],
        "46" => ["name" => "Projetor 2",         "product_type" => "dynamic",  "datasheet_runtime_supported" => false],
        "47" => ["name" => "Retrofit campanula", "product_type" => "highbay",  "datasheet_runtime_supported" => false],
        "48" => ["name" => "Dynamic",            "product_type" => "dynamic",  "datasheet_runtime_supported" => true],
        "49" => ["name" => "ShelfLED",           "product_type" => "shelf",    "datasheet_runtime_supported" => true],
        "50" => ["name" => "Armadura IP",        "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "51" => ["name" => "Village",            "product_type" => "decor",    "datasheet_runtime_supported" => false],
        "52" => ["name" => "DualTop embutir",    "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "53" => ["name" => "DualTop saliente",   "product_type" => "luminaire","datasheet_runtime_supported" => false],
        "54" => ["name" => "Canopy",             "product_type" => "canopy",   "datasheet_runtime_supported" => false],
        "55" => ["name" => "Barra 12V",          "product_type" => "barra",    "datasheet_runtime_supported" => true,  "bar_sizes_file" => "barras"],
        "56" => ["name" => "BT 12V",             "product_type" => "barra",    "datasheet_runtime_supported" => true,  "bar_sizes_file" => "barras_bt",  "strict_bar_validation" => true],
        "57" => ["name" => "Projetor 3",         "product_type" => "dynamic",  "datasheet_runtime_supported" => false],
        "58" => ["name" => "B 24V HOT",          "product_type" => "barra",    "datasheet_runtime_supported" => true,  "bar_sizes_file" => "barras_hot"],
        "59" => ["name" => "NEON 24V",           "product_type" => "barra",    "datasheet_runtime_supported" => true,  "strict_bar_validation" => true],
        "60" => ["name" => "B 24V I45",          "product_type" => "barra",    "datasheet_runtime_supported" => true,  "strict_bar_validation" => true],
    ];

    $registry = [];

    foreach ($baseRegistry as $familyCode => $entry) {
        $registry[$familyCode] = buildFamilyRegistryEntry($familyCode, $entry);
    }

    return $registry;
}

function buildFamilyRegistryEntry(string $familyCode, array $entry): array {
    $productType = (string) ($entry["product_type"] ?? "");
    $showcaseMetadata = getFamilyShowcaseMetadata($familyCode, $productType);

    return array_merge($entry, $showcaseMetadata);
}

function getFamilyShowcaseMetadata(string $familyCode, string $productType): array {
    $rendererConfigs = getShowcaseRendererConfigs();
    $rendererConfig = $rendererConfigs[$productType] ?? null;
    $status = getFamilyShowcaseStatus($familyCode);
    $supported = $rendererConfig !== null && $status !== "blocked_until_mapped";

    if ($rendererConfig === null) {
        return [
            "showcase_supported" => false,
            "showcase_runtime_implemented" => false,
            "showcase_renderer" => null,
            "showcase_status" => "blocked_until_mapped",
            "showcase_sections" => [],
            "showcase_expandable_segments" => [],
            "showcase_defaults" => [
                "sections" => [],
                "filters" => getDefaultShowcaseFilters(),
            ],
        ];
    }

    return [
        "showcase_supported" => $supported,
        "showcase_runtime_implemented" => false,
        "showcase_renderer" => $rendererConfig["renderer"],
        "showcase_status" => $status,
        "showcase_sections" => $rendererConfig["sections"],
        "showcase_expandable_segments" => $rendererConfig["expandable_segments"],
        "showcase_defaults" => [
            "sections" => $rendererConfig["default_sections"],
            "filters" => getDefaultShowcaseFilters(),
        ],
    ];
}

function getShowcaseRendererConfigs(): array {
    static $configs = [
        "barra" => [
            "renderer" => "barra",
            "sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes", "accessories", "power_supplies", "connection_cables"],
            "default_sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "downlight" => [
            "renderer" => "downlight",
            "sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes"],
            "default_sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "tubular" => [
            "renderer" => "tubular",
            "sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes"],
            "default_sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "finish_gallery", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "shelf" => [
            "renderer" => "shelf",
            "sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes", "accessories"],
            "default_sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "finish_gallery", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "dynamic" => [
            "renderer" => "dynamic",
            "sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes"],
            "default_sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "finish_gallery", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "spot" => [
            "renderer" => "spot",
            "sections" => ["overview", "luminotechnical", "spectra", "technical_drawings", "lens_diagrams", "finish_gallery", "option_codes"],
            "default_sections" => ["overview", "luminotechnical", "technical_drawings", "finish_gallery", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "decor" => [
            "renderer" => "decor",
            "sections" => ["overview", "luminotechnical", "technical_drawings", "finish_gallery", "option_codes"],
            "default_sections" => ["overview", "luminotechnical", "finish_gallery", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "finish", "cap", "option"],
        ],
        "highbay" => [
            "renderer" => "highbay",
            "sections" => ["overview", "luminotechnical", "technical_drawings", "finish_gallery", "option_codes", "power_supplies"],
            "default_sections" => ["overview", "luminotechnical", "technical_drawings", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "luminaire" => [
            "renderer" => "luminaire",
            "sections" => ["overview", "luminotechnical", "technical_drawings", "finish_gallery", "option_codes", "power_supplies", "connection_cables"],
            "default_sections" => ["overview", "luminotechnical", "technical_drawings", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "lens", "finish", "cap", "option"],
        ],
        "panel" => [
            "renderer" => "panel",
            "sections" => ["overview", "luminotechnical", "technical_drawings", "finish_gallery", "option_codes", "power_supplies"],
            "default_sections" => ["overview", "luminotechnical", "technical_drawings", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "finish", "cap", "option"],
        ],
        "canopy" => [
            "renderer" => "canopy",
            "sections" => ["overview", "luminotechnical", "technical_drawings", "finish_gallery", "option_codes", "power_supplies"],
            "default_sections" => ["overview", "luminotechnical", "technical_drawings", "option_codes"],
            "expandable_segments" => ["size", "color", "cri", "series", "finish", "cap", "option"],
        ],
    ];

    return $configs;
}

function getDefaultShowcaseFilters(): array {
    return [
        "datasheet_ready_only" => true,
        "max_variants" => 80,
        "max_pages" => 30,
        "sort_by" => "reference",
    ];
}

function getFamilyShowcaseStatus(string $familyCode): string {
    $normalizedFamilyCode = str_pad(trim($familyCode), 2, "0", STR_PAD_LEFT);

    $plannedFirstWave = [
        "01", "04", "05", "06",
        "10", "11", "29", "30", "31", "32", "40",
        "48", "49", "55", "58",
    ];

    $plannedLaterWave = [
        "02", "03", "07", "08", "09",
        "23", "26", "27", "28", "33", "34",
        "41", "42", "45", "46", "56", "57", "59", "60",
    ];

    if (in_array($normalizedFamilyCode, $plannedFirstWave, true)) {
        return "planned_first_wave";
    }

    if (in_array($normalizedFamilyCode, $plannedLaterWave, true)) {
        return "planned_later_wave";
    }

    return "blocked_until_mapped";
}

function getFamilyRegistryEntry(string $familyCode): ?array {
    $normalizedFamilyCode = str_pad(trim($familyCode), 2, "0", STR_PAD_LEFT);
    $registry = getFamilyRegistry();
    return $registry[$normalizedFamilyCode] ?? null;
}

function getFamilyRegistryProductType(string $familyCode): ?string {
    $entry = getFamilyRegistryEntry($familyCode);
    return $entry["product_type"] ?? null;
}

function isFamilyDatasheetRuntimeSupported(string $familyCode): bool {
    $entry = getFamilyRegistryEntry($familyCode);
    return (bool) ($entry["datasheet_runtime_supported"] ?? false);
}

function getFamilyBarSizesFile(string $familyCode): ?string {
    $entry = getFamilyRegistryEntry($familyCode);
    return $entry["bar_sizes_file"] ?? null;
}

function requiresStrictBarValidation(string $familyCode): bool {
    $entry = getFamilyRegistryEntry($familyCode);
    return (bool) ($entry["strict_bar_validation"] ?? false);
}

function isFamilyShowcaseSupported(string $familyCode): bool {
    $entry = getFamilyRegistryEntry($familyCode);
    return (bool) ($entry["showcase_supported"] ?? false);
}

function isFamilyShowcaseRuntimeImplemented(string $familyCode): bool {
    $entry = getFamilyRegistryEntry($familyCode);
    return (bool) ($entry["showcase_runtime_implemented"] ?? false);
}

function getFamilyShowcaseRenderer(string $familyCode): ?string {
    $entry = getFamilyRegistryEntry($familyCode);
    return $entry["showcase_renderer"] ?? null;
}

function getFamilyShowcaseSections(string $familyCode): array {
    $entry = getFamilyRegistryEntry($familyCode);
    return $entry["showcase_sections"] ?? [];
}

function getFamilyShowcaseExpandableSegments(string $familyCode): array {
    $entry = getFamilyRegistryEntry($familyCode);
    return $entry["showcase_expandable_segments"] ?? [];
}

function getFamilyShowcaseDefaults(string $familyCode): array {
    $entry = getFamilyRegistryEntry($familyCode);
    return $entry["showcase_defaults"] ?? [
        "sections" => [],
        "filters" => getDefaultShowcaseFilters(),
    ];
}
