<?php
// generar_reporte.php - VERSIÓN COMPLETA CON TODOS LOS ITEMS
session_start();
require_once 'conexion.php';
require_once 'vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $id = $_POST['id'] ?? 0;
    $formato = $_POST['formato'] ?? 'excel';
    
    if ($id > 0) {
        try {
            // Buscar registro por ID
            $stmt = $pdo->prepare("SELECT * FROM externos.CotizadorTarifas WHERE id = ?");
            $stmt->execute([$id]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($registro) {
                // Procesos mapeados
                $procesos = [
                    ['nro' => 1, 'proceso' => 'Recepción', 'descripcion' => 'Descarga Pallet', 'alcance' => 'Operador + Montacarga', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' descarga_pall'] ?? '0,00'],
                    ['nro' => 2, 'proceso' => 'Recepción', 'descripcion' => 'Descarga Caja/Bulto', 'alcance' => 'Manual x caja / bulto', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' estibaje_cajas'] ?? '0,00'],
                    ['nro' => 3, 'proceso' => 'Recepción', 'descripcion' => 'IN Pallet', 'alcance' => 'Verificación, Reporte, Putaway', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' pall_in'] ?? '0,00'],
                    ['nro' => 4, 'proceso' => 'Recepción', 'descripcion' => 'IN Caja/Bulto', 'alcance' => 'Verificación, Reporte, Putaway', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_cajas_recepcion'] ?? '0,00'],
                    ['nro' => 5, 'proceso' => 'Recepción', 'descripcion' => 'IN Unidades', 'alcance' => 'Verificación, Reporte, Putaway', 'udm' => 'Unidad', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_und_recepcion'] ?? '0,00'],
                    ['nro' => 6, 'proceso' => 'Almacenaje', 'descripcion' => 'Almacenaje m2', 'alcance' => 'Almacenamiento mensual por m2 de ocupación (Incluye Productos + Pasillo)', 'udm' => 'm2', 'frecuencia' => 'Mensual', 'valor' => $registro[' m2_almacen'] ?? '0,00'],
                    ['nro' => 7, 'proceso' => 'Almacenaje', 'descripcion' => 'Almacenaje Pall', 'alcance' => 'Almacenamiento mensual por pallet posicion/rack para pallet mercosur (1m x 1,2m x 1,55m)', 'udm' => 'Pallet', 'frecuencia' => 'Por Evento', 'valor' => $registro[' almacen_pos'] ?? '0,00'],
                    ['nro' => 8, 'proceso' => 'Despacho', 'descripcion' => 'OUT Pallet', 'alcance' => 'Picking a nivel pallet puesto en dock de carga', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' pall_out'] ?? '0,00'],
                    ['nro' => 9, 'proceso' => 'Despacho', 'descripcion' => 'OUT Caja/Bulto', 'alcance' => 'Picking a nivel Caja/Bulto puesto en dock de carga', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_cajas_despacho'] ?? '0,00'],
                    ['nro' => 10, 'proceso' => 'Despacho', 'descripcion' => 'OUT Unidades', 'alcance' => 'Picking a nivel Unidades puesto en dock de carga', 'udm' => 'Unidad', 'frecuencia' => 'Por evento', 'valor' => $registro[' operacion_und_despacho'] ?? '0,00'],
                    ['nro' => 11, 'proceso' => 'Despacho', 'descripcion' => 'Carguío Pallet', 'alcance' => 'Operador Montacarga', 'udm' => 'Pallet', 'frecuencia' => 'Por evento', 'valor' => $registro[' carga_pall'] ?? '0,00'],
                    ['nro' => 12, 'proceso' => 'Despacho', 'descripcion' => 'Carguío Caja/Bulto', 'alcance' => 'Manual x caja / bulto', 'udm' => 'Caja/Bulto', 'frecuencia' => 'Por evento', 'valor' => $registro[' estibaje_cajas'] ?? '0,00'],
                    ['nro' => 13, 'proceso' => 'Otros', 'descripcion' => 'TAKE or PAY', 'alcance' => 'Facturación mínima para el servicio logístico', 'udm' => 'Global', 'frecuencia' => 'Mensual', 'valor' => '400,00']
                ];
                
                // Cliente info
                $cliente_info = ($registro[' almacen'] ?? 'Cliente') . ' - ' . ($registro[' ciudad'] ?? 'Ciudad');
                
                // Generar según formato
                switch($formato) {
                    case 'excel':
                        generarExcelCompleto($registro, $procesos, $cliente_info);
                        break;
                    case 'pdf':
                    case 'imprimir':
                    default:
                        generarVistaImpresionCompleta($registro, $procesos, $cliente_info);
                }
                
            } else {
                die('No se encontró el registro');
            }
            
        } catch(PDOException $e) {
            die('Error en la base de datos: ' . $e->getMessage());
        }
    } else {
        die('ID no válido');
    }
    exit;
}

// ==================== EXCEL COMPLETO CON PHPSPREADSHEET ====================
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

// ==================== EXCEL COMPLETO CON PHPSPREADSHEET (VERSIÓN COMPLETA) ====================

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
    foreach ($headers as $header) {
        $cell = $col . '4';
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
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED2);
        
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
        
        // Color de fondo según proceso
        $color = match($p['proceso']) {
            'Recepción' => 'FFFFFFFF',
            'Almacenaje' => 'FFFFFFFF',
            'Despacho' => 'FFFFFFFF',
            'Otros' => 'FFFFFFFF',
            default => 'FFFFFFFF'
        };
        
        foreach (range('A', 'G') as $col) {
            $sheet1->getStyle($col . $row)
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($color);
        }
        
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
    
    // Configurar columnas como en Detalles P2 (más ordenadas)
    $sheet2->getColumnDimension('A')->setWidth(8);
    $sheet2->getColumnDimension('B')->setWidth(75); // Reducido para dejar espacio para el logo
    $sheet2->getColumnDimension('C')->setWidth(25);
    
    // Ajustar altura de la fila 1 para el logo
    $sheet2->getRowDimension(1)->setRowHeight(50); // Altura suficiente para el logo
    
    // Título con logo
    $sheet2->mergeCells('A1:B1'); // Ahora solo fusionamos A y B
    $sheet2->setCellValue('A1', 'SERVICIO DE ALMACENAJE Y MANIPULACIÓN');
    $sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));
    $sheet2->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF8DE06B');
    $sheet2->getStyle('A1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
    
    // Buscar e insertar logo local
    $logoLocal = 'ransa.png'; // Nombre del archivo local
    
    // Verificar si existe la imagen local
    if (file_exists($logoLocal)) {
        $drawing = new Drawing();
        $drawing->setName('Logo Ransa');
        $drawing->setDescription('Logo Ransa');
        $drawing->setPath($logoLocal);
        $drawing->setHeight(39); // Altura del logo
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
    
    // Ajustar altura de la celda C1 para que coincida con el título
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
    $sheet2->setCellValue('C' . $row, 'Bs696.000,00 / $100.000,00');
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
            $sheet2->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 3. DATOS DEL PROCESO ALMACENAJE (CONTENIDO DE DETALLES P2)
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
            $sheet2->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 4. DATOS DEL PROCESO DESPACHO (CONTENIDO DE DETALLES P2)
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
            $sheet2->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 5. DATOS DEL PROCESO INVENTARIO DE CONTROL (CONTENIDO DE DETALLES P2)
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
            $sheet2->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        }
        
        $row++;
    }
    
    $row++;
    
    // 6. DATOS DEL PROCESO REPORTES (CONTENIDO DE DETALLES P2)
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
            $sheet2->getStyle($col . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
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
        // Habilitar wrap text para todas las celdas de la columna B y C
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
                font-size: 8.5pt; /* Reducido ligeramente para mejor ajuste */
                line-height: 1.15;
                color: #000;
            }
            .contenedor-pagina {
                min-height: 25.7cm; /* Altura aproximada A4 menos márgenes */
                position: relative;
                width: 100%;
                overflow: hidden;
            }
        }
        
        /* ESTILOS GENERALES - MANTENIDOS */
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
            padding: 15px; /* Reducido para mejor ajuste */
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* BOTONES - MANTENIDOS */
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
        
        /* TABLA PRINCIPAL - AJUSTADA PARA A4 VERTICAL */
        .tabla-principal {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 8pt; /* Reducido para mejor ajuste */
            table-layout: fixed; /* Para controlar mejor el ancho */
        }
        
        .tabla-principal th {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            font-weight: bold;
            padding: 6px 4px; /* Reducido */
            text-align: center;
            border: 1px solid #1a252f;
            vertical-align: middle;
            font-size: 8.5pt;
        }
        
        .tabla-principal td {
            padding: 5px 4px; /* Reducido */
            border: 1px solid #ddd;
            vertical-align: top;
            font-size: 8pt;
            word-wrap: break-word; /* Para que el texto se ajuste */
        }
        
        /* NUEVOS ANCHOS DE COLUMNAS PARA A4 VERTICAL */
        .tabla-principal th:nth-child(1),
        .tabla-principal td:nth-child(1) {
            width: 5% !important; /* Nro */
        }
        
        .tabla-principal th:nth-child(2),
        .tabla-principal td:nth-child(2) {
            width: 10% !important; /* Proceso */
        }
        
        .tabla-principal th:nth-child(3),
        .tabla-principal td:nth-child(3) {
            width: 18% !important; /* Descripción */
        }
        
        .tabla-principal th:nth-child(4),
        .tabla-principal td:nth-child(4) {
            width: 30% !important; /* Alcance */
        }
        
        .tabla-principal th:nth-child(5),
        .tabla-principal td:nth-child(5) {
            width: 8% !important; /* UDM */
        }
        
        .tabla-principal th:nth-child(6),
        .tabla-principal td:nth-child(6) {
            width: 10% !important; /* Frecuencia */
        }
        
        .tabla-principal th:nth-child(7),
        .tabla-principal td:nth-child(7) {
            width: 12% !important; /* P.U USD */
        }
        
        /* COLORES POR PROCESO - MANTENIDOS */
        .recepcion { background-color: #e8f6f3 !important; }
        .almacenaje { background-color: #fef9e7 !important; }
        .despacho { background-color: #f4ecf7 !important; }
        .otros { background-color: #fdedec !important; }
        
        /* ENCABEZADO - AJUSTADO */
        .encabezado-titulo {
            text-align: center;
            font-size: 12pt; /* Reducido para mejor ajuste */
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
            padding-bottom: 5px;
            border-bottom: 2px solid #3498db;
        }
        
        /* TABLA DETALLES - AJUSTADA */
        .tabla-detalles {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 7.5pt; /* Reducido */
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
            font-size: 9pt; /* Reducido */
        }
        
        .numero-item {
            width: 35px !important; /* Reducido */
            text-align: center;
            font-weight: bold;
            background: #f1f8ff;
            border-right: 2px solid #5B9BD5;
        }
        
        /* FOOTER - AJUSTADO */
        .footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 7pt; /* Reducido */
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
            font-size: 7.5pt; /* Reducido */
        }
        
        /* AJUSTES ESPECÍFICOS PARA MEJOR AJUSTE */
        .contenedor-pagina > div:not(.botones-contenedor) {
            margin-bottom: 8px;
        }
        
        /* Asegurar que las tablas internas también se ajusten */
        .tabla-detalles table {
            width: 100% !important;
            font-size: 7.5pt !important;
        }
    </style>
</head>
<body>

<!-- El resto del código HTML/PHP permanece EXACTAMENTE IGUAL -->
<!-- Solo se modificaron los estilos CSS arriba -->
    
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
                    switch($p['proceso']) {
                        case 'Recepción': $clase = 'recepcion'; break;
                        case 'Almacenaje': $clase = 'almacenaje'; break;
                        case 'Despacho': $clase = 'despacho'; break;
                        case 'Otros': $clase = 'otros'; break;
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
                            <td style="width: 15%; border: none; text-align: right; font-weight: bold; color: #27ae60;">Bs696.000,00</td>
                            <td style="width: 15%; border: none; text-align: right; font-weight: bold; color: #e74c3c;">$100.000,00</td>
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
                '5.3.' => 'El cliente deberá realizar su ajuste a su sistema para empezar el siguiente mes con un inventario saneado entre ambas partes.',
                '5.3.' => 'La recuperación de productos dañados no está considerado dentro del alcance del servicio.'
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