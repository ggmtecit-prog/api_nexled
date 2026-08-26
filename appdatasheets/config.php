<?php

function connectDBReferencias(){

    $con = mysqli_connect("localhost", "ref", "ref01", "tecit_Referencias");
    //$con = mysqli_connect("tecit.pt", "ref", "ref01", "tecit_Referencias");

    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit();
    }

    mysqli_set_charset($con, "utf8");

    return $con;

}





function connectDBLampadas(){

    $con = mysqli_connect("localhost", "lampadas", "ledlamp74621", "tecit_lampadas");

    //$con = mysqli_connect("tecit.pt", "lampadas", "ledlamp74621", "tecit_lampadas");

    if (mysqli_connect_errno()) {
        echo "Failed to connect to MySQL: " . mysqli_connect_error();
        exit();
    }

    mysqli_set_charset($con, "utf8");

    return $con;

}

function connectDBInf() {

    $con = mysqli_connect("localhost", "nexled_2024", "nexled_2024", "info_nexled_2024");
    //$con = mysqli_connect("tecit.pt", "nexled_2024", "nexled_2024", "info_nexled_2024");

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
