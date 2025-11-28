/*
 * Welcome to your app's main JavaScript file!
 * All frontend dependencies are bundled here via Webpack Encore
 */

// import './bootstrap.js';  // Temporalmente comentado - causa error de compilación
import './styles/app.css';

// Import jQuery FIRST (required by many plugins)
import $ from 'jquery';
// Expose jQuery globally IMMEDIATELY for plugins that expect it
if (typeof window !== 'undefined') {
    window.$ = window.jQuery = $;
}
global.$ = global.jQuery = $;

// Bootstrap 5 (does NOT require jQuery)
import * as bootstrap from 'bootstrap';
// Expose Bootstrap globally for legacy code
if (typeof window !== 'undefined') {
    window.bootstrap = bootstrap;
}
global.bootstrap = bootstrap;

// SweetAlert2 (replacement for alerts/confirms)
import Swal from 'sweetalert2';
global.Swal = Swal;

// Bootstrap Icons
import 'bootstrap-icons/font/bootstrap-icons.css';

// Font Awesome
import '@fortawesome/fontawesome-free/css/all.css';

// Bootstrap Table (comment out temporarily - causes error)
// The package tries to access $.fn before jQuery is fully ready
// TODO: Load bootstrap-table only on pages that need it
// import 'bootstrap-table/dist/bootstrap-table.js';
// import 'bootstrap-table/dist/bootstrap-table.min.css';
// import 'bootstrap-table/dist/locale/bootstrap-table-es-ES.js';

// Tempus Dominus (DateTimePicker)
import { TempusDominus, Namespace } from '@eonasdan/tempus-dominus';
import '@eonasdan/tempus-dominus/dist/css/tempus-dominus.css';
global.tempusDominus = { TempusDominus, Namespace };

// Bootstrap Select (Bootstrap 5 compatible beta)
import 'bootstrap-select';
import 'bootstrap-select/dist/css/bootstrap-select.css';
import 'bootstrap-select/dist/js/i18n/defaults-es_ES.js';

// jQuery Validation
import 'jquery-validation';
import 'jquery-validation/dist/localization/messages_es.js';

// Bootstrap Fileinput
import 'bootstrap-fileinput';
import 'bootstrap-fileinput/css/fileinput.css';
import 'bootstrap-fileinput/js/locales/es.js';

// Bootbox (modals helper)
import bootbox from 'bootbox';
global.bootbox = bootbox;

// Chart.js
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);
global.Chart = Chart;

// D3.js
import * as d3 from 'd3';
global.d3 = d3;

// Moment.js
import moment from 'moment';
import 'moment/locale/es';
moment.locale('es');
global.moment = moment;

// Choices.js
import Choices from 'choices.js';
import 'choices.js/public/assets/styles/choices.css';
global.Choices = Choices;

// QRCode
import QRCode from 'qrcode';
global.QRCode = QRCode;

// jQuery Context Menu
import 'jquery-contextmenu';
import 'jquery-contextmenu/dist/jquery.contextMenu.css';

// twbs-pagination
import 'twbs-pagination';

// JSON Editor
import JSONEditor from '@json-editor/json-editor';
global.JSONEditor = JSONEditor;

console.log('✅ Webpack Encore: All frontend dependencies loaded successfully');
