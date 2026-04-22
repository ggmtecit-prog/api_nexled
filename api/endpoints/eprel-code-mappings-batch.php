<?php

require_once dirname(__FILE__) . "/../lib/eprel-code-mappings.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    eprelCodeMappingsRespondError("METHOD_NOT_ALLOWED", "Method not allowed.", 405, [
        "allowed" => ["POST"],
    ]);
}

$input = readEprelCodeMappingsJsonBody();
$rawTecitCodes = $input["tecit_codes"] ?? null;

if (!is_array($rawTecitCodes)) {
    eprelCodeMappingsRespondError("INVALID_PAYLOAD", "Request body must contain a tecit_codes array.", 400);
}

if (count($rawTecitCodes) === 0) {
    eprelCodeMappingsRespondError("EMPTY_TECIT_CODES", "TecIt codes array must not be empty.", 400);
}

$tecitCodes = [];

foreach ($rawTecitCodes as $index => $rawTecitCode) {
    $tecitCode = normalizeEprelTecitCode($rawTecitCode);

    if ($tecitCode === "") {
        eprelCodeMappingsRespondError("INVALID_TECIT_CODE", "Each TecIt code must be a numeric 17-digit value.", 422, [
            "index" => $index,
        ]);
    }

    $tecitCodes[] = $tecitCode;
}

$tecitCodes = array_values(array_unique($tecitCodes));
$connection = connectDBEprelMappings();
$rowsByTecitCode = fetchEprelCodeMappingsByTecitCodes($connection, $tecitCodes);
closeDB($connection);

$rows = array_map("formatEprelCodeMappingRow", array_values($rowsByTecitCode));
$missingTecitCodes = array_values(array_diff($tecitCodes, array_keys($rowsByTecitCode)));

eprelCodeMappingsRespondSuccess([
    "requested_count" => count($tecitCodes),
    "found_count" => count($rows),
    "missing_tecit_codes" => $missingTecitCodes,
    "rows" => $rows,
]);
