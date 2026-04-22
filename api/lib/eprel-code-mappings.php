<?php

require_once dirname(__FILE__) . "/reference-decoder.php";

const EPREL_CODE_MAPPINGS_TABLE = "eprel_code_mappings";
const EPREL_CODE_MAPPINGS_MAX_SOURCE_TYPE_LENGTH = 32;
const EPREL_CODE_MAPPINGS_MAX_SOURCE_NAME_LENGTH = 255;

function eprelCodeMappingsRespondSuccess(array $payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

function eprelCodeMappingsRespondError(string $code, string $message, int $statusCode = 400, array $details = []): void {
    $payload = [
        "error" => [
            "code" => $code,
            "message" => $message,
        ],
    ];

    if ($details !== []) {
        $payload["error"]["details"] = $details;
    }

    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit();
}

function readEprelCodeMappingsJsonBody(): array {
    $rawBody = file_get_contents("php://input");
    $decoded = json_decode($rawBody ?: "", true);

    if (!is_array($decoded)) {
        eprelCodeMappingsRespondError("INVALID_JSON_BODY", "Invalid or missing JSON body.", 400);
    }

    return $decoded;
}

function normalizeEprelTecitCode(mixed $value): string {
    $reference = validateReference((string) $value);

    if (strlen($reference) !== REFERENCE_LENGTH_FULL || !ctype_digit($reference)) {
        return "";
    }

    return $reference;
}

function normalizeEprelRegistrationNumber(mixed $value): string {
    $normalized = preg_replace('/\D+/', '', trim((string) $value));
    return is_string($normalized) ? $normalized : "";
}

function normalizeEprelSourceType(mixed $value): ?string {
    $normalized = trim((string) $value);

    if ($normalized === "") {
        return null;
    }

    return substr($normalized, 0, EPREL_CODE_MAPPINGS_MAX_SOURCE_TYPE_LENGTH);
}

function normalizeEprelSourceName(mixed $value): ?string {
    $normalized = trim((string) $value);

    if ($normalized === "") {
        return null;
    }

    return substr($normalized, 0, EPREL_CODE_MAPPINGS_MAX_SOURCE_NAME_LENGTH);
}

function normalizeEprelMappingInput(array $mapping, int $index): array {
    $tecitCode = normalizeEprelTecitCode($mapping["tecit_code"] ?? "");
    $registrationNumber = normalizeEprelRegistrationNumber($mapping["eprel_registration_number"] ?? "");

    if ($tecitCode === "") {
        eprelCodeMappingsRespondError(
            "INVALID_TECIT_CODE",
            "Each mapping must contain a numeric 17-digit TecIt code.",
            422,
            ["index" => $index]
        );
    }

    if ($registrationNumber === "") {
        eprelCodeMappingsRespondError(
            "INVALID_EPREL_REGISTRATION_NUMBER",
            "Each mapping must contain a non-empty digits-only EPREL registration number.",
            422,
            ["index" => $index]
        );
    }

    return [
        "tecit_code" => $tecitCode,
        "eprel_registration_number" => $registrationNumber,
        "source_type" => normalizeEprelSourceType($mapping["source_type"] ?? null),
        "source_name" => normalizeEprelSourceName($mapping["source_name"] ?? null),
    ];
}

function formatEprelCodeMappingRow(array $row): array {
    return [
        "tecit_code" => (string) ($row["tecit_code"] ?? ""),
        "eprel_registration_number" => (string) ($row["eprel_registration_number"] ?? ""),
        "source_type" => ($row["source_type"] ?? null) !== null ? (string) $row["source_type"] : null,
        "source_name" => ($row["source_name"] ?? null) !== null ? (string) $row["source_name"] : null,
        "created_at" => ($row["created_at"] ?? null) !== null ? (string) $row["created_at"] : null,
        "updated_at" => ($row["updated_at"] ?? null) !== null ? (string) $row["updated_at"] : null,
    ];
}

function buildEprelCodeMappingsInClause(array $values): string {
    return implode(",", array_fill(0, count($values), "?"));
}

function bindEprelCodeMappingsParams(mysqli_stmt $stmt, string $types, array $values): void {
    $params = [$types];

    foreach ($values as $index => $value) {
        $params[] = &$values[$index];
    }

    call_user_func_array([$stmt, "bind_param"], $params);
}

function fetchEprelCodeMappingsByTecitCodes(mysqli $connection, array $tecitCodes): array {
    if ($tecitCodes === []) {
        return [];
    }

    $normalizedCodes = array_values(array_unique($tecitCodes));
    $sql = "SELECT tecit_code, eprel_registration_number, source_type, source_name, created_at, updated_at
            FROM " . EPREL_CODE_MAPPINGS_TABLE . "
            WHERE tecit_code IN (" . buildEprelCodeMappingsInClause($normalizedCodes) . ")";
    $stmt = mysqli_prepare($connection, $sql);

    if ($stmt === false) {
        eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to prepare mapping lookup query.", 500, [
            "mysql" => mysqli_error($connection),
        ]);
    }

    bindEprelCodeMappingsParams($stmt, str_repeat("s", count($normalizedCodes)), $normalizedCodes);

    if (!mysqli_stmt_execute($stmt)) {
        $mysqlError = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to execute mapping lookup query.", 500, [
            "mysql" => $mysqlError,
        ]);
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[(string) $row["tecit_code"]] = $row;
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function fetchEprelCodeMappings(mysqli $connection, ?string $tecitCode, ?string $registrationNumber): array {
    $sql = "SELECT tecit_code, eprel_registration_number, source_type, source_name, created_at, updated_at
            FROM " . EPREL_CODE_MAPPINGS_TABLE . "
            WHERE 1 = 1";
    $types = "";
    $params = [];

    if ($tecitCode !== null && $tecitCode !== "") {
        $sql .= " AND tecit_code = ?";
        $types .= "s";
        $params[] = $tecitCode;
    }

    if ($registrationNumber !== null && $registrationNumber !== "") {
        $sql .= " AND eprel_registration_number = ?";
        $types .= "s";
        $params[] = $registrationNumber;
    }

    $sql .= " ORDER BY tecit_code ASC";
    $stmt = mysqli_prepare($connection, $sql);

    if ($stmt === false) {
        eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to prepare mapping read query.", 500, [
            "mysql" => mysqli_error($connection),
        ]);
    }

    if ($types !== "") {
        bindEprelCodeMappingsParams($stmt, $types, $params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $mysqlError = mysqli_error($connection);
        mysqli_stmt_close($stmt);
        eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to execute mapping read query.", 500, [
            "mysql" => $mysqlError,
        ]);
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows = [];

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = formatEprelCodeMappingRow($row);
    }

    mysqli_stmt_close($stmt);
    return $rows;
}

function saveEprelCodeMappings(mysqli $connection, array $mappings): array {
    $existingRows = fetchEprelCodeMappingsByTecitCodes(
        $connection,
        array_map(static fn(array $row): string => $row["tecit_code"], $mappings)
    );

    $insertSql = "INSERT INTO " . EPREL_CODE_MAPPINGS_TABLE . "
        (tecit_code, eprel_registration_number, source_type, source_name)
        VALUES (?, ?, ?, ?)";
    $updateSql = "UPDATE " . EPREL_CODE_MAPPINGS_TABLE . "
        SET eprel_registration_number = ?, source_type = ?, source_name = ?
        WHERE tecit_code = ?";

    $insertStmt = mysqli_prepare($connection, $insertSql);
    $updateStmt = mysqli_prepare($connection, $updateSql);

    if ($insertStmt === false || $updateStmt === false) {
        if ($insertStmt instanceof mysqli_stmt) {
            mysqli_stmt_close($insertStmt);
        }
        if ($updateStmt instanceof mysqli_stmt) {
            mysqli_stmt_close($updateStmt);
        }

        eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to prepare mapping write query.", 500, [
            "mysql" => mysqli_error($connection),
        ]);
    }

    $saved = 0;
    $updated = 0;
    $skipped = 0;

    foreach ($mappings as $mapping) {
        $existing = $existingRows[$mapping["tecit_code"]] ?? null;

        if ($existing === null) {
            $tecitCode = $mapping["tecit_code"];
            $registrationNumber = $mapping["eprel_registration_number"];
            $sourceType = $mapping["source_type"];
            $sourceName = $mapping["source_name"];

            mysqli_stmt_bind_param($insertStmt, "ssss", $tecitCode, $registrationNumber, $sourceType, $sourceName);

            if (!mysqli_stmt_execute($insertStmt)) {
                $mysqlError = mysqli_error($connection);
                mysqli_stmt_close($insertStmt);
                mysqli_stmt_close($updateStmt);
                eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to insert mapping.", 500, [
                    "mysql" => $mysqlError,
                    "tecit_code" => $mapping["tecit_code"],
                ]);
            }

            $saved++;
            continue;
        }

        $existingNormalized = [
            "eprel_registration_number" => (string) ($existing["eprel_registration_number"] ?? ""),
            "source_type" => ($existing["source_type"] ?? null) !== null ? (string) $existing["source_type"] : null,
            "source_name" => ($existing["source_name"] ?? null) !== null ? (string) $existing["source_name"] : null,
        ];

        if (
            $existingNormalized["eprel_registration_number"] === $mapping["eprel_registration_number"] &&
            $existingNormalized["source_type"] === $mapping["source_type"] &&
            $existingNormalized["source_name"] === $mapping["source_name"]
        ) {
            $skipped++;
            continue;
        }

        $registrationNumber = $mapping["eprel_registration_number"];
        $sourceType = $mapping["source_type"];
        $sourceName = $mapping["source_name"];
        $tecitCode = $mapping["tecit_code"];

        mysqli_stmt_bind_param($updateStmt, "ssss", $registrationNumber, $sourceType, $sourceName, $tecitCode);

        if (!mysqli_stmt_execute($updateStmt)) {
            $mysqlError = mysqli_error($connection);
            mysqli_stmt_close($insertStmt);
            mysqli_stmt_close($updateStmt);
            eprelCodeMappingsRespondError("DATABASE_ERROR", "Failed to update mapping.", 500, [
                "mysql" => $mysqlError,
                "tecit_code" => $mapping["tecit_code"],
            ]);
        }

        $updated++;
    }

    mysqli_stmt_close($insertStmt);
    mysqli_stmt_close($updateStmt);

    return [
        "saved" => $saved,
        "updated" => $updated,
        "skipped" => $skipped,
        "total_received" => count($mappings),
    ];
}
