import './echo';

/*
 * Shared application assets only.
 * Page-specific CSS and JavaScript must be loaded by each Blade page through
 * its x-layout.app assets list. Keeping them out of app.js prevents duplicate
 * initialization and avoids loading unrelated modules on every page.
 */
import '../css/Main-styles/system-toast.css';
import '../css/Main-styles/shared-ui-enhancements.css';
import '../css/Main-styles/searchable-select.css';
import '../css/Maintenance/maintenance-ui-enhancements.css';
import '../css/Maintenance/fuel-report-modal-refinement.css';
import '../css/Maintenance/fuel-reports-line-enhancements.css';
import './Main-js/system-toast.js';
import './Main-js/automatic-table-search.js';
import './Main-js/auto-id-badges.js';
import './Main-js/shared-shell-enhancements.js';
import './Main-js/scroll-table-pagination.js';
import './Main-js/searchable-select.js';
import './Maintenance/maintenance-ui-enhancements.js';
import './Maintenance/fuel-report-chart-refinement.js';
import './Maintenance/fuel-reports-line-enhancements.js';

import '../css/Maintenance/job-order-redesign.css';
import '../css/Maintenance/job-order-edit-combobox.css';
import './Maintenance/job-order-finish-guard.js';
import './Maintenance/job-order-edit-combobox.js';
import './Maintenance/job-order-new-combobox.js';

import '../css/Maintenance/pms-table-scroll.css';

import '../css/Operation/Scheduling_And_Dispatch/auto-conflict-redesign.css';
import '../css/Operation/Scheduling_And_Dispatch/auto-conflict-readable.css';
import './Operation/Scheduling_And_Dispatch/auto-conflict-redesign.js';

import '../css/Operation/Attendance/batch-attendance.css';
import '../css/Operation/Attendance/batch-attendance-scroll-fix.css';
import '../css/Operation/Attendance/batch-attendance-availability.css';
import '../css/Operation/Attendance/personnel-attendance-refactor.css';
import '../css/Operation/Attendance/personnel-master-bus-alignment.css';
import './Operation/Attendance/batch-attendance.js';
import './Operation/Attendance/batch-attendance-availability.js';
import './Operation/Attendance/personnel-attendance-refactor.js';
