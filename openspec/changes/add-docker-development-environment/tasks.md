# Implementation Tasks: Add Docker Development Environment

## 1. Docker Configuration Files
- [x] 1.1 Create `Dockerfile` with PHP 8.2-fpm base image
- [x] 1.2 Install PHP extensions (pdo_mysql, pdo_pgsql, intl, zip, gd, etc.)
- [x] 1.3 Install Composer dependencies
- [x] 1.4 Configure PHP-FPM settings for development
- [x] 1.5 Add wkhtmltopdf for PDF generation (Knp Snappy)

## 2. Docker Compose Setup
- [x] 2.1 Create `docker-compose.yml` with service definitions
- [x] 2.2 Configure `app` service (PHP-FPM)
- [x] 2.3 Configure `nginx` service with custom config
- [x] 2.4 Configure `mysql` service (8.0) with init scripts
- [x] 2.5 Configure `postgresql` service (12) with init scripts
- [x] 2.6 Setup volume mounts for code hot-reload
- [x] 2.7 Setup named volumes for database persistence
- [x] 2.8 Configure networks for service communication

## 3. Nginx Configuration
- [x] 3.1 Create `docker/nginx/default.conf` for Symfony
- [x] 3.2 Configure FastCGI params for PHP-FPM
- [x] 3.3 Setup proper document root (`/public`)
- [x] 3.4 Configure rewrite rules for Symfony routing

## 4. Database Initialization
- [x] 4.1 Create `docker/mysql/init.sql` with database creation
- [x] 4.2 Create `docker/postgresql/init.sql` with database creation
- [x] 4.3 Add schema import scripts (if needed)
- [x] 4.4 Configure automatic migrations on container start

## 5. Environment Configuration
- [x] 5.1 Create `.env.docker` with local development settings
- [x] 5.2 Configure database connection strings for Docker services
- [x] 5.3 Update `DATABASE_URL` to point to docker mysql service
- [x] 5.4 Update `DATABASE_SIV_URL` to point to docker postgresql service
- [x] 5.5 Disable production-specific settings (mailer, etc.)

## 6. Docker Optimization
- [x] 6.1 Create `.dockerignore` (exclude vendor/, var/, node_modules/)
- [x] 6.2 Setup multi-stage build for smaller image size
- [x] 6.3 Configure proper cache layers in Dockerfile
- [x] 6.4 Add healthcheck for services

## 7. Documentation
- [x] 7.1 Create `docs/DOCKER_SETUP.md` with installation instructions
- [x] 7.2 Document prerequisites (Docker Desktop)
- [x] 7.3 Document how to start/stop services
- [x] 7.4 Document how to run migrations
- [x] 7.5 Document how to access logs
- [x] 7.6 Add troubleshooting section

## 8. Testing & Validation
- [ ] 8.1 Test `docker-compose up` from fresh clone
- [ ] 8.2 Verify MySQL connection works
- [ ] 8.3 Verify PostgreSQL connection works
- [ ] 8.4 Test running Symfony commands inside container
- [ ] 8.5 Test accessing application via browser (http://localhost)
- [ ] 8.6 Verify hot-reload works (code changes reflect immediately)
- [ ] 8.7 Test database migrations
- [ ] 8.8 Test PDF generation (wkhtmltopdf)
- [ ] 8.9 Test Excel generation (PhpSpreadsheet)

## 9. Additional Features (Optional)
- [x] 9.1 Add Makefile for common Docker commands
- [ ] 9.2 Setup Xdebug for debugging
- [x] 9.3 Add phpMyAdmin service for MySQL management
- [x] 9.4 Add pgAdmin service for PostgreSQL management
