<?php

namespace App\Controller\Dashboard;

use App\Service\ConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Visor de Alertmanager
 * Muestra Alertmanager en un iframe - el proxy reverso debe estar configurado a nivel de infraestructura
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
class AlertmanagerController extends AbstractController
{
    public function __construct(
        private readonly ConfigurationService $configService
    ) {}

    /**
     * Vista con iframe de Alertmanager
     * La URL es configurable desde app_settings (alertmanager.url)
     */
    #[AdminRoute('/alertmanager', name: 'alertmanager_dashboard')]
    public function index(): Response
    {
        $alertmanagerUrl = $this->configService->get('alertmanager.url', 'http://10.10.10.19:9093');

        return $this->render('dashboard/alertmanager/index.html.twig', [
            'alertmanager_url' => $alertmanagerUrl,
        ]);
    }
}
