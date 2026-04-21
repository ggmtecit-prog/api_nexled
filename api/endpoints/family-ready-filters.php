<?php

require_once dirname(__FILE__) . "/../lib/code-explorer.php";

$GLOBALS["family_ready_filters_response_sent"] = false;

function encodeFamilyReadyFiltersJson(array $payload): string {
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    if ($json !== false) {
        return $json;
    }

    return '{"error":{"code":"JSON_ENCODE_ERROR","message":"Failed to encode family ready filters response."}}';
}

function emitFamilyReadyFiltersJson(array $payload, int $statusCode = 200): void {
    $GLOBALS["family_ready_filters_response_sent"] = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header_remove("Content-Length");
    header("Content-Type: application/json");
    http_response_code($statusCode);
    echo encodeFamilyReadyFiltersJson($payload);
    exit();
}

function emitFamilyReadyFiltersError(string $code, string $message, int $statusCode = 500): void {
    emitFamilyReadyFiltersJson([
        "error" => [
            "code" => $code,
            "message" => $message,
        ],
    ], $statusCode);
}

function resolveFamilyReadyFiltersFailure(?array $error, string $buffer): array {
    $trimmedBuffer = trim($buffer);
    $errorMessage = trim((string) ($error["message"] ?? ""));

    if ($errorMessage !== "" && str_contains(strtolower($errorMessage), "maximum execution time")) {
        return [
            "status" => 500,
            "code" => "FILTER_BUILD_TIMEOUT",
            "message" => "Family ready filters build timed out.",
        ];
    }

    if (
        $trimmedBuffer !== "" &&
        (
            str_contains($trimmedBuffer, "Database connection failed") ||
            str_contains($trimmedBuffer, "Failed to connect to MySQL") ||
            str_contains($trimmedBuffer, "Database configuration missing")
        )
    ) {
        return [
            "status" => 500,
            "code" => "DATABASE_CONNECTION_FAILED",
            "message" => "Database connection failed while loading family ready filters.",
        ];
    }

    if ($errorMessage !== "") {
        return [
            "status" => 500,
            "code" => "FILTER_BUILD_FAILED",
            "message" => "Failed to build family ready filters.",
        ];
    }

    if ($trimmedBuffer !== "") {
        return [
            "status" => 500,
            "code" => "INVALID_JSON_RESPONSE",
            "message" => "Family ready filters returned invalid output.",
        ];
    }

    return [
        "status" => 500,
        "code" => "FILTER_BUILD_FAILED",
        "message" => "Failed to build family ready filters.",
    ];
}

ob_start();

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    restore_error_handler();

    if (($GLOBALS["family_ready_filters_response_sent"] ?? false) === true) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        return;
    }

    $buffer = "";

    while (ob_get_level() > 0) {
        $buffer = (string) ob_get_contents() . $buffer;
        ob_end_clean();
    }

    $failure = resolveFamilyReadyFiltersFailure(error_get_last(), $buffer);

    header_remove("Content-Length");
    header("Content-Type: application/json");
    http_response_code((int) ($failure["status"] ?? 500));
    echo encodeFamilyReadyFiltersJson([
        "error" => [
            "code" => (string) ($failure["code"] ?? "FILTER_BUILD_FAILED"),
            "message" => (string) ($failure["message"] ?? "Failed to build family ready filters."),
        ],
    ]);
});

try {
    $family = validateFamily($_GET["family"] ?? null);

    if ($family === 0) {
        emitFamilyReadyFiltersError("INVALID_FAMILY", "Missing or invalid family parameter.", 400);
    }

    $familyMeta = getCodeExplorerFamilyMeta($family);

    if ($familyMeta === null) {
        emitFamilyReadyFiltersError("UNKNOWN_FAMILY", "Unknown family.", 400);
    }

    @set_time_limit(300);

    $options = getCodeExplorerFamilyOptions($family);
    $identities = getCodeExplorerLuminosIdentities($familyMeta["code"]);
    $baseRows = getFamilyReadyProductsBaseRows(
        $familyMeta["code"],
        $familyMeta["name"],
        $options,
        $identities
    );
    $filters = getFamilyReadyFilters($_GET, $options, $baseRows);

    emitFamilyReadyFiltersJson(
        buildFamilyReadyFiltersResponse(
            $familyMeta["code"],
            $familyMeta["name"],
            $options,
            $identities,
            $filters
        )
    );
} catch (Throwable $throwable) {
    $message = $throwable->getMessage();
    $code = "FILTER_BUILD_FAILED";
    $statusCode = 500;

    if (str_contains(strtolower($message), "maximum execution time")) {
        $code = "FILTER_BUILD_TIMEOUT";
        $message = "Family ready filters build timed out.";
    } elseif (
        str_contains($message, "Database connection failed") ||
        str_contains($message, "Failed to connect to MySQL") ||
        str_contains($message, "Database configuration missing")
    ) {
        $code = "DATABASE_CONNECTION_FAILED";
        $message = "Database connection failed while loading family ready filters.";
    } else {
        $message = "Failed to build family ready filters.";
    }

    emitFamilyReadyFiltersError($code, $message, $statusCode);
}
