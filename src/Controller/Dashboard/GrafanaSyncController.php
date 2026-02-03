<?php

namespace App\Controller\Dashboard;

use App\Service\GrafanaSyncService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controlador de sincronización entre instancias de Grafana
 * Permite gestionar instancias, ver contenido y sincronizar dashboards
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
class GrafanaSyncController extends AbstractController
{
    public function __construct(
        private readonly GrafanaSyncService $syncService
    ) {}

    /**
     * Vista principal de sincronización
     */
    #[AdminRoute('/grafana/sync', name: 'grafana_sync')]
    public function index(): Response
    {
        $instances = $this->syncService->listInstances();

        return $this->render('dashboard/grafana/sync.html.twig', [
            'instances' => $instances,
        ]);
    }

    /**
     * API: Lista instancias configuradas
     */
    #[Route('/admin/grafana/sync/api/instances', name: 'admin_grafana_sync_api_instances', methods: ['GET'])]
    public function apiListInstances(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'instances' => $this->syncService->listInstances(),
        ]);
    }

    /**
     * API: Guardar/actualizar instancia
     */
    #[Route('/admin/grafana/sync/api/instances', name: 'admin_grafana_sync_api_save_instance', methods: ['POST'])]
    public function apiSaveInstance(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $key = $data['key'] ?? null;
        $name = $data['name'] ?? null;
        $url = $data['url'] ?? null;
        $token = $data['token'] ?? null;

        if (!$key || !$name || !$url || !$token) {
            return $this->json([
                'success' => false,
                'error' => 'Faltan campos requeridos: key, name, url, token',
            ], 400);
        }

        try {
            $this->syncService->saveInstance($key, $name, $url, $token);

            return $this->json([
                'success' => true,
                'message' => "Instancia '{$name}' guardada correctamente",
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Eliminar instancia
     */
    #[Route('/admin/grafana/sync/api/instances/{key}', name: 'admin_grafana_sync_api_delete_instance', methods: ['DELETE'])]
    public function apiDeleteInstance(string $key): JsonResponse
    {
        try {
            $this->syncService->deleteInstance($key);

            return $this->json([
                'success' => true,
                'message' => "Instancia '{$key}' eliminada",
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Test conexión con instancia
     */
    #[Route('/admin/grafana/sync/api/instances/{key}/test', name: 'admin_grafana_sync_api_test', methods: ['GET'])]
    public function apiTestConnection(string $key): JsonResponse
    {
        $result = $this->syncService->testConnection($key);

        return $this->json($result);
    }

    /**
     * API: Listar contenido de una instancia
     */
    #[Route('/admin/grafana/sync/api/instances/{key}/content', name: 'admin_grafana_sync_api_content', methods: ['GET'])]
    public function apiGetContent(string $key): JsonResponse
    {
        try {
            $dashboards = $this->syncService->listDashboards($key);
            $folders = $this->syncService->listFolders($key);
            $datasources = $this->syncService->listDatasources($key);

            return $this->json([
                'success' => true,
                'dashboards' => $dashboards,
                'folders' => $folders,
                'datasources' => $datasources,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Exportar dashboard específico
     */
    #[Route('/admin/grafana/sync/api/instances/{key}/dashboards/{uid}/export', name: 'admin_grafana_sync_api_export_dashboard', methods: ['GET'])]
    public function apiExportDashboard(string $key, string $uid): JsonResponse
    {
        try {
            $dashboard = $this->syncService->exportDashboard($key, $uid);

            return $this->json([
                'success' => true,
                'dashboard' => $dashboard,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Sincronizar entre instancias
     */
    #[Route('/admin/grafana/sync/api/sync', name: 'admin_grafana_sync_api_sync', methods: ['POST'])]
    public function apiSync(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $source = $data['source'] ?? null;
        $target = $data['target'] ?? null;
        $includeDatasources = $data['includeDatasources'] ?? true;
        $overwrite = $data['overwrite'] ?? true;

        if (!$source || !$target) {
            return $this->json([
                'success' => false,
                'error' => 'Faltan campos: source, target',
            ], 400);
        }

        if ($source === $target) {
            return $this->json([
                'success' => false,
                'error' => 'Origen y destino deben ser diferentes',
            ], 400);
        }

        try {
            $results = $this->syncService->syncInstances(
                $source,
                $target,
                $includeDatasources,
                $overwrite
            );

            $hasErrors = !empty($results['errors']);

            return $this->json([
                'success' => !$hasErrors,
                'partial' => $hasErrors,
                'results' => $results,
                'summary' => [
                    'folders' => count($results['folders']),
                    'dashboards' => count($results['dashboards']),
                    'datasources' => count($results['datasources']),
                    'errors' => count($results['errors']),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: Importar dashboard individual a destino
     */
    #[Route('/admin/grafana/sync/api/import-dashboard', name: 'admin_grafana_sync_api_import_dashboard', methods: ['POST'])]
    public function apiImportDashboard(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $source = $data['source'] ?? null;
        $target = $data['target'] ?? null;
        $dashboardUid = $data['dashboardUid'] ?? null;
        $overwrite = $data['overwrite'] ?? true;

        if (!$source || !$target || !$dashboardUid) {
            return $this->json([
                'success' => false,
                'error' => 'Faltan campos: source, target, dashboardUid',
            ], 400);
        }

        try {
            // Exportar desde origen
            $dashboard = $this->syncService->exportDashboard($source, $dashboardUid);

            // Importar folders necesarios
            if (!empty($dashboard['meta']['folderUid'])) {
                $folders = $this->syncService->listFolders($source);
                foreach ($folders as $folder) {
                    if ($folder['uid'] === $dashboard['meta']['folderUid']) {
                        $this->syncService->importFolder($target, $folder);
                        break;
                    }
                }
            }

            // Importar dashboard
            $result = $this->syncService->importDashboard($target, $dashboard, $overwrite);

            return $this->json([
                'success' => true,
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
