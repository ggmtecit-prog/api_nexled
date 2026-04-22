<?php

require_once dirname(__FILE__) . "/tubular.php";
require_once dirname(__FILE__, 3) . "/reference-decoder.php";

function buildShowcaseBarraPages(array $assembledShowcase, array $normalizedRequest): array {
    $sections = is_array($assembledShowcase["sections"] ?? null) ? $assembledShowcase["sections"] : [];
    $lang = (string) ($normalizedRequest["lang"] ?? "pt");
    $pages = [];

    $overviewSection = $sections["overview"] ?? null;
    $luminotechnicalSection = $sections["luminotechnical"] ?? null;
    $technicalDrawingSection = $sections["technical_drawings"] ?? null;
    $spectraSection = $sections["spectra"] ?? null;
    $lensSection = $sections["lens_diagrams"] ?? null;
    $finishGallerySection = $sections["finish_gallery"] ?? null;
    $optionCodesSection = $sections["option_codes"] ?? null;

    if (is_array($overviewSection)) {
        $pages[] = buildShowcaseTubularOverviewPage($overviewSection, $luminotechnicalSection, $lang);
    }

    $pages = array_merge(
        $pages,
        buildShowcaseTubularLuminotechnicalPages($assembledShowcase, $luminotechnicalSection, $technicalDrawingSection, $lang)
    );

    if (is_array($spectraSection) && is_array($spectraSection["groups"] ?? null)) {
        foreach (array_chunk(array_values($spectraSection["groups"]), 3) as $chunk) {
            $pages[] = buildShowcaseTubularSpectrumPage($chunk, $lang);
        }
    }

    if (is_array($lensSection) && is_array($lensSection["groups"] ?? null)) {
        foreach (array_chunk(array_values($lensSection["groups"]), 2) as $chunk) {
            $pages[] = buildShowcaseTubularLensPage($chunk, $lang);
        }
    }

    return array_merge($pages, buildShowcaseTubularClosingPages($finishGallerySection, $optionCodesSection, $lang));
}

function buildShowcaseBarraDocumentTitle(array $assembledShowcase, array $normalizedRequest): string {
    $overview = is_array($assembledShowcase["sections"]["overview"] ?? null) ? $assembledShowcase["sections"]["overview"] : [];
    $reference = trim((string) ($overview["representative_reference"] ?? ""));
    $familyName = trim((string) ($assembledShowcase["family"]["name"] ?? "Barra Showcase"));
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
        : "Barra Showcase";
}

function buildShowcaseBarraFilename(array $assembledShowcase, array $normalizedRequest): string {
    $title = buildShowcaseBarraDocumentTitle($assembledShowcase, $normalizedRequest);
    $lang = sanitizeShowcaseFilenamePart((string) ($normalizedRequest["lang"] ?? "pt"));
    return sanitizeShowcaseFilenamePart($title) . "_" . $lang . ".pdf";
}
