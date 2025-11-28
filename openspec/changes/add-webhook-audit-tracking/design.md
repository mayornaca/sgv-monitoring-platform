# Design: Webhook Audit Tracking System

## Context

El sistema SGV recibe webhooks de múltiples fuentes:
- **Alertmanager/Prometheus**: Alertas de monitoreo de infraestructura
- **WhatsApp Business API**: Status updates y mensajes entrantes
- **Grafana** (futuro): Alertas unificadas

Actualmente, estos webhooks se procesan síncronamente sin persistencia del payload original, resultando en:
1. Pérdida de webhooks cuando falla el procesamiento
2. Imposibilidad de debugging post-mortem
3. Sin visibilidad de qué webhooks llegan de cada concesión

### Stakeholders
- **Administradores del sistema**: Necesitan visibilidad de webhooks para troubleshooting
- **Operadores COT**: Dependen de alertas para respuesta a incidentes
- **Equipo de desarrollo**: Necesita datos para debugging de integraciones

## Goals / Non-Goals

### Goals
- Nunca perder un webhook (persistencia antes de procesamiento)
- Trazabilidad completa del ciclo de vida del webhook
- Identificación multi-tenant (CN/VS)
- Interfaz admin para visualización y gestión
- Base para futuras mejoras (Messenger async, retry automático)

### Non-Goals
- No modificar la lógica de procesamiento existente (solo agregar logging)
- No implementar procesamiento asíncrono en esta fase
- No implementar retry automático (solo manual desde admin)
- No implementar métricas Prometheus de webhooks

## Decisions

### Decision 1: Store-then-Process Pattern
**Qué**: Guardar el webhook en BD ANTES de cualquier validación o procesamiento.

**Por qué**:
- Garantiza que ningún webhook se pierde
- Permite replay de webhooks fallidos
- Facilita debugging con el payload original intacto

**Alternativas consideradas**:
- Log a archivo: Descartado por dificultad de consulta y replay
- Queue primero: Más complejo, reservado para Fase 2

### Decision 2: Single Table Design
**Qué**: Usar una tabla `webhook_log` para todos los tipos de webhook.

**Por qué**:
- Simplicidad de implementación y consulta
- El campo `source` permite filtrar por tipo
- Campos JSON (`headers`, `parsedData`, `processingResult`) acomodan variaciones

**Alternativas consideradas**:
- Tablas separadas por tipo: Mayor complejidad, beneficio marginal
- Herencia Doctrine: Over-engineering para este caso

### Decision 3: Concession Mapping en Código
**Qué**: Mapear phone_number_id a concessionCode mediante constantes en código.

**Por qué**:
- Solo hay 2 números configurados actualmente
- Cambios de mapeo son raros
- Evita complejidad de tabla de configuración

**Alternativas consideradas**:
- Tabla de configuración: Over-engineering para 2 valores
- AppSettings: Posible, pero innecesario actualmente

### Decision 4: EasyAdmin para Visualización
**Qué**: Usar EasyAdmin CrudController para la interfaz de gestión.

**Por qué**:
- Consistente con el resto del sistema
- Filtros, búsqueda y paginación out-of-the-box
- Solo requiere configuración, no desarrollo de UI

## Risks / Trade-offs

### Risk: Crecimiento de tabla
**Mitigación**:
- Índices optimizados (source+created_at, status, concession)
- Método `cleanupOld()` para purgar webhooks completados antiguos
- Considerar particionamiento en el futuro si crece mucho

### Risk: Performance en controladores
**Mitigación**:
- INSERT es operación rápida
- No se hace JOIN ni query compleja en el flujo de recepción
- El flush() es síncrono pero el impacto es mínimo (~5-10ms)

### Trade-off: Payload duplicado
**Decisión**: Guardar rawPayload completo aunque sea redundante con datos procesados.
**Razón**: Valor de debugging y replay supera el costo de storage.

## Migration Plan

### Paso 1: Crear migración
```sql
CREATE TABLE webhook_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(50) NOT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    headers JSON,
    raw_payload LONGTEXT NOT NULL,
    parsed_data JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    processing_status VARCHAR(20) NOT NULL DEFAULT 'received',
    processing_result JSON,
    error_message TEXT,
    retry_count SMALLINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    processed_at DATETIME,
    concession_code VARCHAR(20),
    INDEX idx_source_created (source, created_at),
    INDEX idx_status (processing_status),
    INDEX idx_concession (concession_code),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Paso 2: Deploy gradual
1. Ejecutar migración
2. Agregar logging a PrometheusController
3. Agregar logging a WhatsAppWebhookController
4. Agregar menú en EasyAdmin
5. Verificar funcionamiento

### Rollback
- Eliminar logging de controladores (git revert)
- La tabla puede permanecer sin afectar funcionamiento
- DROP TABLE webhook_log si es necesario

## Open Questions

1. **¿Cuánto tiempo retener webhooks completados?**
   - Propuesta: 30 días para completados, indefinido para fallidos
   - Decisión: Configurable via método cleanupOld(daysToKeep)

2. **¿Integrar con sistema de auditoría existente (AuditLog)?**
   - Propuesta: Mantener separado por ahora, son propósitos diferentes
   - webhook_log = datos técnicos de integración
   - audit_log = acciones de usuario

3. **¿Exponer estadísticas en dashboard?**
   - Propuesta: Fase futura, por ahora solo en EasyAdmin
