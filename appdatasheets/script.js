let erro = 0;


function listaOpcoes() {

    let opcoesProduto = document.getElementById("opcoes-produto");
    opcoesProduto.style.display = "none";
	
    let opcionais = document.querySelectorAll(".opcional");
    opcionais.forEach(element => {
            element.style.display = "none";

    });

    excecoes();

    resetForm();

    let familia = document.getElementById("select-familia").value;

    let caracteristicas = ["tamanho", "cor", "cri", "serie", "lente", "acabamento", "cap", "opcao"];

    caracteristicas.forEach(caracteristica => {
        let select = document.getElementById("select-" + caracteristica);
        select.innerHTML = "";
    });

    fetch("./funcoes/getOpcoesProduto.php", {
        method: "POST",
        body: JSON.stringify(familia),
        headers: {"Content-type": "application/json; charset=UTF-8"}
    })

    .then(response => response.json())
    
    .then(result => {

        let i = 0;
        
        let partesReferencia = [
            ["familia", 2],
            ["tamanho", 4],
            ["cor", 2],
            ["cri", 1],
            ["serie", 1],
            ["lente", 1],
            ["acabamento", 2],
            ["cap", 2],
            ["opcao", 2]
        ];

        caracteristicas.forEach(caracteristica => {

            let select = document.getElementById("select-" + caracteristica);

            let valores = result[caracteristica];

            valores.forEach(valor => {

                // se é string => só código
                if(typeof valor === "string") {

                    ref = valor;

                    partesReferencia.forEach(parte => {

                        if(parte[0] === caracteristica) {
                            while (ref.length < parte[1]) {
                                ref = "0" + ref;
                            }
                        }
                    });

                    let option = document.createElement("option");
                    option.setAttribute("value", valor);
                    option.setAttribute("ref", ref);
                    option.innerHTML = valor;
                    select.append(option);

                    if(!select.getAttribute("value") && !select.getAttribute("ref")) {
                        select.setAttribute("value", valor);
                        select.setAttribute("ref", ref);
                    }

                // se é array => tem nome + código + desc
                } else {

                    ref = valor[1];

                    partesReferencia.forEach(parte => {

                        if(parte[0] === caracteristica) {

                            while (ref.length < parte[1]) {
                                ref = "0" + ref;
                            }

                        }
                    });

                    let option = document.createElement("option");
                    option.setAttribute("value", valor[1]);
                    option.setAttribute("ref", ref);

                    if(valor[2]) {
                        option.setAttribute("desc", valor[2]);
                    }

                    option.innerHTML = valor[0];
                    select.append(option);

                    if(!select.getAttribute("value") && !select.getAttribute("ref")) {
                        select.setAttribute("value", valor[1]);
                        select.setAttribute("ref", ref);
                    }
                }
            });

            if(i < (caracteristicas.length - 1)) {
                i++;
            } else {
                opcoesProduto.style.display = "block";
            }
            
        });

        atualizaInfo();
        
    });

}


function resetForm() {

    let container = document.getElementById("opcoes-produto");

    container.querySelectorAll("input").forEach(input => {
        switch (input.type) {
        case "checkbox":
        case "radio":
            input.checked = false;
            break;
        default:
            input.value = input.defaultValue || "";
        }
    });

    container.querySelectorAll("select").forEach(select => {
        select.selectedIndex = 0;
    });

    container.querySelectorAll("textarea").forEach(textarea => {
        textarea.value = textarea.defaultValue || "";
    });

}

function atualizaInfo() {

    let referencia = "";

    let descricaoPartes = "";

    let partesRef = ["familia", "tamanho", "cor", "cri", "serie", "lente", "acabamento", "cap", "opcao"];

    let partesDesc = ["lente", "acabamento", "cap", "opcao"];

    partesRef.forEach(parte => {

        let select = document.getElementById("select-" + parte);

        let selectedOption = select.options[select.selectedIndex];

        referencia += selectedOption.getAttribute("ref");



        let barras = ["11", "32", "55", "58"];
        let familia = document.getElementById("select-familia").value;

        if(parte === "opcao" && barras.includes(familia)) {

            let opcional = document.getElementById("opcional-cabo-opcao");

            let descOpcao = selectedOption.getAttribute("desc");

            // se value = 0
            if (!descOpcao) {

                if(opcional.style.display !== "none") {
                    opcional.style.display = "none";
                }

                return;

            }

            if(opcional.style.display !== "flex") {
                opcional.style.display = "flex";
            }

            let conector = document.getElementById("select-conectorcabo");
            let tamanho = document.getElementById("tamanhocabo");
            let tipoCabo = document.getElementById("select-tipocabo");

            conector.value = "0";
            tamanho.value = 0;
            tipoCabo.value = "branco";

            Array.from(conector.options).some(option => {

                if (option.value !== "0" && descOpcao.toLowerCase().includes(option.value)) {

                    conector.value = option.value;

                    let sizeMatch = descOpcao.match(/\s(\d+(?:\.\d+)?)(?:m)?(?:P)?$/i);

                    tamanho.value = sizeMatch ? sizeMatch[1] : 0;

                    if (descOpcao.trim().endsWith("P")) {
                        tipoCabo.value = "preto";
                    } else {
                        tipoCabo.value = "branco";
                    }

                    return true;

                }

                return false;

            });

        }

    });

    partesDesc.forEach(parte => {

        let select = document.getElementById("select-" + parte);

        let selectValue = select.value;

        let option = document.querySelector('#select-' + parte + ' option[value="' + selectValue + '"]');

        if(option.getAttribute("desc"))         
        descricaoPartes += " " + option.getAttribute("desc");

    });


    document.getElementById("referencia").value = referencia;

    fetch("./funcoes/getDescricaoProduto.php", {
        method: "POST",
        body: JSON.stringify(referencia),
        headers: {"Content-type": "application/json; charset=UTF-8"}
    })

    .then(response => response.json())
    
    .then(descricao => {

        if(descricao === "erro") {

            erro = 1;

            document.getElementById("descricao").value = "";

            document.getElementById("erro").innerHTML = "A combinação da família, tamanho, cor, CRI e série não exite na view Luminos";

        } else {

            erro = 0;

            document.getElementById("erro").innerHTML = "";

            document.getElementById("descricao").value = descricao + descricaoPartes;
            
        }

    });

    excecoes();

}



function atualizaInfoCaboLigacao() {

    let select = document.getElementById("select-caboligacao");

    let opcional = document.getElementById("opcional-cabo-ligacao");

    if(select.value != 0) {

        if(opcional.style.display !== "flex") {
            opcional.style.display = "flex";
        }

    } else {

        if(opcional.style.display !== "none") {
            opcional.style.display = "none";
        }

    }

}



function gerarDatasheet() {

    if(erro === 0) {

        document.getElementById("erro").innerHTML = "";


        let loading = document.createElement("div");
        loading.classList.add("loading");
        
        let loadingImg = document.createElement("img");
        loadingImg.setAttribute("src", "./img/loading.gif");

        loading.append(loadingImg);
        document.body.append(loading);

        let elementosForm = ["referencia", "descricao", "acrescimo", "lente", "acabamento", "cap", "opcao", "conectorcabo", "tipocabo", "tamanhocabo", "tampa", "vedante", "ip", "fonte", "caboligacao", "conectorligacao", "tamanhocaboligacao", "fixacao", "finalidade", "empresa", "idioma"];

        let elementosValue = ["referencia", "descricao", "acrescimo", "tamanhocabo", "tamanhocaboligacao"];

        let elementosDesc = ["lente", "acabamento"];

        

        let data = {};


        elementosForm.forEach(elemento => {

            if (elementosValue.includes(elemento)) {
                data[elemento] = document.getElementById(elemento).value;
            } else {
                data[elemento] = document.getElementById("select-" + elemento).value;
            }

            if (elementosDesc.includes(elemento)) {
                
                let select = document.getElementById("select-" + elemento);

                let selectValue = select.value;

                let option = document.querySelector('#select-' + elemento + ' option[value="' + selectValue + '"]');

                if(option.getAttribute("desc")){
                    data[elemento] = option.getAttribute("desc").replace(/\s/g, "");
                }

            }

        });
    
        console.log(data)

        fetch("./funcoes/gerarDatasheet.php", {
            method: "POST",
            body: JSON.stringify(data),
            headers: {"Content-type": "application/json; charset=UTF-8"}
        })
        .then((response) => {

            let clone = response.clone();

            function isJson(str) {
                try {
                    JSON.parse(str);
                } catch (e) {
                    return false;
                }
                return true;
            }
          
            response.text().then((result) => {

                if(isJson(result)) {

                    result = result.replaceAll('"', '');

                    document.getElementById("erro").innerHTML = result;

                    loading.remove();

                } else {

                    clone.blob().then((blob) => {

                        let file = URL.createObjectURL(blob);

                        let filename = document.getElementById("descricao").value.replace("LLED ", "").replace(/\s/g, "_");

                        /*if(document.getElementById("select-tampa").value !== "0") {
                            let val = document.getElementById("select-tampa").value;
                            filename += "_" + document.querySelector("#select-tampa option[value='" + val + "']").innerHTML.replace(/\s/g, "");
                        }*/

                        if(document.getElementById("select-ip").value !== "0") {
                            filename += "_" + document.getElementById("select-ip").value;
                        }
                        
                        filename += "_" + document.getElementById("select-idioma").value;


                        let a = document.createElement("a");
                        a.style.display = "none";
                        document.body.appendChild(a);
        
                        a.href = file;
                        a.download = filename + ".pdf";
                        a.click();
                        document.body.removeChild(a);

                        loading.remove();
                               
                    });
                }
            });

          });

    } else {

        alert("Primeiro, corrija o erro");

    }

}





function copy(input) {
    let copy = document.getElementById(input);
    copy.select();
    document.execCommand("copy");
}





function excecoes() {

    let elementosVariaveis = ["acrescimo", "select-tampa", "select-vedante", "select-fonte", "select-caboligacao", "select-fixacao"];

    elementosVariaveis.forEach(elemento => {
        document.getElementById(elemento)?.parentElement?.classList.add("nao-visivel");
    });



    let elementosVariaveisFamilia = [

        {
            familias: ["11", "32", "55", "58"],
            elementos: ["acrescimo", "select-tampa", "select-vedante", "select-caboligacao", "select-fixacao"]
        },

        {
            familias: ["29", "30"],
            elementos: ["select-fonte"]
        },

    ]

    let familia = document.getElementById("select-familia").value;

    elementosVariaveisFamilia.forEach(elemento => {
        if (elemento.familias.includes(familia)) {
            elemento.elementos.forEach(id => {
                document.getElementById(id)?.parentElement?.classList.remove("nao-visivel");
            });
        }
    });

}