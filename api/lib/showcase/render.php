<?php

if (!defined("TCPDF_PATH")) {
    define("TCPDF_PATH", dirname(__FILE__, 4) . "/appdatasheets/tcpdf/");
}

require_once TCPDF_PATH . "tcpdf_include.php";
require_once TCPDF_PATH . "tcpdf.php";
require_once dirname(__FILE__) . "/../images.php";
require_once dirname(__FILE__) . "/../pdf-layout.php";
require_once dirname(__FILE__) . "/../sections.php";
require_once dirname(__FILE__) . "/request.php";
require_once dirname(__FILE__) . "/assembler.php";
require_once dirname(__FILE__) . "/renderers/downlight.php";

class NEXLEDSHOWCASEPDF extends TCPDF {
    public string $showcaseTitle = "";
    public string $showcaseCompany = "0";
    public string $showcaseLang = "pt";
    public string $showcaseProductId = "";

    public function Header(): void {
        $this->Line(10, 20, 200, 20, ["width" => 0.2, "color" => [0, 0, 0]]);

        $nexledLogo = findDamOrLocalSharedAsset(
            "logo",
            ["nexled"],
            dirname(__FILE__, 4) . "/appdatasheets/img/logos/nexled",
            ["png", "svg"]
        );

        if (is_string($nexledLogo) && trim($nexledLogo) !== "") {
            $this->Image(getPdfSafeAssetPath($nexledLogo), 10, 7, 30, 0, "PNG", "", "", true, 300);
        }

        if ($this->showcaseCompany !== "0") {
            $companyLogo = findDamOrLocalSharedAsset(
                "logo",
                [$this->showcaseCompany],
                dirname(__FILE__, 4) . "/appdatasheets/img/logos/" . $this->showcaseCompany,
                ["png", "svg"]
            );

            if (is_string($companyLogo) && trim($companyLogo) !== "") {
                $this->Image(getPdfSafeAssetPath($companyLogo), 170, 7, 30, 0, "PNG", "", "", true, 300);
            }
        }

        $html = "<h1 style=\"font-size:10px; line-height:24px; font-family:Lato; color:black; text-align:center;\">" .
            htmlspecialchars($this->showcaseTitle, ENT_QUOTES, "UTF-8") .
            "</h1>";

        $this->writeHTMLCell(0, 0, "", 7, $html, 0, 1, 0, true, "", true);
    }

    public function Footer(): void {
        $this->Line(10, 275, 200, 275, ["width" => 0.2, "color" => [0, 0, 0]]);

        $tecitLogo = findDamOrLocalSharedAsset(
            "logo",
            ["tecit"],
            dirname(__FILE__, 4) . "/appdatasheets/img/logos/tecit",
            ["png", "svg"]
        );

        if (is_string($tecitLogo) && trim($tecitLogo) !== "") {
            $this->Image(getPdfSafeAssetPath($tecitLogo), 10, 280, 25, 0, "PNG", "", "", true, 300);
        }

        $this->SetY(-18);

        $pageLabel = match ($this->showcaseLang) {
            "en" => "Page",
            "es" => "Pag.",
            default => "Pag.",
        };
        $pageText = $pageLabel . " " . $this->getPage() . "/" . $this->getAliasNbPages();

        $footerText = "";
        if ($this->showcaseProductId !== "") {
            $footerText = getFooter($this->showcaseProductId, $this->showcaseLang);
        }

        if ($footerText !== "") {
            $footerText .= " | showPDF";
            $this->writeHTMLCell(
                0,
                0,
                40,
                280,
                "<p style=\"font-size:7px; line-height:10px; color:#666666;\">{$footerText}</p>",
                0,
                1,
                0,
                true,
                "",
                true
            );
        }

        $this->writeHTMLCell(
            0,
            0,
            185,
            287,
            "<p style=\"font-size:7px; line-height:10px; color:#666666;\">{$pageText}</p>",
            0,
            1,
            0,
            true,
            "",
            true
        );
    }
}

function renderShowcasePdfBinary(array $normalizedRequest, array $assembledShowcase): array {
    $family = (string) ($assembledShowcase["family"]["code"] ?? "");
    $renderer = (string) ($assembledShowcase["renderer"] ?? "");

    if ($renderer !== "downlight" || !in_array($family, ["29", "30"], true)) {
        return buildShowcaseRequestError(
            501,
            "showcase_not_implemented",
            "Showcase PDF renderer not implemented for this family.",
            [
                "family" => $family,
                "renderer" => $renderer,
            ]
        );
    }

    $pages = buildShowcaseDownlightPages($assembledShowcase, $normalizedRequest);

    if ($pages === []) {
        return buildShowcaseRequestError(
            500,
            "showcase_render_failed",
            "Showcase renderer produced no pages."
        );
    }

    $bufferLevel = ob_get_level();
    ob_start();

    try {
        $pdf = new NEXLEDSHOWCASEPDF("P", "mm", "A4", true, "UTF-8", false);
        $pdf->showcaseTitle = buildShowcasePdfTitle($assembledShowcase, $normalizedRequest);
        $pdf->showcaseCompany = (string) ($normalizedRequest["company"] ?? "0");
        $pdf->showcaseLang = (string) ($normalizedRequest["lang"] ?? "pt");
        $pdf->showcaseProductId = (string) (($assembledShowcase["sections"]["overview"]["representative_product_id"] ?? ""));
        $pdf->SetCreator("Nexled API");
        $pdf->SetAuthor("Nexled API");
        $pdf->SetTitle($pdf->showcaseTitle);
        $pdf->SetMargins(10, 25, 10);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(12);
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->setAllowLocalFiles(true);
        $pdf->setRasterizeVectorImages(false);
        $pdf->setFontSubsetting(true);
        $pdf->SetFont("helvetica", "", 10, "", true);

        foreach ($pages as $pageHtml) {
            $pdf->AddPage();
            $pdf->writeHTML($pageHtml, true, false, true, false, "");
        }

        $binary = $pdf->Output("", "S");
    } catch (\Throwable $error) {
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        return buildShowcaseRequestError(
            500,
            "showcase_render_failed",
            "Showcase PDF render failed.",
            [
                "detail" => $error->getMessage(),
            ]
        );
    }

    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }

    return [
        "ok" => true,
        "data" => [
            "filename" => buildShowcasePdfFilename($assembledShowcase, $normalizedRequest),
            "content" => $binary,
            "page_count" => count($pages),
        ],
    ];
}

function buildShowcasePdfTitle(array $assembledShowcase, array $normalizedRequest): string {
    if (
        (string) ($assembledShowcase["renderer"] ?? "") === "downlight" &&
        function_exists("buildShowcaseDownlightDocumentTitle")
    ) {
        return buildShowcaseDownlightDocumentTitle($assembledShowcase, $normalizedRequest);
    }

    $familyName = trim((string) ($assembledShowcase["family"]["name"] ?? "Showcase"));
    $scopeLabel = buildShowcasePdfScopeToken($normalizedRequest);
    return trim($familyName . " Showcase " . $scopeLabel);
}

function buildShowcasePdfFilename(array $assembledShowcase, array $normalizedRequest): string {
    if (
        (string) ($assembledShowcase["renderer"] ?? "") === "downlight" &&
        function_exists("buildShowcaseDownlightFilename")
    ) {
        return buildShowcaseDownlightFilename($assembledShowcase, $normalizedRequest);
    }

    $family = (string) ($assembledShowcase["family"]["code"] ?? "showcase");
    $scope = sanitizeShowcaseFilenamePart(buildShowcasePdfScopeToken($normalizedRequest));
    $lang = sanitizeShowcaseFilenamePart((string) ($normalizedRequest["lang"] ?? "pt"));

    return "showcase_" . $family . "_" . $scope . "_" . $lang . ".pdf";
}

function buildShowcasePdfScopeToken(array $normalizedRequest): string {
    $locked = is_array($normalizedRequest["locked"] ?? null) ? $normalizedRequest["locked"] : [];
    $expanded = is_array($normalizedRequest["expanded"] ?? null) ? $normalizedRequest["expanded"] : [];
    $lockedParts = [];

    foreach (["size", "color", "cri", "series", "lens"] as $segment) {
        $value = trim((string) ($locked[$segment] ?? ""));

        if ($value !== "") {
            $lockedParts[] = $value;
        }
    }

    $expandedToken = $expanded === []
        ? "fixed"
        : "all-" . implode("-", array_map(
            static fn(string $segment): string => strtolower(trim($segment)),
            $expanded
        ));

    $scope = implode("-", $lockedParts);

    return $scope !== ""
        ? $scope . "_" . $expandedToken
        : $expandedToken;
}

function sanitizeShowcaseFilenamePart(string $value): string {
    $sanitized = strtolower(preg_replace("/[^a-zA-Z0-9_-]+/", "-", $value));
    $sanitized = trim($sanitized, "-_");
    return $sanitized !== "" ? $sanitized : "showcase";
}
