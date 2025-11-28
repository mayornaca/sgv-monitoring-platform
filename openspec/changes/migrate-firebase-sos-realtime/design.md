# Design: Real-Time SOS Notifications Architecture

## Context

Sistema legacy usaba Firebase Cloud Messaging v7.14.3 (2020) para notificaciones push de alarmas de sensores SOS. Un servicio externo de hardware monitoring inserta alarmas en la base de datos y llama a un webhook para distribuir notificaciones vía FCM.

**Stakeholders**:
- Operadores COT (Centro de Operaciones de Tráfico)
- Servicio externo de hardware (proveedor de sensores SOS)
- DevOps team (deployment y mantenimiento)

**Constraints**:
- Puerto 443 solo (no puertos adicionales expuestos)
- Sistema debe funcionar offline (cache de assets)
- Notificaciones <1 segundo latency para emergencias
- Servicio externo NO puede modificarse (API contract fijo)
- Server Rocky Linux con Nginx + PHP-FPM + Supervisor

**Current State**:
- Legacy: Firebase v7.14.3 + Workbox 4.0 + PWA service worker
- Current: AJAX polling cada N segundos (ineficiente, sin background notifications)
- GAP: API endpoints NO migrados, service workers NO existen

## Goals / Non-Goals

### Goals
✅ Restaurar funcionalidad legacy de push notifications
✅ Modernizar a Firebase v11 con métodos actualizados
✅ Mejorar latencia con Mercure SSE (<100ms vs ~500ms FCM)
✅ Service worker auto-actualizable sin cache stale
✅ Mantener separación de caches por categoría (óptimo)
✅ Dual redundancy (Mercure + Firebase fallback)
✅ Zero downtime deployment
✅ Compatible con servicio externo existente

### Non-Goals
❌ Migrar a Supabase Realtime (overkill, complejidad innecesaria)
❌ Ratchet WebSocket (Mercure SSE superior para push unidireccional)
❌ Cambiar API contract con servicio externo
❌ Eliminar Firebase completamente (necesario para background)
❌ Unified cache (separación es óptima)

## Decisions

### Decision 1: Mercure over Ratchet para Real-Time

**Context**: Necesitamos server-to-client push notifications con latencia mínima.

**Options Considered**:

**A) Ratchet (WebSocket PHP)**
- Pros: Bidireccional, true WebSocket, lower latency teórico
- Cons:
  - PHP mantiene conexiones persistentes (más RAM)
  - Solo HTTP/1.x compatible
  - Sin auto-reconnect built-in
  - Performance inferior a otras implementaciones PHP (OpenSwoole)
  - Más complejo escalar

**B) Mercure (SSE + Go Hub)**
- Pros:
  - Nativo Symfony (`symfony/mercure-bundle`)
  - SSE con auto-reconnect built-in
  - HTTP/2 multiplexing (múltiples streams en 1 conexión)
  - Hub en Go maneja conexiones (no PHP)
  - 8M notificaciones/día en servidor €6.90/mes (Mail.tm case study)
  - Menos recursos para push unidireccional
  - Built-in authorization con JWT
- Cons:
  - Unidireccional (no problema para este caso)
  - Requiere Hub separado (ligero, binario Go)

**Decision**: **Mercure** ✅

**Rationale**:
1. Alarmas SOS son **unidireccionales** (server → clients), no necesitan bidireccional
2. SSE auto-reconnect crítico para confiabilidad
3. Go Hub más eficiente que PHP para mantener 100+ conexiones persistentes
4. HTTP/2 multiplexing reduce overhead vs WebSocket HTTP/1.x
5. Integración nativa Symfony reduce complejidad
6. Performance comprobado: 8M notificaciones/día con recursos mínimos

### Decision 2: Firebase v11 + Mercure (Híbrido) over Solo Firebase

**Context**: Necesitamos notificaciones background Y foreground con redundancia.

**Options Considered**:

**A) Solo Firebase v11**
- Pros: Cloud managed, sin servidor adicional, background notifications
- Cons: Latency ~500ms, depende de Google Cloud reachability

**B) Solo Mercure**
- Pros: Latencia <100ms, self-hosted
- Cons: No background notifications (requiere browser tab abierto)

**C) Híbrido Firebase + Mercure**
- Pros:
  - Mercure para latencia ultra-baja en foreground
  - Firebase para background notifications
  - Redundancia: Si Mercure falla, Firebase responde
  - Mejor experiencia usuario
- Cons: Dual maintenance (mitigado con Supervisor auto-restart)

**Decision**: **Híbrido** ✅

**Rationale**:
1. **Foreground**: Mercure SSE entrega <100ms (crítico para emergencias)
2. **Background**: Firebase push notifications cuando browser cerrado
3. **Redundancia**: Dual publishing garantiza delivery
4. **Progressive enhancement**: Funciona con solo Firebase, Mercure mejora experiencia

### Decision 3: Mantener Caches Separadas en Service Worker

**Context**: Legacy tiene 8 categorías de cache. ¿Unificar o mantener?

**Legacy Structure**:
```
- google-fonts (fonts.googleapis.com, fonts.gstatic.com)
- gravatar (secure.gravatar.com)
- mapbox-tiles (mapbox.com tiles)
- osm-tiles (tile.osm.org)
- assets (CSS/JS propios)
- fonts (woff/eot/ttf locales)
- images (png/jpg/gif locales)
- default (catch-all)
```

**Options**:

**A) Unified Cache**
- Pros: Más simple, menos caches
- Cons:
  - Invalidación all-or-nothing (cambio CSS limpia fonts también)
  - Búsqueda más lenta en cache grande
  - TTL único para todo
  - Debug difícil

**B) Mantener Separación (8 categorías)**
- Pros:
  - **Invalidación selectiva**: Limpiar solo images sin tocar fonts
  - **TTL diferenciados**: Fonts cache 365 días, API 0 días
  - **Debug granular**: Identificar qué categoría causa problema
  - **Size management**: Evitar cache único >100MB
  - **Performance**: Búsqueda más rápida en caches pequeños
- Cons: Más caches en DevTools (no problema operacional)

**Decision**: **Mantener Separación** ✅

**Rationale**:
1. Fonts/images raramente cambian → cache largo
2. Assets (CSS/JS) cambian con deploys → cache corto
3. Map tiles permanentes → cache indefinido
4. Invalidación selectiva evita re-download innecesario
5. Debug más fácil: "images cache corrupto" vs "cache corrupto"

### Decision 4: Supabase Realtime NO Considerado

**Context**: ¿Supabase Realtime self-hosted puede simplificar?

**Analysis**:
- **Stack requerido**: 10+ containers (Postgres, GoTrue, PostgREST, Realtime Elixir, Storage, Kong, Studio...)
- **Complejidad**: Docker Compose multi-service, Elixir/Phoenix para Realtime
- **Recursos**: Mínimo 4GB RAM para stack completo
- **Integration**: PostgreSQL ya configurado, no migran
- **Curva aprendizaje**: Equipo PHP, no Elixir
- **Tiempo**: 4-6 semanas solo setup

**Decision**: **Rechazado** ❌

**Rationale**:
1. **Overkill**: Requiere stack completo para 1 función
2. **Ya tienen PostgreSQL**: No necesitan migrar
3. **Complejidad operacional**: Mantener 10 servicios vs 1 binario Go
4. **ROI negativo**: 6 semanas vs 3 semanas con Mercure
5. Supabase para greenfield projects, no para migración selectiva

### Decision 5: Versionado Dinámico de Cache

**Legacy Problem**: `const cacheName = '2025_07_22'` → hardcoded date

**Options**:

**A) Fecha Manual**
- Legacy approach
- Cons: Olvido actualizar = cache stale forever

**B) Build-time Injection**
- Webpack/Encore inyecta version en build
- Cons: Requiere build process modificación

**C) Version Constante + Auto-Cleanup**
- `const VERSION = '2025.11.13.001'`
- Auto-cleanup en `activate` elimina versiones antiguas
- Pros: Simple, funciona sin build process, cleanup automático

**Decision**: **Version Constante + Auto-Cleanup** ✅

**Implementation**:
```javascript
const VERSION = '2025.11.13.001';
const CACHE_PREFIX = 'vs-gvops';

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then(keys =>
      Promise.all(
        keys
          .filter(key => key.startsWith(CACHE_PREFIX) && !key.includes(VERSION))
          .map(key => caches.delete(key))
      )
    )
  );
});
```

### Decision 6: Supervisor over systemd para Mercure Hub

**Context**: ¿Cómo mantener Mercure Hub como servicio?

**Options**:

**A) Supervisor**
- Pros:
  - Ya instalado en servidor
  - Más simple configuración
  - Logs centralizados en `/var/log/mercure.log`
  - Web interface opcional
  - Restart policies fáciles
- Cons: Proceso adicional (supervisord)

**B) systemd**
- Pros:
  - Nativo Linux
  - Journal logs integrados
  - Dependency management
- Cons:
  - Más verboso configuración
  - Requiere root para modificar units

**Decision**: **Supervisor** ✅

**Rationale**:
1. Ya existe en servidor (no nuevo dependency)
2. Configuración más simple (`.conf` file)
3. Reinicio automático en fallo
4. Logs fáciles (`supervisorctl tail mercure`)
5. Stop/start sin sudo

**Configuration**:
```ini
[program:mercure]
command=/opt/mercure run --config /opt/mercure/.env
directory=/opt/mercure
user=www
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/mercure.log
environment=SERVER_NAME=":9090"
```

## Risks / Trade-offs

### Risk 1: Firebase Quota Limits
**Risk**: Google FCM free tier tiene límites
**Impact**: Notificaciones fallan si excede cuota
**Probability**: Baja (<100 usuarios concurrentes)
**Mitigation**:
- Mercure como primary (ilimitado)
- Firebase solo fallback
- Monitorear uso en Firebase Console

### Risk 2: Mercure Hub Single Point of Failure
**Risk**: Si Mercure Hub cae, sin real-time
**Impact**: Fallback a Firebase (funciona, pero latencia mayor)
**Probability**: Baja (Supervisor auto-restart)
**Mitigation**:
- Supervisor auto-restart en fallo
- Health check endpoint
- Alert si Mercure down >5min
- Firebase garantiza delivery

### Risk 3: Service Worker Cache Stale
**Risk**: Usuarios quedan con cache antiguo
**Impact**: Bugs, features no aparecen
**Probability**: Media si olvidan actualizar VERSION
**Mitigation**:
- `skipWaiting()` + `clients.claim()` force update
- Auto-cleanup elimina versiones antiguas
- Documentar en DEPLOYMENT.md: "Actualizar VERSION en cada deploy"

### Risk 4: CORS Issues con Mercure
**Risk**: Browsers bloquean SSE por CORS
**Impact**: Mercure no funciona, fallback a Firebase
**Probability**: Baja (misma origin si Nginx proxy)
**Mitigation**:
- Nginx reverse proxy `/mercure` en mismo dominio
- CORS headers configurados en Hub
- Testing pre-deployment

### Risk 5: External Service Down
**Risk**: Servicio externo no envía alarmas
**Impact**: Sin notificaciones (problema existe hoy)
**Probability**: Baja
**Mitigation**:
- Heartbeat endpoint `/api/register_service` monitorea status
- Alert si heartbeat >10min sin respuesta
- Logs de llamadas API para debug

## Migration Plan

### Phase 1: Firebase v11 Foundation (Week 1-2)

**Week 1**:
1. Install npm dependencies
2. Create service worker con Firebase modular API
3. Create API endpoints `/api/send_push_notification`, `/api/register_service`
4. Port FCMServiceManual to Symfony 6.4
5. Frontend integration (token registration, onMessage)

**Week 2**:
6. Obtain VAPID key from Firebase Console
7. Test con servicio externo en staging
8. Multi-window sync testing
9. Notification actions testing
10. Deploy to production (Firebase solo)

**Rollback**: Revert API endpoints, remove service worker

### Phase 2: Mercure Enhancement (Week 3)

**Week 3**:
11. Download Mercure Hub binary
12. Configure Supervisor
13. Configure Nginx reverse proxy
14. Update API endpoints para dual publishing
15. Frontend EventSource client
16. Testing Mercure + Firebase redundancy
17. Deploy to production (Mercure + Firebase)

**Rollback**: Stop Mercure Hub, API revierte a solo Firebase

### Deployment Steps

**Pre-Deployment**:
1. Backup `.env`
2. Obtener VAPID key
3. Verificar Supervisor running
4. Verificar Nginx config syntax

**Deployment Phase 1**:
```bash
# 1. npm install
npm install firebase@^11.0.2 workbox-window@^7.3.0

# 2. Copy service worker
cp firebase-messaging-sw.js public/

# 3. Deploy code (API endpoints, FCMService)
git add src/Controller/Api/SensorAlarmsController.php
git add src/Service/FCMService.php
git commit -m "Add Firebase v11 push notification endpoints"
git push

# 4. Update .env
echo "FIREBASE_VAPID_KEY=..." >> .env.prod
echo "EXTERNAL_SERVICE_TOKEN=f0g0ZYU0GslcJW_fPCJvCIN3-57-Yh7oTVP_qgBh6eE" >> .env.prod

# 5. Clear cache
php bin/console cache:clear --env=prod

# 6. Test API endpoint
curl -X POST https://vs.gvops.cl/api/send_push_notification \
  -H "Authorization: Bearer f0g0ZYU0GslcJW_fPCJvCIN3-57-Yh7oTVP_qgBh6eE" \
  -H "Content-Type: application/json" \
  -d '{"type":"alarms_sos_sensor","alarms_ids":[123]}'
```

**Deployment Phase 2**:
```bash
# 1. Download Mercure
cd /opt
wget https://github.com/dunglas/mercure/releases/download/v0.16.3/mercure_Linux_x86_64.tar.gz
tar -xzf mercure_Linux_x86_64.tar.gz
chmod +x mercure

# 2. Configure Mercure
cat > /opt/mercure/.env <<EOF
SERVER_NAME=:9090
MERCURE_PUBLISHER_JWT_KEY=!ChangeThisMercureHubJWTSecretKey!
MERCURE_SUBSCRIBER_JWT_KEY=!ChangeThisMercureHubJWTSecretKey!
EOF

# 3. Supervisor config
sudo cp mercure.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mercure

# 4. Nginx config
sudo nginx -t
sudo systemctl reload nginx

# 5. Update .env Symfony
echo "MERCURE_URL=https://vs.gvops.cl/.well-known/mercure" >> .env.prod
echo "MERCURE_JWT_SECRET=!ChangeThisMercureHubJWTSecretKey!" >> .env.prod

# 6. Deploy dual publishing code
git add src/Controller/Api/SensorAlarmsController.php (updated)
git commit -m "Add Mercure dual publishing"
git push
php bin/console cache:clear --env=prod
```

**Verification**:
```bash
# Check Mercure running
supervisorctl status mercure

# Check Mercure endpoint
curl https://vs.gvops.cl/.well-known/mercure

# Check service worker registered
# Open DevTools → Application → Service Workers

# Test notification
# Trigger alarm desde servicio externo
```

## Open Questions

1. **VAPID Key**: ¿Crear nueva o usar existente de legacy?
   - **Recommendation**: Crear nueva (legacy puede estar comprometida)

2. **Token Migration**: ¿Migrar tokens existentes de `tbl_00_users_tokens`?
   - **Recommendation**: No, re-registration automática al abrir app

3. **Mercure JWT Secret**: ¿Misma key para publisher y subscriber?
   - **Recommendation**: Sí por simplicidad (solo internal use)

4. **Nginx SSL**: ¿Mercure necesita certificado separado?
   - **No**: Nginx termina SSL, Mercure interno HTTP

5. **Supervisor User**: ¿www o root para Mercure?
   - **Recommendation**: www (menos privilegios)

6. **Cache TTL**: ¿Cuánto tiempo cachear assets?
   - **Recommendation**:
     - Fonts: 365 días
     - Images: 30 días
     - Assets (CSS/JS): 7 días (deploy frecuente)
     - Map tiles: 365 días

## Performance Considerations

**Latency Targets**:
- Mercure SSE: <100ms insert→delivery
- Firebase FCM: ~500ms (acceptable para background)
- AJAX polling eliminado: ~5000ms → 0ms (eliminated)

**Resource Usage**:
- Mercure Hub: ~50MB RAM idle, ~200MB con 100 conexiones
- Firebase: Cloud (no local resources)
- Service Worker: ~10MB cache por usuario

**Scalability**:
- Mercure: Comprobado 8M notificaciones/día, 1 servidor low-end
- Firebase: Google scale (ilimitado para nuestro caso)
- Service Worker: Client-side (escala natural)

**Bottlenecks**:
- PostgreSQL query para alarm details: Optimizar con index en `id`
- FCM batch sending: Chunks de 100 tokens (ya implementado en legacy)
- Nginx connections: Aumentar worker_connections si >1000 SSE concurrentes
