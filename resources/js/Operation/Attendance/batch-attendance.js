document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname.replace(/\/$/, '');
    const type = path.endsWith('/driver-attendance')
        ? 'driver'
        : path.endsWith('/mechanic-attendance')
            ? 'mechanic'
            : null;

    if (!type) return;

    const button = document.querySelector('[data-batch-attendance-open]');
    if (!button) return;

    const label = type === 'driver' ? 'Driver' : 'Mechanic';
    const busyLabel = type === 'driver' ? 'On Duty' : 'On Job';

    let shiftStarts = {};
    let graceMinutes = 10;

    button.addEventListener('click', openModal);

    function spinnerMarkup() {
        return '<span class="gct-spinner gct-spinner-sm" aria-hidden="true"><span class="gct-spinner-ring"></span></span>';
    }

    function openModal() {
        closeModal();

        const overlay = document.createElement('div');
        overlay.id = 'batchAttendanceModal';
        overlay.className = 'modal-overlay batch-attendance-overlay active';
        overlay.innerHTML = `
            <div class="batch-attendance-modal" role="dialog" aria-modal="true" aria-label="Record Daily Attendance">
                <div class="batch-attendance-header">
                    <div class="batch-header-copy">
                        <div class="batch-title-row">
                            <i class="fa-regular fa-calendar-check"></i>
                            <div>
                                <h2>Record Daily Attendance</h2>
                                <p>Record and manage attendance for all ${label.toLowerCase()}s in a single batch.</p>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="batch-close-btn" data-batch-close aria-label="Close modal">&times;</button>
                </div>

                <div class="batch-attendance-controls">
                    <label>
                        <span>Attendance Date</span>
                        <div class="batch-input-wrap">
                            <input type="date" id="batchAttendanceDate" value="${today()}" />
                        </div>
                    </label>
                    <label>
                        <span>Shift</span>
                        <div class="batch-input-wrap">
                            <select id="batchAttendanceShift">
                                <option value="all">All Shifts</option>
                                <option value="Morning">Morning</option>
                                <option value="Afternoon">Afternoon</option>
                                <option value="Night">Night</option>
                            </select>
                        </div>
                    </label>
                    <div class="batch-action-row">
                        <button type="button" class="batch-control-btn" id="batchReload"><i class="fa-solid fa-users-viewfinder"></i> Load Roster</button>
                        <button type="button" class="batch-control-btn success" id="batchMarkPresent"><i class="fa-solid fa-user-check"></i> Mark All Present</button>
                        <button type="button" class="batch-control-btn" id="batchUseCurrentTime"><i class="fa-regular fa-clock"></i> Use Current Time</button>
                        <button type="button" class="batch-control-btn" id="batchApplyTime"><i class="fa-solid fa-clock-rotate-left"></i> Apply Same Time to Selected</button>
                        <button type="button" class="batch-control-btn danger" id="batchClearAll"><i class="fa-regular fa-trash-can"></i> Clear All</button>
                    </div>
                </div>

                <div class="batch-table-shell">
                    <div class="batch-table-wrap">
                        <table class="batch-attendance-table">
                            <thead>
                                <tr>
                                    <th class="select-col"><input type="checkbox" id="batchSelectAll" aria-label="Select all rows"></th>
                                    <th>${label}</th>
                                    <th>Shift</th>
                                    <th>Scheduled Time</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Attendance Status</th>
                                    <th>Availability</th>
                                </tr>
                            </thead>
                            <tbody id="batchAttendanceBody">
                                <tr><td colspan="8" class="batch-loading">${spinnerMarkup()} Loading attendance roster…</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="batch-attendance-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Late is detected automatically after a ${graceMinutes}-minute grace period based on the scheduled time.</span>
                    </div>
                </div>

                <div class="batch-attendance-footer">
                    <div id="batchAttendanceSummary" class="batch-summary-grid"></div>
                    <div class="batch-footer-actions">
                        <button type="button" class="secondary-btn" data-batch-close>Cancel</button>
                        <button type="button" class="primary-btn" id="batchSaveAttendance" data-loading-text="Saving attendance..."><i class="fa-solid fa-floppy-disk"></i> Save All Attendance</button>
                    </div>
                </div>
            </div>`;

        document.body.appendChild(overlay);
        document.body.classList.add('batch-modal-open');

        overlay.querySelectorAll('[data-batch-close]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) closeModal();
        });

        overlay.querySelector('#batchReload').addEventListener('click', loadRoster);
        overlay.querySelector('#batchAttendanceDate').addEventListener('change', loadRoster);
        overlay.querySelector('#batchAttendanceShift').addEventListener('change', loadRoster);
        overlay.querySelector('#batchMarkPresent').addEventListener('click', markAllPresent);
        overlay.querySelector('#batchUseCurrentTime').addEventListener('click', () => {
            overlay.querySelectorAll('[data-batch-row]').forEach((row) => {
                if (!row.classList.contains('is-unavailable')) {
                    row.querySelector('[data-time-in]').value = currentTime();
                    detectLate(row);
                    updateAvailability(row);
                }
            });
            updateSummary();
        });
        overlay.querySelector('#batchApplyTime').addEventListener('click', applySharedTime);
        overlay.querySelector('#batchClearAll').addEventListener('click', clearAllRows);
        overlay.querySelector('#batchSelectAll').addEventListener('change', (event) => {
            overlay.querySelectorAll('[data-row-select]').forEach((input) => {
                input.checked = event.target.checked;
            });
        });
        overlay.querySelector('#batchSaveAttendance').addEventListener('click', saveAttendance);

        loadRoster();
    }

    async function loadRoster() {
        const modal = document.getElementById('batchAttendanceModal');
        if (!modal) return;

        const date = modal.querySelector('#batchAttendanceDate').value;
        const shift = modal.querySelector('#batchAttendanceShift').value;
        const body = modal.querySelector('#batchAttendanceBody');
        body.innerHTML = `<tr><td colspan="8" class="batch-loading">${spinnerMarkup()} Loading attendance roster…</td></tr>`;

        try {
            const response = await fetch(`/operation/attendance/batch/${type}?date=${encodeURIComponent(date)}&shift=${encodeURIComponent(shift)}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(errorMessage(data));
            shiftStarts = data.shift_starts || {};
            graceMinutes = Number(data.grace_minutes || 10);
            const note = modal.querySelector('.batch-attendance-note span');
            if (note) {
                note.textContent = `Late is detected automatically after a ${graceMinutes}-minute grace period based on the scheduled time.`;
            }
            renderRows(Array.isArray(data.rows) ? data.rows : []);
        } catch (error) {
            body.innerHTML = `<tr><td colspan="8" class="batch-error">${escapeHtml(error.message || 'Unable to load attendance roster.')}</td></tr>`;
            updateSummary();
        }
    }

    function renderRows(rows) {
        const modal = document.getElementById('batchAttendanceModal');
        const body = modal?.querySelector('#batchAttendanceBody');
        if (!body) return;

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="8" class="batch-empty">No existing personnel records were found. Add or import personnel first.</td></tr>';
            updateSummary();
            return;
        }

        body.innerHTML = rows.map((row) => {
            const normalizedStatus = normalizeStatus(row.status);
            const initialAvailability = computeAvailability(normalizedStatus, row.assigned_job || '');
            return `
                <tr
                    data-batch-row
                    data-person-id="${escapeHtml(row.person_id)}"
                    data-name="${escapeHtml(row.name)}"
                    data-shift="${escapeHtml(row.shift)}"
                    data-assigned-job="${escapeHtml(row.assigned_job || '')}"
                >
                    <td class="select-col"><input type="checkbox" data-row-select ${['Present', 'Late'].includes(normalizedStatus) ? 'checked' : ''}></td>
                    <td>
                        <div class="batch-person-cell">
                            <span class="batch-avatar">${escapeHtml(initials(row.name))}</span>
                            <div class="batch-person-meta">
                                <strong>${escapeHtml(row.name)}</strong>
                                <small>${label}</small>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="batch-shift-meta">
                            <strong>${escapeHtml(row.shift)}</strong>
                            <small>${escapeHtml(formatTime(shiftStarts[row.shift]) || '')}</small>
                        </div>
                    </td>
                    <td>
                        <span class="batch-scheduled-time">${escapeHtml(formatTime(shiftStarts[row.shift]) || '--:--')}</span>
                    </td>
                    <td>
                        <div class="batch-time-cell">
                            <input type="time" data-time-in data-native-picker="true" value="${escapeHtml(row.time_in || '')}">
                            <button type="button" title="Use current time" data-row-now>
                                <i class="fa-regular fa-clock"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="batch-time-cell batch-time-out-cell">
                            <input type="time" data-time-out data-native-picker="true" value="${escapeHtml(row.time_out || '')}">
                        </div>
                    </td>
                    <td>
                        <div class="batch-status-group" role="group" aria-label="Attendance status">
                            ${renderStatusButton('Present', normalizedStatus)}
                            ${renderStatusButton('Late', normalizedStatus)}
                            ${renderStatusButton('Absent', normalizedStatus)}
                            ${renderStatusButton('On Leave', normalizedStatus)}
                        </div>
                    </td>
                    <td>
                        <span class="batch-availability-badge ${availabilityClass(initialAvailability)}" data-availability>${escapeHtml(initialAvailability)}</span>
                    </td>
                </tr>`;
        }).join('');

        body.querySelectorAll('[data-batch-row]').forEach((row) => initializeRow(row));
        updateSummary();
    }

    function renderStatusButton(value, active) {
        const activeClass = value === active ? 'is-active' : '';
        const themeClass = statusThemeClass(value);
        return `<button type="button" class="batch-status-btn ${themeClass} ${activeClass}" data-status-btn data-status-value="${value}">${value}</button>`;
    }

    function initializeRow(row) {
        const timeIn = row.querySelector('[data-time-in]');
        const timeOut = row.querySelector('[data-time-out]');
        const nowButton = row.querySelector('[data-row-now]');
        const statusButtons = row.querySelectorAll('[data-status-btn]');

        statusButtons.forEach((statusButton) => {
            statusButton.addEventListener('click', () => {
                setSelectedStatus(row, statusButton.dataset.statusValue);
                applyStatusRules(row);
                detectLate(row);
                updateAvailability(row);
                updateSummary();
            });
        });

        timeIn.addEventListener('change', () => {
            detectLate(row);
            updateAvailability(row);
            updateSummary();
        });

        timeOut.addEventListener('change', updateSummary);

        nowButton.addEventListener('click', () => {
            timeIn.value = currentTime();
            detectLate(row);
            updateAvailability(row);
            updateSummary();
        });

        applyStatusRules(row, false);
        updateAvailability(row);
    }

    function markAllPresent() {
        document.querySelectorAll('#batchAttendanceModal [data-batch-row]').forEach((row) => {
            setSelectedStatus(row, 'Present');
            applyStatusRules(row, false);
            detectLate(row);
            updateAvailability(row);
        });
        updateSummary();
    }

    function clearAllRows() {
        document.querySelectorAll('#batchAttendanceModal [data-batch-row]').forEach((row) => {
            row.querySelector('[data-row-select]').checked = false;
            row.querySelector('[data-time-in]').value = '';
            row.querySelector('[data-time-out]').value = '';
            setSelectedStatus(row, 'Present');
            applyStatusRules(row, false);
            updateAvailability(row);
        });
        updateSummary();
    }

    function applySharedTime() {
        const modal = document.getElementById('batchAttendanceModal');
        const selectedRows = [...modal.querySelectorAll('[data-batch-row]')]
            .filter((row) => row.querySelector('[data-row-select]').checked);

        if (!selectedRows.length) {
            toast('Select at least one personnel row.', 'warning');
            return;
        }

        const time = promptTime();
        if (!time) return;

        selectedRows.forEach((row) => {
            if (row.classList.contains('is-unavailable')) return;
            row.querySelector('[data-time-in]').value = time;
            detectLate(row);
            updateAvailability(row);
        });
        updateSummary();
    }

    function promptTime() {
        const value = window.prompt('Enter time in 24-hour format (HH:MM).', currentTime());
        if (!value) return null;
        return /^\d{2}:\d{2}$/.test(value) ? value : null;
    }

    function applyStatusRules(row, clear = true) {
        const status = selectedStatus(row);
        const timeIn = row.querySelector('[data-time-in]');
        const timeOut = row.querySelector('[data-time-out]');
        const disabled = ['Absent', 'On Leave'].includes(status);

        timeIn.disabled = disabled;
        timeOut.disabled = disabled;
        row.classList.toggle('is-unavailable', disabled);

        if (disabled && clear) {
            timeIn.value = '';
            timeOut.value = '';
        }
    }

    function detectLate(row) {
        const status = selectedStatus(row);
        const time = row.querySelector('[data-time-in]').value;
        if (!time || ['Absent', 'On Leave'].includes(status)) return;

        const shift = row.dataset.shift;
        const start = shiftStarts[shift];
        if (!start) return;

        const late = minutes(time) > minutes(String(start).slice(0, 5)) + graceMinutes;
        setSelectedStatus(row, late ? 'Late' : 'Present');
    }

    function updateAvailability(row) {
        const badge = row.querySelector('[data-availability]');
        if (!badge) return;

        const availability = computeAvailability(selectedStatus(row), row.dataset.assignedJob || '');
        badge.textContent = availability;
        badge.className = `batch-availability-badge ${availabilityClass(availability)}`;
    }

    function computeAvailability(status, assignedJob) {
        if (['Absent', 'On Leave'].includes(status)) {
            return 'Unavailable';
        }
        if (assignedJob && String(assignedJob).trim() !== '') {
            return busyLabel;
        }
        return 'Available';
    }

    async function saveAttendance() {
        const modal = document.getElementById('batchAttendanceModal');
        const saveButton = modal?.querySelector('#batchSaveAttendance');
        if (!modal || !saveButton) return;

        const rows = [...modal.querySelectorAll('[data-batch-row]')].map((row) => ({
            person_id: row.dataset.personId,
            name: row.dataset.name,
            shift: row.dataset.shift,
            assigned_job: row.dataset.assignedJob || null,
            time_in: row.querySelector('[data-time-in]').disabled ? null : row.querySelector('[data-time-in]').value || null,
            time_out: row.querySelector('[data-time-out]').disabled ? null : row.querySelector('[data-time-out]').value || null,
            status: selectedStatus(row),
        }));

        if (!rows.length) {
            toast('There are no attendance rows to save.', 'warning');
            return;
        }

        if (window.GCTLoading?.set) {
            window.GCTLoading.set(saveButton, 'Saving attendance...');
        } else {
            saveButton.disabled = true;
            saveButton.innerHTML = `${spinnerMarkup()} <span>Saving attendance...</span>`;
        }

        let saved = false;

        try {
            const response = await fetch(`/operation/attendance/batch/${type}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    attendance_date: modal.querySelector('#batchAttendanceDate').value,
                    rows,
                }),
            });

            const data = await response.json();
            if (!response.ok) throw new Error(errorMessage(data));

            saved = true;
            toast(data.message || 'Attendance saved successfully.', 'success');
            closeModal();

            window.setTimeout(() => {
                window.location.reload();
            }, 450);
        } catch (error) {
            toast(error.message || 'Unable to save attendance.', 'error');
        } finally {
            if (!saved) {
                if (window.GCTLoading?.reset) {
                    window.GCTLoading.reset(saveButton);
                } else {
                    saveButton.disabled = false;
                    saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save All Attendance';
                }
            }
        }
    }

    function selectedStatus(row) {
        return row.querySelector('[data-status-btn].is-active')?.dataset.statusValue || 'Present';
    }

    function setSelectedStatus(row, status) {
        row.querySelectorAll('[data-status-btn]').forEach((statusButton) => {
            statusButton.classList.toggle('is-active', statusButton.dataset.statusValue === status);
        });
    }

    function normalizeStatus(status) {
        return ['Present', 'Late', 'Absent', 'On Leave'].includes(status) ? status : 'Present';
    }

    function statusThemeClass(value) {
        return {
            Present: 'present',
            Late: 'late',
            Absent: 'absent',
            'On Leave': 'leave',
        }[value] || '';
    }

    function availabilityClass(value) {
        if (value === busyLabel) return 'busy';
        if (value === 'Unavailable') return 'unavailable';
        return 'available';
    }

    function updateSummary() {
        const modal = document.getElementById('batchAttendanceModal');
        const summary = modal?.querySelector('#batchAttendanceSummary');
        if (!modal || !summary) return;

        const rows = [...modal.querySelectorAll('[data-batch-row]')];
        const counts = {
            Present: 0,
            Late: 0,
            Absent: 0,
            'On Leave': 0,
            Incomplete: 0,
        };

        rows.forEach((row) => {
            const status = selectedStatus(row);
            counts[status] = (counts[status] || 0) + 1;

            if (!['Absent', 'On Leave'].includes(status) && !row.querySelector('[data-time-in]').value) {
                counts.Incomplete += 1;
            }
        });

        summary.innerHTML = [
            ['Present', 'present', '#2fb45e'],
            ['Late', 'late', '#ff9518'],
            ['Absent', 'absent', '#ef3d3d'],
            ['On Leave', 'leave', '#7c3aed'],
            ['Incomplete', 'incomplete', '#94a3b8'],
        ].map(([name, className, color]) => `
            <div class="batch-summary-card ${className}">
                <div class="batch-summary-label"><i class="fa-solid fa-circle" style="color:${color}"></i>${name}</div>
                <strong>${counts[name]}</strong>
            </div>`).join('');
    }

    function closeModal() {
        document.getElementById('batchAttendanceModal')?.remove();
        document.body.classList.remove('batch-modal-open');
    }

    function today() {
        const now = new Date();
        return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
    }

    function currentTime() {
        const now = new Date();
        return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
    }

    function minutes(value) {
        const [hour, minute] = String(value || '00:00').split(':').map(Number);
        return (hour * 60) + minute;
    }

    function formatTime(value) {
        if (!value) return '';
        const [hour, minute] = String(value).slice(0, 5).split(':').map(Number);
        const suffix = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${String(displayHour).padStart(2, '0')}:${String(minute).padStart(2, '0')} ${suffix}`;
    }

    function initials(name) {
        return String(name || '')
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase() || '')
            .join('');
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function errorMessage(data) {
        if (data?.message) return data.message;
        if (data?.errors) return Object.values(data.errors).flat().join(' ');
        return 'The attendance request could not be completed.';
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    function toast(message, type = 'info') {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(
                message,
                type,
                type === 'success' ? 'Success' : type === 'error' ? 'Error' : 'Notice'
            );
            return;
        }
        window.alert(message);
    }
});
