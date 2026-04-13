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
define("ICONS_PATH",          dirname(__FILE__, 3) . "/appdatasheets/img/icones/");
define("ENERGY_CLASS_PATH",   dirname(__FILE__, 3) . "/appdatasheets/img/classe-energetica/");

function toPdfAssetSrc(string $path): string {
    if ($path === "") {
        return "";
    }

    if (preg_match("#^(https?:)?//#i", $path) || str_starts_with($path, "data:")) {
        return $path;
    }

    $resolved = realpath($path) ?: $path;
    $normalized = str_replace("\\", "/", $resolved);

    if (is_file($resolved)) {
        $contents = file_get_contents($resolved);

        if ($contents !== false) {
            $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
            $mime = match ($extension) {
                "svg"  => "image/svg+xml",
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
    $energyClassImg = toPdfAssetSrc(ENERGY_CLASS_PATH . $energyClass . ".svg");
    $productImage = toPdfAssetSrc($header["image"]);

    return
    "<table nobr=\"true\">" .
        "<tr>" .
            "<td colspan=\"9\" style=\"text-align: right;\">" .
                "<img src=\"{$productImage}\">" .
                "<br>" .
                "<img width=\"40\" src=\"{$energyClassImg}\">" .
            "</td>" .
            "<td colspan=\"1\"></td>" .
            "<td colspan=\"15\">" .
                "<p class=\"descricao\">{$header["description"]}</p>" .
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
function buildCharacteristics(array $characteristics, string $lang): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->caracteristicas->titulo->$lang;

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
function buildLuminotechnical(array $lumino, string $reference, string $description, string $lensName, string $ipRating, string $lang): string {
    $json    = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $section = $json->luminotecnicas;

    $values = [$reference, $description, $lumino["flux"], $lumino["efficacy"], $lumino["cct"], $lumino["color_label"], $lumino["cri"], $lensName];

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
            $symbols .= "<img width=\"30\" src=\"" . toPdfAssetSrc(ICONS_PATH . $symbolFiles[$current]) . "\">";
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
function buildTechnicalDrawing(array $drawing, string $lang): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->desenhotecnico->titulo->$lang;
    $note  = $json->notaMedidas->$lang;

    $image   = toPdfAssetSrc($drawing["drawing"]);
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

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"$colCount\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"$colCount\"><img src=\"$image\"></td></tr>" .
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
function buildColorGraph(array $graph, string $reference, string $lang): string {
    $json   = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title  = $json->graficocor->titulo->$lang;
    $parts  = decodeReference($reference);
    $family = $parts["family"];

    $sdcm = isset($json->graficocor->SDCM->$family->$lang)
        ? $json->graficocor->SDCM->$family->$lang
        : $json->graficocor->SDCM->all->$lang;

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"5\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"5\"><p><b>{$graph["label"]}</b></p></td></tr>" .
        "<tr><td colspan=\"4\"><img src=\"" . toPdfAssetSrc($graph["image"]) . "\"></td></tr>" .
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
function buildLensDiagram(array $diagram, string $lensName, string $lang): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->diagramalente->titulo->$lang;

    $illuminanceCell = "";
    if ($diagram["illuminance"] !== null) {
        $illuminanceCell = "<img height=\"210\" src=\"" . toPdfAssetSrc($diagram["illuminance"]) . "\">";
    }

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"2\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"2\"><p><b>$lensName</b></p></td></tr>" .
        "<tr>" .
            "<td colspan=\"1\"><img height=\"210\" src=\"" . toPdfAssetSrc($diagram["diagram"]) . "\"></td>" .
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
function buildFinishAndLens(array $finishData, string $lensName, string $reference, string $lang): string {
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

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"3\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"3\"><p><b>$bodyLabel:</b> {$finishData["finish_name"]}<br><b>$lensLabel:</b> $lensName</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"1\"><img src=\"" . toPdfAssetSrc($finishData["image"]) . "\"></td></tr>" .
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
function buildFixing(array $fixing, string $reference, string $lang): string {
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

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"3\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"3\"><p><b>{$fixing["name"]}</b></p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr>" .
            "<td colspan=\"2\"><img src=\"" . toPdfAssetSrc($fixing["image"]) . "\"></td>" .
            "<td colspan=\"1\" style=\"text-align:right;\"><img width=\"150\" src=\"" . toPdfAssetSrc($fixing["render"]) . "\"></td>" .
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
function buildPowerSupply(array $supply, string $lang): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->fonte->titulo->$lang;
    $note  = $json->notaMedidas->$lang;

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"3\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"3\"><p>{$supply["description"]}</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr>" .
            "<td colspan=\"2\"><img src=\"" . toPdfAssetSrc($supply["drawing"]) . "\"></td>" .
            "<td colspan=\"1\"><img height=\"210\" src=\"" . toPdfAssetSrc($supply["image"]) . "\"></td>" .
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
function buildConnectionCable(array $cable, string $lang): string {
    $json  = json_decode(file_get_contents(DATASHEET_JSON_PATH));
    $title = $json->ligacao->titulo->$lang;
    $note  = $json->notaLigacao->$lang ?? "";

    return
    "<table nobr=\"true\">" .
        "<tr><td colspan=\"4\"><h2>$title</h2></td></tr>" .
        "<tr><td colspan=\"4\"><p>{$cable["description"]}</p></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"2\"><img height=\"210\" src=\"" . toPdfAssetSrc($cable["image"]) . "\"></td></tr>" .
        "<tr><td></td></tr>" .
        "<tr><td colspan=\"4\"><p>$note</p></td></tr>" .
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
function buildPdfLayout(array $data): string {
    $html  = "";
    $lang  = $data["lang"];
    $ref   = $data["reference"];

    $html .= buildHeader($data["header"], $data["energy_class"]);
    $html .= buildCharacteristics($data["characteristics"], $lang);
    $html .= buildLuminotechnical($data["luminotechnical"], $ref, $data["description"], $data["lens_name"], $data["ip_rating"], $lang);
    $html .= buildTechnicalDrawing($data["drawing"], $lang);

    if (!empty($data["color_graph"])) {
        $html .= buildColorGraph($data["color_graph"], $ref, $lang);
    }

    if (!empty($data["lens_diagram"])) {
        $html .= buildLensDiagram($data["lens_diagram"], $data["lens_name"], $lang);
    }

    $html .= buildFinishAndLens($data["finish"], $data["lens_name"], $ref, $lang);

    if (isset($data["fixing"])) {
        $html .= buildFixing($data["fixing"], $ref, $lang);
    }

    if (isset($data["power_supply"])) {
        $html .= buildPowerSupply($data["power_supply"], $lang);
    }

    if (isset($data["connection_cable"])) {
        $html .= buildConnectionCable($data["connection_cable"], $lang);
    }

    return $html;
}
