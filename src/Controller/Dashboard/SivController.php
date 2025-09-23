<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
use App\Entity\Dashboard\ViListaLlamadasSos;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Knp\Snappy\Pdf;

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

    public function __construct(
        ManagerRegistry $doctrine,
        PaginatorInterface $paginator,
        AdminUrlGenerator $adminUrlGenerator
    ) {
        $this->doctrine = $doctrine;
        $this->paginator = $paginator;
        $this->adminUrlGenerator = $adminUrlGenerator;
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

    #[Route('/admin/lista_llamadas_sos_export_excel', name: 'admin_lista_llamadas_sos_export_excel')]
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

    #[Route('/admin/lista_llamadas_sos_export_pdf', name: 'admin_lista_llamadas_sos_export_pdf')]
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
}
