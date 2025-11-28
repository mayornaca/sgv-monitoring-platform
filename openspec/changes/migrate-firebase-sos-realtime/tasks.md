# Implementation Tasks: Firebase v11 + Mercure Real-Time Notifications

## Phase 1: Firebase v11 Foundation

### 1. NPM Dependencies
- [ ] 1.1 Instalar `firebase@^11.0.2` en package.json
- [ ] 1.2 Instalar `workbox-window@^7.3.0` en package.json
- [ ] 1.3 Ejecutar `npm install` y verificar instalación
- [ ] 1.4 Commit package.json y package-lock.json

### 2. Service Worker Moderno
- [ ] 2.1 Crear `public/firebase-messaging-sw.js`
- [ ] 2.2 Implementar Firebase compat imports (v11)
- [ ] 2.3 Implementar `firebase.initializeApp()` con config
- [ ] 2.4 Migrar de `setBackgroundMessageHandler()` a `onBackgroundMessage()`
- [ ] 2.5 Implementar versionado dinámico (const VERSION)
- [ ] 2.6 Implementar lifecycle: `install` con `skipWaiting()`
- [ ] 2.7 Implementar lifecycle: `activate` con auto-cleanup de caches antiguos
- [ ] 2.8 Implementar lifecycle: `activate` con `clients.claim()`
- [ ] 2.9 Implementar notification handler con actions (Ver Detalles, Cerrar)
- [ ] 2.10 Implementar `notificationclick` handler con multi-window support
- [ ] 2.11 Implementar fetch handler con estrategias de cache

### 3. Cache Strategies (Mantener 8 Categorías)
- [ ] 3.1 Implementar NetworkOnly para APIs dinámicas (/api/, /status, /siv/, /cot/sos)
- [ ] 3.2 Implementar CacheFirst para fonts (googleapis, gstatic, local fonts)
- [ ] 3.3 Implementar CacheFirst para images (png, jpg, svg, etc)
- [ ] 3.4 Implementar CacheFirst para map tiles (OSM, Mapbox)
- [ ] 3.5 Implementar CacheFirst para assets (CSS, JS)
- [ ] 3.6 Implementar StaleWhileRevalidate para Gravatar
- [ ] 3.7 Crear helper functions: `cacheFirst()`, `staleWhileRevalidate()`
- [ ] 3.8 Definir CACHES object con 8 categorías nombradas

### 4. Firebase Configuration
- [ ] 4.1 Obtener VAPID key de Firebase Console → Cloud Messaging → Web Push certificates
- [ ] 4.2 Agregar `FIREBASE_VAPID_KEY` a `.env`
- [ ] 4.3 Agregar `FIREBASE_PROJECT_ID` a `.env`
- [ ] 4.4 Agregar `FIREBASE_MESSAGING_SENDER_ID` a `.env`
- [ ] 4.5 Agregar `FIREBASE_APP_ID` a `.env`
- [ ] 4.6 Verificar Firebase project "cotalert-a278f" activo

### 5. Backend: FCM Service
- [ ] 5.1 Crear `src/Service/FCMService.php`
- [ ] 5.2 Port método `getAccessToken()` de legacy FCMServiceManualService
- [ ] 5.3 Port método `createJwt()` con OpenSSL signing
- [ ] 5.4 Port método `sendNotification()` con FCM API v1
- [ ] 5.5 Implementar token caching (filesystem o Redis)
- [ ] 5.6 Implementar batch sending (chunks de 100 tokens)
- [ ] 5.7 Implementar error handling y retry logic
- [ ] 5.8 Agregar logging para debugging
- [ ] 5.9 Crear unit tests para FCMService

### 6. Backend: API Endpoints
- [ ] 6.1 Crear `src/Controller/Api/SensorAlarmsController.php`
- [ ] 6.2 Implementar `sendPushNotification()` action
- [ ] 6.3 Validar Bearer token authentication
- [ ] 6.4 Agregar `EXTERNAL_SERVICE_TOKEN` a `.env`
- [ ] 6.5 Query alarm details de PostgreSQL (stored function o query directo)
- [ ] 6.6 Construir notification payload (title, body, data)
- [ ] 6.7 Obtener tokens de `tbl_00_users_tokens` WHERE `reg_status=1`
- [ ] 6.8 Llamar `FCMService::sendToAll()` con payload
- [ ] 6.9 Return JSON response con count enviados
- [ ] 6.10 Implementar `registerService()` action para heartbeat
- [ ] 6.11 Update `servicios` table con status y timestamp
- [ ] 6.12 Agregar rate limiting (opcional, prevenir abuse)
- [ ] 6.13 Agregar request logging para auditoría

### 7. Backend: Routes
- [ ] 7.1 Crear route `POST /api/send_push_notification`
- [ ] 7.2 Crear route `POST /api/register_service`
- [ ] 7.3 Verificar routes con `php bin/console debug:router | grep api`
- [ ] 7.4 Agregar CORS headers si necesario

### 8. Frontend: Firebase Integration
- [ ] 8.1 Agregar Firebase SDK v11 modular a template base
- [ ] 8.2 Implementar `initializeApp()` con config
- [ ] 8.3 Implementar `getMessaging()`
- [ ] 8.4 Implementar `Notification.requestPermission()`
- [ ] 8.5 Implementar `getToken()` con VAPID key
- [ ] 8.6 Implementar AJAX call a `/api/register-token` (nuevo endpoint)
- [ ] 8.7 Implementar `onMessage()` handler para foreground
- [ ] 8.8 Implementar `renderDevicesAlarms()` function (ya existe, adaptar)
- [ ] 8.9 Service Worker registration con error handling
- [ ] 8.10 Listener para `navigator.serviceWorker.addEventListener('message')`
- [ ] 8.11 Multi-window postMessage synchronization

### 9. Frontend: Token Management
- [ ] 9.1 Crear route `POST /api/register-token`
- [ ] 9.2 Crear action en `SensorAlarmsController::registerToken()`
- [ ] 9.3 Guardar token en `tbl_00_users_tokens` (INSERT or UPDATE)
- [ ] 9.4 Asociar token con `id_user` del usuario logueado
- [ ] 9.5 Guardar `device` (user agent) y `created_at`
- [ ] 9.6 Implementar token cleanup (eliminar tokens >90 días sin uso)

### 10. Database
- [ ] 10.1 Verificar tabla `tbl_00_users_tokens` existe
- [ ] 10.2 Agregar índice en `token` column si no existe
- [ ] 10.3 Agregar índice en `id_user, reg_status` para query performance
- [ ] 10.4 Verificar tabla `servicios` existe para heartbeat
- [ ] 10.5 Agregar migration si cambios estructurales necesarios

### 11. Testing Phase 1
- [ ] 11.1 Test service worker registration en DevTools
- [ ] 11.2 Test notification permission request
- [ ] 11.3 Test token storage en `tbl_00_users_tokens`
- [ ] 11.4 Test API endpoint con curl y Bearer token
- [ ] 11.5 Test FCM sending con token de prueba
- [ ] 11.6 Test foreground message reception
- [ ] 11.7 Test background message con browser minimizado
- [ ] 11.8 Test notification actions (Ver Detalles, Cerrar)
- [ ] 11.9 Test multi-window sync (abrir 2 pestañas)
- [ ] 11.10 Test cache strategies (verificar en Application → Cache Storage)
- [ ] 11.11 Test service worker update (cambiar VERSION, reload)
- [ ] 11.12 Test con servicio externo en staging
- [ ] 11.13 Puppeteer screenshots de notificaciones

### 12. Documentation Phase 1
- [ ] 12.1 Documentar cómo obtener VAPID key en `docs/FIREBASE_SETUP.md`
- [ ] 12.2 Documentar API endpoints en `docs/API_ENDPOINTS.md`
- [ ] 12.3 Documentar Bearer token en `.env.example`
- [ ] 12.4 Documentar deployment steps en `docs/DEPLOYMENT.md`
- [ ] 12.5 Actualizar VERSION en service worker en cada deploy (documentar)

### 13. Deployment Phase 1
- [ ] 13.1 Backup `.env.prod` actual
- [ ] 13.2 Deploy código a producción
- [ ] 13.3 Ejecutar `npm install` en producción
- [ ] 13.4 Copy `firebase-messaging-sw.js` a `public/`
- [ ] 13.5 Update `.env.prod` con Firebase keys
- [ ] 13.6 `php bin/console cache:clear --env=prod`
- [ ] 13.7 Verificar service worker accesible: `https://vs.gvops.cl/firebase-messaging-sw.js`
- [ ] 13.8 Coordinar con proveedor servicio externo para test
- [ ] 13.9 Monitor logs por errores
- [ ] 13.10 Verificar notificaciones funcionan end-to-end

## Phase 2: Mercure Enhancement

### 14. Mercure Hub Installation
- [ ] 14.1 Download Mercure binary v0.16.3 para Linux x86_64
- [ ] 14.2 Extract a `/opt/mercure/`
- [ ] 14.3 `chmod +x /opt/mercure/mercure`
- [ ] 14.4 Crear `/opt/mercure/.env` con config
- [ ] 14.5 Generar JWT secret seguro para Mercure
- [ ] 14.6 Configurar `SERVER_NAME=:9090`
- [ ] 14.7 Test manual: `./mercure run --config .env`
- [ ] 14.8 Verificar health endpoint: `curl http://localhost:9090/.well-known/mercure`

### 15. Supervisor Configuration
- [ ] 15.1 Crear `/etc/supervisor/conf.d/mercure.conf`
- [ ] 15.2 Configurar command, directory, user (www)
- [ ] 15.3 Configurar autostart, autorestart
- [ ] 15.4 Configurar stdout_logfile
- [ ] 15.5 `sudo supervisorctl reread`
- [ ] 15.6 `sudo supervisorctl update`
- [ ] 15.7 `sudo supervisorctl start mercure`
- [ ] 15.8 `sudo supervisorctl status mercure` → RUNNING
- [ ] 15.9 Test restart: `sudo supervisorctl restart mercure`
- [ ] 15.10 Test auto-restart: `sudo kill <pid>` y verificar Supervisor reinicia

### 16. Nginx Configuration
- [ ] 16.1 Agregar location block para `/\.well-known/mercure`
- [ ] 16.2 Configurar `proxy_pass http://127.0.0.1:9090/.well-known/mercure`
- [ ] 16.3 Configurar `proxy_read_timeout 24h` (conexiones largas)
- [ ] 16.4 Configurar `proxy_http_version 1.1`
- [ ] 16.5 Configurar `proxy_set_header Connection ""`
- [ ] 16.6 Configurar headers X-Forwarded-*
- [ ] 16.7 `sudo nginx -t` → syntax OK
- [ ] 16.8 `sudo systemctl reload nginx`
- [ ] 16.9 Test externo: `curl https://vs.gvops.cl/.well-known/mercure`

### 17. Symfony Mercure Bundle
- [ ] 17.1 `composer require symfony/mercure-bundle`
- [ ] 17.2 Verificar `config/packages/mercure.yaml` generado
- [ ] 17.3 Configurar `MERCURE_URL` en `.env`
- [ ] 17.4 Configurar `MERCURE_JWT_SECRET` en `.env`
- [ ] 17.5 Test publisher simple con `HubInterface`

### 18. Dual Publishing Implementation
- [ ] 18.1 Inyectar `HubInterface` en `SensorAlarmsController`
- [ ] 18.2 Crear `Update` con topic `https://vs.gvops.cl/alarms/sos`
- [ ] 18.3 Publicar a Mercure antes de FCM (primary)
- [ ] 18.4 Mantener FCM como fallback
- [ ] 18.5 Agregar try-catch para Mercure (no fallar si Hub down)
- [ ] 18.6 Log success/failure de ambos métodos
- [ ] 18.7 Return JSON con status de ambos: `{mercure: true, fcm: 15}`

### 19. Frontend EventSource Client
- [ ] 19.1 Crear `EventSource` apuntando a `/\.well-known/mercure?topic=...`
- [ ] 19.2 Implementar `onmessage` handler
- [ ] 19.3 Parse JSON de `event.data`
- [ ] 19.4 Llamar `renderDevicesAlarms()` con data recibida
- [ ] 19.5 Implementar `onerror` handler con log
- [ ] 19.6 Verificar auto-reconnect funciona (built-in SSE)
- [ ] 19.7 Test cierre de EventSource al unload page
- [ ] 19.8 Mantener Firebase `onMessage()` como redundancy

### 20. Testing Phase 2
- [ ] 20.1 Test Mercure Hub running: `supervisorctl status mercure`
- [ ] 20.2 Test Nginx proxy: `curl https://vs.gvops.cl/.well-known/mercure`
- [ ] 20.3 Test EventSource connection en browser DevTools → Network
- [ ] 20.4 Test message delivery Mercure (<100ms latency)
- [ ] 20.5 Test dual publishing: Verificar ambos Mercure y FCM envían
- [ ] 20.6 Test Mercure down: Detener Hub, verificar Firebase fallback funciona
- [ ] 20.7 Test auto-reconnect: Detener/start Hub, verificar cliente reconecta
- [ ] 20.8 Test con servicio externo: Trigger alarma real
- [ ] 20.9 Test múltiples clientes: Abrir 5 pestañas, verificar todos reciben
- [ ] 20.10 Test performance: Medir latencia insert→notification
- [ ] 20.11 Puppeteer test automation

### 21. Monitoring & Alerts
- [ ] 21.1 Crear endpoint `/api/mercure/health` que verifica Hub accesible
- [ ] 21.2 Configurar Prometheus/Grafana (opcional)
- [ ] 21.3 Alert si Mercure down >5min
- [ ] 21.4 Alert si FCM quota excedida
- [ ] 21.5 Alert si heartbeat servicio externo >10min sin respuesta
- [ ] 21.6 Dashboard con métricas: notificaciones/hora, latencia avg, errores

### 22. Documentation Phase 2
- [ ] 22.1 Documentar Mercure setup en `docs/MERCURE_SETUP.md`
- [ ] 22.2 Documentar Supervisor config
- [ ] 22.3 Documentar Nginx config
- [ ] 22.4 Documentar troubleshooting: "Mercure not connecting"
- [ ] 22.5 Documentar rollback plan
- [ ] 22.6 Actualizar architecture diagram

### 23. Deployment Phase 2
- [ ] 23.1 Backup config actual
- [ ] 23.2 Deploy Mercure binary a `/opt/mercure/`
- [ ] 23.3 Deploy Supervisor config
- [ ] 23.4 Deploy Nginx config
- [ ] 23.5 Start Mercure Hub
- [ ] 23.6 Deploy código con dual publishing
- [ ] 23.7 `composer install` en producción
- [ ] 23.8 Update `.env.prod` con Mercure keys
- [ ] 23.9 `php bin/console cache:clear --env=prod`
- [ ] 23.10 Verificar end-to-end functionality
- [ ] 23.11 Monitor por 24h para estabilidad

## Post-Deployment

### 24. Optimization
- [ ] 24.1 Revisar logs por errores recurrentes
- [ ] 24.2 Optimizar queries PostgreSQL si necesario
- [ ] 24.3 Ajustar cache TTLs basado en uso real
- [ ] 24.4 Cleanup tokens antiguos: `DELETE FROM tbl_00_users_tokens WHERE updated_at < NOW() - INTERVAL '90 days'`
- [ ] 24.5 Verificar índices de DB en columnas query frecuentes

### 25. Training & Handoff
- [ ] 25.1 Documentar workflow completo para operadores
- [ ] 25.2 Sesión de training con equipo COT
- [ ] 25.3 Documentar troubleshooting común
- [ ] 25.4 Handoff a DevOps para monitoring
- [ ] 25.5 Actualizar runbook con nuevos componentes

### 26. Future Enhancements (Optional)
- [ ] 26.1 Push notifications para iOS/Android apps (si existen)
- [ ] 26.2 Rich notifications con imágenes
- [ ] 26.3 Sound customization por tipo de alarma
- [ ] 26.4 User preferences: silenciar notificaciones, horarios
- [ ] 26.5 Analytics: tasa de apertura, tiempo respuesta operador
