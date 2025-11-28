# Prometheus y Alertmanager Viewer

Documentación para los módulos de visualización de Prometheus y Alertmanager integrados en EasyAdmin.

## Arquitectura

```
Usuario → Symfony App (EasyAdmin) → Renderiza vista con iframe
                                            ↓
                                    Prometheus/Alertmanager (URL configurable desde BD)
```

**Principios:**
- No se hace proxy en PHP/Symfony
- URL completamente configurable desde base de datos
- El proxy reverso (si es necesario) se configura a nivel de infraestructura (nginx/apache)

## URLs de Acceso

| Servicio | URL Admin | Ruta Symfony |
|----------|-----------|--------------|
| Prometheus | `/admin/prometheus` | `admin_prometheus_dashboard` |
| Alertmanager | `/admin/alertmanager` | `admin_alertmanager_dashboard` |

## Configuración

### Configuración en Base de Datos

Las URLs se almacenan en la tabla `app_settings`:

| Key | Valor por defecto | Descripción |
|-----|-------------------|-------------|
| `prometheus.url` | `http://127.0.0.1:9090` | URL del servidor Prometheus |
| `alertmanager.url` | `http://127.0.0.1:9093` | URL del servidor Alertmanager |

### Actualizar URLs en Producción

```sql
-- Prometheus
UPDATE app_settings SET value = 'http://10.10.10.19:9090' WHERE `key` = 'prometheus.url';

-- Alertmanager
UPDATE app_settings SET value = 'http://10.10.10.19:9093' WHERE `key` = 'alertmanager.url';
```

O usando ConfigurationService:
```php
$configService->set('prometheus.url', 'http://10.10.10.19:9090');
$configService->set('alertmanager.url', 'http://10.10.10.19:9093');
```

## Permisos

Ambas vistas requieren `ROLE_SUPER_ADMIN`.

## Migración

Ejecutar la migración para crear las configuraciones:

```bash
php bin/console doctrine:migrations:migrate
```

La migración `Version20251124085903` crea las entradas en `app_settings`.

## Configuración de X-Frame-Options

Para que Prometheus y Alertmanager se muestren correctamente en el iframe, deben permitir ser embebidos.

### Prometheus

En `prometheus.yml` o archivo de configuración:

```yaml
# Si usas --web.external-url
# Prometheus generalmente permite embedding por defecto
```

O si usas un proxy reverso (nginx), añadir:

```nginx
location /prometheus {
    proxy_pass http://localhost:9090;
    proxy_hide_header X-Frame-Options;
    add_header X-Frame-Options "SAMEORIGIN";
}
```

### Alertmanager

Similar a Prometheus, en la configuración del proxy:

```nginx
location /alertmanager {
    proxy_pass http://localhost:9093;
    proxy_hide_header X-Frame-Options;
    add_header X-Frame-Options "SAMEORIGIN";
}
```

## Troubleshooting

### El iframe muestra error de conexión

1. Verificar que el servicio esté corriendo:
   ```bash
   curl -I http://localhost:9090  # Prometheus
   curl -I http://localhost:9093  # Alertmanager
   ```

2. Verificar la URL en `app_settings`:
   ```sql
   SELECT * FROM app_settings WHERE `key` LIKE '%prometheus%' OR `key` LIKE '%alertmanager%';
   ```

### Error "Refused to display in a frame"

El servicio está bloqueando el iframe. Configurar X-Frame-Options como se indica arriba.

### No aparece en el menú

1. Verificar permisos del usuario (requiere `ROLE_SUPER_ADMIN`)
2. Limpiar caché: `php bin/console cache:clear`
3. Verificar que las rutas existen: `php bin/console debug:router | grep prometheus`

## Archivos del Módulo

| Archivo | Propósito |
|---------|-----------|
| `src/Controller/Dashboard/PrometheusController.php` | Controlador Prometheus |
| `src/Controller/Dashboard/AlertmanagerController.php` | Controlador Alertmanager |
| `templates/dashboard/prometheus/index.html.twig` | Template Prometheus |
| `templates/dashboard/alertmanager/index.html.twig` | Template Alertmanager |
| `migrations/Version20251124085903.php` | Migración de configuración |

## Testing

Usar el script de test para verificar:

```bash
node /www/wwwroot/vs.gvops.cl/test-page.js https://vs.gvops.cl/admin/prometheus
node /www/wwwroot/vs.gvops.cl/test-page.js https://vs.gvops.cl/admin/alertmanager
```

## Consideraciones de Seguridad

1. Los firewalls en `security.yaml` permiten acceso sin autenticación Symfony a `/prometheus` y `/alertmanager`
2. La autenticación se maneja a nivel del servicio o proxy reverso
3. El control de acceso al panel admin (`/admin/prometheus`, `/admin/alertmanager`) está protegido por `ROLE_SUPER_ADMIN`
