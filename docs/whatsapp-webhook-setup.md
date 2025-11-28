# Configuración del Webhook de WhatsApp en Meta Business Manager

## Información General

El webhook permite que Meta notifique a tu aplicación sobre eventos en tiempo real:
- **Cambios de estado de mensajes**: sent → delivered → read
- **Mensajes entrantes**: cuando alguien responde a un mensaje
- **Errores**: cuando falla el envío

## Configuración Actual del Sistema

### Variables de Entorno (.env.prod)

```bash
WHATSAPP_DSN=meta-whatsapp://TOKEN@default?phone_number_id=651420641396348
WHATSAPP_WEBHOOK_VERIFY_TOKEN=XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78
```

### Endpoint del Webhook

```
URL: https://vs.gvops.cl/api/whatsapp/webhook
Método: GET (verificación), POST (eventos)
```

## Paso a Paso: Configurar en Meta Business Manager

### 1. Acceder a Meta Developer Console

1. Ve a: https://developers.facebook.com/apps
2. Selecciona tu aplicación (VS - WhatsApp Business)
3. En el menú lateral, selecciona **WhatsApp** → **Configuration**

### 2. Configurar el Webhook

En la sección **Webhook**, encontrarás dos campos:

#### Campo 1: Callback URL (URL de devolución de llamada)
```
https://vs.gvops.cl/api/whatsapp/webhook
```

#### Campo 2: Verify Token (Identificador de verificación)
```
XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78
```

3. Haz clic en **"Verificar y guardar"** (Verify and Save)

### 3. Qué Sucede Durante la Verificación

Meta realiza una petición GET a tu webhook:

```http
GET https://vs.gvops.cl/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78&hub.challenge=1234567890
```

Tu aplicación debe:
1. Validar que `hub.mode` = "subscribe"
2. Validar que `hub.verify_token` coincida con el configurado
3. Responder con el valor de `hub.challenge`

**Nota importante sobre PHP:** PHP convierte automáticamente los puntos en los query params a guiones bajos:
- `hub.mode` → `hub_mode`
- `hub.verify_token` → `hub_verify_token`
- `hub.challenge` → `hub_challenge`

El código ya está preparado para manejar esto correctamente.

### 4. Suscribirse a Eventos

Una vez verificado el webhook, debes suscribirte a los eventos:

1. En la misma página de **Webhook**, busca la sección **Webhook fields**
2. Haz clic en **"Manage"** o **"Subscribe"**
3. Selecciona los siguientes campos:

#### Eventos Recomendados:

- **messages**: Para recibir mensajes entrantes
- **message_status**: Para recibir actualizaciones de estado de mensajes enviados

#### Eventos Opcionales:

- **message_template_status_update**: Para saber cuando aprueban/rechazan templates
- **account_alerts**: Para alertas de la cuenta
- **phone_number_quality_update**: Para monitoreo de calidad

4. Haz clic en **"Save"**

### 5. Verificar que Funciona

#### Opción A: Enviar mensaje de prueba

```bash
php bin/console app:test-whatsapp-prometheus
```

Luego:
1. Verifica que recibes el mensaje en WhatsApp
2. Ve a EasyAdmin → Mensajes WhatsApp
3. El estado debería cambiar de "sent" a "delivered" automáticamente
4. Si lees el mensaje, cambiará a "read"

#### Opción B: Revisar logs

```bash
tail -f var/log/prod.log | grep -i whatsapp
```

Deberías ver entradas como:
```
[INFO] WhatsApp webhook notification received
[INFO] WhatsApp status update: wamid.xxx → delivered
[INFO] Mensaje xxx actualizado a estado: delivered
```

## Eventos que Recibirás

### 1. Message Status Update

Cuando cambias el estado de un mensaje:

```json
{
  "object": "whatsapp_business_account",
  "entry": [{
    "changes": [{
      "value": {
        "messaging_product": "whatsapp",
        "statuses": [{
          "id": "wamid.HBgLNTY5NzIxMjYwMTYVAgARGBI...",
          "status": "delivered",
          "timestamp": "1234567890",
          "recipient_id": "56972126016"
        }]
      }
    }]
  }]
}
```

Estados posibles:
- `sent`: Mensaje enviado a Meta
- `delivered`: Mensaje entregado al teléfono
- `read`: Mensaje leído por el usuario
- `failed`: Fallo en el envío

### 2. Incoming Message

Cuando alguien responde:

```json
{
  "object": "whatsapp_business_account",
  "entry": [{
    "changes": [{
      "value": {
        "messaging_product": "whatsapp",
        "messages": [{
          "from": "56972126016",
          "id": "wamid.xxx",
          "timestamp": "1234567890",
          "type": "text",
          "text": {
            "body": "Hola, recibí la alerta"
          }
        }]
      }
    }]
  }]
}
```

## Arquitectura del Sistema

```
┌─────────────────┐
│   Meta Cloud    │
│  WhatsApp API   │
└────────┬────────┘
         │ 1. Envías mensaje
         │    via API
         ↓
┌─────────────────────────────┐
│  graph.facebook.com/v22.0   │
│  /651420641396348/messages  │
└─────────────┬───────────────┘
              │ 2. Procesa y envía
              ↓
         ┌────────┐
         │WhatsApp│
         │  App   │
         └────┬───┘
              │ 3. Usuario recibe
              │
         ┌────↓───────────────────────┐
         │ Meta detecta:              │
         │ - delivered                │
         │ - read                     │
         │ - incoming message         │
         └────┬───────────────────────┘
              │ 4. Webhook POST
              ↓
┌────────────────────────────────────┐
│ https://vs.gvops.cl                │
│ /api/whatsapp/webhook              │
│                                    │
│ WhatsAppWebhookController          │
│     ↓                              │
│ WhatsAppWebhookService             │
│     ↓                              │
│ WhatsAppMessageStatusSubscriber    │
│     ↓                              │
│ Actualiza estado en DB             │
└────────────────────────────────────┘
```

## Troubleshooting

### Error: "Failed to verify webhook"

**Causas posibles:**
1. URL incorrecta
2. Token de verificación incorrecto
3. Servidor no responde (timeout)
4. Certificado SSL inválido
5. Firewall bloqueando peticiones de Meta

**Solución:**
```bash
# Probar manualmente la verificación:
curl -v "https://vs.gvops.cl/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78&hub.challenge=TEST123"

# Debería responder: TEST123
```

### Los mensajes no cambian de estado

**Causas posibles:**
1. Webhook no configurado o eventos no suscritos
2. Meta no puede alcanzar tu servidor
3. Error en el procesamiento del webhook

**Solución:**
```bash
# Revisar logs:
tail -f var/log/prod.log | grep -i webhook

# Verificar que lleguen peticiones:
tail -f /var/log/nginx/access.log | grep webhook

# Test manual:
curl -X POST https://vs.gvops.cl/api/whatsapp/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "object": "whatsapp_business_account",
    "entry": [{
      "changes": [{
        "value": {
          "statuses": [{
            "id": "wamid.HBgLNTY5NzIxMjYwMTYVAgARGBJGREEyNTFBMTVFRTMyMTg0NzQA",
            "status": "delivered"
          }]
        }
      }]
    }]
  }'
```

### Los logs muestran "No se encontró mensaje con Meta ID"

Esto es normal si:
- Estás probando con mensajes antiguos
- El mensaje fue enviado antes de implementar el sistema de tracking
- Usaste otro sistema para enviar

### Rangos de IP de Meta

Meta envía webhooks desde estos rangos:
```
173.252.64.0/18
185.60.216.0/22
2a03:2880::/32
2620:0:1c00::/40
```

Asegúrate de que tu firewall permita estas IPs.

## Comandos Útiles

### Enviar mensaje de prueba
```bash
php bin/console app:test-whatsapp-prometheus
```

### Ver mensajes en EasyAdmin
```
URL: https://vs.gvops.cl/admin
Menú: Mensajes WhatsApp
```

### Ver logs en tiempo real
```bash
tail -f var/log/prod.log | grep -i whatsapp
```

### Verificar configuración
```bash
grep WHATSAPP .env.prod
```

### Test de conectividad con Meta API
```bash
curl -X GET "https://graph.facebook.com/v22.0/651420641396348?access_token=TU_TOKEN"
```

## Referencias

- **Meta Developer Docs**: https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks
- **Webhook Reference**: https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components
- **Status Updates**: https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/components#statuses-object

## Contacto y Soporte

Si tienes problemas:
1. Revisa los logs: `var/log/prod.log`
2. Verifica la configuración en EasyAdmin
3. Prueba con el comando de test
4. Consulta la documentación de Meta

---

**Última actualización:** 2025-11-05
**Versión del sistema:** 1.0
**Phone Number ID:** 651420641396348
