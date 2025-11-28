# Flujo de Releases SGV

## Visión General

Este documento describe el proceso completo desde el desarrollo de una nueva versión hasta su distribución a los clientes.

## Versionado Semántico

El SGV sigue [Semantic Versioning 2.0.0](https://semver.org/):

```
MAJOR.MINOR.PATCH[-PRERELEASE]

Ejemplos:
1.0.0       - Primera versión estable
1.1.0       - Nueva funcionalidad (backward compatible)
1.1.1       - Corrección de bug
1.2.0-beta  - Versión beta
2.0.0       - Breaking changes
```

### Reglas de Incremento

| Tipo | Cuándo | Ejemplo |
|------|--------|---------|
| **MAJOR** | Breaking changes, incompatibilidad | 1.x.x → 2.0.0 |
| **MINOR** | Nueva funcionalidad, backward compatible | 1.1.x → 1.2.0 |
| **PATCH** | Bug fixes, mejoras menores | 1.1.1 → 1.1.2 |

## Canales de Release

```
┌─────────────────────────────────────────────────────────────────┐
│                        DESARROLLO                               │
│  feature/* branches → develop branch                            │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        DEV CHANNEL                              │
│  Versiones: x.x.x-dev                                           │
│  Acceso: Solo desarrollo interno                                │
│  Frecuencia: Cada push a develop                                │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        BETA CHANNEL                             │
│  Versiones: x.x.x-beta, x.x.x-rc                               │
│  Acceso: Tier Enterprise                                        │
│  Frecuencia: Semanal durante desarrollo activo                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       STABLE CHANNEL                            │
│  Versiones: x.x.x                                               │
│  Acceso: Tiers Standard y Enterprise                            │
│  Frecuencia: Mensual o según necesidad                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                       HOTFIX CHANNEL                            │
│  Versiones: x.x.x (patch crítico)                              │
│  Acceso: Todos los tiers                                        │
│  Frecuencia: Según necesidad (seguridad/bugs críticos)          │
└─────────────────────────────────────────────────────────────────┘
```

## Proceso de Release

### 1. Preparación

```bash
# 1.1 Asegurar que develop está actualizado
git checkout develop
git pull origin develop

# 1.2 Crear rama de release
git checkout -b release/1.3.0

# 1.3 Actualizar versión en archivos
# - composer.json
# - package.json
# - src/Kernel.php (VERSION constant)
```

### 2. Actualización de Archivos de Versión

```php
// src/Kernel.php
class Kernel extends BaseKernel
{
    public const VERSION = '1.3.0';
    public const VERSION_ID = 10300;
    public const MAJOR_VERSION = 1;
    public const MINOR_VERSION = 3;
    public const RELEASE_VERSION = 0;
}
```

```json
// composer.json
{
    "version": "1.3.0"
}
```

### 3. Changelog

Mantener `CHANGELOG.md` actualizado:

```markdown
# Changelog

## [1.3.0] - 2025-01-15

### Added
- Nueva funcionalidad de reportes avanzados (#123)
- Integración con Prometheus para métricas (#125)

### Changed
- Mejorado rendimiento de consultas SIV (#128)
- Actualizada librería de gráficos a v11.0 (#130)

### Fixed
- Corregido error en cálculo de tiempos de respuesta (#127)
- Solucionado problema de memoria en exportación Excel (#129)

### Security
- Actualizada dependencia symfony/http-kernel (CVE-2025-XXXX)

### Breaking Changes
- Ninguno

### Migration Notes
- Ejecutar: `php bin/console doctrine:migrations:migrate`
- Limpiar cache: `php bin/console cache:clear`
```

### 4. Testing

```bash
# 4.1 Tests unitarios
php bin/phpunit

# 4.2 Tests de integración
php bin/phpunit --testsuite=integration

# 4.3 Análisis estático
vendor/bin/phpstan analyse src

# 4.4 Code style
vendor/bin/php-cs-fixer fix --dry-run
```

### 5. Crear Tag y Release

```bash
# 5.1 Merge a main
git checkout main
git merge --no-ff release/1.3.0

# 5.2 Crear tag
git tag -a v1.3.0 -m "Release v1.3.0

- Nueva funcionalidad de reportes avanzados
- Integración con Prometheus
- Mejoras de rendimiento
- Correcciones de bugs

Ver CHANGELOG.md para detalles completos."

# 5.3 Push
git push origin main --tags

# 5.4 Merge back a develop
git checkout develop
git merge --no-ff main
git push origin develop
```

## GitHub Actions Workflow

### `.github/workflows/release.yml`

```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: intl, pdo_mysql, pdo_pgsql, zip, gd

      - name: Install Dependencies
        run: |
          composer install --no-dev --optimize-autoloader
          npm ci
          npm run build

      - name: Run Tests
        run: php bin/phpunit

      - name: Extract Version
        id: version
        run: echo "VERSION=${GITHUB_REF#refs/tags/v}" >> $GITHUB_OUTPUT

      - name: Build Release Package
        run: |
          VERSION=${{ steps.version.outputs.VERSION }}

          # Crear directorio temporal
          mkdir -p build/sgv-v${VERSION}

          # Copiar archivos necesarios
          rsync -av --exclude-from='.release-exclude' \
            ./ build/sgv-v${VERSION}/

          # Crear archivo VERSION
          echo "${VERSION}" > build/sgv-v${VERSION}/VERSION

          # Generar checksums
          cd build/sgv-v${VERSION}
          find . -type f -exec sha256sum {} \; > ../checksums.sha256
          mv ../checksums.sha256 .

          # Comprimir
          cd ..
          tar -czvf sgv-v${VERSION}.tar.gz sgv-v${VERSION}

          # Calcular hash del paquete
          sha256sum sgv-v${VERSION}.tar.gz > sgv-v${VERSION}.tar.gz.sha256

      - name: Upload to Update Server
        env:
          UPDATE_SERVER_TOKEN: ${{ secrets.UPDATE_SERVER_TOKEN }}
          UPDATE_SERVER_URL: ${{ secrets.UPDATE_SERVER_URL }}
        run: |
          VERSION=${{ steps.version.outputs.VERSION }}

          # Subir paquete
          curl -X POST "${UPDATE_SERVER_URL}/api/admin/releases" \
            -H "Authorization: Bearer ${UPDATE_SERVER_TOKEN}" \
            -F "version=${VERSION}" \
            -F "channel=stable" \
            -F "changelog=@CHANGELOG.md" \
            -F "package=@build/sgv-v${VERSION}.tar.gz"

      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          files: |
            build/sgv-v${{ steps.version.outputs.VERSION }}.tar.gz
            build/sgv-v${{ steps.version.outputs.VERSION }}.tar.gz.sha256
          body_path: CHANGELOG.md
          draft: false
          prerelease: ${{ contains(github.ref, 'beta') || contains(github.ref, 'rc') }}
```

### `.release-exclude`

```
# Archivos a excluir del paquete de release
.git/
.github/
.env
.env.*
!.env.example
config/jwt/*.pem
node_modules/
var/cache/
var/log/
var/sessions/
public/downloads/
public/uploads/
old-project/
tests/
phpunit.xml
.phpunit.result.cache
docker-compose.override.yml
*.md
!README.md
!CHANGELOG.md
!UPGRADE.md
```

## Estructura del Paquete de Release

```
sgv-v1.3.0.tar.gz
├── assets/                     # Assets Symfony
├── bin/
│   ├── console
│   └── migrate-production
├── config/
│   ├── bundles.php
│   ├── packages/
│   ├── routes/
│   ├── routes.yaml
│   └── services.yaml
├── migrations/                 # Migraciones Doctrine
├── public/
│   ├── assets/
│   ├── css/
│   ├── js/
│   ├── images/
│   └── index.php
├── src/                        # Código fuente
├── templates/                  # Templates Twig
├── translations/               # Traducciones
├── vendor/                     # Dependencias (optimizadas)
├── .env.example                # Template de configuración
├── composer.json
├── composer.lock
├── CHANGELOG.md
├── UPGRADE.md
├── VERSION                     # "1.3.0"
└── checksums.sha256            # Hashes de todos los archivos
```

## Proceso de Actualización en Cliente

### Comando de Actualización

```bash
# Verificar actualizaciones disponibles
php bin/console app:update:check

# Aplicar actualización específica
php bin/console app:update:apply 1.3.0

# Aplicar última actualización disponible
php bin/console app:update:apply --latest
```

### Flujo Interno

```
┌─────────────────────────────────────────────────────────────────┐
│                    app:update:apply 1.3.0                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  1. VALIDACIÓN                                                  │
│     - Verificar licencia válida                                 │
│     - Verificar acceso al canal/tier                            │
│     - Verificar requisitos del sistema                          │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  2. DESCARGA                                                    │
│     - Descargar paquete del Update Server                       │
│     - Verificar hash SHA256                                     │
│     - Guardar en var/updates/                                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  3. BACKUP                                                      │
│     - Crear backup de archivos actuales                         │
│     - Exportar base de datos                                    │
│     - Guardar en var/backups/                                   │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  4. INSTALACIÓN                                                 │
│     - Activar modo mantenimiento                                │
│     - Extraer archivos nuevos                                   │
│     - Preservar: .env, config/jwt/, var/                        │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  5. MIGRACIÓN                                                   │
│     - Ejecutar doctrine:migrations:migrate                      │
│     - Ejecutar scripts de migración personalizados              │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│  6. FINALIZACIÓN                                                │
│     - Limpiar cache: cache:clear                                │
│     - Reconstruir assets si necesario                           │
│     - Desactivar modo mantenimiento                             │
│     - Reportar resultado al Update Server                       │
└─────────────────────────────────────────────────────────────────┘
                              │
                   ┌──────────┴──────────┐
                   │                     │
                   ▼                     ▼
           ┌─────────────┐       ┌─────────────┐
           │   ÉXITO     │       │   ERROR     │
           │             │       │             │
           │ - Notificar │       │ - Rollback  │
           │ - Limpiar   │       │ - Notificar │
           │   backups   │       │ - Log error │
           └─────────────┘       └─────────────┘
```

## Rollback

### Automático (en caso de error)

```php
// src/Service/UpdateService.php
public function rollback(string $backupPath): void
{
    $this->logger->warning('Iniciando rollback de actualización');

    // 1. Restaurar archivos
    $this->fileSystem->mirror($backupPath . '/files', $this->projectDir);

    // 2. Restaurar base de datos
    $this->databaseService->restore($backupPath . '/database.sql');

    // 3. Limpiar cache
    $this->cacheService->clear();

    // 4. Reportar
    $this->reportUpdate('rolled_back');

    $this->logger->info('Rollback completado');
}
```

### Manual

```bash
# Listar backups disponibles
php bin/console app:update:backups

# Restaurar backup específico
php bin/console app:update:rollback 2025-01-15_103000
```

## Hotfix Process

Para correcciones urgentes de seguridad o bugs críticos:

```bash
# 1. Crear rama desde main
git checkout main
git checkout -b hotfix/1.2.1

# 2. Aplicar fix
# ... hacer cambios ...

# 3. Commit con referencia al issue
git commit -m "Fix: Security vulnerability in auth (CVE-2025-XXXX)

Refs: #456"

# 4. Tag como hotfix
git tag -a v1.2.1 -m "Hotfix: Security patch for CVE-2025-XXXX"

# 5. Push (trigger automático de release)
git push origin hotfix/1.2.1 --tags

# 6. Merge a main y develop
git checkout main && git merge hotfix/1.2.1
git checkout develop && git merge hotfix/1.2.1
```

## Notificaciones

### Al crear release

```
📦 Nueva versión disponible: SGV v1.3.0

Cambios principales:
- Nueva funcionalidad de reportes avanzados
- Integración con Prometheus
- Mejoras de rendimiento

Canal: stable
Tiers: standard, enterprise

Ver changelog completo en: https://updates.gvops.cl/releases/1.3.0
```

### Al cliente (email/dashboard)

```
Estimado cliente,

Hay una nueva actualización disponible para su sistema SGV.

Versión actual: 1.2.0
Nueva versión: 1.3.0

Para actualizar, ejecute:
php bin/console app:update:apply 1.3.0

O acceda al panel de administración > Actualizaciones

Saludos,
Equipo SGV
```

## Métricas de Release

```prometheus
# Descargas por versión
sgv_release_downloads_total{version="1.3.0", channel="stable"} 45

# Actualizaciones exitosas
sgv_release_updates_total{version="1.3.0", status="applied"} 42

# Actualizaciones fallidas
sgv_release_updates_total{version="1.3.0", status="failed"} 3

# Tiempo promedio de actualización
sgv_release_update_duration_seconds_sum 1350
sgv_release_update_duration_seconds_count 42
```

## Referencias

- [UPDATE_SERVER_ARCHITECTURE.md](./UPDATE_SERVER_ARCHITECTURE.md)
- [LICENSE_SYSTEM.md](./LICENSE_SYSTEM.md)
- [Semantic Versioning](https://semver.org/)
- [Keep a Changelog](https://keepachangelog.com/)
