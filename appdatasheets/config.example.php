<?php

// Copy this file to config.php and fill in your credentials

function connectDBReferencias(){
    $con = mysqli_connect("localhost", "your_user", "your_password", "tecit_referencias");
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit();
    }
    mysqli_set_charset($con, "utf8");
    return $con;
}

function connectDBLampadas(){
    $con = mysqli_connect("localhost", "your_user", "your_password", "tecit_lampadas");
    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit();
    }
    mysqli_set_charset($con, "utf8");
    return $con;
}

function connectDBInf() {
    $con = mysqli_connect("localhost", "your_user", "your_password", "info_nexled_2024");
    mysqli_set_charset($con,'utf8');
    return $con;
}

function closeDB($con) {
    mysqli_close($con);
}

if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle){
        return (strpos($haystack, $needle) !== false);
    }
}
