<?php

require_once dirname(__FILE__) . "/../validate.php";
require_once dirname(__FILE__) . "/../reference-decoder.php";
require_once dirname(__FILE__) . "/../family-registry.php";
require_once dirname(__FILE__) . "/validation.php";

function normalizeCustomDatasheetRequest(array $input): array {
    $topLevelUnknown = validateCustomDatasheetUnknownKeys(
        $input,
        CUSTOM_DATASHEET_ALLOWED_TOP_LEVEL_KEYS,
        "custom_datasheet_invalid_request",
        "Custom datasheet request contains unsupported top-level keys."
    );

    if ($topLevelUnknown !== null) {
        return $topLevelUnknown;
    }

    $baseRequestInput = $input["base_request"] ?? null;

    if (!is_array($baseRequestInput)) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_request",
            "Custom datasheet request requires a base_request object."
        );
    }

    $reference = validateReference((string) ($baseRequestInput["referencia"] ?? ""));

    if (!hasFullReferenceLength($reference)) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_request",
            "Custom datasheet requires a full 17-character Tecit reference."
        );
    }

    $parts = decodeReference($reference);
    $family = $parts["family"];
    $lang = validateLang((string) ($baseRequestInput["idioma"] ?? "pt"));
    $company = preg_replace("/[^a-zA-Z0-9]/", "", (string) ($baseRequestInput["empresa"] ?? "0"));
    $company = $company !== "" ? $company : "0";
    $description = trim((string) ($baseRequestInput["descricao"] ?? ""));
    $warnings = [];

    $customInput = $input["custom"] ?? [];

    if (!is_array($customInput) && $customInput !== null) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_request",
            "Custom datasheet custom payload must be an object when provided."
        );
    }

    $customInput = is_array($customInput) ? $customInput : [];
    $customUnknown = validateCustomDatasheetUnknownKeys(
        $customInput,
        CUSTOM_DATASHEET_ALLOWED_CUSTOM_KEYS,
        "custom_datasheet_invalid_request",
        "Custom datasheet custom payload contains unsupported keys.",
        ["group" => "custom"]
    );

    if ($customUnknown !== null) {
        return $customUnknown;
    }

    $mode = strtolower(trim((string) ($customInput["mode"] ?? "custom")));

    if ($mode !== "" && $mode !== "custom") {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_request",
            "Custom datasheet mode must be 'custom'."
        );
    }

    $textOverridesResult = normalizeCustomDatasheetTextOverrides($customInput["text_overrides"] ?? null, $family);

    if (($textOverridesResult["ok"] ?? false) !== true) {
        return $textOverridesResult;
    }

    $assetOverridesResult = normalizeCustomDatasheetAssetOverrides($customInput["asset_overrides"] ?? null, $family);

    if (($assetOverridesResult["ok"] ?? false) !== true) {
        return $assetOverridesResult;
    }

    $copyModeResult = normalizeCustomDatasheetCopyMode($customInput["copy_mode"] ?? null, $family);

    if (($copyModeResult["ok"] ?? false) !== true) {
        return $copyModeResult;
    }

    $fieldOverridesResult = normalizeCustomDatasheetFieldOverrides($customInput["field_overrides"] ?? null, $family);

    if (($fieldOverridesResult["ok"] ?? false) !== true) {
        return $fieldOverridesResult;
    }

    $copyOverridesResult = normalizeCustomDatasheetCopyOverrides($customInput["copy_overrides"] ?? null, $family);

    if (($copyOverridesResult["ok"] ?? false) !== true) {
        return $copyOverridesResult;
    }

    $sectionVisibilityResult = normalizeCustomDatasheetSectionVisibility($customInput["section_visibility"] ?? null, $family);

    if (($sectionVisibilityResult["ok"] ?? false) !== true) {
        return $sectionVisibilityResult;
    }

    $footerResult = normalizeCustomDatasheetFooter($customInput["footer"] ?? null, $family);

    if (($footerResult["ok"] ?? false) !== true) {
        return $footerResult;
    }

    $textOverrides = $textOverridesResult["data"];
    $assetOverrides = $assetOverridesResult["data"];
    $copyMode = $copyModeResult["data"];
    $fieldOverrides = $fieldOverridesResult["data"];
    $copyOverrides = $copyOverridesResult["data"];
    $sectionVisibility = $sectionVisibilityResult["data"];
    $footer = $footerResult["data"];

    if ($textOverrides === [] && $assetOverrides === [] && $fieldOverrides === [] && $copyOverrides === [] && $sectionVisibility === []) {
        $warnings[] = "custom_datasheet_no_overrides";
    }

    $normalized = [
        "family" => $family,
        "base_reference" => $reference,
        "base_request" => array_merge($baseRequestInput, [
            "referencia" => $reference,
            "idioma" => $lang,
            "empresa" => $company,
            "descricao" => $description,
        ]),
        "custom" => [
            "mode" => "custom",
            "copy_mode" => $copyMode,
            "text_overrides" => $textOverrides,
            "asset_overrides" => $assetOverrides,
            "field_overrides" => $fieldOverrides,
            "copy_overrides" => $copyOverrides,
            "section_visibility" => $sectionVisibility,
            "footer" => $footer,
        ],
    ];

    return [
        "ok" => true,
        "data" => $normalized,
        "warnings" => $warnings,
    ];
}
