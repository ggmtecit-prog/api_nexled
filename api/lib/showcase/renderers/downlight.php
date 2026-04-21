<?php

require_once dirname(__FILE__, 3) . "/characteristics.php";
require_once dirname(__FILE__, 3) . "/reference-decoder.php";

function buildShowcaseDownlightPages(array $assembledShowcase, array $normalizedRequest): array {
    $sections = is_array($assembledShowcase["sections"] ?? null) ? $assembledShowcase["sections"] : [];
    $pages = [];

    $overviewSection = $sections["overview"] ?? null;
    $luminotechnicalSection = $sections["luminotechnical"] ?? null;
    $technicalDrawingSection = $sections["technical_drawings"] ?? null;
    $spectraSection = $sections["spectra"] ?? null;
    $lensSection = $sections["lens_diagrams"] ?? null;
    $finishGallerySection = $sections["finish_gallery"] ?? null;
    $optionCodesSection = $sections["option_codes"] ?? null;

    if (is_array($overviewSection)) {
        $pages[] = buildShowcaseDownlightOverviewPage($overviewSection, $luminotechnicalSection, $normalizedRequest);
    }

    $luminotechnicalPages = buildShowcaseDownlightLuminotechnicalPages(
        $assembledShowcase,
        $luminotechnicalSection,
        $technicalDrawingSection,
        $normalizedRequest
    );
    $pages = array_merge($pages, $luminotechnicalPages);

    if (is_array($spectraSection)) {
        $pages = array_merge(
            $pages,
            buildShowcaseDownlightSpectrumPages(
                is_array($spectraSection["groups"] ?? null) ? $spectraSection["groups"] : [],
                (string) ($normalizedRequest["lang"] ?? "pt")
            )
        );
    }

    $pages = array_merge(
        $pages,
        buildShowcaseDownlightClosingPages(
            $lensSection,
            $finishGallerySection,
            $optionCodesSection,
            (string) ($normalizedRequest["lang"] ?? "pt")
        )
    );

    return array_values(array_filter($pages, static fn($page): bool => is_string($page) && trim($page) !== ""));
}

function buildShowcaseDownlightDocumentTitle(array $assembledShowcase, array $normalizedRequest): string {
    $overview = is_array($assembledShowcase["sections"]["overview"] ?? null) ? $assembledShowcase["sections"]["overview"] : [];
    $reference = trim((string) ($overview["representative_reference"] ?? ""));
    $productId = trim((string) ($overview["representative_product_id"] ?? ""));
    $lang = (string) ($normalizedRequest["lang"] ?? "pt");
    $family = trim((string) ($assembledShowcase["family"]["code"] ?? $normalizedRequest["family"] ?? ""));
    $parts = $reference !== "" ? decodeReference($reference) : [];
    $sizeCode = trim((string) ($normalizedRequest["locked"]["size"] ?? ($parts["size"] ?? "")));
    $size = ltrim($sizeCode, "0");
    $size = $size !== "" ? $size : $sizeCode;
    $shape = match ($family) {
        "29" => "R",
        "30" => "Q",
        default => "",
    };
    $current = "";

    if ($reference !== "" && $productId !== "") {
        $ipRating = getIpRating($productId, "0") ?? "";
        $characteristics = getCharacteristics(
            $productId,
            $ipRating,
            (string) ($parts["family"] ?? $family),
            (string) ($parts["lens"] ?? ""),
            $lang
        ) ?? [];
        $current = extractShowcaseDownlightCurrentLabel($characteristics);
    }

    $titleParts = array_values(array_filter([
        "Downlight",
        $size !== "" ? $size : null,
        $shape !== "" ? $shape : null,
        $current !== "" ? $current : null,
    ]));

    if ($titleParts !== []) {
        return implode(" ", $titleParts);
    }

    return trim((string) ($assembledShowcase["family"]["name"] ?? "Downlight Showcase"));
}

function buildShowcaseDownlightFilename(array $assembledShowcase, array $normalizedRequest): string {
    $title = buildShowcaseDownlightDocumentTitle($assembledShowcase, $normalizedRequest);
    $lang = sanitizeShowcaseFilenamePart((string) ($normalizedRequest["lang"] ?? "pt"));
    return sanitizeShowcaseFilenamePart($title) . "_" . $lang . ".pdf";
}

function buildShowcaseDownlightOverviewPage(array $overviewSection, $luminotechnicalSection, array $normalizedRequest): string {
    $reference = trim((string) ($overviewSection["representative_reference"] ?? ""));
    $productId = trim((string) ($overviewSection["representative_product_id"] ?? ""));
    $lang = (string) ($normalizedRequest["lang"] ?? "pt");
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

    return buildShowcaseDownlightPageShell($headerHtml . $characteristicsHtml);
}

function buildShowcaseDownlightLuminotechnicalPages(
    array $assembledShowcase,
    $luminotechnicalSection,
    $technicalDrawingSection,
    array $normalizedRequest
): array {
    if (!is_array($luminotechnicalSection)) {
        return [];
    }

    $lang = (string) ($normalizedRequest["lang"] ?? "pt");
    $expanded = array_values(array_filter(
        array_map(static fn($segment): string => strtolower(trim((string) $segment)), $normalizedRequest["expanded"] ?? [])
    ));
    $collapsedRows = collapseShowcaseDownlightRows(
        is_array($luminotechnicalSection["rows"] ?? null) ? $luminotechnicalSection["rows"] : [],
        $expanded
    );
    $reference = trim((string) ($assembledShowcase["sections"]["overview"]["representative_reference"] ?? ""));
    $productId = trim((string) ($assembledShowcase["sections"]["overview"]["representative_product_id"] ?? ""));
    $ipRating = $productId !== "" ? (getIpRating($productId, "0") ?? "") : "";

    if ($collapsedRows === []) {
        return [];
    }

    $drawingGroup = is_array($technicalDrawingSection) ? (($technicalDrawingSection["groups"][0] ?? null)) : null;
    $tableHtml = buildShowcaseDownlightLuminotechnicalTable(
        $collapsedRows,
        $lang,
        $reference,
        $ipRating
    );
    $drawingHtml = "";

    if (is_array($drawingGroup)) {
        $drawingHtml = buildTechnicalDrawing(
            array_merge(["drawing" => $drawingGroup["drawing"] ?? null], $drawingGroup["dimensions"] ?? []),
            $lang
        );
    }

    return [buildShowcaseDownlightPageShell($tableHtml . $drawingHtml)];
}

function buildShowcaseDownlightSpectrumPages(array $groups, string $lang): array {
    if ($groups === []) {
        return [];
    }

    $pages = [];

    foreach (array_chunk($groups, 3) as $chunk) {
        $pages[] = buildShowcaseDownlightSpectrumPage($chunk, $lang);
    }

    return $pages;
}

function buildShowcaseDownlightSpectrumPage(array $groups, string $lang): string {
    $json = getShowcaseDownlightDatasheetJson();
    $title = (string) ($json->graficocor->titulo->$lang ?? $json->graficocor->titulo->en ?? "Color spectrum");
    $rows = "";

    foreach ($groups as $group) {
        $label = trim((string) ($group["label"] ?? ""));
        $imageTag = buildPdfImageTag($group["image"] ?? null);
        $rows .=
            "<tr>" .
                "<td class=\"showcase-spectrum-cell\">" .
                    "<p><b>" . escapeShowcasePdfText($label !== "" ? $label : $title) . "</b></p>" .
                    "<div style=\"padding-top:4px;\">" . $imageTag . "</div>" .
                "</td>" .
            "</tr>" .
            "<tr><td></td></tr>";
    }

    $body =
        "<table class=\"showcase-block\" cellpadding=\"0\" cellspacing=\"0\">" .
            "<tr><td><h2>{$title}</h2></td></tr>" .
            $rows .
        "</table>";

    return buildShowcaseDownlightPageShell($body);
}

function buildShowcaseDownlightClosingPages($lensSection, $finishGallerySection, $optionCodesSection, string $lang): array {
    $lensGroups = is_array($lensSection) && is_array($lensSection["groups"] ?? null)
        ? array_values($lensSection["groups"])
        : [];
    $finishGroups = is_array($finishGallerySection) && is_array($finishGallerySection["groups"] ?? null)
        ? array_values($finishGallerySection["groups"])
        : [];
    $optionGroups = is_array($optionCodesSection) && is_array($optionCodesSection["groups"] ?? null)
        ? array_values(array_filter(
            $optionCodesSection["groups"],
            static fn(array $group): bool => in_array((string) ($group["segment"] ?? ""), ["finish", "cap", "option"], true)
        ))
        : [];

    $optionHtml = buildShowcaseDownlightOptionCodesBlock($finishGroups, $optionGroups, $lang);
    $pages = [];

    if ($lensGroups === [] && $optionHtml === "") {
        return [];
    }

    if ($lensGroups === []) {
        return $optionHtml !== ""
            ? [buildShowcaseDownlightPageShell($optionHtml)]
            : [];
    }

    $lensChunks = array_chunk($lensGroups, 2);

    foreach ($lensChunks as $index => $lensChunk) {
        $isLast = $index === array_key_last($lensChunks);
        $body = "";

        if ($lensChunk !== []) {
            $body .= buildShowcaseDownlightLensBlock($lensChunk, $lang);
        }

        if ($isLast && $optionHtml !== "") {
            $body .= $optionHtml;
        }

        $pages[] = buildShowcaseDownlightPageShell($body);
    }

    return $pages;
}

function buildShowcaseDownlightLuminotechnicalTable(
    array $rows,
    string $lang,
    string $reference = "",
    string $ipRating = ""
): string {
    $json = getShowcaseDownlightDatasheetJson();
    $section = $json->luminotecnicas ?? null;
    $title = (string) ($section->titulo->$lang ?? $section->titulo->en ?? "Luminotechnical characteristics");
    $columnLabels = getShowcaseDownlightLuminoColumnLabels($section, $lang);
    $columnSizes = [12, 27, 4, 7, 6, 7, 5, 3, 9];

    $headerRow = "";
    foreach ($columnLabels as $index => $label) {
        $size = (int) ($columnSizes[$index] ?? 8);
        $headerRow .= "<td class=\"linha-tabela-contorno\" colspan=\"{$size}\"><p><b>{$label}</b></p></td>";
    }

    $bodyRows = "";
    foreach ($rows as $row) {
        $values = [
            (string) ($row["reference"] ?? ""),
            (string) ($row["description"] ?? ""),
            (string) ($row["energy_class"] ?? ""),
            (string) ($row["flux"] ?? ""),
            (string) ($row["efficacy"] ?? ""),
            (string) ($row["cct"] ?? ""),
            (string) ($row["color_label"] ?? ""),
            (string) ($row["cri"] ?? ""),
            (string) ($row["lens_label"] ?? ""),
        ];

        $bodyRows .= "<tr>";
        foreach ($values as $index => $value) {
            $size = (int) ($columnSizes[$index] ?? 8);
            $cellHtml = "<p>" . escapeShowcasePdfText($value) . "</p>";

            if ($index === 0) {
                $cellHtml = "<p style=\"font-size:7px;\"><nobr>" . escapeShowcasePdfText($value) . "</nobr></p>";
            } elseif ($index === 1) {
                $cellHtml = "<p style=\"font-size:7px;\"><nobr>" . escapeShowcasePdfText($value) . "</nobr></p>";
            } elseif ($index === 2) {
                $energyIcon = trim((string) buildPdfImageTag(getPdfEnergyLabelPath((string) $value), "width=\"16\""));

                if ($energyIcon !== "") {
                    $cellHtml = "<div style=\"text-align:center;\">" . $energyIcon . "</div>";
                }
            }

            $bodyRows .= "<td class=\"linha-tabela-contorno\" colspan=\"{$size}\">{$cellHtml}</td>";
        }
        $bodyRows .= "</tr>";
    }

    $notesHtml = "";
    if ($reference !== "" && $ipRating !== "") {
        $notesHtml = "<tr><td></td></tr>" .
            buildLuminotechnicalNotes($reference, $ipRating, $section->notas, $lang) .
            "<tr><td></td></tr>" .
            "<tr><td></td></tr>";
    }

    return
        "<table cellpadding=\"0.5\" class=\"tabela\">" .
            "<tr><td colspan=\"80\"><h2>{$title}</h2></td></tr>" .
            "<tr>{$headerRow}</tr>" .
            $bodyRows .
            $notesHtml .
        "</table>";
}

function getShowcaseDownlightLuminoColumnLabels($section, string $lang): array {
    $labels = [];
    $columns = is_array($section->colunas ?? null) ? $section->colunas : [];

    foreach ($columns as $column) {
        $labels[] = (string) ($column->$lang ?? $column->en ?? "");
    }

    $classLabel = match ($lang) {
        "en" => "Class",
        "es" => "Clase",
        default => "Classe",
    };

    if (count($labels) >= 8) {
        return [
            $labels[0],
            $labels[1],
            $classLabel,
            $labels[2],
            $labels[3],
            $labels[4],
            $labels[5],
            $labels[6],
            $labels[7],
        ];
    }

    return [
        "Reference",
        "Description",
        $classLabel,
        "Flux",
        "Efficacy",
        "CCT",
        "Color",
        "CRI",
        "Lens",
    ];
}

function buildShowcaseDownlightLensBlock(array $lensGroups, string $lang): string {
    if (count($lensGroups) === 1) {
        $group = $lensGroups[0];
        $label = trim((string) (($group["lens_labels"][0] ?? $group["lens_codes"][0] ?? "")));
        return buildLensDiagram(
            [
                "diagram" => $group["diagram"] ?? null,
                "illuminance" => $group["illuminance"] ?? null,
            ],
            $label,
            $lang
        );
    }

    $json = getShowcaseDownlightDatasheetJson();
    $title = (string) ($json->diagramalente->titulo->$lang ?? $json->diagramalente->titulo->en ?? "Beam diagram");
    $cells = "";
    $cellWidth = count($lensGroups) > 1 ? "50%" : "100%";

    foreach ($lensGroups as $group) {
        $label = trim((string) (($group["lens_labels"][0] ?? $group["lens_codes"][0] ?? "")));
        $imageTag = buildPdfImageTag($group["diagram"] ?? null, count($lensGroups) > 1 ? "height=\"210\"" : "height=\"210\"");

        $cells .=
            "<td width=\"{$cellWidth}\" class=\"showcase-lens-cell\">" .
                "<p><b>" . escapeShowcasePdfText($label !== "" ? $label : $title) . "</b></p>" .
                "<div style=\"text-align:center;\">" . $imageTag . "</div>" .
            "</td>";
    }

    return
        "<table class=\"showcase-block\" cellpadding=\"0\" cellspacing=\"0\">" .
            "<tr><td colspan=\"" . max(1, count($lensGroups)) . "\"><h2>{$title}</h2></td></tr>" .
            "<tr>{$cells}</tr>" .
            "<tr><td></td></tr>" .
            "<tr><td></td></tr>" .
        "</table>";
}

function buildShowcaseDownlightOptionCodesBlock(array $finishGroups, array $groups, string $lang): string {
    if ($groups === [] && $finishGroups === []) {
        return "";
    }

    $title = match ($lang) {
        "en" => "XXYYZZ - Option code",
        "es" => "XXYYZZ - Codigo de opciones",
        default => "XXYYZZ - Codigo das opcoes",
    };
    $intro = match ($lang) {
        "en" => "The last six characters are additional options.",
        "es" => "Los ultimos seis caracteres son opciones adicionales.",
        default => "Os ultimos seis algarismos sao opcoes adicionais.",
    };
    $finishLegendGroup = null;
    $groupHtml = "";

    foreach ($groups as $group) {
        if ((string) ($group["segment"] ?? "") === "finish" && $finishLegendGroup === null) {
            $finishLegendGroup = $group;
            continue;
        }

        $groupHtml .= buildShowcaseDownlightOptionGroupTable($group);
    }

    $finishHtml = buildShowcaseDownlightFinishOptionsBlock($finishGroups, $finishLegendGroup, $lang);

    if ($finishHtml !== "" && $groupHtml !== "") {
        $groupHtml =
            "<table class=\"showcase-option-layout\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">" .
                "<tr>" .
                    "<td width=\"48%\" class=\"showcase-option-group-cell\">{$finishHtml}</td>" .
                    "<td width=\"4%\"></td>" .
                    "<td width=\"48%\" class=\"showcase-option-group-cell\">{$groupHtml}</td>" .
                "</tr>" .
            "</table>";
    } else {
        $groupHtml = $finishHtml . $groupHtml;
    }

    if ($groupHtml === "") {
        return "";
    }

    return
        "<table class=\"showcase-block showcase-option-block\" cellpadding=\"0\" cellspacing=\"0\">" .
            "<tr><td><h2>{$title}</h2><p>{$intro}</p></td></tr>" .
            "<tr><td></td></tr>" .
            "<tr><td>{$groupHtml}</td></tr>" .
        "</table>";
}

function buildShowcaseDownlightOptionGroupTable(array $group): string {
    $token = trim((string) ($group["token"] ?? ""));
    $groupTitle = trim((string) ($group["title"] ?? ""));
    $items = dedupeShowcaseOptionItems(is_array($group["items"] ?? null) ? $group["items"] : []);
    $heading = $token !== "" && $groupTitle !== ""
        ? $token . " - " . $groupTitle
        : ($groupTitle !== "" ? $groupTitle : $token);

    if ($items === []) {
        return "";
    }

    $rows = "";
    foreach ($items as $item) {
        $code = trim((string) ($item["code"] ?? ""));
        $displayCode = $token !== "" ? trim($token . " = " . $code) : $code;
        $rows .=
            "<tr>" .
                "<td width=\"34%\" class=\"linha-tabela-contorno\"><p><b>" . escapeShowcasePdfText($displayCode) . "</b></p></td>" .
                "<td width=\"66%\" class=\"linha-tabela-contorno\"><p>" . escapeShowcasePdfText((string) ($item["label"] ?? "")) . "</p></td>" .
            "</tr>";
    }

    return
        "<table class=\"tabela showcase-option-table\" cellpadding=\"0.5\" cellspacing=\"0\" border=\"0\">" .
            "<tr><td colspan=\"2\"><p><b>" . escapeShowcasePdfText($heading) . "</b></p></td></tr>" .
            $rows .
            "<tr><td></td></tr>" .
        "</table>";
}

function buildShowcaseDownlightFinishOptionsBlock(array $finishGroups, $finishLegendGroup, string $lang): string {
    $meta = getShowcaseLegendMeta("finish", $lang);
    $token = trim((string) ($finishLegendGroup["token"] ?? ($meta["token"] ?? "XX")));
    $groupTitle = trim((string) ($finishLegendGroup["title"] ?? ($meta["title"] ?? "Finish")));
    $heading = $token !== "" && $groupTitle !== ""
        ? $token . " - " . $groupTitle
        : ($groupTitle !== "" ? $groupTitle : $token);
    $items = $finishLegendGroup !== null
        ? dedupeShowcaseOptionItems(is_array($finishLegendGroup["items"] ?? null) ? $finishLegendGroup["items"] : [])
        : [];
    $cards = buildShowcaseDownlightFinishCards($finishGroups, $items);

    if ($cards === []) {
        return "";
    }

    $rows = "";
    foreach (array_chunk($cards, 2) as $chunk) {
        $cells = "";

        foreach ($chunk as $card) {
            $image = trim((string) ($card["image"] ?? ""));
            $imageTag = $image !== ""
                ? buildPdfImageTag($image, "width=\"88\"")
                : "";
            $name = trim((string) ($card["name"] ?? "")) ?: trim((string) ($card["label"] ?? ""));
            $code = trim((string) ($card["code"] ?? ""));

            $cells .=
                "<td width=\"50%\" class=\"showcase-finish-cell\">" .
                    "<div class=\"showcase-finish-image\">" . $imageTag . "</div>" .
                    "<p><b>" . escapeShowcasePdfText($name !== "" ? $name : $code) . "</b></p>" .
                    "<p>" . escapeShowcasePdfText(trim($token . " = " . $code)) . "</p>" .
                "</td>";
        }

        if (count($chunk) === 1) {
            $cells .= "<td width=\"50%\"></td>";
        }

        $rows .= "<tr>{$cells}</tr>";
    }

    return
        "<table class=\"tabela showcase-option-table\" cellpadding=\"0.5\" cellspacing=\"0\" border=\"0\">" .
            "<tr><td colspan=\"2\"><p><b>" . escapeShowcasePdfText($heading) . "</b></p></td></tr>" .
            $rows .
            "<tr><td></td></tr>" .
        "</table>";
}

function buildShowcaseDownlightFinishCards(array $finishGroups, array $items): array {
    $cardsByCode = [];

    foreach ($finishGroups as $group) {
        $code = trim((string) ($group["finish_code"] ?? ""));

        if ($code === "") {
            continue;
        }

        if (!isset($cardsByCode[$code])) {
            $cardsByCode[$code] = [
                "code" => $code,
                "image" => trim((string) ($group["image"] ?? "")),
                "name" => trim((string) ($group["finish_name"] ?? "")),
                "label" => trim((string) ($group["finish_label"] ?? "")),
            ];
            continue;
        }

        if (trim((string) ($cardsByCode[$code]["image"] ?? "")) === "" && trim((string) ($group["image"] ?? "")) !== "") {
            $cardsByCode[$code]["image"] = trim((string) ($group["image"] ?? ""));
        }

        if (trim((string) ($cardsByCode[$code]["name"] ?? "")) === "" && trim((string) ($group["finish_name"] ?? "")) !== "") {
            $cardsByCode[$code]["name"] = trim((string) ($group["finish_name"] ?? ""));
        }

        if (trim((string) ($cardsByCode[$code]["label"] ?? "")) === "" && trim((string) ($group["finish_label"] ?? "")) !== "") {
            $cardsByCode[$code]["label"] = trim((string) ($group["finish_label"] ?? ""));
        }
    }

    foreach ($items as $item) {
        $code = trim((string) ($item["code"] ?? ""));
        $label = trim((string) ($item["label"] ?? ""));

        if ($code === "") {
            continue;
        }

        if (!isset($cardsByCode[$code])) {
            continue;
        }

        $currentName = trim((string) ($cardsByCode[$code]["name"] ?? ""));
        if (isShowcaseOptionLabelBetter($label, $currentName, $code)) {
            $cardsByCode[$code]["name"] = $label;
        }

        $currentLabel = trim((string) ($cardsByCode[$code]["label"] ?? ""));
        if (isShowcaseOptionLabelBetter($label, $currentLabel, $code)) {
            $cardsByCode[$code]["label"] = $label;
        }
    }

    $cardsByCode = array_filter($cardsByCode, static function (array $card): bool {
        return trim((string) ($card["image"] ?? "")) !== "";
    });

    ksort($cardsByCode, SORT_STRING);
    return array_values($cardsByCode);
}

function collapseShowcaseDownlightRows(array $rows, array $expandedSegments): array {
    $collapsed = [];

    foreach ($rows as $row) {
        $segments = is_array($row["segments"] ?? null) ? $row["segments"] : [];
        $groupKey = implode("|", [
            (string) ($segments["family"] ?? ""),
            (string) ($segments["size"] ?? ""),
            (string) ($segments["color"] ?? ""),
            (string) ($segments["cri"] ?? ""),
            (string) ($segments["series"] ?? ""),
            (string) ($segments["lens"] ?? ""),
        ]);

        if (!isset($collapsed[$groupKey])) {
            $collapsed[$groupKey] = [
                "reference" => buildShowcaseDownlightCollapsedReference($segments, $expandedSegments),
                "description" => buildShowcaseDownlightCollapsedDescription($row),
                "energy_class" => (string) ($row["energy_class"] ?? ""),
                "flux" => (string) ($row["flux"] ?? ""),
                "efficacy" => (string) ($row["efficacy"] ?? ""),
                "cct" => (string) ($row["cct"] ?? ""),
                "color_label" => (string) ($row["color_label"] ?? ""),
                "cri" => (string) ($row["cri"] ?? ""),
                "lens_label" => trim((string) ($row["segment_labels"]["lens"] ?? $segments["lens"] ?? "")),
            ];
        }
    }

    usort($collapsed, static function (array $left, array $right): int {
        return strcmp((string) ($left["reference"] ?? ""), (string) ($right["reference"] ?? ""));
    });

    return array_values($collapsed);
}

function buildShowcaseDownlightCollapsedReference(array $segments, array $expandedSegments): string {
    $suffixTokens = [
        "finish" => "XX",
        "cap" => "YY",
        "option" => "ZZ",
    ];
    $parts = [];

    foreach (["family", "size", "color", "cri", "series", "lens", "finish", "cap", "option"] as $segment) {
        $value = trim((string) ($segments[$segment] ?? ""));

        if (isset($suffixTokens[$segment]) && in_array($segment, $expandedSegments, true)) {
            $parts[] = $suffixTokens[$segment];
            continue;
        }

        $parts[] = $value;
    }

    return implode("", $parts);
}

function buildShowcaseDownlightCollapsedDescription(array $row): string {
    $description = trim(strip_tags((string) ($row["description"] ?? $row["legacy_description"] ?? "")));
    $segmentLabels = is_array($row["segment_labels"] ?? null) ? $row["segment_labels"] : [];

    foreach (["finish", "cap", "option"] as $segment) {
        $label = trim((string) ($segmentLabels[$segment] ?? ""));

        if ($label === "") {
            continue;
        }

        $description = preg_replace(
            "/(?:\\s*[\\-,\\/]\\s*)?" . preg_quote($label, "/") . "(?=\\s*[\\-,\\/]?\\s*$|\\s*[\\-,\\/])/iu",
            "",
            $description
        ) ?? $description;
    }

    $description = preg_replace("/\\s{2,}/", " ", $description) ?? $description;
    $description = trim($description, " ,-/");

    return $description !== "" ? $description : trim((string) ($row["legacy_description"] ?? ""));
}

function dedupeShowcaseOptionItems(array $items): array {
    $map = [];

    foreach ($items as $item) {
        $code = trim((string) ($item["code"] ?? ""));
        $label = trim((string) ($item["label"] ?? ""));

        if ($code === "") {
            continue;
        }

        if (!isShowcaseOptionItemRenderable($label, $code)) {
            continue;
        }

        if (!isset($map[$code])) {
            $map[$code] = [
                "code" => $code,
                "label" => $label !== "" ? $label : $code,
            ];
            continue;
        }

        $existingLabel = trim((string) ($map[$code]["label"] ?? ""));

        if (isShowcaseOptionLabelBetter($label, $existingLabel, $code)) {
            $map[$code]["label"] = $label !== "" ? $label : $code;
        }
    }

    ksort($map, SORT_STRING);
    return array_values($map);
}

function isShowcaseOptionLabelBetter(string $candidate, string $current, string $code): bool {
    $candidateScore = scoreShowcaseOptionLabel($candidate, $code);
    $currentScore = scoreShowcaseOptionLabel($current, $code);
    return $candidateScore > $currentScore;
}

function isShowcaseOptionItemRenderable(string $label, string $code): bool {
    return scoreShowcaseOptionLabel($label, $code) >= 2;
}

function scoreShowcaseOptionLabel(string $label, string $code): int {
    $normalized = strtolower(trim($label));

    if ($normalized === "" || $normalized === strtolower($code)) {
        return 0;
    }

    if (in_array($normalized, ["0", "nada", "none", "ninguno"], true)) {
        return 1;
    }

    return 2;
}

function extractShowcaseDownlightCurrentLabel(array $characteristics): string {
    foreach ($characteristics as $label => $value) {
        $haystack = strtolower(trim((string) $label));

        if (
            !str_contains($haystack, "corrente") &&
            !str_contains($haystack, "current")
        ) {
            continue;
        }

        if (preg_match("/([0-9]{2,4})\\s*m\\s*a/i", (string) $value, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match("/([0-9]{2,4})/", (string) $value, $matches) === 1) {
            return $matches[1];
        }
    }

    return "";
}

function getShowcaseDownlightDatasheetJson(): object {
    static $json = null;

    if ($json === null) {
        $json = json_decode((string) file_get_contents(DATASHEET_JSON_PATH));
    }

    return $json;
}

function buildShowcaseDownlightPageShell(string $body): string {
    return
        "<style>" . getShowcaseDownlightCss() . "</style>" .
        $body;
}

function escapeShowcasePdfText(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
}

function getShowcaseDownlightCss(): string {
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
        " .showcase-option-block{margin-top:8px;}" .
        " .showcase-option-layout{width:100%;}" .
        " .showcase-option-table{margin-top:4px;}" .
        " .showcase-option-group-cell{vertical-align:top;}" .
        " .showcase-finish-cell{padding:4px 6px 8px 0; vertical-align:top; text-align:center;}" .
        " .showcase-finish-image{height:74px; text-align:center;}" .
        " .showcase-lens-cell{padding-right:8px; vertical-align:top;}" .
        " .showcase-spectrum-cell{padding-right:8px; padding-top:8px; vertical-align:top;}";

    return $css;
}
