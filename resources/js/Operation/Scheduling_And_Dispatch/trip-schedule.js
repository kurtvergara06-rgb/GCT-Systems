window.GCTPartialNavigation.registerInitializer('operation-trip-schedule', '.trip-schedule-page', () => {
    // Trip Date belongs to its own calendar dropdown, not the Filter popup.
    // Monthly markers are fetched from the authorized schedule counts endpoint.
    const datePickerRoot = document.querySelector('[data-trip-calendar]');
    const dateTrigger = datePickerRoot?.querySelector('[data-trip-calendar-trigger]');
    const calendarPanel = datePickerRoot?.querySelector('[data-trip-calendar-panel]');
    const calendarTitle = datePickerRoot?.querySelector('[data-trip-calendar-month]');
    const calendarGrid = datePickerRoot?.querySelector('[data-trip-calendar-days]');
    const calendarFeedback = datePickerRoot?.querySelector('[data-trip-calendar-feedback]');
    const selectedDateValue = datePickerRoot?.dataset.selectedDate || '';
    const selectedParts = selectedDateValue.split('-').map(Number);
    const calendarView = new Date(selectedParts[0] || new Date().getFullYear(), (selectedParts[1] || 1) - 1, 1);
    const calendarCounts = new Map();
    const calendarEvents = new AbortController();
    let calendarFetch = null;

    const isoCalendarDate = (year, month, day) =>
        `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
    const calendarMonthKey = () =>
        `${calendarView.getFullYear()}-${String(calendarView.getMonth() + 1).padStart(2, '0')}`;
    const operationalToday = () => {
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit',
        }).formatToParts(new Date());
        const value = (key) => parts.find((part) => part.type === key)?.value;
        return isoCalendarDate(value('year'), value('month'), value('day'));
    };
    const goToScheduleDate = (date) => {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
        const url = new URL(window.location.href);
        url.searchParams.set('trip_date', date);
        url.searchParams.delete('page');
        url.searchParams.delete('history_page');
        window.location.assign(url.toString());
    };
    const renderCalendar = () => {
        if (!calendarGrid || !calendarTitle) return;
        const year = calendarView.getFullYear();
        const month = calendarView.getMonth();
        const monthKey = calendarMonthKey();
        calendarTitle.textContent = new Intl.DateTimeFormat('en-US', {
            month: 'long', year: 'numeric',
        }).format(calendarView);
        calendarGrid.replaceChildren();
        const counts = calendarCounts.get(monthKey) || {};
        const firstWeekday = new Date(year, month, 1).getDay();
        for (let index = 0; index < firstWeekday; index += 1) {
            const spacer = document.createElement('span');
            spacer.className = 'trip-calendar-empty';
            spacer.setAttribute('aria-hidden', 'true');
            calendarGrid.appendChild(spacer);
        }
        const maxDay = new Date(year, month + 1, 0).getDate();
        for (let day = 1; day <= maxDay; day += 1) {
            const key = isoCalendarDate(year, month + 1, day);
            const data = counts[key] || {};
            const total = Number(data.total) || 0;
            const statuses = data.statuses || {};
            const statusLabels = {
                unassigned: 'Scheduled / Unassigned',
                partial: 'Partially Assigned',
                assigned: 'Fully Assigned / Ready',
                in_progress: 'In Progress',
                pending_ddr: 'Pending DDR',
                completed: 'Completed',
                missed: 'Missed',
                cancelled: 'Cancelled',
                delayed: 'Delayed',
                other: 'Other',
            };
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'trip-calendar-day';
            const dayNumber = document.createElement('span');
            dayNumber.textContent = String(day);
            button.appendChild(dayNumber);
            const details = [];
            if (total > 0) {
                button.classList.add('has-trips');
                const dots = document.createElement('span');
                dots.className = 'trip-calendar-status-dots';
                dots.setAttribute('aria-hidden', 'true');
                for (const [status, label] of Object.entries(statusLabels)) {
                    const count = Number(statuses[status]) || 0;
                    if (!count) continue;
                    const dot = document.createElement('span');
                    dot.className = 'trip-calendar-day-dot is-' + status;
                    dots.appendChild(dot);
                    details.push(`${count} ${label}`);
                }
                button.appendChild(dots);
            }
            if (key === selectedDateValue) {
                button.classList.add('is-selected');
                button.setAttribute('aria-current', 'date');
            }
            if (key === operationalToday()) button.classList.add('is-today');
            button.setAttribute('aria-label', `${key}, ${total} ${total === 1 ? 'trip' : 'trips'}${details.length ? ': ' + details.join(', ') : ''}`);
            button.addEventListener('click', () => goToScheduleDate(key));
            calendarGrid.appendChild(button);
        }
    };
    const loadCalendarMonth = async () => {
        if (!datePickerRoot || !calendarPanel) return;
        const key = calendarMonthKey();
        if (calendarCounts.has(key)) {
            calendarFeedback.textContent = '';
            renderCalendar();
            return;
        }
        calendarFetch?.abort();
        calendarFetch = new AbortController();
        const currentFetch = calendarFetch;
        calendarFeedback.textContent = 'Loading available trip dates...';
        try {
            const url = new URL(datePickerRoot.dataset.availabilityUrl, window.location.origin);
            url.searchParams.set('month', key);
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' },
                signal: currentFetch.signal,
            });
            if (!response.ok) throw new Error('Unable to retrieve trip dates');
            const data = await response.json();
            if (data.month !== key) return;
            calendarCounts.set(key, data.dates || {});
            if (calendarMonthKey() === key) {
                calendarFeedback.textContent = '';
                renderCalendar();
            }
        } catch (error) {
            if (error.name !== 'AbortError' && calendarMonthKey() === key) {
                calendarFeedback.textContent = 'Trip indicators unavailable. You can still select a date.';
            }
        }
    };
    const closeCalendar = () => {
        if (!calendarPanel) return;
        calendarPanel.hidden = true;
        dateTrigger?.setAttribute('aria-expanded', 'false');
    };
    dateTrigger?.addEventListener('click', () => {
        const open = calendarPanel.hidden;
        calendarPanel.hidden = !open;
        dateTrigger.setAttribute('aria-expanded', String(open));
        if (open) {
            renderCalendar();
            void loadCalendarMonth();
        }
    });
    datePickerRoot?.querySelector('[data-trip-calendar-close]')?.addEventListener('click', () => {
        closeCalendar();
        dateTrigger?.focus();
    });
    for (const [selector, delta] of [['[data-trip-calendar-prev]', -1], ['[data-trip-calendar-next]', 1]]) {
        datePickerRoot?.querySelector(selector)?.addEventListener('click', () => {
            calendarView.setMonth(calendarView.getMonth() + delta, 1);
            calendarFeedback.textContent = '';
            renderCalendar();
            void loadCalendarMonth();
        });
    }
    datePickerRoot?.querySelector('[data-trip-calendar-today]')?.addEventListener('click', () => goToScheduleDate(operationalToday()));
    document.addEventListener('click', (event) => {
        if (datePickerRoot && !datePickerRoot.contains(event.target)) closeCalendar();
    }, { signal: calendarEvents.signal });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && calendarPanel && !calendarPanel.hidden) {
            closeCalendar();
            dateTrigger?.focus();
        }
    }, { signal: calendarEvents.signal });
    window.addEventListener('gct:navigation-before', () => {
        calendarEvents.abort();
        calendarFetch?.abort();
    }, { once: true });

    const tripModal = document.getElementById('tripModal');
    const viewTripModal = document.getElementById('viewTripModal');

    const tripForm = document.getElementById('tripForm');
    const tripFormMethod = document.getElementById('tripFormMethod');
    const tripModalTitle = document.getElementById('tripModalTitle');
    const tripSubmitText = document.getElementById('tripSubmitText');
    const tripCode = document.getElementById('tripCode');
    const tripCodeLabel = document.getElementById('tripCodeLabel');
    const syncTripCodeLabel = () => {
        if (tripCodeLabel) tripCodeLabel.textContent = tripCode?.value || 'Auto-generated';
        const display = document.getElementById('tripCodeDisplay');
        if (display) display.value = tripCode?.value || 'Auto-generated';
    };
    // Show accessible inline messages rather than transient browser tooltips.
    // Native constraint validation still prevents submitting incomplete trips.
    tripForm?.addEventListener('invalid', (event) => {
        const field = event.target;
        if (!(field instanceof HTMLElement)) return;
        event.preventDefault();
        const group = field.closest('.ui-form-group');
        if (!group) return;
        const message = field.id === 'tripDirection'
            ? 'Select Incoming or Outgoing to continue.'
            : field.id === 'tripServiceMode'
                ? 'Choose Client or No Client to continue.'
            : field.id === 'tripClientName'
                ? 'Select a client to continue.'
                : field.id === 'tripRoute'
                    ? 'Select a route assigned to this client.'
                    : field.id === 'departureTime' || field.id === 'departureTimeSlot'
                        ? 'Select a valid departure time.'
                        : field.validationMessage || 'Complete this field to continue.';
        let error = group.querySelector('.trip-inline-error');
        if (!error) {
            error = document.createElement('span');
            error.className = 'trip-inline-error';
            error.setAttribute('role', 'alert');
            group.appendChild(error);
        }
        error.textContent = message;
        field.setAttribute('aria-invalid', 'true');
        if (!tripForm.querySelector('[data-trip-validation-focused]')) {
            field.setAttribute('data-trip-validation-focused', 'true');
            field.focus({ preventScroll: true });
            field.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }, true);
    const clearTripValidation = (event) => {
        const field = event.target;
        if (!(field instanceof HTMLElement) || !field.matches('input, select, textarea')) return;
        if (!field.validity?.valid) return;
        const group = field.closest('.ui-form-group');
        group?.querySelector('.trip-inline-error')?.remove();
        field.removeAttribute('aria-invalid');
    };
    tripForm?.addEventListener('input', clearTripValidation);
    tripForm?.addEventListener('change', clearTripValidation);
    tripForm?.addEventListener('click', () => {
        tripForm.querySelectorAll('[data-trip-validation-focused]').forEach((field) => field.removeAttribute('data-trip-validation-focused'));
    }, true);

    const tripDate = document.getElementById('tripDate');
    const tripRoute = document.getElementById('tripRoute');
    const tripDirection = document.getElementById('tripDirection');
    const tripClientName = document.getElementById('tripClientName');
    const tripServiceMode = document.getElementById('tripServiceMode');
    const tripModeChoices = Array.from(tripForm?.querySelectorAll('input[name="trip_mode_choice"]') || []);
    const syncTripModeChoices = () => {
        tripModeChoices.forEach((choice) => { choice.checked = choice.value === tripServiceMode?.value; });
    };
    tripModeChoices.forEach((choice) => {
        choice.addEventListener('change', () => {
            if (!choice.checked || !tripServiceMode) return;
            tripServiceMode.value = choice.value;
            tripServiceMode.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
    const tripCodeDisplay = document.getElementById('tripCodeDisplay');
    const tripClientField = document.getElementById('tripClientField');
    const tripRouteLabel = document.getElementById('tripRouteLabel');

    function syncTripRouteOptions({ preserveRoute = false } = {}) {
        if (!tripRoute || !tripClientName || !tripServiceMode) return;

        const creating = Boolean(tripFormMethod?.disabled);
        const mode = tripServiceMode.value;
        const client = tripClientName.value.trim().toLowerCase();
        const previous = tripRoute.value;
        const requiresClient = mode === 'client';

        syncTripModeChoices();
        if (tripClientField) tripClientField.hidden = !requiresClient;
        tripClientName.disabled = !requiresClient;
        tripClientName.required = requiresClient && creating;
        if (tripRouteLabel) {
            tripRouteLabel.innerHTML = (requiresClient ? 'Client Route' : mode === 'general' ? 'General Route' : 'Route')
                + ' <span class="ui-required">*</span>';
        }

        // Never offer a route belonging to another client (or a general route
        // to a client trip), even when the select contains all active routes.
        for (const option of tripRoute.options) {
            if (!option.value) continue;
            const ownerId = String(option.dataset.clientId || '').trim();
            const ownerName = String(option.dataset.clientName || '').trim().toLowerCase();
            const available = mode === 'general'
                ? !ownerId
                : requiresClient && Boolean(client && ownerId && ownerName === client);
            // Preserve an existing legacy route without offering unrelated
            // routes as alternatives when editing a historical schedule.
            const legacyCurrent = !creating && option.value === tripRoute.dataset.originalRouteId;
            option.hidden = !available && !legacyCurrent;
            option.disabled = !available && !legacyCurrent;
        }

        const stillAllowed = Array.from(tripRoute.options).some(option =>
            option.value === previous && !option.disabled && !option.hidden
        );
        tripRoute.value = preserveRoute && stillAllowed ? previous : '';
        // Editing historical trips preserves their existing route selection.
        tripRoute.disabled = creating && (!mode || (requiresClient && !client));
        const placeholder = tripRoute.options[0];
        if (placeholder) {
            placeholder.textContent = !mode ? 'Select trip type first'
                : requiresClient && !client ? 'Select client first'
                : 'Select a ' + (mode === 'general' ? 'general route' : 'client route');
        }

        if (creating && !tripRoute.value && arrivalTime) arrivalTime.value = '';

        const submit = tripForm?.querySelector('[type="submit"]');
        if (submit) submit.disabled = false;
        if (creating && tripSubmitText) tripSubmitText.textContent = 'Create Trip';
    }

    const tripRequiredBuses = document.getElementById('tripRequiredBuses');
    const departureTime = document.getElementById('departureTime');
    const departureTimeSlot = document.getElementById('departureTimeSlot');
    const departureTimeInputWrap = document.getElementById('departureTimeInputWrap');
    const tripTimeConfigNotice = document.getElementById('tripTimeConfigNotice');
    const arrivalTime = document.getElementById('arrivalTime');
    const tripShift = document.getElementById('tripShift');
    const tripStatus = document.getElementById('tripStatus');
    const tripNotes = document.getElementById('tripNotes');

    const viewTripContent = document.getElementById('viewTripContent');
    const viewTripHeroTitle = document.getElementById('viewTripHeroTitle');
    const tripNotesCount = document.getElementById('tripNotesCount');
    const closeViewTripModal = document.getElementById('closeViewTripModal');
    const closeViewTripButton = document.getElementById('closeViewTripButton');

    const cancelServiceTripModal = document.getElementById('cancelServiceTripModal');
    const cancelServiceTripForm = document.getElementById('cancelServiceTripForm');
    const cancelServiceTripCode = document.getElementById('cancelServiceTripCode');
    const cancelServiceTripBack = document.getElementById('cancelServiceTripBack');
    const cancelServiceTripClose = document.getElementById('cancelServiceTripClose');


    const closeTripModal = document.getElementById('closeTripModal');
    const cancelTripModal = document.getElementById('cancelTripModal');

    const createAction = '/operation/trip-schedule';
    const clientTripTimesUrl = '/operation/client-trip-times';

    let clientTripTimeConfigs = {};

    const configScript = document.getElementById('clientTripTimeConfigs');

    if (configScript) {
        try {
            clientTripTimeConfigs = JSON.parse(configScript.textContent || '{}');
        } catch (error) {
            console.warn('Unable to parse client trip time configurations.', error);
            clientTripTimeConfigs = {};
        }
    }

    function formatSlotTimeLabel(timeValue) {
        const [hour, minute] = String(timeValue || '').split(':').map(Number);

        if (Number.isNaN(hour) || Number.isNaN(minute)) {
            return String(timeValue || '');
        }

        const period = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;

        return `${hour12}:${String(minute).padStart(2, '0')} ${period}`;
    }

    function activeDepartureValue() {
        if (
            departureTimeSlot
            && !departureTimeSlot.disabled
            && !departureTimeSlot.hidden
        ) {
            return departureTimeSlot.value || '';
        }

        return departureTime?.value || '';
    }

    function showTripTimeNotice(tone, message) {
        if (!tripTimeConfigNotice) {
            return;
        }

        tripTimeConfigNotice.hidden = false;
        tripTimeConfigNotice.className = `trip-time-config-notice is-${tone}`;
        tripTimeConfigNotice.innerHTML = `
            <i class="fa-solid ${
                tone === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info'
            }" aria-hidden="true"></i>
            <span>${escapeHtml(message)} </span>
            <a href="${clientTripTimesUrl}">Open Client Trip Times</a>
        `;
    }

    function hideTripTimeNotice() {
        if (tripTimeConfigNotice) {
            tripTimeConfigNotice.hidden = true;
            tripTimeConfigNotice.innerHTML = '';
        }
    }

    function clearDepartureSlotOptions() {
        if (!departureTimeSlot) {
            return;
        }

        departureTimeSlot.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select configured time';
        departureTimeSlot.appendChild(placeholder);
    }

    // Keep both controls in the same visual position, including when shared
    // form styles override the native [hidden] attribute.
    function showDepartureControl(mode) {
        const useSlots = mode === 'slots';
        if (!departureTimeInputWrap) return;

        // Physically detach the inactive control. Form component styles can
        // override [hidden] or display, but cannot render a detached node.
        // Keep its JS reference for switching back without losing listeners.
        const active = useSlots ? departureTimeSlot : departureTime;
        const inactive = useSlots ? departureTime : departureTimeSlot;
        inactive.disabled = true;
        inactive.hidden = true;
        inactive.remove();
        active.hidden = false;
        if (active.parentElement !== departureTimeInputWrap) {
            departureTimeInputWrap.appendChild(active);
        }
        departureTimeInputWrap.dataset.departureMode = mode;
    }

    // Switch the departure control between the free time input and the
    // configured slot select based on the selected client, direction, and
    // AM/PM category. Never shows another client's slots.
    function updateTripTimeOptions({ preserveValue = true } = {}) {
        if (!departureTime || !departureTimeSlot) {
            return;
        }

        const currentValue = activeDepartureValue();
        const clientKey = String(tripClientName?.value || '').trim().toLowerCase();
        const direction = tripDirection?.value || '';
        const configurations = clientKey && direction
            ? clientTripTimeConfigs[clientKey] || {}
            : {};
        const directionKey = direction.toLowerCase();
        const matchedConfigs = ['am', 'pm']
            .map((period) => ({
                period: period.toUpperCase(),
                config: configurations[`${directionKey}_${period}`] || null,
            }))
            .filter((item) => item.config);
        const slots = [...new Set(matchedConfigs.flatMap(({ config }) =>
            config.enabled !== false && Array.isArray(config.slots) ? config.slots : []
        ))].sort();
        const config = matchedConfigs.length ? matchedConfigs : null;

        if (!config) {
            showDepartureControl('manual');
            departureTime.disabled = false;
            departureTime.hidden = false;
            departureTime.required = true;
            departureTime.setAttribute('name', 'departure_time');
            departureTimeSlot.disabled = true;
            departureTimeSlot.hidden = true;
            departureTimeSlot.removeAttribute('name');
            departureTimeSlot.required = false;
            departureTimeSlot.value = '';
            departureTimeSlot.dataset.selectedValue = '';
            clearDepartureSlotOptions();

            if (currentValue) {
                departureTime.value = currentValue;
            }

            if (clientKey && direction) {
                showTripTimeNotice(
                    'info',
                    `No trip times are configured for this client's ${direction} service. Any valid time can be used until a configuration is created.`
                );
            } else {
                hideTripTimeNotice();
            }

            calculateArrival();
            return;
        }

        showDepartureControl('slots');
        departureTime.disabled = true;
        departureTime.hidden = true;
        departureTime.required = false;
        departureTime.removeAttribute('name');
        departureTimeSlot.disabled = false;
        departureTimeSlot.hidden = false;
        departureTimeSlot.required = true;
        departureTimeSlot.setAttribute('name', 'departure_time');

        clearDepartureSlotOptions();

        slots.forEach((slot) => {
            const option = document.createElement('option');
            option.value = slot;
            option.textContent = formatSlotTimeLabel(slot);
            departureTimeSlot.appendChild(option);
        });

        let selected = '';

        if (currentValue) {
            const normalized = currentValue.length === 5
                ? currentValue
                : currentValue.slice(0, 5);

            if (slots.includes(normalized)) {
                selected = normalized;
            }
        }

        departureTimeSlot.value = selected;
        departureTimeSlot.dataset.selectedValue = selected;

        if (!slots.length) {
            showTripTimeNotice(
                'warning',
                `No available departure slots exist for this client's ${direction} service. Enable the appropriate AM/PM category and its slots before scheduling.`
            );
        } else if (preserveValue && currentValue && !selected) {
            showTripTimeNotice(
                'warning',
                `${formatSlotTimeLabel(currentValue)} is not an allowed ${direction} departure. Allowed slots: ${slots.map(formatSlotTimeLabel).join(', ')}.`
            );
        } else {
            hideTripTimeNotice();
        }

        calculateArrival();
    }

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('show', 'active');
        document.body.classList.add('modal-open');
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('show', 'active');

        const hasOpenModal = document.querySelector(
            '.ui-form-overlay.show, .ui-form-overlay.active, '
            + '.delete-modal-overlay.show, .delete-modal-overlay.active'
        );

        if (!hasOpenModal) {
            document.body.classList.remove('modal-open');
        }
    }

    function normalizePath(value, fallback) {
        const rawValue = String(value || '').trim();

        if (!rawValue) {
            return fallback;
        }

        if (rawValue.startsWith('/') && !rawValue.startsWith('//')) {
            return rawValue;
        }

        try {
            const parsedUrl = new URL(rawValue, window.location.origin);

            if (parsedUrl.origin === window.location.origin) {
                return parsedUrl.pathname + parsedUrl.search + parsedUrl.hash;
            }
        } catch (error) {
            console.warn('Unable to parse trip URL.', error);
        }

        const cleanedValue = rawValue
            .replace(/^https?:\/+/i, '')
            .replace(/^\/+/, '');

        const pathIndex = cleanedValue.indexOf('operation/trip-schedule');

        if (pathIndex >= 0) {
            return `/${cleanedValue.slice(pathIndex)}`;
        }

        return fallback;
    }

    function detectShift(timeValue) {
        if (!timeValue) {
            return 'Automatic';
        }

        const [hour, minute] = timeValue.split(':').map(Number);

        if (Number.isNaN(hour) || Number.isNaN(minute)) {
            return 'Automatic';
        }

        const totalMinutes = (hour * 60) + minute;

        if (totalMinutes >= 240 && totalMinutes < 720) {
            return 'Morning';
        }

        if (totalMinutes >= 720 && totalMinutes < 1080) {
            return 'Afternoon';
        }

        return 'Night';
    }

    function calculateArrival() {
        const departureValue = activeDepartureValue();

        const setArrivalValue = (value) => {
            if (!arrivalTime || arrivalTime.value === value) {
                return;
            }

            arrivalTime.value = value;
            arrivalTime.dispatchEvent(new Event('input', { bubbles: true }));
        };

        if (tripShift) {
            tripShift.value = detectShift(departureValue);
        }

        if (!departureValue || !tripRoute?.value) {
            setArrivalValue('');
            return;
        }

        const selectedOption = tripRoute.options[tripRoute.selectedIndex];
        const durationMinutes = Number(selectedOption?.dataset.duration || 60);
        const [departureHour, departureMinute] = departureValue.split(':').map(Number);

        if (Number.isNaN(departureHour) || Number.isNaN(departureMinute)) {
            return;
        }

        const departureTotal = (departureHour * 60) + departureMinute;
        const arrivalTotal = (departureTotal + durationMinutes) % 1440;
        const arrivalHour = Math.floor(arrivalTotal / 60);
        const arrivalMinute = arrivalTotal % 60;

        setArrivalValue(
            `${String(arrivalHour).padStart(2, '0')}:`
            + `${String(arrivalMinute).padStart(2, '0')}`
        );
    }

    function getLocalDate() {
        // Keep client-side date constraints aligned with Laravel's
        // configured operation timezone (Asia/Manila), not the device timezone.
        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        }).formatToParts(new Date());
        const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

        return `${values.year}-${values.month}-${values.day}`;
    }

    function updateNotesCount() {
        if (!tripNotesCount) {
            return;
        }

        const length = tripNotes?.value?.length || 0;
        tripNotesCount.textContent = `${length} / 2000`;
    }

    function setStatusCreateMode() {
        if (!tripStatus) {
            return;
        }

        tripStatus.value = 'Scheduled';
        tripStatus.disabled = true;
        tripStatus.removeAttribute('required');
        tripStatus.setAttribute(
            'title',
            'New trips always start as Scheduled.'
        );
    }

    function setStatusEditMode() {
        if (!tripStatus) {
            return;
        }

        tripStatus.disabled = false;
        tripStatus.required = true;
        tripStatus.removeAttribute('title');
    }

    function setTripClientValue(value) {
        if (!tripClientName) return;
        const clientValue = String(value || '').trim();
        // Older trips may reference clients that are now inactive. Preserve
        // their saved value for editing without adding inactive clients to
        // the normal list of selectable clients.
        tripClientName.querySelectorAll('[data-legacy-client]').forEach((option) => option.remove());
        if (clientValue && !Array.from(tripClientName.options).some((option) => option.value === clientValue)) {
            const option = new Option(clientValue + ' (existing allocation)', clientValue, true, true);
            option.dataset.legacyClient = 'true';
            tripClientName.add(option);
        }
        tripClientName.value = clientValue;
    }

    function configureCreateMode({ preserveValues = false } = {}) {
        if (!tripForm) {
            return;
        }

        if (!preserveValues) {
            tripForm.reset();
            setTripClientValue('');
            if (tripServiceMode) tripServiceMode.value = 'client';
        }
        if (tripRoute) tripRoute.dataset.originalRouteId = '';

        tripForm.setAttribute('action', createAction);

        if (tripFormMethod) {
            tripFormMethod.disabled = true;
        }

        if (tripCode) {
            tripCode.value = 'Auto-generated';
        }

        if (tripDate) {
            tripDate.min = getLocalDate();

            if (!preserveValues) {
                tripDate.value = getLocalDate();
            }
        }

        syncTripRouteOptions({ preserveRoute: preserveValues });
        syncTripCodeLabel();
        setStatusCreateMode();
        if (tripDirection) tripDirection.required = true;
        if (tripClientName) tripClientName.required = tripServiceMode?.value === 'client';

        if (tripShift && !preserveValues) {
            tripShift.value = 'Automatic';
        }

        if (arrivalTime && !preserveValues) {
            arrivalTime.value = '';
        }

        if (tripModalTitle) {
            tripModalTitle.textContent = 'New Trip';
        }

        if (tripSubmitText) {
            tripSubmitText.textContent = 'Create Trip';
        }

        syncTripCodeLabel();
        updateNotesCount();
        updateTripTimeOptions({ preserveValue: preserveValues });
        calculateArrival();
    }

    function configureEditMode(button = null, options = {}) {
        if (!tripForm) {
            return;
        }

        const {
            preserveValues = false,
            tripId = button?.dataset?.id || '',
            tripCodeValue = button?.dataset?.tripCode || '',
            updateUrl = button?.dataset?.updateUrl || '',
        } = options;

        const fallbackUrl = tripId
            ? `/operation/trip-schedule/${tripId}`
            : createAction;

        tripForm.setAttribute(
            'action',
            normalizePath(updateUrl, fallbackUrl)
        );

        if (tripFormMethod) {
            tripFormMethod.disabled = false;
            tripFormMethod.value = 'PUT';
        }

        setStatusEditMode();

        if (tripDate) {
            const allowPast = options.allowPast !== undefined
                ? Boolean(options.allowPast)
                : button?.dataset?.allowPast === 'true';

            const preservingPastValue = preserveValues
                && tripDate.value
                && tripDate.value < getLocalDate();

            if (allowPast || preservingPastValue) {
                tripDate.removeAttribute('min');
            } else {
                tripDate.min = getLocalDate();
            }
        }

        if (!preserveValues && button) {
            if (tripCode) {
                tripCode.value = button.dataset.tripCode || '';
            }

            if (tripDate) {
                tripDate.value = button.dataset.tripDate || '';
            }

            if (tripRoute) {
                tripRoute.value = button.dataset.routeId || '';
                tripRoute.dataset.originalRouteId = tripRoute.value;
            }
            if (tripDirection) {
                tripDirection.value = button.dataset.direction || '';
                tripDirection.required = false; // Historical legacy trips may lack a direction.
            }
            if (tripClientName) {
                setTripClientValue(button.dataset.clientName || '');
                if (tripServiceMode) tripServiceMode.value = tripClientName.value ? 'client' : 'general';
                tripClientName.required = false; // Allow editing pre-migration trips.
            }
            if (tripRequiredBuses) tripRequiredBuses.value = button.dataset.requiredBuses || '1';

            if (departureTime) {
                departureTime.value = button.dataset.departureTime || '';
            }


            if (tripStatus) {
                tripStatus.value = button.dataset.status || 'Scheduled';
            }

            if (tripNotes) {
                tripNotes.value = button.dataset.notes || '';
            }
        } else if (tripCode) {
            tripCode.value = tripCodeValue || tripCode.value || '';
        }

        if (tripModalTitle) {
            tripModalTitle.textContent = 'Edit Trip';
        }

        if (tripSubmitText) {
            tripSubmitText.textContent = 'Update Trip';
        }

        syncTripCodeLabel();
        syncTripRouteOptions({ preserveRoute: true });
        updateNotesCount();
        updateTripTimeOptions({ preserveValue: true });
        calculateArrival();
    }

    [closeTripModal, cancelTripModal]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => closeModal(tripModal));
        });

    tripNotes?.addEventListener('input', updateNotesCount);

    departureTime?.addEventListener('input', calculateArrival);
    departureTime?.addEventListener('change', calculateArrival);
    tripRoute?.addEventListener('change', calculateArrival);

    tripServiceMode?.addEventListener('change', () => {
        setTripClientValue('');
        if (tripRoute) tripRoute.value = '';
        syncTripRouteOptions();
        updateTripTimeOptions({ preserveValue: false });
        calculateArrival();
    });
    tripClientName?.addEventListener('input', () => updateTripTimeOptions());
    tripClientName?.addEventListener('change', () => {
        if (tripRoute) tripRoute.value = '';
        syncTripRouteOptions();
        updateTripTimeOptions({ preserveValue: false });
        calculateArrival();
    });
    tripDirection?.addEventListener('change', () => updateTripTimeOptions());
    departureTimeSlot?.addEventListener('change', () => {
        departureTimeSlot.dataset.selectedValue = departureTimeSlot.value || '';
        calculateArrival();
    });

    function parseTripData(rawData) {
        if (!rawData) {
            console.error('Trip data attribute is empty.');
            return {};
        }

        try {
            return JSON.parse(rawData);
        } catch (error) {
            console.error('Unable to parse trip data.', rawData, error);
            return {};
        }
    }

    function renderTripDetails(trip) {
        if (!viewTripContent) {
            return;
        }

        const routeValue = [trip.routeCode, trip.routeName]
            .filter(Boolean)
            .join(' - ');

        if (!trip.tripCode || !trip.date) {
            console.error('Trip Details did not receive the trip data payload.', trip);
            viewTripContent.innerHTML = '<p class="trip-detail-load-error" role="alert">Trip details could not be loaded. Please refresh the schedule and try again.</p>';
            return;
        }

        if (viewTripHeroTitle) {
            viewTripHeroTitle.textContent =
                trip.tripCode
                    ? `${trip.tripCode} · ${routeValue || 'Scheduled Trip'}`
                    : (routeValue || 'Scheduled Trip');
        }

        const fields = [
            {
                label: 'Trip ID',
                value: trip.tripCode,
                icon: 'fa-hashtag',
            },
            {
                label: 'Date',
                value: trip.date,
                icon: 'fa-calendar-day',
            },
            {
                label: 'Route',
                value: routeValue,
                icon: 'fa-route',
                full: true,
            },
            {
                label: 'Status',
                value: trip.status,
                icon: 'fa-circle-check',
                badge: 'status',
            },
            {
                label: 'Client / Company',
                value: trip.clientName || 'No client assigned',
                icon: 'fa-building',
            },
            {
                label: 'Origin',
                value: trip.origin,
                icon: 'fa-location-dot',
            },
            {
                label: 'Destination',
                value: trip.destination,
                icon: 'fa-location-crosshairs',
            },
            {
                label: 'Departure',
                value: trip.departure,
                icon: 'fa-clock',
            },
            {
                label: 'Estimated Arrival',
                value: trip.arrival,
                icon: 'fa-clock-rotate-left',
            },
            {
                label: 'Assignment',
                value: trip.assignment,
                icon: 'fa-user-group',
                badge: 'assignment',
            },
            {
                label: 'Service Direction',
                value: trip.direction || 'Not specified',
                icon: 'fa-arrows-up-down',
            },
            {
                label: 'Required Buses',
                value: String(trip.requiredBuses || 1),
                icon: 'fa-bus',
                full: true,
            },
            {
                label: 'Notes',
                value: trip.notes || 'No notes',
                icon: 'fa-note-sticky',
                full: true,
            },
        ];

        viewTripContent.innerHTML = fields
            .map((field) => {
                const fullClass = field.full ? 'full' : '';
                const safeLabel = escapeHtml(field.label);
                const safeValue = escapeHtml(field.value || '—');
                const icon = escapeHtml(field.icon);

                let valueMarkup =
                    `<div class="trip-detail-value">${safeValue}</div>`;

                if (field.badge) {
                    const stateClass = String(field.value || '')
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-');

                    valueMarkup = `
                        <div class="trip-detail-value">
                            <span class="trip-detail-pill ${field.badge} ${stateClass}">
                                ${safeValue}
                            </span>
                        </div>
                    `;
                }

                return `
                    <article class="trip-detail-card ${fullClass}">
                        <span class="trip-detail-icon">
                            <i class="fa-solid ${icon}"></i>
                        </span>

                        <div class="trip-detail-copy">
                            <label>${safeLabel}</label>
                            ${valueMarkup}
                        </div>
                    </article>
                `;
            })
            .join('');
    }

    [closeViewTripModal, closeViewTripButton]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => closeModal(viewTripModal));
        });

    document.addEventListener('click', (event) => {
        const expandButton = event.target.closest('.service-expand-button');
        if (expandButton) {
            const detailRow = expandButton.closest('tr')?.nextElementSibling;
            if (detailRow?.classList.contains('service-details-row')) {
                detailRow.hidden = !detailRow.hidden;
                expandButton.setAttribute('aria-expanded', String(!detailRow.hidden));
            }
            return;
        }

        const openButton = event.target.closest('#openTripModal');

        if (openButton) {
            configureCreateMode();
            openModal(tripModal);
            return;
        }

        const editButton = event.target.closest('.edit-trip');

        if (editButton) {
            configureEditMode(editButton);
            openModal(tripModal);
            return;
        }

        const viewButton = event.target.closest('.view-trip');

        if (viewButton) {
            const tripData = parseTripData(viewButton.dataset.trip);
            renderTripDetails(tripData);
            openModal(viewTripModal);
            return;
        }

        const cancellationButton = event.target.closest('.cancel-service-trip');
        if (cancellationButton && cancelServiceTripForm && cancelServiceTripModal) {
            cancelServiceTripForm.action = cancellationButton.dataset.cancelUrl || '#';
            cancelServiceTripForm.querySelector('#cancellationReason').value = '';
            if (cancelServiceTripCode) cancelServiceTripCode.textContent = cancellationButton.dataset.tripCode || 'this trip';
            openModal(cancelServiceTripModal);
            cancelServiceTripForm.querySelector('#cancellationReason')?.focus();
            return;
        }

    });

    [cancelServiceTripBack, cancelServiceTripClose].filter(Boolean).forEach((button) => {
        button.addEventListener('click', () => closeModal(cancelServiceTripModal));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeModal(tripModal);
        closeModal(viewTripModal);
    });

    function restoreValidationModal() {
        const recoveryElement = document.getElementById(
            'tripValidationRecovery'
        );

        if (!recoveryElement || !tripForm) {
            return;
        }

        let recovery;

        try {
            recovery = JSON.parse(
                recoveryElement.textContent || '{}'
            );
        } catch (error) {
            console.warn(
                'Unable to restore Trip Schedule validation state.',
                error
            );
            return;
        }

        if (recovery.mode === 'edit') {
            configureEditMode(
                null,
                {
                    preserveValues: true,
                    tripId: recovery.tripId,
                    tripCodeValue: recovery.tripCode,
                    updateUrl: recovery.updateUrl,
                }
            );
        } else {
            configureCreateMode({
                preserveValues: true,
            });
        }

        updateTripTimeOptions({ preserveValue: true });

        openModal(tripModal);

        const firstError = tripModal?.querySelector('.ui-field-error');

        firstError
            ?.closest('.ui-form-group')
            ?.querySelector('input, select, textarea')
            ?.focus();
    }

    syncTripCodeLabel();
    updateNotesCount();
    restoreValidationModal();

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});
