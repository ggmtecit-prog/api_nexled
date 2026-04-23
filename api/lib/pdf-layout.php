<?php

/**
 * PDF Layout Builder
 *
 * Builds the HTML string that TCPDF renders into a PDF.
 * Each function handles one section of the datasheet.
 *
 * Data is passed in as clean arrays from the data fetchers in lib/.
 * This file has NO database calls and NO file lookups — pure HTML assembly.
 *
 * Section titles and notes come from datasheet.json (loaded once at the top).
 *
 * decodeReference() comes from reference-decoder.php
 */

define("DATASHEET_JSON_PATH", dirname(__FILE__, 2) . "/json/datasheet.json");

function toPdfAssetSrc(?string $path): string {
    if (!is_string($path) || trim($path) === "") {
        return "";
    }

    if (str_starts_with($path, "data:") || str_starts_with($path, "file://")) {
        return $path;
    }

    $resolved = function_exists("getPdfSafeAssetPath")
        ? getPdfSafeAssetPath($path)
        : (realpath($path) ?: $path);

    if (preg_match("#^(https?:)?//#i", $resolved)) {
        return $resolved;
    }

    $preferredSvg = resolveLegacySvgPath($resolved);

    if ($preferredSvg !== null) {
        $resolved = $preferredSvg;
    } elseif (function_exists("getPdfRenderableImagePath")) {
        $resolved = getPdfRenderableImagePath($resolved);
    }

    $normalized = str_replace("\\", "/", $resolved);
    $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));

    if (is_file($resolved)) {
        if ($extension === "svg") {
            if (PHP_OS_FAMILY === "Windows") {
                return $normalized;
            }

            if (preg_match("/^[A-Za-z]:\\//", $normalized)) {
                return "file:///" . $normalized;
            }

            if (str_starts_with($normalized, "/")) {
                return "file://" . $normalized;
            }

            return $normalized;
        }

        $contents = file_get_contents($resolved);

        if ($contents !== false) {
            $mime = match ($extension) {
                "png"  => "image/png",
                "jpg", "jpeg" => "image/jpeg",
                "gif"  => "image/gif",
                default => function_exists("mime_content_type")
                    ? (mime_content_type($resolved) ?: "application/octet-stream")
                    : "application/octet-stream",
            };

            return "data:" . $mime . ";base64," . base64_encode($contents);
        }
    }

    if (preg_match("/^[A-Za-z]:\\//", $normalized)) {
        return "file:///" . $normalized;
    }

    if (str_starts_with($normalized, "/")) {
        return "file://" . $normalized;
    }

    return $normalized;
}

function getPdfEnergyLabelPath(string $energyClass): ?string {
    return findDamOrLocalSharedAsset(
        "energy-label",
        [$energyClass],
        dirname(__FILE__, 3) . "/appdatasheets/img/classe-energetica/" . $energyClass,
        ["svg", "png"]
    );
}

function getPdfIconPath(string $iconFile): ?string {
    $baseName = pathinfo($iconFile, PATHINFO_FILENAME);

    return findDamOrLocalSharedAsset(
        "icon",
        [$iconFile, $baseName],
        dirname(__FILE__, 3) . "/appdatasheets/img/icones/" . $baseName,
        ["svg", "png"]
    );
}

function buildPdfImageTag(?string $path, string $attributes = ""): string {
    $src = toPdfAssetSrc($path);

    if ($src === "") {
        return "";
    }

    $attrs = trim($attributes);
    if ($attrs !== "") {
        $attrs .= " ";
    }

    $attrs .= getLegacySvgDimensionAttributes($path, $attributes);

    return "<img {$attrs}src=\"{$src}\">";
}

function buildCustomCopyRows(?string $text, int|string $colspan, string $className = ""): string {
    if (!is_string($text)) {
        return "";
    }

    $normalized = trim(normalizePdfMultilineText($text));

    if ($normalized === "") {
        return "";
    }

    $escaped = formatPdfMultilineText($normalized);
    $classAttr = trim($className) !== "" ? " class=\"{$className}\"" : "";

    return
        "<tr><td colspan=\"{$colspan}\"><p{$classAttr}>{$escaped}</p></td></tr>" .
        "<tr><td></td></tr>";
}

function normalizePdfMultilineText(?string $text): string {
    if (!is_string($text) || trim($text) === "") {
        return "";
    }

    $normalized = str_replace(["<br />", "<br/>", "<br>"], "\n", $text);
    return str_replace(["\r\n", "\r"], "\n", $normalized);
}

function formatPdfMultilineText(?string $text): string {
    $normalized = normalizePdfMultilineText($text);

    if ($normalized === "") {
        return "";
    }

    return nl2br(htmlspecialchars($normalized, ENT_QUOTES, "UTF-8"));
}

function buildCustomFieldSummary(array $items, string $lang): string {
    if ($items === []) {
        return "";
    }

    $title = $lang === "pt" ? "Dados personalizados" : "Custom values";
    $rows = "";

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = trim((string) ($item["label"] ?? ""));
        $value = trim((string) ($item["value"] ?? ""));

        if ($label === "" || $value === "") {
            continue;
        }

        $labelEscaped = htmlspecialchars($label, ENT_QUOTES, "UTF-8");
        $valueEscaped = nl2br(htmlspecialchars($value, ENT_QUOTES, "UTF-8"));

        $rows .=
            "<tr>" .
                "<td class=\"linha-tabela-contorno\" colspan=\"2\"><p><b>{$labelEscaped}</b></p></td>" .
                "<td class=\"linha-tabela-contorno\" colspan=\"3\"><p>{$valueEscaped}</p></td>" .
            "</tr>";
    }

    if ($rows === "") {
        return "";
    }

    return
        "<table class=\"tabela\" border=\"0\" cellpadding=\"2\">" .
            "<tr><td colspan=\"5\" class=\"titulo-tabela\"><h2>{$title}</h2></td></tr>" .
            $rows .
            "<tr><td></td></tr>" .
            "<tr><td></td></tr>" .
        "</table>";
}

function getLegacySvgDimensionAttributes(?string $path, string $existingAttributes = ""): string {
    if (!is_string($path) || trim($path) === "") {
        return "";
    }

    $normalizedAttributes = strtolower($existingAttributes);
    if (
        str_contains($normalizedAttributes, " width=") ||
        str_starts_with($normalizedAttributes, "width=") ||
        str_contains($normalizedAttributes, " height=") ||
        str_starts_with($normalizedAttributes, "height=")
    ) {
        return "";
    }

    $svgPath = resolveLegacySvgPath($path);

    if ($svgPath === null) {
        return "";
    }

    $dimensions = getSvgDimensions($svgPath);

    if ($dimensions === null) {
        return "";
    }

    return "width=\"{$dimensions["width"]}\" height=\"{$dimensions["height"]}\" ";
}

function resolveLegacySvgPath(string $path): ?string {
    if (
        preg_match("#^(https?:)?//#i", $path) ||
        str_starts_with($path, "data:") ||
        str_starts_with($path, "file://")
    ) {
        return null;
    }

    $resolved = realpath($path) ?: $path;
    $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));

    if ($extension === "svg" && is_file($resolved)) {
        return $resolved;
    }

    if ($extension === "png") {
        $siblingSvg = preg_replace("/\\.png$/i", ".svg", $resolved);
        if (is_string($siblingSvg) && is_file($siblingSvg)) {
            return $siblingSvg;
        }
    }

    return null;
}

function getSvgDimensions(string $svgPath): ?array {
    if (!is_file($svgPath)) {
        return null;
    }

    $xml = @simplexml_load_file($svgPath);

    if ($xml === false) {
        return null;
    }

    $width  = trim((string) $xml["width"]);
    $height = trim((string) $xml["height"]);

    if ($width !== "" && $height !== "") {
        return ["width" => $width, "height" => $height];
    }

    $viewBox = trim((string) $xml["viewBox"]);
    if ($viewBox === "") {
        return null;
    }

    $parts = preg_split("/[\s,]+/", $viewBox);
    if ($parts === false || count($parts) !== 4) {
        return null;
    }

    $widthValue  = normalizeSvgDimensionValue($parts[2]);
    $heightValue = normalizeSvgDimensionValue($parts[3]);

    if ($widthValue === "" || $heightValue === "") {
        return null;
    }

    return ["width" => $widthValue, "height" => $heightValue];
}

function normalizeSvgDimensionValue(string $value): string {
    $numeric = (float) $value;

    if ($numeric <= 0) {
        return "";
    }

    if (abs($numeric - round($numeric)) < 0.00001) {
        return (string) (int) round($numeric);
    }

    return rtrim(rtrim(number_format($numeric, 5, ".", ""), "0"), ".");
}



// ---------------------------------------------------------------------------
// HEADER SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the header section: product image on the left, description on the right.
 *
 * @param  array  $header        From getProductHeader(): keys image, description
 * @param  string $energyClass   Letter from getEnergyClass() (e.g. "B")
 * @return string  HTML
 */
function buildHeader(array $header, string $energyClass): string {
    $energyClassImg = getPdfEnergyLabelPath($energyClass);
    $productImageTag = buildPdfImageTag($header["image"] ?? null);
    $energyClassTag = buildPdfImageTag($energyClassImg, "width=\"40\"");
    $description = formatPdfMultilineText((string) ($header["description"] ?? ""));

    return
    "<table nobr=\"true\">" .
        "<tr>" .
            "<td colspan=\"9\" style=\"text-align: right;\">" .
                $productImageTag .
                "<br>" .
                $energyClassTag .
            "</td>" .
            "<td colspan=\"1\"></td>" .
            "<td colspan=\"15\">" .
                "<p class=\"descricao\">{$description}</p>" .
            "</td>" .
        "</tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// CHARACTERISTICS SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the specifications table (Power, IP rating, Beam angle, etc.)
 *
 * @param  array  $characteristics  Key → value pairs from getCharacteristics()
 * @param  string $lang             Language code
 * @return string  HTML
 */
function buildCharacteristics(array $characteristics, string $lang, ?string $customIntro = null): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->caracteristicas->titulo->$lang;
    $intro = buildCustomCopyRows($customIntro, 5);

    $rows = "";
    foreach ($characteristics as $label => $value) {
        $rows .=
        "<tr>" .
            "<td class=\"linha-tabela-contorno\" colspan=\"2\"><p><b>$label</b></p></td>" .
            "<td class=\"linha-tabela-contorno\" colspan=\"3\"><p>$value</p></td>" .
        "</tr>";
    }

    return
    "<table class=\"tabela\" border=\"0\" cellpadding=\"2\">" .
        "<tr><td colspan=\"5\" class=\"titulo-tabela\"><h2>$title</h2></td></tr>" .
        $intro .
        $rows .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// LUMINOTECHNICAL SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the luminotechnical data table (flux, efficacy, CCT, colour, CRI, lens).
 *
 * @param  array  $lumino   From getLuminotechnicalData(): flux, efficacy, cct, color_label, cri
 * @param  string $reference Full product reference
 * @param  string $description Product description text
 * @param  string $lensName  Human-readable lens name
 * @param  string $ipRating  IP rating string
 * @param  string $lang      Language code
 * @return string  HTML
 */
function buildLuminotechnical(array $lumino, string $reference, string $description, string $lensName, string $ipRating, string $lang, ?string $customIntro = null, ?string $displayReference = null): string {
    $json    = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $section = $json->luminotecnicas;
    $intro = buildCustomCopyRows($customIntro, 80);

    $tableReference = is_string($displayReference) && trim($displayReference) !== ""
        ? trim($displayReference)
        : $reference;
    $values = [$tableReference, $description, $lumino["flux"], $lumino["efficacy"], $lumino["cct"], $lumino["color_label"], $lumino["cri"], $lensName];

    $header = "";
    $row    = "";
    $i      = 0;
    foreach ($section->colunas as $col) {
        $header .= "<td colspan=\"{$col->size}\" class=\"linha-tabela-contorno\"><p><b>{$col->$lang}</b></p></td>";
        $row    .= "<td class=\"linha-tabela-contorno\" colspan=\"{$col->size}\"><p>{$values[$i]}</p></td>";
        $i++;
    }

    return
    "<table cellpadding=\"0.5\" class=\"tabela\" nobr=\"true\">" .
        "<tr><td colspan=\"80\" class=\"titulo-tabela\"><h2>{$section->titulo->$lang}</h2></td></tr>" .
        $intro .
        "<tr>$header</tr>" .
        "<tr>$row</tr>" .
        "<tr><td></td></tr>" .
        buildLuminotechnicalNotes($reference, $ipRating, $section->notas, $lang) .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}

/**
 * Builds the notes and symbols row under the luminotechnical table.
 */
function buildLuminotechnicalNotes(string $reference, string $ipRating, object $notesData, string $lang): string {
    $parts  = decodeReference($reference);
    $family = $parts["family"];

    // Notes text
    $notes = "";
    foreach ($notesData->notas as $note) {
        $families = explode("/", $note->familias);
        if (!in_array($family, $families)) continue;

        $text = $note->$lang;
        $text = str_replace(["¹", "²", "³"], ["*", "**", "***"], $text);
        $notes .= $text . "<br>";
    }
    $notes = rtrim($notes, "<br>");

    // Symbol icons
    $symbolFiles = [];
    foreach ($notesData->simbolos as $symbol) {
        if (is_string($symbol)) {
            $symbolFiles[] = $symbol;
        } elseif (isset($symbol->familias) && in_array($family, (array) $symbol->familias)) {
            $symbolFiles[] = $symbol->img;
        }
    }
    $symbolFiles[] = strtolower($ipRating) . ".svg";

    $cols    = 16;
    $symbols = "";
    for ($i = 0; $i < $cols; $i++) {
        $current = -($cols - count($symbolFiles) - $i);
        $symbols .= "<td colspan=\"5\" style=\"text-align:right;\">";
        if (isset($symbolFiles[$current])) {
            $symbols .= buildPdfImageTag(getPdfIconPath($symbolFiles[$current]), "width=\"30\"");
        }
        $symbols .= "</td>";
    }

    return
        "<tr><td colspan=\"80\"><p class=\"notaLumino\">$notes</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr>$symbols</tr>";
}



// ---------------------------------------------------------------------------
// TECHNICAL DRAWING SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the technical drawing section with dimension labels A–J.
 *
 * @param  array  $drawing  From getTechnicalDrawing(): drawing (path), A–J values
 * @param  string $lang     Language code
 * @return string  HTML
 */
function buildTechnicalDrawing(array $drawing, string $lang, ?string $customIntro = null): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->desenhotecnico->titulo->$lang;
    $note  = $json->notaMedidas->$lang;
    $dimKeys = ["A","B","C","D","E","F","G","H","I","J"];

    $headerCells = "";
    $valueCells  = "";
    $colCount    = 0;

    foreach ($dimKeys as $key) {
        if (!isset($drawing[$key]) || $drawing[$key] === "0") continue;
        $headerCells .= "<td class=\"linha-tabela-contorno\" colspan=\"1\"><p style=\"text-align:center;\"><b>$key</b></p></td>";
        $valueCells  .= "<td class=\"linha-tabela-contorno\" colspan=\"1\"><p style=\"text-align:center;\">{$drawing[$key]}</p></td>";
        $colCount++;
    }

    $intro = buildCustomCopyRows($customIntro, max($colCount, 1));

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"$colCount\"><h2>$title</h2></td></tr>" .
        $intro .
        "<tr><td colspan=\"$colCount\">" . buildPdfImageTag($drawing["drawing"] ?? null) . "</td></tr>" .
        "<tr><td colspan=\"$colCount\"></td></tr>" .
        "<tr>$headerCells</tr>" .
        "<tr>$valueCells</tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"$colCount\"><p>$note</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// COLOR GRAPH SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the CIE colour graph section.
 *
 * @param  array  $graph   From getColorGraph(): label, image
 * @param  string $reference Full product reference
 * @param  string $lang    Language code
 * @return string  HTML
 */
function buildColorGraph(array $graph, string $reference, string $lang, ?string $customIntro = null): string {
    $json   = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title  = $json->graficocor->titulo->$lang;
    $parts  = decodeReference($reference);
    $family = $parts["family"];

    $sdcm = isset($json->graficocor->SDCM->$family->$lang)
        ? $json->graficocor->SDCM->$family->$lang
        : $json->graficocor->SDCM->all->$lang;
    $intro = buildCustomCopyRows($customIntro, 5);

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"5\"><h2>$title</h2></td></tr>" .
        $intro .
        "<tr><td colspan=\"5\"><p><b>{$graph["label"]}</b></p></td></tr>" .
        "<tr><td colspan=\"4\">" . buildPdfImageTag($graph["image"] ?? null) . "</td></tr>" .
        "<tr><td colspan=\"5\"><p>$sdcm</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// LENS DIAGRAM SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the polar beam diagram section.
 *
 * @param  array  $diagram  From getLensDiagram(): diagram (path), illuminance (path|null)
 * @param  string $lensName Human-readable lens name
 * @param  string $lang     Language code
 * @return string  HTML
 */
function buildLensDiagram(array $diagram, string $lensName, string $lang, ?string $customIntro = null): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->diagramalente->titulo->$lang;
    $intro = buildCustomCopyRows($customIntro, 2);

    $illuminanceCell = "";
    if ($diagram["illuminance"] !== null) {
        $illuminanceCell = buildPdfImageTag($diagram["illuminance"], "height=\"210\"");
    }

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"2\"><h2>$title</h2></td></tr>" .
        $intro .
        "<tr><td colspan=\"2\"><p><b>$lensName</b></p></td></tr>" .
        "<tr>" .
            "<td colspan=\"1\">" . buildPdfImageTag($diagram["diagram"] ?? null, "height=\"210\"") . "</td>" .
            "<td colspan=\"1\">$illuminanceCell</td>" .
        "</tr>" .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// FINISH & LENS SECTION
// ---------------------------------------------------------------------------

/**
 * Builds the product appearance section showing the finish and lens combination.
 *
 * @param  array  $finishData  From getFinishAndLens(): image, finish_name
 * @param  string $lensName    Human-readable lens name
 * @param  string $reference   Full product reference
 * @param  string $lang        Language code
 * @return string  HTML
 */
function buildFinishAndLens(array $finishData, string $lensName, string $reference, string $lang, ?string $customIntro = null): string {
    $json    = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $section = $json->acabamento;
    $parts   = decodeReference($reference);
    $family  = $parts["family"];

    $title      = $section->titulo->$lang;
    $bodyLabel  = $section->corpo->$lang;
    $lensLabel  = $section->lente->$lang;

    // Notes
    $note = "";
    foreach ($section->notas->comlink->links as $link) {
        if (!in_array($family, (array) $link->familias)) continue;
        $note = $section->notas->comlink->$lang ?? "";
        break;
    }
    if ($note === "" && in_array($family, (array) $section->notas->familias)) {
        $note = $section->notas->semlink->$lang ?? "";
    }

    $intro = buildCustomCopyRows($customIntro, 3);

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"3\"><h2>$title</h2></td></tr>" .
        $intro .
        "<tr><td colspan=\"3\"><p><b>$bodyLabel:</b> {$finishData["finish_name"]}<br><b>$lensLabel:</b> $lensName</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"1\">" . buildPdfImageTag($finishData["image"] ?? null) . "</td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// FIXING SECTION (OPTIONAL)
// ---------------------------------------------------------------------------

/**
 * Builds the mounting hardware section.
 *
 * @param  array  $fixing  From getFixing(): image, render, name
 * @param  string $reference Full product reference
 * @param  string $lang    Language code
 * @return string  HTML
 */
function buildFixing(array $fixing, string $reference, string $lang, ?string $customIntro = null): string {
    $json    = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $section = $json->fixacao;
    $parts   = decodeReference($reference);
    $family  = $parts["family"];

    $title = $section->titulo->$lang;
    $note  = $section->notas->semlink->$lang ?? "";
    $noteMeasures = $json->notaMedidas->$lang;

    foreach ($section->notas->comlink->links as $link) {
        if (in_array($family, (array) $link->familias)) {
            $note = $section->notas->comlink->$lang ?? "";
            break;
        }
    }

    $intro = buildCustomCopyRows($customIntro, 3);

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"3\"><h2>$title</h2></td></tr>" .
        $intro .
        "<tr><td colspan=\"3\"><p><b>{$fixing["name"]}</b></p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr>" .
            "<td colspan=\"2\">" . buildPdfImageTag($fixing["image"] ?? null) . "</td>" .
            "<td colspan=\"1\" style=\"text-align:right;\">" . buildPdfImageTag($fixing["render"] ?? null, "width=\"150\"") . "</td>" .
        "</tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"3\"><p>$noteMeasures</p></td></tr>" .
        "<tr><td colspan=\"3\"><p>$note</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// POWER SUPPLY SECTION (OPTIONAL)
// ---------------------------------------------------------------------------

/**
 * Builds the power supply / driver section.
 *
 * @param  array  $supply  From getPowerSupply(): image, drawing, description
 * @param  string $lang    Language code
 * @return string  HTML
 */
function buildPowerSupply(array $supply, string $lang, ?string $customIntro = null): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->fonte->titulo->$lang;
    $note  = $json->notaMedidas->$lang;
    $descriptionText = is_string($customIntro) && trim($customIntro) !== ""
        ? $customIntro
        : (string) ($supply["description"] ?? "");
    $description = formatPdfMultilineText($descriptionText);

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"3\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"3\"><p>{$description}</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr>" .
            "<td colspan=\"2\">" . buildPdfImageTag($supply["drawing"] ?? null) . "</td>" .
            "<td colspan=\"1\">" . buildPdfImageTag($supply["image"] ?? null, "height=\"210\"") . "</td>" .
        "</tr>" .
        "<tr><td colspan=\"3\"><p>$note</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td></td></tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// CONNECTION CABLE SECTION (OPTIONAL)
// ---------------------------------------------------------------------------

/**
 * Builds the wiring / connection cable section.
 *
 * @param  array  $cable  From getConnectionCable(): image, description
 * @param  string $lang   Language code
 * @return string  HTML
 */
function buildConnectionCable(array $cable, string $lang, ?string $customIntro = null): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->ligacao->titulo->$lang;
    $note  = $json->notaLigacao->$lang ?? "";
    $descriptionText = is_string($customIntro) && trim($customIntro) !== ""
        ? $customIntro
        : (string) ($cable["description"] ?? "");
    $description = formatPdfMultilineText($descriptionText);

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"4\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"4\"><p>{$description}</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"2\">" . buildPdfImageTag($cable["image"] ?? null, "height=\"210\"") . "</td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"4\"><p>$note</p></td></tr>" .
    "</table>";
}

function buildModernHeroSummary(array $data): string {
    $lang = (string) ($data["lang"] ?? "pt");
    $eyebrow = $lang === "pt" ? "Ficha tecnica NexLed" : "NexLed technical datasheet";
    $referenceLabel = $lang === "pt" ? "Referencia" : "Reference";
    $lensLabel = $lang === "pt" ? "Lente" : "Lens";
    $energyLabel = $lang === "pt" ? "Classe energetica" : "Energy class";

    $descriptionRaw = html_entity_decode((string) ($data["description"] ?? ""), ENT_QUOTES, "UTF-8");
    $description = formatPdfMultilineText($descriptionRaw);
    $reference = htmlspecialchars((string) ($data["reference"] ?? ""), ENT_QUOTES, "UTF-8");
    $lensName = htmlspecialchars(trim((string) ($data["lens_name"] ?? "")) ?: "-", ENT_QUOTES, "UTF-8");
    $energyClass = htmlspecialchars(trim((string) ($data["energy_class"] ?? "")) ?: "-", ENT_QUOTES, "UTF-8");

    return
    "<table class=\"modern-hero\" cellpadding=\"6\" nobr=\"true\">" .
        "<tr>" .
            "<td colspan=\"3\"><p class=\"modern-hero-kicker\">{$eyebrow}</p></td>" .
        "</tr>" .
        "<tr>" .
            "<td colspan=\"3\"><h1>{$description}</h1></td>" .
        "</tr>" .
        "<tr>" .
            "<td colspan=\"1\" class=\"modern-meta-cell\"><p class=\"modern-meta-label\">{$referenceLabel}</p><p class=\"modern-meta-value\">{$reference}</p></td>" .
            "<td colspan=\"1\" class=\"modern-meta-cell\"><p class=\"modern-meta-label\">{$lensLabel}</p><p class=\"modern-meta-value\">{$lensName}</p></td>" .
            "<td colspan=\"1\" class=\"modern-meta-cell\"><p class=\"modern-meta-label\">{$energyLabel}</p><p class=\"modern-meta-value\">{$energyClass}</p></td>" .
        "</tr>" .
    "</table>";
}



// ---------------------------------------------------------------------------
// MASTER LAYOUT FUNCTION
// ---------------------------------------------------------------------------

/**
 * Assembles the full HTML content for the PDF.
 *
 * Calls each section builder in order. Optional sections are only included
 * when their data is present.
 *
 * @param  array  $data  All section data assembled by the PDF engine
 * @return string  Full HTML string ready for TCPDF
 */
function buildPdfLayoutClassic(array $data): string {
    $html  = "";
    $lang  = $data["lang"];
    $ref   = $data["reference"];

    $html .= buildHeader($data["header"], $data["energy_class"]);
    $html .= buildCustomFieldSummary(is_array($data["custom_field_summary"] ?? null) ? $data["custom_field_summary"] : [], $lang);
    $html .= buildCharacteristics($data["characteristics"], $lang, $data["characteristics_intro"] ?? null);
    $html .= buildLuminotechnical($data["luminotechnical"], $ref, $data["description"], $data["lens_name"], $data["ip_rating"], $lang, $data["luminotechnical_intro"] ?? null, $data["display_reference"] ?? null);
    $html .= buildTechnicalDrawing($data["drawing"], $lang, $data["drawing_intro"] ?? null);

    if (!empty($data["color_graph"])) {
        $html .= buildColorGraph($data["color_graph"], $ref, $lang, $data["color_graph_intro"] ?? null);
    }

    if (!empty($data["lens_diagram"])) {
        $html .= buildLensDiagram($data["lens_diagram"], $data["lens_name"], $lang, $data["lens_diagram_intro"] ?? null);
    }

    $html .= buildFinishAndLens($data["finish"], $data["lens_name"], $ref, $lang, $data["finish_intro"] ?? null);

    if (isset($data["fixing"])) {
        $html .= buildFixing($data["fixing"], $ref, $lang, $data["fixing_intro"] ?? null);
    }

    if (isset($data["power_supply"])) {
        $html .= buildPowerSupply($data["power_supply"], $lang, $data["power_supply_intro"] ?? null);
    }

    if (isset($data["connection_cable"])) {
        $html .= buildConnectionCable($data["connection_cable"], $lang, $data["connection_cable_intro"] ?? null);
    }

    return $html;
}

function buildPdfLayoutModern(array $data): string {
    $html  = "<div class=\"datasheet-variant-modern\">";
    $lang  = $data["lang"];
    $ref   = $data["reference"];

    $html .= buildModernHeroSummary($data);
    $html .= buildHeader($data["header"], $data["energy_class"]);
    $html .= buildCustomFieldSummary(is_array($data["custom_field_summary"] ?? null) ? $data["custom_field_summary"] : [], $lang);
    $html .= buildLuminotechnical($data["luminotechnical"], $ref, $data["description"], $data["lens_name"], $data["ip_rating"], $lang, $data["luminotechnical_intro"] ?? null, $data["display_reference"] ?? null);
    $html .= buildCharacteristics($data["characteristics"], $lang, $data["characteristics_intro"] ?? null);
    $html .= buildTechnicalDrawing($data["drawing"], $lang, $data["drawing_intro"] ?? null);
    $html .= buildFinishAndLens($data["finish"], $data["lens_name"], $ref, $lang, $data["finish_intro"] ?? null);

    if (!empty($data["color_graph"])) {
        $html .= buildColorGraph($data["color_graph"], $ref, $lang, $data["color_graph_intro"] ?? null);
    }

    if (!empty($data["lens_diagram"])) {
        $html .= buildLensDiagram($data["lens_diagram"], $data["lens_name"], $lang, $data["lens_diagram_intro"] ?? null);
    }

    if (isset($data["fixing"])) {
        $html .= buildFixing($data["fixing"], $ref, $lang, $data["fixing_intro"] ?? null);
    }

    if (isset($data["power_supply"])) {
        $html .= buildPowerSupply($data["power_supply"], $lang, $data["power_supply_intro"] ?? null);
    }

    if (isset($data["connection_cable"])) {
        $html .= buildConnectionCable($data["connection_cable"], $lang, $data["connection_cable_intro"] ?? null);
    }

    return $html . "</div>";
}

function buildPdfLayoutForVariant(array $data, string $designVariant): string {
    return $designVariant === "modern"
        ? buildPdfLayoutModern($data)
        : buildPdfLayoutClassic($data);
}

function buildPdfLayout(array $data): string {
    return buildPdfLayoutClassic($data);
}
