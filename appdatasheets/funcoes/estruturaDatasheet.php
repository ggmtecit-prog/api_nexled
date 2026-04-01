<?php


function estruturaDatasheet($infoDatasheet, $referencia, $descricao, $lang) {


    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];

    
    $json = file_get_contents(dirname(__DIR__) . "/json/datasheet.json");
    $json = json_decode($json);

    $tituloCaracteristicas = $json->caracteristicas->titulo->$lang;

    $jsonLuminotecnicas = $json->luminotecnicas;
    
    $tituloDesenho = $json->desenhotecnico->titulo->$lang;
    
    $tituloGrafico = $json->graficocor->titulo->$lang;
    
    if(isset($json->graficocor->SDCM->$refFamilia->$lang)) {
        $SDCM = $json->graficocor->SDCM->$refFamilia->$lang;
    } else {
        $SDCM = $json->graficocor->SDCM->all->$lang;
    }

    $tituloDiagrama = $json->diagramalente->titulo->$lang;

    $jsonAcabamentos = $json->acabamento;

    $notaMedidas = $json->notaMedidas->$lang;



    // FUNÇÕES ESTRUTURA

    $result = "";

    $result .= criarInfoInicial($infoDatasheet["infoInicial"], $infoDatasheet["classeEnergetica"]);

    $result .= criarCaracteristicas($infoDatasheet["caracteristicas"], $tituloCaracteristicas, $lang);

    $result .= criarLuminotecnicas($infoDatasheet["luminos"], $referencia, $descricao, $infoDatasheet["nomeLente"], $infoDatasheet["ip"], $jsonLuminotecnicas, $lang);

    $result .= criarDesenhoTecnico($infoDatasheet["desenho"], $tituloDesenho, $notaMedidas, $lang);

    $result .= criarGrafico($infoDatasheet["grafico"], $tituloGrafico, $SDCM, $lang);

    $result .= criarDiagrama($infoDatasheet["diagrama"], $infoDatasheet["nomeLente"], $tituloDiagrama);

    $result .= criarAcabamentoLente($referencia, $infoDatasheet["acabamentoLente"], $infoDatasheet["nomeLente"], $jsonAcabamentos, $lang);

    if(isset($infoDatasheet["fixacao"])) {

        $jsonFixacao = $json->fixacao;

        $result .= criarFixacao($referencia, $infoDatasheet["fixacao"], $jsonFixacao, $notaMedidas, $lang);

    }

    if(isset($infoDatasheet["fonte"])) {
        $result .= criarFonte($infoDatasheet["fonte"], $lang);
    }

    if(isset($infoDatasheet["ligacao"])) {
        $result .= criarLigacao($infoDatasheet["ligacao"], $lang);
    }

    return $result;

}





function criarInfoInicial($infoInicial, $classeEnergetica) {
    
    $result = 
    
    "<table nobr=\"true\">" . 

        "<tr>" . 

            "<td colspan=\"9\" style=\"text-align: right;\">" .                        

                "<img src=\"{$infoInicial["imagem"]}\">" . 
                
                "<br>" .

                "<img width=\"40\" src=\"{$classeEnergetica}\">" .

            "</td>" . 
            
            "<td colspan=\"1\">" .
                    
            "</td>" . 

            "<td colspan=\"15\">" . 
                
                "<p class=\"descricao\">{$infoInicial["descricao"]}</p>" .
            
            "</td>" . 
            
        "</tr>" .

    "</table>";


    return $result;

}





function criarCaracteristicas($caracteristicas, $titulo, $lang) {

    $linhas = "";
    
    foreach($caracteristicas as $key => $value) {

        $linhas .= 

        "<tr>" . 

            "<td class=\"linha-tabela-contorno\" colspan=\"2\">" . 

                "<p><b>$key</b></p>" . 

            "</td>" .

            "<td class=\"linha-tabela-contorno\" colspan=\"3\">" . 

                "<p>$value</p>" . 

            "</td>" .

        "</tr>";

    }

    $result = 

    "<table class=\"tabela\" border=\"0\" cellpadding=\"2\">" . 

        "<tr>" . 

            "<td colspan=\"5\" class=\"titulo-tabela\">" .
            
                "<h2>$titulo</h2>" . 
            
            "</td>" .

        "</tr>" .

        $linhas .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

    "</table>"; 
    
    return $result;
    
}





function criarLuminotecnicas($luminos, $referencia, $descricao, $nomeLente, $ip, $json, $lang) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $refLente = $digitos["lente"];


    $header = "";
    $linha = "";


    // fluxo
    if((strval($luminos["rel_flux"]) !== "1.000") || empty($luminos["rel_flux"])){
        $fluxo = round($luminos["nleds"] * $luminos["lumens"] * $luminos["rel_flux"] * (1 - $luminos["att_temp"]) * (1 - $luminos["A" . $refLente]));
    } else {
        $fluxo = round($luminos["nleds"] * $luminos["lumens"] * $luminos["corrente"] / $luminos["serie"] / $luminos["correntelumens"] * (1 - $luminos["att_temp"]) * (1 - $luminos["A" . $refLente]));
    }

    // eficácia
    if($refFamilia === "31"){
        $eficacia = round($fluxo / ($luminos["potencia"] / 3));
    } else {
        $eficacia = round($fluxo / $luminos["potencia"]);
    }

    // temperatura
    $cct = $luminos["cct"];

    // cor
    if(floatval($cct) > 0) {

        $valorTemp = intval(explode(" ", $cct)[0]);

        foreach($json->temperaturas as $temperaturas) {

            $min = $temperaturas->min;

            if(!isset($temperaturas->max)) {
                if($valorTemp >= $min){
                    $cor = $temperaturas->$lang;
                }
            } else {
                $max = $temperaturas->max;
                if($valorTemp >= $min && $valorTemp <= $max){
                    $cor = $temperaturas->$lang;
                }
            }

        }
            
    } else {
        $cor = "-";
    }

    // cri
    $cri = $luminos["cri"];



    $valor = [$referencia, $descricao, $fluxo, $eficacia, $cct, $cor, $cri, $nomeLente];



    $i = 0;

    foreach($json->colunas as $coluna) {
        $header .= "<td colspan=\"{$coluna->size}\" class=\"linha-tabela-contorno\"><p><b>". $coluna->$lang . "</b></p></td>";
        $linha .= "<td class=\"linha-tabela-contorno\" colspan=\"{$coluna->size}\"><p>$valor[$i]</p></td>";
        $i++;
    }

    $result = 

    "<table cellpadding=\"0.5\" class=\"tabela\" nobr=\"true\">" . 

        "<tr>" . 
            
            "<td colspan=\"80\" class=\"titulo-tabela\">" .
            
                "<h2>" . $json->titulo->$lang . "</h2>" . 
            
            "</td>" .
    
        "</tr>" .

        "<tr>" . 

            $header .

        "</tr>" .

        "<tr>" .

            $linha .

        "</tr>" .

        "<tr>" .

            "<td></td>" .

        "</tr>" .

        criarNotas($referencia, $ip, $json->notas, $lang) .

        "<tr>" .

            "<td></td>" .

        "</tr>" .
        
        "<tr>" .

            "<td></td>" .

        "</tr>" .

    "</table>";

    return $result;

}






function criarNotas($referencia, $ip, $json, $lang) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];

    $notas = "";
    $simbolos = "";

    foreach($json->notas as $nota) {

        $familias = explode("/", $nota->familias);

        if(in_array($refFamilia, $familias)) {

            if (str_contains($nota->$lang, "¹")) {
            
                $nota->$lang = str_replace("¹", "*", $nota->$lang);
            
            } else if (str_contains($nota->$lang, "²")) {
            
                $nota->$lang = str_replace("²", "**", $nota->$lang);
            
            } else if (str_contains($nota->$lang, "³")) {
                
                $nota->$lang = str_replace("³", "***", $nota->$lang);
            
            } 

            $notas .= $nota->$lang . "<br>";

        }

    }


    $notas = rtrim($notas, "<br>");




    $imgSimbolos = [];

    foreach($json->simbolos as $simbolo) {
        if(isset($simbolo->familias)) {
            if(in_array($refFamilia, $simbolo->familias)) {
                $imgSimbolos[] = $simbolo->img;
            }
        } else {
            $imgSimbolos[] = $simbolo;
        }
    }


    
    $imgSimbolos[] = strtolower($ip) . ".svg";



    $colspan = 16;

    $pastaSimbolos = dirname(__DIR__) . "/img/icones/";

    for($i = 0; $i < $colspan; $i++) {

        $current = -(16-count($imgSimbolos)-$i);

        $simbolos .= "<td colspan=\"5\" style=\"text-align:right;\">";
    
        if(isset($imgSimbolos[$current])){

            $simbolos .= "<img width=\"30\" src=\"{$pastaSimbolos}{$imgSimbolos[$current]}\">";

        }

        $simbolos .= "</td>";

    }



    $result = 

        "<tr>" .

            "<td colspan=\"80\">" .
            
                "<p class=\"notaLumino\">" . $notas . "</p>" .

            "</td>" .

        "</tr>" . 

        "<tr>" .

            "<td></td>" .

        "</tr>" .

        "<tr>" .
                            
            $simbolos .
                
        "</tr>";

    return $result;

}





function criarDesenhoTecnico($desenho, $titulo, $notaMedidas, $lang){

    $colspan = count(array_filter($desenho))-1;

    $trKeys = "";
    $trValues = "";
        
    foreach(array_keys($desenho) as $key) {

        if($key !== "desenho" && $desenho[$key] !== "0") {

            $trKeys .= 
            "<td class=\"linha-tabela-contorno\" colspan=\"1\">" .
                "<p style=\"text-align: center;\"><b>$key</b></p>" .
            "</td>";

            $trValues .= 
            "<td class=\"linha-tabela-contorno\" colspan=\"1\">" .
                "<p style=\"text-align: center;\">$desenho[$key]</p>" .
            "</td>";

        }

    }



    $result =  

    "<table nobr=\"true\">" . 

        "<tr>" .

            "<td colspan=\"$colspan\">" .
            
                "<h2>$titulo</h2>" .

            "</td>" .

        "</tr>" .
        
        "<tr>" .
                
            "<td colspan=\"$colspan\"><img src=\"{$desenho["desenho"]}\"></td>" .

        "</tr>" .

        "<tr>" .

            "<td colspan=\"$colspan\"></td>" .
                
        "</tr>" .

        "<tr>" .
            
            $trKeys .

        "</tr>" .

        "<tr>" .

            $trValues .

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td colspan=\"$colspan\">" . 

                "<p>$notaMedidas</p>" .
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

    "</table>";


    return $result;

}





function criarGrafico($grafico, $titulo, $SDCM) {


    $result =

    "<table nobr=\"true\">" . 

        "<tr>" .

            "<td colspan=\"5\">" . 

                "<h2>$titulo</h2>" .
            
            "</td>" .
                
        "</tr>" .

        "<tr>" .

            "<td colspan=\"5\">" . 

                "<p><b>{$grafico["led"]}</b></p>" .
            
            "</td>" .
                
        "</tr>" .
        
        "<tr>" .
                
            "<td colspan=\"4\">" . 
            
                "<img src=\"{$grafico["grafico"]}\">" . 
            
            "</td>" .

        "</tr>" .
        
        "<tr>" .
                
            "<td colspan=\"5\">" . 
            
                "<p>$SDCM</p>" . 
            
            "</td>" .

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

    "</table>";

    return $result;

}





function criarDiagrama($diagrama, $nomeLente, $titulo) {

    $imgDiagrama = $diagrama["diagrama"];

    $imgIluminancia = $diagrama["iluminancia"];

    if($imgIluminancia !== "") {
        $imgIluminancia = "<img height=\"210\" src=\"{$imgIluminancia}\">";
    }

    $result =

        "<table nobr=\"true\">" . 

            "<tr>" .

                "<td colspan=\"2\">" . 

                    "<h2>$titulo</h2>" .
                
                "</td>" .
                    
            "</tr>" .

            "<tr>" .

                "<td colspan=\"2\">" . 

                    "<p><b>$nomeLente</b></p>" .
                
                "</td>" .
                    
            "</tr>" .
            
            "<tr>" .
                    
                "<td colspan=\"1\">" . 
                
                    "<img height=\"210\" src=\"{$imgDiagrama}\">" . 
                
                "</td>" .
                    
                "<td colspan=\"1\">" . 
                
                    $imgIluminancia .
                
                "</td>" .

            "</tr>" .
            
            "<tr>" .
                    
                "<td>" . 
                
                "</td>" .

            "</tr>" .
            
            "<tr>" .
                    
                "<td>" . 
                
                "</td>" .

            "</tr>" .

        "</table>";

    
    return $result;

}





function criarAcabamentoLente($referencia, $acabamento, $nomeLente, $json, $lang) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];

    $titulo = $json->titulo->$lang;
    $corpo = $json->corpo->$lang;
    $lente = $json->lente->$lang;


    $nota = ""; 

    foreach($json->notas->comlink->links as $links) {

        if(in_array($refFamilia, $links->familias)) {

            $nota = $json->notas->comlink->$lang;

            if(isset($links->link->$lang)) {
                $notaLink = $links->link->$lang;
            } else {
                $notaLink = $links->link;
            }
        }
    }


    if(in_array($refFamilia, $json->notas->familias) && $nota === "") {
        $nota = $json->notas->semlink->$lang;
    }


	$notaAcabamentoLente = "";
    /*if(isset($nota) && isset($notaLink) && !empty($nota) && !empty($notaLink)) {

        $notaAcabamentoLente = 

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p><a style=\"color: #666666; text-decoration: none;\" href=\"$notaLink\">$nota</a></p>" .

            "</td>" . 

        "</tr>";

    } else if (isset($nota) && !isset($notaLink) && !empty($nota)) {

        $notaAcabamentoLente = 

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p>$nota</p>" .

            "</td>" . 

        "</tr>";

    } else {
        $notaAcabamentoLente = "";
    }*/



    $img = $acabamento["img"];
    $nomeAcabamento = $acabamento["nome"];



    $result = "";

    $result .=
    
    "<table nobr=\"true\">" . 

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<h2>$titulo</h2>" .
            
            "</td>" .
                
        "</tr>" .

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p><b>$corpo:</b> $nomeAcabamento<br>" .

                "<b>$lente:</b> $nomeLente</p>" .
            
            "</td>" .
                
        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .
        
        "<tr>" .
                
            "<td colspan=\"1\">" . 
            
                "<img src=\"{$img}\">" . 
            
            "</td>" .

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

            $notaAcabamentoLente .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

    "</table>";

    return $result;

}





function criarFixacao($referencia, $fixacao, $json, $notaMedidas, $lang) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];

    $titulo = $json->titulo->$lang;

    $nota = "";

    foreach($json->notas->comlink->links as $link) {

        if(in_array($refFamilia, $link->familias)) {

            $nota = $json->notas->comlink->$lang;

            if(isset($link->link->$lang)) {
                $notaLink = $link->link->$lang;
            } else {
                $notaLink = $link->link;
            }
        }
    }

    if($nota === "") {
        $nota = $json->notas->semlink->$lang;
    }


    if(isset($nota) && isset($notaLink) && !empty($nota) && !empty($notaLink)) {

        $notaFixacao = 

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p><a style=\"color: #666666; text-decoration: none;\"href=\"$notaLink\">$nota</a></p>" .

            "</td>" . 

        "</tr>";

    } else if (isset($nota) && !empty($nota)) {

        $notaFixacao = 

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p>$nota</p>" .

            "</td>" . 

        "</tr>";

    } else {
        $notaFixacao = "";
    }


    /*foreach($json->notaClips->notas as $nota) {
        foreach($nota->links as $links) {
            if(in_array($refFamilia, $links->familias)) {
                $nota = $nota->$lang;
                if($links->link->$lang) {
                    $notaLink = $links->link->$lang;
                } else {
                    $notaLink = $links->link;
                }
            }
        }
    }


    if(isset($nota) && isset($notaLink) && !empty($nota) && !empty($notaLink)) {
        $notaClips = "<a style=\"color: #666666; text-decoration: none;\"href=\"$notaLink\">$nota</a>";
    } else {
        $notaClips = $json->notaClips->default->$lang;
    }*/

    
    if(!empty($fixacao)) {

    $img = $fixacao["img"];
    $render = $fixacao["render"];
    $nomeFixacao = $fixacao["nome"];

    $result =
    
    "<table nobr=\"true\">" . 

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<h2>$titulo</h2>" .
            
            "</td>" .
                
        "</tr>" .

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p><b>$nomeFixacao</b></p>" .
            
            "</td>" .
                
        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .
        
        "<tr>" .
                
            "<td colspan=\"2\">" . 
            
                "<img src=\"{$img}\">" . 
            
            "</td>" .
                
            "<td colspan=\"1\" style=\"text-align: right;\">" . 
            
                "<img width=\"150\" src=\"{$render}\">" . 
            
            "</td>" .

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td colspan=\"3\">" . 

                "<p>$notaMedidas</p>" .
                
            "</td>" . 

        "</tr>" .

        $notaFixacao .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

        "<tr>" .

            "<td>" . 
                
            "</td>" . 

        "</tr>" .

    "</table>";

    } else {
        
        $result =
        
        "<table nobr=\"true\">" . 

            "<tr>" .

                "<td>" . 

                    "<h2>$titulo</h2>" .
                
                "</td>" .
                    
            "</tr>" .

            $notaFixacao .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .

        "</table>";

    }

    return $result;

}





function criarFonte($fonte, $lang) {

    
    $json = file_get_contents(dirname(__DIR__) . "/json/datasheet.json");
    $json = json_decode($json);
    $titulo = $json->fonte->titulo->$lang;
    $notaMedidas = $json->notaMedidas->$lang;


    if($fonte !== "") {

        $descricao = $fonte["descricao"];
        $img = $fonte["img"];
        $desenho = $fonte["desenho"];

        $result =
        
        "<table nobr=\"true\">" . 

            "<tr>" .

                "<td colspan=\"3\">" . 

                    "<h2>$titulo</h2>" .
                
                "</td>" .
                    
            "</tr>" .

            "<tr>" .

                "<td colspan=\"3\">" . 

                    "<p>$descricao</p>" .
                
                "</td>" .
                    
            "</tr>" .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .
            
            "<tr>" .
                    
                "<td colspan=\"2\">" . 
                
                "<img src=\"{$desenho}\">" . 

                "</td>" .
                    
                "<td colspan=\"1\">" . 
                
                    "<img height=\"210\" src=\"{$img}\">" . 
                
                "</td>" .

            "</tr>" .
            
            "<tr>" .

                "<td colspan=\"3\">" . 

                    "<p>$notaMedidas</p>" .
                    
                "</td>" . 

            "</tr>" .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .

        "</table>";

    } 

    return $result;

}





function criarLigacao($ligacao, $lang) {

    
    $json = file_get_contents(dirname(__DIR__) . "/json/datasheet.json");
    $json = json_decode($json);
    $titulo = $json->ligacao->titulo->$lang;
    $notaLigacao = $json->notaLigacao->$lang;

    if($ligacao !== "") {

        $img = $ligacao["img"];
        $descricaoLigacao = $ligacao["descricao"];

        $result =
        
        "<table nobr=\"true\">" . 

            "<tr>" .

                "<td colspan=\"4\">" . 

                    "<h2>$titulo</h2>" .
                
                "</td>" .
                    
            "</tr>" .

            "<tr>" .

                "<td colspan=\"4\">" . 

                    "<p>$descricaoLigacao</p>" .
                
                "</td>" .
                    
            "</tr>" .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .
            
            "<tr>" .
                    
                "<td colspan=\"2\">" . 
                
                    "<img height=\"210\" src=\"{$img}\">" . 
                
                "</td>" .

            "</tr>" .

            "<tr>" .

                "<td>" . 
                    
                "</td>" . 

            "</tr>" .

            "<tr>" .

                "<td colspan=\"4\">" . 

                    "<p>$notaLigacao</p>" .
                    
                "</td>" . 

            "</tr>" .

        "</table>";

    } else {
        
        $result =
        
        "<table nobr=\"true\">" . 

            "<tr>" .

                "<td>" . 

                    "<h2>$titulo</h2>" .
                
                "</td>" .
                    
            "</tr>" .

            "<tr>" .

                "<td>" . 

                    "<p>$notaLigacao</p>" .
                    
                "</td>" . 

            "</tr>" .

        "</table>";


    }

    return $result;

}


?>