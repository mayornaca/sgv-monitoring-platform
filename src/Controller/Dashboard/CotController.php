<?php

namespace App\Controller\Dashboard;

use App\Entity\Device;
use App\Entity\DeviceType;
use App\Entity\DeviceAlert;
use App\Entity\Tbl06Concesionaria;
use App\Entity\TblCot06AlarmasDispositivos;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Routing\Attribute\Route;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[AdminDashboard(routePath: '/cot', routeName: 'cot_dashboard')]
class CotController extends AbstractDashboardController
{
    protected EntityManagerInterface $entityManager;
    protected ManagerRegistry $doctrine;

    public function __construct(EntityManagerInterface $entityManager, ManagerRegistry $doctrine)
    {
        $this->entityManager = $entityManager;
        $this->doctrine = $doctrine;
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('COT - Centro de Operaciones de Tráfico')
            ->setFaviconPath('/favicon.ico')
            ->setLocales(['es' => 'Español'])
            ->setDefaultColorScheme('dark');
    }

    /**
     * Get user concessions from current user
     */
    private function getUserConcessions()
    {
        $usr_concessions = false;
        if ($this->getUser()) {
            $current_user = $this->getUser();
            $usr_concessions = explode(',', $current_user->getConcessions());
        }
        return $usr_concessions;
    }

    /**
     * Dashboard principal del COT - Resumen de dispositivos por tipo
     * Migrado y mejorado del sistema original (sgvDashboardBundle:Cot:dashboard)
     *
     * @Route: /dashboard_monitor (GET, POST, PUT)
     */
    #[Route('/dashboard_monitor', name: 'cot_dashboard', methods: ['GET', 'POST', 'PUT'])]
    public function dashboardAction(Request $request): Response
    {
        $em = $this->entityManager;

        // ==================== COMENTADO 2025-10-03 - NO DESCOMENTAR SIN REVISIÓN ====================
        // TblCot06AlarmasDispositivos NO forma parte del módulo de monitoreo operativo
        //
        // CONTEXTO HISTÓRICO:
        // - Este sistema se diseñó para notificaciones del navbar que NUNCA se completó
        // - En el proyecto antiguo SOLO aparecía en devices_alerts_list.html.twig (widget navbar)
        // - NINGUNA vista de monitoreo (index, sos, vs) mostraba estas alarmas en su contenido
        // - El navbar con notificaciones NO se migró al nuevo sistema
        //
        // SISTEMAS DE ALARMAS CORRECTOS:
        // - SOS Monitor (ASS): tbl_cot_09_alarmas_sensores_dispositivos (YA IMPLEMENTADO)
        // - Cada módulo tiene sus propias alarmas específicas
        //
        // IMPACTO EN RENDIMIENTO:
        // - Estas queries causaban lentitud de 15+ segundos sin propósito funcional
        // - Al comentar, el sistema mejoró significativamente
        //
        // ANTES DE DESCOMENTAR: Revisar con arquitecto del sistema y validar caso de uso real
        // =========================================================================================

        /*
        // 1. Obtener alarmas activas de dispositivos
        // Replicando exactamente la query del proyecto antiguo
        $qb_alarmas_dispositivos = $em->createQueryBuilder();
        $qb_alarmas_dispositivos->select('reg')
            ->from(TblCot06AlarmasDispositivos::class, 'reg')
            ->leftJoin('reg.idAlarma', 'alarm')->addSelect('alarm')
            ->leftJoin('reg.idDispositivo', 'disp')->addSelect('disp')
            ->leftJoin('reg.concesionaria', 'conc')->addSelect('conc')
            ->orderBy('reg.id', 'DESC')
            ->andWhere('reg.estado = 0')
            ->setMaxResults(25);  // IMPORTANTE: Limitar resultados para rendimiento

        // Filtrar por concesiones del usuario si aplica
        if ($usr_concessions = $this->getUserConcessions()) {
            $qb_alarmas_dispositivos->andWhere('conc.idConcesionaria IN (:ids_concesionarias)')
                ->setParameter('ids_concesionarias', $usr_concessions);
        }

        $rs_alarmas_dispositivos = $qb_alarmas_dispositivos->getQuery()->getArrayResult();
        */
        $rs_alarmas_dispositivos = []; // Array vacío para mantener compatibilidad

        // 2. Obtener resumen de estado de dispositivos desde la vista
        // Usar conexión 'default' explícitamente como en el proyecto antiguo
        $conn = $this->doctrine->getConnection('default');
        $sql = "SELECT id, tipo, icono, activos, inactivos, total, por_Activos, por_inactivos, concesionaria
                FROM vi_resumen_estado_dispositivos";

        // Filtrar por Costanera Norte (concesionaria 22) - Dashboard exclusivo CN
        $where = " WHERE concesionaria = 22";

        $stmt = $conn->prepare($sql . $where);
        $result = $stmt->executeQuery();
        $data_table = $result->fetchAllAssociative();

        // 3. Si es petición AJAX, devolver JSON para actualización automática
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'data_table' => $data_table,
                'alarmas_dispositivos' => $rs_alarmas_dispositivos,
            ]);
        }

        // 4. Renderizar vista completa del dashboard
        return $this->render('dashboard/cot/dashboard.html.twig', [
            'data_table' => $data_table,
            'alarmas_dispositivos' => $rs_alarmas_dispositivos,
        ]);
    }
    
    
    /**
     * Habilitar consulta de tipo de dispositivo OPC Daemon
     */
    public function enableOpcDaemonDeviceType($id_type)
    {
        $em = $this->entityManager;
        $cot_device = $em->getRepository('App\Entity\TblCot01TiposDispositivos')->find($id_type);
        if ($cot_device) {
            $cot_device->setConsultar(1);
            $em->persist($cot_device);
            $em->flush();
        }
    }
    
    /**
     * Vista de gálibos
     */
    #[Route('/galibos', name: 'galibos_index', methods: ['GET', 'POST'])]
    public function galibosIndexAction(Request $request): Response
    {
        $request->query->set('id', 5);
        return $this->indexAction($request);
    }
    
    /**
     * Vista principal COT - Videowall, Lista Dispositivos, etc.
     * Replicando funcionalidad del proyecto antiguo
     */
    #[Route('/index/{id}', name: 'index', methods: ['GET', 'POST'], defaults: ['id' => 0])]
    #[AdminRoute('/monitor/{id}', name: 'cot_monitor')]
    public function indexAction(Request $request, int $id = 0): Response
    {
        // Obtener parámetros de la request
        $params = $request->getMethod() === 'POST' ? $request->request->all() : $request->query->all();

        $id_device_type_fr = $id;
        $current_user = $this->getUser();

        // Configuración de vista (replicando proyecto antiguo)
        $fixed = (isset($params['fixed']) and $params['fixed'] == 'true') ? true : false;
        $videowall = (isset($params['videowall']) and $params['videowall'] == 'true') ? true : false;
        $device_status = (isset($params['device_status']) and $params['device_status']) ? $params['device_status'] : 'unactive_devices';
        $input_device_finder = (isset($params['input_device_finder']) and $params['input_device_finder']) ? $params['input_device_finder'] : '';
        $contract_ui = isset($params['contract_ui']) ? $params['contract_ui'] == 'true' ? true : false : true;
        $grid_items_width = (isset($params['grid_items_width']) and $params['grid_items_width']) ? $params['grid_items_width'] : '12';

        // Control de permisos simplificado (sin manejo de anónimos por ahora)
        $isPermisive = true; // Simplificado por ahora
        $currentUserRoles = $current_user ? $current_user->getRoles() : ['ROLE_OPERATOR_COT'];
        $canViewCNSpires = true; // Simplificado
        $canViewVSSpires = true; // Simplificado

        $em = $this->entityManager;

        // ==================== COMENTADO 2025-10-03 - NO DESCOMENTAR SIN REVISIÓN ====================
        // TblCot06AlarmasDispositivos NO forma parte del módulo de monitoreo operativo
        // Ver documentación completa en dashboardAction() línea 69
        // Sistema de notificaciones navbar incompleto - NO migrado al nuevo proyecto
        // ANTES DE DESCOMENTAR: Revisar con arquitecto del sistema y validar caso de uso real
        // =========================================================================================

        /*
        // 1. Query para alarmas de dispositivos
        $qb_alarmas_dispositivos = $em->createQueryBuilder();
        $qb_alarmas_dispositivos->select('a', 'al', 'd')
            ->from('App\Entity\TblCot06AlarmasDispositivos', 'a')
            ->leftJoin('a.idAlarma', 'al')
            ->leftJoin('a.idDispositivo', 'd')
            ->orderBy('a.id', 'DESC')
            ->andWhere('a.estado = 0')
            ->setMaxResults(25);

        $rs_alarmas_dispositivos = [];
        try {
            $rs_alarmas_dispositivos = $qb_alarmas_dispositivos->getQuery()->getArrayResult();
        } catch (\Exception $e) {
            error_log('Error obteniendo alarmas: ' . $e->getMessage());
        }
        */
        $rs_alarmas_dispositivos = []; // Array vacío para mantener compatibilidad

        // 2. Query para tipos de dispositivos - Filtrado por Costanera Norte (concesionaria 22)
        $qb_tipos_dispositivos = $em->createQueryBuilder();
        $qb_tipos_dispositivos->select('t')
            ->from('App\Entity\TblCot01TiposDispositivos', 't')
            ->orderBy('t.id', 'ASC')
            ->andWhere('t.mostrar = 1')
            ->andWhere('t.concesionaria = :concesionaria')
            ->setParameter('concesionaria', 22);

        // Aplicar filtros de permisos para espiras
        if (!$canViewCNSpires) {
            $qb_tipos_dispositivos->andWhere('t.id not in(4, 12)');
        }
        if (!$canViewVSSpires) {
            $qb_tipos_dispositivos->andWhere('t.id != 13');
        }

        $tipos_dispositivos = [];
        $all_tipos_dispositivos = [];
        try {
            if (!$request->isXMLHttpRequest()) {
                $all_tipos_dispositivos = $qb_tipos_dispositivos->getQuery()->getArrayResult();

                // Determinar tipo por defecto si es -1
                if ($id_device_type_fr == -1) {
                    if ($all_tipos_dispositivos[0] and $all_tipos_dispositivos[0]['id']) {
                        $id_device_type_fr = $all_tipos_dispositivos[0]['id'];
                    }
                }
            }

            // Aplicar filtro por tipo específico si es necesario
            if ($id_device_type_fr > 0) {
                $arrIdsTiposDispositivos = array_column($all_tipos_dispositivos, 'id');
                if (in_array($id_device_type_fr, $arrIdsTiposDispositivos)) {
                    $qb_tipos_dispositivos->andWhere("t.id = $id_device_type_fr");
                } else {
                    throw new AccessDeniedException();
                }
            }

            $tipos_dispositivos = $qb_tipos_dispositivos->getQuery()->getArrayResult();
            $arrIdsTiposDispositivos = array_column($tipos_dispositivos, 'id');

        } catch (\Exception $e) {
            $tipos_dispositivos = [];
            $all_tipos_dispositivos = [];
            $arrIdsTiposDispositivos = [];
            error_log('Error obteniendo tipos: ' . $e->getMessage());
        }

        // 3. Query para dispositivos (con relaciones completas como en el original)
        // IMPORTANTE: Si es petición AJAX, limpiar caché de Doctrine para obtener datos frescos
        if ($request->isXMLHttpRequest()) {
            $em->clear(); // Limpia todas las entidades del EntityManager
        }

        $qb_dispositivos = $em->createQueryBuilder();
        $qb_dispositivos->select('d', 'type', '_eje', '_tramo')
            ->from('App\Entity\TblCot02Dispositivos', 'd')
            ->leftJoin('d.idTipo', 'type')
            ->leftJoin('d.eje', '_eje')
            ->leftJoin('d.tramo', '_tramo')
            ->addOrderBy('d.estado', 'ASC')
            ->addOrderBy('d.orden', 'ASC')
            ->andWhere('d.regStatus = 1')
            ->andWhere('d.concesionaria = :ids_concesionaria')
            ->setParameter('ids_concesionaria', '22'); // Hardcoded como en el original

        if (!empty($arrIdsTiposDispositivos)) {
            $qb_dispositivos->andWhere('type.id in (:arrIdsTiposDispositivos)')
                ->setParameter('arrIdsTiposDispositivos', $arrIdsTiposDispositivos);
        }

        // Filtro por tipo específico
        if ($id_device_type_fr > 0) {
            $qb_dispositivos->andWhere("type.id = $id_device_type_fr");
        }

        $dispositivos = [];
        try {
            $dispositivos = $qb_dispositivos->getQuery()->getArrayResult();
        } catch (\Exception $e) {
            $dispositivos = [];
            error_log('Error obteniendo dispositivos: ' . $e->getMessage());
        }

        // 4. Calcular estadísticas por tipo y aplicar hacks del sistema original
        $count_status_type = [];
        $active_devices_count = 0;
        $unactive_devices_count = 0;

        foreach ($dispositivos as $key => $value) {
            // HACK: Dispositivo ID 787 siempre debe estar activo (del sistema original)
            $idUID = $value['id'];
            if ($idUID == 787) {
                $dispositivos[$key]['estado'] = 1;
                $value['estado'] = 1;
            }

            $id_type = $value['idTipo']['id'] ?? 0;
            if (!isset($count_status_type['' . $id_type])) {
                $count_status_type['' . $id_type] = [
                    'total' => 0,
                    'active' => 0,
                    'unactive' => 0,
                ];
            }
            $count_status_type['' . $id_type]['total']++;
            if ($value['estado'] == 1) {
                $count_status_type['' . $id_type]['active']++;
                $active_devices_count++;
            } else {
                $count_status_type['' . $id_type]['unactive']++;
                $unactive_devices_count++;
            }
        }

        // 5. Obtener timestamps desde TblCot00Config (igual que en el original)
        $StentofonLastUpdateDateTime = null;
        $SwIpDevicesMonitorLastUpdateDateTime = null;

        try {
            $configRepo = $em->getRepository('App\Entity\TblCot00Config');
            $stentofonConfig = $configRepo->find('StentofonLastUpdateDateTime');
            $swIpConfig = $configRepo->find('SwIpDevicesMonitorLastUpdateDateTime');

            if ($stentofonConfig) {
                $StentofonLastUpdateDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $stentofonConfig->getValor());
            }
            if ($swIpConfig) {
                $SwIpDevicesMonitorLastUpdateDateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $swIpConfig->getValor());
            }
        } catch (\Exception $e) {
            error_log('Error obteniendo configuraciones de timestamp: ' . $e->getMessage());
        }

        // Valores por defecto si no se encuentran en BD
        if (!$StentofonLastUpdateDateTime) {
            $StentofonLastUpdateDateTime = new \DateTime();
        }
        if (!$SwIpDevicesMonitorLastUpdateDateTime) {
            $SwIpDevicesMonitorLastUpdateDateTime = new \DateTime();
        }

        $real_mode = true;

        // 6. Response según el tipo de request
        if ($request->isXMLHttpRequest()) {
            // Response AJAX (igual que en el sistema original)
            return new Response(
                json_encode([
                    'id_device_type_fr' => $id_device_type_fr,
                    'alarmas_dispositivos' => $rs_alarmas_dispositivos,
                    'tipos_dispositivos' => $tipos_dispositivos,
                    'dispositivos' => $dispositivos,
                    'videowall' => $videowall,
                    'fixed' => $fixed,
                    'device_status' => $device_status,
                    'input_device_finder' => $input_device_finder,
                    'contract_ui' => $contract_ui,
                    'real_mode' => $real_mode,
                    'count_status_type' => $count_status_type,
                    'grid_items_width' => $grid_items_width,
                    'StentofonLastUpdateDateTime' => $StentofonLastUpdateDateTime->format('d-m-Y H:i:s'),
                    'SwIpDevicesMonitorLastUpdateDateTime' => $SwIpDevicesMonitorLastUpdateDateTime->format('d-m-Y H:i:s'),
                    'isPermisive' => $isPermisive,
                    'active_devices_count' => $active_devices_count,
                    'unactive_devices_count' => $unactive_devices_count
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        } else {
            // Crear contexto COMPLETO de EasyAdmin como array para Twig
            $eaContext = [
                'i18n' => [
                    'locale' => 'es',
                    'translationDomain' => 'EasyAdminBundle',
                ],
                'crud' => [
                    'entityFqcn' => null,
                    'currentAction' => 'index',
                    'customPageTitle' => 'COT - Centro de Operaciones de Tráfico',
                ],
                'assets' => [
                    'favicon' => '/favicon.ico',
                    'css_files' => [],
                    'js_files' => [],
                ],
                'user' => [
                    'isLoggedIn' => $current_user !== null,
                    'username' => $current_user ? $current_user->getUserIdentifier() : null,
                    'displayName' => $current_user ? $current_user->getUserIdentifier() : 'Anónimo',
                    'avatarUrl' => null,
                    'isImpersonated' => false,
                ],
                'request' => [
                    'route' => 'cot_index',
                    'query' => $request->query->all(),
                    'referrer' => $request->headers->get('referer'),
                ],
                'dashboardTitle' => 'COT Sistema',
                'dashboardFaviconPath' => '/favicon.ico',
                'contentTitle' => 'Centro de Operaciones de Tráfico',
                'contentWidth' => 'full',
                'sidebar' => [
                    'isDisplayed' => false,
                ],
                'mainMenu' => [],
                'usePrettyUrls' => true,
            ];

            // Detectar si estamos en modo galibos (device type 5)
            $app_cot_galibos = ($id_device_type_fr == 5);

            // Response HTML - SIEMPRE usar la misma plantilla como en el proyecto antiguo
            return $this->render('dashboard/cot/videowall.html.twig', [
                'id_device_type_fr' => $id_device_type_fr,
                'alarmas_dispositivos' => $rs_alarmas_dispositivos,
                'all_tipos_dispositivos' => $all_tipos_dispositivos,
                'tipos_dispositivos' => $tipos_dispositivos,
                'dispositivos' => $dispositivos,
                'videowall' => $videowall,
                'fixed' => $fixed,
                'device_status' => $device_status,
                'input_device_finder' => $input_device_finder,
                'contract_ui' => $contract_ui,
                'real_mode' => $real_mode,
                'count_status_type' => $count_status_type,
                'grid_items_width' => $grid_items_width,
                'transitions' => 0,
                'isPermisive' => $isPermisive,
                'active_devices_count' => $active_devices_count,
                'unactive_devices_count' => $unactive_devices_count,
                'app_cot_galibos' => $app_cot_galibos, // Flag para detectar modo galibos
                'StentofonLastUpdateDateTime' => $StentofonLastUpdateDateTime ? $StentofonLastUpdateDateTime->format('d-m-Y H:i:s') : '25-09-2024 14:17:12',
                'SwIpDevicesMonitorLastUpdateDateTime' => $SwIpDevicesMonitorLastUpdateDateTime ? $SwIpDevicesMonitorLastUpdateDateTime->format('d-m-Y H:i:s') : '25-09-2024 14:17:12',
                'ea' => $eaContext
            ]);
        }
    }

    /**
     * Vista de Gálibos (height clearance sensors)
     * Llama a indexAction con tipo de dispositivo 5
     */
    #[AdminRoute('/galibos', name: 'cot_galibos')]
    public function galibosAction(Request $request): Response
    {
        // Gálibos son dispositivos de tipo 5
        // Establecer el parámetro id para filtrar solo gálibos
        $request->query->set('id', 5);

        // Llamar directamente a indexAction con el request modificado
        // Esto mantiene la lógica del sistema original
        return $this->indexAction($request, 5);
    }

    /**
     * Vista de red/network
     */
    #[AdminRoute('/network', name: 'cot_network')]
    public function networkAction(Request $request): Response
    {
        $current_user = $this->getUser();
        $em = $this->entityManager;

        // Por ahora simplificar para evitar errores
        $rs_alarmas_dispositivos = [];
        
        // Template JSON para el gráfico de red
        $json_graphic_template = '{ "class": "go.GraphLinksModel",
  "copiesArrays": true,
  "copiesArrayObjects": true,
  "linkFromPortIdProperty": "fromPort",
  "linkToPortIdProperty": "toPort",
  "nodeDataArray": [ 
{"id":985, "key":1, "name":"TR5\n10.95.10.6", "color":"#00de2a", "size":"150 50", "loc":"-211.45446109192721 360", "leftArray":[], "topArray":[ {"text":"25", "portColor":"#c14617", "portId":"top0"} ], "bottomArray":[ {"text":"26", "portColor":"#316571", "portId":"bottom0"} ], "rightArray":[]},
{"id":986, "key":2, "name":"CCO-1\n10.95.10.5", "color":"#00de2a", "size":"150 50", "loc":"9.577354926106402 134.6673970599648", "leftArray":[ {"portColor":"#316571", "portId":"left1", "text":"26"} ], "topArray":[], "bottomArray":[ {"text":"25", "portId":"bottom0", "portColor":"#c14617"} ], "rightArray":[ {"text":"27", "portId":"right0", "portColor":"#e91e63"} ]},
{"id":987, "key":3, "name":"CCO-2\n10.95.10.4", "color":"#00de2a", "size":"150 50", "loc":"10.045405463772667 594.2653061224488", "leftArray":[ {"text":"25", "portColor":"#c14617", "portId":"left1"} ], "topArray":[ {"text":"28", "portColor":"#9c27b0", "portId":"top0"} ], "bottomArray":[ {"text":"", "portColor":"#6cafdb", "portId":"bottom0"} ], "rightArray":[ {"text":"26", "portId":"right0", "portColor":"#316571"} ]},
{"id":988, "key":4, "name":"Lo Saldes-Kennedy\n10.95.10.51", "color":"#00de2a", "size":"150 50", "loc":"713.7357736745503 135.22362595831984", "leftArray":[ {"text":"25", "portColor":"#c14617", "portId":"left0"} ], "topArray":[], "bottomArray":[ {"text":"26", "portId":"bottom0", "portColor":"#316571"} ], "rightArray":[ {"text":"21", "portColor":"#9c27b0", "portId":"right0"} ]},
{"id":989, "key":-5, "name":"TDL 1\n10.95.10.79", "color":"#00de2a", "size":"90 50", "loc":"946.083644603708 135.07081991310574", "leftArray":[ {"text":"12", "portId":"left0", "portColor":"#18bc9c"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"11", "portId":"right0", "portColor":"#7d4bd6"} ]},
{"id":990, "key":-6, "name":"CN-CS\n10.95.10.54", "color":"#00de2a", "size":"150 50", "loc":"261.9999999999999 593.9999999999995", "leftArray":[ {"text":"26", "portId":"left0", "portColor":"#316571"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"25", "portId":"right0", "portColor":"#c14617"} ]},
{"id":991, "key":-7, "name":"Lo Saldes\n10.95.10.53", "color":"#00de2a", "size":"150 50", "loc":"488.567052332937 593.9999999999995", "leftArray":[ {"text":"26", "portId":"left0", "portColor":"#316571"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"25", "portId":"right0", "portColor":"#c14617"} ]},
{"id":992, "key":-8, "name":"Vespucio-Kennedy\n10.95.10.52", "color":"#00de2a", "size":"150 50", "loc":"714.0000000000006 594", "leftArray":[ {"text":"26", "portId":"left0", "portColor":"#316571"} ], "topArray":[ {"text":"25", "portId":"top0", "portColor":"#c14617"} ], "bottomArray":[], "rightArray":[ {"text":"23", "portId":"right0", "portColor":"#f44336"} ]},
{"id":993, "key":-9, "name":"TDL 2\n10.95.10.80", "color":"#00de2a", "size":"90 50", "loc":"946.0392267419054 208", "leftArray":[ {"text":"11", "portId":"left0", "portColor":"#7d4bd6"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"12", "portId":"right0", "portColor":"#18bc9c"} ]},
{"id":994, "key":-10, "name":"TDL 3\n10.95.10.81", "color":"#00de2a", "size":"90 50", "loc":"946.1578607324428 282.8086900213968", "leftArray":[ {"text":"12", "portId":"left0", "portColor":"#18bc9c"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"11", "portId":"right0", "portColor":"#7d4bd6"} ]},
{"id":995, "key":-11, "name":"TDL 4\n10.95.10.82", "color":"#00de2a", "size":"90 50", "loc":"946.3994984209024 360", "leftArray":[ {"text":"11", "portId":"left0", "portColor":"#7d4bd6"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"12", "portId":"right0", "portColor":"#18bc9c"} ]},
{"id":996, "key":-12, "name":"TDL 5\n10.95.10.83", "color":"#00de2a", "size":"90 50", "loc":"946.2067806539397 431", "leftArray":[ {"text":"12", "portId":"left0", "portColor":"#18bc9c"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"11", "portId":"right0", "portColor":"#7d4bd6"} ]},
{"id":997, "key":-13, "name":"TDL 6\n10.95.10.84", "color":"#00de2a", "size":"90 50", "loc":"946.8324460879654 501.4567067437607", "leftArray":[ {"text":"11", "portId":"left0", "portColor":"#7d4bd6"} ], "topArray":[], "bottomArray":[], "rightArray":[ {"text":"12", "portId":"right0", "portColor":"#18bc9c"} ]},
{"id":998, "key":-14, "name":"TDL 7\n10.95.10.85", "color":"#00de2a", "size":"90 50", "loc":"946.5670523329364 593.9999999999993", "leftArray":[ {"text":"11", "portId":"left0", "portColor":"#7d4bd6"} ], "topArray":[ {"text":"12", "portId":"top0", "portColor":"#18bc9c"} ], "bottomArray":[], "rightArray":[]},
{"category":"Comment", "text":"Anillo\nPrincipal\nERPS ID 1", "key":-15, "loc":"361 354"},
{"category":"Comment", "text":"Sub \nAnillo 1\nERPS ID 2", "key":-16, "loc":"-62 354"},
{"category":"Comment", "text":"Sub \nAnillo 2\nERPS ID 3", "key":-17, "loc":"797 354"},
{"category":"Title", "text":"DIAGRAMA DE RED COSTANERA NORTE", "key":-18, "loc":"361 86"},
{"id":999, "key":-19, "name":"Prueba\n10.91.32.29", "color":"#00de2a", "size":"150 50", "loc":"-403.77001953125 360.5", "leftArray":[], "topArray":[], "bottomArray":[], "rightArray":[]}
 ],
  "linkDataArray": [ 
{"from":4, "to":2, "fromPort":"left0", "toPort":"right0", "text":"", "color":"#d32f2f"},
{"from":1, "to":2, "fromPort":"top0", "toPort":"left1", "text":"", "color":"#1976d2"},
{"from":1, "to":3, "fromPort":"bottom0", "toPort":"left1", "text":"RPL Link", "color":"#1976d2", "dash":[ 3,2 ]},
{"from":-7, "to":-8, "fromPort":"right0", "toPort":"left0", "text":"RPL\nLink", "color":"#d32f2f", "dash":[ 3,2 ]},
{"from":-6, "to":-7, "fromPort":"right0", "toPort":"left0", "text":"", "color":"#d32f2f"},
{"from":3, "to":-6, "fromPort":"right0", "toPort":"left0", "text":"", "color":"#d32f2f"},
{"from":2, "to":3, "fromPort":"bottom0", "toPort":"top0", "text":"", "color":"#d32f2f"},
{"from":4, "to":-5, "fromPort":"right0", "toPort":"left0", "text":"", "color":"#ffc107"},
{"from":-5, "to":-9, "fromPort":"right0", "toPort":"right0", "text":"", "color":"#ffc107"},
{"from":-9, "to":-10, "fromPort":"left0", "toPort":"left0", "text":"", "color":"#ffc107"},
{"from":-10, "to":-11, "fromPort":"right0", "toPort":"right0", "text":"RPL Link", "color":"#ffc107", "dash":[ 3,2 ]},
{"from":-11, "to":-12, "fromPort":"left0", "toPort":"left0", "text":"", "color":"#ffc107"},
{"from":-12, "to":-13, "fromPort":"right0", "toPort":"right0", "text":"", "color":"#ffc107"},
{"from":-13, "to":-14, "fromPort":"left0", "toPort":"top0", "text":"", "color":"#ffc107"},
{"from":-8, "to":-14, "fromPort":"right0", "toPort":"left0", "text":"", "color":"#ffc107"},
{"from":4, "to":-8, "fromPort":"bottom0", "toPort":"top0", "text":"", "color":"#d32f2f"}
 ]}';
        
        return $this->render('dashboard/cot/network.html.twig', [
            'alarmas_dispositivos' => $rs_alarmas_dispositivos,
            'json_graphic_template' => $json_graphic_template,
        ]);
    }
    
    /**
     * Convierte fecha formato dd-mm-yyyy H:i:s a yyyy-mm-dd H:i:s
     */
    private function getDate($dateString): string
    {
        if (empty($dateString)) {
            return '';
        }

        // LEGACY: Parsear fecha en formato dd-mm-yyyy H:i:s usando mktime() para correcto manejo de timezone
        $year = substr($dateString, 6, 4);
        $month = substr($dateString, 3, 2);
        $day = substr($dateString, 0, 2);
        $hours = substr($dateString, 11, 2);
        $minutes = substr($dateString, 14, 2);
        $seconds = substr($dateString, 17, 2);

        return date("Y-m-d H:i:s", mktime($hours, $minutes, $seconds, $month, $day, $year));
    }
    
    /**
     * Historial de Espiras Costanera Norte
     */
    #[AdminRoute('/spire_history', name: 'spire_history')]
    public function cotSpireHistoryGenerateReportAction(Request $request): Response
    {
        // Configurar timezone correcto para Chile
        date_default_timezone_set('America/Santiago');

        // Parámetros de la petición
        $params = $request->getMethod() == 'POST' ? $request->request->all() : $request->query->all();
        
        $action = $params['action'] ?? false;
        $spires = $params['spires'] ?? false;
        $generatePdf = isset($params['generatePdf']) && $params['generatePdf'] == 'on';
        $onlyZeros = isset($params['onlyZeros']) && $params['onlyZeros'] == 'on' ? 1 : 0;
        $onlyEmpty = isset($params['onlyEmpty']) && $params['onlyEmpty'] == 'on' ? 1 : 0;
        $showEmpty = 0; // Siempre 0 según el código original
        
        // Filtros de fecha
        if (isset($params['fechaInicio']) && $params['fechaInicio']) {
            $fechaInicio = $params['fechaInicio'];
            $fechaInicio_Date = $this->getDate($fechaInicio);
        } else {
            $fechaInicio = date('d-m-Y H:i:s', mktime(date('H'), 0, 0, date('n'), date('d'), date('Y')));
            $fechaInicio_Date = date('Y-m-d H:i:s', mktime(date('H'), 0, 0, date('n'), date('d'), date('Y')));
        }
        
        if (isset($params['fechaTermino']) && $params['fechaTermino']) {
            $fechaTermino = $params['fechaTermino'];
            $fechaTermino_Date = $this->getDate($fechaTermino);
        } else {
            $fechaTermino = date('d-m-Y H:i:s', mktime(date('H'), 59, 59, date('n'), date('d'), date('Y')));
            $fechaTermino_Date = date('Y-m-d H:i:s', mktime(date('H'), 59, 59, date('n'), date('d'), date('Y')));
        }

        
        $arr_reg = [];
        $conn = $this->entityManager->getConnection();
        
        // Preparar string de espiras seleccionadas
        $str_spires = '';
        if (is_array($spires)) {
            $str_spires = implode(',', $spires);
        } else if (strlen($spires) > 0) {
            $str_spires = $spires;
        }
        
        // Obtener lista de todas las espiras si no es AJAX - Filtrado por Costanera Norte (concesionaria 22)
        $arr_spires = [];
        if (!$request->isXmlHttpRequest()) {
            $sql_spires = "SELECT * FROM tbl_cot_02_dispositivos WHERE id_tipo = 4 AND concesionaria = 22 ORDER BY nombre ASC";
            $stmt = $conn->prepare($sql_spires);
            $result = $stmt->executeQuery();
            $arr_spires = $result->fetchAllAssociative();
            // No es necesario closeCursor en Doctrine DBAL 3.x
        }
        
        // Llamar al procedimiento almacenado - EXACTO como legacy
        $sql_reg_pt = "CALL fnHistorialEspirasV8('$fechaInicio_Date','$fechaTermino_Date','$str_spires','$onlyZeros','$onlyEmpty','0')";
        
        try {
            $stmt = $conn->prepare($sql_reg_pt);
            $result = $stmt->executeQuery();
            $arr_reg = $result->fetchAllAssociative();

            if (!$arr_reg || count($arr_reg) === 0) {
                $arr_reg = [];
            } else {
                if (isset($arr_reg[0]['JSON_ESPIRAS'])) {
                    // Obtener STRING JSON del SP y decodificar
                    $arr_reg = $arr_reg[0]['JSON_ESPIRAS'];
                    $decoded = json_decode($arr_reg, true);
                    $arr_reg = (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
                } else {
                    $arr_reg = [];
                }
            }
        } catch (\Exception $e) {
            $arr_reg = [];
        }
        
        // Generar PDF si se solicita
        $return_file_name = null;
        if ($generatePdf) {
            // TODO: Implementar generación de PDF con KnpSnappy cuando esté disponible
            // Por ahora solo preparamos el nombre del archivo
            $pre_file_name = 'Estado operativo de espiras [ID-' . uniqid('', true) . ']';
            $return_file_name = $pre_file_name . '.pdf';
        }
        
        // Si es petición AJAX, retornar JSON
        if ($request->isXmlHttpRequest()) {
            return new Response(
                json_encode(['arr_reg_spires' => $arr_reg]),
                200,
                ['Content-Type' => 'application/json']
            );
        }
        
        // Renderizar vista normal
        return $this->render('dashboard/cot/spire_history.html.twig', [
            'arr_reg_spires' => $arr_reg,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'all_spires' => $arr_spires,
            'sel_spires' => $spires,
            'onlyEmpty' => $onlyEmpty,
            'onlyZeros' => $onlyZeros,
            'showEmpty' => $showEmpty,
            'return_file_name_pdf' => $return_file_name
        ]);
    }

    /**
     * Historial de Espiras Minuto a Minuto VS (Vespucio Sur)
     */
    #[AdminRoute('/spire_history_vs_min', name: 'spire_history_vs_min')]
    public function cotSpireHistoryGenerateReportVsMinAction(Request $request): Response
    {
        // Obtener parámetros
        $params = $request->getMethod() == 'POST' ? $request->request->all() : $request->query->all();

        $action = $params['action'] ?? false;
        $spires = $params['spires'] ?? false;
        $generatePdf = isset($params['generatePdf']) && $params['generatePdf'] == 'on';

        // Parámetros de filtro
        $onlyZeros = isset($params['onlyZeros']) && $params['onlyZeros'] == 'on' ? 1 : 0;
        $onlyEmpty = isset($params['onlyEmpty']) && $params['onlyEmpty'] == 'on' ? 1 : 0;
        $showEmpty = 0; // Siempre 0 según el código original

        // Parámetros específicos de VS
        $onlyExpresas = isset($params['onlyExpresas']) && $params['onlyExpresas'] == 'on' ? 1 : 0;
        $onlyEntradasSalidas = isset($params['onlyEntradasSalidas']) && $params['onlyEntradasSalidas'] == 'on' ? 1 : 0;

        // Parámetros critical y sentido - dejar vacíos como en el proyecto antiguo
        // Estos parámetros no tienen controles en el formulario
        $str_critical = ''; // Vacío por defecto
        $str_sentido = ''; // Vacío por defecto
        
        // Fechas con valores por defecto
        if (isset($params['fechaInicio']) && $params['fechaInicio']) {
            $fechaInicio = $params['fechaInicio'];
            $fechaInicio_Date = $this->getDate($fechaInicio);
        } else {
            // Por defecto: hora actual, minuto 0
            $fechaInicio = date('d-m-Y H:i:s', mktime(date('H'), 0, 0, date('n'), date('d'), date('Y')));
            $fechaInicio_Date = date('Y-m-d H:i:s', mktime(date('H'), 0, 0, date('n'), date('d'), date('Y')));
        }
        
        if (isset($params['fechaTermino']) && $params['fechaTermino']) {
            $fechaTermino = $params['fechaTermino'];
            $fechaTermino_Date = $this->getDate($fechaTermino);
        } else {
            // Por defecto: hora actual, minuto 59
            $fechaTermino = date('d-m-Y H:i:s', mktime(date('H'), 59, 59, date('n'), date('d'), date('Y')));
            $fechaTermino_Date = date('Y-m-d H:i:s', mktime(date('H'), 59, 59, date('n'), date('d'), date('Y')));
        }
        
        // Validación de rango de fechas (máximo 2 días)
        $dStart = new \DateTime($fechaInicio_Date);
        $dEnd = new \DateTime($fechaTermino_Date);
        $dDiff = $dStart->diff($dEnd);
        
        if (intval($dDiff->format('%r%a')) > 2) {
            $dStart = $dEnd->sub(new \DateInterval('P2D'));
            $fechaInicio = $dStart->format('d-m-Y H:i:s');
            $fechaInicio_Date = $dStart->format('Y-m-d H:i:s');
        }
        
        // Obtener lista de espiras disponibles
        $arr_spires = [];
        if (!$request->isXMLHttpRequest()) {
            $conn = $this->entityManager->getConnection();
            $sql_spires = "SELECT * FROM tbl_cot_02_dispositivos
                          WHERE id_tipo = 13
                          AND reg_status = 1
                          ORDER BY orientacion, orden ASC";
            $stmt = $conn->prepare($sql_spires);
            $result = $stmt->executeQuery();
            $arr_spires = $result->fetchAllAssociative();
        }

        // Convertir array de espiras a string
        $str_spires = '';
        if (is_array($spires)) {
            $str_spires = implode(',', $spires);
        } else if (strlen($spires) > 0) {
            $str_spires = $spires;
        }

        // Llamar al stored procedure VS V8 con todos los parámetros
        $conn = $this->entityManager->getConnection();

        // Llamar al SP V8 (versión actual del proyecto antiguo)
        $sql_reg_pt = "CALL fnHistorialEspirasVSV8('$fechaInicio_Date','$fechaTermino_Date','$str_spires','$onlyZeros','$onlyEmpty','$showEmpty','$onlyExpresas','$onlyEntradasSalidas');";

        // Log para debug
        error_log("=== VS SPIRE HISTORY V8 DEBUG ===");
        error_log("Calling SP V8: " . $sql_reg_pt);
        error_log("Parameters - Start: $fechaInicio_Date, End: $fechaTermino_Date");
        error_log("Spires: $str_spires, OnlyZeros: $onlyZeros, OnlyEmpty: $onlyEmpty");
        error_log("OnlyExpresas: $onlyExpresas, OnlyEntradasSalidas: $onlyEntradasSalidas");

        try {
            $stmt = $conn->prepare($sql_reg_pt);
            $result = $stmt->executeQuery();
            $arr_reg = $result->fetchAllAssociative();

            error_log("SP executed successfully. Rows returned: " . count($arr_reg));

            if (!$arr_reg || count($arr_reg) === 0) {
                error_log("No results from SP, returning empty array");
                // Si no hay resultados, devolver array vacío
                $arr_reg = [];
            } else {
                // Verificar si tenemos la columna JSON_ESPIRAS
                if (isset($arr_reg[0]['JSON_ESPIRAS'])) {
                    error_log("JSON_ESPIRAS found in result");
                    $jsonData = $arr_reg[0]['JSON_ESPIRAS'];

                    // Validar que sea JSON válido
                    $decoded = json_decode($jsonData, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        error_log("Valid JSON with " . count($decoded) . " groups");
                        $arr_reg = $jsonData; // Keep as JSON string for template
                    } else {
                        error_log("Invalid JSON from SP: " . json_last_error_msg());
                        $arr_reg = [];
                    }
                } else {
                    error_log("JSON_ESPIRAS column not found. Available columns: " . implode(', ', array_keys($arr_reg[0])));
                    $arr_reg = [];
                }
            }
        } catch (\Exception $e) {
            // Log del error
            error_log('ERROR calling fnHistorialEspirasVSV8: ' . $e->getMessage());
            error_log('SQL was: ' . $sql_reg_pt);

            // Verificar si el SP existe
            try {
                $checkSP = "SHOW PROCEDURE STATUS WHERE Name = 'fnHistorialEspirasVSV8'";
                $checkStmt = $conn->prepare($checkSP);
                $checkResult = $checkStmt->executeQuery();
                $spExists = $checkResult->fetchAllAssociative();

                if (empty($spExists)) {
                    error_log("STORED PROCEDURE fnHistorialEspirasVSV8 DOES NOT EXIST!");
                } else {
                    error_log("SP exists but failed with: " . $e->getMessage());
                }
            } catch (\Exception $e2) {
                error_log("Could not check SP existence: " . $e2->getMessage());
            }

            // Si el SP no existe o hay error, devolver array vacío
            error_log("Returning empty array due to SP error");
            $arr_reg = [];
        }

        error_log("=== END VS SPIRE HISTORY DEBUG ===");
        
        // Si es petición AJAX, devolver JSON
        if ($request->isXMLHttpRequest()) {
            return new Response(
                json_encode(['arr_reg_spires' => $arr_reg]),
                200,
                ['Content-Type' => 'application/json']
            );
        }
        
        // Crear contexto mínimo de EasyAdmin para evitar errores
        $eaContext = [
            'i18n' => [
                'locale' => 'es',
                'translationDomain' => 'messages',
            ],
            'crud' => null,
            'assets' => [
                'favicon' => '/favicon.ico',
                'css_files' => [],
                'js_files' => [],
            ],
            'user' => [
                'isLoggedIn' => $this->getUser() !== null,
                'username' => $this->getUser() ? $this->getUser()->getUserIdentifier() : null,
            ],
        ];

        // Renderizar template VS
        return $this->render('admin/dashboard/cot/spire_history_vs_min.html.twig', [
            'arr_reg_spires' => $arr_reg,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'all_spires' => $arr_spires,
            'sel_spires' => $spires,
            'onlyEmpty' => $onlyEmpty,
            'onlyZeros' => $onlyZeros,
            'onlyExpresas' => $onlyExpresas,
            'onlyEntradasSalidas' => $onlyEntradasSalidas,
            'showEmpty' => $showEmpty,
            'return_file_name_pdf' => null,
            'ea' => $eaContext
        ]);
    }
    
    /**
     * Genera datos de prueba para VS cuando el SP no está disponible
     */
    private function getTestDataForVS($fechaInicio, $fechaTermino): string
    {
        // Generar datos de prueba similares a CN
        $startTime = strtotime($fechaInicio);
        $endTime = strtotime($fechaTermino);
        
        $testData = [];
        
        // Crear grupos de espiras VS con nomenclatura específica
        $vsSpires = [
            'VS-E01' => 'Espira Expresa 01',
            'VS-E02' => 'Espira Expresa 02',
            'VS-S01' => 'Espira Salida 01',
            'VS-S02' => 'Espira Salida 02',
            'VS-N01' => 'Espira Normal 01',
            'VS-N02' => 'Espira Normal 02'
        ];
        
        foreach ($vsSpires as $code => $name) {
            $group = [
                'g' => $name,
                'd' => []
            ];
            
            // Generar timeline para cada espira
            $currentTime = $startTime;
            while ($currentTime < $endTime) {
                $duration = rand(300, 1800); // 5 a 30 minutos
                $nextTime = min($currentTime + $duration, $endTime);
                
                // Estados: 1 = verde (OK), 0 = amarillo (warning), -1 = rojo (error)
                $state = rand(0, 100) < 70 ? 1 : (rand(0, 1) ? 0 : -1);
                
                $group['d'][] = [
                    'l' => $code,
                    't' => [$currentTime, $nextTime],
                    'v' => $state
                ];
                
                $currentTime = $nextTime;
            }
            
            $testData[] = $group;
        }
        
        return json_encode($testData);
    }
    
    /**
     * Endpoint para Bootstrap Table server-side
     */
    #[Route('/devices/data', name: 'devices_data', methods: ['GET', 'POST'])]
    public function devicesDataAction(Request $request): Response
    {
        $em = $this->entityManager;

        // Parámetros de Bootstrap Table
        $search = $request->get('search', '');
        $sort = $request->get('sort', 'nombre');
        $order = $request->get('order', 'asc');
        $offset = intval($request->get('offset', 0));
        $limit = intval($request->get('limit', 25));

        // Filtros adicionales
        $tipoId = $request->get('tipo', '');
        $estado = $request->get('estado', '');

        // Query base
        $qb = $em->createQueryBuilder();
        $qb->select('d', 'td', 'c')
            ->from('App\Entity\TblCot02Dispositivos', 'd')
            ->leftJoin('d.idTipo', 'td')
            ->leftJoin('d.concesionaria', 'c');

        // Aplicar búsqueda
        if (!empty($search)) {
            $qb->andWhere('d.nombre LIKE :search OR d.ip LIKE :search OR d.ubicacion LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filtro por tipo
        if (!empty($tipoId)) {
            $qb->andWhere('td.id = :tipo')
                ->setParameter('tipo', $tipoId);
        }

        // Filtro por estado
        if ($estado !== '') {
            $qb->andWhere('d.estado = :estado')
                ->setParameter('estado', $estado);
        }

        // Filtrar por concesiones del usuario
        if ($usr_concessions = $this->getUserConcessions()) {
            $qb->andWhere('c.idConcesionaria IN (:ids_concesionarias)')
                ->setParameter('ids_concesionarias', $usr_concessions);
        }

        // Total sin paginación
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT d.id)');
        $total = $countQb->getQuery()->getSingleScalarResult();

        // Ordenamiento
        $sortField = 'd.' . $sort;
        if ($sort == 'tipo') {
            $sortField = 'td.tipo';
        } elseif ($sort == 'concesionaria') {
            $sortField = 'c.nombre';
        }
        $qb->orderBy($sortField, strtoupper($order));

        // Paginación
        $qb->setFirstResult($offset)
            ->setMaxResults($limit);

        try {
            $dispositivos = $qb->getQuery()->getResult();
        } catch (\Exception $e) {
            error_log('Error en query dispositivos: ' . $e->getMessage());
            return new JsonResponse([
                'total' => 0,
                'rows' => []
            ]);
        }

        // Formatear resultados para Bootstrap Table
        $rows = [];
        foreach ($dispositivos as $dispositivo) {
            $tipo = $dispositivo->getIdTipo();
            $concesionaria = $dispositivo->getConcesionaria();

            $rows[] = [
                'id' => $dispositivo->getId(),
                'tipo' => $tipo ? $tipo->getTipo() : 'Sin tipo',
                'nombre' => $dispositivo->getNombre(),
                'ip' => $dispositivo->getIp(),
                'puerto' => $dispositivo->getPuerto(),
                'estado' => $dispositivo->getEstado(),
                'ubicacion' => $dispositivo->getUbicacion(),
                'concesionaria' => $concesionaria ? $concesionaria->getNombre() : 'Sin concesionaria',
                'ultima_actualizacion' => $dispositivo->getUpdatedAt() ? $dispositivo->getUpdatedAt()->format('Y-m-d H:i:s') : null
            ];
        }

        return new JsonResponse([
            'total' => $total,
            'rows' => $rows
        ]);
    }

    /**
     * Detalles de un dispositivo
     */
    #[Route('/device/{id}/detail', name: 'device_detail', methods: ['GET'])]
    public function deviceDetailAction(int $id): Response
    {
        $em = $this->entityManager;

        try {
            $dispositivo = $em->getRepository('App\Entity\TblCot02Dispositivos')->find($id);

            if (!$dispositivo) {
                return new Response('Dispositivo no encontrado', 404);
            }

            // HTML de detalle (simplificado)
            $html = '<div class="row">
                <div class="col-md-6">
                    <h5>Información General</h5>
                    <table class="table table-sm">
                        <tr><th>Nombre:</th><td>' . $dispositivo->getNombre() . '</td></tr>
                        <tr><th>IP:</th><td>' . $dispositivo->getIp() . '</td></tr>
                        <tr><th>Puerto:</th><td>' . ($dispositivo->getPuerto() ?: 'N/A') . '</td></tr>
                        <tr><th>Estado:</th><td>' . ($dispositivo->getEstado() ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>') . '</td></tr>
                        <tr><th>Ubicación:</th><td>' . ($dispositivo->getUbicacion() ?: 'N/A') . '</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h5>Información Técnica</h5>
                    <table class="table table-sm">
                        <tr><th>Tipo:</th><td>' . ($dispositivo->getIdTipo() ? $dispositivo->getIdTipo()->getTipo() : 'N/A') . '</td></tr>
                        <tr><th>Modelo:</th><td>' . ($dispositivo->getModelo() ?: 'N/A') . '</td></tr>
                        <tr><th>Serial:</th><td>' . ($dispositivo->getSerial() ?: 'N/A') . '</td></tr>
                        <tr><th>Firmware:</th><td>' . ($dispositivo->getFirmware() ?: 'N/A') . '</td></tr>
                    </table>
                </div>
            </div>';

            return new Response($html);
        } catch (\Exception $e) {
            error_log('Error obteniendo detalle dispositivo: ' . $e->getMessage());
            return new Response('Error al cargar detalles', 500);
        }
    }

    /**
     * Ping a dispositivo
     */
    #[Route('/device/ping', name: 'device_ping', methods: ['POST'])]
    public function devicePingAction(Request $request): JsonResponse
    {
        $ip = $request->request->get('ip');

        if (!$ip) {
            return new JsonResponse(['success' => false, 'message' => 'IP no proporcionada']);
        }

        // Validar formato IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return new JsonResponse(['success' => false, 'message' => 'IP inválida']);
        }

        // Ejecutar ping
        $output = [];
        $result = 0;
        exec("ping -c 1 -W 1 " . escapeshellarg($ip), $output, $result);

        $success = $result === 0;

        return new JsonResponse([
            'success' => $success,
            'ip' => $ip,
            'message' => $success ? 'Dispositivo responde' : 'Sin respuesta',
            'output' => implode("\n", $output)
        ]);
    }

    /**
     * Historial de un dispositivo
     */
    #[Route('/device/{id}/history', name: 'device_history', methods: ['GET'])]
    public function deviceHistoryAction(int $id): Response
    {
        // TODO: Implementar vista de historial del dispositivo
        // Por ahora redirigir a la lista
        return $this->redirectToRoute('cot_index');
    }


    /**
     * Actualizar un dispositivo vía AJAX
     */
    #[AdminRoute('/device/{id}/update', name: 'device_update')]
    public function updateDeviceAction(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Verificar permisos
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            // Obtener dispositivo
            $device = $em->getRepository(Device::class)->find($id);

            if (!$device) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Dispositivo no encontrado'
                ], 404);
            }

            // Obtener datos del request
            $data = json_decode($request->getContent(), true);

            if (!$data) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Datos inválidos'
                ], 400);
            }

            // Actualizar campos solo si están presentes
            if (isset($data['descripcion'])) {
                $device->setDescripcion($data['descripcion']);
            }
            if (isset($data['ip'])) {
                $device->setIp($data['ip']);
            }
            if (isset($data['km'])) {
                $device->setKm((string)$data['km']);
            }
            if (isset($data['orientacion'])) {
                $device->setOrientacion($data['orientacion']);
            }
            if (isset($data['estado'])) {
                $device->setEstado((int)$data['estado']);
            }
            if (isset($data['atributos'])) {
                $device->setAtributos($data['atributos']);
            }

            // Actualizar metadata
            $device->setUpdatedAt(new \DateTime());
            // TODO: Obtener usuario actual cuando esté implementado
            // $device->setUpdatedBy($this->getUser()->getId());

            // Guardar cambios
            $em->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Dispositivo actualizado correctamente',
                'device_id' => $id
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Error al actualizar dispositivo: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Monitor de dispositivos Vespucio Sur (VS)
     */
    #[AdminRoute('/vs_index', name: 'vs_index')]
    public function vsIndexAction(Request $request): Response
    {
        // Get parameters
        $params = $request->getMethod() == 'POST' ? $request->request->all() : $request->query->all();

        $id_device_type_fr = isset($params['id']) ? (int)$params['id'] : -1;
        $fixed = isset($params['fixed']) && $params['fixed'] == 'true';
        $videowall = isset($params['videowall']) && $params['videowall'] == 'true';
        $device_status = $params['device_status'] ?? '';
        $input_device_finder = $params['input_device_finder'] ?? '';
        $contract_ui = isset($params['contract_ui']) && $params['contract_ui'] == 'true';
        $grid_items_width = $params['grid_items_width'] ?? '12';

        $em = $this->entityManager;

        // VS concession ID is 20
        $concessionId = 20;

        // ==================== COMENTADO 2025-10-03 - NO DESCOMENTAR SIN REVISIÓN ====================
        // TblCot06AlarmasDispositivos NO forma parte del módulo de monitoreo operativo
        // Ver documentación completa en dashboardAction() línea 69
        // Sistema de notificaciones navbar incompleto - NO migrado al nuevo proyecto
        // ANTES DE DESCOMENTAR: Revisar con arquitecto del sistema y validar caso de uso real
        // =========================================================================================

        /*
        // Get recent device alarms for VS
        $qb_alarmas = $em->createQueryBuilder();
        $qb_alarmas->select('a, alarm, disp')
            ->from('App\Entity\TblCot06AlarmasDispositivos', 'a')
            ->leftJoin('a.idAlarma', 'alarm')
            ->leftJoin('a.idDispositivo', 'disp')
            ->where('a.estado = 0')
            ->andWhere('a.concesionaria = :concession')
            ->setParameter('concession', $concessionId)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(25);

        $alarmas_dispositivos = $qb_alarmas->getQuery()->getArrayResult();
        */
        $alarmas_dispositivos = []; // Array vacío para mantener compatibilidad

        // Get device types for VS
        $qb_tipos = $em->createQueryBuilder();
        $qb_tipos->select('t')
            ->from('App\Entity\TblCot01TiposDispositivos', 't')
            ->where('t.concesionaria = :concession')
            ->setParameter('concession', $concessionId)
            ->orderBy('t.id', 'ASC');

        // Filter by device type if specified
        if ($id_device_type_fr > 0) {
            $qb_tipos->andWhere('t.id = :device_type')
                ->setParameter('device_type', $id_device_type_fr);
        }

        $tipos_dispositivos = $qb_tipos->getQuery()->getArrayResult();
        $all_tipos_dispositivos = $tipos_dispositivos; // Keep all types for filters

        // Get devices for VS
        $qb_dispositivos = $em->createQueryBuilder();
        $qb_dispositivos->select('d, type')
            ->from('App\Entity\TblCot02Dispositivos', 'd')
            ->leftJoin('d.idTipo', 'type')
            ->where('d.regStatus = 1')
            ->andWhere('d.concesionaria = :concession')
            ->setParameter('concession', $concessionId)
            ->orderBy('type.tipo', 'ASC')
            ->addOrderBy('d.nombre', 'ASC');

        // Apply device type filter
        if ($id_device_type_fr > 0) {
            $qb_dispositivos->andWhere('type.id = :device_type')
                ->setParameter('device_type', $id_device_type_fr);
        } else {
            // Exclude certain device types (4=Espiras, 5=Galibos, 10=Red)
            $qb_dispositivos->andWhere('type.id NOT IN (4, 5, 10)');
        }

        // Apply device status filter
        if ($device_status == 'device_active') {
            $qb_dispositivos->andWhere('d.estado = 1');
        } elseif ($device_status == 'device_unactive') {
            $qb_dispositivos->andWhere('d.estado = 0');
        }

        // Apply device name filter
        if (!empty($input_device_finder)) {
            $qb_dispositivos->andWhere('d.nombre LIKE :device_name')
                ->setParameter('device_name', '%' . $input_device_finder . '%');
        }

        $dispositivos = $qb_dispositivos->getQuery()->getArrayResult();

        // Calculate device counts by type and status
        $count_status_type = [];
        foreach ($dispositivos as $device) {
            $type_id = $device['idTipo']['id'] ?? 0;
            if (!isset($count_status_type[$type_id])) {
                $count_status_type[$type_id] = [
                    'total' => 0,
                    'active' => 0,
                    'unactive' => 0
                ];
            }
            $count_status_type[$type_id]['total']++;
            if ($device['estado'] == 1) {
                $count_status_type[$type_id]['active']++;
            } else {
                $count_status_type[$type_id]['unactive']++;
            }
        }

        // Get last update times from config
        try {
            $configRepo = $em->getRepository('App\Entity\TblCot00Config');
            $stentofonConfig = $configRepo->find('StentofonLastUpdateDateTime');
            $swIpConfig = $configRepo->find('SwIpDevicesMonitorLastUpdateDateTime');

            $stentofonLastUpdate = $stentofonConfig ?
                \DateTime::createFromFormat('Y-m-d H:i:s', $stentofonConfig->getValor()) :
                new \DateTime();
            $swIpLastUpdate = $swIpConfig ?
                \DateTime::createFromFormat('Y-m-d H:i:s', $swIpConfig->getValor()) :
                new \DateTime();
        } catch (\Exception $e) {
            $stentofonLastUpdate = new \DateTime();
            $swIpLastUpdate = new \DateTime();
        }

        // Prepare response data
        $responseData = [
            'id_device_type_fr' => $id_device_type_fr,
            'alarmas_dispositivos' => $alarmas_dispositivos,
            'all_tipos_dispositivos' => $all_tipos_dispositivos,
            'tipos_dispositivos' => $tipos_dispositivos,
            'dispositivos' => $dispositivos,
            'videowall' => $videowall,
            'fixed' => $fixed,
            'device_status' => $device_status,
            'input_device_finder' => $input_device_finder,
            'contract_ui' => $contract_ui,
            'real_mode' => true,
            'count_status_type' => $count_status_type,
            'grid_items_width' => $grid_items_width,
            'transitions' => 0,
            'StentofonLastUpdateDateTime' => $stentofonLastUpdate->format('d-m-Y H:i:s'),
            'SwIpDevicesMonitorLastUpdateDateTime' => $swIpLastUpdate->format('d-m-Y H:i:s')
        ];

        // Return JSON for AJAX requests
        if ($request->isXmlHttpRequest()) {
            return $this->json($responseData);
        }

        // Return HTML template for normal requests
        return $this->render('dashboard/cot/vs_index.html.twig', $responseData);
    }

    #[AdminRoute('/sosindex/{id}', name: 'cot_sosindex')]
    public function sosindexAction(Request $request, int $id = 1): Response
    {
        // Get parameters from request
        $params = $request->getMethod() === 'POST' ? $request->request->all() : $request->query->all();
        $id_device_type_fr = $id;

        // Extract display parameters
        $fixed = ($params['fixed'] ?? '') === 'true';
        $videowall = ($params['videowall'] ?? '') === 'true';
        $device_status = $params['device_status'] ?? '';
        $input_device_finder = $params['input_device_finder'] ?? '';
        $contract_ui = ($params['contract_ui'] ?? '') === 'true';
        $grid_items_width = $params['grid_items_width'] ?? '12';

        // Get entity manager and connection
        $em = $this->entityManager;
        $connection = $em->getConnection();

        // Obtener concesiones para filtrado - Replicado desde proyecto antiguo (líneas 853-870)
        if ((!isset($params['concessions']) || $params['concessions'] == "") && $this->getUser()) {
            $concessions = $this->getUserConcessions();
        } elseif (isset($params['concessions']) && $params['concessions']) {
            $concessions = $params['concessions'];
        } else {
            $concessions = [];
        }

        // Query SOS sensor alarms - SIN filtro de concesionaria (igual que proyecto antiguo)
        $asd_sql = "SELECT tcot09.*, tcot02.id AS id_device, tcot02.nombre, tcot02.km, tcot02.eje, tcot04.nombre as eje_nombre, tcot02.orientacion
                    FROM tbl_cot_09_alarmas_sensores_dispositivos tcot09
                    LEFT JOIN tbl_cot_02_dispositivos tcot02 ON (tcot02.id = tcot09.id_dispositivo)
                    LEFT JOIN tbl_cot_04_ejes tcot04 ON (tcot04.id = tcot02.eje)
                    WHERE tcot09.aceptado = 0 AND tcot09.updated_at IS NULL AND tcot09.finished_at IS NULL
                    ORDER BY tcot09.created_at ASC";
        
        $stmt = $connection->prepare($asd_sql);
        $result = $stmt->executeQuery();
        $asd_ds = $result->fetchAllAssociative();

        // ==================== COMENTADO 2025-10-03 - NO DESCOMENTAR SIN REVISIÓN ====================
        // TblCot06AlarmasDispositivos NO forma parte del módulo de monitoreo operativo
        // Ver documentación completa en dashboardAction() línea 69
        //
        // IMPORTANTE: SOS Monitor tiene su propio sistema de alarmas correcto:
        // - tbl_cot_09_alarmas_sensores_dispositivos (línea 1466-1475) ← ESTE ES EL CORRECTO
        // - DeviceAlert NO se usa en SOS, solo alarmas de sensores SOS
        //
        // Sistema de notificaciones navbar incompleto - NO migrado al nuevo proyecto
        // ANTES DE DESCOMENTAR: Revisar con arquitecto del sistema y validar caso de uso real
        // =========================================================================================

        /*
        // Get device alarms - Replicando exactamente query del proyecto antiguo (líneas 770-783)
        $qb_alarmas_dispositivos = $em->createQueryBuilder();
        $qb_alarmas_dispositivos->select('ad')
            ->from(DeviceAlert::class, 'ad')
            ->leftJoin('ad.idAlarma', 'a')->addSelect('a')
            ->leftJoin('ad.idDispositivo', 'd')->addSelect('d')
            ->leftJoin('ad.concesionaria', 'c')->addSelect('c')
            ->orderBy('ad.id', 'ASC')
            ->andWhere('ad.estado = 0');
            //->andWhere('c.idConcesionaria IN (:ids_concesionarias)')
            //->setParameter('ids_concesionarias', $dispositivos)
            //->setMaxResults(25)

        $alarmas_dispositivos = $qb_alarmas_dispositivos->getQuery()->getArrayResult();
        */
        $alarmas_dispositivos = []; // Array vacío para mantener compatibilidad

        // Get device types - Replicando exactamente query del proyecto antiguo
        $qb_tipos_dispositivos = $em->createQueryBuilder();
        $qb_tipos_dispositivos->select('td')
            ->from(DeviceType::class, 'td')
            ->orderBy('td.id', 'ASC')
            ->andWhere('td.mostrar = 1');
            //->andWhere('conc.idConcesionaria in (:ids_concesionarias)')
            //->setParameter('ids_concesionarias', $dispositivos)
            //->setMaxResults(25)

        if (!$request->isXmlHttpRequest()) {
            $all_tipos_dispositivos = $qb_tipos_dispositivos->getQuery()->getArrayResult();

            // If first time entry with -1, get first available type
            if ($id_device_type_fr == -1) {
                if ($all_tipos_dispositivos[0] and $all_tipos_dispositivos[0]['id']) {
                    $id_device_type_fr = $all_tipos_dispositivos[0]['id'];
                }
            }
        }

        if ($id_device_type_fr > 0) {
            $qb_tipos_dispositivos->andWhere("td.id = $id_device_type_fr");
        }

        $tipos_dispositivos = $qb_tipos_dispositivos->getQuery()->getArrayResult();

        // Get devices - Replicado exactamente desde proyecto antiguo (líneas 832-872)
        $qb_dispositivos = $em->createQueryBuilder();
        $qb_dispositivos->select('d')
            ->from(Device::class, 'd')
            ->leftJoin('d.concesionaria', 'c')->addSelect('c')
            ->leftJoin('d.idTipo', 't')->addSelect('t')
            ->leftJoin('d.eje', 'e')->addSelect('e')
            ->leftJoin('d.tramo', 'tr')->addSelect('tr')
            ->orderBy('t.tipo', 'ASC')
            ->addOrderBy('d.orden', 'ASC')
            ->andWhere('t.mostrar = 1')
            ->andWhere('d.regStatus = 1');
            //->andWhere('c.idConcesionaria IN (:ids_concesionarias)')
            //->setParameter('ids_concesionarias', $dispositivos)
            //->setMaxResults(25)

        // Filtro por tipo de dispositivo
        if ($id_device_type_fr > 0) {
            $qb_dispositivos->andWhere('t.id = :tipo_id')
                ->setParameter('tipo_id', $id_device_type_fr);
        }

        // Filtro por concesiones del usuario (dinámico)
        if ($concessions && $concessions[0] != "") {
            $qb_dispositivos->andWhere('c.idConcesionaria IN (:ids_concesionarias)')
                ->setParameter('ids_concesionarias', $concessions);
        }

        // Filtro por buscador de dispositivos
        if (!empty($input_device_finder)) {
            $qb_dispositivos->andWhere('d.nombre LIKE :finder OR d.ip LIKE :finder')
                ->setParameter('finder', '%' . $input_device_finder . '%');
        }

        $dispositivos = $qb_dispositivos->getQuery()->getArrayResult();

        // Calcular estado general de sensores SOS para cada dispositivo
        // Replicado desde proyecto antiguo (líneas 876-910)
        foreach ($dispositivos as $key => $value) {
            if ($value['atributos']) {
                $dv_atrbutes = is_array($value['atributos']) ? $value['atributos'] : json_decode($value['atributos'], true);
                $dispositivos[$key]['atributos'] = $dv_atrbutes;

                $general_status = true;

                // SOLO 3 sensores críticos como en proyecto antiguo (línea 896)
                $sensors_to_find = ['f_all_est', 'f_all_idr', 'f_all_est2'];

                foreach ($sensors_to_find as $sensor_to_find) {
                    if (isset($dv_atrbutes['sos_sensors'][$sensor_to_find]) && $dv_atrbutes['sos_sensors'][$sensor_to_find] == 1) {
                        $general_status = false;
                    }
                }

                $dispositivos[$key]['estado'] = $general_status ? 1 : 0;
            }
        }

        // Count device statuses
        $count_status_type = [
            'all' => count($dispositivos),
            'device_active' => 0,
            'device_unactive' => 0,
            'device_alarm' => 0,
            'device_not_monitored' => 0
        ];

        foreach ($dispositivos as $device) {
            if ($device['estado'] == 1) {
                $count_status_type['device_active']++;
            } elseif ($device['estado'] == 0) {
                $count_status_type['device_unactive']++;
            } elseif ($device['estado'] == 2) {
                $count_status_type['device_alarm']++;
            } else {
                $count_status_type['device_not_monitored']++;
            }
        }

        // Get last update times
        $stentofonLastUpdate = new \DateTime();
        $swIpLastUpdate = new \DateTime();

        // Prepare response data
        $responseData = [
            'concessions' => $concessions,
            'id_device_type_fr' => $id_device_type_fr,
            'alarmas_dispositivos' => $alarmas_dispositivos,
            'all_tipos_dispositivos' => $all_tipos_dispositivos ?? [],
            'tipos_dispositivos' => $tipos_dispositivos,
            'dispositivos' => $dispositivos,
            'videowall' => $videowall,
            'fixed' => $fixed,
            'device_status' => $device_status,
            'input_device_finder' => $input_device_finder,
            'contract_ui' => $contract_ui,
            'real_mode' => true,
            'count_status_type' => $count_status_type,
            'grid_items_width' => $grid_items_width,
            'transitions' => 0,
            'asd_ds' => $asd_ds,
            'SwIpDevicesMonitorLastUpdateDateTime' => $swIpLastUpdate->format('d-m-Y H:i:s')
        ];

        // Return JSON for AJAX requests
        if ($request->isXmlHttpRequest()) {
            return $this->json($responseData);
        }

        // Render videowall template - reutilizado para monitor SOS con detección de módulo
        return $this->render('dashboard/cot/videowall.html.twig', $responseData);
    }

    /**
     * Procesar aceptación de alarmas de sensores SOS
     * Ruta migrada: sgv_cot_sosindex_alarms_process → /asp
     * AJAX endpoint para marcar alarmas SOS como aceptadas
     *
     * Rutas generadas:
     * - /cot/asp (POST, PUT) - Compatibilidad con proyecto legacy
     * - /admin/cot/asp (todos los métodos) - Ruta admin de EasyAdmin
     */
    #[Route('/asp', name: 'asp_legacy', methods: ['POST', 'PUT'])]
    #[AdminRoute('/asp', name: 'cot_sos_alarm_process')]
    public function sosSensorAlarmProcessAction(Request $request): JsonResponse
    {
        $params = $request->request->all();
        $action = $params['action'] ?? false;
        $id = $params['id'] ?? '';

        if ($action === 'process' && $this->isCsrfTokenValid($action . '-asd', $params['token'])) {
            $currentUser = $this->getUser();
            $date = new \DateTime("now", new \DateTimeZone('America/Santiago'));

            $conn = $this->entityManager->getConnection();
            $stmt = $conn->prepare(
                "UPDATE tbl_cot_09_alarmas_sensores_dispositivos
                 SET aceptado = 1, updated_at = :date, updated_by = :user
                 WHERE id = :id AND updated_at IS NULL AND finished_at IS NULL"
            );

            $result = $stmt->executeQuery([
                'date' => $date->format('Y-m-d H:i:s.u'),
                'user' => $currentUser->getId(),
                'id' => $id
            ]);

            return $this->json(['status' => $result->rowCount() > 0]);
        }

        return $this->json(['status' => 'No se realizaron cambios'], 400);
    }

    /**
     * Reporte de alarmas de sensores SOS
     * Migrado del proyecto antiguo: sgvDashboardBundle:Cot:listSosReportStatus
     */
    #[AdminRoute('/sos_report_status', name: 'cot_sos_report_status')]
    public function listSosReportStatusAction(Request $request): Response
    {
        // Obtener parámetros
        $params = $request->getMethod() == 'POST' ? $request->request->all() : $request->query->all();

        $action = isset($params['action']) && $params['action'] ? $params['action'] : false;
        $regStatus = isset($params['regStatus']) && $params['regStatus'] ? $params['regStatus'] : '0';
        $alarmType = (isset($params['alarmType']) && is_array($params['alarmType']) && count($params['alarmType'])) ? implode(",", $params['alarmType']) : '1,2,3';
        $searchTxt = isset($params['searchTxt']) && $params['searchTxt'] ? $params['searchTxt'] : "";
        $rowsPerPage = isset($params['rowsPerPage']) && $params['rowsPerPage'] ? $params['rowsPerPage'] : 100;
        $maxPages = 1;
        $currentPage = isset($params['currentPage']) && $params['currentPage'] ? $params['currentPage'] : 1;
        $where = false;

        // Filtros de fecha
        if (isset($params['fechaInicio']) && $params['fechaInicio']) {
            $fechaInicio = $params['fechaInicio'];
            $fechaInicio_Date = $this->getDate($fechaInicio);
            if ($fechaInicio_Date) {
                $where = " AND tcot09.created_at >='$fechaInicio_Date'";
            }
        } else {
            $fechaInicio = date('d-m-Y H:i:s', mktime(0, 0, 0, date('n'), date('d') - 7, date('Y')));
            $fechaInicio_Date = $this->getDate($fechaInicio);
            if ($fechaInicio_Date) {
                $where = " AND tcot09.created_at >='$fechaInicio_Date'";
            }
        }

        if (isset($params['fechaTermino'])) {
            $fechaTermino = $params['fechaTermino'];
            $fechaTermino_Date = $this->getDate($fechaTermino);
            if ($fechaTermino_Date) {
                $where .= " AND tcot09.created_at  <='$fechaTermino_Date'";
            }
        } else {
            $fechaTermino = date('d-m-Y H:i:s', mktime(23, 59, 59, date('n'), date('d'), date('Y')));
            $fechaTermino_Date = $this->getDate($fechaTermino);
            if ($fechaTermino_Date) {
                $where = " AND tcot09.created_at <='$fechaTermino_Date'";
            }
        }

        // Aplicar filtros de estado
        $human_params_tipos = '';
        switch ($regStatus) {
            case '0':
                $human_params_tipos = 'Todas';
                break;
            case '1':
                $where .= " AND tcot09.updated_at IS NULL AND tcot09.finished_at IS NULL";
                $human_params_tipos = 'Creadas';
                break;
            case '2':
                $where .= " AND tcot09.updated_at IS NOT NULL AND tcot09.finished_at IS NULL";
                $human_params_tipos = 'Aceptadas';
                break;
            case '3':
                $where .= " AND tcot09.updated_at IS NOT NULL AND tcot09.finished_at IS NOT NULL";
                $human_params_tipos = 'Aceptadas y Finalizadas';
                break;
            case '4':
                $where .= " AND tcot09.updated_at IS NULL AND tcot09.finished_at IS NOT NULL";
                $human_params_tipos = 'Finalizadas';
                break;
        }

        // Filtros de tipo de sensor
        $human_params_sensores = ' Tipo de Alarma:';
        if ($alarmType) {
            $where .= " AND tcot09.id_sensor in($alarmType)";
            foreach (explode(',', $alarmType) as $item) {
                switch ($item) {
                    case 1:
                        $human_params_sensores .= ' Extintor 1';
                        break;
                    case 2:
                        $human_params_sensores .= ' Extintor 2';
                        break;
                    case 3:
                        $human_params_sensores .= ' Red Húmeda';
                        break;
                }
            }
        }

        if ($searchTxt) {
            $where .= " AND tcot02.nombre like '%" . $searchTxt . "%'";
        }

        // Filtrar por Costanera Norte (concesionaria 22) - Reporte exclusivo CN
        $where .= " AND tcot02.concesionaria = 22";

        // Consultar datos
        $conn = $this->entityManager->getConnection();
        $sqlMap = "SELECT COUNT(tcot09.id) as totalrows
                FROM tbl_cot_09_alarmas_sensores_dispositivos tcot09
                Left Join tbl_cot_02_dispositivos tcot02 ON (tcot02.id = tcot09.id_dispositivo)
                WHERE 1 = 1 $where";
        $stmt = $conn->prepare($sqlMap);
        $result = $stmt->executeQuery();
        $total_count_rows_data_table = $result->fetchAllAssociative();

        if ($total_count_rows_data_table) {
            $total_count_rows_data_table = $total_count_rows_data_table[0]['totalrows'];

            $maxPages = ceil($total_count_rows_data_table / $rowsPerPage);
            $offset = ($currentPage * $rowsPerPage) - $rowsPerPage;

            $sql_limit = '';
            if ($action != 'excel') {
                $sql_limit = " LIMIT $rowsPerPage OFFSET $offset";
            }

            $sql = "SELECT tcot09.* ,tcot02.nombre
                FROM tbl_cot_09_alarmas_sensores_dispositivos tcot09
                Left Join tbl_cot_02_dispositivos tcot02 ON (tcot02.id = tcot09.id_dispositivo)
                WHERE 1=1 $where
                ORDER BY tcot09.created_at DESC $sql_limit
                ;";
            $stmt = $conn->prepare($sql);
            $result = $stmt->executeQuery();
            $data_table = $result->fetchAllAssociative();
        } else {
            $data_table = array();
        }

        $data_table_json = json_encode($data_table);
        $arr_global_data_vars = array(
            'data_table' => $data_table_json,
            'fechaInicio' => $fechaInicio,
            'fechaTermino' => $fechaTermino,
            'regStatus' => $regStatus,
            'alarmType' => explode(',', $alarmType),
            'searchTxt' => $searchTxt,
            'toolbarIsToggle' => (isset($params['toolbarIsToggle']) && $params['toolbarIsToggle'] == 'true') ? true : false,
            'tableIsFullscreen' => (isset($params['tableIsFullscreen']) && $params['tableIsFullscreen'] == 'true') ? true : false,
            'search' => (isset($params['search']) && $params['search']) ? $params['search'] : '',
            'autoUpdate' => (isset($params['autoUpdate'])) ? ($params['autoUpdate'] == 'true' ? true : false) : false,
            'autoUpdateInterval' => (isset($params['autoUpdateInterval']) && $params['autoUpdateInterval']) ? $params['autoUpdateInterval'] : 30,
            'renderPDF' => false,
            'total_count_rows_data_table' => $total_count_rows_data_table,
            'maxPages' => $maxPages == 0 ? 1 : $maxPages,
            'rowsPerPage' => $rowsPerPage,
            'currentPage' => $currentPage,
        );

        // Acción AJAX - retornar solo tabla
        if ($action == 'ajax') {
            return $this->render('dashboard/cot/sensors_alarms/report/contenedor_tabla.html.twig', $arr_global_data_vars);
        }
        // Acción Excel - generar archivo
        elseif ($action == 'excel') {
            $human_params = "Periodo: desde $fechaInicio " . ($fechaTermino ? " - al $fechaTermino" : '') . " - Estado: $human_params_tipos" . ($searchTxt ? " - Coinciden con $searchTxt" : '') . $human_params_sensores;

            try {
                $file_name = "lista_alarmas_sensores_sos.xlsx";
                $template_path = $this->getParameter('siv_templates_directory');
                $template_file = $template_path . '/' . $file_name;

                // Try to load template, if not exists create from scratch
                if (file_exists($template_file)) {
                    $spreadsheet = IOFactory::load($template_file);
                } else {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    // Create basic headers
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setCellValue('B1', 'REPORTE DE ALARMAS SENSORES SOS');
                    $sheet->setCellValue('B8', 'Id Alarma');
                    $sheet->setCellValue('C8', 'Equipo');
                    $sheet->setCellValue('D8', 'Tipo');
                    $sheet->setCellValue('E8', 'Estado');
                    $sheet->setCellValue('F8', 'Creación');
                    $sheet->setCellValue('G8', 'Aceptación');
                    $sheet->setCellValue('H8', 'Finalización');
                }
                $sheet = $spreadsheet->getActiveSheet();

                $total_count_rows_data_table = count($data_table);
                $sheet->setCellValue('B5', $human_params)
                    ->setCellValue('B6', 'Total: ' . $total_count_rows_data_table)
                    ->setCellValue('G6', date('d-m-Y H:i:s'));

                $idx_rw_titles = 8;
                $idx_rw = $idx_rw_titles + 1;

                for ($i = 0; $i < $total_count_rows_data_table; $i++) {
                    $currentRowData = $data_table[$i];
                    $idx_rw = $idx_rw_titles + 1 + $i;

                    $la_time = new \DateTimeZone('America/Santiago');

                    $TipoSensor = '-';
                    switch ($currentRowData['id_sensor'] ?? null) {
                        case 1: $TipoSensor = 'Extintor 1'; break;
                        case 2: $TipoSensor = 'Extintor 2'; break;
                        case 3: $TipoSensor = 'Red Húmeda'; break;
                    }

                    $EstadoSensor = 'Activa';
                    if (!empty($currentRowData['finished_at'])) {
                        $EstadoSensor = (!empty($currentRowData['aceptado']) && $currentRowData['aceptado'] == 1) ? 'Aceptada Y Finalizada' : 'Finalizada';
                    } elseif (!empty($currentRowData['aceptado']) && $currentRowData['aceptado'] == 1) {
                        $EstadoSensor = 'Aceptada';
                    }

                    $sheet->setCellValue('B' . $idx_rw, $currentRowData['id'] ?? '')
                        ->setCellValue('C' . $idx_rw, $currentRowData['nombre'] ?? '')
                        ->setCellValue('D' . $idx_rw, $TipoSensor)
                        ->setCellValue('E' . $idx_rw, $EstadoSensor);

                    // Fechas con formato Excel
                    if (!empty($currentRowData['created_at'])) {
                        $created_at = new \DateTime($currentRowData['created_at']);
                        $created_at->setTimezone($la_time);
                        $created_at_excel = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($created_at);
                        $sheet->getStyle('F' . $idx_rw)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:MM');
                        $sheet->setCellValueExplicit('F' . $idx_rw, $created_at_excel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } else {
                        $sheet->setCellValue('F' . $idx_rw, '');
                    }

                    if (!empty($currentRowData['updated_at'])) {
                        $updated_at = new \DateTime($currentRowData['updated_at']);
                        $updated_at->setTimezone($la_time);
                        $updated_at_excel = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($updated_at);
                        $sheet->getStyle('G' . $idx_rw)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:MM');
                        $sheet->setCellValueExplicit('G' . $idx_rw, $updated_at_excel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } else {
                        $sheet->setCellValue('G' . $idx_rw, '');
                    }

                    if (!empty($currentRowData['finished_at'])) {
                        $finished_at = new \DateTime($currentRowData['finished_at']);
                        $finished_at->setTimezone($la_time);
                        $finished_at_excel = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($finished_at);
                        $sheet->getStyle('H' . $idx_rw)->getNumberFormat()->setFormatCode('dd-mm-yyyy hh:MM');
                        $sheet->setCellValueExplicit('H' . $idx_rw, $finished_at_excel, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                    } else {
                        $sheet->setCellValue('H' . $idx_rw, '');
                    }
                }

                $path = $this->getParameter('siv_templates_directory');
                $sheet->setTitle('Reporte');
                $spreadsheet->setActiveSheetIndex(0);

                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                $return_file_name = 'Reporte  [' . uniqid('', true) . '].xlsx';
                $writer->save($path . '/' . $return_file_name);

                $file = $path . '/' . $return_file_name;
                $response = new BinaryFileResponse($file);

                $response->headers->set('Content-Type', mime_content_type($file) . '; charset=utf-8');
                $response->headers->set('Content-Disposition', 'attachment;filename="' . $file_name . '"');
                $response->headers->set('Pragma', 'public');
                $response->headers->set('Cache-Control', 'maxage=1');

                return $response;

            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                error_log('Error de PhpSpreadsheet: ' . $e->getMessage());
                return new Response("Se produjo un error al generar el archivo Excel. Por favor, contacte al administrador.", 500);
            } catch (\Exception $e) {
                error_log('Error inesperado al generar el Excel: ' . $e->getMessage());
                return new Response("Se produjo un error inesperado. Por favor, contacte al administrador.", 500);
            }
        }
        // Vista normal - renderizar template
        else {
            return $this->render('dashboard/cot/sensors_alarms/report/get_list.html.twig', $arr_global_data_vars);
        }
    }
}