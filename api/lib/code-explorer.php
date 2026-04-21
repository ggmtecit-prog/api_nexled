<?php

require_once dirname(__FILE__) . "/images.php";
require_once dirname(__FILE__) . "/reference-decoder.php";
require_once dirname(__FILE__) . "/luminotechnical.php";
require_once dirname(__FILE__) . "/characteristics.php";
require_once dirname(__FILE__) . "/product-header.php";
require_once dirname(__FILE__) . "/technical-drawing.php";
require_once dirname(__FILE__) . "/sections.php";

const CODE_EXPLORER_DEFAULT_PAGE = 1;
const CODE_EXPLORER_DEFAULT_PAGE_SIZE = 100;
const CODE_EXPLORER_MAX_PAGE_SIZE = 250;
const CODE_EXPLORER_STATUS_ALL = "all";
const CODE_EXPLORER_STATUS_CONFIGURATOR_VALID = "configurator_valid";
const CODE_EXPLORER_STATUS_CONFIGURATOR_INVALID = "configurator_invalid";
const CODE_EXPLORER_STATUS_DATASHEET_READY = "datasheet_ready";
const CODE_EXPLORER_STATUS_DATASHEET_BLOCKED = "datasheet_blocked";
const CODE_EXPLORER_DEFAULT_LANG = "pt";
const CODE_EXPLORER_MAX_FULL_MATRIX_ROWS = 1000000;
const CODE_EXPLORER_SEGMENT_KEYS = ["size", "color", "cri", "series", "lens", "finish", "cap", "option"];
const FAMILY_READY_FILTER_KEYS = ["product_type", "size", "color", "cri", "series", "lens", "finish", "cap"];
const CODE_EXPLORER_MODE_SEARCH = "search";
const CODE_EXPLORER_MODE_FILTERS = "filters";
const CODE_EXPLORER_SEARCH_TYPE_CODE = "code";
const CODE_EXPLORER_SEARCH_TYPE_DESCRIPTION = "description";
const FAMILY_READY_PRODUCTS_CACHE_VERSION = 4;

function getCodeExplorerMode(mixed $value): string {
    $normalized = trim(strtolower((string) $value));

    return in_array($normalized, [CODE_EXPLORER_MODE_SEARCH, CODE_EXPLORER_MODE_FILTERS], true)
        ? $normalized
        : CODE_EXPLORER_MODE_FILTERS;
}

function getCodeExplorerSearchType(mixed $value): string {
    $normalized = trim(strtolower((string) $value));
    $allowed = [
        CODE_EXPLORER_SEARCH_TYPE_CODE,
        CODE_EXPLORER_SEARCH_TYPE_DESCRIPTION,
    ];

    return in_array($normalized, $allowed, true)
        ? $normalized
        : CODE_EXPLORER_SEARCH_TYPE_CODE;
}

function getCodeExplorerStatusFilter(string $value): string {
    $normalized = trim(strtolower($value));
    $allowed = [
        CODE_EXPLORER_STATUS_ALL,
        CODE_EXPLORER_STATUS_CONFIGURATOR_VALID,
        CODE_EXPLORER_STATUS_CONFIGURATOR_INVALID,
        CODE_EXPLORER_STATUS_DATASHEET_READY,
        CODE_EXPLORER_STATUS_DATASHEET_BLOCKED,
    ];

    return in_array($normalized, $allowed, true)
        ? $normalized
        : CODE_EXPLORER_STATUS_ALL;
}

function getCodeExplorerPage(mixed $value): int {
    $page = intval($value);
    return $page > 0 ? $page : CODE_EXPLORER_DEFAULT_PAGE;
}

function getCodeExplorerPageSize(mixed $value): int {
    $pageSize = intval($value);

    if ($pageSize <= 0) {
        return CODE_EXPLORER_DEFAULT_PAGE_SIZE;
    }

    return min($pageSize, CODE_EXPLORER_MAX_PAGE_SIZE);
}

function sanitizeCodeExplorerSearch(mixed $value): string {
    return trim((string) $value);
}

function getCodeExplorerLanguage(mixed $value): string {
    $normalized = trim(strtolower((string) $value));
    return in_array($normalized, ["pt", "en", "es"], true)
        ? $normalized
        : CODE_EXPLORER_DEFAULT_LANG;
}

function getCodeExplorerIncludeInvalid(mixed $value): bool {
    if (is_bool($value)) {
        return $value;
    }

    $normalized = trim(strtolower((string) $value));
    return in_array($normalized, ["1", "true", "yes", "on"], true);
}

function getCodeExplorerSegmentFilters(array $input, array $options): array {
    $filters = [];

    foreach (CODE_EXPLORER_SEGMENT_KEYS as $segment) {
        $rawValue = trim((string) ($input[$segment] ?? ""));
        $normalizedValue = preg_replace('/\s+/', '', $rawValue);
        $segmentLength = getCodeExplorerSegmentLength($segment);

        if ($normalizedValue !== "" && $segmentLength > 0 && ctype_digit($normalizedValue) && strlen($normalizedValue) < $segmentLength) {
            $normalizedValue = str_pad($normalizedValue, $segmentLength, "0", STR_PAD_LEFT);
        }

        $allowedCodes = array_map(
            static fn(array $option): string => (string) ($option["code"] ?? ""),
            $options[$segment] ?? []
        );

        $filters[$segment] = in_array($normalizedValue, $allowedCodes, true)
            ? $normalizedValue
            : "";
    }

    return $filters;
}

function getCodeExplorerFilteredOptions(array $options, array $segmentFilters): array {
    $filteredOptions = [];

    foreach ($options as $segment => $segmentOptions) {
        $filterCode = (string) ($segmentFilters[$segment] ?? "");

        if ($filterCode === "") {
            $filteredOptions[$segment] = $segmentOptions;
            continue;
        }

        $filteredOptions[$segment] = array_values(array_filter(
            $segmentOptions,
            static fn(array $option): bool => (string) ($option["code"] ?? "") === $filterCode
        ));
    }

    return $filteredOptions;
}

function getCodeExplorerFilteredIdentities(array $identities, array $segmentFilters): array {
    $identitySegments = ["size", "color", "cri", "series"];

    if (!hasCodeExplorerSegmentDrillDown(array_intersect_key($segmentFilters, array_flip($identitySegments)))) {
        return $identities;
    }

    return array_values(array_filter($identities, static function (array $identityData) use ($segmentFilters): bool {
        $identity = (string) ($identityData["identity"] ?? "");

        if (strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
            return false;
        }

        $parts = decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));

        foreach (["size", "color", "cri", "series"] as $segment) {
            $filterCode = (string) ($segmentFilters[$segment] ?? "");

            if ($filterCode !== "" && ($parts[$segment] ?? "") !== $filterCode) {
                return false;
            }
        }

        return true;
    }));
}

function hasCodeExplorerSegmentDrillDown(array $segmentFilters): bool {
    foreach ($segmentFilters as $value) {
        if ((string) $value !== "") {
            return true;
        }
    }

    return false;
}

function matchesCodeExplorerSegmentFilters(array $segments, array $segmentFilters): bool {
    foreach (CODE_EXPLORER_SEGMENT_KEYS as $segment) {
        $filterCode = (string) ($segmentFilters[$segment] ?? "");

        if ($filterCode !== "" && (($segments[$segment] ?? "") !== $filterCode)) {
            return false;
        }
    }

    return true;
}

function getCodeExplorerIdentityMatrixSize(array $options): int {
    return count($options["size"])
        * count($options["color"])
        * count($options["cri"])
        * count($options["series"]);
}

function getCodeExplorerSuffixMatrixSize(array $options): int {
    return count($options["lens"])
        * count($options["finish"])
        * count($options["cap"])
        * count($options["option"]);
}

function getCodeExplorerFullMatrixSize(array $options): int {
    return getCodeExplorerIdentityMatrixSize($options)
        * getCodeExplorerSuffixMatrixSize($options);
}

function getCodeExplorerValidMatrixSize(array $options, array $identities): int {
    return count($identities) * getCodeExplorerSuffixMatrixSize($options);
}

function normalizeCodeExplorerReferenceSearch(string $search): string {
    return strtoupper(preg_replace('/\s+/', '', trim($search)));
}

function isCodeExplorerTargetedReferenceSearch(string $search, string $familyCode): bool {
    $normalized = normalizeCodeExplorerReferenceSearch($search);

    if ($normalized === "") {
        return false;
    }

    if (strlen($normalized) < REFERENCE_LENGTH_IDENTITY || strlen($normalized) > REFERENCE_LENGTH_FULL) {
        return false;
    }

    return str_starts_with($normalized, $familyCode);
}

function getCodeExplorerFamilyMeta(int $family): ?array {
    $con = connectDBReferencias();
    $stmt = mysqli_prepare($con, "SELECT nome, codigo FROM Familias WHERE codigo = ? LIMIT 1");

    if (!$stmt) {
        closeDB($con);
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $family);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $meta = null;

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $code = str_pad((string) ($row["codigo"] ?? $family), 2, "0", STR_PAD_LEFT);
        $meta = [
            "code" => $code,
            "name" => $row["nome"] ?? $code,
        ];
    }

    mysqli_stmt_close($stmt);
    closeDB($con);

    return $meta;
}

function getCodeExplorerFamilyOptions(int $family): array {
    $con = connectDBReferencias();

    $options = [
        "size" => [],
        "color" => [],
        "cri" => [],
        "series" => [],
        "lens" => [],
        "finish" => [],
        "cap" => [],
        "option" => [],
    ];

    $queries = [
        "size" => "SELECT Tamanhos.tamanho FROM Tamanhos, Familias WHERE Tamanhos.familia = Familias.tamanhos AND Familias.codigo = $family ORDER BY tamanho",
        "color" => "SELECT Cor.cor, Cor.codigo FROM Cor, Familias WHERE Cor.familia = Familias.cor AND Familias.codigo = $family ORDER BY Cor.codigo",
        "cri" => "SELECT cri, codigo FROM CRI ORDER BY codigo",
        "series" => "SELECT Series.series, Series.codigo FROM Series, Familias WHERE Series.familia = Familias.series AND Familias.codigo = $family ORDER BY codigo",
        "lens" => "SELECT Acrilico.acrilico, Acrilico.codigo, Acrilico.desc FROM Acrilico, Familias WHERE Acrilico.familia = Familias.acrilico AND Familias.codigo = $family ORDER BY codigo",
        "finish" => "SELECT Acabamento.acabamento, Acabamento.codigo, Acabamento.desc FROM Acabamento, Familias WHERE Acabamento.familia = Familias.acabamento AND Familias.codigo = $family ORDER BY codigo",
        "cap" => "SELECT Cap.cap, Cap.codigo, Cap.desc FROM Cap, Familias WHERE Cap.familia = Familias.cap AND Familias.codigo = $family ORDER BY codigo",
        "option" => "SELECT Opcao.opcao, Opcao.codigo, Opcao.desc FROM Opcao, Familias WHERE Opcao.familia = Familias.opcao AND Familias.codigo = $family ORDER BY codigo",
    ];

    foreach ($queries as $key => $query) {
        $result = mysqli_query($con, $query);

        if (!$result) {
            continue;
        }

        while ($row = mysqli_fetch_assoc($result)) {
            $label = "";

            if ($key === "size") {
                $label = (string) ($row["tamanho"] ?? "");
            } elseif ($key === "color") {
                $label = (string) ($row["cor"] ?: $row["codigo"]);
            } elseif ($key === "cri") {
                $label = (string) ($row["cri"] ?: $row["codigo"]);
            } elseif ($key === "series") {
                $label = (string) ($row["series"] ?: $row["codigo"]);
            } elseif ($key === "lens") {
                $label = (string) ($row["desc"] ?: $row["acrilico"] ?: $row["codigo"]);
            } elseif ($key === "finish") {
                $label = (string) ($row["desc"] ?: $row["acabamento"] ?: $row["codigo"]);
            } elseif ($key === "cap") {
                $label = (string) ($row["desc"] ?: $row["cap"] ?: $row["codigo"]);
            } else {
                $label = (string) ($row["desc"] ?: $row["opcao"] ?: $row["codigo"]);
            }

            $options[$key][] = [
                "code" => str_pad((string) ($row["codigo"] ?? $row["tamanho"] ?? ""), getCodeExplorerSegmentLength($key), "0", STR_PAD_LEFT),
                "label" => $label,
            ];
        }
    }

    closeDB($con);

    return $options;
}

function getCodeExplorerSegmentLength(string $segment): int {
    return match ($segment) {
        "size" => REFERENCE_LENGTH_SIZE,
        "color" => REFERENCE_LENGTH_COLOR,
        "cri" => REFERENCE_LENGTH_CRI,
        "series" => REFERENCE_LENGTH_SERIES,
        "lens" => REFERENCE_LENGTH_LENS,
        "finish" => REFERENCE_LENGTH_FINISH,
        "cap" => REFERENCE_LENGTH_CAP,
        "option" => REFERENCE_LENGTH_OPTION,
        default => 0,
    };
}

function normalizeFamilyReadyFilterValue(string $key, mixed $value): string {
    $normalizedValue = preg_replace('/\s+/', '', trim((string) $value));

    if ($normalizedValue === "") {
        return "";
    }

    if ($key === "product_type") {
        return strtolower($normalizedValue);
    }

    $segmentLength = getCodeExplorerSegmentLength($key);

    if ($segmentLength > 0 && ctype_digit($normalizedValue) && strlen($normalizedValue) < $segmentLength) {
        return str_pad($normalizedValue, $segmentLength, "0", STR_PAD_LEFT);
    }

    return $normalizedValue;
}

function getFamilyReadyFilterAllowedValues(string $key, array $options, array $baseRows): array {
    if ($key === "product_type") {
        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => strtolower((string) ($row["product_type"] ?? "")),
            $baseRows
        ))));
    }

    return array_values(array_filter(array_map(
        static fn(array $option): string => (string) ($option["code"] ?? ""),
        $options[$key] ?? []
    )));
}

function getFamilyReadyFilters(array $input, array $options, array $baseRows): array {
    $filters = [];

    foreach (FAMILY_READY_FILTER_KEYS as $key) {
        $rawValue = trim((string) ($input[$key] ?? ""));

        if ($rawValue === "") {
            $filters[$key] = [];
            continue;
        }

        $allowedValues = getFamilyReadyFilterAllowedValues($key, $options, $baseRows);
        $normalizedValues = array_values(array_unique(array_filter(array_map(
            static fn(string $value): string => normalizeFamilyReadyFilterValue($key, $value),
            explode(",", $rawValue)
        ))));

        $filters[$key] = array_values(array_filter(
            $normalizedValues,
            static fn(string $value): bool => in_array($value, $allowedValues, true)
        ));
    }

    return $filters;
}

function filterFamilyReadyBaseRows(array $baseRows, array $filters): array {
    return array_values(array_filter($baseRows, static function (array $row) use ($filters): bool {
        foreach (FAMILY_READY_FILTER_KEYS as $key) {
            $allowedValues = $filters[$key] ?? [];

            if (!is_array($allowedValues) || count($allowedValues) === 0) {
                continue;
            }

            $rowValue = normalizeFamilyReadyFilterValue($key, $row[$key] ?? "");

            if (!in_array($rowValue, $allowedValues, true)) {
                return false;
            }
        }

        return true;
    }));
}

function getFamilyReadyAppliedFilters(array $filters): array {
    $applied = [];

    foreach (FAMILY_READY_FILTER_KEYS as $key) {
        $values = $filters[$key] ?? [];

        if (is_array($values) && count($values) > 0) {
            $applied[$key] = array_values($values);
        }
    }

    return $applied;
}

function getCodeExplorerLuminosIdentities(string $familyCode): array {
    $con = connectDBLampadas();
    $like = $familyCode . "%";
    $stmt = mysqli_prepare($con, "SELECT ref, ID, ID_Led, `desc` FROM Luminos WHERE ref LIKE ? ORDER BY ref ASC, ID ASC");

    if (!$stmt) {
        closeDB($con);
        return [];
    }

    mysqli_stmt_bind_param($stmt, "s", $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $identities = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $identity = substr((string) ($row["ref"] ?? ""), 0, REFERENCE_LENGTH_IDENTITY);

        if (strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
            continue;
        }

        if (!isset($identities[$identity])) {
            $productType = getProductType($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));
            $identities[$identity] = [
                "identity" => $identity,
                "description" => (string) ($row["desc"] ?? ""),
                "product_type" => $productType,
                "product_id" => (string) ($row["ID"] ?? ""),
                "led_id" => (string) ($row["ID_Led"] ?? ""),
                "dynamic_ids" => [],
            ];
        }

        $productId = (string) ($row["ID"] ?? "");

        if (str_contains($productId, "campanulas")) {
            $identities[$identity]["dynamic_ids"]["1"] = $productId;
        } elseif (str_contains($productId, "projetores")) {
            $identities[$identity]["dynamic_ids"]["0"] = $productId;
        } elseif ($identities[$identity]["product_id"] === "") {
            $identities[$identity]["product_id"] = $productId;
        }

        if ($identities[$identity]["description"] === "" && !empty($row["desc"])) {
            $identities[$identity]["description"] = (string) $row["desc"];
        }
    }

    mysqli_stmt_close($stmt);
    closeDB($con);

    return array_values($identities);
}

function createCodeExplorerCoverageEntry(string $identity, ?array $identityData, array $segmentLookups, ?string $defaultProductType): array {
    $parts = decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));

    return [
        "identity" => $identity,
        "description" => (string) ($identityData["description"] ?? ""),
        "product_type" => (string) ($identityData["product_type"] ?? $defaultProductType ?? ""),
        "product_id" => getCodeExplorerCoverageProductPreview($identityData),
        "segments" => [
            "family" => $parts["family"] ?? "",
            "size" => $parts["size"] ?? "",
            "color" => $parts["color"] ?? "",
            "cri" => $parts["cri"] ?? "",
            "series" => $parts["series"] ?? "",
        ],
        "segment_labels" => [
            "size" => $segmentLookups["size"][$parts["size"] ?? ""] ?? ($parts["size"] ?? ""),
            "color" => $segmentLookups["color"][$parts["color"] ?? ""] ?? ($parts["color"] ?? ""),
            "cri" => $segmentLookups["cri"][$parts["cri"] ?? ""] ?? ($parts["cri"] ?? ""),
            "series" => $segmentLookups["series"][$parts["series"] ?? ""] ?? ($parts["series"] ?? ""),
        ],
        "counts" => [
            "total_codes" => 0,
            "configurator_valid" => 0,
            "configurator_invalid" => 0,
            "datasheet_ready" => 0,
            "datasheet_blocked" => 0,
        ],
        "top_failure_reason" => "",
        "blocked_reasons" => [],
        "status" => "invalid",
        "is_fully_ready" => false,
        "ready_ratio" => 0,
    ];
}

function getCodeExplorerCoverageProductPreview(?array $identityData): string {
    if ($identityData === null) {
        return "";
    }

    $productId = trim((string) ($identityData["product_id"] ?? ""));

    if ($productId !== "") {
        return $productId;
    }

    foreach (($identityData["dynamic_ids"] ?? []) as $dynamicProductId) {
        $normalizedProductId = trim((string) $dynamicProductId);

        if ($normalizedProductId !== "") {
            return $normalizedProductId;
        }
    }

    return "";
}

function ensureCodeExplorerCoverageEntry(array &$coverageMap, string $identity, ?array $identityData, array $segmentLookups, ?string $defaultProductType): void {
    if (isset($coverageMap[$identity])) {
        return;
    }

    $coverageMap[$identity] = createCodeExplorerCoverageEntry($identity, $identityData, $segmentLookups, $defaultProductType);
}

function updateCodeExplorerCoverageCounts(array &$coverageMap, string $identity, ?array $identityData, array $segmentLookups, ?string $defaultProductType, bool $isConfiguratorValid, bool $datasheetReady, string $failureReason, int $count): void {
    ensureCodeExplorerCoverageEntry($coverageMap, $identity, $identityData, $segmentLookups, $defaultProductType);

    $coverageMap[$identity]["counts"]["total_codes"] += $count;

    if ($isConfiguratorValid) {
        $coverageMap[$identity]["counts"]["configurator_valid"] += $count;

        if ($datasheetReady) {
            $coverageMap[$identity]["counts"]["datasheet_ready"] += $count;
            return;
        }

        $coverageMap[$identity]["counts"]["datasheet_blocked"] += $count;
    } else {
        $coverageMap[$identity]["counts"]["configurator_invalid"] += $count;
    }

    if ($failureReason !== "") {
        $coverageMap[$identity]["blocked_reasons"][$failureReason] = ($coverageMap[$identity]["blocked_reasons"][$failureReason] ?? 0) + $count;
    }
}

function getCodeExplorerCoverageStatus(array $counts): string {
    if (($counts["configurator_valid"] ?? 0) === 0) {
        return "invalid";
    }

    if (($counts["datasheet_blocked"] ?? 0) === 0) {
        return "fully_ready";
    }

    if (($counts["datasheet_ready"] ?? 0) > 0) {
        return "partially_ready";
    }

    return "blocked";
}

function finalizeCodeExplorerCoverageEntry(array $entry): array {
    arsort($entry["blocked_reasons"]);
    $entry["top_failure_reason"] = array_key_first($entry["blocked_reasons"]) ?? "";
    $entry["status"] = getCodeExplorerCoverageStatus($entry["counts"]);
    $entry["is_fully_ready"] = $entry["status"] === "fully_ready";
    $entry["ready_ratio"] = ($entry["counts"]["configurator_valid"] ?? 0) > 0
        ? round(($entry["counts"]["datasheet_ready"] / $entry["counts"]["configurator_valid"]) * 100, 1)
        : 0;
    $entry["configurator_valid"] = ($entry["counts"]["configurator_valid"] ?? 0) > 0;

    return $entry;
}

function finalizeCodeExplorerCoverage(array $coverageMap, array $summary): array {
    $entries = array_values($coverageMap);
    $coverageSummary = [
        "total_identities" => count($entries),
        "valid_identities" => 0,
        "fully_ready_identities" => 0,
        "partially_ready_identities" => 0,
        "blocked_identities" => 0,
        "invalid_identities" => 0,
        "datasheet_ready_ratio" => ($summary["configurator_valid"] ?? 0) > 0
            ? round((($summary["datasheet_ready"] ?? 0) / $summary["configurator_valid"]) * 100, 1)
            : 0,
    ];
    $statusRank = [
        "fully_ready" => 0,
        "partially_ready" => 1,
        "blocked" => 2,
        "invalid" => 3,
    ];

    foreach ($entries as &$entry) {
        $entry = finalizeCodeExplorerCoverageEntry($entry);

        if (($entry["counts"]["configurator_valid"] ?? 0) > 0) {
            $coverageSummary["valid_identities"]++;
        }

        if ($entry["status"] === "fully_ready") {
            $coverageSummary["fully_ready_identities"]++;
        } elseif ($entry["status"] === "partially_ready") {
            $coverageSummary["partially_ready_identities"]++;
        } elseif ($entry["status"] === "blocked") {
            $coverageSummary["blocked_identities"]++;
        } else {
            $coverageSummary["invalid_identities"]++;
        }
    }
    unset($entry);

    usort($entries, static function (array $left, array $right) use ($statusRank): int {
        $leftRank = $statusRank[$left["status"] ?? "invalid"] ?? 99;
        $rightRank = $statusRank[$right["status"] ?? "invalid"] ?? 99;

        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }

        $leftReady = (int) ($left["counts"]["datasheet_ready"] ?? 0);
        $rightReady = (int) ($right["counts"]["datasheet_ready"] ?? 0);

        if ($leftReady !== $rightReady) {
            return $rightReady <=> $leftReady;
        }

        $leftTotal = (int) ($left["counts"]["total_codes"] ?? 0);
        $rightTotal = (int) ($right["counts"]["total_codes"] ?? 0);

        if ($leftTotal !== $rightTotal) {
            return $rightTotal <=> $leftTotal;
        }

        return strcmp((string) ($left["identity"] ?? ""), (string) ($right["identity"] ?? ""));
    });

    return [
        "summary" => $coverageSummary,
        "identities" => $entries,
    ];
}

function matchesCodeExplorerIdentitySearch(array $identityEntry, string $search): bool {
    if ($search === "") {
        return true;
    }

    $needle = mb_strtolower($search, "UTF-8");
    $haystacks = [
        $identityEntry["identity"] ?? "",
        $identityEntry["description"] ?? "",
        $identityEntry["product_type"] ?? "",
        $identityEntry["product_id"] ?? "",
    ];

    foreach (["size", "color", "cri", "series"] as $segment) {
        $haystacks[] = $identityEntry["segments"][$segment] ?? "";
        $haystacks[] = $identityEntry["segment_labels"][$segment] ?? "";
    }

    foreach ($haystacks as $value) {
        if (mb_stripos((string) $value, $needle, 0, "UTF-8") !== false) {
            return true;
        }
    }

    return false;
}

function buildCodeExplorerIdentityChunkSourceEntries(string $familyCode, array $options, array $identities, string $search, bool $includeInvalid, array $segmentLookups, ?string $defaultProductType): array {
    $identityMap = [];

    foreach ($identities as $identityData) {
        $identityMap[$identityData["identity"]] = $identityData;
    }

    if (!$includeInvalid) {
        $entries = [];

        foreach ($identities as $identityData) {
            $identity = (string) ($identityData["identity"] ?? "");

            if (strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
                continue;
            }

            $entry = createCodeExplorerCoverageEntry($identity, $identityData, $segmentLookups, $defaultProductType);

            if (!matchesCodeExplorerIdentitySearch($entry, $search)) {
                continue;
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    $entries = [];

    foreach ($options["size"] as $size) {
        foreach ($options["color"] as $color) {
            foreach ($options["cri"] as $cri) {
                foreach ($options["series"] as $series) {
                    $identity = $familyCode . $size["code"] . $color["code"] . $cri["code"] . $series["code"];
                    $identityData = $identityMap[$identity] ?? null;
                    $entry = createCodeExplorerCoverageEntry($identity, $identityData, $segmentLookups, $defaultProductType);

                    if (!matchesCodeExplorerIdentitySearch($entry, $search)) {
                        continue;
                    }

                    $entries[] = $entry;
                }
            }
        }
    }

    return $entries;
}

function buildCodeExplorerIdentityChunkItem(string $familyCode, array $identityEntry, array $identityDataMap, array $options, ?string $defaultProductType, array &$validatorCache): array {
    $identity = (string) ($identityEntry["identity"] ?? "");
    $identityData = $identityDataMap[$identity] ?? null;
    $defaultOptionCode = $options["option"][0]["code"] ?? str_repeat("0", REFERENCE_LENGTH_OPTION);
    $optionCount = max(1, count($options["option"]));
    $item = $identityEntry;
    $item["counts"] = [
        "total_codes" => 0,
        "configurator_valid" => 0,
        "configurator_invalid" => 0,
        "datasheet_ready" => 0,
        "datasheet_blocked" => 0,
    ];
    $item["blocked_reasons"] = [];
    $item["top_failure_reason"] = "";
    $item["status"] = "invalid";
    $item["is_fully_ready"] = false;
    $item["ready_ratio"] = 0;

    foreach ($options["lens"] as $lens) {
        foreach ($options["finish"] as $finish) {
            foreach ($options["cap"] as $cap) {
                $productId = $identityData !== null
                    ? resolveCodeExplorerProductId($familyCode, $identityData, $cap["code"])
                    : null;
                $isConfiguratorValid = $identityData !== null && $productId !== null && $productId !== "";
                $productType = $identityData !== null
                    ? ($identityData["product_type"] ?? $defaultProductType)
                    : $defaultProductType;
                $readiness = [
                    "datasheet_ready" => false,
                    "failure_reason" => "invalid_luminos_combination",
                ];

                if ($isConfiguratorValid) {
                    $validationReference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $defaultOptionCode;
                    $readiness = getCodeExplorerDatasheetReadiness(
                        $validationReference,
                        $productId,
                        $productType,
                        $identityData["led_id"] ?? "",
                        $options,
                        $validatorCache
                    );
                }

                $item["counts"]["total_codes"] += $optionCount;

                if ($isConfiguratorValid) {
                    $item["counts"]["configurator_valid"] += $optionCount;

                    if ($readiness["datasheet_ready"]) {
                        $item["counts"]["datasheet_ready"] += $optionCount;
                    } else {
                        $item["counts"]["datasheet_blocked"] += $optionCount;
                    }
                } else {
                    $item["counts"]["configurator_invalid"] += $optionCount;
                }

                $failureReason = $isConfiguratorValid
                    ? (string) ($readiness["failure_reason"] ?? "")
                    : "invalid_luminos_combination";

                if ($failureReason !== "") {
                    $item["blocked_reasons"][$failureReason] = ($item["blocked_reasons"][$failureReason] ?? 0) + $optionCount;
                }
            }
        }
    }

    return finalizeCodeExplorerCoverageEntry($item);
}

function buildCodeExplorerIdentityChunkResponse(string $familyCode, string $familyName, array $options, array $identities, string $search, string $statusFilter, int $page, int $pageSize, bool $includeInvalid, array $segmentFilters): array {
    $requestedPage = max($page, 1);
    $defaultProductType = getProductType($familyCode . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_FAMILY));
    $segmentLookups = getCodeExplorerSegmentLabelLookups($options);
    $validatorCache = [];
    $identityDataMap = [];
    $sourceEntries = buildCodeExplorerIdentityChunkSourceEntries(
        $familyCode,
        $options,
        $identities,
        $search,
        $includeInvalid,
        $segmentLookups,
        $defaultProductType
    );

    foreach ($identities as $identityData) {
        $identityDataMap[$identityData["identity"]] = $identityData;
    }

    $totalItems = count($sourceEntries);
    $totalPages = max(1, (int) ceil($totalItems / $pageSize));
    $safePage = min($requestedPage, $totalPages);
    $offset = ($safePage - 1) * $pageSize;
    $pageEntries = array_slice($sourceEntries, $offset, $pageSize);
    $chunkItems = [];
    $summary = [
        "total_codes" => 0,
        "configurator_valid" => 0,
        "configurator_invalid" => 0,
        "datasheet_ready" => 0,
        "datasheet_blocked" => 0,
    ];
    $chunkStatusSummary = [
        "total_identities" => $totalItems,
        "valid_identities" => 0,
        "fully_ready_identities" => 0,
        "partially_ready_identities" => 0,
        "blocked_identities" => 0,
        "invalid_identities" => 0,
        "datasheet_ready_ratio" => 0,
        "current_chunk_identities" => count($pageEntries),
    ];

    foreach ($pageEntries as $identityEntry) {
        $item = buildCodeExplorerIdentityChunkItem(
            $familyCode,
            $identityEntry,
            $identityDataMap,
            $options,
            $defaultProductType,
            $validatorCache
        );

        $summary["total_codes"] += $item["counts"]["total_codes"] ?? 0;
        $summary["configurator_valid"] += $item["counts"]["configurator_valid"] ?? 0;
        $summary["configurator_invalid"] += $item["counts"]["configurator_invalid"] ?? 0;
        $summary["datasheet_ready"] += $item["counts"]["datasheet_ready"] ?? 0;
        $summary["datasheet_blocked"] += $item["counts"]["datasheet_blocked"] ?? 0;

        if (($item["counts"]["configurator_valid"] ?? 0) > 0) {
            $chunkStatusSummary["valid_identities"]++;
        }

        if ($item["status"] === "fully_ready") {
            $chunkStatusSummary["fully_ready_identities"]++;
        } elseif ($item["status"] === "partially_ready") {
            $chunkStatusSummary["partially_ready_identities"]++;
        } elseif ($item["status"] === "blocked") {
            $chunkStatusSummary["blocked_identities"]++;
        } else {
            $chunkStatusSummary["invalid_identities"]++;
        }

        $chunkItems[] = $item;
    }

    $chunkStatusSummary["datasheet_ready_ratio"] = $summary["configurator_valid"] > 0
        ? round(($summary["datasheet_ready"] / $summary["configurator_valid"]) * 100, 1)
        : 0;

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "mode" => "identity_chunk",
        "summary" => $summary,
        "chunk" => [
            "source" => $includeInvalid ? "all_identities" : "valid_identities",
            "page" => $safePage,
            "page_size" => $pageSize,
            "total_items" => $totalItems,
            "total_pages" => $totalPages,
            "items" => $chunkItems,
        ],
        "coverage" => [
            "summary" => $chunkStatusSummary,
            "identities" => $chunkItems,
        ],
        "filters" => [
            "search" => $search,
            "status" => $statusFilter,
            "include_invalid" => $includeInvalid,
            "segment_filters" => $segmentFilters,
        ],
        "pagination" => [
            "page" => $safePage,
            "page_size" => $pageSize,
            "total_pages" => $totalPages,
            "total_rows" => $totalItems,
        ],
        "rows" => [],
    ];
}

function buildCodeExplorerResponse(string $familyCode, string $familyName, array $options, array $identities, string $search, string $searchType, string $statusFilter, int $page, int $pageSize, bool $includeInvalid, array $segmentFilters): array {
    $summary = [
        "total_codes" => 0,
        "configurator_valid" => 0,
        "configurator_invalid" => 0,
        "datasheet_ready" => 0,
        "datasheet_blocked" => 0,
    ];
    $validatorCache = [];
    $optionCount = max(1, count($options["option"]));
    $defaultOptionCode = $options["option"][0]["code"] ?? str_repeat("0", REFERENCE_LENGTH_OPTION);
    $identityMap = [];
    $filteredTotal = 0;
    $pageRows = [];
    $requestedPage = max($page, 1);
    $offset = ($requestedPage - 1) * $pageSize;
    $limit = $offset + $pageSize;
    $defaultProductType = getProductType($familyCode . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_FAMILY));
    $segmentLookups = getCodeExplorerSegmentLabelLookups($options);
    $coverageMap = [];

    foreach ($identities as $identityData) {
        $identityMap[$identityData["identity"]] = $identityData;
    }

    if (!$includeInvalid) {
        foreach ($identities as $identityData) {
            $identity = (string) ($identityData["identity"] ?? "");

            if (strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
                continue;
            }

            $identityParts = decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));
            $description = (string) ($identityData["description"] ?? "");
            $productType = $identityData["product_type"] ?? $defaultProductType;

            foreach ($options["lens"] as $lens) {
                foreach ($options["finish"] as $finish) {
                    foreach ($options["cap"] as $cap) {
                        $productId = resolveCodeExplorerProductId($familyCode, $identityData, $cap["code"]);

                        if ($productId === null || $productId === "") {
                            continue;
                        }

                        $validationReference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $defaultOptionCode;
                        $readiness = getCodeExplorerDatasheetReadiness(
                            $validationReference,
                            $productId,
                            $productType,
                            $identityData["led_id"] ?? "",
                            $options,
                            $validatorCache
                        );

                        $summary["total_codes"] += $optionCount;
                        $summary["configurator_valid"] += $optionCount;

                        if ($readiness["datasheet_ready"]) {
                            $summary["datasheet_ready"] += $optionCount;
                        } else {
                            $summary["datasheet_blocked"] += $optionCount;
                        }

                        updateCodeExplorerCoverageCounts(
                            $coverageMap,
                            $identity,
                            $identityData,
                            $segmentLookups,
                            $defaultProductType,
                            true,
                            $readiness["datasheet_ready"],
                            (string) ($readiness["failure_reason"] ?? ""),
                            $optionCount
                        );

                        foreach ($options["option"] as $option) {
                            $row = [
                                "reference" => $identity . $lens["code"] . $finish["code"] . $cap["code"] . $option["code"],
                                "identity" => $identity,
                                "description" => $description,
                                "product_type" => $productType,
                                "product_id" => $productId,
                                "segments" => [
                                    "family" => $familyCode,
                                    "size" => $identityParts["size"],
                                    "color" => $identityParts["color"],
                                    "cri" => $identityParts["cri"],
                                    "series" => $identityParts["series"],
                                    "lens" => $lens["code"],
                                    "finish" => $finish["code"],
                                    "cap" => $cap["code"],
                                    "option" => $option["code"],
                                ],
                                "segment_labels" => [
                                    "size" => $segmentLookups["size"][$identityParts["size"]] ?? $identityParts["size"],
                                    "color" => $segmentLookups["color"][$identityParts["color"]] ?? $identityParts["color"],
                                    "cri" => $segmentLookups["cri"][$identityParts["cri"]] ?? $identityParts["cri"],
                                    "series" => $segmentLookups["series"][$identityParts["series"]] ?? $identityParts["series"],
                                    "lens" => $lens["label"],
                                    "finish" => $finish["label"],
                                    "cap" => $cap["label"],
                                    "option" => $option["label"],
                                ],
                                "configurator_valid" => true,
                                "datasheet_ready" => $readiness["datasheet_ready"],
                                "failure_reason" => $readiness["failure_reason"],
                            ];
                            $row["legacy_description"] = buildCodeExplorerLegacyDescription($row);

                            if (!matchesCodeExplorerSearch($row, $search, $searchType)) {
                                continue;
                            }

                            if (!matchesCodeExplorerStatusFilter($row, $statusFilter)) {
                                continue;
                            }

                            if ($filteredTotal >= $offset && $filteredTotal < $limit) {
                                $pageRows[] = $row;
                            }

                            $filteredTotal++;
                        }
                    }
                }
            }
        }

        $totalPages = max(1, (int) ceil($filteredTotal / $pageSize));
        $safePage = min($requestedPage, $totalPages);

        return [
            "family" => [
                "code" => $familyCode,
                "name" => $familyName,
            ],
            "summary" => $summary,
            "coverage" => finalizeCodeExplorerCoverage($coverageMap, $summary),
            "filters" => [
                "search" => $search,
                "search_type" => $searchType,
                "status" => $statusFilter,
                "include_invalid" => false,
                "segment_filters" => $segmentFilters,
            ],
            "pagination" => [
                "page" => $safePage,
                "page_size" => $pageSize,
                "total_pages" => $totalPages,
                "total_rows" => $filteredTotal,
            ],
            "rows" => $pageRows,
        ];
    }

    foreach ($options["size"] as $size) {
        foreach ($options["color"] as $color) {
            foreach ($options["cri"] as $cri) {
                foreach ($options["series"] as $series) {
                    $identity = $familyCode . $size["code"] . $color["code"] . $cri["code"] . $series["code"];
                    $identityData = $identityMap[$identity] ?? null;
                    $isConfiguratorValidIdentity = $identityData !== null;

                    foreach ($options["lens"] as $lens) {
                        foreach ($options["finish"] as $finish) {
                            foreach ($options["cap"] as $cap) {
                                $productId = $isConfiguratorValidIdentity
                                    ? resolveCodeExplorerProductId($familyCode, $identityData, $cap["code"])
                                    : null;
                                $isConfiguratorValid = $isConfiguratorValidIdentity && $productId !== null && $productId !== "";
                                $productType = $isConfiguratorValidIdentity
                                    ? ($identityData["product_type"] ?? $defaultProductType)
                                    : $defaultProductType;
                                $description = $identityData["description"] ?? "";
                                $readiness = [
                                    "datasheet_ready" => false,
                                    "failure_reason" => "invalid_luminos_combination",
                                ];

                                if ($isConfiguratorValid) {
                                    $validationReference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $defaultOptionCode;
                                        $readiness = getCodeExplorerDatasheetReadiness(
                                            $validationReference,
                                            $productId,
                                            $productType,
                                            $identityData["led_id"] ?? "",
                                            $options,
                                            $validatorCache
                                        );
                                }

                                $summary["total_codes"] += $optionCount;

                                if ($isConfiguratorValid) {
                                    $summary["configurator_valid"] += $optionCount;

                                    if ($readiness["datasheet_ready"]) {
                                        $summary["datasheet_ready"] += $optionCount;
                                    } else {
                                        $summary["datasheet_blocked"] += $optionCount;
                                    }
                                } else {
                                    $summary["configurator_invalid"] += $optionCount;
                                }

                                updateCodeExplorerCoverageCounts(
                                    $coverageMap,
                                    $identity,
                                    $identityData,
                                    $segmentLookups,
                                    $defaultProductType,
                                    $isConfiguratorValid,
                                    $isConfiguratorValid ? $readiness["datasheet_ready"] : false,
                                    $isConfiguratorValid ? (string) ($readiness["failure_reason"] ?? "") : "invalid_luminos_combination",
                                    $optionCount
                                );

                                foreach ($options["option"] as $option) {
                                    $reference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $option["code"];
                                    $row = [
                                        "reference" => $reference,
                                        "identity" => $identity,
                                        "description" => $description,
                                        "product_type" => $productType,
                                        "product_id" => $productId,
                                        "segments" => [
                                            "family" => $familyCode,
                                            "size" => $size["code"],
                                            "color" => $color["code"],
                                            "cri" => $cri["code"],
                                            "series" => $series["code"],
                                            "lens" => $lens["code"],
                                            "finish" => $finish["code"],
                                            "cap" => $cap["code"],
                                            "option" => $option["code"],
                                        ],
                                        "segment_labels" => [
                                            "size" => $size["label"],
                                            "color" => $color["label"],
                                            "cri" => $cri["label"],
                                            "series" => $series["label"],
                                            "lens" => $lens["label"],
                                            "finish" => $finish["label"],
                                            "cap" => $cap["label"],
                                            "option" => $option["label"],
                                        ],
                                        "configurator_valid" => $isConfiguratorValid,
                                        "datasheet_ready" => $isConfiguratorValid ? $readiness["datasheet_ready"] : false,
                                        "failure_reason" => $isConfiguratorValid ? $readiness["failure_reason"] : "invalid_luminos_combination",
                                    ];
                                    $row["legacy_description"] = buildCodeExplorerLegacyDescription($row);

                                    if (!matchesCodeExplorerSearch($row, $search, $searchType)) {
                                        continue;
                                    }

                                    if (!matchesCodeExplorerStatusFilter($row, $statusFilter)) {
                                        continue;
                                    }

                                    if ($filteredTotal >= $offset && $filteredTotal < $limit) {
                                        $pageRows[] = $row;
                                    }

                                    $filteredTotal++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    $totalPages = max(1, (int) ceil($filteredTotal / $pageSize));
    $safePage = min($requestedPage, $totalPages);

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "summary" => $summary,
        "coverage" => finalizeCodeExplorerCoverage($coverageMap, $summary),
        "filters" => [
            "search" => $search,
            "status" => $statusFilter,
            "include_invalid" => true,
            "segment_filters" => $segmentFilters,
        ],
        "pagination" => [
            "page" => $safePage,
            "page_size" => $pageSize,
            "total_pages" => $totalPages,
            "total_rows" => $filteredTotal,
        ],
        "rows" => $pageRows,
    ];
}

function buildCodeExplorerTargetedSearchResponse(string $familyCode, string $familyName, array $options, array $identities, string $search, string $statusFilter, int $page, int $pageSize, bool $includeInvalid, array $segmentFilters): array {
    $normalizedSearch = normalizeCodeExplorerReferenceSearch($search);
    $summary = [
        "total_codes" => 0,
        "configurator_valid" => 0,
        "configurator_invalid" => 0,
        "datasheet_ready" => 0,
        "datasheet_blocked" => 0,
    ];
    $validatorCache = [];
    $identityMap = [];
    $pageRows = [];
    $filteredTotal = 0;
    $requestedPage = max($page, 1);
    $offset = ($requestedPage - 1) * $pageSize;
    $limit = $offset + $pageSize;
    $defaultProductType = getProductType($familyCode . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_FAMILY));
    $segmentLookups = getCodeExplorerSegmentLabelLookups($options);

    foreach ($identities as $identityData) {
        $identityMap[$identityData["identity"]] = $identityData;
    }

    $identity = substr($normalizedSearch, 0, REFERENCE_LENGTH_IDENTITY);
    $suffixSearch = substr($normalizedSearch, REFERENCE_LENGTH_IDENTITY);

    foreach ($options["lens"] as $lens) {
        foreach ($options["finish"] as $finish) {
            foreach ($options["cap"] as $cap) {
                foreach ($options["option"] as $option) {
                    $reference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $option["code"];

                    if ($suffixSearch !== "" && !str_starts_with(substr($reference, REFERENCE_LENGTH_IDENTITY), $suffixSearch)) {
                        continue;
                    }

                    if (!str_starts_with($reference, $normalizedSearch)) {
                        continue;
                    }

                    $parts = decodeReference($reference);

                    if (!matchesCodeExplorerSegmentFilters($parts, $segmentFilters)) {
                        continue;
                    }

                    $identityData = $identityMap[$identity] ?? null;
                    $isConfiguratorValidIdentity = $identityData !== null;
                    $productId = $isConfiguratorValidIdentity
                        ? resolveCodeExplorerProductId($familyCode, $identityData, $parts["cap"])
                        : null;
                    $isConfiguratorValid = $isConfiguratorValidIdentity && $productId !== null && $productId !== "";

                    if (!$includeInvalid && !$isConfiguratorValid) {
                        continue;
                    }

                    $productType = $isConfiguratorValidIdentity
                        ? ($identityData["product_type"] ?? $defaultProductType)
                        : $defaultProductType;
                    $description = $identityData["description"] ?? "";
                    $readiness = [
                        "datasheet_ready" => false,
                        "failure_reason" => "invalid_luminos_combination",
                    ];

                    if ($isConfiguratorValid) {
                        $readiness = getCodeExplorerDatasheetReadiness(
                            $reference,
                            $productId,
                            $productType,
                            $identityData["led_id"] ?? "",
                            $options,
                            $validatorCache
                        );
                    }

                    $row = [
                        "reference" => $reference,
                        "identity" => $identity,
                        "description" => $description,
                        "product_type" => $productType,
                        "product_id" => $productId,
                        "segments" => [
                            "family" => $familyCode,
                            "size" => $parts["size"],
                            "color" => $parts["color"],
                            "cri" => $parts["cri"],
                            "series" => $parts["series"],
                            "lens" => $parts["lens"],
                            "finish" => $parts["finish"],
                            "cap" => $parts["cap"],
                            "option" => $parts["option"],
                        ],
                        "segment_labels" => [
                            "size" => $segmentLookups["size"][$parts["size"]] ?? $parts["size"],
                            "color" => $segmentLookups["color"][$parts["color"]] ?? $parts["color"],
                            "cri" => $segmentLookups["cri"][$parts["cri"]] ?? $parts["cri"],
                            "series" => $segmentLookups["series"][$parts["series"]] ?? $parts["series"],
                            "lens" => resolveCodeExplorerOptionLabel($options["lens"], $parts["lens"]),
                            "finish" => resolveCodeExplorerOptionLabel($options["finish"], $parts["finish"]),
                            "cap" => resolveCodeExplorerOptionLabel($options["cap"], $parts["cap"]),
                            "option" => resolveCodeExplorerOptionLabel($options["option"], $parts["option"]),
                        ],
                        "configurator_valid" => $isConfiguratorValid,
                        "datasheet_ready" => $isConfiguratorValid ? $readiness["datasheet_ready"] : false,
                        "failure_reason" => $isConfiguratorValid ? $readiness["failure_reason"] : "invalid_luminos_combination",
                    ];
                    $row["legacy_description"] = buildCodeExplorerLegacyDescription($row);

                    if (!matchesCodeExplorerStatusFilter($row, $statusFilter)) {
                        continue;
                    }

                    $summary["total_codes"]++;

                    if ($isConfiguratorValid) {
                        $summary["configurator_valid"]++;

                        if ($row["datasheet_ready"]) {
                            $summary["datasheet_ready"]++;
                        } else {
                            $summary["datasheet_blocked"]++;
                        }
                    } else {
                        $summary["configurator_invalid"]++;
                    }

                    if ($filteredTotal >= $offset && $filteredTotal < $limit) {
                        $pageRows[] = $row;
                    }

                    $filteredTotal++;
                }
            }
        }
    }

    $totalPages = max(1, (int) ceil($filteredTotal / $pageSize));
    $safePage = min($requestedPage, $totalPages);

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "summary" => $summary,
        "filters" => [
            "search" => $search,
            "search_type" => CODE_EXPLORER_SEARCH_TYPE_CODE,
            "status" => $statusFilter,
            "include_invalid" => $includeInvalid,
            "segment_filters" => $segmentFilters,
        ],
        "pagination" => [
            "page" => $safePage,
            "page_size" => $pageSize,
            "total_pages" => $totalPages,
            "total_rows" => $filteredTotal,
        ],
        "rows" => $pageRows,
    ];
}

function getFamilyReadyProductsCachePath(string $familyCode): string {
    return BASE_PATH . "/output/family-ready-products/" . $familyCode . ".json";
}

function loadFamilyReadyProductsBaseRows(string $familyCode): ?array {
    $path = getFamilyReadyProductsCachePath($familyCode);

    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $payload = json_decode((string) @file_get_contents($path), true);

    $version = intval($payload["version"] ?? 0);

    if (
        !is_array($payload) ||
        !in_array($version, [3, FAMILY_READY_PRODUCTS_CACHE_VERSION], true) ||
        !isset($payload["rows"]) ||
        !is_array($payload["rows"])
    ) {
        return null;
    }

    return $payload["rows"];
}

function storeFamilyReadyProductsBaseRows(string $familyCode, string $familyName, array $rows): void {
    $path = getFamilyReadyProductsCachePath($familyCode);
    $dir = dirname($path);

    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $payload = [
        "version" => FAMILY_READY_PRODUCTS_CACHE_VERSION,
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "generated_at" => date(DATE_ATOM),
        "rows" => array_values($rows),
    ];

    @file_put_contents($path, json_encode($payload));
}

function normalizeCodeExplorerFloatValue(mixed $value): ?float {
    if ($value === null) {
        return null;
    }

    $normalized = trim(str_replace(",", ".", (string) $value));

    if ($normalized === "" || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function normalizeCodeExplorerIntValue(mixed $value): ?int {
    $floatValue = normalizeCodeExplorerFloatValue($value);

    if ($floatValue === null) {
        return null;
    }

    return (int) round($floatValue);
}

function getCodeExplorerLedMachineData(string $ledId): ?array {
    static $cache = [];
    $cacheKey = trim($ledId);

    if ($cacheKey === "") {
        return null;
    }

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $con = connectDBLampadas();
    $stmt = mysqli_prepare($con, "SELECT CIEx, CIEy, criR9, crimin, crimax FROM Led WHERE ID_led = ? LIMIT 1");

    if ($stmt === false) {
        closeDB($con);
        return $cache[$cacheKey] = null;
    }

    mysqli_stmt_bind_param($stmt, "s", $cacheKey);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = ($result && mysqli_num_rows($result) > 0) ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    closeDB($con);

    if (!is_array($row)) {
        return $cache[$cacheKey] = null;
    }

    return $cache[$cacheKey] = [
        "chrom_x" => normalizeCodeExplorerFloatValue($row["CIEx"] ?? null),
        "chrom_y" => normalizeCodeExplorerFloatValue($row["CIEy"] ?? null),
        "r9" => normalizeCodeExplorerIntValue($row["criR9"] ?? null),
        "cri_min" => normalizeCodeExplorerIntValue($row["crimin"] ?? null),
        "cri_max" => normalizeCodeExplorerIntValue($row["crimax"] ?? null),
    ];
}

function buildCodeExplorerEprelFields(string $productId, string $reference, string $ledId, string $lang = CODE_EXPLORER_DEFAULT_LANG): array {
    static $cache = [];
    $cacheKey = implode("|", [$productId, $reference, $ledId, $lang]);

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $luminotechnical = null;

    if ($productId !== "" && strlen($reference) === REFERENCE_LENGTH_FULL) {
        $luminotechnical = getLuminotechnicalData($productId, $reference, $lang);
    }

    $ledData = getCodeExplorerLedMachineData($ledId) ?? [];
    $energyClass = trim((string) ($luminotechnical["energy_class"] ?? ""));

    return $cache[$cacheKey] = [
        "energy_class" => $energyClass !== "" ? $energyClass : null,
        "luminous_flux" => isset($luminotechnical["flux"]) ? (int) $luminotechnical["flux"] : null,
        "chrom_x" => $ledData["chrom_x"] ?? null,
        "chrom_y" => $ledData["chrom_y"] ?? null,
        "r9" => $ledData["r9"] ?? null,
        "cri_min" => $ledData["cri_min"] ?? null,
        "cri_max" => $ledData["cri_max"] ?? null,
    ];
}

function collectFamilyReadyProductBaseRows(string $familyCode, array $options, array $identities): array {
    $defaultProductType = getProductType($familyCode . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_FAMILY));
    $defaultOptionCode = $options["option"][0]["code"] ?? str_repeat("0", REFERENCE_LENGTH_OPTION);
    $baseRows = [];
    $seenBases = [];

    foreach ($identities as $identityData) {
        $identity = (string) ($identityData["identity"] ?? "");

        if (strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
            continue;
        }

        $description = (string) ($identityData["description"] ?? "");
        $productType = $identityData["product_type"] ?? $defaultProductType;
        $ledId = (string) ($identityData["led_id"] ?? "");
        $identityParts = decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));
        $validatorCache = [];

        foreach ($options["lens"] as $lens) {
            foreach ($options["finish"] as $finish) {
                foreach ($options["cap"] as $cap) {
                    $productId = resolveCodeExplorerProductId($familyCode, $identityData, $cap["code"]);

                    if ($productId === null || $productId === "") {
                        continue;
                    }

                    $validationReference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $defaultOptionCode;
                    $readiness = getCodeExplorerDatasheetReadiness(
                        $validationReference,
                        $productId,
                        $productType,
                        $ledId,
                        $options,
                        $validatorCache
                    );

                    if (($readiness["datasheet_ready"] ?? false) !== true) {
                        continue;
                    }

                    $baseKey = implode("|", [
                        $identity,
                        $lens["code"],
                        $finish["code"],
                        $cap["code"],
                    ]);

                    if (isset($seenBases[$baseKey])) {
                        continue;
                    }

                    $seenBases[$baseKey] = true;

                    $baseRows[] = [
                        "identity" => $identity,
                        "description" => $description,
                        "product_type" => $productType,
                        "product_id" => $productId,
                        "led_id" => $ledId,
                        "eprel_fields" => buildCodeExplorerEprelFields($productId, $validationReference, $ledId),
                        "size" => $identityParts["size"] ?? "",
                        "color" => $identityParts["color"] ?? "",
                        "cri" => $identityParts["cri"] ?? "",
                        "series" => $identityParts["series"] ?? "",
                        "lens" => $lens["code"],
                        "finish" => $finish["code"],
                        "cap" => $cap["code"],
                    ];
                }
            }
        }
    }

    return $baseRows;
}

function getFamilyReadyProductsBaseRows(string $familyCode, string $familyName, array $options, array $identities): array {
    $baseRows = loadFamilyReadyProductsBaseRows($familyCode);

    if ($baseRows === null) {
        $baseRows = collectFamilyReadyProductBaseRows($familyCode, $options, $identities);
        storeFamilyReadyProductsBaseRows($familyCode, $familyName, $baseRows);
    }

    return $baseRows;
}

function getFamilyReadyOptionCodes(array $options): array {
    $optionCodes = array_values(array_filter(array_unique(array_map(
        static fn($option) => (string) ($option["code"] ?? ""),
        $options["option"] ?? []
    ))));

    if (count($optionCodes) === 0) {
        $optionCodes = [str_repeat("0", REFERENCE_LENGTH_OPTION)];
    }

    return $optionCodes;
}

function buildFamilyReadyProductsResponse(
    string $familyCode,
    string $familyName,
    array $options,
    array $identities,
    int $page,
    int $pageSize,
    array $filters = []
): array {
    $requestedPage = max($page, 1);
    $baseRows = getFamilyReadyProductsBaseRows($familyCode, $familyName, $options, $identities);
    $filteredBaseRows = filterFamilyReadyBaseRows($baseRows, $filters);
    $optionCodes = getFamilyReadyOptionCodes($options);
    $optionCount = count($optionCodes);
    $readyTotal = count($filteredBaseRows) * $optionCount;
    $totalPages = max(1, (int) ceil($readyTotal / $pageSize));
    $safePage = min($requestedPage, $totalPages);
    $offset = ($safePage - 1) * $pageSize;
    $pageRows = [];

    if ($readyTotal > 0) {
        $baseIndex = intdiv($offset, $optionCount);
        $optionIndex = $offset % $optionCount;

        while ($baseIndex < count($filteredBaseRows) && count($pageRows) < $pageSize) {
            $baseRow = $filteredBaseRows[$baseIndex];

            while ($optionIndex < $optionCount && count($pageRows) < $pageSize) {
                $optionCode = $optionCodes[$optionIndex];
                $reference = $baseRow["identity"] . $baseRow["lens"] . $baseRow["finish"] . $baseRow["cap"] . $optionCode;
                $pageRows[] = [
                    "reference" => $reference,
                    "identity" => $baseRow["identity"],
                    "description" => $baseRow["description"],
                    "product_type" => $baseRow["product_type"],
                    "product_id" => $baseRow["product_id"],
                    "led_id" => $baseRow["led_id"],
                    "configurator_valid" => true,
                    "datasheet_ready" => true,
                    "eprel_fields" => $baseRow["eprel_fields"] ?? buildCodeExplorerEprelFields(
                        (string) ($baseRow["product_id"] ?? ""),
                        $reference,
                        (string) ($baseRow["led_id"] ?? "")
                    ),
                    "pdf_file_name" => buildFamilyReadyPdfFileName($reference),
                    "pdf_url" => buildFamilyReadyPdfUrl($reference),
                    "spectral_file_name" => buildFamilyReadySpectralFileName($reference),
                    "spectral_url" => buildFamilyReadySpectralUrl($reference),
                ];
                $optionIndex++;
            }

            $baseIndex++;
            $optionIndex = 0;
        }
    }

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "applied_filters" => getFamilyReadyAppliedFilters($filters),
        "summary" => [
            "total_ready_products" => $readyTotal,
        ],
        "pagination" => [
            "page" => $safePage,
            "page_size" => $pageSize,
            "total_pages" => $totalPages,
            "total_rows" => $readyTotal,
        ],
        "rows" => $pageRows,
    ];
}

function buildFamilyReadyFilterChoices(
    string $key,
    array $baseRows,
    array $otherFilters,
    array $options,
    int $optionCount
): array {
    $candidateRows = filterFamilyReadyBaseRows($baseRows, $otherFilters);
    $counts = [];

    foreach ($candidateRows as $row) {
        $value = normalizeFamilyReadyFilterValue($key, $row[$key] ?? "");

        if ($value === "") {
            continue;
        }

        $counts[$value] = ($counts[$value] ?? 0) + $optionCount;
    }

    if (count($counts) === 0) {
        return [];
    }

    $choices = [];

    if ($key === "product_type") {
        ksort($counts);

        foreach ($counts as $value => $count) {
            $choices[] = [
                "value" => $value,
                "label" => $value,
                "count" => $count,
            ];
        }

        return $choices;
    }

    foreach ($options[$key] ?? [] as $option) {
        $value = (string) ($option["code"] ?? "");

        if (!isset($counts[$value])) {
            continue;
        }

        $choices[] = [
            "value" => $value,
            "label" => (string) ($option["label"] ?? $value),
            "count" => $counts[$value],
        ];
    }

    return $choices;
}

function buildFamilyReadyFiltersResponse(
    string $familyCode,
    string $familyName,
    array $options,
    array $identities,
    array $filters = []
): array {
    $baseRows = getFamilyReadyProductsBaseRows($familyCode, $familyName, $options, $identities);
    $optionCodes = getFamilyReadyOptionCodes($options);
    $optionCount = count($optionCodes);
    $filteredBaseRows = filterFamilyReadyBaseRows($baseRows, $filters);
    $availableFilters = [];

    foreach (FAMILY_READY_FILTER_KEYS as $key) {
        $otherFilters = $filters;
        $otherFilters[$key] = [];
        $availableFilters[$key] = buildFamilyReadyFilterChoices($key, $baseRows, $otherFilters, $options, $optionCount);
    }

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "applied_filters" => getFamilyReadyAppliedFilters($filters),
        "summary" => [
            "total_ready_products" => count($filteredBaseRows) * $optionCount,
        ],
        "available_filters" => $availableFilters,
    ];
}

function getCodeExplorerApiBaseUrl(): string {
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $host = trim((string) ($_SERVER["HTTP_X_FORWARDED_HOST"] ?? $_SERVER["HTTP_HOST"] ?? ""));
    $forwardedProto = trim((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""));
    $scheme = $forwardedProto !== ""
        ? trim(explode(",", $forwardedProto)[0])
        : ((!empty($_SERVER["HTTPS"]) && strtolower((string) $_SERVER["HTTPS"]) !== "off") ? "https" : "http");
    $scriptName = str_replace("\\", "/", (string) ($_SERVER["SCRIPT_NAME"] ?? "/api/index.php"));
    $basePath = rtrim(dirname($scriptName), "/");

    if ($basePath === "." || $basePath === DIRECTORY_SEPARATOR) {
        $basePath = "";
    }

    if ($host === "") {
        return $cache = ($basePath !== "" ? $basePath : "/api");
    }

    return $cache = $scheme . "://" . $host . $basePath;
}

function buildFamilyReadyPdfFileName(string $reference): string {
    return $reference . ".pdf";
}

function buildFamilyReadyPdfUrl(string $reference): string {
    return rtrim(getCodeExplorerApiBaseUrl(), "/") . "/?endpoint=file-datasheet&reference=" . rawurlencode($reference);
}

function buildFamilyReadySpectralFileName(string $reference): string {
    return $reference . ".png";
}

function buildFamilyReadySpectralUrl(string $reference): string {
    return rtrim(getCodeExplorerApiBaseUrl(), "/") . "/?endpoint=file-spectral&reference=" . rawurlencode($reference);
}

function buildCodeExplorerDefaultDatasheetPayload(array $pdfSpecs): array {
    $description = trim((string) ($pdfSpecs["summary"]["description"] ?? ""));

    if ($description === "") {
        $description = trim((string) ($pdfSpecs["summary"]["legacy_description"] ?? ""));
    }

    return [
        "referencia" => (string) ($pdfSpecs["reference"] ?? ""),
        "descricao" => $description,
        "idioma" => CODE_EXPLORER_DEFAULT_LANG,
        "empresa" => "0",
        "lente" => (string) ($pdfSpecs["segment_labels"]["lens"] ?? ""),
        "acabamento" => (string) ($pdfSpecs["segment_labels"]["finish"] ?? ""),
        "opcao" => (string) ($pdfSpecs["segments"]["option"] ?? "0"),
        "conectorcabo" => "0",
        "tipocabo" => "branco",
        "tampa" => "0",
        "vedante" => 5,
        "acrescimo" => 0,
        "ip" => "0",
        "fixacao" => "0",
        "fonte" => "0",
        "caboligacao" => "0",
        "conectorligacao" => "0",
        "tamanhocaboligacao" => 0,
        "finalidade" => "0",
    ];
}

function buildCodeExplorerTargetedPreviewResponse(string $familyCode, string $familyName, array $options, array $identities, string $search, string $statusFilter, int $pageSize, bool $includeInvalid, array $segmentFilters): array {
    $normalizedSearch = normalizeCodeExplorerReferenceSearch($search);
    $identity = substr($normalizedSearch, 0, REFERENCE_LENGTH_IDENTITY);
    $suffixSearch = substr($normalizedSearch, REFERENCE_LENGTH_IDENTITY);
    $previewLimit = max(1, min($pageSize, 100));
    $validatorCache = [];
    $identityMap = [];
    $pageRows = [];
    $defaultProductType = getProductType($familyCode . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_FAMILY));
    $segmentLookups = getCodeExplorerSegmentLabelLookups($options);

    foreach ($identities as $identityData) {
        $identityMap[$identityData["identity"]] = $identityData;
    }

    foreach ($options["lens"] as $lens) {
        foreach ($options["finish"] as $finish) {
            foreach ($options["cap"] as $cap) {
                foreach ($options["option"] as $option) {
                    $reference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $option["code"];

                    if ($suffixSearch !== "" && !str_starts_with(substr($reference, REFERENCE_LENGTH_IDENTITY), $suffixSearch)) {
                        continue;
                    }

                    if (!str_starts_with($reference, $normalizedSearch)) {
                        continue;
                    }

                    $parts = decodeReference($reference);

                    if (!matchesCodeExplorerSegmentFilters($parts, $segmentFilters)) {
                        continue;
                    }

                    $identityData = $identityMap[$identity] ?? null;
                    $isConfiguratorValidIdentity = $identityData !== null;
                    $productId = $isConfiguratorValidIdentity
                        ? resolveCodeExplorerProductId($familyCode, $identityData, $parts["cap"])
                        : null;
                    $isConfiguratorValid = $isConfiguratorValidIdentity && $productId !== null && $productId !== "";

                    if (!$includeInvalid && !$isConfiguratorValid) {
                        continue;
                    }

                    $productType = $isConfiguratorValidIdentity
                        ? ($identityData["product_type"] ?? $defaultProductType)
                        : $defaultProductType;
                    $description = $identityData["description"] ?? "";
                    $readiness = [
                        "datasheet_ready" => false,
                        "failure_reason" => "invalid_luminos_combination",
                    ];

                    if ($isConfiguratorValid) {
                        $readiness = getCodeExplorerDatasheetReadiness(
                            $reference,
                            $productId,
                            $productType,
                            $identityData["led_id"] ?? "",
                            $options,
                            $validatorCache
                        );
                    }

                    $row = [
                        "reference" => $reference,
                        "identity" => $identity,
                        "description" => $description,
                        "product_type" => $productType,
                        "product_id" => $productId,
                        "segments" => [
                            "family" => $familyCode,
                            "size" => $parts["size"],
                            "color" => $parts["color"],
                            "cri" => $parts["cri"],
                            "series" => $parts["series"],
                            "lens" => $parts["lens"],
                            "finish" => $parts["finish"],
                            "cap" => $parts["cap"],
                            "option" => $parts["option"],
                        ],
                        "segment_labels" => [
                            "size" => $segmentLookups["size"][$parts["size"]] ?? $parts["size"],
                            "color" => $segmentLookups["color"][$parts["color"]] ?? $parts["color"],
                            "cri" => $segmentLookups["cri"][$parts["cri"]] ?? $parts["cri"],
                            "series" => $segmentLookups["series"][$parts["series"]] ?? $parts["series"],
                            "lens" => resolveCodeExplorerOptionLabel($options["lens"], $parts["lens"]),
                            "finish" => resolveCodeExplorerOptionLabel($options["finish"], $parts["finish"]),
                            "cap" => resolveCodeExplorerOptionLabel($options["cap"], $parts["cap"]),
                            "option" => resolveCodeExplorerOptionLabel($options["option"], $parts["option"]),
                        ],
                        "configurator_valid" => $isConfiguratorValid,
                        "datasheet_ready" => $isConfiguratorValid ? $readiness["datasheet_ready"] : false,
                        "failure_reason" => $isConfiguratorValid ? $readiness["failure_reason"] : "invalid_luminos_combination",
                    ];
                    $row["legacy_description"] = buildCodeExplorerLegacyDescription($row);

                    if (!matchesCodeExplorerStatusFilter($row, $statusFilter)) {
                        continue;
                    }

                    $pageRows[] = $row;

                    if (count($pageRows) >= $previewLimit) {
                        return [
                            "family" => [
                                "code" => $familyCode,
                                "name" => $familyName,
                            ],
                            "preview" => true,
                            "filters" => [
                                "search" => $search,
                                "search_type" => CODE_EXPLORER_SEARCH_TYPE_CODE,
                                "status" => $statusFilter,
                                "include_invalid" => $includeInvalid,
                                "segment_filters" => $segmentFilters,
                            ],
                            "pagination" => [
                                "page" => 1,
                                "page_size" => $previewLimit,
                                "total_pages" => 1,
                                "total_rows" => count($pageRows),
                            ],
                            "rows" => $pageRows,
                        ];
                    }
                }
            }
        }
    }

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "preview" => true,
        "filters" => [
            "search" => $search,
            "search_type" => CODE_EXPLORER_SEARCH_TYPE_CODE,
            "status" => $statusFilter,
            "include_invalid" => $includeInvalid,
            "segment_filters" => $segmentFilters,
        ],
        "pagination" => [
            "page" => 1,
            "page_size" => $previewLimit,
            "total_pages" => 1,
            "total_rows" => count($pageRows),
        ],
        "rows" => $pageRows,
    ];
}

function getCodeExplorerSegmentLabelLookups(array $options): array {
    $lookups = [];

    foreach (["size", "color", "cri", "series"] as $segment) {
        $lookups[$segment] = [];

        foreach ($options[$segment] ?? [] as $option) {
            $lookups[$segment][$option["code"]] = $option["label"];
        }
    }

    return $lookups;
}

function buildCodeExplorerLegacyDescription(array $row): string {
    $parts = [];
    $baseDescription = trim((string) ($row["description"] ?? ""));

    if ($baseDescription !== "") {
        $parts[] = $baseDescription;
    }

    foreach (["lens", "finish", "cap", "option"] as $segment) {
        $label = trim((string) ($row["segment_labels"][$segment] ?? ""));
        $code = trim((string) ($row["segments"][$segment] ?? ""));

        if ($label === "" || $label === $code || $label === "0") {
            continue;
        }

        $parts[] = $label;
    }

    return trim(implode(" ", $parts));
}

function resolveCodeExplorerProductId(string $familyCode, array $identityData, string $capCode): ?string {
    if ($familyCode === "48") {
        $dynamicIds = $identityData["dynamic_ids"] ?? [];
        $normalizedCap = ltrim($capCode, "0");
        return $dynamicIds[$normalizedCap === "1" ? "1" : "0"] ?? null;
    }

    return $identityData["product_id"] ?? null;
}

function matchesCodeExplorerSearch(array $row, string $search, string $searchType = CODE_EXPLORER_SEARCH_TYPE_CODE): bool {
    if ($search === "") {
        return true;
    }

    $needle = mb_strtolower($search, "UTF-8");
    $haystacks = match ($searchType) {
        CODE_EXPLORER_SEARCH_TYPE_DESCRIPTION => [
            $row["legacy_description"] ?? buildCodeExplorerLegacyDescription($row),
        ],
        default => [
            $row["reference"] ?? "",
            $row["identity"] ?? "",
        ],
    };

    foreach ($haystacks as $value) {
        if (mb_stripos((string) $value, $needle, 0, "UTF-8") !== false) {
            return true;
        }
    }

    return false;
}

function matchesCodeExplorerStatusFilter(array $row, string $statusFilter): bool {
    return match ($statusFilter) {
        CODE_EXPLORER_STATUS_CONFIGURATOR_VALID => $row["configurator_valid"] === true,
        CODE_EXPLORER_STATUS_CONFIGURATOR_INVALID => $row["configurator_valid"] === false,
        CODE_EXPLORER_STATUS_DATASHEET_READY => $row["datasheet_ready"] === true,
        CODE_EXPLORER_STATUS_DATASHEET_BLOCKED => $row["configurator_valid"] === true && $row["datasheet_ready"] === false,
        default => true,
    };
}

function getCodeExplorerDatasheetReadiness(string $reference, string $productId, ?string $productType, string $ledId, array $options, array &$cache): array {
    $parts = decodeReference($reference);
    $lang = CODE_EXPLORER_DEFAULT_LANG;
    $cacheKey = $reference . "|" . $productId;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    if (!isDatasheetRuntimeSupported($productType, $parts["family"] ?? null)) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "unsupported_datasheet_runtime",
        ];
    }

    $lensLabel = resolveCodeExplorerOptionLabel($options["lens"], $parts["lens"]);
    $finishLabel = resolveCodeExplorerOptionLabel($options["finish"], $parts["finish"]);
    $config = [
        "lens" => $lensLabel,
        "finish" => $finishLabel,
        "connector_cable" => "0",
        "cable_type" => "branco",
        "end_cap" => "0",
        "purpose" => "0",
        "lang" => $lang,
        "extra_length" => 0,
        "option" => $parts["option"],
        "cable_length" => 0,
        "gasket" => 5,
    ];

    if ($ledId === "") {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_header_data",
        ];
    }

    $header = getProductHeader((string) $productType, $productId, $reference, $ledId, $config);

    if (($header["image"] ?? null) === null || trim((string) ($header["description"] ?? "")) === "") {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_header_data",
        ];
    }

    if (getCodeExplorerTechnicalDrawingPath((string) $productType, $reference, $productId, $config) === null) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_technical_drawing",
        ];
    }

    $colorGraph = getColorGraph($ledId, $lang);

    if ($colorGraph === null) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_color_graph",
        ];
    }

    if ($parts["lens"] !== "0") {
        $lensDiagram = getLensDiagram($productId, $reference);

        if ($lensDiagram === null) {
            return $cache[$cacheKey] = [
                "datasheet_ready" => false,
                "failure_reason" => "missing_lens_diagram",
            ];
        }
    }

    $finishImage = getCodeExplorerFinishImagePath((string) $productType, $productId, $reference, $config);

    if ($finishImage === null || str_contains($finishImage, "/img/placeholders/")) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_finish_image",
        ];
    }

    return $cache[$cacheKey] = [
        "datasheet_ready" => true,
        "failure_reason" => null,
    ];
}

function getCodeExplorerSafeLensAngles(string $family, string $lens): array {
    if (!canReadInfoLensAngles()) {
        return ["beam" => null, "field" => null];
    }

    $con = connectDBInf();
    $queryBeam  = mysqli_query($con, "SELECT beam FROM angulos_lente WHERE familia = '$family' AND lente = '$lens'");
    $queryField = mysqli_query($con, "SELECT field FROM angulos_lente WHERE familia = '$family' AND lente = '$lens'");
    closeDB($con);

    $beam = ($queryBeam instanceof mysqli_result && mysqli_num_rows($queryBeam) > 0)
        ? (mysqli_fetch_assoc($queryBeam)["beam"] ?? null)
        : null;
    $field = ($queryField instanceof mysqli_result && mysqli_num_rows($queryField) > 0)
        ? (mysqli_fetch_assoc($queryField)["field"] ?? null)
        : null;

    return [
        "beam" => $beam,
        "field" => $field,
    ];
}

function getCodeExplorerSafeIpRating(string $productId): ?string {
    $con = connectDBLampadas();
    $query = mysqli_query($con,
        "SELECT valor_pt FROM caracteristicas
         WHERE ID = '$productId'
           AND (texto_pt = 'Grau de protecção' OR texto_pt = 'Grau de proteção')"
    );
    closeDB($con);

    if (!($query instanceof mysqli_result) || mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_row($query);
    return str_replace(" ", "", implode("", $row ?: []));
}

function getCodeExplorerSafeCharacteristics(string $productId, string $ipRating, string $family, string $lens, string $lang): ?array {
    $idPrefix = explode("/", $productId)[0];
    $con = connectDBLampadas();
    $query = mysqli_query($con,
        "SELECT texto_pt, valor_pt, texto_$lang, valor_$lang
         FROM caracteristicas
         WHERE ID = '$productId'
           AND texto_pt NOT LIKE 'data'
           AND texto_pt NOT LIKE 'versao'
           AND texto_pt NOT LIKE 'Dimensões%'
           AND texto_pt NOT LIKE '$idPrefix%'
         ORDER BY indice ASC"
    );
    closeDB($con);

    if (!($query instanceof mysqli_result) || mysqli_num_rows($query) === 0) {
        return null;
    }

    $angles = getCodeExplorerSafeLensAngles($family, $lens);
    $ipLabels = ["Grau de protecção", "Grau de proteção"];
    $beamLabel = "Feixe de luz";
    $fieldLabel = "Abertura de luz";
    $result = [];

    while ($row = mysqli_fetch_assoc($query)) {
        $ptLabel = strval($row["texto_pt"] ?? "");
        $translatedLabel = $row["texto_$lang"] ?? null;
        $translatedValue = $row["valor_$lang"] ?? null;
        $label = ($translatedLabel !== null && $translatedLabel !== "")
            ? strval($translatedLabel)
            : $ptLabel;
        $value = ($translatedValue !== null && $translatedValue !== "")
            ? strval($translatedValue)
            : strval($row["valor_pt"] ?? "");

        if (in_array($ptLabel, $ipLabels, true) && $ipRating !== "") {
            $value = $ipRating;
        }

        if ($ptLabel === $beamLabel && $angles["beam"] !== null) {
            $value = strval($angles["beam"]);
        }

        if ($ptLabel === $fieldLabel && $angles["field"] !== null) {
            $value = strval($angles["field"]);
        }

        $result[$label] = $value;
    }

    return $result;
}

function getCodeExplorerSafeStandardDimensions(string $reference, string $productId): array {
    $dims = array_fill_keys(["A", "B", "C", "D", "E", "F", "G", "H", "I", "J"], "0");
    $con = connectDBLampadas();
    $query = mysqli_query($con,
        "SELECT valor_pt FROM caracteristicas
         WHERE ID = '$productId' AND texto_pt LIKE 'Dimensões%'"
    );
    closeDB($con);

    if (!($query instanceof mysqli_result) || mysqli_num_rows($query) === 0) {
        return $dims;
    }

    $row = mysqli_fetch_assoc($query);
    $tokens = explode(" ", (string) ($row["valor_pt"] ?? ""));

    foreach ($tokens as $token) {
        $pair = explode(":", $token);

        if (!empty($pair[0]) && !empty($pair[1]) && array_key_exists($pair[0], $dims)) {
            $dims[$pair[0]] = $pair[1];
        }
    }

    return $dims;
}

function getCodeExplorerSafeDimensions(string $productType, string $reference, string $productId, ?string $sizesFile, array $config): array {
    if ($productType === "barra") {
        return getBarDrawing($reference, $productId, $sizesFile, $config);
    }

    return array_merge(
        ["drawing" => getCodeExplorerTechnicalDrawingPath($productType, $reference, $productId, $config)],
        getCodeExplorerSafeStandardDimensions($reference, $productId)
    );
}

function buildCodeExplorerPdfSpecsResponse(string $familyCode, string $familyName, array $options, array $identities, string $reference, string $lang = CODE_EXPLORER_DEFAULT_LANG): array {
    $identityMap = [];
    $parts = decodeReference($reference);
    $identity = substr($reference, 0, REFERENCE_LENGTH_IDENTITY);
    $defaultProductType = getProductType($familyCode . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_FAMILY));
    $segmentLookups = getCodeExplorerSegmentLabelLookups($options);

    foreach ($identities as $identityData) {
        $identityMap[(string) ($identityData["identity"] ?? "")] = $identityData;
    }

    $identityData = $identityMap[$identity] ?? null;
    $isConfiguratorValidIdentity = $identityData !== null;
    $productId = $isConfiguratorValidIdentity
        ? resolveCodeExplorerProductId($familyCode, $identityData, $parts["cap"] ?? "")
        : null;
    $isConfiguratorValid = $isConfiguratorValidIdentity && $productId !== null && $productId !== "";
    $productType = $isConfiguratorValidIdentity
        ? ($identityData["product_type"] ?? $defaultProductType)
        : $defaultProductType;
    $ledId = (string) ($identityData["led_id"] ?? "");
    $description = (string) ($identityData["description"] ?? "");
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

    $readiness = [
        "datasheet_ready" => false,
        "failure_reason" => "invalid_luminos_combination",
    ];
    $header = null;
    $characteristics = null;
    $technicalDrawing = null;
    $colorGraph = null;
    $lensDiagram = null;
    $finishImage = null;
    $ipRating = null;

    if ($isConfiguratorValid) {
        $validatorCache = [];
        $readiness = getCodeExplorerDatasheetReadiness($reference, $productId, $productType, $ledId, $options, $validatorCache);
        $config = [
            "lens" => $row["segment_labels"]["lens"],
            "finish" => $row["segment_labels"]["finish"],
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
        $header = $ledId !== "" ? getProductHeader((string) $productType, $productId, $reference, $ledId, $config) : null;
        $ipRating = getCodeExplorerSafeIpRating($productId);
        $characteristics = getCodeExplorerSafeCharacteristics($productId, (string) ($ipRating ?? ""), $familyCode, $parts["lens"] ?? "", $lang);
        $technicalDrawing = getCodeExplorerSafeDimensions((string) $productType, $reference, $productId, getBarSizesFile($reference), $config);
        $colorGraph = $ledId !== "" ? getColorGraph($ledId, $lang) : null;
        $lensDiagram = ($parts["lens"] ?? "0") !== "0"
            ? getLensDiagram($productId, $reference)
            : null;
        $finishImage = getCodeExplorerFinishImagePath((string) $productType, $productId, $reference, $config);
    }

    $characteristicRows = [];

    foreach (($characteristics ?? []) as $label => $value) {
        $characteristicRows[] = [
            "label" => (string) $label,
            "value" => (string) $value,
        ];
    }

    $dimensionRows = [];

    foreach (["A", "B", "C", "D", "E", "F", "G", "H", "I", "J"] as $dimensionKey) {
        $value = trim((string) ($technicalDrawing[$dimensionKey] ?? ""));

        if ($value === "" || $value === "0") {
            continue;
        }

        $dimensionRows[] = [
            "label" => $dimensionKey,
            "value" => $value,
        ];
    }

    $headerDescription = trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace(["<br>", "<br/>", "<br />"], "\n", (string) ($header["description"] ?? "")))));
    $eprelFields = $isConfiguratorValid
        ? buildCodeExplorerEprelFields((string) ($productId ?? ""), $reference, $ledId, $lang)
        : buildCodeExplorerEprelFields("", $reference, $ledId, $lang);

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "reference" => $reference,
        "configurator_valid" => $isConfiguratorValid,
        "datasheet_ready" => $isConfiguratorValid ? (bool) ($readiness["datasheet_ready"] ?? false) : false,
        "failure_reason" => $isConfiguratorValid ? ($readiness["failure_reason"] ?? null) : "invalid_luminos_combination",
        "summary" => [
            "product_id" => (string) ($productId ?? ""),
            "product_type" => (string) ($productType ?? ""),
            "led_id" => $ledId,
            "description" => $description,
            "legacy_description" => (string) ($row["legacy_description"] ?? ""),
            "header_description" => $headerDescription,
            "finish_name" => (string) ($row["segment_labels"]["finish"] ?? ""),
            "ip_rating" => (string) ($ipRating ?? ""),
            "color_graph_label" => (string) ($colorGraph["label"] ?? ""),
        ],
        "eprel_fields" => $eprelFields,
        "segments" => $row["segments"],
        "segment_labels" => $row["segment_labels"],
        "characteristics" => $characteristicRows,
        "dimensions" => $dimensionRows,
        "assets" => [
            "header_image" => ($header["image"] ?? null) !== null,
            "technical_drawing" => ($technicalDrawing["drawing"] ?? null) !== null,
            "color_graph" => $colorGraph !== null,
            "lens_diagram" => ($parts["lens"] ?? "0") === "0" ? null : ($lensDiagram !== null),
            "finish_image" => $finishImage !== null && !str_contains((string) $finishImage, "/img/placeholders/"),
        ],
    ];
}

function resolveCodeExplorerOptionLabel(array $options, string $code): string {
    foreach ($options as $option) {
        if (($option["code"] ?? "") === $code) {
            return (string) ($option["label"] ?? $code);
        }
    }

    return $code;
}

function getCodeExplorerTechnicalDrawingPath(string $productType, string $reference, string $productId, array $config): ?string {
    static $cache = [];
    $cacheKey = implode("|", [
        $productType,
        $productId,
        $reference,
        (string) ($config["lens"] ?? ""),
        (string) ($config["finish"] ?? ""),
        (string) ($config["connector_cable"] ?? ""),
        (string) ($config["cable_type"] ?? ""),
        (string) ($config["end_cap"] ?? ""),
        (string) ($config["option"] ?? ""),
    ]);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $parts = decodeReference($reference);
    $family = $parts["family"];
    $size = $parts["size"];

    if ($productType === "barra") {
        $cap = $parts["cap"];
        $connectorCable = $config["connector_cable"] ?? "0";
        $endCap = $config["end_cap"] ?? "0";
        $folder = IMAGES_BASE_PATH . "/img/$family/desenhos/";
        $candidates = [
            "{$cap}_{$connectorCable}_{$endCap}",
            "{$cap}_{$endCap}",
            "{$connectorCable}_{$endCap}",
            $cap,
        ];

        foreach ($candidates as $name) {
            $drawing = findImage($folder . $name);

            if ($drawing !== null) {
                return $cache[$cacheKey] = $drawing;
            }
        }

        return $cache[$cacheKey] = null;
    }

    if ($productType === "dynamic") {
        $subtype = explode("/", $productId)[1] ?? "";
        $drawing = findDamProductAsset($family, $productId, "drawing", [$size]);

        if ($drawing !== null) {
            return $cache[$cacheKey] = $drawing;
        }

        return $cache[$cacheKey] = findImage(IMAGES_BASE_PATH . "/img/$family/$subtype/desenhos/$size");
    }

    $drawing = findDamProductAsset($family, $productId, "drawing", [$size]);

    if ($drawing === null && $family === "01") {
        $drawing = cloudinaryDamExactAssetUrl("nexled/datasheet/drawings", "t8-fixo.svg");
    } elseif ($drawing === null && $family === "05") {
        $drawingAsset = $parts["cap"] === "02" ? "t5_sfio.svg" : "t5.svg";
        $drawing = cloudinaryDamExactAssetUrl("nexled/datasheet/drawings", $drawingAsset);
    }

    if ($drawing !== null) {
        return $cache[$cacheKey] = $drawing;
    }

    return $cache[$cacheKey] = findImage(IMAGES_BASE_PATH . "/img/$family/desenhos/$size");
}

function getCodeExplorerFinishImagePath(string $productType, string $productId, string $reference, array $config): ?string {
    static $cache = [];
    $cacheKey = implode("|", [
        $productType,
        $productId,
        $reference,
        (string) ($config["lens"] ?? ""),
        (string) ($config["finish"] ?? ""),
        (string) ($config["end_cap"] ?? ""),
        (string) ($config["lang"] ?? ""),
    ]);

    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    $parts = decodeReference($reference);
    $family = $parts["family"];
    $size = $parts["size"];
    $series = $parts["series"];
    $cap = $parts["cap"];
    $finishCode = $parts["finish"];
    $lang = (string) ($config["lang"] ?? CODE_EXPLORER_DEFAULT_LANG);
    $lens = strtolower((string) ($config["lens"] ?? ""));
    $finish = strtolower((string) ($config["finish"] ?? ""));
    $endCap = (string) ($config["end_cap"] ?? "0");

    switch ($productType) {
        case "barra":
            $folder = ($lens === "clear")
                ? "/img/$family/acabamentos/$lens/$series/"
                : "/img/$family/acabamentos/$lens/";

            if ($family === "32") {
                $finishToken = ltrim($finishCode, "0");
                if ($finishToken === "") {
                    $finishToken = "0";
                }

                $candidates = [
                    "{$finishToken}_{$endCap}",
                    "{$finishToken}_{$cap}",
                    str_replace("+", "_", "{$finish}_{$endCap}"),
                    str_replace("+", "_", "{$finish}_{$cap}"),
                ];
            } else {
                $candidates = [
                    str_replace("+", "_", "{$finish}_{$cap}"),
                    str_replace("+", "_", "{$finish}_{$endCap}"),
                ];
            }
            break;
        case "dynamic":
            $subtype = explode("/", $productId)[1] ?? "";
            $folder = "/img/$family/$subtype/acabamentos/";
            $cleanFinish = str_replace("+", "", $finish);
            $candidates = ["{$size}_{$cleanFinish}"];
            break;
        case "shelf":
            $folder = "/img/$family/acabamentos/";
            $cleanFinish = str_replace("+", "_", $finish);
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}_{$endCap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$cleanFinish}_{$cap}",
                "{$cleanFinish}_{$endCap}",
                "{$size}_{$lens}",
                "{$size}",
            ];
            break;
        case "tubular":
            $folder = "/img/$family/acabamentos/";
            $cleanFinish = str_replace("+", "_", $finish);
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$size}_{$lens}",
                "{$size}",
            ];
            break;
        default:
            $folder = "/img/$family/acabamentos/";
            $candidates = ["{$size}_{$lens}_{$finish}"];
            break;
    }

    $image = findDamProductAsset($family, $productId, "finish", $candidates);

    if ($image === null && $family === "01") {
        $finishFolder = strtolower(trim((string) $lens)) === "frost"
            ? "nexled/datasheet/finishes/frost"
            : "nexled/datasheet/finishes/clear";
        $image = cloudinaryDamExactAssetUrl($finishFolder, "acabamento-t8-alu.png");
    } elseif ($image === null && $family === "05") {
        $finishFolder = strtolower(trim((string) $lens)) === "frost"
            ? "nexled/datasheet/finishes/frost"
            : "nexled/datasheet/finishes/clear";
        $image = cloudinaryDamExactAssetUrl($finishFolder, "acabamento-t5-alu.png");
    }

    if ($image === null) {
        foreach ($candidates as $name) {
            $image = findImage(IMAGES_BASE_PATH . $folder . $name);

            if ($image !== null) {
                break;
            }
        }
    }

    return $cache[$cacheKey] = ($image !== null ? $image : getFinishPlaceholderImage());
}
