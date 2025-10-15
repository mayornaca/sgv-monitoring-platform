<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Component\Pager\PaginatorInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Entity\Dashboard\ViListaLlamadasSos;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;

#[Route('/admin/siv')]
class SivController extends AbstractController
{
    #[Route('/test-excel', name: 'admin_test_excel')]
    public function testExcel(): Response
    {
        return new Response('Excel test working', 200, ['Content-Type' => 'text/plain']);
    }
    private $doctrine;
    private $paginator;
    private $adminUrlGenerator;
    private $logger;
    private $pdfGenerator;

    public function __construct(
        ManagerRegistry $doctrine,
        PaginatorInterface $paginator,
        AdminUrlGenerator $adminUrlGenerator,
        LoggerInterface $logger,
        Pdf $pdfGenerator
    ) {
        $this->doctrine = $doctrine;
        $this->paginator = $paginator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->logger = $logger;
        $this->pdfGenerator = $pdfGenerator;
    }

    #[AdminRoute('/test-pdf-simple', name: 'test_pdf_simple')]
    public function testPdfSimple(): Response
    {
        $html = $this->renderView('dashboard/siv/reportes/test_pdf_simple.html.twig');

        $pdfDir = $this->getParameter('kernel.project_dir') . '/public/downloads';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0775, true);
        }

        $pdfPath = $pdfDir . '/test_simple_' . date('YmdHis') . '.pdf';

        // Configurar opciones PDF
        $this->pdfGenerator->setOption("encoding", 'UTF-8');
        $this->pdfGenerator->setOption("enable-local-file-access", true);
        $this->pdfGenerator->setOption("page-size", 'A4');
        $this->pdfGenerator->setOption("margin-bottom", 10);
        $this->pdfGenerator->setOption("margin-left", 10);
        $this->pdfGenerator->setOption("margin-right", 10);
        $this->pdfGenerator->setOption("margin-top", 10);

        try {
            $this->pdfGenerator->generateFromHtml($html, $pdfPath);

            return new JsonResponse([
                'success' => true,
                'message' => 'PDF generado correctamente',
                'file' => basename($pdfPath),
                'size' => filesize($pdfPath),
                'url' => '/downloads/' . basename($pdfPath)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    #[AdminRoute('/lista_llamadas_sos', name: 'lista_llamadas_sos')]
    public function listaLlamadasSosAction(Request $request): Response
    {
        $em = $this->doctrine->getManager('siv');

        // Get filter parameters
        $ci = $request->query->getInt('ci', 1); // carga inicial: 0 = no cargar, 1 = cargar (default 1 para carga automática)
        $filterSos = $request->query->get('filterSos', '');
        
        // Default dates: mes anterior completo (legacy pattern)
        $defaultFechaInicio = date('d-m-Y H:i:s', mktime(0, 0, 0, date('n')-1, 1, date('Y')));
        $defaultFechaTermino = date('d-m-Y H:i:s', mktime(23, 59, 59, date('n'), 0, date('Y')));
        
        $fechaInicio = $request->query->get('fechaInicio', $defaultFechaInicio);
        $fechaTermino = $request->query->get('fechaTermino', $defaultFechaTermino);
        $pageSize = $request->query->getInt('pageSize', 20);

        // DEBUG: Log fechas recibidas
        $this->logger->info('DEBUG lista_llamadas_sos', [
            'ci' => $ci,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'filterSos' => $filterSos
        ]);

        // Only execute query if ci=1 (explicit load request)
        if ($ci === 1) {
            $queryBuilder = $em->getRepository(ViListaLlamadasSos::class)
                ->createQueryBuilder('v');

            // Apply SOS filter
            if (!empty($filterSos)) {
                $queryBuilder->andWhere('v.sos LIKE :sos')
                    ->setParameter('sos', '%' . $filterSos . '%');
            }

            // Apply date filters
            if (!empty($fechaInicio)) {
                try {
                    // Parse date in DD-MM-YYYY HH:mm:ss format
                    $startDate = \DateTime::createFromFormat('d-m-Y H:i:s', $fechaInicio);
                    if (!$startDate) {
                        // Try without time
                        $startDate = \DateTime::createFromFormat('d-m-Y', $fechaInicio);
                        if ($startDate) {
                            $startDate->setTime(0, 0, 0);
                        }
                    }
                    if ($startDate) {
                        $queryBuilder->andWhere('v.fechaUtc >= :inicio')
                            ->setParameter('inicio', $startDate);
                    }
                } catch (\Exception $e) {
                    // Invalid date format, ignore filter
                }
            }

            if (!empty($fechaTermino)) {
                try {
                    // Parse date in DD-MM-YYYY HH:mm:ss format
                    $endDate = \DateTime::createFromFormat('d-m-Y H:i:s', $fechaTermino);
                    if (!$endDate) {
                        // Try without time
                        $endDate = \DateTime::createFromFormat('d-m-Y', $fechaTermino);
                        if ($endDate) {
                            $endDate->setTime(23, 59, 59);
                        }
                    }
                    if ($endDate) {
                        $queryBuilder->andWhere('v.fechaUtc <= :termino')
                            ->setParameter('termino', $endDate);
                    }
                } catch (\Exception $e) {
                    // Invalid date format, ignore filter
                }
            }

            // Order by date DESC
            $queryBuilder->orderBy('v.fechaUtc', 'DESC');

            $query = $queryBuilder->getQuery();

            // Paginate results
            $pagination = $this->paginator->paginate(
                $query,
                $request->query->getInt('page', 1),
                $pageSize
            );
        } else {
            // When ci=0, use empty array instead of fake query
            $pagination = $this->paginator->paginate(
                [],  // Empty array - cleaner than WHERE 1=0
                $request->query->getInt('page', 1),
                $pageSize
            );
        }

        $data = [
            'pagination' => $pagination,
            'admin_url_generator' => $this->adminUrlGenerator,
            'ci' => $ci,
            'filterSos' => $filterSos,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'pageSize' => $pageSize
        ];

        return $this->render('dashboard/siv/lista_llamadas_sos.html.twig', $data);
    }

    #[AdminRoute('/lista_llamadas_sos_export_excel', name: 'lista_llamadas_sos_export_excel')]
    #[IsGranted('ROLE_USER')]
    public function exportExcelAction(Request $request): StreamedResponse
    {
        ini_set('memory_limit', '256M');
        set_time_limit(300);
        $em = $this->doctrine->getManager('siv');

        // Get filter parameters if any
        $filterSos = $request->query->get('filterSos', '');
        $fechaInicio = $request->query->get('fechaInicio', '');
        $fechaTermino = $request->query->get('fechaTermino', '');

        $queryBuilder = $em->getRepository(ViListaLlamadasSos::class)
            ->createQueryBuilder('v');

        // Apply filters if provided (same logic as list view)
        if (!empty($filterSos)) {
            $queryBuilder->andWhere('v.sos LIKE :sos')
                ->setParameter('sos', '%' . $filterSos . '%');
        }

        // Apply date filters with proper format parsing
        if (!empty($fechaInicio)) {
            try {
                $startDate = \DateTime::createFromFormat('d-m-Y H:i:s', $fechaInicio);
                if (!$startDate) {
                    $startDate = \DateTime::createFromFormat('d-m-Y', $fechaInicio);
                    if ($startDate) {
                        $startDate->setTime(0, 0, 0);
                    }
                }
                if ($startDate) {
                    $queryBuilder->andWhere('v.fechaUtc >= :inicio')
                        ->setParameter('inicio', $startDate);
                }
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        if (!empty($fechaTermino)) {
            try {
                $endDate = \DateTime::createFromFormat('d-m-Y H:i:s', $fechaTermino);
                if (!$endDate) {
                    $endDate = \DateTime::createFromFormat('d-m-Y', $fechaTermino);
                    if ($endDate) {
                        $endDate->setTime(23, 59, 59);
                    }
                }
                if ($endDate) {
                    $queryBuilder->andWhere('v.fechaUtc <= :termino')
                        ->setParameter('termino', $endDate);
                }
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        $queryBuilder->orderBy('v.fechaUtc', 'DESC');
        $llamadas = $queryBuilder->getQuery()->getResult();
        $total_row_count = count($llamadas);

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set column widths optimized for content
        $sheet->getColumnDimension('A')->setWidth(12);  // Para el espacio del logo
        $sheet->getColumnDimension('B')->setWidth(18);  // Poste SOS (ej: 40-SOSI-0162)
        $sheet->getColumnDimension('C')->setWidth(20);  // Eje (ej: EJE COSTANERA)
        $sheet->getColumnDimension('D')->setWidth(22);  // Calzada (ej: Poniente-Oriente(Sur))
        $sheet->getColumnDimension('E')->setWidth(12);  // KM (números decimales)
        $sheet->getColumnDimension('F')->setWidth(22);  // Fecha Hora (ej: 25/05/2019 01:23:02)

        // Set row heights for header
        $sheet->getRowDimension('1')->setRowHeight(24.75);
        $sheet->getRowDimension('2')->setRowHeight(18.75);

        // Apply header background style
        $styleArrayHeaderBackground = [
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'ffffff',
                ]
            ]
        ];
        $sheet->getStyle('A1:F7')->applyFromArray($styleArrayHeaderBackground);

        // Apply left border for sub-header
        $styleArraySubHeaderLeftBorder = [
            'borders' => [
                'left' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['argb' => '2f75b5']
                ]
            ]
        ];
        $sheet->getStyle('B3:B6')->applyFromArray($styleArraySubHeaderLeftBorder);

        // Apply table header border
        $styleArrayTableHeaderAllBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '777777']
                ]
            ]
        ];
        $sheet->getStyle('B8:F8')->applyFromArray($styleArrayTableHeaderAllBorder);
        $sheet->getStyle('B8:F8')->getFont()->setBold(true);

        // Apply table body border
        $styleArrayTableBodyAllBorder = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'f1f1f1']
                ]
            ]
        ];

        if ($total_row_count > 0) {
            $sheet->getStyle('B9:F' . ($total_row_count + 8))->applyFromArray($styleArrayTableBodyAllBorder);
            $sheet->getStyle('E9:E' . ($total_row_count + 8))->getNumberFormat()->setFormatCode('#,##0.000');
        }

        // Merge cells for title
        $sheet->mergeCells('C1:E1');
        $styleC1E1 = [
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];
        $sheet->getStyle("C1:E1")->applyFromArray($styleC1E1);

        // Set header values
        $sheet->setCellValue('C1', 'Sociedad Concesionaria Costanera Norte')
            ->setCellValue('B3', 'Lista de llamadas SOS')
            ->setCellValue('B4', 'Obra concesionada: Autopista Costanera Norte')
            ->setCellValue('B5', 'Año: ' . date('Y'))
            ->setCellValue('B6', 'Total: ' . $total_row_count)
            ->setCellValue('F6', date('d-m-Y H:i:s'))
            ->setCellValue('B8', 'Poste')
            ->setCellValue('C8', 'Eje')
            ->setCellValue('D8', 'Calzada')
            ->setCellValue('E8', 'Km')
            ->setCellValue('F8', 'Fecha Hora');

        // Center align Km column header
        $sheet->getStyle('E8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Add images/logos
        try {
            // Logo Concesionaria Costanera Norte
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/concessions/HCSQELGZDdo.png';
            if (file_exists($logoPath)) {
                $gdImage = imagecreatefrompng($logoPath);
                // Preserve PNG transparency
                imagealphablending($gdImage, false);
                imagesavealpha($gdImage, true);
                
                $objDrawing = new MemoryDrawing();
                $objDrawing->setName('Logo Concesionaria');
                $objDrawing->setDescription('Logo Concesionaria Costanera Norte');
                $objDrawing->setImageResource($gdImage);
                $objDrawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                $objDrawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                $objDrawing->setResizeProportional(true);
                $objDrawing->setHeight(55);  // Ajustar altura para que sea proporcional
                $objDrawing->setCoordinates('B1');
                $objDrawing->setWorksheet($sheet);
            }

            // Logo Ministerio de Obras Públicas
            $logoPath2 = $this->getParameter('kernel.project_dir') . '/public/images/coordinacion_concesiones_obras_publicas.jpg';
            if (file_exists($logoPath2)) {
                $gdImageF1 = imagecreatefromjpeg($logoPath2);
                $objDrawingF1 = new MemoryDrawing();
                $objDrawingF1->setName('Logo MOP');
                $objDrawingF1->setDescription('Logo Coordinación de Concesiones de Obras Públicas');
                $objDrawingF1->setImageResource($gdImageF1);
                $objDrawingF1->setRenderingFunction(MemoryDrawing::RENDERING_JPEG);
                $objDrawingF1->setMimeType(MemoryDrawing::MIMETYPE_JPEG);
                $objDrawingF1->setResizeProportional(true);
                $objDrawingF1->setHeight(87);
                $objDrawingF1->setCoordinates('F1');
                $objDrawingF1->setWorksheet($sheet);
            }
        } catch (\Exception $e) {
            // Continue without images if there's an error
        }

        // Add data rows
        $excel_data_row_offset = 9;
        $paint_fill = false;

        foreach ($llamadas as $i => $llamada) {
            $row_data_index = $i + $excel_data_row_offset;

            $sheet->setCellValue('B' . $row_data_index, $llamada->getSos());
            $sheet->setCellValue('C' . $row_data_index, $llamada->getEje());
            $sheet->setCellValue('D' . $row_data_index, $llamada->getCalzada());
            $sheet->setCellValue('E' . $row_data_index, $llamada->getKm());

            // Format Km column with 3 decimals and center alignment
            $sheet->getStyle('E' . $row_data_index)->getNumberFormat()
                ->setFormatCode('#,##0.000');
            $sheet->getStyle('E' . $row_data_index)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Format date
            $fechaFormateada = $llamada->getFechaUtc() ? $llamada->getFechaUtc()->format('d-m-Y H:i:s') : $llamada->getFechaHora();
            $sheet->setCellValue('F' . $row_data_index, $fechaFormateada);

            // Apply alternating row colors
            if ($paint_fill) {
                $sheet->getStyle('B' . $row_data_index . ':F' . $row_data_index)
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('f9f9f9');
            }
            $paint_fill = !$paint_fill;
        }

        // Set sheet title
        $sheet->setTitle('Llamadas SOS');
        $spreadsheet->setActiveSheetIndex(0);

        // Create response
        $response = new StreamedResponse(function() use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        });

        $fileName = 'Lista_de_llamadas_SOS_' . date('Y-m-d_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $fileName . '"');
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    #[AdminRoute('/lista_llamadas_sos_export_pdf', name: 'lista_llamadas_sos_export_pdf')]
    #[IsGranted('ROLE_USER')]
    public function exportPdfAction(Request $request, Pdf $knpSnappyPdf): PdfResponse
    {
        ini_set('memory_limit', '256M');
        set_time_limit(300);
        $em = $this->doctrine->getManager('siv');

        // Get filter parameters (same logic as Excel export)
        $filterSos = $request->query->get('filterSos', '');
        $fechaInicio = $request->query->get('fechaInicio', '');
        $fechaTermino = $request->query->get('fechaTermino', '');

        $queryBuilder = $em->getRepository(ViListaLlamadasSos::class)
            ->createQueryBuilder('v');

        // Apply filters if provided (same logic as Excel export)
        if (!empty($filterSos)) {
            $queryBuilder->andWhere('v.sos LIKE :sos')
                ->setParameter('sos', '%' . $filterSos . '%');
        }

        // Apply date filters with proper format parsing
        if (!empty($fechaInicio)) {
            try {
                $startDate = \DateTime::createFromFormat('d-m-Y H:i:s', $fechaInicio);
                if (!$startDate) {
                    $startDate = \DateTime::createFromFormat('d-m-Y', $fechaInicio);
                    if ($startDate) {
                        $startDate->setTime(0, 0, 0);
                    }
                }
                if ($startDate) {
                    $queryBuilder->andWhere('v.fechaUtc >= :inicio')
                        ->setParameter('inicio', $startDate);
                }
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        if (!empty($fechaTermino)) {
            try {
                $endDate = \DateTime::createFromFormat('d-m-Y H:i:s', $fechaTermino);
                if (!$endDate) {
                    $endDate = \DateTime::createFromFormat('d-m-Y', $fechaTermino);
                    if ($endDate) {
                        $endDate->setTime(23, 59, 59);
                    }
                }
                if ($endDate) {
                    $queryBuilder->andWhere('v.fechaUtc <= :termino')
                        ->setParameter('termino', $endDate);
                }
            } catch (\Exception $e) {
                // Invalid date format, ignore filter
            }
        }

        $queryBuilder->orderBy('v.fechaUtc', 'DESC');
        $llamadas = $queryBuilder->getQuery()->getResult();
        $total_count = count($llamadas);

        $html = $this->renderView('dashboard/siv/lista_llamadas_sos_pdf.html.twig', [
            'llamadas' => $llamadas,
            'total_count' => $total_count,
            'filters' => [
                'filterSos' => $filterSos,
                'fechaInicio' => $fechaInicio,
                'fechaTermino' => $fechaTermino
            ]
        ]);

        $fileName = 'Lista_de_llamadas_SOS_' . date('Y-m-d_His') . '.pdf';

        return new PdfResponse(
            $knpSnappyPdf->getOutputFromHtml($html),
            $fileName
        );
    }

    /**
     * Informe Mensual de Citofonía
     * Genera un reporte mensual consolidado de todas las llamadas de citofonía
     */
    #[AdminRoute('/informe_mensual_citofonia', name: 'siv_dashboard_informe_mensual_citofonia')]
    public function informeMensualCitofoniaAction(Request $request, Pdf $knpSnappyPdf): Response
    {
        set_time_limit(0);

        $em = $this->doctrine->getManager('siv');

        // Get parameters
        $params = $request->query->all();
        $generateExcel = (int) ($params['generateExcel'] ?? 0);
        $generatePdf = (int) ($params['generatePdf'] ?? 0);

        $filterSos = $params['filterSos'] ?? '';

        // Date filters with default values (previous month)
        if (isset($params['fechaInicio']) && $params['fechaInicio']) {
            $fechaInicio = $params['fechaInicio'];
            $fechaInicio_Date = $this->getDate($fechaInicio);
        } else {
            // Default: First day of previous month
            $fechaInicio = date('d-m-Y H:i:s', mktime(0, 0, 0, date('n')-1, 1, date('Y')));
            $fechaInicio_Date = date('Y-m-d H:i:s', mktime(0, 0, 0, date('n')-1, 1, date('Y')));
        }

        if (isset($params['fechaTermino']) && $params['fechaTermino']) {
            $fechaTermino = $params['fechaTermino'];
            $fechaTermino_Date = $this->getDate($fechaTermino);
        } else {
            // Default: Last day of previous month
            $fechaTermino = date('d-m-Y H:i:s', mktime(23, 59, 59, date('n'), 0, date('Y')));
            $fechaTermino_Date = date('Y-m-d H:i:s', mktime(23, 59, 59, date('n'), 0, date('Y')));
        }

        $arr_data_table = [];
        $return_file_name_excel = null;
        $return_file_name_pdf = null;

        // Execute stored function if dates are set
        if (!empty($fechaInicio_Date) && !empty($fechaTermino_Date)) {
            try {
                $conn = $em->getConnection();
                $sql = "SELECT * FROM FN_Reporte_mensual_sitofonia(:inicio, :fin)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('inicio', $fechaInicio_Date);
                $stmt->bindValue('fin', $fechaTermino_Date);
                $result = $stmt->executeQuery();
                $arr_data_table = $result->fetchAllAssociative();

            } catch (\Exception $e) {
                // Log error and continue with empty data
                error_log('Error calling FN_Reporte_mensual_sitofonia: ' . $e->getMessage());
                $arr_data_table = [];
            }

            // Generate Excel if requested
            if ($generateExcel) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();
                $total_row_count = count($arr_data_table);

                // Set column widths (A is spacer, B-L for data)
                foreach (range('B', 'L') as $col) {
                    $sheet->getColumnDimension($col)->setWidth(19);
                }
                $sheet->getColumnDimension('A')->setWidth(1);
                $sheet->getRowDimension(1)->setRowHeight(5);
                $sheet->getRowDimension(8)->setRowHeight(50);
                $sheet->getRowDimension(28)->setRowHeight(50);

                // Apply background styles
                $styleArrayHeaderBackground = [
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFF']]],
                ];
                $sheet->getStyle('A1:L45')->applyFromArray($styleArrayHeaderBackground);

                // Left border for headers section
                $styleArraySubHeaderLeftBorder = [
                    'borders' => ['left' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['argb' => '2f75b5']]],
                ];
                $sheet->getStyle('B3:B6')->applyFromArray($styleArraySubHeaderLeftBorder);
                $sheet->getStyle('B3')->getFont()->setBold(true);
                $sheet->getStyle('B3')->getFont()->getColor()->setARGB('1b809e');
                $sheet->getStyle('B3')->getFont()->setSize(14);

                // Table header styling
                $styleArrayTableHeaderAllBorder = [
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => '777777']]],
                ];
                $sheet->getStyle('B8:L8')->applyFromArray($styleArrayTableHeaderAllBorder);
                $sheet->getStyle('B8:L8')->getFont()->setBold(true);
                $sheet->getStyle('B8:L8')->getAlignment()->setWrapText(true);
                $sheet->getStyle("B8:L8")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Table body styling
                $styleArrayTableBodyAllBorder = [
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'f1f1f1']]],
                ];
                $sheet->getStyle('B9:L' . ($total_row_count + 8))->applyFromArray($styleArrayTableBodyAllBorder);

                // Footer logo section
                $sheet->mergeCells('C28:K28');
                $sheet->getStyle("C28:K28")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

                // Set header values and column titles
                $sheet->setCellValue('C28', 'Sociedad Concesionaria Costanera Norte')
                    ->setCellValue('B3', 'Informe Mensual de Citofonía')
                    ->setCellValue('B4', 'Periodo de consulta desde ' . $fechaInicio . ' hasta ' . $fechaTermino)
                    ->setCellValue('B5', 'Obra concesionada: Autopista Costanera Norte')
                    ->setCellValue('B6', 'Año: ' . date('Y'))
                    ->setCellValue('L6', date('d-m-Y H:i:s'))
                    ->setCellValue('B8', 'Mes')
                    ->setCellValue('C8', 'Consulta por información de call center')
                    ->setCellValue('D8', 'Consulta por condiciones de la vía')
                    ->setCellValue('E8', 'Corte de llamada')
                    ->setCellValue('F8', 'Prueba/Trabajo de Poste en la autopista')
                    ->setCellValue('G8', 'Reclamos')
                    ->setCellValue('H8', 'Registro de incidente')
                    ->setCellValue('I8', 'Total')
                    ->setCellValue('J8', 'Realizadas por postes SOS')
                    ->setCellValue('K8', 'Realizadas por usuarios a Postes SOS')
                    ->setCellValue('L8', 'Realizadas por 24900767');

                // Add logos
                try {
                    $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/concessions/HCSQELGZDdo.png';
                    if (file_exists($logoPath)) {
                        $gdImage = imagecreatefrompng($logoPath);
                        // Preserve PNG transparency
                        imagealphablending($gdImage, false);
                        imagesavealpha($gdImage, true);

                        $objDrawing = new MemoryDrawing();
                        $objDrawing->setName('Logo');
                        $objDrawing->setDescription('Logo Concesionaria');
                        $objDrawing->setImageResource($gdImage);
                        $objDrawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                        $objDrawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                        $objDrawing->setCoordinates('B28');
                        $objDrawing->setWorksheet($sheet);
                    }

                    $logoPath2 = $this->getParameter('kernel.project_dir') . '/public/images/coordinacion_concesiones_obras_publicas.jpg';
                    if (file_exists($logoPath2)) {
                        $gdImageF1 = imagecreatefromjpeg($logoPath2);
                        $objDrawingF1 = new MemoryDrawing();
                        $objDrawingF1->setName('Logo MOP');
                        $objDrawingF1->setDescription('Logo MOP');
                        $objDrawingF1->setImageResource($gdImageF1);
                        $objDrawingF1->setRenderingFunction(MemoryDrawing::RENDERING_JPEG);
                        $objDrawingF1->setMimeType(MemoryDrawing::MIMETYPE_JPEG);
                        $objDrawingF1->setResizeProportional(true);
                        $objDrawingF1->setHeight(87);
                        $objDrawingF1->setCoordinates('L28');
                        $objDrawingF1->setWorksheet($sheet);
                    }
                } catch (\Exception $e) {
                    // Continue without images if error
                }

                // Add data rows
                $excel_data_row_offset = 9;
                $paint_fill = false;

                foreach ($arr_data_table as $i => $row_data) {
                    $row_data_index = $i + $excel_data_row_offset;
                    $sheet->setCellValue('B' . $row_data_index, $row_data['mes_r'] ?? '')
                        ->setCellValue('C' . $row_data_index, $row_data['call_center_r'] ?? 0)
                        ->setCellValue('D' . $row_data_index, $row_data['consulta_via_r'] ?? 0)
                        ->setCellValue('E' . $row_data_index, $row_data['corte_llamada_r'] ?? 0)
                        ->setCellValue('F' . $row_data_index, $row_data['prueba_trabajo_postes_r'] ?? 0)
                        ->setCellValue('G' . $row_data_index, $row_data['reclamo_r'] ?? 0)
                        ->setCellValue('H' . $row_data_index, $row_data['registro_incidente_r'] ?? 0)
                        ->setCellValue('I' . $row_data_index, $row_data['total_r'] ?? 0)
                        ->setCellValue('J' . $row_data_index, $row_data['realizada_por_sos_r'] ?? 0)
                        ->setCellValue('K' . $row_data_index, $row_data['realizada_por_usuario_a_sos_r'] ?? 0)
                        ->setCellValue('L' . $row_data_index, $row_data['realizada_por_767_r'] ?? 0);

                    if ($paint_fill) {
                        $sheet->getStyle('B' . $row_data_index . ':L' . $row_data_index)
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('f9f9f9');
                    }
                    $paint_fill = !$paint_fill;
                }

                // Highlight column K
                $sheet->getStyle('K8:K21')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('fff8dc');

                $sheet->setTitle('Informe Mensual de Citofonía');
                $spreadsheet->setActiveSheetIndex(0);

                // Save file to public/downloads directory
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $fileName = 'Informe_Mensual_Citofonia_' . date('Y-m-d_His') . '.xlsx';

                // Save to public/downloads for permanent download links
                $downloadsDir = $this->getParameter('kernel.project_dir') . '/public/downloads';
                if (!is_dir($downloadsDir)) {
                    mkdir($downloadsDir, 0755, true);
                }
                $filePath = $downloadsDir . '/' . $fileName;
                $writer->save($filePath);

                $return_file_name_excel = $fileName;
            }

            // Generate PDF if requested
            if ($generatePdf) {
                $html = $this->renderView('dashboard/siv/informe_mensual_citofonia_pdf.html.twig', [
                    'data_table' => $arr_data_table,
                    'fechaInicio' => $fechaInicio,
                    'fechaTermino' => $fechaTermino
                ]);

                $fileName = 'Informe_Mensual_Citofonia_' . date('Y-m-d_His') . '.pdf';

                // Configure PDF options
                $knpSnappyPdf->setOptions([
                    'encoding' => 'UTF-8',
                    'page-size' => 'A4',
                    'orientation' => 'Landscape',
                    'margin-top' => '10',
                    'margin-bottom' => '10',
                    'margin-left' => '10',
                    'margin-right' => '10'
                ]);

                // Save to public/downloads for permanent download links
                $downloadsDir = $this->getParameter('kernel.project_dir') . '/public/downloads';
                if (!is_dir($downloadsDir)) {
                    mkdir($downloadsDir, 0755, true);
                }
                $filePath = $downloadsDir . '/' . $fileName;
                file_put_contents($filePath, $knpSnappyPdf->getOutputFromHtml($html));

                $return_file_name_pdf = $fileName;
            }
        }

        // Render the main form/report view
        return $this->render('dashboard/siv/informe_mensual_citofonia.html.twig', [
            'data_table' => $arr_data_table,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'filterSos' => $filterSos,
            'return_file_name_pdf' => $return_file_name_pdf,
            'return_file_name_excel' => $return_file_name_excel
        ]);
    }

    #[AdminRoute('/registro_incidente', name: 'siv_dashboard_registro_incidente')]
    public function registroIncidenteAction(Request $request, Pdf $knpSnappyPdf): Response
    {
        set_time_limit(0);
        $params = $request->query->all();
        $generate_pdf = (int) ($params['generatePdf'] ?? 0);
        $generate_excel = (int) ($params['generateExcel'] ?? 0);
        $return_file_name_excel = null;
        $return_file_name_pdf = null;

        // Fixed Filtros de fecha
        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = $fechaInicio ? $this->getDate($fechaInicio) : '';

        $fechaTermino = $params['fechaTermino'] ?? '';
        $fechaTermino_Date = $fechaTermino ? $this->getDate($fechaTermino) : '';

        // QUERY NATIVA FUNCIONA PERO NO PERMITE EL MAPEO Y PAGINACIÓN
        if ($fechaInicio_Date && $fechaTermino_Date) {
            $conn = $this->doctrine->getConnection('siv');
            $sql = "SELECT * FROM fn_Grafico_IF(:inicio, :fin)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('inicio', $fechaInicio_Date);
            $stmt->bindValue('fin', $fechaTermino_Date);
            $data_table = $stmt->executeQuery()->fetchAllAssociative();

            // Process graphics data
            $graphics = [];
            foreach ($data_table as $item) {
                $graphic_name = $item['nombre_r'];
                $graphic_id = $item['id_g_r'];
                $graphic_type = $item['tipo_r'];
                $graphic_type_name = $item['eje_r'];
                $graphic_group = $item['grupo_r'];
                $graphic_value = 0 + $item['cantidad_r'];
                $graphic_title = $item['titulo_r'];
                $graphic_subject = $item['asunto_r'];

                if (!isset($graphics[$graphic_name][$graphic_id])) {
                    $graphics[$graphic_name][$graphic_id] = [
                        "id" => $graphic_name . "_" . $graphic_id,
                        "title" => $graphic_title,
                        "subtitle" => $graphic_id == "ge" ? "Autopista Costanera Norte" : $graphic_type_name,
                        "subject" => $graphic_subject,
                        "eje_r" => $graphic_type_name,
                        "type" => $graphic_type,
                        "data" => [],
                        "drilldown" => $graphic_id == "ge" ? [] : null
                    ];
                }

                array_push($graphics[$graphic_name][$graphic_id]["data"], [
                    "grupo_r" => $graphic_group,
                    "cantidad_r" => $graphic_value
                ]);

                if ($graphic_id == "ec" || $graphic_id == "ek") {
                    if (!isset($graphics[$graphic_name]["ge"]["drilldown"][$graphic_group])) {
                        $graphics[$graphic_name]["ge"]["drilldown"][$graphic_group] = [
                            "id" => $graphic_group,
                            "data" => []
                        ];
                    }

                    array_push($graphics[$graphic_name]["ge"]["drilldown"][$graphic_group]["data"], [
                        "name" => $graphic_type_name,
                        "value" => $graphic_value
                    ]);
                }
            }

            $arr_data_table = $graphics;

            if ($request->isXmlHttpRequest()) {
                return new Response(
                    json_encode($arr_data_table),
                    200,
                    ['Content-Type' => 'application/json']
                );
            }

            if ($generate_pdf) {
                // Configure PDF options (Highcharts 9.1.0 + window-status signal)
                $knpSnappyPdf->setOption("encoding", 'UTF-8');
                $knpSnappyPdf->setOption("enable-local-file-access", true);
                $knpSnappyPdf->setOption("window-status", 'ready_to_print');  // Señal de completado desde JavaScript
                $knpSnappyPdf->setOption("javascript-delay", 5000);  // Fallback si window-status no se alcanza
                $knpSnappyPdf->setOption("page-size", 'A4');
                $knpSnappyPdf->setOption("margin-bottom", 5);
                $knpSnappyPdf->setOption("margin-left", 1);
                $knpSnappyPdf->setOption("margin-right", 1);
                $knpSnappyPdf->setOption("margin-top", 0);
                $knpSnappyPdf->setOption("orientation", 'Landscape');
                $knpSnappyPdf->setOption("footer-font-size", 8);
                $knpSnappyPdf->setOption("no-stop-slow-scripts", true);

                $pre_file_name = 'Registro incidente [_' . uniqid('', true) . ']';
                $return_file_name_pdf = $this->sanear_string($pre_file_name) . '.pdf';

                // DEBUG: Log data_table para verificar si tiene datos
                $this->logger->info('PDF data_table count: ' . count($arr_data_table));
                foreach ($arr_data_table as $key => $group) {
                    $this->logger->info("  Group '$key': " . count($group) . " graphics");
                }

                $html = $this->renderView('dashboard/siv/reportes/registro_incidente/rpt_registro_incidente.html.twig', [
                    'renderTo' => 'pdf',
                    'data_table' => $arr_data_table,
                    'fechaInicio' => $fechaInicio_Date,
                    'fechaTermino' => $fechaTermino_Date,
                ]);

                $pdfDir = $this->getParameter('kernel.project_dir') . '/public/downloads';
                if (!is_dir($pdfDir)) {
                    mkdir($pdfDir, 0777, true);
                }

                // DEBUG: Guardar HTML crudo para inspección
                file_put_contents($pdfDir . '/debug_last.html', $html);

                $pdfPath = $pdfDir . '/' . $return_file_name_pdf;
                try {
                    $knpSnappyPdf->generateFromHtml($html, $pdfPath);
                } catch (\RuntimeException $e) {
                    // wkhtmltopdf may throw error due to JavaScript warnings (e.g., about:blank)
                    // but still generate the PDF successfully. Check if file exists.
                    if (!file_exists($pdfPath) || filesize($pdfPath) == 0) {
                        // PDF generation truly failed
                        throw $e;
                    }
                    // PDF exists and has content - ignore the error
                    $this->logger->warning('wkhtmltopdf warning (PDF generated successfully): ' . $e->getMessage());
                }
            }

            if ($generate_excel) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                $spreadsheet->getProperties()
                    ->setCreator("Sistema de reportes SIV")
                    ->setLastModifiedBy("Equipo Siv")
                    ->setTitle("Registro Incidente")
                    ->setSubject("Registro Incidente")
                    ->setDescription("Documento generado con Symfony 6")
                    ->setKeywords("centro de control siv incidentes")
                    ->setCategory("Reportes SIV");

                // Configure column widths
                foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
                    $sheet->getColumnDimension($col)->setWidth($col == 'A' ? 1 : 30);
                }

                // Headers
                $sheet->setCellValue('B1', 'Sociedad Concesionaria Costanera Norte');
                $sheet->setCellValue('B3', 'Tabla de datos registro incidente para generación de gráficos');
                $sheet->setCellValue('B4', 'Obra concesionada: Autopista Costanera Norte');
                $sheet->setCellValue('B5', 'Año: ' . date('Y'));
                $sheet->setCellValue('B6', 'Total: ' . count($data_table));
                $sheet->setCellValue('E6', date('d-m-Y H:i:s'));

                // Column headers
                $sheet->setCellValue('B8', 'Titulo');
                $sheet->setCellValue('C8', 'Sub Titulo');
                $sheet->setCellValue('D8', 'Grupo');
                $sheet->setCellValue('E8', 'Cantidad');

                // Data rows
                $excel_data_row_offset = 9;
                foreach ($data_table as $i => $row) {
                    $rowIndex = $i + $excel_data_row_offset;
                    $sheet->setCellValue('B' . $rowIndex, $row['titulo_r']);
                    $sheet->setCellValue('C' . $rowIndex, $row['eje_r']);
                    $sheet->setCellValue('D' . $rowIndex, $row['grupo_r']);
                    $sheet->setCellValue('E' . $rowIndex, $row['cantidad_r']);
                }

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $return_file_name_excel = 'Tabla_datos_Registro_Incidente_[' . uniqid('', true) . '].xlsx';

                $excelDir = $this->getParameter('kernel.project_dir') . '/public/downloads';
                if (!is_dir($excelDir)) {
                    mkdir($excelDir, 0777, true);
                }
                $writer->save($excelDir . '/' . $return_file_name_excel);
            }
        } else {
            $arr_data_table = [];
            $fechaInicio = date('d-m-Y H:i:s', mktime(0, 0, 0, date('n') - 1, 1, date('Y')));
            $fechaTermino = date('d-m-Y H:i:s', mktime(23, 59, 59, date('n'), 0, date('Y')));
        }

        return $this->render('dashboard/siv/reportes/registro_incidente/get_registro_incidente.html.twig', [
            'renderTo' => 'html',
            'data_table' => $arr_data_table,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'return_file_name_pdf' => $return_file_name_pdf,
            'return_file_name_excel' => $return_file_name_excel,
        ]);
    }

    /**
     * Generate Excel export for Atenciones por Clase de Vehículo
     * Following legacy pattern from old SivController.php lines 1749-1847
     */
    private function generateAtencionesClaseVehiculoExcel(
        array $data_table,
        string $fechaInicio,
        string $fechaTermino,
        array $m_tipo_contingencia,
        array $p_tipo_contingencia,
        array $m_categorias_vehiculos,
        array $p_categorias_vehiculos,
        string $p_ruta,
        string $p_sentido,
        string $p_km_ini,
        string $p_km_fin
    ): Response {
        // Build human-readable filter descriptions
        $human_params_contingencias = '';
        if (count($m_tipo_contingencia) === count($p_tipo_contingencia)) {
            $human_params_contingencias = 'Todas';
        } else {
            $p_tipo_contingencia_implode = implode(", ", $p_tipo_contingencia);
            $human_params_contingencias = (strlen($p_tipo_contingencia_implode) > 83)
                ? substr($p_tipo_contingencia_implode, 0, 84) . '...'
                : $p_tipo_contingencia_implode;
        }

        $human_params_vehiculos = '';
        if (count($m_categorias_vehiculos) === count($p_categorias_vehiculos)) {
            $human_params_vehiculos = 'Todas';
        } else {
            $p_categorias_vehiculos_implode = implode(", ", $p_categorias_vehiculos);
            $human_params_vehiculos = (strlen($p_categorias_vehiculos_implode) > 47)
                ? substr($p_categorias_vehiculos_implode, 0, 47) . '...'
                : $p_categorias_vehiculos_implode;
        }

        $human_params_eje = match($p_ruta) {
            '1' => 'Costanera',
            '2' => 'Kennedy',
            '10' => 'AMB',
            default => 'Todos'
        };

        $human_params_sentido = match($p_sentido) {
            'OP' => 'Oriente-Poniente',
            'PO' => 'Poniente-Oriente',
            default => 'Todos'
        };

        $human_params = "Periodo: desde $fechaInicio" . ($fechaTermino ? " al $fechaTermino" : '') .
            " - Contingencias: $human_params_contingencias - Clases de Vehículos: $human_params_vehiculos" .
            " - Eje: $human_params_eje - Sentido: $human_params_sentido - Km Desde: $p_km_ini - Km Hasta: $p_km_fin";

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set metadata
        $spreadsheet->getProperties()
            ->setCreator("Sistema de reportes SIV")
            ->setTitle("Atenciones por Clase de Vehículo");

        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(1);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(19);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(30);

        // Row heights
        $sheet->getRowDimension(1)->setRowHeight(50);

        // Merge cells for header
        $sheet->mergeCells('C1:E1');
        $sheet->getStyle("C1:E1")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // Style for header row (row 8)
        $sheet->getStyle('B8:G8')->getFont()->setBold(true);
        $sheet->getStyle('B8:G8')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Set header values
        $sheet->setCellValue('C1', 'Sociedad Concesionaria Costanera Norte')
            ->setCellValue('B3', 'Atenciones por Clase de Vehículo')
            ->setCellValue('B4', 'Obra concesionada: Autopista Costanera Norte')
            ->setCellValue('B5', $human_params)
            ->setCellValue('B6', 'Total: ' . count($data_table))
            ->setCellValue('P6', date('d-m-Y H:i:s'))
            ->setCellValue('B8', 'Incidente')
            ->setCellValue('C8', 'Fecha Hora')
            ->setCellValue('D8', 'Eje')
            ->setCellValue('E8', 'Sentido')
            ->setCellValue('F8', 'Cód Contingencia')
            ->setCellValue('G8', 'Contingencia');

        $idx_rw_titles = 8;
        $idx_rw = $idx_rw_titles + 1;

        // Array of column letters
        $arr_columns_abc = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'];

        // Write data rows
        for ($i = 0; $i < count($data_table); $i++) {
            $row_data_index = $idx_rw + $i;

            // Convert date to Excel format
            $fecha_hora_r = new \DateTime($data_table[$i]['fecha_hora_r']);
            $fecha_hora_r_excel = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($fecha_hora_r);
            $sheet->getStyle('C' . $row_data_index)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:mm');

            // Set fixed columns
            $sheet->setCellValue('B' . $row_data_index, $data_table[$i]['incidente_r'])
                ->setCellValueExplicit('C' . $row_data_index, $fecha_hora_r_excel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC)
                ->setCellValue('D' . $row_data_index, $data_table[$i]['eje_r'])
                ->setCellValue('E' . $row_data_index, $data_table[$i]['direccion_r'])
                ->setCellValue('F' . $row_data_index, $data_table[$i]['cod_contingencia_r'])
                ->setCellValue('G' . $row_data_index, $data_table[$i]['contingencia_r']);

            // Dynamic columns start at position 7 (column H)
            $index_columns_var = 7;

            // Add dynamic vehicle type columns (only on first row for headers)
            if ($i === 0) {
                if (array_key_exists('CAMB', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Ambulancia');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CAMB']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(12);
                    $index_columns_var++;
                }
                if (array_key_exists('C8', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Ambulancia Ext.');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['C8']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(15);
                    $index_columns_var++;
                }
                if (array_key_exists('C7', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Barredora');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['C7']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(12);
                    $index_columns_var++;
                }
                if (array_key_exists('CEXT', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Externo');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CEXT']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(12);
                    $index_columns_var++;
                }
                if (array_key_exists('CGER', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Gerente del área');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CGER']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(15);
                    $index_columns_var++;
                }
                if (array_key_exists('CGRU', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Grúa');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CGRU']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(10);
                    $index_columns_var++;
                }
                if (array_key_exists('C2', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Grúa Externa');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['C2']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(12);
                    $index_columns_var++;
                }
                if (array_key_exists('CMAN', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Mantención');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CMAN']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(12);
                    $index_columns_var++;
                }
                if (array_key_exists('CMSV', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Paramédico (ST)');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CMSV']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(15);
                    $index_columns_var++;
                }
                if (array_key_exists('CPAT', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Patrulla');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CPAT']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(10);
                    $index_columns_var++;
                }
                if (array_key_exists('C9', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Prensa');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['C9']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(10);
                    $index_columns_var++;
                }
                if (array_key_exists('CRES', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Rescate');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CRES']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(10);
                    $index_columns_var++;
                }
                if (array_key_exists('CCSV', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $idx_rw_titles, 'Supervisor Vial');
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var] . $row_data_index, $data_table[$i]['CCSV']);
                    $sheet->getColumnDimension($arr_columns_abc[$index_columns_var])->setWidth(15);
                }
            } else {
                // For subsequent rows, only write values (headers already set)
                $index_columns_var = 7;
                if (array_key_exists('CAMB', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CAMB']);
                }
                if (array_key_exists('C8', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['C8']);
                }
                if (array_key_exists('C7', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['C7']);
                }
                if (array_key_exists('CEXT', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CEXT']);
                }
                if (array_key_exists('CGER', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CGER']);
                }
                if (array_key_exists('CGRU', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CGRU']);
                }
                if (array_key_exists('C2', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['C2']);
                }
                if (array_key_exists('CMAN', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CMAN']);
                }
                if (array_key_exists('CMSV', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CMSV']);
                }
                if (array_key_exists('CPAT', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CPAT']);
                }
                if (array_key_exists('C9', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['C9']);
                }
                if (array_key_exists('CRES', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CRES']);
                }
                if (array_key_exists('CCSV', $data_table[$i])) {
                    $sheet->setCellValue($arr_columns_abc[$index_columns_var++] . $row_data_index, $data_table[$i]['CCSV']);
                }
            }
        }

        // Set sheet title
        $sheet->setTitle('Atenciones Clase Vehículo');
        $spreadsheet->setActiveSheetIndex(0);

        // Create response
        $response = new StreamedResponse(function() use ($spreadsheet) {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        });

        $filename = 'Atenciones_Clase_Vehiculo_' . date('Y-m-d_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename
        ));
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    /**
     * Helper method to convert date from DD-MM-YYYY HH:mm:ss to YYYY-MM-DD HH:mm:ss
     * Uses mktime() for timezone consistency with legacy system
     */
    private function getDate(string $dateString): string
    {
        if (empty($dateString)) {
            return '';
        }

        // Parse DD-MM-YYYY HH:mm:ss format (legacy compatible with substr)
        $year = substr($dateString, 6, 4);
        $month = substr($dateString, 3, 2);
        $day = substr($dateString, 0, 2);
        $hours = substr($dateString, 11, 2);
        $minutes = substr($dateString, 14, 2);
        $seconds = substr($dateString, 17, 2);

        // Use mktime() like legacy for timezone consistency
        // mktime handles timezone adjustments automatically
        return date("Y-m-d H:i:s", mktime($hours, $minutes, $seconds, $month, $day, $year));
    }

    /**
     * Sanitize string for file names - removes special characters and accents
     */
    private function sanear_string(string $string): string
    {
        $string = trim($string);

        // Remove accents
        $string = str_replace(
            ['á', 'à', 'ä', 'â', 'ª', 'Á', 'À', 'Â', 'Ä'],
            ['a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A'],
            $string
        );

        $string = str_replace(
            ['é', 'è', 'ë', 'ê', 'É', 'È', 'Ê', 'Ë'],
            ['e', 'e', 'e', 'e', 'E', 'E', 'E', 'E'],
            $string
        );

        $string = str_replace(
            ['í', 'ì', 'ï', 'î', 'Í', 'Ì', 'Ï', 'Î'],
            ['i', 'i', 'i', 'i', 'I', 'I', 'I', 'I'],
            $string
        );

        $string = str_replace(
            ['ó', 'ò', 'ö', 'ô', 'Ó', 'Ò', 'Ö', 'Ô'],
            ['o', 'o', 'o', 'o', 'O', 'O', 'O', 'O'],
            $string
        );

        $string = str_replace(
            ['ú', 'ù', 'ü', 'û', 'Ú', 'Ù', 'Û', 'Ü'],
            ['u', 'u', 'u', 'u', 'U', 'U', 'U', 'U'],
            $string
        );

        $string = str_replace(
            ['ñ', 'Ñ', 'ç', 'Ç'],
            ['n', 'N', 'c', 'C'],
            $string
        );

        // Remove special characters
        $string = str_replace(
            ["*", "\\", "¨", "º", "~", "#", "@", "!", "\"", "·", "$", "%", "&", "/", "(", ")", "?", "'", "¡", "¿", "^", "<code>", "+", "}", "{", "¨", "´", ">", "<", ";", ",", ":"],
            '',
            $string
        );

        return $string;
    }

    #[AdminRoute('/ficha_accidente', name: 'siv_dashboard_ficha_accidente')]
    #[IsGranted('ROLE_USER')]
    public function fichaAccidenteAction(Request $request): Response
    {
        set_time_limit(0);
        $em = $this->doctrine->getManager('siv');

        $fechaInicio = $request->query->get('fechaInicio', date('d-m-Y H:i:s', strtotime('-30 days')));
        $fechaFin = $request->query->get('fechaTermino', date('d-m-Y H:i:s'));
        $fraIds = $request->query->get('fra_ids', '');

        $fichas = [];

        if ($fechaInicio && $fechaFin) {
            $fechaInicioDate = $this->getDate($fechaInicio);
            $fechaFinDate = $this->getDate($fechaFin);

            try {
                $sql = "SELECT * FROM fn_ficha_Incidente('', :fechaInicio, :fechaFin) WHERE imagenes != '[]'";
                $stmt = $em->getConnection()->prepare($sql);
                $result = $stmt->executeQuery([
                    'fechaInicio' => $fechaInicioDate,
                    'fechaFin' => $fechaFinDate
                ]);
                $fichas = $result->fetchAllAssociative();
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error al obtener fichas: ' . $e->getMessage());
            }
        }

        return $this->render('dashboard/siv/ficha_accidente.html.twig', [
            'fichas' => $fichas,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaFin,
            'fra_ids' => $fraIds
        ]);
    }

    #[AdminRoute('/atenciones_clase_vehiculo', name: 'siv_dashboard_atenciones_clase_vehiculo')]
    #[IsGranted('ROLE_USER')]
    public function atencionesClaseVehiculoAction(Request $request): Response
    {
        set_time_limit(0);
        $params = $request->query->all();

        $fechaInicio = $params['fechaInicio'] ?? '';
        $startDate = $fechaInicio ? $this->getDate($fechaInicio) : '';
        $fechaTermino = $params['fechaTermino'] ?? '';
        $endDate = $fechaTermino ? $this->getDate($fechaTermino) : '';

        $p_tipo_contingencia = $params['p_tipo_contingencia'] ?? ['all'];
        $p_categorias_vehiculos = $params['p_categorias_vehiculos'] ?? ['all'];
        $p_ruta = $params['p_ruta'] ?? "0";
        $p_sentido = $params['p_sentido'] ?? 'all';
        $p_km_ini = $params['p_km_ini'] ?? "0";
        $p_km_fin = $params['p_km_fin'] ?? "0";

        $limit = intval($params['filasPorPagina'] ?? 10);
        $page = intval($params['page'] ?? 1);
        $offset = ($limit * $page) - $limit;

        $results = [];
        $m_categorias_vehiculos = [];
        $m_tipo_contingencia = [];

        // SIEMPRE obtener listas para los selects (independiente de fechas - legacy pattern)
        $conn = $this->doctrine->getManager('siv')->getConnection();

        // Obtener categorías de vehículos
        $sql = "SELECT c_tip_mzz, t_des_tip_mzz FROM public.tbsy14_tip_mzz";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $m_categorias_vehiculos = $result->fetchAllAssociative();

        // Obtener tipos de contingencia
        $sql = "SELECT cod_contingencia, contingencia FROM tbsi02_tip_eve";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $m_tipo_contingencia = $result->fetchAllAssociative();

        // Solo ejecutar query principal si hay fechas
        if ($startDate && $endDate) {
            $p_tipo_contingencia_implode = count($m_tipo_contingencia) === count($p_tipo_contingencia) ? 'all' : implode(",", $p_tipo_contingencia);
            $p_categorias_vehiculos_implode = implode(",", $p_categorias_vehiculos);

            // Ejecutar función principal con cursores (legacy pattern - direct interpolation)
            $conn->beginTransaction();
            try {
                $sql = "SELECT fn_reporte_Acumulado_atencion_incidentes_clase_vehiculo_V3(
                    'a','b','$startDate','$endDate','$p_categorias_vehiculos_implode','$p_sentido','$p_ruta','$p_km_ini','$p_km_fin',$offset,$limit,'$p_tipo_contingencia_implode'
                ) key_resultset";

                $stmt = $conn->prepare($sql);
                $result = $stmt->executeQuery();

                $cursors = $result->fetchAllAssociative();

                foreach($cursors as $v) {
                    $stmt = $conn->prepare('FETCH ALL IN "' . $v['key_resultset'] . '"');
                    $result = $stmt->executeQuery();
                    $results[$v['key_resultset']] = $result->fetchAllAssociative();
                }
                $conn->commit();
            } catch (\Exception $e) {
                $conn->rollBack();
                $this->addFlash('error', 'Error: ' . $e->getMessage());
            }
        }

        // Process JSON columns into separate fields (legacy pattern)
        $data_table = $results['a'] ?? [];
        $tmp_data_table = [];
        foreach ($data_table as $item) {
            if (isset($item['json_clases_r']) && !empty($item['json_clases_r'])) {
                $tmp_columns = json_decode($item['json_clases_r'], true);
                $columns = [];
                foreach ($tmp_columns as $key => $value) {
                    $columns['C' . $key] = $value;
                }
                array_push($tmp_data_table, array_merge($item, $columns));
            } else {
                array_push($tmp_data_table, $item);
            }
        }
        $data_table = $tmp_data_table;

        // Handle Excel export
        $export = $params['export'] ?? null;
        if ($export === 'excel' && !empty($data_table)) {
            return $this->generateAtencionesClaseVehiculoExcel(
                $data_table,
                $fechaInicio,
                $fechaTermino,
                $m_tipo_contingencia,
                $p_tipo_contingencia,
                $m_categorias_vehiculos,
                $p_categorias_vehiculos,
                $p_ruta,
                $p_sentido,
                $p_km_ini,
                $p_km_fin
            );
        }

        return $this->render('dashboard/siv/atenciones_clase_vehiculo.html.twig', [
            'data_table' => $data_table,
            'count_table' => $results['b'] ?? [],
            'm_categorias_vehiculos' => $m_categorias_vehiculos,
            'm_tipo_contingencia' => $m_tipo_contingencia,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'p_categorias_vehiculos' => $p_categorias_vehiculos,
            'p_tipo_contingencia' => $p_tipo_contingencia,
            'p_ruta' => $p_ruta,
            'p_sentido' => $p_sentido,
            'p_km_ini' => $p_km_ini,
            'p_km_fin' => $p_km_fin
        ]);
    }

    #[AdminRoute('/tiempos_recursos_externos', name: 'siv_dashboard_tiempos_recursos_externos')]
    #[IsGranted('ROLE_USER')]
    public function tiemposRecursosExternosAction(Request $request): Response
    {
        set_time_limit(0);
        $params = $request->query->all();

        // Limpiar fra_ids en búsquedas nuevas (ci=1) para evitar IDs cacheados de consultas anteriores
        $ci = $params['ci'] ?? null;
        $fra_ids = ($ci === '1') ? null : ($params['fra_ids'] ?? null);
        $generate_pdf = isset($params['generatePdf']) ? intval($params['generatePdf']) : 0;
        $generate_excel = isset($params['generateExcel']) ? intval($params['generateExcel']) : 0;
        $return_file_name_pdf = null;
        $return_file_name_excel = null;
        $arr_data_table = [];
        $data_averages = null;

        $arr_fra_ids = [];
        if ($fra_ids && $fra_ids !== 'fra_ids') {
            $arr_fra_ids = explode(',', $fra_ids);
        }

        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = $fechaInicio ? str_replace('-', '', $fechaInicio) : '';

        $data_table = [];

        // Ejecutar query si hay fecha (legacy pattern línea 2250-2260)
        if ($fechaInicio_Date) {
            $conn = $this->doctrine->getManager('siv')->getConnection();

            // Query legacy EXACTA (línea 2253): columnas simples para HTML
            $sql = "SELECT incidente_r, eje_r, pk_r, fechahora_r, a_r, b_r, c_r, s_r, tipo_evento_r, desc_tipo_evento_r
                    FROM fn_incidente_abc(:fechaInicio)";

            try {
                $stmt = $conn->prepare($sql);
                $result = $stmt->executeQuery(['fechaInicio' => $fechaInicio_Date]);
                $data_table = $result->fetchAllAssociative();
                $arr_data_table = $data_table;
            } catch (\Exception $e) {
                $this->logger->error('Error executing fn_incidente_abc: ' . $e->getMessage());
                $arr_data_table = [];
            }
        } else {
            // Default: mes anterior en formato m-Y (legacy pattern línea 2415)
            $fechaInicio = date('m-Y', mktime(0, 0, 0, date('n')-1, 1, date('Y')));
            $arr_data_table = [];
        }

        // Filtrar por registros seleccionados si hay fra_ids (legacy pattern línea 2222)
        if (!empty($arr_fra_ids)) {
            $arr_data_table = array_filter($arr_data_table, function($row) use ($arr_fra_ids) {
                return in_array($row['incidente_r'], $arr_fra_ids);
            });
            $arr_data_table = array_values($arr_data_table); // Re-indexar array
        }

        // Generar Excel si se solicitó
        if ($generate_excel === 1 && !empty($arr_data_table)) {
            try {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Configurar anchos de columnas (A es espaciador, columnas B-Q para 17 columnas)
                $sheet->getColumnDimension('A')->setWidth(2);
                $sheet->getColumnDimension('B')->setWidth(12);  // Incidente
                $sheet->getColumnDimension('C')->setWidth(25);  // Descripción
                $sheet->getColumnDimension('D')->setWidth(18);  // Eje
                $sheet->getColumnDimension('E')->setWidth(8);   // PK
                // 12 columnas de tiempos (F-Q)
                for ($col = 'F'; $col <= 'Q'; $col++) {
                    $sheet->getColumnDimension($col)->setWidth(8);
                }

                // Logos (Row 2-4)
                $sheet->getRowDimension(2)->setRowHeight(55);
                $sheet->getRowDimension(3)->setRowHeight(15);

                // Logo Concesionaria (PNG con transparencia)
                $logoPath1 = $this->getParameter('kernel.project_dir') . '/public/images/concessions/HCSQELGZDdo.png';
                if (file_exists($logoPath1)) {
                    $gdImage = imagecreatefrompng($logoPath1);
                    imagealphablending($gdImage, false);
                    imagesavealpha($gdImage, true);

                    $objDrawing = new MemoryDrawing();
                    $objDrawing->setImageResource($gdImage);
                    $objDrawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                    $objDrawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                    $objDrawing->setCoordinates('B2');
                    $objDrawing->setHeight(55);
                    $objDrawing->setWorksheet($sheet);
                }

                // Logo MOP (JPEG)
                $logoPath2 = $this->getParameter('kernel.project_dir') . '/public/images/coordinacion_concesiones_obras_publicas.jpg';
                if (file_exists($logoPath2)) {
                    $drawing2 = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                    $drawing2->setPath($logoPath2);
                    $drawing2->setCoordinates('O2');  // Movido a la derecha
                    $drawing2->setHeight(55);
                    $drawing2->setWorksheet($sheet);
                }

                // Título del reporte
                $row = 5;
                $sheet->mergeCells('B' . $row . ':Q' . $row);
                $sheet->setCellValue('B' . $row, 'TIEMPOS RECURSOS EXTERNOS - ACCIDENTES');
                $sheet->getStyle('B' . $row)->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Información del periodo
                $row = 6;
                $sheet->mergeCells('B' . $row . ':E' . $row);
                $sheet->setCellValue('B' . $row, 'Periodo: ' . $fechaInicio);
                $sheet->getStyle('B' . $row)->getFont()->setSize(10)->setBold(true);

                $sheet->mergeCells('F' . $row . ':J' . $row);
                $sheet->setCellValue('F' . $row, 'Total: ' . count($arr_data_table));
                $sheet->getStyle('F' . $row)->getFont()->setSize(10);

                $sheet->mergeCells('K' . $row . ':Q' . $row);
                $sheet->setCellValue('K' . $row, date('d-m-Y H:i:s'));
                $sheet->getStyle('K' . $row)->getFont()->setSize(10);
                $sheet->getStyle('K' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Headers Multi-nivel
                $row = 8;
                // Fila 1 de headers: Agrupaciones
                $sheet->setCellValue('B' . $row, 'Incidente');
                $sheet->setCellValue('C' . $row, 'Descripción');
                $sheet->setCellValue('D' . $row, 'Eje');
                $sheet->setCellValue('E' . $row, 'PK');

                // Agrupaciones de tiempos (Ambulancia, Bomberos, Carabineros, SML)
                $sheet->mergeCells('F' . $row . ':H' . $row);
                $sheet->setCellValue('F' . $row, 'Ambulancia (A)');
                $sheet->mergeCells('I' . $row . ':K' . $row);
                $sheet->setCellValue('I' . $row, 'Bomberos (B)');
                $sheet->mergeCells('L' . $row . ':N' . $row);
                $sheet->setCellValue('L' . $row, 'Carabineros (C)');
                $sheet->mergeCells('O' . $row . ':Q' . $row);
                $sheet->setCellValue('O' . $row, 'Serv. Médico Legal (S)');

                $sheet->getStyle('B' . $row . ':Q' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9D9D9']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                ]);

                // Fila 2 de headers: Sub-columnas de tiempos
                $row = 9;
                $sheet->setCellValue('B' . $row, '#');
                $sheet->setCellValue('C' . $row, 'Tipo');
                $sheet->setCellValue('D' . $row, 'Ubicación');
                $sheet->setCellValue('E' . $row, 'Km');

                // Sub-headers de tiempos (1→3, 2→3, 3→4) para cada recurso
                $timeHeaders = ['1→3', '2→3', '3→4'];
                $cols = ['F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q'];
                foreach ($cols as $idx => $col) {
                    $sheet->setCellValue($col . $row, $timeHeaders[$idx % 3]);
                }

                $sheet->getStyle('B' . $row . ':Q' . $row)->applyFromArray([
                    'font' => ['bold' => true, 'size' => 8],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E9ECEF']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                ]);
                $sheet->getRowDimension($row)->setRowHeight(18);

                // Datos de incidentes
                $row = 10;
                foreach ($arr_data_table as $item) {
                    $sheet->setCellValue('B' . $row, $item['incidente_r'] ?? '-');
                    $sheet->setCellValue('C' . $row, $item['desc_tipo_evento_r'] ?? '-');
                    $sheet->setCellValue('D' . $row, $item['eje_r'] ?? '-');
                    $sheet->setCellValue('E' . $row, $item['pk_r'] ?? '-');

                    // 12 columnas de tiempos (legacy structure)
                    $sheet->setCellValue('F' . $row, $item['a_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('G' . $row, $item['a_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('H' . $row, $item['a_time_3_a_4_r'] ?? 0);
                    $sheet->setCellValue('I' . $row, $item['b_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('J' . $row, $item['b_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('K' . $row, $item['b_time_3_a_4_r'] ?? 0);
                    $sheet->setCellValue('L' . $row, $item['c_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('M' . $row, $item['c_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('N' . $row, $item['c_time_3_a_4_r'] ?? 0);
                    $sheet->setCellValue('O' . $row, $item['s_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('P' . $row, $item['s_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('Q' . $row, $item['s_time_3_a_4_r'] ?? 0);

                    // Borders
                    $sheet->getStyle('B' . $row . ':Q' . $row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                    // Alternating fills
                    if ($row % 2 == 0) {
                        $sheet->getStyle('B' . $row . ':Q' . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8F9FA');
                    }

                    $row++;
                }

                // Fila de promedios (si existe)
                if ($data_averages !== null) {
                    $row++; // Saltar una fila
                    $sheet->mergeCells('B' . $row . ':E' . $row);
                    $sheet->setCellValue('B' . $row, 'PROMEDIO');
                    $sheet->getStyle('B' . $row)->getFont()->setBold(true);
                    $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Promedios de tiempos
                    $sheet->setCellValue('F' . $row, $data_averages['a_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('G' . $row, $data_averages['a_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('H' . $row, $data_averages['a_time_3_a_4_r'] ?? 0);
                    $sheet->setCellValue('I' . $row, $data_averages['b_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('J' . $row, $data_averages['b_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('K' . $row, $data_averages['b_time_3_a_4_r'] ?? 0);
                    $sheet->setCellValue('L' . $row, $data_averages['c_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('M' . $row, $data_averages['c_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('N' . $row, $data_averages['c_time_3_a_4_r'] ?? 0);
                    $sheet->setCellValue('O' . $row, $data_averages['s_time_1_a_3_r'] ?? 0);
                    $sheet->setCellValue('P' . $row, $data_averages['s_time_2_a_3_r'] ?? 0);
                    $sheet->setCellValue('Q' . $row, $data_averages['s_time_3_a_4_r'] ?? 0);

                    $sheet->getStyle('B' . $row . ':Q' . $row)->applyFromArray([
                        'font' => ['bold' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFE699']],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
                    ]);
                }

                // Guardar archivo
                $fileName = 'Tiempos_Recursos_Externos_' . date('Y-m-d_His') . '.xlsx';
                $downloadsDir = $this->getParameter('kernel.project_dir') . '/public/downloads';

                if (!is_dir($downloadsDir)) {
                    mkdir($downloadsDir, 0777, true);
                }

                $writer = new Xlsx($spreadsheet);
                $writer->save($downloadsDir . '/' . $fileName);
                $return_file_name_excel = $fileName;

            } catch (\Exception $e) {
                $this->logger->error('Error generating Excel: ' . $e->getMessage());
            }
        }

        // Generar PDF si se solicitó
        if ($generate_pdf === 1 && !empty($arr_data_table)) {
            try {
                $html = $this->renderView('dashboard/siv/tiempos_recursos_externos_pdf.html.twig', [
                    'data_table' => $arr_data_table,
                    'total_count' => count($arr_data_table),
                    'filters' => [
                        'fechaInicio' => $fechaInicio
                    ]
                ]);

                $fileName = 'Tiempos_Recursos_Externos_' . date('Y-m-d_His') . '.pdf';
                $downloadsDir = $this->getParameter('kernel.project_dir') . '/public/downloads';

                if (!is_dir($downloadsDir)) {
                    mkdir($downloadsDir, 0777, true);
                }

                $this->pdfGenerator->generateFromHtml(
                    $html,
                    $downloadsDir . '/' . $fileName,
                    [
                        'page-size' => 'A4',
                        'orientation' => 'Landscape',
                        'margin-top' => 4,   // Legacy margins
                        'margin-right' => 1,
                        'margin-bottom' => 5,
                        'margin-left' => 1,
                    ]
                );

                $return_file_name_pdf = $fileName;

            } catch (\Exception $e) {
                $this->logger->error('Error generating PDF: ' . $e->getMessage());
            }
        }

        // AJAX response
        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode($arr_data_table), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        // Renderizar HTML con datos y archivos generados
        // CRÍTICO: fra_ids siempre vacío para evitar auto-selección de registros
        return $this->render('dashboard/siv/tiempos_recursos_externos.html.twig', [
            'data_table' => $arr_data_table,
            'fechaInicio' => $fechaInicio,
            'fechaInicio_Date' => $fechaInicio_Date,
            'return_file_name_pdf' => $return_file_name_pdf,
            'return_file_name_excel' => $return_file_name_excel,
            'fra_ids' => '',  // Siempre vacío en vista principal
            'arr_fra_ids' => []  // Siempre vacío en vista principal
        ]);
    }

    #[AdminRoute('/report_tiempos_recursos_externos', name: 'siv_dashboard_report_tiempos_recursos_externos')]
    #[IsGranted('ROLE_USER')]
    public function reportTiemposRecursosExternosAction(Request $request): Response
    {
        set_time_limit(0);

        // Acepta POST y GET (legacy pattern línea 2431-2434)
        $params = $request->getMethod() === 'POST' ?
                  $request->request->all() :
                  $request->query->all();

        $action = $params['action'] ?? null;
        $fra_ids = $params['fra_ids'] ?? '';
        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = str_replace('-', '', $fechaInicio);

        $arr_data_table = [];

        // Query con stored function específico para IDs seleccionados (legacy línea 2472-2477)
        if ($fechaInicio_Date && $fra_ids && $fra_ids !== 'fra_ids') {
            $conn = $this->doctrine->getManager('siv')->getConnection();
            $sql = "SELECT incidente_r, eje_r, pk_r,
                    a_time_1_a_3_r, a_time_2_a_3_r, a_time_3_a_4_r,
                    b_time_1_a_3_r, b_time_2_a_3_r, b_time_3_a_4_r,
                    c_time_1_a_3_r, c_time_2_a_3_r, c_time_3_a_4_r,
                    s_time_1_a_3_r, s_time_2_a_3_r, s_time_3_a_4_r,
                    tipo_evento_r, desc_tipo_evento_r
                    FROM fn_calculo_tiempo_incidente_abc(:fra_ids, :fechaInicio_Date)";

            try {
                $stmt = $conn->prepare($sql);
                $result = $stmt->executeQuery([
                    'fra_ids' => $fra_ids,
                    'fechaInicio_Date' => $fechaInicio_Date
                ]);
                $arr_data_table = $result->fetchAllAssociative();
            } catch (\Exception $e) {
                $this->logger->error('Error executing fn_calculo_tiempo_incidente_abc: ' . $e->getMessage());
                $arr_data_table = [];
            }
        }

        // Formatear human_params para reporte (legacy línea 2498-2502)
        $rpt_title = "Tiempos recursos externos";
        $year = substr($fechaInicio, 3, 4);
        $month = substr($fechaInicio, 0, 2);

        // Meses en español (más confiable que setlocale/strftime)
        $meses_es = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
        ];
        $mes_nombre = $meses_es[$month] ?? 'Mes desconocido';
        $human_date = "$mes_nombre del $year";
        $human_params = "Periodo: $human_date";

        // Acción: preview (legacy línea 2504-2514)
        if ($action == 'preview') {
            $response = $this->render('dashboard/siv/tiempos_recursos_externos_report.html.twig', [
                'renderTo' => 'html',
                'data_table' => $arr_data_table,
                'fechaInicio' => $fechaInicio,
                'human_params' => $human_params,
                'rpt_title' => $rpt_title,
                'fra_ids' => $fra_ids
            ]);

            // Headers anti-caché para evitar que el navegador cachee la respuesta
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');

            return $response;
        }
        // Acción: excel usando TEMPLATE (legacy línea 2516-2604)
        elseif ($action == 'excel') {
            try {
                $file_name = "rpt_tmp_rec_ext_acc.xlsx";
                $template_path = $this->getParameter('siv_templates_directory');
                $template_file = $template_path . '/' . $file_name;

                // Cargar template Excel existente
                $spreadsheet = IOFactory::load($template_file);
                $sheet = $spreadsheet->getActiveSheet();

                $total_count_rows_data_table = count($arr_data_table);

                // Rellenar celdas fijas del template
                $sheet->setCellValue('B5', $human_params)
                      ->setCellValue('B6', 'Total: ' . ($total_count_rows_data_table > 0 ? $total_count_rows_data_table - 1 : 0))
                      ->setCellValue('P6', date('d-m-Y H:i:s'));

                // Insertar datos dinámicamente (legacy línea 2536-2577)
                $idx_rw_titles = 11;
                $idx_rw = $idx_rw_titles + 1;

                for ($i = 0; $i < $total_count_rows_data_table; $i++) {
                    if ($arr_data_table[$i]['incidente_r'] != '999999') {
                        if ($idx_rw > $idx_rw_titles + 1) {
                            $sheet->insertNewRowBefore($idx_rw, 1);
                        }
                        // 16 columnas B-Q
                        $sheet->setCellValue('B' . $idx_rw, $arr_data_table[$i]['incidente_r'])
                              ->setCellValue('C' . $idx_rw, $arr_data_table[$i]['desc_tipo_evento_r'])
                              ->setCellValue('D' . $idx_rw, $arr_data_table[$i]['eje_r'])
                              ->setCellValue('E' . $idx_rw, $arr_data_table[$i]['pk_r'])
                              ->setCellValue('F' . $idx_rw, $arr_data_table[$i]['a_time_1_a_3_r'])
                              ->setCellValue('G' . $idx_rw, $arr_data_table[$i]['a_time_2_a_3_r'])
                              ->setCellValue('H' . $idx_rw, $arr_data_table[$i]['a_time_3_a_4_r'])
                              ->setCellValue('I' . $idx_rw, $arr_data_table[$i]['b_time_1_a_3_r'])
                              ->setCellValue('J' . $idx_rw, $arr_data_table[$i]['b_time_2_a_3_r'])
                              ->setCellValue('K' . $idx_rw, $arr_data_table[$i]['b_time_3_a_4_r'])
                              ->setCellValue('L' . $idx_rw, $arr_data_table[$i]['c_time_1_a_3_r'])
                              ->setCellValue('M' . $idx_rw, $arr_data_table[$i]['c_time_2_a_3_r'])
                              ->setCellValue('N' . $idx_rw, $arr_data_table[$i]['c_time_3_a_4_r'])
                              ->setCellValue('O' . $idx_rw, $arr_data_table[$i]['s_time_1_a_3_r'])
                              ->setCellValue('P' . $idx_rw, $arr_data_table[$i]['s_time_2_a_3_r'])
                              ->setCellValue('Q' . $idx_rw, $arr_data_table[$i]['s_time_3_a_4_r']);
                        $idx_rw++;
                    } else {
                        // Fila de promedios (incidente 999999)
                        $avgx_rw = $idx_rw + 1;
                        $sheet->setCellValue('F' . $avgx_rw, $arr_data_table[$i]['a_time_1_a_3_r'])
                              ->setCellValue('G' . $avgx_rw, $arr_data_table[$i]['a_time_2_a_3_r'])
                              ->setCellValue('H' . $avgx_rw, $arr_data_table[$i]['a_time_3_a_4_r'])
                              ->setCellValue('I' . $avgx_rw, $arr_data_table[$i]['b_time_1_a_3_r'])
                              ->setCellValue('J' . $avgx_rw, $arr_data_table[$i]['b_time_2_a_3_r'])
                              ->setCellValue('K' . $avgx_rw, $arr_data_table[$i]['b_time_3_a_4_r'])
                              ->setCellValue('L' . $avgx_rw, $arr_data_table[$i]['c_time_1_a_3_r'])
                              ->setCellValue('M' . $avgx_rw, $arr_data_table[$i]['c_time_2_a_3_r'])
                              ->setCellValue('N' . $avgx_rw, $arr_data_table[$i]['c_time_3_a_4_r'])
                              ->setCellValue('O' . $avgx_rw, $arr_data_table[$i]['s_time_1_a_3_r'])
                              ->setCellValue('P' . $avgx_rw, $arr_data_table[$i]['s_time_2_a_3_r'])
                              ->setCellValue('Q' . $avgx_rw, $arr_data_table[$i]['s_time_3_a_4_r']);
                    }
                }

                // Guardar archivo (legacy línea 2580-2586)
                $sheet->setTitle('Reporte');
                $spreadsheet->setActiveSheetIndex(0);

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $return_file_name = $rpt_title . '_' . uniqid('', true) . '.xlsx';
                $outputDir = $this->getParameter('kernel.project_dir') . '/public/downloads';

                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0777, true);
                }

                $outputPath = $outputDir . '/' . $return_file_name;
                $writer->save($outputPath);

                // Retornar archivo (legacy línea 2588-2598)
                $response = new BinaryFileResponse($outputPath);
                $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; charset=utf-8');
                $response->headers->set('Content-Disposition', 'attachment;filename="' . $return_file_name . '"');
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');

                return $response;

            } catch (\Exception $e) {
                $this->logger->error('Error generating Excel: ' . $e->getMessage());
                return new Response("Error al generar el archivo Excel: " . $e->getMessage(), 500);
            }
        }
        // Acción: pdf (legacy línea 2606-2657)
        elseif ($action == 'pdf') {
            try {
                $html = $this->renderView('dashboard/siv/tiempos_recursos_externos_pdf.html.twig', [
                    'renderTo' => 'pdf',
                    'data_table' => $arr_data_table,
                    'fechaInicio' => $fechaInicio,
                    'human_params' => $human_params,
                    'rpt_title' => $rpt_title
                ]);

                $return_file_name_pdf = 'Tiempos_Recursos_Externos_' . uniqid('', true) . '.pdf';
                $outputDir = $this->getParameter('kernel.project_dir') . '/public/downloads';

                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0777, true);
                }

                $outputPath = $outputDir . '/' . $return_file_name_pdf;

                $this->pdfGenerator->generateFromHtml($html, $outputPath, [
                    'page-size' => 'A4',
                    'orientation' => 'Landscape',
                    'margin-top' => 4,
                    'margin-right' => 4,
                    'margin-bottom' => 6,
                    'margin-left' => 4
                ]);

                $response = new BinaryFileResponse($outputPath);
                $response->headers->set('Content-Type', 'application/pdf; charset=utf-8');
                $response->headers->set('Content-Disposition', 'attachment;filename="' . $return_file_name_pdf . '"');
                $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
                $response->headers->set('Pragma', 'no-cache');
                $response->headers->set('Expires', '0');

                return $response;

            } catch (\Exception $e) {
                $this->logger->error('Error generating PDF: ' . $e->getMessage());
                return new Response("Error al generar el archivo PDF: " . $e->getMessage(), 500);
            }
        }

        return new Response("Acción no válida", 400);
    }

    #[AdminRoute('/tiempos_respuesta_recursos', name: 'siv_dashboard_tiempos_respuesta_recursos')]
    #[IsGranted('ROLE_USER')]
    public function tiemposRespuestaRecursosAction(Request $request): Response
    {
        set_time_limit(0);
        $params = $request->query->all();

        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = $fechaInicio ? $this->getDate($fechaInicio) : '';
        $fechaTermino = $params['fechaTermino'] ?? '';
        $fechaTermino_Date = $fechaTermino ? $this->getDate($fechaTermino) : '';

        $p_tipo_contingencia = $params['p_tipo_contingencia'] ?? ['all'];
        $p_ruta = $params['p_ruta'] ?? false;
        $p_sentido = ($params['p_sentido'] ?? 'all') != 'all' ? $params['p_sentido'] : false;
        $p_km_ini = $params['p_km_ini'] ?? false;
        $p_km_fin = $params['p_km_fin'] ?? false;

        $arr_data_table = [];
        $m_tipo_contingencia = [];

        $conn = $this->doctrine->getManager('siv')->getConnection();

        // Obtener tipos de contingencia
        $sql = "SELECT cod_contingencia, contingencia FROM tbsi02_tip_eve";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $m_tipo_contingencia = $result->fetchAllAssociative();

        $where = '';
        if ($p_tipo_contingencia && count($m_tipo_contingencia) !== count($p_tipo_contingencia)) {
            $p_tipo_contingencia_str = "'" . implode("','", $p_tipo_contingencia) . "'";
            $where = "tipo_contingencia_evento IN ($p_tipo_contingencia_str)";
        }
        if ($p_ruta && $p_ruta !== '0') {
            $where .= ($where ? ' AND ' : '') . "ejes = '$p_ruta'";
        }
        if ($p_sentido) {
            $where .= ($where ? ' AND ' : '') . "sentido = '$p_sentido'";
        }
        if ($p_km_ini !== false && $p_km_fin !== false) {
            $where .= ($where ? ' AND ' : '') . "pk >= $p_km_ini AND pk <= $p_km_fin";
        }
        if ($where) {
            $where = 'WHERE ' . $where;
        }

        if ($fechaInicio_Date && $fechaTermino_Date) {
            $sql = "SELECT * FROM fn_reporte_accion_respuesta_v2(:fechaInicio, :fechaTermino) a $where LIMIT 5000";
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery([
                'fechaInicio' => $fechaInicio_Date,
                'fechaTermino' => $fechaTermino_Date
            ]);
            $arr_data_table = $result->fetchAllAssociative();
        }

        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode($arr_data_table), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        return $this->render('dashboard/siv/tiempos_respuesta_recursos.html.twig', [
            'data_table' => $arr_data_table,
            'm_tipo_contingencia' => $m_tipo_contingencia,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'p_tipo_contingencia' => $p_tipo_contingencia,
            'p_ruta' => $p_ruta,
            'p_sentido' => $p_sentido,
            'p_km_ini' => $p_km_ini,
            'p_km_fin' => $p_km_fin
        ]);
    }

    #[AdminRoute('/report_tiempos_respuesta_recursos', name: 'siv_dashboard_report_tiempos_respuesta_recursos')]
    #[IsGranted('ROLE_USER')]
    public function reportTiemposRespuestaRecursos(Request $request): Response
    {
        set_time_limit(0);
        $params = $request->query->all();
        $action = $params['action'] ?? null;
        $tipo_informe = $params['tipo_informe'] ?? null;

        // Parámetros de filtros
        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = $fechaInicio ? $this->getDate($fechaInicio) : '';
        $fechaTermino = $params['fechaTermino'] ?? '';
        $fechaTermino_Date = $fechaTermino ? $this->getDate($fechaTermino) : '';

        $p_tipo_contingencia = $params['p_tipo_contingencia'] ?? ['all'];
        $p_ruta = $params['p_ruta'] ?? false;
        $p_sentido = ($params['p_sentido'] ?? 'all') != 'all' ? $params['p_sentido'] : false;
        $p_km_ini = $params['p_km_ini'] ?? false;
        $p_km_fin = $params['p_km_fin'] ?? false;

        $conn = $this->doctrine->getManager('siv')->getConnection();

        // Obtener tipos de contingencia
        $sql = "SELECT cod_contingencia, contingencia FROM tbsi02_tip_eve";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $m_tipo_contingencia = $result->fetchAllAssociative();

        // Construir WHERE
        $where = '';
        if ($p_tipo_contingencia && count($m_tipo_contingencia) !== count($p_tipo_contingencia)) {
            $p_tipo_contingencia_str = "'" . implode("','", $p_tipo_contingencia) . "'";
            $where .= ($where ? " AND " : "") . " a.codigo_contingencia_r in ($p_tipo_contingencia_str)";
        }
        if ($p_ruta && $p_ruta !== '0') {
            $where .= ($where ? " AND " : "") . " a.eje_r = '$p_ruta'";
        }
        if ($p_sentido) {
            $where .= ($where ? " AND " : "") . " a.sentido_r = '$p_sentido'";
        }
        if ($p_km_ini !== false && $p_km_ini !== '') {
            $where .= ($where ? " AND " : "") . " a.pk_r >= $p_km_ini";
        }
        if ($p_km_fin !== false && $p_km_fin !== '') {
            $where .= ($where ? " AND " : "") . " a.pk_r <= $p_km_fin";
        }
        if ($where) {
            $where = 'where ' . $where;
        }

        if ($fechaInicio_Date && $fechaTermino_Date) {
            $sql = "SELECT * FROM fn_reporte_accion_respuesta_v2('$fechaInicio_Date','$fechaTermino_Date') a $where LIMIT 5000";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $data_table = $stmt->fetchAll();

            $rpt_title = "Tiempos de respuesta por recursos";
            $human_params = "Periodo: desde $fechaInicio" . ($fechaTermino ? " - al $fechaTermino" : '');

            if ($action == 'excel') {
                try {
                    $file_name = "rpt_tmp_rsp_v2.xlsx";
                    $template_path = $this->getParameter('kernel.project_dir') . '/public/files/templates';
                    $template_file = $template_path . '/' . $file_name;

                    if (!file_exists($template_file)) {
                        return new Response("Template Excel no encontrado: $template_file", 500);
                    }

                    $spreadsheet = IOFactory::load($template_file);
                    $sheet = $spreadsheet->getActiveSheet();

                    $total_count_rows_data_table = count($data_table);

                    $sheet->setCellValue('B3', $rpt_title)
                        ->setCellValue('B5', $human_params)
                        ->setCellValue('B6', 'Total: ' . $total_count_rows_data_table)
                        ->setCellValue('O6', date('d-m-Y H:i:s'));

                    if ($total_count_rows_data_table > 1) {
                        $sheet->insertNewRowBefore(11, $total_count_rows_data_table - 1);
                    }

                    $idx_rw_titles = 9;
                    $idx_rw = $idx_rw_titles + 1;

                    for ($i = 0; $i < $total_count_rows_data_table; $i++) {
                        if (($data_table[$i]['num_incidente_r'] ?? '') != '999999') {
                            $sheet->setCellValue('B' . $idx_rw, $data_table[$i]['num_incidente_r'] ?? '')
                                ->setCellValue('C' . $idx_rw, $data_table[$i]['fecha_creacion_r'] ?? '')
                                ->setCellValue('D' . $idx_rw, $data_table[$i]['pk_r'] ?? '')
                                ->setCellValue('E' . $idx_rw, $data_table[$i]['sentido_r'] ?? '')
                                ->setCellValue('F' . $idx_rw, $data_table[$i]['eje_r'] ?? '')
                                ->setCellValue('G' . $idx_rw, $data_table[$i]['calzada_r'] ?? '')
                                ->setCellValue('H' . $idx_rw, $data_table[$i]['descripcion_r'] ?? '')
                                ->setCellValue('I' . $idx_rw, $data_table[$i]['nombre_recurso_r'] ?? 'N/A')
                                ->setCellValue('J' . $idx_rw, $data_table[$i]['fecha_hora_r'] ?? '')
                                ->setCellValue('K' . $idx_rw, $data_table[$i]['fecha_hora_inicio_r'] ?? '')
                                ->setCellValue('L' . $idx_rw, $data_table[$i]['fecha_hora_fin_r'] ?? '')
                                ->setCellValue('M' . $idx_rw, $data_table[$i]['plan_contingencia_r'] ?? '')
                                ->setCellValue('N' . $idx_rw, $data_table[$i]['comentario_ini_r'] ?? '')
                                ->setCellValue('O' . $idx_rw, $data_table[$i]['comentario_fin_r'] ?? '');

                            $idx_rw++;
                        }
                    }

                    $path = $this->getParameter('kernel.project_dir') . '/public/downloads';
                    if (!is_dir($path)) {
                        mkdir($path, 0755, true);
                    }

                    $sheet->setTitle('Reporte');
                    $spreadsheet->setActiveSheetIndex(0);

                    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                    $return_file_name = $rpt_title . ' [' . uniqid('', true) . '].xlsx';
                    $writer->save($path . '/' . $return_file_name);

                    $file = $path . '/' . $return_file_name;
                    $response = new BinaryFileResponse($file);

                    $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    $response->headers->set('Content-Disposition', 'attachment;filename="' . $return_file_name . '"');
                    $response->headers->set('Cache-Control', 'no-store');

                    return $response;

                } catch (\Exception $e) {
                    return new Response("Error al generar el archivo Excel: " . $e->getMessage(), 500);
                }
            }
        }

        return new Response("Acción no válida o faltan parámetros", 400);
    }

    #[AdminRoute('/tiempos_respuesta_incidente', name: 'siv_dashboard_tiempos_respuesta_incidente')]
    #[IsGranted('ROLE_USER')]
    public function tiemposRespuestaIncidenteAction(Request $request): Response
    {
        set_time_limit(0);
        $params = $request->query->all();

        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = $fechaInicio ? $this->getDate($fechaInicio) : '';
        $fechaTermino = $params['fechaTermino'] ?? '';
        $fechaTermino_Date = $fechaTermino ? $this->getDate($fechaTermino) : '';

        $p_tipo_contingencia = $params['p_tipo_contingencia'] ?? ['all'];
        $p_ruta = $params['p_ruta'] ?? false;
        $p_sentido = ($params['p_sentido'] ?? 'all') != 'all' ? $params['p_sentido'] : false;
        $p_km_ini = $params['p_km_ini'] ?? false;
        $p_km_fin = $params['p_km_fin'] ?? false;

        $arr_data_table = [];
        $m_tipo_contingencia = [];

        $conn = $this->doctrine->getManager('siv')->getConnection();

        // Obtener tipos de contingencia
        $sql = "SELECT cod_contingencia, contingencia FROM tbsi02_tip_eve";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $m_tipo_contingencia = $result->fetchAllAssociative();

        $where = '';
        if ($p_tipo_contingencia && count($m_tipo_contingencia) !== count($p_tipo_contingencia)) {
            $p_tipo_contingencia_str = "'" . implode("','", $p_tipo_contingencia) . "'";
            $where = "tipo_contingencia_evento IN ($p_tipo_contingencia_str)";
        }
        if ($p_ruta) {
            $where .= ($where ? ' AND ' : '') . "ejes = $p_ruta";
        }
        if ($p_sentido) {
            $where .= ($where ? ' AND ' : '') . "sentido = '$p_sentido'";
        }
        if ($p_km_ini !== false && $p_km_fin !== false) {
            $where .= ($where ? ' AND ' : '') . "pk >= $p_km_ini AND pk <= $p_km_fin";
        }
        if ($where) {
            $where = 'WHERE ' . $where;
        }

        if ($fechaInicio_Date && $fechaTermino_Date) {
            // Misma función pero agrupada por incidente
            $sql = "SELECT * FROM fn_reporte_accion_respuesta_v2(:fechaInicio, :fechaTermino) a $where LIMIT 5000";
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery([
                'fechaInicio' => $fechaInicio_Date,
                'fechaTermino' => $fechaTermino_Date
            ]);
            $arr_data_table = $result->fetchAllAssociative();
        }

        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode($arr_data_table), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        return $this->render('dashboard/siv/tiempos_respuesta_incidente.html.twig', [
            'data_table' => $arr_data_table,
            'm_tipo_contingencia' => $m_tipo_contingencia,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'p_tipo_contingencia' => $p_tipo_contingencia,
            'p_ruta' => $p_ruta,
            'p_sentido' => $p_sentido,
            'p_km_ini' => $p_km_ini,
            'p_km_fin' => $p_km_fin
        ]);
    }

    #[AdminRoute('/historial_recursos', name: 'siv_dashboard_historial_recursos')]
    #[IsGranted('ROLE_USER')]
    public function historialRecursosAction(Request $request): Response
    {
        $em = $this->doctrine->getManager('siv');

        $fechaInicio = $request->query->get('fechaInicio', date('d-m-Y H:i:s', strtotime('-1 day')));
        $fechaTermino = $request->query->get('fechaTermino', date('d-m-Y H:i:s'));
        $itemsSelected = $request->query->all()['itemsSelected'] ?? [];

        // Obtener lista de todos los recursos disponibles
        $allItems = [];
        try {
            $sql = "SELECT codigo_recurso, descripcion FROM public.tbsy15_ana_mzz ORDER BY descripcion";
            $stmt = $em->getConnection()->prepare($sql);
            $result = $stmt->executeQuery();
            $allItems = $result->fetchAllAssociative();
        } catch (\Exception $e) {
            // Si no existe la tabla, usar datos de ejemplo
            $allItems = [
                ['codigo_recurso' => 'AMB-01', 'descripcion' => 'Ambulancia 01'],
                ['codigo_recurso' => 'BOM-01', 'descripcion' => 'Bomberos 01'],
                ['codigo_recurso' => 'CAR-01', 'descripcion' => 'Carabineros 01'],
                ['codigo_recurso' => 'GRU-01', 'descripcion' => 'Grúa 01']
            ];
        }

        // Preparar string de recursos seleccionados
        $strItems = '';
        if (is_array($itemsSelected) && !empty($itemsSelected)) {
            $strItems = implode(',', $itemsSelected);
        }

        $arrRegItems = '[]';
        $dataTable = [];

        if ($fechaInicio && $fechaTermino) {
            $fechaInicioDate = $this->getDate($fechaInicio);
            $fechaTerminoDate = $this->getDate($fechaTermino);

            try {
                // Llamar a la función que retorna JSON con v_json_grafico y v_json_excel
                $sql = "SELECT fn_historial_cambios_estado_recursos FROM public.fn_historial_cambios_estado_recursos(:fechaInicio, :fechaTermino, :recursos)";
                $stmt = $em->getConnection()->prepare($sql);
                $result = $stmt->executeQuery([
                    'fechaInicio' => $fechaInicioDate,
                    'fechaTermino' => $fechaTerminoDate,
                    'recursos' => $strItems
                ]);

                $jsonResult = $result->fetchOne();
                if ($jsonResult) {
                    $data = json_decode($jsonResult, true);
                    if (isset($data['v_json_grafico'])) {
                        $arrRegItems = json_encode($data['v_json_grafico']);
                    }
                    if (isset($data['v_json_excel'])) {
                        $dataTable = $data['v_json_excel'];
                    }
                }
            } catch (\Exception $e) {
                // Log el error real - NO mock data
                $this->logger->error('Error en fn_historial_cambios_estado_recursos: ' . $e->getMessage());

                // Mantener arrays vacíos - estamos migrando, no inventando
                $arrRegItems = '[]';
                $dataTable = [];
            }
        }

        // Para peticiones AJAX retornar JSON
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'arr_reg_items' => $arrRegItems,
                'data_table' => $dataTable
            ]);
        }

        return $this->render('dashboard/siv/historial_recursos.html.twig', [
            'arr_reg_items' => $arrRegItems,
            'all_items' => $allItems,
            'itemsSelected' => $itemsSelected,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'data_table' => $dataTable
        ]);
    }

    #[AdminRoute('/lista_permisos_trabajos', name: 'siv_dashboard_lista_permisos_trabajos')]
    #[IsGranted('ROLE_USER')]
    public function listaPermisosTrabajosAction(Request $request): Response
    {
        // Usar conexión 'default' (MySQL) como en legacy OtPermController.php línea 117
        $conn = $this->doctrine->getConnection('default');

        $fechaInicio = $request->query->get('fechaInicio', '');
        $fechaTermino = $request->query->get('fechaTermino', '');
        // Por defecto estados 0,1,2,3,4 (patrón legacy OtPermController.php línea 59)
        $regStatus = $request->query->all()['regStatus'] ?? ['0', '1', '2', '3', '4'];

        $permisos = [];

        // Siempre ejecutar query (patrón legacy - línea 116 del OtPermController.php)
        try {
            // Build WHERE clause
            $where = '';

            if ($fechaInicio) {
                $fechaInicioDate = $this->getDate($fechaInicio);
                if ($fechaInicioDate) {
                    $where = " IFNULL(a.fechahora_inicio_trabajo, a.fechahora_creacion_ot) >= '$fechaInicioDate'";
                }
            }

            if ($fechaTermino) {
                $fechaTerminoDate = $this->getDate($fechaTermino);
                if ($fechaTerminoDate) {
                    $where .= ($where ? " AND " : "") . " IFNULL(a.fechahora_fin_trabajo, fechahora_creacion_ot) <= '$fechaTerminoDate'";
                }
            }

                // Status filters
                $sqlRegStatus = $regStatus;
                if (!in_array('all', $sqlRegStatus, true)) {
                    if (($trashI = array_search('trash', $sqlRegStatus)) !== false) {
                        unset($sqlRegStatus[$trashI]);
                        $where .= ($where ? " AND " : "") . " a.reg_status = 0";
                    } else {
                        $where .= ($where ? " AND " : "") . " a.reg_status = 1";
                    }

                    if (count($sqlRegStatus)) {
                        $strSqlRegStatus = implode(",", $sqlRegStatus);
                        $where .= ($where ? " AND " : "") . " a.estado IN ($strSqlRegStatus)";
                    }
                }

                if ($where) {
                    $where = 'WHERE ' . $where;
                }

                $sql = "SELECT * FROM vi_permisos_de_trabajo a $where LIMIT 500";

                // Debug: log SQL para diagnóstico
                $this->logger->info('SQL Permisos de Trabajo: ' . $sql, [
                    'fechaInicio' => $fechaInicio,
                    'fechaTermino' => $fechaTermino,
                    'regStatus' => $regStatus
                ]);

                $stmt = $conn->prepare($sql);
                $result = $stmt->executeQuery();
                $permisos = $result->fetchAllAssociative();
            } catch (\Exception $e) {
                $this->logger->error('Error fetching permisos de trabajo: ' . $e->getMessage());
                $permisos = [];
            }

        // Convertir a JSON como patrón Bitácora
        $dataTableJson = json_encode($permisos);

        // Detectar acción PDF (patrón legacy línea 150)
        $action = $request->query->get('action', false);

        if ($action === 'pdf') {
            // Generar PDF con Knp Snappy (inyectado via DI)
            $this->pdfGenerator->setOption("encoding", 'UTF-8');
            $this->pdfGenerator->setOption("javascript-delay", 500);
            $this->pdfGenerator->setOption("page-size", 'A4');
            $this->pdfGenerator->setOption("margin-bottom", 6);
            $this->pdfGenerator->setOption("margin-left", 4);
            $this->pdfGenerator->setOption("margin-right", 4);
            $this->pdfGenerator->setOption("margin-top", 4);
            $this->pdfGenerator->setOption("orientation", 'Landscape');

            $html = $this->render('dashboard/siv/permisos_trabajos/get_lista_permisos_trabajos.html.pdf.twig', [
                'data_table' => $dataTableJson,
                'fechaInicio' => $fechaInicio,
                'fechaTermino' => $fechaTermino,
                'regStatus' => $regStatus,
                'renderPDF' => true
            ]);

            $filename = 'Lista_Permisos_Trabajo_' . date('Y-m-d_His') . '.pdf';

            return new Response(
                $this->pdfGenerator->getOutputFromHtml($html->getContent()),
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]
            );
        }

        return $this->render('dashboard/siv/lista_permisos_trabajos.html.twig', [
            'data_table' => $dataTableJson,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'regStatus' => $regStatus,
            'searchTxt' => $request->query->get('searchTxt', ''),
            'search' => $request->query->get('search', ''),
            'autoUpdate' => $request->query->getBoolean('actualizacionAutomatica', false),
            'autoUpdateInterval' => $request->query->get('points', 30)
        ]);
    }

    /**
     * Endpoint AJAX para obtener datos de permisos de trabajo (tabla actualizable)
     * Patrón: Thin Server, Rich Client - retorna HTML partial con datos
     */
    #[AdminRoute('/get_pt', name: 'siv_dashboard_get_pt')]
    #[IsGranted('ROLE_USER')]
    public function getPermisosOtAction(Request $request): Response
    {
        // Usar conexión 'default' (MySQL) como en legacy OtPermController.php línea 233
        $conn = $this->doctrine->getConnection('default');

        // Obtener parámetros
        $params = $request->getMethod() === 'POST' ? $request->request->all() : $request->query->all();
        $action = $params['action'] ?? false;
        // Por defecto estados 0,1,2,3,4 (patrón legacy OtPermController.php línea 59)
        $regStatus = (isset($params['regStatus']) && is_array($params['regStatus']) && count($params['regStatus']))
            ? $params['regStatus']
            : ['0', '1', '2', '3', '4'];
        $rowsPerPage = intval($params['filasPorPagina'] ?? $params['rowsPerPage'] ?? 500);

        // Build WHERE clause
        $where = '';
        if (isset($params['fechaInicio']) && $params['fechaInicio']) {
            $fechaInicio = $params['fechaInicio'];
            $fechaInicioDate = $this->getDate($fechaInicio);
            if ($fechaInicioDate) {
                $where = " IFNULL(a.fechahora_inicio_trabajo, a.fechahora_creacion_ot) >= '$fechaInicioDate'";
            }
        } else {
            $fechaInicio = '';
        }

        if (isset($params['fechaTermino']) && $params['fechaTermino']) {
            $fechaTermino = $params['fechaTermino'];
            $fechaTerminoDate = $this->getDate($fechaTermino);
            if ($fechaTerminoDate) {
                $where .= ($where ? " AND " : "") . " IFNULL(a.fechahora_fin_trabajo, fechahora_creacion_ot) <= '$fechaTerminoDate'";
            }
        } else {
            $fechaTermino = '';
        }

        // Status filters
        $sqlRegStatus = $regStatus;
        if (!in_array('all', $sqlRegStatus, true)) {
            if (($trashI = array_search('trash', $sqlRegStatus)) !== false) {
                unset($sqlRegStatus[$trashI]);
                $where .= ($where ? " AND " : "") . " a.reg_status = 0";
            } else {
                $where .= ($where ? " AND " : "") . " a.reg_status = 1";
            }

            if (count($sqlRegStatus)) {
                $strSqlRegStatus = implode(",", $sqlRegStatus);
                $where .= ($where ? " AND " : "") . " a.estado IN ($strSqlRegStatus)";
            }
        }

        // Execute query
        $dataTable = [];
        try {
            if ($where) {
                $where = 'WHERE ' . $where;
            }
            $sql = "SELECT * FROM vi_permisos_de_trabajo a $where LIMIT $rowsPerPage";

            // Debug: log SQL para diagnóstico
            $this->logger->info('SQL AJAX Permisos de Trabajo: ' . $sql, [
                'fechaInicio' => $params['fechaInicio'] ?? '',
                'fechaTermino' => $params['fechaTermino'] ?? '',
                'regStatus' => $regStatus
            ]);

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery();
            $dataTable = $result->fetchAllAssociative();
        } catch (\Exception $e) {
            $this->logger->error('Error in getPermisosOtAction: ' . $e->getMessage());
        }

        // Preparar datos para el template
        $dataTableJson = json_encode($dataTable);

        // Si es petición AJAX, retornar solo el contenedor de tabla
        if ($action === 'ajax') {
            return $this->render('dashboard/siv/permisos_trabajos/contenedor_tabla.html.twig', [
                'data_table' => $dataTableJson,
                'fechaInicio' => $fechaInicio,
                'fechaTermino' => $fechaTermino,
                'regStatus' => $regStatus,
                'searchTxt' => $params['searchTxt'] ?? '',
                'toolbarIsToggle' => ($params['toolbarIsToggle'] ?? 'false') === 'true',
                'tableIsFullscreen' => ($params['tableIsFullscreen'] ?? 'false') === 'true',
                'search' => $params['search'] ?? '',
                'autoUpdate' => ($params['autoUpdate'] ?? 'false') === 'true',
                'autoUpdateInterval' => $params['autoUpdateInterval'] ?? 30
            ]);
        }

        // Preparar datos de selects (TODO: cargar desde BD)
        $arr_empresas = [
            ['id' => 1, 'nombre' => 'Empresa Ejemplo 1'],
            ['id' => 2, 'nombre' => 'Empresa Ejemplo 2'],
            ['id' => 3, 'nombre' => 'Empresa Ejemplo 3'],
        ];

        $arr_personas = [
            ['id' => 'e1', 'rut' => '12345678-9', 'nombres' => 'Juan', 'apellidos' => 'Pérez', 'autoriza_ot' => false],
            ['id' => 'e2', 'rut' => '98765432-1', 'nombres' => 'María', 'apellidos' => 'González', 'autoriza_ot' => false],
            ['id' => 'i1', 'rut' => '11111111-1', 'nombres' => 'Pedro', 'apellidos' => 'Martínez', 'autoriza_ot' => true],
            ['id' => 'i2', 'rut' => '22222222-2', 'nombres' => 'Ana', 'apellidos' => 'López', 'autoriza_ot' => true],
        ];

        $arr_ub = [
            ['id' => 1, 'nombre' => 'Km 15 Calzada Norte'],
            ['id' => 2, 'nombre' => 'Km 20 Calzada Sur'],
            ['id' => 3, 'nombre' => 'Sector Central'],
        ];

        // Si es acción 'add', retornar formulario vacío
        if ($action === 'add') {
            return $this->render('dashboard/siv/permisos_trabajos/add.html.twig', [
                'permiso' => null,  // null para indicar modo CREATE (no EDIT)
                'arr_empresas' => $arr_empresas,
                'arr_personas' => $arr_personas,
                'arr_ub' => $arr_ub
            ]);
        }

        // Si es acción 'edit', cargar datos del permiso y retornar formulario
        if ($action === 'edit') {
            $id = $params['id'] ?? null;
            if ($id) {
                // Cargar datos reales desde la base de datos
                try {
                    $sql = "SELECT * FROM vi_permisos_de_trabajo WHERE id = :id LIMIT 1";
                    $stmt = $conn->prepare($sql);
                    $result = $stmt->executeQuery(['id' => $id]);
                    $permiso = $result->fetchAssociative();

                    if (!$permiso) {
                        $this->logger->warning('Permiso not found: ' . $id);
                        return new JsonResponse([
                            'error' => 'Permiso de trabajo no encontrado'
                        ], 404);
                    }

                    // Usar add.html.twig (mismo template que add, pero con datos cargados)
                    return $this->render('dashboard/siv/permisos_trabajos/add.html.twig', [
                        'permiso' => $permiso
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Error loading permiso for edit: ' . $e->getMessage());
                    return new JsonResponse([
                        'error' => 'Error al cargar el permiso de trabajo'
                    ], 500);
                }
            }
        }

        // Retorno por defecto
        return new JsonResponse(['data' => $dataTable]);
    }

    /**
     * Endpoint AJAX para operaciones CRUD de permisos de trabajo
     * Patrón: Thin Server, Rich Client - acepta acción + params, retorna JSON
     */
    #[AdminRoute('/set_pt', name: 'siv_dashboard_set_pt')]
    #[IsGranted('ROLE_USER')]
    public function setPermisosOtAction(Request $request): JsonResponse
    {
        $params = $request->getMethod() === 'POST' ? $request->request->all() : $request->query->all();
        $action = $params['action'] ?? false;

        if (!$action) {
            return $this->json([
                'msg_type' => 'error',
                'msg' => 'No se especificó la acción'
            ]);
        }

        $currentUser = $this->getUser();
        $idCurrentUser = $currentUser->getId();
        $conn = $this->doctrine->getConnection('default');

        try {
            // ACTION: insert - Crear nuevo permiso
            if ($action === 'insert') {
                $sqlParams = json_encode([
                    'action' => 'insert',
                    'titulo' => $params['titulo'] ?? '',
                    'descripcion_trabajo' => $params['descripcion_trabajo'] ?? '',
                    'ubicacion' => $params['ubicacion'] ?? '',
                    'empresa_solicitante' => $params['empresa_solicitante'] ?? '',
                    'lider_trabajo' => $params['lider_trabajo'] ?? '',
                    'cantidad_trabajadores' => $params['cantidad_trabajadores'] ?? 0,
                    'vehiculos_involucrados' => $params['vehiculos_involucrados'] ?? '',
                    'telefono_contacto' => $params['telefono_contacto'] ?? '',
                    'observaciones' => $params['observaciones'] ?? '',
                    'created_by' => $idCurrentUser
                ]);

                $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @i, @ID)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('params', $sqlParams);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @i as result, @ID as id");
                $result = $stmt->executeQuery();
                $regPt = $result->fetchAssociative();

                if ($regPt['result'] == "1") {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha creado correctamente el registro',
                        'action' => $action,
                        'id' => $regPt['id']
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar crear el registro',
                        'action' => $action
                    ]);
                }
            }

            // ACTION: update - Actualizar permiso existente
            elseif ($action === 'update') {
                $sqlParams = json_encode([
                    'action' => 'update',
                    'id' => $params['id'] ?? 0,
                    'titulo' => $params['titulo'] ?? '',
                    'descripcion_trabajo' => $params['descripcion_trabajo'] ?? '',
                    'ubicacion' => $params['ubicacion'] ?? '',
                    'empresa_solicitante' => $params['empresa_solicitante'] ?? '',
                    'lider_trabajo' => $params['lider_trabajo'] ?? '',
                    'cantidad_trabajadores' => $params['cantidad_trabajadores'] ?? 0,
                    'vehiculos_involucrados' => $params['vehiculos_involucrados'] ?? '',
                    'telefono_contacto' => $params['telefono_contacto'] ?? '',
                    'observaciones' => $params['observaciones'] ?? '',
                    'update_by' => $idCurrentUser
                ]);

                $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @u, @ID)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('params', $sqlParams);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @u as result");
                $result = $stmt->executeQuery();
                $regPt = $result->fetchAssociative();

                if ($regPt['result'] == "1") {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha actualizado correctamente el registro',
                        'action' => $action
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar actualizar el registro',
                        'action' => $action
                    ]);
                }
            }

            // ACTION: delete - Eliminar permiso
            elseif ($action === 'delete') {
                $sqlParams = json_encode([
                    'action' => 'delete',
                    'id' => $params['id'] ?? 0,
                    'deleted_by' => $idCurrentUser
                ]);

                $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @d, @ID)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('params', $sqlParams);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @d as result");
                $result = $stmt->executeQuery();
                $regPt = $result->fetchAssociative();

                if ($regPt['result'] == "1") {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha eliminado correctamente el registro',
                        'action' => $action,
                        'success' => true
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar eliminar el registro',
                        'action' => $action,
                        'success' => false
                    ]);
                }
            }

            // ACTION: start - Iniciar permiso de trabajo
            elseif ($action === 'start') {
                $sqlParams = json_encode([
                    'action' => 'start',
                    'id' => $params['id'] ?? 0,
                    'update_by' => $idCurrentUser
                ]);

                $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @s, @ID)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('params', $sqlParams);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @s as result");
                $result = $stmt->executeQuery();
                $regPt = $result->fetchAssociative();

                if ($regPt['result'] == "1") {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha iniciado el permiso de trabajo',
                        'action' => $action
                    ]);
                }
            }

            // ACTION: finish - Finalizar permiso de trabajo
            elseif ($action === 'finish') {
                $sqlParams = json_encode([
                    'action' => 'finish',
                    'id' => $params['id'] ?? 0,
                    'update_by' => $idCurrentUser,
                    'observaciones' => $params['observaciones'] ?? ''
                ]);

                $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @f, @ID)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue('params', $sqlParams);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @f as result");
                $result = $stmt->executeQuery();
                $regPt = $result->fetchAssociative();

                if ($regPt['result'] == "1") {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha finalizado correctamente el registro',
                        'action' => $action
                    ]);
                }
            }

            return $this->json([
                'msg_type' => 'error',
                'msg' => 'Acción no reconocida'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in setPermisosOtAction: ' . $e->getMessage());
            return $this->json([
                'msg_type' => 'error',
                'msg' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Lista de Bitácora SCADA con soporte para AJAX y PDF
     * Migrado desde: old_project/src/siv/DashboardBundle/Controller/OtPermController.php::listaBitacoraScadaAction (línea 1799-1968)
     */
    #[AdminRoute('/bitacora', name: 'siv_dashboard_lista_bitacora_scada')]
    #[IsGranted('ROLE_USER')]
    public function listaBitacoraScadaAction(Request $request): Response
    {
        // Obtener parámetros (POST para ajax, GET para normal)
        if ($request->getMethod() === 'POST') {
            $params = $request->request->all();
        } else {
            $params = $request->query->all();
        }

        $action = $params['action'] ?? false;

        // Procesar regStatus - puede ser array o string
        $regStatus = isset($params['regStatus']) && (is_array($params['regStatus']) && count($params['regStatus']))
            ? $params['regStatus']
            : ['active'];

        $searchTxt = $params['searchTxt'] ?? '';
        $where = '';

        // Filtro de fecha inicio
        if (isset($params['fechaInicio']) && $params['fechaInicio']) {
            $fechaInicio = $params['fechaInicio'];
            $fechaInicioDate = $this->getDate($fechaInicio);
            if ($fechaInicioDate) {
                $where = " IFNULL(a.created_at, a.created_at) >= '$fechaInicioDate'";
            }
        } else {
            // Default: últimos 7 días
            $fechaInicio = date('d-m-Y H:i:s', mktime(0, 0, 0, date('n'), date('d') - 7, date('Y')));
            $fechaInicioDate = $this->getDate($fechaInicio);
            if ($fechaInicioDate) {
                $where = " IFNULL(a.created_at, a.created_at) >= '$fechaInicioDate'";
            }
        }

        // Filtro de fecha término
        if (isset($params['fechaTermino']) && $params['fechaTermino']) {
            $fechaTermino = $params['fechaTermino'];
            $fechaTerminoDate = $this->getDate($fechaTermino);
            if ($fechaTerminoDate) {
                $where .= ($where ? " AND " : "") . " GREATEST(GREATEST(COALESCE(deleted_restored_at, FROM_UNIXTIME(0)), COALESCE(updated_at, FROM_UNIXTIME(0))), created_at) <= '$fechaTerminoDate'";
            }
        } else {
            $fechaTermino = '';
        }

        $sqlRegStatus = $regStatus;

        // Procesar filtros de estado
        if (($trashI = array_search('trash', $sqlRegStatus)) === false || ($activeI = array_search('active', $sqlRegStatus)) === false) {
            if (($trashI = array_search('trash', $sqlRegStatus)) !== false) {
                unset($sqlRegStatus[$trashI]);
                $where .= ($where ? " AND " : "") . " a.reg_status = 0";
            }
            if (($activeI = array_search('active', $sqlRegStatus)) !== false) {
                unset($sqlRegStatus[$activeI]);
                $where .= ($where ? " AND " : "") . " a.reg_status = 1";
            }
        }

        // Filtro de destacados (featured)
        if (($featuredI = array_search('featured', $sqlRegStatus)) !== false) {
            unset($sqlRegStatus[$featuredI]);
            $where .= ($where ? " AND " : "") . " a.status = 1";
        }

        // Filtro de búsqueda de texto
        if ($searchTxt) {
            $where .= ($where ? " AND " : "") . "( a.text LIKE '%" . $searchTxt . "%' OR a.created_by_name LIKE '%" . $searchTxt . "%' OR a.deleted_restored_by_name LIKE '%" . $searchTxt . "%' )";
        }

        // Ejecutar query
        $dataTable = [];
        $conn = $this->doctrine->getManager()->getConnection();

        if ($where) {
            $where = 'WHERE ' . $where;
        }

        $sql = "SELECT * FROM vi_bitacora a $where";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $dataTable = $result->fetchAllAssociative();

        // Preparar variables para render
        $dataTableJson = json_encode($dataTable);
        $arrGlobalDataVars = [
            'data_table' => $dataTableJson,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'regStatus' => $regStatus,
            'searchTxt' => $searchTxt,
            'toolbarIsToggle' => (isset($params['toolbarIsToggle']) && $params['toolbarIsToggle'] == 'true') ? true : false,
            'tableIsFullscreen' => (isset($params['tableIsFullscreen']) && $params['tableIsFullscreen'] == 'true') ? true : false,
            'search' => $params['search'] ?? '',
            'autoUpdate' => isset($params['autoUpdate']) ? ($params['autoUpdate'] == 'true' ? true : false) : false,
            'autoUpdateInterval' => $params['autoUpdateInterval'] ?? 10,
            'renderPDF' => false
        ];

        // Manejar diferentes acciones
        if ($action == 'ajax') {
            // Retornar solo el contenedor de tabla para actualización parcial
            return $this->render('dashboard/siv/bitacora/contenedor_tabla.html.twig', $arrGlobalDataVars);
        } elseif ($action == 'pdf') {
            // Generación de PDF con KnpSnappyBundle
            $snappy = $this->pdfGenerator;
            $snappy->setOption('encoding', 'UTF-8');
            $snappy->setOption('javascript-delay', 500);
            $snappy->setOption('page-size', 'A4');
            $snappy->setOption('margin-bottom', 6);
            $snappy->setOption('margin-left', 4);
            $snappy->setOption('margin-right', 4);
            $snappy->setOption('margin-top', 4);
            $snappy->setOption('orientation', 'Landscape');
            $snappy->setOption('footer-font-size', 9);

            // Configurar footer si existe plantilla
            try {
                $footerHtml = $this->renderView('dashboard/siv/report_footer.html.twig');
                $snappy->setOption('footer-html', $footerHtml);
            } catch (\Exception $e) {
                // Si no existe footer, continuar sin él
            }

            $arrGlobalDataVars['renderPDF'] = true;

            // Renderizar HTML para PDF
            $html = $this->render('dashboard/siv/bitacora/get_lista_bitacoras_scada.html.pdf.twig', $arrGlobalDataVars);

            // Generar nombre de archivo
            $fileName = 'Lista_de_registros_de_bitacora_' . date('Y-m-d_His') . '.pdf';

            // Generar PDF desde HTML
            $pdfContent = $snappy->getOutputFromHtml($html->getContent());

            // Retornar como descarga
            return new Response(
                $pdfContent,
                200,
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                    'cache-control' => 'no-cache, must-revalidate, post-check=0, pre-check=0',
                    'expires' => '0',
                    'pragma' => 'no-cache'
                ]
            );
        } else {
            // Renderizar vista completa
            return $this->render('dashboard/siv/bitacora.html.twig', $arrGlobalDataVars);
        }
    }

    /**
     * CRUD completo de Bitácora SCADA
     * Migrado desde: old_project/src/siv/DashboardBundle/Controller/OtPermController.php::BitacoraScadaAction (línea 1971-2265)
     */
    #[AdminRoute('/bitacora_scada', name: 'siv_dashboard_bitacora_scada')]
    #[IsGranted('ROLE_USER')]
    public function bitacoraScadaAction(Request $request): Response
    {
        // Obtener parámetros
        $params = $request->getMethod() === 'POST' ? $request->request->all() : $request->query->all();
        $action = $params['action'] ?? false;

        if ($action) {
            $current_user = $this->getUser();
            $id_current_user = $current_user->getId();

            // ACTION: add - Renderizar vista de creación
            if ($action === 'add') {
                return $this->render('dashboard/siv/bitacora/add.html.twig', [
                    'action' => $action,
                ]);
            }

            // Parámetros adicionales
            $id = $params['id'] ?? '';
            $concesionaria = $params['concesionaria'] ?? '22';
            $observaciones = $params['observaciones'] ?? '';
            $attached_files = $params['attached_files'] ?? '';

            $reg_pt = [];
            // Usar conexión MySQL donde está FN_ACTUALIZA_BITACORA (igual que legacy)
            $conn = $this->doctrine->getConnection('default');

            // ACTION: insert - Crear nuevo registro
            if ($action === 'insert' && $this->isCsrfTokenValid($action . '-bs', $params['token'])) {
                $sql_reg_pt = "CALL FN_ACTUALIZA_BITACORA(null, :user_id, null, :observaciones, @i)";
                $stmt = $conn->prepare($sql_reg_pt);
                $stmt->bindValue('user_id', $id_current_user);
                $stmt->bindValue('observaciones', $observaciones);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @i as result");
                $result = $stmt->executeQuery();
                $reg_pt = $result->fetchAllAssociative();

                if ($reg_pt[0]['result'] !== '-1') {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha creado correctamente el registro',
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar crear el registro',
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                }
            }

            // ACTION: delete - Eliminar registro
            elseif ($action === 'delete' && $this->isCsrfTokenValid($action . '-bs', $params['token'])) {
                $sql_reg_pt = "CALL FN_ACTUALIZA_BITACORA(:id, :user_id, null, null, @d)";
                $stmt = $conn->prepare($sql_reg_pt);
                $stmt->bindValue('id', $id);
                $stmt->bindValue('user_id', $id_current_user);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @d as result");
                $result = $stmt->executeQuery();
                $reg_pt = $result->fetchAllAssociative();

                if ($reg_pt[0]['result'] == '1') {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se ha eliminado correctamente el registro',
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar eliminar el registro',
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                }
            }

            // ACTION: highlight - Marcar/desmarcar como destacado
            elseif ($action === 'highlight' && $this->isCsrfTokenValid($action . '-bs', $params['token'])) {
                $highlight = $params['highlight'] ?? 0;
                $sql_reg_pt = "CALL FN_ACTUALIZA_BITACORA(:id, :user_id, :highlight, null, @d)";
                $stmt = $conn->prepare($sql_reg_pt);
                $stmt->bindValue('id', $id);
                $stmt->bindValue('user_id', $id_current_user);
                $stmt->bindValue('highlight', $highlight);
                $stmt->executeQuery();

                $stmt = $conn->prepare("SELECT @d as result");
                $result = $stmt->executeQuery();
                $reg_pt = $result->fetchAllAssociative();

                $str_highlight = $highlight ? 'marcado como destacado' : 'marcado como no destacado';

                if ($reg_pt[0]['result'] == '1') {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => "El registro se ha $str_highlight correctamente",
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar realizar la operación',
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                }
            }

            // ACTION: upload-file - Subir archivo adjunto
            elseif ($action === 'upload-file' && $this->isCsrfTokenValid($action . 's', $params['token'])) {
                $relativePath = '/attachment/' . date("Y") . '/' . date("m") . '/pt_' . $params['id'];
                $sivPtDirectory = $this->getParameter('siv_templates_directory'); // Usar parámetro existente
                $path = $sivPtDirectory . $relativePath;

                // Crear directorio si no existe
                if (!is_dir($path)) {
                    mkdir($path, 0777, true);
                }

                $file = $request->files->get('file');
                if (!$file) {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'No se recibió ningún archivo',
                    ]);
                }

                $fileName = uniqid('', true) . '.' . $file->guessExtension();
                $clientOriginalName = $file->getClientOriginalName();

                $isUploaded = 0;
                try {
                    $file->move($path, $fileName);
                    $isUploaded = 1;
                } catch (\Exception $e) {
                    $isUploaded = 0;
                }

                $reg_pt_attached_file = [];
                if ($isUploaded === 1) {
                    $reg_pt_attached_file = [
                        'file_id' => uniqid('', true),
                        'id_pt' => $id,
                        'file_index' => $params['fileIndex'],
                        'file_name_original' => $clientOriginalName,
                        'file_name_fisic' => $fileName,
                        'path' => $relativePath,
                        'reg_status' => 1,
                        'created_by' => (string)$id_current_user,
                        'create_at' => date("Y-m-d H:i:s"),
                        'deleted_restored_by' => '1',
                        'deleted_restored_at' => '1',
                    ];
                }

                return $this->json([
                    'isUploaded' => $isUploaded,
                    'attached_file' => $reg_pt_attached_file,
                    'params' => $params,
                    'msg_type' => 'success',
                    'msg' => 'Archivo cargado',
                ]);
            }

            // ACTION: delete-uploaded-file - Eliminar archivos adjuntos
            elseif ($action === 'delete-uploaded-file' && $this->isCsrfTokenValid($action . 's', $params['token'])) {
                $sivPtDirectory = $this->getParameter('siv_templates_directory');
                $attachedFilesToRemove = $params['attachedFilesToRemove'];
                $resultRemoveAllFiles = 1;

                foreach ($attachedFilesToRemove as $filesToRemove) {
                    $fileNameFisic = $filesToRemove['file_name_fisic'];
                    $relativePath = $filesToRemove['path'];
                    $absolutePath = $sivPtDirectory . $relativePath;
                    $pathFileName = $absolutePath . '/' . $fileNameFisic;

                    if (file_exists($pathFileName)) {
                        try {
                            unlink($pathFileName);
                        } catch (\Exception $e) {
                            $resultRemoveAllFiles = 0;
                        }
                    }
                }

                if ($resultRemoveAllFiles) {
                    return $this->json([
                        'msg_type' => 'success',
                        'msg' => 'Se eliminaron los archivos seleccionados',
                        'resultRemoveAllFiles' => $resultRemoveAllFiles,
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                } else {
                    return $this->json([
                        'msg_type' => 'error',
                        'msg' => 'Error al intentar eliminar los archivos seleccionados',
                        'resultRemoveAllFiles' => $resultRemoveAllFiles,
                        'action' => $action,
                        'reg_pt' => $reg_pt,
                    ]);
                }
            } else {
                return $this->json([
                    'msg_type' => 'error',
                    'msg' => 'Error: no se especificó el método o token inválido',
                ]);
            }
        } else {
            return $this->json([
                'action_result' => 'No se especificó método'
            ]);
        }
    }
}
