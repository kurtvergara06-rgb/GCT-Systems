window.GCTPartialNavigation.registerInitializer('operation-incidents', '.inc-page', () => {
    initSearchableCombos();
    initTripPrefill();
    initIncidentBusLookup();
    initIncidentReportModal();
    initIncidentRecordModals();
    initIncidentWorkflowModal();
    initEmbeddedIncidentWorkflow();
});

function initEmbeddedIncidentWorkflow() {
    const workflow = document.querySelector('[data-embedded-incident-workflow]');
    if (!workflow || workflow.dataset.ready === '1') return;
    workflow.dataset.ready = '1';

    const tabs = Array.from(workflow.querySelectorAll('[data-wf-tab]'));
    const panels = Array.from(workflow.querySelectorAll('[data-wf-panel]'));

    const activatePanel = (name, focusId = '') => {
        tabs.forEach((tab) => {
            const active = tab.dataset.wfTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
        panels.forEach((panel) => {
            const active = panel.dataset.wfPanel === name;
            panel.hidden = !active;
            panel.classList.toggle('is-active', active);
        });

        if (focusId) {
            window.requestAnimationFrame(() => {
                const target = document.getElementById(focusId);
                target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target?.focus({ preventScroll: true });
            });
        }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activatePanel(tab.dataset.wfTab));
        tab.addEventListener('keydown', (event) => {
            if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault();
            const direction = event.key === 'ArrowRight' ? 1 : -1;
            const next = tabs[(index + direction + tabs.length) % tabs.length];
            activatePanel(next.dataset.wfTab);
            next.focus();
        });
    });

    workflow.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-wf-show-panel]');
        if (!trigger) return;
        activatePanel(trigger.dataset.wfShowPanel, trigger.dataset.wfFocus || '');
    });
}

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
        const formatIncidentTime = (rawTime) => {
            if (!rawTime) return '';
            const match = String(rawTime).trim().match(/^(\d{1,2}):(\d{2})/);
            if (!match) return String(rawTime);
            const hours24 = parseInt(match[1], 10);
            const minutes = match[2];
            const period = hours24 >= 12 ? 'PM' : 'AM';
            const hours12 = hours24 % 12 || 12;
            return `${hours12}:${minutes} ${period}`;
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
                trip: option?.value ? [formatIncidentTime(option.dataset.departure), formatIncidentTime(option.dataset.arrival)].filter(Boolean).join(' → ') : '—',
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
                    ? 'No Ready trip assignment matches this bus today.'
                    : 'Enter a Bus ID to see matching trip and driver details.';
                emptyNote.hidden = Boolean(option?.value);
            }
            context.hidden = false;
            if (summary) summary.hidden = false;
            if (status) {
                status.textContent = option?.value ? (option.dataset.tripStatus || 'Scheduled') : (bus ? 'No Trip Match' : 'Awaiting Bus ID');
                status.classList.toggle('is-pending', !option?.value);
            }
            const contextPanelBody = form.closest('.inc-report-layout')?.querySelector('.inc-context-panel-body');
            if (contextPanelBody) {
                if (option?.value) {
                    contextPanelBody.innerHTML = '';
                    const items = [
                        { title: `Trip ${details.trip}`, sub: metas.trip, icon: 'fa-circle-check', color: 'var(--inc-green)' },
                        { title: details.bus, sub: metas.bus, icon: 'fa-bus' },
                        { title: details.driver, sub: metas.driver, icon: 'fa-user' },
                        { title: details.route, sub: metas.route, icon: 'fa-route' },
                    ];
                    items.forEach((item) => {
                        const row = document.createElement('div');
                        row.className = 'inc-context-item';
                        const info = document.createElement('div');
                        const strong = document.createElement('strong');
                        strong.textContent = item.title;
                        const small = document.createElement('small');
                        small.textContent = item.sub;
                        info.append(strong, small);
                        const icon = document.createElement('i');
                        icon.className = `fa-solid ${item.icon}`;
                        if (item.color) icon.style.color = item.color;
                        row.append(info, icon);
                        contextPanelBody.appendChild(row);
                    });
                }
            }
        };
        const chooseTrip = (option) => {
            // Trip IDs repeat across slots; select the exact option/assignment.
            tripSelect.selectedIndex = option.index;
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
                notice.textContent = 'No Ready trip assignment matches this bus today.';
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
                notice.textContent = 'No bus assigned to a Ready trip matches this search.';
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
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                results.hidden = true;
                event.stopPropagation();
            }
        });
        form.addEventListener('pointerdown', (event) => {
            if (!lookup.contains(event.target)) results.hidden = true;
        });
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
            const overview = {
                no: data.incidentNo,
                reported: data.incidentReported,
                status: data.incidentStatus,
                bus: [data.incidentBus, data.incidentPlate].filter(Boolean).join(' / '),
                driver: [data.incidentDriver, data.incidentDriverId].filter(Boolean).join(' · '),
                trip: [data.incidentTrip !== '—' ? data.incidentTrip : '', data.incidentRoute].filter(Boolean).join(' · ')
            };
            Object.entries(overview).forEach(([key, value]) => {
                const target = edit.querySelector('[data-incident-edit-info="' + key + '"]');
                if (target) target.textContent = value || '—';
            });
            const typeField = edit.querySelector('[data-incident-edit-type]');
            if (typeField) typeField.value = data.incidentType || '';
            const count = edit.querySelector('[data-incident-edit-count]');
            const updateCount = () => { if (count) count.textContent = edit.elements.description.value.length + ' / 2000'; };
            edit.elements.description.oninput = updateCount;
            updateCount();
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
    fullLink?.addEventListener('click', (event) => {
        event.preventDefault();
        const baseUrl = fullLink.getAttribute('href');
        if (baseUrl && baseUrl !== '#') {
            close();
            const workflowUrl = baseUrl.includes('?') ? `${baseUrl}&embedded=1` : `${baseUrl}?embedded=1`;
            const workflowModal = document.getElementById('incidentWorkflowModal');
            const frame = workflowModal?.querySelector('iframe');
            if (workflowModal && frame) {
                frame.src = workflowUrl;
                workflowModal.hidden = false;
                document.body.style.overflow = 'hidden';
            }
        }
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

function initIncidentWorkflowModal() {
    const modal = document.getElementById('incidentWorkflowModal');
    const page = document.querySelector('.inc-page');
    if (!modal || !page || modal.dataset.bound === '1') return;
    modal.dataset.bound = '1';
    const dialog = modal.querySelector('[role="dialog"]');
    const frame = modal.querySelector('iframe');
    let returnFocus = null;
    let previousOverflow = '';
    const close = () => {
        if (modal.hidden) return;
        modal.hidden = true;
        frame.removeAttribute('src');
        document.body.style.overflow = previousOverflow;
        returnFocus?.focus?.();
    };
    const openWorkflow = (url, triggerEl) => {
        if (!url) return;
        returnFocus = triggerEl || returnFocus;
        previousOverflow = document.body.style.overflow;
        frame.src = url;
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        dialog?.focus();
    };
    frame.addEventListener('load', () => {
        try {
            frame.contentWindow?.scrollTo(0, 0);
        } catch (_) {
            // Same-origin locally, but a failed reset must never block the workflow.
        }
    });
    page.addEventListener('click', event => {
        const trigger = event.target.closest('[data-incident-workflow-open]');
        if (!trigger) return;
        event.preventDefault();
        const targetUrl = trigger.dataset.workflowUrl || trigger.getAttribute('href') || trigger.href;
        openWorkflow(targetUrl, trigger);
    });
    modal.querySelector('[data-incident-workflow-close]')?.addEventListener('click', close);
    window.addEventListener('message', event => {
        if (event.origin !== window.location.origin || event.source !== frame.contentWindow) return;
        if (event.data === 'close-incident-modal') {
            close();
            return;
        }
        if (event.data?.type === 'gct:incident-workflow-toast') {
            const message = String(event.data.message || '').trim();
            if (!message) return;
            const level = event.data.level === 'error' ? 'error' : 'success';
            window.showSystemToast?.(message, level, level === 'error' ? 'Validation Error' : 'Success');
        }
    });
    modal.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            close();
        }
        if (event.key === 'Tab') {
            const controls = Array.from(modal.querySelectorAll('button:not(:disabled), iframe'));
            if (controls.length < 2) return;
            const first = controls[0], last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}
