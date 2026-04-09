<?php

// GET /api/?endpoint=reference&ref=11037581110010100
// Returns the product description for a given reference code

$ref = $_GET["ref"] ?? null;

if (!$ref) {
    http_response_code(400);
    echo json_encode(["error" => "Missing ref parameter"]);
    exit();
}

$ref = validateReference($ref);
$ref = substr($ref, 0, 10); // product identity is the first 10 characters

if ($ref === "") {
    http_response_code(400);
    echo json_encode(["error" => "Invalid ref parameter"]);
    exit();
}

$con  = connectDBLampadas();
$stmt = mysqli_prepare($con, "SELECT `desc` FROM Luminos WHERE ref = ?");
mysqli_stmt_bind_param($stmt, "s", $ref);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_row($result);
    echo json_encode(["description" => $row[0]]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Reference not found"]);
}

closeDB($con);
