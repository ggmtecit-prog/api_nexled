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

function buildCodeExplorerResponse(string $familyCode, string $familyName, array $options, array $identities, string $search, string $statusFilter, int $page, int $pageSize, bool $includeInvalid, array $segmentFilters): array {
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

                            if (!matchesCodeExplorerSearch($row, $search)) {
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
            "filters" => [
                "search" => $search,
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

                                    if (!matchesCodeExplorerSearch($row, $search)) {
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

function resolveCodeExplorerProductId(string $familyCode, array $identityData, string $capCode): ?string {
    if ($familyCode === "48") {
        $dynamicIds = $identityData["dynamic_ids"] ?? [];
        $normalizedCap = ltrim($capCode, "0");
        return $dynamicIds[$normalizedCap === "1" ? "1" : "0"] ?? null;
    }

    return $identityData["product_id"] ?? null;
}

function matchesCodeExplorerSearch(array $row, string $search): bool {
    if ($search === "") {
        return true;
    }

    $needle = mb_strtolower($search, "UTF-8");
    $haystacks = [
        $row["reference"] ?? "",
        $row["identity"] ?? "",
        $row["description"] ?? "",
        $row["product_id"] ?? "",
        $row["failure_reason"] ?? "",
    ];

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

    if (!isDatasheetRuntimeSupported($productType)) {
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

function resolveCodeExplorerOptionLabel(array $options, string $code): string {
    foreach ($options as $option) {
        if (($option["code"] ?? "") === $code) {
            return (string) ($option["label"] ?? $code);
        }
    }

    return $code;
}

function getCodeExplorerTechnicalDrawingPath(string $productType, string $reference, string $productId, array $config): ?string {
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
                return $drawing;
            }
        }

        return null;
    }

    if ($productType === "dynamic") {
        $subtype = explode("/", $productId)[1] ?? "";
        return findImage(IMAGES_BASE_PATH . "/img/$family/$subtype/desenhos/$size");
    }

    return findImage(IMAGES_BASE_PATH . "/img/$family/desenhos/$size");
}

function getCodeExplorerFinishImagePath(string $productType, string $productId, string $reference, array $config): ?string {
    $parts = decodeReference($reference);
    $family = $parts["family"];
    $size = $parts["size"];
    $series = $parts["series"];
    $cap = $parts["cap"];
    $lens = strtolower((string) ($config["lens"] ?? ""));
    $finish = strtolower((string) ($config["finish"] ?? ""));
    $endCap = (string) ($config["end_cap"] ?? "0");

    switch ($productType) {
        case "barra":
            $folder = ($lens === "clear")
                ? "/img/$family/acabamentos/$lens/$series/"
                : "/img/$family/acabamentos/$lens/";
            $candidates = [
                str_replace("+", "_", "{$finish}_{$cap}"),
                str_replace("+", "_", "{$finish}_{$endCap}"),
            ];
            break;
        case "dynamic":
            $subtype = explode("/", $productId)[1] ?? "";
            $folder = "/img/$family/$subtype/acabamentos/";
            $cleanFinish = str_replace("+", "", $finish);
            $candidates = ["{$size}_{$cleanFinish}"];
            break;
        default:
            $folder = "/img/$family/acabamentos/";
            $candidates = ["{$size}_{$lens}_{$finish}"];
            break;
    }

    foreach ($candidates as $name) {
        $image = findImage(IMAGES_BASE_PATH . $folder . $name);

        if ($image !== null) {
            return $image;
        }
    }

    return getFinishPlaceholderImage();
}
