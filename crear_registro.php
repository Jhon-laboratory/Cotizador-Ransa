<?php
// crear_registro.php - SISTEMA DE CREACIÓN DE NUEVAS TARIFAS CON MODO ESPECIAL Y GUID
session_start();
require_once 'conexion.php';

// ===== VERIFICAR SI EL USUARIO ESTÁ LOGUEADO =====
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit;
}

// ===== Validar si usuario puede crear opciones basado en id_perfil =====
$puede_crear_registro = false;
if (isset($_SESSION['id_perfil'])) {
    if ($_SESSION['id_perfil'] == 2) {
        $puede_crear_registro = true;
    }
}

// Si no tiene permiso, redirigir al index
if (!$puede_crear_registro) {
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
$servicios = $ciudades = $almacenes = $gestiones = [];

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
} catch(Exception $e) {
    // Error silencioso
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

// Guardar después de confirmación (AJAX)
if (isset($_POST['confirmar_guardado']) && $_POST['confirmar_guardado'] === 'true') {
    header('Content-Type: application/json');
    
    try {
        $servicio = trim($_POST['servicio_final'] ?? '');
        $ciudad = trim($_POST['ciudad_final'] ?? '');
        $almacen = trim($_POST['almacen_final'] ?? '');
        $gestion = (int) trim($_POST['gestion_final'] ?? date('Y'));
        $utd = trim($_POST['utd_final'] ?? '0.15');
        $modo_especial = trim($_POST['modo_especial'] ?? '0');
        
        // Validar campos obligatorios
        if (empty($servicio) || empty($ciudad) || empty($almacen)) {
            throw new Exception('Servicio, Ciudad y Almacén son campos obligatorios');
        }
        
        // Generar GUID para modo especial
        $guid_tarifa = '';
        if ($modo_especial === '1') {
            $guid_tarifa = com_create_guid();
            // Limpiar llaves si las tiene
            $guid_tarifa = trim($guid_tarifa, '{}');
        }
        
        // Si NO es modo especial, obtener las tarifas normales
        if ($modo_especial === '0') {
            $descarga_pall = trim($_POST['descarga_pall'] ?? '0,000');
            $estibaje_cajas = trim($_POST['estibaje_cajas'] ?? '0,000');
            $pall_in = trim($_POST['pall_in'] ?? '0,000');
            $operacion_cajas_recepcion = trim($_POST['operacion_cajas_recepcion'] ?? '0,000');
            $operacion_und_recepcion = trim($_POST['operacion_und_recepcion'] ?? '0,000');
            $m2_almacen = trim($_POST['m2_almacen'] ?? '0,000');
            $almacen_pos = trim($_POST['almacen_pos'] ?? '0,000');
            $pall_out = trim($_POST['pall_out'] ?? '0,000');
            $operacion_cajas_despacho = trim($_POST['operacion_cajas_despacho'] ?? '0,000');
            $operacion_und_despacho = trim($_POST['operacion_und_despacho'] ?? '0,000');
            $carga_pall = trim($_POST['carga_pall'] ?? '0,000');
        } else {
            // Si es modo especial, todos los campos de tarifa van vacíos
            $descarga_pall = '0,000';
            $estibaje_cajas = '0,000';
            $pall_in = '0,000';
            $operacion_cajas_recepcion = '0,000';
            $operacion_und_recepcion = '0,000';
            $m2_almacen = '0,000';
            $almacen_pos = '0,000';
            $pall_out = '0,000';
            $operacion_cajas_despacho = '0,000';
            $operacion_und_despacho = '0,000';
            $carga_pall = '0,000';
        }
        
        // CONSULTA CORREGIDA según tu estructura de BD CON GUID
        $sql = "INSERT INTO externos.CotizadorTarifas (
            servicio, 
            [ ciudad], 
            [ almacen], 
            [ pall_in], 
            [ descarga_pall], 
            [ operacion_cajas_recepcion], 
            [ operacion_und_recepcion], 
            [ almacen_pos], 
            [ m2_almacen], 
            [ pall_out], 
            [ carga_pall], 
            [ operacion_cajas_despacho], 
            [ operacion_und_despacho], 
            [ estibaje_cajas], 
            [ gestion], 
            [ utd],
            [modo_especial],
            [ID_Tarifas]
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = array(
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
            $guid_tarifa
        );
        
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        
        if ($stmt === false) {
            $errors = sqlsrv_errors();
            $error_msg = 'Error al preparar consulta SQL';
            if ($errors) {
                $error_msg .= ': ' . $errors[0]['message'];
            }
            throw new Exception($error_msg);
        }
        
        if (!sqlsrv_execute($stmt)) {
            $errors = sqlsrv_errors();
            $error_msg = 'Error al ejecutar consulta SQL';
            if ($errors) {
                $error_msg .= ': ' . $errors[0]['message'];
            }
            throw new Exception($error_msg);
        }
        
        // OBTENER EL ID GENERADO (numérico)
        $sql_id = "SELECT SCOPE_IDENTITY() AS new_id";
        $stmt_id = sqlsrv_query($conn, $sql_id);
        $row_id = sqlsrv_fetch_array($stmt_id, SQLSRV_FETCH_ASSOC);
        $nuevo_id = $row_id['new_id'];
        
        // Si es modo especial, guardar las tarifas especiales con el MISMO GUID
        if ($modo_especial === '1' && isset($_POST['tarifas_especiales'])) {
            $tarifas_especiales = json_decode($_POST['tarifas_especiales'], true);
            
            if (is_array($tarifas_especiales)) {
                foreach ($tarifas_especiales as $tarifa) {
                    $servicio_tarifa = trim($tarifa['servicio'] ?? '');
                    $costo = trim($tarifa['costo'] ?? '0,000');
                    $frecuencia = trim($tarifa['frecuencia'] ?? 'Mensualizado');
                    
                    if (!empty($servicio_tarifa)) {
                        // Convertir coma a punto para BD
                        $costo_bd = str_replace(',', '.', $costo);
                        
                        // Determinar tabla correcta
                        $tabla_especial = 'externos.TarifaEspecial'; // Primero intentar esta
                        
                        $sql_especial = "INSERT INTO $tabla_especial 
                                        (Servicio, costo, ID_Tarifa, Frecuencia, fecha_creacion) 
                                        VALUES (?, ?, ?, ?, GETDATE())";
                        
                        $params_especial = array(
                            $servicio_tarifa,
                            $costo_bd,
                            $guid_tarifa,  // USAR EL MISMO GUID
                            $frecuencia
                        );
                        
                        $stmt_especial = sqlsrv_prepare($conn, $sql_especial, $params_especial);
                        
                        if ($stmt_especial === false) {
                            // Intentar con otra tabla si falla
                            $tabla_especial = 'DPL.externos.TarifaEspecial';
                            $sql_especial = "INSERT INTO $tabla_especial 
                                            (Servicio, costo, ID_Tarifa, Frecuencia, fecha_creacion) 
                                            VALUES (?, ?, ?, ?, GETDATE())";
                            
                            $stmt_especial = sqlsrv_prepare($conn, $sql_especial, $params_especial);
                        }
                        
                        if ($stmt_especial === false) {
                            error_log("Error preparando tarifa especial: " . print_r(sqlsrv_errors(), true));
                            continue; // Continuar con la siguiente tarifa
                        }
                        
                        if (!sqlsrv_execute($stmt_especial)) {
                            error_log("Error ejecutando tarifa especial: " . print_r(sqlsrv_errors(), true));
                        }
                        
                        if ($stmt_especial) sqlsrv_free_stmt($stmt_especial);
                    }
                }
            }
        }
        
        sqlsrv_free_stmt($stmt);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Registro creado exitosamente!',
            'id' => $nuevo_id, // ID numérico para retrocompatibilidad
            'guid' => $guid_tarifa, // GUID para registros especiales
            'modo_especial' => $modo_especial
        ]);
        exit;
        
    } catch(Exception $e) {
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
  <title>CREAR NUEVO REGISTRO - SISTEMA DE COTIZACIÓN</title>
  
  <!-- Gentelella CSS -->
  <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
  <link href="vendors/select2/dist/css/select2.min.css" rel="stylesheet">
  <link href="build/css/custom.min.css" rel="stylesheet">
  
  <style>
    .form-section {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        border-left: 4px solid #28a745;
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
        color: #28a745;
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
        background: linear-gradient(135deg, #009A3F, #00c853);
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
    .menu-crear-registro {
        background: linear-gradient(135deg, #f39c12, #e67e22) !important;
        border-left: 4px solid #ebefee !important;
    }
    
    .menu-crear-registro a {
        color: white !important;
        font-weight: 600 !important;
    }
    
    .menu-crear-registro i {
        color: white !important;
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
                <li class="active menu-crear-registro">
                  <a href="crear_registro.php"><i class="fa fa-plus-circle"></i> Crear Nuevo</a>
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
        
        <div class="row">
          <div class="col-md-12 col-sm-12">
            <div class="x_panel">
              <div class="x_title">
                <h2><i class="fa fa-edit"></i> Formulario de Nuevo Registro</h2>
                <div class="clearfix"></div>
              </div>
              
              <div class="x_content compact-form">
                <form id="formCrearRegistro">
                  
                  <!-- SWITCH MODO TARIFA -->
                  <div class="switch-container">
                    <span class="switch-label">Modo de Tarifa:</span>
                    <label class="switch">
                      <input type="checkbox" id="switchModoEspecial">
                      <span class="slider"></span>
                    </label>
                    <span class="mode-label">
                      <span id="modoTextoNormal" class="mode-normal">Normal</span>
                      <span id="modoTextoEspecial" class="mode-especial" style="display: none;">Especial</span>
                    </span>
                    <input type="hidden" name="modo_especial" id="inputModoEspecial" value="0">
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
                            <option value="<?= htmlspecialchars($serv) ?>"><?= htmlspecialchars($serv) ?></option>
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
                            <option value="<?= htmlspecialchars($ciudad) ?>"><?= htmlspecialchars($ciudad) ?></option>
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
                            <option value="<?= htmlspecialchars($alm) ?>"><?= htmlspecialchars($alm) ?></option>
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
                          <option value="<?= date('Y') ?>"><?= date('Y') ?> (Actual)</option>
                          <?php foreach($gestiones as $ges): ?>
                            <option value="<?= htmlspecialchars($ges) ?>"><?= htmlspecialchars($ges) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      
                      <div class="col-md-3 col-sm-6 mb-3">
                        <label class="form-label">UTD (%)</label>
                        <div class="input-group">
                          <input type="text" name="utd" class="form-control" placeholder="0.15" value="0.15">
                          <div class="input-group-append">
                            <span class="input-group-text">%</span>
                          </div>
                        </div>
                        <small class="text-muted">Decimal (0.15 = 15%)</small>
                      </div>
                    </div>
                  </div>
                  
                  <!-- TARIFAS NORMALES (VISIBLE POR DEFECTO) -->
                  <div class="form-section" id="seccionTarifasNormales">
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
                              <input type="text" name="descarga_pall" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Estibaje Cajas/Bultos</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="estibaje_cajas" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="pall_in" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Caja/Bulto</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_cajas_recepcion" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Unidad</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_und_recepcion" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
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
                              <input type="text" name="m2_almacen" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Almacenaje Posición</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="almacen_pos" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
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
                              <input type="text" name="pall_out" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Caja/Bulto</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_cajas_despacho" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Unidad</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_und_despacho" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Carguío Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="carga_pall" class="form-control tarifa-normal" placeholder="0,000" value="0,000">
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
                  
                  <!-- TARIFAS ESPECIALES (OCULTO POR DEFECTO) -->
                  <div class="form-section" id="seccionTarifasEspeciales" style="display: none;">
                    <div class="section-title">
                      <i class="fa fa-star"></i> Tarifas Especiales
                    </div>
                    
                    <div class="row">
                      <div class="col-12">
                        <div class="alert alert-info mb-3">
                          <i class="fa fa-info-circle"></i> 
                          <strong>Instrucciones:</strong> Agregue servicios especiales con sus respectivos costos y frecuencias.
                          Puede agregar tantos como necesite.
                        </div>
                      </div>
                    </div>
                    
                    <!-- Contenedor para tarifas especiales dinámicas -->
                    <div id="contenedorTarifasEspeciales">
                      <!-- Las tarifas especiales se agregarán aquí dinámicamente -->
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
                    <button type="button" id="btnGuardar" class="btn btn-success">
                      <i class="fa fa-save"></i> Guardar Registro
                    </button>
                    <a href="index.php" class="btn btn-secondary" style="margin-left: 10px;">
                      <i class="fa fa-times"></i> Cancelar
                    </a>
                  </div>
                  
                </form>
              </div>
            </div>
          </div>
        </div>

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
        <div class="modal-header" style="background: linear-gradient(135deg, #009A3F, #00c853); color: white;">
          <h5 class="modal-title"><i class="fa fa-check-circle"></i> Confirmar Registro</h5>
          <button type="button" class="close" data-dismiss="modal" style="color: white;">
            <span>&times;</span>
          </button>
        </div>
        <div class="modal-body">
          <p>Revise los datos antes de guardar:</p>
          
          <div class="data-preview" id="dataPreview">
            <!-- Datos se cargarán aquí -->
          </div>
          
          <div class="text-center mt-3">
            <button type="button" class="btn btn-success" id="btnConfirmarGuardar">
              <i class="fa fa-check"></i> Sí, Guardar Registro
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
  <script src="build/js/custom.min.js"></script>

  <script>
  $(document).ready(function() {
    
    // Inicializar Select2
    $('.select2-field').select2({
      placeholder: "Seleccionar...",
      width: '100%'
    });
    
    // Contador para tarifas especiales
    let contadorTarifasEspeciales = 0;
    
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
                       placeholder="0,000" value="0,000" 
                       data-index="${contadorTarifasEspeciales}">
              </div>
            </div>
            <div class="col-md-4 mb-2">
              <label class="form-label">Frecuencia *</label>
              <select class="form-control frecuencia-especial" data-index="${contadorTarifasEspeciales}">
                <option value="Mensualizado">Mensualizado</option>
                <option value="Anual">Anual</option>
                <option value="Dia">Día</option>
                <option value="Unitaria">Unitaria</option>
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
        // Cambiar a modo especial
        $('#seccionTarifasNormales').hide();
        $('#seccionTarifasEspeciales').show();
        $('#modoTextoNormal').hide();
        $('#modoTextoEspecial').show();
        $('#inputModoEspecial').val('1');
        
        // Agregar primera tarifa especial si no hay ninguna
        if ($('#contenedorTarifasEspeciales').children().length === 0) {
          agregarTarifaEspecial();
        }
      } else {
        // Cambiar a modo normal
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
        $(this).val(num.toFixed(3).replace('.', ','));
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
    
    // Botón Guardar
    $('#btnGuardar').on('click', function(e) {
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
        
        // Validar que todos los servicios especiales tengan nombre
        for (let tarifa of tarifasEspeciales) {
          if (!tarifa.servicio.trim()) {
            alert('Todos los servicios especiales deben tener un nombre');
            return;
          }
          if (!tarifa.costo || tarifa.costo === '0,000') {
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
        servicio: servicioFinal,
        ciudad: ciudadFinal,
        almacen: almacenFinal,
        gestion: $('select[name="gestion"]').val(),
        utd: $('input[name="utd"]').val(),
        modo_especial: modoEspecial ? 'Sí' : 'No'
      };
      
      // Agregar datos según el modo
      if (!modoEspecial) {
        // Datos de tarifas normales
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
        // Datos de tarifas especiales
        datos.tarifas_especiales = obtenerTarifasEspeciales();
      }
      
      // Mostrar datos en popup
      let html = `
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
          <div class="data-row">
            <div class="data-label">IN Pallet:</div>
            <div class="data-value">$${datos.pall_in}</div>
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
      window.datosParaGuardar = datos;
      window.modoEspecialParaGuardar = modoEspecial;
    });
    
    // Confirmar guardado
    $('#btnConfirmarGuardar').on('click', function() {
      let $btn = $(this);
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
      
      // Preparar datos para enviar
      let datosEnvio = {
        confirmar_guardado: 'true',
        servicio_final: window.datosParaGuardar.servicio,
        ciudad_final: window.datosParaGuardar.ciudad,
        almacen_final: window.datosParaGuardar.almacen,
        gestion_final: window.datosParaGuardar.gestion,
        utd_final: window.datosParaGuardar.utd,
        modo_especial: window.modoEspecialParaGuardar ? '1' : '0'
      };
      
      // Agregar datos según el modo
      if (!window.modoEspecialParaGuardar) {
        // Datos normales
        datosEnvio.descarga_pall = window.datosParaGuardar.descarga_pall;
        datosEnvio.estibaje_cajas = window.datosParaGuardar.estibaje_cajas;
        datosEnvio.pall_in = window.datosParaGuardar.pall_in;
        datosEnvio.operacion_cajas_recepcion = window.datosParaGuardar.operacion_cajas_recepcion;
        datosEnvio.operacion_und_recepcion = window.datosParaGuardar.operacion_und_recepcion;
        datosEnvio.m2_almacen = window.datosParaGuardar.m2_almacen;
        datosEnvio.almacen_pos = window.datosParaGuardar.almacen_pos;
        datosEnvio.pall_out = window.datosParaGuardar.pall_out;
        datosEnvio.operacion_cajas_despacho = window.datosParaGuardar.operacion_cajas_despacho;
        datosEnvio.operacion_und_despacho = window.datosParaGuardar.operacion_und_despacho;
        datosEnvio.carga_pall = window.datosParaGuardar.carga_pall;
      } else {
        // Datos especiales - ¡IMPORTANTE: Enviar como JSON string!
        if (window.datosParaGuardar.tarifas_especiales) {
          datosEnvio.tarifas_especiales = JSON.stringify(window.datosParaGuardar.tarifas_especiales);
        }
      }
      
      // Enviar datos por AJAX
      $.ajax({
        url: 'crear_registro.php',
        method: 'POST',
        data: datosEnvio,
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            $('#confirmModal').modal('hide');
            let mensaje = `✅ ${response.message}`;
            if (response.id) {
              mensaje += `\nID del nuevo registro: ${response.id}`;
              
              if (window.modoEspecialParaGuardar && response.guid) {
                mensaje += `\nGUID del registro especial: ${response.guid}`;
              }
              
              if (confirm(mensaje + '\n\n¿Desea ver el registro creado?')) {
                window.location.href = `index.php`;
              } else {
                window.location.href = 'index.php';
              }
            } else {
              alert(mensaje);
              window.location.href = 'index.php';
            }
          } else {
            alert('❌ ' + response.message);
            $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Sí, Guardar Registro');
          }
        },
        error: function(xhr, status, error) {
          alert('❌ Error al conectar con el servidor: ' + error);
          $btn.prop('disabled', false).html('<i class="fa fa-check"></i> Sí, Guardar Registro');
        }
      });
    });
    
  });
  </script>

</body>
</html>