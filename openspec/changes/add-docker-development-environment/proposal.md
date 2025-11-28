# Change: Add Docker Development Environment

## Why
El proyecto actualmente requiere configuración manual compleja en el servidor de producción, lo que dificulta:
- Onboarding de nuevos desarrolladores (requiere configurar PHP 8.2, Nginx, PostgreSQL, MySQL)
- Desarrollo local consistente entre diferentes máquinas
- Testing en entorno aislado antes de deploy a producción
- Reproducibilidad del ambiente de producción

Docker Desktop permitirá clonar el proyecto y ejecutarlo localmente en minutos, no horas.

## What Changes
- Agregar `Dockerfile` multi-stage para PHP 8.2 con extensiones necesarias (pdo_mysql, pdo_pgsql)
- Crear `docker-compose.yml` con servicios: app, nginx, mysql, postgresql
- Configurar volúmenes para persistencia de datos y hot-reload de código
- Agregar `.dockerignore` para optimizar build
- Documentar setup en `docs/DOCKER_SETUP.md`
- Agregar archivo `.env.docker` con configuración para desarrollo local
- Scripts de inicialización para bases de datos (migrations automáticas)

## Impact
- **Affected specs**: Nueva capability `deployment` (no existía antes)
- **Affected code**:
  - Archivos nuevos: `Dockerfile`, `docker-compose.yml`, `.dockerignore`
  - Configuración: `.env.docker`
  - Documentación: `docs/DOCKER_SETUP.md`
  - Scripts: `docker/init-db.sh`, `docker/nginx.conf`
- **Breaking changes**: Ninguno - el deployment actual en servidor no se modifica
- **Dependencies**: Requiere Docker Desktop en máquina local de desarrollo
- **Time estimate**: 1-2 días de implementación y testing
