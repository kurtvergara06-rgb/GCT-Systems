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
    const departureTime = document.getElementById('departureTime');
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
    let selectedDeleteForm = null;

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
        if (tripShift) {
            tripShift.value = detectShift(departureTime?.value);
        }

        if (!departureTime?.value || !tripRoute?.value) {
            if (arrivalTime) {
                arrivalTime.value = '';
            }
            return;
        }

        const selectedOption = tripRoute.options[tripRoute.selectedIndex];
        const durationMinutes = Number(selectedOption?.dataset.duration || 60);
        const [departureHour, departureMinute] = departureTime.value.split(':').map(Number);

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
            tripSubmitText.textContent = 'Save Trip';
        }

        updateNotesCount();
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

        updateNotesCount();
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
            },
            {
                label: 'Status',
                value: trip.status,
                icon: 'fa-circle-check',
                badge: 'status',
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
                label: 'Shift',
                value: trip.shift,
                icon: 'fa-business-time',
            },
            {
                label: 'Assignment',
                value: trip.assignment,
                icon: 'fa-user-group',
                badge: 'assignment',
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
