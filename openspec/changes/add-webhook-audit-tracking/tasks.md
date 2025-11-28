# Tasks: Add Webhook Audit Tracking System

## 1. Database Layer
- [x] 1.1 Crear entidad `WebhookLog` con campos: id, source, endpoint, method, headers, rawPayload, parsedData, ipAddress, userAgent, processingStatus, processingResult, errorMessage, retryCount, createdAt, processedAt, concessionCode, relatedEntityType, relatedEntityId, metaMessageId
- [x] 1.2 Crear `WebhookLogRepository` con métodos: findBySource, findByStatus, findRetryable, findByConcession, findByDateRange, findByMetaMessageId, findByRelatedEntity, getStatistics, cleanupOld
- [x] 1.3 Crear migración para tabla `webhook_log` con índices: idx_source_created, idx_status, idx_concession, idx_meta_message_id, idx_related_entity

## 2. Service Layer
- [x] 2.1 Crear `WebhookLogService` con métodos:
  - `logIncoming(Request, string $source): WebhookLog` - Registra webhook entrante
  - `markAsProcessing(WebhookLog): void` - Marca como en procesamiento
  - `markAsCompleted(WebhookLog, array $result): void` - Marca como completado
  - `markAsFailed(WebhookLog, string $error): void` - Marca como fallido
  - `correlateWithMessage(WebhookLog, string $metaMessageId): void` - Correlaciona con mensaje WhatsApp
  - `setRelatedEntity(WebhookLog, string $type, int $id): void` - Establece entidad relacionada
- [x] 2.2 Crear `ConcessionMappingService` con métodos:
  - `getConcessionByPhoneNumberId(string $phoneNumberId): ?string` - Obtiene código de concesión
  - `getPhoneNumberIdsByConcession(string $code): array` - Lista phone IDs de una concesión
  - `getAvailableConcessions(): array` - Lista concesiones configuradas

## 3. Admin Interface
- [x] 3.1 Crear `WebhookLogCrudController` con:
  - Listado con columnas: id, source, endpoint, processingStatus, concessionCode, metaMessageId, createdAt
  - Filtros por: source, processingStatus, concessionCode, fechas
  - Detalle con visualización del rawPayload expandible
  - Link a mensaje relacionado (si relatedEntityType='whatsapp_message')
  - Acción de "Retry" para webhooks fallidos
- [x] 3.2 Agregar menú "Webhook Logs" en DashboardController bajo sección "Logs & Auditoría"
- [ ] 3.3 Modificar `MessageCrudController` (si existe) para mostrar webhooks relacionados en el detalle del mensaje

## 4. Integration
- [x] 4.1 Modificar `PrometheusController::webhook()`:
  - Registrar webhook ANTES de cualquier validación
  - Actualizar estado durante procesamiento
  - Registrar resultado final (completado/fallido)
- [x] 4.2 Modificar `WhatsAppWebhookController::handleNotification()`:
  - Detectar tipo de webhook (status/message/error)
  - Extraer concessionCode del phone_number_id
  - Registrar webhook antes de procesar
  - Actualizar estado al finalizar

## 5. Validation & Testing
- [x] 5.1 Ejecutar migración en ambiente desarrollo
- [ ] 5.2 Probar registro de webhook Prometheus (enviar alerta de prueba) - Pendiente prueba manual
- [ ] 5.3 Probar registro de webhook WhatsApp (enviar mensaje de prueba) - Pendiente prueba manual
- [ ] 5.4 Verificar visualización correcta en EasyAdmin - Pendiente prueba manual
- [ ] 5.5 Probar filtros y búsqueda - Pendiente prueba manual
- [ ] 5.6 Ejecutar migración en producción - Pendiente deploy

## Dependencies
- Fase 1 → Fase 2 (Service necesita Entity)
- Fase 2 → Fase 3 (Admin necesita Service)
- Fase 2 → Fase 4 (Integration necesita Service)
- Todo → Fase 5 (Testing al final)

## Notes
- Esta es la Fase 1 del plan completo de robustecimiento
- Fases futuras: Symfony Messenger, endpoint unificado Grafana, Dead Letter Queue
- No se modifica la lógica de procesamiento existente, solo se agrega logging
