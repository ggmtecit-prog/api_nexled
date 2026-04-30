<?php

// GET /api/?endpoint=options&family=11
// Returns all dropdown options for a product family

require_once dirname(__FILE__) . "/../lib/cache.php";

$family = validateFamily($_GET["family"] ?? null);

if ($family === 0) {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid family parameter"]);
    exit();
}

header("Cache-Control: public, max-age=3600");

$payload = cacheRemember("options:" . $family, 3600, function () use ($family) {
    $con = connectDBReferencias();

    $tamanhos = [];
    $q = mysqli_query($con, "SELECT Tamanhos.tamanho FROM Tamanhos, Familias WHERE Tamanhos.familia = Familias.tamanhos AND Familias.codigo = $family ORDER BY tamanho");
    while ($row = mysqli_fetch_assoc($q)) { $tamanhos[] = $row["tamanho"]; }

    $cores = [];
    $q = mysqli_query($con, "SELECT Cor.cor, Cor.codigo FROM Cor, Familias WHERE Cor.familia = Familias.cor AND Familias.codigo = $family ORDER BY Cor.codigo");
    while ($row = mysqli_fetch_assoc($q)) { $cores[] = [$row["cor"], $row["codigo"]]; }

    $cri = [];
    $q = mysqli_query($con, "SELECT cri, codigo FROM CRI ORDER BY codigo");
    while ($row = mysqli_fetch_assoc($q)) { $cri[] = [$row["cri"], $row["codigo"]]; }

    $series = [];
    $q = mysqli_query($con, "SELECT Series.series, Series.codigo FROM Series, Familias WHERE Series.familia = Familias.series AND Familias.codigo = $family ORDER BY codigo");
    while ($row = mysqli_fetch_assoc($q)) { $series[] = [$row["series"], $row["codigo"]]; }

    $lentes = [];
    $q = mysqli_query($con, "SELECT Acrilico.acrilico, Acrilico.codigo, Acrilico.desc FROM Acrilico, Familias WHERE Acrilico.familia = Familias.acrilico AND Familias.codigo = $family ORDER BY codigo");
    while ($row = mysqli_fetch_assoc($q)) {
        $row["desc"] = str_replace(["&deg;", "&deg"], "°", $row["desc"]);
        $lentes[] = [$row["acrilico"], $row["codigo"], $row["desc"]];
    }

    $acabamentos = [];
    $q = mysqli_query($con, "SELECT Acabamento.acabamento, Acabamento.codigo, Acabamento.desc FROM Acabamento, Familias WHERE Acabamento.familia = Familias.acabamento AND Familias.codigo = $family ORDER BY codigo");
    while ($row = mysqli_fetch_assoc($q)) { $acabamentos[] = [$row["acabamento"], $row["codigo"], $row["desc"]]; }

    $caps = [];
    $q = mysqli_query($con, "SELECT Cap.cap, Cap.codigo, Cap.desc FROM Cap, Familias WHERE Cap.familia = Familias.cap AND Familias.codigo = $family ORDER BY codigo");
    while ($row = mysqli_fetch_assoc($q)) {
        $row["desc"] = str_replace("&acirc;", "â", $row["desc"]);
        $caps[] = [$row["cap"], $row["codigo"], $row["desc"]];
    }

    $opcoes = [];
    $q = mysqli_query($con, "SELECT Opcao.opcao, Opcao.codigo, Opcao.desc FROM Opcao, Familias WHERE Opcao.familia = Familias.opcao AND Familias.codigo = $family ORDER BY codigo");
    while ($row = mysqli_fetch_assoc($q)) { $opcoes[] = [$row["opcao"], $row["codigo"], $row["desc"]]; }

    closeDB($con);

    return [
        "tamanho"    => $tamanhos,
        "cor"        => $cores,
        "cri"        => $cri,
        "serie"      => $series,
        "lente"      => $lentes,
        "acabamento" => $acabamentos,
        "cap"        => $caps,
        "opcao"      => $opcoes
    ];
});

echo json_encode($payload);
