import './echo';

/*
 * Shared application assets only.
 * Page-specific CSS and JavaScript are loaded by each Blade page through
 * its x-layout.app assets list. Keeping them out of app.js prevents duplicate
 * initialization and avoids loading unrelated modules on every page.
 */
import '../css/Main-styles/system-toast.css';
import '../css/Main-styles/shared-ui-enhancements.css';
import '../css/Main-styles/scroll-record-lists.css';
import '../css/Main-styles/toolbar-cleanup.css';
import '../css/Main-styles/searchable-select.css';
import '../css/Main-styles/date-time-picker.css';
import '../css/Main-styles/spinner.css';
import '../css/Main-styles/topbar.css';
import '../css/Maintenance/maintenance-ui-enhancements.css';
import '../css/Operation/Routes/route-pin-enhancements.css';

import './Main-js/system-toast.js';
import './Main-js/automatic-table-search.js';
import './Main-js/auto-id-badges.js';
import './Main-js/shared-shell-enhancements.js';
import './Main-js/scroll-table-pagination.js';
import './Main-js/searchable-select.js';
import './Main-js/date-time-picker.js';
import './Main-js/loading-state.js';
import './Main-js/ajax-regions.js';
import './Main-js/topbar.js';
import './Maintenance/maintenance-ui-enhancements.js';

/* Shared by Driver and Mechanic Attendance pages. */
import '../css/Operation/Attendance/batch-attendance.css';
import './Operation/Attendance/batch-attendance.js';

/* Page-only controls are lazy-loaded only when their page root exists. */
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('routeModal')) {
        import('./Operation/Routes/route-pin-controls.js');
    }

    if (document.getElementById('gpsUploadForm')) {
        import('./Admin/Data_Management/batch-profile-selector.js');
    }

    if (document.getElementById('importModule') && document.getElementById('exportModule')) {
        import('./Admin/Data_Management/import-export.js');
    }

    if (document.getElementById('historyDetailsModal')) {
        import('./Admin/Data_Management/data-history.js');
    }
});