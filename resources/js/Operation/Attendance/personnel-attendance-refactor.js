document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname.replace(/\/$/, '');

    ensureOperationPersonnelMenu(path);

    const isDriver = path.endsWith('/driver-attendance');
    const isMechanic = path.endsWith('/mechanic-attendance');

    if (!isDriver && !isMechanic) return;

    /*
    |--------------------------------------------------------------------------
    | ATTENDANCE-ONLY PAGE
    |--------------------------------------------------------------------------
    |
    | Permanent personnel creation belongs exclusively to Driver Master List
    | and Mechanic Master List. The attendance pages only keep daily attendance
    | import, batch recording, viewing, editing, and deletion of attendance.
    |
    */

    const toolbar = document.querySelector('.attendance-toolbar');
    const oldNewRecordButton = toolbar?.querySelector('.primary-btn');
    const oldPersonnelLink = toolbar?.querySelector('.personnel-master-link');
    const importButton = toolbar?.querySelector('.import-btn');

    oldPersonnelLink?.remove();
    oldNewRecordButton?.remove();

    if (importButton) {
        importButton.innerHTML = '<i class="fa-solid fa-file-import"></i> Import Attendance';
        importButton.title = 'Import daily attendance records';
    }

    const pageCard = document.querySelector('.attendance-card');
    const sectionHeader = pageCard?.querySelector('.section-header');

    if (sectionHeader && !sectionHeader.querySelector('.attendance-scope-note')) {
        const note = document.createElement('div');
        note.className = 'attendance-scope-note';
        note.innerHTML = `
            <i class="fa-solid fa-circle-info"></i>
            <span>
                This page records daily attendance only. Permanent ${isDriver ? 'driver' : 'mechanic'} profiles are managed in Personnel Management.
            </span>`;
        sectionHeader.appendChild(note);
    }

    /* Remove the old single-record creation modal from the active page flow. */
    const oldCreateModalId = isDriver
        ? 'driverAttendanceModal'
        : 'mechanicAttendanceModal';

    document.getElementById(oldCreateModalId)?.remove();

    /*
    |--------------------------------------------------------------------------
    | GCT TIME INPUT MODAL
    |--------------------------------------------------------------------------
    */

    document.addEventListener('click', (event) => {
        const applyButton = event.target.closest('#batchApplyTime');
        if (!applyButton) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        const attendanceModal = document.getElementById('batchAttendanceModal');
        if (!attendanceModal) return;

        const selectedRows = [...attendanceModal.querySelectorAll('[data-batch-row]')]
            .filter((row) => row.querySelector('[data-row-select]')?.checked);

        if (!selectedRows.length) {
            showToast('Select at least one personnel row.', 'warning');
            return;
        }

        openTimeModal(selectedRows);
    }, true);

    function openTimeModal(selectedRows) {
        document.getElementById('batchTimeModal')?.remove();

        const now = new Date();
        const defaultTime = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        const overlay = document.createElement('div');
        overlay.id = 'batchTimeModal';
        overlay.className = 'batch-time-modal-overlay';
        overlay.innerHTML = `
            <div class="batch-time-modal" role="dialog" aria-modal="true" aria-labelledby="batchTimeModalTitle">
                <div class="batch-time-modal-icon"><i class="fa-regular fa-clock"></i></div>
                <div class="batch-time-modal-copy">
                    <h3 id="batchTimeModalTitle">Apply Time to Selected</h3>
                    <p>This time will be applied to ${selectedRows.length} selected attendance record(s).</p>
                </div>
                <button type="button" class="batch-time-modal-close" data-time-modal-close>&times;</button>
                <label class="batch-time-modal-field">
                    <span>Time In</span>
                    <input type="time" id="batchTimeModalInput" value="${defaultTime}">
                    <small>Attendance status will be recalculated automatically using the shift grace period.</small>
                </label>
                <div class="batch-time-modal-actions">
                    <button type="button" class="secondary-btn" data-time-modal-close>Cancel</button>
                    <button type="button" class="primary-btn" id="confirmBatchTime"><i class="fa-solid fa-check"></i> Apply Time</button>
                </div>
            </div>`;

        document.body.appendChild(overlay);
        const input = overlay.querySelector('#batchTimeModalInput');
        window.setTimeout(() => input?.focus(), 50);

        overlay.querySelectorAll('[data-time-modal-close]').forEach((button) => {
            button.addEventListener('click', () => overlay.remove());
        });

        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) overlay.remove();
        });

        overlay.querySelector('#confirmBatchTime').addEventListener('click', () => {
            const time = input.value;
            if (!time) {
                showToast('Choose a valid time first.', 'warning');
                return;
            }

            selectedRows.forEach((row) => {
                const timeInput = row.querySelector('[data-time-in]');
                if (!timeInput || timeInput.disabled) return;
                timeInput.value = time;
                timeInput.dispatchEvent(new Event('change', { bubbles: true }));
            });

            overlay.remove();
            showToast(`Time applied to ${selectedRows.length} selected attendance record(s).`, 'success');
        });
    }

    function showToast(message, type) {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(
                message,
                type,
                type === 'success' ? 'Attendance Updated' : 'Attendance Notice',
            );
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }
});

function ensureOperationPersonnelMenu(path) {
    const sidebar = document.getElementById('appSidebar');
    const menu = sidebar?.querySelector('.menu');
    const department = sidebar?.querySelector('.brand h2')?.textContent?.trim().toLowerCase();

    if (!menu || department !== 'operation') return;

    const dropdowns = [...menu.querySelectorAll('.menu-dropdown')];
    const hasPersonnelMenu = dropdowns.some((dropdown) => (
        dropdown.querySelector('.dropdown-toggle span')?.textContent?.trim() === 'Personnel Management'
    ));

    if (hasPersonnelMenu) return;

    const attendanceDropdown = dropdowns.find((dropdown) => (
        dropdown.querySelector('.dropdown-toggle span')?.textContent?.trim() === 'Attendance'
    ));

    if (!attendanceDropdown) return;

    const driverActive = path === '/operation/personnel/drivers';
    const mechanicActive = path === '/operation/personnel/mechanics';
    const parentActive = driverActive || mechanicActive;
    const personnelDropdown = document.createElement('div');

    personnelDropdown.className = `menu-dropdown${parentActive ? ' open active' : ''}`;
    personnelDropdown.innerHTML = `
        <button
            type="button"
            class="menu-item dropdown-toggle${parentActive ? ' active' : ''}"
            aria-expanded="${parentActive ? 'true' : 'false'}"
            title="Personnel Management"
        >
            <i class="fa-solid fa-address-book"></i>
            <span>Personnel Management</span>
            <i class="fa-solid fa-chevron-down dropdown-arrow"></i>
        </button>
        <div class="submenu">
            <a
                href="/operation/personnel/drivers"
                class="submenu-item${driverActive ? ' active' : ''}"
                title="Driver Master List"
            >
                <i class="fa-solid fa-id-card"></i>
                <span>Driver Master List</span>
            </a>
            <a
                href="/operation/personnel/mechanics"
                class="submenu-item${mechanicActive ? ' active' : ''}"
                title="Mechanic Master List"
            >
                <i class="fa-solid fa-users-gear"></i>
                <span>Mechanic Master List</span>
            </a>
        </div>`;

    const toggle = personnelDropdown.querySelector('.dropdown-toggle');

    toggle?.addEventListener('click', () => {
        const isOpen = personnelDropdown.classList.toggle('open');
        toggle.classList.toggle('active', isOpen || parentActive);
        toggle.setAttribute('aria-expanded', String(isOpen));
    });

    attendanceDropdown.before(personnelDropdown);
}
