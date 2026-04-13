<?php

/**
 * Product Characteristics
 *
 * Fetches the specs table that appears in every datasheet.
 * Example rows: "Power: 20W", "IP Rating: IP65", "Beam angle: 38°"
 *
 * Data comes from two databases:
 * - tecit_lampadas  → main specs (características table)
 * - info_nexled_2024 → lens beam and field angles (angulos_lente table)
 *
 * Some rows are overridden with more precise values:
 * - IP rating → replaced with the actual tested IP value
 * - Beam angle → replaced with the value from angulos_lente if available
 * - Field angle → same
 */



/**
 * Returns the beam angle and field angle for a specific family + lens combination.
 *
 * These override the generic values stored in the characteristics table,
 * since angles vary per lens and the main table may have a default.
 *
 * @param  string $family  Product family code (e.g. "11")
 * @param  string $lens    Lens code from the reference (e.g. "1")
 * @return array  Keys: "beam" and "field", both nullable strings
 */
function getLensAngles(string $family, string $lens): array {
    if (!canReadInfoLensAngles()) {
        return ["beam" => null, "field" => null];
    }

    $con = connectDBInf();

    $queryBeam  = mysqli_query($con, "SELECT beam FROM angulos_lente WHERE familia = '$family' AND lente = '$lens'");
    $queryField = mysqli_query($con, "SELECT field FROM angulos_lente WHERE familia = '$family' AND lente = '$lens'");

    closeDB($con);

    $beam  = mysqli_num_rows($queryBeam)  > 0 ? mysqli_fetch_assoc($queryBeam)["beam"]   : null;
    $field = mysqli_num_rows($queryField) > 0 ? mysqli_fetch_assoc($queryField)["field"] : null;

    return ["beam" => $beam, "field" => $field];
}

function canReadInfoLensAngles(): bool {
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    if (
        !function_exists("probeRuntimeDatabase")
        || !function_exists("getRuntimeDatabaseName")
        || !function_exists("hasRuntimeDatabaseConfig")
        || !hasRuntimeDatabaseConfig()
    ) {
        $available = true;
        return $available;
    }

    $probe = probeRuntimeDatabase(
        getRuntimeDatabaseName(["INF_DB_NAME", "DB_NAME_INF"], ["info_nexled_2024"]),
        ["DB_USER_INF"],
        ["DB_PASS_INF", "MYSQLPASSWORD"],
        [
            [["MYSQLUSER"], ["MYSQLPASSWORD"]],
            [["DB_USER_LAMP"], ["DB_PASS_LAMP"]],
            [["DB_USER_REF"], ["DB_PASS_REF"]],
        ]
    );

    $available = !empty($probe["ok"]);

    if (!$available) {
        error_log("NexLed datasheet: info_nexled_2024 unavailable, using fallback lens angles.");
    }

    return $available;
}



/**
 * Fetches and returns the product characteristics as a label → value array.
 *
 * Rows are fetched in display order (by `indice`).
 * Internal rows (data, version, dimensions, ID-prefixed) are excluded.
 *
 * For multilanguage: uses the translated label/value if available,
 * falls back to Portuguese if the translation is missing.
 *
 * Three rows get special treatment:
 * - "Grau de protecção" / "Grau de proteção" → value replaced with $ipRating
 * - "Feixe de luz" (beam angle) → value replaced with angulos_lente data
 * - "Abertura de luz" (field angle) → same
 *
 * @param  string $productId  Internal product ID (e.g. "48/recessed/01")
 * @param  string $ipRating   Actual IP rating string (e.g. "IP65")
 * @param  string $family     Product family code
 * @param  string $lens       Lens code from the reference
 * @param  string $lang       Language code ("pt", "en", "es")
 * @return array|null  Ordered associative array of label → value pairs, or null if not found
 */
function getCharacteristics(string $productId, string $ipRating, string $family, string $lens, string $lang): ?array {
    // The first segment of the product ID is used to filter out ID-prefixed rows
    $idPrefix = explode("/", $productId)[0];

    $con = connectDBLampadas();

    $query = mysqli_query($con,
        "SELECT texto_pt, valor_pt, texto_$lang, valor_$lang
         FROM caracteristicas
         WHERE ID = '$productId'
           AND texto_pt NOT LIKE 'data'
           AND texto_pt NOT LIKE 'versao'
           AND texto_pt NOT LIKE 'Dimensões%'
           AND texto_pt NOT LIKE '$idPrefix%'
         ORDER BY indice ASC"
    );

    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $angles = getLensAngles($family, $lens);

    // Portuguese label constants used to identify rows that need special handling.
    // These are the database keys — always in PT regardless of output language.
    $ipLabels    = ["Grau de protecção", "Grau de proteção"];
    $beamLabel   = "Feixe de luz";
    $fieldLabel  = "Abertura de luz";

    $result = [];

    while ($row = mysqli_fetch_assoc($query)) {
        $ptLabel = strval($row["texto_pt"]);

        // Use translated label if available, fall back to Portuguese
        $label = ($row["texto_$lang"] !== null && $row["texto_$lang"] !== "")
            ? strval($row["texto_$lang"])
            : $ptLabel;

        // Use translated value if available, fall back to Portuguese
        $value = ($row["valor_$lang"] !== null && $row["valor_$lang"] !== "")
            ? strval($row["valor_$lang"])
            : strval($row["valor_pt"]);

        // Override: IP rating
        if (in_array($ptLabel, $ipLabels)) {
            $value = $ipRating;
        }

        // Override: beam angle (from angulos_lente table)
        if ($ptLabel === $beamLabel && $angles["beam"] !== null) {
            $value = strval($angles["beam"]);
        }

        // Override: field angle (from angulos_lente table)
        if ($ptLabel === $fieldLabel && $angles["field"] !== null) {
            $value = strval($angles["field"]);
        }

        $result[$label] = $value;
    }

    return $result;
}



/**
 * Fetches the IP rating for a product.
 *
 * If the user selected "auto" (value "0"), it reads the IP from the
 * characteristics table in the database.
 * Otherwise it uses the value the user explicitly chose.
 *
 * @param  string $productId    Internal product ID
 * @param  string $selectedIp  IP value from the form ("0" = auto-detect)
 * @return string|null  IP rating string (e.g. "IP65"), or null if not found
 */
function getIpRating(string $productId, string $selectedIp): ?string {
    if ($selectedIp !== "0") {
        return $selectedIp;
    }

    $con = connectDBLampadas();

    $query = mysqli_query($con,
        "SELECT valor_pt FROM caracteristicas
         WHERE ID = '$productId'
           AND (texto_pt = 'Grau de protecção' OR texto_pt = 'Grau de proteção')"
    );

    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_row($query);
    return str_replace(" ", "", implode("", $row));
}
