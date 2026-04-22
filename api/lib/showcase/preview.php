<?php

require_once dirname(__FILE__) . "/query.php";
require_once dirname(__FILE__) . "/request.php";

function buildShowcasePreview(array $normalizedRequest, array $warnings = []): array {
    $family = (string) ($normalizedRequest["family"] ?? "");
    $filters = is_array($normalizedRequest["filters"] ?? null) ? $normalizedRequest["filters"] : [];
    $sections = is_array($normalizedRequest["sections"] ?? null) ? $normalizedRequest["sections"] : [];
    $querySummary = getShowcaseQuerySummary($normalizedRequest);
    $variantCount = (int) ($querySummary["variant_count"] ?? 0);

    if ($variantCount <= 0) {
        return buildShowcaseRequestError(
            422,
            "showcase_no_matching_variants",
            "Showcase request matched no valid variants.",
            [
                "family" => $family,
            ]
        );
    }

    $renderer = (string) (getFamilyShowcaseRenderer($family) ?? "");
    $estimate = estimateShowcasePageCount($renderer, $sections, $querySummary, $normalizedRequest);
    $estimatedPages = (int) ($estimate["estimated_pages"] ?? 0);
    $maxVariants = (int) ($filters["max_variants"] ?? 80);
    $maxPages = (int) ($filters["max_pages"] ?? 30);
    $allWarnings = array_values(array_unique(array_merge(
        $warnings,
        $estimate["warnings"] ?? [],
        (($querySummary["datasheet_ready_only"] ?? true) ? [] : ["showcase_preview_includes_datasheet_blocked_variants"])
    )));

    if ($variantCount > $maxVariants) {
        return buildShowcaseRequestError(
            422,
            "showcase_limit_exceeded",
            "Showcase request exceeds configured limits.",
            [
                "detail" => "Matched {$variantCount} variants but max_variants is {$maxVariants}.",
                "variant_count" => $variantCount,
                "estimated_pages" => $estimatedPages,
                "limits" => [
                    "max_variants" => $maxVariants,
                    "max_pages" => $maxPages,
                ],
            ]
        );
    }

    if ($estimatedPages > $maxPages) {
        return buildShowcaseRequestError(
            422,
            "showcase_limit_exceeded",
            "Showcase request exceeds configured limits.",
            [
                "detail" => "Estimated {$estimatedPages} pages but max_pages is {$maxPages}.",
                "variant_count" => $variantCount,
                "estimated_pages" => $estimatedPages,
                "limits" => [
                    "max_variants" => $maxVariants,
                    "max_pages" => $maxPages,
                ],
            ]
        );
    }

    return [
        "ok" => true,
        "data" => [
            "variant_count" => $variantCount,
            "estimated_pages" => $estimatedPages,
            "warnings" => $allWarnings,
            "counts" => $querySummary["distinct_counts"] ?? [],
        ],
    ];
}

function estimateShowcasePageCount(string $renderer, array $sections, array $querySummary, array $normalizedRequest = []): array {
    $profile = getShowcaseEstimateProfile($renderer);
    $counts = $querySummary["distinct_counts"] ?? [];
    $variantCount = (int) ($querySummary["variant_count"] ?? 0);
    $expandedSegments = array_values(array_filter(
        array_map(static fn($segment): string => strtolower(trim((string) $segment)), $normalizedRequest["expanded"] ?? [])
    ));
    $legendGroupCount = max(1, count($expandedSegments));

    if ($renderer === "downlight") {
        return estimateDownlightShowcasePageCount($sections, $counts, $variantCount, $legendGroupCount);
    }

    $estimatedPages = 0;

    foreach ($sections as $section) {
        $estimatedPages += match ($section) {
            "overview" => 1,
            "luminotechnical" => max(1, (int) ceil($variantCount / max(1, (int) ($profile["luminotechnical_rows_per_page"] ?? 18)))),
            "spectra" => ((int) ($counts["led_ids"] ?? 0)) > 0
                ? max(1, (int) ceil(((int) ($counts["led_ids"] ?? 0)) / max(1, (int) ($profile["spectra_per_page"] ?? 4))))
                : 0,
            "technical_drawings" => ((int) ($counts["drawings"] ?? 0)) > 0
                ? max(1, (int) ceil(((int) ($counts["drawings"] ?? 0)) / max(1, (int) ($profile["technical_drawings_per_page"] ?? 2))))
                : 0,
            "lens_diagrams" => ((int) ($counts["lenses"] ?? 0)) > 0
                ? max(1, (int) ceil(((int) ($counts["lenses"] ?? 0)) / max(1, (int) ($profile["lens_diagrams_per_page"] ?? 6))))
                : 0,
            "finish_gallery" => ((int) ($counts["finishes"] ?? 0)) > 0
                ? max(1, (int) ceil(((int) ($counts["finishes"] ?? 0)) / max(1, (int) ($profile["finish_gallery_per_page"] ?? 6))))
                : 0,
            "option_codes" => max(1, (int) ceil($legendGroupCount / max(1, (int) ($profile["option_code_groups_per_page"] ?? 2)))),
            "accessories" => 1,
            "power_supplies" => 1,
            "connection_cables" => 1,
            default => 0,
        };
    }

    return [
        "estimated_pages" => max(1, $estimatedPages),
        "warnings" => ["showcase_estimated_pages_is_heuristic"],
    ];
}

function estimateDownlightShowcasePageCount(array $sections, array $counts, int $variantCount, int $legendGroupCount): array {
    $estimatedPages = 0;
    $identityCount = max(1, (int) ($counts["identities"] ?? $variantCount));
    $spectraGroupEstimate = max(0, min(
        $identityCount,
        max(1, (int) ceil((int) ($counts["led_ids"] ?? 0) * 0.7))
    ));
    $hasOverview = in_array("overview", $sections, true);
    $hasLumino = in_array("luminotechnical", $sections, true);
    $hasDrawing = in_array("technical_drawings", $sections, true);
    $hasSpectra = in_array("spectra", $sections, true);
    $hasLens = in_array("lens_diagrams", $sections, true);
    $hasOptions = in_array("option_codes", $sections, true);

    if ($hasOverview) {
        $estimatedPages++;
    }

    if ($hasLumino || $hasDrawing) {
        if ($hasLumino) {
            $estimatedPages += max(1, (int) ceil($identityCount / 14));
        } else {
            $estimatedPages++;
        }
    }

    if ($hasSpectra) {
        $estimatedPages += $spectraGroupEstimate > 0
            ? max(1, (int) ceil($spectraGroupEstimate / 3))
            : 0;
    }

    if ($hasLens || $hasOptions) {
        $lensPages = $hasLens && ((int) ($counts["lenses"] ?? 0)) > 0
            ? max(1, (int) ceil(((int) ($counts["lenses"] ?? 0)) / 2))
            : 0;
        $suffixLegendGroupCount = min(3, $legendGroupCount);
        $optionPages = $hasOptions ? max(1, (int) ceil($suffixLegendGroupCount / 3)) : 0;
        $estimatedPages += max($lensPages, $optionPages, 1);
    }

    return [
        "estimated_pages" => max(1, $estimatedPages),
        "warnings" => ["showcase_estimated_pages_is_heuristic"],
    ];
}

function getShowcaseEstimateProfile(string $renderer): array {
    return match ($renderer) {
        "barra" => [
            "luminotechnical_rows_per_page" => 20,
            "spectra_per_page" => 4,
            "technical_drawings_per_page" => 2,
            "lens_diagrams_per_page" => 6,
            "finish_gallery_per_page" => 8,
            "option_code_groups_per_page" => 2,
        ],
        "downlight" => [
            "luminotechnical_rows_per_page" => 18,
            "spectra_per_page" => 4,
            "technical_drawings_per_page" => 2,
            "lens_diagrams_per_page" => 6,
            "finish_gallery_per_page" => 6,
            "option_code_groups_per_page" => 2,
        ],
        "tubular", "dynamic", "shelf" => [
            "luminotechnical_rows_per_page" => 18,
            "spectra_per_page" => 4,
            "technical_drawings_per_page" => 2,
            "lens_diagrams_per_page" => 6,
            "finish_gallery_per_page" => 6,
            "option_code_groups_per_page" => 2,
        ],
        default => [
            "luminotechnical_rows_per_page" => 16,
            "spectra_per_page" => 4,
            "technical_drawings_per_page" => 2,
            "lens_diagrams_per_page" => 4,
            "finish_gallery_per_page" => 6,
            "option_code_groups_per_page" => 2,
        ],
    };
}
