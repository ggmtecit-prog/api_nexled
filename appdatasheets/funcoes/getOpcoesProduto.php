<?php

include_once dirname(__FILE__, 2) . "/config.php";

$refFam = json_decode(file_get_contents('php://input'), true);

$tamanhos = listarTamanhos($refFam);
$cores = listarCores($refFam);
$cri = listarCri();
$series = listarSeries($refFam);
$lentes = listarLentes($refFam);
$perfil = listarPerfis($refFam);
$cap = listarCaps($refFam);
$opcoes = listarOpcoes($refFam);

$infoProduto = array (
    "tamanho" => $tamanhos,
    "cor" => $cores,
    "cri" => $cri,
    "serie" => $series,
    "lente" => $lentes,
    "acabamento" => $perfil,
    "cap" => $cap,
    "opcao" => $opcoes
);

echo json_encode($infoProduto);



function listarTamanhos($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Tamanhos.tamanho, Tamanhos.desc FROM Tamanhos, Familias WHERE Tamanhos.familia = Familias.tamanhos AND Familias.codigo = $refFam ORDER BY tamanho");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = $row["tamanho"];
    }

    return $results;

}



function listarCores($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Cor.cor, Cor.codigo FROM Cor, Familias WHERE Cor.familia = Familias.cor AND Familias.codigo = $refFam ORDER BY Cor.codigo");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = [$row["cor"], $row["codigo"]];
    }

    return $results;

}



function listarCri() {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT cri, codigo FROM CRI ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = [$row["cri"], $row["codigo"]];
    }

    return $results;

}



function listarSeries($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Series.series, Series.codigo FROM Series, Familias WHERE Series.familia = Familias.series AND Familias.codigo = $refFam ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = [$row["series"], $row["codigo"]];
    }

    return $results;

}



function listarLentes($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Acrilico.acrilico, Acrilico.codigo, Acrilico.desc FROM Acrilico, Familias WHERE Acrilico.familia = Familias.acrilico AND Familias.codigo = $refFam ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {

        if(str_contains($row["desc"], "&deg;")) {
            $row["desc"] = str_replace("&deg;", "°", $row["desc"]);
        } else if (str_contains($row["desc"], "&deg")) {
            $row["desc"] = str_replace("&deg", "°", $row["desc"]);
        } 

        $results[] = [$row["acrilico"], $row["codigo"], $row["desc"]];
    }

    return $results;

}



function listarPerfis($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Acabamento.acabamento, Acabamento.codigo, Acabamento.desc FROM Acabamento, Familias WHERE Acabamento.familia = Familias.acabamento AND Familias.codigo = $refFam ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = [$row["acabamento"], $row["codigo"], $row["desc"]];
    }

    return $results;

}



function listarCaps($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Cap.cap, Cap.codigo, Cap.desc FROM Cap, Familias WHERE Cap.familia = Familias.cap AND Familias.codigo = $refFam ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {

        if(str_contains($row["desc"], "&acirc;")) {
            $row["desc"] = str_replace("&acirc;", "â", $row["desc"]);
        }

        $results[] = [$row["cap"], $row["codigo"], $row["desc"]];
    }

    return $results;

}



function listarOpcoes($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Opcao.opcao, Opcao.codigo, Opcao.desc FROM Opcao, Familias WHERE Opcao.familia = Familias.opcao AND Familias.codigo = $refFam ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = [$row["opcao"], $row["codigo"], $row["desc"]];
    }

    return $results;

}



function listarCabos($refFam) {

    $con = connectDBReferencias();

    $results = [];

    $query = mysqli_query($con, "SELECT Opcao.opcao, Opcao.codigo FROM Opcao, Familias WHERE Opcao.familia = Familias.opcao AND Familias.codigo = $refFam ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {
        $results[] = [$row["opcao"], $row["codigo"], $row["desc"]];
    }

    return $results;

}



?>