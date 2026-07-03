document.addEventListener('DOMContentLoaded', () => {
    const addPmsModal = document.getElementById('addPmsModal');
    const editPmsModal = document.getElementById('editPmsModal');

    const pmsBusSelect = document.getElementById('pmsBusSelect');
    const currentGpsKm = document.getElementById('currentGpsKm');
    const gpsReportDate = document.getElementById('gpsReportDate');

    const lastPmsKm = document.getElementById('lastPmsKm');
    const pmsIntervalKm = document.getElementById('pmsIntervalKm');
    const nextPmsKm = document.getElementById('nextPmsKm');
    const pmsStatusPreview = document.getElementById('pmsStatusPreview');

    function formatKm(value) {
        const number = Number(value || 0);

        return number.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    function updatePmsPreview() {
        if (!pmsBusSelect || !lastPmsKm || !pmsIntervalKm) {
            return;
        }

        const selectedOption = pmsBusSelect.options[pmsBusSelect.selectedIndex];

        const currentKm = Number(
            selectedOption?.dataset.currentKm || 0
        );

        const lastKm = Number(lastPmsKm.value || 0);
        const intervalKm = Number(pmsIntervalKm.value || 0);

        const nextKm = lastKm + intervalKm;

        if (nextPmsKm) {
            nextPmsKm.value = intervalKm > 0
                ? `${formatKm(nextKm)} km`
                : '';
        }

        if (!pmsStatusPreview) {
            return;
        }

        if (!selectedOption || !selectedOption.value || intervalKm <= 0) {
            pmsStatusPreview.value = '';
            return;
        }

        if (currentKm >= nextKm) {
            pmsStatusPreview.value = 'Overdue';
        } else if (currentKm >= nextKm - 500) {
            pmsStatusPreview.value = 'Due Soon';
        } else {
            pmsStatusPreview.value = 'Upcoming';
        }
    }

    function updateSelectedBusData() {
        if (!pmsBusSelect) {
            return;
        }

        const selectedOption = pmsBusSelect.options[pmsBusSelect.selectedIndex];

        if (!selectedOption || !selectedOption.value) {
            if (currentGpsKm) {
                currentGpsKm.value = '';
            }

            if (gpsReportDate) {
                gpsReportDate.value = '';
            }

            updatePmsPreview();
            return;
        }

        if (currentGpsKm) {
            currentGpsKm.value =
                `${formatKm(selectedOption.dataset.currentKm)} km`;
        }

        if (gpsReportDate) {
            gpsReportDate.value =
                selectedOption.dataset.gpsDate || 'No date available';
        }

        updatePmsPreview();
    }

    if (pmsBusSelect) {
        pmsBusSelect.addEventListener('change', updateSelectedBusData);
    }

    if (lastPmsKm) {
        lastPmsKm.addEventListener('input', updatePmsPreview);
    }

    if (pmsIntervalKm) {
        pmsIntervalKm.addEventListener('input', updatePmsPreview);
    }

    document.querySelectorAll('[data-open-add-pms]').forEach((button) => {
        button.addEventListener('click', () => {
            addPmsModal?.classList.add('show');
        });
    });

    document.querySelectorAll('[data-close-add-pms]').forEach((button) => {
        button.addEventListener('click', () => {
            addPmsModal?.classList.remove('show');
        });
    });

    document.querySelectorAll('[data-close-edit-pms]').forEach((button) => {
        button.addEventListener('click', () => {
            editPmsModal?.classList.remove('show');
        });
    });

    document.querySelectorAll('.pms-modal-overlay').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                modal.classList.remove('show');
            }
        });
    });

    updateSelectedBusData();
});