<?php

if (!defined("BASE_PATH")) {
    define("BASE_PATH", dirname(__DIR__));
}

if (!defined("BASE_URL")) {
    define("BASE_URL", "/");
}

if (function_exists("mysqli_report")) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

loadLocalEnvFiles([
    dirname(__DIR__) . "/.env.local",
    dirname(__DIR__) . "/.env",
]);

defineCloudinaryConfigConstants();

if (!function_exists("connectDBReferencias")) {
    if (hasRuntimeDatabaseConfig()) {
        function connectDBReferencias() {
            return connectRuntimeDatabase(
                getRuntimeDatabaseName(["REFERENCIAS_DB_NAME", "DB_NAME_REF"], ["tecit_Referencias", "tecit_referencias"]),
                ["DB_USER_REF", "MYSQLUSER"],
                ["DB_PASS_REF", "MYSQLPASSWORD"]
            );
        }

        function connectDBLampadas() {
            return connectRuntimeDatabase(
                getRuntimeDatabaseName(["LAMPADAS_DB_NAME", "DB_NAME_LAMP"], ["tecit_lampadas"]),
                ["DB_USER_LAMP", "MYSQLUSER"],
                ["DB_PASS_LAMP", "MYSQLPASSWORD"]
            );
        }

        function connectDBInf() {
            return connectRuntimeDatabase(
                getRuntimeDatabaseName(["INF_DB_NAME", "DB_NAME_INF"], ["info_nexled_2024"]),
                ["DB_USER_INF"],
                ["DB_PASS_INF", "MYSQLPASSWORD"],
                [
                    [["MYSQLUSER"], ["MYSQLPASSWORD"]],
                    [["DB_USER_LAMP"], ["DB_PASS_LAMP"]],
                    [["DB_USER_REF"], ["DB_PASS_REF"]],
                ]
            );
        }

        function closeDB($con) {
            mysqli_close($con);
        }
    } else {
        $legacyConfigPath = dirname(__DIR__) . "/appdatasheets/config.php";

        if (file_exists($legacyConfigPath)) {
            require_once $legacyConfigPath;
        } else {
            function connectDBReferencias() {
                failRuntimeBootstrap("No database configuration is available for tecit_referencias.");
            }

            function connectDBLampadas() {
                failRuntimeBootstrap("No database configuration is available for tecit_lampadas.");
            }

            function connectDBInf() {
                failRuntimeBootstrap("No database configuration is available for info_nexled_2024.");
            }

            function closeDB($con) {
            }
        }
    }
}

if (!function_exists("connectDBDam")) {
    function connectDBDam() {
        $databaseName = getRuntimeDatabaseName(["DAM_DB_NAME"], ["nexled_dam"]);

        if (!hasDamRuntimeDatabaseConfig() && !hasRuntimeDatabaseConfig()) {
            if (!function_exists("mysqli_connect")) {
                failRuntimeBootstrap("The MySQLi extension is not available.");
            }

            $connection = mysqli_connect("localhost", "root", "", $databaseName);

            if ($connection === false || mysqli_connect_errno()) {
                failRuntimeDatabaseConnection($databaseName, mysqli_connect_error() ?: "Unable to connect to the database.");
            }

            mysqli_set_charset($connection, "utf8");
            return $connection;
        }

        return connectDedicatedRuntimeDatabase(
            $databaseName,
            ["DAM_DB_USER", "MYSQLUSER"],
            ["DAM_DB_PASS", "MYSQLPASSWORD"],
            ["DAM_DB_HOST", "DB_HOST", "MYSQLHOST"],
            ["DAM_DB_PORT", "DB_PORT", "MYSQLPORT"]
        );
    }
}

if (!function_exists("connectDBEprelMappings")) {
    function connectDBEprelMappings() {
        $databaseName = getRuntimeDatabaseName(["EPREL_MAP_DB_NAME"], ["nexled_eprel"]);

        if (!hasEprelMappingsRuntimeDatabaseConfig() && !hasRuntimeDatabaseConfig()) {
            if (!function_exists("mysqli_connect")) {
                failRuntimeBootstrap("The MySQLi extension is not available.");
            }

            $connection = mysqli_connect("localhost", "root", "", $databaseName);

            if ($connection === false || mysqli_connect_errno()) {
                failRuntimeDatabaseConnection($databaseName, mysqli_connect_error() ?: "Unable to connect to the database.");
            }

            mysqli_set_charset($connection, "utf8");
            return $connection;
        }

        return connectDedicatedRuntimeDatabase(
            $databaseName,
            ["EPREL_MAP_DB_USER", "MYSQLUSER"],
            ["EPREL_MAP_DB_PASS", "MYSQLPASSWORD"],
            ["EPREL_MAP_DB_HOST", "DB_HOST", "MYSQLHOST"],
            ["EPREL_MAP_DB_PORT", "DB_PORT", "MYSQLPORT"]
        );
    }
}

if (!function_exists("tryConnectDBDam")) {
    function tryConnectDBDam() {
        $databaseName = getRuntimeDatabaseName(["DAM_DB_NAME"], ["nexled_dam"]);

        if (!hasDamRuntimeDatabaseConfig() && !hasRuntimeDatabaseConfig()) {
            if (!function_exists("mysqli_connect")) {
                error_log("NexLed API bootstrap failed: The MySQLi extension is not available.");
                return null;
            }

            $connection = @mysqli_connect("localhost", "root", "", $databaseName);

            if ($connection === false || mysqli_connect_errno()) {
                error_log(
                    "NexLed API database connection failed for {$databaseName}: " .
                    (mysqli_connect_error() ?: "Unable to connect to the database.")
                );
                return null;
            }

            mysqli_set_charset($connection, "utf8");
            return $connection;
        }

        if (!function_exists("mysqli_init") || !function_exists("mysqli_real_connect")) {
            error_log("NexLed API bootstrap failed: The MySQLi extension is not available.");
            return null;
        }

        $lastError = "Unable to connect to the database.";

        foreach (resolveRuntimeCredentialCandidates(["DAM_DB_USER", "MYSQLUSER"], ["DAM_DB_PASS", "MYSQLPASSWORD"]) as $credentials) {
            $config = getDedicatedRuntimeDatabaseConfig(
                $databaseName,
                $credentials["user"],
                $credentials["password"],
                ["DAM_DB_HOST", "DB_HOST", "MYSQLHOST"],
                ["DAM_DB_PORT", "DB_PORT", "MYSQLPORT"]
            );
            [$connection, $errorMessage] = openRuntimeDatabaseConnection($config, true);

            if ($connection !== null) {
                mysqli_set_charset($connection, "utf8");
                return $connection;
            }

            if ($errorMessage !== "") {
                $lastError = $errorMessage;
            }
        }

        error_log("NexLed API database connection failed for {$databaseName}: {$lastError}");
        return null;
    }
}

if (!function_exists("str_contains")) {
    function str_contains($haystack, $needle) {
        return strpos($haystack, $needle) !== false;
    }
}

function hasRuntimeDatabaseConfig(): bool {
    return getRuntimeEnvValue("DB_HOST") !== null
        || getRuntimeEnvValue("DB_USER_REF") !== null
        || getRuntimeEnvValue("DB_USER_LAMP") !== null
        || getRuntimeEnvValue("DB_USER_INF") !== null
        || getRuntimeEnvValue("MYSQLHOST") !== null
        || getRuntimeEnvValue("MYSQL_URL") !== null
        || getRuntimeEnvValue("DATABASE_URL") !== null;
}

function hasDamRuntimeDatabaseConfig(): bool {
    return getRuntimeEnvValue("DAM_DB_HOST") !== null
        || getRuntimeEnvValue("DAM_DB_PORT") !== null
        || getRuntimeEnvValue("DAM_DB_NAME") !== null
        || getRuntimeEnvValue("DAM_DB_USER") !== null
        || getRuntimeEnvValue("DAM_DB_PASS") !== null;
}

function hasEprelMappingsRuntimeDatabaseConfig(): bool {
    return getRuntimeEnvValue("EPREL_MAP_DB_HOST") !== null
        || getRuntimeEnvValue("EPREL_MAP_DB_PORT") !== null
        || getRuntimeEnvValue("EPREL_MAP_DB_NAME") !== null
        || getRuntimeEnvValue("EPREL_MAP_DB_USER") !== null
        || getRuntimeEnvValue("EPREL_MAP_DB_PASS") !== null;
}

function getRuntimeDatabaseName(array $envKeys, array $fallbacks): string {
    $value = getRuntimeEnvValueFromList($envKeys);

    if ($value !== null && $value !== "") {
        return $value;
    }

    if (getRuntimeEnvValue("DB_HOST") !== null) {
        foreach ($fallbacks as $fallback) {
            if (is_string($fallback) && trim($fallback) !== "") {
                return $fallback;
            }
        }
    }

    $defaultDatabaseName = getDefaultRuntimeDatabaseName();

    if ($defaultDatabaseName !== null && $defaultDatabaseName !== "") {
        return $defaultDatabaseName;
    }

    foreach ($fallbacks as $fallback) {
        if (is_string($fallback) && trim($fallback) !== "") {
            return $fallback;
        }
    }

    return "";
}

function getRuntimeEnvValue(string $key): ?string {
    $value = getenv($key);

    if (!is_string($value)) {
        return null;
    }

    $trimmedValue = trim($value);
    return $trimmedValue !== "" ? $trimmedValue : null;
}

function getRuntimeEnvValueFromList(array $keys): ?string {
    foreach ($keys as $key) {
        $value = getRuntimeEnvValue($key);

        if ($value !== null) {
            return $value;
        }
    }

    return null;
}

function loadLocalEnvFiles(array $paths): void {
    foreach ($paths as $path) {
        loadLocalEnvFile($path);
    }
}

function loadLocalEnvFile(string $path): void {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if (!is_array($lines)) {
        return;
    }

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($trimmedLine === "" || substr($trimmedLine, 0, 1) === "#") {
            continue;
        }

        $separatorIndex = strpos($trimmedLine, "=");

        if ($separatorIndex === false) {
            continue;
        }

        $key = trim(substr($trimmedLine, 0, $separatorIndex));
        $value = trim(substr($trimmedLine, $separatorIndex + 1));

        if ($key === "" || getenv($key) !== false) {
            continue;
        }

        $normalizedValue = trim($value, " \t\n\r\0\x0B\"'");
        putenv($key . "=" . $normalizedValue);
        $_ENV[$key] = $normalizedValue;
        $_SERVER[$key] = $normalizedValue;
    }
}

function defineCloudinaryConfigConstants(): void {
    $uploadConfig = parseCloudinaryConfigUrl(
        getRuntimeEnvValue("DAM_CLOUDINARY_URL") ?? getRuntimeEnvValue("CLOUDINARY_URL")
    );
    $adminConfig = parseCloudinaryConfigUrl(
        getRuntimeEnvValue("DAM_CLOUDINARY_ADMIN_URL") ?? getRuntimeEnvValue("CLOUDINARY_ADMIN_URL")
    );

    defineCloudinaryConfigConstant(
        "CLOUDINARY_CLOUD_NAME",
        ["DAM_CLOUDINARY_CLOUD_NAME", "CLOUDINARY_CLOUD_NAME"],
        $uploadConfig["cloud_name"] ?? null
    );
    defineCloudinaryConfigConstant(
        "CLOUDINARY_API_KEY",
        ["DAM_CLOUDINARY_API_KEY", "CLOUDINARY_API_KEY"],
        $uploadConfig["api_key"] ?? null
    );
    defineCloudinaryConfigConstant(
        "CLOUDINARY_API_SECRET",
        ["DAM_CLOUDINARY_API_SECRET", "CLOUDINARY_API_SECRET"],
        $uploadConfig["api_secret"] ?? null
    );
    defineCloudinaryConfigConstant(
        "CLOUDINARY_ADMIN_API_KEY",
        ["DAM_CLOUDINARY_ADMIN_API_KEY", "CLOUDINARY_ADMIN_API_KEY"],
        $adminConfig["api_key"] ?? null
    );
    defineCloudinaryConfigConstant(
        "CLOUDINARY_ADMIN_API_SECRET",
        ["DAM_CLOUDINARY_ADMIN_API_SECRET", "CLOUDINARY_ADMIN_API_SECRET"],
        $adminConfig["api_secret"] ?? null
    );
}

function defineCloudinaryConfigConstant(string $name, array $envKeys, ?string $fallback = null): void {
    if (defined($name)) {
        return;
    }

    $value = getRuntimeEnvValueFromList($envKeys);

    if ($value === null || $value === "") {
        $value = is_string($fallback) ? trim($fallback) : null;
    }

    if ($value !== null && $value !== "") {
        define($name, $value);
    }
}

function parseCloudinaryConfigUrl(?string $value): array {
    if (!is_string($value) || trim($value) === "") {
        return [];
    }

    $parsedUrl = parse_url(trim($value));

    if (!is_array($parsedUrl) || ($parsedUrl["scheme"] ?? "") !== "cloudinary") {
        return [];
    }

    $host = isset($parsedUrl["host"]) ? trim((string) $parsedUrl["host"]) : "";
    $user = isset($parsedUrl["user"]) ? trim((string) $parsedUrl["user"]) : "";
    $pass = isset($parsedUrl["pass"]) ? trim((string) $parsedUrl["pass"]) : "";

    if ($host === "" || $user === "" || $pass === "") {
        return [];
    }

    return [
        "cloud_name" => urldecode($host),
        "api_key" => urldecode($user),
        "api_secret" => urldecode($pass),
    ];
}

function connectRuntimeDatabase(
    string $databaseName,
    array $userEnvKeys = ["MYSQLUSER"],
    array $passwordEnvKeys = ["MYSQLPASSWORD"],
    array $fallbackCredentialSets = []
) {
    if (!function_exists("mysqli_init") || !function_exists("mysqli_real_connect")) {
        failRuntimeBootstrap("The MySQLi extension is not available.");
    }

    $lastError = "Unable to connect to the database.";

    foreach (resolveRuntimeCredentialCandidates($userEnvKeys, $passwordEnvKeys, $fallbackCredentialSets) as $credentials) {
        $config = getRuntimeDatabaseConfig($databaseName, $credentials["user"], $credentials["password"]);
        [$connection, $errorMessage] = openRuntimeDatabaseConnection($config, false);

        if ($connection !== null) {
            mysqli_set_charset($connection, "utf8");
            return $connection;
        }

        if ($errorMessage !== "") {
            $lastError = $errorMessage;
        }
    }

    failRuntimeDatabaseConnection($databaseName, $lastError);
}

function connectDedicatedRuntimeDatabase(
    string $databaseName,
    array $userEnvKeys,
    array $passwordEnvKeys,
    array $hostEnvKeys,
    array $portEnvKeys,
    array $fallbackCredentialSets = []
) {
    if (!function_exists("mysqli_init") || !function_exists("mysqli_real_connect")) {
        failRuntimeBootstrap("The MySQLi extension is not available.");
    }

    $lastError = "Unable to connect to the database.";

    foreach (resolveRuntimeCredentialCandidates($userEnvKeys, $passwordEnvKeys, $fallbackCredentialSets) as $credentials) {
        $config = getDedicatedRuntimeDatabaseConfig(
            $databaseName,
            $credentials["user"],
            $credentials["password"],
            $hostEnvKeys,
            $portEnvKeys
        );
        [$connection, $errorMessage] = openRuntimeDatabaseConnection($config, false);

        if ($connection !== null) {
            mysqli_set_charset($connection, "utf8");
            return $connection;
        }

        if ($errorMessage !== "") {
            $lastError = $errorMessage;
        }
    }

    failRuntimeDatabaseConnection($databaseName, $lastError);
}

function getRuntimeDatabaseConfig(string $databaseName, string $user, string $password): array {
    $dbHost = getRuntimeEnvValue("DB_HOST");

    if ($dbHost !== null) {
        return [
            "host" => $dbHost,
            "user" => $user,
            "password" => $password,
            "database" => $databaseName,
            "port" => (int) (getRuntimeEnvValue("DB_PORT") ?? "3306"),
        ];
    }

    $databaseUrl = getRuntimeEnvValue("MYSQL_URL");

    if ($databaseUrl === null) {
        $databaseUrl = getRuntimeEnvValue("DATABASE_URL");
    }

    if ($databaseUrl !== null) {
        $parsedUrl = parse_url($databaseUrl);

        if (is_array($parsedUrl) && isset($parsedUrl["host"], $parsedUrl["user"])) {
            return [
                "host" => $parsedUrl["host"],
                "user" => $user,
                "password" => $password,
                "database" => $databaseName,
                "port" => isset($parsedUrl["port"]) ? (int) $parsedUrl["port"] : 3306,
            ];
        }
    }

    return [
        "host" => getRuntimeEnvValue("MYSQLHOST") ?? "localhost",
        "user" => $user,
        "password" => $password,
        "database" => $databaseName,
        "port" => (int) (getRuntimeEnvValue("MYSQLPORT") ?? "3306"),
    ];
}

function getDedicatedRuntimeDatabaseConfig(
    string $databaseName,
    string $user,
    string $password,
    array $hostEnvKeys,
    array $portEnvKeys
): array {
    $dbHost = getRuntimeEnvValueFromList($hostEnvKeys);
    $dbPort = getRuntimeEnvValueFromList($portEnvKeys);

    if ($dbHost !== null) {
        return [
            "host" => $dbHost,
            "user" => $user,
            "password" => $password,
            "database" => $databaseName,
            "port" => (int) ($dbPort ?? "3306"),
        ];
    }

    $databaseUrl = getRuntimeEnvValue("MYSQL_URL");

    if ($databaseUrl === null) {
        $databaseUrl = getRuntimeEnvValue("DATABASE_URL");
    }

    if ($databaseUrl !== null) {
        $parsedUrl = parse_url($databaseUrl);

        if (is_array($parsedUrl) && isset($parsedUrl["host"], $parsedUrl["user"])) {
            return [
                "host" => $parsedUrl["host"],
                "user" => $user,
                "password" => $password,
                "database" => $databaseName,
                "port" => isset($parsedUrl["port"]) ? (int) $parsedUrl["port"] : 3306,
            ];
        }
    }

    return [
        "host" => getRuntimeEnvValue("MYSQLHOST") ?? "localhost",
        "user" => $user,
        "password" => $password,
        "database" => $databaseName,
        "port" => (int) (getRuntimeEnvValue("MYSQLPORT") ?? "3306"),
    ];
}

function getDefaultRuntimeCredentials(): array {
    $databaseUrl = getRuntimeEnvValue("MYSQL_URL");

    if ($databaseUrl === null) {
        $databaseUrl = getRuntimeEnvValue("DATABASE_URL");
    }

    if ($databaseUrl !== null) {
        $parsedUrl = parse_url($databaseUrl);

        if (is_array($parsedUrl) && isset($parsedUrl["user"])) {
            return [
                "user" => $parsedUrl["user"],
                "password" => $parsedUrl["pass"] ?? "",
            ];
        }
    }

    return [
        "user" => "root",
        "password" => "",
    ];
}

function hasRuntimeEnvValueFromList(array $keys): bool {
    foreach ($keys as $key) {
        if (getRuntimeEnvValue($key) !== null) {
            return true;
        }
    }

    return false;
}

function resolveRuntimeCredentialCandidates(array $userEnvKeys, array $passwordEnvKeys, array $fallbackCredentialSets = []): array {
    $defaultCredentials = getDefaultRuntimeCredentials();
    $candidateSets = array_merge([[ $userEnvKeys, $passwordEnvKeys ]], $fallbackCredentialSets);
    $candidates = [];
    $seen = [];

    foreach ($candidateSets as $credentialSet) {
        $candidateUserKeys = $credentialSet[0] ?? [];
        $candidatePasswordKeys = $credentialSet[1] ?? [];
        $hasRuntimeValues = hasRuntimeEnvValueFromList($candidateUserKeys) || hasRuntimeEnvValueFromList($candidatePasswordKeys);

        if (!$hasRuntimeValues) {
            continue;
        }

        $candidate = [
            "user" => getRuntimeEnvValueFromList($candidateUserKeys) ?? $defaultCredentials["user"],
            "password" => getRuntimeEnvValueFromList($candidatePasswordKeys) ?? $defaultCredentials["password"],
        ];
        $signature = $candidate["user"] . "\0" . $candidate["password"];

        if (isset($seen[$signature])) {
            continue;
        }

        $seen[$signature] = true;
        $candidates[] = $candidate;
    }

    if ($candidates === []) {
        $candidates[] = $defaultCredentials;
    }

    return $candidates;
}

function openRuntimeDatabaseConnection(array $config, bool $suppressWarnings = false): array {
    $connection = mysqli_init();

    if ($connection === false) {
        return [null, "Unable to initialize MySQLi."];
    }

    mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    $connected = $suppressWarnings
        ? @mysqli_real_connect(
            $connection,
            $config["host"],
            $config["user"],
            $config["password"],
            $config["database"],
            $config["port"]
        )
        : mysqli_real_connect(
            $connection,
            $config["host"],
            $config["user"],
            $config["password"],
            $config["database"],
            $config["port"]
        );

    if (!$connected) {
        $errorMessage = mysqli_connect_error() ?: "Unable to connect to the database.";
        mysqli_close($connection);
        return [null, $errorMessage];
    }

    return [$connection, ""];
}

function probeRuntimeDatabase(
    string $databaseName,
    array $userEnvKeys,
    array $passwordEnvKeys,
    array $fallbackCredentialSets = []
): array {
    if (!function_exists("mysqli_init") || !function_exists("mysqli_real_connect")) {
        return [
            "ok" => false,
            "database" => $databaseName,
            "message" => "The MySQLi extension is not available.",
        ];
    }

    $lastError = "Unable to connect to the database.";

    foreach (resolveRuntimeCredentialCandidates($userEnvKeys, $passwordEnvKeys, $fallbackCredentialSets) as $credentials) {
        $config = getRuntimeDatabaseConfig($databaseName, $credentials["user"], $credentials["password"]);
        [$connection, $errorMessage] = openRuntimeDatabaseConnection($config, true);

        if ($connection !== null) {
            mysqli_close($connection);
            return [
                "ok" => true,
                "database" => $databaseName,
            ];
        }

        if ($errorMessage !== "") {
            $lastError = $errorMessage;
        }
    }

    return [
        "ok" => false,
        "database" => $databaseName,
        "message" => $lastError,
    ];
}

function probeDedicatedRuntimeDatabase(
    string $databaseName,
    array $userEnvKeys,
    array $passwordEnvKeys,
    array $hostEnvKeys,
    array $portEnvKeys,
    array $fallbackCredentialSets = []
): array {
    if (!function_exists("mysqli_init") || !function_exists("mysqli_real_connect")) {
        return [
            "ok" => false,
            "database" => $databaseName,
            "message" => "The MySQLi extension is not available.",
        ];
    }

    $lastError = "Unable to connect to the database.";

    foreach (resolveRuntimeCredentialCandidates($userEnvKeys, $passwordEnvKeys, $fallbackCredentialSets) as $credentials) {
        $config = getDedicatedRuntimeDatabaseConfig(
            $databaseName,
            $credentials["user"],
            $credentials["password"],
            $hostEnvKeys,
            $portEnvKeys
        );
        [$connection, $errorMessage] = openRuntimeDatabaseConnection($config, true);

        if ($connection !== null) {
            mysqli_close($connection);
            return [
                "ok" => true,
                "database" => $databaseName,
            ];
        }

        if ($errorMessage !== "") {
            $lastError = $errorMessage;
        }
    }

    return [
        "ok" => false,
        "database" => $databaseName,
        "message" => $lastError,
    ];
}

function getApiHealthSnapshot(): array {
    $references = probeRuntimeDatabase(
        getRuntimeDatabaseName(["REFERENCIAS_DB_NAME", "DB_NAME_REF"], ["tecit_Referencias", "tecit_referencias"]),
        ["DB_USER_REF", "MYSQLUSER"],
        ["DB_PASS_REF", "MYSQLPASSWORD"]
    );
    $lampadas = probeRuntimeDatabase(
        getRuntimeDatabaseName(["LAMPADAS_DB_NAME", "DB_NAME_LAMP"], ["tecit_lampadas"]),
        ["DB_USER_LAMP", "MYSQLUSER"],
        ["DB_PASS_LAMP", "MYSQLPASSWORD"]
    );
    $info = probeRuntimeDatabase(
        getRuntimeDatabaseName(["INF_DB_NAME", "DB_NAME_INF"], ["info_nexled_2024"]),
        ["DB_USER_INF"],
        ["DB_PASS_INF", "MYSQLPASSWORD"],
        [
            [["MYSQLUSER"], ["MYSQLPASSWORD"]],
            [["DB_USER_LAMP"], ["DB_PASS_LAMP"]],
            [["DB_USER_REF"], ["DB_PASS_REF"]],
        ]
    );
    $dam = probeDedicatedRuntimeDatabase(
        getRuntimeDatabaseName(["DAM_DB_NAME"], ["nexled_dam"]),
        ["DAM_DB_USER", "MYSQLUSER"],
        ["DAM_DB_PASS", "MYSQLPASSWORD"],
        ["DAM_DB_HOST", "DB_HOST", "MYSQLHOST"],
        ["DAM_DB_PORT", "DB_PORT", "MYSQLPORT"]
    );

    $services = [
        "families"  => $references["ok"],
        "options"   => $references["ok"],
        "reference" => $lampadas["ok"],
        // PDF generation only needs references + lampadas — info is used by DAM only
        "datasheet" => $references["ok"] && $lampadas["ok"],
        "dam"       => $dam["ok"],
    ];

    return [
        "ok" => $services["families"] && $services["reference"] && $services["datasheet"],
        "services" => $services,
        "databases" => [
            "references" => $references,
            "lampadas" => $lampadas,
            "info" => $info,
            "dam" => $dam,
        ],
    ];
}

function getDefaultRuntimeDatabaseName(): ?string {
    $databaseName = getRuntimeEnvValue("MYSQLDATABASE");

    if ($databaseName !== null) {
        return $databaseName;
    }

    $databaseName = getRuntimeEnvValue("DB_NAME");

    if ($databaseName !== null) {
        return $databaseName;
    }

    $databaseUrl = getRuntimeEnvValue("MYSQL_URL");

    if ($databaseUrl === null) {
        $databaseUrl = getRuntimeEnvValue("DATABASE_URL");
    }

    if ($databaseUrl === null) {
        return null;
    }

    $parsedUrl = parse_url($databaseUrl);

    if (!is_array($parsedUrl) || !isset($parsedUrl["path"])) {
        return null;
    }

    $databaseName = ltrim($parsedUrl["path"], "/");
    return $databaseName !== "" ? $databaseName : null;
}

function failRuntimeDatabaseConnection(string $databaseName, string $message): void {
    error_log("NexLed API database connection failed for {$databaseName}: {$message}");
    http_response_code(500);
    echo json_encode([
        "error" => "Database connection failed",
        "database" => $databaseName,
    ]);
    exit();
}

function failRuntimeBootstrap(string $message): void {
    error_log("NexLed API bootstrap failed: {$message}");
    http_response_code(500);
    echo json_encode([
        "error" => "Database configuration missing",
    ]);
    exit();
}
