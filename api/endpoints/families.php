<?php

// GET /api/?endpoint=families
// Returns all product families plus runtime coverage metadata.

require_once dirname(__FILE__) . "/../lib/family-registry.php";

$con = connectDBReferencias();

$result = mysqli_query($con, "SELECT nome, codigo FROM Familias ORDER BY codigo");

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    closeDB($con);
    exit();
}

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

$families = [];

while ($row = mysqli_fetch_assoc($result)) {
    $familyCode = str_pad((string) $row["codigo"], 2, "0", STR_PAD_LEFT);
    $runtime = getFamilyRegistryEntry($familyCode);
    $luminosIdentityCount = $luminosCounts[$familyCode] ?? 0;

    $families[] = [
        "codigo" => $familyCode,
        "nome" => $row["nome"],
        "product_type" => $runtime["product_type"] ?? null,
        "datasheet_runtime_supported" => (bool) ($runtime["datasheet_runtime_supported"] ?? false),
        "luminos_identity_count" => $luminosIdentityCount,
        "has_luminos_identities" => $luminosIdentityCount > 0,
    ];
}

closeDB($con);

echo json_encode($families);
