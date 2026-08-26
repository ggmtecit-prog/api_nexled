<?php


function imagemInicial($lente, $referencia) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];

    $lente = strtolower($lente);

    $pasta = "/img/$refFamilia/produto/";
        
    $imagensPossiveis = array(
        $tamanho . "_" . $lente
    );

    $result = array(
        "pasta" => $pasta,
        "imagens" => $imagensPossiveis
    );

    return $result;

}





function imagemAcabamentoLente($referencia, $lente, $acabamento) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];

    $pasta = "/img/$refFamilia/acabamentos/";
    $lente = strtolower($lente);
    $acabamento = strtolower($acabamento);

    $imagensPossiveis = array(
        $tamanho . "_" . $lente . "_" . $acabamento
    );

    $result = array(
        "pasta" => $pasta,
        "imagens" => $imagensPossiveis
    );

    return $result;

}

?>