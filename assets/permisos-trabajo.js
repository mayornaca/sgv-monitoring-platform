/**
 * Módulo para la página de Lista de Permisos de Trabajo
 * Implementa multiselect profesional usando tom-select con AssetMapper
 */

import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.bootstrap5.min.css';

// Variables globales
let timer_interval;
let regStatusSelect = null;

document.addEventListener('DOMContentLoaded', function() {
    console.log('=' Inicializando Permisos de Trabajo (tom-select)');

    initializeRegStatusSelect();
    SetPTTimer();

    const timerInput = document.getElementById('timer-range-ajax-update');
    if (timerInput) {
        timerInput.addEventListener('change', function() {
            clearInterval(timer_interval);
            SetPTTimer();
        });
    }

    console.log(' Módulo inicializado');
});

function initializeRegStatusSelect() {
    const el = document.getElementById('regStatus');
    if (!el) return;

    try {
        regStatusSelect = new TomSelect(el, {
            plugins: ['remove_button', 'checkbox_options'],
            maxItems: null,
            placeholder: 'Seleccione estados...',
            closeAfterSelect: false
        });

        if (regStatusSelect.getValue().length === 0) {
            regStatusSelect.addItem('all', true);
        }
        console.log(' tom-select inicializado');
    } catch (e) {
        console.error('L Error tom-select:', e);
    }
}

function SetPTTimer() {
    const timerInput = document.getElementById('timer-range-ajax-update');
    if (!timerInput) return;

    timer_interval = setInterval(function () {
        const autoUpdate = document.getElementById('actualizacionAutomatica');
        if(autoUpdate && autoUpdate.checked) {
            getPTData();
        }
    }, timerInput.value * 1000);
}

function getPTData() {
    const form = document.getElementById('report_params');
    const params = new URLSearchParams(new FormData(form));
    params.append('action', 'ajax');

    const container = document.getElementById('pt_table_container');
    container.innerHTML = '<div class="alert alert-info text-center"><i class="fas fa-spinner fa-spin"></i> Cargando...</div>';

    fetch(window.location.pathname.replace('/lista_permisos_trabajos', '/get_pt') + '?' + params)
        .then(r => r.text())
        .then(html => container.innerHTML = html)
        .catch(e => {
            container.innerHTML = '<div class="alert alert-danger">Error al cargar</div>';
            console.error(e);
        });
}

window.getPTData = getPTData;
window.showModalAddPT = () => new bootstrap.Modal(document.getElementById('modalPermisoTrabajo')).show();
window.viewPT = (id) => alert('Ver: ' + id);
window.editPT = (id) => new bootstrap.Modal(document.getElementById('modalPermisoTrabajo')).show();
window.deletePT = (id) => {
    if(confirm('¿Eliminar permiso?')) {
        fetch(window.location.pathname.replace('/lista_permisos_trabajos', '/set_pt'), {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete', id})
        })
        .then(r => r.json())
        .then(d => { if(d.success) getPTData(); });
    }
};
window.downloadPtListPDF = () => {
    const params = new URLSearchParams(new FormData(document.getElementById('report_params')));
    params.append('action', 'pdf');
    window.open(window.location.pathname + '?' + params, '_blank');
};