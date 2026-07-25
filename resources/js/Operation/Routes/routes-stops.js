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

        modal.classList.add('show');
        modal.classList.add('active');

        document.body.style.overflow = 'hidden';
    }


    function closeModal(modal) {

        if (!modal) {
            return;
        }

        modal.classList.remove('show');
        modal.classList.remove('active');

        const openModalExists =
            document.querySelector(
                '.route-modal-overlay.show, .modal-overlay.show'
            );

        if (!openModalExists) {
            document.body.style.overflow = '';
        }
    }


    /* =========================================================
       RESET CREATE FORM
    ========================================================= */

    function resetCreateForm() {

        if (!routeForm) {
            return;
        }

        routeForm.reset();

        /*
         * Important:
         * Laravel POST form should NOT send _method=POST.
         */
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


        if (routeStopList) {

            routeStopList.innerHTML = '';

            addStopInput();

        }
    }


    /*
     * Store original action once.
     */
    if (routeForm) {

        routeForm.dataset.storeUrl =
            routeForm.getAttribute('action');

    }


    /* =========================================================
       NEW ROUTE
    ========================================================= */

    if (openRouteModalBtn) {

        openRouteModalBtn.addEventListener(
            'click',
            () => {

                resetCreateForm();

                openModal(routeModal);

            }
        );

    }


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
       ADD STOP
    ========================================================= */

    if (addRouteStop) {

        addRouteStop.addEventListener(
            'click',
            () => {

                addStopInput();

            }
        );

    }


    function addStopInput(value = '') {

        if (!routeStopList) {
            return;
        }


        const item =
            document.createElement('div');


        item.className =
            'route-stop-item';


        item.innerHTML = `
            <div class="route-stop-number"></div>

            <input
                type="text"
                name="stops[]"
                placeholder="Enter stop name"
            >

            <button
                type="button"
                class="remove-route-stop"
                title="Remove Stop"
            >
                <i class="fa-solid fa-trash"></i>
            </button>
        `;


        const input =
            item.querySelector('input');


        if (input) {
            input.value = value;
        }


        routeStopList.appendChild(item);

        updateStopNumbers();
    }


    /* =========================================================
       REMOVE STOP
    ========================================================= */

    if (routeStopList) {

        routeStopList.addEventListener(
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


                item.remove();


                if (
                    routeStopList.querySelectorAll(
                        '.route-stop-item'
                    ).length === 0
                ) {

                    addStopInput();

                }


                updateStopNumbers();

            }
        );

    }


    function updateStopNumbers() {

        if (!routeStopList) {
            return;
        }


        routeStopList
            .querySelectorAll('.route-stop-item')
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
       GET BUTTON DATA
    ========================================================= */

    function getRouteData(button) {

        let stops = [];


        try {

            stops =
                JSON.parse(
                    button.dataset.stops || '[]'
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
                getRouteData(editButton);


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
             * Change POST form to Laravel PUT.
             */
            if (routeFormMethod) {

                routeFormMethod.disabled = false;
                routeFormMethod.value = 'PUT';

            }


            if (
                routeForm &&
                route.updateUrl
            ) {

                routeForm.action =
                    route.updateUrl;

            }


            if (routeStopList) {

                routeStopList.innerHTML = '';


                if (route.stops.length > 0) {

                    route.stops.forEach(
                        stop => {

                            addStopInput(stop);

                        }
                    );

                } else {

                    addStopInput();

                }

            }


            openModal(routeModal);

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
                getRouteData(viewButton);


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


    function setText(id, value) {

        const element =
            document.getElementById(id);


        if (element) {
            element.textContent = value;
        }
    }


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


        wrapper.appendChild(badge);
    }


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


        /*
         * Origin
         */
        path.appendChild(
            createPathItem(
                origin || 'Unknown Origin',
                'Origin',
                'start',
                true
            )
        );


        /*
         * Stops
         */
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


        /*
         * Destination
         */
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


        marker.appendChild(icon);


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


        content.appendChild(strong);
        content.appendChild(span);


        item.appendChild(marker);
        item.appendChild(content);


        return item;
    }


    /* =========================================================
       CLOSE VIEW MODAL
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
                    ? document.getElementById(formId)
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


    if (cancelDeleteRoute) {

        cancelDeleteRoute.addEventListener(
            'click',
            () => {

                selectedDeleteForm = null;

                closeModal(
                    deleteRouteModal
                );

            }
        );

    }


    if (confirmDeleteRoute) {

        confirmDeleteRoute.addEventListener(
            'click',
            () => {

                if (!selectedDeleteForm) {
                    return;
                }


                const form =
                    selectedDeleteForm;


                selectedDeleteForm = null;


                closeModal(
                    deleteRouteModal
                );


                form.requestSubmit();

            }
        );

    }


    /* =========================================================
       CLOSE MODALS BY CLICKING BACKDROP
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

                    if (event.target === modal) {

                        closeModal(modal);

                    }

                }
            );

        });


    /* =========================================================
       ESC KEY
    ========================================================= */

    document.addEventListener(
        'keydown',
        event => {

            if (event.key !== 'Escape') {
                return;
            }


            closeModal(routeModal);

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
       GLOBAL FEEDBACK MODAL
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
       INITIAL STOP NUMBERS
    ========================================================= */

    updateStopNumbers();

});