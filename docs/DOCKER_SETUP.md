# Docker Development Environment - Sistema de Gestión Vial (SGV)

Guía completa para configurar y ejecutar el proyecto SGV en un entorno Docker local.

## Requisitos Previos

- **Docker Desktop** instalado y ejecutándose
  - Windows/Mac: [Descargar Docker Desktop](https://www.docker.com/products/docker-desktop)
  - Linux: Docker Engine + Docker Compose
- **Git** para clonar el repositorio
- **Mínimo 4GB RAM** disponible para Docker
- **Mínimo 10GB espacio en disco**

## Instalación Rápida

### 1. Clonar el Repositorio

```bash
git clone <repository-url>
cd vs.gvops.cl
```

### 2. Iniciar los Servicios

```bash
docker-compose up -d
```

Este comando:
- Construye la imagen PHP con todas las extensiones necesarias
- Inicia Nginx, MySQL, PostgreSQL
- Monta el código para hot-reload
- Expone la aplicación en http://localhost

### 3. Ejecutar Migraciones

```bash
# MySQL (default entity manager)
docker-compose exec app php bin/console doctrine:migrations:migrate --no-interaction

# PostgreSQL (siv entity manager)
docker-compose exec app php bin/console doctrine:migrations:migrate --em=siv --no-interaction
```

### 4. Acceder a la Aplicación

- **Aplicación**: http://localhost
- **phpMyAdmin** (opcional): http://localhost:8080
- **pgAdmin** (opcional): http://localhost:8081

## Arquitectura de Servicios

### Servicios Principales

| Servicio | Puerto | Descripción |
|----------|--------|-------------|
| `app` | 9000 | PHP 8.2-FPM con extensiones (pdo_mysql, pdo_pgsql, wkhtmltopdf) |
| `nginx` | 80 | Servidor web para Symfony |
| `mysql` | 3306 | MySQL 8.0 - base de datos principal (gesvial_sgv) |
| `postgresql` | 5432 | PostgreSQL 12 - base de datos SIV (dbpuente) |

### Servicios Opcionales (profiles: tools)

| Servicio | Puerto | Credenciales |
|----------|--------|--------------|
| `phpmyadmin` | 8080 | User: root / Pass: root |
| `pgadmin` | 8081 | Email: admin@sgv.local / Pass: admin |

Para iniciar con herramientas:
```bash
docker-compose --profile tools up -d
```

## Configuración de Bases de Datos

### MySQL (Entity Manager: default)

- **Database**: gesvial_sgv
- **User**: sgv_user
- **Password**: sgv_password
- **Conexión interna**: mysql:3306
- **Conexión host**: localhost:3306

### PostgreSQL (Entity Manager: siv)

- **Database**: dbpuente
- **User**: postgres
- **Password**: postgres
- **Conexión interna**: postgresql:5432
- **Conexión host**: localhost:5432

## Comandos Útiles

### Gestión de Contenedores

```bash
# Iniciar servicios
docker-compose up -d

# Detener servicios
docker-compose down

# Reiniciar un servicio específico
docker-compose restart app

# Ver logs en tiempo real
docker-compose logs -f app

# Ver logs de todos los servicios
docker-compose logs -f

# Ver estado de servicios
docker-compose ps
```

### Ejecutar Comandos Symfony

```bash
# Limpiar caché
docker-compose exec app php bin/console cache:clear

# Ver rutas
docker-compose exec app php bin/console debug:router

# Crear migración
docker-compose exec app php bin/console make:migration

# Ejecutar migraciones MySQL
docker-compose exec app php bin/console doctrine:migrations:migrate

# Ejecutar migraciones PostgreSQL
docker-compose exec app php bin/console doctrine:migrations:migrate --em=siv

# Ver estado de migraciones
docker-compose exec app php bin/console doctrine:migrations:status
```

### Acceso a Shells

```bash
# Shell del contenedor PHP
docker-compose exec app bash

# Shell de MySQL
docker-compose exec mysql mysql -u sgv_user -psgv_password gesvial_sgv

# Shell de PostgreSQL
docker-compose exec postgresql psql -U postgres -d dbpuente

# Shell de Nginx
docker-compose exec nginx sh
```

### Composer y NPM

```bash
# Instalar dependencias Composer
docker-compose exec app composer install

# Actualizar dependencias
docker-compose exec app composer update

# Instalar paquete específico
docker-compose exec app composer require vendor/package

# NPM (si se agrega servicio node)
docker-compose run --rm node npm install
docker-compose run --rm node npm run build
```

## Hot-Reload y Desarrollo

### ¿Cómo Funciona el Hot-Reload?

El código fuente está montado como volumen en el contenedor:
```yaml
volumes:
  - .:/var/www/html
```

**Esto significa:**
- Los cambios en archivos PHP, Twig, CSS, JS se reflejan inmediatamente
- NO necesitas reconstruir contenedores para cambios de código
- Los cambios en `.env.docker` requieren reiniciar servicios

### Cuándo Reconstruir Imagen

Solo necesitas reconstruir cuando cambias:
- Dependencias de Composer (`composer.json`)
- Extensiones PHP en `Dockerfile`
- Configuración de PHP-FPM

```bash
# Reconstruir imagen
docker-compose build app

# Reconstruir y reiniciar
docker-compose up -d --build
```

## Persistencia de Datos

### Volúmenes Nombrados

Las bases de datos persisten en volúmenes Docker:
```
mysql_data: Datos de MySQL
postgresql_data: Datos de PostgreSQL
```

### Eliminar Datos (CUIDADO)

```bash
# Detener servicios y eliminar volúmenes
docker-compose down -v

# Esto BORRARÁ todos los datos de bases de datos
```

### Backup de Bases de Datos

```bash
# Backup MySQL
docker-compose exec mysql mysqldump -u sgv_user -psgv_password gesvial_sgv > backup_mysql.sql

# Backup PostgreSQL
docker-compose exec postgresql pg_dump -U postgres dbpuente > backup_postgresql.sql

# Restaurar MySQL
docker-compose exec -T mysql mysql -u sgv_user -psgv_password gesvial_sgv < backup_mysql.sql

# Restaurar PostgreSQL
docker-compose exec -T postgresql psql -U postgres dbpuente < backup_postgresql.sql
```

## Testing de Funcionalidades

### Verificar PDF Generation (wkhtmltopdf)

```bash
# Verificar que wkhtmltopdf está instalado
docker-compose exec app wkhtmltopdf --version

# Acceder a cualquier reporte PDF en la aplicación
# Ejemplo: http://localhost/admin/siv/tiempos-recursos-externos
```

### Verificar Excel Generation (PhpSpreadsheet)

```bash
# Verificar extensiones PHP necesarias
docker-compose exec app php -m | grep -E 'zip|xml|gd'

# Generar reporte Excel desde la aplicación
# Ejemplo: http://localhost/admin/siv/informe-mensual-citofonia
```

### Verificar Conexiones DB

```bash
# Test conexión MySQL
docker-compose exec app php bin/console dbal:run-sql "SELECT 1" --connection=default

# Test conexión PostgreSQL
docker-compose exec app php bin/console dbal:run-sql "SELECT 1" --connection=siv
```

## Troubleshooting

### Error: "Cannot connect to MySQL/PostgreSQL"

**Solución:**
```bash
# Verificar que servicios estén corriendo
docker-compose ps

# Reiniciar bases de datos
docker-compose restart mysql postgresql

# Verificar logs
docker-compose logs mysql
docker-compose logs postgresql
```

### Error: "Permission denied" en var/cache

**Solución:**
```bash
# Desde el contenedor
docker-compose exec app chown -R www-data:www-data var

# O desde el host (Linux/Mac)
sudo chown -R $(id -u):$(id -g) var
```

### Error: "Port already in use"

**Problema:** Puerto 80, 3306 o 5432 ya está en uso en tu máquina.

**Solución:** Editar `docker-compose.yml` y cambiar mapeo de puertos:
```yaml
ports:
  - "8000:80"  # Cambia 80 a 8000
```

### Contenedor PHP sale inmediatamente

**Solución:**
```bash
# Ver logs detallados
docker-compose logs app

# Reconstruir imagen
docker-compose build --no-cache app
docker-compose up -d
```

### Hot-reload no funciona

**Problema:** Cambios en código no se reflejan.

**Solución:**
```bash
# Verificar que el código esté montado
docker-compose exec app ls -la /var/www/html

# Limpiar caché Symfony
docker-compose exec app php bin/console cache:clear

# En Mac, puede ser problema de permisos
# Asegurar que Docker tiene acceso a la carpeta del proyecto
```

### "wkhtmltopdf: not found"

**Solución:**
```bash
# Verificar instalación
docker-compose exec app which wkhtmltopdf

# Si no existe, reconstruir imagen
docker-compose build --no-cache app
```

## Comparación: Docker vs Producción

| Aspecto | Docker (dev) | Producción |
|---------|--------------|------------|
| Servidor web | Nginx container | Nginx en Rocky Linux |
| PHP | PHP 8.2-FPM container | PHP 8.2-FPM instalado |
| MySQL | Container mysql:8.0 | MySQL 8.0 nativo |
| PostgreSQL | Container postgres:12 | PostgreSQL 12 nativo |
| Environment | .env.docker | .env.prod |
| Hot-reload | ✅ Sí | ❌ No |
| wkhtmltopdf | En container | Instalado en sistema |

## Limpieza Completa

```bash
# Detener y eliminar todo (contenedores, volúmenes, imágenes)
docker-compose down -v --rmi all

# Eliminar volúmenes huérfanos
docker volume prune

# Eliminar imágenes no usadas
docker image prune -a
```

## Próximos Pasos

1. **Configurar Xdebug**: Para debugging con tu IDE
2. **Agregar Mailhog**: Para testing de emails
3. **Configurar Redis**: Para caché y sesiones
4. **CI/CD**: Integrar con GitHub Actions

## Soporte

- Documentación Symfony: https://symfony.com/doc/current/
- Docker Compose: https://docs.docker.com/compose/
- Issues del proyecto: [Ver repositorio]
