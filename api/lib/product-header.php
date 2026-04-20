<?php

/**
 * Product Header
 *
 * Builds the data for the top section of every datasheet:
 * - The product photo
 * - The description text block (product + LED + purpose + energy class)
 *
 * Runtime now resolves DAM first for migrated families, with legacy local
 * disk fallback kept only for families still outside DAM rollout.
 * Description texts come from JSON files in api/json/descricao/.
 *
 * Image lookup is fuzzy: several candidate filenames are tried in order,
 * and the first one that exists on disk is used. This mirrors the way
 * products are organised — one image can cover several configurations.
 */

// findImage() lives in images.php — included by the datasheet engine
define("JSON_DESC_PATH", dirname(__FILE__, 2) . "/json/descricao");



/**
 * Returns the product photo path for the header section.
 *
 * Each product type organises its images differently:
 * - Barra:     /img/{family}/produto/{lens}/{series}/ or /img/{family}/produto/{lens}/
 * - Downlight: /img/{family}/produto/
 * - Shelf:     /img/{family}/produto/
 * - Tubular:   /img/{family}/produto/
 * - Dynamic:   /img/{family}/{subtype}/produto/
 *
 * Multiple candidate filenames are tried — the first match wins.
 *
 * @param  string $productType  "barra", "downlight", "shelf", "tubular", or "dynamic"
 * @param  string $productId    Internal product ID (e.g. "48/recessed/01")
 * @param  array  $parts        Decoded reference (from decodeReference())
 * @param  array  $config       User selections: lens, finish, connector_cable, cable_type, end_cap
 * @return string|null  Full image path, or null if no image found
 */
function getProductImage(string $productType, string $productId, array $parts, array $config): ?string {
    if ($productType === "barra") {
        $config = normalizeBarAssetConfig($parts["raw"] ?? "", $config);
    }

    $family  = $parts["family"];
    $size    = $parts["size"];
    $series  = $parts["series"];
    $cap     = $parts["cap"];
    $lens    = strtolower($config["lens"]);
    $finish  = strtolower($config["finish"]);
    $connectorCable = $config["connector_cable"];
    $cableType      = $config["cable_type"];
    $endCap         = $config["end_cap"];

    switch ($productType) {

        case "barra":
            // Barras with "clear" lens use a series-specific subfolder
            $folder = ($lens === "clear")
                ? "/img/$family/produto/$lens/$series/"
                : "/img/$family/produto/$lens/";

            // Try combinations from most specific to least specific
            $candidates = [
                str_replace("+", "_", "{$finish}_{$connectorCable}_{$cableType}_{$endCap}"),
                str_replace("+", "_", "{$finish}_{$cap}"),
            ];

            // Special case: BT bars (family 32) use connector + cable type + end cap only
            if ($family === "32") {
                $candidates = ["{$connectorCable}_{$cableType}_{$endCap}"];
            }

            // Special case: HOT bars (family 58) use finish + end cap only
            if ($family === "58") {
                $candidates = ["{$finish}_{$endCap}"];
            }
            break;

        case "downlight":
            $folder     = "/img/$family/produto/";
            $candidates = ["{$size}_{$lens}"];
            break;

        case "shelf":
            $folder = "/img/$family/produto/";
            $cleanFinish = str_replace("+", "_", $finish);
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}_{$endCap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$size}_{$lens}",
                "{$size}",
            ];
            break;

        case "tubular":
            $folder = "/img/$family/produto/";
            $cleanFinish = str_replace("+", "_", $finish);
            $candidates = [
                "{$size}_{$lens}_{$cleanFinish}_{$cap}",
                "{$size}_{$lens}_{$cleanFinish}",
                "{$size}_{$lens}",
                "{$size}",
            ];
            break;

        case "dynamic":
            $idParts  = explode("/", $productId);
            $subtype  = $idParts[1];
            $folder   = "/img/$family/$subtype/produto/";
            $finish   = str_replace("+", "", $finish);
            $candidates = ["{$size}_{$finish}"];
            break;

        default:
            return null;
    }

    $damImage = findDamProductAsset($family, $productId, "packshot", $candidates);

    if ($damImage === null && $family === "01") {
        $damImage = getTubularFamily01DamPackshot($productId);
    }

    if ($damImage === null && $family === "05") {
        $damImage = getTubularFamily05DamPackshot($parts, $config);
    }

    if ($damImage !== null) {
        return $damImage;
    }

    foreach ($candidates as $filename) {
        $image = findImage(IMAGES_BASE_PATH . $folder . $filename);

        if ($image !== null) {
            return $image;
        }
    }

    return null;
}

/**
 * Resolves family 01 T8 special packshots directly from Cloudinary DAM naming
 * when the DAM metadata DB is unavailable.
 *
 * @param  string $productId
 * @return string|null
 */
function getTubularFamily01DamPackshot(string $productId): ?string {
    $productId = trim($productId);

    if ($productId === "") {
        return null;
    }

    $folderPath = "nexled/datasheet/packshots/generic";

    if (preg_match("#^T8/AL/[0-9]+/2s$#i", $productId) === 1) {
        return cloudinaryDamExactAssetUrl($folderPath, "T8_ECO.png");
    }

    if (preg_match("#^T8PINK/AL/[0-9]+/3s$#i", $productId) === 1) {
        return cloudinaryDamExactAssetUrl($folderPath, "T8_Pink_tecto.png");
    }

    if (preg_match("#^T8PINK/[0-9]+/3s$#i", $productId) === 1) {
        return cloudinaryDamExactAssetUrl($folderPath, "T8_Pink.png");
    }

    return null;
}

/**
 * Resolves family 05 T5 packshots directly from Cloudinary DAM naming when
 * DAM metadata is unavailable.
 *
 * @param  array $parts
 * @param  array $config
 * @return string|null
 */
function getTubularFamily05DamPackshot(array $parts, array $config): ?string {
    $folderPath = "nexled/datasheet/packshots/generic";
    $lens = strtolower(trim((string) ($config["lens"] ?? "")));
    $cap = trim((string) ($parts["cap"] ?? ""));

    $assetName = match (true) {
        $lens === "frost" && $cap === "02" => "T5_Frost_Alu_LB.png",
        $lens === "frost" => "T5_Frost_Alu.png",
        $cap === "02" => "T5_Clear_Alu_LB.png",
        default => "T5_Clear_Alu.png",
    };

    return cloudinaryDamExactAssetUrl($folderPath, $assetName);
}



/**
 * Returns the marketing description paragraphs for a product.
 *
 * The key is derived from the product ID segments:
 * - Regular products: "{id[0]}_{id[1]}_{id[3]}" (e.g. "Barra_24v_3s")
 * - Dynamic products: "{id[0]}_{id[1]}"         (e.g. "48_recessed")
 *
 * @param  string $productId  Internal product ID
 * @param  string $family     Product family code (used to detect dynamic)
 * @param  string $lang       Language code
 * @return string  HTML paragraphs joined with <br><br>, or empty string if not found
 */
function getProductDescriptionText(string $productId, string $family, string $lang): string {
    $json = json_decode(file_get_contents(JSON_DESC_PATH . "/produtos.json"));

    $id = explode("/", $productId);
    $candidateKeys = [];

    if ($family === "48") {
        $candidateKeys[] = $id[0] . "_" . $id[1];
    } else {
        if (isset($id[3]) && $id[3] !== "") {
            $candidateKeys[] = $id[0] . "_" . $id[1] . "_" . $id[3];
        }

        if (isset($id[2]) && $id[2] !== "") {
            $candidateKeys[] = $id[0] . "_" . $id[1] . "_" . $id[2];
        }

        if (isset($id[1]) && $id[1] !== "") {
            $candidateKeys[] = $id[0] . "_" . $id[1];
        }
    }

    $key = null;
    foreach ($candidateKeys as $candidateKey) {
        if (isset($json->descricao->$candidateKey->$lang)) {
            $key = $candidateKey;
            break;
        }
    }

    if ($key === null) {
        return "";
    }

    $lines = [];
    foreach ($json->descricao->$key->$lang as $line) {
        $lines[] = $line;
    }

    return implode("<br><br>", $lines) . "<br><br>";
}



/**
 * Returns the LED description text for a given LED ID.
 *
 * Each LED has a label (e.g. "Warm White 3000K CRI >80").
 * If the LED uses SDCM binning, an additional note is appended.
 * Family-specific SDCM exceptions are checked first.
 *
 * @param  string $ledId   LED identifier from the Luminos table (e.g. "LM301H")
 * @param  string $family  Product family code
 * @param  string $lang    Language code
 * @return string  Description text with <br><br> spacing, or empty string if not found
 */
function getLedDescriptionText(string $ledId, string $family, string $lang): string {
    $json = json_decode(file_get_contents(JSON_DESC_PATH . "/leds.json"));

    $prefix = $json->descricao->$lang ?? "";
    $text   = "";

    foreach ($json->leds as $led) {
        if (!in_array($ledId, (array) $led->led)) {
            continue;
        }

        $text .= $prefix . " " . ($led->$lang ?? "") . "<br><br>";

        // SDCM note — appended when the LED has SDCM binning
        if (isset($led->scdm) && $led->scdm === "1") {
            $sdcmText = "";

            foreach ($json->SDCM->excecoes as $exception) {
                if (in_array($family, (array) $exception->familias)) {
                    $sdcmText = ($exception->$lang ?? "") . "<br><br>";
                    break;
                }
            }

            if ($sdcmText === "") {
                $sdcmText = ($json->SDCM->default->$lang ?? "") . "<br><br>";
            }

            $text .= $sdcmText;
        }

        break;
    }

    return $text;
}



/**
 * Returns the purpose description text (e.g. food safety, medical, etc.)
 * Only used when a specific purpose was selected (not "0").
 *
 * @param  string $purposeId  Purpose code from the form (e.g. "1", "2")
 * @param  string $lang       Language code
 * @return string  Description text, or empty string if not found or not applicable
 */
function getPurposeText(string $purposeId, string $lang): string {
    if ($purposeId === "0") {
        return "";
    }

    $json = json_decode(file_get_contents(JSON_DESC_PATH . "/finalidades.json"));

    if (!isset($json->descricao->$purposeId)) {
        return "";
    }

    $entry = $json->descricao->$purposeId;
    $text  = "";

    if (isset($entry->$lang)) {
        $text .= $entry->$lang . "<br><br>";
    }

    // Some purposes include an additional food safety note
    if (isset($entry->safetyfood) && $entry->safetyfood === "1") {
        $text .= ($json->descricao->safetyfood->$lang ?? "") . "<br><br>";
    }

    return $text;
}



/**
 * Returns the standard energy class description text.
 * This is the same boilerplate paragraph that appears on every datasheet.
 *
 * @param  string $lang  Language code
 * @return string  Description text, or empty string if not found
 */
function getEnergyClassText(string $lang): string {
    $json = json_decode(file_get_contents(JSON_DESC_PATH . "/classe.json"));
    return isset($json->classe->$lang) ? $json->classe->$lang . "<br><br>" : "";
}



/**
 * Master function — returns all data needed to render the header section.
 *
 * @param  string $productType  "barra", "downlight", or "dynamic"
 * @param  string $productId    Internal product ID
 * @param  string $reference    Full product reference code
 * @param  string $ledId        LED identifier from luminotechnical data
 * @param  array  $config       User selections: lens, finish, connector_cable, cable_type, end_cap, purpose, lang
 * @return array  Keys: image (path|null), description (HTML string)
 *                Missing image returns null — the PDF builder decides how to handle it
 */
function getProductHeader(string $productType, string $productId, string $reference, string $ledId, array $config): array {
    $parts = decodeReference($reference);
    $lang  = $config["lang"];

    $description =
        getProductDescriptionText($productId, $parts["family"], $lang) .
        getLedDescriptionText($ledId, $parts["family"], $lang) .
        getPurposeText($config["purpose"], $lang) .
        getEnergyClassText($lang);

    return [
        "image"       => getProductImage($productType, $productId, $parts, $config),
        "description" => $description,
    ];
}
