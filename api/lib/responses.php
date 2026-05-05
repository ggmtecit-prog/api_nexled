<?php

// Caveman API response helpers. Standardized error shape: error / error_code / details.
// respondError auto-logs via logger.php. Both helpers exit() — terminal calls.

require_once dirname(__FILE__) . "/logger.php";

function currentEndpointName(): string {
    $ep = $_GET["endpoint"] ?? null;
    if (is_string($ep) && $ep !== "") return $ep;
    return "unknown";
}

function respondError(int $statusCode, string $errorCode, string $message, array $details = []): void {
    logError(currentEndpointName(), $message, [
        "status" => $statusCode,
        "error_code" => $errorCode,
        "details" => $details,
    ]);

    if (!headers_sent()) {
        http_response_code($statusCode);
        header("Content-Type: application/json");
    }

    echo json_encode([
        "error" => $message,
        "error_code" => $errorCode,
        "details" => (object) $details,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit();
}

function respondJson($data, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header("Content-Type: application/json");
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
