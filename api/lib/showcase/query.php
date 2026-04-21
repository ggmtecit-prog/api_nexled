<?php

require_once dirname(__FILE__, 3) . "/bootstrap.php";
require_once dirname(__FILE__) . "/../code-explorer.php";
require_once dirname(__FILE__) . "/../family-registry.php";

const SHOWCASE_IDENTITY_SEGMENTS = ["size", "color", "cri", "series"];
const SHOWCASE_SUFFIX_SEGMENTS = ["lens", "finish", "cap", "option"];

function queryShowcaseVariants(array $normalizedRequest): array {
    $family = (string) ($normalizedRequest["family"] ?? "");
    $lang = (string) ($normalizedRequest["lang"] ?? "pt");
    $locked = is_array($normalizedRequest["locked"] ?? null) ? $normalizedRequest["locked"] : [];
    $filters = is_array($normalizedRequest["filters"] ?? null) ? $normalizedRequest["filters"] : [];
    $datasheetReadyOnly = (bool) ($filters["datasheet_ready_only"] ?? true);
    $familyEntry = getFamilyRegistryEntry($family);
    $options = getCodeExplorerFamilyOptions((int) $family);
    $labelLookups = getCodeExplorerSegmentLabelLookups($options);
    $selectedOptions = getShowcaseSelectedOptions($options, $locked);

    if (($selectedOptions["option"] ?? []) === []) {
        return buildShowcaseEmptyQueryResult($family, $lang, $datasheetReadyOnly, $familyEntry, $options, $selectedOptions, $labelLookups);
    }

    $baseRows = $datasheetReadyOnly
        ? getShowcaseReadyBaseRows($family, $options, $labelLookups)
        : collectShowcaseConfiguratorValidBaseRows($family, $options, $selectedOptions, $locked, $labelLookups);

    if ($baseRows === []) {
        return buildShowcaseEmptyQueryResult($family, $lang, $datasheetReadyOnly, $familyEntry, $options, $selectedOptions, $labelLookups);
    }

    $matchedBaseRows = array_values(array_filter(
        $baseRows,
        static fn(array $baseRow): bool => matchesShowcaseBaseRowLocks($baseRow, $locked)
    ));

    usort($matchedBaseRows, "compareShowcaseBaseRows");

    if ($matchedBaseRows === []) {
        return buildShowcaseEmptyQueryResult($family, $lang, $datasheetReadyOnly, $familyEntry, $options, $selectedOptions, $labelLookups);
    }

    $variantRows = buildShowcaseVariantRows($family, $matchedBaseRows, $selectedOptions, $labelLookups, $options);

    return [
        "family" => $family,
        "family_name" => (string) ($familyEntry["name"] ?? $family),
        "lang" => $lang,
        "datasheet_ready_only" => $datasheetReadyOnly,
        "options" => $options,
        "selected_options" => $selectedOptions,
        "label_lookups" => $labelLookups,
        "base_rows" => $matchedBaseRows,
        "rows" => $variantRows,
        "base_variant_count" => count($matchedBaseRows),
        "variant_count" => count($variantRows),
        "distinct_counts" => buildShowcaseDistinctCounts($matchedBaseRows, $selectedOptions),
    ];
}

function getShowcaseQuerySummary(array $normalizedRequest): array {
    $query = queryShowcaseVariants($normalizedRequest);

    return [
        "family" => $query["family"],
        "datasheet_ready_only" => $query["datasheet_ready_only"],
        "base_variant_count" => $query["base_variant_count"],
        "variant_count" => $query["variant_count"],
        "distinct_counts" => $query["distinct_counts"],
    ];
}

function buildShowcaseEmptyQueryResult(
    string $family,
    string $lang,
    bool $datasheetReadyOnly,
    ?array $familyEntry,
    array $options,
    array $selectedOptions,
    array $labelLookups
): array {
    return [
        "family" => $family,
        "family_name" => (string) ($familyEntry["name"] ?? $family),
        "lang" => $lang,
        "datasheet_ready_only" => $datasheetReadyOnly,
        "options" => $options,
        "selected_options" => $selectedOptions,
        "label_lookups" => $labelLookups,
        "base_rows" => [],
        "rows" => [],
        "base_variant_count" => 0,
        "variant_count" => 0,
        "distinct_counts" => [
            "identities" => 0,
            "led_ids" => 0,
            "drawings" => 0,
            "lenses" => 0,
            "finishes" => 0,
            "caps" => 0,
            "options" => 0,
        ],
    ];
}

function buildShowcaseDistinctCounts(array $baseRows, array $selectedOptions): array {
    $identityMap = [];
    $ledIds = [];
    $drawingKeys = [];
    $nonZeroLensCodes = [];
    $finishCodes = [];
    $capCodes = [];
    $optionCodes = [];

    foreach ($baseRows as $baseRow) {
        $identity = (string) ($baseRow["identity"] ?? "");
        $ledId = trim((string) ($baseRow["led_id"] ?? ""));
        $drawingKey = buildShowcaseDrawingKey($baseRow);
        $lensCode = (string) ($baseRow["lens"] ?? "");
        $finishCode = (string) ($baseRow["finish"] ?? "");
        $capCode = (string) ($baseRow["cap"] ?? "");

        if ($identity !== "") {
            $identityMap[$identity] = true;
        }

        if ($ledId !== "") {
            $ledIds[$ledId] = true;
        }

        if ($drawingKey !== "") {
            $drawingKeys[$drawingKey] = true;
        }

        if ($lensCode !== "" && $lensCode !== "0") {
            $nonZeroLensCodes[$lensCode] = true;
        }

        if ($finishCode !== "") {
            $finishCodes[$finishCode] = true;
        }

        if ($capCode !== "") {
            $capCodes[$capCode] = true;
        }
    }

    foreach (($selectedOptions["option"] ?? []) as $option) {
        $optionCode = (string) ($option["code"] ?? "");

        if ($optionCode !== "") {
            $optionCodes[$optionCode] = true;
        }
    }

    return [
        "identities" => count($identityMap),
        "led_ids" => count($ledIds),
        "drawings" => count($drawingKeys),
        "lenses" => count($nonZeroLensCodes),
        "finishes" => count($finishCodes),
        "caps" => count($capCodes),
        "options" => count($optionCodes),
    ];
}

function getShowcaseSelectedOptions(array $options, array $locked): array {
    $selected = [];

    foreach (SHOWCASE_SUFFIX_SEGMENTS as $segment) {
        $selected[$segment] = getShowcaseSelectedSegmentOptions(
            $options[$segment] ?? [],
            $locked[$segment] ?? null,
            $segment
        );
    }

    return $selected;
}

function getShowcaseSelectedSegmentOptions(array $segmentOptions, mixed $lockedValue, string $segment): array {
    $normalizedOptions = $segmentOptions;

    if ($normalizedOptions === []) {
        $normalizedOptions[] = [
            "code" => str_repeat("0", getCodeExplorerSegmentLength($segment)),
            "label" => "0",
        ];
    }

    $lockedCode = trim((string) $lockedValue);

    if ($lockedCode === "") {
        return array_values($normalizedOptions);
    }

    $matchedOptions = array_values(array_filter(
        $normalizedOptions,
        static fn(array $option): bool => (string) ($option["code"] ?? "") === $lockedCode
    ));

    if ($matchedOptions !== []) {
        return $matchedOptions;
    }

    return [[
        "code" => $lockedCode,
        "label" => $lockedCode,
    ]];
}

function getShowcaseReadyBaseRows(string $familyCode, array $options, array $labelLookups): array {
    $familyName = (string) ((getFamilyRegistryEntry($familyCode)["name"] ?? $familyCode));
    $baseRows = loadFamilyReadyProductsBaseRows($familyCode);

    if ($baseRows === null) {
        $identities = getCodeExplorerLuminosIdentities($familyCode);
        $baseRows = collectFamilyReadyProductBaseRows($familyCode, $options, $identities);
        storeFamilyReadyProductsBaseRows($familyCode, $familyName, $baseRows);
    }

    return array_values(array_map(
        static fn(array $baseRow): array => enrichShowcaseBaseRow($familyCode, $baseRow, $labelLookups, $options),
        $baseRows
    ));
}

function collectShowcaseConfiguratorValidBaseRows(
    string $familyCode,
    array $options,
    array $selectedOptions,
    array $locked,
    array $labelLookups
): array {
    $identities = getCodeExplorerLuminosIdentities($familyCode);
    $baseRows = [];
    $seen = [];

    foreach ($identities as $identityData) {
        $identity = (string) ($identityData["identity"] ?? "");

        if (!matchesShowcaseIdentityLocks($identity, $locked)) {
            continue;
        }

        foreach ($selectedOptions["lens"] as $lens) {
            foreach ($selectedOptions["finish"] as $finish) {
                foreach ($selectedOptions["cap"] as $cap) {
                    $productId = resolveCodeExplorerProductId($familyCode, $identityData, (string) ($cap["code"] ?? ""));

                    if ($productId === null || $productId === "") {
                        continue;
                    }

                    $rawBaseRow = [
                        "identity" => $identity,
                        "description" => (string) ($identityData["description"] ?? ""),
                        "product_type" => (string) ($identityData["product_type"] ?? ""),
                        "product_id" => (string) $productId,
                        "led_id" => (string) ($identityData["led_id"] ?? ""),
                        "lens" => (string) ($lens["code"] ?? ""),
                        "finish" => (string) ($finish["code"] ?? ""),
                        "cap" => (string) ($cap["code"] ?? ""),
                    ];
                    $baseSignature = buildShowcaseBaseSignature(
                        (string) $rawBaseRow["identity"],
                        (string) $rawBaseRow["lens"],
                        (string) $rawBaseRow["finish"],
                        (string) $rawBaseRow["cap"]
                    );

                    if (isset($seen[$baseSignature])) {
                        continue;
                    }

                    $seen[$baseSignature] = true;
                    $baseRows[] = enrichShowcaseBaseRow($familyCode, $rawBaseRow, $labelLookups, $options);
                }
            }
        }
    }

    return $baseRows;
}

function enrichShowcaseBaseRow(string $familyCode, array $baseRow, array $labelLookups, array $options): array {
    $identity = (string) ($baseRow["identity"] ?? "");
    $lens = (string) ($baseRow["lens"] ?? "");
    $finish = (string) ($baseRow["finish"] ?? "");
    $cap = (string) ($baseRow["cap"] ?? "");
    $parts = strlen($identity) === REFERENCE_LENGTH_IDENTITY
        ? decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY))
        : [];

    return [
        "identity" => $identity,
        "description" => (string) ($baseRow["description"] ?? ""),
        "product_type" => (string) ($baseRow["product_type"] ?? ""),
        "product_id" => (string) ($baseRow["product_id"] ?? ""),
        "led_id" => (string) ($baseRow["led_id"] ?? ""),
        "lens" => $lens,
        "finish" => $finish,
        "cap" => $cap,
        "base_signature" => buildShowcaseBaseSignature($identity, $lens, $finish, $cap),
        "reference_prefix" => $identity . $lens . $finish . $cap,
        "segments" => [
            "family" => $familyCode,
            "size" => (string) ($parts["size"] ?? ""),
            "color" => (string) ($parts["color"] ?? ""),
            "cri" => (string) ($parts["cri"] ?? ""),
            "series" => (string) ($parts["series"] ?? ""),
            "lens" => $lens,
            "finish" => $finish,
            "cap" => $cap,
        ],
        "segment_labels" => [
            "size" => getShowcaseSegmentLabel($options, $labelLookups, "size", (string) ($parts["size"] ?? "")),
            "color" => getShowcaseSegmentLabel($options, $labelLookups, "color", (string) ($parts["color"] ?? "")),
            "cri" => getShowcaseSegmentLabel($options, $labelLookups, "cri", (string) ($parts["cri"] ?? "")),
            "series" => getShowcaseSegmentLabel($options, $labelLookups, "series", (string) ($parts["series"] ?? "")),
            "lens" => getShowcaseSegmentLabel($options, $labelLookups, "lens", $lens),
            "finish" => getShowcaseSegmentLabel($options, $labelLookups, "finish", $finish),
            "cap" => getShowcaseSegmentLabel($options, $labelLookups, "cap", $cap),
        ],
    ];
}

function buildShowcaseVariantRows(
    string $familyCode,
    array $baseRows,
    array $selectedOptions,
    array $labelLookups,
    array $options
): array {
    $variantRows = [];

    foreach ($baseRows as $baseRow) {
        foreach (($selectedOptions["option"] ?? []) as $option) {
            $optionCode = (string) ($option["code"] ?? "");

            if ($optionCode === "") {
                continue;
            }

            $reference = (string) ($baseRow["reference_prefix"] ?? "") . $optionCode;

            if (strlen($reference) !== REFERENCE_LENGTH_FULL) {
                continue;
            }

            $variantRows[] = [
                "reference" => $reference,
                "identity" => (string) ($baseRow["identity"] ?? ""),
                "base_signature" => (string) ($baseRow["base_signature"] ?? ""),
                "description" => (string) ($baseRow["description"] ?? ""),
                "product_type" => (string) ($baseRow["product_type"] ?? ""),
                "product_id" => (string) ($baseRow["product_id"] ?? ""),
                "led_id" => (string) ($baseRow["led_id"] ?? ""),
                "segments" => [
                    "family" => $familyCode,
                    "size" => (string) ($baseRow["segments"]["size"] ?? ""),
                    "color" => (string) ($baseRow["segments"]["color"] ?? ""),
                    "cri" => (string) ($baseRow["segments"]["cri"] ?? ""),
                    "series" => (string) ($baseRow["segments"]["series"] ?? ""),
                    "lens" => (string) ($baseRow["segments"]["lens"] ?? ""),
                    "finish" => (string) ($baseRow["segments"]["finish"] ?? ""),
                    "cap" => (string) ($baseRow["segments"]["cap"] ?? ""),
                    "option" => $optionCode,
                ],
                "segment_labels" => [
                    "size" => (string) ($baseRow["segment_labels"]["size"] ?? ""),
                    "color" => (string) ($baseRow["segment_labels"]["color"] ?? ""),
                    "cri" => (string) ($baseRow["segment_labels"]["cri"] ?? ""),
                    "series" => (string) ($baseRow["segment_labels"]["series"] ?? ""),
                    "lens" => (string) ($baseRow["segment_labels"]["lens"] ?? ""),
                    "finish" => (string) ($baseRow["segment_labels"]["finish"] ?? ""),
                    "cap" => (string) ($baseRow["segment_labels"]["cap"] ?? ""),
                    "option" => getShowcaseSegmentLabel($options, $labelLookups, "option", $optionCode),
                ],
            ];
        }
    }

    usort($variantRows, "compareShowcaseVariantRows");

    foreach ($variantRows as &$variantRow) {
        $variantRow["legacy_description"] = buildCodeExplorerLegacyDescription($variantRow);
    }
    unset($variantRow);

    return $variantRows;
}

function getShowcaseSegmentLabel(array $options, array $labelLookups, string $segment, string $code): string {
    if ($code === "") {
        return "";
    }

    if (isset($labelLookups[$segment][$code])) {
        return (string) $labelLookups[$segment][$code];
    }

    if (in_array($segment, ["lens", "finish", "cap", "option"], true)) {
        return resolveCodeExplorerOptionLabel($options[$segment] ?? [], $code);
    }

    return $code;
}

function matchesShowcaseIdentityLocks(string $identity, array $locked): bool {
    if (strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
        return false;
    }

    $parts = decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));

    foreach (SHOWCASE_IDENTITY_SEGMENTS as $segment) {
        $lockedValue = trim((string) ($locked[$segment] ?? ""));

        if ($lockedValue !== "" && (($parts[$segment] ?? "") !== $lockedValue)) {
            return false;
        }
    }

    return true;
}

function matchesShowcaseBaseRowLocks(array $baseRow, array $locked): bool {
    $identity = (string) ($baseRow["identity"] ?? "");

    if (!matchesShowcaseIdentityLocks($identity, $locked)) {
        return false;
    }

    foreach (["lens", "finish", "cap"] as $segment) {
        $lockedValue = trim((string) ($locked[$segment] ?? ""));

        if ($lockedValue !== "" && ((string) ($baseRow[$segment] ?? "") !== $lockedValue)) {
            return false;
        }
    }

    return true;
}

function buildShowcaseBaseSignature(string $identity, string $lens, string $finish, string $cap): string {
    return implode("|", [$identity, $lens, $finish, $cap]);
}

function compareShowcaseBaseRows(array $left, array $right): int {
    return strcmp(
        (string) ($left["reference_prefix"] ?? ""),
        (string) ($right["reference_prefix"] ?? "")
    );
}

function compareShowcaseVariantRows(array $left, array $right): int {
    return strcmp(
        (string) ($left["reference"] ?? ""),
        (string) ($right["reference"] ?? "")
    );
}

function buildShowcaseDrawingKey(array $baseRow): string {
    $productType = (string) ($baseRow["product_type"] ?? "");
    $productId = trim((string) ($baseRow["product_id"] ?? ""));
    $identity = (string) ($baseRow["identity"] ?? "");
    $cap = (string) ($baseRow["cap"] ?? "");

    if ($productId === "") {
        return "";
    }

    $parts = strlen($identity) === REFERENCE_LENGTH_IDENTITY
        ? decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY))
        : [];
    $size = (string) ($parts["size"] ?? "");

    return match ($productType) {
        "barra" => implode("|", [$productId, $cap]),
        "dynamic" => implode("|", [$productId, $size, $cap]),
        default => implode("|", [$productId, $size]),
    };
}
