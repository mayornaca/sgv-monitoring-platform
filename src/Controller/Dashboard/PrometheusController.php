<?php

namespace App\Controller\Dashboard;

use App\Service\ConfigurationService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Visor de Prometheus
 * Muestra Prometheus en un iframe - el proxy reverso debe estar configurado a nivel de infraestructura
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
class PrometheusController extends AbstractController
{
    public function __construct(
        private readonly ConfigurationService $configService
    ) {}

    /**
     * Vista con iframe de Prometheus
     * La URL es configurable desde app_settings (prometheus.url)
     */
    #[AdminRoute('/prometheus', name: 'prometheus_dashboard')]
    public function index(): Response
    {
        $prometheusUrl = $this->configService->get('prometheus.url', 'http://10.10.10.19:9090');

        return $this->render('dashboard/prometheus/index.html.twig', [
            'prometheus_url' => $prometheusUrl,
        ]);
    }
}
