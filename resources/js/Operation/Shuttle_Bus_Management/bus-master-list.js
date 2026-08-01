import '../../echo';

document.addEventListener('DOMContentLoaded', () => {

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

    const importBusModal =
        document.getElementById('importBusModal');

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
    | Import Bus
    |--------------------------------------------------------------------------
    */

    const openImportBusModal =
        document.getElementById('openImportBusModal');

    const closeImportBusModal =
        document.getElementById('closeImportBusModal');

    const cancelImportBusModal =
        document.getElementById('cancelImportBusModal');


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


    /*
    |--------------------------------------------------------------------------
    | Import CSV Modal
    |--------------------------------------------------------------------------
    */

    if (openImportBusModal) {
        openImportBusModal.addEventListener('click', () => {
            openModal(importBusModal);
        });
    }


    if (closeImportBusModal) {
        closeImportBusModal.addEventListener('click', () => {
            closeModal(importBusModal);
        });
    }


    if (cancelImportBusModal) {
        cancelImportBusModal.addEventListener('click', () => {
            closeModal(importBusModal);
        });
    }


    /*
    |--------------------------------------------------------------------------
    | Edit Bus Modal
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll('.open-edit-bus')
        .forEach((button) => {

            button.addEventListener('click', (event) => {

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

    document
        .querySelectorAll('.open-delete-bus')
        .forEach((button) => {

            button.addEventListener('click', (event) => {

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


    /*
    |--------------------------------------------------------------------------
    | Click Outside Modal
    |--------------------------------------------------------------------------
    */

    document
        .querySelectorAll(
            '.modal-overlay, .delete-modal-overlay, .success-modal-overlay'
        )
        .forEach((modal) => {

            modal.addEventListener('click', (event) => {

                if (event.target === modal) {
                    closeModal(modal);
                }
            });
        });


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
        closeModal(importBusModal);
        closeModal(editBusModal);
        closeModal(deleteBusModal);
    });

});