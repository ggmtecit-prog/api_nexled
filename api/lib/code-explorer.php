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
const CODE_EXPLORER_STATUS_DATASHEET_READY = "datasheet_ready";
const CODE_EXPLORER_STATUS_DATASHEET_BLOCKED = "datasheet_blocked";
const CODE_EXPLORER_DEFAULT_LANG = "pt";

function getCodeExplorerStatusFilter(string $value): string {
    $normalized = trim(strtolower($value));
    $allowed = [
        CODE_EXPLORER_STATUS_ALL,
        CODE_EXPLORER_STATUS_CONFIGURATOR_VALID,
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
        "lens" => [],
        "finish" => [],
        "cap" => [],
        "option" => [],
    ];

    $queries = [
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

            if ($key === "lens") {
                $label = (string) ($row["desc"] ?: $row["acrilico"] ?: $row["codigo"]);
            } elseif ($key === "finish") {
                $label = (string) ($row["desc"] ?: $row["acabamento"] ?: $row["codigo"]);
            } elseif ($key === "cap") {
                $label = (string) ($row["desc"] ?: $row["cap"] ?: $row["codigo"]);
            } else {
                $label = (string) ($row["desc"] ?: $row["opcao"] ?: $row["codigo"]);
            }

            $options[$key][] = [
                "code" => str_pad((string) ($row["codigo"] ?? ""), getCodeExplorerSegmentLength($key), "0", STR_PAD_LEFT),
                "label" => $label,
            ];
        }
    }

    closeDB($con);

    return $options;
}

function getCodeExplorerSegmentLength(string $segment): int {
    return match ($segment) {
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
    $stmt = mysqli_prepare($con, "SELECT ref, ID, `desc` FROM Luminos WHERE ref LIKE ? ORDER BY ref ASC, ID ASC");

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

function buildCodeExplorerResponse(string $familyCode, string $familyName, array $options, array $identities, string $search, string $statusFilter, int $page, int $pageSize): array {
    $summary = [
        "total_codes" => 0,
        "configurator_valid" => 0,
        "datasheet_ready" => 0,
        "datasheet_blocked" => 0,
    ];
    $rows = [];
    $validatorCache = [];

    foreach ($identities as $identityData) {
        $identity = $identityData["identity"];
        $segments = decodeReference($identity . str_repeat("0", REFERENCE_LENGTH_FULL - REFERENCE_LENGTH_IDENTITY));

        foreach ($options["lens"] as $lens) {
            foreach ($options["finish"] as $finish) {
                foreach ($options["cap"] as $cap) {
                    foreach ($options["option"] as $option) {
                        $reference = $identity . $lens["code"] . $finish["code"] . $cap["code"] . $option["code"];
                        $productId = resolveCodeExplorerProductId($familyCode, $identityData, $cap["code"]);

                        if ($productId === null || $productId === "") {
                            continue;
                        }

                        $summary["total_codes"]++;
                        $summary["configurator_valid"]++;

                        $readiness = getCodeExplorerDatasheetReadiness(
                            $reference,
                            $productId,
                            $identityData["product_type"],
                            $identityData["description"],
                            $options,
                            $validatorCache
                        );

                        if ($readiness["datasheet_ready"]) {
                            $summary["datasheet_ready"]++;
                        } else {
                            $summary["datasheet_blocked"]++;
                        }

                        $row = [
                            "reference" => $reference,
                            "identity" => $identity,
                            "description" => $identityData["description"],
                            "product_type" => $identityData["product_type"],
                            "product_id" => $productId,
                            "segments" => [
                                "family" => $segments["family"],
                                "size" => $segments["size"],
                                "color" => $segments["color"],
                                "cri" => $segments["cri"],
                                "series" => $segments["series"],
                                "lens" => $lens["code"],
                                "finish" => $finish["code"],
                                "cap" => $cap["code"],
                                "option" => $option["code"],
                            ],
                            "segment_labels" => [
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

                        $rows[] = $row;
                    }
                }
            }
        }
    }

    usort($rows, fn(array $a, array $b): int => strcmp($a["reference"], $b["reference"]));

    $filteredTotal = count($rows);
    $totalPages = max(1, (int) ceil($filteredTotal / $pageSize));
    $safePage = min(max($page, 1), $totalPages);
    $offset = ($safePage - 1) * $pageSize;
    $pagedRows = array_slice($rows, $offset, $pageSize);

    return [
        "family" => [
            "code" => $familyCode,
            "name" => $familyName,
        ],
        "summary" => $summary,
        "filters" => [
            "search" => $search,
            "status" => $statusFilter,
        ],
        "pagination" => [
            "page" => $safePage,
            "page_size" => $pageSize,
            "total_pages" => $totalPages,
            "total_rows" => $filteredTotal,
        ],
        "rows" => $pagedRows,
    ];
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
        CODE_EXPLORER_STATUS_DATASHEET_READY => $row["datasheet_ready"] === true,
        CODE_EXPLORER_STATUS_DATASHEET_BLOCKED => $row["datasheet_ready"] === false,
        default => true,
    };
}

function getCodeExplorerDatasheetReadiness(string $reference, string $productId, ?string $productType, string $description, array $options, array &$cache): array {
    $parts = decodeReference($reference);
    $family = $parts["family"];
    $lang = CODE_EXPLORER_DEFAULT_LANG;
    $cacheKey = $reference . "|" . $productId;

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
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

    $lumino = getLuminotechnicalData($productId, $reference, $lang);

    if ($lumino === null) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_header_data",
        ];
    }

    $header = getProductHeader((string) $productType, $productId, $reference, $lumino["led_id"], $config);

    if (($header["image"] ?? null) === null || trim((string) ($header["description"] ?? "")) === "") {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_header_data",
        ];
    }

    $ipRating = getIpRating($productId, "0") ?? "";
    $characteristics = getCharacteristics($productId, $ipRating, $family, $parts["lens"], $lang);

    if ($characteristics === null) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_header_data",
        ];
    }

    $sizesFile = getBarSizesFile($reference);
    $drawing = getTechnicalDrawing((string) $productType, $reference, $productId, $sizesFile, $config);

    if (($drawing["drawing"] ?? null) === null) {
        return $cache[$cacheKey] = [
            "datasheet_ready" => false,
            "failure_reason" => "missing_technical_drawing",
        ];
    }

    $colorGraph = getColorGraph($lumino["led_id"], $lang);

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

    $finishData = getFinishAndLens((string) $productType, $productId, $reference, $config);

    if ($finishData === null || str_contains((string) ($finishData["image"] ?? ""), "/img/placeholders/")) {
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
