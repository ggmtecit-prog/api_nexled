<?php

// ligação BD
include_once dirname(__FILE__, 2) . "/config.php";



function infoDatasheet($IDProduto, $infoProduto) {

    $result = [];
    $result["erro"] = "";


    // TIPO DE PRODUTO 

    $referencia = $infoProduto["referencia"];

    $tipoProduto = tipoProduto($referencia);


    if(isset($tipoProduto["erro"]) && !empty($tipoProduto["erro"])) {

        $result["erro"] = $tipoProduto["erro"];

    } else {

        $tipo = $tipoProduto["tipo"];

        $jsonTamanhos = $tipoProduto["json"];
    }



    if($result["erro"] !== "") {

        return $result;
    
    } else {
    

        // Áreas do datasheet que podem existir ou não, conforme a família
        $digitos = getDigitos($infoProduto["referencia"]);
        $refFamilia = $digitos["refFamilia"];

        $json = file_get_contents(dirname(__DIR__) . "/json/datasheet.json");
        $json = json_decode($json);
            
        

        $nomeLente = getNomeLente($infoProduto["referencia"], $infoProduto["lente"], $infoProduto["idioma"]);

        $luminos = luminos($IDProduto, $infoProduto["referencia"]);

        $classeEnergetica = getClasseEnergetica($luminos, $infoProduto["referencia"]);

        $infoInicial = getInfoInicial($tipo, $IDProduto, $infoProduto, $luminos["ID_Led"]);

        $ip = getIP($IDProduto, $infoProduto["ip"]);

        $caracteristicas = getCaracteristicas($IDProduto, $ip["ip"], $infoProduto["idioma"], $refFamilia,$digitos["lente"]);

        $grafico = getGrafico($luminos["ID_Led"], $infoProduto["idioma"]);

        $diagrama = getDiagrama($IDProduto, $infoProduto["referencia"]);

        $imagemAcabamentoLente = getAcabamentoLente($tipo, $IDProduto, $infoProduto);


     
        if($tipo === "barra") {

			$desenho = desenhoTecnicoBarras($referencia, $infoProduto["acrescimo"], $infoProduto["opcao"], $infoProduto["tamanhocabo"], $infoProduto["conectorcabo"], $infoProduto["tampa"], $infoProduto["vedante"], $jsonTamanhos);


        } else {

            $desenho = desenhoTecnico($referencia, $IDProduto);

        }



        # ERROS

        $funcoesErro = [$nomeLente, $luminos, $classeEnergetica, $infoInicial, $ip, $caracteristicas, $desenho, $grafico, $diagrama, $imagemAcabamentoLente];

        // se foi selecionada fixação e essa família tem fixações datasheet.json -> fixacao
        if(in_array($refFamilia, $json->fixacao->familias) && $infoProduto["fixacao"] !== "0") {

            $fixacao = getFixacao($infoProduto["referencia"], $infoProduto["lente"], $infoProduto["tipocabo"], $infoProduto["tampa"], $infoProduto["fixacao"], $infoProduto["idioma"]);

            $funcoesErro[] = $fixacao;

        // se não foi selecionada uma fixação mas essa família tem fixações datasheet.json -> fixacao
        } else if(in_array("fixacao", $infoProduto)) {
            $fixacao = "";
        }

        // se foi selecionada uma fonte
        if($infoProduto["fonte"] !== "0") {
            $fonte = getFonte($infoProduto["fonte"], $infoProduto["idioma"]);
            $funcoesErro[] = $fonte;
        }

        // se foi selecionada um cabo de ligação
        if($infoProduto["caboligacao"] !== "0") {
            $ligacao = getLigacao($infoProduto["referencia"], $infoProduto["caboligacao"], $infoProduto["conectorligacao"], $infoProduto["tamanhocaboligacao"], $infoProduto["idioma"]);
            $funcoesErro[] = $ligacao;
        }


        
        foreach($funcoesErro as $funcao) {
            
            if(isset($funcao["erro"]) && !empty($funcao["erro"])) {

                if($result["erro"] === "") {
                    $result["erro"] .= "<b>Em falta:</b> <br>";
                }

                if(is_array($result["erro"])) {
                    $result["erro"] .= implode(",", $funcao["erro"]);
                } else {
                    $result["erro"] .= $funcao["erro"];
                }

            }
        }



        // Se não existem erros, cria o array com toda a informação 

        if($result["erro"] === "") {

            $result = [];

            $result["nomeLente"] = $nomeLente;

            $result["luminos"] = $luminos;

            $result["classeEnergetica"] = $classeEnergetica;

            $result["infoInicial"] = $infoInicial;

            $result["ip"] = $ip["ip"];

            $result["caracteristicas"] = $caracteristicas;

            $result["desenho"] = $desenho;

            $result["grafico"] = $grafico;

            $result["diagrama"] = $diagrama;

            $result["acabamentoLente"] = $imagemAcabamentoLente;

            if(isset($fixacao)) {
                $result["fixacao"] = $fixacao;
            }
        
            if(isset($fonte)) {
                $result["fonte"] = $fonte;
            }
        
            if(isset($ligacao)) {
                $result["ligacao"] = $ligacao;
            }

        }

    }

    return $result;

}





function tipoProduto($referencia) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];

    $json = file_get_contents(dirname(__DIR__) . "/json/correspondenciaProdutos.json");
    $json = json_decode($json);

    $tipo = "";

    foreach($json->produtos as $produtos) {

        if(in_array($refFamilia, $produtos->familias)) {

            $tipo = $produtos->tipo;

            if(isset($produtos->tamanhos)) {
                $jsonTamanhos = "/json/tamanhos/{$produtos->tamanhos}.json";
            } else {
                $jsonTamanhos = 0;
            }

        }
    }

    if($tipo !== "") {

        // inclui o ficheiro com as informações daquele tipo de produto específico
        include_once dirname(__DIR__) . "/funcoes/datasheets/$tipo.php";

        $result = [];
        $result["tipo"] = $tipo;
        $result["json"] = $jsonTamanhos;

    } else {
        $result = [];
        $result["erro"] = "tipo de produto não encontrado em /json/correspondenciaProdutos.json<br>";
    }

    return $result;

}





function getNomeLente($referencia, $lente, $lang) {

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $refLente = $digitos["lente"];


    $con = connectDBReferencias();

    $query = mysqli_query($con, "SELECT Acrilico.$lang FROM Acrilico, Familias WHERE Familias.codigo = '$refFamilia' AND Acrilico.familia = Familias.acrilico AND Acrilico.codigo = '$refLente' AND Acrilico.$lang != ''");

    closeDB($con);

    $result = "";

    if(mysqli_num_rows($query) > 0) {
        $result = mysqli_fetch_row($query);
        if($result !== "") {
            $result = implode("", $result);
        }
    } else {
        $result = $lente;
    }

    
    return $result;

}





function luminos($IDProduto, $referencia) {

    $referenciaLumino = substr($referencia, 0, 10);

    $con = connectDBLampadas();
    $query = mysqli_query($con, "SELECT * FROM Luminos WHERE ID LIKE '$IDProduto' AND ref LIKE '$referenciaLumino%'");
    closeDB($con);

    if(mysqli_num_rows($query) != 0) {

        $result = [];

        while($row = mysqli_fetch_assoc($query)) {
            foreach($row as $key => $value) {
                $result[$key] = $value;
            }
        }

    } else {
        $result = [];
        $result["erro"] = "Não foram encontradas as informações do produto com ID $IDProduto e ref $referenciaLumino na view Luminos <br>";
    }

    return $result;
    
}





function getClasseEnergetica($luminos, $referencia) {

    $json = file_get_contents(dirname(__DIR__) . "/json/datasheet.json");
    $json = json_decode($json);
    $json = $json->luminotecnicas;


    $digitos = getDigitos($referencia);
    $refLente = $digitos["lente"];


    if((strval($luminos["rel_flux"]) !== "1.000") || empty($luminos["rel_flux"])){
        $fluxo = round($luminos["nleds"] * $luminos["lumens"] * $luminos["rel_flux"] * (1 - $luminos["att_temp"]) * (1 - $luminos["A" . $refLente]));
    } else {
        $fluxo = round($luminos["nleds"] * $luminos["lumens"] * $luminos["corrente"] / $luminos["serie"] / $luminos["correntelumens"] * (1 - $luminos["att_temp"]) * (1 - $luminos["A" . $refLente]));
    }


    $fonteluz = strval($luminos["fonteluz"]);
    if(isset($json->fonteLuz->$fonteluz)) {
        $valorFL = $json->fonteLuz->$fonteluz;
    } else {
        $valorFL = "1";
    }

    $valorClasse = round(($fluxo / $luminos["potencia"]) * $valorFL);

    foreach($json->classeEnergetica as $classeEnergetica) {

        $min = $classeEnergetica->min;

        if(!isset($classeEnergetica->max)) {
            if($valorClasse >= $min){
                $imgClasse = $classeEnergetica->src;
            }
        } else {
            $max = $classeEnergetica->max;
            if($valorClasse >= $min && $valorClasse <= $max){
                $imgClasse = $classeEnergetica->src;
            }
        }
    }

    $classe = dirname(__DIR__) . "/img/classe-energetica/$imgClasse.svg";

    if(file_exists($classe)) {
        $result = $classe;
    } else {
        $result = [];
        $result["erro"] = "Imagem para a classe energética $imgClasse <br>";

    }

    return $result;

}





function getInfoInicial($tipoProduto, $IDProduto, $infoProduto, $led) {

    $result = [];
    $result["erro"] = "";


    $lang = $infoProduto["idioma"];
    $finalidade = $infoProduto["finalidade"];


    $digitos = getDigitos($infoProduto["referencia"]);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];

    $lente = strtolower($infoProduto["lente"]);

    $imagemProduto = 0;



    // excecao Barra
    if($tipoProduto === "barra") {

        $imagens = imagemInicial($infoProduto["referencia"], $infoProduto["lente"], $infoProduto["acabamento"], $infoProduto["conectorcabo"], $infoProduto["tipocabo"], $infoProduto["tampa"]);

    // se não é Barra
    } else {

        // exceção dynamic
        if($refFamilia === "48") {
            
            $imagens = imagemInicial($IDProduto, $infoProduto["referencia"], $infoProduto["acabamento"]);

        } else {

            $imagens = imagemInicial($infoProduto["lente"], $infoProduto["referencia"]);
            
        }
    }
	
    $pasta = $imagens["pasta"];
    $imagensPossiveis = $imagens["imagens"];

    foreach($imagensPossiveis as $imagem) {
        $src = dirname(__DIR__, 1) . $pasta . $imagem;
        if($imagemProduto === 0) {
            $imagemProduto = existeImagem($src);
        }
    }

    if($imagemProduto === 0) {
        $result["erro"] .= "imagem do produto => $tipoProduto $pasta => " . implode(" ou ", $imagensPossiveis) . "<br>";
    } else {
        $imagemProduto = $imagemProduto;
    }    


    
    if($refFamilia === "48") {
        $id = explode("/", $IDProduto);
        $id = $id[0] . "_". $id[1];
    } else {
        $id = explode("/", $IDProduto);
        $id = $id[0] . "_". $id[1] . "_". $id[3];
    }

    
    $descricaoProduto = "";

    $jsonDescricao= file_get_contents(dirname(__DIR__) . "/json/descricao/produtos.json");
    $jsonDescricao = json_decode($jsonDescricao);

    if(isset($jsonDescricao->descricao->$id->$lang)) {
        foreach($jsonDescricao->descricao->$id->$lang as $linha) {
            $descricaoProduto .= "$linha<br><br>";
        }
    }

    if($descricaoProduto === "") {
        $result["erro"] .= "descrição do produto no ficheiro /json/descricao/produtos.json => $id <br>";
    }
    
    

    $descricaoLED = "";

    $jsonLED = file_get_contents(dirname(__DIR__) . "/json/descricao/leds.json");
    $jsonLED = json_decode($jsonLED);

    foreach($jsonLED->leds as $infoLed) {

        foreach($infoLed->led as $leds) {

            if($leds === $led) {

                $descricaoLED .= $jsonLED->descricao->$lang . " " . $infoLed->$lang . "<br><br>";

                if($infoLed->scdm && $infoLed->scdm === "1") {

                    $descLED = "";

                    foreach ($jsonLED->SDCM->excecoes as $excecao) {

                        if (in_array($refFamilia, $excecao->familias)) {
                            $descLED = $excecao->$lang . "<br><br>";
                        }

                    }

                    if($descLED === "") {
                        $descLED = $jsonLED->SDCM->default->$lang . "<br><br>";
                    }

                    $descricaoLED .= $descLED;

                }
            }
        }
    }
    
    if($descricaoLED === "") {
        $result["erro"] .= "descrição do led no ficheiro /json/descricao/leds.json => $led <br>";
    }
    


    $descricaoFinalidade = "";

    if($finalidade !== "0") {

        $jsonFinalidade = file_get_contents(dirname(__DIR__) . "/json/descricao/finalidades.json");
        $jsonFinalidade = json_decode($jsonFinalidade);

        
        if(isset($jsonFinalidade->descricao->$finalidade)) {

            if(isset($jsonFinalidade->descricao->$finalidade->$lang)) {
                $descricaoFinalidade .= $jsonFinalidade->descricao->$finalidade->$lang . "<br><br>";
            }

            if(isset($jsonFinalidade->descricao->$finalidade->safetyfood) && $jsonFinalidade->descricao->$finalidade->safetyfood === "1") {
                $descricaoFinalidade .= $jsonFinalidade->descricao->safetyfood->$lang . "<br><br>";
            }
        }


        if($descricaoFinalidade === "") {
            $result["erro"] .= "descrição da finalidade no ficheiro /json/descricao/finalidades.json => $finalidade <br>";
        }

    }



    $descricaoClasse = "";
    
    $jsonClasse = file_get_contents(dirname(__DIR__) . "/json/descricao/classe.json");
    $jsonClasse = json_decode($jsonClasse);

    if(isset($jsonClasse->classe->$lang)) {
        $descricaoClasse .= $jsonClasse->classe->$lang . "<br><br>";
    }

    if($descricaoClasse === "") {
        $result["erro"] .= "descrição da classe no ficheiro /json/descricao/classe.json <br>";
    }



    if($result["erro"] === "") {

        $result = array(
            "imagem" => $imagemProduto,
            "descricao" => $descricaoProduto . $descricaoLED . $descricaoFinalidade . $descricaoClasse
        );

    }

    return $result;

}





function getIP($IDProduto, $ip) {

    $result = [];
    $result["erro"] = "";

    if($ip === "0") {

        $con = connectDBLampadas();

        $query = mysqli_query($con, "SELECT valor_pt FROM caracteristicas WHERE ID = '$IDProduto' AND (texto_pt = 'Grau de protecção' OR texto_pt = 'Grau de proteção')");

        closeDB($con);

        if(mysqli_num_rows($query) > 0) {
            $result = mysqli_fetch_row($query);
            $result["ip"] = implode("", $result);
            $result["ip"] = str_replace(' ', '', $result["ip"]);
        } else {
            $result["erro"] .= "ip do produto $IDProduto na BD tecit_lampadas tabela Características <br>";
        }

    } else {
        $result["ip"] = $ip;
    }



    $src = dirname(__DIR__) . "/img/icones/" . strtolower($result["ip"]) . ".svg";

    if(!file_exists($src)) {
        $result["erro"] .= "não foi encontrado o icone do IP " . $result["ip"] . "<br>";
    }

    return $result;

}





function getCaracteristicas($IDProduto, $ip, $lang, $refFamilia, $lente) {

    $id = explode("/", $IDProduto)[0];

    $con = connectDBLampadas();

    if($lang === "pt") {
        $query = mysqli_query($con, "SELECT texto_pt, valor_pt FROM caracteristicas WHERE ID = '$IDProduto' AND texto_pt NOT LIKE 'data' AND texto_pt NOT LIKE 'versao' AND texto_pt NOT LIKE 'Dimensões%' AND texto_pt NOT LIKE '$id%' ORDER BY indice ASC");
    } else {
        $query = mysqli_query($con, "SELECT texto_pt, valor_pt, texto_$lang, valor_$lang FROM caracteristicas WHERE ID = '$IDProduto' AND texto_pt NOT LIKE 'data' AND texto_pt NOT LIKE 'versao' AND texto_pt NOT LIKE 'Dimensões%' AND texto_pt NOT LIKE '$id%' ORDER BY indice ASC");
    }

    closeDB($con);

    $con = connectDBInf();

    $queryBeam = mysqli_query($con,"SELECT beam FROM angulos_lente WHERE familia = '$refFamilia' AND lente = '$lente'");

    $queryField = mysqli_query($con,"SELECT field FROM angulos_lente WHERE familia = '$refFamilia' AND lente = '$lente'");

    closeDB($con);

    if(mysqli_num_rows($queryBeam) != 0) {
        $row = mysqli_fetch_assoc($queryBeam);
        $beam = $row["beam"];
    } else {
        $beam = NULL;
    }

    if(mysqli_num_rows($queryField) != 0) {
        $row = mysqli_fetch_assoc($queryField);
        $field = $row["field"];
    } else {
        $field = NULL;
    }

    if(mysqli_num_rows($query) != 0) {

        $result = [];

        while($row = mysqli_fetch_assoc($query)) {
            
            if ($row["texto_$lang"] !== NULL) {
                $texto = strval($row["texto_$lang"]);
            } else {
                $texto = strval($row["texto_pt"]);
            }

            if ($row["valor_$lang"] !== NULL && $row["valor_$lang"] !== "") {
                $valor = strval($row["valor_" . $lang]);
            } else {
                $valor = strval($row["valor_pt"]);
            }

            if($row["texto_pt"] === "Feixe de luz" && $beam !== NULL) {
                $valor = strval($beam);
            }

            if($row["texto_pt"] === "Abertura de luz" && $field !== NULL) {
                $valor = strval($field);
            }

            if($row["texto_pt"] === "Grau de protecção") {
                $result[$texto] = $ip;
            } else {
                $result[$texto] = $valor;
            }
        }

    } else {
        $result = [];
        $result["erro"] = "Características do produto com ID $IDProduto na tabela Caracteristicas <br>";
    }

    return $result;

}





function getDigitos($referencia) {

    $digitos = array (
        "refFamilia" => substr($referencia, 0, 2), 
        "tamanho" => substr($referencia, 2, 4),
        "led" => substr($referencia, 6, 3),
        "serie" => substr($referencia, 9, 1),
        "lente" => substr($referencia, 10, 1),
        "acabamento" => substr($referencia, 11, 2),
        "cap" => substr($referencia, 13, 2),
        "opcao" => substr($referencia, 15, 5)
    );

    return $digitos;

}





function getIDProduto($referencia) {

    $refLuminos = substr($referencia, 0, 10);

    $con = connectDBLampadas();

    $query = mysqli_query($con, "SELECT ID FROM Luminos WHERE ref = '$refLuminos'");

    closeDB($con);

    if(mysqli_num_rows($query) != 0) {
        $result = mysqli_fetch_row($query);
        $result = implode("", $result);
    } else {
        $result = [];
        $result["erro"] = "ID não encontrado <br>";
    }

    return $result;

}





function getIDProdutoDynamic($referencia, $cap) {

    $refLuminos = substr($referencia, 0, 10);

    if($cap === "0") {
        $tipoDynamic = "projetores";
    } else if($cap === "1") {
        $tipoDynamic = "campanulas";
    }

    $con = connectDBLampadas();

    $query = mysqli_query($con, "SELECT ID FROM Luminos WHERE ref = '$refLuminos' AND ID LIKE '%$tipoDynamic%'");

    closeDB($con);

    if(mysqli_num_rows($query) != 0) {
        $result = mysqli_fetch_row($query);
        $result = implode("", $result);
    } else {
        $result = [];
        $result["erro"] = "ID não encontrado <br>";
    }

    return $result;

}





function criarFooter($IDProduto, $lang) {

    $data = getData($IDProduto);

    $versao = getVersao($IDProduto);

    $texto = "";

    $json = file_get_contents(dirname(__DIR__) . "/json/datasheet.json");
    $json = json_decode($json);

    foreach($json->footer as $nota) {
        $texto .= $nota->$lang;
    }

    if ((str_contains($texto, "[data]") !== false) && (str_contains($texto, "[versao]") !== false)) {
        $texto = str_replace(array("[data]", "[versao]"), array($data, $versao), $texto);
    }

    $result = $texto;

    return $result;

}





function getData($IDProduto){

    $con = connectDBLampadas();

    $query = mysqli_query($con, "SELECT valor_pt FROM caracteristicas WHERE texto_pt LIKE 'data' AND ID LIKE '$IDProduto'");

    closeDB($con);

    $result = mysqli_fetch_row($query);
    $result = implode("", $result);

    return $result;

}





function getVersao($IDProduto){

    $con = connectDBLampadas();

    $query = mysqli_query($con, "CALL versao('$IDProduto', @v)");

    $query = mysqli_query($con, "SELECT @v");

    closeDB($con);

    $versao = mysqli_fetch_array($query);

    return $versao["@v"];

}





function getGrafico($led, $lang) {

    $result = [];
    $result["erro"] = "";


    if($led) {
    
        $json = file_get_contents(dirname(__DIR__) . "/json/descricao/leds.json");
        $json = json_decode($json);

        $descricaoLED = "";

        foreach($json->leds as $graficos) {
            foreach($graficos->led as $leds) {
                if($leds === $led) {
                    $descricaoLED = $graficos->$lang;
                }
            }
        }

        if($descricaoLED === "") {
            $result["erro"] .= "descrição do LED $led em /json/leds.json<br>";
        }

    } else {
        $result["erro"] .= "ID do led não encontrado na view Luminos <br>";
    }



    $graficoCor = 0;

    $src = dirname(__DIR__) . "/img/temperaturas/" . $led;
    if($graficoCor === 0) {
        $graficoCor = existeImagem($src);
    }

    if($graficoCor === 0) {
        $result["erro"] .= "gráfico $led => $src<br>";
    }

    


    if ($result["erro"] === "") {

        $result["led"] = $led . " - " . $descricaoLED;
        $result["grafico"] = $graficoCor;

    }


    return $result;

}




function getDiagrama($IDProduto, $referencia) {

    $result = [];
    $result["erro"] = "";


    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];



    $lente = $digitos["lente"];

    // exceção dynamic
    if($refFamilia === "48") {

        $id = explode("/", $IDProduto);
        $tipoDynamic = $id[1];
        
        $diagrama = "/img/$refFamilia/$tipoDynamic/diagramas/$lente.svg";

        $iluminancia = "/img/$refFamilia/$tipoDynamic/diagramas/i/$lente.svg";

    } else {

        $diagrama = "/img/$refFamilia/diagramas/$lente.svg";

        $iluminancia = "/img/$refFamilia/diagramas/i/$lente.svg";

    }



    if(file_exists(dirname(__DIR__) . $diagrama)) {

        $diagrama = dirname(__DIR__) . $diagrama;

        if(file_exists(dirname(__DIR__) . $iluminancia)) {
            $iluminancia = dirname(__DIR__) . $iluminancia;
        } else {
            $iluminancia = "x";
        }

    } else {
        $result["erro"] = "não foi encontrado o diagrama $diagrama <br>";
    }



    if ($result["erro"] === "") {
        $result["diagrama"] = $diagrama;
        $result["iluminancia"] = $iluminancia;
    }

    return $result; 

}





// se o produto não for Barra
function desenhoTecnico($referencia, $IDProduto) {

    $result = [];
    $result["erro"] = "";

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $tamanho = $digitos["tamanho"];


    // exceção dynamic
    if($refFamilia === "48") {
        $id = explode("/", $IDProduto);
        $tipoDynamic = $id[1];
        $pasta = "/img/$refFamilia/$tipoDynamic/desenhos/";
    } else {
        $pasta = "/img/$refFamilia/desenhos/";
    }

    $desenhosPossiveis = array(
        $tamanho
    );


    $desenhoProduto = 0;

    foreach($desenhosPossiveis as $desenhos) {
        $src = dirname(__DIR__, 1) . $pasta . $desenhos;
        if($desenhoProduto === 0) {
            $desenhoProduto = existeImagem($src);
        }
    }

    if($desenhoProduto === 0) {
        $result["erro"] .= "desenho do produto => $pasta => " . implode(" ou ", $desenhosPossiveis) . "<br>";
    } else {
        $desenho = $desenhoProduto;
    }



    $con = connectDBLampadas();

    $query = mysqli_query($con, "SELECT valor_pt FROM caracteristicas WHERE ID = '$IDProduto' AND texto_pt LIKE 'Dimensões%'");

    closeDB($con);

    if(mysqli_num_rows($query) > 0) {
        $dimensoes = mysqli_fetch_assoc($query);
        $dimensoes = $dimensoes["valor_pt"];
    } else {
        $result["erro"] .= "dimensões do desenho <br>";
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

        $medidas = explode(" ", $dimensoes);

        foreach($medidas as $medida) {

            $dimensao = explode(":", $medida);

            if(!empty($dimensao[0]) && !empty($dimensao[1])){
                $result[$dimensao[0]] = $dimensao[1];
            }

        }

    }

    return $result;

}





function getAcabamentoLente($tipoProduto, $IDProduto, $infoProduto) {

    $result = [];
    $result["erro"] = "";



    $lang = $infoProduto["idioma"];

    $digitos = getDigitos($infoProduto["referencia"]);
    $refFamilia = $digitos["refFamilia"];
    $codigoAcabamento = $digitos["acabamento"];

    

    $con = connectDBReferencias();

    $query = mysqli_query($con, "SELECT Acabamento.desc, Acabamento.$lang FROM Acabamento, Familias WHERE Acabamento.familia = Familias.acabamento AND Familias.codigo = '$refFamilia' AND Acabamento.codigo = '$codigoAcabamento' AND (Acabamento.desc IS NOT NULL AND Acabamento.desc != '' OR Acabamento.$lang IS NOT NULL AND Acabamento.$lang != '')");

    closeDB($con);

    $nomeAcabamento = "";

    if(mysqli_num_rows($query) > 0) {

        $row = mysqli_fetch_assoc($query);

        $nomeAcabamento = "";

        if ($row["desc"] !== "" && $row["desc"] !== NULL) {
            $nomeAcabamento = $row["desc"];
        } 
        
        if($row[$lang] !== "" && $row[$lang] !== NULL){
            $nomeAcabamento .= " - " . $row[$lang];
        } 

    } else {
        $result["erro"] .= "descrição do acabamento com o código $codigoAcabamento na BD tecit_referencias tabela Acabamento";
    }



    // exceção barras
    if($tipoProduto === "barra") {

        $imagens = imagemAcabamentoLente($infoProduto["referencia"], $infoProduto["lente"], $infoProduto["acabamento"], $infoProduto["tampa"]);
    
    // exceção Dynamic
    } else if ($tipoProduto === "dynamic") {

        $imagens = imagemAcabamentoLente($IDProduto, $infoProduto["referencia"], $infoProduto["acabamento"]);

    } else {

        $imagens = imagemAcabamentoLente($infoProduto["referencia"], $infoProduto["lente"], $infoProduto["acabamento"]);

    }

    

    $pasta = $imagens["pasta"];
    $imagensPossiveis = $imagens["imagens"];

    $imgAcabamento = 0;

    foreach($imagensPossiveis as $imagens) {
        $src = dirname(__DIR__, 1) . $pasta . $imagens;
        if($imgAcabamento === 0) {
            $imgAcabamento = existeImagem($src);
        }
    }

    if($imgAcabamento === 0) {
        $result["erro"] = "imagem de acabamento => $pasta => " . implode(" ou ", $imagensPossiveis) . "<br>";
    } else {
        $imagemAcabamento = $imgAcabamento;
    }

    if($result["erro"] === "") {
        $result["img"] = $imagemAcabamento;
        $result["nome"] = $nomeAcabamento;
    }
    
    return $result;

}





function getFixacao($referencia, $lente, $tipocabo, $tampa, $fixacao, $lang) {

    $result = [];
    $result["erro"] = "";

    $digitos = getDigitos($referencia);
    $refFamilia = $digitos["refFamilia"];
    $cap = $digitos["cap"];



    $pasta = "/img/$refFamilia/fixacao/";

    $imagensPossiveis = array(
        $lente . "_" . $tipocabo . "_" . $tampa . "_" . $fixacao,
        $lente . "_" . $cap . "_" . $fixacao,
        $tampa . "_" . $fixacao,
        $lente . "_" . $fixacao,
        $fixacao
    );


    $imgFixacao = 0;
    $renderFixacao = 0;

    foreach($imagensPossiveis as $imagem) {
        $src = dirname(__DIR__) . $pasta . $imagem;
        if($imgFixacao === 0) {
            $imgFixacao = existeImagem($src);
        }
    }

    foreach($imagensPossiveis as $imagem) {
        $src = dirname(__DIR__) . $pasta . $imagem . "_render";
        if($renderFixacao === 0) {
            $renderFixacao = existeImagem($src);
        }
    }


    if($imgFixacao === 0) {
        $result["erro"] .= "imagem de fixação => $pasta => " . implode(" ou ", $imagensPossiveis) . "<br>";
    }

    if($renderFixacao === 0) {
        $result["erro"] .= "render de fixação => $pasta => " . implode("_render ou ", $imagensPossiveis) . "<br>";
    }



    $json = file_get_contents(dirname(__DIR__) . "/json/fixacao.json");
    $json = json_decode($json);

    if(isset($json->fixacao->$fixacao->$lang)) {
        $nomeFixacao = $json->fixacao->$fixacao->$lang;
    } else {
        $result["erro"] .= "descrição da fixação com o código $fixacao em /json/fixacao.json";
    }



    if($result["erro"] === "") {
        $result["img"] = $imgFixacao;
        $result["render"] = $renderFixacao;
        $result["nome"] = $nomeFixacao;
    }
    

    return $result;

}





function getFonte($fonte, $lang) {

    if($fonte !== "0") {

        $result = [];
        $result["erro"] = "";


        $pasta = "/img/fontes/";

        $img = 0;
        $desenho = 0;

        $src = dirname(__DIR__) . $pasta . $fonte;
        if($img === 0) {
            $img = existeImagem($src);
        }

        if($img === 0) {
            $result["erro"] .= "imagem da fonte => $pasta => $fonte<br>";
        }
        

        $src = dirname(__DIR__) . $pasta . $fonte . "_desenho";
        if($desenho === 0) {
            $desenho = existeImagem($src);
        }

        if($desenho === 0) {
            $result["erro"] .= "desenho da fonte => $pasta => $fonte<br>";
        }


       
        $json = file_get_contents(dirname(__DIR__) . "/json/fontes.json");
        $json = json_decode($json);
    
        if(isset($json->fontes->$fonte)) {
            $descricao = $json->fontes->$fonte->$lang;
        } else {
            $result["erro"] .= "descrição da fonte $fonte em /json/fontes.json <br>";
        }
        
        
        if($result["erro"] === "") {
            $result["descricao"] = $descricao;
            $result["img"] = $img;
            $result["desenho"] = $desenho;
        }
    
    } else {

        $result = "";

    }

    return $result;

}





function getLigacao($referencia, $caboligacao, $conectorligacao, $tamanhocaboligacao, $lang) {

    if($caboligacao !== "0") {

        $result = [];
        $result["erro"] = "";


        $digitos = getDigitos($referencia);
        $refFamilia = $digitos["refFamilia"];
        $cap = $digitos["cap"];


        $imagensPossiveis = array(
            $conectorligacao . "_" . $caboligacao,
            $cap . "_" . $caboligacao
        );
        
        
        $pasta = "/img/$refFamilia/ligacao/";

        $imgLigacao = 0;

        foreach($imagensPossiveis as $imagem) {
            $src = dirname(__DIR__) . $pasta . $imagem;
            if($imgLigacao === 0) {
                $imgLigacao = existeImagem($src);
            }
        }

        if($imgLigacao === 0) {
            $result["erro"] .= "imagem de ligação => $pasta => $imagensPossiveis[0] ou $imagensPossiveis[1]<br>";
        }


       
        $json = file_get_contents(dirname(__DIR__) . "/json/ligacao.json");
        $json = json_decode($json);
    
        if(isset($json->ligacao->id->$caboligacao)) {
            $idLigacao = $json->ligacao->id->$caboligacao;
        } else {
            $result["erro"] .= "ID do cabo de ligação $caboligacao em /json/ligacao.json";
        }


        if(isset($idLigacao)) {

            $idLigacao = str_replace("[conector]", $conectorligacao, $idLigacao);

            $con = connectDBLampadas();
            $query = mysqli_query($con, "SELECT ID, desc_$lang FROM descricaoCabos WHERE ID = '$idLigacao'");
            closeDB($con);


            $descricaoCabo = "";

            if(mysqli_num_rows($query) > 0) {

                $row = mysqli_fetch_assoc($query);

                if ($row["desc_$lang"] !== "" && $row["desc_$lang"] !== NULL) {
                    $descricaoCabo = $row["desc_$lang"];
                } 

                
                $descricaoCabo .= "<br>" . $json->ligacao->tamanho->$lang;
                $descricaoCabo = str_replace("[tamanho]", $tamanhocaboligacao, $descricaoCabo);

            } else {

                $result["erro"] .= "descrição do cabo $idLigacao na BD tecit_lampadas tabela descricaoCabos";

            }

        }

        
        
        if($result["erro"] === "") {
            $result["img"] = $imgLigacao;
            $result["descricao"] = $descricaoCabo;
        }
    


    } else {

        $result = "";

    }

    return $result;

}





function existeImagem($src) {

    $extensoes = [".png", ".jpg", ".jpeg", ".svg"];

    $existeImagem = 0;
	
	$imagem = "";

    foreach($extensoes as $extensao) {

        if(file_exists($src . $extensao)) {

            $existeImagem = 1;
			
			if($extensao === ".png") {

				// Carregar a imagem original
				$img = imagecreatefrompng($src . $extensao);

				// Criar uma nova imagem com fundo branco
				$largura = imagesx($img);
				$altura = imagesy($img);
				$novaImagem = imagecreatetruecolor($largura, $altura);
				$branco = imagecolorallocate($novaImagem, 255, 255, 255); // Cor branca
				imagefill($novaImagem, 0, 0, $branco);

				// Copiar a parte não transparente da imagem original para a nova imagem
				imagecopy($novaImagem, $img, 0, 0, 0, 0, $largura, $altura);

				// Gravar a nova imagem com fundo branco
				$imageComFundoBranco = $src . $extensao;
				imagepng($novaImagem, $imageComFundoBranco);

				// Libertar a memória
				imagedestroy($img);
				imagedestroy($novaImagem);

				$imagem = $imageComFundoBranco;

			} else {

				$imagem = $src . $extensao;
                
			}
			
    	}
	}

    if($existeImagem === 0) {
        $imagem = 0;
    }

    return $imagem;

}

?>