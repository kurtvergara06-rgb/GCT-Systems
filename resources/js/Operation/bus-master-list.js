document.addEventListener('DOMContentLoaded', () => {
    const busModal = document.getElementById('busModal');
    const importBusModal = document.getElementById('importBusModal');
    const editBusModal = document.getElementById('editBusModal');
    const deleteBusModal = document.getElementById('deleteBusModal');

    const openBusModal = document.getElementById('openBusModal');
    const closeBusModal = document.getElementById('closeBusModal');
    const cancelBusModal = document.getElementById('cancelBusModal');

    const openImportBusModal = document.getElementById('openImportBusModal');
    const closeImportBusModal = document.getElementById('closeImportBusModal');
    const cancelImportBusModal = document.getElementById('cancelImportBusModal');

    const closeEditBusModal = document.getElementById('closeEditBusModal');
    const cancelEditBusModal = document.getElementById('cancelEditBusModal');

    const cancelDeleteBus = document.getElementById('cancelDeleteBus');
    const confirmDeleteBus = document.getElementById('confirmDeleteBus');
    const deleteBusNo = document.getElementById('deleteBusNo');

    const editBusForm = document.getElementById('editBusForm');

    let selectedDeleteForm = null;

    function openModal(modal) {
        if (!modal) return;

        modal.classList.add('show');
        modal.classList.add('active');
    }

    function closeModal(modal) {
        if (!modal) return;

        modal.classList.remove('show');
        modal.classList.remove('active');
    }

    openBusModal?.addEventListener('click', () => {
        openModal(busModal);
    });

    closeBusModal?.addEventListener('click', () => {
        closeModal(busModal);
    });

    cancelBusModal?.addEventListener('click', () => {
        closeModal(busModal);
    });

    openImportBusModal?.addEventListener('click', () => {
        openModal(importBusModal);
    });

    closeImportBusModal?.addEventListener('click', () => {
        closeModal(importBusModal);
    });

    cancelImportBusModal?.addEventListener('click', () => {
        closeModal(importBusModal);
    });

    document.querySelectorAll('.open-edit-bus').forEach((button) => {
        button.addEventListener('click', () => {
            if (!editBusForm) return;

            editBusForm.action = button.dataset.updateUrl;

            document.getElementById('edit_bus_no').value =
                button.dataset.busNo || '';

            document.getElementById('edit_plate_no').value =
                button.dataset.plateNo || '';

            document.getElementById('edit_bus_model').value =
                button.dataset.busModel || '';

            document.getElementById('edit_year_model').value =
                button.dataset.yearModel || '';

            document.getElementById('edit_capacity').value =
                button.dataset.capacity || '';

            document.getElementById('edit_route_grouping').value =
                button.dataset.routeGrouping || '';

            document.getElementById('edit_status').value =
                button.dataset.status || 'Active';

            document.getElementById('edit_last_pms_km').value =
                button.dataset.lastPmsKm || 0;

            document.getElementById('edit_pms_interval_km').value =
                button.dataset.pmsIntervalKm || 5000;

            openModal(editBusModal);
        });
    });

    closeEditBusModal?.addEventListener('click', () => {
        closeModal(editBusModal);
    });

    cancelEditBusModal?.addEventListener('click', () => {
        closeModal(editBusModal);
    });

    document.querySelectorAll('.open-delete-bus').forEach((button) => {
        button.addEventListener('click', () => {
            const id = button.dataset.id;

            selectedDeleteForm = document.getElementById(
                `deleteBusForm-${id}`
            );

            if (deleteBusNo) {
                deleteBusNo.textContent =
                    button.dataset.busNo || 'this bus';
            }

            openModal(deleteBusModal);
        });
    });

    cancelDeleteBus?.addEventListener('click', () => {
        selectedDeleteForm = null;
        closeModal(deleteBusModal);
    });

    confirmDeleteBus?.addEventListener('click', () => {
        if (selectedDeleteForm) {
            selectedDeleteForm.submit();
        }
    });

    document.querySelectorAll(
        '.modal-overlay, .delete-modal-overlay, .success-modal-overlay'
    ).forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.querySelectorAll('.close-feedback-modal').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.closest('.success-modal-overlay'));
        });
    });
});