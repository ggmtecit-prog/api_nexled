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

defineCloudinaryEnvConstant("CLOUDINARY_CLOUD_NAME");
defineCloudinaryEnvConstant("CLOUDINARY_API_KEY");
defineCloudinaryEnvConstant("CLOUDINARY_API_SECRET");

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
                ["DB_USER_INF", "MYSQLUSER"],
                ["DB_PASS_INF", "MYSQLPASSWORD"]
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

function getRuntimeDatabaseName(array $envKeys, array $fallbacks): string {
    $value = getRuntimeEnvValueFromList($envKeys);

    if ($value !== null && $value !== "") {
        return $value;
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

function defineCloudinaryEnvConstant(string $name): void {
    if (defined($name)) {
        return;
    }

    $value = getRuntimeEnvValue($name);

    if ($value !== null) {
        define($name, $value);
    }
}

function connectRuntimeDatabase(string $databaseName, array $userEnvKeys = ["MYSQLUSER"], array $passwordEnvKeys = ["MYSQLPASSWORD"]) {
    if (!function_exists("mysqli_init") || !function_exists("mysqli_real_connect")) {
        failRuntimeBootstrap("The MySQLi extension is not available.");
    }

    $config = getRuntimeDatabaseConfig($databaseName, $userEnvKeys, $passwordEnvKeys);
    $connection = mysqli_init();

    if ($connection === false) {
        failRuntimeDatabaseConnection($databaseName, "Unable to initialize MySQLi.");
    }

    mysqli_options($connection, MYSQLI_OPT_CONNECT_TIMEOUT, 10);

    $connected = mysqli_real_connect(
        $connection,
        $config["host"],
        $config["user"],
        $config["password"],
        $config["database"],
        $config["port"]
    );

    if (!$connected) {
        failRuntimeDatabaseConnection($databaseName, mysqli_connect_error());
    }

    mysqli_set_charset($connection, "utf8");

    return $connection;
}

function getRuntimeDatabaseConfig(string $databaseName, array $userEnvKeys, array $passwordEnvKeys): array {
    $dbHost = getRuntimeEnvValue("DB_HOST");

    if ($dbHost !== null) {
        return [
            "host" => $dbHost,
            "user" => getRuntimeEnvValueFromList($userEnvKeys) ?? "root",
            "password" => getRuntimeEnvValueFromList($passwordEnvKeys) ?? "",
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
                "user" => getRuntimeEnvValueFromList($userEnvKeys) ?? $parsedUrl["user"],
                "password" => getRuntimeEnvValueFromList($passwordEnvKeys) ?? ($parsedUrl["pass"] ?? ""),
                "database" => $databaseName,
                "port" => isset($parsedUrl["port"]) ? (int) $parsedUrl["port"] : 3306,
            ];
        }
    }

    return [
        "host" => getRuntimeEnvValue("MYSQLHOST") ?? "localhost",
        "user" => getRuntimeEnvValueFromList($userEnvKeys) ?? "root",
        "password" => getRuntimeEnvValueFromList($passwordEnvKeys) ?? "",
        "database" => $databaseName,
        "port" => (int) (getRuntimeEnvValue("MYSQLPORT") ?? "3306"),
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
