<?php

namespace App\Controller\Dashboard;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\Persistence\ManagerRegistry;
use Knp\Snappy\Pdf;
use Psr\Log\LoggerInterface;

/**
 * Controller dedicado para gestión de Permisos de Trabajo (V2)
 *
 * DESHABILITADO - Prototipo no funcional
 * El prototipo V2 intentó usar Stimulus + Tabulator pero no siguió el patrón legacy del proyecto
 *
 * Usar en su lugar: SivController::listaPermisosTrabajos()
 * Ruta funcional: /admin/siv/lista_permisos_trabajos
 */
// #[Route('/admin/permisos-trabajos-v2')]  // DESHABILITADO
// #[IsGranted('ROLE_VIEW_WORK_PERMITS')]
class PermisosTrabajoController extends AbstractDashboardController
{
    private ManagerRegistry $doctrine;
    private LoggerInterface $logger;
    private Pdf $pdfGenerator;

    public function __construct(
        ManagerRegistry $doctrine,
        LoggerInterface $logger,
        Pdf $pdfGenerator
    ) {
        $this->doctrine = $doctrine;
        $this->logger = $logger;
        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * Configuración del Dashboard para EasyAdmin
     */
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Permisos de Trabajo V2')
            ->setFaviconPath('favicon.ico')
            ->generateRelativeUrls();
    }

    /**
     * Configuración del menú para EasyAdmin
     */
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToRoute('Lista Permisos V2', 'fas fa-hard-hat', 'admin_permisos_trabajo_v2_index');
        yield MenuItem::linkToRoute('Volver a Admin', 'fas fa-arrow-left', 'admin');
    }

    /**
     * Vista principal: Lista de permisos de trabajos
     * Ruta moderna V2 equivalente a /admin/siv/lista_permisos_trabajos
     * DESHABILITADO
     */
    // #[Route('/', name: 'admin_permisos_trabajo_v2_index', methods: ['GET', 'POST'])]
    public function lista(Request $request): Response
    {
        $conn = $this->doctrine->getConnection('default');

        // Detectar método y leer parámetros (patrón legacy preservado)
        $params = $request->getMethod() === 'POST'
            ? $request->request->all()
            : $request->query->all();

        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaTermino = $params['fechaTermino'] ?? '';
        $regStatus = $params['regStatus'] ?? ['0', '1', '2', '3', '4'];
        $rowsPerPage = intval($params['filasPorPagina'] ?? 50);

        $this->logger->info('[PT V2] Request parameters', [
            'method' => $request->getMethod(),
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'regStatus' => $regStatus,
            'action' => $params['action'] ?? 'none'
        ]);

        $permisos = [];

        try {
            $where = $this->buildWhereClause($fechaInicio, $fechaTermino, $regStatus);
            $sql = "SELECT * FROM vi_permisos_de_trabajo a $where LIMIT 500";

            $this->logger->info('[PT V2] SQL Query', ['sql' => $sql]);

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery();
            $permisos = $result->fetchAllAssociative();

            $this->logger->info('[PT V2] Query results', [
                'total_records' => count($permisos),
                'first_ids' => array_slice(array_column($permisos, 'id'), 0, 5)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Query error: ' . $e->getMessage());
            $permisos = [];
        }

        $dataTableJson = json_encode($permisos);

        // Generar PDF si se solicita
        if (($params['action'] ?? '') === 'pdf') {
            return $this->generateListPdf($dataTableJson, $fechaInicio, $fechaTermino, $regStatus);
        }

        // Renderizar vista principal V2
        return $this->render('dashboard/siv/permisos_trabajos_v2/index.html.twig', [
            'data_table' => $dataTableJson,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'regStatus' => $regStatus,
            'searchTxt' => $request->query->get('searchTxt', ''),
            'search' => $request->query->get('search', ''),
            'autoUpdate' => $request->query->getBoolean('actualizacionAutomatica', true),
            'autoUpdateInterval' => $request->query->get('points', 15)
        ]);
    }

    /**
     * Endpoint AJAX: Obtener datos de permisos (tabla actualizable)
     * Retorna HTML partial con datos actualizados
     * DESHABILITADO
     */
    // #[Route('/get', name: 'admin_permisos_trabajo_v2_get', methods: ['GET', 'POST'])]
    public function getPermisos(Request $request): Response
    {
        $conn = $this->doctrine->getConnection('default');

        $params = $request->getMethod() === 'POST'
            ? $request->request->all()
            : $request->query->all();

        $action = $params['action'] ?? false;
        $regStatus = (isset($params['regStatus']) && is_array($params['regStatus']) && count($params['regStatus']))
            ? $params['regStatus']
            : ['0', '1', '2', '3', '4'];
        $rowsPerPage = intval($params['filasPorPagina'] ?? $params['rowsPerPage'] ?? 500);

        $fechaInicio = $params['fechaInicio'] ?? '';
        $fechaTermino = $params['fechaTermino'] ?? '';

        $dataTable = [];

        try {
            $where = $this->buildWhereClause($fechaInicio, $fechaTermino, $regStatus);
            $sql = "SELECT * FROM vi_permisos_de_trabajo a $where LIMIT $rowsPerPage";

            $this->logger->info('[PT V2] AJAX Query', ['sql' => $sql]);

            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery();
            $dataTable = $result->fetchAllAssociative();

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] AJAX error: ' . $e->getMessage());
        }

        $dataTableJson = json_encode($dataTable);

        // Si es petición AJAX, retornar solo el contenedor de tabla
        if ($action === 'ajax') {
            return $this->render('dashboard/siv/permisos_trabajos_v2/_table_container.html.twig', [
                'data_table' => $dataTableJson,
                'fechaInicio' => $fechaInicio,
                'fechaTermino' => $fechaTermino,
                'regStatus' => $regStatus,
                'searchTxt' => $params['searchTxt'] ?? '',
                'toolbarIsToggle' => ($params['toolbarIsToggle'] ?? 'false') === 'true',
                'tableIsFullscreen' => ($params['tableIsFullscreen'] ?? 'false') === 'true',
                'search' => $params['search'] ?? '',
                'autoUpdate' => ($params['autoUpdate'] ?? 'true') === 'true',
                'autoUpdateInterval' => $params['autoUpdateInterval'] ?? 15
            ]);
        }

        // Generar PDF individual de permiso
        if ($action === 'pdf' && isset($params['id']) && $params['id'] > 0) {
            return $this->generatePermisoPdf($params['id']);
        }

        // Cargar datos para formulario (empresas, ubicaciones, personal)
        try {
            $arr_empresas = $this->loadEmpresas($conn);
            $arr_personas = $this->loadPersonal($conn);
            $arr_ub = $this->loadUbicaciones($conn);
        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Error loading select data: ' . $e->getMessage());
            $arr_empresas = [];
            $arr_personas = [];
            $arr_ub = [];
        }

        // Acción: Crear nuevo registro
        if ($action === 'add') {
            $regPt = $this->createEmptyPermiso();

            if (!$regPt) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No se pudo crear el registro'
                ], 500);
            }

            return $this->render('dashboard/siv/permisos_trabajos_v2/_modal_edit.html.twig', [
                'reg_pt' => $regPt,
                'arr_empresas' => $arr_empresas,
                'arr_personas' => $arr_personas,
                'arr_ub' => $arr_ub,
                'action' => 'add'
            ]);
        }

        // Acción: Editar o ver registro existente
        if (($action === 'edit' || $action === 'view') && isset($params['id'])) {
            $regPt = $this->loadPermisoById($params['id']);

            if (!$regPt) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Permiso no encontrado'
                ], 404);
            }

            return $this->render('dashboard/siv/permisos_trabajos_v2/_modal_edit.html.twig', [
                'reg_pt' => $regPt,
                'arr_empresas' => $arr_empresas,
                'arr_personas' => $arr_personas,
                'arr_ub' => $arr_ub,
                'action' => $action
            ]);
        }

        return new JsonResponse(['error' => 'Acción no válida'], 400);
    }

    /**
     * Endpoint POST: Guardar, actualizar o eliminar permiso
     * DESHABILITADO
     */
    // #[Route('/set', name: 'admin_permisos_trabajo_v2_set', methods: ['POST'])]
    // #[IsGranted('ROLE_EDIT_WORK_PERMITS')]
    public function setPermiso(Request $request): JsonResponse
    {
        $conn = $this->doctrine->getConnection('default');
        $params = $request->request->all();
        $action = $params['action'] ?? '';

        $this->logger->info('[PT V2] Set action', [
            'action' => $action,
            'id' => $params['id'] ?? 'none'
        ]);

        try {
            if ($action === 'update') {
                $result = $this->updatePermiso($params);
            } elseif ($action === 'delete') {
                $result = $this->deletePermiso($params['id'] ?? 0);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Acción no válida'
                ], 400);
            }

            return new JsonResponse($result);

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Set error: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'error' => 'Error al procesar la solicitud: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint: Cargar formularios anidados (empresa, ubicación, personal)
     * Este endpoint será usado por modales anidados o Live Components
     * DESHABILITADO
     */
    // #[Route('/get-form', name: 'admin_permisos_trabajo_v2_get_form', methods: ['GET', 'POST'])]
    public function getForm(Request $request): Response
    {
        $params = $request->getMethod() === 'POST'
            ? $request->request->all()
            : $request->query->all();

        $action = $params['action'] ?? '';

        // Determinar qué formulario cargar
        $templateMap = [
            'ajax_frm_add_supplier' => 'dashboard/siv/permisos_trabajos_v2/forms/_new_empresa.html.twig',
            'ajax_frm_add_location' => 'dashboard/siv/permisos_trabajos_v2/forms/_new_ubicacion.html.twig',
            'ajax_frm_add_int_staff' => 'dashboard/siv/permisos_trabajos_v2/forms/_new_personal_interno.html.twig',
            'ajax_frm_add_ext_staff' => 'dashboard/siv/permisos_trabajos_v2/forms/_new_personal_externo.html.twig',
        ];

        if (isset($templateMap[$action])) {
            return $this->render($templateMap[$action], [
                'action' => $action
            ]);
        }

        return new JsonResponse(['error' => 'Formulario no encontrado'], 404);
    }

    /**
     * Endpoint: Upload de archivos
     * DESHABILITADO
     */
    // #[Route('/upload-files', name: 'admin_permisos_trabajo_v2_upload_files', methods: ['POST'])]
    // #[IsGranted('ROLE_EDIT_WORK_PERMITS')]
    public function uploadFiles(Request $request): JsonResponse
    {
        // Implementación de upload usando UploadedFile de Symfony
        $uploadedFile = $request->files->get('file');
        $permisoTrabajoId = $request->request->get('permiso_trabajo_id');

        if (!$uploadedFile) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No se recibió ningún archivo'
            ], 400);
        }

        try {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/permisos_trabajo/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = transliterator_transliterate(
                'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
                $originalFilename
            );
            $newFilename = $safeFilename . '_' . uniqid() . '.' . $uploadedFile->guessExtension();

            $uploadedFile->move($uploadDir, $newFilename);

            $fileInfo = [
                'name' => $uploadedFile->getClientOriginalName(),
                'size' => $uploadedFile->getSize(),
                'path' => '/uploads/permisos_trabajo/' . $newFilename,
                'uploaded_at' => date('Y-m-d H:i:s')
            ];

            return new JsonResponse([
                'success' => true,
                'file_info' => $fileInfo
            ]);

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Upload error: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'error' => 'Error al subir el archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint: Eliminar archivo
     * DESHABILITADO
     */
    // #[Route('/delete-file', name: 'admin_permisos_trabajo_v2_delete_file', methods: ['POST'])]
    // #[IsGranted('ROLE_EDIT_WORK_PERMITS')]
    public function deleteFile(Request $request): JsonResponse
    {
        $filePath = $request->request->get('file_path');

        if (!$filePath) {
            return new JsonResponse([
                'success' => false,
                'error' => 'No se especificó el archivo'
            ], 400);
        }

        try {
            $fullPath = $this->getParameter('kernel.project_dir') . '/public' . $filePath;

            if (file_exists($fullPath)) {
                unlink($fullPath);

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Archivo eliminado correctamente'
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'error' => 'Archivo no encontrado'
            ], 404);

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Delete file error: ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'error' => 'Error al eliminar el archivo'
            ], 500);
        }
    }

    // ============================================
    // MÉTODOS PRIVADOS (LÓGICA DE NEGOCIO)
    // ============================================

    /**
     * Construir cláusula WHERE para queries
     */
    private function buildWhereClause(?string $fechaInicio, ?string $fechaTermino, array $regStatus): string
    {
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

        // Filtros de estado
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

        return $where ? 'WHERE ' . $where : '';
    }

    /**
     * Convertir fecha a formato MySQL con reconstrucción inteligente de fechas parciales
     * - Solo día → usa mes y año actual
     * - Día-Mes → usa año actual
     * - Día-Mes-Año → usa 00:00:00 si no hay hora
     * - Día-Mes-Año HH:mm → completa con :00 (segundos)
     */
    private function getDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        try {
            // Trim whitespace
            $dateStr = trim($dateStr);

            // Obtener valores actuales como defaults
            $currentYear = (int)date('Y');
            $currentMonth = (int)date('n');
            $currentDay = (int)date('j');

            // Inicializar variables con valores por defecto
            $day = $currentDay;
            $month = $currentMonth;
            $year = $currentYear;
            $hours = 0;
            $minutes = 0;
            $seconds = 0;

            // Detectar formato según longitud y contenido
            $len = strlen($dateStr);

            // Casos según formato DD-MM-YYYY HH:mm:ss
            if ($len >= 10 && strpos($dateStr, '-') !== false) {
                // Tiene al menos DD-MM-YYYY o DD-MM
                $parts = explode(' ', $dateStr);
                $datePart = $parts[0];
                $timePart = $parts[1] ?? null;

                $dateComponents = explode('-', $datePart);

                // Parsear componentes de fecha
                if (count($dateComponents) >= 1) {
                    $day = (int)$dateComponents[0];
                }
                if (count($dateComponents) >= 2) {
                    $month = (int)$dateComponents[1];
                }
                if (count($dateComponents) >= 3) {
                    $year = (int)$dateComponents[2];
                }

                // Parsear componentes de hora si existen
                if ($timePart) {
                    $timeComponents = explode(':', $timePart);
                    if (count($timeComponents) >= 1) {
                        $hours = (int)$timeComponents[0];
                    }
                    if (count($timeComponents) >= 2) {
                        $minutes = (int)$timeComponents[1];
                    }
                    if (count($timeComponents) >= 3) {
                        $seconds = (int)$timeComponents[2];
                    }
                }
            } elseif (is_numeric($dateStr) && $len <= 2) {
                // Solo día (ej: "15")
                $day = (int)$dateStr;
            }

            // Use mktime() like legacy for timezone consistency
            return date("Y-m-d H:i:s", mktime($hours, $minutes, $seconds, $month, $day, $year));
        } catch (\Exception $e) {
            $this->logger->warning('[PT V2] Invalid date format: ' . $dateStr);
            return null;
        }
    }

    /**
     * Cargar empresas desde BD
     */
    private function loadEmpresas($conn): array
    {
        $sql = "SELECT * FROM vi_empresa_solicitante";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    /**
     * Cargar personal desde BD (stored procedure)
     */
    private function loadPersonal($conn): array
    {
        $sql = "CALL FN_PERSONA_SOLICITANTE()";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    /**
     * Cargar ubicaciones desde BD
     */
    private function loadUbicaciones($conn): array
    {
        $sql = "SELECT * FROM vi_ubicaciones";
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        return $result->fetchAllAssociative();
    }

    /**
     * Crear permiso vacío en BD
     */
    private function createEmptyPermiso(): ?array
    {
        $conn = $this->doctrine->getConnection('default');

        try {
            $currentUser = $this->getUser();
            $idCurrentUser = $currentUser ? $currentUser->getId() : null;

            $sqlParams = json_encode([
                'accion' => 'insert',
                'concesionaria' => 22,
                'created_by' => $idCurrentUser
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @i, @ID)";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue('params', $sqlParams);
            $stmt->executeQuery();

            $stmt = $conn->prepare("SELECT @i as result, @ID as new_id");
            $result = $stmt->executeQuery();
            $registro = $result->fetchAssociative();

            if ($registro['result'] == "1") {
                $newId = $registro['new_id'];
                return $this->loadPermisoById($newId);
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Create empty permiso error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cargar permiso por ID
     */
    private function loadPermisoById(int $id): ?array
    {
        $conn = $this->doctrine->getConnection('default');

        try {
            $sql = "SELECT * FROM vi_permisos_de_trabajo WHERE id = :id LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id);
            $result = $stmt->executeQuery();
            $permiso = $result->fetchAssociative();

            return $permiso ?: null;

        } catch (\Exception $e) {
            $this->logger->error('[PT V2] Load permiso error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Actualizar permiso (llamada a stored procedure)
     */
    private function updatePermiso(array $params): array
    {
        $conn = $this->doctrine->getConnection('default');

        $sqlParams = json_encode([
            'accion' => 'update',
            'id' => $params['id'] ?? 0,
            'titulo' => $params['titulo'] ?? '',
            'empresa_solicitante' => $params['empresa_solicitante'] ?? '',
            'ubicacion' => $params['ubicacion'] ?? '',
            'persona_solicitante' => $params['persona_solicitante'] ?? '',
            // ... añadir más campos según necesites
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @i, @ID)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('params', $sqlParams);
        $stmt->executeQuery();

        $stmt = $conn->prepare("SELECT @i as result");
        $result = $stmt->executeQuery();
        $registro = $result->fetchAssociative();

        if ($registro['result'] == "1") {
            return [
                'success' => true,
                'message' => 'Permiso actualizado correctamente'
            ];
        }

        return [
            'success' => false,
            'error' => 'No se pudo actualizar el permiso'
        ];
    }

    /**
     * Eliminar permiso (lógico, cambia reg_status a 0)
     */
    private function deletePermiso(int $id): array
    {
        $conn = $this->doctrine->getConnection('default');

        $sqlParams = json_encode([
            'accion' => 'delete',
            'id' => $id
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $sql = "CALL FN_ACTUALIZA_PERMISOS_TRABAJO(:params, @i, @ID)";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue('params', $sqlParams);
        $stmt->executeQuery();

        $stmt = $conn->prepare("SELECT @i as result");
        $result = $stmt->executeQuery();
        $registro = $result->fetchAssociative();

        if ($registro['result'] == "1") {
            return [
                'success' => true,
                'message' => 'Permiso eliminado correctamente'
            ];
        }

        return [
            'success' => false,
            'error' => 'No se pudo eliminar el permiso'
        ];
    }

    /**
     * Generar PDF de listado
     */
    private function generateListPdf(string $dataTableJson, string $fechaInicio, string $fechaTermino, array $regStatus): Response
    {
        $this->pdfGenerator->setOption("encoding", 'UTF-8');
        $this->pdfGenerator->setOption("javascript-delay", 500);
        $this->pdfGenerator->setOption("page-size", 'A4');
        $this->pdfGenerator->setOption("margin-bottom", 6);
        $this->pdfGenerator->setOption("margin-left", 4);
        $this->pdfGenerator->setOption("margin-right", 4);
        $this->pdfGenerator->setOption("margin-top", 4);
        $this->pdfGenerator->setOption("orientation", 'Landscape');

        $html = $this->render('dashboard/siv/permisos_trabajos_v2/pdf_list.html.twig', [
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

    /**
     * Generar PDF individual de permiso
     */
    private function generatePermisoPdf(int $id): Response
    {
        $conn = $this->doctrine->getConnection('default');

        $sql = "SELECT vp.*, c.logo as concesionaria_logo
                FROM vi_permisos_de_trabajo vp
                LEFT JOIN tbl_06_concesionaria c ON vp.concesionaria = c.id_concesionaria
                WHERE vp.id = :id";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        $result = $stmt->executeQuery();
        $regPt = $result->fetchAssociative();

        if (!$regPt) {
            throw $this->createNotFoundException('Permiso de trabajo no encontrado');
        }

        $this->pdfGenerator->setOption('enable-local-file-access', true);
        $this->pdfGenerator->setOption('encoding', 'UTF-8');
        $this->pdfGenerator->setOption('javascript-delay', 300);
        $this->pdfGenerator->setOption('page-size', 'A4');
        $this->pdfGenerator->setOption('margin-bottom', 1);
        $this->pdfGenerator->setOption('margin-left', 4);
        $this->pdfGenerator->setOption('margin-right', 4);
        $this->pdfGenerator->setOption('margin-top', 4);
        $this->pdfGenerator->setOption('orientation', 'Portrait');

        $html = $this->render('dashboard/siv/permisos_trabajos_v2/pdf_single.html.twig', [
            'renderTo' => 'pdf',
            'reg_pt' => $regPt
        ]);

        $fileName = 'Permiso_de_Trabajo_' . $regPt['id'] . '.pdf';

        return new Response(
            $this->pdfGenerator->getOutputFromHtml($html->getContent()),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'cache-control' => 'no-cache, must-revalidate, post-check=0, pre-check=0',
                'expires' => '0',
                'pragma' => 'no-cache'
            ]
        );
    }
}
