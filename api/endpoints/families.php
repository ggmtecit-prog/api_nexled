<?php

// GET /api/?endpoint=families
// Returns all product families

$con = connectDBReferencias();

$result = mysqli_query($con, "SELECT nome, codigo FROM Familias ORDER BY codigo");

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    closeDB($con);
    exit();
}

$families = [];

while ($row = mysqli_fetch_assoc($result)) {
    // Pad single-digit codes to 2 digits (e.g. "9" → "09")
    if (strlen($row['codigo']) < 2) {
        $row['codigo'] = "0" . $row['codigo'];
    }
    $families[] = $row;
}

closeDB($con);

echo json_encode($families);
