# Project Context: Sistema de Gestión Vial (SGV)

## Purpose
Sistema de Gestión Vial para monitoreo y gestión de autopistas concesionadas en Chile. El sistema maneja múltiples concesiones (Costanera Norte y Vespucio Sur) con capacidades de:
- Monitoreo de dispositivos (sensores, espiras, cámaras CCTV)
- Sistema de Información Vial (SIV) para gestión de incidentes
- Reportería avanzada con gráficos y exportación a Excel/PDF
- Integración con WhatsApp Business API para notificaciones
- Panel de control unificado (SCADA) para permisos de trabajo y bitácora

## Tech Stack

### Backend
- **Framework**: Symfony 6.4 (PHP 8.2+)
- **ORM**: Doctrine ORM (múltiples entity managers)
- **Admin Panel**: EasyAdminBundle 4.x
- **PDF Generation**: Knp Snappy (wkhtmltopdf)
- **Excel Generation**: PhpSpreadsheet

### Frontend
- **Template Engine**: Twig
- **JavaScript**:
  - jQuery 3.7.1 (legacy compatibility)
  - Bootstrap 5.x
  - Bootstrap Table 1.22.1
  - Highcharts (visualizaciones)
  - Tempus Dominus 6 (date pickers)
  - Choices.js (selects avanzados)
- **CSS**: Bootstrap 5 + custom CSS

### Databases
- **MySQL 8.0**: Base de datos principal (`gesvial_sgv`)
  - Dispositivos, configuraciones, usuarios
- **PostgreSQL 12**: Base de datos SIV (`dbpuente`)
  - Incidentes, reportes, stored functions
- **PostgreSQL**: Bases de datos VS (Vespucio Sur)
  - `dbpuente` (datos de espiras VS)
  - Adquisición de datos espiras

### Infrastructure
- **Web Server**: Nginx
- **OS**: Linux (Rocky Linux / CentOS)
- **Process Management**: Supervisor (para workers)
- **Deployment**: Manual via SSH/SCP

## Project Conventions

### Code Style
- **PHP**:
  - PSR-12 coding standard
  - Type declarations obligatorias
  - Comentarios en español cuando sean necesarios
  - No usar iconos/emojis en código
  - Comentarios genéricos, no como agente IA
- **JavaScript**:
  - AJAX directo (patrón Thin Server, Rich Client para módulos legacy)
  - ES6+ syntax donde sea compatible
  - Evitar frameworks pesados sin justificación
- **Twig**:
  - Herencia de templates (`@EasyAdmin/page/content.html.twig`)
  - Variables con prefijos claros (`data_table`, `return_file_name_excel`)
- **SQL**:
  - Stored functions PostgreSQL con sufijo `_r` para columnas resultado
  - Prepared statements siempre (seguridad)

### Architecture Patterns

#### Multi-Concession Architecture
- **Costanera Norte (CN)**: Concesión principal
- **Vespucio Sur (VS)**: Segunda concesión con reportes separados
- Compartir funciones SQL cuando sea posible, separar presentación (templates, logos, branding)

#### Patrón MVC con Symfony
- **Controllers**:
  - `Admin/DashboardController.php` - Menús y navegación EasyAdmin
  - `Dashboard/SivController.php` - Reportes SIV
  - `Dashboard/CotController.php` - Monitor dispositivos
- **Templates**: `/templates/dashboard/{module}/`
- **Entity Managers**:
  - `default` → MySQL (gesvial_sgv)
  - `siv` → PostgreSQL (dbpuente)
  - `db_espiras_vs` → PostgreSQL VS espiras

#### Reportes Pattern
1. Controller method con route EasyAdmin (`#[AdminRoute]`)
2. Query a stored function PostgreSQL
3. Opción de generación Excel desde template
4. Opción de generación PDF vía Knp Snappy
5. Vista HTML con Bootstrap Table para filtrado/búsqueda

#### Legacy Compatibility
- **NO cambiar radicalmente patrones** sin justificación del framework
- Sistema legacy usa AJAX directo + jQuery → mantener para consistencia
- Symfony Forms solo cuando agregue valor real
- No forzar patrones modernos en módulos funcionales

### Testing Strategy
- Testing manual principalmente
- Puppeteer script (`test-page.js`) para screenshots y debug visual
- Comando: `node /www/wwwroot/vs.gvops.cl/test-page.js [URL]`
- Verificar funcionalidad en navegador antes de deploy

### Git Workflow
- **Branch principal**: `main`
- Commits descriptivos en español
- No commits hasta que funcionalidad esté completa y probada
- Git hooks configurados (respetarlos siempre)
- No usar `--amend` a menos que sea explícito

### File Permissions (CRÍTICO)
- **Archivos `.env`**: `640` permisos, owner `opc:www`
- **Archivos públicos**: `664` permisos, owner `www:www`
- **Directorios**: `775` permisos
- Ver: `docs/FILE_PERMISSIONS.md`

## Domain Context

### Gestión de Incidentes (SIV)
- **Tipos de incidentes**: Accidentes, bloqueos, trabajos, operación normal
- **Recursos**: Ambulancias (A), Bomberos (B), Carabineros (C), Servicios (S)
- **Estados**: En curso, finalizados, atendidos
- **Tiempos críticos**: Respuesta, atención, finalización

### Monitoreo de Dispositivos (COT)
- **Tipos**: Sensores ambientales, espiras, cámaras CCTV, señalética variable
- **Estados**: Online, offline, con alarmas
- **Alarmas**: Alta temperatura, CO alto, opacidad, viento
- **Históricos**: Espiras con datos cada 5 minutos

### Concesiones
- **CN**: Costanera Norte (logo GESVIAL)
- **VS**: Vespucio Sur (logo vs_logo.png)
- Cada concesión tiene:
  - Centro de costos separado
  - Personal asignado
  - Proveedores
  - Reportes branded

### WhatsApp Business Integration
- Notificaciones de incidentes críticos
- Validación de números con certificados
- Comando revalidación: `php bin/console app:whatsapp:revalidate <PIN>`
- Ver: `docs/WHATSAPP_REVALIDATION.md`

## Important Constraints

### Performance
- Reportes pueden tener hasta 1000+ registros
- Excel generation debe usar templates (no generar desde cero)
- Queries complejas usan stored functions PostgreSQL (optimizadas)
- `set_time_limit(0)` en reportes pesados

### Security
- NUNCA exponer credenciales en código
- Variables sensibles en `.env`
- CSRF protection en formularios
- Roles y permisos EasyAdmin (`ROLE_VIEW_INCIDENT_REPORTS`, etc.)
- SQL injection prevention vía prepared statements

### Browser Compatibility
- Soporte IE11 legacy (algunos clientes)
- Bootstrap 5 compatible
- Highcharts para gráficos (compatible legacy)

### Database
- PostgreSQL stored functions son READ-ONLY desde aplicación
- No modificar estructura de tablas sin migración
- Múltiples conexiones DB (no mezclar entity managers)

### Regulatory
- Datos de incidentes son críticos (auditoría)
- Reportes deben ser reproducibles
- Logos y branding corporativo obligatorios en PDFs

## External Dependencies

### APIs Externas
- **WhatsApp Business API** (Meta)
  - Webhook configurado en `/api/whatsapp/webhook`
  - Certificados en `/hash/`
  - Diagnóstico: `/admin/whatsapp/diagnostic`

### Servicios Internos
- **Base PUENTE**: PostgreSQL (datos históricos incidentes)
- **Servidor SCADA**: Datos en tiempo real dispositivos
- **Grafana** (opcional): Dashboards de monitoreo

### Dependencias Composer (principales)
- `doctrine/orm`
- `easycorp/easyadmin-bundle`
- `knplabs/knp-snappy-bundle`
- `phpoffice/phpspreadsheet`
- `symfony/mailer`
- `symfony/security-bundle`

### Dependencias NPM (principales)
- `bootstrap@5.x`
- `jquery@3.7.1`
- `bootstrap-table`
- `highcharts`
- `@eonasdan/tempus-dominus@6`
- `choices.js`

### Scripts Útiles
- **Test visual**: `node test-page.js [URL]`
- **Crear template Excel**: `php bin/create-incidentes-template.php`
- **Migraciones**: `php bin/console doctrine:migrations:migrate`
- **Limpiar caché**: `php bin/console cache:clear --env=prod`

## Development Guidelines

### Investigar Antes de Duplicar
- Buscar avances previos que puedan reutilizarse
- No crear código redundante/spaghetti
- Comparar con legacy para entender el cambio de API
- Buscar documentación oficial y patrones de éxito
- Mantener buenas prácticas del framework y stack completo

### No Lazy/Hacky Solutions
- No interfaces visuales no profesionales
- No atajos que comprometan mantenibilidad
- Siempre seguir convenciones del framework
- Código limpio y documentado

### Consistencia Legacy
- Si legacy no usaba FormTypes, no forzarlos ahora
- Mantener patrón AJAX directo donde existe
- Solo modernizar con justificación clara
