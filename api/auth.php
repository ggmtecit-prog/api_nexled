<?php

// API Keys — one per project
// Add a new entry for each consumer
$validKeys = [
    "appdatasheets"  => "CHANGE_ME_KEY_1",
    "store"          => "CHANGE_ME_KEY_2",
    "mainpage"       => "CHANGE_ME_KEY_3",
    "internaltool"   => "CHANGE_ME_KEY_4",
];

$apiKey = $_SERVER["HTTP_X_API_KEY"] ?? $_GET["api_key"] ?? null;

if (!$apiKey || !in_array($apiKey, $validKeys)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
