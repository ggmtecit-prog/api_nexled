<?php

include_once dirname(__FILE__, 2) . "/config.php";

$referencia = json_decode(file_get_contents('php://input'), true);

$ref = substr($referencia, 0, 10);

$con = connectDBLampadas();

$query = mysqli_query($con, "SELECT Luminos.desc FROM Luminos WHERE ref = $ref");

if(mysqli_num_rows($query) != 0) {

    $query = mysqli_fetch_row($query);

    $result = $query;

    $result = implode("", $result);

} else {

    $result = "erro";

}

echo json_encode($result);


?>