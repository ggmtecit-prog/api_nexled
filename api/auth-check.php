<?php

/**
 * API key authentication.
 *
 * Local dev  : create api/auth.php (gitignored) — it handles everything and returns.
 * Production : set API_KEYS env var on Railway (comma-separated list of valid keys).
 */

// Local override
if (file_exists(__DIR__ . "/auth.php")) {
    require_once __DIR__ . "/auth.php";
    return;
}

// Production
$provided  = $_SERVER["HTTP_X_API_KEY"] ?? "";
$envKeys   = getenv("API_KEYS") ?: "";
$validKeys = array_filter(array_map("trim", explode(",", $envKeys)));

if (empty($validKeys) || !in_array($provided, $validKeys, true)) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit();
}
