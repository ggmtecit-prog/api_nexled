<?php

require_once dirname(__FILE__) . "/../lib/reference-decoder.php";

// GET /api/?endpoint=decode-reference&ref=11037581110010100
// Decodes a Tecit reference into normalized configurator segments.

$reference = validateReference($_GET["ref"] ?? "");

if ($reference === "") {
    http_response_code(400);
    echo json_encode(["error" => "Missing or invalid ref parameter"]);
    exit();
}

$parts = decodeReference($reference);
$productType = getProductType($reference);
$productId = null;
$warnings = [];
$errorCode = null;
$errorMessage = null;

if (!hasFullReferenceLength($reference)) {
    $warnings[] = "unexpected_length";
    $errorCode = "unexpected_length";
    $errorMessage = "Unexpected reference length.";
}

if ($productType !== null) {
    $productId = $productType === "dynamic"
        ? getProductIdDynamic($reference, $parts["cap"])
        : getProductId($reference);

    if ($productId === null) {
        $warnings[] = "product_not_found";
        $errorCode = "invalid_luminos_combination";
        $errorMessage = "A combinacao da familia, tamanho, cor, CRI e serie nao exite na view Luminos";
    }
} else {
    $warnings[] = "unknown_family";
    if ($errorCode === null) {
        $errorCode = "unknown_family";
        $errorMessage = "Unknown product family in Tecit code.";
    }
}

$description = getDecodedReferenceDescription($parts["identity"]);

echo json_encode([
    "reference" => $reference,
    "valid" => hasFullReferenceLength($reference) && $productType !== null && $productId !== null,
    "length" => $parts["length"],
    "expected_length" => REFERENCE_LENGTH_FULL,
    "identity" => $parts["identity"],
    "segments" => [
        "family" => $parts["family"],
        "size" => $parts["size"],
        "color" => $parts["color"],
        "cri" => $parts["cri"],
        "series" => $parts["series"],
        "lens" => $parts["lens"],
        "finish" => $parts["finish"],
        "cap" => $parts["cap"],
        "option" => $parts["option"],
    ],
    "product_type" => $productType,
    "product_id" => $productId,
    "description" => $description,
    "warnings" => $warnings,
    "error_code" => $errorCode,
    "error_message" => $errorMessage,
]);

function getDecodedReferenceDescription(string $identity): ?string {
    if ($identity === "" || strlen($identity) !== REFERENCE_LENGTH_IDENTITY) {
        return null;
    }

    $con = connectDBLampadas();
    $stmt = mysqli_prepare($con, "SELECT `desc` FROM Luminos WHERE ref = ? LIMIT 1");

    if (!$stmt) {
        closeDB($con);
        return null;
    }

    mysqli_stmt_bind_param($stmt, "s", $identity);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $description = null;

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_row($result);
        $description = $row[0] ?? null;
    }

    mysqli_stmt_close($stmt);
    closeDB($con);

    return $description;
}
