<?php

require_once dirname(__FILE__) . "/../lib/eprel-code-mappings.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    eprelCodeMappingsRespondError("METHOD_NOT_ALLOWED", "Method not allowed.", 405, [
        "allowed" => ["GET"],
    ]);
}

$tecitCode = null;
$registrationNumber = null;

if (isset($_GET["tecit_code"])) {
    $tecitCode = normalizeEprelTecitCode($_GET["tecit_code"]);

    if ($tecitCode === "") {
        eprelCodeMappingsRespondError("INVALID_TECIT_CODE", "TecIt code must be a numeric 17-digit value.", 422);
    }
}

if (isset($_GET["eprel_registration_number"])) {
    $registrationNumber = normalizeEprelRegistrationNumber($_GET["eprel_registration_number"]);

    if ($registrationNumber === "") {
        eprelCodeMappingsRespondError("INVALID_EPREL_REGISTRATION_NUMBER", "EPREL registration number must contain digits only.", 422);
    }
}

if (($tecitCode === null || $tecitCode === "") && ($registrationNumber === null || $registrationNumber === "")) {
    eprelCodeMappingsRespondError(
        "MISSING_LOOKUP_PARAMETER",
        "Provide tecit_code or eprel_registration_number.",
        400
    );
}

$connection = connectDBEprelMappings();
$rows = fetchEprelCodeMappings($connection, $tecitCode, $registrationNumber);
closeDB($connection);

eprelCodeMappingsRespondSuccess([
    "count" => count($rows),
    "rows" => $rows,
]);
