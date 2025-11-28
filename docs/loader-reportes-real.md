# Listado REAL: Botones de Reportes para Loader

## ✅ IMPLEMENTADO

### 1. Reporte Estado Dispositivos COT
- **Template**: `templates/dashboard/cot/videowall.html.twig`
- **Botón**: `#generate-report`
- **Función**: `generarReporteDispositivos()` (línea 1708)
- **AJAX**: línea 1724
- **Estado**: ✅ COMPLETADO

---

## 🔴 PRIORIDAD ALTA - Reportes con Múltiples Botones

### 2. Tiempos Recursos Externos (SIV)
**Template**: `templates/dashboard/siv/tiempos_recursos_externos.html.twig`

#### A. Botón "Generar reporte" (Vista principal)
- **Botón**: `#btn_generate_report` (línea 50)
- **Evento**: `$('#btn_generate_report').on('click', ...)` (línea 398)
- **Función**: `getReportTREA()` (línea 412)
- **AJAX**: línea 424
- **beforeSend**: Ya tiene loader manual (línea 438-441)
- **Acción**: Genera preview del reporte en modal

**Implementación**:
```javascript
// Línea 398-408: Reemplazar
$('#btn_generate_report').on('click', function(e) {
    e.preventDefault();
    if (selections.length > 0) {
        ReportLoader.show('#btn_generate_report', 'Generando reporte...');
        getReportTREA();
    }
});

// Línea 424: Agregar complete
$.ajax({
    complete: () => ReportLoader.hide('#btn_generate_report')
});
```

#### B. Botón "Excel" (Desde modal)
- **Botón**: `onclick="downloadExcelBySelectedAccident()"` (línea 162)
- **Función**: `downloadExcelBySelectedAccident()` (línea 453)
- **Método**: POST con form submit (no AJAX)
- **Acción**: Descarga Excel de registros seleccionados

**Nota**: Form submit directo, no necesita loader (descarga automática)

#### C. Botón "PDF" (Desde modal)
- **Botón**: `onclick="downloadPdfBySelectedAccident()"` (línea 165)
- **Función**: `downloadPdfBySelectedAccident()` (línea 492)
- **Método**: POST con form submit (no AJAX)
- **Acción**: Descarga PDF de registros seleccionados

**Nota**: Form submit directo, no necesita loader (descarga automática)

#### D. Botón "Descargar Excel" (Vista principal)
- **Botón**: `onclick="downloadFileExcel()"` (línea 108)
- **Función**: `downloadFileExcel()` (línea 197)
- **Método**: XMLHttpRequest con blob
- **Acción**: Descarga archivo Excel ya generado

**Implementación**:
```javascript
// Convertir a async/await con loader
async function downloadFileExcel() {
    const btn = document.querySelector('button[onclick="downloadFileExcel()"]');
    ReportLoader.show(btn, 'Descargando...');
    try {
        const response = await fetch('/downloads/{{ return_file_name_excel }}');
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = "{{ return_file_name_excel }}";
        a.click();
        window.URL.revokeObjectURL(url);
    } finally {
        ReportLoader.hide(btn);
    }
}
```

#### E. Botón "Descargar PDF" (Vista principal)
- **Botón**: `onclick="downloadFilePdf()"` (línea 114)
- **Función**: `downloadFilePdf()` (línea 218)
- **Método**: XMLHttpRequest con blob
- **Acción**: Descarga archivo PDF ya generado

**Implementación**: Similar al Excel

---

### 3. Informe Mensual Citofonía (SIV)
**Template**: `templates/dashboard/siv/informe_mensual_citofonia.html.twig`

#### A. Botón "Descargar PDF"
- **Botón**: `#btn-exp-pdf` (línea 140)
- **Evento**: `jQuery("#btn-exp-pdf").click(...)` (línea 321)
- **Método**: XMLHttpRequest con blob
- **Acción**: Descarga PDF ya generado

**Implementación**:
```javascript
jQuery("#btn-exp-pdf").click(function () {
    ReportLoader.show('#btn-exp-pdf', 'Descargando...');
    // ... código existente ...
    // En onreadystatechange success:
    ReportLoader.hide('#btn-exp-pdf');
});
```

---

## 📊 RESUMEN DE BOTONES ENCONTRADOS

| Template | Botones con AJAX | Botones descarga | Total |
|----------|------------------|------------------|-------|
| videowall.html.twig | 1 (✅) | 0 | 1 |
| tiempos_recursos_externos | 1 | 4 | 5 |
| informe_mensual_citofonia | 0 | 1 | 1 |
| **TOTAL** | **2** | **5** | **7** |

---

## 🎯 PRIORIDADES DE IMPLEMENTACIÓN

### FASE 1: Botones AJAX (generan reportes)
1. ✅ `#generate-report` (COT) - COMPLETADO
2. ✅ `#btn_generate_report` (SIV Tiempos Recursos) - COMPLETADO

### FASE 2: Botones de Descarga (archivos ya generados)
3. ⏳ `downloadFileExcel()` - Tiempos Recursos
4. ⏳ `downloadFilePdf()` - Tiempos Recursos
5. ⏳ `#btn-exp-pdf` - Informe Mensual Citofonía

---

## 📝 PENDIENTE DE INVESTIGAR

Faltan por revisar:
- Reporte Tiempos Respuesta Incidente
- Reporte Historial Espiras CN/VS
- Reporte Alarmas SOS
- Export Llamadas SOS (Excel/PDF)

**Próximo paso**: Buscar botones en estos templates restantes.

---

**Última actualización**: 2025-11-04
**Archivo**: `/www/wwwroot/vs.gvops.cl/docs/loader-reportes-real.md`

## ✅ FASE 1 COMPLETADA

Ambos reportes AJAX han sido implementados con el loader estandarizado:
- COT: Reporte Estado Dispositivos (`videowall.html.twig`)
- SIV: Tiempos Recursos Externos (`tiempos_recursos_externos.html.twig`)