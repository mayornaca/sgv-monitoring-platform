# Grafana SSO con OAuth2 (Symfony)

Documentación para configurar Single Sign-On entre Symfony y Grafana usando OAuth2.

## Arquitectura

```
Usuario → Symfony (Login) → OAuth2 Authorization → Grafana (Auto-login)
                ↓
         Token + UserInfo
```

## Endpoints OAuth2 de Symfony

| Endpoint | URL | Descripción |
|----------|-----|-------------|
| Authorize | `/authorize` | Inicio del flujo OAuth2 |
| Token | `/token` | Intercambio de código por token |
| UserInfo | `/api/userinfo` | Información del usuario autenticado |

## Cliente OAuth2 Creado

```
Identifier: 4be6b3195cd71f4fe5d0998e7f279a1c
Secret: 50407db4228bb31c8897854502a649093c74c51d6b076b730cf68dd8545b25bb7b924c1be0e4d4b3ddec0e5863f4a15102642e2a6508f6fd304dd4a8aaccd358
```

## Configuración de Grafana

### Para VS - Vespucio Sur (obs.gvops.cl)

Grafana en: `https://obs.gvops.cl`
Symfony OAuth en: `https://vs.gvops.cl`

```ini
[server]
root_url = https://obs.gvops.cl/
serve_from_sub_path = false

[security]
allow_embedding = true
cookie_samesite = lax

[users]
auto_assign_org = true
auto_assign_org_id = 1
auto_assign_org_role = Viewer
default_language = es-ES

[auth.generic_oauth]
enabled = true
name = SGV
icon = signin
allow_sign_up = true
auto_login = false
client_id = 4be6b3195cd71f4fe5d0998e7f279a1c
client_secret = 50407db4228bb31c8897854502a649093c74c51d6b076b730cf68dd8545b25bb7b924c1be0e4d4b3ddec0e5863f4a15102642e2a6508f6fd304dd4a8aaccd358
scopes = openid email profile
auth_url = https://vs.gvops.cl/authorize
token_url = https://vs.gvops.cl/token
api_url = https://vs.gvops.cl/api/userinfo
redirect_uri = https://obs.gvops.cl/login/generic_oauth
id_attribute_path = sub
login_attribute_path = email
email_attribute_path = email
name_attribute_path = name
role_attribute_path = grafana_role
allow_assign_grafana_admin = true
skip_org_role_sync = false
```

### Para CN - Costanera Norte (sgv.costaneranorte.cl/grafana)

Grafana en: `https://sgv.costaneranorte.cl/grafana`
Symfony OAuth en: `https://sgv.costaneranorte.cl`

**1. Crear cliente OAuth en Symfony:**
```bash
php bin/console league:oauth2-server:create-client grafana-cn \
  --scope=openid --scope=email --scope=profile \
  --grant-type=authorization_code --grant-type=refresh_token \
  --redirect-uri=https://sgv.costaneranorte.cl/grafana/login/generic_oauth
```

**2. Configurar `grafana.ini`:**
```ini
[server]
root_url = https://sgv.costaneranorte.cl/grafana/
serve_from_sub_path = true

[security]
allow_embedding = true
cookie_samesite = lax

[users]
auto_assign_org = true
auto_assign_org_id = 1
auto_assign_org_role = Viewer
default_language = es-ES

[auth.generic_oauth]
enabled = true
name = SGV
icon = signin
allow_sign_up = true
auto_login = false
client_id = <NUEVO_CLIENT_ID>
client_secret = <NUEVO_CLIENT_SECRET>
scopes = openid email profile
auth_url = https://sgv.costaneranorte.cl/authorize
token_url = https://sgv.costaneranorte.cl/token
api_url = https://sgv.costaneranorte.cl/api/userinfo
redirect_uri = https://sgv.costaneranorte.cl/grafana/login/generic_oauth
id_attribute_path = sub
login_attribute_path = email
email_attribute_path = email
name_attribute_path = name
role_attribute_path = grafana_role
allow_assign_grafana_admin = true
skip_org_role_sync = false
```

## Vincular Usuarios Existentes

**IMPORTANTE:** Si ya existen usuarios en Grafana creados antes de OAuth2, NO eliminarlos. Vincularlos manualmente:

### 1. Obtener ID del usuario en Grafana

```sql
-- En la base de datos SQLite de Grafana
SELECT id, login, email FROM user;
```

### 2. Obtener ID del usuario en Symfony

```sql
-- En MySQL de Symfony
SELECT id, email FROM security_user WHERE email = 'usuario@ejemplo.com';
```

### 3. Vincular usuario a OAuth2

```sql
-- En SQLite de Grafana
INSERT INTO user_auth (user_id, auth_module, auth_id, created)
VALUES (
  1,                        -- ID del usuario en Grafana
  'oauth_generic_oauth',    -- Módulo de autenticación
  '1',                      -- ID del usuario en Symfony (sub del userinfo)
  datetime('now')
);
```

### Ejemplo completo VS:

```bash
# Acceder a la BD de Grafana
sudo sqlite3 /www/dk_project/dk_app/grafana/grafana_xFTk/data/grafana.db

# Ver usuarios existentes
SELECT id, login, email FROM user;

# Vincular usuario (ejemplo: user_id=1 en Grafana, sub=1 en Symfony)
INSERT INTO user_auth (user_id, auth_module, auth_id, created)
VALUES (1, 'oauth_generic_oauth', '1', datetime('now'));

# Verificar vinculación
SELECT * FROM user_auth WHERE user_id = 1;
```

## Mapeo de Roles

El endpoint `/api/userinfo` mapea roles de Symfony a Grafana:

| Rol Symfony | Rol Grafana |
|-------------|-------------|
| ROLE_SUPER_ADMIN | Admin |
| ROLE_ADMIN | Editor |
| Otros | Viewer |

## Flujo de Autenticación

1. Usuario accede a Grafana (`/grafana`)
2. Grafana redirige a Symfony (`/authorize?client_id=...`)
3. Si no está autenticado en Symfony, muestra login
4. Usuario autoriza la aplicación
5. Symfony redirige a Grafana con código de autorización
6. Grafana intercambia código por token (`/token`)
7. Grafana obtiene info del usuario (`/api/userinfo`)
8. Usuario queda autenticado en Grafana

## Comandos Útiles

### Crear nuevo cliente OAuth
```bash
php bin/console league:oauth2-server:create-client <nombre> \
  --scope=openid --scope=email --scope=profile \
  --grant-type=authorization_code --grant-type=refresh_token \
  --redirect-uri=<URL_CALLBACK>
```

### Listar clientes
```bash
php bin/console league:oauth2-server:list-clients
```

### Eliminar cliente
```bash
php bin/console league:oauth2-server:delete-client <identifier>
```

### Actualizar cliente
```bash
php bin/console league:oauth2-server:update-client <identifier> \
  --redirect-uri=<NUEVA_URL>
```

## Archivos de Configuración

| Archivo | Descripción |
|---------|-------------|
| `config/packages/league_oauth2_server.yaml` | Configuración OAuth2 Server |
| `config/packages/security.yaml` | Firewalls y access control |
| `src/Controller/Api/OAuth2UserInfoController.php` | Endpoint userinfo |
| `migrations/Version20251125163130.php` | Tablas OAuth2 |

## Troubleshooting

### Error "Invalid redirect_uri"
- Verificar que la URL de callback coincida exactamente con la registrada
- Incluir protocolo (https://) y path completo

### Error "Access denied"
- Verificar que el usuario esté autenticado en Symfony
- Comprobar que el cliente tiene los scopes correctos

### Usuario no se crea en Grafana
- Verificar `allow_sign_up = true` en grafana.ini
- Comprobar que `/api/userinfo` devuelve datos correctos

### Roles no se asignan correctamente
- Verificar `role_attribute_path = grafana_role`
- Comprobar respuesta de `/api/userinfo`

### Botón OAuth muestra "Sign in with ..."
- Grafana automáticamente agrega "Sign in with " antes del nombre
- Usar nombre corto: `name = SGV` en lugar de `name = Iniciar sesión con SGV`
- Resultado: "Sign in with SGV"

### Logo o idioma no cambian después de reiniciar
- Limpiar cache del navegador (Ctrl+Shift+R o Ctrl+F5)
- Verificar que el contenedor se reinició: `docker-compose restart`
- Verificar montaje: `docker-compose exec grafana_xFTk ls -la /usr/share/grafana/public/img/`

## Personalización de Logo (Portable)

### Estructura de Carpetas

```
grafana_xFTk/
├── docker-compose.yml    # Define los volúmenes
├── grafana.ini           # Configuración OAuth2 + branding
├── .env                  # Variables de entorno
├── custom/               # Logos personalizados (PORTABLE)
│   ├── logo.svg          # Logo principal (login y menú)
│   └── fav32.png         # Favicon del navegador
└── data/                 # Datos de Grafana (BD, plugins)
```

### docker-compose.yml con Logos

```yaml
services:
  grafana_xFTk:
    image: grafana/grafana:${VERSION}
    restart: always
    ports:
      - ${HOST_IP}:${WEB_HTTP_PORT}:3000
    volumes:
      - ${APP_PATH}/data:/var/lib/grafana
      - ${APP_PATH}/grafana.ini:/etc/grafana/grafana.ini
      - ${APP_PATH}/custom/logo.svg:/usr/share/grafana/public/img/grafana_icon.svg:ro
      - ${APP_PATH}/custom/fav32.png:/usr/share/grafana/public/img/fav32.png:ro
    networks:
      - baota_net

networks:
  baota_net:
    external: true
```

### Archivos de Logo

| Archivo | Destino en Grafana | Uso |
|---------|-------------------|-----|
| `custom/logo.svg` | `/usr/share/grafana/public/img/grafana_icon.svg` | Logo en login y menú lateral |
| `custom/fav32.png` | `/usr/share/grafana/public/img/fav32.png` | Favicon del navegador |

### Replicar en Otro Servidor

```bash
# Copiar carpeta completa (incluye logos, config, datos)
scp -r grafana_xFTk/ usuario@servidor:/ruta/destino/

# En destino
cd /ruta/destino/grafana_xFTk
docker-compose up -d
```

**Nota:** Los logos se montan como volúmenes read-only (`:ro`), no se incrustan en el contenedor.

## Configuración de Idioma

### Idioma por defecto (Español)

En `grafana.ini`, sección `[users]`:

```ini
[users]
default_language = es-ES
```

### Idiomas disponibles

| Código | Idioma |
|--------|--------|
| en-US | English (default) |
| es-ES | Español |
| fr-FR | Français |
| de-DE | Deutsch |
| pt-BR | Português |
| zh-Hans | 中文 (简体) |

**Nota:** El usuario puede cambiar su idioma en Preferences después del login.

---

## Checklist de Deployment

### Nueva Instancia

- [ ] Ejecutar migración OAuth2: `php bin/console doctrine:migrations:migrate`
- [ ] Generar claves JWT con passphrase:
  ```bash
  openssl genpkey -algorithm RSA -out config/jwt/private.pem -aes256 \
    -pass pass:<PASSPHRASE> -pkeyopt rsa_keygen_bits:4096
  openssl rsa -in config/jwt/private.pem -passin pass:<PASSPHRASE> \
    -pubout -out config/jwt/public.pem
  ```
- [ ] Configurar variables en `.env`:
  ```
  OAUTH_PRIVATE_KEY=%kernel.project_dir%/config/jwt/private.pem
  OAUTH_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
  OAUTH_PASSPHRASE=<PASSPHRASE>
  OAUTH_ENCRYPTION_KEY=<base64_random_key>
  ```
- [ ] Crear cliente OAuth2 para Grafana
- [ ] Copiar archivos:
  - `src/Controller/Api/OAuth2UserInfoController.php`
  - `src/EventSubscriber/OAuth2ConsentSubscriber.php`
- [ ] Configurar `grafana.ini`
- [ ] Vincular usuarios existentes via SQL
- [ ] Reiniciar Grafana
- [ ] Probar flujo SSO completo

## Migración de Dashboards entre Instancias

### Usando grafana-backup-tool

```bash
# Instalar
pip install grafana-backup

# Backup desde origen (ej: CN)
export GRAFANA_URL=https://sgv.costaneranorte.cl/grafana
export GRAFANA_TOKEN=<api_token_admin>
grafana-backup save

# Restaurar en destino (ej: VS)
export GRAFANA_URL=https://obs.gvops.cl
export GRAFANA_TOKEN=<api_token_admin>
grafana-backup restore grafana-backup-*.tar.gz
```

**Nota:** Las credenciales de datasources NO se migran por seguridad. Re-ingresarlas manualmente.

## Testing

Probar el endpoint userinfo:
```bash
curl -H "Authorization: Bearer <TOKEN>" https://vs.gvops.cl/api/userinfo
```

Respuesta esperada:
```json
{
  "sub": "1",
  "email": "usuario@example.com",
  "email_verified": true,
  "preferred_username": "usuario@example.com",
  "login": "usuario@example.com",
  "name": "Nombre Apellido",
  "grafana_role": "Admin",
  "groups": ["ROLE_SUPER_ADMIN", "ROLE_ADMIN"]
}
```

## Actualización de Grafana

### Configuración de Versión

En `.env` del directorio de Grafana:

```env
VERSION=latest    # Siempre última versión estable
# o versión específica:
VERSION=12.3.0
```

### Actualizar a última versión

```bash
cd /www/dk_project/dk_app/grafana/grafana_xFTk

# Descargar nueva imagen y recrear contenedor
sudo docker-compose pull && sudo docker-compose up -d

# Verificar versión
sudo docker-compose exec grafana_xFTk grafana cli --version
```

### Verificar estado

```bash
# Ver logs
sudo docker-compose logs --tail=20

# Ver estado del contenedor
sudo docker-compose ps
```

### Rollback a versión anterior

```bash
# Editar .env con versión específica
VERSION=12.2.1

# Recrear con versión anterior
sudo docker-compose up -d
```

### Notas importantes

- Los datos persisten en `data/` (volumen montado)
- La configuración persiste en `grafana.ini`
- Los logos personalizados persisten en `custom/`
- Siempre hacer backup antes de actualizar: `cp -r data/ data_backup_$(date +%Y%m%d)/`
