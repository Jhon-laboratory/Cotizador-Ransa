<?php
// index.php - SISTEMA SIMPLIFICADO CON TABLA DATATABLES
session_start();

// Configuración de conexión
$host = "Jorgeserver.database.windows.net";
$dbname = "DPL";
$username = "Jmmc";
$password = "ChaosSoldier01";

// Conexión a la base de datos
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

// Obtener filtros únicos para los dropdowns
$servicios = [];
$gestiones = [];
$almacenes = [];
$utds = [];

try {
    // Servicios únicos
    $stmt = $pdo->query("SELECT DISTINCT servicio FROM externos.CotizadorTarifas WHERE servicio IS NOT NULL ORDER BY servicio");
    $servicios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Gestiones únicas
    $stmt = $pdo->query("SELECT DISTINCT [ gestion] FROM externos.CotizadorTarifas WHERE [ gestion] IS NOT NULL ORDER BY [ gestion] DESC");
    $gestiones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Almacenes únicos
    $stmt = $pdo->query("SELECT DISTINCT [ almacen] FROM externos.CotizadorTarifas WHERE [ almacen] IS NOT NULL ORDER BY [ almacen]");
    $almacenes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // UTDs únicos
    $stmt = $pdo->query("SELECT DISTINCT [ utd] FROM externos.CotizadorTarifas WHERE [ utd] IS NOT NULL ORDER BY [ utd]");
    $utds_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Convertir UTDs a porcentajes
    $utds = [];
    foreach ($utds_raw as $utd_valor) {
        if ($utd_valor !== null && $utd_valor !== '') {
            $porcentaje = convertirAPorcentaje($utd_valor);
            $utds[$utd_valor] = $porcentaje;
        }
    }
    
} catch(PDOException $e) {
    $error = "Error al obtener filtros: " . $e->getMessage();
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
$sql = "SELECT id, servicio, [ ciudad], [ almacen], [ gestion], [ utd] FROM externos.CotizadorTarifas";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

try {
    if (!empty($params)) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $_SESSION['cotizador_tarifas_cache'] = $registros;
    
} catch(PDOException $e) {
    $error = "Error al obtener registros: " . $e->getMessage();
    $registros = [];
}

// Simular datos de sesión para el diseño
$_SESSION['gb_nombre'] = $_SESSION['gb_nombre'] ?? 'Usuario Sistema';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SISTEMA DE COTIZACIÓN DE TARIFAS</title>
  
  <!-- Gentelella CSS -->
  <link href="vendors/bootstrap/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="vendors/font-awesome/css/font-awesome.min.css" rel="stylesheet">
  <link href="vendors/nprogress/nprogress.css" rel="stylesheet">
  <link href="vendors/select2/dist/css/select2.min.css" rel="stylesheet">
  <link href="vendors/datatables.net-bs/css/dataTables.bootstrap.min.css" rel="stylesheet">
  <link href="vendors/datatables.net-buttons-bs/css/buttons.bootstrap.min.css" rel="stylesheet">
  <link href="vendors/datatables.net-responsive-bs/css/responsive.bootstrap.min.css" rel="stylesheet">
  <link href="build/css/custom.min.css" rel="stylesheet">
  
  <!-- Estilos personalizados -->
  <style>
    body {
        background: linear-gradient(rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.95)), 
                    url('img/imglogin.jpg') center/cover no-repeat fixed;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* CABECERA SUPERIOR */
    .top_nav {
        background: #98e0b6ff;
        min-height: 10px !important;
        max-height: 30px !important;
        border-bottom: 1px solid #009A3F;
        display: flex;
        align-items: center;
    }
    
    .top_nav .nav_menu {
        min-height: 40px !important;
        padding: 0 !important;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .nav_menu .navbar-left {
        display: flex;
        align-items: center;
    }
    
    /* TÍTULO EN LA CABECERA */
    .nav-title {
        color: #28a745;
        font-size: 14px;
        font-weight: 600;
        margin-left: 1px;
        display: flex;
        align-items: center;
    }
    
    .nav-title i {
        margin-right: 1px;
        font-size: 13px;
        color: #20c997;
    }
    
    /* ESTILOS PARA EL NOMBRE DE USUARIO */
    .nav_menu .navbar-right a.user-profile {
        color: #28a745 !important;
        padding: 8px 15px !important;
        font-size: 14px;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        margin: 5px 10px;
        transition: all 0.3s ease;
        border: 1px solid rgba(40, 167, 69, 0.3);
    }
    
    /* CONTENIDO PRINCIPAL */
    .right_col {
        padding: 15px !important;
        min-height: calc(100vh - 40px) !important;
    }
    
    /* PANEL DE CONTENIDO */
    .x_panel {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }
    
    .x_title {
        border-bottom: 2px solid #e8f5e9;
        padding: 15px;
        margin-bottom: 0;
    }
    
    .x_title h2 {
        margin: 0;
        font-size: 16px;
        font-weight: 600;
        color: #009A3F;
        display: flex;
        align-items: center;
    }
    
    .x_title h2 i {
        margin-right: 10px;
        color: #009A3F;
    }
    
    .x_content {
        padding: 15px !important;
    }
    
    /* FILTROS */
    .filter-container {
        background: #f8f9fa;
        padding: 10px;s
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
    
    /* BADGES */
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
        background: linear-gradient(135deg, #40703bff, #0a8024ff) !important;
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
    
    /* BOTÓN VER REPORTE */
    .btn-reporte {
        background: linear-gradient(135deg, #009A3F, #00c853);
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
    
    .btn-reporte:hover {
        background: linear-gradient(135deg, #008a35, #00b848);
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0,154,63,0.3);
        color: white;
    }
    
    .btn-reporte i {
        margin-right: 5px;
        font-size: 11px;
    }
    
    /* CONTADOR DE REGISTROS */
    .records-count {
        background: linear-gradient(135deg, #009A3F, #00c853);
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
    
    /* MODAL DE REPORTE */
    .reporte-modal .modal-dialog {
        max-width: 95%;
        margin: 10px auto;
    }
    
    .modal-header-success {
        background: linear-gradient(135deg, #009A3F, #00c853);
        color: white;
        border-radius: 6px 6px 0 0;
    }
    
    /* LOADING */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        display: none;
    }
    
    .loading-spinner {
        text-align: center;
        color: #009A3F;
    }
    
    .loading-spinner i {
        font-size: 40px;
        margin-bottom: 10px;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* RESPONSIVE */
    @media (max-width: 768px) {
        .nav-title {
            font-size: 12px;
        }
        
        .x_title h2 {
            font-size: 14px;
        }
        
        .dataTables_wrapper .table {
            font-size: 12px;
        }
        
        .btn-reporte {
            font-size: 11px;
            padding: 4px 8px;
        }
    }
  </style>
</head>

<body class="nav-md">
  <!-- LOADING OVERLAY -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
      <i class="fa fa-spinner fa-spin"></i>
      <p>Procesando...</p>
    </div>
  </div>
  
  <div class="container body">
    <div class="main_container">
      
      <!-- Sidebar de Gentelella -->
      <div class="col-md-3 left_col">
        <div class="left_col scroll-view">
          <div class="navbar nav_title" style="border: 0;">
            <a href="index.php" class="site_title">
              <i class="fa fa-file-invoice-dollar"></i> <span>Cotizador Tarifas</span>
            </a>
          </div>

          <div class="clearfix"></div>

          <div class="profile clearfix">
            <div class="profile_pic">
              <div style="width: 50px; height: 50px; border-radius: 50%; background: linear-gradient(135deg, #009A3F, #00c853); display: flex; align-items: center; justify-content: center; color: white; font-size: 20px;">
                <i class="fa fa-user"></i>
              </div>
            </div>
            <div class="profile_info">
              <span>Bienvenido,</span>
              <h2><?php echo $_SESSION['gb_nombre']; ?></h2>
            </div>
          </div>

          <br />

          <!-- MENÚ -->
          <div id="sidebar-menu" class="main_menu_side hidden-print main_menu">
            <div class="menu_section">
              <h3>Menú Principal</h3>
              <ul class="nav side-menu">
                <li class="active">
                  <a href="index.php"><i class="fa fa-home"></i> Inicio</a>
                </li>
                <li>
                  <a href="#"><i class="fa fa-sign-out"></i> Cerrar Sesión</a>
                </li>
              </ul>
            </div>
          </div>

          <!-- FOOTER SIDEBAR -->
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
            <a data-toggle="tooltip" data-placement="top" title="Cerrar Sesión">
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
              <i class="fa fa-file-invoice-dollar"></i> SISTEMA DE COTIZACIÓN DE TARIFAS
            </div>
          </div>
          
          <nav class="nav navbar-nav">
            <ul class="navbar-right">
              <li class="nav-item dropdown open">
                <a href="javascript:;" class="user-profile dropdown-toggle" id="navbarDropdown" data-toggle="dropdown">
                  <div style="display: inline-flex; align-items: center; background: #28a745; width: 26px; height: 26px; border-radius: 50%; justify-content: center; margin-right: 8px;">
                    <i class="fa fa-user" style="color: white; font-size: 12px;"></i>
                  </div>
                  <?php echo $_SESSION['gb_nombre']; ?>
                  <span class="fa fa-angle-down"></span>
                </a>
                <div class="dropdown-menu dropdown-usermenu pull-right" aria-labelledby="navbarDropdown">
                  <a class="dropdown-item" href="#"><i class="fa fa-user pull-right"></i> Perfil</a>
                  <a class="dropdown-item" href="#"><i class="fa fa-cog pull-right"></i> Configuración</a>
                  <div class="dropdown-divider"></div>
                  <a class="dropdown-item" href="#"><i class="fa fa-sign-out pull-right"></i> Salir</a>
                </div>
              </li>
            </ul>
          </nav>
        </div>
      </div>

      <!-- CONTENIDO PRINCIPAL -->
      <div class="right_col" role="main">
        
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
                          <a href="index.php" class="btn btn-clear-filters">
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
                <h2><i class="fa fa-table"></i> Registros de Tarifas</h2>
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
                  <table id="datatable-tarifas" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                      <tr>
                        <th width="5%">ID</th>
                        <th width="20%">Servicio</th>
                        <th width="15%">Ciudad</th>
                        <th width="20%">Almacén</th>
                        <th width="10%">Gestión</th>
                        <th width="10%">UTD</th>
                        <th width="20%">Acciones</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(!empty($registros)): ?>
                        <?php foreach($registros as $registro): ?>
                          <tr data-id="<?= $registro['id'] ?>">
                            <td class="text-center"><?= htmlspecialchars($registro['id']) ?></td>
                            <td>
                              <span class="badge badge-service"><?= htmlspecialchars($registro['servicio'] ?? 'N/A') ?></span>
                            </td>
                            <td>
                              <span class="badge badge-city"><?= htmlspecialchars($registro[' ciudad'] ?? '') ?></span>
                            </td>
                            <td>
                              <span class="badge badge-warehouse"><?= htmlspecialchars($registro[' almacen'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                              <span class="badge badge-year"><?= htmlspecialchars($registro[' gestion'] ?? '') ?></span>
                            </td>
                            <td class="text-center">
                              <?php if(!empty($registro[' utd'])): ?>
                                <span class="badge badge-utd" title="Valor original: <?= htmlspecialchars($registro[' utd']) ?>">
                                  <?= convertirAPorcentaje($registro[' utd']) ?>
                                </span>
                              <?php else: ?>
                                <span class="badge bg-secondary">N/A</span>
                              <?php endif; ?>
                            </td>
                            <td class="text-center">
                              <button class="btn btn-reporte ver-detalles" 
                                      data-id="<?= $registro['id'] ?>"
                                      data-servicio="<?= htmlspecialchars($registro['servicio'] ?? '') ?>"
                                      data-gestion="<?= htmlspecialchars($registro[' gestion'] ?? '') ?>"
                                      data-almacen="<?= htmlspecialchars($registro[' almacen'] ?? '') ?>"
                                      data-utd="<?= htmlspecialchars($registro[' utd'] ?? '') ?>">
                                <i class="fa fa-eye"></i> Ver Reporte
                              </button>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="7" class="text-center">No se encontraron registros</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- FOOTER -->
      <footer>
        <div class="pull-right">
          Sistema de Cotización de Tarifas © <?php echo date('Y') ?>
        </div>
        <div class="clearfix"></div>
      </footer>
    </div>
  </div>

<!-- MODAL PARA REPORTE - ACTUALIZADO CON BOTONES DE EXPORTACIÓN -->
<div class="modal fade reporte-modal" id="reporteModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header modal-header-success">
                <h5 class="modal-title"><i class="fa fa-file-invoice-dollar"></i> Reporte de Tarifas</h5>
                <button type="button" class="close" data-dismiss="modal" style="color: white; opacity: 0.8;">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div id="reporte-container">
                    <div class="text-center py-4">
                        <i class="fa fa-spinner fa-spin fa-2x" style="color: #009A3F;"></i>
                        <p class="mt-2">Cargando reporte...</p>
                    </div>
                </div>
            </div>
            
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-dismiss="modal">
        <i class="fa fa-times"></i> Cerrar
    </button>
    <button type="button" class="btn btn-info" id="btn-exportar-excel">
        <i class="fa fa-file-excel"></i> Exportar Excel
    </button>
    <button type="button" class="btn btn-danger" id="btn-exportar-pdf">
        <i class="fa fa-file-pdf"></i> Exportar PDF
    </button>
    <button type="button" class="btn btn-success" id="btn-imprimir-reporte">
        <i class="fa fa-print"></i> Imprimir
    </button>
</div>
        </div>
    </div>
</div>



  <!-- SCRIPTS GENTEELLA -->
  <script src="vendors/jquery/dist/jquery.min.js"></script>
  <script src="vendors/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="vendors/nprogress/nprogress.js"></script>
  <script src="vendors/select2/dist/js/select2.min.js"></script>
  <script src="vendors/datatables.net/js/jquery.dataTables.min.js"></script>
  <script src="vendors/datatables.net-bs/js/dataTables.bootstrap.min.js"></script>
  <script src="build/js/custom.min.js"></script>

  <!-- SCRIPTS PERSONALIZADOS -->
  <script>
  $(document).ready(function() {
    // Mostrar loading overlay
    function showLoading() {
      $('#loadingOverlay').fadeIn();
    }
    
    function hideLoading() {
      $('#loadingOverlay').fadeOut();
    }
    
    // Inicializar Select2
    $('.select2-filtro').select2({
      placeholder: "Seleccionar...",
      allowClear: true,
      width: '100%'
    });
    
    // Inicializar DataTable
    var table = $('#datatable-tarifas').DataTable({
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
        { orderable: false, targets: 6 } // Columna de acciones no ordenable
      ]
    });
    
    // Actualizar contador de registros
    function updateRecordsCount() {
      var count = table.rows().count();
      $('#totalRecords').text(count);
    }
    
    // Inicializar contador
    updateRecordsCount();
    
    // Filtros automáticos
    $('.select2-filtro').on('change', function() {
      showLoading();
      const formData = $('#filtrosForm').serialize();
      const urlParams = new URLSearchParams(formData);
      
      let newUrl = window.location.pathname;
      if (formData) {
        newUrl += '?' + urlParams.toString();
      }
      
      window.history.replaceState({}, '', newUrl);
      
      // Recargar la página después de un breve delay
      setTimeout(function() {
        window.location.reload();
      }, 300);
    });
    
    // Manejar clic en botón de reporte
    $(document).on('click', '.ver-detalles', function(e) {
      e.preventDefault();
      e.stopPropagation();
      
      const id = $(this).data('id');
      const servicio = $(this).data('servicio');
      const gestion = $(this).data('gestion');
      const almacen = $(this).data('almacen');
      const utd = $(this).data('utd');
      
      $('#reporteModal').modal('show');
      $('#reporte-container').html(`
        <div class="text-center py-4">
          <i class="fa fa-spinner fa-spin fa-2x" style="color: #009A3F;"></i>
          <p class="mt-2">Generando reporte...</p>
        </div>
      `);
      
      // Obtener reporte via AJAX
      $.ajax({
        url: 'obtener_detalles.php',
        method: 'POST',
        data: {
          accion: 'obtener_detalles',
          id: id,
          servicio: servicio,
          gestion: gestion,
          almacen: almacen,
          utd: utd
        },
        dataType: 'json',
        beforeSend: function() {
          showLoading();
        },
        success: function(response) {
          hideLoading();
          if (response.success) {
            $('#reporte-container').html(response.html);
          } else {
            $('#reporte-container').html(`
              <div class="alert alert-danger">
                <i class="fa fa-exclamation-triangle"></i> ${response.message}
              </div>
            `);
          }
        },
        error: function(xhr, status, error) {
          hideLoading();
          $('#reporte-container').html(`
            <div class="alert alert-danger">
              <i class="fa fa-exclamation-triangle"></i> Error al cargar el reporte
            </div>
          `);
        }
      });
    });
    var registroActual = null;
    
    // Imprimir reporte
    $(document).on('click', '#btn-imprimir-reporte', function() {
      const printContent = $('#reporte-container').html();
      const ventanaImpresion = window.open('', '_blank');
      
      ventanaImpresion.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Reporte de Tarifas</title>
          <meta charset="UTF-8">
          <style>
            @page { size: landscape; margin: 10mm; }
            body { 
              font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
              font-size: 11px; 
              color: #000;
              margin: 0;
              padding: 10px;
            }
            .tarifas-reporte {
              width: 100%;
            }
            .reporte-header {
              background: #f8f9fa;
              padding: 10px;
              border-radius: 5px;
              border-left: 4px solid #009A3F;
              margin-bottom: 15px;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              margin-bottom: 10px;
              font-size: 10px;
            }
            th, td {
              border: 1px solid #ddd;
              padding: 5px;
              text-align: left;
            }
            th {
              background-color: #2c3e50 !important;
              color: white !important;
              font-weight: bold;
            }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .badge {
              padding: 3px 7px;
              border-radius: 3px;
              font-size: 0.8em;
            }
            .alert {
              padding: 8px;
              margin-bottom: 8px;
              border-radius: 4px;
              font-size: 10px;
            }
            .no-print { display: none !important; }
          </style>
        </head>
        <body>${printContent}</body>
        </html>
      `);
      
      ventanaImpresion.document.close();
      ventanaImpresion.focus();
      
      setTimeout(function() {
        ventanaImpresion.print();
      }, 500);
    });
    // ========== FUNCIONALIDAD PARA LOS 3 BOTONES ==========
// ========== FUNCIONALIDAD PARA LOS 3 BOTONES ==========

// Guardar datos del registro actual
var registroActual = null;

// Cuando se hace clic en "Ver Reporte", guardar datos
$(document).on('click', '.ver-detalles', function(e) {
    registroActual = {
        id: $(this).data('id'),
        servicio: $(this).data('servicio'),
        gestion: $(this).data('gestion'),
        almacen: $(this).data('almacen'),
        utd: $(this).data('utd')
    };
});

// 1. BOTÓN EXCEL
$(document).on('click', '#btn-exportar-excel', function() {
    if (!registroActual || !registroActual.id) {
        alert('No hay registro seleccionado');
        return;
    }
    
    // Crear formulario oculto
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generar_reporte.php';
    form.target = '_blank';
    
    // Agregar campos
    const accionInput = document.createElement('input');
    accionInput.type = 'hidden';
    accionInput.name = 'accion';
    accionInput.value = 'exportar';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = registroActual.id;
    
    const formatoInput = document.createElement('input');
    formatoInput.type = 'hidden';
    formatoInput.name = 'formato';
    formatoInput.value = 'excel';
    
    form.appendChild(accionInput);
    form.appendChild(idInput);
    form.appendChild(formatoInput);
    
    // Enviar formulario
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});

// 2. BOTÓN PDF
$(document).on('click', '#btn-exportar-pdf', function() {
    if (!registroActual || !registroActual.id) {
        alert('No hay registro seleccionado');
        return;
    }
    
    // Abrir en nueva pestaña
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generar_reporte.php';
    form.target = '_blank';
    
    const accionInput = document.createElement('input');
    accionInput.type = 'hidden';
    accionInput.name = 'accion';
    accionInput.value = 'exportar';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = registroActual.id;
    
    const formatoInput = document.createElement('input');
    formatoInput.type = 'hidden';
    formatoInput.name = 'formato';
    formatoInput.value = 'imprimir';
    
    form.appendChild(accionInput);
    form.appendChild(idInput);
    form.appendChild(formatoInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});

// 3. BOTÓN IMPRIMIR
$(document).on('click', '#btn-imprimir-reporte', function() {
    if (!registroActual || !registroActual.id) {
        alert('No hay registro seleccionado');
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generar_reporte.php';
    form.target = '_blank';
    
    const accionInput = document.createElement('input');
    accionInput.type = 'hidden';
    accionInput.name = 'accion';
    accionInput.value = 'exportar';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = registroActual.id;
    
    const formatoInput = document.createElement('input');
    formatoInput.type = 'hidden';
    formatoInput.name = 'formato';
    formatoInput.value = 'imprimir';
    
    form.appendChild(accionInput);
    form.appendChild(idInput);
    form.appendChild(formatoInput);
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
});

// Guardar el ID actual cuando se abre el modal
$(document).on('click', '.ver-detalles', function(e) {
    const id = $(this).data('id');
    $('#reporteModal').data('current-id', id);
});
    // Funciones de ayuda
    function toggleFullScreen() {
      if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
      } else {
        if (document.exitFullscreen) {
          document.exitFullscreen();
        }
      }
    }
    
    function reloadPage() {
      location.reload();
    }
    
    // Activar tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Actualizar contador cuando se filtra la tabla
    table.on('draw', function() {
      updateRecordsCount();
    });
    
    // Inicializar DataTable después de cargar
    hideLoading();
  });
  </script>

</body>
</html>