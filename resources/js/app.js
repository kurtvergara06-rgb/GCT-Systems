import './echo';

/*
 * Shared application assets only.
 * Page-specific CSS and JavaScript are loaded by each Blade page through
 * its x-layout.app assets list. Keeping them out of app.js prevents duplicate
 * initialization and avoids loading unrelated modules on every page.
 */
import '../css/Main-styles/system-toast.css';
import '../css/Main-styles/shared-ui-enhancements.css';
import '../css/Main-styles/searchable-select.css';
import '../css/Maintenance/maintenance-ui-enhancements.css';

import './Main-js/system-toast.js';
import './Main-js/automatic-table-search.js';
import './Main-js/auto-id-badges.js';
import './Main-js/shared-shell-enhancements.js';
import './Main-js/scroll-table-pagination.js';
import './Main-js/searchable-select.js';
import './Maintenance/maintenance-ui-enhancements.js';

/* Shared by Driver and Mechanic Attendance pages. */
import '../css/Operation/Attendance/batch-attendance.css';
import './Operation/Attendance/batch-attendance.js';
