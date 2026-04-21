<?php

require_once dirname(__FILE__) . "/../validate.php";
require_once dirname(__FILE__) . "/../reference-decoder.php";
require_once dirname(__FILE__) . "/../family-registry.php";
require_once dirname(__FILE__) . "/pattern.php";

function buildShowcaseRequestError(int $statusCode, string $errorCode, string $message, array $details = []): array {
    return [
        "ok" => false,
        "status_code" => $statusCode,
        "error" => array_merge([
            "error" => $message,
            "error_code" => $errorCode,
        ], $details),
    ];
}

function getShowcaseSegmentLengths(): array {
    return [
        "size" => REFERENCE_LENGTH_SIZE,
        "color" => REFERENCE_LENGTH_COLOR,
        "cri" => REFERENCE_LENGTH_CRI,
        "series" => REFERENCE_LENGTH_SERIES,
        "lens" => REFERENCE_LENGTH_LENS,
        "finish" => REFERENCE_LENGTH_FINISH,
        "cap" => REFERENCE_LENGTH_CAP,
        "option" => REFERENCE_LENGTH_OPTION,
    ];
}

function normalizeShowcaseRequest(array $input): array {
    $warnings = [];
    $lang = validateLang((string) ($input["lang"] ?? "pt"));
    $company = preg_replace("/[^a-zA-Z0-9]/", "", (string) ($input["company"] ?? "0"));
    $company = $company !== "" ? $company : "0";
    $pattern = strtoupper(preg_replace("/[^A-Z0-9]/", "", (string) ($input["pattern"] ?? "")));
    $baseReference = validateReference((string) ($input["base_reference"] ?? ""));

    $autoLocked = [];
    $hardLocked = [];
    $expanded = [];
    $derivedFamily = "";

    if ($baseReference !== "") {
        if (!hasFullReferenceLength($baseReference)) {
            return buildShowcaseRequestError(
                400,
                "showcase_invalid_request",
                "Base reference must contain a full 17-character live Tecit code."
            );
        }

        $baseParts = decodeReference($baseReference);
        $derivedFamily = $baseParts["family"];

        foreach (array_keys(getShowcaseSegmentLengths()) as $segment) {
            $autoLocked[$segment] = $baseParts[$segment];
        }

        $warnings[] = "showcase_base_reference_applied";
    }

    if ($pattern !== "") {
        $patternResult = parseShowcasePattern($pattern);

        if (($patternResult["ok"] ?? false) !== true) {
            return buildShowcaseRequestError(
                (int) ($patternResult["status_code"] ?? 400),
                (string) ($patternResult["error_code"] ?? "showcase_invalid_pattern"),
                (string) ($patternResult["message"] ?? "Invalid showcase pattern.")
            );
        }

        $patternData = $patternResult["data"];
        $patternFamily = $patternData["family"];

        if ($derivedFamily !== "" && $derivedFamily !== $patternFamily) {
            return buildShowcaseRequestError(
                400,
                "showcase_conflicting_pattern_and_fields",
                "Pattern family conflicts with base reference family.",
                [
                    "base_reference_family" => $derivedFamily,
                    "pattern_family" => $patternFamily,
                ]
            );
        }

        $derivedFamily = $patternFamily;
        $hardLocked = array_merge($hardLocked, $patternData["locked"] ?? []);
        $expanded = array_values(array_unique(array_merge($expanded, $patternData["expanded"] ?? [])));
        $warnings[] = "showcase_pattern_applied";
    }

    $rawFamilyInput = isset($input["family"]) ? trim((string) $input["family"]) : "";
    $explicitFamilyValue = validateFamily($input["family"] ?? null);

    if ($rawFamilyInput !== "" && $explicitFamilyValue <= 0) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Invalid family code provided for showcase request."
        );
    }

    $explicitFamily = $explicitFamilyValue > 0
        ? str_pad((string) $explicitFamilyValue, 2, "0", STR_PAD_LEFT)
        : "";

    if ($explicitFamily !== "" && $derivedFamily !== "" && $explicitFamily !== $derivedFamily) {
        return buildShowcaseRequestError(
            400,
            "showcase_conflicting_pattern_and_fields",
            "Explicit family conflicts with pattern or base reference family.",
            [
                "family" => $explicitFamily,
                "derived_family" => $derivedFamily,
            ]
        );
    }

    $family = $explicitFamily !== "" ? $explicitFamily : $derivedFamily;

    if ($family === "") {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Showcase request requires a family, a base reference, or a showcase pattern."
        );
    }

    $lockedResult = normalizeShowcaseLockedSegments($input["locked"] ?? null);

    if (($lockedResult["ok"] ?? false) !== true) {
        return $lockedResult;
    }

    foreach ($lockedResult["data"] as $segment => $value) {
        if (isset($hardLocked[$segment]) && $hardLocked[$segment] !== $value) {
            return buildShowcaseRequestError(
                400,
                "showcase_conflicting_pattern_and_fields",
                "Locked segment conflicts with showcase pattern.",
                [
                    "segment" => $segment,
                    "pattern_value" => $hardLocked[$segment],
                    "locked_value" => $value,
                ]
            );
        }

        $hardLocked[$segment] = $value;
    }

    foreach ($hardLocked as $segment => $value) {
        if (isset($autoLocked[$segment]) && $autoLocked[$segment] !== $value) {
            return buildShowcaseRequestError(
                400,
                "showcase_conflicting_pattern_and_fields",
                "Locked segment conflicts with base reference.",
                [
                    "segment" => $segment,
                    "base_reference_value" => $autoLocked[$segment],
                    "locked_value" => $value,
                ]
            );
        }
    }

    $expandedResult = normalizeShowcaseExpandedSegments($input["expanded"] ?? null, $family);

    if (($expandedResult["ok"] ?? false) !== true) {
        return $expandedResult;
    }

    $expanded = array_values(array_unique(array_merge($expanded, $expandedResult["data"])));
    $expandedValidation = normalizeShowcaseExpandedSegments($expanded, $family);

    if (($expandedValidation["ok"] ?? false) !== true) {
        return $expandedValidation;
    }

    $expanded = $expandedValidation["data"];

    foreach ($expanded as $segment) {
        if (isset($hardLocked[$segment])) {
            return buildShowcaseRequestError(
                400,
                "showcase_conflicting_pattern_and_fields",
                "The same segment cannot be both explicitly locked and expanded.",
                [
                    "segment" => $segment,
                ]
            );
        }
    }

    $locked = array_merge($autoLocked, $hardLocked);

    foreach ($expanded as $segment) {
        unset($locked[$segment]);
    }

    $defaults = getFamilyShowcaseDefaults($family);
    $sectionsResult = normalizeShowcaseSections($input["sections"] ?? null, $family, $defaults["sections"] ?? []);

    if (($sectionsResult["ok"] ?? false) !== true) {
        return $sectionsResult;
    }

    $filtersResult = normalizeShowcaseFilters($input["filters"] ?? null, $defaults["filters"] ?? getDefaultShowcaseFilters());

    if (($filtersResult["ok"] ?? false) !== true) {
        return $filtersResult;
    }

    return [
        "ok" => true,
        "data" => [
            "family" => $family,
            "lang" => $lang,
            "company" => $company,
            "base_reference" => $baseReference,
            "pattern" => $pattern !== "" ? $pattern : null,
            "locked" => $locked,
            "expanded" => $expanded,
            "sections" => $sectionsResult["data"],
            "filters" => $filtersResult["data"],
        ],
        "warnings" => $warnings,
    ];
}

function normalizeShowcaseLockedSegments(mixed $locked): array {
    if ($locked === null) {
        return [
            "ok" => true,
            "data" => [],
        ];
    }

    if (!is_array($locked)) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Locked segments must be an object keyed by segment name."
        );
    }

    $segmentLengths = getShowcaseSegmentLengths();
    $normalized = [];

    foreach ($locked as $segment => $value) {
        $normalizedSegment = strtolower(trim((string) $segment));

        if (!array_key_exists($normalizedSegment, $segmentLengths)) {
            return buildShowcaseRequestError(
                400,
                "showcase_invalid_request",
                "Unsupported locked segment provided.",
                [
                    "segment" => $normalizedSegment,
                ]
            );
        }

        $normalizedValue = strtoupper(preg_replace("/[^A-Z0-9]/", "", (string) $value));
        $expectedLength = $segmentLengths[$normalizedSegment];

        if ($normalizedValue === "" || strlen($normalizedValue) !== $expectedLength) {
            return buildShowcaseRequestError(
                400,
                "showcase_invalid_request",
                "Locked segment value has an invalid length.",
                [
                    "segment" => $normalizedSegment,
                    "expected_length" => $expectedLength,
                ]
            );
        }

        $normalized[$normalizedSegment] = $normalizedValue;
    }

    return [
        "ok" => true,
        "data" => $normalized,
    ];
}

function normalizeShowcaseExpandedSegments(mixed $expanded, string $family): array {
    if ($expanded === null) {
        return [
            "ok" => true,
            "data" => [],
        ];
    }

    if (!is_array($expanded)) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Expanded segments must be an array."
        );
    }

    $supportedSegments = getFamilyShowcaseExpandableSegments($family);
    $normalized = [];

    foreach ($expanded as $segment) {
        $normalizedSegment = strtolower(trim((string) $segment));

        if (!in_array($normalizedSegment, $supportedSegments, true)) {
            return buildShowcaseRequestError(
                400,
                "showcase_invalid_request",
                "Expanded segment is not supported for this family.",
                [
                    "segment" => $normalizedSegment,
                    "family" => $family,
                ]
            );
        }

        if (!in_array($normalizedSegment, $normalized, true)) {
            $normalized[] = $normalizedSegment;
        }
    }

    return [
        "ok" => true,
        "data" => $normalized,
    ];
}

function normalizeShowcaseSections(mixed $sections, string $family, array $defaultSections): array {
    if ($sections === null) {
        return [
            "ok" => true,
            "data" => $defaultSections,
        ];
    }

    if (!is_array($sections)) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Sections must be an array."
        );
    }

    $supportedSections = getFamilyShowcaseSections($family);
    $normalized = [];

    foreach ($sections as $section) {
        $normalizedSection = strtolower(trim((string) $section));

        if (!in_array($normalizedSection, $supportedSections, true)) {
            return buildShowcaseRequestError(
                400,
                "showcase_invalid_request",
                "Requested section is not supported for this family.",
                [
                    "section" => $normalizedSection,
                    "family" => $family,
                ]
            );
        }

        if (!in_array($normalizedSection, $normalized, true)) {
            $normalized[] = $normalizedSection;
        }
    }

    if ($normalized === []) {
        return buildShowcaseRequestError(
            400,
            "showcase_no_supported_sections",
            "Showcase request must contain at least one supported section."
        );
    }

    return [
        "ok" => true,
        "data" => $normalized,
    ];
}

function normalizeShowcaseFilters(mixed $filters, array $defaults): array {
    if ($filters === null) {
        return [
            "ok" => true,
            "data" => $defaults,
        ];
    }

    if (!is_array($filters)) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Filters must be an object."
        );
    }

    $datasheetReadyOnly = array_key_exists("datasheet_ready_only", $filters)
        ? (bool) $filters["datasheet_ready_only"]
        : (bool) ($defaults["datasheet_ready_only"] ?? true);
    $maxVariants = array_key_exists("max_variants", $filters)
        ? intval($filters["max_variants"])
        : intval($defaults["max_variants"] ?? 80);
    $maxPages = array_key_exists("max_pages", $filters)
        ? intval($filters["max_pages"])
        : intval($defaults["max_pages"] ?? 30);
    $sortBy = strtolower(trim((string) ($filters["sort_by"] ?? ($defaults["sort_by"] ?? "reference"))));

    if ($maxVariants < 1 || $maxVariants > 500) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "max_variants must stay between 1 and 500."
        );
    }

    if ($maxPages < 1 || $maxPages > 100) {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "max_pages must stay between 1 and 100."
        );
    }

    if ($sortBy !== "reference") {
        return buildShowcaseRequestError(
            400,
            "showcase_invalid_request",
            "Unsupported sort_by value for showcase preview."
        );
    }

    return [
        "ok" => true,
        "data" => [
            "datasheet_ready_only" => $datasheetReadyOnly,
            "max_variants" => $maxVariants,
            "max_pages" => $maxPages,
            "sort_by" => $sortBy,
        ],
    ];
}
