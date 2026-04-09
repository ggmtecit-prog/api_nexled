<?php

/**
 * Reference Code Decoder
 *
 * A product reference is a string where each segment of characters
 * encodes a specific product attribute.
 *
 * Structure: [Family 2][Size 4][Color 3][Series 1][Lens 1][Finish 2][Cap 2][Option 5]
 * Example:    11        0375    811      1         0       01        01     00
 */



/**
 * Splits a reference code into its individual parts.
 *
 * @param  string $reference  Full product reference code (e.g. "11037581110010100")
 * @return array  Associative array with keys: family, size, color, series, lens, finish, cap, option
 */
function decodeReference(string $reference): array {
    return [
        "family"  => substr($reference, 0, 2),   // e.g. "11" = Barra 24V
        "size"    => substr($reference, 2, 4),   // e.g. "0375" = 375mm
        "color"   => substr($reference, 6, 3),   // e.g. "811" or "WWW"
        "series"  => substr($reference, 9, 1),   // e.g. "1"
        "lens"    => substr($reference, 10, 1),  // e.g. "0" = none
        "finish"  => substr($reference, 11, 2),  // e.g. "01" = aluminium
        "cap"     => substr($reference, 13, 2),  // e.g. "01" = standard
        "option"  => substr($reference, 15, 5),  // e.g. "00" = none
    ];
}



/**
 * Returns the product type for a given reference code.
 *
 * Product types control which images, drawings, and PDF sections are used.
 * The mapping lives in correspondenciaProdutos.json (reference only).
 *
 * Known types: "barra", "downlight", "dynamic"
 *
 * @param  string $reference  Full product reference code
 * @return string|null  Product type, or null if the family is not mapped
 */
function getProductType(string $reference): ?string {
    $parts = decodeReference($reference);
    $family = $parts["family"];

    $map = [
        "barra"     => ["11", "55", "58", "32"],
        "downlight" => ["29", "30"],
        "dynamic"   => ["48"],
    ];

    foreach ($map as $type => $families) {
        if (in_array($family, $families)) {
            return $type;
        }
    }

    return null;
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
    $family = $parts["family"];

    $map = [
        "barras"     => ["11", "55"],
        "barras_hot" => ["58"],
        "barras_bt"  => ["32"],
    ];

    foreach ($map as $file => $families) {
        if (in_array($family, $families)) {
            return $file;
        }
    }

    return null;
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
    $ref     = substr($reference, 0, 10);
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
    $ref = substr($reference, 0, 10);

    $con = connectDBLampadas();
    $query = mysqli_query($con, "SELECT ID FROM Luminos WHERE ref = '$ref'");
    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $row = mysqli_fetch_row($query);
    return $row[0];
}
