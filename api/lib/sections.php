<?php

/**
 * Datasheet Sections — Data Fetchers
 *
 * Fetches the data for all remaining PDF sections:
 *   - Color graph         (CIE chromaticity diagram)
 *   - Lens diagram        (polar beam chart + illuminance chart)
 *   - Finish & lens       (product appearance photo)
 *   - Fixing              (optional — mounting hardware)
 *   - Power supply        (optional — driver/PSU)
 *   - Connection cable    (optional — wiring)
 *   - Footer              (date, version, legal text)
 *
 * findImage() comes from images.php
 * decodeReference() comes from reference-decoder.php
 */

define("JSON_PATH",  dirname(__FILE__, 2) . "/json");

if (!defined("FINISH_PLACEHOLDER_PATH")) {
    define("FINISH_PLACEHOLDER_PATH", IMAGES_BASE_PATH . "/img/placeholders/finish-missing");
}

if (!defined("COLOR_GRAPH_ALIASES")) {
    define("COLOR_GRAPH_ALIASES", [
        "3528PINK"   => "2835PINK",
        "3528XN"     => "WW303",
        "CW503HE"    => "CW503",
        "CW503HEPRO" => "CW503",
        "CW573HEPRO" => "CW573HE",
        "CW653HE"    => "CW653",
        "CW653HEPRO" => "CW653",
        "NW353HEPRO" => "NW353HE",
        "NW403E1"    => "NW403",
        "WW303E1"    => "WW303",
        "WW303HETHR" => "WW303HEPRO",
    ]);
}

function getFinishPlaceholderImage(): ?string {
    $placeholder = findImage(FINISH_PLACEHOLDER_PATH);

    if ($placeholder !== null) {
        return $placeholder;
    }

    return findImage(IMAGES_BASE_PATH . "/img/logos/nexled");
}

function isFinishPlaceholderImage(?string $path): bool {
    if (!is_string($path) || trim($path) === "") {
        return false;
    }

    $normalized = str_replace("\\", "/", $path);
    return str_contains($normalized, "/img/placeholders/finish-missing");
}

function resolveColorGraphAlias(string $ledId): string {
    return COLOR_GRAPH_ALIASES[$ledId] ?? $ledId;
}

function getColorGraphLabel(string $ledId, string $lang, object $json): string {
    foreach ($json->leds as $led) {
        foreach ($led->led as $id) {
            if ($id === $ledId) {
                return $led->$lang ?? "";
            }
        }
    }

    return "";
}



// ---------------------------------------------------------------------------
// COLOR GRAPH
// ---------------------------------------------------------------------------

/**
 * Returns the CIE chromaticity diagram for a given LED.
 *
 * The graph image is stored per LED type in /img/temperaturas/{led_id}.
 * The LED label (e.g. "Warm White 3000K CRI >80") comes from leds.json.
 *
 * @param  string $ledId  LED identifier from the Luminos table
 * @param  string $lang   Language code
 * @return array|null  Keys: label (string), image (path) — null if not found
 */
function getColorGraph(string $ledId, string $lang): ?array {
    $json  = json_decode(file_get_contents(JSON_PATH . "/descricao/leds.json"));
    $aliasLedId = resolveColorGraphAlias($ledId);
    $label      = getColorGraphLabel($ledId, $lang, $json);

    if ($label === "" && $aliasLedId !== $ledId) {
        $label = getColorGraphLabel($aliasLedId, $lang, $json);
    }

    if ($label === "") {
        return null;
    }

    $image = findImage(IMAGES_BASE_PATH . "/img/temperaturas/" . $ledId);

    if ($image === null && $aliasLedId !== $ledId) {
        $image = findImage(IMAGES_BASE_PATH . "/img/temperaturas/" . $aliasLedId);
    }

    if ($image === null) {
        return null;
    }

    return [
        "label" => $ledId . " - " . $label,
        "image" => $image,
    ];
}



// ---------------------------------------------------------------------------
// LENS DIAGRAM
// ---------------------------------------------------------------------------

/**
 * Returns the polar beam diagram and (optional) illuminance chart for a product.
 *
 * Diagram path: /img/{family}/diagramas/{lens}.svg
 * Dynamic path: /img/{family}/{subtype}/diagramas/{lens}.svg
 *
 * The illuminance chart (/diagramas/i/{lens}.svg) is optional — not all
 * products have one.
 *
 * @param  string $productId  Internal product ID
 * @param  string $reference  Full product reference
 * @return array|null  Keys: diagram (path), illuminance (path|null) — null if diagram not found
 */
function getLensDiagram(string $productId, string $reference): ?array {
    $parts  = decodeReference($reference);
    $family = $parts["family"];
    $lens   = $parts["lens"];

    // Lens code 0 means no dedicated optic/lens. Official behaviour: hide this section.
    if ($lens === "" || $lens === "0") {
        return null;
    }

    if ($family === "48") {
        $subtype = explode("/", $productId)[1];
        $base    = IMAGES_BASE_PATH . "/img/$family/$subtype/diagramas/";
    } else {
        $base = IMAGES_BASE_PATH . "/img/$family/diagramas/";
    }

    $diagramPath = findImage($base . $lens);

    if ($diagramPath === null) {
        $diagramPath = findDamProductAsset($family, $productId, "technical_diagram", [$lens]);

        if ($diagramPath === null) {
            return null;
        }
    }

    $illuminancePath = findImage($base . "i/$lens");

    return [
        "diagram"     => $diagramPath,
        "illuminance" => $illuminancePath,
    ];
}



// ---------------------------------------------------------------------------
// FINISH & LENS
// ---------------------------------------------------------------------------

/**
 * Returns the product appearance photo and finish name for the finish/lens section.
 *
 * Each product type organises finish images differently:
 * - Barra:     /img/{family}/acabamentos/{lens}/{series}/ or /{lens}/
 * - Downlight: /img/{family}/acabamentos/
 * - Shelf:     /img/{family}/acabamentos/
 * - Tubular:   /img/{family}/acabamentos/
 * - Dynamic:   /img/{family}/{subtype}/acabamentos/
 *
 * The finish name (e.g. "Aluminium — Silver") is fetched from tecit_referencias.
 *
 * @param  string $productType  "barra", "downlight", "shelf", "tubular", or "dynamic"
 * @param  string $productId    Internal product ID
 * @param  string $reference    Full product reference
 * @param  array  $config       User selections: lens, finish, end_cap, lang
 * @return array|null  Keys: image (path), finish_name (string) — null if image not found
 */
function getFinishAndLens(string $productType, string $productId, string $reference, array $config): ?array {
    $parts  = decodeReference($reference);
    $family = $parts["family"];
    $size   = $parts["size"];
    $series = $parts["series"];
    $cap    = $parts["cap"];
    $finishCode = $parts["finish"];
    $lang   = $config["lang"];
    $lens   = strtolower($config["lens"]);
    $finish = strtolower($config["finish"]);
    $endCap = $config["end_cap"];

    // Fetch the finish name from the database
    $con   = connectDBReferencias();
    $query = mysqli_query($con,
        "SELECT Acabamento.desc, Acabamento.$lang
         FROM Acabamento, Familias
         WHERE Acabamento.familia = Familias.acabamento
           AND Familias.codigo = '$family'
           AND Acabamento.codigo = '$finishCode'
           AND (Acabamento.desc IS NOT NULL AND Acabamento.desc != ''
             OR Acabamento.$lang IS NOT NULL AND Acabamento.$lang != '')"
    );
    closeDB($con);

    $finishName = "";
    if (mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_assoc($query);
        if (!empty($row["desc"]))    $finishName  = $row["desc"];
        if (!empty($row[$lang]))     $finishName .= ($finishName ? " - " : "") . $row[$lang];
    }

    // Find the finish image
    switch ($productType) {

        case "barra":
            $folder = ($lens === "clear")
                ? "/img/$family/acabamentos/$lens/$series/"
                : "/img/$family/acabamentos/$lens/";

            if ($family === "32") {
                $finishToken = ltrim($finishCode, "0");
                if ($finishToken === "") {
                    $finishToken = "0";
                }

                $candidates = [
                    "{$finishToken}_{$endCap}",
                    "{$finishToken}_{$cap}",
                    str_replace("+", "_", "{$finish}_{$endCap}"),
                    str_replace("+", "_", "{$finish}_{$cap}"),
                ];
            } else {
                $candidates = [
                    str_replace("+", "_", "{$finish}_{$cap}"),
                    str_replace("+", "_", "{$finish}_{$endCap}"),
                ];
            }
            break;

        case "dynamic":
            $subtype    = explode("/", $productId)[1];
            $folder     = "/img/$family/$subtype/acabamentos/";
            $cleanFinish = str_replace("+", "", $finish);
            $candidates = ["{$size}_{$cleanFinish}"];
            break;

        case "shelf":
            $folder = "/img/$family/acabamentos/";
            $cleanFinish = str_replace("+", "_", $finish);
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}_{$endCap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$cleanFinish}_{$cap}",
                "{$cleanFinish}_{$endCap}",
                "{$size}_{$lens}",
                "{$size}",
            ];
            break;

        case "tubular":
            $folder = "/img/$family/acabamentos/";
            $cleanFinish = str_replace("+", "_", $finish);
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$size}_{$lens}",
                "{$size}",
            ];
            break;

        default: // downlight
            $folder     = "/img/$family/acabamentos/";
            $candidates = ["{$size}_{$lens}_{$finish}"];
            break;
    }

    $image = null;
    foreach ($candidates as $name) {
        $image = findImage(IMAGES_BASE_PATH . $folder . $name);
        if ($image !== null) break;
    }

    if (
        $image === null &&
        (
            in_array($productType, ["shelf", "tubular"], true) ||
            ($productType === "barra" && in_array($family, ["31", "40"], true))
        )
    ) {
        $image = findDamProductAsset($family, $productId, "technical_finish", $candidates);
    }

    if ($image === null) {
        $image = getFinishPlaceholderImage();

        if ($image === null) {
            return null;
        }

        error_log(
            "NexLed datasheet: missing finish image, using placeholder. " .
            "reference={$reference}; folder={$folder}; candidates=" . implode(",", $candidates)
        );

        if ($finishName === "") {
            $finishName = "Finish preview unavailable";
        }
    }

    return [
        "image"       => $image,
        "finish_name" => $finishName,
    ];
}



// ---------------------------------------------------------------------------
// FIXING (OPTIONAL)
// ---------------------------------------------------------------------------

/**
 * Returns the fixing / mounting hardware data for the datasheet.
 * Only called when the user selected a fixing option.
 *
 * Requires two images: the technical drawing and a render photo.
 * The fixing name comes from fixacao.json.
 *
 * @param  string $reference  Full product reference
 * @param  string $lens       Lens name (lowercase, e.g. "clear", "frost")
 * @param  string $cableType  Cable type selection
 * @param  string $endCap     End cap selection
 * @param  string $fixingId   Fixing option code
 * @param  string $lang       Language code
 * @return array|null  Keys: image, render, name — null if not found
 */
function getFixing(string $reference, string $lens, string $cableType, string $endCap, string $fixingId, string $lang): ?array {
    $parts  = decodeReference($reference);
    $family = $parts["family"];
    $cap    = $parts["cap"];

    $folder = IMAGES_BASE_PATH . "/img/$family/fixacao/";

    $candidates = [
        "{$lens}_{$cableType}_{$endCap}_{$fixingId}",
        "{$lens}_{$cap}_{$fixingId}",
        "{$endCap}_{$fixingId}",
        "{$lens}_{$fixingId}",
        $fixingId,
    ];

    $image  = null;
    $render = null;

    foreach ($candidates as $name) {
        if ($image  === null) $image  = findImage($folder . $name);
        if ($render === null) $render = findImage($folder . $name . "_render");
    }

    if ($image === null || $render === null) {
        return null;
    }

    $json = json_decode(file_get_contents(JSON_PATH . "/fixacao.json"));
    $name = $json->fixacao->$fixingId->$lang ?? null;

    if ($name === null) {
        return null;
    }

    return [
        "image"  => $image,
        "render" => $render,
        "name"   => $name,
    ];
}



// ---------------------------------------------------------------------------
// POWER SUPPLY (OPTIONAL)
// ---------------------------------------------------------------------------

/**
 * Returns the power supply (driver/PSU) data for the datasheet.
 * Only called when the user selected a power supply option.
 *
 * Requires two images: a photo and a technical drawing.
 * The description comes from fontes.json.
 *
 * @param  string $supplyId  Power supply option code
 * @param  string $lang      Language code
 * @return array|null  Keys: image, drawing, description — null if not applicable or not found
 */
function getPowerSupply(string $supplyId, string $lang): ?array {
    if ($supplyId === "0") {
        return null;
    }

    $folder = IMAGES_BASE_PATH . "/img/fontes/";

    $image   = findImage($folder . $supplyId);
    $drawing = findImage($folder . $supplyId . "_desenho");

    if ($image === null || $drawing === null) {
        return null;
    }

    $json        = json_decode(file_get_contents(JSON_PATH . "/fontes.json"));
    $description = $json->fontes->$supplyId->$lang ?? null;

    if ($description === null) {
        return null;
    }

    return [
        "image"       => $image,
        "drawing"     => $drawing,
        "description" => $description,
    ];
}



// ---------------------------------------------------------------------------
// CONNECTION CABLE (OPTIONAL)
// ---------------------------------------------------------------------------

/**
 * Returns the connection cable data for the datasheet.
 * Only called when the user selected a connection cable option.
 *
 * The cable image is looked up by connector + cable code combination.
 * The description comes from the descricaoCabos table in tecit_lampadas,
 * with the cable length substituted in.
 *
 * @param  string $reference    Full product reference
 * @param  string $cableId      Connection cable code
 * @param  string $connectorId  Connector type code
 * @param  string $cableLength  Cable length value
 * @param  string $lang         Language code
 * @return array|null  Keys: image, description — null if not applicable or not found
 */
function getConnectionCable(string $reference, string $cableId, string $connectorId, string $cableLength, string $lang): ?array {
    if ($cableId === "0") {
        return null;
    }

    $parts  = decodeReference($reference);
    $family = $parts["family"];
    $cap    = $parts["cap"];

    $folder = IMAGES_BASE_PATH . "/img/$family/ligacao/";

    $candidates = [
        "{$connectorId}_{$cableId}",
        "{$cap}_{$cableId}",
    ];

    $image = null;
    foreach ($candidates as $name) {
        $image = findImage($folder . $name);
        if ($image !== null) break;
    }

    if ($image === null) {
        $image = findDamProductAsset($family, "", "connector", $candidates);

        if ($image === null) {
            return null;
        }
    }

    // Look up cable ID template from JSON, substitute connector, then fetch description from DB
    $json     = json_decode(file_get_contents(JSON_PATH . "/ligacao.json"));
    $idTemplate = $json->ligacao->id->$cableId ?? null;

    if ($idTemplate === null) {
        return null;
    }

    $cableDbId = str_replace("[conector]", $connectorId, $idTemplate);

    $con   = connectDBLampadas();
    $query = mysqli_query($con,
        "SELECT desc_$lang FROM descricaoCabos WHERE ID = '$cableDbId'"
    );
    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $row         = mysqli_fetch_assoc($query);
    $description = $row["desc_$lang"] ?? "";

    // Append cable length line (e.g. "Cable length: 1.5m")
    $lengthLine  = $json->ligacao->tamanho->$lang ?? "";
    $description .= "<br>" . str_replace("[tamanho]", $cableLength, $lengthLine);

    return [
        "image"       => $image,
        "description" => $description,
    ];
}



// ---------------------------------------------------------------------------
// FOOTER
// ---------------------------------------------------------------------------

/**
 * Returns the footer text for the datasheet.
 *
 * The footer template comes from datasheet.json and contains
 * placeholders for the product date and version number,
 * both fetched from the database.
 *
 * @param  string $productId  Internal product ID
 * @param  string $lang       Language code
 * @return string  Footer text with date and version substituted in
 */
function getFooter(string $productId, string $lang): string {
    $con = connectDBLampadas();

    $dateQuery = mysqli_query($con, "SELECT valor_pt FROM caracteristicas WHERE texto_pt LIKE 'data' AND ID LIKE '$productId'");

    $version = "";
    try {
        mysqli_query($con, "CALL versao('$productId', @v)");
        mysqli_query($con, "SELECT @v");
        $versionResult = mysqli_query($con, "SELECT @v");
        if ($versionResult) {
            $row     = mysqli_fetch_array($versionResult);
            $version = $row["@v"] ?? "";
        }
    } catch (\Exception $e) {
        // Stored procedure not available (e.g. local dev) — version stays empty
    }

    closeDB($con);

    $date = "";

    if (mysqli_num_rows($dateQuery) > 0) {
        $row  = mysqli_fetch_row($dateQuery);
        $date = implode("", $row);
    }

    $json     = json_decode(file_get_contents(JSON_PATH . "/datasheet.json"));
    $template = "";

    foreach ($json->footer as $line) {
        $template .= $line->$lang ?? "";
    }

    return str_replace(["[data]", "[versao]"], [$date, $version], $template);
}
