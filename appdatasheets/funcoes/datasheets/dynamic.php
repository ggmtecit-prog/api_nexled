<?php


function imagemInicial($IDProduto, $referencia, $acabamento) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];

    $id = explode("/", $IDProduto);
    $tipoDynamic = $id[1];

    $pasta = "/img/$refFamilia/$tipoDynamic/produto/";
    $acabamento = strtolower($acabamento);

    if (str_contains($acabamento, "+")) {
        $acabamento = str_replace("+", "", $acabamento);
    }

    $imagensPossiveis = array(
        $tamanho . "_" . $acabamento
    );

    $result = array(
        "pasta" => $pasta,
        "imagens" => $imagensPossiveis
    );

    return $result;

}





function imagemAcabamentoLente($IDProduto, $referencia, $acabamento) {

    $id = explode("/", $IDProduto);
    $tipoDynamic = $id[1];

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];

    $pasta = "/img/$refFamilia/$tipoDynamic/acabamentos/";
    $acabamento = strtolower($acabamento);

    if (str_contains($acabamento, "+")) {
        $acabamento = str_replace("+", "", $acabamento);
    }

    $imagensPossiveis = array(
        $tamanho . "_" . $acabamento
    );

    $result = array(
        "pasta" => $pasta,
        "imagens" => $imagensPossiveis
    );

    return $result;

}

?>