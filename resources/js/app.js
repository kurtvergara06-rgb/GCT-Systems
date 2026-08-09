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
import '../css/Main-styles/date-time-picker.css';
import '../css/Main-styles/spinner.css';
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
import './Maintenance/maintenance-ui-enhancements.js';

/* Shared by Driver and Mechanic Attendance pages. */
import '../css/Operation/Attendance/batch-attendance.css';
import './Operation/Attendance/batch-attendance.js';

/*
 * Compatibility cleanup for rejected Maintenance Purchase Requests.
 * An older Blade attribute was emitted as `ddata-resubmit-url`, which leaves
 * the page-specific PR script without a resubmit URL and can make the form
 * fall back to the normal create endpoint. Normalize it before the page's
 * delegated click handler reads button.dataset.resubmitUrl.
 */
document.addEventListener('click', (event) => {
    const button = event.target.closest('.open-edit-pr-modal[ddata-resubmit-url]');

    if (!button || button.dataset.resubmitUrl) {
        return;
    }

    const resubmitUrl = button.getAttribute('ddata-resubmit-url');

    if (resubmitUrl) {
        button.dataset.resubmitUrl = resubmitUrl;
    }
}, true);

document.addEventListener('DOMContentLoaded', () => {
    /*
     * Attendance records must come from the permanent Driver / Mechanic
     * master lists. Daily attendance is recorded through the shared batch
     * attendance workflow, so single-record creation actions are redundant.
     * Keep existing attendance records editable, but remove the legacy
     * Driver "New Record" and Mechanic "Add New Mechanic" actions.
     */
    const attendancePath = window.location.pathname.replace(/\/$/, '');
    if (
        attendancePath.endsWith('/driver-attendance') ||
        attendancePath.endsWith('/mechanic-attendance')
    ) {
        document.getElementById('openDriverAttendanceModal')?.remove();
        document.getElementById('openMechanicAttendanceModal')?.remove();
    }

    /*
     * Issued inventory transactions now belong to Stock Movements.
     * Keep the underlying Purchase Request records, but remove the duplicate
     * Issued Parts History panel from the Warehouse Part Requests UI.
     */
    if (window.location.pathname.replace(/\/$/, '').endsWith('/part-requests')) {
        document.querySelector('.warehouse-history-card')?.remove();
    }

    window.setTimeout(() => {
        const routeModal = document.getElementById('routeModal');
        const routeOrigin = document.getElementById('routeOrigin');
        const routeDestination = document.getElementById('routeDestination');
        const routeStopList = document.getElementById('routeStopList');
        const mapElement = document.getElementById('routeFormGpsMap');
        const existingPinButton = document.getElementById('pinActiveLocationBtn');
        const activeFieldLabel = document.getElementById('routeMapActiveField');

        if (!routeModal || !routeOrigin || !routeDestination || !existingPinButton) {
            return;
        }

        const roleFor = (input) => {
            if (input === routeOrigin) return 'Origin';
            if (input === routeDestination) return 'Destination';
            return input?.dataset?.role || 'Stop';
        };

        const clearActiveState = () => {
            routeModal
                .querySelectorAll('.route-location-active')
                .forEach((element) => element.classList.remove('route-location-active'));
        };

        const markActive = (input) => {
            clearActiveState();

            const target = input.closest('.ui-form-group') || input.closest('.route-stop-item');
            target?.classList.add('route-location-active');
        };

        const beginPinning = (input) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            input.focus();
            input.dispatchEvent(new MouseEvent('click', { bubbles: true }));
            markActive(input);

            if (activeFieldLabel) {
                activeFieldLabel.innerHTML = `
                    <i class="fa-solid fa-location-crosshairs"></i>
                    Pinning: ${roleFor(input)}
                `;
            }

            if (!existingPinButton.disabled) {
                existingPinButton.click();
            }

            window.setTimeout(() => {
                mapElement?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
            }, 80);
        };

        const createPinButton = (input, compact = false) => {
            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            const host = compact
                ? input.closest('.route-stop-location-field')
                : input.closest('.ui-input-wrap');

            if (!host || host.querySelector(':scope > .route-direct-pin-btn')) {
                return;
            }

            host.classList.add('route-location-pin-host');

            const button = document.createElement('button');
            button.type = 'button';
            button.className = `route-direct-pin-btn${compact ? ' is-stop-pin' : ''}`;
            button.title = `Pin ${roleFor(input)} on map`;
            button.setAttribute('aria-label', `Pin ${roleFor(input)} on map`);
            button.innerHTML = compact
                ? '<i class="fa-solid fa-thumbtack"></i>'
                : '<i class="fa-solid fa-thumbtack"></i><span>Pin</span>';

            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => beginPinning(input));

            host.appendChild(button);

            input.addEventListener('focus', () => markActive(input));
            input.addEventListener('click', () => markActive(input));
        };

        const enhanceStopInputs = () => {
            routeStopList
                ?.querySelectorAll('input[name="stops[]"]')
                .forEach((input) => createPinButton(input, true));
        };

        createPinButton(routeOrigin);
        createPinButton(routeDestination);
        enhanceStopInputs();

        if (routeStopList) {
            const observer = new MutationObserver(enhanceStopInputs);
            observer.observe(routeStopList, {
                childList: true,
                subtree: true,
            });
        }

        existingPinButton.innerHTML = `
            <i class="fa-solid fa-thumbtack"></i>
            Pin Selected Field
        `;
    }, 0);
});
