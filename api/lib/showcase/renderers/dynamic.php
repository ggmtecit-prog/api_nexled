<?php

require_once dirname(__FILE__) . "/tubular.php";
require_once dirname(__FILE__, 3) . "/reference-decoder.php";

function buildShowcaseDynamicPages(array $assembledShowcase, array $normalizedRequest): array {
    return buildShowcaseTubularPages($assembledShowcase, $normalizedRequest);
}

function buildShowcaseDynamicDocumentTitle(array $assembledShowcase, array $normalizedRequest): string {
    $overview = is_array($assembledShowcase["sections"]["overview"] ?? null) ? $assembledShowcase["sections"]["overview"] : [];
    $reference = trim((string) ($overview["representative_reference"] ?? ""));
    $familyName = trim((string) ($assembledShowcase["family"]["name"] ?? "Dynamic Showcase"));
    $parts = $reference !== "" ? decodeReference($reference) : [];
    $sizeCode = trim((string) ($normalizedRequest["locked"]["size"] ?? ($parts["size"] ?? "")));
    $size = ltrim($sizeCode, "0");
    $size = $size !== "" ? $size : $sizeCode;

    $titleParts = array_values(array_filter([
        $familyName !== "" ? $familyName : null,
        $size !== "" ? $size . "mm" : null,
    ]));

    return $titleParts !== []
        ? implode(" ", $titleParts)
        : "Dynamic Showcase";
}

function buildShowcaseDynamicFilename(array $assembledShowcase, array $normalizedRequest): string {
    $title = buildShowcaseDynamicDocumentTitle($assembledShowcase, $normalizedRequest);
    $lang = sanitizeShowcaseFilenamePart((string) ($normalizedRequest["lang"] ?? "pt"));
    return sanitizeShowcaseFilenamePart($title) . "_" . $lang . ".pdf";
}
