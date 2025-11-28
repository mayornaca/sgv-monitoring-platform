# Change: Add Webhook Audit Tracking System

## Why

El sistema actual de procesamiento de webhooks (Alertmanager/Prometheus y WhatsApp) tiene problemas críticos:

1. **Pérdida de alertas**: Los webhooks de Alertmanager se procesan síncronamente sin persistencia del payload original. Si falla la búsqueda del template, la conexión a Meta API, o cualquier validación, el webhook se pierde sin registro.

2. **Sin auditoría**: No hay forma de saber qué webhooks llegaron, cuáles se procesaron correctamente, y cuáles fallaron. Imposible hacer debugging o replay de webhooks fallidos.

3. **Multi-tenant no identificado**: Los webhooks de WhatsApp llegan a un solo endpoint pero no se identifica a qué concesión (CN/VS) corresponden, dificultando el soporte y troubleshooting.

## What Changes

- **ADDED**: Nueva entidad `WebhookLog` para almacenar todos los webhooks entrantes con payload raw completo
- **ADDED**: Campos de correlación en `WebhookLog`: `relatedEntityType`, `relatedEntityId`, `metaMessageId`
- **ADDED**: `WebhookLogRepository` con métodos de consulta, estadísticas y correlación
- **ADDED**: `WebhookLogService` para logging centralizado de webhooks
- **ADDED**: `WebhookLogCrudController` para visualización en EasyAdmin con vista de webhooks relacionados
- **ADDED**: Mapeo configurable de phone_number_id a concession_code
- **MODIFIED**: `PrometheusController` para registrar webhooks antes de procesar
- **MODIFIED**: `WhatsAppWebhookController` para registrar webhooks, extraer concesión y correlacionar con mensajes

## Impact

- **Affected specs**: Nueva capacidad `webhook-audit` (no existía previamente)
- **Affected code**:
  - `src/Entity/WebhookLog.php` (nuevo)
  - `src/Repository/WebhookLogRepository.php` (nuevo)
  - `src/Service/WebhookLogService.php` (nuevo)
  - `src/Controller/Admin/WebhookLogCrudController.php` (nuevo)
  - `src/Controller/Api/PrometheusController.php` (modificar)
  - `src/Controller/Api/WhatsAppWebhookController.php` (modificar)
  - `src/Controller/Admin/DashboardController.php` (agregar menú)
- **Database**: Nueva tabla `webhook_log` en MySQL (entity manager default)
- **Breaking changes**: Ninguno

## Benefits

1. Nunca perder un webhook (siempre se guarda primero)
2. Visibilidad completa de qué llega y cómo se procesa
3. Capacidad de replay de webhooks fallidos
4. Filtrado por concesión para soporte multi-tenant
5. Estadísticas de procesamiento (tiempos, tasas de éxito)
