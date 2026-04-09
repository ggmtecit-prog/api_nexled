<?php

// API Keys template — copy this file to auth.php and fill in real keys
// Generate a key with: bin2hex(random_bytes(32))

$validKeys = [
    "configurator" => "REPLACE_WITH_REAL_KEY",
    "store"        => "REPLACE_WITH_REAL_KEY",
    "website"      => "REPLACE_WITH_REAL_KEY",
    "internaltool" => "REPLACE_WITH_REAL_KEY",
];

$apiKey = $_SERVER["HTTP_X_API_KEY"] ?? $_GET["api_key"] ?? null;

if (!$apiKey || !in_array($apiKey, $validKeys)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
