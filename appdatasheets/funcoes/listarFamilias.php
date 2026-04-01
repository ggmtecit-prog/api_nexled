<?php

include_once dirname(__FILE__, 2) . "/config.php";

function listarFamilias() {

    $con = connectDBReferencias();

    $query = mysqli_query($con, "SELECT nome, codigo FROM Familias ORDER BY codigo");

    while ($row = mysqli_fetch_assoc($query)) {

        $ref = $row['codigo'];

        if(strlen($row['codigo']) < 2) {
            $ref = "0" . $ref;
        }

        echo "<option value='" . $row['codigo'] . "' ref='" . $ref . "'>" . $row['nome'] . "</option>";

    }

}

?>