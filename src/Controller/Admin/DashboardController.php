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
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // Renderizar dashboard principal en blanco
        // En el futuro se pueden agregar estadísticas, widgets o KPIs aquí
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="/assets/images/logo.png" style="height: 35px;">')
            ->setFaviconPath('favicon.ico');
            // Sidebar visible por defecto - el botón hamburguesa permite ocultarlo
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName($user->getUserIdentifier())
            ->displayUserAvatar(true)
            ->addMenuItems([
                MenuItem::linkToUrl('Mi Perfil', 'fas fa-user', '/profile'),
            ]);
    }

    public function configureMenuItems(): iterable
    {
        //yield MenuItem::linkToDashboard('Dashboard', 'fas fa-tachometer-alt');

        // Monitor de dispositivos - CN (Costanera Norte)
        yield MenuItem::section('Monitoreo');
        yield MenuItem::linkToRoute('Resumen', 'fas fa-pie-chart', 'cot_dashboard');
        yield MenuItem::linkToRoute('Videowall Dispositivos', 'fas fa-th-large', 'admin_cot_monitor', [
            'id' => 0,
            'videowall' => 'true',
            'device_status' => 'all',
            'contract_ui' => 'true',
            'masonry' => 'true',
            'grid_items_width' => '2',
            'fixed' => 'true',
            'input_device_finder' => ''
        ]);
        yield MenuItem::linkToRoute('Lista Dispositivos', 'fas fa-list', 'admin_cot_monitor', [
            'id' => 0,
            'videowall' => 'false',
            'device_status' => 'all',
            'contract_ui' => 'true',
            'masonry' => 'true',
            'grid_items_width' => '2',
            'input_device_finder' => ''
        ]);
        yield MenuItem::linkToRoute('Gálibos', 'fas fa-arrows-v', 'admin_cot_galibos');
        yield MenuItem::linkToRoute('Monitor sensores SOS', 'fas fa-exclamation-circle', 'admin_cot_sosindex', ['id' => 1]);
        yield MenuItem::linkToRoute('Reporte sensores SOS', 'fas fa-file-alt', 'admin_cot_sos_report_status');
        yield MenuItem::linkToRoute('Red', 'fas fa-network-wired', 'admin_cot_network');
        yield MenuItem::linkToRoute('Monitor Espiras', 'fas fa-circle-notch', 'admin_cot_monitor', ['id' => 4]);
        yield MenuItem::linkToRoute('Historial Espiras', 'fas fa-history', 'admin_spire_history');

        
        // Reportes
        yield MenuItem::section('Reportes');
        
        // Citofonía
        yield MenuItem::subMenu('Citofonía', 'fas fa-phone')->setSubItems([
            MenuItem::linkToRoute('Lista de llamadas SOS', 'fas fa-phone-volume', 'admin_lista_llamadas_sos'),
            MenuItem::linkToRoute('Informe Mensual de Citofonía', 'fas fa-file-invoice', 'admin_siv_dashboard_informe_mensual_citofonia'),
        ]);

        // Incidentes
        yield MenuItem::subMenu('Incidentes', 'fas fa-car-crash')->setSubItems([
            MenuItem::linkToRoute('Registro Incidente', 'fas fa-plus-circle', 'admin_siv_dashboard_registro_incidente'),
            //MenuItem::linkToRoute('Ficha Accidente', 'fas fa-file-medical', 'admin_siv_dashboard_ficha_accidente'),
            MenuItem::linkToRoute('Atenciones por Clase de Vehículo', 'fas fa-car', 'admin_siv_dashboard_atenciones_clase_vehiculo'),
            MenuItem::linkToRoute('Tiempos recursos externos', 'fas fa-clock', 'admin_siv_dashboard_tiempos_recursos_externos'),
            MenuItem::linkToRoute('Tiempos de respuesta por recursos', 'fas fa-stopwatch', 'admin_siv_dashboard_tiempos_respuesta_recursos'),
            MenuItem::linkToRoute('Tiempos de respuesta por incidente', 'fas fa-hourglass', 'admin_siv_dashboard_tiempos_respuesta_incidente'),
            MenuItem::linkToRoute('Historial de recursos', 'fas fa-history', 'admin_siv_dashboard_historial_recursos'),
        ]);

        // SCADA
        yield MenuItem::section('SCADA');
        yield MenuItem::linkToRoute('Permisos de Trabajos', 'fas fa-hard-hat', 'admin_siv_dashboard_lista_permisos_trabajos');
        yield MenuItem::linkToRoute('Bitácora', 'fas fa-book', 'admin_siv_dashboard_lista_bitacora_scada');

        // VS (Vespucio Sur)
        yield MenuItem::subMenu('VS', 'fas fa-highway')->setSubItems([
            //MenuItem::linkToRoute('Lista Dispositivos VS', 'fas fa-list', 'admin_vs_index', ['id' => 0]),
            MenuItem::linkToRoute('Historial Espiras VS', 'fas fa-history', 'admin_spire_history_vs_min'),
            MenuItem::linkToRoute('Monitor Espiras VS', 'fas fa-circle-notch', 'admin_vs_index', ['id' => 13]),
        ]);

        // Gestión de Usuarios (sección administrativa)
        yield MenuItem::section('Administración');
        yield MenuItem::linkToCrud('Usuarios', 'fas fa-users', User::class)
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Personal', 'fas fa-id-card', Tbl14Personal::class)
            ->setPermission('ROLE_SUPER_ADMIN');
        yield MenuItem::linkToCrud('Alertas', 'fas fa-exclamation-triangle', Alert::class)
            ->setPermission('ROLE_OPERATOR_COT');
        yield MenuItem::linkToCrud('Reglas de Alerta', 'fas fa-cogs', AlertRule::class)
            ->setPermission('ROLE_ADMIN');


        
        //yield MenuItem::section('Sistema');

        // WhatsApp Management
        yield MenuItem::section('WhatsApp API')->setPermission('ROLE_SUPER_ADMIN');;
        yield MenuItem::linkToCrud('Destinatarios', 'fab fa-whatsapp', Recipient::class)
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Grupos de Destinatarios', 'fas fa-user-group', RecipientGroup::class)
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Templates', 'fas fa-file-lines', Template::class)
            ->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Mensajes Enviados', 'fas fa-message', Message::class)
            ->setPermission('ROLE_ADMIN');
        //yield MenuItem::linkToUrl('Volver al sitio', 'fas fa-home', '/');
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
