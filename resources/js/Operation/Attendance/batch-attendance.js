function initBatchAttendancePage() {
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
    const modalDescription = type === 'driver'
        ? 'All active drivers from the Driver Master List load automatically for the selected date.'
        : 'Active mechanics from the Mechanic Master List load automatically for the selected date.';

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
        overlay.className = `modal-overlay batch-attendance-overlay active batch-attendance-${type}`;
        overlay.innerHTML = `
            <div class="batch-attendance-modal" role="dialog" aria-modal="true" aria-label="Record Daily Attendance">
                <div class="batch-attendance-header">
                    <div class="batch-header-copy">
                        <div class="batch-title-row">
                            <i class="fa-regular fa-calendar-check"></i>
                            <div>
                                <h2>${type === 'mechanic' ? 'Manage Daily Attendance' : 'Record Daily Attendance'}</h2>
                                <p>${modalDescription}</p>
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
                    <label style="display:none">
                        <span>Shift</span>
                        <div class="batch-input-wrap">
                            <select id="batchAttendanceShift">
                                <option value="all">All Shifts</option>
                                <option value="Morning">Morning</option>
                                <option value="Afternoon">Afternoon</option>
                            </select>
                        </div>
                    </label>
                    <div class="batch-action-row">
                        <button type="button" class="batch-control-btn success" id="batchMarkPresent"><i class="fa-solid fa-user-check"></i> Mark All Present</button>
                        <button type="button" class="batch-control-btn" id="batchUseCurrentTime"><i class="fa-regular fa-clock"></i> Use Current Time</button>
                        <button type="button" class="batch-control-btn" id="batchApplyTime"><i class="fa-solid fa-clock-rotate-left"></i> Apply Same Time to Selected</button>
                        <button type="button" class="batch-control-btn danger" id="batchClearAll"><i class="fa-solid fa-trash"></i> Clear All</button>
                    </div>
                    ${type === 'driver' ? '<label class="batch-driver-search"><span>Find Driver</span><input type="search" id="batchDriverSearch" placeholder="Search driver name..." autocomplete="off" aria-label="Search attendance drivers"></label>' : ''}
                </div>

                <div class="batch-shared-time-layer" id="batchSharedTimeLayer" hidden>
                    <div class="batch-shared-time-dialog" role="dialog" aria-modal="true" aria-labelledby="batchSharedTimeTitle">
                        <h3 id="batchSharedTimeTitle"><i class="fa-regular fa-clock"></i> Apply Time to Selected</h3>
                        <p>Apply the selected time to Time-in for new attendance, or Time-out for checked-in mechanics.</p>
                        <label for="batchSharedTimeInput">Attendance Time</label>
                        <input type="time" id="batchSharedTimeInput" required />
                        <div class="batch-shared-time-actions">
                            <button type="button" class="secondary-btn" id="batchSharedTimeCancel">Cancel</button>
                            <button type="button" class="primary-btn" id="batchSharedTimeConfirm">Apply Time</button>
                        </div>
                    </div>
                </div>

                <div class="batch-table-shell">
                    <div class="batch-table-wrap">
                        <table class="batch-attendance-table">
                            <thead>
                                <tr>
                                    <th class="select-col"><input type="checkbox" id="batchSelectAll" aria-label="Select all rows"></th>
                                    <th>${label}</th>
                                    <th style="${type === 'mechanic' ? 'display:none' : ''}">${type === 'driver' ? 'Duty' : 'Shift'}</th>
                                    <th>${type === 'driver' ? 'Reporting' : 'Scheduled Time'}</th>
                                    <th>Time In</th>
                                    <th>${type === 'driver' ? 'Recorded' : 'Time Out'}</th>
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
                        <span>${type === 'mechanic' ? 'New mechanics: record Time-in. Checked-in mechanics: enter Time-out; saved Time-in and status remain locked.' : 'Drivers remain Present when checked in.'}</span>
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

        overlay.dataset.pageOwned = 'true';
        document.body.appendChild(overlay);
        document.body.classList.add('batch-modal-open');

        overlay.querySelectorAll('[data-batch-close]').forEach((el) => {
            el.addEventListener('click', closeModal);
        });
        overlay.querySelector('#batchAttendanceDate').addEventListener('change', loadRoster);
        overlay.querySelector('#batchAttendanceShift').addEventListener('change', loadRoster);
        overlay.querySelector('#batchDriverSearch')?.addEventListener('input', (event) => {
            const query = event.target.value.trim().toLowerCase();
            overlay.querySelectorAll('[data-batch-row]').forEach((row) => {
                row.hidden = !String(row.dataset.name || '').toLowerCase().includes(query);
            });
        });
        overlay.querySelector('#batchMarkPresent').addEventListener('click', markAllPresent);
        overlay.querySelector('#batchUseCurrentTime').addEventListener('click', () => {
            overlay.querySelectorAll('[data-batch-row]').forEach((row) => {
                if (!row.classList.contains('is-unavailable')) {
                    row.querySelector(row.dataset.recorded === 'true' ? '[data-time-out]' : '[data-time-in]').value = currentTime();
                    detectLate(row);
                    updateAvailability(row);
                }
            });
            updateSummary();
        });
        overlay.querySelector('#batchApplyTime').addEventListener('click', applySharedTime);
        overlay.querySelector('#batchSharedTimeCancel').addEventListener('click', closeSharedTime);
        overlay.querySelector('#batchSharedTimeConfirm').addEventListener('click', confirmSharedTime);
        overlay.querySelector('#batchSharedTimeInput').addEventListener('gct:time-selected', (event) => {
            confirmSharedTime(event.detail?.value);
        });
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
        const shift = type === 'mechanic' ? 'all' : modal.querySelector('#batchAttendanceShift').value;
        const body = modal.querySelector('#batchAttendanceBody');
        body.innerHTML = `<tr><td colspan="8" class="batch-loading">${spinnerMarkup()} Loading attendance roster…</td></tr>`;

        try {
            const response = await fetch(`${type === 'mechanic' ? '/maintenance/attendance/batch/mechanic' : '/operation/attendance/batch/driver'}?date=${encodeURIComponent(date)}&shift=${encodeURIComponent(shift)}`, {
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
                note.textContent = type === 'mechanic' ? 'New mechanics: record Time-in. Checked-in mechanics: enter Time-out; saved Time-in and status remain locked.' : 'Drivers remain Present when checked in.';
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
            body.innerHTML = type === 'driver'
                ? '<tr><td colspan="8" class="batch-empty">No active drivers found for this date.</td></tr>'
                : '<tr><td colspan="8" class="batch-empty">All mechanics have completed attendance for this date.</td></tr>';
            updateSummary();
            return;
        }

        body.innerHTML = rows.map((row) => {
            const normalizedStatus = normalizeStatus(row.status);
            const isRecorded = row.recorded === true;
            const initialAvailability = computeAvailability(normalizedStatus, row.assigned_job || '');
            return `
                <tr
                    data-batch-row
                    data-recorded="${isRecorded ? 'true' : 'false'}"
                    data-person-id="${escapeHtml(row.person_id)}"
                    data-name="${escapeHtml(row.name)}"
                    data-shift="${escapeHtml(row.shift)}"
                    data-assigned-job="${escapeHtml(row.assigned_job || '')}"
                >
                    <td class="select-col"><input type="checkbox" data-row-select ${(isRecorded && type === 'driver') ? 'disabled title="Already recorded for this date"' : (['Present', 'Late'].includes(normalizedStatus) ? 'checked' : '')}></td>
                    <td>
                        <div class="batch-person-cell">
                            <span class="batch-avatar">${escapeHtml(initials(row.name))}</span>
                            <div class="batch-person-meta">
                                <strong>${escapeHtml(row.name)}</strong>
                                <small>${isRecorded && type === 'mechanic' ? 'Time-in recorded · Awaiting time-out' : isRecorded ? 'Recorded · View in attendance list to edit' : 'New time-in · Not yet recorded'}</small>
                            </div>
                        </div>
                    </td>
                    <td style="${type === 'mechanic' ? 'display:none' : ''}">
                        <div class="batch-shift-meta">
                            <strong>${type === 'driver' ? 'Daily Attendance' : escapeHtml(row.shift)}</strong>
                            <small>${type === 'driver' ? 'No shift assignment' : escapeHtml(formatTime(shiftStarts[row.shift]) || '')}</small>
                        </div>
                    </td>
                    <td>
                        <span class="batch-scheduled-time">${type === 'driver' ? 'Flexible' : escapeHtml(formatTime(shiftStarts[row.shift]) || '--:--')}</span>
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
                        <div class="batch-time-cell batch-time-out-cell" style="${type === 'driver' ? 'display:none' : ''}">
                            <input type="time" data-time-out data-native-picker="true" value="${escapeHtml(row.time_out || '')}">
                        </div>
                        ${type === 'driver' ? '<span class="batch-scheduled-time">Daily</span>' : ''}
                    </td>
                    <td>
                        <div class="batch-status-group" role="group" aria-label="Attendance status">
                            ${type === 'driver' ? renderStatusButton('Unmarked', normalizedStatus) : ''}
                            ${renderStatusButton('Present', normalizedStatus)}
                            ${type === 'mechanic' ? renderStatusButton('Late', normalizedStatus) : ''}
                            ${renderStatusButton('Absent', normalizedStatus)}
                            ${renderStatusButton('On Leave', normalizedStatus)}
                        </div>
                    </td>
                    <td>
                        <span class="batch-availability-badge ${availabilityClass(initialAvailability)}" data-availability>${escapeHtml(initialAvailability)}</span>
                    </td>
                </tr>`;
        }).join('');

        body.querySelectorAll('[data-batch-row]').forEach((row) => {
            initializeRow(row);
            if (row.dataset.recorded === 'true') {
                row.querySelector('[data-time-in]').readOnly = true;
                row.querySelector('[data-row-now]').disabled = true;
                row.querySelectorAll('[data-status-btn]').forEach(button => { button.disabled = true; });
                row.classList.add('batch-awaiting-timeout');
            }
        });
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
                if (type === 'driver') row.querySelector('[data-row-select]').checked = statusButton.dataset.statusValue !== 'Unmarked';
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

        if (type === 'mechanic' && row.dataset.recorded !== 'true') timeOut.disabled = true;
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
        document.querySelectorAll('#batchAttendanceModal [data-batch-row]:not([data-recorded="true"])').forEach((row) => {
            setSelectedStatus(row, 'Present');
            if (type === 'driver') row.querySelector('[data-row-select]').checked = true;
            applyStatusRules(row, false);
            detectLate(row);
            updateAvailability(row);
        });
        updateSummary();
    }

    function clearAllRows() {
        document.querySelectorAll('#batchAttendanceModal [data-batch-row]:not([data-recorded="true"])').forEach((row) => {
            row.querySelector('[data-row-select]').checked = false;
            row.querySelector('[data-time-in]').value = '';
            row.querySelector('[data-time-out]').value = '';
            setSelectedStatus(row, type === 'driver' ? 'Unmarked' : 'Present');
            applyStatusRules(row, false);
            updateAvailability(row);
        });
        updateSummary();
    }

    function closeSharedTime() {
        const modal = document.getElementById('batchAttendanceModal');
        if (!modal) return;
        const layer = modal.querySelector('#batchSharedTimeLayer');
        layer.hidden = true;
        layer.classList.remove('is-picker-only');
        modal.querySelector('#batchApplyTime')?.focus();
    }

    function applySharedTime() {
        const modal = document.getElementById('batchAttendanceModal');
        if (!modal) return;
        const selectedRows = [...modal.querySelectorAll('[data-batch-row]')]
            .filter((row) => row.querySelector('[data-row-select]').checked && !row.classList.contains('is-unavailable'));
        if (!selectedRows.length) {
            toast('Select at least one available personnel row.', 'warning');
            return;
        }

        const input = modal.querySelector('#batchSharedTimeInput');
        input.value = currentTime();
        input.dataset.pickerAnchor = '#batchApplyTime';
        const layer = modal.querySelector('#batchSharedTimeLayer');
        layer.hidden = false;
        layer.classList.add('is-picker-only');
        // The shared GCT picker enhances inputs added dynamically.
        // Wait for its trigger to be installed, then open the actual Choose a Time panel.
        const openPicker = (attempt = 0) => {
            if (layer.hidden) return;
            const trigger = input.parentElement?.querySelector('.gct-picker-trigger');
            if (trigger) {
                trigger.click();
            } else if (attempt < 10) {
                window.setTimeout(() => openPicker(attempt + 1), 25);
            } else {
                layer.classList.remove('is-picker-only');
                toast('Time picker could not be opened. Please try again.', 'error');
            }
        };
        openPicker();
    }

    function confirmSharedTime(selectedTime = null) {
        const modal = document.getElementById('batchAttendanceModal');
        if (!modal) return;
        const input = modal.querySelector('#batchSharedTimeInput');
        const layer = modal.querySelector('#batchSharedTimeLayer');
        if (layer.hidden) return;
        const time = typeof selectedTime === 'string' ? selectedTime : input.value;
        if (!/^([01]\d|2[0-3]):[0-5]\d$/.test(time)) {
            input.setCustomValidity('Choose a valid time.');
            input.reportValidity();
            input.setCustomValidity('');
            return;
        }
        let updated = 0;
        modal.querySelectorAll('[data-batch-row]').forEach((row) => {
            if (!row.querySelector('[data-row-select]').checked || row.classList.contains('is-unavailable')) return;
            const target = row.querySelector(row.dataset.recorded === 'true' ? '[data-time-out]' : '[data-time-in]');
            target.value = time;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            detectLate(row);
            updateAvailability(row);
            updated += 1;
        });
        updateSummary();
        closeSharedTime();

    }

    function applyStatusRules(row, clear = true) {
        if (row.dataset.recorded === 'true') return;
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
        if (row.dataset.recorded === 'true') return;
        const status = selectedStatus(row);
        const time = row.querySelector('[data-time-in]').value;
        if (!time || ['Absent', 'On Leave'].includes(status)) return;

        const shift = row.dataset.shift;
        const start = type === 'mechanic' ? '08:00' : shiftStarts[shift];
        if (!start) return;

        const late = minutes(time) > minutes(String(start).slice(0, 5)) + graceMinutes;
        setSelectedStatus(row, type === 'mechanic' && late ? 'Late' : 'Present');
    }

    function updateAvailability(row) {
        const badge = row.querySelector('[data-availability]');
        if (!badge) return;

        const availability = computeAvailability(selectedStatus(row), row.dataset.assignedJob || '');
        badge.textContent = availability;
        badge.className = `batch-availability-badge ${availabilityClass(availability)}`;
    }

    function computeAvailability(status, assignedJob) {
        if (status === 'Unmarked') return 'Not recorded';
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

        const pendingRows = [...modal.querySelectorAll('[data-batch-row]')]
             .filter((row) => (row.dataset.recorded !== 'true'
                 ? (type !== 'mechanic' || Boolean(row.querySelector('[data-time-in]').value) || ['Absent', 'On Leave'].includes(selectedStatus(row)))
                 : (type === 'mechanic' && Boolean(row.querySelector('[data-time-out]').value)))
                && (type !== 'driver' || (selectedStatus(row) !== 'Unmarked' && row.querySelector('[data-row-select]').checked))
                && (type !== 'mechanic' || row.querySelector('[data-row-select]').checked));
        const rows = pendingRows.map((row) => ({
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
            const response = await fetch(type === 'mechanic' ? '/maintenance/attendance/batch/mechanic' : '/operation/attendance/batch/driver', {
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
        return row.querySelector('[data-status-btn].is-active')?.dataset.statusValue || (type === 'driver' ? 'Unmarked' : 'Present');
    }

    function setSelectedStatus(row, status) {
        row.querySelectorAll('[data-status-btn]').forEach((statusButton) => {
            statusButton.classList.toggle('is-active', statusButton.dataset.statusValue === status);
        });
    }

    function normalizeStatus(status) {
        return ['Present', 'Absent', 'On Leave', ...(type === 'driver' ? ['Unmarked'] : ['Late'])].includes(status) ? status : (type === 'driver' ? 'Unmarked' : 'Present');
    }

    function statusThemeClass(value) {
        return {
            Unmarked: 'unmarked',
            Present: 'present',
            Late: 'late',
            Absent: 'absent',
            'On Leave': 'leave',
        }[value] || '';
    }

    function availabilityClass(value) {
        if (value === busyLabel) return 'busy';
        if (value === 'Unavailable') return 'unavailable';
        if (value === 'Not recorded') return 'unmarked';
        return 'available';
    }

    function updateSummary() {
        const modal = document.getElementById('batchAttendanceModal');
        const summary = modal?.querySelector('#batchAttendanceSummary');
        if (!modal || !summary) return;

        const rows = [...modal.querySelectorAll('[data-batch-row]')];
        const counts = {
            Unmarked: 0,
            Present: 0,
            Late: 0,
            Absent: 0,
            'On Leave': 0,
            Incomplete: 0,
        };

        rows.forEach((row) => {
            if (row.dataset.recorded === 'true') return;
            const status = selectedStatus(row);
            counts[status] = (counts[status] || 0) + 1;

            if (status !== 'Unmarked' && !['Absent', 'On Leave'].includes(status) && !row.querySelector('[data-time-in]').value) {
                counts.Incomplete += 1;
            }
        });

        summary.innerHTML = [
            ...(type === 'driver' ? [['Unmarked', 'unmarked', '#94a3b8']] : []),
            ['Present', 'present', '#2fb45e'],
            ['Late', 'late', '#ff9518'],
            ['Absent', 'absent', '#ef3d3d'],
            ['On Leave', 'leave', '#7c3aed'],
            ['Incomplete', 'incomplete', '#94a3b8'],
        ].map(([name, className, color]) => `
            <div class="batch-summary-card ${className}">
                <div class="batch-summary-label"><i class="fa-solid fa-circle" style="color:${color}"></i>${type === 'mechanic' && name === 'Incomplete' ? 'Missing Time In' : name}</div>
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
}

window.GCTPartialNavigation.registerInitializer(
    'operation-batch-attendance',
    '[data-batch-attendance-open]',
    initBatchAttendancePage,
);
