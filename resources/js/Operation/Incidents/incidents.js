window.GCTPartialNavigation.registerInitializer('operation-incidents', '.inc-page', () => {
    initSearchableCombos();
    initTripPrefill();
    initIncidentBusLookup();
    initIncidentReportModal();
    initIncidentRecordModals();
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
        const summary = lookup.querySelector('[data-incident-trip-summary]');
        const status = lookup.querySelector('[data-incident-trip-status]');
        const tripSelect = form?.querySelector('[data-trip-select]');
        const busSelect = form?.querySelector('[data-incident-bus-select]');
        const driverSelect = form?.querySelector('[data-incident-driver-select]');
        if (!form || !input || !results || !context || !tripSelect || !busSelect) return;

        const buses = Array.from(lookup.querySelectorAll('[data-bus-id]'));
        const trips = Array.from(tripSelect.options).filter(option => option.value && option.dataset.busId);
        let confirmedBus = '';

        const clearTrip = () => {
            tripSelect.value = '';
            tripSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (driverSelect) { driverSelect.disabled = false; driverSelect.value = ''; }
            showDetails(null);
        };
        const matchingTrips = (busId) => trips.filter(option => option.dataset.busId === String(busId));
        const showDetails = (option) => {
            const bus = buses.find(item => item.dataset.busId === (option?.dataset.busId || confirmedBus));
            const details = {
                trip: option?.value ? option.textContent.trim().split('•')[0].trim() : 'Not selected',
                route: option?.dataset.route || 'Not available',
                bus: bus?.dataset.busNo || 'Select a bus',
                driver: option?.dataset.driverName || 'Not available',
            };
            const metas = {
                trip: option?.value ? [option.dataset.departure, option.dataset.arrival].filter(Boolean).join(' → ') : '—',
                route: option?.dataset.origin && option?.dataset.destination
                    ? option.dataset.origin + ' → ' + option.dataset.destination : '—',
                bus: bus?.dataset.plateNo || '—',
                driver: option?.dataset.driverId || '—',
            };
            Object.entries(details).forEach(([key, value]) => {
                const field = context.querySelector('[data-trip-summary="' + key + '"]');
                if (field) field.textContent = value;
            });
            Object.entries(metas).forEach(([key, value]) => {
                const field = context.querySelector('[data-trip-meta="' + key + '"]');
                if (field) field.textContent = value;
            });
            const emptyNote = lookup.querySelector('[data-trip-empty-note]');
            if (emptyNote) {
                emptyNote.textContent = bus
                    ? 'No scheduled trip matched this bus today. You can still report the incident.'
                    : 'Enter a Bus ID to see matching trip and driver details.';
                emptyNote.hidden = Boolean(option?.value);
            }
            context.hidden = false;
            if (summary) summary.hidden = false;
            if (status) {
                status.textContent = option?.value ? (option.dataset.tripStatus || 'Scheduled') : (bus ? 'No Trip Match' : 'Awaiting Bus ID');
                status.classList.toggle('is-pending', !option?.value);
            }
        };
        const chooseTrip = (option) => {
            tripSelect.value = option.value;
            tripSelect.dispatchEvent(new Event('change', { bubbles: true }));
            if (driverSelect) driverSelect.disabled = false;
            showDetails(option);
        };
        const showTripOptions = (bus) => {
            results.replaceChildren();
            const options = matchingTrips(bus.dataset.busId);
            if (options.length === 1) {
                chooseTrip(options[0]);
                results.hidden = true;
            } else if (options.length > 1) {
                const notice = document.createElement('p');
                notice.textContent = 'Multiple trips assigned today. Select the correct trip:';
                results.appendChild(notice);
                options.forEach(option => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'inc-bus-lookup-option';
                    button.textContent = [option.textContent.trim().split('•')[0].trim(),
                        option.dataset.route, option.dataset.driverName, option.dataset.departure].filter(Boolean).join(' · ');
                    button.addEventListener('click', () => { chooseTrip(option); results.hidden = true; });
                    results.appendChild(button);
                });
                results.hidden = false;
            } else {
                const notice = document.createElement('p');
                notice.textContent = 'Bus found in Master List. No scheduled trip assigned today; enter incident details manually.';
                results.appendChild(notice);
                results.hidden = false;
            }
        };
        const selectBus = (bus) => {
            confirmedBus = bus.dataset.busId;
            busSelect.value = confirmedBus;
            input.value = bus.dataset.busNo;
            input.setCustomValidity('');
            clearTrip();
            showTripOptions(bus);
            if (!matchingTrips(confirmedBus).length) showDetails(null);
        };
        const search = () => {
            const typed = input.value.trim().toLowerCase();
            if (busSelect.value !== confirmedBus || !buses.some(bus => bus.dataset.busId === confirmedBus &&
                [bus.dataset.busNo, bus.dataset.plateNo].some(s => (s || '').toLowerCase() === typed))) {
                confirmedBus = '';
                busSelect.value = '';
                clearTrip();
                input.setCustomValidity('Select a Bus ID from the suggestions.');
            }
            results.replaceChildren();
            if (!typed) { results.hidden = true; return; }
            const matches = buses.filter(bus => [bus.dataset.busNo, bus.dataset.plateNo]
                .some(value => (value || '').toLowerCase().includes(typed))).slice(0, 30);
            const exact = matches.filter(bus => [bus.dataset.busNo, bus.dataset.plateNo]
                .some(value => (value || '').toLowerCase() === typed));
            if (exact.length === 1) { selectBus(exact[0]); return; }
            if (!matches.length) {
                const notice = document.createElement('p');
                notice.textContent = 'No Bus ID or plate number matches the Bus Master List.';
                results.appendChild(notice);
            } else {
                matches.forEach(bus => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'inc-bus-lookup-option';
                    button.textContent = [bus.dataset.busNo, bus.dataset.plateNo, bus.dataset.busStatus].filter(Boolean).join(' · ');
                    button.addEventListener('click', () => selectBus(bus));
                    results.appendChild(button);
                });
            }
            results.hidden = false;
        };
        input.addEventListener('input', search);
        input.addEventListener('focus', () => {
            if (input.value && !confirmedBus) search();
        });
        tripSelect.addEventListener('change', () => {
            const selected = tripSelect.selectedOptions[0];
            if (selected?.dataset.busId) {
                const bus = buses.find(item => item.dataset.busId === selected.dataset.busId);
                if (bus) {
                    confirmedBus = bus.dataset.busId;
                    input.value = bus.dataset.busNo;
                    busSelect.value = confirmedBus;
                    input.setCustomValidity('');
                }
            }
            showDetails(selected);
        });
        const initialTrip = tripSelect.selectedOptions[0];
        if (initialTrip?.dataset.busId) {
            const bus = buses.find(item => item.dataset.busId === initialTrip.dataset.busId);
            if (bus) {
                confirmedBus = bus.dataset.busId;
                input.value = bus.dataset.busNo;
                busSelect.value = confirmedBus;
            }
        }
        input.setCustomValidity(confirmedBus ? '' : 'Select a Bus ID from the suggestions.');
        showDetails(initialTrip);
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

/* Incident row actions: View and Edit stay in the incident list modal.
 * The full workflow is available through an explicit link in View. */
function initIncidentRecordModals() {
    const modal = document.getElementById('incidentRecordModal');
    const page = document.querySelector('.inc-page');
    if (!modal || !page || modal.dataset.initialized === '1') return;
    modal.dataset.initialized = '1';
    const dialog = modal.querySelector('[role="dialog"]');
    const view = modal.querySelector('[data-incident-view-panel]');
    const edit = modal.querySelector('[data-incident-edit-panel]');
    const error = modal.querySelector('[data-incident-modal-error]');
    const title = modal.querySelector('#incidentRecordModalTitle');
    const subtitle = modal.querySelector('#incidentRecordModalSubtitle');
    const fullLink = modal.querySelector('[data-incident-full-link]');
    let opener = null;
    let currentRecord = null;
    let bodyOverflow = '';

    function close() {
        modal.hidden = true;
        document.body.style.overflow = bodyOverflow;
        opener?.focus();
    }
    function open(button) {
        const record = button.closest('[data-incident-record]');
        if (!record) return;
        opener = button;
        currentRecord = record;
        const data = record.dataset;
        const isEdit = button.dataset.incidentModalAction === 'edit';
        title.textContent = isEdit ? 'Edit Incident' : 'Incident Details';
        subtitle.textContent = data.incidentNo || '';
        view.hidden = isEdit;
        edit.hidden = !isEdit;
        error.hidden = true;
        error.textContent = '';
        if (isEdit) {
            edit.action = button.dataset.updateUrl || '';
            edit.elements.location.value = data.incidentLocation || '';
            edit.elements.description.value = data.incidentDescription || '';
        } else {
            const fields = {
                no: data.incidentNo, status: data.incidentStatus,
                type: data.incidentType, reported: data.incidentReported,
                bus: [data.incidentBus, data.incidentPlate].filter(Boolean).join(' / '),
                trip: [data.incidentTrip !== '—' ? data.incidentTrip : '', data.incidentRoute].filter(Boolean).join(' · '),
                driver: [data.incidentDriver, data.incidentDriverId].filter(Boolean).join(' · '),
                location: data.incidentLocation, description: data.incidentDescription,
                schedule: data.incidentSchedule, reporter: data.incidentReporter,
                updated: data.incidentUpdated, referral: data.incidentReferral,
                replacement: data.incidentReplacement
            };
            for (const [key, value] of Object.entries(fields)) {
                const target = modal.querySelector('[data-incident-display="' + key + '"]');
                if (target) target.textContent = value || '—';
            }
            const viewAnchor = record.querySelector('[data-incident-full-url]');
            const fallback = record.querySelector('[data-incident-modal-action="view"]');
            fullLink.href = viewAnchor?.dataset.incidentFullUrl || fallback?.dataset.incidentFullUrl || '#';
            const switchEdit = modal.querySelector('[data-incident-switch-edit]');
            switchEdit.hidden = data.incidentCanEdit !== '1';
            const timeline = modal.querySelector('[data-incident-timeline-panel]');
            timeline.replaceChildren();
            const addEvent = (time, label, note) => {
                const item = document.createElement('div');
                item.className = 'inc-modal-timeline-item';
                const heading = document.createElement('strong');
                heading.textContent = [time, label].filter(Boolean).join(' · ');
                const description = document.createElement('small');
                description.textContent = note;
                item.append(heading, description);
                timeline.appendChild(item);
            };
            addEvent(data.incidentReported, 'Reported', 'Incident recorded by Operation');
            try {
                const responses = JSON.parse(data.incidentTimeline || '[]');
                responses.forEach(item => addEvent(item.time, item.status || 'Response', item.note || 'Incident response recorded'));
            } catch (_) {
                // Omit invalid optional timeline data rather than inventing events.
            }
        }
        if (modal.hidden) bodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        modal.hidden = false;
        (isEdit ? edit.elements.location : dialog)?.focus();
    }
    page.addEventListener('click', event => {
        const button = event.target.closest('[data-incident-modal-action]');
        if (button && !button.disabled) open(button);
    });
    modal.querySelectorAll('[data-incident-modal-close]').forEach(button => button.addEventListener('click', close));
    modal.querySelector('[data-incident-switch-edit]')?.addEventListener('click', () => {
        const editButton = currentRecord?.querySelector('[data-incident-modal-action="edit"]:not(:disabled)');
        if (!editButton) return;
        open(editButton);
    });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(); return; }
        if (event.key !== 'Tab') return;
        const elements = Array.from(modal.querySelectorAll('button:not(:disabled), input:not(:disabled), textarea:not(:disabled), a[href]'))
            .filter(el => !el.closest('[hidden]') && el.getClientRects().length);
        if (!elements.length) return;
        if (event.shiftKey && document.activeElement === elements[0]) { event.preventDefault(); elements[elements.length - 1].focus(); }
        else if (!event.shiftKey && document.activeElement === elements[elements.length - 1]) { event.preventDefault(); elements[0].focus(); }
    });
    edit.addEventListener('submit', async event => {
        event.preventDefault();
        const save = edit.querySelector('[type="submit"]');
        if (!edit.reportValidity() || save.disabled) return;
        save.disabled = true;
        error.hidden = true;
        try {
            const response = await fetch(edit.action, {
                method: 'POST',
                body: new FormData(edit),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            if (response.ok) {
                close();
                window.location.reload();
                return;
            }
            if (response.status === 422) {
                const result = await response.json();
                error.textContent = Object.values(result.errors || {}).flat().join(' ') || result.message || 'Please review the form.';
            } else if (response.status === 403) {
                error.textContent = 'This incident is locked and cannot be edited.';
            } else {
                error.textContent = 'Unable to save changes. Please try again.';
            }
            error.hidden = false;
        } catch (_) {
            error.textContent = 'Network error. Your changes have not been confirmed.';
            error.hidden = false;
        } finally {
            save.disabled = false;
        }
    });
}
