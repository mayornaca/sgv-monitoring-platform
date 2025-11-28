/**
 * FilterPanel - Componente reutilizable para paneles de filtros
 * Versión: 1.0.0
 *
 * Proporciona funcionalidad estándar para todos los paneles de filtros:
 * - Manejo de formularios
 * - Auto-actualización configurable
 * - AJAX loading states
 * - Validación
 * - Preservación de estado
 * - Callbacks personalizables
 *
 * Uso:
 * ```javascript
 * const filterPanel = new FilterPanel('#filter-panel', {
 *     autoUpdate: true,
 *     updateInterval: 60000,
 *     onFilter: (data) => { ... },
 *     onReset: () => { ... }
 * });
 * ```
 */

class FilterPanel {
    /**
     * Constructor
     * @param {string|HTMLElement} selector - Selector CSS o elemento DOM del panel
     * @param {Object} options - Opciones de configuración
     */
    constructor(selector, options = {}) {
        // Obtener elemento
        this.element = typeof selector === 'string'
            ? document.querySelector(selector)
            : selector;

        if (!this.element) {
            console.error(`FilterPanel: No se encontró el elemento con selector "${selector}"`);
            return;
        }

        // Configuración por defecto
        this.options = {
            // Auto-actualización
            autoUpdate: false,
            updateInterval: 60000, // 60 segundos
            autoUpdateOnInit: false,

            // Selectores
            formSelector: 'form',
            submitButtonSelector: '[type="submit"]',
            resetButtonSelector: '[type="reset"]',
            autoUpdateSwitchSelector: '#autoUpdateSwitch',
            updateIntervalInputSelector: '#updateInterval',

            // Callbacks
            onFilter: null,      // function(formData) - Al aplicar filtros
            onReset: null,       // function() - Al resetear filtros
            onAutoUpdate: null,  // function(formData) - En cada auto-actualización
            onValidate: null,    // function(formData) - Validación custom, retorna {valid: bool, errors: []}

            // Estados
            preserveState: true, // Preservar estado en localStorage
            stateKey: null,      // Clave para localStorage (auto-generado si null)

            // UI
            showLoadingOverlay: true,
            disableFormOnSubmit: true,
            collapseOnSubmit: false,

            // Validación
            validateOnSubmit: true,
            showValidationErrors: true,

            ...options
        };

        // Estado interno
        this.state = {
            isLoading: false,
            isAutoUpdating: false,
            autoUpdateTimer: null,
            lastSubmitData: null
        };

        // Inicializar
        this.init();
    }

    /**
     * Inicialización del componente
     */
    init() {
        // Buscar elementos
        this.form = this.element.querySelector(this.options.formSelector);
        if (!this.form) {
            console.error('FilterPanel: No se encontró el formulario dentro del panel');
            return;
        }

        this.submitButton = this.form.querySelector(this.options.submitButtonSelector);
        this.resetButton = this.form.querySelector(this.options.resetButtonSelector);
        this.autoUpdateSwitch = this.form.querySelector(this.options.autoUpdateSwitchSelector);
        this.updateIntervalInput = this.form.querySelector(this.options.updateIntervalInputSelector);

        // Generar stateKey si no existe
        if (this.options.preserveState && !this.options.stateKey) {
            this.options.stateKey = `filterPanel_${this.element.id || this._generateUniqueId()}`;
        }

        // Event listeners
        this._attachEventListeners();

        // Restaurar estado si está habilitado
        if (this.options.preserveState) {
            this.restoreState();
        }

        // Auto-update inicial si está configurado
        if (this.options.autoUpdate && this.options.autoUpdateOnInit) {
            this.startAutoUpdate();
        }
    }

    /**
     * Adjuntar event listeners
     */
    _attachEventListeners() {
        // Submit del formulario
        this.form.addEventListener('submit', (e) => this._handleSubmit(e));

        // Reset del formulario
        if (this.resetButton) {
            this.resetButton.addEventListener('click', (e) => this._handleReset(e));
        }

        // Auto-update switch
        if (this.autoUpdateSwitch) {
            this.autoUpdateSwitch.addEventListener('change', (e) => {
                if (e.target.checked) {
                    this.startAutoUpdate();
                } else {
                    this.stopAutoUpdate();
                }
            });
        }

        // Update interval change
        if (this.updateIntervalInput) {
            this.updateIntervalInput.addEventListener('change', (e) => {
                const newInterval = parseInt(e.target.value) * 1000;
                if (newInterval >= 5000) { // Mínimo 5 segundos
                    this.options.updateInterval = newInterval;

                    // Reiniciar auto-update con nuevo intervalo
                    if (this.state.isAutoUpdating) {
                        this.stopAutoUpdate();
                        this.startAutoUpdate();
                    }
                }
            });
        }

        // Visibility API - pausar auto-update cuando tab no está visible
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && this.state.isAutoUpdating) {
                this.stopAutoUpdate();
                this._wasAutoUpdatingBeforeHidden = true;
            } else if (!document.hidden && this._wasAutoUpdatingBeforeHidden) {
                this.startAutoUpdate();
                this._wasAutoUpdatingBeforeHidden = false;
            }
        });
    }

    /**
     * Manejar submit del formulario
     */
    _handleSubmit(e) {
        e.preventDefault();

        // Obtener datos del formulario
        const formData = new FormData(this.form);
        const formDataObject = Object.fromEntries(formData);

        // Validación
        if (this.options.validateOnSubmit) {
            const validation = this._validate(formDataObject);
            if (!validation.valid) {
                if (this.options.showValidationErrors) {
                    this._showValidationErrors(validation.errors);
                }
                return;
            }
        }

        // Limpiar errores previos
        this._clearValidationErrors();

        // Guardar estado
        if (this.options.preserveState) {
            this.saveState(formDataObject);
        }

        // Loading state
        if (this.options.showLoadingOverlay) {
            this.showLoading();
        }

        if (this.options.disableFormOnSubmit) {
            this.disableForm();
        }

        // Guardar última data
        this.state.lastSubmitData = formDataObject;

        // Callback
        if (typeof this.options.onFilter === 'function') {
            Promise.resolve(this.options.onFilter(formDataObject))
                .finally(() => {
                    this.hideLoading();
                    this.enableForm();

                    // Colapsar si está configurado
                    if (this.options.collapseOnSubmit) {
                        this.collapse();
                    }
                });
        } else {
            this.hideLoading();
            this.enableForm();
        }
    }

    /**
     * Manejar reset del formulario
     */
    _handleReset(e) {
        e.preventDefault();

        // Reset form
        this.form.reset();

        // Limpiar errores
        this._clearValidationErrors();

        // Limpiar estado guardado
        if (this.options.preserveState) {
            this.clearState();
        }

        // Callback
        if (typeof this.options.onReset === 'function') {
            this.options.onReset();
        }

        // Trigger submit automático después de reset
        setTimeout(() => {
            this.form.dispatchEvent(new Event('submit', { cancelable: true }));
        }, 100);
    }

    /**
     * Validar datos del formulario
     */
    _validate(formData) {
        const errors = [];

        // Validación custom
        if (typeof this.options.onValidate === 'function') {
            const customValidation = this.options.onValidate(formData);
            if (customValidation && !customValidation.valid) {
                errors.push(...customValidation.errors);
            }
        }

        // Validación HTML5
        const invalidFields = this.form.querySelectorAll(':invalid');
        invalidFields.forEach(field => {
            if (field.validationMessage) {
                errors.push({
                    field: field.name,
                    message: field.validationMessage
                });
            }
        });

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    /**
     * Mostrar errores de validación
     */
    _showValidationErrors(errors) {
        errors.forEach(error => {
            const field = this.form.querySelector(`[name="${error.field}"]`);
            if (field) {
                field.classList.add('is-invalid');

                // Crear mensaje de error si no existe
                let errorElement = field.parentElement.querySelector('.invalid-feedback');
                if (!errorElement) {
                    errorElement = document.createElement('div');
                    errorElement.className = 'invalid-feedback';
                    field.parentElement.appendChild(errorElement);
                }
                errorElement.textContent = error.message;
            }
        });
    }

    /**
     * Limpiar errores de validación
     */
    _clearValidationErrors() {
        this.form.querySelectorAll('.is-invalid').forEach(field => {
            field.classList.remove('is-invalid');
        });
        this.form.querySelectorAll('.invalid-feedback').forEach(error => {
            error.textContent = '';
        });
    }

    /**
     * Iniciar auto-actualización
     */
    startAutoUpdate() {
        if (this.state.autoUpdateTimer) {
            clearInterval(this.state.autoUpdateTimer);
        }

        this.state.isAutoUpdating = true;

        this.state.autoUpdateTimer = setInterval(() => {
            const formData = new FormData(this.form);
            const formDataObject = Object.fromEntries(formData);

            if (typeof this.options.onAutoUpdate === 'function') {
                this.options.onAutoUpdate(formDataObject);
            } else if (typeof this.options.onFilter === 'function') {
                this.options.onFilter(formDataObject);
            }
        }, this.options.updateInterval);

        console.log(`FilterPanel: Auto-update iniciado (intervalo: ${this.options.updateInterval}ms)`);
    }

    /**
     * Detener auto-actualización
     */
    stopAutoUpdate() {
        if (this.state.autoUpdateTimer) {
            clearInterval(this.state.autoUpdateTimer);
            this.state.autoUpdateTimer = null;
        }
        this.state.isAutoUpdating = false;

        console.log('FilterPanel: Auto-update detenido');
    }

    /**
     * Mostrar loading overlay
     */
    showLoading() {
        this.state.isLoading = true;
        this.element.classList.add('loading');

        // Crear overlay si no existe
        if (!this.loadingOverlay) {
            this.loadingOverlay = document.createElement('div');
            this.loadingOverlay.className = 'filter-panel-loading-overlay';
            this.loadingOverlay.innerHTML = `
                <div class="spinner-border filter-panel-loading-spinner" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
            `;
            this.element.style.position = 'relative';
            this.element.appendChild(this.loadingOverlay);
        } else {
            this.loadingOverlay.style.display = 'flex';
        }
    }

    /**
     * Ocultar loading overlay
     */
    hideLoading() {
        this.state.isLoading = false;
        this.element.classList.remove('loading');

        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = 'none';
        }
    }

    /**
     * Deshabilitar formulario
     */
    disableForm() {
        const elements = this.form.querySelectorAll('input, select, textarea, button');
        elements.forEach(el => el.disabled = true);
    }

    /**
     * Habilitar formulario
     */
    enableForm() {
        const elements = this.form.querySelectorAll('input, select, textarea, button');
        elements.forEach(el => el.disabled = false);
    }

    /**
     * Colapsar panel
     */
    collapse() {
        const collapseElement = this.element.querySelector('.collapse');
        if (collapseElement) {
            const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) ||
                               new bootstrap.Collapse(collapseElement, { toggle: false });
            bsCollapse.hide();
        }
    }

    /**
     * Expandir panel
     */
    expand() {
        const collapseElement = this.element.querySelector('.collapse');
        if (collapseElement) {
            const bsCollapse = bootstrap.Collapse.getInstance(collapseElement) ||
                               new bootstrap.Collapse(collapseElement, { toggle: false });
            bsCollapse.show();
        }
    }

    /**
     * Guardar estado en localStorage
     */
    saveState(data) {
        try {
            localStorage.setItem(this.options.stateKey, JSON.stringify(data));
        } catch (e) {
            console.warn('FilterPanel: Error al guardar estado', e);
        }
    }

    /**
     * Restaurar estado desde localStorage
     */
    restoreState() {
        try {
            const savedState = localStorage.getItem(this.options.stateKey);
            if (savedState) {
                const data = JSON.parse(savedState);

                // Restaurar valores en el formulario
                Object.keys(data).forEach(key => {
                    const field = this.form.querySelector(`[name="${key}"]`);
                    if (field) {
                        if (field.type === 'checkbox') {
                            field.checked = data[key] === 'on' || data[key] === true;
                        } else if (field.type === 'radio') {
                            if (field.value === data[key]) {
                                field.checked = true;
                            }
                        } else {
                            field.value = data[key];
                        }

                        // Trigger change event para selectpickers, etc.
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            }
        } catch (e) {
            console.warn('FilterPanel: Error al restaurar estado', e);
        }
    }

    /**
     * Limpiar estado guardado
     */
    clearState() {
        try {
            localStorage.removeItem(this.options.stateKey);
        } catch (e) {
            console.warn('FilterPanel: Error al limpiar estado', e);
        }
    }

    /**
     * Obtener datos actuales del formulario
     */
    getFormData() {
        const formData = new FormData(this.form);
        return Object.fromEntries(formData);
    }

    /**
     * Establecer datos en el formulario
     */
    setFormData(data) {
        Object.keys(data).forEach(key => {
            const field = this.form.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = data[key];
                } else {
                    field.value = data[key];
                }
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    }

    /**
     * Trigger submit programáticamente
     */
    submit() {
        this.form.dispatchEvent(new Event('submit', { cancelable: true }));
    }

    /**
     * Reset programático
     */
    reset() {
        this.form.dispatchEvent(new Event('reset', { cancelable: true }));
    }

    /**
     * Destruir componente
     */
    destroy() {
        // Detener auto-update
        this.stopAutoUpdate();

        // Remover event listeners (recreando el form para limpiar todos los listeners)
        const newForm = this.form.cloneNode(true);
        this.form.parentNode.replaceChild(newForm, this.form);

        // Limpiar referencias
        this.element = null;
        this.form = null;
        this.submitButton = null;
        this.resetButton = null;
        this.autoUpdateSwitch = null;
        this.updateIntervalInput = null;
        this.loadingOverlay = null;
    }

    /**
     * Generar ID único
     */
    _generateUniqueId() {
        return 'fp_' + Math.random().toString(36).substr(2, 9);
    }
}

// Exportar para uso en módulos ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FilterPanel;
}

// Agregar al objeto window para uso global
if (typeof window !== 'undefined') {
    window.FilterPanel = FilterPanel;
}
