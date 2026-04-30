<?php

// GET /api/?endpoint=families
// Returns all product families plus runtime coverage metadata.

require_once dirname(__FILE__) . "/../lib/family-registry.php";
require_once dirname(__FILE__) . "/../lib/cache.php";

header("Cache-Control: public, max-age=3600");

$families = cacheRemember("families", 3600, function () {
    $con = connectDBReferencias();
    $result = mysqli_query($con, "SELECT nome, codigo FROM Familias ORDER BY codigo");
    if (!$result) { closeDB($con); return null; }

    $luminosCounts = [];
    $conLampadas = connectDBLampadas();
    $luminosResult = mysqli_query(
        $conLampadas,
        "SELECT LPAD(LEFT(ref, 2), 2, '0') AS family_code, COUNT(*) AS total
         FROM Luminos
         GROUP BY LPAD(LEFT(ref, 2), 2, '0')"
    );
    if ($luminosResult) {
        while ($luminosRow = mysqli_fetch_assoc($luminosResult)) {
            $luminosCounts[$luminosRow["family_code"]] = intval($luminosRow["total"]);
        }
    }
    closeDB($conLampadas);

    $out = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $familyCode = str_pad((string) $row["codigo"], 2, "0", STR_PAD_LEFT);
        $runtime = getFamilyRegistryEntry($familyCode);
        $luminosIdentityCount = $luminosCounts[$familyCode] ?? 0;
        $out[] = [
            "codigo" => $familyCode,
            "nome" => $row["nome"],
            "product_type" => $runtime["product_type"] ?? null,
            "datasheet_runtime_supported" => (bool) ($runtime["datasheet_runtime_supported"] ?? false),
            "showcase_supported" => (bool) ($runtime["showcase_supported"] ?? false),
            "showcase_runtime_implemented" => (bool) ($runtime["showcase_runtime_implemented"] ?? false),
            "showcase_renderer" => $runtime["showcase_renderer"] ?? null,
            "showcase_status" => $runtime["showcase_status"] ?? "blocked_until_mapped",
            "custom_datasheet_supported" => (bool) ($runtime["custom_datasheet_supported"] ?? false),
            "custom_datasheet_runtime_implemented" => (bool) ($runtime["custom_datasheet_runtime_implemented"] ?? false),
            "custom_datasheet_status" => $runtime["custom_datasheet_status"] ?? "blocked_until_datasheet_runtime",
            "luminos_identity_count" => $luminosIdentityCount,
            "has_luminos_identities" => $luminosIdentityCount > 0,
        ];
    }
    closeDB($con);
    return $out;
});

if ($families === null) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    exit();
}

echo json_encode($families);
