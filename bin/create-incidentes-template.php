#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

echo "Creando template Excel para Incidentes Ocupación VS...\n";

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Incidentes Ocupación');

// FILA 1: Vacía (separador)
$sheet->getRowDimension(1)->setRowHeight(5);

// FILA 2: Títulos de sección con celdas agrupadas
$sheet->setCellValue('A2', 'DATOS INCIDENTES');
$sheet->setCellValue('K2', 'Archivo Ocupación de Pista (Sábana)');

// Agrupar celdas en fila 2
$sheet->mergeCells('A2:J2');
$sheet->mergeCells('K2:P2');

// Estilo para títulos de sección (fila 2)
$sectionStyle = [
    'font' => [
        'bold' => true,
        'size' => 11,
        'color' => ['rgb' => '000000']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle('A2:J2')->applyFromArray($sectionStyle);
$sheet->getStyle('K2:P2')->applyFromArray($sectionStyle);
$sheet->getRowDimension(2)->setRowHeight(25);

// FILA 3: Headers de columnas (16 columnas)
$headers = [
    'A3' => 'Código del incidente',
    'B3' => 'Tipo de incidente',
    'C3' => 'Descripción',
    'D3' => 'Tramo',
    'E3' => 'Calzada',
    'F3' => 'Ubicación',
    'G3' => 'Informado Por',
    'H3' => 'Observaciones',
    'I3' => 'Fecha hora creación',
    'J3' => 'Fecha de finalización',
    'K3' => 'ubicacion',
    'L3' => 'pk_desde',
    'M3' => 'pk_hasta',
    'N3' => 'sector',
    'O3' => 'fechahora_creacion_incidente',
    'P3' => 'Fechahora_fin_ultimo_control_tto'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Estilo para headers de columnas (fila 3)
$headerStyle = [
    'font' => [
        'bold' => true,
        'size' => 10,
        'color' => ['rgb' => '000000']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
];

$sheet->getStyle('A3:P3')->applyFromArray($headerStyle);

// Configurar altura de fila de headers
$sheet->getRowDimension(3)->setRowHeight(30);

// Configurar anchos de columnas
$columnWidths = [
    'A' => 18,  // Código del incidente
    'B' => 20,  // Tipo de incidente
    'C' => 35,  // Descripción
    'D' => 12,  // Tramo
    'E' => 15,  // Calzada
    'F' => 20,  // Ubicación
    'G' => 20,  // Informado Por
    'H' => 40,  // Observaciones
    'I' => 20,  // Fecha hora creación
    'J' => 20,  // Fecha de finalización
    'K' => 12,  // Sentido
    'L' => 12,  // PK Desde
    'M' => 12,  // PK Hasta
    'N' => 15,  // Sector
    'O' => 25,  // Fecha hora creación incidente
    'P' => 25   // Fecha hora fin último control TTO
];

foreach ($columnWidths as $col => $width) {
    $sheet->getColumnDimension($col)->setWidth($width);
}

// Estilo para datos (a partir de fila 4)
$dataStyle = [
    'alignment' => [
        'vertical' => Alignment::VERTICAL_TOP,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'CCCCCC']
        ]
    ]
];

// Aplicar estilo a las primeras 1000 filas de datos (suficiente para la mayoría de reportes)
$sheet->getStyle('A4:P1003')->applyFromArray($dataStyle);

// FILA 4: Agregar fila de ejemplo con datos reales del CSV
$sheet->setCellValue('A4', '27836');
$sheet->setCellValue('B4', 'TR - 01');
$sheet->setCellValue('C4', 'TRABAJOS, TRABAJOS DE MANTENCIÓN Y CONSERVACIÓN');
$sheet->setCellValue('D4', '1');
$sheet->setCellValue('E4', 'O-P');
$sheet->setCellValue('F4', 'Pista/Calzada');
$sheet->setCellValue('G4', 'Telefòno de Emergencia');
$sheet->setCellValue('H4', 'PULIDO Y PINTADO DE PASARELAS SECTOR LOS TILOS');
$sheet->setCellValue('I4', '31/07/2025 08:53:00');
$sheet->setCellValue('J4', '31/07/2025 19:40:29');
$sheet->setCellValue('K4', 'Pasarela');
$sheet->setCellValue('L4', '-5,929');
$sheet->setCellValue('M4', '-5,929');
$sheet->setCellValue('N4', 'Las Torres (Maipú / Cerrillos)');
$sheet->setCellValue('O4', '31-07-2025 08:53');
$sheet->setCellValue('P4', '');

// Proteger headers (opcional)
// $sheet->getProtection()->setSheet(true);
// $sheet->getStyle('A1:P1')->getProtection()->setLocked(true);

// Guardar archivo en /tmp primero
$tmpPath = '/tmp/rpt_incidentes_ocupacion_vs.xlsx';
$outputPath = __DIR__ . '/../public/files/templates/rpt_incidentes_ocupacion_vs.xlsx';

$writer = new Xlsx($spreadsheet);
$writer->save($tmpPath);

echo "✅ Template creado en /tmp: $tmpPath\n";
echo "   - Fila 1: Separador\n";
echo "   - Fila 2: Títulos de sección agrupados\n";
echo "   - Fila 3: 16 headers de columnas\n";
echo "   - Fila 4: Ejemplo con datos reales\n";
echo "   - Formato profesional con estilos\n";
echo "\n📝 Ahora mueve el archivo con: sudo mv $tmpPath $outputPath && sudo chown www:www $outputPath && sudo chmod 664 $outputPath\n";
