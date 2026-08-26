<?php
include_once dirname(__FILE__) . "/funcoes/listarFamilias.php";

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style/style.css">
    <link rel="icon" type="image/x-icon" href="./img/favicon.ico">
    <script type="text/javascript" src="script.js"></script>
    <title>Ferramenta de construção de referências</title>
</head>
<body>

    <div class="main">

        <div>
            <img src="img/logos/tecit.png" style="width:150px">
        </div>

        <p>Selecione a família da lâmpada e, de seguida, todos os parâmetros da mesma</p>

        <form id="search">

        <div class="select-container">
            <label for="select-familia">Família do Produto: </label>
            <select id="select-familia" onchange="listaOpcoes();">
                <option value="0">Escolher a família</option>
                <?php listarFamilias(); ?>
            </select>
        </div>

        <div id="opcoes-produto">

            <div class="select-container">

                <label for="select-tamanho">Tamanho: </label>
                <select id="select-tamanho" onchange="atualizaInfo();"></select>

                <div class="opcional">
                    <div>
                        <label for="acrescimo">Acréscimo:</label>
                        <input type="number" id="acrescimo" class="input-com-unidade-mm" value="0" step="1">
                        <span class="unidade-input-mm" >mm</span>
                    </div>
                </div>

            </div>

            <div class="select-container">
                <label for="select-cor">Cor: </label>
                <select id="select-cor" onchange="atualizaInfo();"></select>
            </div>

            <div class="select-container">
                <label for="select-cri">CRI: </label>
                <select id="select-cri" onchange="atualizaInfo();"></select>
            </div>

            <div class="select-container">
                <label for="select-serie">Séries/Corrente: </label>
                <select id="select-serie" onchange="atualizaInfo();"></select>
            </div>

            <div class="select-container">
                <label for="select-lente">Acrílico: </label>
                <select id="select-lente" onchange="atualizaInfo();"></select>
            </div>

            <div class="select-container">
                <label for="select-acabamento">Acabamento: </label>
                <select id="select-acabamento" onchange="atualizaInfo();"></select>
            </div>

            <div class="select-container">
                <label for="select-cap">Cap/Base/Refletor: </label>
                <select id="select-cap" onchange="atualizaInfo();"></select>
            </div>

            <div class="select-container">
                
                <label for="select-opcao">Opção: </label>
                <select id="select-opcao" onchange="atualizaInfo();"></select>

                <div class="opcional" id="opcional-cabo-opcao">

                    <p>Características do cabo ➜</p>

                    <select id="select-conectorcabo">
                        <option value="0">Sem conector</option>
                        <option value="asqc2">ASQC2</option>
                        <option value="c1m">C1M</option>
                        <option value="dc24">DC24</option>
                        <option value="dcj">DCJ</option>
                        <option value="c2p">C2P</option>
                        <option value="c1f">C1F</option>
                    </select>

                    <div>
                        <input type="number" id="tamanhocabo" class="input-com-unidade" value="0" step="0.01">
                        <span class="unidade-input" >m</span>
                    </div>

                    <select id="select-tipocabo">
                        <option value="branco">Cabo branco</option>
                        <option value="preto">Cabo preto</option>
                        <option value="cinza">Cabo cinzento</option>
                        <option value="fio">Fio</option>
                        <option value="transparente">Fio transparente</option>
                    </select>
             
                </div>

            </div>

            <div class="select-container">

                <label for="select-tampa">Tampa: </label>
                <select id="select-tampa">
                    <option value="0">Normal</option>
                    <option value="1">Fricon</option>
                    <option value="2">Chapa</option>
                </select>
                
            </div>

            <div class="select-container">

                <label for="select-vedante">Vedante: </label>
                <select id="select-vedante">
                    <option value="5">5 mm</option>
                    <option value="0.5">0,5 mm</option>
                </select>

            </div>

            <div class="select-container">
                <label for="select-ip">IP: </label>
                <select id="select-ip">
                    <option value="0">Pré-definido</option>
                    <option value="IP20">IP20</option>
                    <option value="IP40">IP40</option>
                    <option value="IP42">IP42</option>
                    <option value="IP45">IP45</option>
                    <option value="IP60">IP60</option>
                    <option value="IP64">IP64</option>
                    <option value="IP65">IP65</option>
                    <option value="IP66">IP66</option>
                    <option value="IP67">IP67</option>
                </select>
            </div>
            
            <div class="select-container">
                <label for="select-fonte">Fonte:</label>
                <select id="select-fonte">
                    <option value="0">Nenhuma</option>
                    <option value="30w">30W</option>
                </select>
            </div>

            <div class="select-container">

                <label for="select-caboligacao">Cabo de ligação: </label>
                <select id="select-caboligacao" onchange="atualizaInfoCaboLigacao();">
                    <option value="0">Nenhum</option>
                    <option value="branco">Branco</option>
                    <option value="preto">Preto</option>
                    <option value="cinzento">Cinzento</option>
                    <option value="transparente">Transparente</option>
                </select>

                <div class="opcional" id="opcional-cabo-ligacao">

                    <p>Características do cabo ➜</p>

                    <select id="select-conectorligacao">
                        <option value="0">Sem conector</option>
                        <option value="asqc2">ASQC2</option>
                        <option value="c1m">C1M</option>
                        <option value="dc24">DC24</option>
                        <option value="dcj">DCJ</option>
                        <option value="c2p">C2P</option>
                    </select>

                    <div>
                        <input type="number" class="input-com-unidade" id="tamanhocaboligacao" value="0" step="0.01">
                        <span class="unidade-input" >m</span>
                    </div>

                </div>

            </div>

            <div class="select-container">
                <label for="select-fixacao">Fixação: </label>
                <select id="select-fixacao">
                    <option value="0">Nada</option>
                    <option value="clip180">Clip metálico fixo 180º</option>
                    <option value="cliprotativo">Clip metálico rotativo</option>
                    <option value="clipmagnetico">Clip PC Magnético</option>
                    <option value="suporte180">Suporte PVC 180º</option>
                    <option value="suporte45">Suporte PVC 45º</option>
                    <option value="suportecanto">Suporte PVC Canto</option>
                </select>
            </div>

            <div class="select-container">
                <label for="select-finalidade">Finalidade: </label>
                <select id="select-finalidade">
                    <option value="0">Geral</option>
                    <option value="retalho">Retalho</option>
                    <option value="padaria">Padaria</option>
                    <option value="queijo">Queijo</option>
                    <option value="peixe">Peixe</option>
                    <option value="talho">Carne</option>
                    <option value="talhoR9">CarneR9</option>
                    <option value="charcutaria">Charcutaria</option>
                    <option value="murallacteo">Mural Lácteo</option>
                    <option value="muralcongelados">Mural Congelados</option>
                    <option value="frutaslegumes">Frutas e Legumes</option>
                    <option value="vinho">Vinho</option>
                </select>
            </div>

            <div class="select-container">
                <label for="select-empresa">Empresa: </label>
                <select id="select-empresa">
                    <option value="0">Nenhum</option>
                    <option value="jordao">Jordão</option>
                    <option value="fricon">Fricon</option>
                    <option value="tensai">Tensai</option>
                </select>
            </div>

            <div class="select-container">
                <label for="select-idioma">Idioma: </label>
                <select id="select-idioma">
                    <option value="pt">PT</option>
                    <option value="en">EN</option>
                    <option value="es">ES</option>
                </select>
            </div>

            <div class="select-container">
                <label for="referencia">Referência: </label>
                <input type="text" id="referencia" readonly>
                <img src="img/clipboard-outline.svg" height="20" width="20" onclick="copy('referencia')">
            </div>

            <div class="select-container descricao">
                <label for="descricao">Descrição: </label>
                <input type="text" id="descricao" readonly>
                <img src="img/clipboard-outline.svg" height="20" width="20" onclick="copy('descricao')">
            </div>

            <div class="submit-container">
                
                <button type="button" onclick="gerarDatasheet();">Gerar Datasheet</button>
                
                <p id="erro"></p>
                
            </div>


        </div>

        </form>

    </div>
    
</body>
</html>