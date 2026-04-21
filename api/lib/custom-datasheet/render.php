<?php

require_once dirname(__FILE__) . "/../pdf-engine.php";
require_once dirname(__FILE__) . "/assets.php";

function buildCustomDatasheetPdfBinary(array $normalizedRequest): string {
    $baseRequest = $normalizedRequest["base_request"] ?? [];
    $custom = $normalizedRequest["custom"] ?? [];

    if (!is_array($baseRequest)) {
        throwDatasheetRequestError(400, [
            "error" => "Custom datasheet request requires a valid base_request payload.",
            "error_code" => "custom_datasheet_invalid_request",
        ]);
    }

    if (!is_array($custom)) {
        throwDatasheetRequestError(400, [
            "error" => "Custom datasheet request requires a valid custom payload.",
            "error_code" => "custom_datasheet_invalid_request",
        ]);
    }

    $context = buildDatasheetRenderContext($baseRequest);
    $context = applyCustomDatasheetOverridesToContext($context, $custom);

    return renderDatasheetPdfBinaryFromContext($context);
}

function applyCustomDatasheetOverridesToContext(array $context, array $custom): array {
    $textOverrides = is_array($custom["text_overrides"] ?? null) ? $custom["text_overrides"] : [];
    $assetOverrides = is_array($custom["asset_overrides"] ?? null) ? $custom["asset_overrides"] : [];
    $sectionVisibility = is_array($custom["section_visibility"] ?? null) ? $custom["section_visibility"] : [];
    $footer = is_array($custom["footer"] ?? null) ? $custom["footer"] : [];

    if (isset($textOverrides["document_title"])) {
        $context["document_title"] = (string) $textOverrides["document_title"];
    }

    if (isset($textOverrides["header_copy"])) {
        $context["data"]["header"]["description"] = (string) $textOverrides["header_copy"];
    }

    if (isset($textOverrides["footer_note"])) {
        $context["footer_note"] = (string) $textOverrides["footer_note"];
    }

    if (isset($footer["marker"])) {
        $context["footer_marker"] = (string) $footer["marker"];
    }

    applyCustomDatasheetAssetOverrides($context, $assetOverrides);
    applyCustomDatasheetSectionVisibility($context, $sectionVisibility);

    return $context;
}

function applyCustomDatasheetAssetOverrides(array &$context, array $assetOverrides): void {
    $assetTargets = [
        "header_image" => ["data", "header", "image"],
        "drawing_image" => ["data", "drawing", "drawing"],
        "finish_image" => ["data", "finish", "image"],
    ];

    foreach ($assetTargets as $field => $path) {
        if (!isset($assetOverrides[$field]) || !is_array($assetOverrides[$field])) {
            continue;
        }

        $resolvedAsset = resolveCustomDatasheetAssetOverride($assetOverrides[$field]);

        if (!is_string($resolvedAsset) || trim($resolvedAsset) === "") {
            throwDatasheetRequestError(422, [
                "error" => "Custom datasheet asset override could not be resolved.",
                "error_code" => "custom_datasheet_asset_missing",
                "field" => $field,
            ]);
        }

        setCustomDatasheetNestedValue($context, $path, $resolvedAsset);
    }
}

function applyCustomDatasheetSectionVisibility(array &$context, array $sectionVisibility): void {
    $sectionTargets = [
        "fixing" => "fixing",
        "power_supply" => "power_supply",
        "connection_cable" => "connection_cable",
    ];

    foreach ($sectionTargets as $field => $dataKey) {
        if (($sectionVisibility[$field] ?? true) !== false) {
            continue;
        }

        unset($context["data"][$dataKey]);
    }
}

function setCustomDatasheetNestedValue(array &$root, array $path, mixed $value): void {
    $cursor = &$root;
    $lastIndex = count($path) - 1;

    foreach ($path as $index => $segment) {
        if ($index === $lastIndex) {
            $cursor[$segment] = $value;
            return;
        }

        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }

        $cursor = &$cursor[$segment];
    }
}
