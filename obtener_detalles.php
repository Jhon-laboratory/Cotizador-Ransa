<?php
// obtener_detalles.php - VERSIÓN SIMPLIFICADA CON SQLSRV
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'obtener_detalles') {
    
    $response = ['success' => false, 'message' => '', 'html' => '', 'registro' => []];
    $id = $_POST['id'] ?? 0;
    
    if ($id > 0) {
        try {
            // Buscar por ID con SQLSRV
            $sql = "SELECT * FROM externos.CotizadorTarifas WHERE id = ?";
            $stmt = sqlsrv_prepare($conn, $sql, array(&$id));
            
            if ($stmt === false) {
                $response['message'] = 'Error al preparar consulta: ' . print_r(sqlsrv_errors(), true);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            if (!sqlsrv_execute($stmt)) {
                $response['message'] = 'Error al ejecutar consulta: ' . print_r(sqlsrv_errors(), true);
                echo json_encode($response, JSON_UNESCAPED_UNICODE);
                exit;
            }
            
            $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($registro) {
                // Generar HTML formateado para la tabla
                $html = generarTablaTarifas($registro);
                
                $response = [
                    'success' => true,
                    'message' => 'Registro encontrado',
                    'html' => $html,
                    'registro' => $registro
                ];
            } else {
                $response['message'] = 'No se encontró el registro con ID: ' . $id;
            }
            
            // Liberar recursos
            sqlsrv_free_stmt($stmt);
            
        } catch(Exception $e) {
            $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'ID no válido';
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// Función para generar la tabla formateada
function generarTablaTarifas($registro) {
    $fecha_actual = date('d/m/Y H:i:s');
    
    // Mapeo de columnas de la BD a los procesos
    $procesos = [
        [
            'nro' => 1,
            'proceso' => 'Recepción',
            'descripcion' => 'Descarga Pallet',
            'alcance' => 'Operador + Montacarga',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' descarga_pall'] ?? '0,00'
        ],
        [
            'nro' => 2,
            'proceso' => 'Recepción',
            'descripcion' => 'Descarga Caja/Bulto',
            'alcance' => 'Manual x caja / bulto',
            'udm' => 'Caja / Bulto',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' estibaje_cajas'] ?? '0,00'
        ],
        [
            'nro' => 3,
            'proceso' => 'Recepción',
            'descripcion' => 'IN Pallet',
            'alcance' => 'Verificación, Reporte, Putaway',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' pall_in'] ?? '0,00'
        ],
        [
            'nro' => 4,
            'proceso' => 'Recepción',
            'descripcion' => 'IN Caja/Bulto',
            'alcance' => 'Verificación, Reporte, Putaway',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' operacion_cajas_recepcion'] ?? '0,00'
        ],
        [
            'nro' => 5,
            'proceso' => 'Recepción',
            'descripcion' => 'IN Unidades',
            'alcance' => 'Verificación, Reporte, Putaway',
            'udm' => 'Unidad',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' operacion_und_recepcion'] ?? '0,00'
        ],
        [
            'nro' => 6,
            'proceso' => 'Almacenaje',
            'descripcion' => 'Almacenaje m2',
            'alcance' => 'Almacenamiento mensual por m2 de ocupación (Incluye Productos + Pasillo)',
            'udm' => 'm2',
            'frecuencia' => 'Mensual',
            'valor' => $registro[' m2_almacen'] ?? '0,00'
        ],
        [
            'nro' => 7,
            'proceso' => 'Almacenaje',
            'descripcion' => 'Almacenaje Pallet',
            'alcance' => 'Almacenamiento mensual por pallet posicion/rack para pallet mercosur (1m x 1,2m x 1,55m)',
            'udm' => 'Pallet',
            'frecuencia' => 'Por Evento',
            'valor' => $registro[' almacen_pos'] ?? '0,00'
        ],
        [
            'nro' => 8,
            'proceso' => 'Despacho',
            'descripcion' => 'OUT Pallet',
            'alcance' => 'Picking a nivel pallet puesto en dock de carga',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' pall_out'] ?? '0,00'
        ],
        [
            'nro' => 9,
            'proceso' => 'Despacho',
            'descripcion' => 'OUT Caja/Bulto',
            'alcance' => 'Picking a nivel Caja/Bulto puesto en dock de carga',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' operacion_cajas_despacho'] ?? '0,00'
        ],
        [
            'nro' => 10,
            'proceso' => 'Despacho',
            'descripcion' => 'OUT Unidades',
            'alcance' => 'Picking a nivel Unidades puesto en dock de carga',
            'udm' => 'Unidad',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' operacion_und_despacho'] ?? '0,00'
        ],
        [
            'nro' => 11,
            'proceso' => 'Despacho',
            'descripcion' => 'Carguío Pallet',
            'alcance' => 'Operador Montacarga',
            'udm' => 'Pallet',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' carga_pall'] ?? '0,00'
        ],
        [
            'nro' => 12,
            'proceso' => 'Despacho',
            'descripcion' => 'Carguio Caja/Bulto',
            'alcance' => 'Manual x caja / bulto',
            'udm' => 'Caja/Bulto',
            'frecuencia' => 'Por evento',
            'valor' => $registro[' estibaje_cajas'] ?? '0,00'
        ],
        [
            'nro' => 13,
            'proceso' => 'Otros',
            'descripcion' => 'TAKE or PAY',
            'alcance' => 'Facturación mínima para el servicio logístico',
            'udm' => 'Global',
            'frecuencia' => 'Mensual',
            'valor' => '400,00'
        ]
    ];
    
    // Información del cliente
    $cliente_info = htmlspecialchars($registro[' almacen'] ?? 'Cliente') . ' - ' . 
                   htmlspecialchars($registro[' ciudad'] ?? 'Ciudad');
    
    // Generar HTML SOLO CON LA TABLA PRINCIPAL
    $html = '
    <div class="tarifas-reporte">
        <div class="reporte-header mb-4">
            <h4 class="text-center mb-3" style="color: #2c3e50;">
                <i class="fas fa-file-invoice-dollar"></i> TARIFAS ALMACENAJE CLIENTE
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
                <i class="fas fa-exclamation-circle"></i> <strong>Nota1.-</strong> Todos los precios incluyen impuestos de ley
            </div>
            <div class="alert alert-info mb-0" style="font-size: 11px;">
                <i class="fas fa-exclamation-circle"></i> <strong>Nota2.-</strong> Los precios están con punto (,) decimal
            </div>
        </div>
    </div>';
    
    return $html;
}

echo json_encode(['success' => false, 'message' => 'Método no permitido']);
?>