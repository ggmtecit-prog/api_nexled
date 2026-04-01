<?php

// GET /api/?endpoint=options&family=11
// Returns available options for a product family

$family = $_GET["family"] ?? null;

if (!$family) {
    http_response_code(400);
    echo json_encode(["error" => "Missing family parameter"]);
    exit();
}

// Reuse existing logic from appdatasheets
ob_start();
$_SERVER["REQUEST_METHOD"] = "POST";
$GLOBALS["_INPUT"] = json_encode($family);
require_once "../../appdatasheets/funcoes/getOpcoesProduto.php";
ob_end_clean();
