<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Servicio de sincronización con Grafana API
 * Permite exportar e importar dashboards, datasources y folders entre instancias
 */
class GrafanaSyncService
{
    private const DEFAULT_TIMEOUT = 30;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigurationService $configService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Obtiene la configuración de una instancia de Grafana
     * @return array{url: string, token: string, name: string}|null
     */
    public function getInstanceConfig(string $instanceKey): ?array
    {
        $instances = $this->configService->get('grafana.instances', [], false);

        if (!is_array($instances) || !isset($instances[$instanceKey])) {
            return null;
        }

        return $instances[$instanceKey];
    }

    /**
     * Lista todas las instancias configuradas
     * @return array<string, array{url: string, token: string, name: string}>
     */
    public function listInstances(): array
    {
        $instances = $this->configService->get('grafana.instances', [], false);

        if (!is_array($instances)) {
            return [];
        }

        // No exponer tokens completos
        $result = [];
        foreach ($instances as $key => $config) {
            $result[$key] = [
                'name' => $config['name'] ?? $key,
                'url' => $config['url'] ?? '',
                'hasToken' => !empty($config['token']),
            ];
        }

        return $result;
    }

    /**
     * Guarda configuración de una instancia
     */
    public function saveInstance(string $key, string $name, string $url, string $token): void
    {
        $instances = $this->configService->get('grafana.instances', [], false);

        if (!is_array($instances)) {
            $instances = [];
        }

        $instances[$key] = [
            'name' => $name,
            'url' => rtrim($url, '/'),
            'token' => $token,
        ];

        $this->configService->set('grafana.instances', $instances, 'json', 'grafana');
    }

    /**
     * Elimina una instancia
     */
    public function deleteInstance(string $key): void
    {
        $instances = $this->configService->get('grafana.instances', [], false);

        if (is_array($instances) && isset($instances[$key])) {
            unset($instances[$key]);
            $this->configService->set('grafana.instances', $instances, 'json', 'grafana');
        }
    }

    /**
     * Verifica conexión con una instancia
     * @return array{success: bool, org: string|null, version: string|null, error: string|null}
     */
    public function testConnection(string $instanceKey): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            return ['success' => false, 'org' => null, 'version' => null, 'error' => 'Instancia no encontrada'];
        }

        try {
            $orgResponse = $this->apiRequest($config, 'GET', '/api/org');
            $healthResponse = $this->apiRequest($config, 'GET', '/api/health');

            return [
                'success' => true,
                'org' => $orgResponse['name'] ?? null,
                'version' => $healthResponse['version'] ?? null,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'org' => null,
                'version' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Lista dashboards de una instancia
     * @return array<int, array{uid: string, title: string, folderTitle: string|null}>
     */
    public function listDashboards(string $instanceKey): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        $response = $this->apiRequest($config, 'GET', '/api/search', ['type' => 'dash-db']);

        return array_map(fn($dash) => [
            'uid' => $dash['uid'],
            'title' => $dash['title'],
            'folderTitle' => $dash['folderTitle'] ?? null,
            'folderUid' => $dash['folderUid'] ?? null,
        ], $response);
    }

    /**
     * Lista folders de una instancia
     * @return array<int, array{uid: string, title: string}>
     */
    public function listFolders(string $instanceKey): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        $response = $this->apiRequest($config, 'GET', '/api/folders');

        return array_map(fn($folder) => [
            'uid' => $folder['uid'],
            'title' => $folder['title'],
        ], $response);
    }

    /**
     * Lista datasources de una instancia
     * @return array<int, array{id: int, uid: string, name: string, type: string}>
     */
    public function listDatasources(string $instanceKey): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        $response = $this->apiRequest($config, 'GET', '/api/datasources');

        return array_map(fn($ds) => [
            'id' => $ds['id'],
            'uid' => $ds['uid'] ?? null,
            'name' => $ds['name'],
            'type' => $ds['type'],
            'url' => $ds['url'] ?? null,
        ], $response);
    }

    /**
     * Exporta un dashboard completo
     */
    public function exportDashboard(string $instanceKey, string $uid): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        return $this->apiRequest($config, 'GET', "/api/dashboards/uid/{$uid}");
    }

    /**
     * Exporta un datasource
     */
    public function exportDatasource(string $instanceKey, int $id): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        $ds = $this->apiRequest($config, 'GET', "/api/datasources/{$id}");

        // Limpiar campos sensibles y no necesarios para import
        unset($ds['id'], $ds['orgId'], $ds['version'], $ds['readOnly']);

        return $ds;
    }

    /**
     * Importa un folder
     */
    public function importFolder(string $instanceKey, array $folder): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        $payload = [
            'uid' => $folder['uid'],
            'title' => $folder['title'],
        ];

        try {
            return $this->apiRequest($config, 'POST', '/api/folders', [], $payload);
        } catch (\Exception $e) {
            // Si ya existe, intentar obtenerlo
            if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'conflict')) {
                return ['uid' => $folder['uid'], 'title' => $folder['title'], 'status' => 'exists'];
            }
            throw $e;
        }
    }

    /**
     * Importa un dashboard
     */
    public function importDashboard(string $instanceKey, array $dashboardData, bool $overwrite = true): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        $dashboard = $dashboardData['dashboard'];
        $dashboard['id'] = null; // Forzar creación nueva

        $payload = [
            'dashboard' => $dashboard,
            'overwrite' => $overwrite,
        ];

        // Mantener folder si existe
        if (!empty($dashboardData['meta']['folderUid'])) {
            $payload['folderUid'] = $dashboardData['meta']['folderUid'];
        }

        return $this->apiRequest($config, 'POST', '/api/dashboards/db', [], $payload);
    }

    /**
     * Importa un datasource
     */
    public function importDatasource(string $instanceKey, array $datasource): array
    {
        $config = $this->getInstanceConfig($instanceKey);

        if (!$config) {
            throw new \InvalidArgumentException("Instancia '$instanceKey' no encontrada");
        }

        // Limpiar campos que no deben estar en la creación
        unset($datasource['id'], $datasource['orgId'], $datasource['version'], $datasource['readOnly']);

        try {
            return $this->apiRequest($config, 'POST', '/api/datasources', [], $datasource);
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists') || str_contains($e->getMessage(), 'name already exists')) {
                return ['name' => $datasource['name'], 'status' => 'exists'];
            }
            throw $e;
        }
    }

    /**
     * Sincronización completa entre instancias
     * @return array{folders: array, dashboards: array, datasources: array, errors: array}
     */
    public function syncInstances(
        string $sourceKey,
        string $targetKey,
        bool $includeDatasources = true,
        bool $overwrite = true
    ): array {
        $results = [
            'folders' => [],
            'dashboards' => [],
            'datasources' => [],
            'errors' => [],
        ];

        // 1. Sincronizar folders
        $this->logger->info("Sincronizando folders desde {$sourceKey} a {$targetKey}");
        $folders = $this->listFolders($sourceKey);

        foreach ($folders as $folder) {
            try {
                $result = $this->importFolder($targetKey, $folder);
                $results['folders'][] = [
                    'title' => $folder['title'],
                    'status' => $result['status'] ?? 'created',
                ];
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'type' => 'folder',
                    'name' => $folder['title'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        // 2. Sincronizar datasources (sin credenciales)
        if ($includeDatasources) {
            $this->logger->info("Sincronizando datasources desde {$sourceKey} a {$targetKey}");
            $datasources = $this->listDatasources($sourceKey);

            foreach ($datasources as $ds) {
                try {
                    $fullDs = $this->exportDatasource($sourceKey, $ds['id']);
                    $result = $this->importDatasource($targetKey, $fullDs);
                    $results['datasources'][] = [
                        'name' => $ds['name'],
                        'type' => $ds['type'],
                        'status' => $result['status'] ?? 'created',
                    ];
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'type' => 'datasource',
                        'name' => $ds['name'],
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        // 3. Sincronizar dashboards
        $this->logger->info("Sincronizando dashboards desde {$sourceKey} a {$targetKey}");
        $dashboards = $this->listDashboards($sourceKey);

        foreach ($dashboards as $dash) {
            try {
                $fullDash = $this->exportDashboard($sourceKey, $dash['uid']);
                $result = $this->importDashboard($targetKey, $fullDash, $overwrite);
                $results['dashboards'][] = [
                    'title' => $dash['title'],
                    'folder' => $dash['folderTitle'],
                    'status' => $result['status'] ?? 'imported',
                ];
            } catch (\Exception $e) {
                $results['errors'][] = [
                    'type' => 'dashboard',
                    'name' => $dash['title'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Realiza una petición a la API de Grafana
     */
    private function apiRequest(
        array $config,
        string $method,
        string $endpoint,
        array $query = [],
        ?array $body = null
    ): array {
        $url = $config['url'] . $endpoint;

        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['token'],
                'Content-Type' => 'application/json',
            ],
            'timeout' => self::DEFAULT_TIMEOUT,
            'verify_peer' => false, // Algunos servidores usan certificados self-signed
            'verify_host' => false,
        ];

        if (!empty($query)) {
            $options['query'] = $query;
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $content = $response->getContent(false);
                $error = json_decode($content, true);
                throw new \RuntimeException(
                    $error['message'] ?? "Error HTTP {$statusCode}: {$content}"
                );
            }

            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            $this->logger->error("Grafana API error: {$e->getMessage()}", [
                'url' => $url,
                'method' => $method,
            ]);
            throw new \RuntimeException("Error de conexión: " . $e->getMessage());
        }
    }
}
