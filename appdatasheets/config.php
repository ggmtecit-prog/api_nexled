<?php

function connectDBReferencias() {
    $con = mysqli_connect(
        getenv("DB_HOST")     ?: "localhost",
        getenv("DB_USER_REF") ?: "root",
        getenv("DB_PASS_REF") ?: "",
        "tecit_referencias"
    );
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit();
    }
    mysqli_set_charset($con, "utf8");
    return $con;
}

function connectDBLampadas() {
    $con = mysqli_connect(
        getenv("DB_HOST")      ?: "localhost",
        getenv("DB_USER_LAMP") ?: "root",
        getenv("DB_PASS_LAMP") ?: "",
        "tecit_lampadas"
    );
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit();
    }
    mysqli_set_charset($con, "utf8");
    return $con;
}

function connectDBInf() {
    $con = mysqli_connect(
        getenv("DB_HOST")     ?: "localhost",
        getenv("DB_USER_INF") ?: "root",
        getenv("DB_PASS_INF") ?: "",
        "info_nexled_2024"
    );
    mysqli_set_charset($con, "utf8");
    return $con;
}

function closeDB($con) {
    mysqli_close($con);
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return (strpos($haystack, $needle) !== false);
    }
}

// Cloudinary — env vars on Railway, hardcoded fallback for local dev
if (!function_exists("nexledResolveEnvValue")) {
    function nexledResolveEnvValue(array $keys): ?string {
        foreach ($keys as $key) {
            $value = getenv($key);

            if (!is_string($value)) {
                continue;
            }

            $trimmedValue = trim($value);

            if ($trimmedValue !== "") {
                return $trimmedValue;
            }
        }

        return null;
    }
}

if (!function_exists("nexledParseCloudinaryUrl")) {
    function nexledParseCloudinaryUrl(?string $value): array {
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
}

$nexledCloudinaryUpload = nexledParseCloudinaryUrl(
    nexledResolveEnvValue(["DAM_CLOUDINARY_URL", "CLOUDINARY_URL"])
);
$nexledCloudinaryAdmin = nexledParseCloudinaryUrl(
    nexledResolveEnvValue(["DAM_CLOUDINARY_ADMIN_URL", "CLOUDINARY_ADMIN_URL"])
);

if (!defined("CLOUDINARY_CLOUD_NAME")) {
    define("CLOUDINARY_CLOUD_NAME", nexledResolveEnvValue(["DAM_CLOUDINARY_CLOUD_NAME", "CLOUDINARY_CLOUD_NAME"]) ?: ($nexledCloudinaryUpload["cloud_name"] ?? ""));
}

if (!defined("CLOUDINARY_API_KEY")) {
    define("CLOUDINARY_API_KEY", nexledResolveEnvValue(["DAM_CLOUDINARY_API_KEY", "CLOUDINARY_API_KEY"]) ?: ($nexledCloudinaryUpload["api_key"] ?? ""));
}

if (!defined("CLOUDINARY_API_SECRET")) {
    define("CLOUDINARY_API_SECRET", nexledResolveEnvValue(["DAM_CLOUDINARY_API_SECRET", "CLOUDINARY_API_SECRET"]) ?: ($nexledCloudinaryUpload["api_secret"] ?? ""));
}

if (!defined("CLOUDINARY_ADMIN_API_KEY")) {
    define("CLOUDINARY_ADMIN_API_KEY", nexledResolveEnvValue(["DAM_CLOUDINARY_ADMIN_API_KEY", "CLOUDINARY_ADMIN_API_KEY"]) ?: ($nexledCloudinaryAdmin["api_key"] ?? ""));
}

if (!defined("CLOUDINARY_ADMIN_API_SECRET")) {
    define("CLOUDINARY_ADMIN_API_SECRET", nexledResolveEnvValue(["DAM_CLOUDINARY_ADMIN_API_SECRET", "CLOUDINARY_ADMIN_API_SECRET"]) ?: ($nexledCloudinaryAdmin["api_secret"] ?? ""));
}
