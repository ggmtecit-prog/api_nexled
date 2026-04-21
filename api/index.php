<?php

// Ensure requires resolve relative to this file regardless of the web server's CWD.
// FrankenPHP (Railway) sets CWD to the document root (/app), not to /app/api/.
chdir(__DIR__);

$endpoint = $_GET["endpoint"] ?? null;

if (
    $endpoint !== "datasheet"
    && $endpoint !== "file-datasheet"
    && $endpoint !== "file-spectral"
    && $endpoint !== "showcase-pdf"
    && $endpoint !== "custom-datasheet-pdf"
) {
    header("Content-Type: application/json");
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "./auth-check.php";
require_once "./bootstrap.php";
require_once "./lib/validate.php";

// Route: /api/?endpoint=families
// Route: /api/?endpoint=options&family=11
// Route: /api/?endpoint=reference&ref=...
// Route: /api/?endpoint=decode-reference&ref=...
// Route: /api/?endpoint=datasheet (POST)
// Route: /api/?endpoint=file-datasheet&reference=...
// Route: /api/?endpoint=file-spectral&reference=...
// Route: /api/?endpoint=health
// Route: /api/?endpoint=svg-diagnostics&ref=...
// Route: /api/?endpoint=dam&action=tree|list|asset|create-folder|sync-folders|upload|product-assets|link|unlink
// Route: /api/?endpoint=code-explorer&family=11&page=1&page_size=100&search=&status=all
// Route: /api/?endpoint=family-ready-products&family=01&page=1&page_size=100
// Route: /api/?endpoint=family-ready-filters&family=01
// Route: /api/?endpoint=code-repair&reference=11007502110010100&lang=pt
// Route: /api/?endpoint=showcase-preview (POST)
// Route: /api/?endpoint=showcase-pdf (POST)
// Route: /api/?endpoint=custom-datasheet-preview (POST)
// Route: /api/?endpoint=custom-datasheet-pdf (POST)

if (!$endpoint) {
    http_response_code(400);
    echo json_encode(["error" => "No endpoint specified"]);
    exit();
}

switch ($endpoint) {
    case "families":
        require "./endpoints/families.php";
        break;
    case "options":
        require "./endpoints/options.php";
        break;
    case "reference":
        require "./endpoints/reference.php";
        break;
    case "decode-reference":
        require "./endpoints/decode-reference.php";
        break;
    case "datasheet":
        require "./endpoints/datasheet.php";
        break;
    case "file-datasheet":
        require "./endpoints/file-datasheet.php";
        break;
    case "file-spectral":
        require "./endpoints/file-spectral.php";
        break;
    case "health":
        require "./endpoints/health.php";
        break;
    case "svg-diagnostics":
        require "./endpoints/svg-diagnostics.php";
        break;
    case "code-explorer":
        require "./endpoints/code-explorer.php";
        break;
    case "family-ready-products":
        require "./endpoints/family-ready-products.php";
        break;
    case "family-ready-filters":
        require "./endpoints/family-ready-filters.php";
        break;
    case "code-repair":
        require "./endpoints/code-repair.php";
        break;
    case "showcase-preview":
        require "./endpoints/showcase-preview.php";
        break;
    case "showcase-pdf":
        require "./endpoints/showcase-pdf.php";
        break;
    case "custom-datasheet-preview":
        require "./endpoints/custom-datasheet-preview.php";
        break;
    case "custom-datasheet-pdf":
        require "./endpoints/custom-datasheet-pdf.php";
        break;
    case "dam":
        require "./endpoints/dam.php";
        break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
        break;
}
