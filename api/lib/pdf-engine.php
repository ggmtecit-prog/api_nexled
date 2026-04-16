<?php

/**
 * PDF Engine
 *
 * The orchestrator. Reads POST JSON, calls all data fetchers,
 * builds the HTML layout, and outputs a PDF via TCPDF.
 *
 * Replaces the old gerarDatasheet.php + estruturaDatasheet.php pair.
 * The NEXLEDPDF class (header/footer) still lives in appdatasheets/tcpdf/classes.php.
 *
 * Expected POST JSON fields:
 *   referencia, descricao, idioma, empresa,
 *   lente, acabamento, opcao, conectorcabo, tipocabo, tampa,
 *   vedante, acrescimo, ip, fixacao, fonte,
 *   caboligacao, conectorligacao, tamanhocaboligacao, finalidade
 */

define("TCPDF_PATH",   dirname(__FILE__, 3) . "/appdatasheets/tcpdf/");
define("APP_CSS_PATH", dirname(__FILE__, 3) . "/appdatasheets/style/datasheet.css");

require_once TCPDF_PATH . "tcpdf_include.php";
require_once TCPDF_PATH . "tcpdf.php";
require_once TCPDF_PATH . "classes.php";

require_once dirname(__FILE__) . "/images.php";
require_once dirname(__FILE__) . "/reference-decoder.php";
require_once dirname(__FILE__) . "/luminotechnical.php";
require_once dirname(__FILE__) . "/characteristics.php";
require_once dirname(__FILE__) . "/product-header.php";
require_once dirname(__FILE__) . "/technical-drawing.php";
require_once dirname(__FILE__) . "/sections.php";
require_once dirname(__FILE__) . "/pdf-layout.php";



// ---------------------------------------------------------------------------
// TCPDF BRIDGE
// ---------------------------------------------------------------------------

/**
 * Bridge for NEXLEDPDF::Footer() — it calls criarFooter() by name.
 * We delegate to our new getFooter() from sections.php so the old
 * NEXLEDPDF class doesn't need to be modified.
 *
 * @param  string $productId  Internal product ID
 * @param  string $lang       Language code
 * @return string  Footer text with date and version substituted in
 */
function criarFooter(string $productId, string $lang): string {
    return getFooter($productId, $lang);
}



// ---------------------------------------------------------------------------
// LENS NAME LOOKUP
// ---------------------------------------------------------------------------

/**
 * Returns the translated lens name for display on the datasheet.
 * Queries the Acrilico table in tecit_referencias.
 * Falls back to the raw form value if nothing is found.
 *
 * @param  string $reference  Full product reference
 * @param  string $lens       Lens display value from the form (e.g. "Clear")
 * @param  string $lang       Language code
 * @return string  Translated lens name
 */
function getLensName(string $reference, string $lens, string $lang): string {
    $parts    = decodeReference($reference);
    $family   = $parts["family"];
    $lensCode = $parts["lens"];

    $con   = connectDBReferencias();
    $query = mysqli_query($con,
        "SELECT Acrilico.$lang
         FROM Acrilico, Familias
         WHERE Familias.codigo = '$family'
           AND Acrilico.familia = Familias.acrilico
           AND Acrilico.codigo = '$lensCode'
           AND Acrilico.$lang != ''"
    );
    closeDB($con);

    if (mysqli_num_rows($query) > 0) {
        $row = mysqli_fetch_row($query);
        return implode("", $row);
    }

    return $lens;
}

function respondDatasheetJsonError(int $statusCode, array $payload): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header("Content-Type: application/json");
    }

    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function validateStrictShelfCompleteness(array $data, array $parts): ?string {
    $headerImage = $data["header"]["image"] ?? null;
    if (!is_string($headerImage) || trim($headerImage) === "") {
        return "Missing required data: product image";
    }

    $drawingImage = $data["drawing"]["drawing"] ?? null;
    if (!is_string($drawingImage) || trim($drawingImage) === "") {
        return "Missing required data: technical drawing";
    }

    if (($data["color_graph"] ?? null) === null) {
        return "Missing required data: color graph";
    }

    if (($parts["lens"] ?? "") !== "0" && ($data["lens_diagram"] ?? null) === null) {
        return "Missing required data: lens diagram";
    }

    $finishImage = $data["finish"]["image"] ?? null;
    if (!is_string($finishImage) || trim($finishImage) === "" || isFinishPlaceholderImage($finishImage)) {
        return "Missing required data: finish image";
    }

    return null;
}

function validateStrictTubularCompleteness(array $data, array $parts): ?string {
    $headerImage = $data["header"]["image"] ?? null;
    if (!is_string($headerImage) || trim($headerImage) === "") {
        return "Missing required data: product image";
    }

    $drawingImage = $data["drawing"]["drawing"] ?? null;
    if (!is_string($drawingImage) || trim($drawingImage) === "") {
        return "Missing required data: technical drawing";
    }

    if (($data["color_graph"] ?? null) === null) {
        return "Missing required data: color graph";
    }

    if (($parts["lens"] ?? "") !== "0" && ($data["lens_diagram"] ?? null) === null) {
        return "Missing required data: lens diagram";
    }

    $finishImage = $data["finish"]["image"] ?? null;
    if (!is_string($finishImage) || trim($finishImage) === "" || isFinishPlaceholderImage($finishImage)) {
        return "Missing required data: finish image";
    }

    return null;
}



// ---------------------------------------------------------------------------
// ENGINE
// ---------------------------------------------------------------------------

/**
 * Generates a PDF datasheet from a POST JSON request.
 *
 * Outputs the PDF directly (Content-Type: application/pdf).
 * Returns a JSON error (with the appropriate HTTP status) if any
 * required data is missing or the product cannot be found.
 */
function generateDatasheet(): void {
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        respondDatasheetJsonError(400, ["error" => "Invalid or missing JSON body"]);
        return;
    }

    $reference = "";
    $productId = "";
    $stage = "parse_input";

    try {

    // --- Parse and validate inputs ---
    $reference      = validateReference($input["referencia"]         ?? "");
    $lang           = validateLang($input["idioma"]                  ?? "pt");
    $ipOverride     = validateIpRating($input["ip"]                  ?? "0");

    // These fields are used in file/image lookups, not SQL — strip dangerous characters
    $description    = htmlspecialchars($input["descricao"]           ?? "", ENT_QUOTES, "UTF-8");
    $company        = preg_replace("/[^a-zA-Z0-9]/", "", $input["empresa"]       ?? "0");
    $lens           = preg_replace("/[^a-zA-Z0-9°×\s]/", "", $input["lente"]    ?? "");
    $finish         = preg_replace("/[^a-zA-Z0-9+_-]/", "", $input["acabamento"] ?? "");
    $option         = preg_replace("/[^a-zA-Z0-9]/", "", $input["opcao"]         ?? "0");
    $connectorCable = preg_replace("/[^a-zA-Z0-9]/", "", $input["conectorcabo"]  ?? "0");
    $cableType      = preg_replace("/[^a-zA-Z0-9]/", "", $input["tipocabo"]      ?? "");
    $endCap         = preg_replace("/[^a-zA-Z0-9]/", "", $input["tampa"]         ?? "0");
    $gasket         = floatval($input["vedante"]                     ?? 5);
    $extraLength    = intval($input["acrescimo"]                     ?? 0);
    $fixingId       = preg_replace("/[^a-zA-Z0-9]/", "", $input["fixacao"]       ?? "0");
    $supplyId       = preg_replace("/[^a-zA-Z0-9]/", "", $input["fonte"]         ?? "0");
    $cableId        = preg_replace("/[^a-zA-Z0-9]/", "", $input["caboligacao"]   ?? "0");
    $connectorId    = preg_replace("/[^a-zA-Z0-9]/", "", $input["conectorligacao"] ?? "0");
    $cableLength    = floatval($input["tamanhocaboligacao"]          ?? 0);
    $purpose        = preg_replace("/[^a-zA-Z0-9]/", "", $input["finalidade"]    ?? "0");

    if (strlen($reference) < 10) {
        respondDatasheetJsonError(400, ["error" => "Invalid or missing reference code"]);
        return;
    }

    // --- Decode reference and determine product type ---
    $stage       = "decode_reference";
    $parts       = decodeReference($reference);
    $productType = getProductType($reference);

    if ($productType === null) {
        respondDatasheetJsonError(422, ["error" => "Unknown product family in reference: $reference"]);
        return;
    }

    // --- Get product ID from the database ---
    $stage = "resolve_product_id";
    if ($productType === "dynamic") {
        $productId = getProductIdDynamic($reference, $parts["cap"]);
    } else {
        $productId = getProductId($reference);
    }

    if ($productId === null) {
        respondDatasheetJsonError(404, ["error" => "Product not found in database for reference: $reference"]);
        return;
    }

    if (!isDatasheetRuntimeSupported($productType, $parts["family"])) {
        respondDatasheetJsonError(422, [
            "error" => "Datasheet runtime not mapped yet for product family: " . $parts["family"],
            "error_code" => "unsupported_datasheet_runtime",
        ]);
        return;
    }

    // --- Shared config passed to multiple fetchers ---
    $config = [
        "lens"            => $lens,
        "finish"          => $finish,
        "connector_cable" => $connectorCable,
        "cable_type"      => $cableType,
        "end_cap"         => $endCap,
        "purpose"         => $purpose,
        "lang"            => $lang,
        "extra_length"    => $extraLength,
        "option"          => $option,
        "cable_length"    => $cableLength,
        "gasket"          => $gasket,
    ];

    // --- Fetch all section data ---
    $stage = "fetch_luminotechnical";
    $lumino = getLuminotechnicalData($productId, $reference, $lang);

    if ($lumino === null) {
        respondDatasheetJsonError(422, ["error" => "Luminotechnical data not found for product: $productId"]);
        return;
    }

    $stage     = "fetch_sections";
    $ledId     = $lumino["led_id"];
    $lensName  = getLensName($reference, $lens, $lang);
    $ipRating  = getIpRating($productId, $ipOverride) ?? "";
    $sizesFile = getBarSizesFile($reference);

    $header          = getProductHeader($productType, $productId, $reference, $ledId, $config);
    $characteristics = getCharacteristics($productId, $ipRating, $parts["family"], $parts["lens"], $lang);
    $drawing         = getTechnicalDrawing($productType, $reference, $productId, $sizesFile, $config);
    $colorGraph      = getColorGraph($ledId, $lang);
    $lensDiagram     = getLensDiagram($productId, $reference);
    $finishData      = getFinishAndLens($productType, $productId, $reference, $config);

    // --- Optional sections ---
    $fixing = ($fixingId !== "0")
        ? getFixing($reference, $lens, $cableType, $endCap, $fixingId, $lang)
        : null;

    $powerSupply = getPowerSupply($supplyId, $lang);

    $connectionCable = ($cableId !== "0")
        ? getConnectionCable($reference, $cableId, $connectorId, $cableLength, $lang)
        : null;

    // --- Validate required sections ---
    // color_graph and lens_diagram are optional — not all products have them
    if ($characteristics === null) {
        respondDatasheetJsonError(422, ["error" => "Missing required data: characteristics"]);
        return;
    }
    if ($finishData === null) {
        respondDatasheetJsonError(422, ["error" => "Missing required data: finish image"]);
        return;
    }

    // --- Assemble data array for the layout builder ---
    $data = [
        "lang"            => $lang,
        "reference"       => $reference,
        "description"     => $description,
        "energy_class"    => $lumino["energy_class"],
        "header"          => $header,
        "characteristics" => $characteristics,
        "luminotechnical" => $lumino,
        "lens_name"       => $lensName,
        "ip_rating"       => $ipRating,
        "drawing"         => $drawing,
        "color_graph"     => $colorGraph,
        "lens_diagram"    => $lensDiagram,
        "finish"          => $finishData,
    ];

    if ($fixing          !== null) $data["fixing"]           = $fixing;
    if ($powerSupply     !== null) $data["power_supply"]     = $powerSupply;
    if ($connectionCable !== null) $data["connection_cable"] = $connectionCable;

    if ($productType === "shelf") {
        $strictShelfError = validateStrictShelfCompleteness($data, $parts);

        if ($strictShelfError !== null) {
            respondDatasheetJsonError(422, ["error" => $strictShelfError]);
            return;
        }
    }

    if ($productType === "tubular") {
        $strictTubularError = validateStrictTubularCompleteness($data, $parts);

        if ($strictTubularError !== null) {
            respondDatasheetJsonError(422, ["error" => $strictTubularError]);
            return;
        }
    }

    // --- Build HTML ---
    $stage = "build_layout";
    $css  = "<style>" . file_get_contents(APP_CSS_PATH) . "</style>";
    $html = $css . buildPdfLayout($data);

    // --- Set TCPDF globals (read by NEXLEDPDF::Header() and NEXLEDPDF::Footer()) ---
    // NEXLEDPDF uses: global $pdf, $descricaoProduto, $empresa, $IDProduto, $lang
    global $pdf, $descricaoProduto, $empresa, $IDProduto;
    $descricaoProduto  = $description;
    $empresa           = $company;
    $IDProduto         = $productId;
    $GLOBALS["lang"]   = $lang;  // NEXLEDPDF::Footer() reads the global $lang

    // --- Generate PDF ---
    $stage = "render_pdf";
    set_time_limit(0);
    ini_set("memory_limit", "640M");

    ob_start();

    $pdf = new NEXLEDPDF("p", "mm", "A4", true, "UTF-8", false);
    $pdf->SetTopMargin(25);
    $pdf->SetLeftMargin(10);
    $pdf->SetRightMargin(10);
    $pdf->AddPage();
    $pdf->setAllowLocalFiles(true);
    $pdf->setRasterizeVectorImages(false);
    $pdf->setFontSubsetting(true);
    $pdf->SetFont("helvetica", "", 10, "", true);
    $pdf->writeHTML($html, true, false, true, false, "");

    ob_end_clean();

    $pdf->Output("", "D");
    } catch (\Throwable $error) {
        error_log(
            "NexLed datasheet fatal: stage={$stage}; reference={$reference}; productId={$productId}; " .
            "message=" . $error->getMessage()
        );

        respondDatasheetJsonError(500, [
            "error" => "Datasheet internal error",
            "stage" => $stage,
            "detail" => $error->getMessage(),
            "reference" => $reference,
        ]);
    }
}
