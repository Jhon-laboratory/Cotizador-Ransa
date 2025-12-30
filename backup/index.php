<?php
// index.php - SISTEMA COMPLETO CON FILTROS AUTOMÁTICOS
require_once 'conexion.php';
session_start();

// Obtener filtros únicos para los dropdowns
$servicios = [];
$gestiones = [];
$almacenes = [];
$utds = [];

try {
    // Servicios únicos - NOTA: columna SIN espacio
    $stmt = $pdo->query("SELECT DISTINCT servicio FROM externos.CotizadorTarifas WHERE servicio IS NOT NULL ORDER BY servicio");
    $servicios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Gestiones únicas - NOTA: columna CON espacio [ gestion]
    $stmt = $pdo->query("SELECT DISTINCT [ gestion] FROM externos.CotizadorTarifas WHERE [ gestion] IS NOT NULL ORDER BY [ gestion] DESC");
    $gestiones = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Almacenes únicos - NOTA: columna CON espacio [ almacen]
    $stmt = $pdo->query("SELECT DISTINCT [ almacen] FROM externos.CotizadorTarifas WHERE [ almacen] IS NOT NULL ORDER BY [ almacen]");
    $almacenes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // UTDs únicos - NOTA: columna CON espacio [ utd]
    $stmt = $pdo->query("SELECT DISTINCT [ utd] FROM externos.CotizadorTarifas WHERE [ utd] IS NOT NULL ORDER BY [ utd]");
    $utds_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Convertir UTDs a porcentajes para mostrar en el dropdown
    $utds = [];
    foreach ($utds_raw as $utd_valor) {
        if ($utd_valor !== null && $utd_valor !== '') {
            // Convertir a porcentaje
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
    
    // Limpiar el valor
    $valor_limpio = str_replace(',', '.', trim($valor));
    
    // Convertir a float
    $float_valor = floatval($valor_limpio);
    
    // Convertir a porcentaje (multiplicar por 100)
    $porcentaje = $float_valor * 100;
    
    // Formatear sin decimales si es entero, con 2 decimales si no
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
$sql = "SELECT * FROM externos.CotizadorTarifas";
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
    
    // Guardar en cache de sesión
    $_SESSION['cotizador_tarifas_cache'] = $registros;
    
} catch(PDOException $e) {
    $error = "Error al obtener registros: " . $e->getMessage();
    $registros = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Cotizador de Tarifas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
        .detalles-modal .modal-dialog {
            max-width: 95%;
        }
        .valor-seleccionado {
            background-color: #e8f4fd;
            font-weight: bold;
        }
        .table-hover tbody tr:hover {
            cursor: pointer;
            background-color: #f5f5f5;
        }
        .btn-detalles {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 5px 15px;
            border-radius: 5px;
            font-size: 14px;
        }
        .btn-detalles:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
        }
        .tarifas-reporte {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reporte-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 8px;
            border-left: 5px solid #3498db;
        }
        .process-recepcion {
            background-color: #e8f6f3 !important;
        }
        .process-almacenaje {
            background-color: #fef9e7 !important;
        }
        .process-despacho {
            background-color: #f4ecf7 !important;
        }
        .process-otros {
            background-color: #fdedec !important;
        }
        .table-tarifas th {
            background-color: #2c3e50 !important;
            color: white !important;
            font-weight: 600;
        }
        .total-row {
            background-color: #2ecc71 !important;
            color: white !important;
            font-weight: bold;
        }
        .badge-process {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .select2-container {
            width: 100% !important;
        }
        .select2-selection {
            height: 38px !important;
            border: 1px solid #ced4da !important;
        }
        .badge-utd {
            background-color: #9b59b6 !important;
            font-size: 0.75em;
        }
        .auto-filter-message {
            font-size: 0.85em;
            color: #6c757d;
            font-style: italic;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .modal-dialog {
                max-width: 100% !important;
                margin: 0 !important;
            }
            .modal-content {
                border: none !important;
                box-shadow: none !important;
            }
            .tarifas-reporte {
                font-size: 11px !important;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <h1 class="mb-4">📊 Cotizador de Tarifas - Azure SQL Server</h1>
        
        <!-- Filtros -->
        <div class="card mb-4 shadow">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros de Búsqueda</h5>
                <span class="auto-filter-message">
                    <i class="fas fa-bolt"></i> Los filtros se aplican automáticamente
                </span>
            </div>
            <div class="card-body">
                <form method="GET" id="filtrosForm" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Servicio</label>
                        <select name="servicio" class="form-select select2-filtro" data-placeholder="Seleccionar servicio...">
                            <option value="">Todos los servicios</option>
                            <?php foreach($servicios as $serv): ?>
                                <option value="<?= htmlspecialchars($serv) ?>" <?= $filtro_servicio == $serv ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($serv) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Gestión</label>
                        <select name="gestion" class="form-select select2-filtro" data-placeholder="Seleccionar gestión...">
                            <option value="">Todas las gestiones</option>
                            <?php foreach($gestiones as $ges): ?>
                                <option value="<?= htmlspecialchars($ges) ?>" <?= $filtro_gestion == $ges ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ges) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Almacén</label>
                        <select name="almacen" class="form-select select2-filtro" data-placeholder="Seleccionar almacén...">
                            <option value="">Todos los almacenes</option>
                            <?php foreach($almacenes as $alm): ?>
                                <option value="<?= htmlspecialchars($alm) ?>" <?= $filtro_almacen == $alm ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($alm) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">UTD (Porcentaje)</label>
                        <select name="utd" class="form-select select2-filtro" data-placeholder="Seleccionar UTD...">
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
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-broom"></i> Limpiar Filtros
                                </a>
                                <span class="badge bg-info ms-2">
                                    <?= count($registros) ?> registros
                                </span>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-info-circle"></i> UTD mostrado como porcentaje
                                </span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabla de resultados -->
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-table"></i> Registros de Tarifas</h5>
            </div>
            <div class="card-body">
                <?php if(isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table id="tabla-tarifas" class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Servicio</th>
                                <th>Ciudad</th>
                                <th>Almacén</th>
                                <th>Gestión</th>
                                <th>UTD</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($registros)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <i class="fas fa-database fa-2x text-muted mb-2"></i><br>
                                        No se encontraron registros
                                        <?php if($filtro_servicio || $filtro_gestion || $filtro_almacen || $filtro_utd): ?>
                                            <br><small class="text-muted">con los filtros aplicados</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($registros as $registro): ?>
                                    <tr data-id="<?= $registro['id'] ?>">
                                        <td><?= htmlspecialchars($registro['id']) ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($registro['servicio'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($registro[' ciudad'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($registro[' almacen'] ?? '') ?></td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= htmlspecialchars($registro[' gestion'] ?? '') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if(!empty($registro[' utd'])): ?>
                                                <span class="badge badge-utd" title="Valor original: <?= htmlspecialchars($registro[' utd']) ?>">
                                                    <?= convertirAPorcentaje($registro[' utd']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-detalles ver-detalles" 
                                                    data-id="<?= $registro['id'] ?>"
                                                    data-servicio="<?= htmlspecialchars($registro['servicio'] ?? '') ?>"
                                                    data-gestion="<?= htmlspecialchars($registro[' gestion'] ?? '') ?>"
                                                    data-almacen="<?= htmlspecialchars($registro[' almacen'] ?? '') ?>"
                                                    data-utd="<?= htmlspecialchars($registro[' utd'] ?? '') ?>">
                                                <i class="fas fa-file-invoice"></i> Ver Reporte
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para detalles -->
    <div class="modal fade detalles-modal" id="detallesModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar"></i> Reporte de Tarifas</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <!-- Contenedor para el reporte -->
                    <div id="detalles-container">
                        <!-- El reporte se cargará aquí dinámicamente -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando reporte...</span>
                            </div>
                            <p class="mt-3">Generando reporte de tarifas...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-primary no-print" id="btn-imprimir">
                        <i class="fas fa-print"></i> Imprimir Reporte
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    
    <script>
    $(document).ready(function() {
        // Inicializar Select2 para los filtros
        $('.select2-filtro').select2({
            placeholder: function() {
                return $(this).data('placeholder');
            },
            allowClear: true,
            width: '100%'
        });
        
        // Filtrar automáticamente al cambiar cualquier filtro
        $('.select2-filtro').on('change', function() {
            // Agregar parámetros a la URL sin recargar
            const formData = $('#filtrosForm').serialize();
            const urlParams = new URLSearchParams(formData);
            
            // Construir nueva URL
            let newUrl = window.location.pathname;
            if (formData) {
                newUrl += '?' + urlParams.toString();
            }
            
            // Actualizar la URL en el navegador (sin recargar)
            window.history.replaceState({}, '', newUrl);
            
            // Recargar la página para aplicar filtros
            window.location.reload();
        });
        
        // Inicializar DataTable
        const dataTable = $('#tabla-tarifas').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            pageLength: 10,
            order: [[0, 'desc']],
            dom: '<"top"lf>rt<"bottom"ip><"clear">'
        });
        
        // Manejar clic en botón de detalles
        $(document).on('click', '.ver-detalles', function() {
            const id = $(this).data('id');
            const servicio = $(this).data('servicio');
            const gestion = $(this).data('gestion');
            const almacen = $(this).data('almacen');
            const utd = $(this).data('utd');
            
            // Mostrar modal
            const modal = new bootstrap.Modal(document.getElementById('detallesModal'));
            modal.show();
            
            // Limpiar contenedor y mostrar loading
            $('#detalles-container').html(`
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando reporte...</span>
                    </div>
                    <p class="mt-3">Generando reporte de tarifas...</p>
                </div>
            `);
            
            // Obtener detalles via AJAX
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
                success: function(response) {
                    if (response.success) {
                        // Mostrar el HTML formateado
                        $('#detalles-container').html(response.html);
                    } else {
                        $('#detalles-container').html(`
                            <div class="text-center text-danger py-4">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                                <h5>Error</h5>
                                <p>${response.message}</p>
                            </div>
                        `);
                    }
                },
                error: function(xhr, status, error) {
                    $('#detalles-container').html(`
                        <div class="text-center text-danger py-4">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                            <h5>Error de conexión</h5>
                            <p>No se pudo cargar el reporte. Por favor, intente nuevamente.</p>
                            <small>Detalle: ${error}</small>
                        </div>
                    `);
                }
            });
        });
        
        // Función para imprimir el reporte
        $(document).on('click', '#btn-imprimir', function() {
            const printContent = $('#detalles-container').html();
            const ventanaImpresion = window.open('', '_blank');
            
            ventanaImpresion.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Reporte de Tarifas - ${$('#detallesModal .modal-title').text()}</title>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        @media print {
                            @page {
                                size: landscape;
                                margin: 10mm;
                            }
                        }
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
                            border-left: 4px solid #3498db;
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
                        .text-end { 
                            text-align: right; 
                        }
                        .text-center { 
                            text-align: center; 
                        }
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
                        .card {
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            margin-bottom: 10px;
                        }
                        .card-header {
                            padding: 6px 10px;
                            font-weight: bold;
                        }
                        .process-recepcion { background-color: #e8f6f3 !important; }
                        .process-almacenaje { background-color: #fef9e7 !important; }
                        .process-despacho { background-color: #f4ecf7 !important; }
                        .process-otros { background-color: #fdedec !important; }
                        .no-print { display: none !important; }
                        .page-break { page-break-after: always; }
                    </style>
                </head>
                <body>
                    ${printContent}
                </body>
                </html>
            `);
            
            ventanaImpresion.document.close();
            ventanaImpresion.focus();
            
            // Esperar a que cargue el contenido antes de imprimir
            setTimeout(function() {
                ventanaImpresion.print();
                // ventanaImpresion.close(); // Comentado para que el usuario decida cerrar
            }, 500);
        });
        
        // También permitir Ctrl+P para imprimir dentro del modal
        $(document).on('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p' && $('#detallesModal').hasClass('show')) {
                e.preventDefault();
                $('#btn-imprimir').click();
            }
        });
        
        // Función para formatear UTD como porcentaje (para uso en JavaScript si es necesario)
        window.formatoPorcentaje = function(valor) {
            if (!valor) return '0%';
            const num = parseFloat(valor.toString().replace(',', '.'));
            if (isNaN(num)) return '0%';
            
            const porcentaje = num * 100;
            if (porcentaje == parseInt(porcentaje)) {
                return parseInt(porcentaje) + '%';
            } else {
                return porcentaje.toFixed(2).replace('.', ',') + '%';
            }
        };
    });
    </script>
</body>
</html>