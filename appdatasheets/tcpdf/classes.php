<?php
class NEXLEDPDF extends TCPDF {


    public function Header() {

        global $pdf, $descricaoProduto, $empresa;

        $pdf->Line(10, 20, 200, 20, array('width' => 0.2, 'color' => array(0, 0, 0)));

        $pdf->Image(dirname(__DIR__) . "/img/logos/nexled.png", 10, 7, 30, 0, 'PNG', '', '', true, 300);

        if($empresa !== "0") {
            $pdf->Image(dirname(__DIR__) . "/img/logos/$empresa.png", 170, 7, 30, 0, 'PNG', '', '', true, 300);
        }

        $html = <<<EOD
        <h1 style="font-size: 10px; line-height: 24px; font-family: Lato; color: black; text-align: center;">$descricaoProduto</h1>
        EOD;

        $pdf->writeHTMLCell(0, 0, '', 7, $html, 0, 1, 0, true, '', true);

    }


    public function Footer() {

        global $pdf, $IDProduto, $lang;
        
        $pdf->Line(10, 275, 200, 275, array('width' => 0.2, 'color' => array(0, 0, 0)));

        $pdf->Image(dirname(__DIR__) . "/img/logos/tecit.png", 10, 280, 25, 0, 'PNG', '', '', true, 300);
        

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