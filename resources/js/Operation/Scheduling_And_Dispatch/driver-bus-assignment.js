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
                    const label = `${driver.name} — ${driver.shift} Shift`;
                    const search = `${driver.driver_id} ${driver.name} ${driver.shift} ${driver.status}`.toLowerCase();

                    return `<button type="button" class="assignment-combobox-option assignment-driver-option" data-value="${escapeHtml(driver.id)}" data-label="${escapeHtml(label)}" data-search="${escapeHtml(search)}"><span><strong>${escapeHtml(driver.name)}</strong><small>${escapeHtml(driver.driver_id)} — ${escapeHtml(driver.shift)} Shift — ${escapeHtml(driver.status)}</small></span><i class="fa-solid fa-check"></i></button>`;
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

                option.hidden = !shouldShow;
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
        const resourceButton = event.target.closest('.resource-assign-driver, .resource-assign-bus');
        if (resourceButton && !resourceButton.disabled) {
            resetAssignmentForm();
            openModal(assignmentModal);
            assignmentTrip?.focus();
            // Trip selection controls which resources are eligible. Never
            // preselect a driver or bus without validating that trip first.
            return;
        }

        const newButton = event.target.closest('#openAssignmentModal');

        if (newButton && !newButton.disabled) {
            resetAssignmentForm();
            openModal(assignmentModal);
            return;
        }

        const assignButton = event.target.closest('.open-assignment');

        if (assignButton && !assignButton.disabled) {
            resetAssignmentForm();

            if (assignmentTrip) {
                assignmentTrip.value = assignButton.dataset.tripId || '';
            }

            await loadAvailability(assignButton.dataset.tripId || '');
            openModal(assignmentModal);
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
