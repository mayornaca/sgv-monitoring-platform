# Checklist: Implementación de ReportLoader

## ✅ IMPLEMENTADO

### 1. Reporte Estado Dispositivos COT
- **Archivo**: `templates/dashboard/cot/videowall.html.twig`
- **Línea**: 1721-1767
- **Función**: `generarReporteDispositivos()`
- **Botón**: `#generate-report`
- **Acción**: Genera reporte PDF de dispositivos COT
- **Estado**: ✅ **COMPLETADO**

### 2. Reporte Tiempos Recursos Externos (SIV)
- **Archivo**: `templates/dashboard/siv/tiempos_recursos_externos.html.twig`
- **Líneas**: 182 (script), 402 (show), 452 (hide)
- **Función**: `getReportTREA()`
- **Botón**: `#btn_generate_report`
- **Acción**: Genera reporte PDF/Excel de tiempos recursos externos
- **Estado**: ✅ **COMPLETADO**

---

## 📋 PENDIENTES (Prioridad Alta - Reportes)

---

### 3. Historial de Recursos (SIV)
- **Archivo**: `templates/dashboard/siv/historial_recursos.html.twig`
- **Línea**: 267
- **Función**: Carga tabla de historial
- **Acción**: Filtra y muestra historial de recursos
- **Prioridad**: 🟡 MEDIA (carga de datos)

**Implementación sugerida**:
```javascript
// Usar overlay en el contenedor de la tabla
ReportLoader.showOverlay('#tabla-historial-container', 'Cargando historial...');
// En complete:
complete: () => ReportLoader.hideOverlay('#tabla-historial-container')
```

---

### 4. Bitácora SCADA (COT/SIV)
- **Archivo**: `templates/dashboard/siv/bitacora.html.twig`
- **Línea**: 213
- **Función**: Actualiza tabla de bitácora
- **Acción**: Carga registros de bitácora con filtros
- **Prioridad**: 🟡 MEDIA (actualización frecuente)

**Implementación sugerida**:
```javascript
ReportLoader.showOverlay('#bitacora-table-container', 'Actualizando registros...');
```

---

## 📝 PENDIENTES (Prioridad Media - Formularios)

### 5. Crear Proveedor (Modal)
- **Archivo**: `templates/dashboard/siv/permisos_trabajos/forms/frm_new_supplier.html.twig`
- **Línea**: 78
- **Función**: Guarda nuevo proveedor
- **Botón**: Botón submit del formulario
- **Prioridad**: 🟢 BAJA (formulario rápido)

### 6. Crear Ubicación (Modal)
- **Archivo**: `templates/dashboard/siv/permisos_trabajos/forms/frm_new_location.html.twig`
- **Línea**: 76
- **Prioridad**: 🟢 BAJA

### 7. Crear Personal Externo (Modal)
- **Archivo**: `templates/dashboard/siv/permisos_trabajos/forms/frm_new_ext_staff.html.twig`
- **Línea**: 126
- **Prioridad**: 🟢 BAJA

### 8. Crear Personal Interno (Modal)
- **Archivo**: `templates/dashboard/siv/permisos_trabajos/forms/frm_new_int_staff.html.twig`
- **Línea**: 193
- **Prioridad**: 🟢 BAJA

---

## 📊 PENDIENTES (Prioridad Baja - CRUD)

### 9-16. Operaciones Bitácora SCADA
- **Archivo**: `templates/dashboard/cot/sensors_alarms/report/tabla.html.twig`
- **Líneas**: 299, 353, 492, 581, 645, 682, 719
- **Operaciones**: Add, Edit, Get, Update, Highlight, Delete, Start
- **Prioridad**: 🟢 BAJA (operaciones CRUD rápidas)

### 17-20. Operaciones Bitácora General
- **Archivos**:
  - `templates/dashboard/siv/bitacora.html.twig` (línea 355)
  - `templates/dashboard/siv/bitacora/tabla.html.twig` (líneas 382, 521, 607, 645, 678, 711)
  - `templates/dashboard/siv/bitacora/add.html.twig` (línea 135)
- **Prioridad**: 🟢 BAJA

---

## 🎯 PLAN DE IMPLEMENTACIÓN RECOMENDADO

### Fase 1: Reportes Importantes (Esta semana)
1. ✅ Reporte Dispositivos COT (COMPLETADO)
2. ⏳ Reporte Tiempos Recursos Externos
3. ⏳ Historial Recursos

### Fase 2: Tablas con Filtros (Próxima semana)
4. ⏳ Bitácora SCADA
5. ⏳ Otras tablas con carga AJAX

### Fase 3: Formularios Modales (Cuando haya tiempo)
6-8. ⏳ Formularios de creación rápida

### Fase 4: Operaciones CRUD (Opcional)
9-20. ⏳ Operaciones individuales rápidas (pueden no necesitar loader)

---

## 📝 NOTAS DE IMPLEMENTACIÓN

### Pattern para Reportes (PDF/Excel):
```javascript
const reportBtn = '#selector-boton';
ReportLoader.show(reportBtn, 'Generando reporte...');

$.ajax({
    url: '...',
    type: 'POST',
    data: {...},
    success: (response) => {...},
    error: (xhr, status, error) => {...},
    complete: () => ReportLoader.hide(reportBtn)
});
```

### Pattern para Tablas con Carga:
```javascript
ReportLoader.showOverlay('#contenedor-tabla', 'Cargando datos...');

$.ajax({
    url: '...',
    type: 'GET',
    success: (html) => {
        $('#contenedor-tabla').html(html);
    },
    complete: () => ReportLoader.hideOverlay('#contenedor-tabla')
});
```

### Pattern para Formularios:
```javascript
const submitBtn = '#btn-submit';
ReportLoader.show(submitBtn, 'Guardando...');

$.ajax({
    url: '...',
    type: 'POST',
    data: {...},
    success: (response) => {...},
    complete: () => ReportLoader.hide(submitBtn)
});
```

---

## 🔧 HERRAMIENTAS

### Componente Global: `/public/js/report-loader.js`
```javascript
// Para botones
ReportLoader.show(selector, text);
ReportLoader.hide(selector);

// Para contenedores
ReportLoader.showOverlay(selector, text);
ReportLoader.hideOverlay(selector);
```

### Incluido en:
- `templates/dashboard/cot/videowall.html.twig` (línea 898)
- TODO: Incluir en templates base de SIV

---

## ✅ CHECKLIST DE VERIFICACIÓN

Antes de marcar como completado, verificar:
- [ ] Loader se muestra inmediatamente al hacer click
- [ ] Botón se deshabilita durante carga
- [ ] Loader se oculta en success Y error
- [ ] Texto descriptivo apropiado
- [ ] No hay flashes (considerar delay si es muy rápido)
- [ ] Accesibilidad: spinner tiene role="status"

---

**Última actualización**: 2025-11-03
**Responsable**: Equipo de desarrollo
**Documentación**: https://getbootstrap.com/docs/5.3/components/spinners/
