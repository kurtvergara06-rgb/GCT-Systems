window.GCTPartialNavigation.registerInitializer('operation-incidents', '.inc-page', () => {
    initSearchableCombos();
    initTripPrefill();
    initIncidentBusLookup();
    initIncidentReportModal();
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

        const selectOption = (option) => {
            hidden.value = option.dataset.value;
            input.value = selectedLabel(option.dataset.value);
            combo.classList.add('has-value');
            close();
            input.dispatchEvent(new Event('change'));
        };

        input.addEventListener('focus', open);
        input.addEventListener('input', () => {
            if (hidden.value) {
                hidden.value = '';
                combo.classList.remove('has-value');
            }
            open();
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
            if (event.key !== 'Enter') return;
            event.preventDefault();
            const visible = options.find((opt) => opt.style.display !== 'none');
            if (visible) selectOption(visible);
        });

        options.forEach((option) => {
            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                selectOption(option);
            });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                hidden.value = '';
                input.value = '';
                combo.classList.remove('has-value');
                input.focus();
                open();
            });
        }

        document.addEventListener('click', (event) => {
            if (!combo.contains(event.target)) close();
        });
    });
}

/* =========================================================
   TRIP PREFILL (existing assignment / selected trip)
========================================================= */

function initTripPrefill() {
    document.querySelectorAll('[data-trip-select]').forEach((tripSelect) => {
        const form = tripSelect.closest('form');
        if (!form) return;
        const assignmentInput = form.querySelector('[data-trip-assignment-id]');
        const driverSelect = form.querySelector('[data-incident-driver-select]');
        const busSelect = form.querySelector('[data-incident-bus-select]');
        const prefillLink = form.querySelector('[data-prefill-link]');

        const syncPrefill = () => {
            const selected = tripSelect.selectedOptions[0];
            const assignmentId = selected?.dataset.assignmentId || '';
            if (assignmentInput) assignmentInput.value = assignmentId;

            // Follow the selected schedule's actual assignment, never guess.
            // Without a trip, let staff select the driver and bus manually.
            if (assignmentId) {
                if (driverSelect) driverSelect.value = selected.dataset.driverId || '';
                if (busSelect) busSelect.value = selected.dataset.busId || '';
            }
            if (prefillLink) {
                prefillLink.hidden = !assignmentId;
                if (assignmentId) prefillLink.href = '?trip_assignment_id=' + encodeURIComponent(assignmentId);
            }
        };

        tripSelect.addEventListener('change', syncPrefill);
        syncPrefill();
    });
}


/* Look up scheduled assignments by Bus ID, without guessing a trip. */
function initIncidentBusLookup() {
    document.querySelectorAll('[data-incident-bus-lookup]').forEach((lookup) => {
        const form = lookup.closest('form');
        const input = lookup.querySelector('[data-incident-bus-search]');
        const results = lookup.querySelector('[data-incident-bus-results]');
        const context = lookup.querySelector('[data-incident-trip-context]');
        const tripSelect = form?.querySelector('[data-trip-select]');
        if (!form || !input || !results || !context || !tripSelect) return;
        const options = Array.from(tripSelect.options).filter(opt => opt.value && opt.dataset.busId);

        function showContext(option) {
            context.replaceChildren();
            if (!option || !option.dataset.busId) {
                context.hidden = true;
                return;
            }
            const details = [
                ['Bus', [option.dataset.busNo, option.dataset.plateNo].filter(Boolean).join(' / ')],
                ['Driver', [option.dataset.driverName, option.dataset.driverId].filter(Boolean).join(' · ')],
                ['Route', option.dataset.route],
                ['From / To', [option.dataset.origin, option.dataset.destination].filter(Boolean).join(' → ')],
                ['Trip Code', option.textContent.trim().split('•')[0].trim()],
                ['Scheduled', [option.dataset.departure, option.dataset.arrival].filter(Boolean).join(' → ')],
                ['Status', option.dataset.tripStatus],
            ];
            details.forEach(([label, value]) => {
                if (!value) return;
                const item = document.createElement('div');
                const title = document.createElement('small');
                const text = document.createElement('strong');
                title.textContent = label;
                text.textContent = value;
                item.append(title, text);
                context.appendChild(item);
            });
            context.hidden = !context.childElementCount;
        }

        function selectTrip(option) {
            tripSelect.value = option.value;
            tripSelect.dispatchEvent(new Event('change', { bubbles: true }));
            input.value = option.dataset.busNo || option.dataset.plateNo || '';
            results.hidden = true;
            results.replaceChildren();
            showContext(option);
        }

        function search() {
            const term = input.value.trim().toLowerCase();
            results.replaceChildren();
            context.hidden = true;
            if (!term) {
                results.hidden = true;
                return;
            }
            const matches = options.filter(opt => [opt.dataset.busNo, opt.dataset.plateNo]
                .some(value => (value || '').toLowerCase().includes(term)));
            if (!matches.length) {
                const hint = document.createElement('p');
                hint.textContent = 'No assigned trip found for this Bus ID today. You can still report an incident manually.';
                results.appendChild(hint);
            } else {
                matches.forEach(option => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'inc-bus-lookup-option';
                    const code = option.textContent.trim().split('•')[0].trim();
                    button.textContent = [option.dataset.busNo, option.dataset.plateNo, code, option.dataset.route,
                        option.dataset.driverName, option.dataset.departure].filter(Boolean).join(' · ');
                    button.addEventListener('click', () => selectTrip(option));
                    results.appendChild(button);
                });
            }
            results.hidden = false;
        }

        input.addEventListener('input', search);
        tripSelect.addEventListener('change', () => {
            const selected = tripSelect.selectedOptions[0];
            showContext(selected);
            if (selected?.dataset.busNo) input.value = selected.dataset.busNo;
        });
        showContext(tripSelect.selectedOptions[0]);
    });
}

/* =========================================================
   INCIDENT REPORT MODAL (listing page)
========================================================= */

function initIncidentReportModal() {
    const modal = document.getElementById('incidentReportModal');
    const trigger = document.getElementById('openIncidentReportModal');
    if (!modal || !trigger) return;

    const form = modal.querySelector('form');
    const closeButtons = [
        document.getElementById('closeIncidentReport'),
        document.getElementById('cancelIncidentReport'),
    ].filter(Boolean);
    const submitButton = document.getElementById('incidentReportSubmit');
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(', ');

    let previousBodyOverflow = '';
    let isSubmitting = false;

    const open = (focusError = false) => {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.classList.add('show', 'active');

        const errorTarget = focusError
            ? modal.querySelector('.ui-field-error')?.closest('.inc-form-group, .ui-form-group')?.querySelector('input, select, textarea')
            : null;

        (errorTarget || modal.querySelector('input[name="trip_schedule_id"]'))?.focus();
    };

    const close = () => {
        modal.classList.remove('show', 'active');
        document.body.style.overflow = previousBodyOverflow;
        trigger.focus();
    };

    trigger.addEventListener('click', () => open());
    closeButtons.forEach((button) => button.addEventListener('click', close));

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    modal.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
            return;
        }

        if (event.key !== 'Tab') return;

        const focusable = Array.from(modal.querySelectorAll(focusableSelector))
            .filter((element) => element.getClientRects().length > 0);
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    // Show a useful inline validation message instead of relying on the
    // browser's generic "Please fill out this field" tooltip.
    const fieldMessage = (field) => {
        if (field.validity.valueMissing) return 'This field is required.';
        if (field.validity.typeMismatch) return 'Enter a valid value.';
        if (field.validity.tooLong) return 'This value is too long.';
        return field.validationMessage || 'Please check this field.';
    };
    const clearFieldError = (field) => {
        field.removeAttribute('aria-invalid');
        const group = field.closest('.inc-form-group, .ui-form-group');
        group?.querySelectorAll('[data-incident-validation-error]').forEach((el) => el.remove());
    };
    form?.addEventListener('invalid', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
        event.preventDefault();
        const group = field.closest('.inc-form-group, .ui-form-group');
        if (!group) return;
        clearFieldError(field);
        field.setAttribute('aria-invalid', 'true');
        const error = document.createElement('span');
        error.className = 'ui-field-error inc-inline-error';
        error.dataset.incidentValidationError = 'true';
        error.setAttribute('role', 'alert');
        error.textContent = fieldMessage(field);
        group.appendChild(error);
        if (!form.dataset.incidentValidationFocused) {
            field.focus();
            form.dataset.incidentValidationFocused = 'true';
            queueMicrotask(() => { delete form.dataset.incidentValidationFocused; });
        }
    }, true);
    ['input', 'change'].forEach((eventName) => {
        form?.addEventListener(eventName, (event) => {
            const field = event.target;
            if (field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement) {
                if (field.checkValidity()) clearFieldError(field);
            }
        });
    });

    form?.addEventListener('submit', (event) => {
        if (isSubmitting) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) return;

        isSubmitting = true;
        submitButton?.setAttribute('aria-busy', 'true');
        if (submitButton) submitButton.disabled = true;

        const label = submitButton?.querySelector('[data-loading-label]');
        if (label) label.textContent = 'Saving...';
    });

    if (modal.querySelector('.ui-field-error, .inc-modal-alert')) {
        open(true);
    }
}
