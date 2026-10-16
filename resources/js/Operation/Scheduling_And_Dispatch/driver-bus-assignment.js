window.GCTPartialNavigation.registerInitializer('operation-driver-bus-assignment', '.assignment-page', () => {
    // Resource filters are page-local and never change assignment eligibility.
    // Re-evaluate after AJAX region refreshes without introducing a page reload.
    const applyResourceFilter = (toolbar) => {
        if (!toolbar) return;
        const group = toolbar.dataset.resourceFilter;
        const query = (toolbar.querySelector('input[type="search"]')?.value || '').trim().toLowerCase();
        const category = (toolbar.querySelector('select')?.value || '').trim().toLowerCase();
        const card = toolbar.closest('.resource-card');
        card?.querySelectorAll('[data-resource-row]').forEach((row) => {
            if (row.dataset.resourceRow !== group) return;
            row.hidden = !(String(row.dataset.resourceText || '').includes(query)
                && (!category || String(row.dataset.resourceCategory || '') === category));
        });
    };
    document.addEventListener('input', (event) => {
        const toolbar = event.target.closest?.('[data-resource-filter]');
        if (toolbar) applyResourceFilter(toolbar);
    });
    document.addEventListener('change', (event) => {
        const toolbar = event.target.closest?.('[data-resource-filter]');
        if (toolbar) applyResourceFilter(toolbar);
    });

    /*
    |--------------------------------------------------------------------------
    | Modal Elements
    |--------------------------------------------------------------------------
    */

    const assignmentModal =
        document.getElementById('assignmentModal');

    const viewAssignmentModal =
        document.getElementById('viewAssignmentModal');

    const removeAssignmentModal =
        document.getElementById('removeAssignmentModal');


    /*
    |--------------------------------------------------------------------------
    | Assignment Form Elements
    |--------------------------------------------------------------------------
    */

    const assignmentForm =
        document.getElementById('assignmentForm');

    const assignmentFormMethod =
        document.getElementById('assignmentFormMethod');

    const assignmentModalTitle =
        document.getElementById('assignmentModalTitle');

    const assignmentSubmitText =
        document.getElementById('assignmentSubmitText');

    const assignmentTrip =
        document.getElementById('assignmentTrip');

    const assignmentDriver =
        document.getElementById('assignmentDriver');

    const assignmentBus =
        document.getElementById('assignmentBus');

    const assignmentEditId =
        document.getElementById('assignmentEditId');

    const assignmentRestoreState =
        document.getElementById('assignmentRestoreState');


    /*
    |--------------------------------------------------------------------------
    | Driver Combobox Elements
    |--------------------------------------------------------------------------
    */

    const assignmentDriverCombobox =
        document.getElementById(
            'assignmentDriverCombobox'
        );

    const assignmentDriverTrigger =
        document.getElementById(
            'assignmentDriverTrigger'
        );

    const assignmentDriverMenu =
        document.getElementById(
            'assignmentDriverMenu'
        );

    const assignmentDriverLabel =
        document.getElementById(
            'assignmentDriverLabel'
        );

    const assignmentDriverSearch =
        document.getElementById(
            'assignmentDriverSearch'
        );

    const assignmentDriverOptionsContainer =
        document.getElementById('assignmentDriverOptions');

    let assignmentDriverOptions = [];


    /*
    |--------------------------------------------------------------------------
    | Bus Combobox Elements
    |--------------------------------------------------------------------------
    */

    const assignmentBusCombobox =
        document.getElementById(
            'assignmentBusCombobox'
        );

    const assignmentBusTrigger =
        document.getElementById(
            'assignmentBusTrigger'
        );

    const assignmentBusMenu =
        document.getElementById(
            'assignmentBusMenu'
        );

    const assignmentBusLabel =
        document.getElementById(
            'assignmentBusLabel'
        );

    const assignmentBusSearch =
        document.getElementById(
            'assignmentBusSearch'
        );

    const assignmentBusOptionsContainer =
        document.getElementById('assignmentBusOptions');

    let assignmentBusOptions = [];
    let availabilityController = null;


    /*
    |--------------------------------------------------------------------------
    | View Assignment Elements
    |--------------------------------------------------------------------------
    */

    const viewAssignmentContent =
        document.getElementById(
            'viewAssignmentContent'
        );

    const closeViewAssignmentModal =
        document.getElementById(
            'closeViewAssignmentModal'
        );

    const closeViewAssignmentButton =
        document.getElementById(
            'closeViewAssignmentButton'
        );


    /*
    |--------------------------------------------------------------------------
    | Remove Assignment Elements
    |--------------------------------------------------------------------------
    */

    const removeAssignmentName =
        document.getElementById(
            'removeAssignmentName'
        );

    const cancelRemoveAssignment =
        document.getElementById(
            'cancelRemoveAssignment'
        );

    const confirmRemoveAssignment =
        document.getElementById(
            'confirmRemoveAssignment'
        );

    let selectedRemoveForm = null;


    /*
    |--------------------------------------------------------------------------
    | Other Elements
    |--------------------------------------------------------------------------
    */

    const closeAssignmentModal =
        document.getElementById(
            'closeAssignmentModal'
        );

    const cancelAssignmentModal =
        document.getElementById(
            'cancelAssignmentModal'
        );

    const createAction =
        '/operation/driver-bus-assignment';


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
                'Unable to parse assignment URL.',
                error
            );
        }

        const cleanedValue = rawValue
            .replace(/^https?:\/+/i, '')
            .replace(/^\/+/, '');

        const pathIndex =
            cleanedValue.indexOf(
                'operation/driver-bus-assignment'
            );

        if (pathIndex >= 0) {
            return `/${cleanedValue.slice(pathIndex)}`;
        }

        return fallback;
    }


    function setAvailabilityMessage(message) {
        const markup = `<p class="assignment-combobox-empty">${escapeHtml(message)}</p>`;

        if (assignmentDriverOptionsContainer) {
            assignmentDriverOptionsContainer.innerHTML = markup;
        }

        if (assignmentBusOptionsContainer) {
            assignmentBusOptionsContainer.innerHTML = markup;
        }

        assignmentDriverOptions = [];
        assignmentBusOptions = [];
    }


    function renderAvailability(data) {
        const drivers = Array.isArray(data?.drivers) ? data.drivers : [];
        const buses = Array.isArray(data?.buses) ? data.buses : [];

        if (assignmentDriverOptionsContainer) {
            assignmentDriverOptionsContainer.innerHTML = drivers.length
                ? drivers.map((driver) => {
                    const label = driver.name;
                    const search = `${driver.driver_id} ${driver.name} ${driver.status}`.toLowerCase();
                    const authorizedBuses = Array.isArray(driver.authorized_bus_ids)
                        ? driver.authorized_bus_ids.join(',')
                        : '';

                    return `<button type="button" class="assignment-combobox-option assignment-driver-option" data-value="${escapeHtml(driver.id)}" data-label="${escapeHtml(label)}" data-authorized-buses="${escapeHtml(authorizedBuses)}" data-search="${escapeHtml(search)}"><span><strong>${escapeHtml(driver.name)}</strong><small>${escapeHtml(driver.driver_id)} — ${escapeHtml(driver.status)}</small></span><i class="fa-solid fa-check"></i></button>`;
                }).join('')
                : '<p class="assignment-combobox-empty">No eligible drivers are available for this trip.</p>';
        }

        if (assignmentBusOptionsContainer) {
            assignmentBusOptionsContainer.innerHTML = buses.length
                ? buses.map((bus) => {
                    const busIdentifier = bus.plate_no || 'Plate not recorded';
                    const label = bus.model ? `${busIdentifier} — ${bus.model}` : busIdentifier;
                    const search = `${bus.bus_no} ${bus.model || ''} ${bus.plate_no || ''}`.toLowerCase();

                    return `<button type="button" class="assignment-combobox-option assignment-bus-option" data-value="${escapeHtml(bus.id)}" data-label="${escapeHtml(label)}" data-search="${escapeHtml(search)}"><span><strong>${escapeHtml(busIdentifier)}</strong><small>${escapeHtml(bus.model || 'Operational bus')}</small></span><i class="fa-solid fa-check"></i></button>`;
                }).join('')
                : '<p class="assignment-combobox-empty">No active buses are available for this trip.</p>';
        }

        assignmentDriverOptions = Array.from(
            assignmentDriverOptionsContainer?.querySelectorAll('.assignment-driver-option') || []
        );
        assignmentBusOptions = Array.from(
            assignmentBusOptionsContainer?.querySelectorAll('.assignment-bus-option') || []
        );
    }


    function ensureTripOption(tripId, label, availabilityUrl) {
        if (!assignmentTrip || !tripId) return null;

        let option = assignmentTrip.querySelector(
            `option[value="${CSS.escape(String(tripId))}"]`
        );

        if (!option) {
            option = document.createElement('option');
            option.value = tripId;
            option.textContent = label || `Trip ${tripId}`;
            option.dataset.editOnly = 'true';
            assignmentTrip.appendChild(option);
        }

        if (availabilityUrl) {
            option.dataset.availabilityUrl = availabilityUrl;
        }

        return option;
    }


    async function loadAvailability(tripId, explicitUrl = '') {
        const pendingSlotInput = document.getElementById('assignmentPendingSlot');
        const lockedTripInput = document.getElementById('assignmentLockedTrip');
        const pendingContext = document.getElementById('assignmentPendingSlotContext');
        if (pendingSlotInput) { pendingSlotInput.disabled = true; pendingSlotInput.value = ''; }
        if (lockedTripInput) { lockedTripInput.disabled = true; lockedTripInput.value = ''; }
        if (pendingContext) { pendingContext.hidden = true; pendingContext.textContent = ''; }

        resetDriverSelection();
        resetBusSelection();
        availabilityController?.abort();

        const option = assignmentTrip?.querySelector(
            `option[value="${CSS.escape(String(tripId || ''))}"]`
        );
        const url = explicitUrl || option?.dataset.availabilityUrl;

        if (!tripId || !url) {
            setAvailabilityMessage('Select a trip to load available resources.');
            return false;
        }

        const controller = new AbortController();
        availabilityController = controller;
        setAvailabilityMessage('Loading available resources…');

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
                signal: controller.signal,
            });

            const data = await response.json();

            if (!response.ok) {
                const message = Object.values(data?.errors || {})
                    .flat()
                    .find(Boolean) || 'Unable to load available resources.';
                throw new Error(message);
            }

            if (availabilityController !== controller) {
                return false;
            }

            renderAvailability(data);
            return true;
        } catch (error) {
            if (error?.name === 'AbortError') {
                return false;
            }

            setAvailabilityMessage(error?.message || 'Unable to load available resources.');
            return false;
        } finally {
            if (availabilityController === controller) {
                availabilityController = null;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Driver Dropdown
    |--------------------------------------------------------------------------
    */

    function openDriverDropdown() {
        if (
            !assignmentDriverMenu
            || !assignmentDriverTrigger
        ) {
            return;
        }

        closeBusDropdown();

        assignmentDriverMenu.classList.add(
            'show'
        );

        assignmentDriverTrigger.setAttribute(
            'aria-expanded',
            'true'
        );

        window.setTimeout(() => {
            assignmentDriverSearch?.focus();
        }, 50);
    }


    function closeDriverDropdown() {
        if (
            !assignmentDriverMenu
            || !assignmentDriverTrigger
        ) {
            return;
        }

        assignmentDriverMenu.classList.remove(
            'show'
        );

        assignmentDriverTrigger.setAttribute(
            'aria-expanded',
            'false'
        );
    }


    function filterDriverOptions(searchValue) {
        const normalizedSearch =
            String(searchValue || '')
                .trim()
                .toLowerCase();

        assignmentDriverOptions.forEach(
            (option) => {
                const searchableText =
                    option.dataset.search || '';

                const shouldShow =
                    !normalizedSearch
                    || searchableText.includes(
                        normalizedSearch
                    );

                option.hidden = !shouldShow;
            }
        );
    }


    function resetDriverSelection() {
        if (assignmentDriver) {
            assignmentDriver.value = '';
        }

        if (assignmentDriverLabel) {
            assignmentDriverLabel.textContent =
                'Select available driver';

            assignmentDriverLabel.classList.add(
                'placeholder'
            );
        }

        assignmentDriverOptions.forEach(
            (option) => {
                option.classList.remove(
                    'selected'
                );
            }
        );

        if (assignmentDriverSearch) {
            assignmentDriverSearch.value = '';
        }

        filterDriverOptions('');
        closeDriverDropdown();
    }


    function selectDriver(value, label) {
        if (assignmentDriver) {
            assignmentDriver.value = value;
        }

        if (assignmentDriverLabel) {
            assignmentDriverLabel.textContent =
                label;

            assignmentDriverLabel.classList.remove(
                'placeholder'
            );
        }

        assignmentDriverOptions.forEach(
            (option) => {
                const isSelected =
                    String(option.dataset.value)
                    === String(value);

                option.classList.toggle(
                    'selected',
                    isSelected
                );
            }
        );

        const allowed = assignmentDriverOptions.find(
            (option) => String(option.dataset.value) === String(value)
        )?.dataset.authorizedBuses?.split(',').filter(Boolean) || [];
        if (assignmentBus?.value && !allowed.includes(String(assignmentBus.value))) {
            resetBusSelection();
        }
        filterBusOptions(assignmentBusSearch?.value || '');
        closeDriverDropdown();
    }


    function selectDriverById(driverId) {
        const selectedOption =
            Array.from(
                assignmentDriverOptions
            ).find((option) => {
                return (
                    String(option.dataset.value)
                    === String(driverId)
                );
            });

        if (!selectedOption) {
            resetDriverSelection();
            return;
        }

        selectDriver(
            selectedOption.dataset.value,
            selectedOption.dataset.label
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Bus Dropdown
    |--------------------------------------------------------------------------
    */

    function openBusDropdown() {
        if (
            !assignmentBusMenu
            || !assignmentBusTrigger
        ) {
            return;
        }

        closeDriverDropdown();

        assignmentBusMenu.classList.add(
            'show'
        );

        assignmentBusTrigger.setAttribute(
            'aria-expanded',
            'true'
        );

        window.setTimeout(() => {
            assignmentBusSearch?.focus();
        }, 50);
    }


    function closeBusDropdown() {
        if (
            !assignmentBusMenu
            || !assignmentBusTrigger
        ) {
            return;
        }

        assignmentBusMenu.classList.remove(
            'show'
        );

        assignmentBusTrigger.setAttribute(
            'aria-expanded',
            'false'
        );
    }


    function filterBusOptions(searchValue) {
        const normalizedSearch =
            String(searchValue || '')
                .trim()
                .toLowerCase();

        assignmentBusOptions.forEach(
            (option) => {
                const searchableText =
                    option.dataset.search || '';

                const shouldShow =
                    !normalizedSearch
                    || searchableText.includes(
                        normalizedSearch
                    );

                const selectedDriver = assignmentDriverOptions.find(
                    (driverOption) => String(driverOption.dataset.value) === String(assignmentDriver?.value || '')
                );
                const allowed = selectedDriver
                    ? (selectedDriver.dataset.authorizedBuses || '').split(',').filter(Boolean)
                    : null;
                const busAuthorized = !allowed || allowed.includes(String(option.dataset.value));

                option.hidden = !shouldShow || !busAuthorized;
            }
        );
    }


    function resetBusSelection() {
        if (assignmentBus) {
            assignmentBus.value = '';
        }

        if (assignmentBusLabel) {
            assignmentBusLabel.textContent =
                'Select available bus';

            assignmentBusLabel.classList.add(
                'placeholder'
            );
        }

        assignmentBusOptions.forEach(
            (option) => {
                option.classList.remove(
                    'selected'
                );
            }
        );

        if (assignmentBusSearch) {
            assignmentBusSearch.value = '';
        }

        filterBusOptions('');
        closeBusDropdown();
    }


    function selectBus(value, label) {
        if (assignmentBus) {
            assignmentBus.value = value;
        }

        if (assignmentBusLabel) {
            assignmentBusLabel.textContent =
                label;

            assignmentBusLabel.classList.remove(
                'placeholder'
            );
        }

        assignmentBusOptions.forEach(
            (option) => {
                const isSelected =
                    String(option.dataset.value)
                    === String(value);

                option.classList.toggle(
                    'selected',
                    isSelected
                );
            }
        );

        closeBusDropdown();
    }


    function selectBusById(busId) {
        const selectedOption =
            Array.from(
                assignmentBusOptions
            ).find((option) => {
                return (
                    String(option.dataset.value)
                    === String(busId)
                );
            });

        if (!selectedOption) {
            resetBusSelection();
            return;
        }

        selectBus(
            selectedOption.dataset.value,
            selectedOption.dataset.label
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Reset Form
    |--------------------------------------------------------------------------
    */

    function resetAssignmentForm() {
        if (!assignmentForm) {
            return;
        }

        assignmentForm.reset();
        assignmentTrip?.querySelectorAll('option[data-edit-only="true"]')
            .forEach((option) => option.remove());

        assignmentForm.setAttribute(
            'action',
            createAction
        );

        if (assignmentFormMethod) {
            assignmentFormMethod.disabled = true;
        }

        if (assignmentEditId) {
            assignmentEditId.value = '';
        }

        if (assignmentTrip) {
            assignmentTrip.disabled = false;
            assignmentTrip.required = true;
        }

        resetDriverSelection();
        resetBusSelection();
        setAvailabilityMessage('Select a trip to load available resources.');

        if (assignmentModalTitle) {
            assignmentModalTitle.textContent =
                'Driver & Bus Assignment';
        }

        if (assignmentSubmitText) {
            assignmentSubmitText.textContent =
                'Confirm Assignment';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | New Assignment
    |--------------------------------------------------------------------------
    */

    document.addEventListener('click', async (event) => {
        const newButton = event.target.closest('#openAssignmentModal');

        if (newButton && !newButton.disabled) {
            resetAssignmentForm();
            openModal(assignmentModal);
            return;
        }

        const assignButton = event.target.closest('.open-assignment');

        if (assignButton && !assignButton.disabled) {
            resetAssignmentForm();

            const tripId = assignButton.dataset.tripId || '';
            const slotNumber = assignButton.dataset.slotNumber || '';
            if (assignmentTrip) {
                assignmentTrip.value = tripId;
            }
            const pendingSlotInput = document.getElementById('assignmentPendingSlot');
            const lockedTripInput = document.getElementById('assignmentLockedTrip');
            const pendingContext = document.getElementById('assignmentPendingSlotContext');
            if (slotNumber) {
                if (pendingSlotInput) { pendingSlotInput.value = slotNumber; pendingSlotInput.disabled = false; }
                if (lockedTripInput) { lockedTripInput.value = tripId; lockedTripInput.disabled = false; }
                if (assignmentTrip) { assignmentTrip.disabled = true; assignmentTrip.required = false; }
                if (pendingContext) {
                    pendingContext.textContent = (assignButton.dataset.tripLabel || 'Trip') + ' — Slot ' + slotNumber + ' awaiting assignment';
                    pendingContext.hidden = false;
                }
                if (assignmentModalTitle) assignmentModalTitle.textContent = 'Assign Pending Slot';
                if (assignmentSubmitText) assignmentSubmitText.textContent = 'Confirm Assignment';
            }

            // Open immediately: loading availability must never block the modal.
            openModal(assignmentModal);
            try {
                await loadAvailability(tripId, assignButton.dataset.availabilityUrl || '');
            } catch (error) {
                setAvailabilityMessage('Unable to load available resources. Please try again.');
                console.error('Assignment availability load failed:', error);
            }
            return;
        }

        const editButton = event.target.closest('.edit-assignment');

        if (editButton && !editButton.disabled) {
            if (!assignmentForm) return;

            resetAssignmentForm();

            const assignmentId = editButton.dataset.assignmentId;

            ensureTripOption(
                editButton.dataset.tripId,
                editButton.dataset.tripLabel,
                editButton.dataset.availabilityUrl
            );

            if (assignmentEditId) {
                assignmentEditId.value = assignmentId || '';
            }

            const fallbackUrl =
                `/operation/driver-bus-assignment/${assignmentId}`;

            assignmentForm.setAttribute(
                'action',
                normalizePath(editButton.dataset.updateUrl, fallbackUrl)
            );

            if (assignmentFormMethod) {
                assignmentFormMethod.disabled = false;
                assignmentFormMethod.value = 'PUT';
            }

            if (assignmentTrip) {
                assignmentTrip.value = editButton.dataset.tripId || '';
                assignmentTrip.disabled = true;
                assignmentTrip.required = false;
            }

            await loadAvailability(
                editButton.dataset.tripId || '',
                editButton.dataset.availabilityUrl || ''
            );

            selectDriverById(editButton.dataset.driverId || '');
            selectBusById(editButton.dataset.busId || '');

            if (assignmentModalTitle) {
                assignmentModalTitle.textContent = 'Edit Assignment';
            }

            if (assignmentSubmitText) {
                assignmentSubmitText.textContent = 'Update Assignment';
            }

            openModal(assignmentModal);
            return;
        }

        const viewButton = event.target.closest('.view-assignment');

        if (viewButton && !viewButton.disabled) {
            renderAssignmentDetails(
                parseAssignmentDetails(viewButton.dataset.details)
            );
            openModal(viewAssignmentModal);
            return;
        }

        const removeButton = event.target.closest('.remove-assignment');

        if (removeButton && !removeButton.disabled) {
            selectedRemoveForm = document.getElementById(
                removeButton.dataset.formId
            );

            if (removeAssignmentName) {
                removeAssignmentName.textContent =
                    removeButton.dataset.tripCode || 'this trip';
            }

            openModal(removeAssignmentModal);
        }
    });


    /*
    |--------------------------------------------------------------------------
    | Close Assignment Modal
    |--------------------------------------------------------------------------
    */

    [
        closeAssignmentModal,
        cancelAssignmentModal,
    ]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeDriverDropdown();
                    closeBusDropdown();
                    closeModal(assignmentModal);
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Driver Dropdown Events
    |--------------------------------------------------------------------------
    */

    assignmentDriverTrigger?.addEventListener(
        'click',
        () => {
            const isOpen =
                assignmentDriverMenu
                    ?.classList
                    .contains('show');

            if (isOpen) {
                closeDriverDropdown();
            } else {
                openDriverDropdown();
            }
        }
    );


    assignmentDriverSearch?.addEventListener(
        'input',
        () => {
            filterDriverOptions(
                assignmentDriverSearch.value
            );
        }
    );


    assignmentDriverOptionsContainer?.addEventListener('click', (event) => {
        const option = event.target.closest('.assignment-driver-option');
        if (!option) return;

        selectDriver(option.dataset.value, option.dataset.label);
    });


    /*
    |--------------------------------------------------------------------------
    | Bus Dropdown Events
    |--------------------------------------------------------------------------
    */

    assignmentBusTrigger?.addEventListener(
        'click',
        () => {
            const isOpen =
                assignmentBusMenu
                    ?.classList
                    .contains('show');

            if (isOpen) {
                closeBusDropdown();
            } else {
                openBusDropdown();
            }
        }
    );


    assignmentBusSearch?.addEventListener(
        'input',
        () => {
            filterBusOptions(
                assignmentBusSearch.value
            );
        }
    );


    assignmentBusOptionsContainer?.addEventListener('click', (event) => {
        const option = event.target.closest('.assignment-bus-option');
        if (!option) return;

        selectBus(option.dataset.value, option.dataset.label);
    });

    assignmentTrip?.addEventListener('change', () => {
        void loadAvailability(assignmentTrip.value);
    });


    /*
    |--------------------------------------------------------------------------
    | Close Dropdowns When Clicking Outside
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        (event) => {
            if (
                assignmentDriverCombobox
                && !assignmentDriverCombobox.contains(
                    event.target
                )
            ) {
                closeDriverDropdown();
            }

            if (
                assignmentBusCombobox
                && !assignmentBusCombobox.contains(
                    event.target
                )
            ) {
                closeBusDropdown();
            }
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Edit Assignment
    |--------------------------------------------------------------------------
    */

    assignmentForm?.addEventListener('submit', () => {
        if (assignmentTrip) {
            assignmentTrip.disabled = false;
        }
    });

    if (assignmentRestoreState?.dataset.hasErrors === 'true') {
        const tripId = assignmentRestoreState.dataset.tripId || '';
        const editId = assignmentEditId?.value || '';
        const editButton = editId
            ? document.querySelector(`.edit-assignment[data-assignment-id="${CSS.escape(editId)}"]`)
            : null;

        if (editButton) {
            editButton.click();
        } else if (tripId) {
            assignmentTrip.value = tripId;
            void loadAvailability(tripId).then(() => {
                selectDriverById(assignmentRestoreState.dataset.driverId || '');
                selectBusById(assignmentRestoreState.dataset.busId || '');
                openModal(assignmentModal);
            });
        } else {
            openModal(assignmentModal);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | View Assignment
    |--------------------------------------------------------------------------
    */

    function parseAssignmentDetails(rawData) {
        if (!rawData) {
            return {};
        }

        try {
            return JSON.parse(rawData);
        } catch (error) {
            console.error(
                'Unable to read assignment details.',
                error
            );

            return {};
        }
    }


    function renderAssignmentDetails(details) {
        if (!viewAssignmentContent) {
            return;
        }

        const fields = [
            {
                label: 'Trip ID',
                value: details.tripCode,
            },
            {
                label: 'Date',
                value: details.date,
            },
            {
                label: 'Route',
                value: details.route,
            },
            {
                label: 'Trip Status',
                value: details.status,
            },
            {
                label: 'Departure',
                value: details.departure,
            },
            {
                label: 'Estimated Arrival',
                value: details.arrival,
            },
            {
                label: 'Driver',
                value:
                    details.driver
                    || 'Not Assigned',
            },
            {
                label: 'Driver Attendance',
                value:
                    details.driverStatus
                    || '—',
            },
            {
                label: 'Bus',
                value:
                    details.bus
                    || 'Not Assigned',
            },
            {
                label: 'Assignment',
                value:
                    details.assignmentStatus,
            },
        ];

        viewAssignmentContent.innerHTML =
            fields
                .map((field) => {
                    return `
                        <div class="assignment-detail-card">
                            <label>
                                ${escapeHtml(field.label)}
                            </label>

                            <div class="assignment-detail-value">
                                ${escapeHtml(field.value || '—')}
                            </div>
                        </div>
                    `;
                })
                .join('');
    }


    /*
    |--------------------------------------------------------------------------
    | Close View Modal
    |--------------------------------------------------------------------------
    */

    [
        closeViewAssignmentModal,
        closeViewAssignmentButton,
    ]
        .filter(Boolean)
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    closeModal(
                        viewAssignmentModal
                    );
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | Remove Assignment
    |--------------------------------------------------------------------------
    */

    if (cancelRemoveAssignment) {
        cancelRemoveAssignment.addEventListener(
            'click',
            () => {
                selectedRemoveForm = null;

                closeModal(
                    removeAssignmentModal
                );
            }
        );
    }


    if (confirmRemoveAssignment) {
        confirmRemoveAssignment.addEventListener(
            'click',
            () => {
                if (!selectedRemoveForm) {
                    return;
                }

                selectedRemoveForm
                    .requestSubmit();
            }
        );
    }


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

            closeDriverDropdown();
            closeBusDropdown();

            closeModal(assignmentModal);
            closeModal(viewAssignmentModal);
            closeModal(removeAssignmentModal);
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

    const applySource = () => {
        const form = document.getElementById('assignmentForm');
        const source = document.getElementById('assignmentSource');
        const pending = document.getElementById('assignmentPendingSlot');
        const company = document.getElementById('companyAssignmentFields');
        const rental = document.getElementById('externalRentalFields');
        if (!form || !source || !pending || !company || !rental) return;
        const selector = document.getElementById('assignmentSourceSelector');
        const isRental = Boolean(selector && !selector.hidden && source.value === 'rental');
        document.querySelectorAll('[data-assignment-source]').forEach((button) => {
            const selected = button.dataset.assignmentSource === source.value;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
        company.hidden = isRental;
        company.style.display = isRental ? 'none' : '';
        rental.hidden = !isRental;
        rental.style.display = isRental ? 'block' : 'none';
        const validation = form.querySelector('.assignment-validation');
        if (validation) validation.hidden = isRental;
        for (const field of rental.querySelectorAll('input,textarea,select')) {
            field.disabled = !isRental;
        }
        const confirmed = rental.querySelector('[name="rental_status"]')?.value === 'Confirmed';
        for (const name of ['company_name', 'plate_number', 'driver_name']) {
            const field = rental.querySelector('[name="' + name + '"]');
            if (field) field.required = isRental && (name === 'company_name' || confirmed);
        }
        for (const name of ['driver_attendance_id', 'bus_id']) {
            const field = form.elements.namedItem(name);
            if (field) { field.disabled = isRental; field.required = !isRental; }
        }
        if (isRental) {
            if (!form.dataset.companyAction) form.dataset.companyAction = form.action;
            form.action = "/operation/external-bus-rentals";
            const method = document.getElementById('assignmentFormMethod');
            if (method) method.disabled = true;
            const trip = document.getElementById('assignmentLockedTrip');
            if (trip) trip.disabled = false;
            const submit = document.getElementById('assignmentSubmitText');
            if (submit) submit.textContent = 'Save Rental';
        } else {
            if (form.dataset.companyAction) form.action = form.dataset.companyAction;
            const submit = document.getElementById('assignmentSubmitText');
            if (submit && !pending.disabled) submit.textContent = 'Confirm Assignment';
        }
    };
    document.addEventListener('click', (event) => {
        const button = event.target.closest('.open-assignment');
        const selector = document.getElementById('assignmentSourceSelector');
        const source = document.getElementById('assignmentSource');
        const form = document.getElementById('assignmentForm');
        if (button && selector && source && form) {
            form.dataset.companyAction = form.action;
            selector.hidden = false;
            source.value = 'company';
            const rental = document.getElementById('externalRentalFields');
            if (rental) {
                const values = {
                    company_name: button.dataset.rentalCompany || '',
                    contact_details: button.dataset.rentalContact || '',
                    plate_number: button.dataset.rentalPlate || '',
                    driver_name: button.dataset.rentalDriver || '',
                    notes: button.dataset.rentalNotes || '',
                    rental_status: button.dataset.rentalStatus || 'Pending',
                };
                for (const [name,value] of Object.entries(values)) {
                    const field = rental.querySelector('[name="' + name + '"]');
                    if (field) field.value = value;
                }
            }
            applySource();
        }
        if (event.target.closest('#openAssignmentModal, .edit-assignment')) {
            if (form) form.dataset.companyAction = form.action;
            if (selector) selector.hidden = true;
            if (source) source.value = 'company';
            applySource();
        }
    });
    // Capture card clicks before generic modal handlers; cards are not form submits.
    assignmentModal?.addEventListener('click', (event) => {
        const choice = event.target.closest('[data-assignment-source]');
        if (!choice) return;
        event.preventDefault();
        event.stopPropagation();
        const source = document.getElementById('assignmentSource');
        if (!source) return;
        source.value = choice.dataset.assignmentSource;
        applySource();
    }, true);
    assignmentModal?.addEventListener('change', (event) => {
        if (event.target?.id === 'assignmentSource' || event.target?.id === 'externalRentalStatus') {
            applySource();
        }
    });
});

if (!window.__gctAssignmentModalRefreshBound) {
    window.__gctAssignmentModalRefreshBound = true;

    window.addEventListener('system-regions-refreshed', (event) => {
        const regions = Array.isArray(event.detail?.regions)
            ? event.detail.regions
            : [];

        if (
            document.querySelector('.assignment-page')
            && regions.includes('assignment-modal')
        ) {
            window.dispatchEvent(new CustomEvent('gct:navigation-ready', {
                detail: { source: 'realtime-assignment-modal' },
            }));
        }
    });
}


/* Print the existing authenticated assignment sheet without a modal, iframe, or navigation. */
(() => {
    if (window.__gctAssignmentDirectPrintBound) return;
    window.__gctAssignmentDirectPrintBound = true;

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-direct-assignment-print]');
        if (!button) return;
        event.preventDefault();
        if (button.disabled || !button.dataset.printUrl) return;

        button.disabled = true;
        let sheet = null;
        try {
            const response = await fetch(button.dataset.printUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'text/html' },
            });
            if (!response.ok) throw new Error('Assignment sheet request failed');
            const parsed = new DOMParser().parseFromString(await response.text(), 'text/html');
            const sections = ['header', '.meta', 'table', '.signatures'];
            if (sections.some((selector) => !parsed.body.querySelector(selector))) {
                throw new Error('The printable assignment sheet is unavailable');
            }
            document.getElementById('assignmentPrintDocument')?.remove();
            sheet = document.createElement('section');
            sheet.id = 'assignmentPrintDocument';
            sheet.setAttribute('aria-label', 'Printable Driver and Bus Assignment Sheet');
            for (const selector of sections) {
                sheet.appendChild(document.importNode(parsed.body.querySelector(selector), true));
            }
            document.body.appendChild(sheet);
            document.body.classList.add('printing-assignment-sheet');
            // Print the current document: no extra browser page or iframe is created.
            window.print();
        } catch (error) {
            window.alert('Unable to print the assignment sheet. Please try again.');
        } finally {
            document.body.classList.remove('printing-assignment-sheet');
            sheet?.remove();
            button.disabled = false;
        }
    });
})();
