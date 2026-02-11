<?php
// obtener_detalles.php - VERSIÓN CON GUID PARA TARIFAS ESPECIALES
session_start();
require_once 'conexion.php';

// Validar sesión
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'Acceso no autorizado'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'obtener_detalles') {
    
    $response = ['success' => false, 'message' => '', 'html' => '', 'registro' => []];
    $id = $_POST['id'] ?? 0;
    
    // Ahora $id puede ser numérico (para normales) o venir desde el index
    if (empty($id)) {
        $response['message'] = 'ID no válido';
        echo json_encode($response);
        exit;
    }
    
    try {
        // Consultar registro principal - siempre por ID numérico (desde el index viene id numérico)
        $sql = "SELECT * FROM externos.CotizadorTarifas WHERE id = ?";
        $params = [$id];
        
        $stmt = sqlsrv_prepare($conn, $sql, $params);
        
        if ($stmt === false || !sqlsrv_execute($stmt)) {
            throw new Exception('Error al consultar registro principal: ' . print_r(sqlsrv_errors(), true));
        }
        
        $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($stmt);
        
        if (!$registro) {
            $response['message'] = 'No se encontró el registro con ID: ' . $id;
            echo json_encode($response);
            exit;
        }
        
        // Determinar modo
        $modo_especial = isset($registro['modo_especial']) && $registro['modo_especial'] == 1;
        
        if ($modo_especial) {
            // Obtener tarifas especiales usando el GUID (ID_Tarifa)
            $id_tarifa = $registro['ID_Tarifas'] ?? '';
            $tarifas_especiales = obtenerTarifasEspecialesPorGUID($conn, $id_tarifa);
            $html = generarTablaTarifasEspeciales($registro, $tarifas_especiales, $id_tarifa);
        } else {
            // Modo normal
            $html = generarTablaTarifasNormales($registro);
        }
        
        $response = [
            'success' => true,
            'message' => 'Registro encontrado',
            'html' => $html,
            'registro' => $registro,
            'modo_especial' => $modo_especial
        ];
        
    } catch(Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// FUNCIÓN PARA OBTENER TARIFAS ESPECIALES POR GUID
function obtenerTarifasEspecialesPorGUID($conn, $guid) {
    if (empty($guid)) {
        return [];
    }
    
    // Intentar diferentes ubicaciones de tabla
    $tablas_posibles = [
        'DPL.externos.TarifaEspecial',
        'externos.TarifaEspecial',
        'TarifaEspecial'
    ];
    
    foreach ($tablas_posibles as $tabla) {
        try {
            $sql = "SELECT Servicio, costo, Frecuencia 
                    FROM $tabla 
                    WHERE ID_Tarifa = ? 
                    ORDER BY Servicio";
            
            $stmt = sqlsrv_prepare($conn, $sql, [$guid]);
            if ($stmt !== false && sqlsrv_execute($stmt)) {
                $resultados = [];
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $resultados[] = $row;
                }
                sqlsrv_free_stmt($stmt);
                
                if (!empty($resultados)) {
                    return $resultados;
                }
            }
            
        } catch(Exception $e) {
            // Continuar con la siguiente tabla
            continue;
        }
    }
    
    return [];
}

// Función para generar la tabla de tarifas normales
function generarTablaTarifasNormales($registro) {
    $fecha_actual = date('d/m/Y H:i:s');
    
    // ARRAY COMPLETO DE PROCESOS
    $procesos = [
        [
            'nro' => 1,
            'proceso' => 'Recepción',
            'descripcion' => 'Descarga Pallet',
            'alcance' => 'Operador + Montacarga',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' descarga_pall']) ? $registro[' descarga_pall'] : '0,000'
        ],
        [
            'nro' => 2,
            'proceso' => 'Recepción',
            'descripcion' => 'Descarga Caja/Bulto',
            'alcance' => 'Manual x caja / bulto',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' estibaje_cajas']) ? $registro[' estibaje_cajas'] : '0,000'
        ],
        [
            'nro' => 3,
            'proceso' => 'Recepción',
            'descripcion' => 'IN Pallet',
            'alcance' => 'Verificación, Reporte, Putaway',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' pall_in']) ? $registro[' pall_in'] : '0,000'
        ],
        [
            'nro' => 4,
            'proceso' => 'Recepción',
            'descripcion' => 'IN Caja/Bulto',
            'alcance' => 'Verificación, Reporte, Putaway',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' operacion_cajas_recepcion']) ? $registro[' operacion_cajas_recepcion'] : '0,000'
        ],
        [
            'nro' => 5,
            'proceso' => 'Recepción',
            'descripcion' => 'IN Unidades',
            'alcance' => 'Verificación, Reporte, Putaway',
            'udm' => 'Unidad',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' operacion_und_recepcion']) ? $registro[' operacion_und_recepcion'] : '0,000'
        ],
        [
            'nro' => 6,
            'proceso' => 'Almacenaje',
            'descripcion' => 'Almacenaje m2',
            'alcance' => 'Almacenamiento mensual por m2 de ocupación (Incluye Productos + Pasillo)',
            'udm' => 'm2',
            'frecuencia' => 'Mensual',
            'valor' => isset($registro[' m2_almacen']) ? $registro[' m2_almacen'] : '0,000'
        ],
        [
            'nro' => 7,
            'proceso' => 'Almacenaje',
            'descripcion' => 'Almacenaje Pall',
            'alcance' => 'Almacenamiento mensual por pallet posicion/rack para pallet mercosur (1m x 1,2m x 1,55m)',
            'udm' => 'Pallet',
            'frecuencia' => 'Por Evento',
            'valor' => isset($registro[' almacen_pos']) ? $registro[' almacen_pos'] : '0,000'
        ],
        [
            'nro' => 8,
            'proceso' => 'Despacho',
            'descripcion' => 'OUT Pallet',
            'alcance' => 'Picking a nivel pallet puesto en dock de carga',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' pall_out']) ? $registro[' pall_out'] : '0,000'
        ],
        [
            'nro' => 9,
            'proceso' => 'Despacho',
            'descripcion' => 'OUT Caja/Bulto',
            'alcance' => 'Picking a nivel Caja/Bulto puesto en dock de carga',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' operacion_cajas_despacho']) ? $registro[' operacion_cajas_despacho'] : '0,000'
        ],
        [
            'nro' => 10,
            'proceso' => 'Despacho',
            'descripcion' => 'OUT Unidades',
            'alcance' => 'Picking a nivel Unidades puesto en dock de carga',
            'udm' => 'Unidad',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' operacion_und_despacho']) ? $registro[' operacion_und_despacho'] : '0,000'
        ],
        [
            'nro' => 11,
            'proceso' => 'Despacho',
            'descripcion' => 'Carguío Pallet',
            'alcance' => 'Operador Montacarga',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' carga_pall']) ? $registro[' carga_pall'] : '0,000'
        ],
        [
            'nro' => 12,
            'proceso' => 'Despacho',
            'descripcion' => 'Carguío Caja/Bulto',
            'alcance' => 'Manual x caja / bulto',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => isset($registro[' estibaje_cajas']) ? $registro[' estibaje_cajas'] : '0,000'
        ],
        [
            'nro' => 13,
            'proceso' => 'Otros',
            'descripcion' => 'TAKE or PAY',
            'alcance' => 'Facturación mínima para el servicio logístico',
            'udm' => 'Global',
            'frecuencia' => 'Mensual',
            'valor' => '400,000'
        ]
    ];
    
    // Información del cliente
    $cliente_info = htmlspecialchars($registro[' almacen'] ?? 'Cliente') . ' - ' . 
                   htmlspecialchars($registro[' ciudad'] ?? 'Ciudad');
    
    // Generar HTML
    $html = '
    <div class="tarifas-reporte">
        <div class="reporte-header mb-4">
            <h4 class="text-center mb-3" style="color: #2c3e50;">
                <i class="fa fa-file-invoice-dollar"></i> TARIFAS ALMACENAJE CLIENTE
                <span class="badge bg-success" style="font-size: 10px; margin-left: 10px;">NORMAL</span>
            </h4>
            
            <div class="row">
                <div class="col-md-8">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td style="width: 30%;"><strong>Cliente:</strong></td>
                            <td style="color: #2980b9;">' . $cliente_info . '</td>
                        </tr>
                        <tr>
                            <td><strong>Servicio:</strong></td>
                            <td><span class="badge bg-info">' . htmlspecialchars($registro['servicio'] ?? 'N/A') . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Gestión:</strong></td>
                            <td><span class="badge bg-warning text-dark">' . htmlspecialchars($registro[' gestion'] ?? 'N/A') . '</span></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-4">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td><strong>Fecha:</strong></td>
                            <td class="text-end">' . $fecha_actual . '</td>
                        </tr>
                        <tr>
                            <td><strong>ID Registro:</strong></td>
                            <td class="text-end">#' . htmlspecialchars($registro['id']) . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="font-size: 12px;">
                <thead class="table-dark" style="background-color: #34495e;">
                    <tr>
                        <th width="5%" class="text-center">Nro.</th>
                        <th width="12%">Proceso</th>
                        <th width="25%">Descripción</th>
                        <th width="25%">Alcance</th>
                        <th width="8%">UDM</th>
                        <th width="10%">Frecuencia</th>
                        <th width="15%" class="text-end">P.U USD (Facturado)</th>
                    </tr>
                </thead>
                <tbody>';
    
    // Agregar filas de procesos
    foreach ($procesos as $proceso) {
        // Determinar color de fondo según proceso
        $bg_color = '';
        if ($proceso['proceso'] == 'Recepción') $bg_color = 'background-color: #e8f6f3;';
        if ($proceso['proceso'] == 'Almacenaje') $bg_color = 'background-color: #fef9e7;';
        if ($proceso['proceso'] == 'Despacho') $bg_color = 'background-color: #f4ecf7;';
        if ($proceso['proceso'] == 'Otros') $bg_color = 'background-color: #fdedec;';
        
        $html .= '
                <tr style="' . $bg_color . '">
                    <td class="text-center"><strong>' . $proceso['nro'] . '</strong></td>
                    <td><strong>' . $proceso['proceso'] . '</strong></td>
                    <td>' . $proceso['descripcion'] . '</td>
                    <td>' . $proceso['alcance'] . '</td>
                    <td class="text-center">' . $proceso['udm'] . '</td>
                    <td class="text-center">' . $proceso['frecuencia'] . '</td>
                    <td class="text-end"><strong>$ ' . $proceso['valor'] . '</strong></td>
                </tr>';
    }
    
    // Pie de tabla
    $html .= '
                </tbody>
            </table>
        </div>
        
        <div class="mt-4">
            <div class="alert alert-warning mb-2" style="font-size: 11px;">
                <i class="fa fa-exclamation-circle"></i> <strong>Nota1.-</strong> Todos los precios incluyen impuestos de ley
            </div>
            <div class="alert alert-info mb-0" style="font-size: 11px;">
                <i class="fa fa-exclamation-circle"></i> <strong>Nota2.-</strong> Los precios están con punto (,) decimal
            </div>
        </div>
    </div>';
    
    return $html;
}

// Función para generar la tabla de tarifas especiales
function generarTablaTarifasEspeciales($registro, $tarifas_especiales, $guid = '') {
    $fecha_actual = date('d/m/Y H:i:s');
    
    // Información del cliente
    $cliente_info = htmlspecialchars($registro[' almacen'] ?? 'Cliente') . ' - ' . 
                   htmlspecialchars($registro[' ciudad'] ?? 'Ciudad');
    
    // Generar HTML
    $html = '
    <div class="tarifas-reporte">
        <div class="reporte-header mb-4">
            <h4 class="text-center mb-3" style="color: #2c3e50;">
                <i class="fa fa-star"></i> TARIFAS ESPECIALES
                <span class="badge bg-primary" style="font-size: 10px; margin-left: 10px;">ESPECIAL</span>
            </h4>
            
            <div class="row">
                <div class="col-md-8">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td style="width: 30%;"><strong>Cliente:</strong></td>
                            <td style="color: #2980b9;">' . $cliente_info . '</td>
                        </tr>
                        <tr>
                            <td><strong>Servicio:</strong></td>
                            <td><span class="badge bg-info">' . htmlspecialchars($registro['servicio'] ?? 'N/A') . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>Gestión:</strong></td>
                            <td><span class="badge bg-warning text-dark">' . htmlspecialchars($registro[' gestion'] ?? 'N/A') . '</span></td>
                        </tr>
                        <tr>
                            <td><strong>UTD:</strong></td>
                            <td><span class="badge bg-secondary">' . ($registro[' utd'] ?? '0.15') . '%</span></td>
                        </tr>';
    
    // Mostrar GUID si está disponible
    if (!empty($guid)) {
        $html .= '
                        <tr>
                            <td><strong>GUID:</strong></td>
                            <td><small class="text-muted">' . htmlspecialchars($guid) . '</small></td>
                        </tr>';
    }
    
    $html .= '
                    </table>
                </div>
                <div class="col-md-4">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td><strong>Fecha:</strong></td>
                            <td class="text-end">' . $fecha_actual . '</td>
                        </tr>
                        <tr>
                            <td><strong>ID Registro:</strong></td>
                            <td class="text-end">#' . htmlspecialchars($registro['id']) . '</td>
                        </tr>
                        <tr>
                            <td><strong>Total Items:</strong></td>
                            <td class="text-end">' . count($tarifas_especiales) . '</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>';
    
    if (empty($tarifas_especiales)) {
        $html .= '
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i> No se encontraron tarifas especiales para este registro.';
        
        if (!empty($guid)) {
            $html .= '<br><small class="mt-1">Buscando con GUID: <strong>' . htmlspecialchars($guid) . '</strong></small>';
        }
        
        $html .= '
        </div>';
    } else {
        $html .= '
        <div class="table-responsive">
            <table class="table table-bordered table-hover" style="font-size: 12px;">
                <thead class="table-dark" style="background-color: #007bff;">
                    <tr>
                        <th width="5%" class="text-center">#</th>
                        <th width="40%">Servicio Especial</th>
                        <th width="30%" class="text-end">Costo (USD)</th>
                        <th width="25%">Frecuencia</th>
                    </tr>
                </thead>
                <tbody>';
        
        // Agregar filas de tarifas especiales
        $contador = 1;
        $total_costo = 0;
        
        foreach ($tarifas_especiales as $tarifa) {
            // Convertir costo a número
            $costo_limpio = str_replace(',', '.', $tarifa['costo'] ?? '0');
            $costo_numero = floatval($costo_limpio);
            $total_costo += $costo_numero;
            
            $costo_formateado = number_format($costo_numero, 3, ',', '.');
            
            $html .= '
                <tr>
                    <td class="text-center"><strong>' . $contador . '</strong></td>
                    <td><strong>' . htmlspecialchars($tarifa['Servicio'] ?? 'Sin nombre') . '</strong></td>
                    <td class="text-end"><strong>$ ' . $costo_formateado . '</strong></td>
                    <td class="text-center">
                        <span class="badge" style="background-color: ';
            
            // Color diferente según frecuencia
            // Color diferente según frecuencia
            $frecuencia = $tarifa['Frecuencia'] ?? 'Mensualizado';
            if ($frecuencia == 'Anual') $html .= '#28a745';
            elseif ($frecuencia == 'Mensualizado') $html .= '#007bff';
            elseif ($frecuencia == 'Unitaria') $html .= '#9b59b6'; // Color morado para Unitaria
            else $html .= '#6c757d';
            
            $html .= '; color: white;">';
            if ($frecuencia == 'Unitaria') {
                $html .= '<i class="fa fa-cube"></i> ' . htmlspecialchars($frecuencia);
            } else {
                $html .= htmlspecialchars($frecuencia);
            }
            $html .= '</span>
                    </td>
                </tr>';
            
            $contador++;
        }
        
        $total_formateado = number_format($total_costo, 3, ',', '.');
        
        $html .= '
                </tbody>
                <tfoot style="background-color: #f8f9fa;">
                    <tr>
                        <td colspan="2" class="text-end"><strong>Total:</strong></td>
                        <td class="text-end"><strong>$ ' . $total_formateado . '</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>';
    }
    
    $html .= '
        <div class="mt-4">
            <div class="alert alert-info mb-2" style="font-size: 11px;">
                <i class="fa fa-info-circle"></i> <strong>Nota:</strong> Este registro está en modo especial. Las tarifas normales no aplican.
            </div>
        </div>
    </div>';
    
    return $html;
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>