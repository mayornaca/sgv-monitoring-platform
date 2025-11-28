# Guía de Uso del Sistema de Alertas WhatsApp

## Tabla de Contenidos

1. [Introducción](#introducción)
2. [Gestión de Componentes](#gestión-de-componentes)
3. [Pruebas y Testing](#pruebas-y-testing)
4. [Integración con Sistemas](#integración-con-sistemas)
5. [Monitoreo y Logs](#monitoreo-y-logs)
6. [Casos de Uso](#casos-de-uso)
7. [Troubleshooting](#troubleshooting)

---

## Introducción

El sistema de alertas WhatsApp permite enviar notificaciones automatizadas usando templates aprobados por Meta Business. El sistema incluye:

- **Gestión completa desde EasyAdmin**: Templates, destinatarios, grupos y mensajes
- **Tracking de estados**: pending → sent → delivered → read
- **Integración con Prometheus y COT**: Alertas automáticas
- **Webhook de Meta**: Actualización automática de estados
- **API RESTful**: Endpoints para integraciones

### Arquitectura

```
┌──────────────┐     ┌─────────────────┐     ┌──────────────┐
│  Prometheus  │────→│  PrometheusCtrl │────→│   Template   │
│    Alert     │     │   /api/v1/...   │     │   + Grupo    │
└──────────────┘     └─────────────────┘     └───────┬──────┘
                                                      │
┌──────────────┐     ┌─────────────────┐            │
│ COT Sistema  │────→│  CotAlertsCtrl  │────────────┤
│   Espiras    │     │  /api/cot/...   │            │
└──────────────┘     └─────────────────┘            ↓
                                           ┌──────────────────┐
┌──────────────┐     ┌─────────────────┐  │ WhatsAppNotify   │
│  CLI Manual  │────→│  Test Command   │─→│     Service      │
│   Testing    │     │  bin/console    │  └────────┬─────────┘
└──────────────┘     └─────────────────┘           │
                                                    ↓
                                           ┌────────────────┐
                                           │  Meta Cloud    │
                                           │  WhatsApp API  │
                                           └────────┬───────┘
                                                    │
                                                    ↓
                                           ┌────────────────┐
                                           │  Destinatarios │
                                           │   WhatsApp     │
                                           └────────────────┘
```

---

## Gestión de Componentes

### 1. Templates de WhatsApp

Los templates deben crearse y aprobarse primero en **Meta Business Manager**, luego registrarse en el sistema.

#### Crear un Template en Meta

1. Ve a Meta Business Manager → WhatsApp Manager → Message Templates
2. Crea un nuevo template con:
   - Nombre (ejemplo: `server_down_alert`)
   - Idioma (ejemplo: `es`)
   - Categoría (ejemplo: `UTILITY`)
   - Contenido con parámetros: `{{1}}`, `{{2}}`, etc.
3. Espera aprobación de Meta (usualmente 24-48 horas)
4. Anota el **Template Name** exacto

#### Registrar en EasyAdmin

1. Ve a `https://vs.gvops.cl/admin`
2. Navega a **Templates WhatsApp**
3. Click en **Crear Template**
4. Completa:
   - **Nombre**: Nombre descriptivo interno (ejemplo: "Alerta Servidor Caído")
   - **ID Template Meta**: El nombre exacto del template en Meta (ejemplo: `server_down_alert`)
   - **Descripción**: Explicación del propósito
   - **Cantidad de parámetros**: Número de `{{N}}` en el template (0-10)
   - **Idioma**: `es`, `en`, `pt`, etc.
   - **Activo**: ✓ Marcar como activo
   - **Descripción de parámetros** (opcional): JSON explicando cada parámetro
     ```json
     {
       "1": "Nombre del servidor",
       "2": "Timestamp del evento",
       "3": "Mensaje de error",
       "4": "Nivel de severidad"
     }
     ```
5. Guardar

#### Ejemplo de Template Completo

**En Meta Business Manager:**
```
Alerta de Servidor - {{1}}

Servidor: {{2}}
Estado: {{3}}
Detalles: {{4}}

Favor revisar inmediatamente.
```

**En EasyAdmin:**
- Nombre: "Alerta de Servidor"
- Meta Template ID: `server_down_alert`
- Parámetros: 4
- Idioma: es
- Activo: ✓

---

### 2. Destinatarios (Recipients)

#### Crear un Destinatario

1. Ve a **Destinatarios WhatsApp** en EasyAdmin
2. Click en **Crear Destinatario**
3. Completa:
   - **Nombre**: Nombre completo (ejemplo: "Juan Pérez")
   - **Teléfono**: Formato internacional con + (ejemplo: `+56972126016`)
   - **Email** (opcional): Para referencia
   - **Activo**: ✓ Marcar como activo
   - **Grupos**: Selecciona uno o más grupos
   - **Notas** (opcional): Información adicional

#### Formato de Teléfono

- **Correcto**: `+56972126016`, `+56912345678`
- **Incorrecto**: `972126016`, `56972126016`, `+56 9 7212 6016`

**Reglas:**
- Debe empezar con `+` (opcional)
- Solo dígitos después del código de país
- Longitud: 10-15 dígitos
- Sin espacios, guiones ni paréntesis

#### Estados del Destinatario

- **Activo (✓)**: Recibirá mensajes
- **Inactivo (✗)**: No recibirá mensajes (útil para pausar temporalmente)

---

### 3. Grupos de Destinatarios

Los grupos permiten enviar mensajes a múltiples personas de una vez.

#### Crear un Grupo

1. Ve a **Grupos de Destinatarios** en EasyAdmin
2. Click en **Crear Grupo**
3. Completa:
   - **Nombre**: Nombre descriptivo (ejemplo: "Equipo de Infraestructura")
   - **Slug**: Identificador único en minúsculas (ejemplo: `infra_team`)
   - **Descripción**: Propósito del grupo
   - **Activo**: ✓ Marcar como activo
   - **Destinatarios**: Selecciona uno o más destinatarios

#### Reglas de Slug

- Solo letras minúsculas, números y guión bajo (`_`)
- Sin espacios, acentos ni caracteres especiales
- Único en todo el sistema
- Ejemplos válidos: `prometheus_alerts`, `cot_team`, `management_staff`
- Ejemplos inválidos: `Prometheus Alerts`, `cot-team`, `equipo-técnico`

#### Grupos del Sistema

Grupos preconfigurados para integraciones:

1. **`prometheus_alerts`**
   - Propósito: Alertas de Prometheus
   - Usado por: `/api/v1/prometheus/webhook`
   - Template: `prometheus_alert_firing`

2. **`spire_alerts`**
   - Propósito: Alertas de espiras COT
   - Usado por: `/api/cot/spire_general_alert`
   - Template: `card_transaction_alert_1`

---

### 4. Mensajes (Solo Lectura)

La sección **Mensajes WhatsApp** muestra el historial completo de mensajes enviados.

#### Información Visible

- **ID**: Identificador único del mensaje
- **Destinatario**: Nombre y teléfono
- **Template**: Template usado
- **Estado**: Badge de color según estado
- **Fecha**: Timestamp de creación
- **Context**: Contexto adicional (ejemplo: `prometheus_alert:HighCPU`)
- **Meta Message ID**: ID de Meta para tracking
- **Parámetros**: Valores enviados al template
- **Texto**: Preview del mensaje enviado

#### Estados de Mensaje

| Estado | Badge | Significado |
|--------|-------|-------------|
| `pending` | Gris | Esperando envío |
| `sent` | Azul | Enviado a Meta API |
| `delivered` | Verde | Entregado al teléfono |
| `read` | Azul oscuro | Leído por el usuario |
| `failed` | Rojo | Error en el envío |

#### Filtros Disponibles

- Por **Estado**: Ver solo mensajes con estado específico
- Por **Destinatario**: Ver mensajes de una persona
- Por **Template**: Ver mensajes de un template
- Por **Context**: Buscar por contexto
- Por **Fecha**: Rango de fechas

---

## Pruebas y Testing

### Comando de Prueba: Prometheus Alert

El comando simula una alerta de Prometheus y envía un mensaje real.

#### Uso Básico

```bash
php bin/console app:test-whatsapp-prometheus
```

#### Con Parámetros Personalizados

```bash
php bin/console app:test-whatsapp-prometheus \
  --alert-name="HighMemoryUsage" \
  --severity="critical" \
  --summary="Memoria por encima del 90% en servidor principal" \
  --instance="prod-web-01"
```

#### Opciones Disponibles

| Opción | Descripción | Default |
|--------|-------------|---------|
| `--alert-name` | Nombre de la alerta | `TestAlert` |
| `--severity` | Nivel de severidad | `critical` |
| `--summary` | Descripción de la alerta | `Prueba de alerta WhatsApp...` |
| `--instance` | Servidor/instancia afectada | `test-server-01` |

#### Flujo del Comando

1. **Verificación**: Valida template y grupo
2. **Listado**: Muestra destinatarios activos
3. **Confirmación**: Solicita confirmación antes de enviar
4. **Envío**: Envía mensaje con sleep de 1s entre destinatarios
5. **Resultado**: Muestra tabla con mensajes creados

#### Ejemplo de Salida

```
Prueba de envío de alerta WhatsApp (Prometheus)
===============================================

Paso 1: Verificando configuración
---------------------------------

 [OK] ✓ Template encontrado: Alerta Prometheus Firing

  Meta Template ID: prometheus_alert_firing
  Parámetros requeridos: 4
  Activo: Sí

 [OK] ✓ Grupo encontrado: Alertas Prometheus

  Slug: prometheus_alerts
  Activo: Sí
  Destinatarios activos: 2
 * Jonathan Nacaratto (+56972126016)
 * María González (+56987654321)

Paso 2: Preparando mensaje
--------------------------

 ------------------------- ---------------------------------------------
  Parámetro 1 (alertname)   HighMemoryUsage
  Parámetro 2 (severity)    critical
  Parámetro 3 (summary)     Memoria por encima del 90%...
  Parámetro 4 (instance)    prod-web-01
 ------------------------- ---------------------------------------------

¿Enviar mensaje de prueba a 2 destinatario(s)? (yes/no) [yes]:
> yes

Paso 3: Enviando mensaje
------------------------

Esto puede tomar unos segundos (sleep de 1s entre destinatarios)...

 [OK] ✓ Mensajes enviados exitosamente en 3.2 segundos

 ---- -------------------- -------------- -------- ------------------------------------------------------------
  ID   Destinatario         Teléfono       Estado   Meta Message ID
 ---- -------------------- -------------- -------- ------------------------------------------------------------
  15   Jonathan Nacaratto   +56972126016   sent     wamid.HBgLNTY5NzIxMjYwMTYVAgARGBI...
  16   María González       +56987654321   sent     wamid.HBgLNTY5ODc2NTQzMjEVAgASGBI...
 ---- -------------------- -------------- -------- ------------------------------------------------------------

Próximos pasos
--------------

 * Verifica que recibiste el mensaje en WhatsApp
 * Revisa el estado en EasyAdmin: /admin → Mensajes WhatsApp
 * Si configuraste el webhook correctamente, el estado se actualizará a "delivered" automáticamente
```

---

## Integración con Sistemas

### 1. Integración con Prometheus

#### Configuración en Prometheus

Edita `alertmanager.yml`:

```yaml
route:
  receiver: 'whatsapp-critical'
  routes:
    - match:
        severity: critical
      receiver: 'whatsapp-critical'

receivers:
  - name: 'whatsapp-critical'
    webhook_configs:
      - url: 'https://vs.gvops.cl/api/v1/prometheus/webhook'
        send_resolved: false
```

#### Payload Esperado

```json
{
  "receiver": "whatsapp-critical",
  "status": "firing",
  "alerts": [
    {
      "status": "firing",
      "labels": {
        "alertname": "HighCPU",
        "severity": "critical",
        "instance": "server-01",
        "job": "node-exporter"
      },
      "annotations": {
        "summary": "CPU usage above 90% for 5 minutes",
        "description": "CPU usage is critically high"
      }
    }
  ]
}
```

#### Mapeo de Parámetros

El sistema extrae automáticamente:

| Parámetro | Fuente | Fallback |
|-----------|--------|----------|
| 1 | `labels.alertname` | "Nombre de Alerta no disponible" |
| 2 | `labels.severity` | "Severidad no especificada" |
| 3 | `annotations.summary` | "Sin resumen" |
| 4 | `labels.instance` | `labels.job` \|\| "Dispositivo no especificado" |

#### Filtros Aplicados

Solo se envían alertas que cumplan **AMBAS** condiciones:
- `status` = `"firing"` (no resolved)
- `labels.severity` = `"critical"` (no warning, no info)

#### Prueba con curl

```bash
curl -X POST https://vs.gvops.cl/api/v1/prometheus/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "alerts": [{
      "status": "firing",
      "labels": {
        "alertname": "TestAlert",
        "severity": "critical",
        "instance": "test-server"
      },
      "annotations": {
        "summary": "Prueba desde curl"
      }
    }]
  }'
```

---

### 2. Integración con Sistema COT (Espiras)

#### Endpoint

```
POST/GET https://vs.gvops.cl/api/cot/spire_general_alert
Authorization: Bearer b6ef5d2419cd945cfb7b70e67c976b5302e3d92ac0c565ad191d9f6ecdf34362
```

#### Lógica del Sistema

1. Analiza datos de espiras desde las 00:00 del día actual
2. Identifica espiras con pérdida de datos >= 3%
3. Calcula hash de dispositivos afectados
4. Compara con hash anterior (cache de 24h)
5. Solo envía si cambió la lista de dispositivos afectados
6. Limita a 3 dispositivos en el mensaje

#### Ejemplo de Alerta COT

**Texto enviado:**
```
Espiras con pérdida de datos detectadas:

Período: 00:00 a 14:30 del 05-11-2025

- Espira KM 45+200 con 15%
- Espira KM 52+800 con 8%
- Espira KM 60+100 con 5%

Favor revisar el sistema.
```

#### Configuración en Cron

Para ejecución automática cada 30 minutos:

```bash
# /etc/crontab
*/30 * * * * curl -X POST "https://vs.gvops.cl/api/cot/spire_general_alert" \
  -H "Authorization: Bearer b6ef5d2419cd945cfb7b70e67c976b5302e3d92ac0c565ad191d9f6ecdf34362"
```

#### Respuestas del Endpoint

**Sin espiras con problemas:**
```json
{
  "status": "ok",
  "message": "Sin espiras con pérdida de datos",
  "umbral": 3,
  "periodo": "00:00 a 14:30 del 05-11-2025"
}
```

**Sin cambios (deduplicación):**
```json
{
  "status": "sin cambios",
  "message": "Los dispositivos afectados no han cambiado desde la última alerta",
  "dispositivos_count": 5
}
```

**Alertas enviadas:**
```json
{
  "status": "ok",
  "message": "Alertas enviadas: 5 espiras con pérdida >= 3%",
  "dispositivos_afectados": 5,
  "mensajes_enviados": 2,
  "periodo": "00:00 a 14:30 del 05-11-2025",
  "hash": "a1b2c3d4e5f6..."
}
```

---

### 3. Crear Nueva Integración

Para integrar tu propio sistema, sigue estos pasos:

#### Paso 1: Crear Template en Meta

Diseña el mensaje que quieres enviar con parámetros dinámicos.

#### Paso 2: Registrar en EasyAdmin

Crea el template y grupo de destinatarios en el panel de administración.

#### Paso 3: Usar el Servicio

```php
use App\Service\WhatsAppNotificationService;
use App\Repository\WhatsApp\TemplateRepository;
use App\Repository\WhatsApp\RecipientGroupRepository;

class MiController extends AbstractController
{
    public function __construct(
        private WhatsAppNotificationService $whatsAppService,
        private TemplateRepository $templateRepo,
        private RecipientGroupRepository $groupRepo
    ) {}

    public function enviarAlerta(): Response
    {
        // Buscar template y grupo
        $template = $this->templateRepo->findOneBy([
            'metaTemplateId' => 'mi_template'
        ]);

        $group = $this->groupRepo->findOneBy([
            'slug' => 'mi_grupo'
        ]);

        // Preparar parámetros (deben coincidir con parametrosCount)
        $parameters = [
            'Valor del parámetro 1',
            'Valor del parámetro 2',
            // ... hasta parametrosCount
        ];

        try {
            // Enviar mensajes
            $messages = $this->whatsAppService->sendTemplateMessage(
                $template,
                $parameters,
                $group,
                'mi_contexto_opcional'
            );

            return $this->json([
                'success' => true,
                'messages_sent' => count($messages)
            ]);

        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
```

#### Paso 4: Configurar Ruta

```yaml
# config/routes.yaml
app_mi_alerta:
    path: /api/mi-sistema/alertas
    controller: App\Controller\MiController::enviarAlerta
    methods: [POST]
```

---

## Monitoreo y Logs

### Ver Mensajes en EasyAdmin

1. Ve a `https://vs.gvops.cl/admin`
2. Navega a **Mensajes WhatsApp**
3. Usa los filtros para buscar:
   - Por estado
   - Por destinatario
   - Por template
   - Por rango de fechas

### Verificar Estado de un Mensaje

1. Busca el mensaje por ID o destinatario
2. Verifica el badge de estado:
   - Verde (delivered) = Mensaje entregado
   - Azul oscuro (read) = Mensaje leído
   - Rojo (failed) = Error en envío
3. Revisa los timestamps:
   - `created_at`: Cuando se creó en DB
   - `sent_at`: Cuando se envió a Meta API
   - `delivered_at`: Cuando se entregó al teléfono
   - `read_at`: Cuando lo leyó el usuario

### Calcular Tiempo de Entrega

El sistema calcula automáticamente el tiempo entre `sent_at` y `delivered_at`.

En el detalle del mensaje, verás:
```
Tiempo de entrega: 2 segundos
```

### Logs del Sistema

Los logs se almacenan en diferentes ubicaciones según el entorno:

**Producción:**
```bash
tail -f var/log/prod.log | grep -i whatsapp
```

**Development:**
```bash
tail -f var/log/dev.log | grep -i whatsapp
```

**Nginx (peticiones HTTP):**
```bash
tail -f /var/log/nginx/access.log | grep webhook
```

### Eventos en Logs

```
[INFO] Enviando template "prometheus_alert_firing" a 2 destinatarios del grupo "Alertas Prometheus"
[INFO] Mensaje enviado exitosamente a +56972126016 (Meta ID: wamid.xxx)
[INFO] WhatsApp webhook notification received
[INFO] WhatsApp status update: wamid.xxx → delivered
[INFO] Mensaje wamid.xxx actualizado a estado: delivered
```

---

## Casos de Uso

### Caso 1: Alertas de Servidor Caído

**Objetivo:** Notificar cuando un servidor deja de responder

**Setup:**
1. Crear template `server_down` con 3 parámetros:
   - Nombre del servidor
   - Timestamp
   - Último estado conocido

2. Crear grupo `ops_team` con el equipo de operaciones

3. Configurar monitoreo que llame:
```bash
curl -X POST https://vs.gvops.cl/api/mi-sistema/server-down \
  -d '{"server":"web-01", "timestamp":"2025-11-05 14:30", "last_status":"timeout"}'
```

---

### Caso 2: Alertas de Backup Fallido

**Objetivo:** Notificar cuando falla un backup programado

**Setup:**
1. Template `backup_failed` con 4 parámetros:
   - Nombre del backup
   - Fecha programada
   - Error
   - Servidor

2. Grupo `backup_admins`

3. Script de backup que al fallar ejecute:
```bash
php bin/console app:send-backup-alert \
  --name="DB_PROD" \
  --date="2025-11-05" \
  --error="Disk full" \
  --server="backup-01"
```

---

### Caso 3: Confirmación de Mantenimiento

**Objetivo:** Notificar cuando se completa un mantenimiento

**Setup:**
1. Template `maintenance_complete` con 3 parámetros:
   - Sistema
   - Duración
   - Estado final

2. Grupo `stakeholders` con gerentes y coordinadores

3. Al finalizar mantenimiento:
```php
$this->whatsAppService->sendTemplateMessage(
    $maintenanceTemplate,
    ['Sistema de Facturación', '45 minutos', 'Exitoso'],
    $stakeholdersGroup,
    'maintenance_2025_11_05'
);
```

---

## Troubleshooting

### Problema: Mensaje no llega a WhatsApp

**Verificar:**

1. **Estado en EasyAdmin:**
   - Si está en `pending`: El servicio no intentó enviar
   - Si está en `sent`: Meta recibió pero puede haber problema
   - Si está en `failed`: Ver error_message

2. **Destinatario activo:**
   ```sql
   SELECT * FROM whatsapp_recipients WHERE activo = 1 AND telefono = '+56972126016';
   ```

3. **Formato de teléfono:**
   - Debe ser internacional con +
   - Sin espacios ni caracteres especiales

4. **Token de Meta válido:**
   ```bash
   curl "https://graph.facebook.com/v22.0/651420641396348?access_token=TU_TOKEN"
   ```

5. **Template aprobado en Meta:**
   - Ve a Meta Business Manager → WhatsApp → Message Templates
   - Verifica que esté en estado "Approved"

---

### Problema: Estados no se actualizan

**Causa:** Webhook no configurado o no funciona

**Solución:**

1. **Verificar configuración del webhook:**
   ```bash
   curl "https://vs.gvops.cl/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=TOKEN&hub.challenge=TEST"
   ```
   Debe responder: `TEST`

2. **Verificar suscripción a eventos en Meta:**
   - Ve a Meta Developer Console → WhatsApp → Configuration
   - Verifica que estés suscrito a `message_status`

3. **Probar webhook manualmente:**
   ```bash
   curl -X POST https://vs.gvops.cl/api/whatsapp/webhook \
     -H "Content-Type: application/json" \
     -d '{
       "object": "whatsapp_business_account",
       "entry": [{
         "changes": [{
           "value": {
             "statuses": [{
               "id": "REEMPLAZAR_CON_META_MESSAGE_ID",
               "status": "delivered"
             }]
           }
         }]
       }]
     }'
   ```

---

### Problema: Error "Template not configured"

**Causa:** El template no existe en la base de datos o el metaTemplateId no coincide

**Solución:**

1. Verificar en EasyAdmin:
   - Ve a Templates WhatsApp
   - Busca el template por Meta Template ID
   - Verifica que esté marcado como Activo

2. Verificar en base de datos:
   ```sql
   SELECT * FROM whatsapp_templates WHERE meta_template_id = 'prometheus_alert_firing';
   ```

3. Si no existe, créalo en EasyAdmin con el ID exacto de Meta

---

### Problema: Error "Recipient group not configured"

**Causa:** El grupo no existe o el slug es incorrecto

**Solución:**

1. Verificar en EasyAdmin:
   - Ve a Grupos de Destinatarios
   - Busca el grupo por slug
   - Verifica que esté marcado como Activo
   - Verifica que tenga destinatarios activos

2. Verificar en base de datos:
   ```sql
   SELECT g.*, COUNT(r.id) as recipients_count
   FROM whatsapp_recipient_groups g
   LEFT JOIN whatsapp_group_recipients gr ON g.id = gr.group_id
   LEFT JOIN whatsapp_recipients r ON gr.recipient_id = r.id AND r.activo = 1
   WHERE g.slug = 'prometheus_alerts'
   GROUP BY g.id;
   ```

---

### Problema: Error "Meta API error (HTTP 400)"

**Causas comunes:**

1. **Template no existe o no está aprobado en Meta**
   - Verifica en Meta Business Manager

2. **Número de teléfono no registrado en WhatsApp**
   - El número debe tener WhatsApp activo

3. **Mismatch de parámetros**
   - Número de parámetros no coincide con el template

4. **Token expirado**
   - Genera un nuevo token en Meta Developer Console

---

### Problema: "The template requires X parameters, received Y"

**Causa:** El número de parámetros enviados no coincide con `parametrosCount`

**Solución:**

1. Verificar el template en EasyAdmin:
   ```
   Template: prometheus_alert_firing
   Parámetros requeridos: 4
   ```

2. Asegurarte de enviar exactamente 4 parámetros:
   ```php
   $parameters = [
       'param1',
       'param2',
       'param3',
       'param4'  // <- Exactamente 4
   ];
   ```

3. Si el template cambió en Meta, actualiza `parametrosCount` en EasyAdmin

---

## Comandos Útiles

### Enviar mensaje de prueba
```bash
php bin/console app:test-whatsapp-prometheus
```

### Ver todos los templates
```bash
php bin/console doctrine:query:sql "SELECT id, nombre, meta_template_id, activo FROM whatsapp_templates"
```

### Ver todos los grupos
```bash
php bin/console doctrine:query:sql "SELECT id, nombre, slug, activo FROM whatsapp_recipient_groups"
```

### Ver destinatarios de un grupo
```bash
php bin/console doctrine:query:sql "
SELECT r.nombre, r.telefono, r.activo
FROM whatsapp_recipients r
JOIN whatsapp_group_recipients gr ON r.id = gr.recipient_id
JOIN whatsapp_recipient_groups g ON gr.group_id = g.id
WHERE g.slug = 'prometheus_alerts'
"
```

### Ver últimos 10 mensajes
```bash
php bin/console doctrine:query:sql "
SELECT m.id, r.nombre, m.estado, m.created_at, m.meta_message_id
FROM whatsapp_messages m
JOIN whatsapp_recipients r ON m.recipient_id = r.id
ORDER BY m.created_at DESC
LIMIT 10
"
```

### Limpiar cache (si hay problemas)
```bash
php bin/console cache:clear
```

---

## Mantenimiento

### Limpieza de Mensajes Antiguos

Para mantener la base de datos ligera, puedes eliminar mensajes antiguos:

```sql
-- Mensajes entregados de más de 90 días
DELETE FROM whatsapp_messages
WHERE estado IN ('delivered', 'read')
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Monitoreo de Salud del Sistema

```sql
-- Resumen de mensajes por estado
SELECT estado, COUNT(*) as total
FROM whatsapp_messages
WHERE created_at >= CURDATE()
GROUP BY estado;

-- Tasa de éxito del día
SELECT
    SUM(CASE WHEN estado IN ('sent', 'delivered', 'read') THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as tasa_exito
FROM whatsapp_messages
WHERE created_at >= CURDATE();

-- Mensajes fallidos hoy
SELECT m.id, r.nombre, r.telefono, m.error_message, m.created_at
FROM whatsapp_messages m
JOIN whatsapp_recipients r ON m.recipient_id = r.id
WHERE m.estado = 'failed'
AND m.created_at >= CURDATE()
ORDER BY m.created_at DESC;
```

---

## Límites y Consideraciones

### Límites de Meta WhatsApp API

- **Límite de rate (mensajes por segundo):**
  - Tier 1: 80 mensajes/segundo
  - Tier 2: 1000 mensajes/segundo
  - El sistema tiene sleep de 1s entre mensajes para evitar límites

- **Ventana de 24 horas:**
  - Solo puedes iniciar conversación con templates aprobados
  - Después de que el usuario responda, tienes 24h para enviar mensajes libres

- **Quality Rating:**
  - Meta monitorea la calidad de tus mensajes
  - Demasiados reportes como spam = límites o suspensión

### Mejores Prácticas

1. **No enviar spam**: Solo mensajes relevantes y necesarios
2. **Templates claros**: Mensajes concisos y con valor
3. **Opt-out**: Permitir que usuarios se den de baja
4. **Monitoreo**: Revisar tasas de entrega y lectura
5. **Testing**: Probar siempre antes de producción

---

## Soporte y Contacto

- **Documentación de Meta**: https://developers.facebook.com/docs/whatsapp
- **Panel de Administración**: https://vs.gvops.cl/admin
- **Logs del sistema**: `var/log/prod.log`

---

**Última actualización:** 2025-11-05
**Versión:** 1.0
