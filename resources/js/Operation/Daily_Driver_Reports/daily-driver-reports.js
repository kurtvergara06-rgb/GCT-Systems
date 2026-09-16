document.addEventListener('DOMContentLoaded', () => {
    initSearchableCombos();
    initScheduleContextPanel();
});

/* =========================================================
   SEARCHABLE COMBO SELECT
========================================================= */

function initSearchableCombos() {
    document.querySelectorAll('[data-combo]').forEach((combo) => {
        const input = combo.querySelector('[data-combo-input]');
        const hidden = combo.querySelector('[data-combo-value]');
        const clearBtn = combo.querySelector('[data-combo-clear]');
        const list = combo.querySelector('[data-combo-list]');
        const options = Array.from(list ? list.querySelectorAll('[data-combo-option]') : []);

        const selectedLabel = (value) => {
            const option = options.find((opt) => opt.dataset.value === String(value));
            return option ? option.querySelector('strong').textContent : '';
        };

        const open = () => {
            if (options.length > 0) {
                combo.classList.add('open');
                filter(input.value);
            }
        };

        const close = () => {
            combo.classList.remove('open');
        };

        const filter = (term) => {
            const needle = term.toLowerCase().trim();
            let visibleCount = 0;

            options.forEach((option) => {
                const label = option.querySelector('strong')
                    ? option.querySelector('strong').textContent.toLowerCase()
                    : '';
                const detail = option.querySelector('small')
                    ? option.querySelector('small').textContent.toLowerCase()
                    : '';
                const haystack = [option.dataset.search || '', label, detail].join(' ');

                const show = !needle || haystack.includes(needle);
                option.style.display = show ? '' : 'none';
                if (show) visibleCount++;
            });

            let emptyHint = list.querySelector('[data-combo-empty]');

            if (visibleCount === 0) {
                if (!emptyHint) {
                    emptyHint = document.createElement('li');
                    emptyHint.className = 'is-empty';
                    emptyHint.setAttribute('data-combo-empty', '');
                    emptyHint.textContent = 'No records found.';
                    list.appendChild(emptyHint);
                }
                emptyHint.style.display = '';
            } else if (emptyHint) {
                emptyHint.style.display = 'none';
            }
        };

        const select = (value, label) => {
            hidden.value = value;
            input.value = label;
            combo.classList.add('has-value');
            close();

            window.dispatchEvent(new CustomEvent('combo:select', {
                detail: { field: combo.dataset.field || '', value },
            }));
        };

        const clear = () => {
            hidden.value = '';
            input.value = '';
            combo.classList.remove('has-value');
        };

        input.addEventListener('focus', open);

        input.addEventListener('input', () => {
            clear();
            open();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') close();
        });

        options.forEach((option) => {
            option.addEventListener('mousedown', (e) => {
                e.preventDefault();
                select(option.dataset.value, option.querySelector('strong').textContent);
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                clear();
                input.focus();
            });
        }

        document.addEventListener('click', (e) => {
            if (!combo.contains(e.target)) close();
        });

        if (hidden.value) {
            const label = selectedLabel(hidden.value);
            if (label) {
                input.value = label;
                combo.classList.add('has-value');
            }
        }
    });
}

/* =========================================================
   SCHEDULE CONTEXT PANEL (create page)
========================================================= */

function initScheduleContextPanel() {
    const statusEl = document.getElementById('sfcStatus');
    const listEl = document.getElementById('sfcList');
    if (!statusEl || !listEl) return;

    const comboValues = () => ({
        date: document.querySelector('input[name="report_date"]')?.value || '',
        driverId: document.querySelector('input[name="driver_id"]')?.value || '',
        busId: document.querySelector('input[name="bus_id"]')?.value || '',
    });

    const showStatus = (cls, iconClass, message) => {
        statusEl.className = 'ddr-sched-status ' + cls;

        let icon = statusEl.querySelector('i');
        if (!icon) {
            icon = document.createElement('i');
            statusEl.prepend(icon);
        }
        icon.className = 'fa-solid ' + iconClass;

        let span = statusEl.querySelector('span');
        if (!span) {
            span = document.createElement('span');
            statusEl.appendChild(span);
        }
        span.textContent = message;
    };

    const renderSchedules = (schedules) => {
        listEl.innerHTML = '';
        listEl.classList.remove('has-items');

        schedules.forEach((schedule) => {
            const item = document.createElement('div');
            item.className = 'ddr-sched-item';

            const head = document.createElement('div');
            const code = document.createElement('strong');
            code.textContent = schedule.trip_code || '—';

            const time = document.createElement('span');
            time.textContent = schedule.departure_time + ' → ' + schedule.estimated_arrival_time;

            head.appendChild(code);
            head.appendChild(time);

            const route = document.createElement('small');
            route.textContent = [
                schedule.route_label,
                schedule.shift ? schedule.shift + ' shift' : '',
                schedule.status,
            ].filter(Boolean).join(' • ');

            item.appendChild(head);
            item.appendChild(route);

            listEl.appendChild(item);
        });

        if (schedules.length > 0) {
            listEl.classList.add('has-items');
        }
    };

    const lookup = async () => {
        const { date, driverId, busId } = comboValues();

        listEl.innerHTML = '';
        listEl.classList.remove('has-items');

        if (!date || !driverId || !busId) {
            showStatus('idle', 'fa-magnifying-glass', 'Select a date, driver, and bus to check matching scheduled trips.');
            return;
        }

        showStatus('loading', 'fa-circle-notch', 'Checking scheduled trips...');

        try {
            const query = new URLSearchParams({
                report_date: date,
                driver_id: driverId,
                bus_id: busId,
            });

            const response = await fetch(
                window.ddrScheduleLookupUrl + '?' + query.toString(),
                { headers: { 'Accept': 'application/json' } }
            );

            const payload = await response.json();

            const rows = payload.schedules || [];

            renderSchedules(rows);

            showStatus(
                rows.length > 0 ? 'matched' : 'unmatched',
                rows.length > 0 ? 'fa-circle-check' : 'fa-triangle-exclamation',
                payload.message || 'Schedule match unavailable.'
            );
        } catch (error) {
            showStatus('unmatched', 'fa-triangle-exclamation', 'Unable to check scheduled trips right now. The report can still be encoded.');
        }
    };

    const dateInput = document.querySelector('input[name="report_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', lookup);
    }

    document.addEventListener('combo:select', lookup);
}