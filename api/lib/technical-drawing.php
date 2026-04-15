<?php

/**
 * Technical Drawing
 *
 * Returns the drawing image and dimension labels (A through J)
 * for the technical drawing section of the datasheet.
 *
 * Barra products: dimensions are CALCULATED from the sizes JSON file
 * and user selections (cable length, connectors, gaskets, etc.)
 *
 * Downlight / Dynamic: dimensions are READ from the database
 * (stored as a string like "A:145 B:72 C:70" in the características table)
 *
 * findImage() comes from images.php
 */

// Base path to sizes JSON files for bar products
define("SIZES_JSON_PATH", dirname(__FILE__, 3) . "/appdatasheets/json/tamanhos");

function getEmptyBarSizesDefinition(): object {
    return (object) [
        "tampa" => (object) [],
        "barra" => (object) [],
        "caps" => [],
        "conectorcabo" => (object) [],
    ];
}

function loadBarSizesDefinition(?string $sizesFile, string $reference): object {
    if ($sizesFile === null || $sizesFile === "") {
        error_log("NexLed datasheet: missing bar sizes file mapping for reference {$reference}");
        return getEmptyBarSizesDefinition();
    }

    $jsonPath = SIZES_JSON_PATH . "/{$sizesFile}.json";

    if (!is_file($jsonPath)) {
        error_log("NexLed datasheet: bar sizes file not found at {$jsonPath} for reference {$reference}");
        return getEmptyBarSizesDefinition();
    }

    $decoded = json_decode((string) file_get_contents($jsonPath));

    if (!is_object($decoded)) {
        error_log("NexLed datasheet: invalid bar sizes JSON at {$jsonPath} for reference {$reference}");
        return getEmptyBarSizesDefinition();
    }

    if (!isset($decoded->tampa) || !is_object($decoded->tampa)) {
        $decoded->tampa = (object) [];
    }

    if (!isset($decoded->barra) || !is_object($decoded->barra)) {
        $decoded->barra = (object) [];
    }

    if (!isset($decoded->caps) || !is_array($decoded->caps)) {
        $decoded->caps = [];
    }

    if (!isset($decoded->conectorcabo) || !is_object($decoded->conectorcabo)) {
        $decoded->conectorcabo = (object) [];
    }

    return $decoded;
}



/**
 * Returns the technical drawing and dimensions for a BAR product.
 *
 * Bar dimensions depend on: the PCB size, end cap type, connector cable,
 * gaskets (vedantes), and any length add-ons (acrescimo).
 *
 * Dimension labels on the drawing:
 *   A = total length
 *   B = bar height
 *   C = bar width
 *   D = end cap height
 *   E = end cap width
 *   F = cable length (or connector height if no cable)
 *   G = cable connector height (or connector width)
 *   H = cable connector width
 *   I = bar connector height (when cap has both cable + bar connector)
 *   J = bar connector width
 *
 * @param  string $reference    Full product reference
 * @param  string|null $sizesFile  Sizes JSON filename key (e.g. "barras", "barras_bt")
 * @param  array  $config       User selections: extra_length, option, cable_length,
 *                              connector_cable, end_cap, gasket, cable_type
 * @return array  Keys: drawing (path|null), A–J (dimension values, "0" means not shown)
 */
function getBarDrawing(string $reference, ?string $sizesFile, array $config): array {
    $config = normalizeBarAssetConfig($reference, $config);

    $parts          = decodeReference($reference);
    $family         = $parts["family"];
    $pcbSize        = ltrim($parts["size"], "0"); // strip leading zeros (e.g. "0375" → "375")
    $lens           = $parts["lens"];
    $cap            = $parts["cap"];

    $extraLength    = $config["extra_length"];
    $option         = $config["option"];
    $cableLength    = $config["cable_length"];
    $connectorCable = $config["connector_cable"];
    $endCap         = $config["end_cap"];
    $gasket         = $config["gasket"];

    $sizes = loadBarSizesDefinition($sizesFile, $reference);

    // --- Find the drawing image ---
    $folder = "/img/$family/desenhos/";
    $candidates = [
        "{$cap}_{$connectorCable}_{$endCap}",
        "{$cap}_{$endCap}",
        "{$connectorCable}_{$endCap}",
        $cap,
    ];

    $drawing = null;
    foreach ($candidates as $name) {
        $drawing = findImage(IMAGES_BASE_PATH . $folder . $name);
        if ($drawing !== null) break;
    }

    // --- Read end cap dimensions from sizes JSON ---
    $endCapLength = 0;
    $endCapHeight = 0;
    $endCapWidth  = 0;

    if (isset($sizes->tampa->$endCap)) {
        $t = $sizes->tampa->$endCap;

        if (isset($t->comprimento) && $t->comprimento !== "") {
            $endCapLength = floatval($t->comprimento);
        }
        if (isset($t->largura) && $t->largura !== "") {
            $endCapWidth = floatval($t->largura);
        }
        // Height depends on the lens code
        if (isset($t->altura->$lens) && $t->altura->$lens !== "") {
            $endCapHeight = floatval($t->altura->$lens);
        }
    }

    // --- Read bar cross-section dimensions ---
    $barHeight = 0;
    $barWidth  = 0;

    if (isset($sizes->barra)) {
        if (isset($sizes->barra->largura) && $sizes->barra->largura !== "") {
            $barWidth = floatval($sizes->barra->largura);
        }
        if (isset($sizes->barra->altura->$lens) && $sizes->barra->altura->$lens !== "") {
            $barHeight = floatval($sizes->barra->altura->$lens);
        }
    }

    // --- Read cap connector dimensions ---
    $numEndCaps           = 2;   // default: 2 end caps (one each side)
    $numGaskets           = 2;   // default: 2 gaskets
    $barConnectorLength   = 0;
    $barConnectorHeight   = 0;
    $barConnectorWidth    = 0;

    if (isset($sizes->caps)) {
        foreach ($sizes->caps as $capEntry) {
            if (!in_array($cap, (array) $capEntry->cap)) continue;

            if (isset($capEntry->tampas))   $numEndCaps         = intval($capEntry->tampas);
            if (isset($capEntry->vedantes)) $numGaskets         = intval($capEntry->vedantes);
            if (isset($capEntry->comprimento)) $barConnectorLength = floatval($capEntry->comprimento);
            if (isset($capEntry->altura) && $capEntry->altura !== "") $barConnectorHeight = floatval($capEntry->altura);
            if (isset($capEntry->largura) && $capEntry->largura !== "") $barConnectorWidth  = floatval($capEntry->largura);
            break;
        }
    }

    // --- Read cable connector dimensions ---
    $cableConnectorHeight = 0;
    $cableConnectorWidth  = 0;

    if ($connectorCable !== "0" && isset($sizes->conectorcabo->$connectorCable)) {
        $cc = $sizes->conectorcabo->$connectorCable;
        $cableConnectorHeight = floatval($cc->altura);
        $cableConnectorWidth  = floatval($cc->largura);
    }

    // When 2 end caps and no option selected, default cable length to 1.5m
    if ($numEndCaps === 2 && $cableLength === "0" && $option === "0") {
        $cableLength = 1.5;
    }

    // --- Calculate all dimensions ---
    $dims = array_fill_keys(["A","B","C","D","E","F","G","H","I","J"], "0");

    $dims["A"] = strval(round(
        $pcbSize
        + ($endCapLength * $numEndCaps)
        + ($gasket * $numGaskets)
        + $barConnectorLength
        + floatval($extraLength)
    ));

    $dims["B"] = strval($barHeight);
    $dims["C"] = strval($barWidth);

    if ($numEndCaps === 2) {
        // Standard: 2 end caps + cable
        $dims["D"] = strval($endCapHeight);
        $dims["E"] = strval($endCapWidth);
        if ($cableLength !== "" && $cableLength !== "0") {
            $dims["F"] = strval(floatval($cableLength) * 1000);
        }
        if ($cableConnectorHeight !== 0) $dims["G"] = strval($cableConnectorHeight);
        if ($cableConnectorWidth  !== 0) $dims["H"] = strval($cableConnectorWidth);

    } elseif ($numEndCaps === 1) {
        // One end cap — may have cable + bar connector, or just bar connector
        $dims["D"] = strval($endCapHeight);
        $dims["E"] = strval($endCapWidth);

        if ($cableLength !== "" && $cableLength !== "0") {
            $dims["F"] = strval(floatval($cableLength) * 1000);
            if ($cableConnectorHeight !== 0) $dims["G"] = strval($cableConnectorHeight);
            if ($cableConnectorWidth  !== 0) $dims["H"] = strval($cableConnectorWidth);
            if ($barConnectorHeight   !== 0) $dims["I"] = strval($barConnectorHeight);
            if ($barConnectorWidth    !== 0) $dims["J"] = strval($barConnectorWidth);
        } else {
            if ($barConnectorHeight !== 0) $dims["F"] = strval($barConnectorHeight);
            if ($barConnectorWidth  !== 0) $dims["G"] = strval($barConnectorWidth);
        }

    } elseif ($numEndCaps === 0) {
        // No end caps — both sides have bar connectors
        $dims["D"] = strval($barConnectorHeight);
        $dims["E"] = strval($barConnectorWidth);
    }

    return array_merge(["drawing" => $drawing], $dims);
}



/**
 * Returns the technical drawing and dimensions for DOWNLIGHT or DYNAMIC products.
 *
 * For these types, dimensions come directly from the database
 * (stored as "A:145 B:72 C:70" in the características table).
 *
 * Dynamic products have a subtype inside their product ID (e.g. "48/recessed/01").
 *
 * @param  string $reference  Full product reference
 * @param  string $productId  Internal product ID
 * @return array  Keys: drawing (path|null), A–J (dimension values, "0" means not shown)
 */
function getStandardDrawing(string $reference, string $productId): array {
    $parts  = decodeReference($reference);
    $family = $parts["family"];
    $size   = $parts["size"];

    // Dynamic uses a subtype subfolder
    if ($family === "48") {
        $subtype = explode("/", $productId)[1];
        $folder  = "/img/$family/$subtype/desenhos/";
    } else {
        $folder = "/img/$family/desenhos/";
    }

    $drawing = findImage(IMAGES_BASE_PATH . $folder . $size);

    // Read dimensions string from database: "A:145 B:72 C:70"
    $con   = connectDBLampadas();
    $query = mysqli_query($con,
        "SELECT valor_pt FROM caracteristicas
         WHERE ID = '$productId' AND texto_pt LIKE 'Dimensões%'"
    );
    closeDB($con);

    $dims = array_fill_keys(["A","B","C","D","E","F","G","H","I","J"], "0");

    if (mysqli_num_rows($query) > 0) {
        $row    = mysqli_fetch_assoc($query);
        $tokens = explode(" ", $row["valor_pt"]);

        foreach ($tokens as $token) {
            $pair = explode(":", $token);
            if (!empty($pair[0]) && !empty($pair[1])) {
                $dims[$pair[0]] = $pair[1];
            }
        }
    }

    return array_merge(["drawing" => $drawing], $dims);
}



/**
 * Master function — returns the correct drawing data based on product type.
 *
 * @param  string      $productType  "barra", "downlight", or "dynamic"
 * @param  string      $reference    Full product reference
 * @param  string      $productId    Internal product ID
 * @param  string|null $sizesFile    Sizes JSON key for bar products (from getBarSizesFile())
 * @param  array       $config       User selections (needed for bar drawing calculation)
 * @return array  Keys: drawing (path|null), A–J dimension values
 */
function getTechnicalDrawing(string $productType, string $reference, string $productId, ?string $sizesFile, array $config): array {
    if ($productType === "barra") {
        return getBarDrawing($reference, $sizesFile, $config);
    }

    return getStandardDrawing($reference, $productId);
}
