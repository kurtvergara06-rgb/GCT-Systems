document.addEventListener('DOMContentLoaded', () => {
    /*
    |--------------------------------------------------------------------------
    | Modal Elements
    |--------------------------------------------------------------------------
    */

    const tripModal =
        document.getElementById('tripModal');

    const viewTripModal =
        document.getElementById('viewTripModal');

    const deleteTripModal =
        document.getElementById('deleteTripModal');


    /*
    |--------------------------------------------------------------------------
    | Trip Form Elements
    |--------------------------------------------------------------------------
    */

    const tripForm =
        document.getElementById('tripForm');

    const tripFormMethod =
        document.getElementById('tripFormMethod');

    const tripModalTitle =
        document.getElementById('tripModalTitle');

    const tripSubmitText =
        document.getElementById('tripSubmitText');

    const tripCode =
        document.getElementById('tripCode');

    const tripDate =
        document.getElementById('tripDate');

    const tripRoute =
        document.getElementById('tripRoute');

    const departureTime =
        document.getElementById('departureTime');

    const arrivalTime =
        document.getElementById('arrivalTime');

    const tripShift =
        document.getElementById('tripShift');

    const tripStatus =
        document.getElementById('tripStatus');

    const tripNotes =
        document.getElementById('tripNotes');


    /*
    |--------------------------------------------------------------------------
    | View Trip Elements
    |--------------------------------------------------------------------------
    */

    const viewTripContent =
        document.getElementById('viewTripContent');

    const closeViewTripModal =
        document.getElementById('closeViewTripModal');

    const closeViewTripButton =
        document.getElementById('closeViewTripButton');


    /*
    |--------------------------------------------------------------------------
    | Delete Trip Elements
    |--------------------------------------------------------------------------
    */

    const deleteTripName =
        document.getElementById('deleteTripName');

    const cancelDeleteTrip =
        document.getElementById('cancelDeleteTrip');

    const confirmDeleteTrip =
        document.getElementById('confirmDeleteTrip');

    let selectedDeleteForm = null;


    /*
    |--------------------------------------------------------------------------
    | Other Elements
    |--------------------------------------------------------------------------
    */

    const openTripModal =
        document.getElementById('openTripModal');

    const closeTripModal =
        document.getElementById('closeTripModal');

    const cancelTripModal =
        document.getElementById('cancelTripModal');

    const createAction =
        '/operation/trip-schedule';


    /*
    |--------------------------------------------------------------------------
    | Modal Helpers
    |--------------------------------------------------------------------------
    */

    function openModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.add('show');
        modal.classList.add('active');
    }


    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.classList.remove('active');
    }


    /*
    |--------------------------------------------------------------------------
    | Production-Safe URL Helper
    |--------------------------------------------------------------------------
    */

    function normalizePath(value, fallback) {
        const rawValue =
            String(value || '').trim();

        if (!rawValue) {
            return fallback;
        }

        if (
            rawValue.startsWith('/')
            && !rawValue.startsWith('//')
        ) {
            return rawValue;
        }

        try {
            const parsedUrl = new URL(
                rawValue,
                window.location.origin
            );

            if (
                parsedUrl.origin
                === window.location.origin
            ) {
                return (
                    parsedUrl.pathname
                    + parsedUrl.search
                    + parsedUrl.hash
                );
            }
        } catch (error) {
            console.warn(
                'Unable to parse trip URL.',
                error
            );
        }

        const cleanedValue = rawValue
            .replace(/^https?:\/+/i, '')
            .replace(/^\/+/, '');

        const pathIndex =
            cleanedValue.indexOf(
                'operation/trip-schedule'
            );

        if (pathIndex >= 0) {
            return `/${cleanedValue.slice(pathIndex)}`;
        }

        return fallback;
    }


    /*
    |--------------------------------------------------------------------------
    | Shift Detection
    |--------------------------------------------------------------------------
    */

    function detectShift(timeValue) {
        if (!timeValue) {
            return 'Automatic';
        }

        const parts =
            timeValue.split(':').map(Number);

        const hour = parts[0];
        const minute = parts[1];

        if (
            Number.isNaN(hour)
            || Number.isNaN(minute)
        ) {
            return 'Automatic';
        }

        const totalMinutes =
            (hour * 60) + minute;

        if (
            totalMinutes >= 240
            && totalMinutes < 720
        ) {
            return 'Morning';
        }

        if (
            totalMinutes >= 720
            && totalMinutes < 1080
        ) {
            return 'Afternoon';
        }

        return 'Night';
    }


    /*
    |--------------------------------------------------------------------------
    | Automatic ETA
    |--------------------------------------------------------------------------
    */

    function calculateArrival() {
        if (tripShift) {
            tripShift.value =
                detectShift(
                    departureTime?.value
                );
        }

        if (
            !departureTime?.value
            || !tripRoute?.value
        ) {
            return;
        }

        const selectedOption =
            tripRoute.options[
                tripRoute.selectedIndex
            ];

        const durationMinutes = Number(
            selectedOption?.dataset.duration || 60
        );

        const timeParts =
            departureTime.value
                .split(':')
                .map(Number);

        const departureHour =
            timeParts[0];

        const departureMinute =
            timeParts[1];

        if (
            Number.isNaN(departureHour)
            || Number.isNaN(departureMinute)
        ) {
            return;
        }

        const departureTotal =
            (departureHour * 60)
            + departureMinute;

        const arrivalTotal =
            (
                departureTotal
                + durationMinutes
            ) % 1440;

        const arrivalHour =
            Math.floor(arrivalTotal / 60);

        const arrivalMinute =
            arrivalTotal % 60;

        if (arrivalTime) {
            arrivalTime.value =
                `${String(arrivalHour).padStart(2, '0')}:`
                + `${String(arrivalMinute).padStart(2, '0')}`;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Reset Form for New Trip
    |--------------------------------------------------------------------------
    */

    function resetCreateForm() {
        if (!tripForm) {
            return;
        }

        tripForm.reset();

        tripForm.setAttribute(
            'action',
            createAction
        );

        if (tripFormMethod) {
            tripFormMethod.disabled = true;
        }

        if (tripCode) {
            tripCode.value =
                'Auto-generated';
        }

        if (tripDate) {
            tripDate.value =
                getLocalDate();
        }

        if (tripStatus) {
            tripStatus.value =
                'Scheduled';
        }

        if (tripShift) {
            tripShift.value =
                'Automatic';
        }

        if (tripModalTitle) {
            tripModalTitle.textContent =
                'New Trip';
        }

        if (tripSubmitText) {
            tripSubmitText.textContent =
                'Save Trip';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Local Date Helper
    |--------------------------------------------------------------------------
    */

    function getLocalDate() {
        const now = new Date();

        const year =
            now.getFullYear();

        const month =
            String(
                now.getMonth() + 1
            ).padStart(2, '0');

        const day =
            String(
                now.getDate()
            ).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }


    /*
    |--------------------------------------------------------------------------
    | Open New Trip Modal
    |--------------------------------------------------------------------------
    */

    if (openTripModal) {
        openTripModal.addEventListener(
            'click',
            () => {
                resetCreateForm();
                openModal(tripModal);
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Close Trip Modal
    |--------------------------------------------------------------------------
    */

    [
        closeTripModal,
        cancelTripModal,
    ]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeModal(tripModal);
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Automatic Shift and ETA Events
    |--------------------------------------------------------------------------
    */

    if (departureTime) {
        departureTime.addEventListener(
            'input',
            calculateArrival
        );

        departureTime.addEventListener(
            'change',
            calculateArrival
        );
    }


    if (tripRoute) {
        tripRoute.addEventListener(
            'change',
            calculateArrival
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Edit Trip
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.edit-trip')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    if (!tripForm) {
                        return;
                    }

                    const tripId =
                        button.dataset.id;

                    const fallbackUrl =
                        `/operation/trip-schedule/${tripId}`;

                    tripForm.setAttribute(
                        'action',
                        normalizePath(
                            button.dataset.updateUrl,
                            fallbackUrl
                        )
                    );

                    if (tripFormMethod) {
                        tripFormMethod.disabled =
                            false;

                        tripFormMethod.value =
                            'PUT';
                    }

                    if (tripCode) {
                        tripCode.value =
                            button.dataset.tripCode
                            || '';
                    }

                    if (tripDate) {
                        tripDate.value =
                            button.dataset.tripDate
                            || '';
                    }

                    if (tripRoute) {
                        tripRoute.value =
                            button.dataset.routeId
                            || '';
                    }

                    if (departureTime) {
                        departureTime.value =
                            button.dataset.departureTime
                            || '';
                    }

                    if (arrivalTime) {
                        arrivalTime.value =
                            button.dataset.arrivalTime
                            || '';
                    }

                    if (tripStatus) {
                        tripStatus.value =
                            button.dataset.status
                            || 'Scheduled';
                    }

                    if (tripNotes) {
                        tripNotes.value =
                            button.dataset.notes
                            || '';
                    }

                    if (tripShift) {
                        tripShift.value =
                            detectShift(
                                departureTime?.value
                            );
                    }

                    if (tripModalTitle) {
                        tripModalTitle.textContent =
                            'Edit Trip';
                    }

                    if (tripSubmitText) {
                        tripSubmitText.textContent =
                            'Update Trip';
                    }

                    openModal(tripModal);
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | View Trip
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.view-trip')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const tripData =
                        parseTripData(
                            button.dataset.trip
                        );

                    renderTripDetails(
                        tripData
                    );

                    openModal(viewTripModal);
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Parse Trip JSON
    |--------------------------------------------------------------------------
    */

    function parseTripData(rawData) {
        if (!rawData) {
            console.error(
                'Trip data attribute is empty.'
            );

            return {};
        }

        try {
            return JSON.parse(rawData);
        } catch (error) {
            console.error(
                'Unable to parse trip data.',
                rawData,
                error
            );

            return {};
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Render Trip Details
    |--------------------------------------------------------------------------
    */

    function renderTripDetails(trip) {
        if (!viewTripContent) {
            return;
        }

        const routeValue = [
            trip.routeCode,
            trip.routeName,
        ]
            .filter(Boolean)
            .join(' - ');

        const fields = [
            {
                label: 'Trip ID',
                value: trip.tripCode,
            },
            {
                label: 'Date',
                value: trip.date,
            },
            {
                label: 'Route',
                value: routeValue,
            },
            {
                label: 'Status',
                value: trip.status,
            },
            {
                label: 'Origin',
                value: trip.origin,
            },
            {
                label: 'Destination',
                value: trip.destination,
            },
            {
                label: 'Departure',
                value: trip.departure,
            },
            {
                label: 'Estimated Arrival',
                value: trip.arrival,
            },
            {
                label: 'Shift',
                value: trip.shift,
            },
            {
                label: 'Assignment',
                value: trip.assignment,
            },
            {
                label: 'Notes',
                value: trip.notes || 'No notes',
                full: true,
            },
        ];

        viewTripContent.innerHTML =
            fields
                .map((field) => {
                    const fullClass =
                        field.full
                            ? 'full'
                            : '';

                    const safeLabel =
                        escapeHtml(field.label);

                    const safeValue =
                        escapeHtml(
                            field.value || '—'
                        );

                    return `
                        <div class="trip-detail-card ${fullClass}">
                            <label>
                                ${safeLabel}
                            </label>

                            <div class="trip-detail-value">
                                ${safeValue}
                            </div>
                        </div>
                    `;
                })
                .join('');
    }


    /*
    |--------------------------------------------------------------------------
    | Close View Trip Modal
    |--------------------------------------------------------------------------
    */

    [
        closeViewTripModal,
        closeViewTripButton,
    ]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeModal(
                        viewTripModal
                    );
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Delete Trip
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.delete-trip')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const formId =
                        button.dataset.formId;

                    selectedDeleteForm =
                        document.getElementById(
                            formId
                        );

                    if (deleteTripName) {
                        deleteTripName.textContent =
                            button.dataset.tripCode
                            || 'this trip';
                    }

                    openModal(
                        deleteTripModal
                    );
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Cancel Delete
    |--------------------------------------------------------------------------
    */

    if (cancelDeleteTrip) {
        cancelDeleteTrip.addEventListener(
            'click',
            () => {
                selectedDeleteForm = null;

                closeModal(
                    deleteTripModal
                );
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Confirm Delete
    |--------------------------------------------------------------------------
    */

    if (confirmDeleteTrip) {
        confirmDeleteTrip.addEventListener(
            'click',
            () => {
                if (!selectedDeleteForm) {
                    return;
                }

                selectedDeleteForm
                    .requestSubmit();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Close Modal When Clicking Overlay
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.ui-form-overlay, '
            + '.delete-modal-overlay'
        )
        .forEach((modal) => {
            modal.addEventListener(
                'click',
                (event) => {
                    if (event.target === modal) {
                        closeModal(modal);
                    }
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Escape Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'keydown',
        (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            closeModal(tripModal);
            closeModal(viewTripModal);
            closeModal(deleteTripModal);
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Escape HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
});