<?php
// buscar_registros.php - PARA BÚSQUEDA DE REGISTROS EN MODIFICAR_REGISTRO.PHP
session_start();
require_once 'conexion.php';

header('Content-Type: application/json');

// Validar sesión y permisos
if (!isset($_SESSION['user_id']) || !isset($_SESSION['id_perfil']) || $_SESSION['id_perfil'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
    exit;
}

// Obtener parámetros de búsqueda
$servicio = $_POST['servicio'] ?? '';
$almacen = $_POST['almacen'] ?? '';
$gestion = $_POST['gestion'] ?? '';

try {
    // Construir consulta con filtros
    $sql = "SELECT id, servicio, [ciudad], [almacen], [gestion], modo_especial 
            FROM externos.CotizadorTarifas 
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($servicio)) {
        $sql .= " AND servicio = ?";
        $params[] = $servicio;
    }
    
    if (!empty($almacen)) {
        $sql .= " AND [almacen] = ?";
        $params[] = $almacen;
    }
    
    if (!empty($gestion)) {
        $sql .= " AND [gestion] = ?";
        $params[] = $gestion;
    }
    
    $sql .= " ORDER BY id DESC";
    
    $stmt = sqlsrv_prepare($conn, $sql, $params);
    
    if ($stmt === false || !sqlsrv_execute($stmt)) {
        throw new Exception('Error en consulta de búsqueda');
    }
    
    $registros = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $registros[] = [
            'id' => $row['id'],
            'servicio' => $row['servicio'],
            'ciudad' => $row['ciudad'],
            'almacen' => $row['almacen'],
            'gestion' => $row['gestion'],
            'modo_especial' => $row['modo_especial'] ?? 0
        ];
    }
    
    sqlsrv_free_stmt($stmt);
    
    echo json_encode([
        'success' => true,
        'registros' => $registros,
        'total' => count($registros)
    ]);
    
} catch(Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>