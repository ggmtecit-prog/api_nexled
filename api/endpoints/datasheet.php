<?php

// POST /api/?endpoint=datasheet
// Generates and returns a PDF datasheet
// Body: same JSON structure as the original form submission

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

// Delegate to existing datasheet generator
require_once "../../appdatasheets/funcoes/gerarDatasheet.php";
