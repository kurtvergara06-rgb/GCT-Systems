document.addEventListener('DOMContentLoaded', () => {
    const path = window.location.pathname.replace(/\/$/, '');
    const type = path.endsWith('/driver-attendance')
        ? 'driver'
        : path.endsWith('/mechanic-attendance')
            ? 'mechanic'
            : null;

    if (!type) return;

    const toolbar = document.querySelector('.attendance-toolbar');
    const newRecordButton = toolbar?.querySelector('.primary-btn');
    if (!toolbar || !newRecordButton) return;

    const label = type === 'driver' ? 'Driver' : 'Mechanic';
    const busyLabel = type === 'driver' ? 'On Duty' : 'On Job';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'secondary-btn batch-attendance-open';
    button.innerHTML = '<i class="fa-solid fa-clipboard-check"></i> Record Daily Attendance';
    toolbar.insertBefore(button, newRecordButton);

    let shiftStarts = {};
    let graceMinutes = 10;

    button.addEventListener('click', openModal);

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
                                <tr><td colspan="8" class="batch-loading">Loading attendance roster…</td></tr>
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
                        <button type="button" class="primary-btn" id="batchSaveAttendance"><i class="fa-solid fa-floppy-disk"></i> Save All Attendance</button>
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
        body.innerHTML = '<tr><td colspan="8" class="batch-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading attendance roster…</td></tr>';

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
                            <input type="time" data-time-in value="${escapeHtml(row.time_in || '')}">
                            <button type="button" title="Use current time" data-row-now>
                                <i class="fa-regular fa-clock"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="batch-time-cell batch-time-out-cell">
                            <input type="time" data-time-out value="${escapeHtml(row.time_out || '')}">
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

        statusButtons.forEach((button) => {
            button.addEventListener('click', () => {
                setSelectedStatus(row, button.dataset.statusValue);
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
        const selectedRows = [...modal.querySelectorAll('[data-batch-row]')].filter((row) => row.querySelector('[data-row-select]').checked);

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

        const incomplete = rows.filter((row) => ['Present', 'Late'].includes(row.status) && !row.time_in);
        if (incomplete.length) {
            toast(`${incomplete.length} personnel record(s) still need a time-in.`, 'warning');
            return;
        }

        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

        try {
            const response = await fetch(`/operation/attendance/batch/${type}`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                        || document.querySelector('input[name="_token"]')?.value
                        || '',
                },
                body: JSON.stringify({
                    attendance_date: modal.querySelector('#batchAttendanceDate').value,
                    rows,
                }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(errorMessage(data));
            toast(data.message || 'Attendance saved successfully.', 'success');
            closeModal();
            window.setTimeout(() => window.location.reload(), 600);
        } catch (error) {
            toast(error.message || 'Unable to save attendance.', 'error');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save All Attendance';
        }
    }

    function updateSummary() {
        const modal = document.getElementById('batchAttendanceModal');
        const summary = modal?.querySelector('#batchAttendanceSummary');
        if (!summary) return;

        const rows = [...modal.querySelectorAll('[data-batch-row]')];
        const counts = { Present: 0, Late: 0, Absent: 0, 'On Leave': 0, Incomplete: 0 };

        rows.forEach((row) => {
            const status = selectedStatus(row);
            counts[status] = (counts[status] || 0) + 1;
            if (['Present', 'Late'].includes(status) && !row.querySelector('[data-time-in]').value) {
                counts.Incomplete++;
            }
        });

        summary.innerHTML = [
            summaryCard('Present', counts.Present, 'present'),
            summaryCard('Late', counts.Late, 'late'),
            summaryCard('Absent', counts.Absent, 'absent'),
            summaryCard('On Leave', counts['On Leave'], 'leave'),
            summaryCard('Incomplete', counts.Incomplete, 'incomplete'),
        ].join('');
    }

    function summaryCard(labelText, value, theme) {
        return `
            <div class="batch-summary-card ${theme}">
                <span class="batch-summary-label"><i class="fa-solid fa-circle"></i>${labelText}</span>
                <strong>${value}</strong>
            </div>`;
    }

    function selectedStatus(row) {
        return row.querySelector('[data-status-btn].is-active')?.dataset.statusValue || 'Present';
    }

    function setSelectedStatus(row, value) {
        row.querySelectorAll('[data-status-btn]').forEach((button) => {
            button.classList.toggle('is-active', button.dataset.statusValue === value);
        });
    }

    function normalizeStatus(value) {
        return ['Present', 'Late', 'Absent', 'On Leave'].includes(value) ? value : 'Present';
    }

    function statusThemeClass(status) {
        switch (status) {
        case 'Present':
            return 'present';
        case 'Late':
            return 'late';
        case 'Absent':
            return 'absent';
        case 'On Leave':
            return 'leave';
        default:
            return '';
        }
    }

    function availabilityClass(value) {
        switch (value) {
        case 'Available':
            return 'available';
        case 'Unavailable':
            return 'unavailable';
        default:
            return 'busy';
        }
    }

    function closeModal() {
        document.getElementById('batchAttendanceModal')?.remove();
        document.body.classList.remove('batch-modal-open');
    }

    function toast(message, type) {
        if (typeof window.showSystemToast === 'function') {
            window.showSystemToast(
                message,
                type,
                type === 'success' ? 'Attendance Saved' : null,
            );
        } else {
            window.alert(message);
        }
    }

    function today() {
        return new Date().toISOString().slice(0, 10);
    }

    function currentTime() {
        const d = new Date();
        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    function minutes(value) {
        const [h, m] = String(value).split(':').map(Number);
        return (h * 60) + m;
    }

    function formatTime(value) {
        if (!value) return '';
        const [hourRaw, minuteRaw] = String(value).split(':');
        const hour = Number(hourRaw);
        const minute = String(minuteRaw ?? '00').padStart(2, '0');
        const suffix = hour >= 12 ? 'PM' : 'AM';
        const twelve = ((hour + 11) % 12) + 1;
        return `${String(twelve).padStart(2, '0')}:${minute} ${suffix}`;
    }

    function initials(name) {
        return String(name || '')
            .trim()
            .split(/\s+/)
            .slice(0, 2)
            .map((part) => part.charAt(0).toUpperCase())
            .join('') || '--';
    }

    function errorMessage(data) {
        return data?.message || Object.values(data?.errors || {}).flat()[0] || 'Request failed.';
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = String(value ?? '');
        return div.innerHTML;
    }
});