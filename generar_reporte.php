<?php
// generar_reporte.php - VERSIÓN COMPATIBLE CON PHP 7.4 CON SQLSRV - CON SOPORTE PARA TARIFAS ESPECIALES
// MODIFICADO: Cambiado el contenido de la sección de detalles para tarifas especiales por "Alcance de Servicio de Inventario"
session_start();
require_once 'conexion.php';
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $id = $_POST['id'] ?? 0;
    $formato = $_POST['formato'] ?? 'excel';
    
    if ($id > 0) {
        try {
            // Buscar registro por ID con SQLSRV
            $sql = "SELECT * FROM externos.CotizadorTarifas WHERE id = ?";
            $stmt = sqlsrv_prepare($conn, $sql, array(&$id));
            
            if ($stmt === false) {
                die('Error al preparar consulta: ' . print_r(sqlsrv_errors(), true));
            }
            
            if (!sqlsrv_execute($stmt)) {
                die('Error al ejecutar consulta: ' . print_r(sqlsrv_errors(), true));
            }
            
            $registro = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
            
            if ($registro) {
                // Verificar si es modo especial
                $modo_especial = isset($registro['modo_especial']) && $registro['modo_especial'] == 1;
                $guid_tarifa = $registro['ID_Tarifas'] ?? '';
                
                // Cliente info
                $cliente_info = ($registro[' almacen'] ?? 'Cliente') . ' - ' . ($registro[' ciudad'] ?? 'Ciudad');
                
                // Si es modo especial, obtener tarifas especiales
                if ($modo_especial && !empty($guid_tarifa)) {
                    $tarifas_especiales = obtenerTarifasEspeciales($conn, $guid_tarifa);
                    
                    // Generar según formato para tarifas especiales
                    switch($formato) {
                        case 'excel':
                            generarExcelTarifasEspeciales($registro, $tarifas_especiales, $cliente_info);
                            break;
                        case 'pdf':
                        case 'imprimir':
                        default:
                            generarVistaTarifasEspeciales($registro, $tarifas_especiales, $cliente_info);
                    }
                } else {
                    // Modo normal - usar los procesos normales
                    $procesos = obtenerProcesosNormales($registro);
                    
                    // Generar según formato para tarifas normales
                    switch($formato) {
                        case 'excel':
                            generarExcelCompleto($registro, $procesos, $cliente_info);
                            break;
                        case 'pdf':
                        case 'imprimir':
                        default:
                            generarVistaImpresionCompleta($registro, $procesos, $cliente_info);
                    }
                }
                
                // Liberar recursos
                sqlsrv_free_stmt($stmt);
                
            } else {
                die('No se encontró el registro');
            }
            
        } catch(Exception $e) {
            die('Error en la base de datos: ' . $e->getMessage());
        }
    } else {
        die('ID no válido');
    }
    exit;
}

// ==================== FUNCIONES AUXILIARES ====================

// Función para obtener tarifas especiales por GUID
function obtenerTarifasEspeciales($conn, $guid_tarifa) {
    $tarifas_especiales = [];
    
    // Intentar diferentes ubicaciones de tabla
    $tablas_posibles = [
        'DPL.externos.TarifaEspecial',
        'externos.TarifaEspecial'
    ];
    
    foreach ($tablas_posibles as $tabla) {
        try {
            $sql = "SELECT Servicio, costo, Frecuencia 
                    FROM $tabla 
                    WHERE ID_Tarifa = ? 
                    ORDER BY Servicio";
            
            $stmt = sqlsrv_prepare($conn, $sql, array(&$guid_tarifa));
            
            if ($stmt !== false && sqlsrv_execute($stmt)) {
                while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                    $tarifas_especiales[] = $row;
                }
                sqlsrv_free_stmt($stmt);
                
                if (!empty($tarifas_especiales)) {
                    break;
                }
            }
        } catch(Exception $e) {
            continue;
        }
    }
    
    return $tarifas_especiales;
}

// Función para obtener procesos normales
function obtenerProcesosNormales($registro) {
    return [
        ['nro' => 1, 'proceso' => 'Recepción', 'descripcion' => 'Recepción', 'alcance' => 'Descarga Pallet', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' descarga_pall'] ?? '0,000'],
        ['nro' => 2, 'proceso' => 'Recepción', 'descripcion' => 'Recepción', 'alcance' => 'Descarga Caja/Bulto', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' estibaje_cajas'] ?? '0,000'],
        ['nro' => 3, 'proceso' => 'Recepción', 'descripcion' => 'Recepción', 'alcance' => 'IN Pallet', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' pall_in'] ?? '0,000'],
        ['nro' => 4, 'proceso' => 'Recepción', 'descripcion' => 'Recepción', 'alcance' => 'IN Caja/Bulto', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_cajas_recepcion'] ?? '0,000'],
        ['nro' => 5, 'proceso' => 'Recepción', 'descripcion' => 'Recepción', 'alcance' => 'IN Unidades', 'udm' => 'Unidad', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_und_recepcion'] ?? '0,000'],
        ['nro' => 6, 'proceso' => 'Almacenaje', 'descripcion' => 'Almacenaje', 'alcance' => 'Almacenaje m2', 'udm' => 'm2', 'frecuencia' => 'Mensual', 'valor' => $registro[' m2_almacen'] ?? '0,000'],
        ['nro' => 7, 'proceso' => 'Almacenaje', 'descripcion' => 'Almacenaje', 'alcance' => 'Almacenaje Pall', 'udm' => 'Pallet', 'frecuencia' => 'Mensual', 'valor' => $registro[' almacen_pos'] ?? '0,000'],
        ['nro' => 8, 'proceso' => 'Despacho', 'descripcion' => 'Despacho', 'alcance' => 'OUT Pallet', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' pall_out'] ?? '0,000'],
        ['nro' => 9, 'proceso' => 'Despacho', 'descripcion' => 'Despacho', 'alcance' => 'OUT Caja/Bulto', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_cajas_despacho'] ?? '0,000'],
        ['nro' => 10, 'proceso' => 'Despacho', 'descripcion' => 'Despacho', 'alcance' => 'OUT Unidades', 'udm' => 'Unidad', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_und_despacho'] ?? '0,000'],
        ['nro' => 11, 'proceso' => 'Despacho', 'descripcion' => 'Despacho', 'alcance' => 'Carguío Pallet', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' carga_pall'] ?? '0,000'],
        ['nro' => 12, 'proceso' => 'Despacho', 'descripcion' => 'Despacho', 'alcance' => 'Carguío Caja/Bulto', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' estibaje_cajas'] ?? '0,000'],
        ['nro' => 13, 'proceso' => 'Otros', 'descripcion' => 'Otros', 'alcance' => 'TAKE or PAY', 'udm' => 'Global', 'frecuencia' => 'Mensual', 'valor' => '400,000']
    ];
}

// ==================== EXCEL PARA TARIFAS ESPECIALES ====================
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

function generarExcelTarifasEspeciales($registro, $tarifas_especiales, $cliente_info) {
    // Crear nuevo Spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // ========== HOJA 1: TARIFAS ESPECIALES ==========
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('Tarifas Especiales');
    
    // Configurar página
    $sheet1->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_A4);
    
    // Establecer anchos de columna
    $sheet1->getColumnDimension('A')->setWidth(5);
    $sheet1->getColumnDimension('B')->setWidth(25);
    $sheet1->getColumnDimension('C')->setWidth(20);
    $sheet1->getColumnDimension('D')->setWidth(40);
    $sheet1->getColumnDimension('E')->setWidth(8);
    $sheet1->getColumnDimension('F')->setWidth(12);
    $sheet1->getColumnDimension('G')->setWidth(15);
    
    // Título
    $sheet1->mergeCells('A1:G1');
    $sheet1->setCellValue('A1', 'TARIFAS ESPECIALES - CLIENTE: ' . $cliente_info);
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet1->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A24');
    $sheet1->getStyle('A1')->getFont()->setColor(new Color('FFFFFFFF'));
    
    // Información del cliente
    $sheet1->setCellValue('A2', 'Servicio:');
    $sheet1->setCellValue('B2', $registro['servicio'] ?? 'N/A');
    $sheet1->setCellValue('D2', 'Ciudad:');
    $sheet1->setCellValue('E2', $registro[' ciudad'] ?? 'N/A');
    $sheet1->setCellValue('F2', 'Gestión:');
    $sheet1->setCellValue('G2', $registro[' gestion'] ?? 'N/A');
    
    // Fecha
    $sheet1->setCellValue('A3', 'Fecha:');
    $sheet1->mergeCells('B3:G3');
    $sheet1->setCellValue('B3', date('d/m/Y H:i:s'));
    $sheet1->getStyle('B3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Modo especial badge
    $sheet1->setCellValue('A4', 'Modo:');
    $sheet1->setCellValue('B4', 'ESPECIAL');
    $sheet1->getStyle('B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A24');
    $sheet1->getStyle('B4')->getFont()->setColor(new Color('FFFFFFFF'))->setBold(true);
    
    // Encabezados para tarifas especiales
    $headers = ['Nro.', 'Servicio', 'Ciudad', 'Servicio Especial', 'UDM', 'Frecuencia', 'P.U USD (Facturado)'];
    $col = 'A';
    $row = 6;
    
    foreach ($headers as $header) {
        $cell = $col . $row;
        $sheet1->setCellValue($cell, $header);
        $sheet1->getStyle($cell)
            ->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $sheet1->getStyle($cell)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3498DB');
        $sheet1->getStyle($cell)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getStyle($cell)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $col++;
    }
    
    // Filas de tarifas especiales
    $row = 7;
    $contador = 1;
    
    if (!empty($tarifas_especiales)) {
        foreach ($tarifas_especiales as $tarifa) {
            $servicio_principal = $registro['servicio'] ?? 'N/A';
            $ciudad = $registro[' ciudad'] ?? 'N/A';
            $servicio_especial = $tarifa['Servicio'] ?? 'Sin nombre';
            $udm = '1'; // UDM por defecto para tarifas especiales
            $frecuencia = $tarifa['Frecuencia'] ?? 'Mensualizado';
            $costo = $tarifa['costo'] ?? '0,000';
            
            $sheet1->setCellValue('A' . $row, $contador);
            $sheet1->setCellValue('B' . $row, $servicio_principal);
            $sheet1->setCellValue('C' . $row, $ciudad);
            $sheet1->setCellValue('D' . $row, $servicio_especial);
            $sheet1->setCellValue('E' . $row, $udm);
            $sheet1->setCellValue('F' . $row, $frecuencia);
            
            // Formato numérico para precio
            $valor = str_replace(',', '.', $costo);
            $sheet1->setCellValue('G' . $row, $valor);
            $sheet1->getStyle('G' . $row)
                ->getNumberFormat()
                ->setFormatCode('#,##0.000');
            
            // Bordes para toda la fila
            foreach (range('A', 'G') as $col) {
                $sheet1->getStyle($col . $row)
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            }
            
            // Centrar columnas específicas
            $sheet1->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet1->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            
            // Color de fondo alternado para mejor lectura
            if ($contador % 2 == 0) {
                foreach (range('A', 'G') as $col) {
                    $sheet1->getStyle($col . $row)
                        ->getFill()->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFF2F2F2');
                }
            }
            
            $row++;
            $contador++;
        }
    } else {
        // Si no hay tarifas especiales
        $sheet1->mergeCells('A' . $row . ':G' . $row);
        $sheet1->setCellValue('A' . $row, 'No se encontraron tarifas especiales para este registro.');
        $sheet1->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;
    }
    
    // Notas
    $row++;
    $sheet1->mergeCells('A' . $row . ':G' . $row);
    $sheet1->setCellValue('A' . $row, 'Nota1.- Todos los precios incluyen impuestos de ley');
    $sheet1->getStyle('A' . $row)->getFont()->setItalic(true);
    
    $row++;
    $sheet1->mergeCells('A' . $row . ':G' . $row);
    $sheet1->setCellValue('A' . $row, 'Nota2.- Los precios están con COMA (,) decimal');
    $sheet1->getStyle('A' . $row)->getFont()->setItalic(true);
    
    // Autoajustar altura de filas
    foreach (range(2, $row) as $rowNum) {
        $sheet1->getRowDimension($rowNum)->setRowHeight(-1);
    }
    
    // ========== HOJA 2: ALCANCE DE SERVICIO DE INVENTARIO (TARIFAS ESPECIALES) ==========
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Alcance - Inventario');
    $sheet2->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
        ->setPaperSize(PageSetup::PAPERSIZE_A4);
    
    // Configurar columnas
    $sheet2->getColumnDimension('A')->setWidth(8);
    $sheet2->getColumnDimension('B')->setWidth(75);
    $sheet2->getColumnDimension('C')->setWidth(25);
    
    // Ajustar altura de la fila 1 para el logo
    $sheet2->getRowDimension(1)->setRowHeight(50);
    
    // TÍTULO MODIFICADO: Alcance de Servicio de Inventario
    $sheet2->mergeCells('A1:B1');
    $sheet2->setCellValue('A1', 'ALCANCE DE SERVICIO DE INVENTARIO');
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A24');
    $sheet2->getStyle('A1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    
    // Buscar e insertar logo local
    $logoLocal = 'ransa.png';
    
    // Verificar si existe la imagen local
    if (file_exists($logoLocal)) {
        $drawing = new Drawing();
        $drawing->setName('Logo Ransa');
        $drawing->setDescription('Logo Ransa');
        $drawing->setPath($logoLocal);
        $drawing->setHeight(39);
        $drawing->setCoordinates('C1');
        $drawing->setWorksheet($sheet2);
        
        // Centrar el logo en la celda
        $sheet2->getStyle('C1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    } else {
        // Si no existe el logo local, poner texto alternativo
        $sheet2->setCellValue('C1', 'LOGO RANSA');
        $sheet2->getStyle('C1')->getFont()->setBold(true)->setSize(12);
        $sheet2->getStyle('C1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet2->getStyle('C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F4F8');
    }
    
    // Ajustar altura de la celda C1
    $sheet2->getStyle('C1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $sheet2->setCellValue('A2', 'Fecha:');
    $sheet2->setCellValue('B2', date('d/m/Y'));
    $sheet2->setCellValue('A3', 'Almacen:');
    $sheet2->setCellValue('B3', $cliente_info);
    $sheet2->setCellValue('A4', 'Modo:');
    $sheet2->setCellValue('B4', 'ESPECIAL - SERVICIO DE INVENTARIO');
    $sheet2->getStyle('B4')->getFont()->setBold(true)->setColor(new Color('FF009A24'));
    
    $row = 5;
    
    // ENCABEZADO DE SECCIÓN MODIFICADO
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, 'ALCANCE DEL SERVICIO DE INVENTARIO - DESCRIPCIÓN COMPLETA');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->setCellValue('A' . $row, 'Servicio de Inventario Físico - Conteo, Validación y Conciliación');
    $sheet2->getStyle('A' . $row)->getFont()->setItalic(true);
    $row += 2;
    
    // ========== 1. OBJETIVO DEL SERVICIO ==========
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '1. OBJETIVO DEL SERVICIO');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Realizar el conteo físico, validación y conciliación de los productos almacenados, con el fin de asegurar la exactitud de los registros de inventario, identificar diferencias y generar reportes confiables para la gestión operativa y financiera.');
    $sheet2->getStyle('B' . $row)->getAlignment()->setWrapText(true);
    $row++;
    $row++;
    
    // ========== 2. ACTIVIDADES INCLUIDAS ==========
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '2. ACTIVIDADES INCLUIDAS');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    // 2.1. Planificación previa
    $sheet2->setCellValue('A' . $row, '2.1.');
    $sheet2->setCellValue('B' . $row, 'Planificación previa');
    $sheet2->getStyle('B' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Reunión inicial con el cliente para definir:');
    $row++;
    
    $sheet2->setCellValue('A' . $row, 'o');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Tipología de productos.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, 'o');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Metodología de conteo (cíclico, total, selectivo).');
    $row++;
    
    $sheet2->setCellValue('A' . $row, 'o');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Sectores, zonas o racks a inventariar.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, 'o');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Reglas de seguridad y normas internas.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Elaboración del plan de trabajo y cronograma.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Identificación de recursos necesarios: personal, equipos, lectores, etiquetas, etc.');
    $row++;
    $row++;
    
    // 2.2. Ejecución del inventario físico
    $sheet2->setCellValue('A' . $row, '2.2.');
    $sheet2->setCellValue('B' . $row, 'Ejecución del inventario físico');
    $sheet2->getStyle('B' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Despliegue del equipo de inventario en planta.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Conteo físico de unidades, bultos, pallets u otras presentaciones.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Verificación de:');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'SKU / código del producto.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Descripciones.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Condición del empaque o estado del producto.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Alturas, posiciones y ubicaciones (ubicado/no ubicado).');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Uso de herramientas de conteo: RANSA.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Registro inmediato de los datos recolectados.');
    $row++;
    $row++;
    
    // 2.3. Conteos dobles o auditorías
    $sheet2->setCellValue('A' . $row, '2.3.');
    $sheet2->setCellValue('B' . $row, 'Conteos dobles o auditorías');
    $sheet2->getStyle('B' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Realización de conteos de control (segunda vuelta) en posiciones con diferencias.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Validación cruzada con supervisión o auditor interno/externo del cliente.');
    $row++;
    $row++;
    
    // 2.4. Conciliación y análisis de diferencias
    $sheet2->setCellValue('A' . $row, '2.4.');
    $sheet2->setCellValue('B' . $row, 'Conciliación y análisis de diferencias');
    $sheet2->getStyle('B' . $row)->getFont()->setBold(true);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Comparación entre conteo físico y stock teórico del sistema.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Identificación y clasificación de diferencias:');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Faltantes');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Sobrantes');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Productos mal ubicados');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Elaboración de informes de hallazgos.');
    $row++;
    $row++;
    
    // ========== 3. ENTREGABLES DEL SERVICIO ==========
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '3. ENTREGABLES DEL SERVICIO');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Reporte consolidado de inventario físico, con:');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Inventario total por SKU.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Diferencias entre físico vs. sistema.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Porcentaje de exactitud global y por familia de productos.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Detalle de productos observados o con incidencias.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Acta de inventario con firma del cliente.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Recomendaciones operativas para mejora del control de stock.');
    $row++;
    $row++;
    
    // ========== 4. RECURSOS INCLUIDOS ==========
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '4. RECURSOS INCLUIDOS');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Personal entrenado en procesos de inventario.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Supervisión y control remoto de la calidad del conteo.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Equipos de captura (handhelds, scanners, u otro equipamiento cuando aplique).');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Elementos de seguridad industrial según normativa RANSA.');
    $row++;
    $row++;
    
    // ========== 5. ACTIVIDADES NO INCLUIDAS ==========
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '5. ACTIVIDADES NO INCLUIDAS (OPCIONAL SEGÚN CONTRATO)');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Re-etiquetado, reempaque o paletizado de productos.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Movimientos de mercancía fuera de lo necesario para el conteo.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Ajustes en el sistema del cliente (ERP/WMS).');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Limpieza o reordenamiento de la bodega.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Auditorías externas regulatorias.');
    $row++;
    $row++;
    
    // ========== 6. RESPONSABILIDADES DEL CLIENTE ==========
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '6. RESPONSABILIDADES DEL CLIENTE');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Proveer acceso al almacén y zonas a inventariar.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Asegurar que no exista movimiento de los productos durante el conteo.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Entregar el archivo maestro: SKU, descripciones, etc.');
    $row++;
    
    $sheet2->setCellValue('A' . $row, '');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'Proveer acompañamiento del área de almacén, logística o auditoría.');
    $row++;
    $row++;
    
    // Resumen final
    $row += 2;
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '📋 Resumen: Este documento contiene el alcance completo del Servicio de Inventario, organizado en 6 secciones principales que cubren objetivo, actividades, entregables, recursos, exclusiones y responsabilidades.');
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A24');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Ajustar altura de filas para texto largo
    foreach (range(5, $row) as $rowNum) {
        $sheet2->getRowDimension($rowNum)->setRowHeight(-1);
        $sheet2->getStyle('B' . $rowNum)->getAlignment()->setWrapText(true);
        $sheet2->getStyle('C' . $rowNum)->getAlignment()->setWrapText(true);
    }
    
    // ========== ENVIAR AL NAVEGADOR ==========
    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Tarifas_Especiales_Inventario_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cliente_info) . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    
    $writer->save('php://output');
    exit;
}

// ==================== VISTA IMPRESIÓN PARA TARIFAS ESPECIALES ====================
function generarVistaTarifasEspeciales($registro, $tarifas_especiales, $cliente_info) {
    $fecha_completa = date('d/m/Y H:i:s');
    $fecha_hora2 = date('H:i:s');
    $fecha_simple = date('d/m/Y');
    
    ?>
    <!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Tarifas Especiales - <?php echo htmlspecialchars($cliente_info); ?></title>
    <style>
        /* ESTILOS PARA IMPRESIÓN - MODIFICADO A A4 VERTICAL */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.8cm;
            }
            .pagina2, .pagina3 {
                page-break-before: always;
            }
            .no-print { display: none !important; }
            body { 
                font-family: 'Calibri', Arial, sans-serif;
                font-size: 8.5pt;
                line-height: 1.15;
                color: #000;
            }
            .contenedor-pagina {
                min-height: 25.7cm;
                position: relative;
                width: 100%;
                overflow: hidden;
            }
        }
        
        /* ESTILOS GENERALES */
        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 10px;
            background: #f5f5f5;
        }
        
        .contenedor-pagina {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* BOTONES */
        .botones-contenedor {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, #009A24, #00D12F);
            border-radius: 8px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11pt;
            font-weight: bold;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-excel {
            background: linear-gradient(135deg, #217346, #1e9e6a);
            color: white;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #009A24, #00D12F);
            color: white;
        }
        
        .btn-imprimir {
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
        }
        
        .btn-cerrar {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* TABLA PRINCIPAL TARIFAS ESPECIALES */
        .tabla-especiales {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8pt;
            table-layout: fixed;
        }
        
        .tabla-especiales th {
            background: linear-gradient(135deg, #009A24, #00D12F);
            color: white;
            font-weight: bold;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid #7D3C98;
            vertical-align: middle;
            font-size: 9pt;
        }
        
        .tabla-especiales td {
            padding: 6px 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 8.5pt;
            word-wrap: break-word;
        }
        
        /* ANCHOS DE COLUMNAS */
        .tabla-especiales th:nth-child(1),
        .tabla-especiales td:nth-child(1) {
            width: 5% !important;
            text-align: center;
        }
        
        .tabla-especiales th:nth-child(2),
        .tabla-especiales td:nth-child(2) {
            width: 15% !important;
        }
        
        .tabla-especiales th:nth-child(3),
        .tabla-especiales td:nth-child(3) {
            width: 12% !important;
        }
        
        .tabla-especiales th:nth-child(4),
        .tabla-especiales td:nth-child(4) {
            width: 35% !important;
        }
        
        .tabla-especiales th:nth-child(5),
        .tabla-especiales td:nth-child(5) {
            width: 8% !important;
            text-align: center;
        }
        
        .tabla-especiales th:nth-child(6),
        .tabla-especiales td:nth-child(6) {
            width: 12% !important;
            text-align: center;
        }
        
        .tabla-especiales th:nth-child(7),
        .tabla-especiales td:nth-child(7) {
            width: 13% !important;
            text-align: right;
        }
        
        /* COLORES ALTERNADOS */
        .fila-par { background-color: #f8f9fa !important; }
        .fila-impar { background-color: white !important; }
        
        /* ENCABEZADO ESPECIAL */
        .encabezado-especial {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            color: white;
            margin-bottom: 15px;
            padding: 10px;
            background: linear-gradient(135deg, #009A24, #00D12F);
            border-radius: 8px;
        }
        
        .info-cliente {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        /* TABLA DETALLES - INVENTARIO */
        .tabla-detalles-inventario {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 7.5pt;
            table-layout: fixed;
        }
        
        .tabla-detalles-inventario th {
            background: linear-gradient(135deg, #009A24, #00D12F);
            color: white;
            font-weight: bold;
            padding: 6px 4px;
            text-align: left;
            border: 1px solid #2e5aa7;
            font-size: 8pt;
        }
        
        .tabla-detalles-inventario td {
            padding: 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 7.5pt;
        }
        
        .seccion-titulo-inventario {
            background: linear-gradient(135deg, #009A24, #00D12F);
            color: white;
            font-weight: bold;
            padding: 6px;
            margin: 12px 0 4px 0;
            border-radius: 3px;
            font-size: 9pt;
        }
        
        .numero-item-inventario {
            width: 35px !important;
            text-align: center;
            font-weight: bold;
            background: #e8f5e9;
            border-right: 2px solid #009A24;
        }
        
        /* FOOTER */
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 7pt;
            color: #7f8c8d;
            text-align: center;
            position: absolute;
            bottom: 10px;
            width: calc(100% - 30px);
        }
        
        .notas-especiales {
            margin-top: 15px;
            padding: 10px;
            background: linear-gradient(135deg, #FFD700, #FFC107);
            border-radius: 5px;
            font-size: 8.5pt;
            border-left: 4px solid #FF9800;
        }
        
        /* BADGE MODO ESPECIAL */
        .badge-especial {
            display: inline-block;
            padding: 3px 8px;
            background: linear-gradient(135deg, #009A24, #00D12F);
            color: white;
            border-radius: 15px;
            font-size: 9pt;
            font-weight: bold;
            margin-left: 10px;
        }
        
        /* ESTILOS PARA LISTAS DE INVENTARIO */
        .lista-vineta {
            margin-left: 20px;
            list-style-type: none;
            padding-left: 0;
        }
        
        .lista-vineta li {
            margin-bottom: 3px;
            position: relative;
            padding-left: 20px;
        }
        
        .lista-vineta li:before {
            content: "•";
            color: #009A24;
            font-weight: bold;
            position: absolute;
            left: 0;
        }
        
        .sub-lista {
            margin-left: 30px;
            list-style-type: none;
        }
        
        .sub-lista li:before {
            content: "◦";
            color: #555;
        }
    </style>
</head>
<body>

    <!-- BOTONES (solo en pantalla) -->
    <div class="no-print botones-contenedor">
        <button class="btn btn-excel" onclick="descargarExcel()">
            📊 Descargar Excel
        </button>
        <button class="btn btn-pdf" onclick="window.print()">
            📄 Generar PDF/Imprimir
        </button>
        <button class="btn btn-cerrar" onclick="window.close()">
            ❌ Cerrar
        </button>
    </div>
    
    <!-- === PÁGINA 1: TABLA DE TARIFAS ESPECIALES === -->
    <div class="contenedor-pagina pagina1">
        <div class="encabezado-especial">
            TARIFAS ESPECIALES - SERVICIO DE INVENTARIO <span class="badge-especial">MODO ESPECIAL</span>
        </div>
        
        <div class="info-cliente">
            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                <div><strong>Fecha:</strong> <?php echo $fecha_completa; ?></div>
                <div><strong>ID Registro:</strong> #<?php echo $registro['id']; ?></div>
            </div>
            <div><strong>Almacen:</strong> <?php echo htmlspecialchars($cliente_info); ?></div>
            <div><strong>Modo:</strong> ESPECIAL - SERVICIO DE INVENTARIO</div>
        </div>
        
        <?php if (!empty($tarifas_especiales)): ?>
        <table class="tabla-especiales">
            <thead>
                <tr>
                    <th>Nro.</th>
                    <th>Servicio</th>
                    <th>Ciudad</th>
                    <th>Servicio Especial</th>
                    <th>UDM</th>
                    <th>Frecuencia</th>
                    <th>P.U USD (Facturado)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tarifas_especiales as $index => $tarifa): 
                    $clase_fila = ($index % 2 == 0) ? 'fila-par' : 'fila-impar';
                ?>
                <tr class="<?php echo $clase_fila; ?>">
                    <td align="center"><strong><?php echo ($index + 1); ?></strong></td>
                    <td><?php echo htmlspecialchars($registro['servicio'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($registro[' ciudad'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($tarifa['Servicio'] ?? 'Sin nombre'); ?></td>
                    <td align="center">1</td>
                    <td align="center">
                        <?php 
                        $frecuencia = $tarifa['Frecuencia'] ?? 'Mensualizado';
                        $color_frecuencia = '';
                        
                        switch($frecuencia) {
                            case 'Mensualizado':
                                $color_frecuencia = '#3498db';
                                break;
                            case 'Anual':
                                $color_frecuencia = '#2ecc71';
                                break;
                            case 'Dia':
                                $color_frecuencia = '#e74c3c';
                                break;
                            case 'Unitaria':
                                $color_frecuencia = '#f39c12';
                                break;
                            default:
                                $color_frecuencia = '#7f8c8d';
                                break;
                        }
                        ?>
                        <span style="display: inline-block; padding: 2px 6px; background: <?php echo $color_frecuencia; ?>; color: white; border-radius: 10px; font-size: 7.5pt;">
                            <?php echo $frecuencia; ?>
                        </span>
                    </td>
                    <td align="right" style="font-weight: bold; color: #27ae60;">
                        $ <?php echo htmlspecialchars($tarifa['costo'] ?? '0,000'); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div style="text-align: center; padding: 40px; background: #f8d7da; border-radius: 5px; border: 1px solid #f5c6cb;">
            <h3 style="color: #721c24; margin-bottom: 10px;">⚠️ No se encontraron tarifas especiales</h3>
            <p style="color: #721c24;">Este registro está marcado como especial pero no tiene tarifas especiales asociadas.</p>
            <p style="color: #721c24; font-size: 9pt; margin-top: 10px;">
                GUID del registro: <?php echo htmlspecialchars($registro['ID_Tarifas'] ?? 'No tiene GUID'); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="notas-especiales">
            <p><strong>📝 Nota1.-</strong> Todos los precios incluyen impuestos de ley</p>
            <p><strong>💰 Nota2.-</strong> Los precios están con COMA (,) decimal</p>
            <p><strong>⚡ Nota3.-</strong> Las tarifas especiales corresponden al Servicio de Inventario</p>
        </div>
        
        <div class="footer">
            Página 1 de 2 | Sistema de Cotización - Tarifas Especiales de Inventario
        </div>
    </div>
    
    <!-- === PÁGINA 2: ALCANCE DE SERVICIO DE INVENTARIO (TARIFAS ESPECIALES) === -->
    <div class="contenedor-pagina pagina2">
        <div class="encabezado-especial" style="background: linear-gradient(135deg, #009A24, #00D12F);">
            ALCANCE DE SERVICIO DE INVENTARIO
        </div>
        
        <div style="text-align: right; margin-bottom: 15px;">
            <strong>Fecha:</strong> <?php echo $fecha_simple; ?>
        </div>
        
        <div style="margin-bottom: 20px; padding: 10px; background: #e8f5e9; border-radius: 5px; border-left: 4px solid #009A24;">
            <strong>Cliente:</strong> <?php echo htmlspecialchars($cliente_info); ?><br>
            <strong>Modo:</strong> <span style="color: #009A24; font-weight: bold;">ESPECIAL - SERVICIO DE INVENTARIO</span>
        </div>
        
        <div style="margin: 10px 0 20px 5px; font-style: italic; font-size: 11pt; font-weight: bold; color: #009A24;">
            Servicio de Inventario Físico - Conteo, Validación y Conciliación
        </div>
        
        <!-- 1. OBJETIVO DEL SERVICIO -->
        <div class="seccion-titulo-inventario">1. OBJETIVO DEL SERVICIO</div>
        <table class="tabla-detalles-inventario" style="margin-bottom: 15px;">
            <tr>
                <td colspan="2" style="padding: 8px; background: #f9f9f9;">
                    Realizar el conteo físico, validación y conciliación de los productos almacenados, con el fin de asegurar la exactitud de los registros de inventario, identificar diferencias y generar reportes confiables para la gestión operativa y financiera.
                </td>
            </tr>
        </table>
        
        <!-- 2. ACTIVIDADES INCLUIDAS -->
        <div class="seccion-titulo-inventario">2. ACTIVIDADES INCLUIDAS</div>
        
        <div style="margin-bottom: 10px; font-weight: bold; color: #009A24;">2.1. Planificación previa</div>
        <ul class="lista-vineta" style="margin-bottom: 10px;">
            <li><strong>Reunión inicial con el cliente para definir:</strong>
                <ul class="sub-lista">
                    <li>Tipología de productos.</li>
                    <li>Metodología de conteo (cíclico, total, selectivo).</li>
                    <li>Sectores, zonas o racks a inventariar.</li>
                    <li>Reglas de seguridad y normas internas.</li>
                </ul>
            </li>
            <li>Elaboración del plan de trabajo y cronograma.</li>
            <li>Identificación de recursos necesarios: personal, equipos, lectores, etiquetas, etc.</li>
        </ul>
        
        <div style="margin-bottom: 10px; font-weight: bold; color: #009A24;">2.2. Ejecución del inventario físico</div>
        <ul class="lista-vineta" style="margin-bottom: 10px;">
            <li>Despliegue del equipo de inventario en planta.</li>
            <li>Conteo físico de unidades, bultos, pallets u otras presentaciones.</li>
            <li><strong>Verificación de:</strong>
                <ul class="sub-lista">
                    <li>SKU / código del producto.</li>
                    <li>Descripciones.</li>
                    <li>Condición del empaque o estado del producto.</li>
                    <li>Alturas, posiciones y ubicaciones (ubicado/no ubicado).</li>
                </ul>
            </li>
            <li>Uso de herramientas de conteo: RANSA.</li>
            <li>Registro inmediato de los datos recolectados.</li>
        </ul>
        
        <div style="margin-bottom: 10px; font-weight: bold; color: #009A24;">2.3. Conteos dobles o auditorías</div>
        <ul class="lista-vineta" style="margin-bottom: 10px;">
            <li>Realización de conteos de control (segunda vuelta) en posiciones con diferencias.</li>
            <li>Validación cruzada con supervisión o auditor interno/externo del cliente.</li>
        </ul>
        
        <div style="margin-bottom: 10px; font-weight: bold; color: #009A24;">2.4. Conciliación y análisis de diferencias</div>
        <ul class="lista-vineta" style="margin-bottom: 15px;">
            <li>Comparación entre conteo físico y stock teórico del sistema.</li>
            <li><strong>Identificación y clasificación de diferencias:</strong>
                <ul class="sub-lista">
                    <li>Faltantes</li>
                    <li>Sobrantes</li>
                    <li>Productos mal ubicados</li>
                </ul>
            </li>
            <li>Elaboración de informes de hallazgos.</li>
        </ul>
        
        <!-- 3. ENTREGABLES DEL SERVICIO -->
        <div class="seccion-titulo-inventario">3. ENTREGABLES DEL SERVICIO</div>
        <ul class="lista-vineta" style="margin-bottom: 15px;">
            <li><strong>Reporte consolidado de inventario físico, con:</strong>
                <ul class="sub-lista">
                    <li>Inventario total por SKU.</li>
                    <li>Diferencias entre físico vs. sistema.</li>
                    <li>Porcentaje de exactitud global y por familia de productos.</li>
                    <li>Detalle de productos observados o con incidencias.</li>
                </ul>
            </li>
            <li>Acta de inventario con firma del cliente.</li>
            <li>Recomendaciones operativas para mejora del control de stock.</li>
        </ul>
        
        <!-- 4. RECURSOS INCLUIDOS -->
        <div class="seccion-titulo-inventario">4. RECURSOS INCLUIDOS</div>
        <ul class="lista-vineta" style="margin-bottom: 15px;">
            <li>Personal entrenado en procesos de inventario.</li>
            <li>Supervisión y control remoto de la calidad del conteo.</li>
            <li>Equipos de captura (handhelds, scanners, u otro equipamiento cuando aplique).</li>
            <li>Elementos de seguridad industrial según normativa RANSA.</li>
        </ul>
        
        <!-- 5. ACTIVIDADES NO INCLUIDAS -->
        <div class="seccion-titulo-inventario">5. ACTIVIDADES NO INCLUIDAS (OPCIONAL SEGÚN CONTRATO)</div>
        <ul class="lista-vineta" style="margin-bottom: 15px;">
            <li>Re-etiquetado, reempaque o paletizado de productos.</li>
            <li>Movimientos de mercancía fuera de lo necesario para el conteo.</li>
            <li>Ajustes en el sistema del cliente (ERP/WMS).</li>
            <li>Limpieza o reordenamiento de la bodega.</li>
            <li>Auditorías externas regulatorias.</li>
        </ul>
        
        <!-- 6. RESPONSABILIDADES DEL CLIENTE -->
        <div class="seccion-titulo-inventario">6. RESPONSABILIDADES DEL CLIENTE</div>
        <ul class="lista-vineta" style="margin-bottom: 15px;">
            <li>Proveer acceso al almacén y zonas a inventariar.</li>
            <li>Asegurar que no exista movimiento de los productos durante el conteo.</li>
            <li>Entregar el archivo maestro: SKU, descripciones, etc.</li>
            <li>Proveer acompañamiento del área de almacén, logística o auditoría.</li>
        </ul>
        
        <div style="margin-top: 30px; padding: 15px; background: linear-gradient(135deg, #009A24, #00D12F); border-radius: 5px; text-align: center;">
            <strong style="color: white;">📋 Resumen:</strong> 
            <span style="color: white;">
                Este documento contiene el alcance completo del Servicio de Inventario, organizado en 6 secciones principales que cubren objetivo, actividades, entregables, recursos, exclusiones y responsabilidades.
            </span>
        </div>
        
        <div class="footer">
            Página 2 de 2 | Documento generado el <?php echo $fecha_completa; ?> | © <?php echo date('Y'); ?> RANSA - Servicio de Inventario
        </div>
    </div>
    
    <script>
    function descargarExcel() {
        const id = <?php echo $registro['id']; ?>;
        
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
        idInput.value = id;
        
        const formatoInput = document.createElement('input');
        formatoInput.type = 'hidden';
        formatoInput.name = 'formato';
        formatoInput.value = 'excel';
        
        form.appendChild(accionInput);
        form.appendChild(idInput);
        form.appendChild(formatoInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    </script>
    
    </body>
    </html>
    <?php
    exit;
}

// ==================== EXCEL COMPLETO CON PHPSPREADSHEET ====================

function generarExcelCompleto($registro, $procesos, $cliente_info) {
    // Crear nuevo Spreadsheet
    $spreadsheet = new Spreadsheet();
    
    // ========== HOJA 1: TARIFAS ==========
    $sheet1 = $spreadsheet->getActiveSheet();
    $sheet1->setTitle('PU (Cliente)');
    
    // Configurar página
    $sheet1->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_A4);
    
    // Establecer anchos de columna
    $sheet1->getColumnDimension('A')->setWidth(5);
    $sheet1->getColumnDimension('B')->setWidth(10);
    $sheet1->getColumnDimension('C')->setWidth(20);
    $sheet1->getColumnDimension('D')->setWidth(30);
    $sheet1->getColumnDimension('E')->setWidth(8);
    $sheet1->getColumnDimension('F')->setWidth(12);
    $sheet1->getColumnDimension('G')->setWidth(15);
    
    // Título
    $sheet1->mergeCells('A1:G1');
    $sheet1->setCellValue('A1', 'TARIFAS ALMACENAJE CLIENTE: ' . $cliente_info);
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    
    // Fecha
    $sheet1->setCellValue('A2', 'Fecha:');
    $sheet1->mergeCells('B2:G2');
    $sheet1->setCellValue('B2', date('d/m/Y H:i:s'));
    $sheet1->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    
    // Encabezados
    $headers = ['Nro.', 'Proceso', 'Descripción', 'Alcance', 'UDM', 'Frecuencia', 'P.U USD (Facturado)'];
    $col = 'A';
    $row = 4;
    
    foreach ($headers as $header) {
        $cell = $col . $row;
        $sheet1->setCellValue($cell, $header);
        $sheet1->getStyle($cell)
            ->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
        $sheet1->getStyle($cell)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
        $sheet1->getStyle($cell)
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet1->getStyle($cell)
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $col++;
    }
    
    // Filas de datos
    $row = 5;
    foreach ($procesos as $p) {
        $sheet1->setCellValue('A' . $row, $p['nro']);
        $sheet1->setCellValue('B' . $row, $p['proceso']);
        $sheet1->setCellValue('C' . $row, $p['descripcion']);
        $sheet1->setCellValue('D' . $row, $p['alcance']);
        $sheet1->setCellValue('E' . $row, $p['udm']);
        $sheet1->setCellValue('F' . $row, $p['frecuencia']);
        
        // Formato numérico para precio
        $valor = str_replace(',', '.', $p['valor']);
        $sheet1->setCellValue('G' . $row, $valor);
        $sheet1->getStyle('G' . $row)
            ->getNumberFormat()
            ->setFormatCode('#,##0.000');
        
        // Bordes para toda la fila
        foreach (range('A', 'G') as $col) {
            $sheet1->getStyle($col . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        // Centrar columnas específicas
        $sheet1->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('E' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('F' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        
        $row++;
    }
    
    // Notas
    $row++;
    $sheet1->mergeCells('A' . $row . ':G' . $row);
    $sheet1->setCellValue('A' . $row, 'Nota1.- Todos los precios incluyen impuestos de ley');
    $sheet1->getStyle('A' . $row)->getFont()->setItalic(true);
    
    $row++;
    $sheet1->mergeCells('A' . $row . ':G' . $row);
    $sheet1->setCellValue('A' . $row, 'Nota2.- Los precios están con punto (,) decimal');
    $sheet1->getStyle('A' . $row)->getFont()->setItalic(true);
    
    // Autoajustar altura de filas según contenido
    foreach (range(5, $row) as $rowNum) {
        $sheet1->getRowDimension($rowNum)->setRowHeight(-1);
    }
    
    // ========== HOJA 2: DETALLES COMPLETOS (P1 + P2) ==========
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Detalles P1');
    $sheet2->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
        ->setPaperSize(PageSetup::PAPERSIZE_A4);
    
    // Configurar columnas
    $sheet2->getColumnDimension('A')->setWidth(8);
    $sheet2->getColumnDimension('B')->setWidth(75);
    $sheet2->getColumnDimension('C')->setWidth(25);
    
    // Ajustar altura de la fila 1 para el logo
    $sheet2->getRowDimension(1)->setRowHeight(50);
    
    // Título con logo
    $sheet2->mergeCells('A1:B1');
    $sheet2->setCellValue('A1', 'SERVICIO DE ALMACENAJE Y MANIPULACIÓN');
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8DE06B');
    $sheet2->getStyle('A1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    
    // Buscar e insertar logo local
    $logoLocal = 'ransa.png';
    
    // Verificar si existe la imagen local
    if (file_exists($logoLocal)) {
        $drawing = new Drawing();
        $drawing->setName('Logo Ransa');
        $drawing->setDescription('Logo Ransa');
        $drawing->setPath($logoLocal);
        $drawing->setHeight(39);
        $drawing->setCoordinates('C1');
        $drawing->setWorksheet($sheet2);
        
        // Centrar el logo en la celda
        $sheet2->getStyle('C1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
    } else {
        // Si no existe el logo local, poner texto alternativo
        $sheet2->setCellValue('C1', 'LOGO RANSA');
        $sheet2->getStyle('C1')->getFont()->setBold(true)->setSize(12);
        $sheet2->getStyle('C1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet2->getStyle('C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8F4F8');
    }
    
    // Ajustar altura de la celda C1
    $sheet2->getStyle('C1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    $sheet2->setCellValue('A2', 'Fecha:');
    $sheet2->setCellValue('B2', date('d/m/Y'));
    $sheet2->setCellValue('A3', 'Almacen:');
    $sheet2->setCellValue('B3', $cliente_info);
    
    $row = 5;
    
    // Encabezado de sección
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, 'ALCANCE DEL SERVICIO - DESCRIPCIÓN');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $sheet2->setCellValue('A' . $row, 'Nuestros Servicios Incluyen:');
    $sheet2->getStyle('A' . $row)->getFont()->setItalic(true);
    $row += 2;
    
    // 1. CONTEXTO DEL SERVICIO
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '1. CONTEXTO DEL SERVICIO');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    // 1.1
    $sheet2->setCellValue('A' . $row, '1.1.');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'El servicio no considera mercadería peligroso (Inflamable, explosivas etc.) y Sustancias Controladas.');
    $row++;
    
    // 1.2
    $sheet2->setCellValue('A' . $row, '1.2.');
    $sheet2->mergeCells('B' . $row . ':C' . $row);
    $sheet2->setCellValue('B' . $row, 'El Estándar de almacenaje será SENASAG');
    $row++;
    
    // 1.3
    $sheet2->setCellValue('A' . $row, '1.3.');
    $sheet2->setCellValue('B' . $row, 'El tiempo del Servicio será en meses de:');
    $sheet2->setCellValue('C' . $row, '12');
    $sheet2->getStyle('C' . $row)->getFont()->setBold(true);
    $sheet2->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $row++;
    
    $sheet2->setCellValue('B' . $row, 'Si la necesidad fuera a menor tiempo de contrato, se revisará para aplicar tarifas Spot.');
    $sheet2->getStyle('B' . $row)->getFont()->setItalic(true);
    $sheet2->getStyle('B' . $row)->getAlignment()->setWrapText(true);
    $row++;
    
    // 1.4
    $sheet2->setCellValue('A' . $row, '1.4.');
    $sheet2->setCellValue('B' . $row, 'El valor de la mercadería utilizada para las tarifas Estándar es de:');
    $sheet2->setCellValue('C' . $row, 'Bs696.000,000 / $100.000,000');
    $sheet2->getStyle('C' . $row)->getFont()->setBold(true);
    $row++;
    
    // 2. DATOS DEL PROCESO RECEPCIÓN
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '2. DATOS DEL PROCESO RECEPCIÓN');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    // Items de recepción (2.1 - 2.16)
    $recepcion_items = [
        ['2.1.', 'Peso del Pallet estándar Mercosur:', '1 TN o su equivalente 1.000kg'],
        ['2.2.', 'Medidas del pallet estándar Mercosur:', '1m de ancho x 1,2m de fondo x 1,55m de altura productos (Sin Pallet)'],
        ['2.3.', 'Tamaño de la Caja master:', 'Tamaño permisible para estibaje, manipulación de 1 caja x Persona.'],
        ['2.4.', 'Peso de Caja master:', 'Igual o menor a 15kg'],
        ['2.5.', 'Peso de la Unidad:', 'Menor a 5kg'],
        ['2.6.', 'Sku´s por recepción - Sku´s por Camión:', 'Maximo de 10 Sku´s o 10 ítems diferentes en una recepción.'],
        ['2.7.', 'Rango de Volumen (Cantidad mes) Pallet:', 'Hasta 400 Pallet mes, Para activar economía de escala.'],
        ['2.8.', 'Rango de Volumen (Cantidad mes) Bultos:', 'Hasta 4.000 cajas master, Para activar economía de escala.'],
        ['2.9.', 'Rango de Volumen (Cantidad mes) Unidades:', 'Hasta 5.000 unidades, Para activar economía de escala.'],
        ['2.10.', 'Horarios de atención:', 'Los horarios son de 08:00 a 12:00 y 14:00 a 18:00 de Lunes a viernes y los días Sábados de 08:00 a 13:00.'],
        ['2.11.', 'RANSA enviara un informe de recepción al cliente con todas las novedades levantadas en la recepción de la mercaderia entre 24 a 48 horas posterior al evento realizado (Recepción).', ''],
        ['2.12.', 'El alcance incluye Nivel de inspeción 1: Inspección visual del pallet o de la mercaderia agranel y realizar una nuestra aleatoria por tabla militar, Si la mercaderia no cumple el standar el cliente tomara la disposición final de la mercaderia y la responsabilidad faltantes de aceptar la mercaderia para que pase al proceso de almacenamiento.', ''],
        ['2.13.', 'El alcance NO incluye Nivel de inspeción 2: Abrir cajas y revisar el 100%, Inspeción 3: Revisión al 100% a nivel Unitario.', ''],
        ['2.14.', 'Las tarifas de logisticas de reversa o Inversa se deberán cotizar con datos que proporcione el cliente y el nivel de complejidad.', ''],
        ['2.15.', 'Todo pallet que el cliente quiera ingresar a los almacenes de Ransa deben contar con tratamiento térmico según Norma la NIMF 15, en caso que no cuente con ello no podrá ingresar.', ''],
        ['2.16.', 'Las tarifas de descarga se utilizará por cada UDM Logística (Pallet y Caja master o Bulto).', '']
    ];
    
    foreach ($recepcion_items as $item) {
        $sheet2->setCellValue('A' . $row, $item[0]);
        $sheet2->setCellValue('B' . $row, $item[1]);
        if (!empty($item[2])) {
            $sheet2->setCellValue('C' . $row, $item[2]);
            $sheet2->getStyle('C' . $row)->getFont()->setItalic(true);
        }
        
        // Aplicar bordes
        foreach (['A', 'B', 'C'] as $col) {
            $sheet2->getStyle($col . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 3. DATOS DEL PROCESO ALMACENAJE
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '3. DATOS DEL PROCESO ALMACENAJE');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $almacenaje_items = [
        '3.1.' => 'Las tarifas de almacenaje aplican para los negocios de: Retail, Consumo masivo y otros servicios bajo el estándar SENASAG.',
        '3.2.' => 'Los Servicios Bajo Estándar AGEMED, Temperatura controlada, Refrigerados, Congelados, Petróleo y Gas, Productos químicos, Sustancias controladas no aplican a la propuesta Estándar de servicios Logísticos RANSA.',
        '3.3.' => 'Las tarifas de almacenaje son tarifas mensualizadas, donde el máximo del mes en ocupación se usa para el cobro en la prefectura.',
        '3.4.' => 'Cada Pallet de almacenaje podrá soportar hasta 4 Sku´s o 4 ítems diferentes por cada Posición. (No excluyente la utilización del espacio).',
        '3.5.' => 'La tarifa No incluye posición estantería para unitarios.',
        '3.6.' => 'La tarifa SI incluye SEGUROS de TODO RIESGOS y RESPONSABILIDAD CIVIL.',
        '3.7.' => 'Ransa incluye un Sistema WMS para el control de las Operaciones.',
        '3.8.' => 'Ransa por estándar almacena productos en pallet Mercosur (1mx1,2mx1,55m) y con un Peso igual o menor a 1tn o 1.000kg.',
        '3.9.' => 'El servicio de almacenaje incluye control de plaga.',
        '3.10.' => 'Las tarifa de almacenaje incluye los procesos de Slotting para mejorar la productividad de los despachos (Picking).',
        '3.11.' => 'Ransa realiza un control de contaminación cruzada para evitar contaminación de los diferentes productos almacenados en Bodega.',
        '3.12.' => 'Por su standar de Calidad, Ransa exigirá a sus clientes que todo pallet que llegue a su centro de distribución cumpla los siguientes criterios: Pallet con tratamiento termico según Norma la NIMF 15 y su respectiva fumigación.'
    ];
    
    foreach ($almacenaje_items as $item => $desc) {
        $sheet2->setCellValue('A' . $row, $item);
        $sheet2->mergeCells('B' . $row . ':C' . $row);
        $sheet2->setCellValue('B' . $row, $desc);
        
        // Aplicar bordes
        foreach (['A', 'B', 'C'] as $col) {
            $sheet2->getStyle($col . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 4. DATOS DEL PROCESO DESPACHO
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '4. DATOS DEL PROCESO DESPACHO');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $despacho_items = [
        '4.1.' => 'Las tarifas aplicarán para cada UDM de preparación de pedidos.',
        '4.2.' => 'Las tarifas para carga se utilizará por cada UDM Logística (Pallet, Caja master o bulto).',
        '4.3.' => 'Las tarifas despacho incluye los procesos de picking primario (Consolidado / Estrategia establecidad por RANSA) y picking secundario (Por pedido).',
        '4.4.' => 'Las operaciones de despacho se realizan con equipos eléctricos para evitar contaminación a la mercadería de nuestro cliente.',
        '4.5.' => 'Las tarifas de despacho NO incluye inspección o validación unitaria antes de despacho.',
        '4.6.' => 'Ransa tiene 24 horas para la entrega de los pedidos en muelle de carga según los volúmenes establecidos para las tarifas estándar.'
    ];
    
    foreach ($despacho_items as $item => $desc) {
        $sheet2->setCellValue('A' . $row, $item);
        $sheet2->mergeCells('B' . $row . ':C' . $row);
        $sheet2->setCellValue('B' . $row, $desc);
        
        // Aplicar bordes
        foreach (['A', 'B', 'C'] as $col) {
            $sheet2->getStyle($col . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 5. DATOS DEL PROCESO INVENTARIO DE CONTROL
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '5. DATOS DEL PROCESO INVENTARIO DE CONTROL');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $inventario_items = [
        '5.1.' => 'El servicio ofertado por RANSA considera (1) un inventario wall to wall al mes',
        '5.2.' => 'Las diferencias negativas se netean con las diferencias positivas y la diferencia real se ajusta a precio FABRICA de la mercadería a ser descontada por RANSA.',
        '5.3.' => 'Se recomienda para los inventarios mensuales el paralizar las operaciones (No se recepcionan y no se despacha) y 1 día antes se disminuye la capacidad de atención para adecuar el stock para el inventario.',
        '5.4.' => 'El cliente deberá realizar su ajuste a su sistema para empezar el siguiente mes con un inventario saneado entre ambas partes.',
        '5.5.' => 'La recuperación de productos dañados no está considerado dentro del alcance del servicio.'
    ];
    
    foreach ($inventario_items as $item => $desc) {
        $sheet2->setCellValue('A' . $row, $item);
        $sheet2->mergeCells('B' . $row . ':C' . $row);
        $sheet2->setCellValue('B' . $row, $desc);
        
        // Aplicar bordes
        foreach (['A', 'B', 'C'] as $col) {
            $sheet2->getStyle($col . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 6. DATOS DEL PROCESO REPORTES
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '6. DATOS DEL PROCESO REPORTES');
    $sheet2->getStyle('A' . $row)->getFont()->setBold(true)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    $row++;
    
    $reportes_items = [
        '6.1.' => 'Ransa tiene una aplicación web RANSA 360, En donde el cliente podrá observar un DASHBOARD de control de inventario, recepción y despacho.',
        '6.2.' => 'Los datos de las Operaciones con RANSA estará a disposición del cliente en tiempo real con una actualización cada 1 hora de intervalo.',
        '6.3.' => 'Los procesos de transferencia de datos de recepción y solicitud de Pedidos se realizará por medio de plantillas estándar proporcionadas por RANSA.',
        '6.4.' => 'Será posible trabajar con el cliente procesos de integración de sistemas cliente y RANSA, Siempre que no aplique cambios en la estructura de RANSA a costo Cero.'
    ];
    
    foreach ($reportes_items as $item => $desc) {
        $sheet2->setCellValue('A' . $row, $item);
        $sheet2->mergeCells('B' . $row . ':C' . $row);
        $sheet2->setCellValue('B' . $row, $desc);
        
        // Aplicar bordes
        foreach (['A', 'B', 'C'] as $col) {
            $sheet2->getStyle($col . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    // Resumen final
    $row += 2;
    $sheet2->mergeCells('A' . $row . ':C' . $row);
    $sheet2->setCellValue('A' . $row, '📋 Resumen: Este documento contiene las 46 especificaciones completas del servicio de almacenaje, organizadas en 6 categorías principales.');
    $sheet2->getStyle('A' . $row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF009A3F');
    $sheet2->getStyle('A' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Ajustar altura de filas para texto largo
    foreach (range(5, $row) as $rowNum) {
        $sheet2->getRowDimension($rowNum)->setRowHeight(-1);
        $sheet2->getStyle('B' . $rowNum)->getAlignment()->setWrapText(true);
        $sheet2->getStyle('C' . $rowNum)->getAlignment()->setWrapText(true);
    }
    
    // ========== ENVIAR AL NAVEGADOR ==========
    $writer = new Xlsx($spreadsheet);
    
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Tarifas_Almacenaje_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $cliente_info) . '_' . date('Ymd_His') . '.xlsx"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    header('Expires: 0');
    
    $writer->save('php://output');
    exit;
}

// ==================== VISTA IMPRESIÓN COMPLETA (3 PÁGINAS) ====================
function generarVistaImpresionCompleta($registro, $procesos, $cliente_info) {
    $fecha_completa = date('d/m/Y H:i:s');
    $fecha_hora2 = date('H:i:s');
    $fecha_simple = date('d/m/Y');
    
    ?>
    <!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte Completo - <?php echo htmlspecialchars($cliente_info); ?></title>
    <style>
        /* ESTILOS PARA IMPRESIÓN - MODIFICADO A A4 VERTICAL */
        @media print {
            @page {
                size: A4 portrait;
                margin: 0.8cm;
            }
            .pagina2, .pagina3 {
                page-break-before: always;
            }
            .no-print { display: none !important; }
            body { 
                font-family: 'Calibri', Arial, sans-serif;
                font-size: 8.5pt;
                line-height: 1.15;
                color: #000;
            }
            .contenedor-pagina {
                min-height: 25.7cm;
                position: relative;
                width: 100%;
                overflow: hidden;
            }
        }
        
        /* ESTILOS GENERALES */
        body {
            font-family: 'Calibri', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.3;
            color: #333;
            margin: 0;
            padding: 10px;
            background: #f5f5f5;
        }
        
        .contenedor-pagina {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* BOTONES */
        .botones-contenedor {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            border-radius: 8px;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 11pt;
            font-weight: bold;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-excel {
            background: linear-gradient(135deg, #217346, #1e9e6a);
            color: white;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #c0392b, #e74c3c);
            color: white;
        }
        
        .btn-imprimir {
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
        }
        
        .btn-cerrar {
            background: linear-gradient(135deg, #7f8c8d, #95a5a6);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        /* TABLA PRINCIPAL */
        .tabla-principal {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8pt;
            table-layout: fixed;
        }
        
        .tabla-principal th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            font-weight: bold;
            padding: 6px 4px;
            text-align: center;
            border: 1px solid #1a252f;
            vertical-align: middle;
            font-size: 8.5pt;
        }
        
        .tabla-principal td {
            padding: 5px 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 8pt;
            word-wrap: break-word;
        }
        
        /* ANCHOS DE COLUMNAS */
        .tabla-principal th:nth-child(1),
        .tabla-principal td:nth-child(1) {
            width: 5% !important;
        }
        
        .tabla-principal th:nth-child(2),
        .tabla-principal td:nth-child(2) {
            width: 10% !important;
        }
        
        .tabla-principal th:nth-child(3),
        .tabla-principal td:nth-child(3) {
            width: 18% !important;
        }
        
        .tabla-principal th:nth-child(4),
        .tabla-principal td:nth-child(4) {
            width: 30% !important;
        }
        
        .tabla-principal th:nth-child(5),
        .tabla-principal td:nth-child(5) {
            width: 8% !important;
        }
        
        .tabla-principal th:nth-child(6),
        .tabla-principal td:nth-child(6) {
            width: 10% !important;
        }
        
        .tabla-principal th:nth-child(7),
        .tabla-principal td:nth-child(7) {
            width: 12% !important;
        }
        
        /* COLORES POR PROCESO */
        .recepcion { background-color: #e8f6f3 !important; }
        .almacenaje { background-color: #fef9e7 !important; }
        .despacho { background-color: #f4ecf7 !important; }
        .otros { background-color: #fdedec !important; }
        
        /* ENCABEZADO */
        .encabezado-titulo {
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 2px solid #3498db;
        }
        
        /* TABLA DETALLES */
        .tabla-detalles {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 7.5pt;
            table-layout: fixed;
        }
        
        .tabla-detalles th {
            background: linear-gradient(135deg, #5B9BD5, #4472C4);
            color: white;
            font-weight: bold;
            padding: 6px 4px;
            text-align: left;
            border: 1px solid #2e5aa7;
            font-size: 8pt;
        }
        
        .tabla-detalles td {
            padding: 4px;
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 7.5pt;
        }
        
        .seccion-titulo {
            background: linear-gradient(135deg, #5B9BD5, #4472C4);
            color: white;
            font-weight: bold;
            padding: 6px;
            margin: 12px 0 4px 0;
            border-radius: 3px;
            font-size: 9pt;
        }
        
        .numero-item {
            width: 35px !important;
            text-align: center;
            font-weight: bold;
            background: #f1f8ff;
            border-right: 2px solid #5B9BD5;
        }
        
        /* FOOTER */
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 7pt;
            color: #7f8c8d;
            text-align: center;
            position: absolute;
            bottom: 10px;
            width: calc(100% - 30px);
        }
        
        .notas {
            margin-top: 15px;
            padding: 8px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            font-size: 7.5pt;
        }
    </style>
</head>
<body>

    <!-- BOTONES (solo en pantalla) -->
    <div class="no-print botones-contenedor">
        <button class="btn btn-excel" onclick="descargarExcel()">
            📊 Descargar Excel
        </button>
        <button class="btn btn-pdf" onclick="window.print()">
            📄 Generar PDF/Imprimir
        </button>
        <button class="btn btn-cerrar" onclick="window.close()">
            ❌ Cerrar
        </button>
    </div>
    
    <!-- === PÁGINA 1: TABLA DE TARIFAS === -->
    <div class="contenedor-pagina pagina1">
        <div class="encabezado-titulo">TARIFAS ALMACENAJE CLIENTE:</div>
        
        <div style="text-align: right; margin-bottom: 15px;">
            <strong>Fecha:</strong> <?php echo $fecha_completa; ?> <?php echo $fecha_hora2; ?>
        </div>
        
        <div style="margin-bottom: 15px; padding: 10px; background: #e8f4f8; border-radius: 5px;">
            <strong>Almacen:</strong> <?php echo htmlspecialchars($cliente_info); ?>
        </div>
        
        <table class="tabla-principal">
            <thead>
                <tr>
                    <th width="5%">Nro.</th>
                    <th width="10%">Proceso</th>
                    <th width="20%">Descripción</th>
                    <th width="30%">Alcance</th>
                    <th width="8%">UDM</th>
                    <th width="10%">Frecuencia</th>
                    <th width="12%">P.U USD (Facturado)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($procesos as $p): 
                    $clase = '';
                    // VERSIÓN PHP 7.4 - sin match()
                    switch($p['proceso']) {
                        case 'Recepción':
                            $clase = 'recepcion';
                            break;
                        case 'Almacenaje':
                            $clase = 'almacenaje';
                            break;
                        case 'Despacho':
                            $clase = 'despacho';
                            break;
                        case 'Otros':
                            $clase = 'otros';
                            break;
                        default:
                            $clase = '';
                            break;
                    }
                ?>
                <tr class="<?php echo $clase; ?>">
                    <td align="center"><strong><?php echo $p['nro']; ?></strong></td>
                    <td><strong><?php echo $p['proceso']; ?></strong></td>
                    <td><?php echo $p['descripcion']; ?></td>
                    <td><?php echo $p['alcance']; ?></td>
                    <td align="center"><?php echo $p['udm']; ?></td>
                    <td align="center"><?php echo $p['frecuencia']; ?></td>
                    <td align="right" style="font-weight: bold; color: #27ae60;">$ <?php echo $p['valor']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="notas">
            <p><strong>📝 Nota1.-</strong> Todos los precios incluyen impuestos de ley</p>
            <p><strong>💰 Nota2.-</strong> Los precios están con punto (,) decimal</p>
        </div>
        
        <div class="footer">
            Página 1 de 3 | Sistema de Cotización de Tarifas
        </div>
    </div>
    
    <!-- === PÁGINA 2: DETALLES (PARTE 1) === -->
    <div class="contenedor-pagina pagina2">
        <div class="encabezado-titulo" style="text-align: center; font-size: 16pt;">
            SERVICIO DE ALMACENAJE Y MANIPULACIÓN
        </div>
        
        <div style="text-align: right; margin-bottom: 15px;">
            <strong>Fecha:</strong> <?php echo $fecha_simple; ?>
        </div>
        
        <div style="margin-bottom: 20px; padding: 10px; background: #e8f4f8; border-radius: 5px;">
            <strong>Cliente:</strong> <?php echo htmlspecialchars($cliente_info); ?>
        </div>
        
        <table class="tabla-detalles">
            <thead>
                <tr>
                    <th width="5%">ITEM</th>
                    <th width="95%">ALCANCE DEL SERVICIO - DESCRIPCIÓN</th>
                </tr>
            </thead>
        </table>
        
        <div style="margin: 10px 0 20px 5px; font-style: italic;">
            Nuestros Servicios Incluyen:
        </div>
        
        <!-- 1. CONTEXTO DEL SERVICIO -->
        <div class="seccion-titulo">1. CONTEXTO DEL SERVICIO</div>
        <table class="tabla-detalles">
            <tr>
                <td class="numero-item">1.1.</td>
                <td>El servicio no considera mercadería peligroso (Inflamable, explosivas etc.) y Sustancias Controladas.</td>
            </tr>
            <tr>
                <td class="numero-item">1.2.</td>
                <td>El Estándar de almacenaje será SENASAG</td>
            </tr>
            <tr>
                <td class="numero-item">1.3.</td>
                <td>
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="width: 60%; border: none;">El tiempo del Servicio será en meses de:</td>
                            <td style="width: 10%; border: none; text-align: center; font-weight: bold; background: #d4edda;">12</td>
                            <td style="width: 30%; border: none;">Si la necesidad fuera a menor tiempo de contrato, se revisará para aplicar tarifas Spot.</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr>
                <td class="numero-item">1.4.</td>
                <td>
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="width: 70%; border: none;">El valor de la mercadería utilizada para las tarifas Estándar es de:</td>
                            <td style="width: 15%; border: none; text-align: right; font-weight: bold; color: #27ae60;">Bs696.000,000</td>
                            <td style="width: 15%; border: none; text-align: right; font-weight: bold; color: #e74c3c;">$100.000,000</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        
        <!-- 2. DATOS DEL PROCESO RECEPCIÓN -->
        <div class="seccion-titulo">2. DATOS DEL PROCESO RECEPCIÓN</div>
        <table class="tabla-detalles">
            <?php
            $recepcion_items = [
                '2.1.' => ['Peso del Pallet estándar Mercosur:', '1 TN o su equivalente 1.000kg'],
                '2.2.' => ['Medidas del pallet estándar Mercosur:', '1m de ancho x 1,2m de fondo x 1,55m de altura productos (Sin Pallet)'],
                '2.3.' => ['Tamaño de la Caja master:', 'Tamaño permisible para estibaje, manipulación de 1 caja x Persona.'],
                '2.4.' => ['Peso de Caja master:', 'Igual o menor a 15kg'],
                '2.5.' => ['Peso de la Unidad:', 'Menor a 5kg'],
                '2.6.' => ['Sku´s por recepción - Sku´s por Camión:', 'Maximo de 10 Sku´s o 10 ítems diferentes en una recepción.'],
                '2.7.' => ['Rango de Volumen (Cantidad mes) Pallet:', 'Hasta 400 Pallet mes, Para activar economía de escala.'],
                '2.8.' => ['Rango de Volumen (Cantidad mes) Bultos:', 'Hasta 4.000 cajas master, Para activar economía de escala.'],
                '2.9.' => ['Rango de Volumen (Cantidad mes) Unidades:', 'Hasta 5.000 unidades, Para activar economía de escala.'],
                '2.10.' => ['Horarios de atención:', 'Los horarios son de 08:00 a 12:00 y 14:00 a 18:00 de Lunes a viernes y los días Sábados de 08:00 a 13:00.'],
                '2.11.' => ['RANSA enviara un informe de recepción al cliente con todas las novedades levantadas en la recepción de la mercaderia entre 24 a 48 horas posterior al evento realizado (Recepción).', ''],
                '2.12.' => ['El alcance incluye Nivel de inspeción 1: Inspección visual del pallet o de la mercaderia agranel y realizar una nuestra aleatoria por tabla militar, Si la mercaderia no cumple el standar el cliente tomara la disposición final de la mercaderia y la responsabilidad faltantes de aceptar la mercaderia para que pase al proceso de almacenamiento.', ''],
                '2.13.' => ['El alcance NO incluye Nivel de inspeción 2: Abrir cajas y revisar el 100%, Inspeción 3: Revisión al 100% a nivel Unitario.', ''],
                '2.14.' => ['Las tarifas de logisticas de reversa o Inversa se deberán cotizar con datos que proporcione el cliente y el nivel de complejidad.', ''],
                '2.15.' => ['Todo pallet que el cliente quiera ingresar a los almacenes de Ransa deben contar con tratamiento térmico según Norma la NIMF 15, en caso que no cuente con ello no podrá ingresar.', ''],
                '2.16.' => ['Las tarifas de descarga se utilizará por cada UDM Logística (Pallet y Caja master o Bulto).', '']
            ];
            
            foreach ($recepcion_items as $item => $data):
            ?>
            <tr>
                <td class="numero-item"><?php echo $item; ?></td>
                <td>
                    <?php echo $data[0]; ?>
                    <?php if (!empty($data[1])): ?>
                    <div style="margin-left: 20px; font-style: italic; color: #555;">
                        → <?php echo $data[1]; ?>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <!-- 3. DATOS DEL PROCESO ALMACENAJE -->
        <div class="seccion-titulo">3. DATOS DEL PROCESO ALMACENAJE</div>
        <table class="tabla-detalles">
            <?php
            $almacenaje_items = [
                '3.1.' => 'Las tarifas de almacenaje aplican para los negocios de: Retail, Consumo masivo y otros servicios bajo el estándar SENASAG.',
                '3.2.' => 'Los Servicios Bajo Estándar AGEMED, Temperatura controlada, Refrigerados, Congelados, Petróleo y Gas, Productos químicos, Sustancias controladas no aplican a la propuesta Estándar de servicios Logísticos RANSA.',
                '3.3.' => 'Las tarifas de almacenaje son tarifas mensualizadas, donde el máximo del mes en ocupación se usa para el cobro en la prefectura.',
                '3.4.' => 'Cada Pallet de almacenaje podrá soportar hasta 4 Sku´s o 4 ítems diferentes por cada Posición. (No excluyente la utilización del espacio).',
                '3.5.' => 'La tarifa No incluye posición estantería para unitarios.',
                '3.6.' => 'La tarifa SI incluye SEGUROS de TODO RIESGOS y RESPONSABILIDAD CIVIL.',
                '3.7.' => 'Ransa incluye un Sistema WMS para el control de las Operaciones.',
                '3.8.' => 'Ransa por estándar almacena productos en pallet Mercosur (1mx1,2mx1,55m) y con un Peso igual o menor a 1tn o 1.000kg.',
                '3.9.' => 'El servicio de almacenaje incluye control de plaga.',
                '3.10.' => 'Las tarifa de almacenaje incluye los procesos de Slotting para mejorar la productividad de los despachos (Picking).',
                '3.11.' => 'Ransa realiza un control de contaminación cruzada para evitar contaminación de los diferentes productos almacenados en Bodega.',
                '3.12.' => 'Por su standar de Calidad, Ransa exigirá a sus clientes que todo pallet que llegue a su centro de distribución cumpla los siguientes criterios: Pallet con tratamiento termico según Norma la NIMF 15 y su respectiva fumigación.'
            ];
            
            foreach ($almacenaje_items as $item => $desc):
            ?>
            <tr>
                <td class="numero-item"><?php echo $item; ?></td>
                <td><?php echo $desc; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="footer">
            Página 2 de 3 | Continúa...
        </div>
    </div>
    
    <!-- === PÁGINA 3: DETALLES (PARTE 2) === -->
    <div class="contenedor-pagina pagina3">
        <div class="encabezado-titulo" style="text-align: center; font-size: 16pt;">
            SERVICIO DE ALMACENAJE Y MANIPULACIÓN (CONTINUACIÓN)
        </div>
        
        <!-- 4. DATOS DEL PROCESO DESPACHO -->
        <div class="seccion-titulo">4. DATOS DEL PROCESO DESPACHO</div>
        <table class="tabla-detalles">
            <?php
            $despacho_items = [
                '4.1.' => 'Las tarifas aplicarán para cada UDM de preparación de pedidos.',
                '4.2.' => 'Las tarifas para carga se utilizará por cada UDM Logística (Pallet, Caja master o bulto).',
                '4.3.' => 'Las tarifas despacho incluye los procesos de picking primario (Consolidado / Estrategia establecidad por RANSA) y picking secundario (Por pedido).',
                '4.4.' => 'Las operaciones de despacho se realizan con equipos eléctricos para evitar contaminación a la mercadería de nuestro cliente.',
                '4.5.' => 'Las tarifas de despacho NO incluye inspección o validación unitaria antes de despacho.',
                '4.6.' => 'Ransa tiene 24 horas para la entrega de los pedidos en muelle de carga según los volúmenes establecidos para las tarifas estándar.'
            ];
            
            foreach ($despacho_items as $item => $desc):
            ?>
            <tr>
                <td class="numero-item"><?php echo $item; ?></td>
                <td><?php echo $desc; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <!-- 5. DATOS DEL PROCESO INVENTARIO DE CONTROL -->
        <div class="seccion-titulo">5. DATOS DEL PROCESO INVENTARIO DE CONTROL</div>
        <table class="tabla-detalles">
            <?php
            $inventario_items = [
                '5.1.' => 'El servicio ofertado por RANSA considera (1) un inventario wall to wall al mes',
                '5.2.' => 'Las diferencias negativas se netean con las diferencias positivas y la diferencia real se ajusta a precio FABRICA de la mercadería a ser descontada por RANSA.',
                '5.3.' => 'Se recomienda para los inventarios mensuales el paralizar las operaciones (No se recepcionan y no se despacha) y 1 día antes se disminuye la capacidad de atención para adecuar el stock para el inventario.',
                '5.4.' => 'El cliente deberá realizar su ajuste a su sistema para empezar el siguiente mes con un inventario saneado entre ambas partes.',
                '5.5.' => 'La recuperación de productos dañados no está considerado dentro del alcance del servicio.'
            ];
            
            foreach ($inventario_items as $item => $desc):
            ?>
            <tr>
                <td class="numero-item"><?php echo $item; ?></td>
                <td><?php echo $desc; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <!-- 6. DATOS DEL PROCESO REPORTES -->
        <div class="seccion-titulo">6. DATOS DEL PROCESO REPORTES</div>
        <table class="tabla-detalles">
            <?php
            $reportes_items = [
                '6.1.' => 'Ransa tiene una aplicación web RANSA 360, En donde el cliente podrá observar un DASHBOARD de control de inventario, recepción y despacho.',
                '6.2.' => 'Los datos de las Operaciones con RANSA estará a disposición del cliente en tiempo real con una actualización cada 1 hora de intervalo.',
                '6.3.' => 'Los procesos de transferencia de datos de recepción y solicitud de Pedidos se realizará por medio de plantillas estándar proporcionadas por RANSA.',
                '6.4.' => 'Será posible trabajar con el cliente procesos de integración de sistemas cliente y RANSA, Siempre que no aplique cambios en la estructura de RANSA a costo Cero.'
            ];
            
            foreach ($reportes_items as $item => $desc):
            ?>
            <tr>
                <td class="numero-item"><?php echo $item; ?></td>
                <td><?php echo $desc; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; border-left: 4px solid #28a745;">
            <strong>📋 Resumen:</strong> Este documento contiene las 46 especificaciones completas del servicio de almacenaje, organizadas en 6 categorías principales.
        </div>
        
        <div class="footer">
            Página 3 de 3 | Documento completo generado el <?php echo $fecha_completa; ?> | © <?php echo date('Y'); ?> RANSA
        </div>
    </div>
    
    <script>
    function descargarExcel() {
        const id = <?php echo $registro['id']; ?>;
        
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
        idInput.value = id;
        
        const formatoInput = document.createElement('input');
        formatoInput.type = 'hidden';
        formatoInput.name = 'formato';
        formatoInput.value = 'excel';
        
        form.appendChild(accionInput);
        form.appendChild(idInput);
        form.appendChild(formatoInput);
        
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }
    
    // Auto-imprimir si se desea
    // window.onload = function() {
    //     setTimeout(() => {
    //         window.print();
    //     }, 1000);
    // }
    </script>
    
    </body>
    </html>
    <?php
    exit;
}
?>