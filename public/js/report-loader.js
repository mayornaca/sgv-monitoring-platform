/**
 * Loader estandarizado para reportes AJAX
 * Basado en Bootstrap 5 Spinners (oficial)
 * Documentación: https://getbootstrap.com/docs/5.3/components/spinners/
 */

const ReportLoader = {
    /**
     * Muestra loader en un botón o elemento
     * @param {string} selector - Selector jQuery del elemento
     * @param {string} text - Texto a mostrar durante carga (default: 'Generando...')
     */
    show(selector, text = 'Generando...') {
        const $element = $(selector);

        if ($element.length === 0) {
            console.warn('ReportLoader: Elemento no encontrado:', selector);
            return;
        }

        $element
            .prop('disabled', true)
            .data('report-loader-original-html', $element.html())
            .html(`
                <span class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </span>
                ${text}
            `);
    },

    /**
     * Oculta loader y restaura contenido original
     * @param {string} selector - Selector jQuery del elemento
     */
    hide(selector) {
        const $element = $(selector);

        if ($element.length === 0) {
            console.warn('ReportLoader: Elemento no encontrado:', selector);
            return;
        }

        const originalHtml = $element.data('report-loader-original-html');

        if (originalHtml) {
            $element
                .prop('disabled', false)
                .html(originalHtml)
                .removeData('report-loader-original-html');
        }
    },

    /**
     * Muestra loader overlay en contenedor
     * @param {string} selector - Selector del contenedor
     * @param {string} text - Texto a mostrar (default: 'Cargando...')
     */
    showOverlay(selector, text = 'Cargando...') {
        const $container = $(selector);

        if ($container.length === 0) {
            console.warn('ReportLoader: Contenedor no encontrado:', selector);
            return;
        }

        const overlayHtml = `
            <div class="report-loader-overlay" style="
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
            ">
                <div class="text-center">
                    <div class="spinner-border text-primary mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div>${text}</div>
                </div>
            </div>
        `;

        $container.css('position', 'relative').append(overlayHtml);
    },

    /**
     * Oculta loader overlay
     * @param {string} selector - Selector del contenedor
     */
    hideOverlay(selector) {
        const $container = $(selector);

        if ($container.length === 0) {
            console.warn('ReportLoader: Contenedor no encontrado:', selector);
            return;
        }

        $container.find('.report-loader-overlay').remove();
    },

    /**
     * Muestra loader en botón con descarga directa (link)
     * Para descargas via <a href>, el loader se oculta automáticamente después de un timeout
     * @param {string} selector - Selector del botón/link
     * @param {string} text - Texto a mostrar (default: 'Descargando...')
     * @param {number} duration - Duración del loader en ms (default: 2000)
     */
    showForDirectDownload(selector, text = 'Descargando...', duration = 2000) {
        this.show(selector, text);

        setTimeout(() => {
            this.hide(selector);
        }, duration);
    }
};

// Exportar para uso global
window.ReportLoader = ReportLoader;
