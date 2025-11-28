# Change: Migrar Sistema de Notificaciones Real-Time SOS a Firebase v11 + Mercure

## Why

El sistema legacy de notificaciones push para alarmas de sensores SOS está completamente NO FUNCIONAL en el proyecto actual:

**Problemas Críticos**:
- Firebase SDK v7.14.3 (2020) deprecado y con vulnerabilidades
- Service workers NO existen en el proyecto nuevo
- API endpoints `/api/send_push_notification` y `/api/register_service` NO migrados
- Servicio externo de hardware NO puede notificar alarmas
- Sistema actual usa AJAX polling ineficiente (alta latencia, desperdicio recursos)
- Sin notificaciones browser background
- Sin multi-ventana synchronization

**Impacto Operacional**:
Los operadores NO reciben notificaciones inmediatas cuando se abre una puerta de sensor SOS (extintores, red húmeda), generando:
- Retraso en respuesta a emergencias
- Dependencia de estar en la página de monitoreo activamente
- Pérdida de alarmas críticas si ventana no está abierta
- Carga innecesaria en servidor con polling continuo

**Oportunidad de Mejora**:
Migrar a stack moderno permite no solo restaurar funcionalidad, sino mejorarla con:
- Firebase v11 modular con métodos actualizados
- Mercure SSE para latencia sub-segundo
- Service worker optimizado sin cache estático problemático
- Redundancia (Mercure + Firebase fallback)

## What Changes

### Fase 1: Firebase v11 Moderno (Semanas 1-2)
- Instalar `firebase@^11.0.2` y `workbox-window@^7.3.0` en package.json
- Crear service worker moderno `public/firebase-messaging-sw.js`:
  - Migrar de `setBackgroundMessageHandler()` a `onBackgroundMessage()`
  - Versionado dinámico (no fecha estática)
  - Auto-cleanup de caches antiguos en `activate`
  - Mantener 8 categorías de cache separadas (fonts, images, assets, map-tiles, external, etc.)
  - NetworkOnly para APIs dinámicas
  - CacheFirst para assets estáticos
- Frontend integration con modular API:
  - `import { getMessaging, getToken, onMessage }`
  - Request notification permission
  - Token registration en servidor
  - Foreground message handler
- **CRÍTICO**: API endpoints Symfony:
  - `POST /api/send_push_notification` - Recibe alarmas del servicio externo
  - `POST /api/register_service` - Heartbeat del servicio monitor
  - Bearer token authentication
- Port `FCMServiceManualService.php` a Symfony 6.4:
  - OAuth 2.0 con service account
  - JWT signing con OpenSSL
  - Token caching
  - Batch sending a múltiples tokens

### Fase 2: Mercure SSE Integration (Semana 3)
- Instalar `symfony/mercure-bundle`
- Deploy Mercure Hub (Go binary) en `/opt/mercure`
- Configurar Supervisor para mantener Hub como servicio
- Nginx reverse proxy `/mercure` → `:9090`
- Dual publishing:
  - Mercure SSE para browsers activos (latencia <100ms)
  - Firebase FCM para background notifications
- Frontend EventSource client con auto-reconnect

### Mejoras Service Worker
- Versionado dinámico basado en build date
- `skipWaiting()` + `clients.claim()` para updates inmediatos
- Cleanup automático de caches antiguos por prefijo
- Notification actions: "Ver Detalles", "Cerrar"
- Multi-window postMessage synchronization
- Mantener separación de caches por categoría (óptimo para invalidación selectiva)

## Impact

**Affected Specs**:
- `sos-sensor-monitoring` (NEW - esta funcionalidad existe pero sin spec)

**Affected Code**:
- `package.json` - Agregar firebase, workbox-window
- `public/firebase-messaging-sw.js` - NEW service worker
- `src/Controller/Api/SensorAlarmsController.php` - NEW API endpoints
- `src/Service/FCMService.php` - NEW (port de legacy FCMServiceManual)
- `templates/dashboard/cot/videowall.html.twig` - Agregar Firebase SDK
- `config/packages/mercure.yaml` - NEW Mercure configuration
- `.env` - Agregar MERCURE_URL, MERCURE_JWT_SECRET, FIREBASE_VAPID_KEY
- Nginx config - Agregar location `/mercure`
- Supervisor config - Agregar `mercure.conf`

**Breaking Changes**:
- Ninguno - Es restauración de funcionalidad perdida

**Dependencies**:
- Firebase project "cotalert-a278f" (ya existe)
- Obtener VAPID key de Firebase Console
- Mercure Hub binary v0.16.3
- Supervisor instalado (ya existe en servidor)

**Migration Path**:
1. Fase 1 puede deployarse sin Mercure (Firebase solo)
2. Fase 2 es additive (agrega Mercure sin romper Firebase)
3. Servicio externo no requiere cambios (mismas rutas API)
4. Tokens legacy pueden limpiarse gradualmente (nueva registration)

**Rollback Plan**:
1. Deshabilitar service worker: Eliminar `firebase-messaging-sw.js`
2. Revertir a AJAX polling: Ya existe como fallback
3. API endpoints pueden mantenerse (no afectan si no se usan)
4. Mercure puede detenerse sin afectar Firebase

**Security**:
- Bearer token validation en API endpoints
- CORS configuration para Mercure
- Firebase token validation server-side
- Mercure JWT para authorization
- HTTPS required (ya configurado)

**Performance**:
- Eliminación de AJAX polling reduce load servidor
- Mercure SSE más eficiente que WebSocket para push notifications
- Cache separation permite invalidación selectiva (mejor performance)
- Firebase CDN para SDK (no self-hosted)

**Monitoring**:
- Service worker registration status
- Firebase token count en DB
- Mercure Hub health endpoint
- Supervisor process status
- Notification delivery logs

## Timeline

- **Semana 1**: Firebase v11 + Service Worker + API endpoints
- **Semana 2**: Testing, VAPID keys, integration con servicio externo
- **Semana 3**: Mercure integration + dual publishing
- **Total**: 3 semanas

## Success Criteria

1. Servicio externo puede enviar alarmas via `/api/send_push_notification`
2. Browsers registrados reciben push notifications background
3. Modal de alarma aparece automáticamente en ventanas abiertas
4. Notification actions funcionan (Ver Detalles, Cerrar)
5. Service worker se auto-actualiza sin cache stale
6. Mercure SSE entrega mensajes <100ms latency
7. Fallback a Firebase funciona si Mercure falla
8. Multi-ventana sync con postMessage
9. Zero downtime durante deployment
