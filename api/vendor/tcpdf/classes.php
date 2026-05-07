<?php
class NEXLEDPDF extends TCPDF {


    public function Header() {

        global $pdf, $descricaoProduto, $empresa;

        $pdf->Line(10, 20, 200, 20, array('width' => 0.2, 'color' => array(0, 0, 0)));

        $nexledLogo = function_exists("findDamOrLocalSharedAsset")
            ? findDamOrLocalSharedAsset("logo", ["nexled"], dirname(__DIR__) . "/img/logos/nexled", ["png", "svg"])
            : dirname(__DIR__) . "/img/logos/nexled.png";
        $nexledLogo = is_string($nexledLogo) && function_exists("getPdfSafeAssetPath")
            ? getPdfSafeAssetPath($nexledLogo)
            : $nexledLogo;

        if (is_string($nexledLogo) && trim($nexledLogo) !== "") {
            $pdf->Image($nexledLogo, 10, 7, 30, 0, 'PNG', '', '', true, 300);
        }

        if($empresa !== "0") {
            $companyLogo = function_exists("findDamOrLocalSharedAsset")
                ? findDamOrLocalSharedAsset("logo", [$empresa], dirname(__DIR__) . "/img/logos/$empresa", ["png", "svg"])
                : dirname(__DIR__) . "/img/logos/$empresa.png";
            $companyLogo = is_string($companyLogo) && function_exists("getPdfSafeAssetPath")
                ? getPdfSafeAssetPath($companyLogo)
                : $companyLogo;

            if (is_string($companyLogo) && trim($companyLogo) !== "") {
                $pdf->Image($companyLogo, 170, 7, 30, 0, 'PNG', '', '', true, 300);
            }
        }

        $html = <<<EOD
        <h1 style="font-size: 10px; line-height: 24px; font-family: Lato; color: black; text-align: center;">$descricaoProduto</h1>
        EOD;

        $pdf->writeHTMLCell(0, 0, '', 7, $html, 0, 1, 0, true, '', true);

    }


    public function Footer() {

        global $pdf, $IDProduto, $lang;
        
        $pdf->Line(10, 275, 200, 275, array('width' => 0.2, 'color' => array(0, 0, 0)));

        $tecitLogo = function_exists("findDamOrLocalSharedAsset")
            ? findDamOrLocalSharedAsset("logo", ["tecit"], dirname(__DIR__) . "/img/logos/tecit", ["png", "svg"])
            : dirname(__DIR__) . "/img/logos/tecit.png";
        $tecitLogo = is_string($tecitLogo) && function_exists("getPdfSafeAssetPath")
            ? getPdfSafeAssetPath($tecitLogo)
            : $tecitLogo;

        if (is_string($tecitLogo) && trim($tecitLogo) !== "") {
            $pdf->Image($tecitLogo, 10, 280, 25, 0, 'PNG', '', '', true, 300);
        }
        

        $pdf->SetY(-18);

        $texto = criarFooter($IDProduto, $lang);

        $texto = <<<EOD
        <p style="font-size: 7px; line-height: 10px; color: #666666;">$texto</p>
        EOD;

        $pdf->writeHTMLCell(0, 0, 40, 280, $texto, 0, 1, 0, true, '', true);


        $pagina = $pdf->getPage() . "/" . $pdf->getAliasNbPages();

        $page = 
        <<<EOD
        <p style="font-size: 7px; line-height: 10px; color: #666666;">Pag. $pagina</p>
        EOD;

        $pdf->writeHTMLCell(0, 0, 185, 287, $page, 0, 1, 0, true, '', true);

    }

}

?>
