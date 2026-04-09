<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit();
}

require_once "./auth.php";
require_once "../appdatasheets/config.php";
require_once "./lib/validate.php";

// Route: /api/?endpoint=families
// Route: /api/?endpoint=options&family=11
// Route: /api/?endpoint=reference&ref=...
// Route: /api/?endpoint=datasheet (POST)

$endpoint = $_GET["endpoint"] ?? null;

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
    case "datasheet":
        require "./endpoints/datasheet.php";
        break;
    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint not found"]);
        break;
}
