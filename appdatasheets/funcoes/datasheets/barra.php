<?php


function imagemInicial($referencia, $lente, $acabamento, $conectorcabo, $tipocabo, $tampa) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $serie = $digitos["serie"];
    $cap = $digitos["cap"];


    $lente = strtolower($lente);
    $acabamento = strtolower($acabamento);


    if($lente === "clear") {
        $pasta = "/img/$refFamilia/produto/$lente/$serie/";
    } else {
        $pasta = "/img/$refFamilia/produto/$lente/";
    }


    // se BT
    if($refFamilia === "32") {

        $imagensPossiveis = array(
            $conectorcabo . "_" . $tipocabo . "_" . $tampa
        );

    // se HOT
    } else if($refFamilia === "58") {

        $imagensPossiveis = array(
             $acabamento . "_" .  $tampa
        );

    } else {

        $imagensPossiveis = array(
            str_replace("+", "_", $acabamento . "_" . $conectorcabo . "_" . $tipocabo . "_" . $tampa),
            str_replace("+", "_", $acabamento . "_" . $cap)
        );

    }

    $result = array(
        "pasta" => $pasta,
        "imagens" => $imagensPossiveis
    );

    return $result;

}





function desenhoTecnicoBarras($referencia, $acrescimo, $opcao, $tamanhocabo, $conectorcabo, $tampa, $vedante, $jsonTamanhos) {


    $json = json_decode(file_get_contents(dirname(__DIR__, 2) . $jsonTamanhos));


    $result = [];
    $result["erro"] = "";


    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];
    $codigoLente = $digitos["lente"];
    $cap = $digitos["cap"];


    $pasta = "/img/$refFamilia/desenhos/";

    $desenhosPossiveis = array(
        $cap . "_" . $conectorcabo . "_" . $tampa,
        $cap . "_" . $tampa,
        $conectorcabo . "_" . $tampa,
        $cap
    );


    $desenhoProduto = 0;

    foreach($desenhosPossiveis as $desenhos) {
        $src = dirname(__DIR__, 2) . $pasta . $desenhos;
        if($desenhoProduto === 0) {
            $desenhoProduto = existeImagem($src);
        }
    }

    if($desenhoProduto === 0) {
        $result["erro"] .= "desenho do produto => $pasta => " . implode(" ou ", $desenhosPossiveis) . "<br>";
    } else {
        $desenho = $desenhoProduto;
    }



    $medidas = ["comprimento", "altura", "largura"];

    $medidasTampa = array(
        "comprimento" => 0, 
        "altura" => 0,
        "largura" => 0,
    );

    $medidasBarra = array(
        "altura" => 0,
        "largura" => 0,
    );

    foreach($medidas as $medida) {

        if(array_key_exists($medida, $medidasTampa)){
            if($medida === "altura") {
                if(isset($json->tampa->$tampa->$medida->$codigoLente) && $json->tampa->$tampa->$medida->$codigoLente !== ""){
                    $medidasTampa[$medida] = $json->tampa->$tampa->$medida->$codigoLente;
                }
            } else if(isset($json->tampa->$tampa->$medida) && $json->tampa->$tampa->$medida !== ""){
                $medidasTampa[$medida] = $json->tampa->$tampa->$medida;
            }

            if($medidasTampa[$medida] === 0) {
                $result["erro"] .= "medida da tampa $tampa com lente $codigoLente em $jsonTamanhos <br>";
            }
        }

        if(array_key_exists($medida, $medidasBarra)){
            if($medida === "altura") {
                if(isset($json->barra->$medida->$codigoLente) && $json->barra->$medida->$codigoLente !== ""){
                    $medidasBarra[$medida] = $json->barra->$medida->$codigoLente;
                }
            } else if (isset($json->barra->$medida) && $json->barra->$medida !== "") {
                $medidasBarra[$medida] = $json->barra->$medida;
            }

            if($medidasBarra[$medida] === "0") {
                $result["erro"] .= "medida da barra com a lente $codigoLente em $jsonTamanhos <br>";
            }
        }
        
    }



    $nrTampas = "2";
    $nrVedantes = "2";
    $alturaConectorBarra = 0;
    $larguraConectorBarra = 0;
    $comprimentoConectorBarra = 0;
    $alturaConectorCabo = 0;
    $larguraConectorCabo = 0;

    if(isset($json->caps)) {

        foreach($json->caps as $caps) {
    
            if(in_array($cap, $caps->cap)) {
                
                if(isset($caps->tampas)) {
                    $nrTampas = $caps->tampas;
                }
    
                if(isset($caps->vedantes)) {
                    $nrVedantes = $caps->vedantes;
                }
    
                if(isset($caps->altura)) {
                    $alturaConectorBarra = $caps->altura;
                }
    
                if(isset($caps->largura)) {
                    $larguraConectorBarra = $caps->largura;
                }
    
                $comprimentoConectorBarra = $caps->comprimento;
            }
    
        }

    }

    

    if($conectorcabo !== "0") {
        if (isset($json->conectorcabo->$conectorcabo)) {
            $alturaConectorCabo = $json->conectorcabo->$conectorcabo->altura;
            $larguraConectorCabo = $json->conectorcabo->$conectorcabo->largura;
        }
    }


    if($nrTampas === "2" && $tamanhocabo === "0" && $opcao !== "0") {
        $result["erro"] .= "tamanho do cabo da barra<br>";
    } else if ($nrTampas === "2" && $tamanhocabo === "0" && $opcao === "0"){
        $tamanhocabo = 1.5;
    }


    if ($result["erro"] === "") {
    
        $result = array (
            "desenho" => $desenho,
            "A" => "0",
            "B" => "0",
            "C" => "0",
            "D" => "0",
            "E" => "0",
            "F" => "0",
            "G" => "0",
            "H" => "0",
            "I" => "0",
            "J" => "0",
        );


        $sizePlaca = ltrim($tamanho, "0");

        $result["A"] = round($sizePlaca + ($medidasTampa["comprimento"]*$nrTampas) + ($vedante*$nrVedantes) + $comprimentoConectorBarra + $acrescimo);
		
        $result["B"] = $medidasBarra["altura"];

        $result["C"] = $medidasBarra["largura"];


        if($nrTampas === "2") { // cabo

            $result["D"] = $medidasTampa["altura"];

            $result["E"] = $medidasTampa["largura"];

            if($tamanhocabo !== "") {
                $result["F"] = $tamanhocabo * 1000;
            }

            if($alturaConectorCabo !== 0) {
                $result["G"] = $alturaConectorCabo;
            }

            if($larguraConectorCabo !== 0) {
                $result["H"] = $larguraConectorCabo;
            }

        } else if ($nrTampas === "1"){ // cabo ou tampa + conector

            $result["D"] = $medidasTampa["altura"];

            $result["E"] = $medidasTampa["largura"];

            if($tamanhocabo !== "" && $tamanhocabo !== "0") {

                $result["F"] = $tamanhocabo * 1000;

                if($alturaConectorCabo !== 0) {
                    $result["G"] = $alturaConectorCabo;
                }
    
                if($larguraConectorCabo !== 0) {
                    $result["H"] = $larguraConectorCabo;
                }
    
                if($alturaConectorBarra !== 0) {
                    $result["I"] = $alturaConectorBarra;
                }
    
                if($larguraConectorBarra !== 0) {
                    $result["J"] = $larguraConectorBarra;
                }

            } else {
    
                if($alturaConectorBarra !== 0) {
                    $result["F"] = $alturaConectorBarra;
                }
    
                if($larguraConectorBarra !== 0) {
                    $result["G"] = $larguraConectorBarra;
                }

            }

        } else if ($nrTampas === "0"){ // 2 conectores

            $result["D"] = $alturaConectorBarra;

            $result["E"] = $larguraConectorBarra;
            
        }

    }


    return $result;

}





function imagemAcabamentoLente($referencia, $lente, $acabamento, $tampa) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $serie = $digitos["serie"];
    $cap = $digitos["cap"];


    $lente = strtolower($lente);

    $acabamento = strtolower($acabamento);

    if($lente === "clear") {
        $pasta = "/img/$refFamilia/acabamentos/$lente/$serie/";
    } else {
        $pasta = "/img/$refFamilia/acabamentos/$lente/";
    }

    $imagensPossiveis = array(
        str_replace("+","_",$acabamento . "_" . $cap),
        str_replace("+","_",$acabamento . "_" . $tampa)
    );

    $result = array(
        "pasta" => $pasta,
        "imagens" => $imagensPossiveis
    );

    return $result;

}

?>