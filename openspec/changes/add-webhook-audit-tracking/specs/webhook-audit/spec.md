# Webhook Audit Tracking

Sistema de auditoría y tracking de webhooks entrantes para Alertmanager/Prometheus, Grafana y WhatsApp Business API.

## ADDED Requirements

### Requirement: Webhook Persistence
The system SHALL persist all incoming webhooks to the `webhook_log` table BEFORE any processing or validation, ensuring that no webhook is lost even if processing fails.

#### Scenario: Webhook Alertmanager recibido exitosamente
- **WHEN** un webhook POST llega a `/api/v1/prometheus/webhook`
- **THEN** el sistema crea un registro en `webhook_log` con source='alertmanager'
- **AND** el rawPayload contiene el JSON completo sin modificar
- **AND** el processingStatus es 'received' inicialmente
- **AND** el registro se persiste antes de intentar procesar el contenido

#### Scenario: Webhook WhatsApp recibido exitosamente
- **WHEN** un webhook POST llega a `/api/whatsapp/webhook`
- **THEN** el sistema crea un registro en `webhook_log` con source apropiado ('whatsapp_status', 'whatsapp_message', o 'whatsapp_error')
- **AND** el concessionCode se extrae del phone_number_id en el payload
- **AND** el rawPayload contiene el JSON completo sin modificar

#### Scenario: Webhook con JSON malformado
- **WHEN** un webhook llega con contenido que no es JSON válido
- **THEN** el sistema aún crea un registro en `webhook_log`
- **AND** el rawPayload contiene el contenido original (aunque no sea JSON)
- **AND** el processingStatus es 'failed'
- **AND** el errorMessage indica el error de parsing

### Requirement: Webhook Status Tracking
The system SHALL update the webhook processing status through its lifecycle: received → processing → completed/failed.

#### Scenario: Procesamiento exitoso
- **WHEN** un webhook se procesa correctamente
- **THEN** el processingStatus cambia a 'completed'
- **AND** el processedAt se actualiza con la fecha/hora actual
- **AND** el processingResult contiene detalles del procesamiento (alertas enviadas, estados actualizados, etc.)

#### Scenario: Procesamiento fallido
- **WHEN** ocurre un error durante el procesamiento
- **THEN** el processingStatus cambia a 'failed'
- **AND** el errorMessage contiene la descripción del error
- **AND** el retryCount se mantiene en 0 (para permitir retry manual)
- **AND** el webhook original permanece intacto en rawPayload

### Requirement: Multi-Tenant Identification
The system SHALL identify the origin concession for WhatsApp webhooks based on the phone_number_id in the payload.

#### Scenario: Identificar concesión Costanera Norte
- **WHEN** un webhook WhatsApp contiene phone_number_id='651420641396348'
- **THEN** el concessionCode se establece como 'CN'

#### Scenario: Identificar concesión Vespucio Sur
- **WHEN** un webhook WhatsApp contiene phone_number_id='888885257633217'
- **THEN** el concessionCode se establece como 'VS'

#### Scenario: Phone number desconocido
- **WHEN** un webhook WhatsApp contiene un phone_number_id no mapeado
- **THEN** el concessionCode se establece como null o 'UNKNOWN'
- **AND** el webhook se procesa normalmente

### Requirement: Admin Visualization
The system SHALL provide an EasyAdmin interface to visualize, filter, and manage registered webhooks, accessible only to ROLE_SUPER_ADMIN.

#### Scenario: Listar webhooks recientes
- **WHEN** un super admin accede a "Webhook Logs" en EasyAdmin
- **THEN** ve una lista de webhooks ordenados por fecha descendente
- **AND** cada fila muestra: id, source, endpoint, processingStatus, concessionCode, createdAt

#### Scenario: Filtrar por estado
- **WHEN** un admin filtra por processingStatus='failed'
- **THEN** solo se muestran webhooks con estado fallido
- **AND** puede identificar rápidamente qué webhooks necesitan atención

#### Scenario: Ver detalle de webhook
- **WHEN** un admin hace clic en un webhook
- **THEN** ve el rawPayload completo formateado
- **AND** ve los headers HTTP
- **AND** ve el processingResult si existe
- **AND** ve el errorMessage si existe

#### Scenario: Filtrar por concesión
- **WHEN** un admin filtra por concessionCode='CN'
- **THEN** solo se muestran webhooks relacionados con Costanera Norte
- **AND** facilita el troubleshooting específico por concesión

### Requirement: Webhook Message Correlation
The system SHALL correlate incoming webhooks with their related WhatsApp messages using the meta_message_id field, enabling full traceability from webhook to message to concession.

#### Scenario: Correlacionar webhook status con mensaje enviado
- **WHEN** un webhook de tipo 'whatsapp_status' llega con un message_id
- **THEN** el sistema extrae el meta_message_id del payload
- **AND** almacena el meta_message_id en el WebhookLog
- **AND** puede relacionar el webhook con el registro en whatsapp_messages via ese ID

#### Scenario: Correlacionar webhook con entidad relacionada
- **WHEN** un webhook se procesa y se identifica el mensaje relacionado
- **THEN** el sistema actualiza relatedEntityType='whatsapp_message'
- **AND** el sistema actualiza relatedEntityId con el ID del mensaje en whatsapp_messages
- **AND** la concesión se hereda del phoneNumberUsed del mensaje original

#### Scenario: Ver historial de webhooks de un mensaje
- **WHEN** un admin consulta un mensaje en EasyAdmin
- **THEN** puede ver todos los webhooks relacionados (sent, delivered, read, failed)
- **AND** cada webhook muestra su timestamp y payload original

#### Scenario: Trazar concesión desde webhook
- **WHEN** un webhook llega sin concessionCode explícito
- **THEN** el sistema puede inferir la concesión consultando el mensaje relacionado
- **AND** el phoneNumberUsed del mensaje determina la concesión (mapping configurable)

### Requirement: Concession Mapping Configuration
The system SHALL provide a configurable mapping between phone_number_id and concession codes, allowing flexible multi-tenant identification without code changes.

#### Scenario: Mapeo por phone_number_id
- **WHEN** un webhook WhatsApp contiene metadata.phone_number_id
- **THEN** el sistema consulta el mapeo configurado para determinar la concesión
- **AND** phone_number_id='651420641396348' mapea a 'CN' (Costanera Norte)
- **AND** phone_number_id='888885257633217' mapea a 'VS' (Vespucio Sur)

#### Scenario: Mapeo extensible para futuras concesiones
- **WHEN** se agrega una nueva concesión (ej: sgv.vespuciosur.cl)
- **THEN** el administrador puede agregar el mapeo en AppSettings o constantes
- **AND** no requiere modificar lógica de procesamiento de webhooks

#### Scenario: Fallback cuando phone_number_id no está mapeado
- **WHEN** llega un webhook con phone_number_id desconocido
- **THEN** el concessionCode se establece como 'UNKNOWN'
- **AND** se registra un warning en logs para investigación
- **AND** el webhook se procesa normalmente

### Requirement: Webhook Statistics
The system SHALL provide methods to obtain webhook statistics for monitoring and reporting.

#### Scenario: Obtener conteo por estado
- **WHEN** se solicitan estadísticas de webhooks
- **THEN** el sistema retorna conteo por processingStatus (received, processing, completed, failed)

#### Scenario: Obtener estadísticas por fuente
- **WHEN** se solicitan estadísticas por fuente
- **THEN** el sistema retorna conteo agrupado por source y processingStatus

#### Scenario: Tiempo promedio de procesamiento
- **WHEN** se solicita tiempo promedio de procesamiento
- **THEN** el sistema calcula el promedio de (processedAt - createdAt) para webhooks completados
