window.GCTPartialNavigation.registerInitializer('operation-daily-driver-reports', '.ddr-page', () => {
    initSearchableCombos();
    initDdrDriverFirstLayout();
    initDdrExceptionMode();
    initDdrAssignedDrivers();
    initScheduleContextPanel();
    initDdrEncodeModal();
    initDdrAdditionalTrips();
    initDdrCustomValidation();
    initDdrDeleteModal();
    initDdrRecordModals();
    initDdrEditTripSections();
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
            // Scheduled DDR bus is derived from its assignment, not manually selected.
            if (combo.dataset.field === 'bus' && input.readOnly) return;
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

                const show = !option.hidden && (!needle || haystack.includes(needle));
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
            if (combo.dataset.field === 'bus' && input.readOnly) return;
            hidden.value = value;
            input.value = label;
            combo.classList.add('has-value');
            close();

            window.dispatchEvent(new CustomEvent('combo:select', {
                detail: { field: combo.dataset.field || '', value, source: combo },
            }));
        };

        const clear = () => {
            if (combo.dataset.field === 'bus' && input.readOnly) return;
            const hadSelection = Boolean(hidden.value);
            hidden.value = '';
            input.value = '';
            combo.classList.remove('has-value');
            // Clearing a driver must also clear its selected trip and assigned bus.
            if (hadSelection && combo.dataset.field === 'driver') {
                window.dispatchEvent(new CustomEvent('combo:select', {
                    detail: { field: 'driver', value: '', source: combo },
                }));
            }
        };

        input.addEventListener('focus', open);

        // Preserve the user's search text. The old clear() call emptied it on every keystroke.
        input.addEventListener('input', () => {
            if (combo.dataset.field === 'bus' && input.readOnly) return;
            const hadSelection = Boolean(hidden.value);
            hidden.value = '';
            combo.classList.remove('has-value');
            if (hadSelection && combo.dataset.field === 'driver') {
                window.dispatchEvent(new CustomEvent('combo:select', {
                    detail: { field: 'driver', value: '', source: combo },
                }));
            }
            open();
        });

        // Accept an unambiguous exact driver name/ID, including keyboard entry.
        const selectExactMatch = () => {
            if (combo.dataset.field !== 'driver' || hidden.value || !input.value.trim()) return false;
            const search = input.value.trim().toLocaleLowerCase();
            const matches = options.filter(option => !option.hidden && (
                option.querySelector('strong')?.textContent.trim().toLocaleLowerCase() === search
                || String(option.dataset.value).toLocaleLowerCase() === search
            ));
            if (matches.length !== 1) return false;
            const option = matches[0];
            select(option.dataset.value, option.querySelector('strong').textContent);
            return true;
        };

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                close();
            } else if (e.key === 'Enter' && combo.dataset.field === 'driver' && selectExactMatch()) {
                e.preventDefault();
            }
        });
        input.addEventListener('blur', () => {
            // Let suggestion mousedown commit first; do not choose ambiguous partial names.
            selectExactMatch();
        });

        options.forEach((option) => {
            option.addEventListener('mousedown', (e) => {
                e.preventDefault();
                if (option.hidden) return;
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

function initDdrAssignedDrivers() {
    const modal = document.getElementById('ddrEncodeModal');
    const date = modal?.querySelector('[name="report_date"]');
    const driverCombo = modal?.querySelector('[data-combo][data-field="driver"]');
    const exception = modal?.querySelector('#ddrUnscheduledMode');
    if (!date || !driverCombo || !window.ddrAssignedDriversUrl) return;
    let requestToken = 0;
    const update = async () => {
        const token = ++requestToken;
        const options = Array.from(driverCombo.querySelectorAll('[data-combo-option]'));
        if (exception?.checked) {
            options.forEach((option) => { option.hidden = false; option.style.display = ''; });
            return;
        }
        if (!date.value) {
            // The scheduled DDR driver list is date-scoped; do not show unrelated drivers.
            options.forEach((option) => { option.hidden = true; option.style.display = 'none'; });
            driverCombo.classList.remove('open');
            return;
        }
        options.forEach((option) => { option.hidden = true; option.style.display = 'none'; });
        driverCombo.classList.remove('open');
        try {
            const response = await fetch(window.ddrAssignedDriversUrl + '?' + new URLSearchParams({report_date: date.value}), {
                headers: {'Accept': 'application/json'}
            });
            if (!response.ok) throw new Error('Unable to load assigned drivers');
            const data = await response.json();
            if (token !== requestToken) return;
            const allowed = new Set((data.driver_ids || []).map(String));
            options.forEach((option) => {
                const visible = allowed.has(String(option.dataset.value));
                option.hidden = !visible;
                option.style.display = visible ? '' : 'none';
            });
            const selected = driverCombo.querySelector('[data-combo-value]');
            if (selected?.value && !allowed.has(String(selected.value))) {
                selected.value = '';
                const input = driverCombo.querySelector('[data-combo-input]');
                if (input) input.value = '';
                driverCombo.classList.remove('has-value');
                // A date change invalidating the driver must also clear its old trip/bus.
                window.dispatchEvent(new CustomEvent('combo:select', {
                    detail: { field: 'driver', value: '', source: driverCombo },
                }));
            }
        } catch (error) {
            if (token !== requestToken) return;
            // Fail closed: an unsuccessful availability lookup must not display every driver.
            options.forEach((option) => { option.hidden = true; option.style.display = 'none'; });
            driverCombo.classList.remove('open');
        }
    };
    date.addEventListener('change', update);
    exception?.addEventListener('change', update);
    update();
}

function initDdrExceptionMode() {
    const modal = document.getElementById('ddrEncodeModal');
    const checkbox = modal?.querySelector('#ddrUnscheduledMode');
    const reasonWrap = modal?.querySelector('#ddrUnscheduledReasonWrap');
    const reasonInput = modal?.querySelector('#ddrUnscheduledReason');
    const tripPanel = modal?.querySelector('.ddr-modal-sched-panel');
    const selectedId = modal?.querySelector('#ddrSelectedTripId');
    const busField = modal?.querySelector('[name="bus_id"]')?.closest('.ui-form-group, .ddr-form-group');
    if (!checkbox || !reasonWrap) return;
    const sync = () => {
        reasonWrap.hidden = !checkbox.checked;
        if (reasonInput) reasonInput.required = checkbox.checked;
        if (tripPanel) tripPanel.hidden = checkbox.checked;
        if (busField) busField.hidden = false;
        const busInput = modal?.querySelector('[data-combo][data-field="bus"] [data-combo-input]');
        if (busInput) busInput.readOnly = !checkbox.checked;
        if (!checkbox.checked) modal?.querySelector('[data-combo][data-field="bus"]')?.classList.remove('open');
        for (const name of ['from_location', 'to_location']) {
            const input = modal?.querySelector('[name="' + name + '"]');
            if (input) input.readOnly = false;
        }
        if (checkbox.checked && selectedId) {
            selectedId.value = '';
            const busCombo = modal?.querySelector('[data-combo][data-field="bus"]');
            if (busCombo) {
                const busValue = busCombo.querySelector('[data-combo-value]');
                const busText = busCombo.querySelector('[data-combo-input]');
                if (busValue) busValue.value = '';
                if (busText) busText.value = '';
                busCombo.classList.remove('has-value');
            }
        }
    };
    checkbox.addEventListener('change', sync);
    modal?.querySelector('form')?.addEventListener('reset', () => {
        checkbox.checked = false;
        sync();
    });
    sync();
}

/* Keep the assignment choice adjacent to the date/driver inputs, not hidden below the form. */
function initDdrDriverFirstLayout() {
    const modal = document.getElementById('ddrEncodeModal');
    if (!modal || modal.dataset.ddrLayoutReady === '1') return;
    const grid = modal?.querySelector('.ddr-modal-form-grid');
    const panel = modal?.querySelector('.ddr-modal-sched-panel');
    if (!grid || !panel) return;
    const dateField = grid.querySelector('[name="report_date"]')?.closest('.ui-form-group, .ddr-form-group');
    const driverField = grid.querySelector('[name="driver_id"]')?.closest('.ui-form-group, .ddr-form-group');
    if (!dateField || !driverField) return;
    // One physical DDR header (date, driver, bus), followed by Trip 1.
    const busField = grid.querySelector('[name="bus_id"]')?.closest('.ui-form-group, .ddr-form-group');
    const heading = modal.querySelector('.ddr-report-heading');
    if (!busField || !heading) return;
    modal.dataset.ddrLayoutReady = '1';
    const meta = document.createElement('div');
    meta.className = 'ddr-report-meta-grid';
    heading.after(meta);
    const numberField = document.createElement('div');
    numberField.className = 'ddr-number-preview';
    numberField.innerHTML = '<label>DDR Number</label><span><i class="fa-solid fa-hashtag" aria-hidden="true"></i> Auto-generated</span>';
    driverField.classList.add('ddr-meta-driver');
    busField.classList.add('ddr-meta-bus');
    dateField.classList.add('ddr-meta-date');
    numberField.classList.add('ddr-meta-number');
    // Report date determines eligible drivers and trip assignments.
    // Layout: Date | DDR Number, then Driver | Bus.
    meta.append(dateField, numberField, driverField, busField);

    const firstTripHeading = document.createElement('div');
    firstTripHeading.className = 'ddr-trip-one-heading';
    firstTripHeading.innerHTML = '<strong>Trip 1</strong><span class="ddr-trip-assigned-tag"><i class="fa-solid fa-link" aria-hidden="true"></i> Assigned trip</span>';

    const primaryTrip = document.createElement('section');
    primaryTrip.className = 'ddr-primary-trip';
    grid.before(primaryTrip);
    primaryTrip.append(firstTripHeading, grid);
    grid.prepend(panel);
    panel.style.gridColumn = '1 / -1';

    const entriesHeader = modal.querySelector('.ddr-multi-header');
    if (entriesHeader) {
        primaryTrip.before(entriesHeader);
    }
}

/* =========================================================
   SCHEDULE CONTEXT PANEL (create page + encode modal)
========================================================= */

function initScheduleContextPanel() {
    const statusEl = document.getElementById('sfcStatus');
    const listEl = document.getElementById('sfcList');
    if (!statusEl || !listEl) return;

    const root =
        statusEl.closest('#ddrEncodeModal')
        || statusEl.closest('.ddr-encode-layout')
        || document;

    const find = (selector) => root.querySelector(selector);

    const comboValues = () => ({
        date: find('input[name="report_date"]')?.value || '',
        driverId: find('input[name="driver_id"]')?.value || '',
        busId: find('input[name="bus_id"]')?.value || '',
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

    const selectComboValue = (field, value, fallbackLabel) => {
        const combo = root.querySelector('[data-combo][data-field="' + field + '"]');
        if (!combo || !value) return;
        const input = combo.querySelector('[data-combo-input]');
        const hidden = combo.querySelector('[data-combo-value]');
        const option = Array.from(combo.querySelectorAll('[data-combo-option]'))
            .find(item => String(item.dataset.value) === String(value));
        if (!input || !hidden) return;
        hidden.value = String(value);
        input.value = option?.querySelector('strong')?.textContent || fallbackLabel || String(value);
        combo.classList.add('has-value');
    };
    const renderSchedules = (schedules) => {
        listEl.replaceChildren();
        root.ddrEligibleSchedules = schedules;
        root.dispatchEvent(new CustomEvent('ddr:schedules-updated', {detail: {schedules}}));
        const list = document.createElement('div');
        list.className = 'ddr-ready-trip-list';
        const choose = (schedule, card) => {
            list.querySelectorAll('.ddr-ready-trip-card').forEach(item => {
                item.classList.toggle('is-selected', item === card);
                item.setAttribute('aria-pressed', item === card ? 'true' : 'false');
            });
            const selectedId = root.querySelector('#ddrSelectedTripId');
            if (selectedId) selectedId.value = String(schedule.id);
            root.dispatchEvent(new CustomEvent('ddr:primary-assignment-changed'));
            selectComboValue('driver', schedule.driver_id, schedule.driver_name);
            const busInput = root.querySelector('[data-combo][data-field="bus"] [data-combo-input]');
            if (busInput) busInput.readOnly = true;
            // The bus comes from this exact trip assignment, never a separate search.
            selectComboValue('bus', schedule.bus_id, schedule.bus_no);
            for (const name of ['from_location', 'to_location']) {
                const input = find('input[name="' + name + '"]');
                if (input) input.readOnly = false;
            }
            showStatus('matched', 'fa-circle-check',
                'Trip ' + schedule.trip_code + ' linked · Assigned bus: ' + (schedule.bus_no || 'Unavailable') +
                '. Record actual locations and travel times from the physical DDR.');
        };
        schedules.forEach(schedule => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'ddr-ready-trip-card';
            card.setAttribute('aria-pressed', 'false');
            const details = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = [schedule.trip_code, schedule.route_label].filter(Boolean).join(' · ');
            const sub = document.createElement('small');
            sub.textContent = [schedule.driver_name, schedule.bus_no && 'Bus ' + schedule.bus_no,
                schedule.departure_time + '–' + schedule.estimated_arrival_time].filter(Boolean).join(' · ');
            details.append(name, sub);
            const status = document.createElement('span');
            status.className = 'ddr-ready-trip-status';
            status.textContent = schedule.status || 'Awaiting DDR';
            card.append(details, status);
            card.addEventListener('click', () => choose(schedule, card));
            list.appendChild(card);
        });
        if (!schedules.length) {
            const empty = document.createElement('p');
            empty.textContent = 'No completed-service assignments awaiting DDR for this date and driver.';
            list.appendChild(empty);
        }
        listEl.appendChild(list);
        if (schedules.length === 1) {
            // One eligible assignment: select the trip and fill its actual assigned bus.
            choose(schedules[0], list.querySelector('.ddr-ready-trip-card'));
        } else if (schedules.length > 1) {
            // Multiple trips can be associated with a driver. Never guess the trip.
            // If all eligible assignments use the same bus, it is safe to prefill it.
            const busIds = [...new Set(schedules.map(schedule => String(schedule.bus_id || '')))];
            if (busIds.length === 1 && busIds[0]) {
                selectComboValue('bus', busIds[0], schedules[0].bus_no);
            } else {
                showStatus('matched', 'fa-circle-info',
                    'This driver has trips on different buses. Select the correct trip to fill its bus.');
            }
        }
        listEl.classList.toggle('has-items', schedules.length > 0);
    };

    let lookupSequence = 0;
    const lookup = async () => {
        const sequence = ++lookupSequence;
        const { date, driverId } = comboValues();
        const selectedId = root.querySelector('#ddrSelectedTripId');
        if (selectedId) selectedId.value = '';
        const busCombo = root.querySelector('[data-combo][data-field="bus"]');
        const busInput = busCombo?.querySelector('[data-combo-input]');
        const busValue = busCombo?.querySelector('[data-combo-value]');
        if (busInput) { busInput.readOnly = !root.querySelector('#ddrUnscheduledMode')?.checked; busInput.value = ''; }
        if (busInput?.readOnly) busCombo?.classList.remove('open');
        if (busValue) busValue.value = '';
        busCombo?.classList.remove('has-value');
        for (const name of ['from_location', 'to_location']) {
            const input = find('input[name="' + name + '"]');
            if (input) input.readOnly = false;
        }

        listEl.innerHTML = '';
        listEl.classList.remove('has-items');
        root.ddrEligibleSchedules = [];
        root.dispatchEvent(new CustomEvent('ddr:schedules-updated', {detail: {schedules: []}}));

        if (!date || !driverId) {
            showStatus('idle', 'fa-magnifying-glass', 'Select a report date and driver to see their trips awaiting DDR.');
            return;
        }

        showStatus('loading', 'fa-circle-notch', 'Checking scheduled trips...');

        try {
            const query = new URLSearchParams({
                report_date: date,
            });
            query.set('driver_id', driverId);
            // Scheduled DDR encoding shows only trips that are awaiting a DDR.
            query.set('pending_only', '1');

            const response = await fetch(
                window.ddrScheduleLookupUrl + '?' + query.toString(),
                { headers: { 'Accept': 'application/json' } }
            );

            if (!response.ok) throw new Error('Assignment lookup failed');
            const payload = await response.json();
            if (sequence !== lookupSequence || date !== comboValues().date || driverId !== comboValues().driverId) return;

            const rows = payload.schedules || [];

            renderSchedules(rows);
            // A single trip is selected automatically; keep its assigned-bus confirmation visible.
            if (rows.length !== 1) {
                showStatus(
                    rows.length > 0 ? 'matched' : 'unmatched',
                    rows.length > 0 ? 'fa-circle-check' : 'fa-triangle-exclamation',
                    payload.message || 'Schedule match unavailable.'
                );
            }
        } catch (error) {
            if (sequence === lookupSequence) {
                showStatus('unmatched', 'fa-triangle-exclamation', 'Unable to retrieve assignments. Please try again.');
            }
        }
    };

    const dateInput = find('input[name="report_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', lookup);
    }

    window.addEventListener('combo:select', (event) => {
        if (event.detail?.field !== 'driver' || !event.detail.source || !root.contains(event.detail.source)) return;
        const bus = root.querySelector('[data-combo][data-field="bus"]');
        const hidden = bus?.querySelector('[data-combo-value]');
        const input = bus?.querySelector('[data-combo-input]');
        if (hidden) hidden.value = '';
        if (input) { input.value = ''; input.readOnly = !root.querySelector('#ddrUnscheduledMode')?.checked; }
        bus?.classList.remove('has-value');
        lookup();
    });
}

/* =========================================================
   ENCODE NEW REPORT MODAL (listing page)
========================================================= */

function initDdrEncodeModal() {
    const modal = document.getElementById('ddrEncodeModal');
    if (!modal) return;

    const trigger = document.getElementById('openEncodeReportModal');
    const form = modal.querySelector('form');

    const closeButtons = [
        document.getElementById('closeEncodeReport'),
        document.getElementById('cancelEncodeReport'),
    ].filter(Boolean);

    let previousBodyOverflow = '';

    const resetScheduleStatus = () => {
        const statusEl = modal.querySelector('#sfcStatus');
        const listEl = modal.querySelector('#sfcList');

        if (statusEl) {
            statusEl.className = 'ddr-sched-status idle';

            const icon = statusEl.querySelector('i');
            if (icon) icon.className = 'fa-solid fa-magnifying-glass';

            const span = statusEl.querySelector('span');
            if (span) span.textContent = 'Select a report date and driver to see assigned trips.';
        }

        if (listEl) {
            listEl.innerHTML = '';
            listEl.classList.remove('has-items');
        }
    };

    const clearCombos = () => {
        modal.querySelectorAll('[data-combo]').forEach((combo) => {
            const input = combo.querySelector('[data-combo-input]');
            const hidden = combo.querySelector('[data-combo-value]');

            if (input) input.value = '';
            if (hidden) hidden.value = '';

            combo.classList.remove('has-value');
        });
    };

    const open = (reset = true) => {
        if (reset) {
            form?.reset();
            clearCombos();
            resetScheduleStatus();
            const selectedTrip = modal.querySelector('#ddrSelectedTripId');
            if (selectedTrip) selectedTrip.value = '';
        }

        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.classList.add('show', 'active');
        modal.querySelector('input[name="report_date"]')?.focus();
    };

    const close = () => {
        modal.classList.remove('show', 'active');
        document.body.style.overflow = previousBodyOverflow;
        trigger?.focus();
    };

    if (trigger) {
        trigger.addEventListener('click', () => open(true));
    }

    closeButtons.forEach((button) => {
        button.addEventListener('click', close);
    });

    // Keep the DDR form open when the user clicks outside the dialog.

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        if (modal.classList.contains('show') || modal.classList.contains('active')) {
            close();
        }
    });

    const hasServerErrors = modal.querySelector('.ui-field-error, .ddr-alert-error');

    if (hasServerErrors) {
        open(false);

        const errorGroup = modal.querySelector('.ui-field-error')
            ?.closest('.ui-form-group, .ddr-form-group');

        const focusTarget = errorGroup
            ? errorGroup.querySelector('input, select, [data-combo-input]')
            : modal.querySelector('input[name="report_date"]');

        focusTarget?.focus();
    }
}

/* Physical DDR: extra trip rows are children of one daily report. */
function initDdrAdditionalTrips() {
    const modal = document.getElementById('ddrEncodeModal');
    if (!modal || modal.dataset.ddrRowsInitialized === '1') return;
    modal.dataset.ddrRowsInitialized = '1';

    const container = modal.querySelector('[data-ddr-trip-rows]');
    const template = modal.querySelector('template[data-ddr-trip-template]');
    const add = modal.querySelector('[data-ddr-add-trip]');
    if (!container || !template || !add) return;

    let scheduleOptions = [];
    const refillAssignmentSelectors = () => {
        const selectedPrimary = modal.querySelector('#ddrSelectedTripId')?.value || '';
        const selectors = Array.from(container.querySelectorAll('.ddr-extra-assignment'));
        const reserved = new Set(selectedPrimary ? [String(selectedPrimary)] : []);
        // Retain valid earlier selections. Each physical trip needs its own schedule link.
        selectors.forEach(select => {
            const previous = select.dataset.initialSchedule || select.value;
            const bus = modal.querySelector('[data-combo][data-field="bus"] [data-combo-value]')?.value;
            const available = scheduleOptions.filter(row =>
                (!bus || String(row.bus_id) === String(bus)) && !reserved.has(String(row.id))
            );
            select.replaceChildren(new Option('Select this entry’s assignment', ''));
            available.forEach(row => {
                const label = [row.trip_code, row.bus_no, row.departure_time + '–' + row.estimated_arrival_time].filter(Boolean).join(' · ');
                select.add(new Option(label, String(row.id)));
            });
            if (previous && available.some(row => String(row.id) === String(previous))) {
                select.value = String(previous);
            } else if (available.length === 1) {
                // The sole remaining eligible assignment can be safely linked automatically.
                select.value = String(available[0].id);
            }
            if (select.value) reserved.add(select.value);
            select.dataset.initialSchedule = '';
            const group = select.closest('.ddr-extra-assignment-group');
            if (!group) return;
            const unscheduled = Boolean(modal.querySelector('#ddrUnscheduledMode')?.checked);
            const autoLinked = !unscheduled && available.length === 1 && !!select.value;
            group.hidden = unscheduled || autoLinked || available.length === 0;
            let feedback = group.parentElement.querySelector('.ddr-assignment-feedback');
            if (!feedback) {
                feedback = document.createElement('div');
                feedback.className = 'ddr-assignment-feedback';
                group.after(feedback);
            }
            const match = available.find(row => String(row.id) === String(select.value));
            feedback.hidden = !autoLinked && (unscheduled || available.length > 0);
            feedback.classList.toggle('is-warning', !autoLinked);
            if (autoLinked && match) {
                feedback.textContent = 'Automatically linked to ' +
                    [match.trip_code, match.bus_no && 'Bus ' + match.bus_no].filter(Boolean).join(' · ') +
                    '. Enter the actual journey details below.';
            } else if (!unscheduled && available.length === 0) {
                feedback.textContent = 'No other eligible assigned trip is available for this driver and bus. Remove this extra entry, or choose the correct trip above.';
            } else {
                feedback.textContent = '';
            }
        });
    };
    modal.addEventListener('ddr:primary-assignment-changed', refillAssignmentSelectors);
    modal.addEventListener('ddr:schedules-updated', event => {
        scheduleOptions = event.detail.schedules || [];
        refillAssignmentSelectors();
    });
    modal.addEventListener('change', event => {
        if (event.target.matches('#ddrUnscheduledMode, [data-combo-value], .ddr-extra-assignment')) refillAssignmentSelectors();
    });
    const renumber = () => {
        Array.from(container.children).forEach((row, index) => {
            row.querySelector('[data-trip-title]').textContent = 'Trip ' + (index + 2);
            row.querySelectorAll('[data-trip-field]').forEach((field) => {
                field.name = 'additional_trips[' + index + '][' + field.dataset.tripField + ']';
                field.id = 'ddr_extra_trip_' + index + '_' + field.dataset.tripField;
                const label = field.closest('.ui-form-group')?.querySelector('label');
                if (label) label.htmlFor = field.id;
            });
        });
        const tripCount = modal.querySelector('[data-ddr-trip-count]');
        const total = container.children.length + 1;
        if (tripCount) tripCount.textContent = total + (total === 1 ? ' trip' : ' trips');
    };
    const append = (values = {}, open = false) => {
        if (container.children.length >= 30) return;
        const row = template.content.firstElementChild.cloneNode(true);
        row.open = open;
        row.querySelectorAll('[data-trip-field]').forEach((field) => {
            field.value = values[field.dataset.tripField] || '';
            if (field.classList.contains('ddr-extra-assignment')) field.dataset.initialSchedule = String(field.value || '');
        });
        row.querySelector('[data-ddr-remove-trip]').addEventListener('click', () => {
            row.remove();
            renumber();
        });
        container.appendChild(row);
        renumber();
        refillAssignmentSelectors();
    };
    const previousRows = modal.querySelector('[data-ddr-old-trips]');
    if (previousRows) {
        try {
            const restored = JSON.parse(previousRows.textContent);
            if (Array.isArray(restored)) restored.forEach((values) => append(values));
        } catch (_) {
            // Invalid preserved input must not prevent new rows being added.
        }
    }
    add.addEventListener('click', () => append({}, true));
    modal.querySelector('#ddrEncodeForm')?.addEventListener('reset', () => {
        container.replaceChildren();
        renumber();
    });
    renumber();
}

/* Compact floating DDR field hint: never alters the form grid. */
function initDdrCustomValidation() {
    document.querySelectorAll('form.ddr-form, #ddrEncodeForm').forEach((form) => {
        if (form.dataset.ddrValidationReady === '1') return;
        form.dataset.ddrValidationReady = '1';
        form.noValidate = true;

        const tooltip = document.createElement('div');
        tooltip.className = 'ddr-validation-tooltip';
        tooltip.setAttribute('role', 'alert');
        tooltip.hidden = true;
        document.body.appendChild(tooltip);
        let invalidField = null;

        const hide = () => {
            tooltip.hidden = true;
            invalidField?.classList.remove('ddr-input-invalid');
            invalidField?.removeAttribute('aria-invalid');
            invalidField = null;
        };
        const position = () => {
            if (!invalidField || tooltip.hidden || !invalidField.isConnected) return;
            const rect = invalidField.getBoundingClientRect();
            const desiredLeft = rect.left + 14;
            tooltip.style.left = Math.max(8, Math.min(desiredLeft, window.innerWidth - tooltip.offsetWidth - 8)) + 'px';
            const below = rect.bottom + 9;
            const above = rect.top - tooltip.offsetHeight - 9;
            tooltip.classList.toggle('is-above', below + tooltip.offsetHeight > window.innerHeight && above >= 8);
            tooltip.style.top = (tooltip.classList.contains('is-above') ? above : below) + 'px';
        };
        const show = (field, message) => {
            hide();
            invalidField = field;
            tooltip.textContent = message;
            tooltip.hidden = false;
            field.classList.add('ddr-input-invalid');
            field.setAttribute('aria-invalid', 'true');
            field.focus({ preventScroll: true });
            field.scrollIntoView({ block: 'nearest' });
            position();
        };
        const controls = () => Array.from(form.querySelectorAll('input, select, textarea'))
            .filter((field) => !field.disabled && field.type !== 'hidden' &&
                field.type !== 'button' && field.type !== 'submit' && !field.closest('template'));

        form.addEventListener('submit', (event) => {
            hide();
            for (const field of controls()) {
                if (!field.checkValidity()) {
                    event.preventDefault();
                    const label = field.closest('.ui-form-group, .ddr-form-group')
                        ?.querySelector('label')?.textContent?.replace('*', '').trim() || 'This field';
                    const msg = field.validity.valueMissing ? 'Please fill out ' + label.toLowerCase() + '.'
                        : field.validity.rangeUnderflow ? label + ' must be at least ' + field.min + '.'
                        : field.validity.rangeOverflow ? label + ' must not exceed ' + field.max + '.'
                        : 'Please enter a valid ' + label.toLowerCase() + '.';
                    show(field, msg);
                    return;
                }
                // Combo ID inputs are hidden, so validate the visible search control.
                const combo = field.closest('[data-combo]');
                if (combo && !combo.querySelector('[data-combo-value]')?.value.trim()) {
                    event.preventDefault();
                    show(field, 'Please select a ' + (combo.dataset.field === 'bus' ? 'bus' : 'driver') + '.');
                    return;
                }
            }
        });
        form.addEventListener('input', (event) => {
            if (event.target === invalidField) hide();
        });
        form.addEventListener('change', (event) => {
            if (event.target === invalidField) hide();
        });
        form.addEventListener('reset', hide);
        form.addEventListener('focusin', (event) => {
            if (invalidField && event.target !== invalidField) hide();
        });
        window.addEventListener('combo:select', hide);
        window.addEventListener('resize', position);
        document.addEventListener('scroll', position, true);
        document.addEventListener('click', (event) => {
            if (invalidField && !form.contains(event.target)) hide();
        });
    });
}

function initDdrDeleteModal() {
    const modal = document.getElementById('ddrDeleteModal');
    const form = document.getElementById('ddrDeleteForm');
    const selected = document.getElementById('ddrDeleteNumber');
    const cancel = document.getElementById('ddrDeleteCancel');
    const confirm = document.getElementById('ddrDeleteConfirm');
    if (!modal || !form || !selected || !cancel || !confirm) return;

    const close = () => {
        modal.classList.remove('show', 'active');
        form.removeAttribute('action');
    };
    document.querySelectorAll('.ddr-delete-trigger:not([disabled])').forEach((button) => {
        button.addEventListener('click', () => {
            if (!button.dataset.deleteUrl) return;
            form.action = button.dataset.deleteUrl;
            selected.textContent = button.dataset.ddrId || 'this report';
            modal.classList.add('show', 'active');
            cancel.focus();
        });
    });
    cancel.addEventListener('click', close);
    confirm.addEventListener('click', () => {
        if (!form.getAttribute('action')) return;
        confirm.disabled = true;
        form.requestSubmit();
    });
}

function initDdrRecordModals() {
    const close = (modal) => {
        if (!modal) return;
        modal.hidden = true;
        modal.classList.remove('is-open');
    };
    // Pagination and server filtering add rows and modal portals after init.
    // One page-scoped delegate covers those records without rebinding handlers.
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest?.('.ddr-open-modal');
        if (trigger) {
            const modal = document.getElementById(trigger.dataset.ddrModal);
            if (!modal) return;
            modal.hidden = false;
            modal.classList.add('is-open');
            modal.querySelector('.ddr-record-close')?.focus();
            return;
        }
        const closeButton = event.target.closest?.('.ddr-record-close');
        if (closeButton) close(closeButton.closest('.ddr-record-overlay'));
        // A backdrop click intentionally does nothing.
    });
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.ddr-record-overlay.is-open').forEach(close);
    });
}

function initDdrEditTripSections() {
    document.querySelectorAll('.ddr-record-dialog-edit .ddr-edit-trip-list').forEach((list) => {
        list.addEventListener('toggle', (event) => {
            const opened = event.target;
            if (!(opened instanceof HTMLDetailsElement) || !opened.open || !opened.classList.contains('ddr-edit-trip')) return;
            list.querySelectorAll('.ddr-edit-trip').forEach((section) => {
                if (section !== opened) section.open = false;
            });
        }, true);
    });
}
