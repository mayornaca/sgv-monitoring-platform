<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Device;
use App\Entity\DeviceType;
// COMENTADO 2025-10-03: DeviceAlert no se usa en este controlador
// Ver CotController.php línea 69 para documentación completa sobre por qué se comentó
// use App\Entity\DeviceAlert;
use App\Entity\Alert;
use App\Entity\AlertRule;
use App\Entity\AuditLog;
use App\Entity\NotificationLog;
use App\Entity\Tbl14Personal;
use App\Entity\WhatsApp\Recipient;
use App\Entity\WhatsApp\RecipientGroup;
use App\Entity\WhatsApp\Template;
use App\Entity\WhatsApp\Message;
use App\Entity\AppSetting;
use App\Entity\WebhookLog;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    /**
     * IDs de concesiones configuradas para esta instancia
     */
    private array $concessionIds = [];

    /**
     * String original de configuración
     */
    private string $instanceConcessionsRaw;

    public function __construct(string $instanceConcessions = '')
    {
        $this->instanceConcessionsRaw = $instanceConcessions;

        // Parsear string de concesiones a array de IDs
        if (!empty($instanceConcessions)) {
            $this->concessionIds = array_map(
                fn($id) => (int) trim($id),
                array_filter(explode(',', $instanceConcessions))
            );
        }
    }

    /**
     * Verifica si una concesión específica está habilitada en esta instancia
     */
    public function hasConcession(int $concessionId): bool
    {
        // Si no hay concesiones configuradas, todas están habilitadas
        if (empty($this->concessionIds)) {
            return true;
        }

        return in_array($concessionId, $this->concessionIds, true);
    }

    /**
     * Verifica si alguna de las concesiones especificadas está habilitada
     */
    public function hasAnyConcession(array $concessionIds): bool
    {
        if (empty($this->concessionIds)) {
            return true;
        }

        return !empty(array_intersect($this->concessionIds, $concessionIds));
    }

    /**
     * Obtiene los IDs de concesiones configuradas
     */
    public function getConcessionIds(): array
    {
        return $this->concessionIds;
    }

    /**
     * Verifica si la instancia tiene concesiones específicas o es global
     */
    public function isGlobalInstance(): bool
    {
        return empty($this->concessionIds);
    }

    public function index(): Response
    {
        // Redirigir automáticamente al primer menú útil según el rol del usuario
        $user = $this->getUser();
        $roles = $user->getRoles();

        // Determinar primer menú según rol (orden de prioridad: más específico primero)
        if (in_array('ROLE_SUPER_ADMIN', $roles)) {
            return $this->render('admin/dashboard.html.twig');
            // Super Admin → Resumen general COT
            //return $this->redirectToRoute('cot_dashboard');

        } elseif (in_array('ROLE_USUARIO_VS', $roles)) {
            // Usuario VS → Historial Espiras VS (primer menú)
            return $this->redirectToRoute('admin_spire_history_vs_min');

        } elseif (in_array('ROLE_OPERATOR_SCADA', $roles)) {
            // Operador SCADA → Permisos de Trabajo (primer menú)
            return $this->redirectToRoute('admin_siv_dashboard_lista_permisos_trabajos');

        } elseif (in_array('ROLE_MONITOR_GERENCIA', $roles)) {
            // Monitor Gerencia → Monitor Espiras (primer menú, id=4)
            return $this->redirectToRoute('admin_cot_spires', [
                'id' => 4,
                'videowall' => 'false',
                'device_status' => 'all',
                'contract_ui' => 'true',
                'masonry' => 'true',
                'grid_items_width' => '12',
                'input_device_finder' => ''
            ]);

        } elseif (in_array('ROLE_MONITOR_GERENCIA_REPORTES', $roles)) {
            // Monitor Gerencia Reportes → Reporte registro incidentes
            return $this->redirectToRoute('admin_siv_dashboard_registro_incidente');

        } elseif (in_array('ROLE_ADMIN_COT', $roles) ||
                  in_array('ROLE_SU_COT', $roles) ||
                  in_array('ROLE_OPERATOR_COT', $roles)) {
            // Admin COT, Supervisor COT, Operador COT → Lista Dispositivos (primer menú)
            return $this->redirectToRoute('admin_cot_monitor', [
                'id' => 0,
                'videowall' => 'false',
                'device_status' => 'all',
                'contract_ui' => 'true',
                'masonry' => 'true',
                'grid_items_width' => '2',
                'input_device_finder' => ''
            ]);

        } else {
            // Fallback: renderizar dashboard genérico para roles sin configuración específica
            return $this->render('admin/dashboard.html.twig');
        }
    }

    public function configureDashboard(): Dashboard
    {
        $logoHtml = '<img src="/assets/images/logo.png" style="height: 35px;">';
        if ($this->hasConcession(20) && !$this->hasConcession(22)) {
            // mostrar logo Vespucio Sur si la concesión 20 está habilitada como svg
            $logoHtml = '<img src="/images/concessions/vs_logo.png" style="height: 35px;">';
        }
        elseif ($this->hasConcession(22) && !$this->hasConcession(20)){
            $logoHtml = '<img src="/images/concessions/cn_logo.png" style="height: 35px;">';
        }

        return Dashboard::new()
            ->setTitle($logoHtml)
            ->setFaviconPath('favicon.ico');
            // Sidebar visible por defecto - el botón hamburguesa permite ocultarlo
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        $displayName = $user->getUserIdentifier();

        // Intentar usar nombre completo si está disponible
        if (method_exists($user, 'getFirstName') && method_exists($user, 'getLastName')) {
            $firstName = $user->getFirstName();
            $lastName = $user->getLastName();

            if ($firstName || $lastName) {
                $displayName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
            }
        }

        return parent::configureUserMenu($user)
            ->setName($displayName)
            ->displayUserName(true)
            ->displayUserAvatar(true)
            ->addMenuItems([
                MenuItem::linkToRoute('Mi Perfil', 'fas fa-user', 'admin_app_profile_show'),
            ]);
    }

    public function configureMenuItems(): iterable
    {
        //yield MenuItem::linkToDashboard('Dashboard', 'fas fa-tachometer-alt');

        // Monitor de dispositivos - CN (Costanera Norte)
        yield MenuItem::section('Monitoreo')->setPermission('ROLE_VIEW_DEVICE_LIST');
        // Menú específico para concesión Costanera Norte (22)
        if ($this->hasConcession(22) && !$this->hasConcession(20)){
            yield MenuItem::linkToRoute('Resumen', 'fas fa-pie-chart', 'cot_dashboard')
                ->setPermission('ROLE_SUPER_ADMIN');
//        yield MenuItem::linkToRoute('Videowall Dispositivos', 'fas fa-th-large', 'admin_cot_monitor', [
//            'id' => 0,
//            'videowall' => 'true',
//            'device_status' => 'all',
//            'contract_ui' => 'true',
//            'masonry' => 'true',
//            'grid_items_width' => '2',
//            'fixed' => 'true',
//            'input_device_finder' => ''
//        ])->setPermission('ROLE_SUPER_ADMIN');
            yield MenuItem::linkToRoute('Lista Dispositivos', 'fas fa-list', 'admin_cot_monitor', [
                'id' => 0,
                'videowall' => 'false',
                'device_status' => 'all',
                'contract_ui' => 'true',
                'masonry' => 'true',
                'grid_items_width' => '2',
                'input_device_finder' => ''
            ])->setPermission('ROLE_VIEW_DEVICE_LIST');
            yield MenuItem::linkToRoute('Gálibos', 'fas fa-arrows-v', 'admin_cot_galibos')
                ->setPermission('ROLE_VIEW_GALIBOS');
            yield MenuItem::linkToRoute('Monitor sensores SOS', 'fas fa-exclamation-circle', 'admin_cot_sosindex', [
                'id' => 1,
                'videowall' => 'false',
                'device_status' => 'all',
                'contract_ui' => 'true',
                'masonry' => 'true',
                'grid_items_width' => '2',
                'input_device_finder' => ''
            ])->setPermission('ROLE_VIEW_SOS_MONITOR');
            yield MenuItem::linkToRoute('Reporte sensores SOS', 'fas fa-file-alt', 'admin_cot_sos_report_status')
                ->setPermission('ROLE_VIEW_SOS_REPORT');
            yield MenuItem::linkToRoute('Red', 'fas fa-network-wired', 'admin_cot_network')
                ->setPermission('ROLE_SUPER_ADMIN');
            yield MenuItem::linkToRoute('Monitor Espiras', 'fas fa-circle-notch', 'admin_cot_spires', [
                'id' => 4,
                'videowall' => 'false',
                'device_status' => 'all',
                'contract_ui' => 'true',
                'masonry' => 'true',
                'grid_items_width' => '12',
                'input_device_finder' => ''
            ])->setPermission('ROLE_VIEW_SPIRE_MONITOR');
            yield MenuItem::linkToRoute('Historial Espiras', 'fas fa-history', 'admin_spire_history')
                ->setPermission('ROLE_VIEW_SPIRE_HISTORY');

            // Reportes
            yield MenuItem::section('Reportes')->setPermission('ROLE_VIEW_REPORTS');

            // Citofonía
            yield MenuItem::subMenu('Citofonía', 'fas fa-phone')->setSubItems([
                MenuItem::linkToRoute('Lista de llamadas SOS', 'fas fa-phone-volume', 'admin_lista_llamadas_sos',['ci' => '0']),
                MenuItem::linkToRoute('Informe Mensual de Citofonía', 'fas fa-file-invoice', 'admin_siv_dashboard_informe_mensual_citofonia'),
            ])->setPermission('ROLE_VIEW_PHONE_REPORTS');

            // Incidentes
            yield MenuItem::subMenu('Incidentes', 'fas fa-car-crash')->setSubItems([
                MenuItem::linkToRoute('Registro Incidente', 'fas fa-plus-circle', 'admin_siv_dashboard_registro_incidente'),
                //MenuItem::linkToRoute('Ficha Accidente', 'fas fa-file-medical', 'admin_siv_dashboard_ficha_accidente'),
                MenuItem::linkToRoute('Atenciones por Clase de Vehículo', 'fas fa-car', 'admin_siv_dashboard_atenciones_clase_vehiculo'),
                MenuItem::linkToRoute('Tiempos recursos externos', 'fas fa-clock', 'admin_siv_dashboard_tiempos_recursos_externos'),
                MenuItem::linkToRoute('Tiempos de respuesta por recursos', 'fas fa-stopwatch', 'admin_siv_dashboard_tiempos_respuesta_recursos'),
                MenuItem::linkToRoute('Tiempos de respuesta por incidente', 'fas fa-hourglass', 'admin_siv_dashboard_tiempos_respuesta_incidente'),
                MenuItem::linkToRoute('Historial de recursos', 'fas fa-history', 'admin_siv_dashboard_historial_recursos'),
            ])->setPermission('ROLE_VIEW_INCIDENT_REPORTS');

            // SCADA
            yield MenuItem::section('SCADA')->setPermission('ROLE_VIEW_WORK_PERMITS');
            yield MenuItem::linkToRoute('Permisos de Trabajos', 'fas fa-hard-hat', 'admin_siv_dashboard_lista_permisos_trabajos')
                ->setPermission('ROLE_VIEW_WORK_PERMITS');
            yield MenuItem::linkToRoute('Bitácora', 'fas fa-book', 'admin_siv_dashboard_lista_bitacora_scada')
                ->setPermission('ROLE_VIEW_SCADA_LOG');
        }
        elseif ($this->hasConcession(20) && !$this->hasConcession(22)){

            // Menu específico para concesión Vespucio Sur (20)
            // Monitor Dispositivos (el reporte está dentro via botón "Generar Reporte")

//            yield MenuItem::linkToRoute('Lista Dispositivos VS', 'fas fa-list', 'admin_cot_monitor', [
//                'id' => 0,
//                'concession' => 20,
//                'videowall' => 'false',
//                'device_status' => 'all',
//                'contract_ui' => 'true',
//                'masonry' => 'true',
//                'grid_items_width' => '2',
//                'input_device_finder' => ''
//            ])->setPermission('ROLE_SUPER_ADMIN');

            yield MenuItem::linkToRoute('Monitor Espiras VS', 'fas fa-circle-notch', 'admin_vs_index', ['id' => 13])
                ->setPermission('ROLE_VIEW_VS_SPIRE_HISTORY');

            yield MenuItem::linkToRoute('Historial Espiras VS', 'fas fa-history', 'admin_spire_history_vs_min')
                ->setPermission('ROLE_VIEW_VS_SPIRE_HISTORY');

            // Incidentes
            yield MenuItem::subMenu('Incidentes', 'fas fa-car-crash')->setSubItems([
                // Reportes SIV (enlaces duplicados desde menú general para facilitar acceso)
                 MenuItem::linkToRoute('Tiempos Recursos externos', 'fas fa-clock', 'admin_siv_dashboard_tiempos_recursos_externos_vs')
                    ->setPermission('ROLE_VIEW_INCIDENT_REPORTS'),
            MenuItem::linkToRoute('Tiempos Respuesta por recurso', 'fas fa-stopwatch', 'admin_siv_dashboard_tiempos_respuesta_recursos_vs')
                ->setPermission('ROLE_VIEW_INCIDENT_REPORTS'),
            MenuItem::linkToRoute('Tiempos Respuesta por incidente', 'fas fa-hourglass', 'admin_siv_dashboard_tiempos_respuesta_incidente_vs')
                ->setPermission('ROLE_VIEW_INCIDENT_REPORTS'),
            MenuItem::linkToRoute('Detalle Incidentes', 'fas fa-list-alt', 'admin_siv_dashboard_detalle_incidentes_vs')
                ->setPermission('ROLE_USUARIO_VS'),
            MenuItem::linkToRoute('Incidentes Ocupación', 'fas fa-road', 'admin_siv_dashboard_incidentes_ocupacion_vs')
                ->setPermission('ROLE_VIEW_INCIDENT_REPORTS')
            ])->setPermission('ROLE_VIEW_INCIDENT_REPORTS');
        }

        // Menu específico para concesión Costanera Norte (22) y Vespucio Sur (20)
        if ($this->hasConcession(22) && $this->hasConcession(20)){
            // VS (Vespucio Sur)
            yield MenuItem::subMenu('VESPUCIO SUR', 'fas fa-highway')->setSubItems([
                // Monitor Dispositivos (el reporte está dentro via botón "Generar Reporte")
                MenuItem::linkToRoute('Lista Dispositivos', 'fas fa-list', 'admin_cot_monitor', [
                    'id' => 0,
                    'concession' => 20,
                    'videowall' => 'false',
                    'device_status' => 'all',
                    'contract_ui' => 'true',
                    'masonry' => 'true',
                    'grid_items_width' => '2',
                    'input_device_finder' => ''
                ])->setPermission('ROLE_SUPER_ADMIN'),

                MenuItem::linkToRoute('Monitor Espiras', 'fas fa-circle-notch', 'admin_vs_index', ['id' => 13])
                    ->setPermission('ROLE_VIEW_VS_SPIRE_HISTORY'),
                MenuItem::linkToRoute('Historial Espiras VS', 'fas fa-history', 'admin_spire_history_vs_min')
                    ->setPermission('ROLE_VIEW_VS_SPIRE_HISTORY'),

                // Reportes SIV (enlaces duplicados desde menú general para facilitar acceso)
                MenuItem::linkToRoute('Tiempos Recursos externos', 'fas fa-clock', 'admin_siv_dashboard_tiempos_recursos_externos_vs')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkToRoute('Tiempos Respuesta por recurso', 'fas fa-stopwatch', 'admin_siv_dashboard_tiempos_respuesta_recursos_vs')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkToRoute('Tiempos Respuesta por incidente', 'fas fa-hourglass', 'admin_siv_dashboard_tiempos_respuesta_incidente_vs')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkToRoute('Detalle Incidentes', 'fas fa-list-alt', 'admin_siv_dashboard_detalle_incidentes_vs')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkToRoute('Incidentes Ocupación', 'fas fa-road', 'admin_siv_dashboard_incidentes_ocupacion_vs')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ])->setPermission('ROLE_VIEW_VS_SPIRE_HISTORY');
        }

        // Gestión de Usuarios (sección administrativa)
        yield MenuItem::section('Administración')->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Configuraciones', 'fas fa-sliders-h', AppSetting::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-users', User::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Personal', 'fas fa-id-card', Tbl14Personal::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Alertas', 'fas fa-exclamation-triangle', Alert::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Reglas de Alerta', 'fas fa-cogs', AlertRule::class)
            ->setPermission('ROLE_SUPER_ADMIN');

        //yield MenuItem::section('Sistema');

        // WhatsApp Management
        yield MenuItem::section('WhatsApp API')->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Diagnóstico', 'fas fa-stethoscope', 'admin_whatsapp_diagnostic')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Destinatarios', 'fab fa-whatsapp', Recipient::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Grupos de Destinatarios', 'fas fa-user-group', RecipientGroup::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Templates', 'fas fa-file-lines', Template::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Mensajes Enviados', 'fas fa-message', Message::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Grafana Dashboard', 'fas fa-chart-line', 'admin_grafana_dashboard')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Grafana Sync', 'fas fa-sync-alt', 'admin_grafana_sync')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Prometheus', 'fas fa-database', 'admin_prometheus_dashboard')
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToRoute('Alertmanager', 'fas fa-bell', 'admin_alertmanager_dashboard')
            ->setPermission('ROLE_SUPER_ADMIN');

        // Logs & Auditoría
        yield MenuItem::section('Logs & Auditoría')->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Webhook Logs', 'fas fa-exchange-alt', WebhookLog::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Audit Log', 'fas fa-history', AuditLog::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Notification Log', 'fas fa-bell', NotificationLog::class)
            ->setPermission('ROLE_SUPER_ADMIN');

        //yield MenuItem::linkToUrl('Volver al sitio', 'fas fa-home', '/');
        yield MenuItem::section('-');
        yield MenuItem::linkToLogout('Cerrar sesión', 'fas fa-sign-out-alt');
    }

    /**
     * Configurar assets globales para todo el admin
     * Approach oficial de EasyAdmin 4 según documentación
     *
     * NOTA: Siguiendo las mejores prácticas de EasyAdmin 4, usamos controles nativos HTML5
     * (datetime-local, select multiple) en lugar de librerías JavaScript externas.
     * Esto mejora performance, reduce dependencias y evita problemas de inicialización.
     */
    public function configureAssets(): Assets
    {
        return parent::configureAssets()
            // CSS global custom para títulos y estilos del sistema
            ->addCssFile('css/easyadmin-custom.css');
    }

}
