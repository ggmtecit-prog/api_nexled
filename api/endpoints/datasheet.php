<?php

// POST /api/?endpoint=datasheet
// Generates and returns a PDF datasheet
// Body: JSON with referencia, descricao, idioma, empresa, lente, acabamento,
//       opcao, conectorcabo, tipocabo, tampa, vedante, acrescimo, ip,
//       fixacao, fonte, caboligacao, conectorligacao, tamanhocaboligacao, finalidade

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit();
}

require_once dirname(__FILE__, 2) . "/lib/pdf-engine.php";

generateDatasheet();
