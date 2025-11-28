/**
 * NotificationManager - Sistema unificado de notificaciones
 * Versión: 1.0.0
 *
 * Proporciona API consistente para mostrar notificaciones usando Bootstrap 5 Toasts.
 * Reemplaza sistemas legacy como $.notify() con una interfaz moderna y unificada.
 *
 * Tipos de notificación:
 * - success: Operaciones exitosas
 * - error: Errores críticos
 * - warning: Advertencias
 * - info: Información general
 *
 * Uso:
 * ```javascript
 * NotificationManager.success('Operación completada');
 * NotificationManager.error('Error al guardar');
 * NotificationManager.warning('Atención: datos incompletos');
 * NotificationManager.info('Procesando solicitud...');
 * ```
 */

class NotificationManager {
    /**
     * Inicializar el manager (crear contenedor de toasts si no existe)
     */
    static init() {
        if (!this.initialized) {
            this._createToastContainer();
            this.initialized = true;
        }
    }

    /**
     * Crear contenedor de toasts
     */
    static _createToastContainer() {
        // Verificar si ya existe
        if (document.getElementById('toast-container')) {
            return;
        }

        // Crear contenedor
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    /**
     * Obtener contenedor de toasts
     */
    static _getToastContainer() {
        this.init();
        return document.getElementById('toast-container');
    }

    /**
     * Mostrar notificación
     * @param {string} message - Mensaje a mostrar
     * @param {Object} options - Opciones de configuración
     */
    static show(message, options = {}) {
        const defaultOptions = {
            type: 'info',           // success, error, warning, info
            title: null,            // Título del toast (null = auto-generado)
            duration: 5000,         // Duración en ms (0 = no auto-cerrar)
            closeable: true,        // Mostrar botón cerrar
            position: 'top-right',  // Posición (no implementado aún, siempre top-right)
            icon: true,             // Mostrar icono
            sound: false,           // Reproducir sonido (no implementado)
            ...options
        };

        // Auto-generar título si no se proporciona
        if (!defaultOptions.title) {
            defaultOptions.title = this._getTitleForType(defaultOptions.type);
        }

        // Crear toast
        const toastElement = this._createToastElement(message, defaultOptions);

        // Agregar al contenedor
        const container = this._getToastContainer();
        container.appendChild(toastElement);

        // Inicializar Bootstrap Toast
        const bsToast = new bootstrap.Toast(toastElement, {
            autohide: defaultOptions.duration > 0,
            delay: defaultOptions.duration
        });

        // Mostrar toast
        bsToast.show();

        // Eliminar del DOM después de que se oculte
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });

        return bsToast;
    }

    /**
     * Crear elemento toast
     */
    static _createToastElement(message, options) {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');

        // Agregar clase de color según tipo
        const colorClass = this._getColorClassForType(options.type);
        toast.classList.add(`bg-${colorClass}`, 'text-white');

        // Construir contenido
        let toastContent = `
            <div class="d-flex">
                <div class="toast-body">
        `;

        // Título si existe
        if (options.title) {
            toastContent += `
                <div class="d-flex align-items-center mb-1">
                    ${options.icon ? this._getIconForType(options.type) : ''}
                    <strong class="me-auto">${this._escapeHtml(options.title)}</strong>
                </div>
            `;
        } else if (options.icon) {
            toastContent += `${this._getIconForType(options.type)} `;
        }

        // Mensaje
        toastContent += `
                    <div>${this._escapeHtml(message)}</div>
                </div>
        `;

        // Botón cerrar si está habilitado
        if (options.closeable) {
            toastContent += `
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            `;
        }

        toastContent += `
            </div>
        `;

        toast.innerHTML = toastContent;

        return toast;
    }

    /**
     * Obtener título por defecto según tipo
     */
    static _getTitleForType(type) {
        const titles = {
            success: 'Éxito',
            error: 'Error',
            warning: 'Advertencia',
            info: 'Información'
        };
        return titles[type] || 'Notificación';
    }

    /**
     * Obtener clase de color según tipo
     */
    static _getColorClassForType(type) {
        const colors = {
            success: 'success',
            error: 'danger',
            warning: 'warning',
            info: 'info'
        };
        return colors[type] || 'secondary';
    }

    /**
     * Obtener icono según tipo
     */
    static _getIconForType(type) {
        const icons = {
            success: '<i class="fas fa-check-circle me-2"></i>',
            error: '<i class="fas fa-times-circle me-2"></i>',
            warning: '<i class="fas fa-exclamation-triangle me-2"></i>',
            info: '<i class="fas fa-info-circle me-2"></i>'
        };
        return icons[type] || '';
    }

    /**
     * Escapar HTML para prevenir XSS
     */
    static _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Mostrar notificación de éxito
     */
    static success(message, options = {}) {
        return this.show(message, { ...options, type: 'success' });
    }

    /**
     * Mostrar notificación de error
     */
    static error(message, options = {}) {
        return this.show(message, { ...options, type: 'error', duration: 0 }); // Errores no se auto-cierran
    }

    /**
     * Mostrar notificación de advertencia
     */
    static warning(message, options = {}) {
        return this.show(message, { ...options, type: 'warning', duration: 7000 }); // Warnings duran más
    }

    /**
     * Mostrar notificación de información
     */
    static info(message, options = {}) {
        return this.show(message, { ...options, type: 'info' });
    }

    /**
     * Limpiar todas las notificaciones visibles
     */
    static clearAll() {
        const container = this._getToastContainer();
        const toasts = container.querySelectorAll('.toast');

        toasts.forEach(toast => {
            const bsToast = bootstrap.Toast.getInstance(toast);
            if (bsToast) {
                bsToast.hide();
            } else {
                toast.remove();
            }
        });
    }

    /**
     * Mostrar notificación de carga (con spinner)
     */
    static loading(message, options = {}) {
        const loadingMessage = `
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <span>${message}</span>
            </div>
        `;

        return this.show(loadingMessage, {
            ...options,
            type: 'info',
            duration: 0,        // No auto-cerrar
            closeable: false,   // No cerrable manualmente
            icon: false,        // Sin icono (usamos spinner)
            title: options.title || 'Procesando'
        });
    }

    /**
     * Mostrar notificación de confirmación (con botones)
     * NOTA: Para confirmaciones reales, usar modals de Bootstrap en su lugar
     */
    static confirm(message, onConfirm, onCancel, options = {}) {
        console.warn('NotificationManager.confirm() no está implementado. Use modals de Bootstrap para confirmaciones.');

        // Alternativa simple: usar confirm() nativo
        if (confirm(message)) {
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        } else {
            if (typeof onCancel === 'function') {
                onCancel();
            }
        }
    }
}

// Inicialización automática
NotificationManager.initialized = false;

// Compatibility layer para $.notify() legacy
// Permite reemplazar $.notify() sin modificar código existente
if (typeof window !== 'undefined' && typeof jQuery !== 'undefined') {
    // Sobrescribir $.notify si existe
    if (jQuery.notify) {
        console.info('NotificationManager: Reemplazando $.notify() legacy');
    }

    jQuery.notify = function(message, type = 'info') {
        // Mapear tipos de $.notify a NotificationManager
        const typeMap = {
            'success': 'success',
            'error': 'error',
            'warn': 'warning',
            'warning': 'warning',
            'info': 'info',
            'danger': 'error'
        };

        const mappedType = typeMap[type] || 'info';
        NotificationManager.show(message, { type: mappedType });
    };

    // Alias para $.notify
    jQuery.fn.notify = function(message, type) {
        jQuery.notify(message, type);
        return this;
    };
}

// Exportar para uso en módulos ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NotificationManager;
}

// Agregar al objeto window para uso global
if (typeof window !== 'undefined') {
    window.NotificationManager = NotificationManager;

    // Auto-inicializar cuando el DOM esté listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            NotificationManager.init();
        });
    } else {
        NotificationManager.init();
    }
}
