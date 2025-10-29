# Estrategia de Deployment Manual (sin Git)

## Resumen de Cambios Realizados

### Archivos PHP Modificados
1. `src/Controller/UserProfileController.php` - Redirige perfil a EasyAdmin
2. `src/Controller/Admin/DashboardController.php` - URL del menú de perfil
3. `src/Entity/User.php` - Agregadas propiedades 2FA

### Templates Modificados
1. `templates/profile/index.html.twig` - Navbar agregado
2. `templates/profile/two_factor.html.twig` - Navbar agregado

### Cambios en Base de Datos

⚠️ **IMPORTANTE:** Hay múltiples tablas nuevas que faltan en producción.

**Tablas Nuevas a Crear:**
1. `audit_log` - Sistema de auditoría
2. `alerts` - Sistema de alertas
3. `alert_rules` - Reglas de notificación
4. `notification_logs` - Log de notificaciones
5. `otp_codes` - Códigos OTP para 2FA
6. `whatsapp_recipients` - Destinatarios WhatsApp
7. `whatsapp_recipient_groups` - Grupos de destinatarios
8. `whatsapp_group_recipients` - Relación grupos-destinatarios
9. `whatsapp_templates` - Templates de mensajes
10. `whatsapp_messages` - Mensajes enviados

**Columnas agregadas a `security_user`:**
- `totp_secret` VARCHAR(255)
- `two_factor_enabled` TINYINT(1)
- `preferred2fa_method` VARCHAR(20)
- `login_count` INT
- `reset_token` VARCHAR(100)
- `reset_token_expires_at` DATETIME
- `must_change_password` TINYINT(1)

---

## ESTRATEGIA RECOMENDADA: Usar Script SQL Completo

### ⚠️ SCRIPT SQL COMPLETO DISPONIBLE

Hemos creado un script SQL completo que incluye TODAS las tablas nuevas:

📄 **Archivo:** `deployment-production.sql`

Este script incluye:
- ✅ Verificaciones IF NOT EXISTS (seguro de ejecutar)
- ✅ Todas las 10 tablas nuevas
- ✅ Todas las columnas 2FA en security_user
- ✅ Foreign keys de WhatsApp
- ✅ Idempotente (puedes ejecutarlo múltiples veces)

### Crear archivo de migración SQL (alternativa manual)

Si prefieres crear el archivo manualmente:

```bash
cat > migration-profile-fix.sql << 'EOF'
-- Profile Fix Migration
-- Fecha: 2025-10-29

-- 1. Crear tabla audit_log si no existe
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    level VARCHAR(20) DEFAULT 'INFO' NOT NULL,
    source VARCHAR(100) DEFAULT NULL,
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity_type (entity_type),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- 2. Agregar columnas 2FA si no existen
ALTER TABLE security_user
ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) DEFAULT NULL AFTER must_change_password,
ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret,
ADD COLUMN IF NOT EXISTS preferred2fa_method VARCHAR(20) DEFAULT NULL AFTER two_factor_enabled,
ADD COLUMN IF NOT EXISTS login_count INT NOT NULL DEFAULT 0 AFTER preferred2fa_method;
EOF
```

### Pasos para Deployment en Producción

#### 1. BACKUP (CRÍTICO - NO OMITIR)
```bash
# En servidor de producción
cd /ruta/produccion

# Backup de archivos
tar -czf backup-profile-$(date +%Y%m%d-%H%M%S).tar.gz \
  src/Controller/UserProfileController.php \
  src/Controller/Admin/DashboardController.php \
  src/Entity/User.php \
  templates/profile/index.html.twig \
  templates/profile/two_factor.html.twig

# Backup de base de datos
mysqldump -u USUARIO -p BASE_DATOS > backup-db-$(date +%Y%m%d-%H%M%S).sql
```

#### 2. TRANSFERIR ARCHIVOS

**Opción A: Usando Git (si es posible en prod)**
```bash
cd /ruta/produccion
git pull origin main
```

**Opción B: Copiar archivos manualmente (SCP)**
```bash
# Desde tu servidor local/desarrollo
scp src/Controller/UserProfileController.php user@prod:/ruta/produccion/src/Controller/
scp src/Controller/Admin/DashboardController.php user@prod:/ruta/produccion/src/Controller/Admin/
scp src/Entity/User.php user@prod:/ruta/produccion/src/Entity/
scp templates/profile/index.html.twig user@prod:/ruta/produccion/templates/profile/
scp templates/profile/two_factor.html.twig user@prod:/ruta/produccion/templates/profile/
```

**Opción C: Copiar contenido manualmente**
1. Abrir cada archivo en tu editor local
2. Conectar por SFTP/FTP al servidor de producción
3. Abrir el mismo archivo en producción
4. Copiar y pegar el contenido completo
5. Guardar

#### 3. EJECUTAR SQL EN PRODUCCIÓN

**Opción A: Usar el script completo (RECOMENDADO)**
```bash
# Transferir el archivo SQL a producción
scp deployment-production.sql user@prod:/ruta/produccion/

# En el servidor de producción
mysql -u USUARIO -p BASE_DATOS < deployment-production.sql
```

**Opción B: Ejecutar manualmente el SQL**
```bash
# Conectar a MySQL
mysql -u USUARIO -p BASE_DATOS

# Dentro de MySQL, copiar y pegar el contenido de deployment-production.sql
```

**Opción C: SQL mínimo (solo para el fix del perfil)**
```bash
# Conectar a MySQL
mysql -u USUARIO -p BASE_DATOS

# Dentro de MySQL, pegar el siguiente SQL:
```
```sql
-- Crear tabla audit_log
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    user_id INT DEFAULT NULL,
    username VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    old_values JSON DEFAULT NULL,
    new_values JSON DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL,
    level VARCHAR(20) DEFAULT 'INFO' NOT NULL,
    source VARCHAR(100) DEFAULT NULL,
    INDEX idx_audit_created_at (created_at),
    INDEX idx_audit_user_id (user_id),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity_type (entity_type),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- Agregar columnas 2FA
ALTER TABLE security_user
ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(255) DEFAULT NULL AFTER must_change_password,
ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER totp_secret,
ADD COLUMN IF NOT EXISTS preferred2fa_method VARCHAR(20) DEFAULT NULL AFTER two_factor_enabled,
ADD COLUMN IF NOT EXISTS login_count INT NOT NULL DEFAULT 0 AFTER preferred2fa_method;
```

#### 4. LIMPIAR CACHÉ
```bash
cd /ruta/produccion
php bin/console cache:clear --env=prod
```

#### 5. VERIFICAR
```bash
# Verificar que la URL funciona
curl -I https://tu-dominio-prod/profile

# Debe devolver 302 (redirect) o 200
```

---

## Método Alternativo: Panel Web (cPanel/Plesk)

### 1. Backup via Panel
- Ir a "Administrador de Archivos"
- Seleccionar los 5 archivos mencionados
- Descargar como ZIP

### 2. Editar Archivos via Panel
- Navegar a cada archivo
- Hacer clic en "Editar"
- Copiar el contenido nuevo
- Pegar (reemplazar todo el contenido)
- Guardar

### 3. SQL via phpMyAdmin
- Abrir phpMyAdmin
- Seleccionar la base de datos
- Ir a pestaña "SQL"
- Pegar el código SQL completo
- Ejecutar

### 4. Limpiar Caché via Terminal (en panel)
- Abrir Terminal del panel
- Ejecutar: `cd /ruta/app && php bin/console cache:clear --env=prod`

---

## Verificación Post-Deployment

✅ Checklist:

1. [ ] Acceder a `https://tu-dominio/profile`
2. [ ] Debe redirigir a `https://tu-dominio/admin/user/X/edit`
3. [ ] Se muestra el formulario de edición de usuario
4. [ ] NO aparece error 500
5. [ ] El menú "Mi Perfil" del admin funciona
6. [ ] La tabla `audit_log` existe en la BD
7. [ ] Las columnas 2FA existen en `security_user`

### Comandos de Verificación
```bash
# Verificar tabla audit_log
mysql -u USER -p -e "DESCRIBE audit_log;" DATABASE

# Verificar columnas en security_user
mysql -u USER -p -e "SHOW COLUMNS FROM security_user WHERE Field IN ('totp_secret', 'two_factor_enabled', 'preferred2fa_method', 'login_count');" DATABASE

# Ver logs de errores
tail -f /ruta/produccion/var/log/prod.log
```

---

## Rollback (Si algo sale mal)

```bash
# 1. Restaurar archivos
cd /ruta/produccion
tar -xzf backup-profile-TIMESTAMP.tar.gz

# 2. Restaurar BD (solo si ejecutaste el SQL)
mysql -u USER -p DATABASE < backup-db-TIMESTAMP.sql

# 3. Limpiar caché
php bin/console cache:clear --env=prod
```

---

## Archivos que Debes Copiar a Producción

**Lista completa de archivos modificados:**

1. **src/Controller/UserProfileController.php**
   - Contiene el redirect a EasyAdmin

2. **src/Controller/Admin/DashboardController.php**
   - Línea 55: `MenuItem::linkToUrl('Mi Perfil', 'fas fa-user', '/profile')`

3. **src/Entity/User.php**
   - Agregadas propiedades y métodos 2FA (líneas 77-87 y 323-376)

4. **templates/profile/index.html.twig**
   - Navbar agregado

5. **templates/profile/two_factor.html.twig**
   - Navbar agregado

---

## Notas de Seguridad

⚠️ **IMPORTANTE:**
1. Siempre hacer backup ANTES de cualquier cambio
2. Probar en ambiente de staging primero (si existe)
3. Hacer deployment en horario de bajo tráfico
4. Tener plan de rollback listo
5. Verificar permisos de archivos después de copiar:
   ```bash
   chmod 644 src/Controller/*.php
   chmod 644 src/Entity/*.php
   chmod 644 templates/profile/*.twig
   ```

---

## Soporte

Si encuentras problemas:

1. **Error 500:** Revisar `/var/log/nginx/error.log` y `/ruta/app/var/log/prod.log`
2. **Tabla no existe:** Re-ejecutar el SQL de creación de tabla
3. **Cache no se limpia:** Eliminar manualmente: `rm -rf var/cache/prod/*`
4. **Permisos:** Ejecutar `chown -R www-data:www-data /ruta/app` (ajustar usuario según tu servidor)

---

## Resumen Rápido (TL;DR)

```bash
# 1. Backup
tar -czf backup.tar.gz src/Controller/ src/Entity/ templates/profile/
mysqldump -u USER -p DB > backup.sql

# 2. Copiar 5 archivos PHP/Twig a producción

# 3. Ejecutar SQL (crear tabla + agregar columnas)

# 4. Limpiar caché
php bin/console cache:clear --env=prod

# 5. Verificar
curl -I https://dominio/profile
```
