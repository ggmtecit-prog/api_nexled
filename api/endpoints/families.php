<?php

// GET /api/?endpoint=families
// Returns all product families

$con = connectDBReferencias();

$result = mysqli_query($con, "SELECT id, nome FROM familias ORDER BY nome");

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Database error"]);
    closeDB($con);
    exit();
}

$families = [];

while ($row = mysqli_fetch_assoc($result)) {
    $families[] = $row;
}

closeDB($con);

echo json_encode($families);
