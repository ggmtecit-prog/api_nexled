<?php

require_once dirname(__FILE__, 3) . "/characteristics.php";
require_once dirname(__FILE__, 3) . "/reference-decoder.php";

function buildShowcaseTubularPages(array $assembledShowcase, array $normalizedRequest): array {
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

    $closingPages = buildShowcaseTubularClosingPages($finishGallerySection, $optionCodesSection, $lang);
    $pages = array_merge($pages, $closingPages);

    return array_values(array_filter($pages, static fn($page): bool => is_string($page) && trim($page) !== ""));
}

function buildShowcaseTubularDocumentTitle(array $assembledShowcase, array $normalizedRequest): string {
    $overview = is_array($assembledShowcase["sections"]["overview"] ?? null) ? $assembledShowcase["sections"]["overview"] : [];
    $reference = trim((string) ($overview["representative_reference"] ?? ""));
    $familyName = trim((string) ($assembledShowcase["family"]["name"] ?? "Tubular Showcase"));
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
        : "Tubular Showcase";
}

function buildShowcaseTubularFilename(array $assembledShowcase, array $normalizedRequest): string {
    $title = buildShowcaseTubularDocumentTitle($assembledShowcase, $normalizedRequest);
    $lang = sanitizeShowcaseFilenamePart((string) ($normalizedRequest["lang"] ?? "pt"));
    return sanitizeShowcaseFilenamePart($title) . "_" . $lang . ".pdf";
}

function buildShowcaseTubularOverviewPage(array $overviewSection, $luminotechnicalSection, string $lang): string {
    $reference = trim((string) ($overviewSection["representative_reference"] ?? ""));
    $productId = trim((string) ($overviewSection["representative_product_id"] ?? ""));
    $energyClass = "G";

    if (is_array($luminotechnicalSection) && is_array($luminotechnicalSection["rows"] ?? null) && $luminotechnicalSection["rows"] !== []) {
        $energyClass = trim((string) ($luminotechnicalSection["rows"][0]["energy_class"] ?? "G")) ?: "G";
    }

    $headerHtml = buildHeader([
        "image" => $overviewSection["hero_image"] ?? null,
        "description" => (string) ($overviewSection["description_html"] ?? ""),
    ], $energyClass);

    $characteristicsHtml = "";

    if ($reference !== "" && $productId !== "") {
        $parts = decodeReference($reference);
        $ipRating = getIpRating($productId, "0") ?? "";
        $characteristics = getCharacteristics(
            $productId,
            $ipRating,
            (string) ($parts["family"] ?? ""),
            (string) ($parts["lens"] ?? ""),
            $lang
        );

        if (is_array($characteristics) && $characteristics !== []) {
            $characteristicsHtml = buildCharacteristics($characteristics, $lang);
        }
    }

    return buildShowcaseTubularPageShell($headerHtml . $characteristicsHtml);
}

function buildShowcaseTubularLuminotechnicalPages(
    array $assembledShowcase,
    $luminotechnicalSection,
    $technicalDrawingSection,
    string $lang
): array {
    if (!is_array($luminotechnicalSection)) {
        return [];
    }

    $overview = is_array($assembledShowcase["sections"]["overview"] ?? null) ? $assembledShowcase["sections"]["overview"] : [];
    $reference = trim((string) ($overview["representative_reference"] ?? ""));
    $productId = trim((string) ($overview["representative_product_id"] ?? ""));
    $ipRating = $productId !== "" ? (getIpRating($productId, "0") ?? "") : "";
    $rows = is_array($luminotechnicalSection["rows"] ?? null) ? $luminotechnicalSection["rows"] : [];

    if ($rows === []) {
        return [];
    }

    $tableHtml = buildShowcaseTubularLuminotechnicalTable($rows, $lang, $reference, $ipRating);
    $drawingGroups = is_array($technicalDrawingSection) && is_array($technicalDrawingSection["groups"] ?? null)
        ? array_values($technicalDrawingSection["groups"])
        : [];

    if ($drawingGroups === []) {
        return [buildShowcaseTubularPageShell($tableHtml)];
    }

    $pages = [];
    $firstPageBody = $tableHtml;

    foreach (array_slice($drawingGroups, 0, 1) as $group) {
        $firstPageBody .= buildShowcaseTubularDrawingGroupBlock($group, $lang);
    }

    $pages[] = buildShowcaseTubularPageShell($firstPageBody);

    foreach (array_chunk(array_slice($drawingGroups, 1), 2) as $chunk) {
        $body = "";

        foreach ($chunk as $group) {
            $body .= buildShowcaseTubularDrawingGroupBlock($group, $lang);
        }

        if ($body !== "") {
            $pages[] = buildShowcaseTubularPageShell($body);
        }
    }

    return $pages;
}

function buildShowcaseTubularLuminotechnicalTable(array $rows, string $lang, string $reference, string $ipRating): string {
    $json = getShowcaseTubularDatasheetJson();
    $section = $json->luminotecnicas ?? null;

    if (!is_object($section) || !isset($section->colunas)) {
        return "";
    }

    $headerCells = "";
    $bodyRows = "";

    foreach ($section->colunas as $index => $col) {
        $size = intval($col->size ?? 10);
        $label = (string) ($col->$lang ?? $col->en ?? "");
        $headerCells .= "<td colspan=\"{$size}\" class=\"linha-tabela-contorno\"><p><b>{$label}</b></p></td>";
    }

    foreach ($rows as $row) {
        $lensName = (string) ($row["segment_labels"]["lens"] ?? "");
        $values = [
            (string) ($row["reference"] ?? ""),
            (string) ($row["legacy_description"] ?? $row["description"] ?? ""),
            (string) ($row["flux"] ?? ""),
            (string) ($row["efficacy"] ?? ""),
            (string) ($row["cct"] ?? ""),
            (string) ($row["color_label"] ?? ""),
            (string) ($row["cri"] ?? ""),
            $lensName,
        ];

        $cells = "";

        foreach ($section->colunas as $index => $col) {
            $size = intval($col->size ?? 10);
            $value = htmlspecialchars((string) ($values[$index] ?? ""), ENT_QUOTES, "UTF-8");
            $cellHtml = "<p>{$value}</p>";

            if ($index === 0) {
                $cellHtml = "<p style=\"font-size:7px;\"><nobr>{$value}</nobr></p>";
            } elseif ($index === 1) {
                $cellHtml = "<p style=\"font-size:7px;\"><nobr>{$value}</nobr></p>";
            }

            $cells .= "<td colspan=\"{$size}\" class=\"linha-tabela-contorno\">{$cellHtml}</td>";
        }

        $bodyRows .= "<tr>{$cells}</tr>";
    }

    $notesHtml = buildLuminotechnicalNotes($reference, $ipRating, $section->notas, $lang);

    return
        "<table cellpadding=\"0.5\" class=\"tabela\">" .
            "<tr><td colspan=\"80\" class=\"titulo-tabela\"><h2>{$section->titulo->$lang}</h2></td></tr>" .
            "<tr>{$headerCells}</tr>" .
            $bodyRows .
            "<tr><td></td></tr>" .
            $notesHtml .
            "<tr><td></td></tr>" .
        "</table>";
}

function buildShowcaseTubularDrawingGroupBlock(array $group, string $lang): string {
    $drawingData = array_merge(
        ["drawing" => $group["drawing"] ?? null],
        is_array($group["dimensions"] ?? null) ? $group["dimensions"] : []
    );

    return buildTechnicalDrawing($drawingData, $lang);
}

function buildShowcaseTubularSpectrumPage(array $groups, string $lang): string {
    $json = getShowcaseTubularDatasheetJson();
    $title = (string) ($json->graficocor->titulo->$lang ?? $json->graficocor->titulo->en ?? "Color spectrum");
    $rows = "";

    foreach ($groups as $group) {
        $label = htmlspecialchars(trim((string) ($group["label"] ?? "")), ENT_QUOTES, "UTF-8");
        $rows .=
            "<tr>" .
                "<td class=\"showcase-spectrum-cell\">" .
                    "<p><b>" . ($label !== "" ? $label : $title) . "</b></p>" .
                    "<div style=\"padding-top:4px;\">" . buildPdfImageTag($group["image"] ?? null) . "</div>" .
                "</td>" .
            "</tr>" .
            "<tr><td></td></tr>";
    }

    return buildShowcaseTubularPageShell(
        "<table class=\"showcase-block\" cellpadding=\"0\" cellspacing=\"0\">" .
            "<tr><td><h2>{$title}</h2></td></tr>" .
            $rows .
        "</table>"
    );
}

function buildShowcaseTubularLensPage(array $groups, string $lang): string {
    $json = getShowcaseTubularDatasheetJson();
    $title = (string) ($json->diagramalente->titulo->$lang ?? $json->diagramalente->titulo->en ?? "Lens diagrams");
    $body = "<table class=\"showcase-block\" cellpadding=\"0\" cellspacing=\"0\"><tr><td><h2>{$title}</h2></td></tr>";

    foreach ($groups as $group) {
        $label = htmlspecialchars(implode(" / ", array_values($group["lens_labels"] ?? [])), ENT_QUOTES, "UTF-8");
        $diagramTag = buildPdfImageTag($group["diagram"] ?? null, "height=\"180\"");
        $illuminanceTag = buildPdfImageTag($group["illuminance"] ?? null, "height=\"180\"");

        $body .=
            "<tr><td><p><b>{$label}</b></p></td></tr>" .
            "<tr>" .
                "<td>" .
                    "<table cellpadding=\"0\" cellspacing=\"0\"><tr>" .
                        "<td width=\"50%\">{$diagramTag}</td>" .
                        "<td width=\"50%\">{$illuminanceTag}</td>" .
                    "</tr></table>" .
                "</td>" .
            "</tr>" .
            "<tr><td></td></tr>";
    }

    $body .= "</table>";

    return buildShowcaseTubularPageShell($body);
}

function buildShowcaseTubularClosingPages($finishGallerySection, $optionCodesSection, string $lang): array {
    $pages = [];
    $finishGroups = is_array($finishGallerySection) && is_array($finishGallerySection["groups"] ?? null)
        ? array_values($finishGallerySection["groups"])
        : [];
    $optionGroups = is_array($optionCodesSection) && is_array($optionCodesSection["groups"] ?? null)
        ? array_values($optionCodesSection["groups"])
        : [];

    if ($finishGroups !== []) {
        foreach (array_chunk($finishGroups, 4) as $chunk) {
            $pages[] = buildShowcaseTubularFinishPage($chunk, $lang);
        }
    }

    if ($optionGroups !== []) {
        foreach (array_chunk($optionGroups, 2) as $chunk) {
            $pages[] = buildShowcaseTubularOptionCodesPage($chunk, $lang);
        }
    }

    return $pages;
}

function buildShowcaseTubularFinishPage(array $groups, string $lang): string {
    $json = getShowcaseTubularDatasheetJson();
    $title = (string) ($json->acabamento->titulo->$lang ?? $json->acabamento->titulo->en ?? "Finish");
    $rows = "";

    foreach (array_chunk($groups, 2) as $rowGroups) {
        $cells = "";

        foreach ($rowGroups as $group) {
            $name = trim((string) ($group["finish_name"] ?? $group["finish_label"] ?? ""));
            $code = trim((string) ($group["finish_code"] ?? ""));
            $cells .=
                "<td width=\"50%\" class=\"showcase-finish-cell\">" .
                    "<div class=\"showcase-finish-image\">" . buildPdfImageTag($group["image"] ?? null, "height=\"90\"") . "</div>" .
                    "<p><b>" . htmlspecialchars($name, ENT_QUOTES, "UTF-8") . "</b></p>" .
                    "<p>" . htmlspecialchars($code !== "" ? ("XX = " . $code) : "", ENT_QUOTES, "UTF-8") . "</p>" .
                "</td>";
        }

        if (count($rowGroups) === 1) {
            $cells .= "<td width=\"50%\"></td>";
        }

        $rows .= "<tr>{$cells}</tr><tr><td></td></tr>";
    }

    return buildShowcaseTubularPageShell(
        "<table class=\"showcase-block\" cellpadding=\"0\" cellspacing=\"0\">" .
            "<tr><td><h2>{$title}</h2></td></tr>" .
            $rows .
        "</table>"
    );
}

function buildShowcaseTubularOptionCodesPage(array $groups, string $lang): string {
    $title = $lang === "en" ? "Option codes" : "Codigo das opcoes";
    $tables = "";

    foreach ($groups as $group) {
        $rows = "";

        foreach ((array) ($group["items"] ?? []) as $item) {
            $code = htmlspecialchars((string) ($item["code"] ?? ""), ENT_QUOTES, "UTF-8");
            $label = htmlspecialchars((string) ($item["label"] ?? ""), ENT_QUOTES, "UTF-8");
            $rows .=
                "<tr>" .
                    "<td width=\"34%\" class=\"linha-tabela-contorno\"><p><b>{$code}</b></p></td>" .
                    "<td width=\"66%\" class=\"linha-tabela-contorno\"><p>{$label}</p></td>" .
                "</tr>";
        }

        $groupTitle = htmlspecialchars((string) ($group["title"] ?? ""), ENT_QUOTES, "UTF-8");
        $tables .=
            "<table class=\"tabela showcase-option-table\" cellpadding=\"0.5\" cellspacing=\"0\" border=\"0\">" .
                "<tr><td colspan=\"2\"><p><b>{$groupTitle}</b></p></td></tr>" .
                $rows .
            "</table>" .
            "<br>";
    }

    return buildShowcaseTubularPageShell(
        "<table class=\"showcase-block\" cellpadding=\"0\" cellspacing=\"0\">" .
            "<tr><td><h2>{$title}</h2></td></tr>" .
            "<tr><td>{$tables}</td></tr>" .
        "</table>"
    );
}

function getShowcaseTubularDatasheetJson(): object {
    static $json = null;

    if ($json === null) {
        $json = json_decode((string) file_get_contents(DATASHEET_JSON_PATH));
    }

    return $json;
}

function buildShowcaseTubularPageShell(string $body): string {
    return "<style>" . getShowcaseTubularCss() . "</style>" . $body;
}

function getShowcaseTubularCss(): string {
    static $css = null;

    if ($css !== null) {
        return $css;
    }

    $cssPath = dirname(__FILE__, 5) . "/appdatasheets/style/datasheet.css";
    $baseCss = is_file($cssPath) ? (string) file_get_contents($cssPath) : "";

    $css = $baseCss .
        " table{width:100%;}" .
        " .tabela{width:100%;}" .
        " .showcase-block{margin-bottom:12px;}" .
        " .showcase-option-table{margin-top:4px;}" .
        " .showcase-finish-cell{padding:4px 6px 8px 0; vertical-align:top; text-align:center;}" .
        " .showcase-finish-image{height:96px; text-align:center;}" .
        " .showcase-spectrum-cell{padding-right:8px; padding-top:8px; vertical-align:top;}";

    return $css;
}
