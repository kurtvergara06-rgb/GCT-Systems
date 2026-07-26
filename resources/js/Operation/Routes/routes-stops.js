document.addEventListener('DOMContentLoaded', () => {

    /* =========================================================
       ELEMENTS
    ========================================================= */

    const routeModal =
        document.getElementById('routeModal');

    const routeDetailsModal =
        document.getElementById('routeDetailsModal');

    const openRouteModalBtn =
        document.getElementById('openRouteModal');

    const routeForm =
        document.getElementById('routeForm');

    const routeFormMethod =
        document.getElementById('routeFormMethod');

    const routeModalTitle =
        document.getElementById('routeModalTitle');

    const routeCode =
        document.getElementById('routeCode');

    const routeName =
        document.getElementById('routeName');

    const routeOrigin =
        document.getElementById('routeOrigin');

    const routeDestination =
        document.getElementById('routeDestination');

    const routeDistance =
        document.getElementById('routeDistance');

    const routeTime =
        document.getElementById('routeTime');

    const routeStatus =
        document.getElementById('routeStatus');

    const routeStopList =
        document.getElementById('routeStopList');

    const addRouteStop =
        document.getElementById('addRouteStop');

    const saveRouteText =
        document.getElementById('saveRouteText');

    const deleteRouteModal =
        document.getElementById('deleteRouteModal');

    const deleteRouteName =
        document.getElementById('deleteRouteName');

    const cancelDeleteRoute =
        document.getElementById('cancelDeleteRoute');

    const confirmDeleteRoute =
        document.getElementById('confirmDeleteRoute');

    let selectedDeleteForm = null;


    /* =========================================================
       MODAL HELPERS
    ========================================================= */

    function openModal(modal) {

        if (!modal) {
            return;
        }

        modal.classList.add('show', 'active');

        document.body.style.overflow = 'hidden';
    }


    function closeModal(modal) {

        if (!modal) {
            return;
        }

        modal.classList.remove('show', 'active');

        const openModalExists =
            document.querySelector(
                '.ui-form-overlay.show, ' +
                '.route-modal-overlay.show, ' +
                '.modal-overlay.show'
            );

        if (!openModalExists) {
            document.body.style.overflow = '';
        }
    }


    /* =========================================================
       STORE ORIGINAL ACTION
    ========================================================= */

    if (routeForm) {

        routeForm.dataset.storeUrl =
            routeForm.getAttribute('action');

    }


    /* =========================================================
       RESET CREATE FORM
    ========================================================= */

    function resetCreateForm() {

        if (!routeForm) {
            return;
        }

        routeForm.reset();


        if (routeFormMethod) {

            routeFormMethod.disabled = true;
            routeFormMethod.value = 'POST';

        }


        routeForm.action =
            routeForm.dataset.storeUrl ||
            routeForm.getAttribute('action');


        if (routeModalTitle) {
            routeModalTitle.textContent =
                'New Route';
        }


        if (saveRouteText) {
            saveRouteText.textContent =
                'Save Route';
        }


        if (routeStatus) {
            routeStatus.value =
                'Active';
        }


        resetStops();
    }


    /* =========================================================
       NEW ROUTE
    ========================================================= */

    openRouteModalBtn?.addEventListener(
        'click',
        () => {

            resetCreateForm();

            openModal(routeModal);

        }
    );


    /* =========================================================
       CLOSE ADD / EDIT
    ========================================================= */

    document
        .querySelectorAll('[data-close-route-modal]')
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    closeModal(routeModal);

                }
            );

        });


    /* =========================================================
       STOP ROW
       GLOBAL FORM REPEATER CLASSES
    ========================================================= */

    function createStopRow(value = '') {

        const item =
            document.createElement('div');


        /*
         * IMPORTANT:
         * These ui-form-* classes use the GLOBAL
         * form-components.css styling.
         */
        item.className =
            'ui-form-repeater-row route-stop-item';


        const number =
            document.createElement('div');

        number.className =
            'ui-form-repeater-number route-stop-number';


        const input =
            document.createElement('input');

        input.type = 'text';
        input.name = 'stops[]';
        input.value = value;
        input.placeholder = 'Enter stop name';


        const removeButton =
            document.createElement('button');

        removeButton.type = 'button';

        removeButton.className =
            'ui-form-repeater-remove remove-route-stop';

        removeButton.title =
            'Remove Stop';


        const trashIcon =
            document.createElement('i');

        trashIcon.className =
            'fa-solid fa-trash';


        removeButton.appendChild(
            trashIcon
        );


        item.appendChild(
            number
        );

        item.appendChild(
            input
        );

        item.appendChild(
            removeButton
        );


        return item;
    }


    /* =========================================================
       ADD STOP
    ========================================================= */

    function addStopInput(value = '') {

        if (!routeStopList) {
            return;
        }


        const item =
            createStopRow(value);


        routeStopList.appendChild(
            item
        );


        updateStopNumbers();
    }


    addRouteStop?.addEventListener(
        'click',
        () => {

            addStopInput();

        }
    );


    /* =========================================================
       RESET STOPS
    ========================================================= */

    function resetStops() {

        if (!routeStopList) {
            return;
        }


        routeStopList.innerHTML = '';


        addStopInput();
    }


    /* =========================================================
       POPULATE STOPS
    ========================================================= */

    function populateStops(stops = []) {

        if (!routeStopList) {
            return;
        }


        routeStopList.innerHTML = '';


        if (
            Array.isArray(stops) &&
            stops.length > 0
        ) {

            stops.forEach(stop => {

                addStopInput(stop);

            });

        } else {

            addStopInput();

        }


        updateStopNumbers();
    }


    /* =========================================================
       REMOVE STOP
    ========================================================= */

    routeStopList?.addEventListener(
        'click',
        event => {

            const removeButton =
                event.target.closest(
                    '.remove-route-stop'
                );


            if (!removeButton) {
                return;
            }


            const item =
                removeButton.closest(
                    '.route-stop-item'
                );


            if (!item) {
                return;
            }


            const rows =
                routeStopList.querySelectorAll(
                    '.route-stop-item'
                );


            /*
             * Always keep at least one stop field.
             */
            if (rows.length <= 1) {

                const input =
                    item.querySelector('input');

                if (input) {
                    input.value = '';
                }

                return;
            }


            item.remove();


            updateStopNumbers();

        }
    );


    /* =========================================================
       STOP NUMBERS
    ========================================================= */

    function updateStopNumbers() {

        if (!routeStopList) {
            return;
        }


        routeStopList
            .querySelectorAll(
                '.route-stop-item'
            )
            .forEach(
                (item, index) => {

                    const number =
                        item.querySelector(
                            '.route-stop-number'
                        );


                    if (number) {

                        number.textContent =
                            String(index + 1);

                    }

                }
            );
    }


    /* =========================================================
       GET ROUTE DATA
    ========================================================= */

    function getRouteData(button) {

        let stops = [];


        try {

            stops =
                JSON.parse(
                    button.dataset.stops ||
                    '[]'
                );

        } catch (error) {

            console.error(
                'Invalid route stops data.',
                error
            );

        }


        return {

            id:
                button.dataset.id || '',

            routeCode:
                button.dataset.routeCode || '',

            routeName:
                button.dataset.routeName || '',

            origin:
                button.dataset.origin || '',

            destination:
                button.dataset.destination || '',

            distance:
                button.dataset.distance || '',

            time:
                button.dataset.time || '',

            status:
                button.dataset.status || 'Active',

            updateUrl:
                button.dataset.updateUrl || '',

            stops:
                Array.isArray(stops)
                    ? stops
                    : [],

        };
    }


    /* =========================================================
       EDIT ROUTE
    ========================================================= */

    document.addEventListener(
        'click',
        event => {

            const editButton =
                event.target.closest(
                    '.edit-route-btn'
                );


            if (!editButton) {
                return;
            }


            const route =
                getRouteData(
                    editButton
                );


            if (routeModalTitle) {

                routeModalTitle.textContent =
                    'Edit Route';

            }


            if (saveRouteText) {

                saveRouteText.textContent =
                    'Update Route';

            }


            if (routeCode) {

                routeCode.value =
                    route.routeCode;

            }


            if (routeName) {

                routeName.value =
                    route.routeName;

            }


            if (routeOrigin) {

                routeOrigin.value =
                    route.origin;

            }


            if (routeDestination) {

                routeDestination.value =
                    route.destination;

            }


            if (routeDistance) {

                routeDistance.value =
                    route.distance;

            }


            if (routeTime) {

                routeTime.value =
                    route.time;

            }


            if (routeStatus) {

                routeStatus.value =
                    route.status;

            }


            /*
             * Laravel PUT request.
             */
            if (routeFormMethod) {

                routeFormMethod.disabled =
                    false;

                routeFormMethod.value =
                    'PUT';

            }


            if (
                routeForm &&
                route.updateUrl
            ) {

                routeForm.action =
                    route.updateUrl;

            }


            /*
             * Load stops using GLOBAL repeater.
             */
            populateStops(
                route.stops
            );


            openModal(
                routeModal
            );

        }
    );


    /* =========================================================
       VIEW ROUTE
    ========================================================= */

    document.addEventListener(
        'click',
        event => {

            const viewButton =
                event.target.closest(
                    '.open-route-details'
                );


            if (!viewButton) {
                return;
            }


            const route =
                getRouteData(
                    viewButton
                );


            setText(
                'viewRouteCode',
                route.routeCode || '—'
            );


            setText(
                'viewRouteName',
                route.routeName || '—'
            );


            setText(
                'viewRouteOrigin',
                route.origin || '—'
            );


            setText(
                'viewRouteDestination',
                route.destination || '—'
            );


            setText(
                'viewRouteDistance',

                route.distance
                    ? `${formatNumber(route.distance)} KM`
                    : '—'
            );


            setText(
                'viewRouteTime',

                route.time
                    ? `${route.time} minutes`
                    : '—'
            );


            renderViewStatus(
                route.status
            );


            renderRoutePath(
                route.origin,
                route.stops,
                route.destination
            );


            openModal(
                routeDetailsModal
            );

        }
    );


    /* =========================================================
       SET TEXT
    ========================================================= */

    function setText(id, value) {

        const element =
            document.getElementById(id);


        if (element) {

            element.textContent =
                value;

        }
    }


    /* =========================================================
       FORMAT NUMBER
    ========================================================= */

    function formatNumber(value) {

        const number =
            Number(value);


        if (!Number.isFinite(number)) {

            return value;

        }


        return number.toLocaleString(
            undefined,
            {
                minimumFractionDigits: 0,
                maximumFractionDigits: 1,
            }
        );
    }


    /* =========================================================
       VIEW STATUS
    ========================================================= */

    function renderViewStatus(status) {

        const wrapper =
            document.getElementById(
                'viewRouteStatus'
            );


        if (!wrapper) {
            return;
        }


        const statusClass =
            String(status)
                .trim()
                .toLowerCase()
                .replaceAll(' ', '-');


        wrapper.innerHTML = '';


        const badge =
            document.createElement('span');


        badge.className =
            `badge route-badge route-status ${statusClass}`;


        badge.textContent =
            status || 'Unknown';


        wrapper.appendChild(
            badge
        );
    }


    /* =========================================================
       ROUTE PATH
    ========================================================= */

    function renderRoutePath(
        origin,
        stops,
        destination
    ) {

        const path =
            document.getElementById(
                'viewRoutePath'
            );


        if (!path) {
            return;
        }


        path.innerHTML = '';


        path.appendChild(
            createPathItem(
                origin || 'Unknown Origin',
                'Origin',
                'start',
                true
            )
        );


        stops.forEach(
            (stop, index) => {

                path.appendChild(
                    createPathItem(
                        stop,
                        `Stop ${index + 1}`,
                        '',
                        false
                    )
                );

            }
        );


        path.appendChild(
            createPathItem(
                destination ||
                    'Unknown Destination',

                'Destination',

                'destination',

                true
            )
        );
    }


    /* =========================================================
       CREATE PATH ITEM
    ========================================================= */

    function createPathItem(
        name,
        label,
        extraClass,
        locationIcon
    ) {

        const item =
            document.createElement('div');


        item.className =
            `route-path-item ${extraClass}`.trim();


        const marker =
            document.createElement('div');


        marker.className =
            'path-marker';


        const icon =
            document.createElement('i');


        icon.className =
            locationIcon
                ? 'fa-solid fa-location-dot'
                : 'fa-solid fa-circle';


        marker.appendChild(
            icon
        );


        const content =
            document.createElement('div');


        const strong =
            document.createElement('strong');


        strong.textContent =
            name;


        const span =
            document.createElement('span');


        span.textContent =
            label;


        content.appendChild(
            strong
        );

        content.appendChild(
            span
        );


        item.appendChild(
            marker
        );

        item.appendChild(
            content
        );


        return item;
    }


    /* =========================================================
       CLOSE VIEW
    ========================================================= */

    document
        .querySelectorAll(
            '[data-close-route-details]'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    closeModal(
                        routeDetailsModal
                    );

                }
            );

        });


    /* =========================================================
       DELETE MODAL
    ========================================================= */

    document.addEventListener(
        'click',
        event => {

            const deleteButton =
                event.target.closest(
                    '.open-delete-route-modal'
                );


            if (!deleteButton) {
                return;
            }


            const formId =
                deleteButton.dataset.formId;


            selectedDeleteForm =
                formId
                    ? document.getElementById(
                        formId
                    )
                    : null;


            const routeDisplay =
                [
                    deleteButton.dataset.routeCode,
                    deleteButton.dataset.routeName
                ]
                    .filter(Boolean)
                    .join(' - ');


            if (deleteRouteName) {

                deleteRouteName.textContent =
                    routeDisplay ||
                    'this route';

            }


            openModal(
                deleteRouteModal
            );

        }
    );


    cancelDeleteRoute?.addEventListener(
        'click',
        () => {

            selectedDeleteForm =
                null;


            closeModal(
                deleteRouteModal
            );

        }
    );


    confirmDeleteRoute?.addEventListener(
        'click',
        () => {

            if (!selectedDeleteForm) {
                return;
            }


            const form =
                selectedDeleteForm;


            selectedDeleteForm =
                null;


            closeModal(
                deleteRouteModal
            );


            form.requestSubmit();

        }
    );


    /* =========================================================
       BACKDROP CLOSE
    ========================================================= */

    [
        routeModal,
        routeDetailsModal,
        deleteRouteModal
    ]
        .filter(Boolean)
        .forEach(modal => {

            modal.addEventListener(
                'click',
                event => {

                    if (
                        event.target ===
                        modal
                    ) {

                        closeModal(
                            modal
                        );

                    }

                }
            );

        });


    /* =========================================================
       ESC
    ========================================================= */

    document.addEventListener(
        'keydown',
        event => {

            if (
                event.key !==
                'Escape'
            ) {
                return;
            }


            closeModal(
                routeModal
            );

            closeModal(
                routeDetailsModal
            );

            closeModal(
                deleteRouteModal
            );

        }
    );


    /* =========================================================
       VALIDATION ERROR MODAL
    ========================================================= */

    const validationModal =
        document.getElementById(
            'routeValidationModal'
        );


    const closeValidationModal =
        document.getElementById(
            'closeRouteValidationModal'
        );


    if (
        validationModal &&
        closeValidationModal
    ) {

        closeValidationModal
            .addEventListener(
                'click',
                () => {

                    validationModal.remove();

                }
            );

    }


    /* =========================================================
       FEEDBACK MODAL
    ========================================================= */

    document
        .querySelectorAll(
            '.close-feedback-modal'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    const modal =
                        button.closest(
                            '.modal-overlay'
                        );


                    if (modal) {
                        modal.remove();
                    }

                }
            );

        });


    /* =========================================================
       INITIAL NUMBERING
    ========================================================= */

    updateStopNumbers();

});