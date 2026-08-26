<?php

ob_start();

global $pdf, $descricaoProduto, $empresa, $IDProduto, $lang;

require_once dirname(__DIR__) . "/tcpdf/tcpdf_include.php";
require_once dirname(__DIR__) . "/tcpdf/tcpdf.php";
require_once dirname(__DIR__) . "/tcpdf/classes.php";
include_once "./funcoesDatasheet.php";



$css = "<style>" . strval(file_get_contents(dirname(__DIR__) . "/style/datasheet.css")) . "</style>";


$infoProduto = json_decode(file_get_contents("php://input"), true);


$digitos = getDigitos($infoProduto["referencia"]);
$refFamilia = $digitos["refFamilia"];

if($refFamilia === "48") {
    $IDProduto = getIDProdutoDynamic($infoProduto["referencia"], $infoProduto["cap"]);
} else {
    $IDProduto = getIDProduto($infoProduto["referencia"]);
}



$descricaoProduto = $infoProduto["descricao"];
$lang = $infoProduto["idioma"];
$empresa = $infoProduto["empresa"];



$erro = 0;

$infoDatasheet = infoDatasheet($IDProduto, $infoProduto);


if(isset($infoDatasheet["erro"])) {
    $erro = 1;
    echo json_encode($infoDatasheet["erro"], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES );
}



if($erro === 0) {

    include_once "./estruturaDatasheet.php";

    $estruturaDatasheet = estruturaDatasheet($infoDatasheet, $infoProduto["referencia"], $infoProduto["descricao"], $lang);


    // header + footer
    $pdf = new NEXLEDPDF("p", "mm", "A4", true, "UTF-8", false);

    $pdf->SetTopMargin(25);
    $pdf->SetLeftMargin(10);
    $pdf->SetRightMargin(10);

    $pdf->AddPage();

    $pdf->setFontSubsetting(true);

    $pdf->SetFont("helvetica", "", 10, "", true);

    set_time_limit(0);

    ini_set("memory_limit", "640M");

    $html = $css . $estruturaDatasheet;

    $pdf->writeHTML($html, true, false, true, false, "");

    ob_end_clean();

    $pdf->Output("", "D");
    
}



?>