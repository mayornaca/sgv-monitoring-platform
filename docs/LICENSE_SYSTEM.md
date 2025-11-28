# Sistema de Licencias SGV

## Visión General

El sistema de licencias controla el acceso a actualizaciones y funcionalidades del SGV según el nivel de suscripción del cliente.

## Niveles de Licencia (Tiers)

| Tier | Concesiones | Usuarios | Soporte | Actualizaciones | Precio Ref. |
|------|-------------|----------|---------|-----------------|-------------|
| **Basic** | 1 | 5 | Email (48h) | Solo parches críticos | $X/mes |
| **Standard** | 3 | 20 | Prioritario (24h) | Stable releases | $Y/mes |
| **Enterprise** | Ilimitado | Ilimitado | 24/7 + SLA | Todas (incluye beta) | Cotizar |

### Características por Tier

#### Basic
- Dashboard COT básico
- Dashboard SIV básico
- Reportes estándar
- 1 concesión
- 5 usuarios máximo
- Actualizaciones: solo parches de seguridad

#### Standard
- Todo lo de Basic
- Hasta 3 concesiones
- 20 usuarios máximo
- Integración WhatsApp
- Reportes avanzados
- Actualizaciones: todas las versiones stable

#### Enterprise
- Todo lo de Standard
- Concesiones ilimitadas
- Usuarios ilimitados
- OAuth2 SSO
- Acceso API completo
- Actualizaciones: incluye beta y preview
- Soporte 24/7 con SLA

## Schema de Base de Datos

### Tabla: `licenses`

```sql
CREATE TABLE licenses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),

    -- Identificación
    license_key VARCHAR(64) UNIQUE NOT NULL,

    -- Cliente
    client_name VARCHAR(255) NOT NULL,
    client_email VARCHAR(255) NOT NULL,
    client_domain VARCHAR(255) NOT NULL,

    -- Configuración
    product_id VARCHAR(50) NOT NULL DEFAULT 'sgv',
    tier VARCHAR(20) NOT NULL DEFAULT 'standard',
    concession_ids TEXT,                    -- "20,22" o NULL para todas
    max_users INTEGER NOT NULL DEFAULT 10,

    -- Features adicionales (JSONB)
    features JSONB DEFAULT '{
        "whatsapp": false,
        "oauth2_sso": false,
        "api_access": false,
        "beta_updates": false
    }',

    -- Validez
    valid_from TIMESTAMP NOT NULL DEFAULT NOW(),
    valid_until TIMESTAMP,                  -- NULL = sin expiración

    -- Estado
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    suspended_reason TEXT,

    -- Auditoría
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW(),
    created_by VARCHAR(255),

    -- Constraints
    CONSTRAINT valid_tier CHECK (tier IN ('basic', 'standard', 'enterprise')),
    CONSTRAINT valid_product CHECK (product_id IN ('sgv', 'cot', 'siv'))
);

-- Índices
CREATE INDEX idx_licenses_client_domain ON licenses(client_domain);
CREATE INDEX idx_licenses_tier ON licenses(tier);
CREATE INDEX idx_licenses_active ON licenses(is_active) WHERE is_active = true;
CREATE INDEX idx_licenses_expiring ON licenses(valid_until)
    WHERE valid_until IS NOT NULL AND valid_until > NOW();
```

### Tabla: `releases`

```sql
CREATE TABLE releases (
    id SERIAL PRIMARY KEY,

    -- Versión
    version VARCHAR(20) NOT NULL,           -- Semver: "1.2.3"
    product_id VARCHAR(50) NOT NULL DEFAULT 'sgv',
    channel VARCHAR(20) NOT NULL DEFAULT 'stable',

    -- Requisitos
    min_tier VARCHAR(20) NOT NULL DEFAULT 'basic',
    min_php_version VARCHAR(10) DEFAULT '8.2',
    min_symfony_version VARCHAR(10) DEFAULT '6.4',

    -- Contenido
    changelog TEXT,
    release_notes TEXT,
    breaking_changes TEXT,

    -- Archivo
    file_path VARCHAR(500) NOT NULL,
    file_hash VARCHAR(64) NOT NULL,         -- SHA256
    file_size BIGINT NOT NULL,

    -- Migraciones
    requires_migration BOOLEAN DEFAULT FALSE,
    migration_notes TEXT,

    -- Estado
    released_at TIMESTAMP NOT NULL DEFAULT NOW(),
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    is_latest BOOLEAN DEFAULT FALSE,

    -- Constraints
    CONSTRAINT valid_channel CHECK (channel IN ('stable', 'beta', 'dev', 'hotfix')),
    CONSTRAINT valid_min_tier CHECK (min_tier IN ('basic', 'standard', 'enterprise')),
    CONSTRAINT unique_version_product UNIQUE (version, product_id)
);

-- Índices
CREATE INDEX idx_releases_product_channel ON releases(product_id, channel);
CREATE INDEX idx_releases_latest ON releases(is_latest) WHERE is_latest = true;
CREATE INDEX idx_releases_active ON releases(is_active) WHERE is_active = true;
```

### Tabla: `update_logs`

```sql
CREATE TABLE update_logs (
    id SERIAL PRIMARY KEY,

    -- Referencia
    license_id UUID NOT NULL REFERENCES licenses(id),
    release_id INTEGER REFERENCES releases(id),

    -- Versiones
    from_version VARCHAR(20),
    to_version VARCHAR(20) NOT NULL,

    -- Estado
    status VARCHAR(20) NOT NULL DEFAULT 'pending',

    -- Tiempos
    started_at TIMESTAMP NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMP,
    duration_seconds INTEGER,

    -- Resultado
    error_message TEXT,
    error_stack TEXT,

    -- Metadata
    client_ip INET,
    user_agent TEXT,

    -- Constraints
    CONSTRAINT valid_status CHECK (status IN (
        'pending', 'downloading', 'verifying',
        'extracting', 'migrating', 'applied', 'failed', 'rolled_back'
    ))
);

-- Índices
CREATE INDEX idx_update_logs_license ON update_logs(license_id);
CREATE INDEX idx_update_logs_status ON update_logs(status);
CREATE INDEX idx_update_logs_date ON update_logs(started_at DESC);
```

### Tabla: `license_usage`

```sql
CREATE TABLE license_usage (
    id SERIAL PRIMARY KEY,

    license_id UUID NOT NULL REFERENCES licenses(id),

    -- Métricas
    check_count INTEGER DEFAULT 0,
    download_count INTEGER DEFAULT 0,
    active_users INTEGER DEFAULT 0,

    -- Período
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,

    -- Timestamps
    last_check_at TIMESTAMP,
    last_download_at TIMESTAMP,

    CONSTRAINT unique_license_period UNIQUE (license_id, period_start)
);

-- Índice
CREATE INDEX idx_license_usage_period ON license_usage(period_start, period_end);
```

## Generación de Licencias

### Estructura del License Key (JWT)

```json
{
    "header": {
        "alg": "RS256",
        "typ": "JWT"
    },
    "payload": {
        "iss": "updates.gvops.cl",
        "sub": "license:abc123",
        "aud": "sgv-client",
        "iat": 1704067200,
        "exp": 1735689600,
        "client": "Vespucio Sur S.A.",
        "domain": "vs.gvops.cl",
        "product": "sgv",
        "tier": "standard",
        "concessions": [22],
        "features": {
            "whatsapp": true,
            "oauth2_sso": false
        }
    },
    "signature": "..."
}
```

### Generador de Licencias

```php
// src/Service/LicenseGenerator.php
namespace App\Service;

use Firebase\JWT\JWT;

class LicenseGenerator
{
    public function __construct(
        private string $privateKeyPath,
        private string $issuer = 'updates.gvops.cl'
    ) {}

    public function generate(array $options): string
    {
        $now = time();

        $payload = [
            'iss' => $this->issuer,
            'sub' => 'license:' . bin2hex(random_bytes(16)),
            'aud' => 'sgv-client',
            'iat' => $now,
            'exp' => $options['valid_until'] ?? $now + (365 * 24 * 60 * 60),

            'client' => $options['client_name'],
            'domain' => $options['domain'],
            'product' => $options['product_id'] ?? 'sgv',
            'tier' => $options['tier'] ?? 'standard',
            'concessions' => $options['concession_ids'] ?? [],
            'max_users' => $options['max_users'] ?? 10,
            'features' => $options['features'] ?? [],
        ];

        $privateKey = file_get_contents($this->privateKeyPath);

        return JWT::encode($payload, $privateKey, 'RS256');
    }
}
```

## Validación de Licencias

### Validador del Servidor

```php
// src/Service/LicenseValidator.php
namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class LicenseValidator
{
    public function __construct(
        private string $publicKeyPath,
        private LicenseRepository $repository
    ) {}

    public function validate(string $licenseKey, string $domain): ValidationResult
    {
        try {
            // 1. Decodificar JWT
            $publicKey = file_get_contents($this->publicKeyPath);
            $decoded = JWT::decode($licenseKey, new Key($publicKey, 'RS256'));

            // 2. Verificar expiración
            if ($decoded->exp < time()) {
                return ValidationResult::expired();
            }

            // 3. Verificar dominio
            if ($decoded->domain !== $domain) {
                return ValidationResult::domainMismatch();
            }

            // 4. Verificar en base de datos (suspensión, etc.)
            $license = $this->repository->findByKey($licenseKey);
            if (!$license || !$license->isActive()) {
                return ValidationResult::inactive();
            }

            return ValidationResult::valid($decoded);

        } catch (\Exception $e) {
            return ValidationResult::invalid($e->getMessage());
        }
    }
}
```

### Validador del Cliente

```php
// src/Service/LicenseClientValidator.php (en cada instancia SGV)
namespace App\Service;

class LicenseClientValidator
{
    private const CACHE_KEY = 'license_validation';
    private const CACHE_TTL = 86400; // 24 horas

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private string $updateServerUrl,
        private string $licenseKey
    ) {}

    public function isValid(): bool
    {
        // Intentar cache primero
        return $this->cache->get(self::CACHE_KEY, function () {
            return $this->validateRemote();
        });
    }

    private function validateRemote(): bool
    {
        try {
            $response = $this->httpClient->request('GET',
                $this->updateServerUrl . '/api/v1/license/validate',
                [
                    'headers' => [
                        'X-License-Key' => $this->licenseKey,
                        'X-Domain' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                    ]
                ]
            );

            $data = json_decode($response->getContent(), true);
            return $data['valid'] ?? false;

        } catch (\Exception $e) {
            // Si no hay conexión, asumir válida (grace period)
            return true;
        }
    }
}
```

## Verificación de Acceso a Actualizaciones

```php
// src/Service/UpdateAccessChecker.php
namespace App\Service;

class UpdateAccessChecker
{
    private const TIER_HIERARCHY = [
        'basic' => 1,
        'standard' => 2,
        'enterprise' => 3,
    ];

    private const CHANNEL_ACCESS = [
        'basic' => ['hotfix'],
        'standard' => ['hotfix', 'stable'],
        'enterprise' => ['hotfix', 'stable', 'beta', 'dev'],
    ];

    public function canAccess(License $license, Release $release): bool
    {
        // 1. Verificar tier mínimo
        $licenseTierLevel = self::TIER_HIERARCHY[$license->getTier()] ?? 0;
        $requiredTierLevel = self::TIER_HIERARCHY[$release->getMinTier()] ?? 0;

        if ($licenseTierLevel < $requiredTierLevel) {
            return false;
        }

        // 2. Verificar canal
        $allowedChannels = self::CHANNEL_ACCESS[$license->getTier()] ?? [];
        if (!in_array($release->getChannel(), $allowedChannels)) {
            return false;
        }

        // 3. Verificar producto
        if ($license->getProductId() !== $release->getProductId()) {
            return false;
        }

        return true;
    }
}
```

## Comandos de Administración

### Crear Licencia

```bash
php bin/console app:license:create \
    --client="Vespucio Sur S.A." \
    --domain="vs.gvops.cl" \
    --tier="standard" \
    --concessions="22" \
    --valid-until="2026-01-01"
```

### Listar Licencias

```bash
php bin/console app:license:list
php bin/console app:license:list --tier=enterprise
php bin/console app:license:list --expiring=30  # Próximas a expirar
```

### Suspender Licencia

```bash
php bin/console app:license:suspend LICENSE_KEY --reason="Pago pendiente"
```

### Renovar Licencia

```bash
php bin/console app:license:renew LICENSE_KEY --extend=365  # días
```

## API de Gestión de Licencias

### Endpoints Admin

```
POST   /admin/api/licenses           # Crear licencia
GET    /admin/api/licenses           # Listar licencias
GET    /admin/api/licenses/{id}      # Ver licencia
PUT    /admin/api/licenses/{id}      # Actualizar licencia
DELETE /admin/api/licenses/{id}      # Eliminar licencia
POST   /admin/api/licenses/{id}/suspend   # Suspender
POST   /admin/api/licenses/{id}/activate  # Activar
POST   /admin/api/licenses/{id}/renew     # Renovar
```

### Ejemplo: Crear Licencia

```bash
curl -X POST https://updates.gvops.cl/admin/api/licenses \
    -H "Authorization: Bearer ADMIN_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
        "client_name": "Autopista Central S.A.",
        "client_email": "admin@autopista-central.cl",
        "client_domain": "ac.gvops.cl",
        "tier": "enterprise",
        "concession_ids": [20, 21],
        "max_users": 50,
        "valid_until": "2026-12-31",
        "features": {
            "whatsapp": true,
            "oauth2_sso": true,
            "api_access": true,
            "beta_updates": true
        }
    }'
```

## Monitoreo de Licencias

### Alertas Automáticas

1. **Licencia próxima a expirar** (30, 15, 7, 1 días)
2. **Uso excesivo** (más de X usuarios activos)
3. **Múltiples dominios** (intento de uso en dominio no autorizado)
4. **Actualizaciones fallidas** repetidas

### Métricas

```prometheus
# Licencias activas por tier
sgv_licenses_active{tier="basic"} 5
sgv_licenses_active{tier="standard"} 15
sgv_licenses_active{tier="enterprise"} 3

# Licencias próximas a expirar (30 días)
sgv_licenses_expiring_soon 4

# Validaciones por resultado
sgv_license_validations_total{result="valid"} 10000
sgv_license_validations_total{result="expired"} 50
sgv_license_validations_total{result="domain_mismatch"} 10
```

## Seguridad

### Protección de Claves

- Clave privada JWT solo en el servidor de actualizaciones
- Clave pública distribuida a clientes (verificación offline)
- Rotación de claves cada 2 años

### Prevención de Fraude

- Verificación de dominio en cada request
- Fingerprinting de instancia (hash de config)
- Rate limiting por licencia
- Logs de actividad sospechosa

## Referencias

- [UPDATE_SERVER_ARCHITECTURE.md](./UPDATE_SERVER_ARCHITECTURE.md)
- [RELEASE_WORKFLOW.md](./RELEASE_WORKFLOW.md)
- [JWT RFC 7519](https://tools.ietf.org/html/rfc7519)
