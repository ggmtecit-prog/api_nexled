<?php

/**
 * Reference Code Decoder
 *
 * Live Tecit format used by configurator and product lookup:
 * [Family 2][Size 4][Color 2][CRI 1][Series 1][Lens 1][Finish 2][Cap 2][Option 2]
 *
 * Example:
 * 11 0375 81 1 1 0 01 01 00
 * 11037581110010100
 */

require_once dirname(__FILE__) . "/family-registry.php";

const REFERENCE_LENGTH_FAMILY = 2;
const REFERENCE_LENGTH_SIZE = 4;
const REFERENCE_LENGTH_COLOR = 2;
const REFERENCE_LENGTH_CRI = 1;
const REFERENCE_LENGTH_SERIES = 1;
const REFERENCE_LENGTH_LENS = 1;
const REFERENCE_LENGTH_FINISH = 2;
const REFERENCE_LENGTH_CAP = 2;
const REFERENCE_LENGTH_OPTION = 2;
const REFERENCE_LENGTH_IDENTITY = 10;
const REFERENCE_LENGTH_FULL = 17;



/**
 * Returns first 10 chars used by Luminos identity lookup.
 *
 * Structure:
 * [Family 2][Size 4][Color 2][CRI 1][Series 1]
 *
 * @param  string $reference  Full product reference code
 * @return string  Identity fragment used by the database lookup
 */
function getReferenceIdentity(string $reference): string {
    return substr($reference, 0, REFERENCE_LENGTH_IDENTITY);
}



/**
 * Returns whether a reference has the expected full live length.
 *
 * @param  string $reference  Full product reference code
 * @return bool  True when the code matches the live 17-char format
 */
function hasFullReferenceLength(string $reference): bool {
    return strlen($reference) === REFERENCE_LENGTH_FULL;
}



/**
 * Splits a reference code into its individual parts.
 *
 * @param  string $reference  Full product reference code (e.g. "11037581110010100")
 * @return array  Associative array with normalized live-format keys
 */
function decodeReference(string $reference): array {
    return [
        "raw"          => $reference,
        "length"       => strlen($reference),
        "identity"     => getReferenceIdentity($reference),
        "family"       => substr($reference, 0, REFERENCE_LENGTH_FAMILY),
        "size"         => substr($reference, REFERENCE_LENGTH_FAMILY, REFERENCE_LENGTH_SIZE),
        "color"        => substr($reference, 6, REFERENCE_LENGTH_COLOR),
        "cri"          => substr($reference, 8, REFERENCE_LENGTH_CRI),
        "led_segment"  => substr($reference, 6, REFERENCE_LENGTH_COLOR + REFERENCE_LENGTH_CRI),
        "series"       => substr($reference, 9, REFERENCE_LENGTH_SERIES),
        "lens"         => substr($reference, 10, REFERENCE_LENGTH_LENS),
        "finish"       => substr($reference, 11, REFERENCE_LENGTH_FINISH),
        "cap"          => substr($reference, 13, REFERENCE_LENGTH_CAP),
        "option"       => substr($reference, 15, REFERENCE_LENGTH_OPTION),
    ];
}



/**
 * Returns the product type for a given reference code.
 *
 * Product types control which images, drawings, and PDF sections are used.
 * The mapping lives in correspondenciaProdutos.json (reference only).
 *
 * Known runtime classes include:
 * "barra", "downlight", "dynamic", "shelf", "tubular",
 * plus unsupported-but-recognized classes such as "spot", "panel", etc.
 *
 * @param  string $reference  Full product reference code
 * @return string|null  Product type, or null if the family is not mapped
 */
function getProductType(string $reference): ?string {
    $parts = decodeReference($reference);
    return getFamilyRegistryProductType($parts["family"]);
}

function isDatasheetRuntimeSupported(?string $productType, ?string $familyCode = null): bool {
    if ($familyCode === null || trim($familyCode) === "") {
        return false;
    }

    return isFamilyDatasheetRuntimeSupported($familyCode);
}



/**
 * Returns the sizes JSON filename for bar (barra) products.
 * Different bar subtypes have different dimension files.
 *
 * @param  string $reference  Full product reference code
 * @return string|null  JSON filename key, or null if not a bar product
 */
function getBarSizesFile(string $reference): ?string {
    $parts = decodeReference($reference);
    return getFamilyBarSizesFile($parts["family"]);
}



/**
 * Looks up the product ID for a Dynamic product (family 48).
 *
 * Dynamic products have subtypes (projectors vs pendants) determined
 * by the cap selection: "0" = projector, "1" = pendant.
 *
 * @param  string $reference  Full product reference
 * @param  string $cap        Cap selection from the form
 * @return string|null  Product ID, or null if not found
 */
function getProductIdDynamic(string $reference, string $cap): ?string {
    $ref     = getReferenceIdentity($reference);
    $subtype = ($cap === "1") ? "campanulas" : "projetores";

    $con   = connectDBLampadas();
    $query = mysqli_query($con, "SELECT ID FROM Luminos WHERE ref = '$ref' AND ID LIKE '%$subtype%'");
    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_row($query);
    return $row[0];
}



/**
 * Looks up the product's internal database ID from the Luminos table.
 * This ID is used to fetch characteristics, drawings, and other data.
 *
 * @param  string $reference  Full product reference code
 * @return string|null  Product ID (e.g. "48/recessed/01"), or null if not found
 */
function getProductId(string $reference): ?string {
    $ref = getReferenceIdentity($reference);

    $con = connectDBLampadas();
    $query = mysqli_query($con, "SELECT ID FROM Luminos WHERE ref = '$ref'");
    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_row($query);
    return $row[0];
}

function getFamilyCapDescription(string $familyCode, string $capCode): string {
    if ($familyCode === "" || $capCode === "") {
        return "";
    }

    $con = connectDBReferencias();
    $stmt = mysqli_prepare(
        $con,
        "SELECT Cap.desc
         FROM Cap, Familias
         WHERE Cap.familia = Familias.cap
           AND Familias.codigo = ?
           AND Cap.codigo = ?
         LIMIT 1"
    );

    if (!$stmt) {
        closeDB($con);
        return "";
    }

    $familyInt = intval($familyCode);
    $stmtCapCode = ltrim($capCode, "0");
    if ($stmtCapCode === "") {
        $stmtCapCode = "0";
    }

    mysqli_stmt_bind_param($stmt, "is", $familyInt, $stmtCapCode);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $description = "";

    if ($result && ($row = mysqli_fetch_assoc($result))) {
        $description = (string) ($row["desc"] ?? "");
    }

    mysqli_stmt_close($stmt);
    closeDB($con);

    return $description;
}

function inferFamily32ConnectorCable(string $capDescription, string $fallback = "0"): string {
    $normalized = strtolower(trim($capDescription));

    if ($normalized === "") {
        return $fallback;
    }

    return match (true) {
        str_contains($normalized, "dc24")  => "dc24",
        str_contains($normalized, "dcj")   => "dcj",
        str_contains($normalized, "c1m")   => "c1m",
        str_contains($normalized, "asqc2"),
        str_contains($normalized, "asqc")  => "asqc2",
        default                            => $fallback,
    };
}

function inferFamily32CableType(string $capDescription, string $fallback = "branco"): string {
    $normalized = strtolower(preg_replace("/\s+/", "", $capDescription));

    if ($normalized === "") {
        return $fallback;
    }

    if (str_ends_with($normalized, "p")) {
        return "preto";
    }

    return $fallback;
}

function normalizeBarAssetConfig(string $reference, array $config): array {
    $parts = decodeReference($reference);

    if (($parts["family"] ?? "") !== "32") {
        return $config;
    }

    $connectorCable = strtolower(trim((string) ($config["connector_cable"] ?? "0")));
    $cableType = strtolower(trim((string) ($config["cable_type"] ?? "branco")));

    if ($connectorCable !== "" && $connectorCable !== "0") {
        return $config;
    }

    $capDescription = getFamilyCapDescription($parts["family"], $parts["cap"]);
    $inferredConnector = inferFamily32ConnectorCable($capDescription, $connectorCable === "" ? "0" : $connectorCable);

    if ($inferredConnector === "" || $inferredConnector === "0") {
        return $config;
    }

    $config["connector_cable"] = $inferredConnector;
    $config["cable_type"] = inferFamily32CableType(
        $capDescription,
        $cableType === "" || $cableType === "0" ? "branco" : $cableType
    );

    return $config;
}
