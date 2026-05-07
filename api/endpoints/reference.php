<?php

// GET /api/?endpoint=reference&ref=11037581110010100
// Returns the product description for a given reference code

$ref = $_GET["ref"] ?? null;

if (!$ref) {
    respondError(400, "missing_ref", "Missing ref parameter");
}

$ref = validateReference($ref);
$ref = substr($ref, 0, 10); // product identity is the first 10 characters

if ($ref === "") {
    respondError(400, "invalid_ref", "Invalid ref parameter", ["ref" => $_GET["ref"] ?? null]);
}

$con  = connectDBLampadas();
$stmt = mysqli_prepare($con, "SELECT `desc` FROM Luminos WHERE ref = ?");
mysqli_stmt_bind_param($stmt, "s", $ref);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_row($result);
    closeDB($con);
    respondJson(["description" => $row[0]]);
}

closeDB($con);
respondError(404, "invalid_luminos_combination", "A combinacao da familia, tamanho, cor, CRI e serie nao exite na view Luminos", ["ref" => $ref]);
