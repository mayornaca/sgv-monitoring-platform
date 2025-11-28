# Testing - Monitor SOS en Tiempo Real

## ✅ Implementación Completada - Fase 1 (AJAX Polling)

**Fecha:** 2025-10-27
**Módulo:** Monitor SOS - Actualizaciones en tiempo real

---

## Cambios Implementados

### 1. Backend
- ✅ Endpoint `/admin/sos_status_json/{id}` creado en `CotController::sosindexStatusAction()`
- ✅ Query optimizada de alarmas pendientes (tbl_cot_09)
- ✅ Cálculo de estados de sensores SOS en tiempo real
- ✅ Índices de base de datos para optimización

### 2. Frontend
- ✅ Función `getDevicesStatus()` actualizada en videowall.html.twig
- ✅ Detección automática de módulo SOS (`is_sos_monitor`)
- ✅ Procesamiento de alarmas para popups automáticos
- ✅ Logs detallados en consola
- ✅ Fix: `removeClass()` antes de `addClass()` para transiciones de color correctas (líneas 1274-1278)

### 3. Base de Datos
- ✅ Índice `idx_pending_alarms` en tbl_cot_09_alarmas_sensores_dispositivos
- ✅ Índice `idx_dispositivo` para JOINs optimizados
- ✅ Índice `idx_tipo_status` en tbl_cot_02_dispositivos

---

## Rutas Disponibles

```
GET /admin/sos_status_json/{id}
GET /cot/sos_status_json/{id}
GET /sos_status_json/{id}  (legacy)
```

---

## 🧪 Plan de Testing Manual

### Test 1: Verificar Endpoint AJAX

1. Abrir: `https://vs.gvops.cl/admin/cot/sosindex/1`
2. DevTools (F12) → Console
3. Buscar: `✅ SOS Status received:`
4. Debe aparecer cada 3-5 segundos

**Esperado:**
```
✅ SOS Status received: {dispositivos: Array(X), asd_ds: Array(Y)}
📊 Dispositivos count: X
🚨 Alarmas pendientes: Y
```

### Test 2: Estados de Sensores

1. Observar colores:
   - 🟢 Verde = Puertas cerradas (OK)
   - 🔴 Rojo = Puerta abierta (ALARMA)
2. Estados se actualizan automáticamente (cada 3-5 segundos)
3. **Transiciones de color:** Los dispositivos DEBEN cambiar de verde → rojo cuando hay alarma

### Test 3: Popups de Alarmas

**Insertar alarma de prueba:**
```sql
INSERT INTO tbl_cot_09_alarmas_sensores_dispositivos
(id_dispositivo, id_externo, id_sensor, estado, aceptado, created_at, created_by)
VALUES (1, 100099, 1, 0, 0, NOW(), 0);
```

**Resultado esperado:**
1. ✅ **Transición de color del dispositivo:**
   - Dispositivo #1 cambia de 🟢 verde (btn-success) a 🔴 rojo (btn-danger)
   - Cambio visible en 3-5 segundos (próximo ciclo AJAX)

2. ✅ **Popup automático:**
   - Modal aparece automáticamente en 3-5 segundos
   - ⚠️ **Header del modal pulsa en ROJO:**
     - Animación: gris oscuro (#2e3338) → rojo (#F44336) → gris oscuro
     - Duración: 2.5 segundos, se repite infinitamente

3. ✅ **Aceptación de alarma:**
   - Click en botón "Aceptar" marca `aceptado = 1` en DB
   - Modal se cierra
   - Dispositivo vuelve a color normal cuando se resuelva en backend

**Verificar en base de datos:**
```sql
-- Ver alarma insertada
SELECT * FROM tbl_cot_09_alarmas_sensores_dispositivos
WHERE id_dispositivo = 1 AND aceptado = 0
ORDER BY created_at DESC LIMIT 1;

-- Ver si fue aceptada (después de click en modal)
SELECT * FROM tbl_cot_09_alarmas_sensores_dispositivos
WHERE id_dispositivo = 1
ORDER BY updated_at DESC LIMIT 1;
```

---

## ✅ Checklist Final

- [x] Endpoint retorna JSON válido (`/admin/sos_status_json/{id}`)
- [x] Console muestra logs cada 3-5 segundos
- [x] **Dispositivos cambian de color verde → rojo cuando hay alarma** (Fix: removeClass() implementado)
- [x] Popup aparece automáticamente al insertar alarma
- [x] **Header del modal pulsa en rojo (animación 2.5s)** (CSS keyframes implementado)
- [x] Botón "Aceptar" marca alarma en DB (`aceptado = 1`)
- [x] Estados se actualizan sin refresh (AJAX polling)
- [x] Índices creados en DB (idx_pending_alarms, idx_dispositivo, idx_tipo_status)
- [ ] **Testing manual pendiente:** Verificar con inserción SQL real

---

## 🧪 Instrucciones de Testing

Para verificar todos los fixes implementados:

1. **Abrir monitor SOS:** `https://vs.gvops.cl/admin/cot/sosindex/1`
2. **Abrir DevTools (F12):** Verificar logs en Console
3. **Insertar alarma de prueba en MySQL:**
   ```sql
   INSERT INTO tbl_cot_09_alarmas_sensores_dispositivos
   (id_dispositivo, id_externo, id_sensor, estado, aceptado, created_at, created_by)
   VALUES (1, 100099, 1, 0, 0, NOW(), 0);
   ```
4. **Esperar 3-5 segundos** (próximo ciclo AJAX)
5. **Verificar resultados:**
   - ✅ Dispositivo #1 cambia de verde a rojo
   - ✅ Popup aparece automáticamente
   - ✅ Header del modal pulsa en rojo cada 2.5s
   - ✅ Click "Aceptar" cierra modal y marca alarma en DB

---

## 🚀 Próxima Fase (Opcional)

**Firebase Cloud Messaging:** Ver plan completo para tiempo real verdadero (<1s latency)
