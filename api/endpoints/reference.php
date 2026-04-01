<?php

// GET /api/?endpoint=reference&ref=1104WW3A00
// Returns description for a reference code

$ref = $_GET["ref"] ?? null;

if (!$ref) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ref parameter"]);
    exit();
}

// Reuse existing logic from appdatasheets
ob_start();
$GLOBALS["_INPUT"] = json_encode($ref);
require_once "../../appdatasheets/funcoes/getDescricaoProduto.php";
ob_end_clean();
