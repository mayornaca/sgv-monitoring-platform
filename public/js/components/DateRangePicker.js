/**
 * DateRangePicker - Componente reutilizable para selección de rangos de fechas
 * Versión: 1.0.0
 *
 * Wrapper sobre Tempus Dominus 6 que proporciona:
 * - Configuración estándar
 * - Linking entre pickers (fecha inicio limita fecha fin)
 * - Validación de rango máximo
 * - Validación de rango mínimo
 * - Formato consistente
 * - Locale español
 *
 * Uso:
 * ```javascript
 * const dateRange = new DateRangePicker('#dtpFechaInicio', '#dtpFechaTermino', {
 *     maxDaysDiff: 7,
 *     format: 'dd-MM-yyyy HH:mm:ss'
 * });
 * ```
 */

class DateRangePicker {
    /**
     * Constructor
     * @param {string|HTMLElement} startSelector - Selector del input de fecha inicio
     * @param {string|HTMLElement} endSelector - Selector del input de fecha fin
     * @param {Object} options - Opciones de configuración
     */
    constructor(startSelector, endSelector, options = {}) {
        // Obtener elementos
        this.startElement = typeof startSelector === 'string'
            ? document.querySelector(startSelector)
            : startSelector;

        this.endElement = typeof endSelector === 'string'
            ? document.querySelector(endSelector)
            : endSelector;

        if (!this.startElement || !this.endElement) {
            console.error('DateRangePicker: No se encontraron los elementos start/end');
            return;
        }

        // Configuración por defecto
        this.options = {
            // Formato
            format: 'dd-MM-yyyy HH:mm:ss',
            locale: 'es',

            // Validación
            maxDaysDiff: null,        // Máximo de días entre fechas (null = sin límite)
            minDaysDiff: null,        // Mínimo de días entre fechas (null = sin límite)
            maxDate: null,            // Fecha máxima permitida
            minDate: null,            // Fecha mínima permitida
            allowSameDay: true,       // Permitir que inicio y fin sean el mismo día

            // Componentes visibles
            showTime: true,           // Mostrar selector de tiempo
            showSeconds: true,        // Mostrar segundos
            showCalendar: true,       // Mostrar calendario

            // Modo month picker (solo mes/año, sin días)
            monthPickerMode: false,

            // Callbacks
            onStartChange: null,      // function(date) - Al cambiar fecha inicio
            onEndChange: null,        // function(date) - Al cambiar fecha fin
            onValidationError: null,  // function(error) - Al detectar error de validación

            // Linking
            linkPickers: true,        // Vincular pickers (inicio limita fin y viceversa)

            // Tempus Dominus options override
            tempusDominusOptions: {},

            ...options
        };

        // Estado interno
        this.state = {
            pickerStart: null,
            pickerEnd: null,
            startDate: null,
            endDate: null
        };

        // Inicializar
        this.init();
    }

    /**
     * Inicialización
     */
    init() {
        // Configuración base de Tempus Dominus
        const baseOptions = this._getTempusDominusBaseOptions();

        // Crear picker de inicio
        this.state.pickerStart = new tempusDominus.TempusDominus(
            this.startElement,
            {
                ...baseOptions,
                ...this.options.tempusDominusOptions
            }
        );

        // Crear picker de fin
        this.state.pickerEnd = new tempusDominus.TempusDominus(
            this.endElement,
            {
                ...baseOptions,
                ...this.options.tempusDominusOptions
            }
        );

        // Aplicar restricciones iniciales
        if (this.options.minDate) {
            this.setMinDate(this.options.minDate);
        }
        if (this.options.maxDate) {
            this.setMaxDate(this.options.maxDate);
        }

        // Subscribirse a cambios
        this._attachEventListeners();

        // Leer valores iniciales si existen
        this._readInitialValues();
    }

    /**
     * Obtener configuración base de Tempus Dominus
     */
    _getTempusDominusBaseOptions() {
        if (this.options.monthPickerMode) {
            // Modo month picker
            return {
                display: {
                    viewMode: 'months',
                    components: {
                        calendar: true,
                        date: false,
                        month: true,
                        year: true,
                        decades: true,
                        clock: false,
                        hours: false,
                        minutes: false,
                        seconds: false
                    },
                    buttons: {
                        today: true,
                        clear: true,
                        close: true
                    }
                },
                localization: {
                    locale: this.options.locale,
                    format: 'MM-yyyy',
                    hourCycle: 'h23'
                },
                restrictions: {
                    daysOfWeekDisabled: []
                }
            };
        } else {
            // Modo normal (con fecha y hora)
            return {
                display: {
                    components: {
                        calendar: this.options.showCalendar,
                        date: true,
                        month: true,
                        year: true,
                        decades: true,
                        clock: this.options.showTime,
                        hours: this.options.showTime,
                        minutes: this.options.showTime,
                        seconds: this.options.showTime && this.options.showSeconds
                    },
                    buttons: {
                        today: true,
                        clear: true,
                        close: true
                    }
                },
                localization: {
                    locale: this.options.locale,
                    format: this.options.format,
                    hourCycle: 'h23'
                },
                restrictions: {
                    daysOfWeekDisabled: []
                }
            };
        }
    }

    /**
     * Adjuntar event listeners
     */
    _attachEventListeners() {
        // Cambio en fecha inicio
        this.state.pickerStart.subscribe(tempusDominus.Namespace.events.change, (e) => {
            this._handleStartChange(e);
        });

        // Cambio en fecha fin
        this.state.pickerEnd.subscribe(tempusDominus.Namespace.events.change, (e) => {
            this._handleEndChange(e);
        });
    }

    /**
     * Manejar cambio en fecha inicio
     */
    _handleStartChange(e) {
        if (e.date) {
            this.state.startDate = e.date;

            // Linking: actualizar restricciones de fecha fin
            if (this.options.linkPickers) {
                this.state.pickerEnd.updateOptions({
                    restrictions: {
                        minDate: e.date
                    }
                });
            }

            // Validar rango
            if (this.state.endDate) {
                const validation = this._validateRange();
                if (!validation.valid) {
                    this._handleValidationError(validation.error);
                }
            }

            // Callback
            if (typeof this.options.onStartChange === 'function') {
                this.options.onStartChange(e.date);
            }
        }
    }

    /**
     * Manejar cambio en fecha fin
     */
    _handleEndChange(e) {
        if (e.date) {
            this.state.endDate = e.date;

            // Linking: actualizar restricciones de fecha inicio
            if (this.options.linkPickers) {
                this.state.pickerStart.updateOptions({
                    restrictions: {
                        maxDate: e.date
                    }
                });
            }

            // Validar rango
            if (this.state.startDate) {
                const validation = this._validateRange();
                if (!validation.valid) {
                    this._handleValidationError(validation.error);
                }
            }

            // Callback
            if (typeof this.options.onEndChange === 'function') {
                this.options.onEndChange(e.date);
            }
        }
    }

    /**
     * Validar rango de fechas
     */
    _validateRange() {
        if (!this.state.startDate || !this.state.endDate) {
            return { valid: true };
        }

        const start = moment(this.state.startDate);
        const end = moment(this.state.endDate);

        // Validar que inicio sea menor o igual a fin
        if (start.isAfter(end)) {
            return {
                valid: false,
                error: {
                    type: 'invalid_range',
                    message: 'La fecha de inicio debe ser menor o igual a la fecha de fin'
                }
            };
        }

        // Validar mismo día si no está permitido
        if (!this.options.allowSameDay && start.isSame(end, 'day')) {
            return {
                valid: false,
                error: {
                    type: 'same_day_not_allowed',
                    message: 'La fecha de inicio y fin no pueden ser el mismo día'
                }
            };
        }

        // Validar diferencia máxima de días
        if (this.options.maxDaysDiff !== null) {
            const daysDiff = end.diff(start, 'days');
            if (daysDiff > this.options.maxDaysDiff) {
                return {
                    valid: false,
                    error: {
                        type: 'max_days_exceeded',
                        message: `El rango máximo permitido es de ${this.options.maxDaysDiff} días`,
                        maxDays: this.options.maxDaysDiff,
                        currentDays: daysDiff
                    }
                };
            }
        }

        // Validar diferencia mínima de días
        if (this.options.minDaysDiff !== null) {
            const daysDiff = end.diff(start, 'days');
            if (daysDiff < this.options.minDaysDiff) {
                return {
                    valid: false,
                    error: {
                        type: 'min_days_not_met',
                        message: `El rango mínimo requerido es de ${this.options.minDaysDiff} días`,
                        minDays: this.options.minDaysDiff,
                        currentDays: daysDiff
                    }
                };
            }
        }

        return { valid: true };
    }

    /**
     * Manejar error de validación
     */
    _handleValidationError(error) {
        console.warn('DateRangePicker: Error de validación', error);

        // Si hay maxDaysDiff, ajustar fecha fin automáticamente
        if (error.type === 'max_days_exceeded' && this.options.maxDaysDiff !== null) {
            const newEndDate = moment(this.state.startDate)
                .add(this.options.maxDaysDiff, 'days')
                .toDate();

            this.setEndDate(newEndDate);

            // Mostrar mensaje al usuario
            if (typeof this.options.onValidationError === 'function') {
                this.options.onValidationError(error);
            } else if (window.NotificationManager) {
                window.NotificationManager.warning(error.message);
            } else {
                alert(error.message);
            }
        }

        // Callback custom
        if (typeof this.options.onValidationError === 'function') {
            this.options.onValidationError(error);
        }
    }

    /**
     * Leer valores iniciales de los inputs
     */
    _readInitialValues() {
        const startInput = this.startElement.querySelector('input');
        const endInput = this.endElement.querySelector('input');

        if (startInput && startInput.value) {
            const parsedDate = moment(startInput.value, this.options.format);
            if (parsedDate.isValid()) {
                this.state.startDate = parsedDate.toDate();
            }
        }

        if (endInput && endInput.value) {
            const parsedDate = moment(endInput.value, this.options.format);
            if (parsedDate.isValid()) {
                this.state.endDate = parsedDate.toDate();
            }
        }
    }

    /**
     * Establecer fecha de inicio
     */
    setStartDate(date) {
        const momentDate = moment(date);
        if (momentDate.isValid()) {
            this.state.pickerStart.dates.setValue(momentDate.toDate());
        }
    }

    /**
     * Establecer fecha de fin
     */
    setEndDate(date) {
        const momentDate = moment(date);
        if (momentDate.isValid()) {
            this.state.pickerEnd.dates.setValue(momentDate.toDate());
        }
    }

    /**
     * Obtener fecha de inicio
     */
    getStartDate() {
        return this.state.startDate;
    }

    /**
     * Obtener fecha de fin
     */
    getEndDate() {
        return this.state.endDate;
    }

    /**
     * Obtener fecha de inicio como string formateado
     */
    getStartDateFormatted() {
        if (this.state.startDate) {
            return moment(this.state.startDate).format(this.options.format);
        }
        return null;
    }

    /**
     * Obtener fecha de fin como string formateado
     */
    getEndDateFormatted() {
        if (this.state.endDate) {
            return moment(this.state.endDate).format(this.options.format);
        }
        return null;
    }

    /**
     * Establecer fecha mínima para ambos pickers
     */
    setMinDate(date) {
        const momentDate = moment(date);
        if (momentDate.isValid()) {
            this.state.pickerStart.updateOptions({
                restrictions: {
                    minDate: momentDate.toDate()
                }
            });
            this.state.pickerEnd.updateOptions({
                restrictions: {
                    minDate: momentDate.toDate()
                }
            });
        }
    }

    /**
     * Establecer fecha máxima para ambos pickers
     */
    setMaxDate(date) {
        const momentDate = moment(date);
        if (momentDate.isValid()) {
            this.state.pickerStart.updateOptions({
                restrictions: {
                    maxDate: momentDate.toDate()
                }
            });
            this.state.pickerEnd.updateOptions({
                restrictions: {
                    maxDate: momentDate.toDate()
                }
            });
        }
    }

    /**
     * Limpiar fecha de inicio
     */
    clearStartDate() {
        this.state.pickerStart.dates.clear();
        this.state.startDate = null;
    }

    /**
     * Limpiar fecha de fin
     */
    clearEndDate() {
        this.state.pickerEnd.dates.clear();
        this.state.endDate = null;
    }

    /**
     * Limpiar ambas fechas
     */
    clear() {
        this.clearStartDate();
        this.clearEndDate();
    }

    /**
     * Validar rango actual
     */
    validate() {
        return this._validateRange();
    }

    /**
     * Habilitar pickers
     */
    enable() {
        this.state.pickerStart.enable();
        this.state.pickerEnd.enable();
    }

    /**
     * Deshabilitar pickers
     */
    disable() {
        this.state.pickerStart.disable();
        this.state.pickerEnd.disable();
    }

    /**
     * Destruir componente
     */
    destroy() {
        if (this.state.pickerStart) {
            this.state.pickerStart.dispose();
        }
        if (this.state.pickerEnd) {
            this.state.pickerEnd.dispose();
        }

        this.startElement = null;
        this.endElement = null;
        this.state = null;
    }
}

// Exportar para uso en módulos ES6
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DateRangePicker;
}

// Agregar al objeto window para uso global
if (typeof window !== 'undefined') {
    window.DateRangePicker = DateRangePicker;
}
