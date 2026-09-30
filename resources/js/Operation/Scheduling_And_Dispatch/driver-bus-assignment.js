document.addEventListener('DOMContentLoaded', () => {
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

    const assignmentDriverOptions =
        document.querySelectorAll(
            '.assignment-driver-option'
        );


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

    const assignmentBusOptions =
        document.querySelectorAll(
            '.assignment-combobox-option'
        );


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

    const openAssignmentModal =
        document.getElementById(
            'openAssignmentModal'
        );

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

        assignmentForm.setAttribute(
            'action',
            createAction
        );

        if (assignmentFormMethod) {
            assignmentFormMethod.disabled = true;
        }

        if (assignmentTrip) {
            assignmentTrip.disabled = false;
            assignmentTrip.required = true;
        }

        resetDriverSelection();
        resetBusSelection();

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

    if (openAssignmentModal) {
        openAssignmentModal.addEventListener(
            'click',
            () => {
                resetAssignmentForm();
                openModal(assignmentModal);
            }
        );
    }


    document
        .querySelectorAll('.open-assignment')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    resetAssignmentForm();

                    if (assignmentTrip) {
                        assignmentTrip.value =
                            button.dataset.tripId
                            || '';
                    }

                    openModal(
                        assignmentModal
                    );
                }
            );
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


    assignmentDriverOptions.forEach(
        (option) => {
            option.addEventListener(
                'click',
                () => {
                    selectDriver(
                        option.dataset.value,
                        option.dataset.label
                    );
                }
            );
        }
    );


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


    assignmentBusOptions.forEach(
        (option) => {
            option.addEventListener(
                'click',
                () => {
                    selectBus(
                        option.dataset.value,
                        option.dataset.label
                    );
                }
            );
        }
    );


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

    document
        .querySelectorAll('.edit-assignment')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    if (!assignmentForm) {
                        return;
                    }

                    const assignmentId =
                        button.dataset.assignmentId;

                    const fallbackUrl =
                        `/operation/driver-bus-assignment/${assignmentId}`;

                    assignmentForm.setAttribute(
                        'action',
                        normalizePath(
                            button.dataset.updateUrl,
                            fallbackUrl
                        )
                    );

                    if (assignmentFormMethod) {
                        assignmentFormMethod.disabled =
                            false;

                        assignmentFormMethod.value =
                            'PUT';
                    }

                    if (assignmentTrip) {
                        assignmentTrip.value =
                            button.dataset.tripId
                            || '';

                        assignmentTrip.disabled =
                            true;

                        assignmentTrip.required =
                            false;
                    }

                    selectDriverById(
                        button.dataset.driverId
                        || ''
                    );

                    selectBusById(
                        button.dataset.busId
                        || ''
                    );

                    if (assignmentModalTitle) {
                        assignmentModalTitle.textContent =
                            'Edit Assignment';
                    }

                    if (assignmentSubmitText) {
                        assignmentSubmitText.textContent =
                            'Update Assignment';
                    }

                    openModal(
                        assignmentModal
                    );
                }
            );
        });


    /*
    |--------------------------------------------------------------------------
    | View Assignment
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.view-assignment')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const details =
                        parseAssignmentDetails(
                            button.dataset.details
                        );

                    renderAssignmentDetails(
                        details
                    );

                    openModal(
                        viewAssignmentModal
                    );
                }
            );
        });


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

    document
        .querySelectorAll('.remove-assignment')
        .forEach((button) => {
            button.addEventListener(
                'click',
                () => {
                    const formId =
                        button.dataset.formId;

                    selectedRemoveForm =
                        document.getElementById(
                            formId
                        );

                    if (removeAssignmentName) {
                        removeAssignmentName.textContent =
                            button.dataset.tripCode
                            || 'this trip';
                    }

                    openModal(
                        removeAssignmentModal
                    );
                }
            );
        });


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
                        closeDriverDropdown();
                        closeBusDropdown();
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