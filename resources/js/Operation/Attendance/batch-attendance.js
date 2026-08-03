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
            <div class="batch-attendance-modal" role="dialog" aria-modal="true">
                <div class="batch-attendance-header">
                    <div>
                        <span class="batch-eyebrow">Paper attendance encoding</span>
                        <h2>Record Daily ${label} Attendance</h2>
                        <p>Mark everyone present, then change only the exceptions from the paper sheet.</p>
                    </div>
                    <button type="button" class="close-btn" data-batch-close>&times;</button>
                </div>

                <div class="batch-attendance-controls">
                    <label>Date<input type="date" id="batchAttendanceDate" value="${today()}" /></label>
                    <label>Shift<select id="batchAttendanceShift">
                        <option value="all">All Shifts</option>
                        <option value="Morning">Morning</option>
                        <option value="Afternoon">Afternoon</option>
                        <option value="Night">Night</option>
                    </select></label>
                    <button type="button" class="batch-control-btn" id="batchReload"><i class="fa-solid fa-rotate"></i> Load Roster</button>
                    <button type="button" class="batch-control-btn" id="batchMarkPresent"><i class="fa-solid fa-user-check"></i> Mark All Present</button>
                </div>

                <div class="batch-bulk-tools">
                    <label class="batch-check-all"><input type="checkbox" id="batchSelectAll"> Select all rows</label>
                    <div class="batch-time-apply">
                        <input type="time" id="batchSharedTime">
                        <button type="button" class="batch-control-btn" id="batchUseCurrentTime">Use Current Time</button>
                        <button type="button" class="batch-control-btn primary" id="batchApplyTime">Apply Time to Selected</button>
                    </div>
                </div>

                <div class="batch-attendance-table-wrap">
                    <table class="batch-attendance-table">
                        <thead><tr>
                            <th></th><th>${label}</th><th>Shift</th><th>Availability</th><th>Time In</th><th>Time Out</th><th>Status</th>
                        </tr></thead>
                        <tbody id="batchAttendanceBody"><tr><td colspan="7" class="batch-loading">Loading attendance roster…</td></tr></tbody>
                    </table>
                </div>

                <div class="batch-attendance-footer">
                    <div id="batchAttendanceSummary" class="batch-summary">No records loaded.</div>
                    <div class="batch-footer-actions">
                        <button type="button" class="secondary-btn" data-batch-close>Cancel</button>
                        <button type="button" class="primary-btn" id="batchSaveAttendance"><i class="fa-solid fa-floppy-disk"></i> Save All Attendance</button>
                    </div>
                </div>
            </div>`;

        document.body.appendChild(overlay);
        document.body.classList.add('batch-modal-open');

        overlay.querySelectorAll('[data-batch-close]').forEach((el) => el.addEventListener('click', closeModal));
        overlay.addEventListener('click', (event) => { if (event.target === overlay) closeModal(); });
        overlay.querySelector('#batchReload').addEventListener('click', loadRoster);
        overlay.querySelector('#batchAttendanceDate').addEventListener('change', loadRoster);
        overlay.querySelector('#batchAttendanceShift').addEventListener('change', loadRoster);
        overlay.querySelector('#batchMarkPresent').addEventListener('click', markAllPresent);
        overlay.querySelector('#batchUseCurrentTime').addEventListener('click', () => {
            overlay.querySelector('#batchSharedTime').value = currentTime();
        });
        overlay.querySelector('#batchApplyTime').addEventListener('click', applySharedTime);
        overlay.querySelector('#batchSelectAll').addEventListener('change', (event) => {
            overlay.querySelectorAll('[data-row-select]').forEach((input) => { input.checked = event.target.checked; });
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
        body.innerHTML = '<tr><td colspan="7" class="batch-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading attendance roster…</td></tr>';

        try {
            const response = await fetch(`/operation/attendance/batch/${type}?date=${encodeURIComponent(date)}&shift=${encodeURIComponent(shift)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            if (!response.ok) throw new Error(errorMessage(data));
            shiftStarts = data.shift_starts || {};
            graceMinutes = Number(data.grace_minutes || 10);
            renderRows(Array.isArray(data.rows) ? data.rows : []);
        } catch (error) {
            body.innerHTML = `<tr><td colspan="7" class="batch-error">${escapeHtml(error.message || 'Unable to load attendance roster.')}</td></tr>`;
        }
    }

    function renderRows(rows) {
        const modal = document.getElementById('batchAttendanceModal');
        const body = modal?.querySelector('#batchAttendanceBody');
        if (!body) return;

        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="7" class="batch-empty">No existing personnel records were found. Add or import personnel first.</td></tr>';
            updateSummary();
            return;
        }

        body.innerHTML = rows.map((row, index) => `
            <tr data-batch-row data-person-id="${escapeHtml(row.person_id)}" data-name="${escapeHtml(row.name)}" data-shift="${escapeHtml(row.shift)}" data-assigned-job="${escapeHtml(row.assigned_job || '')}">
                <td><input type="checkbox" data-row-select></td>
                <td><strong>${escapeHtml(row.name)}</strong><small>${escapeHtml(row.person_id)}</small></td>
                <td><span class="batch-shift">${escapeHtml(row.shift)}</span></td>
                <td><span class="batch-availability ${row.availability === 'Available' ? 'available' : 'busy'}">${escapeHtml(row.availability)}</span></td>
                <td><div class="batch-time-cell"><input type="time" data-time-in value="${escapeHtml(row.time_in || '')}"><button type="button" title="Use current time" data-row-now><i class="fa-solid fa-clock"></i></button></div></td>
                <td><input type="time" data-time-out value="${escapeHtml(row.time_out || '')}"></td>
                <td><select data-status>
                    ${['Present', 'Late', 'Absent', 'On Leave'].map((status) => `<option value="${status}" ${row.status === status ? 'selected' : ''}>${status}</option>`).join('')}
                </select><small class="batch-auto-status">Late is detected automatically.</small></td>
            </tr>`).join('');

        body.querySelectorAll('[data-batch-row]').forEach((row) => initializeRow(row));
        updateSummary();
    }

    function initializeRow(row) {
        const status = row.querySelector('[data-status]');
        const timeIn = row.querySelector('[data-time-in]');
        const timeOut = row.querySelector('[data-time-out]');
        const nowButton = row.querySelector('[data-row-now]');

        status.addEventListener('change', () => { applyStatusRules(row); updateSummary(); });
        timeIn.addEventListener('change', () => { detectLate(row); updateSummary(); });
        timeOut.addEventListener('change', updateSummary);
        nowButton.addEventListener('click', () => { timeIn.value = currentTime(); detectLate(row); updateSummary(); });
        applyStatusRules(row, false);
    }

    function markAllPresent() {
        document.querySelectorAll('#batchAttendanceModal [data-batch-row]').forEach((row) => {
            row.querySelector('[data-status]').value = 'Present';
            applyStatusRules(row, false);
            detectLate(row);
        });
        updateSummary();
    }

    function applySharedTime() {
        const modal = document.getElementById('batchAttendanceModal');
        const time = modal?.querySelector('#batchSharedTime').value;
        if (!time) return toast('Choose a time first.', 'warning');
        const selected = [...modal.querySelectorAll('[data-batch-row]')].filter((row) => row.querySelector('[data-row-select]').checked);
        if (!selected.length) return toast('Select at least one personnel row.', 'warning');
        selected.forEach((row) => {
            const status = row.querySelector('[data-status]').value;
            if (!['Absent', 'On Leave'].includes(status)) {
                row.querySelector('[data-time-in]').value = time;
                detectLate(row);
            }
        });
        updateSummary();
    }

    function applyStatusRules(row, clear = true) {
        const status = row.querySelector('[data-status]').value;
        const timeIn = row.querySelector('[data-time-in]');
        const timeOut = row.querySelector('[data-time-out]');
        const disabled = ['Absent', 'On Leave'].includes(status);
        timeIn.disabled = disabled;
        timeOut.disabled = disabled;
        row.classList.toggle('is-unavailable', disabled);
        if (disabled && clear) { timeIn.value = ''; timeOut.value = ''; }
    }

    function detectLate(row) {
        const status = row.querySelector('[data-status]');
        const time = row.querySelector('[data-time-in]').value;
        if (!time || ['Absent', 'On Leave'].includes(status.value)) return;
        const shift = row.dataset.shift;
        const start = shiftStarts[shift];
        if (!start) return;
        status.value = minutes(time) > minutes(start.slice(0, 5)) + graceMinutes ? 'Late' : 'Present';
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
            status: row.querySelector('[data-status]').value,
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
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="_token"]')?.value || '',
                },
                body: JSON.stringify({ attendance_date: modal.querySelector('#batchAttendanceDate').value, rows }),
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
            const status = row.querySelector('[data-status]').value;
            counts[status] = (counts[status] || 0) + 1;
            if (['Present', 'Late'].includes(status) && !row.querySelector('[data-time-in]').value) counts.Incomplete++;
        });
        summary.innerHTML = `<strong>${rows.length} personnel</strong><span>Present ${counts.Present}</span><span>Late ${counts.Late}</span><span>Absent ${counts.Absent}</span><span>On Leave ${counts['On Leave']}</span><span class="${counts.Incomplete ? 'warning' : ''}">Incomplete ${counts.Incomplete}</span>`;
    }

    function closeModal() {
        document.getElementById('batchAttendanceModal')?.remove();
        document.body.classList.remove('batch-modal-open');
    }

    function toast(message, type) {
        if (typeof window.showSystemToast === 'function') window.showSystemToast(message, type, type === 'success' ? 'Attendance Saved' : null);
        else window.alert(message);
    }

    function today() { return new Date().toISOString().slice(0, 10); }
    function currentTime() { const d = new Date(); return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`; }
    function minutes(value) { const [h, m] = String(value).split(':').map(Number); return (h * 60) + m; }
    function errorMessage(data) { return data?.message || Object.values(data?.errors || {}).flat()[0] || 'Request failed.'; }
    function escapeHtml(value) { const div = document.createElement('div'); div.textContent = String(value ?? ''); return div.innerHTML; }
});
