<?php

/**
 * Luminotechnical Data
 *
 * Handles all light-related calculations for the datasheet:
 * - Fetching raw data from the Luminos database view
 * - Calculating luminous flux, efficacy, and energy class
 * - Determining the color temperature label (Warm / Neutral / Cool)
 *
 * All values come from the tecit_lampadas database (Luminos view).
 */



// Energy class thresholds (efficacy in Lm/W)
// Source: EU Ecodesign regulation
const ENERGY_CLASSES = [
    ["min" => 210,              "class" => "A"],
    ["min" => 185, "max" => 209, "class" => "B"],
    ["min" => 160, "max" => 184, "class" => "C"],
    ["min" => 135, "max" => 159, "class" => "D"],
    ["min" => 110, "max" => 134, "class" => "E"],
    ["min" => 85,  "max" => 109, "class" => "F"],
    ["min" => 0,   "max" => 84,  "class" => "G"],
];

// Light source correction factors for energy class calculation
const LIGHT_SOURCE_FACTORS = [
    "NDLS_MLS"  => 1.0,
    "NDLS_NMLS" => 0.926,
    "DLS_MLS"   => 1.176,
    "DLS_NMLS"  => 1.089,
];

// Color temperature ranges and their labels per language
const COLOR_TEMPERATURE_RANGES = [
    ["min" => 5000,               "pt" => "Frio",   "en" => "Cool",    "es" => "Frio"],
    ["min" => 3500, "max" => 4999, "pt" => "Neutro", "en" => "Neutral", "es" => "Neutro"],
    ["min" => 0,    "max" => 3499, "pt" => "Quente", "en" => "Warm",    "es" => "Caliente"],
];



/**
 * Fetches the raw luminotechnical row from the Luminos database view.
 *
 * The Luminos view joins LED data with product reference data.
 * It contains the raw numbers used for all light calculations.
 *
 * @param  string $productId  Internal product ID (e.g. "48/recessed/01")
 * @param  string $reference  Full product reference code
 * @return array|null  Raw row data, or null if not found
 */
function fetchLuminousData(string $productId, string $reference): ?array {
    $ref = substr($reference, 0, 10);

    $con = connectDBLampadas();
    $query = mysqli_query($con, "SELECT * FROM Luminos WHERE ID LIKE '$productId' AND ref LIKE '$ref%'");
    closeDB($con);

    if (mysqli_num_rows($query) === 0) {
        return null;
    }

    $data = [];
    while ($row = mysqli_fetch_assoc($query)) {
        foreach ($row as $key => $value) {
            $data[$key] = $value;
        }
    }

    return $data;
}



/**
 * Calculates the luminous flux (in lumens) for a product.
 *
 * Two formulas exist depending on whether the relative flux factor is used:
 * - Standard:  nleds × lumens × rel_flux × (1 - thermal_loss) × (1 - lens_loss)
 * - Override:  nleds × lumens × (current / series / rated_current) × (1 - thermal_loss) × (1 - lens_loss)
 *
 * @param  array  $data  Raw data from fetchLuminousData()
 * @param  string $lens  Lens code from the reference (e.g. "0", "1", "2")
 * @return int  Rounded luminous flux in lumens
 */
function calculateFlux(array $data, string $lens): int {
    $lensLoss = floatval($data["A" . $lens]);
    $thermalLoss = floatval($data["att_temp"]);

    if (strval($data["rel_flux"]) !== "1.000" || empty($data["rel_flux"])) {
        $flux = $data["nleds"] * $data["lumens"] * $data["rel_flux"]
              * (1 - $thermalLoss)
              * (1 - $lensLoss);
    } else {
        $flux = $data["nleds"] * $data["lumens"]
              * ($data["corrente"] / $data["serie"] / $data["correntelumens"])
              * (1 - $thermalLoss)
              * (1 - $lensLoss);
    }

    return (int) round($flux);
}



/**
 * Calculates the luminous efficacy (lumens per watt).
 *
 * Family 31 is a special case — it uses 3 channels, so power is divided by 3.
 *
 * @param  int    $flux    Luminous flux in lumens
 * @param  float  $power   Power consumption in watts
 * @param  string $family  Product family code (e.g. "11", "31")
 * @return int  Rounded efficacy in Lm/W
 */
function calculateEfficacy(int $flux, float $power, string $family): int {
    if ($family === "31") {
        return (int) round($flux / ($power / 3));
    }

    return (int) round($flux / $power);
}



/**
 * Determines the energy class letter (A through G) for a product.
 *
 * The class is based on the corrected efficacy value, which uses a
 * light source factor to account for different LED technologies.
 *
 * @param  int    $flux        Luminous flux in lumens
 * @param  float  $power       Power consumption in watts
 * @param  string $lightSource Light source type key (from Luminos.fonteluz)
 * @return string  Energy class letter ("A" through "G")
 */
function getEnergyClass(int $flux, float $power, string $lightSource): string {
    $factor = LIGHT_SOURCE_FACTORS[$lightSource] ?? 1.0;
    $correctedEfficacy = (int) round(($flux / $power) * $factor);

    foreach (ENERGY_CLASSES as $range) {
        $aboveMin = $correctedEfficacy >= $range["min"];
        $belowMax = !isset($range["max"]) || $correctedEfficacy <= $range["max"];

        if ($aboveMin && $belowMax) {
            return $range["class"];
        }
    }

    return "G"; // fallback
}



/**
 * Returns the color temperature label (Warm / Neutral / Cool) for a CCT value.
 *
 * @param  string $cct   Color temperature string from the database (e.g. "3000 K")
 * @param  string $lang  Language code ("pt", "en", "es")
 * @return string  Label in the requested language, or "-" if CCT is not a number
 */
function getColorTemperatureLabel(string $cct, string $lang): string {
    $kelvin = intval(explode(" ", $cct)[0]);

    if ($kelvin <= 0) {
        return "-";
    }

    foreach (COLOR_TEMPERATURE_RANGES as $range) {
        $aboveMin = $kelvin >= $range["min"];
        $belowMax = !isset($range["max"]) || $kelvin <= $range["max"];

        if ($aboveMin && $belowMax) {
            return $range[$lang] ?? $range["en"];
        }
    }

    return "-";
}



/**
 * Master function — returns all computed luminotechnical values for a product.
 *
 * This is what the PDF builder calls. It fetches the raw data, runs all
 * calculations, and returns a clean array ready to display.
 *
 * @param  string $productId  Internal product ID
 * @param  string $reference  Full product reference code
 * @param  string $lang       Language code ("pt", "en", "es")
 * @return array|null  Array with keys: flux, efficacy, energy_class, cct, color_label, cri, led_id
 *                     Returns null if data not found in the database
 */
function getLuminotechnicalData(string $productId, string $reference, string $lang): ?array {
    $data = fetchLuminousData($productId, $reference);

    if ($data === null) {
        return null;
    }

    $parts   = decodeReference($reference);
    $flux    = calculateFlux($data, $parts["lens"]);
    $power   = floatval($data["potencia"]);

    return [
        "flux"         => $flux,
        "efficacy"     => calculateEfficacy($flux, $power, $parts["family"]),
        "energy_class" => getEnergyClass($flux, $power, strval($data["fonteluz"])),
        "cct"          => $data["cct"],
        "color_label"  => getColorTemperatureLabel(strval($data["cct"]), $lang),
        "cri"          => $data["cri"],
        "led_id"       => $data["ID_Led"],
    ];
}
