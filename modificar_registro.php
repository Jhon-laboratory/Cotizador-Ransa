<?php
// modificar_registro.php - SISTEMA DE MODIFICACIÓN DE REGISTROS EXISTENTES CON TABLA DATATABLES
session_start();
require_once 'conexion.php';

// ===== VERIFICAR SI EL USUARIO ESTÁ LOGUEADO =====
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit;
}

// ===== Validar si usuario puede modificar registros basado en id_perfil =====
$puede_modificar_registro = false;
if (isset($_SESSION['id_perfil'])) {
    if ($_SESSION['id_perfil'] == 2) {
        $puede_modificar_registro = true;
    }
}

// Si no tiene permiso, redirigir al index
if (!$puede_modificar_registro) {
    header("Location: index.php");
    exit;
}
// ===== FIN VALIDACIÓN =====

// Obtener datos del usuario desde la sesión
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];
$user_type = $_SESSION['user_type'];
$user_color = $_SESSION['user_color'] ?? '#009A3F';

// Obtener valores únicos para dropdowns
$servicios = $ciudades = $almacenes = $gestiones = $utds = [];

try {
    $stmt = sqlsrv_query($conn, "SELECT DISTINCT servicio FROM externos.CotizadorTarifas WHERE servicio IS NOT NULL ORDER BY servicio");
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $servicios[] = $row['servicio'];
    }
    
    $stmt = sqlsrv_query($conn, "SELECT DISTINCT [ ciudad] FROM externos.CotizadorTarifas WHERE [ ciudad] IS NOT NULL ORDER BY [ ciudad]");
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $ciudades[] = $row[' ciudad'];
    }
    
    $stmt = sqlsrv_query($conn, "SELECT DISTINCT [ almacen] FROM externos.CotizadorTarifas WHERE [ almacen] IS NOT NULL ORDER BY [ almacen]");
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $almacenes[] = $row[' almacen'];
    }
    
    $stmt = sqlsrv_query($conn, "SELECT DISTINCT [ gestion] FROM externos.CotizadorTarifas WHERE [ gestion] IS NOT NULL ORDER BY [ gestion] DESC");
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $gestiones[] = $row[' gestion'];
    }
    
    // UTDs únicos
    $stmt = sqlsrv_query($conn, "SELECT DISTINCT [ utd] FROM externos.CotizadorTarifas WHERE [ utd] IS NOT NULL ORDER BY [ utd]");
    while ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $utd_valor = $row[' utd'];
        if ($utd_valor !== null && $utd_valor !== '') {
            $porcentaje = convertirAPorcentaje($utd_valor);
            $utds[$utd_valor] = $porcentaje;
        }
    }
} catch(Exception $e) {
    // Error silencioso
}

// Función para convertir valor a porcentaje
function convertirAPorcentaje($valor) {
    if ($valor === null || $valor === '') return '';
    
    $valor_limpio = str_replace(',', '.', trim($valor));
    $float_valor = floatval($valor_limpio);
    $porcentaje = $float_valor * 100;
    
    if ($porcentaje == intval($porcentaje)) {
        return intval($porcentaje) . '%';
    } else {
        return number_format($porcentaje, 2, ',', '') . '%';
    }
}

// Aplicar filtros si existen
$where = [];
$params = [];

$filtro_servicio = $_GET['servicio'] ?? '';
$filtro_gestion = $_GET['gestion'] ?? '';
$filtro_almacen = $_GET['almacen'] ?? '';
$filtro_utd = $_GET['utd'] ?? '';

if ($filtro_servicio) {
    $where[] = "servicio = ?";
    $params[] = $filtro_servicio;
}

if ($filtro_gestion) {
    $where[] = "[ gestion] = ?";
    $params[] = $filtro_gestion;
}

if ($filtro_almacen) {
    $where[] = "[ almacen] = ?";
    $params[] = $filtro_almacen;
}

if ($filtro_utd) {
    $where[] = "[ utd] = ?";
    $params[] = $filtro_utd;
}

// Consulta con filtros
$sql = "SELECT id, servicio, [ ciudad], [ almacen], [ gestion], [ utd], [modo_especial] FROM externos.CotizadorTarifas";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

try {
    if (!empty($params)) {
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }
        if (!sqlsrv_execute($stmt)) {
            die(print_r(sqlsrv_errors(), true));
        }
    } else {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            die(print_r(sqlsrv_errors(), true));
        }
    }
    
    $registros = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $registros[] = $row;
    }
    
} catch(Exception $e) {
    $error = "Error al obtener registros: " . $e->getMessage();
    $registros = [];
}

// Si se envió un ID para modificar directamente (desde la tabla)
$registro_id = $_GET['id'] ?? 0;
$registro = null;
$tarifas_especiales = [];
$modo_actual = 'normal';

if ($registro_id > 0) {
    // Si viene un ID, mostrar formulario de modificación
    try {
        // Obtener registro principal
        $sql = "SELECT * FROM externos.CotizadorTarifas WHERE id = ?";
        $stmt = sqlsrv_prepare($conn, $sql, [$registro_id]);
        
        if ($stmt && sqlsrv_execute($stmt)) {
            $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            sqlsrv_free_stmt($stmt);
            
            if ($registro) {
                $modo_actual = isset($registro['modo_especial']) && $registro['modo_especial'] == 1 ? 'especial' : 'normal';
                
                // Si es modo especial, obtener tarifas especiales
                if ($modo_actual == 'especial') {
                    $guid = $registro['ID_Tarifas'] ?? '';
                    if (!empty($guid)) {
                        $tablas_posibles = ['DPL.externos.TarifaEspecial', 'externos.TarifaEspecial'];
                        
                        foreach ($tablas_posibles as $tabla) {
                            try {
                                $sql_especial = "SELECT Servicio, costo, Frecuencia 
                                                 FROM $tabla 
                                                 WHERE ID_Tarifa = ? 
                                                 ORDER BY Servicio";
                                
                                $stmt_especial = sqlsrv_prepare($conn, $sql_especial, [$guid]);
                                if ($stmt_especial !== false && sqlsrv_execute($stmt_especial)) {
                                    while ($row = sqlsrv_fetch_array($stmt_especial, SQLSRV_FETCH_ASSOC)) {
                                        $tarifas_especiales[] = $row;
                                    }
                                    sqlsrv_free_stmt($stmt_especial);
                                    
                                    if (!empty($tarifas_especiales)) {
                                        break;
                                    }
                                }
                            } catch(Exception $e) {
                                continue;
                            }
                        }
                    }
                }
            }
        }
    } catch(Exception $e) {
        error_log("Error obteniendo registro: " . $e->getMessage());
    }
}

// Función para generar GUID si no existe com_create_guid
if (!function_exists('com_create_guid')) {
    function com_create_guid() {
        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535), mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }
}

// Guardar modificaciones
if (isset($_POST['confirmar_modificacion']) && $_POST['confirmar_modificacion'] === 'true') {
    header('Content-Type: application/json');
    
    try {
        $id = (int) ($_POST['id_registro'] ?? 0);
        $servicio = trim($_POST['servicio_final'] ?? '');
        $ciudad = trim($_POST['ciudad_final'] ?? '');
        $almacen = trim($_POST['almacen_final'] ?? '');
        $gestion = (int) trim($_POST['gestion_final'] ?? date('Y'));
        $utd = trim($_POST['utd_final'] ?? '0.15');
        $modo_especial = trim($_POST['modo_especial'] ?? '0');
        
        // Validar campos obligatorios
        if (empty($servicio) || empty($ciudad) || empty($almacen) || $id <= 0) {
            throw new Exception('Datos incompletos o ID inválido');
        }
        
        // Iniciar transacción
        sqlsrv_begin_transaction($conn);
        
        $guid_tarifa = $_POST['guid_actual'] ?? '';
        $cambio_modo = false;
        
        if ($modo_especial === '1' && empty($guid_tarifa)) {
            $guid_tarifa = com_create_guid();
            $guid_tarifa = trim($guid_tarifa, '{}');
            $cambio_modo = true;
        } elseif ($modo_especial === '0' && !empty($guid_tarifa)) {
            $guid_tarifa = '';
            $cambio_modo = true;
        }
        
        // Determinar valores según modo
        if ($modo_especial === '0') {
            $descarga_pall = trim($_POST['descarga_pall'] ?? '0,00');
            $estibaje_cajas = trim($_POST['estibaje_cajas'] ?? '0,00');
            $pall_in = trim($_POST['pall_in'] ?? '0,00');
            $operacion_cajas_recepcion = trim($_POST['operacion_cajas_recepcion'] ?? '0,00');
            $operacion_und_recepcion = trim($_POST['operacion_und_recepcion'] ?? '0,00');
            $m2_almacen = trim($_POST['m2_almacen'] ?? '0,00');
            $almacen_pos = trim($_POST['almacen_pos'] ?? '0,00');
            $pall_out = trim($_POST['pall_out'] ?? '0,00');
            $operacion_cajas_despacho = trim($_POST['operacion_cajas_despacho'] ?? '0,00');
            $operacion_und_despacho = trim($_POST['operacion_und_despacho'] ?? '0,00');
            $carga_pall = trim($_POST['carga_pall'] ?? '0,00');
        } else {
            $descarga_pall = '0,00';
            $estibaje_cajas = '0,00';
            $pall_in = '0,00';
            $operacion_cajas_recepcion = '0,00';
            $operacion_und_recepcion = '0,00';
            $m2_almacen = '0,00';
            $almacen_pos = '0,00';
            $pall_out = '0,00';
            $operacion_cajas_despacho = '0,00';
            $operacion_und_despacho = '0,00';
            $carga_pall = '0,00';
        }
        
        // ACTUALIZAR REGISTRO PRINCIPAL (ajustar según tu estructura real)
        $sql_update = "UPDATE externos.CotizadorTarifas SET
            servicio = ?, 
            [ ciudad] = ?, 
            [ almacen] = ?, 
            [ pall_in] = ?, 
            [ descarga_pall] = ?, 
            [ operacion_cajas_recepcion] = ?, 
            [ operacion_und_recepcion] = ?, 
            [ almacen_pos] = ?, 
            [ m2_almacen] = ?, 
            [ pall_out] = ?, 
            [ carga_pall] = ?, 
            [ operacion_cajas_despacho] = ?, 
            [ operacion_und_despacho] = ?, 
            [ estibaje_cajas] = ?, 
            [ gestion] = ?, 
            [ utd] = ?,
            [modo_especial] = ?,
            [ID_Tarifas] = ?
        WHERE id = ?";
        
        $params_update = array(
            $servicio,
            $ciudad,
            $almacen,
            $pall_in,
            $descarga_pall,
            $operacion_cajas_recepcion,
            $operacion_und_recepcion,
            $almacen_pos,
            $m2_almacen,
            $pall_out,
            $carga_pall,
            $operacion_cajas_despacho,
            $operacion_und_despacho,
            $estibaje_cajas,
            $gestion,
            $utd,
            $modo_especial,
            $guid_tarifa,
            $id
        );
        
        $stmt_update = sqlsrv_prepare($conn, $sql_update, $params_update);
        
        if ($stmt_update === false) {
            throw new Exception('Error al preparar actualización');
        }
        
        if (!sqlsrv_execute($stmt_update)) {
            throw new Exception('Error al ejecutar actualización');
        }
        
        // Si es modo especial, manejar tarifas especiales
        if ($modo_especial === '1' && isset($_POST['tarifas_especiales'])) {
            $tarifas_especiales = json_decode($_POST['tarifas_especiales'], true);
            
            // Eliminar tarifas existentes para este GUID
            if (!empty($guid_tarifa)) {
                $tablas_posibles = ['DPL.externos.TarifaEspecial', 'externos.TarifaEspecial'];
                foreach ($tablas_posibles as $tabla) {
                    try {
                        $sql_delete = "DELETE FROM $tabla WHERE ID_Tarifa = ?";
                        $stmt_delete = sqlsrv_prepare($conn, $sql_delete, [$guid_tarifa]);
                        if ($stmt_delete) {
                            sqlsrv_execute($stmt_delete);
                            sqlsrv_free_stmt($stmt_delete);
                        }
                    } catch(Exception $e) {
                        // Continuar
                    }
                }
            }
            
            // Insertar nuevas tarifas especiales
            if (is_array($tarifas_especiales)) {
                foreach ($tarifas_especiales as $tarifa) {
                    $servicio_tarifa = trim($tarifa['servicio'] ?? '');
                    $costo = trim($tarifa['costo'] ?? '0,00');
                    $frecuencia = trim($tarifa['frecuencia'] ?? 'Mensualizado');
                    
                    if (!empty($servicio_tarifa)) {
                        $costo_bd = str_replace(',', '.', $costo);
                        
                        $tabla_especial = 'externos.TarifaEspecial';
                        $sql_especial = "INSERT INTO $tabla_especial 
                                        (Servicio, costo, ID_Tarifa, Frecuencia, fecha_creacion) 
                                        VALUES (?, ?, ?, ?, GETDATE())";
                        
                        $params_especial = array($servicio_tarifa, $costo_bd, $guid_tarifa, $frecuencia);
                        $stmt_especial = sqlsrv_prepare($conn, $sql_especial, $params_especial);
                        
                        if ($stmt_especial === false) {
                            $tabla_especial = 'DPL.externos.TarifaEspecial';
                            $sql_especial = "INSERT INTO $tabla_especial 
                                            (Servicio, costo, ID_Tarifa, Frecuencia, fecha_creacion) 
                                            VALUES (?, ?, ?, ?, GETDATE())";
                            $stmt_especial = sqlsrv_prepare($conn, $sql_especial, $params_especial);
                        }
                        
                        if ($stmt_especial !== false) {
                            sqlsrv_execute($stmt_especial);
                            sqlsrv_free_stmt($stmt_especial);
                        }
                    }
                }
            }
        }
        
        // Confirmar transacción
        sqlsrv_commit($conn);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registro modificado exitosamente!',
            'id' => $id,
            'modo_especial' => $modo_especial
        ]);
        exit;
        
    } catch(Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MODIFICAR REGISTRO - SISTEMA DE COTIZACIÓN</title>
  
  <!-- Gentelella CSS -->
  <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
  <link href="vendors/select2/dist/css/select2.min.css" rel="stylesheet">
  <link href="vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
  <link href="build/css/custom.min.css" rel="stylesheet">
  
  <style>
    /* ESTILOS SIMILARES A INDEX.PHP */
    .form-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border-left: 4px solid #3498db;
    }
    
    .section-title {
        font-size: 14px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
    }
    
    .section-title i {
        margin-right: 10px;
        color: #3498db;
    }
    
    .form-label {
        font-weight: 600;
        color: #495057;
        font-size: 13px;
        margin-bottom: 5px;
    }
    
    .campo-nuevo {
        margin-top: 10px;
        padding: 10px;
        background: #e8f4f8;
        border-radius: 4px;
        border-left: 3px solid #17a2b8;
    }
    
    .modal-confirm .modal-header {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border-radius: 5px 5px 0 0;
    }
    
    .data-preview {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
        margin-bottom: 15px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .data-row {
        display: flex;
        padding: 8px 0;
        border-bottom: 1px dashed #dee2e6;
    }
    
    .data-label {
        width: 150px;
        font-weight: 600;
        color: #495057;
        flex-shrink: 0;
    }
    
    .data-value {
        flex: 1;
        color: #2c3e50;
    }
    
    .compact-form .form-control {
        height: 35px;
        font-size: 13px;
    }
    
    .compact-form .input-group-text {
        height: 35px;
        font-size: 13px;
    }
    
    /* Switch Toggle */
    .switch-container {
        display: flex;
        align-items: center;
        margin-bottom: 20px;
        padding: 10px;
        background: #e8f4f8;
        border-radius: 5px;
    }
    
    .switch-label {
        font-weight: 600;
        color: #2c3e50;
        margin-right: 15px;
        font-size: 14px;
    }
    
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 30px;
    }
    
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 34px;
    }
    
    .slider:before {
        position: absolute;
        content: "";
        height: 22px;
        width: 22px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    
    input:checked + .slider {
        background-color: #28a745;
    }
    
    input:focus + .slider {
        box-shadow: 0 0 1px #28a745;
    }
    
    input:checked + .slider:before {
        transform: translateX(30px);
    }
    
    .mode-label {
        margin-left: 10px;
        font-weight: 600;
        font-size: 14px;
    }
    
    .mode-normal {
        color: #28a745;
    }
    
    .mode-especial {
        color: #007bff;
    }
    
    /* Tarifas Especiales */
    .tarifa-especial-item {
        background: white;
        padding: 15px;
        margin-bottom: 10px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }
    
    .tarifa-especial-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
    }
    
    .btn-eliminar-tarifa {
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 3px;
        padding: 2px 8px;
        font-size: 12px;
        cursor: pointer;
    }
    
    .btn-eliminar-tarifa:hover {
        background: #c82333;
    }
    
    /* AVATAR DEL USUARIO CON SU COLOR */
    .user-avatar-circle {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 16px;
        margin-right: 10px;
    }
    
    /* ESTILO PARA EL MENÚ ACTIVO */
    .menu-modificar-registro {
        background: linear-gradient(135deg, #f39c12, #e67e22) !important;
        border-left: 4px solid #fff !important;
    }
    
    .menu-modificar-registro a {
        color: white !important;
        font-weight: 600 !important;
    }
    
    .menu-modificar-registro i {
        color: white !important;
    }
    
    /* BOTÓN MODIFICAR EN TABLA */
    .btn-modificar {
        background: linear-gradient(135deg, #3498db, #2980b9);
        border: none;
        color: white;
        font-size: 12px;
        padding: 5px 12px;
        border-radius: 4px;
        font-weight: 500;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    
    .btn-modificar:hover {
        background: linear-gradient(135deg, #2980b9, #21618c);
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(52, 152, 219, 0.3);
        color: white;
    }
    
    .btn-modificar i {
        margin-right: 5px;
        font-size: 11px;
    }
    
    /* Badge para modo especial */
    .badge-mod-especial {
        background: linear-gradient(135deg, #9b59b6, #8e44ad) !important;
        color: white !important;
        font-size: 10px !important;
        padding: 2px 6px !important;
        border-radius: 8px !important;
        font-weight: 500;
    }
    
    .badge-mod-normal {
        background: linear-gradient(135deg, #2ecc71, #27ae60) !important;
        color: white !important;
        font-size: 10px !important;
        padding: 2px 6px !important;
        border-radius: 8px !important;
        font-weight: 500;
    }
    
    /* ESTILOS IGUALES A INDEX.PHP PARA FILTROS Y TABLA */
    .filter-container {
        background: #f8f9fa;
        padding: 10px;
        border-radius: 2px;
        margin-bottom: 2px;
        border: 1px solid #e9ecef;
    }
    
    .filter-container .form-label {
        font-weight: 600;
        color: #495057;
        font-size: 13px;
        margin-bottom: 1px;
    }
    
    .filter-select {
        width: 100%;
        font-size: 13px;
    }
    
    .select2-container--default .select2-selection--single {
        border: 1px solid #ced4da;
        height: 34px;
        border-radius: 4px;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 34px;
        font-size: 13px;
    }
    
    /* BADGES IGUALES A INDEX.PHP */
    .badge-service {
        background: linear-gradient(135deg, #3498db, #2980b9) !important;
        color: white !important;
        font-size: 11px !important;
        padding: 3px 8px !important;
        border-radius: 10px !important;
        font-weight: 500;
    }
    
    .badge-year {
        background: linear-gradient(135deg, #f39c12, #e67e22) !important;
        color: white !important;
        font-size: 11px !important;
        padding: 3px 8px !important;
        border-radius: 10px !important;
        font-weight: 500;
    }
    
    .badge-utd {
        background: linear-gradient(135deg, #9b59b6, #8e44ad) !important;
        color: white !important;
        font-size: 11px !important;
        padding: 3px 8px !important;
        border-radius: 10px !important;
        font-weight: 500;
    }
    
    .badge-city {
        background: linear-gradient(135deg, #1abc9c, #16a085) !important;
        color: white !important;
        font-size: 11px !important;
        padding: 3px 8px !important;
        border-radius: 10px !important;
        font-weight: 500;
    }
    
    .badge-warehouse {
        background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
        color: white !important;
        font-size: 11px !important;
        padding: 3px 8px !important;
        border-radius: 10px !important;
        font-weight: 500;
    }
    
    /* TABLA DATATABLES */
    .dataTables_wrapper {
        padding: 0 !important;
    }
    
    .dataTables_wrapper .table {
        margin-bottom: 0 !important;
        font-size: 13px;
    }
    
    .dataTables_wrapper .table thead th {
        background: linear-gradient(135deg, #3498db, #2980b9) !important;
        color: white !important;
        font-weight: 600;
        border: none !important;
        padding: 12px 8px !important;
        vertical-align: middle;
        text-align: center;
    }
    
    .dataTables_wrapper .table tbody td {
        padding: 10px 8px !important;
        vertical-align: middle;
        border-color: #e9ecef !important;
    }
    
    .dataTables_wrapper .table tbody tr:hover {
        background-color: #f8f9fa !important;
    }
    
    /* CONTADOR DE REGISTROS */
    .records-count {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        padding: 8px 15px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
    }
    
    .records-count i {
        margin-right: 8px;
        font-size: 12px;
    }
    
    /* BOTÓN LIMPIAR FILTROS */
    .btn-clear-filters {
        background: #6c757d;
        border: none;
        color: white;
        font-size: 13px;
        padding: 8px 16px;
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    
    .btn-clear-filters:hover {
        background: #5a6268;
        color: white;
    }
  </style>
</head>

<body class="nav-md">
  <div class="container body">
    <div class="main_container">
      
      <!-- Sidebar de Gentelella -->
      <div class="col-md-3 left_col">
        <div class="left_col scroll-view">
          <div class="navbar nav_title" style="border: 0;">
            <a href="index.php" class="site_title">
              <img src="img/ransa.png" alt="LOGIRAN S.A." style="height: 40px;">
            </a>
          </div>

          <div class="clearfix"></div>

          <div class="profile clearfix">
            <div class="profile_pic">
              
            </div>
            <div class="profile_info">
              <span>Bienvenido,</span>
              <h2><?php echo htmlspecialchars($user_name); ?></h2>
              <small><?php echo htmlspecialchars($user_type); ?></small>
            </div>
          </div>

          <br />

          <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
            <div class="menu_section">
              <h3>Menú Principal</h3>
              <ul class="nav side-menu">
                <li>
                  <a href="index.php"><i class="fa fa-home"></i> Inicio</a>
                </li>
                <li>
                  <a href="crear_registro.php"><i class="fa fa-plus-circle"></i> Crear Nuevo</a>
                </li>
                <li class="active menu-modificar-registro">
                  <a href="modificar_registro.php"><i class="fa fa-edit"></i> Modificar Registro</a>
                </li>
                <li>
                  <a href="logout.php"><i class="fa fa-sign-out"></i> Cerrar Sesión</a>
                </li>
              </ul>
            </div>
          </div>

          <div class="sidebar-footer hidden-small">
            <a data-toggle="tooltip" data-placement="top" title="Configuración">
              <span class="fa fa-cog"></span>
            </a>
            <a data-toggle="tooltip" data-placement="top" title="Pantalla Completa" onclick="toggleFullScreen()">
              <span class="fa fa-arrows-alt"></span>
            </a>
            <a data-toggle="tooltip" data-placement="top" title="Recargar" onclick="reloadPage()">
              <span class="fa fa-refresh"></span>
            </a>
            <a data-toggle="tooltip" data-placement="top" title="Cerrar Sesión" href="logout.php">
              <span class="fa fa-power-off"></span>
            </a>
          </div>
        </div>
      </div>

      <!-- CABECERA SUPERIOR -->
      <div class="top_nav">
        <div class="nav_menu">
          <div class="navbar-left">
            <div class="nav toggle">
              <a id="menu_toggle"><i class="fa fa-bars"></i></a>
            </div>
            <div class="nav-title">
              <i class="fa fa-edit"></i> MODIFICAR REGISTRO
              <?php if ($registro_id > 0 && $registro): ?>
                <span class="badge badge-warning ml-2" style="font-size: 12px;">Editando ID: <?= htmlspecialchars($registro_id) ?></span>
              <?php endif; ?>
            </div>
          </div>
          
          <nav class="nav navbar-nav">
            <ul class="navbar-right">
              <li class="nav-item dropdown open">
                <a href="javascript:;" class="user-profile dropdown-toggle" id="navbarDropdown" data-toggle="dropdown">
                  <div class="user-avatar-circle" style="background: <?php echo htmlspecialchars($user_color); ?>; width: 32px; height: 20px; margin-right: 8px;">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                  </div>
                  <?php echo htmlspecialchars($user_name); ?>
                  <span class="fa fa-angle-down"></span>
                </a>
                <div class="dropdown-menu dropdown-usermenu pull-right" aria-labelledby="navbarDropdown">
                  <a class="dropdown-item" href="#"><i class="fa fa-user pull-right"></i> Perfil</a>
                  <a class="dropdown-item" href="#"><i class="fa fa-cog pull-right"></i> Configuración</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="logout.php"><i class="fa fa-sign-out pull-right"></i> Salir</a>
                </div>
              </li>
            </ul>
          </nav>
        </div>
      </div>

      <!-- CONTENIDO PRINCIPAL -->
      <div class="right_col" role="main">
        
        <!-- SI NO HAY REGISTRO SELECCIONADO, MOSTRAR TABLA DE BÚSQUEDA -->
        <?php if (!$registro): ?>
        
        <!-- PANEL DE FILTROS -->
        <div class="row">
          <div class="col-md-12 col-sm-12">
            <div class="x_panel">
              
              <div class="x_content">
                <div class="filter-container">
                  <form id="filtrosForm" class="row">
                    <div class="col-md-3 col-sm-6 mb-3">
                      <label class="form-label">Servicio</label>
                      <select name="servicio" class="form-control filter-select select2-filtro">
                        <option value="">Todos los servicios</option>
                        <?php foreach($servicios as $serv): ?>
                          <option value="<?= htmlspecialchars($serv) ?>" <?= $filtro_servicio == $serv ? 'selected' : '' ?>>
                            <?= htmlspecialchars($serv) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                      <label class="form-label">Gestión</label>
                      <select name="gestion" class="form-control filter-select select2-filtro">
                        <option value="">Todas las gestiones</option>
                        <?php foreach($gestiones as $ges): ?>
                          <option value="<?= htmlspecialchars($ges) ?>" <?= $filtro_gestion == $ges ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ges) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                      <label class="form-label">Almacén</label>
                      <select name="almacen" class="form-control filter-select select2-filtro">
                        <option value="">Todos los almacenes</option>
                        <?php foreach($almacenes as $alm): ?>
                          <option value="<?= htmlspecialchars($alm) ?>" <?= $filtro_almacen == $alm ? 'selected' : '' ?>>
                            <?= htmlspecialchars($alm) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="col-md-3 col-sm-6 mb-3">
                      <label class="form-label">UTD</label>
                      <select name="utd" class="form-control filter-select select2-filtro">
                        <option value="">Todos los UTD</option>
                        <?php foreach($utds as $valor_original => $porcentaje): ?>
                          <option value="<?= htmlspecialchars($valor_original) ?>" <?= $filtro_utd == $valor_original ? 'selected' : '' ?>>
                            <?= htmlspecialchars($porcentaje) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    
                    <div class="col-12">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <a href="modificar_registro.php" class="btn btn-clear-filters">
                            <i class="fa fa-times"></i> Limpiar Filtros
                          </a>
                        </div>
                        <div class="records-count">
                          <i class="fa fa-database"></i> 
                          <span id="totalRecords"><?= count($registros) ?></span> registros
                        </div>
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- PANEL DE LA TABLA -->
        <div class="row">
          <div class="col-md-12 col-sm-12">
            <div class="x_panel">
              <div class="x_title">
                <h2><i class="fa fa-table"></i> Seleccione el Registro a Modificar</h2>
                <div class="clearfix"></div>
              </div>
              <div class="x_content">
                <?php if(isset($error)): ?>
                  <div class="alert alert-danger alert-dismissible fade in" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                      <span aria-hidden="true">×</span>
                    </button>
                    <i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                  </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                  <table id="datatable-modificar" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                      <tr>
                        <th width="5%">ID</th>
                        <th width="15%">Servicio</th>
                        <th width="12%">Ciudad</th>
                        <th width="15%">Almacén</th>
                        <th width="8%">Gestión</th>
                        <th width="8%">UTD</th>
                        <th width="10%">Modo</th>
                        <th width="27%">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(!empty($registros)): ?>
                        <?php foreach($registros as $registro_tabla): ?>
                          <tr data-id="<?= $registro_tabla['id'] ?>">
                            <td class="text-center"><?= htmlspecialchars($registro_tabla['id']) ?></td>
                            <td>
                              <span class="badge badge-service"><?= htmlspecialchars($registro_tabla['servicio'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                              <span class="badge badge-city"><?= htmlspecialchars($registro_tabla[' ciudad'] ?? '') ?></span>
                            </td>
                            <td>
                              <span class="badge badge-warehouse"><?= htmlspecialchars($registro_tabla[' almacen'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                              <span class="badge badge-year"><?= htmlspecialchars($registro_tabla[' gestion'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                              <?php if(!empty($registro_tabla[' utd'])): ?>
                                <span class="badge badge-utd" title="Valor original: <?= htmlspecialchars($registro_tabla[' utd']) ?>">
                                  <?= convertirAPorcentaje($registro_tabla[' utd']) ?>
                                </span>
                              <?php else: ?>
                                <span class="badge bg-secondary">N/A</span>
                              <?php endif; ?>
                            </td>
                            <td class="text-center">
                              <?php if(isset($registro_tabla['modo_especial']) && $registro_tabla['modo_especial'] == 1): ?>
                                <span class="badge-mod-especial">Especial</span>
                              <?php else: ?>
                                <span class="badge-mod-normal">Normal</span>
                              <?php endif; ?>
                            </td>
                            <td class="text-center">
                              <a href="modificar_registro.php?id=<?= htmlspecialchars($registro_tabla['id']) ?>" class="btn btn-modificar">
                                <i class="fa fa-edit"></i> Modificar
                              </a>
                              <a href="index.php" class="btn btn-reporte" style="margin-left: 5px;">
                                <i class="fa fa-eye"></i> Ver
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="8" class="text-center">No se encontraron registros</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <?php else: ?>
        <!-- SI HAY REGISTRO SELECCIONADO, MOSTRAR FORMULARIO DE MODIFICACIÓN -->
        
        <?php if ($registro_id > 0 && !$registro): ?>
        <div class="row">
          <div class="col-md-12 col-sm-12">
            <div class="alert alert-danger">
              <i class="fa fa-exclamation-triangle"></i> No se encontró el registro con ID: <?= htmlspecialchars($registro_id) ?>
              <a href="modificar_registro.php" class="btn btn-sm btn-outline-danger ml-3">Volver a lista</a>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <!-- FORMULARIO DE MODIFICACIÓN -->
        <?php if ($registro): ?>
        <div class="row">
          <div class="col-md-12 col-sm-12">
            <div class="x_panel">
              <div class="x_title">
                <h2><i class="fa fa-edit"></i> Modificar Registro #<?= htmlspecialchars($registro_id) ?></h2>
                <div class="clearfix"></div>
              </div>
              
              <div class="x_content compact-form">
                <form id="formModificarRegistro">
                  <input type="hidden" name="id_registro" value="<?= htmlspecialchars($registro_id) ?>">
                  <input type="hidden" name="guid_actual" value="<?= htmlspecialchars($registro['ID_Tarifas'] ?? '') ?>">
                  
                  <!-- SWITCH MODO TARIFA -->
                  <div class="switch-container">
                    <span class="switch-label">Modo de Tarifa:</span>
                    <label class="switch">
                      <input type="checkbox" id="switchModoEspecial" <?= $modo_actual == 'especial' ? 'checked' : '' ?>>
                      <span class="slider"></span>
                    </label>
                    <span class="mode-label">
                      <span id="modoTextoNormal" class="mode-normal" <?= $modo_actual == 'especial' ? 'style="display:none;"' : '' ?>>Normal</span>
                      <span id="modoTextoEspecial" class="mode-especial" <?= $modo_actual == 'normal' ? 'style="display:none;"' : '' ?>>Especial</span>
                    </span>
                    <input type="hidden" name="modo_especial" id="inputModoEspecial" value="<?= $modo_actual == 'especial' ? '1' : '0' ?>">
                  </div>
                  
                  <!-- INFORMACIÓN GENERAL -->
                  <div class="form-section">
                    <div class="section-title">
                      <i class="fa fa-info-circle"></i> Información General
                    </div>
                    
                    <div class="row">
                      <div class="col-md-4 col-sm-6 mb-3">
                        <label class="form-label">Servicio *</label>
                        <select name="servicio" class="form-control select2-field" id="selectServicio">
                          <option value="">Seleccionar...</option>
                          <?php foreach($servicios as $serv): ?>
                            <option value="<?= htmlspecialchars($serv) ?>" <?= isset($registro['servicio']) && $registro['servicio'] == $serv ? 'selected' : '' ?>>
                              <?= htmlspecialchars($serv) ?>
                            </option>
                          <?php endforeach; ?>
                          <option value="NUEVO">[+ Crear nuevo servicio]</option>
                        </select>
                        <div class="campo-nuevo" id="campoNuevoServicio" style="display: none;">
                          <input type="text" name="nuevo_servicio" class="form-control" placeholder="Nombre del nuevo servicio">
                        </div>
                      </div>
                      
                      <div class="col-md-4 col-sm-6 mb-3">
                        <label class="form-label">Ciudad *</label>
                        <select name="ciudad" class="form-control select2-field" id="selectCiudad">
                          <option value="">Seleccionar...</option>
                          <?php foreach($ciudades as $ciudad): ?>
                            <option value="<?= htmlspecialchars($ciudad) ?>" <?= isset($registro[' ciudad']) && $registro[' ciudad'] == $ciudad ? 'selected' : '' ?>>
                              <?= htmlspecialchars($ciudad) ?>
                            </option>
                          <?php endforeach; ?>
                          <option value="NUEVA">[+ Crear nueva ciudad]</option>
                        </select>
                        <div class="campo-nuevo" id="campoNuevaCiudad" style="display: none;">
                          <input type="text" name="nueva_ciudad" class="form-control" placeholder="Nombre de la nueva ciudad">
                        </div>
                      </div>
                      
                      <div class="col-md-4 col-sm-6 mb-3">
                        <label class="form-label">Almacén *</label>
                        <select name="almacen" class="form-control select2-field" id="selectAlmacen">
                          <option value="">Seleccionar...</option>
                          <?php foreach($almacenes as $alm): ?>
                            <option value="<?= htmlspecialchars($alm) ?>" <?= isset($registro[' almacen']) && $registro[' almacen'] == $alm ? 'selected' : '' ?>>
                              <?= htmlspecialchars($alm) ?>
                            </option>
                          <?php endforeach; ?>
                          <option value="NUEVO">[+ Crear nuevo almacén]</option>
                        </select>
                        <div class="campo-nuevo" id="campoNuevoAlmacen" style="display: none;">
                          <input type="text" name="nuevo_almacen" class="form-control" placeholder="Nombre del nuevo almacén">
                        </div>
                      </div>
                      
                      <div class="col-md-3 col-sm-6 mb-3">
                        <label class="form-label">Gestión</label>
                        <select name="gestion" class="form-control select2-field">
                          <?php foreach($gestiones as $ges): ?>
                            <option value="<?= htmlspecialchars($ges) ?>" <?= isset($registro[' gestion']) && $registro[' gestion'] == $ges ? 'selected' : '' ?>>
                              <?= htmlspecialchars($ges) ?>
                            </option>
                          <?php endforeach; ?>
                          <option value="<?= date('Y') ?>" <?= !isset($registro[' gestion']) ? 'selected' : '' ?>>
                            <?= date('Y') ?> (Actual)
                          </option>
                        </select>
                      </div>
                      
                      <div class="col-md-3 col-sm-6 mb-3">
                        <label class="form-label">UTD (%)</label>
                        <div class="input-group">
                          <input type="text" name="utd" class="form-control" 
                                 placeholder="0.15" 
                                 value="<?= htmlspecialchars($registro[' utd'] ?? '0.15') ?>">
                          <div class="input-group-append">
                            <span class="input-group-text">%</span>
                          </div>
                        </div>
                        <small class="text-muted">Decimal (0.15 = 15%)</small>
                      </div>
                    </div>
                  </div>
                  
                  <!-- TARIFAS NORMALES -->
                  <div class="form-section" id="seccionTarifasNormales" style="<?= $modo_actual == 'especial' ? 'display:none;' : '' ?>">
                    <div class="section-title">
                      <i class="fa fa-dollar-sign"></i> Tarifas Normales (USD)
                    </div>
                    
                    <div class="row">
                      <div class="col-md-6">
                        <h6 style="color: #009A3F; margin-bottom: 15px;"><i class="fa fa-truck-loading"></i> Recepción</h6>
                        
                        <div class="row">
                          <div class="col-12 mb-2">
                            <label class="form-label">Descarga Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="descarga_pall" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' descarga_pall'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Estibaje Cajas/Bultos</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="estibaje_cajas" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' estibaje_cajas'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="pall_in" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' pall_in'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Caja/Bulto</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_cajas_recepcion" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' operacion_cajas_recepcion'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Unidad</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_und_recepcion" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' operacion_und_recepcion'] ?? '0,00') ?>">
                            </div>
                          </div>
                        </div>
                        
                        <h6 style="color: #009A3F; margin: 20px 0 15px 0;"><i class="fa fa-warehouse"></i> Almacenaje</h6>
                        
                        <div class="row">
                          <div class="col-12 mb-2">
                            <label class="form-label">Almacenaje m²</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="m2_almacen" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' m2_almacen'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Almacenaje Posición</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="almacen_pos" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' almacen_pos'] ?? '0,00') ?>">
                            </div>
                          </div>
                        </div>
                      </div>
                      
                      <div class="col-md-6">
                        <h6 style="color: #009A3F; margin-bottom: 15px;"><i class="fa fa-shipping-fast"></i> Despacho</h6>
                        
                        <div class="row">
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="pall_out" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' pall_out'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Caja/Bulto</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_cajas_despacho" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' operacion_cajas_despacho'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Unidad</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_und_despacho" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' operacion_und_despacho'] ?? '0,00') ?>">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Carguío Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="carga_pall" class="form-control tarifa-normal" 
                                     placeholder="0,00" 
                                     value="<?= htmlspecialchars($registro[' carga_pall'] ?? '0,00') ?>">
                            </div>
                          </div>
                        </div>
                        
                        <div class="alert alert-info mt-3" style="font-size: 12px;">
                          <i class="fa fa-info-circle"></i> 
                          <strong>Nota:</strong> El campo "Estibaje Cajas/Bultos" se utiliza tanto para recepción como para despacho.
                        </div>
                      </div>
                    </div>
                  </div>
                  
                  <!-- TARIFAS ESPECIALES -->
                  <div class="form-section" id="seccionTarifasEspeciales" style="<?= $modo_actual == 'normal' ? 'display:none;' : '' ?>">
                    <div class="section-title">
                      <i class="fa fa-star"></i> Tarifas Especiales
                    </div>
                    
                    <div class="row">
                      <div class="col-12">
                        <div class="alert alert-info mb-3">
                          <i class="fa fa-info-circle"></i> 
                          <strong>Instrucciones:</strong> Modifique los servicios especiales según sea necesario.
                          Puede agregar, eliminar o modificar tarifas.
                        </div>
                      </div>
                    </div>
                    
                    <!-- Contenedor para tarifas especiales dinámicas -->
                    <div id="contenedorTarifasEspeciales">
                      <?php if ($modo_actual == 'especial' && !empty($tarifas_especiales)): ?>
                        <?php foreach($tarifas_especiales as $index => $tarifa): ?>
                          <div class="tarifa-especial-item" id="tarifaEspecial<?= $index + 1 ?>">
                            <div class="tarifa-especial-header">
                              <h6 style="margin: 0; color: #007bff;">Tarifa Especial #<?= $index + 1 ?></h6>
                              <button type="button" class="btn-eliminar-tarifa" onclick="eliminarTarifaEspecial(<?= $index + 1 ?>)">
                                <i class="fa fa-times"></i> Eliminar
                              </button>
                            </div>
                            <div class="row">
                              <div class="col-md-5 mb-2">
                                <label class="form-label">Servicio Especial *</label>
                                <input type="text" class="form-control servicio-especial" 
                                       placeholder="Ej: Palletizado, Enbalsamado, Etiquetado" 
                                       value="<?= htmlspecialchars($tarifa['Servicio'] ?? '') ?>"
                                       data-index="<?= $index + 1 ?>">
                              </div>
                              <div class="col-md-3 mb-2">
                                <label class="form-label">Costo (USD) *</label>
                                <div class="input-group">
                                  <div class="input-group-prepend">
                                    <span class="input-group-text">$</span>
                                  </div>
                                  <input type="text" class="form-control costo-especial" 
                                         placeholder="0,00" 
                                         value="<?= htmlspecialchars($tarifa['costo'] ?? '0,00') ?>"
                                         data-index="<?= $index + 1 ?>">
                                </div>
                              </div>
                              <div class="col-md-4 mb-2">
                                <label class="form-label">Frecuencia *</label>
                                <select class="form-control frecuencia-especial" data-index="<?= $index + 1 ?>">
                                  <option value="Mensualizado" <?= isset($tarifa['Frecuencia']) && $tarifa['Frecuencia'] == 'Mensualizado' ? 'selected' : '' ?>>Mensualizado</option>
                                  <option value="Anual" <?= isset($tarifa['Frecuencia']) && $tarifa['Frecuencia'] == 'Anual' ? 'selected' : '' ?>>Anual</option>
                                  <option value="Dia" <?= isset($tarifa['Frecuencia']) && $tarifa['Frecuencia'] == 'Dia' ? 'selected' : '' ?>>Día</option>
                                </select>
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                    
                    <!-- Botón para agregar nueva tarifa especial -->
                    <div class="text-center mt-3">
                      <button type="button" id="btnAgregarTarifaEspecial" class="btn btn-primary btn-sm">
                        <i class="fa fa-plus"></i> Agregar Tarifa Especial
                      </button>
                    </div>
                  </div>
                  
                  <!-- BOTONES -->
                  <div class="text-center mt-4">
                    <button type="button" id="btnGuardarModificacion" class="btn btn-warning">
                      <i class="fa fa-save"></i> Guardar Cambios
                    </button>
                    <a href="modificar_registro.php" class="btn btn-secondary" style="margin-left: 10px;">
                      <i class="fa fa-times"></i> Volver a lista
                    </a>
                  </div>
                  
                </form>
              </div>
            </div>
          </div>
        </div>
        <?php endif; ?>
        
        <?php endif; ?>

      </div>

      <footer>
        <div class="pull-right">
          Sistema de Cotización de Tarifas © <?php echo date('Y') ?> | Usuario: <?php echo htmlspecialchars($user_name); ?>
        </div>
        <div class="clearfix"></div>
      </footer>
    </div>
  </div>

  <!-- MODAL DE CONFIRMACIÓN -->
  <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #3498db, #2980b9); color: white;">
          <h5 class="modal-title"><i class="fa fa-check-circle"></i> Confirmar Modificación</h5>
          <button type="button" class="close" data-dismiss="modal" style="color: white;">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Revise los cambios antes de guardar:</p>
          
          <div class="data-preview" id="dataPreview">
            <!-- Datos se cargarán aquí -->
          </div>
          
          <div class="text-center mt-3">
            <button type="button" class="btn btn-warning" id="btnConfirmarModificacion">
              <i class="fa fa-check"></i> Sí, Guardar Cambios
            </button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal" style="margin-left: 10px;">
              <i class="fa fa-times"></i> Cancelar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- SCRIPTS -->
  <script src="vendors/jquery/dist/jquery.min.js"></script>
  <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="vendors/nprogress/nprogress.js"></script>
  <script src="vendors/select2/dist/js/select2.min.js"></script>
  <script src="vendors/datatables.net/js/jquery.dataTables.min.js"></script>
  <script src="vendors/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
  <script src="build/js/custom.min.js"></script>

  <script>
  $(document).ready(function() {
    
    // Inicializar Select2
    $('.select2-filtro, .select2-field').select2({
      placeholder: "Seleccionar...",
      width: '100%'
    });
    
    // Inicializar DataTable en la tabla de búsqueda
    <?php if (!$registro): ?>
    var table = $('#datatable-modificar').DataTable({
      language: {
        "sProcessing": "Procesando...",
        "sLengthMenu": "Mostrar _MENU_ registros",
        "sZeroRecords": "No se encontraron resultados",
        "sEmptyTable": "Ningún dato disponible en esta tabla",
        "sInfo": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
        "sInfoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
        "sInfoFiltered": "(filtrado de un total de _MAX_ registros)",
        "sSearch": "Buscar:",
        "sLoadingRecords": "Cargando...",
        "oPaginate": {
          "sFirst": "Primero",
          "sLast": "Último",
          "sNext": "Siguiente",
          "sPrevious": "Anterior"
        }
      },
      pageLength: 10,
      order: [[0, 'desc']],
      responsive: true,
      dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
           '<"row"<"col-sm-12"tr>>' +
           '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
      columnDefs: [
        { orderable: false, targets: 7 } // Columna de acciones no ordenable
      ]
    });
    
    // Actualizar contador de registros
    function updateRecordsCount() {
      var count = table.rows().count();
      $('#totalRecords').text(count);
    }
    
    updateRecordsCount();
    
    // Filtros automáticos
    $('.select2-filtro').on('change', function() {
      const formData = $('#filtrosForm').serialize();
      const urlParams = new URLSearchParams(formData);
      
      let newUrl = window.location.pathname;
      if (formData) {
        newUrl += '?' + urlParams.toString();
      }
      
      window.history.replaceState({}, '', newUrl);
      window.location.reload();
    });
    <?php endif; ?>
    
    // FUNCIONALIDADES DEL FORMULARIO DE MODIFICACIÓN
    <?php if ($registro): ?>
    
    // Contador para tarifas especiales
    let contadorTarifasEspeciales = <?= $modo_actual == 'especial' ? count($tarifas_especiales) : 0 ?>;
    
    // Función para agregar tarifa especial
    function agregarTarifaEspecial() {
      contadorTarifasEspeciales++;
      const html = `
        <div class="tarifa-especial-item" id="tarifaEspecial${contadorTarifasEspeciales}">
          <div class="tarifa-especial-header">
            <h6 style="margin: 0; color: #007bff;">Tarifa Especial #${contadorTarifasEspeciales}</h6>
            <button type="button" class="btn-eliminar-tarifa" onclick="eliminarTarifaEspecial(${contadorTarifasEspeciales})">
              <i class="fa fa-times"></i> Eliminar
            </button>
          </div>
          <div class="row">
            <div class="col-md-5 mb-2">
              <label class="form-label">Servicio Especial *</label>
              <input type="text" class="form-control servicio-especial" 
                     placeholder="Ej: Palletizado, Enbalsamado, Etiquetado" 
                     data-index="${contadorTarifasEspeciales}">
            </div>
            <div class="col-md-3 mb-2">
              <label class="form-label">Costo (USD) *</label>
              <div class="input-group">
                <div class="input-group-prepend">
                  <span class="input-group-text">$</span>
                </div>
                <input type="text" class="form-control costo-especial" 
                       placeholder="0,00" value="0,00" 
                       data-index="${contadorTarifasEspeciales}">
              </div>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label">Frecuencia *</label>
              <select class="form-control frecuencia-especial" data-index="${contadorTarifasEspeciales}">
                <option value="Mensualizado">Mensualizado</option>
                <option value="Anual">Anual</option>
                <option value="Dia">Día</option>
              </select>
            </div>
          </div>
        </div>
      `;
      
      $('#contenedorTarifasEspeciales').append(html);
    }
    
    // Función para eliminar tarifa especial
    window.eliminarTarifaEspecial = function(id) {
      $(`#tarifaEspecial${id}`).remove();
      
      // Renumerar las tarifas restantes
      let index = 1;
      $('.tarifa-especial-item').each(function() {
        $(this).find('h6').text(`Tarifa Especial #${index}`);
        index++;
      });
    };
    
    // Mostrar/ocultar campos para nuevos valores
    $('#selectServicio').on('change', function() {
      if ($(this).val() === 'NUEVO') {
        $('#campoNuevoServicio').show().find('input').focus();
      } else {
        $('#campoNuevoServicio').hide().find('input').val('');
      }
    });
    
    $('#selectCiudad').on('change', function() {
      if ($(this).val() === 'NUEVA') {
        $('#campoNuevaCiudad').show().find('input').focus();
      } else {
        $('#campoNuevaCiudad').hide().find('input').val('');
      }
    });
    
    $('#selectAlmacen').on('change', function() {
      if ($(this).val() === 'NUEVO') {
        $('#campoNuevoAlmacen').show().find('input').focus();
      } else {
        $('#campoNuevoAlmacen').hide().find('input').val('');
      }
    });
    
    // Switch modo especial
    $('#switchModoEspecial').on('change', function() {
      const isEspecial = $(this).is(':checked');
      
      if (isEspecial) {
        $('#seccionTarifasNormales').hide();
        $('#seccionTarifasEspeciales').show();
        $('#modoTextoNormal').hide();
        $('#modoTextoEspecial').show();
        $('#inputModoEspecial').val('1');
        
        if ($('#contenedorTarifasEspeciales').children().length === 0) {
          agregarTarifaEspecial();
        }
      } else {
        $('#seccionTarifasNormales').show();
        $('#seccionTarifasEspeciales').hide();
        $('#modoTextoNormal').show();
        $('#modoTextoEspecial').hide();
        $('#inputModoEspecial').val('0');
      }
    });
    
    // Botón para agregar tarifa especial
    $('#btnAgregarTarifaEspecial').on('click', function() {
      agregarTarifaEspecial();
    });
    
    // Formatear números al perder foco
    $('input[type="text"]').on('blur', function() {
      let value = $(this).val().trim();
      if (value && /^\d+(\.\d+)?$/.test(value.replace(',', '.'))) {
        let num = parseFloat(value.replace(',', '.'));
        $(this).val(num.toFixed(2).replace('.', ','));
      }
    });
    
    // Función para obtener datos de tarifas especiales
    function obtenerTarifasEspeciales() {
      const tarifas = [];
      
      $('.tarifa-especial-item').each(function() {
        const servicio = $(this).find('.servicio-especial').val().trim();
        const costo = $(this).find('.costo-especial').val().trim();
        const frecuencia = $(this).find('.frecuencia-especial').val();
        
        if (servicio) {
          tarifas.push({
            servicio: servicio,
            costo: costo,
            frecuencia: frecuencia
          });
        }
      });
      
      return tarifas;
    }
    
    // Botón Guardar Modificación
    $('#btnGuardarModificacion').on('click', function(e) {
      e.preventDefault();
      
      // Obtener valores
      let servicio = $('#selectServicio').val();
      let nuevoServicio = $('input[name="nuevo_servicio"]').val().trim();
      let ciudad = $('#selectCiudad').val();
      let nuevaCiudad = $('input[name="nueva_ciudad"]').val().trim();
      let almacen = $('#selectAlmacen').val();
      let nuevoAlmacen = $('input[name="nuevo_almacen"]').val().trim();
      let modoEspecial = $('#inputModoEspecial').val() === '1';
      
      // Validar campos obligatorios
      if ((!servicio || servicio === 'NUEVO') && !nuevoServicio) {
        alert('Por favor seleccione o ingrese un servicio');
        return;
      }
      
      if ((!ciudad || ciudad === 'NUEVA') && !nuevaCiudad) {
        alert('Por favor seleccione o ingrese una ciudad');
        return;
      }
      
      if ((!almacen || almacen === 'NUEVO') && !nuevoAlmacen) {
        alert('Por favor seleccione o ingrese un almacén');
        return;
      }
      
      // Validar tarifas especiales si está en modo especial
      if (modoEspecial) {
        const tarifasEspeciales = obtenerTarifasEspeciales();
        if (tarifasEspeciales.length === 0) {
          alert('Debe agregar al menos una tarifa especial');
          return;
        }
        
        for (let tarifa of tarifasEspeciales) {
          if (!tarifa.servicio.trim()) {
            alert('Todos los servicios especiales deben tener un nombre');
            return;
          }
          if (!tarifa.costo || tarifa.costo === '0,00') {
            alert('Todos los servicios especiales deben tener un costo');
            return;
          }
        }
      }
      
      // Determinar valores finales
      let servicioFinal = servicio === 'NUEVO' ? nuevoServicio : $('#selectServicio option:selected').text();
      let ciudadFinal = ciudad === 'NUEVA' ? nuevaCiudad : $('#selectCiudad option:selected').text();
      let almacenFinal = almacen === 'NUEVO' ? nuevoAlmacen : $('#selectAlmacen option:selected').text();
      
      // Preparar datos para mostrar
      let datos = {
        id: $('input[name="id_registro"]').val(),
        servicio: servicioFinal,
        ciudad: ciudadFinal,
        almacen: almacenFinal,
        gestion: $('select[name="gestion"]').val(),
        utd: $('input[name="utd"]').val(),
        modo_especial: modoEspecial ? 'Sí' : 'No'
      };
      
      // Agregar datos según el modo
      if (!modoEspecial) {
        datos.descarga_pall = $('input[name="descarga_pall"]').val();
        datos.estibaje_cajas = $('input[name="estibaje_cajas"]').val();
        datos.pall_in = $('input[name="pall_in"]').val();
        datos.operacion_cajas_recepcion = $('input[name="operacion_cajas_recepcion"]').val();
        datos.operacion_und_recepcion = $('input[name="operacion_und_recepcion"]').val();
        datos.m2_almacen = $('input[name="m2_almacen"]').val();
        datos.almacen_pos = $('input[name="almacen_pos"]').val();
        datos.pall_out = $('input[name="pall_out"]').val();
        datos.operacion_cajas_despacho = $('input[name="operacion_cajas_despacho"]').val();
        datos.operacion_und_despacho = $('input[name="operacion_und_despacho"]').val();
        datos.carga_pall = $('input[name="carga_pall"]').val();
      } else {
        datos.tarifas_especiales = obtenerTarifasEspeciales();
      }
      
      // Mostrar datos en popup
      let html = `
        <div class="data-row">
          <div class="data-label">ID:</div>
          <div class="data-value"><strong>${datos.id}</strong></div>
        </div>
        <div class="data-row">
          <div class="data-label">Servicio:</div>
          <div class="data-value">${datos.servicio}</div>
        </div>
        <div class="data-row">
          <div class="data-label">Ciudad:</div>
          <div class="data-value">${datos.ciudad}</div>
        </div>
        <div class="data-row">
          <div class="data-label">Almacén:</div>
          <div class="data-value">${datos.almacen}</div>
        </div>
        <div class="data-row">
          <div class="data-label">Gestión:</div>
          <div class="data-value">${datos.gestion}</div>
        </div>
        <div class="data-row">
          <div class="data-label">UTD:</div>
          <div class="data-value">${datos.utd}%</div>
        </div>
        <div class="data-row">
          <div class="data-label">Modo Especial:</div>
          <div class="data-value">${datos.modo_especial}</div>
        </div>
      `;
      
      if (!modoEspecial) {
        html += `
          <h6 style="color: #009A3F; margin-top: 10px;">Tarifas Normales</h6>
          <div class="data-row">
            <div class="data-label">Descarga Pallet:</div>
            <div class="data-value">$${datos.descarga_pall}</div>
          </div>
          <div class="data-row">
            <div class="data-label">Estibaje Cajas/Bultos:</div>
            <div class="data-value">$${datos.estibaje_cajas}</div>
          </div>
        `;
      } else {
        html += `
          <h6 style="color: #007bff; margin-top: 10px;">Tarifas Especiales</h6>
        `;
        
        datos.tarifas_especiales.forEach((tarifa, index) => {
          html += `
            <div class="data-row">
              <div class="data-label">${tarifa.servicio}:</div>
              <div class="data-value">$${tarifa.costo} (${tarifa.frecuencia})</div>
            </div>
          `;
        });
      }
      
      $('#dataPreview').html(html);
      $('#confirmModal').modal('show');
      
      // Guardar datos para usar después
      window.datosParaModificar = datos;
      window.modoEspecialParaModificar = modoEspecial;
    });
    
    // Confirmar modificación
    $('#btnConfirmarModificacion').on('click', function() {
      let $btn = $(this);
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
      
      // Preparar datos para enviar
      let datosEnvio = {
        confirmar_modificacion: 'true',
        id_registro: window.datosParaModificar.id,
        servicio_final: window.datosParaModificar.servicio,
        ciudad_final: window.datosParaModificar.ciudad,
        almacen_final: window.datosParaModificar.almacen,
        gestion_final: window.datosParaModificar.gestion,
        utd_final: window.datosParaModificar.utd,
        modo_especial: window.modoEspecialParaModificar ? '1' : '0',
        guid_actual: $('input[name="guid_actual"]').val()
      };
      
      // Agregar datos según el modo
      if (!window.modoEspecialParaModificar) {
        datosEnvio.descarga_pall = window.datosParaModificar.descarga_pall;
        datosEnvio.estibaje_cajas = window.datosParaModificar.estibaje_cajas;
        datosEnvio.pall_in = window.datosParaModificar.pall_in;
        datosEnvio.operacion_cajas_recepcion = window.datosParaModificar.operacion_cajas_recepcion;
        datosEnvio.operacion_und_recepcion = window.datosParaModificar.operacion_und_recepcion;
        datosEnvio.m2_almacen = window.datosParaModificar.m2_almacen;
        datosEnvio.almacen_pos = window.datosParaModificar.almacen_pos;
        datosEnvio.pall_out = window.datosParaModificar.pall_out;
        datosEnvio.operacion_cajas_despacho = window.datosParaModificar.operacion_cajas_despacho;
        datosEnvio.operacion_und_despacho = window.datosParaModificar.operacion_und_despacho;
        datosEnvio.carga_pall = window.datosParaModificar.carga_pall;
      } else {
        if (window.datosParaModificar.tarifas_especiales) {
          datosEnvio.tarifas_especiales = JSON.stringify(window.datosParaModificar.tarifas_especiales);
        }
      }
      
      // Enviar datos por AJAX
      $.ajax({
        url: 'modificar_registro.php',
        method: 'POST',
        data: datosEnvio,
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            $('#confirmModal').modal('hide');
            let mensaje = `✅ ${response.message}`;
            
            if (confirm(mensaje + '\n\n¿Desea ver el registro modificado?')) {
              window.location.href = `index.php`;
            } else {
              window.location.href = 'modificar_registro.php';
            }
          } else {
            alert('❌ ' + response.message);
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Sí, Guardar Cambios');
          }
        },
        error: function(xhr, status, error) {
          alert('❌ Error al conectar con el servidor: ' + error);
          $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Sí, Guardar Cambios');
        }
      });
    });
    
    <?php endif; ?>
    
  });
  </script>

</body>
</html>