window.GCTPartialNavigation.registerInitializer('operation-trip-schedule', '.trip-schedule-page', () => {
    const tripPage = document.querySelector('.trip-schedule-page');
    if (!tripPage) {
        return;
    }
    const tripModal = document.getElementById('tripModal');
    const generateTripsModal = document.getElementById('generateTripsModal');
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
    const departureTimeSlot = document.getElementById('departureTimeSlot');
    const departureTimeInputWrap = document.getElementById('departureTimeInputWrap');
    const tripTimeConfigNotice = document.getElementById('tripTimeConfigNotice');
    const departureTime = document.getElementById('departureTime');
    const arrivalTime = document.getElementById('arrivalTime');
    const tripShift = document.getElementById('tripShift');
    const tripStatus = document.getElementById('tripStatus');
    const tripNotes = document.getElementById('tripNotes');

    const viewTripContent = document.getElementById('viewTripContent');
    const closeViewTripModal = document.getElementById('closeViewTripModal');
    const closeViewTripButton = document.getElementById('closeViewTripButton');

    const deleteTripName = document.getElementById('deleteTripName');
    const cancelDeleteTrip = document.getElementById('cancelDeleteTrip');
    const confirmDeleteTrip = document.getElementById('confirmDeleteTrip');

    const openTripModal = document.getElementById('openTripModal');
    const openGenerateTripsModal = document.getElementById('openGenerateTripsModal');

    const closeTripModal = document.getElementById('closeTripModal');
    const cancelTripModal = document.getElementById('cancelTripModal');
    const closeGenerateTripsModal = document.getElementById('closeGenerateTripsModal');
    const cancelGenerateTripsModal = document.getElementById('cancelGenerateTripsModal');

    const generateTripsForm = document.getElementById('generateTripsForm');
    const generateTargetDate = document.getElementById('generateTargetDate');

    const createAction = '/operation/trip-schedule';
    const clientTripTimesUrl = '/operation/client-trip-times';
    let selectedDeleteForm = null;
    let clientTripTimeConfigs = {};

    const configScript = document.getElementById('clientTripTimeConfigs');

    if (configScript) {
        try {
            clientTripTimeConfigs = JSON.parse(configScript.textContent || '{}');
        } catch (error) {
            console.error('Unable to parse client trip time configurations.', error);
        }
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

        if (!document.querySelector(
            '.ui-form-overlay.show, .ui-form-overlay.active, '
            + '.delete-modal-overlay.show, .delete-modal-overlay.active'
        )) {
            document.body.classList.remove('modal-open');
        }
    }

    function activeDepartureValue() {
        if (departureTimeSlot && !departureTimeSlot.disabled && !departureTimeSlot.hidden) {
            return departureTimeSlot.value || '';
        }

        return departureTime?.value || '';
    }

    function formatSlotTimeLabel(timeValue) {
        const [hour, minute] = String(timeValue || '').split(':').map(Number);

        if (Number.isNaN(hour) || Number.isNaN(minute)) {
            return String(timeValue || '');
        }

        const hour12 = hour % 12 || 12;
        return `${hour12}:${String(minute).padStart(2, '0')} ${hour >= 12 ? 'PM' : 'AM'}`;
    }

    function showTripTimeNotice(tone, message) {
        if (!tripTimeConfigNotice) {
            return;
        }

        tripTimeConfigNotice.hidden = false;
        tripTimeConfigNotice.className = `trip-time-config-notice is-${tone}`;
        tripTimeConfigNotice.replaceChildren();

        const icon = document.createElement('i');
        icon.className = `fa-solid ${tone === 'warning' ? 'fa-triangle-exclamation' : 'fa-circle-info'}`;
        icon.setAttribute('aria-hidden', 'true');

        const text = document.createElement('span');
        text.textContent = message;

        const link = document.createElement('a');
        link.href = clientTripTimesUrl;
        link.textContent = 'Open Client Trip Times';

        tripTimeConfigNotice.append(icon, text, link);
    }

    function hideTripTimeNotice() {
        if (tripTimeConfigNotice) {
            tripTimeConfigNotice.hidden = true;
            tripTimeConfigNotice.replaceChildren();
        }
    }

    function clearDepartureSlotOptions() {
        if (!departureTimeSlot) {
            return;
        }

        departureTimeSlot.replaceChildren(new Option('Select configured time', ''));
    }

    function showDepartureControl(mode) {
        if (!departureTimeInputWrap || !departureTime || !departureTimeSlot) {
            return;
        }

        const useSlots = mode === 'slots';
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
            .map((period) => configurations[`${directionKey}_${period}`] || null)
            .filter(Boolean);
        const slots = [...new Set(matchedConfigs.flatMap((config) =>
            config.enabled !== false && Array.isArray(config.slots) ? config.slots : []
        ))].sort();

        if (!matchedConfigs.length) {
            showDepartureControl('manual');
            departureTime.disabled = false;
            departureTime.hidden = false;
            departureTime.required = true;
            departureTime.name = 'departure_time';
            departureTimeSlot.disabled = true;
            departureTimeSlot.hidden = true;
            departureTimeSlot.removeAttribute('name');
            departureTimeSlot.required = false;
            departureTimeSlot.value = '';
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
        departureTimeSlot.name = 'departure_time';
        clearDepartureSlotOptions();

        slots.forEach((slot) => {
            departureTimeSlot.add(new Option(formatSlotTimeLabel(slot), slot));
        });

        const normalizedCurrentValue = currentValue.length === 5
            ? currentValue
            : currentValue.slice(0, 5);
        const selected = slots.includes(normalizedCurrentValue) ? normalizedCurrentValue : '';
        departureTimeSlot.value = selected;

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

        const setArrivalValue = (value) => {
            if (!arrivalTime || arrivalTime.value === value) {
                return;
            }

            arrivalTime.value = value;
            arrivalTime.dispatchEvent(new Event('input', { bubbles: true }));
        };

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
            `${String(arrivalHour).padStart(2, '0')}:${String(arrivalMinute).padStart(2, '0')}`
        );
    }

    function getLocalDate() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        const day = String(now.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }

    function resetCreateForm() {
        if (!tripForm) {
            return;
        }

        tripForm.reset();
        tripForm.setAttribute('action', createAction);

        if (tripFormMethod) {
            tripFormMethod.disabled = true;
        }

        if (tripCode) {
            tripCode.value = 'Auto-generated';
        }

        if (tripDate) {
            tripDate.value = getLocalDate();
        }

        if (tripStatus) {
            tripStatus.value = 'Scheduled';
        }

        if (tripDirection) {
            tripDirection.value = '';
        }

        if (tripClientName) {
            tripClientName.value = '';
        }

        updateTripTimeOptions({ preserveValue: false });

        if (tripShift) {
            tripShift.value = 'Automatic';
        }

        if (tripModalTitle) {
            tripModalTitle.textContent = 'New Trip';
        }

        if (tripSubmitText) {
            tripSubmitText.textContent = 'Save Trip';
        }
    }

    function resetBulkForm(form, targetInput) {
        if (!form) {
            return;
        }

        form.reset();

        if (targetInput) {
            targetInput.value = getLocalDate();
        }
    }

    openTripModal?.addEventListener('click', () => {
        resetCreateForm();
        openModal(tripModal);
    });

    openGenerateTripsModal?.addEventListener('click', () => {
        resetBulkForm(generateTripsForm, generateTargetDate);
        openModal(generateTripsModal);
    });

    [closeTripModal, cancelTripModal]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => closeModal(tripModal));
        });

    [closeGenerateTripsModal, cancelGenerateTripsModal]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => closeModal(generateTripsModal));
        });

    departureTime?.addEventListener('input', calculateArrival);
    departureTime?.addEventListener('change', calculateArrival);
    departureTimeSlot?.addEventListener('change', calculateArrival);
    tripRoute?.addEventListener('change', calculateArrival);

    tripClientName?.addEventListener('input', () => updateTripTimeOptions());
    tripClientName?.addEventListener('change', () => updateTripTimeOptions());
    tripDirection?.addEventListener('change', () => updateTripTimeOptions());

    tripPage?.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const button = event.target.closest('.edit-trip, .view-trip, .delete-trip');

        if (!button) {
            return;
        }

        if (button.matches('.edit-trip')) {
            if (!tripForm) {
                return;
            }

            const tripId = button.dataset.id;
            const fallbackUrl = `/operation/trip-schedule/${tripId}`;

            tripForm.setAttribute(
                'action',
                normalizePath(button.dataset.updateUrl, fallbackUrl)
            );

            if (tripFormMethod) {
                tripFormMethod.disabled = false;
                tripFormMethod.value = 'PUT';
            }

            if (tripCode) {
                tripCode.value = button.dataset.tripCode || '';
            }

            if (tripDate) {
                tripDate.value = button.dataset.tripDate || '';
            }

            if (tripRoute) {
                tripRoute.value = button.dataset.routeId || '';
            }

            if (departureTime) {
                departureTime.value = button.dataset.departureTime || '';
            }

            if (tripDirection) {
                tripDirection.value = button.dataset.direction || '';
            }

            if (tripClientName) {
                tripClientName.value = button.dataset.clientName || '';
            }

            updateTripTimeOptions({ preserveValue: false });
            const activeTimeControl = departureTimeSlot?.disabled ? departureTime : departureTimeSlot;
            if (activeTimeControl) {
                activeTimeControl.value = button.dataset.departureTime || '';
            }

            if (arrivalTime) {
                arrivalTime.value = button.dataset.arrivalTime || '';
            }

            if (tripStatus) {
                tripStatus.value = button.dataset.status || 'Scheduled';
            }

            if (tripNotes) {
                tripNotes.value = button.dataset.notes || '';
            }

            if (tripShift) {
                tripShift.value = detectShift(activeDepartureValue());
            }

            if (tripModalTitle) {
                tripModalTitle.textContent = 'Edit Trip';
            }

            if (tripSubmitText) {
                tripSubmitText.textContent = 'Update Trip';
            }
            openModal(tripModal);
            return;
        }

        if (button.matches('.view-trip')) {
            const tripData = parseTripData(button.dataset.trip);
            renderTripDetails(tripData);
            openModal(viewTripModal);
            return;
        }

        selectedDeleteForm = document.getElementById(button.dataset.formId);

        if (deleteTripName) {
            deleteTripName.textContent = button.dataset.tripCode || 'this trip';
        }

        openModal(deleteTripModal);
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

        const fields = [
            { label: 'Trip ID', value: trip.tripCode },
            { label: 'Date', value: trip.date },
            { label: 'Route', value: routeValue },
            { label: 'Status', value: trip.status },
            { label: 'Origin', value: trip.origin },
            { label: 'Destination', value: trip.destination },
            { label: 'Departure', value: trip.departure },
            { label: 'Estimated Arrival', value: trip.arrival },
            { label: 'Shift', value: trip.shift },
            { label: 'Assignment', value: trip.assignment },
            { label: 'Notes', value: trip.notes || 'No notes', full: true },
        ];

        viewTripContent.innerHTML = fields
            .map((field) => {
                const fullClass = field.full ? 'full' : '';
                const safeLabel = escapeHtml(field.label);
                const safeValue = escapeHtml(field.value || '—');

                return `
                    <div class="trip-detail-card ${fullClass}">
                        <label>${safeLabel}</label>
                        <div class="trip-detail-value">${safeValue}</div>
                    </div>
                `;
            })
            .join('');
    }

    [closeViewTripModal, closeViewTripButton]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener('click', () => closeModal(viewTripModal));
        });

    cancelDeleteTrip?.addEventListener('click', () => {
        selectedDeleteForm = null;
        closeModal(deleteTripModal);
    });

    confirmDeleteTrip?.addEventListener('click', () => {
        if (!selectedDeleteForm) {
            return;
        }

        selectedDeleteForm.requestSubmit();
    });

    const requestedTool = new URLSearchParams(window.location.search)
        .get('schedule_tool');

    if (requestedTool === 'generate') {
        openModal(generateTripsModal);
    }

    tripPage.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        closeModal(tripModal);
        closeModal(generateTripsModal);
        closeModal(viewTripModal);
        closeModal(deleteTripModal);
    });

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});