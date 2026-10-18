window.GCTPartialNavigation.registerInitializer('operation-trip-schedule', '.trip-schedule-page', () => {
    const tripModal = document.getElementById('tripModal');
    const viewTripModal = document.getElementById('viewTripModal');
    const deleteTripModal = document.getElementById('deleteTripModal');

    const tripForm = document.getElementById('tripForm');
    const tripFormMethod = document.getElementById('tripFormMethod');
    const tripModalTitle = document.getElementById('tripModalTitle');
    const tripSubmitText = document.getElementById('tripSubmitText');
    const tripCode = document.getElementById('tripCode');
    const tripDate = document.getElementById('tripDate');
    const tripRoute = document.getElementById('tripRoute');
    const tripDirection = document.getElementById('tripDirection');
    const tripClientName = document.getElementById('tripClientName');
    const tripRequiredBuses = document.getElementById('tripRequiredBuses');
    const departureTime = document.getElementById('departureTime');
    const departureTimeSlot = document.getElementById('departureTimeSlot');
    const tripPeriod = document.getElementById('tripPeriod');
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

    const deleteTripName = document.getElementById('deleteTripName');
    const cancelDeleteTrip = document.getElementById('cancelDeleteTrip');
    const confirmDeleteTrip = document.getElementById('confirmDeleteTrip');

    const closeTripModal = document.getElementById('closeTripModal');
    const cancelTripModal = document.getElementById('cancelTripModal');

    const createAction = '/operation/trip-schedule';
    const clientTripTimesUrl = '/operation/client-trip-times';
    let selectedDeleteForm = null;

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
        const period = tripPeriod?.value || '';
        const category = direction && period
            ? `${direction.toLowerCase()}_${period.toLowerCase()}`
            : null;
        const config = clientKey && category
            ? (clientTripTimeConfigs[clientKey] || {})[category] || null
            : null;

        if (!config) {
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

            if (clientKey && category) {
                showTripTimeNotice(
                    'info',
                    `No trip times are configured for this client's ${direction} ${period} category. Any valid time can be used until a configuration is created.`
                );
            } else {
                hideTripTimeNotice();
            }

            calculateArrival();
            return;
        }

        departureTime.disabled = true;
        departureTime.hidden = true;
        departureTime.required = false;
        departureTime.removeAttribute('name');
        departureTimeSlot.disabled = false;
        departureTimeSlot.hidden = false;
        departureTimeSlot.required = true;
        departureTimeSlot.setAttribute('name', 'departure_time');

        clearDepartureSlotOptions();

        const slots = Array.isArray(config.slots) ? config.slots : [];

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

        const rangeText = `${formatSlotTimeLabel(config.start)} – ${formatSlotTimeLabel(config.end)}`;

        if (!slots.length) {
            showTripTimeNotice(
                'warning',
                `${direction} ${period} is configured for this client (${rangeText}) but has no enabled time slots. Add slots before scheduling this category.`
            );
        } else if (preserveValue && currentValue && !selected) {
            showTripTimeNotice(
                'warning',
                `${formatSlotTimeLabel(currentValue)} is not an allowed ${direction} ${period} time for this client. Allowed slots: ${slots.map(formatSlotTimeLabel).join(', ')}.`
            );
        } else {
            showTripTimeNotice(
                'info',
                `Configured ${direction} ${period} window for this client: ${rangeText}. Only the configured slots can be selected.`
            );
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

        if (tripShift) {
            tripShift.value = detectShift(departureValue);
        }

        if (!departureValue || !tripRoute?.value) {
            if (arrivalTime) {
                arrivalTime.value = '';
            }
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

        if (arrivalTime) {
            arrivalTime.value =
                `${String(arrivalHour).padStart(2, '0')}:`
                + `${String(arrivalMinute).padStart(2, '0')}`;
        }
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

    function configureCreateMode({ preserveValues = false } = {}) {
        if (!tripForm) {
            return;
        }

        if (!preserveValues) {
            tripForm.reset();
        }

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

        setStatusCreateMode();
        if (tripDirection) tripDirection.required = true;
        if (tripClientName) tripClientName.required = false;
        if (tripPeriod) tripPeriod.value = '';

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

        updateNotesCount();
        updateTripTimeOptions({ preserveValue });
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
            }
            if (tripDirection) {
                tripDirection.value = button.dataset.direction || '';
                tripDirection.required = false; // Historical legacy trips may lack a direction.
            }
            if (tripClientName) {
                tripClientName.value = button.dataset.clientName || '';
                tripClientName.required = false; // Allow editing pre-migration trips.
            }
            if (tripRequiredBuses) tripRequiredBuses.value = button.dataset.requiredBuses || '1';

            if (departureTime) {
                departureTime.value = button.dataset.departureTime || '';
            }

            if (tripPeriod && button.dataset.departureTime) {
                const [prefillHour] = button.dataset.departureTime.split(':').map(Number);
                tripPeriod.value = Number.isNaN(prefillHour) ? '' : (prefillHour >= 12 ? 'PM' : 'AM');
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

    tripClientName?.addEventListener('input', () => updateTripTimeOptions());
    tripClientName?.addEventListener('change', () => updateTripTimeOptions());
    tripDirection?.addEventListener('change', () => updateTripTimeOptions());
    tripPeriod?.addEventListener('change', () => updateTripTimeOptions());
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

        const deleteButton = event.target.closest('.delete-trip');

        if (deleteButton) {
            selectedDeleteForm = document.getElementById(
                deleteButton.dataset.formId
            );

            if (deleteTripName) {
                deleteTripName.textContent =
                    deleteButton.dataset.tripCode || 'this trip';
            }

            openModal(deleteTripModal);
        }
    });

    cancelDeleteTrip?.addEventListener('click', () => {
        selectedDeleteForm = null;
        closeModal(deleteTripModal);
    });

    confirmDeleteTrip?.addEventListener('click', () => {
        if (!selectedDeleteForm?.isConnected) {
            selectedDeleteForm = null;
            closeModal(deleteTripModal);
            return;
        }

        selectedDeleteForm.requestSubmit();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeModal(tripModal);
        closeModal(viewTripModal);
        closeModal(deleteTripModal);
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

        // The trip period select is not part of server-side old() input, so
        // re-derive AM/PM from the recovered departure time when possible.
        if (tripPeriod && !tripPeriod.value && departureTime?.value) {
            const [recoveredHour] = departureTime.value.split(':').map(Number);
            tripPeriod.value = Number.isNaN(recoveredHour)
                ? ''
                : (recoveredHour >= 12 ? 'PM' : 'AM');
        }

        updateTripTimeOptions({ preserveValue: true });

        openModal(tripModal);

        const firstError = tripModal?.querySelector('.ui-field-error');

        firstError
            ?.closest('.ui-form-group')
            ?.querySelector('input, select, textarea')
            ?.focus();
    }

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
