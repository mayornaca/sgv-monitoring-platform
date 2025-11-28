# Arquitectura del Servidor de Actualizaciones

## Visión General

El servidor de actualizaciones es un sistema centralizado que gestiona la distribución de releases del SGV a múltiples instancias cliente, con validación de licencias y control de versiones.

## Diagrama de Arquitectura

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    UPDATE SERVER (updates.gvops.cl)                      │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────┐   │
│  │  Release Manager │  │  License Service │  │  Update API          │   │
│  │                  │  │                  │  │                      │   │
│  │  - Git tags      │  │  - JWT validation│  │  GET  /api/v1/check  │   │
│  │  - Build package │  │  - Tier check    │  │  POST /api/v1/download│  │
│  │  - SHA256 hash   │  │  - Domain verify │  │  POST /api/v1/report │   │
│  └────────┬─────────┘  └────────┬─────────┘  └──────────┬───────────┘   │
│           │                     │                       │               │
│           └─────────────────────┼───────────────────────┘               │
│                                 │                                        │
│  ┌──────────────────────────────┴──────────────────────────────────┐    │
│  │                     PostgreSQL Database                          │    │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────────┐  │    │
│  │  │  licenses   │  │  releases   │  │  update_logs            │  │    │
│  │  └─────────────┘  └─────────────┘  └─────────────────────────┘  │    │
│  └──────────────────────────────────────────────────────────────────┘    │
│                                                                          │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                     File Storage (releases/)                      │   │
│  │  sgv-v1.0.0.tar.gz  sgv-v1.1.0.tar.gz  sgv-v1.2.0.tar.gz  ...   │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    │ HTTPS
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        ▼                           ▼                           ▼
┌───────────────────┐   ┌───────────────────┐   ┌───────────────────┐
│  vs.gvops.cl      │   │  cnorte.gvops.cl  │   │  cliente-n.cl     │
│                   │   │                   │   │                   │
│  License: L001    │   │  License: L002    │   │  License: L00N    │
│  Tier: standard   │   │  Tier: enterprise │   │  Tier: basic      │
│  Concession: 22   │   │  Concession: 20   │   │  Concession: X    │
│  Version: 1.2.0   │   │  Version: 1.3.0   │   │  Version: 1.1.0   │
│                   │   │                   │   │                   │
│  UpdateService    │   │  UpdateService    │   │  UpdateService    │
│  - checkUpdates() │   │  - checkUpdates() │   │  - checkUpdates() │
│  - applyUpdate()  │   │  - applyUpdate()  │   │  - applyUpdate()  │
└───────────────────┘   └───────────────────┘   └───────────────────┘
```

## Componentes del Sistema

### 1. Update Server (Symfony Application)

**Stack tecnológico:**
- PHP 8.2 + Symfony 6.4
- PostgreSQL 15
- Nginx + PHP-FPM
- Redis (cache de licencias)

**Estructura del proyecto:**
```
updates.gvops.cl/
├── src/
│   ├── Controller/
│   │   └── Api/
│   │       └── UpdateController.php
│   ├── Entity/
│   │   ├── License.php
│   │   ├── Release.php
│   │   └── UpdateLog.php
│   ├── Repository/
│   │   ├── LicenseRepository.php
│   │   ├── ReleaseRepository.php
│   │   └── UpdateLogRepository.php
│   ├── Service/
│   │   ├── LicenseService.php
│   │   ├── ReleaseService.php
│   │   └── PackageService.php
│   └── Security/
│       └── LicenseAuthenticator.php
├── config/
│   ├── packages/
│   └── services.yaml
├── releases/                    # Paquetes de release
│   ├── sgv-v1.0.0.tar.gz
│   ├── sgv-v1.1.0.tar.gz
│   └── ...
└── var/
    └── log/
```

### 2. Base de Datos

Ver [LICENSE_SYSTEM.md](./LICENSE_SYSTEM.md) para el schema completo.

**Tablas principales:**
- `licenses` - Licencias de clientes
- `releases` - Versiones disponibles
- `update_logs` - Registro de actualizaciones

### 3. API de Actualizaciones

#### `GET /api/v1/check`

Verifica si hay actualizaciones disponibles.

**Headers requeridos:**
```
X-License-Key: <license_key>
X-Current-Version: <version>
X-Domain: <domain>
```

**Response exitosa (200):**
```json
{
    "available": true,
    "version": "1.3.0",
    "channel": "stable",
    "changelog": "## Cambios en v1.3.0\n- Feature X\n- Fix Y",
    "size": 12345678,
    "hash": "sha256:abc123...",
    "requires_migration": true,
    "released_at": "2025-01-15T10:30:00Z"
}
```

**Response sin actualizaciones (200):**
```json
{
    "available": false,
    "current_version": "1.3.0",
    "message": "Ya tienes la última versión"
}
```

**Errores:**
- `401` - Licencia inválida o expirada
- `403` - Dominio no autorizado
- `429` - Rate limit excedido

#### `POST /api/v1/download`

Descarga un paquete de actualización.

**Headers requeridos:**
```
X-License-Key: <license_key>
```

**Body:**
```json
{
    "version": "1.3.0"
}
```

**Response exitosa (200):**
- Binary stream del archivo `.tar.gz`
- O URL firmada temporal (válida 1 hora)

```json
{
    "download_url": "https://updates.gvops.cl/releases/sgv-v1.3.0.tar.gz?token=xyz&expires=1234567890",
    "expires_at": "2025-01-15T11:30:00Z"
}
```

#### `POST /api/v1/report`

Reporta el resultado de una actualización.

**Headers requeridos:**
```
X-License-Key: <license_key>
```

**Body:**
```json
{
    "from_version": "1.2.0",
    "to_version": "1.3.0",
    "status": "applied",
    "duration_seconds": 45,
    "error_message": null
}
```

**Response (200):**
```json
{
    "logged": true,
    "update_id": 12345
}
```

### 4. Cliente de Actualizaciones

El cliente se integra en cada instancia del SGV.

**Archivos del cliente:**
```
src/
├── Service/
│   └── UpdateService.php
├── Command/
│   ├── UpdateCheckCommand.php
│   └── UpdateApplyCommand.php
└── Controller/
    └── Admin/
        └── UpdateController.php
```

**Configuración en `.env`:**
```bash
UPDATE_SERVER_URL=https://updates.gvops.cl
UPDATE_LICENSE_KEY=your-license-key-here
UPDATE_AUTO_CHECK=true
UPDATE_CHECK_INTERVAL=21600  # 6 horas
```

## Flujo de Actualización

### 1. Verificación Automática (Cron)

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Cron Job   │────▶│ UpdateCheck │────▶│ Update API  │
│  cada 6h    │     │  Command    │     │  /check     │
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
                    ┌─────────────┐            │
                    │  Notificar  │◀───────────┘
                    │  Admin      │   Si hay update
                    └─────────────┘
```

### 2. Aplicación Manual

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Admin      │────▶│ UpdateApply │────▶│ Update API  │
│  Dashboard  │     │  Command    │     │  /download  │
└─────────────┘     └─────────────┘     └──────┬──────┘
                                               │
                    ┌─────────────┐            │
                    │  1. Backup  │◀───────────┘
                    │  2. Extract │
                    │  3. Migrate │
                    │  4. Clear   │
                    │  5. Report  │
                    └─────────────┘
```

### 3. Proceso de Actualización Detallado

```php
// src/Service/UpdateService.php
class UpdateService
{
    public function applyUpdate(string $version): UpdateResult
    {
        // 1. Descargar paquete
        $package = $this->downloadPackage($version);

        // 2. Verificar integridad (SHA256)
        if (!$this->verifyHash($package)) {
            throw new IntegrityException('Hash mismatch');
        }

        // 3. Crear backup
        $backup = $this->createBackup();

        try {
            // 4. Extraer archivos
            $this->extractPackage($package);

            // 5. Ejecutar migraciones
            $this->runMigrations();

            // 6. Limpiar cache
            $this->clearCache();

            // 7. Reportar éxito
            $this->reportUpdate($version, 'applied');

            return new UpdateResult(true);

        } catch (\Exception $e) {
            // Rollback
            $this->restoreBackup($backup);
            $this->reportUpdate($version, 'failed', $e->getMessage());

            throw $e;
        }
    }
}
```

## Seguridad

### Autenticación

- **License Key**: Token JWT firmado con RS256
- **Verificación de dominio**: El header `X-Domain` debe coincidir con la licencia
- **Rate limiting**: 10 requests/minuto por licencia

### Integridad de Paquetes

- Cada release incluye `checksums.sha256`
- El cliente verifica el hash antes de extraer
- Opcionalmente, firma GPG para tier Enterprise

### Comunicación

- Solo HTTPS (TLS 1.3)
- Certificate pinning opcional
- Headers de seguridad estándar

## Escalabilidad

### Horizontal

- Múltiples instancias del Update Server detrás de load balancer
- Base de datos PostgreSQL con réplicas de lectura
- Storage de releases en S3/MinIO

### Vertical

- Cache de licencias en Redis (TTL 1 hora)
- CDN para distribución de paquetes
- Compresión de paquetes con zstd

## Monitoreo

### Métricas (Prometheus)

```
# Actualizaciones exitosas
sgv_updates_total{status="applied"} 150

# Actualizaciones fallidas
sgv_updates_total{status="failed"} 3

# Tiempo de descarga
sgv_update_download_duration_seconds_bucket{le="60"} 145

# Licencias activas
sgv_licenses_active_total 25
```

### Alertas

- Licencia próxima a expirar (30 días)
- Múltiples fallos de actualización
- Rate limit excedido
- Error de integridad de paquete

## Implementación por Fases

### Fase 1: MVP (2-3 semanas)

- [ ] Proyecto Symfony básico
- [ ] Entidades License, Release
- [ ] API /check y /download
- [ ] Validación de licencias básica
- [ ] Cliente UpdateService

### Fase 2: Producción (2-3 semanas)

- [ ] Dashboard admin de licencias
- [ ] GitHub Action para releases
- [ ] Reportes de actualización
- [ ] Notificaciones por email

### Fase 3: Enterprise (4-6 semanas)

- [ ] Firma GPG de paquetes
- [ ] Auto-update configurable
- [ ] Rollback automático
- [ ] Multi-region deployment

## Referencias

- [LICENSE_SYSTEM.md](./LICENSE_SYSTEM.md) - Sistema de licencias
- [RELEASE_WORKFLOW.md](./RELEASE_WORKFLOW.md) - Flujo de releases
- [Plan original](/home/opc/.claude/plans/iridescent-hugging-raccoon.md)
