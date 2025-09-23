<?php

use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/vendor/autoload.php';

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

// Boot kernel
$kernel = new \App\Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

// Get the container
$container = $kernel->getContainer();

// Get doctrine
$doctrine = $container->get('doctrine');
$em = $doctrine->getManager('siv');

// Test query
try {
    $repository = $em->getRepository(\App\Entity\Dashboard\ViListaLlamadasSos::class);
    $queryBuilder = $repository->createQueryBuilder('v')
        ->orderBy('v.fechaUtc', 'DESC')
        ->setMaxResults(5);

    $llamadas = $queryBuilder->getQuery()->getResult();

    echo "✅ Database connection successful\n";
    echo "Found " . count($llamadas) . " records\n\n";

    // Test Excel generation
    echo "Testing Excel generation...\n";

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'Test');
    $sheet->setCellValue('B1', 'Excel');
    $sheet->setCellValue('C1', 'Export');

    // Test MemoryDrawing
    echo "Testing image embedding...\n";
    $logoPath = __DIR__ . '/public/images/concessions/HCSQELGZDdo.png';

    if (file_exists($logoPath)) {
        $gdImage = imagecreatefrompng($logoPath);
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing();
        $drawing->setName('Logo');
        $drawing->setImageResource($gdImage);
        $drawing->setRenderingFunction(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::RENDERING_PNG);
        $drawing->setMimeType(\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing::MIMETYPE_PNG);
        $drawing->setCoordinates('D1');
        $drawing->setWorksheet($sheet);
        echo "✅ Image embedded successfully\n";
    } else {
        echo "⚠️ Logo file not found at: $logoPath\n";
    }

    // Save to temp file
    $tempFile = '/tmp/test_excel_' . uniqid() . '.xlsx';
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($tempFile);

    if (file_exists($tempFile)) {
        $size = filesize($tempFile);
        echo "✅ Excel file created successfully: $tempFile (Size: $size bytes)\n";
        unlink($tempFile);
    } else {
        echo "❌ Failed to create Excel file\n";
    }

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}