<?php

require_once dirname(__FILE__) . "/query.php";
require_once dirname(__FILE__) . "/../reference-decoder.php";
require_once dirname(__FILE__) . "/../luminotechnical.php";
require_once dirname(__FILE__) . "/../product-header.php";
require_once dirname(__FILE__) . "/../technical-drawing.php";
require_once dirname(__FILE__) . "/../sections.php";

function buildShowcaseOverviewSection(array $normalizedRequest, array $query): array {
    $representativeRow = $query["rows"][0] ?? null;
    $header = $representativeRow !== null
        ? getShowcaseHeaderCached($representativeRow, (string) ($query["lang"] ?? "pt"))
        : null;

    return [
        "data" => [
            "type" => "overview",
            "title" => (string) ($query["family_name"] ?? $query["family"] ?? ""),
            "representative_reference" => $representativeRow["reference"] ?? null,
            "representative_product_type" => $representativeRow["product_type"] ?? null,
            "representative_product_id" => $representativeRow["product_id"] ?? null,
            "hero_image" => $header["image"] ?? null,
            "description_html" => $header["description"] ?? "",
            "summary" => [
                "variant_count" => (int) ($query["variant_count"] ?? 0),
                "base_variant_count" => (int) ($query["base_variant_count"] ?? 0),
                "distinct_counts" => $query["distinct_counts"] ?? [],
            ],
            "scope" => [
                "locked" => $normalizedRequest["locked"] ?? [],
                "expanded" => $normalizedRequest["expanded"] ?? [],
            ],
        ],
        "warnings" => [],
    ];
}

function buildShowcaseLuminotechnicalSection(array $query): array {
    $rows = [];
    $missingCount = 0;

    foreach ($query["rows"] ?? [] as $variantRow) {
        $luminotechnical = getShowcaseLuminotechnicalDataCached(
            (string) ($variantRow["product_id"] ?? ""),
            (string) ($variantRow["reference"] ?? ""),
            (string) ($query["lang"] ?? "pt")
        );

        if ($luminotechnical === null) {
            $missingCount++;
            continue;
        }

        $rows[] = [
            "reference" => (string) ($variantRow["reference"] ?? ""),
            "identity" => (string) ($variantRow["identity"] ?? ""),
            "description" => (string) ($variantRow["description"] ?? ""),
            "legacy_description" => (string) ($variantRow["legacy_description"] ?? ""),
            "flux" => $luminotechnical["flux"] ?? null,
            "efficacy" => $luminotechnical["efficacy"] ?? null,
            "energy_class" => $luminotechnical["energy_class"] ?? null,
            "cct" => $luminotechnical["cct"] ?? null,
            "color_label" => $luminotechnical["color_label"] ?? null,
            "cri" => $luminotechnical["cri"] ?? null,
            "led_id" => $luminotechnical["led_id"] ?? null,
            "segments" => $variantRow["segments"] ?? [],
            "segment_labels" => $variantRow["segment_labels"] ?? [],
        ];
    }

    return [
        "data" => [
            "type" => "luminotechnical",
            "rows" => $rows,
            "summary" => [
                "total_rows" => count($rows),
                "missing_rows" => $missingCount,
            ],
        ],
        "warnings" => $missingCount > 0 ? ["showcase_luminotechnical_rows_skipped"] : [],
    ];
}

function buildShowcaseSpectraSection(array $query): array {
    $groups = [];
    $missingCount = 0;

    foreach ($query["rows"] ?? [] as $variantRow) {
        $ledId = trim((string) ($variantRow["led_id"] ?? ""));

        if ($ledId === "") {
            $missingCount++;
            continue;
        }

        $graph = getShowcaseColorGraphCached($ledId, (string) ($query["lang"] ?? "pt"));

        if ($graph === null || trim((string) ($graph["image"] ?? "")) === "") {
            $missingCount++;
            continue;
        }

        $groupKey = (string) ($graph["image"] ?? "");

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                "key" => $groupKey,
                "label" => (string) ($graph["label"] ?? $ledId),
                "image" => (string) ($graph["image"] ?? ""),
                "led_ids" => [],
                "variant_count" => 0,
                "references" => [],
                "color_codes" => [],
                "cri_codes" => [],
            ];
        }

        $groups[$groupKey]["led_ids"][$ledId] = true;
        $groups[$groupKey]["variant_count"]++;
        $groups[$groupKey]["references"][] = (string) ($variantRow["reference"] ?? "");
        $groups[$groupKey]["color_codes"][(string) ($variantRow["segments"]["color"] ?? "")] = true;
        $groups[$groupKey]["cri_codes"][(string) ($variantRow["segments"]["cri"] ?? "")] = true;
    }

    $groups = finalizeShowcaseGroupedItems($groups, static function (array $group): array {
        $group["led_ids"] = array_values(array_keys($group["led_ids"]));
        $group["color_codes"] = array_values(array_filter(array_keys($group["color_codes"])));
        $group["cri_codes"] = array_values(array_filter(array_keys($group["cri_codes"])));
        sort($group["references"]);
        return $group;
    });

    return [
        "data" => [
            "type" => "spectra",
            "groups" => $groups,
        ],
        "warnings" => $missingCount > 0 ? ["showcase_spectra_groups_skipped"] : [],
    ];
}

function buildShowcaseTechnicalDrawingsSection(array $query): array {
    $groups = [];
    $missingCount = 0;
    $representativeRows = getShowcaseRepresentativeRowsByBaseSignature($query["rows"] ?? []);
    $variantCounts = getShowcaseVariantCountsByBaseSignature($query["rows"] ?? []);
    $referencesByBase = getShowcaseReferencesByBaseSignature($query["rows"] ?? []);

    foreach ($query["base_rows"] ?? [] as $baseRow) {
        $baseSignature = (string) ($baseRow["base_signature"] ?? "");
        $variantRow = $representativeRows[$baseSignature] ?? null;

        if ($variantRow === null) {
            $missingCount++;
            continue;
        }

        $drawing = getShowcaseTechnicalDrawingCached($variantRow, (string) ($query["lang"] ?? "pt"));
        $drawingPath = trim((string) ($drawing["drawing"] ?? ""));

        if ($drawingPath === "") {
            $missingCount++;
            continue;
        }

        $dimensions = filterShowcaseDimensions($drawing);
        $groupKey = $drawingPath . "|" . json_encode($dimensions);

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                "key" => $groupKey,
                "drawing" => $drawingPath,
                "dimensions" => $dimensions,
                "variant_count" => 0,
                "base_variant_count" => 0,
                "references" => [],
                "size_codes" => [],
                "lens_codes" => [],
                "cap_codes" => [],
            ];
        }

        $groups[$groupKey]["variant_count"] += (int) ($variantCounts[$baseSignature] ?? 0);
        $groups[$groupKey]["base_variant_count"]++;
        $groups[$groupKey]["references"] = array_merge($groups[$groupKey]["references"], $referencesByBase[$baseSignature] ?? []);
        $groups[$groupKey]["size_codes"][(string) ($baseRow["segments"]["size"] ?? "")] = true;
        $groups[$groupKey]["lens_codes"][(string) ($baseRow["segments"]["lens"] ?? "")] = true;
        $groups[$groupKey]["cap_codes"][(string) ($baseRow["segments"]["cap"] ?? "")] = true;
    }

    $groups = finalizeShowcaseGroupedItems($groups, static function (array $group): array {
        $group["size_codes"] = array_values(array_filter(array_keys($group["size_codes"])));
        $group["lens_codes"] = array_values(array_filter(array_keys($group["lens_codes"])));
        $group["cap_codes"] = array_values(array_filter(array_keys($group["cap_codes"])));
        sort($group["references"]);
        return $group;
    });

    return [
        "data" => [
            "type" => "technical_drawings",
            "groups" => $groups,
        ],
        "warnings" => $missingCount > 0 ? ["showcase_technical_drawings_skipped"] : [],
    ];
}

function buildShowcaseLensDiagramsSection(array $query): array {
    $groups = [];
    $missingCount = 0;
    $representativeRows = getShowcaseRepresentativeRowsByBaseSignature($query["rows"] ?? []);
    $variantCounts = getShowcaseVariantCountsByBaseSignature($query["rows"] ?? []);
    $referencesByBase = getShowcaseReferencesByBaseSignature($query["rows"] ?? []);

    foreach ($query["base_rows"] ?? [] as $baseRow) {
        $lensCode = (string) ($baseRow["segments"]["lens"] ?? "");

        if ($lensCode === "" || $lensCode === "0") {
            continue;
        }

        $baseSignature = (string) ($baseRow["base_signature"] ?? "");
        $variantRow = $representativeRows[$baseSignature] ?? null;

        if ($variantRow === null) {
            $missingCount++;
            continue;
        }

        $diagram = getShowcaseLensDiagramCached(
            (string) ($variantRow["product_id"] ?? ""),
            (string) ($variantRow["reference"] ?? "")
        );

        if ($diagram === null || trim((string) ($diagram["diagram"] ?? "")) === "") {
            $missingCount++;
            continue;
        }

        $groupKey = (string) ($diagram["diagram"] ?? "") . "|" . (string) ($diagram["illuminance"] ?? "");

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                "key" => $groupKey,
                "diagram" => (string) ($diagram["diagram"] ?? ""),
                "illuminance" => $diagram["illuminance"] ?? null,
                "lens_codes" => [],
                "lens_labels" => [],
                "variant_count" => 0,
                "references" => [],
            ];
        }

        $groups[$groupKey]["lens_codes"][$lensCode] = true;
        $groups[$groupKey]["lens_labels"][(string) ($baseRow["segment_labels"]["lens"] ?? $lensCode)] = true;
        $groups[$groupKey]["variant_count"] += (int) ($variantCounts[$baseSignature] ?? 0);
        $groups[$groupKey]["references"] = array_merge($groups[$groupKey]["references"], $referencesByBase[$baseSignature] ?? []);
    }

    $groups = finalizeShowcaseGroupedItems($groups, static function (array $group): array {
        $group["lens_codes"] = array_values(array_filter(array_keys($group["lens_codes"])));
        $group["lens_labels"] = array_values(array_filter(array_keys($group["lens_labels"])));
        sort($group["references"]);
        return $group;
    });

    return [
        "data" => [
            "type" => "lens_diagrams",
            "groups" => $groups,
        ],
        "warnings" => $missingCount > 0 ? ["showcase_lens_diagrams_skipped"] : [],
    ];
}

function buildShowcaseFinishGallerySection(array $query): array {
    $groups = [];
    $missingCount = 0;
    $representativeRows = getShowcaseRepresentativeRowsByBaseSignature($query["rows"] ?? []);
    $variantCounts = getShowcaseVariantCountsByBaseSignature($query["rows"] ?? []);
    $referencesByBase = getShowcaseReferencesByBaseSignature($query["rows"] ?? []);

    foreach ($query["base_rows"] ?? [] as $baseRow) {
        $baseSignature = (string) ($baseRow["base_signature"] ?? "");
        $variantRow = $representativeRows[$baseSignature] ?? null;

        if ($variantRow === null) {
            $missingCount++;
            continue;
        }

        $finishData = getShowcaseFinishAndLensCached($variantRow, (string) ($query["lang"] ?? "pt"));

        if ($finishData === null || trim((string) ($finishData["image"] ?? "")) === "") {
            $missingCount++;
            continue;
        }

        $finishCode = (string) ($baseRow["segments"]["finish"] ?? "");
        $groupKey = (string) ($finishData["image"] ?? "") . "|" . $finishCode;

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                "key" => $groupKey,
                "image" => (string) ($finishData["image"] ?? ""),
                "finish_code" => $finishCode,
                "finish_label" => (string) ($baseRow["segment_labels"]["finish"] ?? $finishCode),
                "finish_name" => (string) ($finishData["finish_name"] ?? ""),
                "variant_count" => 0,
                "references" => [],
                "lens_labels" => [],
            ];
        }

        $groups[$groupKey]["variant_count"] += (int) ($variantCounts[$baseSignature] ?? 0);
        $groups[$groupKey]["references"] = array_merge($groups[$groupKey]["references"], $referencesByBase[$baseSignature] ?? []);
        $groups[$groupKey]["lens_labels"][(string) ($baseRow["segment_labels"]["lens"] ?? "")] = true;
    }

    $groups = finalizeShowcaseGroupedItems($groups, static function (array $group): array {
        $group["lens_labels"] = array_values(array_filter(array_keys($group["lens_labels"])));
        sort($group["references"]);
        return $group;
    });

    return [
        "data" => [
            "type" => "finish_gallery",
            "groups" => $groups,
        ],
        "warnings" => $missingCount > 0 ? ["showcase_finish_gallery_items_skipped"] : [],
    ];
}

function buildShowcaseOptionCodesSection(array $normalizedRequest, array $query): array {
    $groups = [];

    foreach (getShowcaseLegendSegments($normalizedRequest, $query) as $segment) {
        $items = getShowcaseLegendItems($query, $segment);

        if ($items === []) {
            continue;
        }

        $meta = getShowcaseLegendMeta($segment, (string) ($query["lang"] ?? "pt"));
        $groups[] = [
            "segment" => $segment,
            "token" => $meta["token"],
            "title" => $meta["title"],
            "items" => $items,
        ];
    }

    return [
        "data" => [
            "type" => "option_codes",
            "groups" => $groups,
        ],
        "warnings" => [],
    ];
}

function buildShowcasePlaceholderItemsSection(string $type, string $reason): array {
    return [
        "data" => [
            "type" => $type,
            "items" => [],
            "available" => false,
            "reason" => $reason,
        ],
        "warnings" => [],
    ];
}

function getShowcaseRepresentativeRowsByBaseSignature(array $rows): array {
    $map = [];

    foreach ($rows as $row) {
        $baseSignature = (string) ($row["base_signature"] ?? "");

        if ($baseSignature !== "" && !isset($map[$baseSignature])) {
            $map[$baseSignature] = $row;
        }
    }

    return $map;
}

function getShowcaseVariantCountsByBaseSignature(array $rows): array {
    $counts = [];

    foreach ($rows as $row) {
        $baseSignature = (string) ($row["base_signature"] ?? "");

        if ($baseSignature === "") {
            continue;
        }

        $counts[$baseSignature] = ($counts[$baseSignature] ?? 0) + 1;
    }

    return $counts;
}

function getShowcaseReferencesByBaseSignature(array $rows): array {
    $references = [];

    foreach ($rows as $row) {
        $baseSignature = (string) ($row["base_signature"] ?? "");
        $reference = (string) ($row["reference"] ?? "");

        if ($baseSignature === "" || $reference === "") {
            continue;
        }

        $references[$baseSignature][] = $reference;
    }

    foreach ($references as &$items) {
        sort($items);
    }
    unset($items);

    return $references;
}

function finalizeShowcaseGroupedItems(array $groups, callable $finalizer): array {
    $finalized = [];

    foreach ($groups as $group) {
        $finalized[] = $finalizer($group);
    }

    usort($finalized, static function (array $left, array $right): int {
        return strcmp((string) ($left["key"] ?? ""), (string) ($right["key"] ?? ""));
    });

    return $finalized;
}

function filterShowcaseDimensions(array $drawing): array {
    $dimensions = [];

    foreach (["A", "B", "C", "D", "E", "F", "G", "H", "I", "J"] as $dimensionKey) {
        $value = trim((string) ($drawing[$dimensionKey] ?? ""));

        if ($value === "" || $value === "0") {
            continue;
        }

        $dimensions[$dimensionKey] = $value;
    }

    return $dimensions;
}

function getShowcaseLegendSegments(array $normalizedRequest, array $query): array {
    $expanded = array_values(array_filter(
        array_map(static fn($segment): string => strtolower(trim((string) $segment)), $normalizedRequest["expanded"] ?? [])
    ));

    if ($expanded !== []) {
        return $expanded;
    }

    $fallbackSegments = array_values(array_intersect(
        ["finish", "cap", "option"],
        getFamilyShowcaseExpandableSegments((string) ($query["family"] ?? ""))
    ));

    return $fallbackSegments;
}

function getShowcaseLegendItems(array $query, string $segment): array {
    $items = [];

    foreach (($query["options"][$segment] ?? []) as $option) {
        $code = (string) ($option["code"] ?? "");

        if ($code === "") {
            continue;
        }

        $items[] = [
            "code" => $code,
            "label" => (string) ($option["label"] ?? $code),
        ];
    }

    usort($items, static function (array $left, array $right): int {
        return strcmp((string) ($left["code"] ?? ""), (string) ($right["code"] ?? ""));
    });

    return $items;
}

function getShowcaseLegendMeta(string $segment, string $lang): array {
    $titles = [
        "pt" => [
            "size" => "Dimensao",
            "color" => "Espectro de cor",
            "cri" => "CRI",
            "series" => "Serie",
            "lens" => "Optica / lente",
            "finish" => "Acabamento do corpo",
            "cap" => "Tampa / base",
            "option" => "Opcoes",
        ],
        "en" => [
            "size" => "Size",
            "color" => "Color spectrum",
            "cri" => "CRI",
            "series" => "Series",
            "lens" => "Optic / lens",
            "finish" => "Body finish",
            "cap" => "Cap / base",
            "option" => "Options",
        ],
        "es" => [
            "size" => "Dimension",
            "color" => "Espectro de color",
            "cri" => "CRI",
            "series" => "Serie",
            "lens" => "Optica / lente",
            "finish" => "Acabado del cuerpo",
            "cap" => "Tapa / base",
            "option" => "Opciones",
        ],
    ];
    $tokens = [
        "finish" => "XX",
        "cap" => "YY",
        "option" => "ZZ",
    ];
    $language = isset($titles[$lang]) ? $lang : "en";

    return [
        "title" => $titles[$language][$segment] ?? $segment,
        "token" => $tokens[$segment] ?? null,
    ];
}

function getShowcaseVariantConfig(array $variantRow, string $lang): array {
    return [
        "lens" => strtolower(trim((string) ($variantRow["segment_labels"]["lens"] ?? $variantRow["segments"]["lens"] ?? ""))),
        "finish" => strtolower(trim((string) ($variantRow["segment_labels"]["finish"] ?? $variantRow["segments"]["finish"] ?? ""))),
        "connector_cable" => "0",
        "cable_type" => "branco",
        "end_cap" => "0",
        "purpose" => "0",
        "lang" => $lang,
        "extra_length" => 0,
        "option" => (string) ($variantRow["segments"]["option"] ?? "0"),
        "cable_length" => 0,
        "gasket" => 5,
    ];
}

function getShowcaseLuminotechnicalDataCached(string $productId, string $reference, string $lang): ?array {
    static $cache = [];
    $cacheKey = $productId . "|" . $reference . "|" . $lang;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = getLuminotechnicalData($productId, $reference, $lang);
}

function getShowcaseHeaderCached(array $variantRow, string $lang): ?array {
    static $cache = [];
    $cacheKey = implode("|", [
        (string) ($variantRow["product_type"] ?? ""),
        (string) ($variantRow["product_id"] ?? ""),
        (string) ($variantRow["reference"] ?? ""),
        (string) ($variantRow["led_id"] ?? ""),
        $lang,
    ]);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $productType = trim((string) ($variantRow["product_type"] ?? ""));
    $productId = trim((string) ($variantRow["product_id"] ?? ""));
    $reference = trim((string) ($variantRow["reference"] ?? ""));
    $ledId = trim((string) ($variantRow["led_id"] ?? ""));

    if ($productType === "" || $productId === "" || $reference === "" || $ledId === "") {
        return $cache[$cacheKey] = null;
    }

    return $cache[$cacheKey] = getProductHeader(
        $productType,
        $productId,
        $reference,
        $ledId,
        getShowcaseVariantConfig($variantRow, $lang)
    );
}

function getShowcaseTechnicalDrawingCached(array $variantRow, string $lang): array {
    static $cache = [];
    $reference = (string) ($variantRow["reference"] ?? "");
    $productType = (string) ($variantRow["product_type"] ?? "");
    $productId = (string) ($variantRow["product_id"] ?? "");
    $cacheKey = $productType . "|" . $productId . "|" . $reference . "|" . $lang;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = getTechnicalDrawing(
        $productType,
        $reference,
        $productId,
        getBarSizesFile($reference),
        getShowcaseVariantConfig($variantRow, $lang)
    );
}

function getShowcaseColorGraphCached(string $ledId, string $lang): ?array {
    static $cache = [];
    $cacheKey = $ledId . "|" . $lang;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = getColorGraph($ledId, $lang);
}

function getShowcaseLensDiagramCached(string $productId, string $reference): ?array {
    static $cache = [];
    $cacheKey = $productId . "|" . $reference;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = getLensDiagram($productId, $reference);
}

function getShowcaseFinishAndLensCached(array $variantRow, string $lang): ?array {
    static $cache = [];
    $reference = (string) ($variantRow["reference"] ?? "");
    $productType = (string) ($variantRow["product_type"] ?? "");
    $productId = (string) ($variantRow["product_id"] ?? "");
    $cacheKey = $productType . "|" . $productId . "|" . $reference . "|" . $lang;

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    return $cache[$cacheKey] = getFinishAndLens(
        $productType,
        $productId,
        $reference,
        getShowcaseVariantConfig($variantRow, $lang)
    );
}
