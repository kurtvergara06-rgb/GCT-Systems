window.GCTPartialNavigation.registerInitializer('operation-daily-driver-reports', '.ddr-page', () => {
    initSearchableCombos();
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

    const dateInput = find('input[name="report_date"]');
    if (dateInput) {
        dateInput.addEventListener('change', lookup);
    }

    document.addEventListener('combo:select', lookup);
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
            if (span) span.textContent = 'Select a date, driver, and bus to check matching scheduled trips.';
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
    };
    const append = (values = {}) => {
        if (container.children.length >= 30) return;
        const row = template.content.firstElementChild.cloneNode(true);
        row.querySelectorAll('[data-trip-field]').forEach((field) => {
            field.value = values[field.dataset.tripField] || '';
        });
        row.querySelector('[data-ddr-remove-trip]').addEventListener('click', () => {
            row.remove();
            renumber();
        });
        container.appendChild(row);
        renumber();
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
    add.addEventListener('click', () => append());
    modal.querySelector('#ddrEncodeForm')?.addEventListener('reset', () => {
        container.replaceChildren();
    });
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
