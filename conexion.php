<?php
$host = "Jorgeserver.database.windows.net";
$dbname = "DPL";
$username = "Jmmc";
$password = "ChaosSoldier01";

try {
    $pdo = new PDO(
        "sqlsrv:Server=$host;Database=$dbname",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>