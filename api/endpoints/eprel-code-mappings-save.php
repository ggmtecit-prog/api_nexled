<?php

require_once dirname(__FILE__) . "/../lib/eprel-code-mappings.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    eprelCodeMappingsRespondError("METHOD_NOT_ALLOWED", "Method not allowed.", 405, [
        "allowed" => ["POST"],
    ]);
}

$input = readEprelCodeMappingsJsonBody();
$rawMappings = $input["mappings"] ?? null;

if (!is_array($rawMappings)) {
    eprelCodeMappingsRespondError("INVALID_PAYLOAD", "Request body must contain a mappings array.", 400);
}

if (count($rawMappings) === 0) {
    eprelCodeMappingsRespondError("EMPTY_MAPPINGS", "Mappings array must not be empty.", 400);
}

$normalizedMappings = [];

foreach ($rawMappings as $index => $mapping) {
    if (!is_array($mapping)) {
        eprelCodeMappingsRespondError("INVALID_MAPPING", "Each mapping must be an object.", 422, [
            "index" => $index,
        ]);
    }

    $normalizedMappings[] = normalizeEprelMappingInput($mapping, (int) $index);
}

$connection = connectDBEprelMappings();
$summary = saveEprelCodeMappings($connection, $normalizedMappings);
closeDB($connection);

eprelCodeMappingsRespondSuccess($summary);
