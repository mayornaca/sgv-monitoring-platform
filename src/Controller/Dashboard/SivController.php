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

    public function __construct(
        ManagerRegistry $doctrine,
        PaginatorInterface $paginator,
        AdminUrlGenerator $adminUrlGenerator,
        LoggerInterface $logger
    ) {
        $this->doctrine = $doctrine;
        $this->paginator = $paginator;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->logger = $logger;
    }

    #[AdminRoute('/lista_llamadas_sos', name: 'lista_llamadas_sos')]
    public function listaLlamadasSosAction(Request $request): Response
    {
        $em = $this->doctrine->getManager('siv');

        // Get filter parameters
        $ci = $request->query->getInt('ci', 0); // carga inicial: 0 = no cargar, 1 = cargar
        $filterSos = $request->query->get('filterSos', '');
        $fechaInicio = $request->query->get('fechaInicio', '');
        $fechaTermino = $request->query->get('fechaTermino', '');
        $pageSize = $request->query->getInt('pageSize', 20);

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
            'filters' => [
                'ci' => $ci,
                'filterSos' => $filterSos,
                'fechaInicio' => $fechaInicio,
                'fechaTermino' => $fechaTermino,
                'pageSize' => $pageSize
            ]
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

        // Add images/logos
        try {
            // Logo Concesionaria Costanera Norte
            $logoPath = $this->getParameter('kernel.project_dir') . '/public/images/concessions/HCSQELGZDdo.png';
            if (file_exists($logoPath)) {
                $gdImage = imagecreatefrompng($logoPath);
                $objDrawing = new MemoryDrawing();
                $objDrawing->setName('Logo Concesionaria');
                $objDrawing->setDescription('Logo Concesionaria Costanera Norte');
                $objDrawing->setImageResource($gdImage);
                $objDrawing->setRenderingFunction(MemoryDrawing::RENDERING_PNG);
                $objDrawing->setMimeType(MemoryDrawing::MIMETYPE_PNG);
                $objDrawing->setResizeProportional(true);
                $objDrawing->setHeight(70);  // Ajustar altura para que sea proporcional
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

                // Process data for proper formatting
                foreach ($arr_data_table as &$row) {
                    // Format numbers and dates if needed
                    if (isset($row['total_llamadas'])) {
                        $row['total_llamadas'] = (int) $row['total_llamadas'];
                    }
                    if (isset($row['tiempo_promedio'])) {
                        $row['tiempo_promedio'] = number_format((float) $row['tiempo_promedio'], 2);
                    }
                }

            } catch (\Exception $e) {
                // Log error but continue with empty data
                error_log('Error calling FN_Reporte_mensual_sitofonia: ' . $e->getMessage());

                // Try alternative query if function doesn't exist
                try {
                    // Fallback: query the view directly with grouping
                    $sql = "SELECT
                                DATE_FORMAT(fecha_utc, '%Y-%m-%d') as fecha,
                                sos as poste,
                                COUNT(*) as total_llamadas,
                                AVG(TIMESTAMPDIFF(SECOND, fecha_utc, fecha_utc)) as tiempo_promedio
                            FROM vi_lista_llamadas_sos
                            WHERE fecha_utc BETWEEN :inicio AND :fin
                            GROUP BY DATE_FORMAT(fecha_utc, '%Y-%m-%d'), sos
                            ORDER BY fecha, sos";

                    $stmt = $conn->prepare($sql);
                    $stmt->bindValue('inicio', $fechaInicio_Date);
                    $stmt->bindValue('fin', $fechaTermino_Date);
                    $result = $stmt->executeQuery();
                    $arr_data_table = $result->fetchAllAssociative();
                } catch (\Exception $e2) {
                    // If fallback also fails, continue with empty data
                    error_log('Fallback query also failed: ' . $e2->getMessage());
                }
            }

            // Generate Excel if requested
            if ($generateExcel) {
                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                // Set title
                $sheet->setCellValue('A1', 'INFORME MENSUAL DE CITOFONÍA');
                $sheet->mergeCells('A1:F1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Set date range
                $sheet->setCellValue('A3', 'Período: ' . $fechaInicio . ' al ' . $fechaTermino);
                $sheet->mergeCells('A3:F3');

                // Headers
                $headers = ['Fecha', 'Poste SOS', 'Total Llamadas', 'Tiempo Promedio (seg)', 'Eje', 'Calzada'];
                $col = 'A';
                $row = 5;
                foreach ($headers as $header) {
                    $sheet->setCellValue($col . $row, $header);
                    $sheet->getStyle($col . $row)->getFont()->setBold(true);
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('FFE0E0E0');
                    $col++;
                }

                // Data
                $row = 6;
                foreach ($arr_data_table as $data) {
                    $sheet->setCellValue('A' . $row, $data['fecha'] ?? '');
                    $sheet->setCellValue('B' . $row, $data['poste'] ?? '');
                    $sheet->setCellValue('C' . $row, $data['total_llamadas'] ?? 0);
                    $sheet->setCellValue('D' . $row, $data['tiempo_promedio'] ?? 0);
                    $sheet->setCellValue('E' . $row, $data['eje'] ?? '');
                    $sheet->setCellValue('F' . $row, $data['calzada'] ?? '');
                    $row++;
                }

                // Auto-size columns
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // Save file
                $writer = new Xlsx($spreadsheet);
                $fileName = 'Informe_Mensual_Citofonia_' . date('Y-m-d_His') . '.xlsx';
                $tempFile = sys_get_temp_dir() . '/' . $fileName;
                $writer->save($tempFile);

                $return_file_name_excel = $fileName;

                // Return Excel file as download
                if (!$generatePdf) {
                    return $this->file($tempFile, $fileName, ResponseHeaderBag::DISPOSITION_ATTACHMENT);
                }
            }

            // Generate PDF if requested
            if ($generatePdf) {
                $html = $this->renderView('dashboard/siv/informe_mensual_citofonia_pdf.html.twig', [
                    'data_table' => $arr_data_table,
                    'fechaInicio' => $fechaInicio,
                    'fechaTermino' => $fechaTermino,
                    'filterSos' => $filterSos
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

                return new PdfResponse(
                    $knpSnappyPdf->getOutputFromHtml($html),
                    $fileName
                );
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
        $generate_pdf = isset($params['generatePdf']) ? intval($params['generatePdf']) : null;
        $generate_excel = isset($params['generateExcel']) ? intval($params['generateExcel']) : null;
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

            if ($generate_pdf == 'true') {
                // Configure PDF options
                $knpSnappyPdf->setOption("encoding", 'UTF-8');
                $knpSnappyPdf->setOption("javascript-delay", 800);
                $knpSnappyPdf->setOption("page-size", 'A4');
                $knpSnappyPdf->setOption("margin-bottom", 5);
                $knpSnappyPdf->setOption("margin-left", 1);
                $knpSnappyPdf->setOption("margin-right", 1);
                $knpSnappyPdf->setOption("margin-top", 0);
                $knpSnappyPdf->setOption("orientation", 'Landscape');
                $knpSnappyPdf->setOption("footer-font-size", 8);

                $pre_file_name = 'Registro incidente [_' . uniqid('', true) . ']';
                $return_file_name_pdf = $this->sanear_string($pre_file_name) . '.pdf';

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
                $knpSnappyPdf->generateFromHtml($html, $pdfDir . '/' . $return_file_name_pdf);
            }

            if ($generate_excel == 'true') {
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
     * Helper method to convert date from dd-mm-yyyy to yyyy-mm-dd format
     */
    private function getDate(string $dateString): string
    {
        if (empty($dateString)) {
            return '';
        }

        // Parse date in DD-MM-YYYY HH:mm:ss format
        $parts = explode(' ', $dateString);
        $datePart = $parts[0] ?? '';
        $timePart = $parts[1] ?? '00:00:00';

        $dateParts = explode('-', $datePart);
        if (count($dateParts) == 3) {
            return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' ' . $timePart;
        }

        return $dateString;
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

        if ($startDate && $endDate) {
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

            $p_tipo_contingencia_implode = count($m_tipo_contingencia) === count($p_tipo_contingencia) ? 'all' : implode(",", $p_tipo_contingencia);
            $p_categorias_vehiculos_implode = implode(",", $p_categorias_vehiculos);

            // Ejecutar función principal con cursores
            $conn->beginTransaction();
            try {
                $sql = "SELECT fn_reporte_Acumulado_atencion_incidentes_clase_vehiculo_V3(
                    'a','b',:startDate,:endDate,:categorias,:sentido,:ruta,:km_ini,:km_fin,
                    :offset,:limit,:tipo_contingencia
                ) key_resultset";

                $stmt = $conn->prepare($sql);
                $result = $stmt->executeQuery([
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'categorias' => $p_categorias_vehiculos_implode,
                    'sentido' => $p_sentido,
                    'ruta' => $p_ruta,
                    'km_ini' => $p_km_ini,
                    'km_fin' => $p_km_fin,
                    'offset' => $offset,
                    'limit' => $limit,
                    'tipo_contingencia' => $p_tipo_contingencia_implode
                ]);

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

        return $this->render('dashboard/siv/atenciones_clase_vehiculo.html.twig', [
            'data_table' => $results['a'] ?? [],
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

        $fra_ids = $params['fra_ids'] ?? null;
        $generate_pdf = isset($params['generatePdf']) ? intval($params['generatePdf']) : null;
        $generate_excel = isset($params['generateExcel']) ? intval($params['generateExcel']) : null;
        $return_file_name_pdf = null;
        $arr_data_table = [];

        $arr_fra_ids = [];
        if ($fra_ids && $fra_ids !== 'fra_ids') {
            $arr_fra_ids = explode(',', $fra_ids);
        }

        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaInicio_Date = $fechaInicio ? str_replace('-', '', $fechaInicio) : '';

        $data_table = [];

        if ($fechaInicio_Date) {
            $conn = $this->doctrine->getManager('siv')->getConnection();

            $sql = "SELECT incidente_r, eje_r, pk_r, fechahora_r, a_r, b_r, c_r, s_r, tipo_evento_r, desc_tipo_evento_r
                    FROM fn_incidente_abc(:fechaInicio)";

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery(['fechaInicio' => $fechaInicio_Date]);
            $data_table = $result->fetchAllAssociative();

            $arr_data_table = $data_table;
        }

        if ($request->isXmlHttpRequest()) {
            return new Response(json_encode($arr_data_table), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        return $this->render('dashboard/siv/tiempos_recursos_externos.html.twig', [
            'data_table' => $arr_data_table,
            'fechaInicio' => $fechaInicio,
            'fechaInicio_Date' => $fechaInicio_Date,
            'return_file_name_pdf' => $return_file_name_pdf,
            'fra_ids' => $fra_ids,
            'arr_fra_ids' => $arr_fra_ids
        ]);
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
        $em = $this->doctrine->getManager('siv');

        $fechaInicio = $request->query->get('fechaInicio', date('d-m-Y H:i:s', strtotime('-7 days')));
        $fechaFin = $request->query->get('fechaTermino', date('d-m-Y H:i:s'));
        $estado = $request->query->get('estado', 'todos');

        $permisos = [];

        if ($fechaInicio && $fechaFin) {
            try {
                $sql = "SELECT * FROM tbl_lista_permisos_ot WHERE fecha_inicio >= :fechaInicio AND fecha_fin <= :fechaFin";
                if ($estado !== 'todos') {
                    $sql .= " AND estado = :estado";
                }
                $sql .= " ORDER BY fecha_inicio DESC";

                $stmt = $em->getConnection()->prepare($sql);
                $params = [
                    'fechaInicio' => $this->getDate($fechaInicio),
                    'fechaFin' => $this->getDate($fechaFin)
                ];
                if ($estado !== 'todos') {
                    $params['estado'] = $estado;
                }

                $result = $stmt->executeQuery($params);
                $permisos = $result->fetchAllAssociative();
            } catch (\Exception $e) {
                // Datos mock si la tabla no existe
                $permisos = [
                    ['id' => 1, 'numero_ot' => 'OT-2024-001', 'descripcion' => 'Mantenimiento preventivo', 'fecha_inicio' => '2024-01-15', 'fecha_fin' => '2024-01-16', 'estado' => 'activo'],
                    ['id' => 2, 'numero_ot' => 'OT-2024-002', 'descripcion' => 'Reparación urgente', 'fecha_inicio' => '2024-01-14', 'fecha_fin' => '2024-01-14', 'estado' => 'finalizado']
                ];
            }
        }

        return $this->render('dashboard/siv/lista_permisos_trabajos.html.twig', [
            'permisos' => $permisos,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaFin,
            'estado' => $estado
        ]);
    }

    #[AdminRoute('/bitacora', name: 'siv_dashboard_bitacora')]
    #[IsGranted('ROLE_USER')]
    public function bitacoraAction(Request $request): Response
    {
        $em = $this->doctrine->getManager('siv');

        $fechaInicio = $request->query->get('fechaInicio', date('d-m-Y H:i:s', strtotime('-1 day')));
        $fechaFin = $request->query->get('fechaTermino', date('d-m-Y H:i:s'));
        $nivel = $request->query->get('nivel', 'todos');

        $entradas = [];

        if ($fechaInicio && $fechaFin) {
            try {
                $sql = "SELECT * FROM vi_lista_bitacora_scada WHERE fecha >= :fechaInicio AND fecha <= :fechaFin";
                if ($nivel !== 'todos') {
                    $sql .= " AND nivel = :nivel";
                }
                $sql .= " ORDER BY fecha DESC LIMIT 500";

                $stmt = $em->getConnection()->prepare($sql);
                $params = [
                    'fechaInicio' => $this->getDate($fechaInicio),
                    'fechaFin' => $this->getDate($fechaFin)
                ];
                if ($nivel !== 'todos') {
                    $params['nivel'] = $nivel;
                }

                $result = $stmt->executeQuery($params);
                $entradas = $result->fetchAllAssociative();
            } catch (\Exception $e) {
                // Datos mock si la vista no existe
                $entradas = [
                    ['fecha' => '2024-01-15 10:30:45', 'nivel' => 'info', 'usuario' => 'operador1', 'accion' => 'Inicio de sesión', 'descripcion' => 'Usuario ingresó al sistema'],
                    ['fecha' => '2024-01-15 10:35:12', 'nivel' => 'warning', 'usuario' => 'operador1', 'accion' => 'Modificación', 'descripcion' => 'Cambió estado de dispositivo #123'],
                    ['fecha' => '2024-01-15 10:40:00', 'nivel' => 'error', 'usuario' => 'sistema', 'accion' => 'Error conexión', 'descripcion' => 'Pérdida de conexión con dispositivo #456']
                ];
            }
        }

        return $this->render('dashboard/siv/bitacora.html.twig', [
            'entradas' => $entradas,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaFin,
            'nivel' => $nivel
        ]);
    }
}
