document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname.replace(/\/$/, '');
    const isDriver = path.endsWith('/driver-attendance');
    const isMechanic = path.endsWith('/mechanic-attendance');

    if (!isDriver && !isMechanic) return;

    const toolbar = document.querySelector('.attendance-toolbar');
    const importButton = toolbar?.querySelector('.import-btn');

    if (toolbar && importButton && !toolbar.querySelector('.personnel-master-link')) {
        const masterLink = document.createElement('a');
        masterLink.className = 'secondary-btn personnel-master-link';
        masterLink.href = isDriver
            ? '/operation/personnel/drivers'
            : '/operation/personnel/mechanics';
        masterLink.innerHTML = `<i class="fa-solid fa-address-book"></i> ${isDriver ? 'Driver' : 'Mechanic'} Master List`;
        toolbar.insertBefore(masterLink, importButton);
    }

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
                    <p>This time will be applied to ${selectedRows.length} selected personnel record(s).</p>
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
            showToast(`Time applied to ${selectedRows.length} selected record(s).`, 'success');
        });
    }

    function showToast(message, type) {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(message, type, type === 'success' ? 'Attendance Updated' : 'Attendance Notice');
            return;
        }

        console[type === 'error' ? 'error' : 'log'](message);
    }
});
