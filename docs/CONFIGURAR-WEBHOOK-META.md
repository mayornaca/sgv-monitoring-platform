# Configurar Webhook en Meta Business Manager - Guía Rápida

## ✅ Estado Actual

- **Mensaje de prueba**: ✅ Enviado exitosamente
- **Webhook endpoint**: ✅ Funcionando correctamente
- **Verificación**: ✅ Validada con curl
- **Destinatario**: Jonathan Nacaratto (+56972126016)
- **Meta Message ID**: `wamid.HBgLNTY5NzIxMjYwMTYVAgARGBJGREEyNTFBMTVFRTMyMTg0NzQA`

## 🎯 Próximos Pasos

### 1. Configurar Webhook en Meta

1. Ve a: https://developers.facebook.com/apps
2. Selecciona tu aplicación de WhatsApp Business
3. En el menú lateral: **WhatsApp** → **Configuration**
4. Sección **Webhook**, ingresa:

   **URL de devolución de llamada:**
   ```
   https://vs.gvops.cl/api/whatsapp/webhook
   ```

   **Identificador de verificación:**
   ```
   XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78
   ```

5. Click en **"Verificar y guardar"** → Debe aparecer ✅ verificado

### 2. Suscribirse a Eventos

1. En la misma página, sección **Webhook fields**
2. Click en **"Manage"** o **"Subscribe"**
3. Activar los siguientes eventos:
   - ✅ **messages** (mensajes entrantes)
   - ✅ **message_status** (actualizaciones de estado)
4. Click en **"Save"**

### 3. Verificar que Funciona

#### Opción A: Ver en EasyAdmin

1. Ve a: https://vs.gvops.cl/admin
2. Navega a: **Mensajes WhatsApp**
3. Busca el mensaje con ID `7` (enviado hace unos minutos)
4. El estado debería cambiar automáticamente:
   - `sent` → `delivered` (cuando llegue a tu teléfono)
   - `delivered` → `read` (cuando lo leas)

#### Opción B: Enviar otro mensaje de prueba

```bash
php bin/console app:test-whatsapp-prometheus \
  --alert-name="PruebaDespuesDeWebhook" \
  --summary="Verificando que el webhook actualiza estados"
```

Luego revisa en EasyAdmin que el estado cambie automáticamente.

## 📋 Información de Referencia

### Credenciales del Sistema

```bash
# .env.prod
WHATSAPP_DSN=meta-whatsapp://TOKEN@default?phone_number_id=651420641396348
WHATSAPP_WEBHOOK_VERIFY_TOKEN=XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78
```

### URLs del Sistema

```
Webhook:     https://vs.gvops.cl/api/whatsapp/webhook
EasyAdmin:   https://vs.gvops.cl/admin
Prometheus:  https://vs.gvops.cl/api/v1/prometheus/webhook
COT Alerts:  https://vs.gvops.cl/api/cot/spire_general_alert
```

### Comandos Útiles

```bash
# Enviar mensaje de prueba
php bin/console app:test-whatsapp-prometheus

# Verificar webhook
curl "https://vs.gvops.cl/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78&hub.challenge=TEST"

# Ver mensajes en base de datos
php bin/console doctrine:query:sql "SELECT id, estado, created_at FROM whatsapp_messages ORDER BY created_at DESC LIMIT 5"
```

## 📚 Documentación Completa

Para más detalles, consulta:
- **Configuración del webhook**: `docs/whatsapp-webhook-setup.md`
- **Guía de uso completa**: `docs/whatsapp-usage-guide.md`

## ⚠️ Notas Importantes

1. **Verificación solo funciona una vez**: Una vez que Meta verifique el webhook, no necesitas verificarlo de nuevo a menos que cambies la URL o el token.

2. **Los eventos llegan en tiempo real**: Después de suscribirte, Meta enviará notificaciones inmediatamente cuando cambien los estados.

3. **Firewall**: Asegúrate de que tu servidor permita peticiones desde los rangos de IP de Meta:
   - `173.252.64.0/18`
   - `185.60.216.0/22`

4. **SSL válido**: Meta requiere HTTPS con certificado válido (✅ ya lo tienes con Let's Encrypt).

## 🐛 Troubleshooting

### Si la verificación falla

```bash
# Probar manualmente el endpoint
curl -v "https://vs.gvops.cl/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=XUuGqNJSWn2SIu3UUTdXVaOakOLKUZVj4oSdQGO0vD9QFWlcwWyGYagyIyWTT78&hub.challenge=TEST123"

# Debería responder: TEST123 con HTTP 200
```

### Si los estados no se actualizan

1. Verifica que estés suscrito a `message_status` en Meta
2. Revisa los logs: `tail -f /var/log/nginx/access.log | grep webhook`
3. Envía un evento de prueba manual (ver documentación completa)

## ✅ Checklist Final

- [ ] Webhook configurado en Meta Business Manager
- [ ] Verificación exitosa (✅ aparece en Meta)
- [ ] Suscrito a eventos `messages` y `message_status`
- [ ] Enviado mensaje de prueba post-configuración
- [ ] Verificado que el estado cambia en EasyAdmin
- [ ] Documentado cualquier problema encontrado

---

**Fecha**: 2025-11-05
**Sistema**: VS WhatsApp Integration v1.0
**Phone Number ID**: 651420641396348
