# Visor de Grafana - Documentación

## Descripción

Módulo simple para visualizar Grafana en un iframe dentro del panel de administración.

**Características:**
- ✅ Desacoplado del sistema operativo
- ✅ No hace proxy en PHP/Symfony
- ✅ URL completamente configurable desde base de datos
- ✅ Compatible con cualquier instalación de Grafana (local, remota, con proxy reverso)

## Arquitectura

```
Usuario → Symfony App → Renderiza vista con iframe
                              ↓
                     Grafana (URL configurable)
```

El **proxy reverso** (si es necesario) se configura a nivel de infraestructura (nginx/apache), NO en la aplicación.

## Archivos del módulo

### 1. Controlador
**Ubicación:** `src/Controller/Dashboard/GrafanaController.php`

```php
#[AdminRoute('/grafana', name: 'grafana_dashboard')]
public function index(): Response
{
    $grafanaUrl = $this->configService->get('grafana.url', 'http://127.0.0.1:3000');

    return $this->render('dashboard/grafana/index.html.twig', [
        'grafana_url' => $grafanaUrl,
    ]);
}
```

### 2. Template
**Ubicación:** `templates/dashboard/grafana/index.html.twig`

Iframe simple que muestra la URL configurada.

### 3. Migración
**Ubicación:** `migrations/Version20251105202508.php`

Crea la configuración inicial en `app_settings`:
```sql
INSERT INTO app_settings (`key`, value, type, category, description)
VALUES ('grafana.url', 'http://127.0.0.1:3000', 'string', 'integrations', 'URL del servidor Grafana');
```

### 4. Menú
**Ubicación:** `src/Controller/Admin/DashboardController.php` (línea 178)

```php
yield MenuItem::linkToRoute('Grafana Dashboard', 'fas fa-chart-line', 'admin_grafana_dashboard')
    ->setPermission('ROLE_SUPER_ADMIN');
```

## Configuración

### Cambiar URL de Grafana

**Opción A: Via SQL**
```sql
UPDATE app_settings SET value = 'https://obs.gvops.cl/' WHERE `key` = 'grafana.url';
```

**Opción B: Via EasyAdmin**
1. Ir a panel de administración
2. Configuración → App Settings
3. Buscar clave `grafana.url`
4. Editar valor

### URLs válidas

- `http://127.0.0.1:3000` - Grafana local en Docker
- `https://obs.gvops.cl/` - Grafana externo con HTTPS
- `/grafana/` - Si hay proxy reverso en nginx/apache del mismo dominio
- `http://otro-servidor:3000` - Cualquier otra instalación

## Solución a problema X-Frame-Options

Si aparece el error: **"Refused to display in a frame because it set 'X-Frame-Options' to 'deny'"**

### Solución 1: Configurar Grafana para permitir embedding

#### Instalación Docker (actual)

**Ubicación Grafana:** `/www/dk_project/dk_app/grafana/grafana_xFTk/`

**Paso 1:** Crear archivo de configuración personalizado

```bash
cd /www/dk_project/dk_app/grafana/grafana_xFTk/
cat > grafana.ini <<EOF
[security]
# Permitir embedding en iframes
allow_embedding = true

# Cookie configuration para iframe
cookie_samesite = lax
EOF
```

**Paso 2:** Modificar docker-compose.yml para montar el archivo

```yaml
services:
  grafana_xFTk:
    image: grafana/grafana:${VERSION}
    restart: always
    ports:
      - ${HOST_IP}:${WEB_HTTP_PORT}:3000
    volumes:
      - ${APP_PATH}/data:/var/lib/grafana
      - ${APP_PATH}/grafana.ini:/etc/grafana/grafana.ini  # ← AGREGAR ESTA LÍNEA
```

**Paso 3:** Reiniciar contenedor

```bash
cd /www/dk_project/dk_app/grafana/grafana_xFTk/
sudo docker-compose down
sudo docker-compose up -d
```

**Paso 4:** Verificar

```bash
sudo docker exec grafana_xftk-grafana_xFTk-1 cat /etc/grafana/grafana.ini | grep allow_embedding
# Debe mostrar: allow_embedding = true
```

#### Instalación Nativa (sin Docker)

Editar `/etc/grafana/grafana.ini`:

```ini
[security]
allow_embedding = true
cookie_samesite = lax
```

Reiniciar:
```bash
systemctl restart grafana-server
```

### Solución 2: Proxy reverso con nginx

Si tienes proxy reverso en nginx que apunta a Grafana:

```nginx
location /grafana/ {
    proxy_pass http://127.0.0.1:3000/;

    # Headers básicos
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;

    # Permitir iframe embedding
    proxy_hide_header X-Frame-Options;
    add_header X-Frame-Options "SAMEORIGIN" always;
}
```

Entonces configurar en base de datos:
```sql
UPDATE app_settings SET value = '/grafana/' WHERE `key` = 'grafana.url';
```

## Ventajas de este enfoque

1. **Portabilidad**: Funciona en desarrollo y producción sin cambios
2. **Simplicidad**: ~100 líneas de código total
3. **Desacoplamiento**: No depende de configuración del servidor
4. **Flexibilidad**: Se adapta a cualquier topología de red
5. **Mantenibilidad**: Código simple y directo

## Responsabilidades

| Componente | Responsable |
|------------|-------------|
| Visor (iframe) | Aplicación Symfony |
| URL de Grafana | Configuración en BD |
| Proxy reverso (opcional) | Infraestructura (nginx/apache) |
| Configuración Grafana | Administrador de sistemas |
| Permisos de acceso | Symfony (ROLE_SUPER_ADMIN) |

## Troubleshooting

### Iframe muestra página en blanco
- Verificar que Grafana esté corriendo: `curl http://127.0.0.1:3000`
- Verificar URL en configuración: `SELECT * FROM app_settings WHERE key = 'grafana.url'`

### Error "Connection refused"
- Grafana no está corriendo
- Puerto incorrecto en configuración
- Firewall bloqueando conexión

### Error X-Frame-Options
- Ver sección "Solución a problema X-Frame-Options" arriba
- Configurar `allow_embedding = true` en Grafana

### Aparece barra de debug de Symfony en iframe
- **Esto NO debe pasar con la implementación actual**
- Si ocurre, verificar que el controlador NO tiene método `proxy()`
- El iframe debe apuntar directamente a Grafana, no a ruta de Symfony

## Testing

```bash
# Verificar ruta registrada
php bin/console debug:router | grep grafana

# Debe mostrar:
# admin_grafana_dashboard    ANY    ANY    ANY    /admin/grafana

# Probar con Puppeteer
node /www/wwwroot/vs.gvops.cl/test-page.js /admin/grafana

# Debe mostrar:
# ✅ No Symfony errors detected
# ✅ No console errors detected
```

## Deployment a producción

1. Ejecutar migración: `php bin/console doctrine:migrations:migrate`
2. Configurar URL según entorno (actualizar `app_settings`)
3. Si es necesario, configurar Grafana para permitir embedding
4. Limpiar caché: `php bin/console cache:clear --env=prod`

**No requiere cambios en nginx/apache de la aplicación Symfony.**
