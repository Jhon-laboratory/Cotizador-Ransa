<?php
// crear_registro.php - SISTEMA DE CREACIÓN DE NUEVAS TARIFAS
session_start();
require_once 'conexion.php';

// ===== VERIFICAR SI EL USUARIO ESTÁ LOGUEADO (USANDO NUESTRA SESIÓN) =====
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

// Guardar después de confirmación (AJAX)
if (isset($_POST['confirmar_guardado']) && $_POST['confirmar_guardado'] === 'true') {
    header('Content-Type: application/json');
    
    try {
        $servicio = trim($_POST['servicio_final'] ?? '');
        $ciudad = trim($_POST['ciudad_final'] ?? '');
        $almacen = trim($_POST['almacen_final'] ?? '');
        $gestion = (int) trim($_POST['gestion_final'] ?? date('Y'));
        $utd = trim($_POST['utd_final'] ?? '0.15');
        
        // Campos de tarifas
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
        
        // Validar campos obligatorios
        if (empty($servicio) || empty($ciudad) || empty($almacen)) {
            throw new Exception('Servicio, Ciudad y Almacén son campos obligatorios');
        }
        
        // CONSULTA CORREGIDA según tu estructura de BD
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
            [ utd]
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
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
            $utd
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
        
        sqlsrv_free_stmt($stmt);
        
        echo json_encode(['success' => true, 'message' => 'Registro creado exitosamente!']);
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
                  
                  <!-- TARIFAS -->
                  <div class="form-section">
                    <div class="section-title">
                      <i class="fa fa-dollar-sign"></i> Tarifas (USD)
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
                              <input type="text" name="descarga_pall" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Estibaje Cajas/Bultos</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="estibaje_cajas" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="pall_in" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Caja/Bulto</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_cajas_recepcion" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">IN Unidad</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_und_recepcion" class="form-control" placeholder="0,00" value="0,00">
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
                              <input type="text" name="m2_almacen" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Almacenaje Posición</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="almacen_pos" class="form-control" placeholder="0,00" value="0,00">
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
                              <input type="text" name="pall_out" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Caja/Bulto</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_cajas_despacho" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">OUT Unidad</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="operacion_und_despacho" class="form-control" placeholder="0,00" value="0,00">
                            </div>
                          </div>
                          
                          <div class="col-12 mb-2">
                            <label class="form-label">Carguío Pallet</label>
                            <div class="input-group">
                              <div class="input-group-prepend">
                                <span class="input-group-text">$</span>
                              </div>
                              <input type="text" name="carga_pall" class="form-control" placeholder="0,00" value="0,00">
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
    
    // Formatear números al perder foco
    $('input[type="text"]').on('blur', function() {
      let value = $(this).val().trim();
      if (value && /^\d+(\.\d+)?$/.test(value.replace(',', '.'))) {
        let num = parseFloat(value.replace(',', '.'));
        $(this).val(num.toFixed(2).replace('.', ','));
      }
    });
    
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
        descarga_pall: $('input[name="descarga_pall"]').val(),
        estibaje_cajas: $('input[name="estibaje_cajas"]').val(),
        pall_in: $('input[name="pall_in"]').val(),
        operacion_cajas_recepcion: $('input[name="operacion_cajas_recepcion"]').val(),
        operacion_und_recepcion: $('input[name="operacion_und_recepcion"]').val(),
        m2_almacen: $('input[name="m2_almacen"]').val(),
        almacen_pos: $('input[name="almacen_pos"]').val(),
        pall_out: $('input[name="pall_out"]').val(),
        operacion_cajas_despacho: $('input[name="operacion_cajas_despacho"]').val(),
        operacion_und_despacho: $('input[name="operacion_und_despacho"]').val(),
        carga_pall: $('input[name="carga_pall"]').val()
      };
      
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
        <h6 style="color: #009A3F; margin-top: 10px;">Tarifas de Recepción</h6>
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
        <h6 style="color: #009A3F; margin-top: 10px;">Tarifas de Despacho</h6>
        <div class="data-row">
          <div class="data-label">OUT Pallet:</div>
          <div class="data-value">$${datos.pall_out}</div>
        </div>
        <div class="data-row">
          <div class="data-label">OUT Caja/Bulto:</div>
          <div class="data-value">$${datos.operacion_cajas_despacho}</div>
        </div>
        <div class="data-row">
          <div class="data-label">Carguío Pallet:</div>
          <div class="data-value">$${datos.carga_pall}</div>
        </div>
      `;
      
      $('#dataPreview').html(html);
      $('#confirmModal').modal('show');
      
      // Guardar datos para usar después
      window.datosParaGuardar = datos;
    });
    
    // Confirmar guardado
    $('#btnConfirmarGuardar').on('click', function() {
      let $btn = $(this);
      $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Guardando...');
      
      // Enviar datos por AJAX
      $.ajax({
        url: 'crear_registro.php',
        method: 'POST',
        data: {
          confirmar_guardado: 'true',
          servicio_final: window.datosParaGuardar.servicio,
          ciudad_final: window.datosParaGuardar.ciudad,
          almacen_final: window.datosParaGuardar.almacen,
          gestion_final: window.datosParaGuardar.gestion,
          utd_final: window.datosParaGuardar.utd,
          descarga_pall: window.datosParaGuardar.descarga_pall,
          estibaje_cajas: window.datosParaGuardar.estibaje_cajas,
          pall_in: window.datosParaGuardar.pall_in,
          operacion_cajas_recepcion: window.datosParaGuardar.operacion_cajas_recepcion,
          operacion_und_recepcion: window.datosParaGuardar.operacion_und_recepcion,
          m2_almacen: window.datosParaGuardar.m2_almacen,
          almacen_pos: window.datosParaGuardar.almacen_pos,
          pall_out: window.datosParaGuardar.pall_out,
          operacion_cajas_despacho: window.datosParaGuardar.operacion_cajas_despacho,
          operacion_und_despacho: window.datosParaGuardar.operacion_und_despacho,
          carga_pall: window.datosParaGuardar.carga_pall
        },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            $('#confirmModal').modal('hide');
            alert('✅ ' + response.message);
            window.location.href = 'index.php';
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