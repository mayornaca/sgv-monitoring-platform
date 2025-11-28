/**
 * AJAX Helpers - Utilidades para llamadas AJAX estandarizadas
 * Versión: 1.0.0
 *
 * Proporciona funciones helper para operaciones AJAX comunes:
 * - Refresh de contenido (preservando estado UI)
 * - Descarga de archivos
 * - Manejo de errores estándar
 * - Loading states
 *
 * Compatible con jQuery y Fetch API nativa
 */

const AjaxHelpers = {
    /**
     * Refresh de contenido con AJAX preservando estado UI
     * @param {string} containerSelector - Selector del contenedor a refrescar
     * @param {string} url - URL del endpoint
     * @param {Object} data - Datos a enviar
     * @param {Object} options - Opciones adicionales
     */
    refreshContent: async function(containerSelector, url, data = {}, options = {}) {
        const defaultOptions = {
            method: 'GET',
            preserveState: true,        // Preservar estados (fullscreen, collapse, etc.)
            showLoading: true,          // Mostrar indicador de carga
            loadingMessage: 'Cargando...',
            onSuccess: null,            // Callback después de éxito
            onError: null,              // Callback después de error
            replaceStrategy: 'replace', // 'replace' o 'html' (replace = reemplazar elemento completo)
            ...options
        };

        const container = document.querySelector(containerSelector);
        if (!container) {
            console.error(`AjaxHelpers: No se encontró el contenedor ${containerSelector}`);
            return;
        }

        // Preservar estado UI antes del refresh
        let preservedState = {};
        if (defaultOptions.preserveState) {
            preservedState = this._captureUIState(container);
        }

        // Mostrar loading
        if (defaultOptions.showLoading) {
            this._showLoadingInContainer(container, defaultOptions.loadingMessage);
        }

        try {
            // Hacer request
            const response = await this._makeRequest(url, {
                method: defaultOptions.method,
                data: data
            });

            // Actualizar contenido
            if (defaultOptions.replaceStrategy === 'replace') {
                // Reemplazar elemento completo
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = response;
                const newContainer = tempDiv.firstElementChild;

                if (newContainer) {
                    container.replaceWith(newContainer);

                    // Restaurar estado en el nuevo elemento
                    if (defaultOptions.preserveState) {
                        this._restoreUIState(newContainer, preservedState);
                    }
                } else {
                    console.error('AjaxHelpers: No se pudo parsear la respuesta HTML');
                }
            } else {
                // Solo reemplazar contenido interno
                container.innerHTML = response;

                // Restaurar estado
                if (defaultOptions.preserveState) {
                    this._restoreUIState(container, preservedState);
                }
            }

            // Callback success
            if (typeof defaultOptions.onSuccess === 'function') {
                defaultOptions.onSuccess(response);
            }

        } catch (error) {
            console.error('AjaxHelpers: Error en refresh', error);

            // Mostrar error al usuario
            if (window.NotificationManager) {
                NotificationManager.error('Error al actualizar el contenido');
            }

            // Callback error
            if (typeof defaultOptions.onError === 'function') {
                defaultOptions.onError(error);
            }
        } finally {
            // Ocultar loading
            if (defaultOptions.showLoading) {
                this._hideLoadingInContainer(container);
            }
        }
    },

    /**
     * Capturar estado de UI (fullscreen, collapse, selecciones, etc.)
     */
    _captureUIState: function(container) {
        const state = {};

        // Fullscreen state (Bootstrap Table)
        const bsTable = container.querySelector('.bootstrap-table');
        if (bsTable) {
            state.isFullscreen = bsTable.classList.contains('fullscreen');
        }

        // Collapse state
        const collapseElements = container.querySelectorAll('.collapse');
        state.collapseStates = {};
        collapseElements.forEach((el, index) => {
            state.collapseStates[index] = el.classList.contains('show');
        });

        // Scroll position
        state.scrollTop = container.scrollTop;
        state.scrollLeft = container.scrollLeft;

        return state;
    },

    /**
     * Restaurar estado de UI
     */
    _restoreUIState: function(container, state) {
        // Restaurar fullscreen
        if (state.isFullscreen) {
            const bsTable = container.querySelector('.bootstrap-table');
            if (bsTable) {
                bsTable.classList.add('fullscreen');
            }
        }

        // Restaurar collapse states
        if (state.collapseStates) {
            const collapseElements = container.querySelectorAll('.collapse');
            collapseElements.forEach((el, index) => {
                if (state.collapseStates[index]) {
                    el.classList.add('show');
                } else {
                    el.classList.remove('show');
                }
            });
        }

        // Restaurar scroll
        if (state.scrollTop !== undefined) {
            container.scrollTop = state.scrollTop;
        }
        if (state.scrollLeft !== undefined) {
            container.scrollLeft = state.scrollLeft;
        }
    },

    /**
     * Mostrar loading en contenedor
     */
    _showLoadingInContainer: function(container, message) {
        // Agregar clase loading
        container.classList.add('position-relative', 'ajax-loading');

        // Crear overlay
        const overlay = document.createElement('div');
        overlay.className = 'ajax-loading-overlay';
        overlay.innerHTML = `
            <div class="ajax-loading-content">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <div class="ajax-loading-message mt-2">${message}</div>
            </div>
        `;

        // Estilos inline (se sobrescribirán con CSS externo si existe)
        overlay.style.cssText = `
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        `;

        container.appendChild(overlay);
    },

    /**
     * Ocultar loading en contenedor
     */
    _hideLoadingInContainer: function(container) {
        container.classList.remove('ajax-loading');

        const overlay = container.querySelector('.ajax-loading-overlay');
        if (overlay) {
            overlay.remove();
        }
    },

    /**
     * Hacer request AJAX (compatible con jQuery y Fetch)
     */
    _makeRequest: async function(url, options = {}) {
        const defaultOptions = {
            method: 'GET',
            data: {},
            headers: {},
            ...options
        };

        // Usar jQuery si está disponible (para compatibilidad con código legacy)
        if (typeof jQuery !== 'undefined') {
            return new Promise((resolve, reject) => {
                jQuery.ajax({
                    url: url,
                    method: defaultOptions.method,
                    data: defaultOptions.data,
                    headers: defaultOptions.headers,
                    success: resolve,
                    error: (xhr, status, error) => {
                        reject(new Error(error || 'Error en request AJAX'));
                    }
                });
            });
        } else {
            // Usar Fetch API nativa
            const fetchOptions = {
                method: defaultOptions.method,
                headers: {
                    'Content-Type': 'application/json',
                    ...defaultOptions.headers
                }
            };

            if (defaultOptions.method !== 'GET' && defaultOptions.data) {
                fetchOptions.body = JSON.stringify(defaultOptions.data);
            }

            const response = await fetch(url, fetchOptions);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.text();
        }
    },

    /**
     * Descargar archivo via AJAX (POST con Blob)
     * @param {string} url - URL del endpoint
     * @param {Object} data - Datos a enviar (POST)
     * @param {string} filename - Nombre del archivo a descargar
     * @param {Object} options - Opciones adicionales
     */
    downloadFile: async function(url, data = {}, filename = 'download.pdf', options = {}) {
        const defaultOptions = {
            method: 'POST',
            showLoading: true,
            loadingMessage: 'Generando archivo...',
            onSuccess: null,
            onError: null,
            ...options
        };

        // Mostrar loading
        if (defaultOptions.showLoading) {
            if (window.NotificationManager) {
                const loadingToast = NotificationManager.loading(defaultOptions.loadingMessage);
                defaultOptions._loadingToast = loadingToast;
            }
        }

        try {
            // Hacer request con XMLHttpRequest para obtener blob
            const blob = await this._downloadBlob(url, data, defaultOptions.method);

            // Crear link temporal y descargar
            const downloadUrl = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(downloadUrl);

            // Notificar éxito
            if (window.NotificationManager) {
                // Cerrar loading toast
                if (defaultOptions._loadingToast) {
                    defaultOptions._loadingToast.hide();
                }
                NotificationManager.success('Archivo descargado correctamente');
            }

            // Callback
            if (typeof defaultOptions.onSuccess === 'function') {
                defaultOptions.onSuccess();
            }

        } catch (error) {
            console.error('AjaxHelpers: Error en descarga de archivo', error);

            // Notificar error
            if (window.NotificationManager) {
                // Cerrar loading toast
                if (defaultOptions._loadingToast) {
                    defaultOptions._loadingToast.hide();
                }
                NotificationManager.error('Error al descargar el archivo');
            }

            // Callback
            if (typeof defaultOptions.onError === 'function') {
                defaultOptions.onError(error);
            }
        }
    },

    /**
     * Descargar blob via XMLHttpRequest
     */
    _downloadBlob: function(url, data, method) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open(method, url, true);
            xhr.responseType = 'blob';
            xhr.setRequestHeader('Content-Type', 'application/json');

            xhr.onload = function() {
                if (this.status === 200) {
                    resolve(this.response);
                } else {
                    reject(new Error(`HTTP error! status: ${this.status}`));
                }
            };

            xhr.onerror = function() {
                reject(new Error('Network error'));
            };

            if (method === 'POST') {
                xhr.send(JSON.stringify(data));
            } else {
                xhr.send();
            }
        });
    },

    /**
     * Submit formulario via AJAX con validación
     * @param {string|HTMLFormElement} formSelector - Selector o elemento del form
     * @param {Object} options - Opciones
     */
    submitForm: async function(formSelector, options = {}) {
        const form = typeof formSelector === 'string'
            ? document.querySelector(formSelector)
            : formSelector;

        if (!form) {
            console.error('AjaxHelpers: No se encontró el formulario');
            return;
        }

        const defaultOptions = {
            url: form.action,
            method: form.method || 'POST',
            validate: true,
            showLoading: true,
            disableForm: true,
            onSuccess: null,
            onError: null,
            ...options
        };

        // Validación HTML5
        if (defaultOptions.validate && !form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Obtener datos
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        // Deshabilitar form
        if (defaultOptions.disableForm) {
            this._disableForm(form);
        }

        // Mostrar loading
        if (defaultOptions.showLoading && window.NotificationManager) {
            const loadingToast = NotificationManager.loading('Procesando...');
            defaultOptions._loadingToast = loadingToast;
        }

        try {
            const response = await this._makeRequest(defaultOptions.url, {
                method: defaultOptions.method,
                data: data
            });

            // Parsear respuesta JSON si es posible
            let parsedResponse;
            try {
                parsedResponse = JSON.parse(response);
            } catch (e) {
                parsedResponse = response;
            }

            // Notificar éxito
            if (window.NotificationManager) {
                if (defaultOptions._loadingToast) {
                    defaultOptions._loadingToast.hide();
                }
                NotificationManager.success('Operación completada');
            }

            // Callback
            if (typeof defaultOptions.onSuccess === 'function') {
                defaultOptions.onSuccess(parsedResponse);
            }

        } catch (error) {
            console.error('AjaxHelpers: Error en submit form', error);

            // Notificar error
            if (window.NotificationManager) {
                if (defaultOptions._loadingToast) {
                    defaultOptions._loadingToast.hide();
                }
                NotificationManager.error('Error al procesar la solicitud');
            }

            // Callback
            if (typeof defaultOptions.onError === 'function') {
                defaultOptions.onError(error);
            }

        } finally {
            // Re-habilitar form
            if (defaultOptions.disableForm) {
                this._enableForm(form);
            }
        }
    },

    /**
     * Deshabilitar formulario
     */
    _disableForm: function(form) {
        const elements = form.querySelectorAll('input, select, textarea, button');
        elements.forEach(el => {
            el.disabled = true;
            el.dataset.wasDisabled = el.disabled;
        });
    },

    /**
     * Habilitar formulario
     */
    _enableForm: function(form) {
        const elements = form.querySelectorAll('input, select, textarea, button');
        elements.forEach(el => {
            if (el.dataset.wasDisabled !== 'true') {
                el.disabled = false;
            }
        });
    }
};

// Exportar para uso en módulos ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AjaxHelpers;
}

// Agregar al objeto window para uso global
if (typeof window !== 'undefined') {
    window.AjaxHelpers = AjaxHelpers;
}
