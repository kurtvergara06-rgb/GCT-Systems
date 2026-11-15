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
                detail: { field: combo.dataset.field || '', value },
            }));
        };

        const clear = () => {
            if (combo.dataset.field === 'bus' && input.readOnly) return;
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
            // Keep the driver search usable before a date is selected.
            options.forEach((option) => { option.hidden = false; option.style.display = ''; });
            return;
        }
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
            }
        } catch (error) {
            // A failed lookup must not make the driver's input appear broken.
            options.forEach((option) => { option.hidden = false; option.style.display = ''; });
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
    if (!checkbox || !reasonWrap) return;
    const sync = () => {
        reasonWrap.hidden = !checkbox.checked;
        if (reasonInput) reasonInput.required = checkbox.checked;
        if (tripPanel) tripPanel.hidden = checkbox.checked;
        const busInput = modal?.querySelector('[data-combo][data-field="bus"] [data-combo-input]');
        if (busInput) busInput.readOnly = !checkbox.checked && Boolean(selectedId?.value);
        for (const name of ['from_location', 'to_location']) {
            const input = modal?.querySelector('[name="' + name + '"]');
            if (input) input.readOnly = false;
        }
        if (checkbox.checked && selectedId) selectedId.value = '';
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
    meta.append(dateField, driverField, busField, numberField);

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
        const select = document.createElement('select');
        select.className = 'ddr-assigned-trip-select';
        select.setAttribute('aria-label', 'Select assigned trip');
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = schedules.length ? 'Select an assigned trip...' : 'No eligible completed-service trips';
        select.appendChild(placeholder);
        schedules.forEach((schedule, index) => {
            const option = document.createElement('option');
            option.value = String(index);
            option.textContent = [schedule.trip_code, schedule.bus_no && 'Bus ' + schedule.bus_no,
                schedule.departure_time + '–' + schedule.estimated_arrival_time]
                .filter(Boolean).join(' · ');
            option.title = schedule.route_label || '';
            select.appendChild(option);
        });
        select.disabled = !schedules.length;
        select.addEventListener('change', () => {
            const schedule = select.value === '' ? null : schedules[Number(select.value)];
            const selectedId = root.querySelector('#ddrSelectedTripId');
            if (selectedId) selectedId.value = schedule ? String(schedule.id) : '';
            const busInput = root.querySelector('[data-combo][data-field="bus"] [data-combo-input]');
            if (!schedule) {
                if (busInput) busInput.readOnly = false;
                for (const name of ['from_location', 'to_location']) {
                    const input = find('input[name="' + name + '"]');
                    if (input) input.readOnly = false;
                }
                return;
            }
            selectComboValue('driver', schedule.driver_id, schedule.driver_name);
            if (busInput) busInput.readOnly = true;
            selectComboValue('bus', schedule.bus_id, schedule.bus_no);
            const origin = find('input[name="from_location"]');
            const destination = find('input[name="to_location"]');
            // From/To are ACTUAL DDR locations, not the route's planned endpoints.
            // Never populate them from the selected schedule.
            // Physical DDR captures actual locations; keep them editable.
            if (origin) origin.readOnly = false;
            if (destination) destination.readOnly = false;
            showStatus('matched', 'fa-circle-check', 'Assignment linked. Enter actual From/To locations and travel times from the physical DDR.');
        });
        listEl.appendChild(select);
        listEl.classList.toggle('has-items', schedules.length > 0);
    };

    let lookupSequence = 0;
    const lookup = async () => {
        const sequence = ++lookupSequence;
        const { date, driverId, busId } = comboValues();
        const selectedId = root.querySelector('#ddrSelectedTripId');
        if (selectedId) selectedId.value = '';
        const busInput = root.querySelector('[data-combo][data-field="bus"] [data-combo-input]');
        if (busInput) busInput.readOnly = false;
        for (const name of ['from_location', 'to_location']) {
            const input = find('input[name="' + name + '"]');
            if (input) input.readOnly = false;
        }

        listEl.innerHTML = '';
        listEl.classList.remove('has-items');
        root.ddrEligibleSchedules = [];
        root.dispatchEvent(new CustomEvent('ddr:schedules-updated', {detail: {schedules: []}}));

        if (!date || (!driverId && !busId)) {
            showStatus('idle', 'fa-magnifying-glass', 'Select a date, then choose a driver or bus to see assignments.');
            return;
        }

        showStatus('loading', 'fa-circle-notch', 'Checking scheduled trips...');

        try {
            const query = new URLSearchParams({
                report_date: date,
            });
            if (driverId) query.set('driver_id', driverId);
            if (busId) query.set('bus_id', busId);

            const response = await fetch(
                window.ddrScheduleLookupUrl + '?' + query.toString(),
                { headers: { 'Accept': 'application/json' } }
            );

            if (!response.ok) throw new Error('Assignment lookup failed');
            const payload = await response.json();
            if (sequence !== lookupSequence) return;

            const rows = payload.schedules || [];

            renderSchedules(rows);
            if (rows.length === 1) {
                // One clear assignment: no need for an extra selection step.
                const select = listEl.querySelector('.ddr-assigned-trip-select');
                if (select) {
                    select.value = '0';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            showStatus(
                rows.length > 0 ? 'matched' : 'unmatched',
                rows.length > 0 ? 'fa-circle-check' : 'fa-triangle-exclamation',
                payload.message || 'Schedule match unavailable.'
            );
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
        const field = event.detail?.field;
        if (field !== 'driver' && field !== 'bus') return;
        const otherField = field === 'driver' ? 'bus' : 'driver';
        const other = root.querySelector('[data-combo][data-field="' + otherField + '"]');
        if (other) {
            const hidden = other.querySelector('[data-combo-value]');
            const input = other.querySelector('[data-combo-input]');
            if (hidden) hidden.value = '';
            if (input) {
                input.value = '';
                input.readOnly = false;
            }
            other.classList.remove('has-value');
        }
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

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

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
        Array.from(container.querySelectorAll('.ddr-extra-assignment')).forEach(select => {
            const previous = select.value;
            const initial = select.dataset.initialSchedule || previous;
            select.replaceChildren(new Option('Select this entry’s assignment', ''));
            const bus = modal.querySelector('[data-combo][data-field="bus"] [data-combo-value]')?.value;
            scheduleOptions.filter(row => !bus || String(row.bus_id) === String(bus)).forEach(row => {
                const label = [row.trip_code, row.bus_no, row.departure_time + '–' + row.estimated_arrival_time].filter(Boolean).join(' · ');
                select.add(new Option(label, String(row.id)));
            });
            if (initial && Array.from(select.options).some(option => option.value === initial)) select.value = initial;
            select.dataset.initialSchedule = '';
        });
    };
    modal.addEventListener('ddr:schedules-updated', event => {
        scheduleOptions = event.detail.schedules || [];
        refillAssignmentSelectors();
    });
    modal.addEventListener('change', event => {
        if (event.target.matches('#ddrUnscheduledMode, [data-combo-value]')) refillAssignmentSelectors();
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
    document.querySelectorAll('.ddr-open-modal').forEach((button) => {
        button.addEventListener('click', () => {
            const modal = document.getElementById(button.dataset.ddrModal);
            if (!modal) return;
            modal.hidden = false;
            modal.classList.add('is-open');
            modal.querySelector('.ddr-record-close')?.focus();
        });
    });
    document.querySelectorAll('.ddr-record-overlay').forEach((modal) => {
        modal.querySelectorAll('.ddr-record-close').forEach((button) =>
            button.addEventListener('click', () => close(modal)));
        modal.addEventListener('click', (event) => {
            if (event.target === modal) return; // Backdrop must not close the modal.
        });
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
