import '../../echo';

window.GCTPartialNavigation.registerInitializer('operation-bus-master-list', '.bus-master-list-page', () => {

    function normalizeBusPath(
        value,
        fallback = '/bus-master-list'
    ) {
        const rawValue = String(value || '').trim();

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
            const parsed = new URL(
                rawValue,
                window.location.origin
            );

            if (
                parsed.origin
                === window.location.origin
            ) {
                return `${parsed.pathname}${parsed.search}${parsed.hash}`;
            }
        } catch (error) {
            // Continue to malformed URL cleanup.
        }

        const withoutScheme = rawValue
            .replace(/^https?:\/+/i, '')
            .replace(/^\/+/, '');

        const pathIndex =
            withoutScheme.indexOf(
                'bus-master-list'
            );

        if (pathIndex >= 0) {
            return `/${withoutScheme.slice(pathIndex)}`;
        }

        return fallback;
    }


    /*
    |--------------------------------------------------------------------------
    | Elements
    |--------------------------------------------------------------------------
    */

    const busModal =
        document.getElementById('busModal');

    const editBusModal =
        document.getElementById('editBusModal');

    const deleteBusModal =
        document.getElementById('deleteBusModal');


    /*
    |--------------------------------------------------------------------------
    | Add Bus
    |--------------------------------------------------------------------------
    */

    const openBusModal =
        document.getElementById('openBusModal');

    const closeBusModal =
        document.getElementById('closeBusModal');

    const cancelBusModal =
        document.getElementById('cancelBusModal');


    /*
    |--------------------------------------------------------------------------
    | Edit Bus
    |--------------------------------------------------------------------------
    */

    const closeEditBusModal =
        document.getElementById('closeEditBusModal');

    const cancelEditBusModal =
        document.getElementById('cancelEditBusModal');

    const editBusForm =
        document.getElementById('editBusForm');


    /*
    |--------------------------------------------------------------------------
    | Delete Bus
    |--------------------------------------------------------------------------
    */

    const cancelDeleteBus =
        document.getElementById('cancelDeleteBus');

    const confirmDeleteBus =
        document.getElementById('confirmDeleteBus');

    const deleteBusNo =
        document.getElementById('deleteBusNo');

    let selectedDeleteForm = null;


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
    | Add Bus Modal
    |--------------------------------------------------------------------------
    */

    if (openBusModal) {
        openBusModal.addEventListener('click', () => {
            openModal(busModal);
        });
    }


    if (closeBusModal) {
        closeBusModal.addEventListener('click', () => {
            closeModal(busModal);
        });
    }


    if (cancelBusModal) {
        cancelBusModal.addEventListener('click', () => {
            closeModal(busModal);
        });
    }


    // Delegated view action works after AJAX table filtering and remains read-only.
    const viewBusModal = document.getElementById('viewBusModal');
    const viewBusPage = document.querySelector('.bus-master-list-page');
    viewBusPage?.addEventListener('click', (event) => {
        const button = event.target.closest('.open-view-bus');
        if (!button || !viewBusPage.contains(button) || !viewBusModal) return;
        event.preventDefault();
        const fields = {
            busNo: button.dataset.busNo,
            plateNo: button.dataset.plateNo,
            busModel: button.dataset.busModel,
            yearModel: button.dataset.yearModel,
            capacity: button.dataset.capacity,
            status: button.dataset.status,
            routeGrouping: button.dataset.displayRoute || button.dataset.routeGrouping,
        };
        for (const [key, value] of Object.entries(fields)) {
            const target = viewBusModal.querySelector('[data-bus-detail="' + key + '"]');
            if (target) target.textContent = String(value || '—');
        }
        const statusBadge = viewBusModal.querySelector('.bus-details-status');
        if (statusBadge) {
            const validStatuses = ['Active', 'Inactive', 'Under Maintenance'];
            statusBadge.dataset.status = validStatuses.includes(fields.status) ? fields.status : 'Unknown';
        }
        viewBusModal.setAttribute('aria-hidden', 'false');
        openModal(viewBusModal);
    });
    ['closeViewBusModal', 'dismissViewBusModal'].forEach((id) => {
        document.getElementById(id)?.addEventListener('click', () => {
            closeModal(viewBusModal);
            viewBusModal?.setAttribute('aria-hidden', 'true');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Edit Bus Modal
    |--------------------------------------------------------------------------
    */

    // Delegate table actions so they continue working after AJAX filtering.
    const busPage = document.querySelector('.bus-master-list-page');

    busPage?.addEventListener('click', (event) => {
        const button = event.target.closest('.open-edit-bus');
        if (!button || !busPage.contains(button)) return;

                event.preventDefault();

                if (!editBusForm) {
                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Update Form URL
                |--------------------------------------------------------------------------
                */

                editBusForm.setAttribute(
                    'action',
                    normalizeBusPath(
                        button.dataset.updateUrl,
                        `/bus-master-list/${button.dataset.id}`
                    )
                );


                /*
                |--------------------------------------------------------------------------
                | Get Edit Inputs
                |--------------------------------------------------------------------------
                */

                const editBusNo =
                    document.getElementById('edit_bus_no');

                const editPlateNo =
                    document.getElementById('edit_plate_no');

                const editBusModel =
                    document.getElementById('edit_bus_model');

                const editYearModel =
                    document.getElementById('edit_year_model');

                const editCapacity =
                    document.getElementById('edit_capacity');

                const editRouteGrouping =
                    document.getElementById('edit_route_grouping');

                const editStatus =
                    document.getElementById('edit_status');


                /*
                |--------------------------------------------------------------------------
                | Fill Form
                |--------------------------------------------------------------------------
                */

                if (editBusNo) {
                    editBusNo.value =
                        button.dataset.busNo || '';
                }


                if (editPlateNo) {
                    editPlateNo.value =
                        button.dataset.plateNo || '';
                }


                if (editBusModel) {
                    editBusModel.value =
                        button.dataset.busModel || '';
                }


                if (editYearModel) {
                    editYearModel.value =
                        button.dataset.yearModel || '';
                }


                if (editCapacity) {
                    editCapacity.value =
                        button.dataset.capacity || '';
                }


                if (editRouteGrouping) {
                    editRouteGrouping.value =
                        button.dataset.routeGrouping || '';
                }


                if (editStatus) {
                    editStatus.value =
                        button.dataset.status || 'Active';
                }


                openModal(editBusModal);
    });


    if (closeEditBusModal) {
        closeEditBusModal.addEventListener('click', () => {
            closeModal(editBusModal);
        });
    }


    if (cancelEditBusModal) {
        cancelEditBusModal.addEventListener('click', () => {
            closeModal(editBusModal);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Delete Bus Modal
    |--------------------------------------------------------------------------
    */

    busPage?.addEventListener('click', (event) => {
        const button = event.target.closest('.open-delete-bus');
        if (!button || !busPage.contains(button)) return;

                event.preventDefault();

                const id =
                    button.dataset.id;


                selectedDeleteForm =
                    document.getElementById(
                        `deleteBusForm-${id}`
                    );


                if (deleteBusNo) {
                    deleteBusNo.textContent =
                        button.dataset.busNo
                        || 'this bus';
                }


                openModal(deleteBusModal);
    });


    if (cancelDeleteBus) {
        cancelDeleteBus.addEventListener('click', () => {

            selectedDeleteForm = null;

            closeModal(deleteBusModal);
        });
    }


    if (confirmDeleteBus) {
        confirmDeleteBus.addEventListener('click', () => {

            if (selectedDeleteForm) {
                selectedDeleteForm.requestSubmit();
            }
        });
    }


    // Modal backdrops never close dialogs; use the explicit close/cancel buttons.


    /*
    |--------------------------------------------------------------------------
    | Feedback Modal
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.close-feedback-modal')
        .forEach((button) => {

            button.addEventListener('click', () => {

                closeModal(
                    button.closest(
                        '.success-modal-overlay'
                    )
                );
            });
        });


    /*
    |--------------------------------------------------------------------------
    | Escape Key
    |--------------------------------------------------------------------------
    */

    document.addEventListener('keydown', (event) => {

        if (event.key !== 'Escape') {
            return;
        }

        closeModal(busModal);
        closeModal(editBusModal);
        closeModal(deleteBusModal);
    });

});
