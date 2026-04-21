<?php

require_once dirname(__FILE__) . "/request.php";
require_once dirname(__FILE__) . "/sections.php";

function assembleShowcasePayload(array $normalizedRequest): array {
    $query = queryShowcaseVariants($normalizedRequest);

    if (($query["variant_count"] ?? 0) <= 0) {
        return buildShowcaseRequestError(
            422,
            "showcase_no_matching_variants",
            "Showcase request matched no valid variants.",
            [
                "family" => $query["family"] ?? ($normalizedRequest["family"] ?? ""),
            ]
        );
    }

    $sections = [];
    $warnings = [];

    foreach (($normalizedRequest["sections"] ?? []) as $sectionName) {
        $result = buildShowcaseSectionPayload((string) $sectionName, $normalizedRequest, $query);
        $sections[$sectionName] = $result["data"];
        $warnings = array_values(array_unique(array_merge($warnings, $result["warnings"] ?? [])));
    }

    return [
        "ok" => true,
        "data" => [
            "family" => [
                "code" => (string) ($query["family"] ?? ""),
                "name" => (string) ($query["family_name"] ?? ""),
            ],
            "renderer" => getFamilyShowcaseRenderer((string) ($query["family"] ?? "")),
            "scope" => [
                "locked" => $normalizedRequest["locked"] ?? [],
                "expanded" => $normalizedRequest["expanded"] ?? [],
                "variant_count" => (int) ($query["variant_count"] ?? 0),
                "base_variant_count" => (int) ($query["base_variant_count"] ?? 0),
                "datasheet_ready_only" => (bool) ($query["datasheet_ready_only"] ?? true),
            ],
            "counts" => $query["distinct_counts"] ?? [],
            "sections" => $sections,
            "warnings" => $warnings,
        ],
    ];
}

function buildShowcaseSectionPayload(string $sectionName, array $normalizedRequest, array $query): array {
    return match ($sectionName) {
        "overview" => buildShowcaseOverviewSection($normalizedRequest, $query),
        "luminotechnical" => buildShowcaseLuminotechnicalSection($query),
        "spectra" => buildShowcaseSpectraSection($query),
        "technical_drawings" => buildShowcaseTechnicalDrawingsSection($query),
        "lens_diagrams" => buildShowcaseLensDiagramsSection($query),
        "finish_gallery" => buildShowcaseFinishGallerySection($query),
        "option_codes" => buildShowcaseOptionCodesSection($normalizedRequest, $query),
        "accessories" => buildShowcasePlaceholderItemsSection("accessories", "showcase_accessories_not_derived_yet"),
        "power_supplies" => buildShowcasePlaceholderItemsSection("power_supplies", "showcase_power_supplies_not_derived_yet"),
        "connection_cables" => buildShowcasePlaceholderItemsSection("connection_cables", "showcase_connection_cables_not_derived_yet"),
        default => [
            "data" => [
                "type" => $sectionName,
                "items" => [],
            ],
            "warnings" => [],
        ],
    };
}
