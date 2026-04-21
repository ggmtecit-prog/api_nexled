<?php

require_once dirname(__FILE__) . "/../family-registry.php";

const CUSTOM_DATASHEET_ALLOWED_TOP_LEVEL_KEYS = ["base_request", "custom"];
const CUSTOM_DATASHEET_ALLOWED_CUSTOM_KEYS = ["mode", "text_overrides", "asset_overrides", "section_visibility", "footer"];
const CUSTOM_DATASHEET_ALLOWED_ASSET_OVERRIDE_KEYS = ["source", "asset_id", "asset_key"];
const CUSTOM_DATASHEET_ALLOWED_FOOTER_KEYS = ["marker"];

function buildCustomDatasheetRequestError(int $statusCode, string $errorCode, string $message, array $details = []): array {
    return [
        "ok" => false,
        "status_code" => $statusCode,
        "error" => array_merge([
            "error" => $message,
            "error_code" => $errorCode,
        ], $details),
    ];
}

function getCustomDatasheetTextOverrideLimits(): array {
    return [
        "document_title" => 120,
        "header_copy" => 1200,
        "footer_note" => 160,
    ];
}

function validateCustomDatasheetUnknownKeys(array $input, array $allowedKeys, string $errorCode, string $message, array $details = []): ?array {
    $unknownKeys = array_values(array_diff(array_keys($input), $allowedKeys));

    if ($unknownKeys === []) {
        return null;
    }

    return buildCustomDatasheetRequestError(
        400,
        $errorCode,
        $message,
        array_merge($details, ["unknown_keys" => $unknownKeys])
    );
}

function normalizeCustomDatasheetTextOverrides(mixed $value, string $family): array {
    if ($value === null) {
        return ["ok" => true, "data" => []];
    }

    if (!is_array($value)) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_override",
            "Text overrides must be an object."
        );
    }

    $allowedFields = getFamilyCustomDatasheetAllowedFields($family)["text_overrides"] ?? [];
    $unknown = validateCustomDatasheetUnknownKeys(
        $value,
        $allowedFields,
        "custom_datasheet_unsupported_field",
        "Custom datasheet text override contains unsupported fields.",
        ["group" => "text_overrides"]
    );

    if ($unknown !== null) {
        return $unknown;
    }

    $limits = getCustomDatasheetTextOverrideLimits();
    $normalized = [];

    foreach ($value as $field => $fieldValue) {
        if ($fieldValue === null) {
            continue;
        }

        if (!is_scalar($fieldValue)) {
            return buildCustomDatasheetRequestError(
                400,
                "custom_datasheet_invalid_override",
                "Text override value must be plain text.",
                ["group" => "text_overrides", "field" => $field]
            );
        }

        $text = normalizeCustomDatasheetPlainText((string) $fieldValue, $field === "header_copy");

        if ($text === "") {
            continue;
        }

        $limit = $limits[$field] ?? 255;

        if (mb_strlen($text) > $limit) {
            return buildCustomDatasheetRequestError(
                400,
                "custom_datasheet_text_too_long",
                "Custom datasheet text override exceeds maximum length.",
                ["group" => "text_overrides", "field" => $field, "max_length" => $limit]
            );
        }

        $normalized[$field] = $text;
    }

    return ["ok" => true, "data" => $normalized];
}

function normalizeCustomDatasheetAssetOverrides(mixed $value, string $family): array {
    if ($value === null) {
        return ["ok" => true, "data" => []];
    }

    if (!is_array($value)) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_override",
            "Asset overrides must be an object."
        );
    }

    $allowedFields = getFamilyCustomDatasheetAllowedFields($family)["asset_overrides"] ?? [];
    $unknown = validateCustomDatasheetUnknownKeys(
        $value,
        $allowedFields,
        "custom_datasheet_unsupported_field",
        "Custom datasheet asset override contains unsupported fields.",
        ["group" => "asset_overrides"]
    );

    if ($unknown !== null) {
        return $unknown;
    }

    $normalized = [];

    foreach ($value as $field => $fieldValue) {
        if ($fieldValue === null) {
            continue;
        }

        if (!is_array($fieldValue)) {
            return buildCustomDatasheetRequestError(
                400,
                "custom_datasheet_invalid_override",
                "Asset override must be an object.",
                ["group" => "asset_overrides", "field" => $field]
            );
        }

        $unknownAssetKeys = validateCustomDatasheetUnknownKeys(
            $fieldValue,
            CUSTOM_DATASHEET_ALLOWED_ASSET_OVERRIDE_KEYS,
            "custom_datasheet_invalid_override",
            "Asset override contains unsupported keys.",
            ["group" => "asset_overrides", "field" => $field]
        );

        if ($unknownAssetKeys !== null) {
            return $unknownAssetKeys;
        }

        $source = strtolower(trim((string) ($fieldValue["source"] ?? "")));

        if (!in_array($source, ["dam", "local"], true)) {
            return buildCustomDatasheetRequestError(
                400,
                "custom_datasheet_invalid_override",
                "Asset override source must be dam or local.",
                ["group" => "asset_overrides", "field" => $field]
            );
        }

        if ($source === "dam") {
            $assetId = trim((string) ($fieldValue["asset_id"] ?? ""));

            if ($assetId === "" || preg_match('/^[A-Za-z0-9._:-]+$/', $assetId) !== 1) {
                return buildCustomDatasheetRequestError(
                    400,
                    "custom_datasheet_invalid_override",
                    "DAM asset override requires a valid asset_id.",
                    ["group" => "asset_overrides", "field" => $field]
                );
            }

            $normalized[$field] = [
                "source" => "dam",
                "asset_id" => $assetId,
            ];
            continue;
        }

        $assetKey = trim((string) ($fieldValue["asset_key"] ?? ""));

        if ($assetKey === "" || preg_match('#^[A-Za-z0-9/_\.-]+$#', $assetKey) !== 1) {
            return buildCustomDatasheetRequestError(
                400,
                "custom_datasheet_invalid_override",
                "Local asset override requires a valid asset_key.",
                ["group" => "asset_overrides", "field" => $field]
            );
        }

        $normalized[$field] = [
            "source" => "local",
            "asset_key" => $assetKey,
        ];
    }

    return ["ok" => true, "data" => $normalized];
}

function normalizeCustomDatasheetSectionVisibility(mixed $value, string $family): array {
    if ($value === null) {
        return ["ok" => true, "data" => []];
    }

    if (!is_array($value)) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_override",
            "Section visibility overrides must be an object."
        );
    }

    $allowedFields = getFamilyCustomDatasheetAllowedFields($family)["section_visibility"] ?? [];
    $unknown = validateCustomDatasheetUnknownKeys(
        $value,
        $allowedFields,
        "custom_datasheet_section_forbidden",
        "Custom datasheet section visibility contains unsupported fields.",
        ["group" => "section_visibility"]
    );

    if ($unknown !== null) {
        return $unknown;
    }

    $normalized = [];

    foreach ($value as $field => $fieldValue) {
        $booleanValue = normalizeCustomDatasheetBoolean($fieldValue);

        if ($booleanValue === null) {
            return buildCustomDatasheetRequestError(
                400,
                "custom_datasheet_invalid_override",
                "Section visibility override must be boolean.",
                ["group" => "section_visibility", "field" => $field]
            );
        }

        $normalized[$field] = $booleanValue;
    }

    return ["ok" => true, "data" => $normalized];
}

function normalizeCustomDatasheetFooter(mixed $value, string $family): array {
    $defaults = getFamilyCustomDatasheetDefaults($family)["footer"] ?? ["marker" => "CustPDF"];

    if ($value === null) {
        return ["ok" => true, "data" => $defaults];
    }

    if (!is_array($value)) {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_override",
            "Footer overrides must be an object."
        );
    }

    $allowedFields = getFamilyCustomDatasheetAllowedFields($family)["footer"] ?? [];
    $unknown = validateCustomDatasheetUnknownKeys(
        $value,
        $allowedFields,
        "custom_datasheet_unsupported_field",
        "Custom datasheet footer override contains unsupported fields.",
        ["group" => "footer"]
    );

    if ($unknown !== null) {
        return $unknown;
    }

    $marker = array_key_exists("marker", $value)
        ? normalizeCustomDatasheetFooterMarker((string) $value["marker"])
        : (string) ($defaults["marker"] ?? "CustPDF");

    if ($marker === "") {
        return buildCustomDatasheetRequestError(
            400,
            "custom_datasheet_invalid_override",
            "Footer marker must not be empty.",
            ["group" => "footer", "field" => "marker"]
        );
    }

    return [
        "ok" => true,
        "data" => [
            "marker" => $marker,
        ],
    ];
}

function normalizeCustomDatasheetPlainText(string $value, bool $allowMultiline): string {
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[^\P{C}\n\t]/u', '', $value) ?? $value;
    $value = trim($value);

    if (!$allowMultiline) {
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        return trim($value);
    }

    $lines = array_map(
        static fn(string $line): string => trim(preg_replace('/\s+/u', ' ', $line) ?? $line),
        explode("\n", $value)
    );
    $lines = array_values(array_filter($lines, static fn(string $line): bool => $line !== ""));

    return implode("\n", $lines);
}

function normalizeCustomDatasheetBoolean(mixed $value): ?bool {
    if (is_bool($value)) {
        return $value;
    }

    if (is_int($value) || is_float($value)) {
        if ((int) $value === 1) {
            return true;
        }

        if ((int) $value === 0) {
            return false;
        }

        return null;
    }

    $normalized = strtolower(trim((string) $value));

    if (in_array($normalized, ["1", "true", "yes", "on"], true)) {
        return true;
    }

    if (in_array($normalized, ["0", "false", "no", "off"], true)) {
        return false;
    }

    return null;
}

function normalizeCustomDatasheetFooterMarker(string $value): string {
    $value = preg_replace('/[^A-Za-z0-9_-]/', '', trim($value)) ?? "";
    return substr($value, 0, 32);
}
