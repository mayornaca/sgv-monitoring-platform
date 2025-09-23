<?php

namespace App\Controller\Dashboard;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;

#[AdminDashboard(routePath: '/cot', routeName: 'cot_dashboard')]
class CotController extends AbstractDashboardController
{
    protected EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
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
     * Dashboard principal del COT - Migrado del controller original
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET', 'POST'])]
    public function dashboardAction(Request $request): Response
    {
        $em = $this->entityManager;
        
        // Query para alarmas de dispositivos
        $qb_alarmas_dispositivos = $em->createQueryBuilder();
        $qb_alarmas_dispositivos->select('reg')
            ->from('App\Entity\TblCot06AlarmasDispositivos', 'reg')
            ->leftJoin('reg.idAlarma', 'alarm')->addSelect('alarm')
            ->leftJoin('reg.idDispositivo', 'disp')->addSelect('disp')
            ->leftJoin('reg.concesionaria', 'conc')->addSelect('conc')
            ->orderBy('reg.id', 'ASC')
            ->andWhere('reg.estado = 0'); // Solo alarmas activas
        
        // Filtrar por concesiones del usuario si las tiene
        if ($usr_concessions = $this->getUserConcessions()) {
            $qb_alarmas_dispositivos->andWhere('conc.idConcesionaria IN (:ids_concesionarias)')
                ->setParameter('ids_concesionarias', $usr_concessions);
        }
        
        try {
            $rs_alarmas_dispositivos = $qb_alarmas_dispositivos->getQuery()->getArrayResult();
        } catch (\Exception $e) {
            // Si falla la consulta (entidad no mapeada, etc), usar array vacío
            $rs_alarmas_dispositivos = [];
            error_log('Error en query alarmas_dispositivos: ' . $e->getMessage());
        }
        
        // Usar conexión DBAL desde entity manager como en el proyecto original
        $conn = $this->entityManager->getConnection();
        $sql = "select id,tipo,icono,activos,inactivos,total,por_Activos,por_inactivos, concesionaria From vi_resumen_estado_dispositivos";

        $where = '';
        if ($usr_concessions = $this->getUserConcessions()) {
            $usr_concessions = implode(',', $usr_concessions);
            $where = " WHERE concesionaria  In ($usr_concessions);";
        }

        $stmt = $conn->prepare($sql . $where);
        $result = $stmt->executeQuery();
        $data_table = $result->fetchAllAssociative();
        
        // Si es petición AJAX, devolver JSON
        if ($request->isXmlHttpRequest()) {
            return new Response(
                json_encode([
                    'data_table' => $data_table,
                    'alarmas_dispositivos' => $rs_alarmas_dispositivos,
                ]),
                200,
                ['Content-Type' => 'application/json']
            );
        }
        
        // Obtener roles del usuario actual
        $current_user = $this->getUser();
        $currentUserRoles = $current_user ? $current_user->getRoles() : ['ROLE_USER'];
        
        // Crear contexto mínimo de EasyAdmin para evitar errores
        $eaContext = [
            'i18n' => [
                'locale' => 'es',
                'translationDomain' => 'messages',
            ],
            'crud' => null,
            'assets' => [
                'favicon' => '/favicon.ico',
            ],
        ];
        
        // Renderizar template con contexto de EasyAdmin
        return $this->render('dashboard/cot/dashboard.html.twig', [
            'data_table' => $data_table,
            'alarmas_dispositivos' => $rs_alarmas_dispositivos,
            'current_user' => $current_user,
            'currentUserRoles' => $currentUserRoles,
            'ea' => $eaContext,
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

        // 2. Query para tipos de dispositivos
        $qb_tipos_dispositivos = $em->createQueryBuilder();
        $qb_tipos_dispositivos->select('t')
            ->from('App\Entity\TblCot01TiposDispositivos', 't')
            ->orderBy('t.id', 'ASC')
            ->andWhere('t.mostrar = 1');

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
                'StentofonLastUpdateDateTime' => $StentofonLastUpdateDateTime,
                'SwIpDevicesMonitorLastUpdateDateTime' => $SwIpDevicesMonitorLastUpdateDateTime,
                'ea' => $eaContext
            ]);
        }
    }
    
    /**
     * Vista de red/network
     */
    #[Route('/network', name: 'network_index', methods: ['GET', 'POST'])]
    public function networkIndexAction(Request $request): Response
    {
        $current_user = $this->getUser();
        $em = $this->entityManager;
        
        // Query para alarmas de dispositivos de red
        $qb_alarmas_dispositivos = $em->createQueryBuilder();
        $qb_alarmas_dispositivos->select('reg')
            ->from('App\Entity\TblCot06AlarmasDispositivos', 'reg')
            ->leftJoin('reg.idAlarma', 'alarm')->addSelect('alarm')
            ->leftJoin('reg.idDispositivo', 'disp')->addSelect('disp')
            ->leftJoin('reg.concesionaria', 'conc')->addSelect('conc')
            ->orderBy('reg.id', 'ASC')
            ->andWhere('reg.estado = 0');
        
        try {
            $rs_alarmas_dispositivos = $qb_alarmas_dispositivos->getQuery()->getArrayResult();
        } catch (\Exception $e) {
            $rs_alarmas_dispositivos = [];
        }
        
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
        
        // Parsear fecha en formato dd-mm-yyyy H:i:s
        $parts = explode(' ', $dateString);
        $datePart = $parts[0] ?? '';
        $timePart = $parts[1] ?? '00:00:00';
        
        $dateParts = explode('-', $datePart);
        if (count($dateParts) == 3) {
            return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0] . ' ' . $timePart;
        }
        
        return $dateString; // Retornar sin cambios si no se puede parsear
    }
    
    /**
     * Historial de Espiras Costanera Norte
     */
    #[AdminRoute('/spire_history', name: 'spire_history')]
    public function cotSpireHistoryGenerateReportAction(Request $request): Response
    {
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
        
        // Verificar que el rango no exceda 2 días
        $dStart = new \DateTime($fechaInicio_Date);
        $dEnd = new \DateTime($fechaTermino_Date);
        $dDiff = $dStart->diff($dEnd);
        
        if (intval($dDiff->format('%r%a')) > 2) {
            $dStart = $dEnd->sub(new \DateInterval('P2D'));
            $fechaInicio = $dStart->format('d-m-Y H:i:s');
            $fechaInicio_Date = $dStart->format('Y-m-d H:i:s');
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
        
        // Obtener lista de todas las espiras si no es AJAX
        $arr_spires = [];
        if (!$request->isXmlHttpRequest()) {
            $sql_spires = "SELECT * FROM tbl_cot_02_dispositivos WHERE id_tipo = 4 ORDER BY nombre ASC";
            $stmt = $conn->prepare($sql_spires);
            $result = $stmt->executeQuery();
            $arr_spires = $result->fetchAllAssociative();
            // No es necesario closeCursor en Doctrine DBAL 3.x
        }
        
        // Llamar al procedimiento almacenado
        $sql_reg_pt = "CALL fnHistorialEspirasV8('$fechaInicio_Date','$fechaTermino_Date','$str_spires','$onlyZeros','$onlyEmpty','0')";
        
        // Log para depuración
        error_log("=== SPIRE HISTORY DEBUG ===");
        error_log("SQL Query: " . $sql_reg_pt);
        error_log("Parameters - Start: $fechaInicio_Date, End: $fechaTermino_Date, Spires: $str_spires, OnlyZeros: $onlyZeros, OnlyEmpty: $onlyEmpty");
        
        try {
            $stmt = $conn->prepare($sql_reg_pt);
            $result = $stmt->executeQuery();
            $arr_reg = $result->fetchAllAssociative();
            // No es necesario closeCursor en Doctrine DBAL 3.x
            
            error_log("Query executed. Row count: " . count($arr_reg));
            
            // Debug: verificar estructura completa del resultado
            if (count($arr_reg) > 0) {
                error_log("Result has data. Checking structure...");
                error_log("Number of columns: " . count($arr_reg[0]));
                if (count($arr_reg[0]) > 0) {
                    error_log("Column names: " . implode(', ', array_keys($arr_reg[0])));
                    // Mostrar primeros 500 caracteres del resultado
                    $resultStr = json_encode($arr_reg[0]);
                    error_log("First row (truncated): " . substr($resultStr, 0, 500));
                }
            }
            
            if (!$arr_reg || count($arr_reg) === 0) {
                $messageException = $this->translator ? 
                    $this->translator->trans('Not found.') : 
                    'Not found.';
                // Log pero no lanzar excepción
                error_log('No data found for spires history: ' . $messageException);
                
                // Debug: verificar si hay datos en el rango
                try {
                    $debugSql = "SELECT COUNT(*) as total FROM tbl_cot_07_historial_estado_dispositivos_espiras WHERE created_at BETWEEN '$fechaInicio_Date' AND '$fechaTermino_Date'";
                    error_log("Debug SQL: " . $debugSql);
                    $debugStmt = $conn->prepare($debugSql);
                    $debugResult = $debugStmt->executeQuery();
                    $debugData = $debugResult->fetchAllAssociative();
                    error_log("Records in date range: " . $debugData[0]['total']);
                } catch (\Exception $e) {
                    error_log("Debug query error: " . $e->getMessage());
                }
                
                $arr_reg = [];
            } else {
                error_log("Processing result set...");
                
                if (isset($arr_reg[0]['JSON_ESPIRAS'])) {
                    $arr_reg = $arr_reg[0]['JSON_ESPIRAS'];
                    error_log("JSON_ESPIRAS found, length: " . strlen($arr_reg));
                    error_log("First 200 chars of JSON: " . (is_string($arr_reg) ? substr($arr_reg, 0, 200) : json_encode($arr_reg)));
                    
                    // Validar que sea JSON válido
                    $decoded = json_decode($arr_reg, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        error_log("Invalid JSON: " . json_last_error_msg());
                        $arr_reg = [];
                    } else {
                        error_log("Valid JSON with " . count($decoded) . " elements");
                    }
                } else {
                    error_log("JSON_ESPIRAS column not found!");
                    error_log("Available columns: " . implode(', ', array_keys($arr_reg[0])));
                    // Intentar usar el valor directo si existe
                    if (count($arr_reg[0]) === 1) {
                        $firstKey = array_key_first($arr_reg[0]);
                        error_log("Using first column '$firstKey' as JSON data");
                        $arr_reg = $arr_reg[0][$firstKey] ?? '[]';
                    } else {
                        $arr_reg = [];
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Error calling fnHistorialEspirasV8: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Manejo específico para error de definer
            if (strpos($e->getMessage(), 'definer') !== false) {
                error_log('DEFINER ERROR: El stored procedure tiene un definer inválido. Ajuste el definer ejecutando:');
                error_log('ALTER DEFINER=`sql_vs_gvops_cl`@`localhost` PROCEDURE fnHistorialEspirasV8;');
                
                // Mostrar mensaje más claro en el frontend si es AJAX
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'error' => 'Error de configuración en la base de datos. Por favor contacte al administrador.',
                        'details' => 'El stored procedure requiere ajuste de permisos.'
                    ], 500);
                }
            }
            
            $arr_reg = []; // JSON vacío por defecto
        }
        
        error_log("Final arr_reg: " . (is_string($arr_reg) ? substr($arr_reg, 0, 200) . (strlen($arr_reg) > 200 ? '...' : '') : json_encode($arr_reg)));
        error_log("=== END SPIRE HISTORY DEBUG ===");
        
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
}