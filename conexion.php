<?php
$host = "Jorgeserver.database.windows.net";
$dbname = "DPL";
$username = "Jmmc";
$password = "ChaosSoldier01";

// Configuración de conexión SQLSRV
$connectionInfo = array(
    "Database" => $dbname,
    "UID" => $username,
    "PWD" => $password,
    "CharacterSet" => "UTF-8",
    "ReturnDatesAsStrings" => true,
    "MultipleActiveResultSets" => false
);

try {
    // Conexión con sqlsrv
    $conn = sqlsrv_connect($host, $connectionInfo);
    
    if ($conn === false) {
        $errors = sqlsrv_errors();
        die("Error de conexión: " . print_r($errors, true));
    }
} catch(Exception $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>