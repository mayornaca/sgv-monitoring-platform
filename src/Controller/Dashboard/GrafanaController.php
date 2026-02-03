<?php

namespace App\Controller\Dashboard;

use App\Service\ConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Visor de Grafana
 * Muestra Grafana en un iframe - el proxy reverso debe estar configurado a nivel de infraestructura
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
class GrafanaController extends AbstractController
{
    public function __construct(
        private readonly ConfigurationService $configService
    ) {}

    /**
     * Vista con iframe de Grafana
     * La URL es configurable desde app_settings (grafana.url)
     */
    #[AdminRoute('/grafana', name: 'grafana_dashboard')]
    public function index(): Response
    {
        $grafanaUrl = $this->configService->get('grafana.url', 'http://10.10.10.19/grafana');

        return $this->render('dashboard/grafana/index.html.twig', [
            'grafana_url' => $grafanaUrl,
        ]);
    }
}
