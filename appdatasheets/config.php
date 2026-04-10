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
define("CLOUDINARY_CLOUD_NAME", getenv("CLOUDINARY_CLOUD_NAME") ?: "NexledApi");
define("CLOUDINARY_API_KEY",    getenv("CLOUDINARY_API_KEY")    ?: "293731379949566");
define("CLOUDINARY_API_SECRET", getenv("CLOUDINARY_API_SECRET") ?: "Cg8oss-uAixnsX_4D6tfXtxdSW8");
