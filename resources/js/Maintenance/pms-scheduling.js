document.addEventListener('DOMContentLoaded', () => {

    const DEFAULT_AVERAGE_DAILY_KM = 250;

    const standardPmsTypes = [
        'Change Oil',
        'Oil Filter',
        'Brake Check',
        'Air Filter',
        'Full PMS',
    ];


    /* =========================================================
       MODALS / FORMS
    ========================================================= */

    const addPmsModal =
        document.getElementById('addPmsModal');

    const editPmsModal =
        document.getElementById('editPmsModal');

    const addPmsForm =
        document.getElementById('addPmsForm');

    const editPmsForm =
        document.getElementById('editPmsForm');


    /* =========================================================
       ADD PMS ELEMENTS
    ========================================================= */

    const pmsBusSelect =
        document.getElementById('pmsBusSelect');

    const currentGpsKm =
        document.getElementById('currentGpsKm');

    const gpsReportDate =
        document.getElementById('gpsReportDate');

    const lastPmsKm =
        document.getElementById('lastPmsKm');

    const pmsIntervalKm =
        document.getElementById('pmsIntervalKm');

    const nextPmsKm =
        document.getElementById('nextPmsKm');

    const pmsStatusPreview =
        document.getElementById('pmsStatusPreview');

    const recommendedDate =
        document.getElementById('recommendedDate');

    const maintenanceType =
        document.getElementById('maintenanceType');

    const customMaintenanceTypeGroup =
        document.getElementById(
            'customMaintenanceTypeGroup'
        );

    const customMaintenanceType =
        document.getElementById(
            'customMaintenanceType'
        );

    const finalMaintenanceType =
        document.getElementById(
            'finalMaintenanceType'
        );


    /* =========================================================
       EDIT PMS ELEMENTS
    ========================================================= */

    const editBusNo =
        document.getElementById('editPmsBusNo');

    const editMaintenanceType =
        document.getElementById(
            'editPmsMaintenanceType'
        );

    const editCustomMaintenanceTypeGroup =
        document.getElementById(
            'editCustomMaintenanceTypeGroup'
        );

    const editCustomMaintenanceType =
        document.getElementById(
            'editCustomMaintenanceType'
        );

    const editFinalMaintenanceType =
        document.getElementById(
            'editFinalMaintenanceType'
        );

    const editLastPmsKm =
        document.getElementById(
            'editLastPmsKm'
        );

    const editPmsIntervalKm =
        document.getElementById(
            'editPmsIntervalKm'
        );

    const editNextPmsKm =
        document.getElementById(
            'editNextPmsKm'
        );

    const editRecommendedDate =
        document.getElementById(
            'editRecommendedDate'
        );


    let editCurrentKm = null;
    let editGpsDate = null;


    /* =========================================================
       HELPERS
    ========================================================= */

    function formatKm(value) {

        const number =
            Number(value || 0);

        return number.toLocaleString(
            'en-US',
            {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }
        );
    }


    function parseDate(value) {

        if (!value) {
            return null;
        }

        const date =
            new Date(value);

        if (
            Number.isNaN(
                date.getTime()
            )
        ) {
            return null;
        }

        return date;
    }


    function formatDateInput(date) {

        if (
            !(date instanceof Date) ||
            Number.isNaN(date.getTime())
        ) {
            return '';
        }

        const year =
            date.getFullYear();

        const month =
            String(
                date.getMonth() + 1
            ).padStart(2, '0');

        const day =
            String(
                date.getDate()
            ).padStart(2, '0');

        return `${year}-${month}-${day}`;
    }


    function calculateRecommendedDate(
        currentKm,
        nextKm,
        baseDate
    ) {

        const numericCurrentKm =
            Number(currentKm);

        const numericNextKm =
            Number(nextKm);

        const parsedBaseDate =
            parseDate(baseDate);


        if (
            !Number.isFinite(numericCurrentKm) ||
            !Number.isFinite(numericNextKm) ||
            !parsedBaseDate
        ) {
            return '';
        }


        const remainingKm =
            numericNextKm -
            numericCurrentKm;


        /*
         * Date input requires YYYY-MM-DD.
         * When overdue, use the base GPS date.
         */
        if (remainingKm <= 0) {

            return formatDateInput(
                parsedBaseDate
            );

        }


        const estimatedDays =
            Math.max(
                1,
                Math.ceil(
                    remainingKm /
                    DEFAULT_AVERAGE_DAILY_KM
                )
            );


        const estimatedDate =
            new Date(
                parsedBaseDate
            );


        estimatedDate.setDate(
            estimatedDate.getDate() +
            estimatedDays
        );


        return formatDateInput(
            estimatedDate
        );
    }


    function openModal(modal) {

        if (!modal) {
            return;
        }

        modal.classList.add(
            'show',
            'active'
        );

        document.body.style.overflow =
            'hidden';
    }


    function closeModal(modal) {

        if (!modal) {
            return;
        }

        modal.classList.remove(
            'show',
            'active'
        );


        const openModalExists =
            document.querySelector(
                '.ui-form-overlay.show, ' +
                '.pms-modal-overlay.show'
            );


        if (!openModalExists) {

            document.body.style.overflow =
                '';

        }
    }


    /* =========================================================
       ADD PMS PREVIEW
    ========================================================= */

    function updateAddPmsPreview() {

        if (
            !pmsBusSelect ||
            !lastPmsKm ||
            !pmsIntervalKm
        ) {
            return;
        }


        const selectedOption =
            pmsBusSelect.options[
                pmsBusSelect.selectedIndex
            ];


        const hasSelectedBus =
            selectedOption &&
            selectedOption.value;


        const currentKm =
            Number(
                selectedOption
                    ?.dataset
                    .currentKm || 0
            );


        const lastKm =
            Number(
                lastPmsKm.value || 0
            );


        const intervalKm =
            Number(
                pmsIntervalKm.value || 0
            );


        const calculatedNextKm =
            lastKm +
            intervalKm;


        if (nextPmsKm) {

            nextPmsKm.value =
                intervalKm > 0
                    ? `${formatKm(calculatedNextKm)} km`
                    : '';

        }


        if (pmsStatusPreview) {

            if (
                !hasSelectedBus ||
                intervalKm <= 0
            ) {

                pmsStatusPreview.value =
                    '';

            } else if (
                currentKm >=
                calculatedNextKm
            ) {

                pmsStatusPreview.value =
                    'Overdue';

            } else if (
                currentKm >=
                calculatedNextKm - 500
            ) {

                pmsStatusPreview.value =
                    'Due Soon';

            } else {

                pmsStatusPreview.value =
                    'Upcoming';

            }

        }


        if (recommendedDate) {

            if (
                !hasSelectedBus ||
                intervalKm <= 0
            ) {

                recommendedDate.value =
                    '';

            } else {

                const baseDate =
                    selectedOption
                        .dataset
                        .gpsDateIso ||
                    selectedOption
                        .dataset
                        .gpsDate ||
                    '';


                recommendedDate.value =
                    calculateRecommendedDate(
                        currentKm,
                        calculatedNextKm,
                        baseDate
                    );

            }

        }
    }


    function updateSelectedBusData() {

        if (!pmsBusSelect) {
            return;
        }


        const selectedOption =
            pmsBusSelect.options[
                pmsBusSelect.selectedIndex
            ];


        if (
            !selectedOption ||
            !selectedOption.value
        ) {

            if (currentGpsKm) {
                currentGpsKm.value = '';
            }

            if (gpsReportDate) {
                gpsReportDate.value = '';
            }

            if (recommendedDate) {
                recommendedDate.value = '';
            }


            updateAddPmsPreview();

            return;
        }


        if (currentGpsKm) {

            currentGpsKm.value =
                `${formatKm(
                    selectedOption
                        .dataset
                        .currentKm
                )} km`;

        }


        if (gpsReportDate) {

            gpsReportDate.value =
                selectedOption
                    .dataset
                    .gpsDate ||
                'No date available';

        }


        updateAddPmsPreview();
    }


    /* =========================================================
       EDIT PMS PREVIEW
    ========================================================= */

    function updateEditPmsPreview() {

        if (
            !editLastPmsKm ||
            !editPmsIntervalKm
        ) {
            return;
        }


        const lastKm =
            Number(
                editLastPmsKm.value || 0
            );


        const intervalKm =
            Number(
                editPmsIntervalKm.value || 0
            );


        const calculatedNextKm =
            lastKm +
            intervalKm;


        if (editNextPmsKm) {

            editNextPmsKm.value =
                intervalKm > 0
                    ? `${formatKm(calculatedNextKm)} km`
                    : '';

        }


        if (editRecommendedDate) {

            if (
                intervalKm <= 0 ||
                editCurrentKm === null ||
                !editGpsDate
            ) {

                return;

            }


            editRecommendedDate.value =
                calculateRecommendedDate(
                    editCurrentKm,
                    calculatedNextKm,
                    editGpsDate
                );

        }
    }


    /* =========================================================
       ADD PMS TYPE
    ========================================================= */

    function updateAddMaintenanceType(
        shouldFocus = false
    ) {

        if (
            !maintenanceType ||
            !customMaintenanceTypeGroup ||
            !customMaintenanceType ||
            !finalMaintenanceType
        ) {
            return;
        }


        const isOther =
            maintenanceType.value ===
            'Other';


        customMaintenanceTypeGroup.hidden =
            !isOther;

        customMaintenanceType.disabled =
            !isOther;

        customMaintenanceType.readOnly =
            false;

        customMaintenanceType.required =
            isOther;


        if (isOther) {

            finalMaintenanceType.value =
                customMaintenanceType
                    .value
                    .trim();


            if (shouldFocus) {

                window.setTimeout(
                    () => {

                        customMaintenanceType.focus();

                    },
                    50
                );

            }

        } else {

            customMaintenanceType.value =
                '';

            finalMaintenanceType.value =
                maintenanceType.value;

        }
    }


    /* =========================================================
       EDIT PMS TYPE
    ========================================================= */

    function updateEditMaintenanceType(
        shouldFocus = false
    ) {

        if (
            !editMaintenanceType ||
            !editCustomMaintenanceTypeGroup ||
            !editCustomMaintenanceType ||
            !editFinalMaintenanceType
        ) {
            return;
        }


        const isOther =
            editMaintenanceType.value ===
            'Other';


        editCustomMaintenanceTypeGroup.hidden =
            !isOther;

        editCustomMaintenanceType.disabled =
            !isOther;

        editCustomMaintenanceType.readOnly =
            false;

        editCustomMaintenanceType.required =
            isOther;


        if (isOther) {

            editFinalMaintenanceType.value =
                editCustomMaintenanceType
                    .value
                    .trim();


            if (shouldFocus) {

                window.setTimeout(
                    () => {

                        editCustomMaintenanceType.focus();

                    },
                    50
                );

            }

        } else {

            editCustomMaintenanceType.value =
                '';

            editFinalMaintenanceType.value =
                editMaintenanceType.value;

        }
    }


    /* =========================================================
       INPUT EVENTS
    ========================================================= */

    pmsBusSelect?.addEventListener(
        'change',
        updateSelectedBusData
    );


    lastPmsKm?.addEventListener(
        'input',
        updateAddPmsPreview
    );


    pmsIntervalKm?.addEventListener(
        'input',
        updateAddPmsPreview
    );


    editLastPmsKm?.addEventListener(
        'input',
        updateEditPmsPreview
    );


    editPmsIntervalKm?.addEventListener(
        'input',
        updateEditPmsPreview
    );


    maintenanceType?.addEventListener(
        'change',
        () => {

            updateAddMaintenanceType(true);

        }
    );


    customMaintenanceType?.addEventListener(
        'input',
        () => {

            if (finalMaintenanceType) {

                finalMaintenanceType.value =
                    customMaintenanceType
                        .value
                        .trim();

            }

        }
    );


    editMaintenanceType?.addEventListener(
        'change',
        () => {

            updateEditMaintenanceType(true);

        }
    );


    editCustomMaintenanceType?.addEventListener(
        'input',
        () => {

            if (editFinalMaintenanceType) {

                editFinalMaintenanceType.value =
                    editCustomMaintenanceType
                        .value
                        .trim();

            }

        }
    );


    /* =========================================================
       ADD PMS MODAL
    ========================================================= */

    document
        .querySelectorAll(
            '[data-open-add-pms]'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    updateAddMaintenanceType();

                    updateSelectedBusData();

                    openModal(
                        addPmsModal
                    );

                }
            );

        });


    document
        .querySelectorAll(
            '[data-close-add-pms]'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    closeModal(
                        addPmsModal
                    );

                }
            );

        });


    /* =========================================================
       EDIT PMS MODAL
       IMPORTANT:
       Close PMS Task View before opening Edit.
    ========================================================= */

    document
        .querySelectorAll(
            '.open-edit-pms'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    if (!editPmsForm) {
                        return;
                    }


                    /* =========================================
                       CLOSE CURRENT VIEW POPUP FIRST
                    ========================================= */

                    const currentTaskModal =
                        button.closest(
                            '.pms-modal-overlay'
                        );


                    if (currentTaskModal) {

                        closeModal(
                            currentTaskModal
                        );

                    }


                    /* =========================================
                       FORM ACTION
                    ========================================= */

                    editPmsForm.action =
                        button.dataset.updateUrl ||
                        '#';


                    /* =========================================
                       BUS
                    ========================================= */

                    if (editBusNo) {

                        editBusNo.value =
                            button.dataset.busNo ||
                            '';

                    }


                    /* =========================================
                       PMS TYPE
                    ========================================= */

                    const savedType =
                        button
                            .dataset
                            .maintenanceType ||
                        '';


                    if (
                        editMaintenanceType &&
                        editCustomMaintenanceType
                    ) {

                        if (
                            standardPmsTypes.includes(
                                savedType
                            )
                        ) {

                            editMaintenanceType.value =
                                savedType;

                            editCustomMaintenanceType.value =
                                '';

                        } else {

                            editMaintenanceType.value =
                                'Other';

                            editCustomMaintenanceType.value =
                                savedType;

                        }

                    }


                    /* =========================================
                       LAST PMS KM
                    ========================================= */

                    if (editLastPmsKm) {

                        editLastPmsKm.value =
                            button
                                .dataset
                                .lastPmsKm ||
                            0;

                    }


                    /* =========================================
                       PMS INTERVAL
                    ========================================= */

                    if (editPmsIntervalKm) {

                        editPmsIntervalKm.value =
                            button
                                .dataset
                                .pmsIntervalKm ||
                            5000;

                    }


                    /* =========================================
                       CURRENT KM
                    ========================================= */

                    editCurrentKm =
                        button.dataset.currentKm !== ''
                            ? Number(
                                button.dataset.currentKm
                            )
                            : null;


                    /* =========================================
                       GPS DATE
                    ========================================= */

                    editGpsDate =
                        button
                            .dataset
                            .gpsDateIso ||
                        button
                            .dataset
                            .gpsDate ||
                        null;


                    /* =========================================
                       SAVED RECOMMENDED DATE
                    ========================================= */

                    if (editRecommendedDate) {

                        editRecommendedDate.value =
                            button
                                .dataset
                                .recommendedDate ||
                            '';

                    }


                    updateEditMaintenanceType();

                    updateEditPmsPreview();


                    /*
                     * Give the previous overlay time to close
                     * before the global Edit form appears.
                     */
                    window.setTimeout(
                        () => {

                            openModal(
                                editPmsModal
                            );

                        },
                        50
                    );

                }
            );

        });


    document
        .querySelectorAll(
            '[data-close-edit-pms]'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    closeModal(
                        editPmsModal
                    );

                }
            );

        });


    /* =========================================================
       PMS TASK LIST VIEW
    ========================================================= */

    document
        .querySelectorAll(
            '.open-pms-tasks-modal'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    const modal =
                        document.getElementById(
                            button
                                .dataset
                                .modalTarget
                        );


                    openModal(
                        modal
                    );

                }
            );

        });


    document
        .querySelectorAll(
            '.close-pms-tasks-modal'
        )
        .forEach(button => {

            button.addEventListener(
                'click',
                () => {

                    const modal =
                        document.getElementById(
                            button
                                .dataset
                                .modalTarget
                        );


                    closeModal(
                        modal
                    );

                }
            );

        });


    /* =========================================================
       ADD FORM VALIDATION
    ========================================================= */

    addPmsForm?.addEventListener(
        'submit',
        event => {

            updateAddMaintenanceType();


            if (
                !finalMaintenanceType
                    ?.value
                    .trim()
            ) {

                event.preventDefault();

                customMaintenanceType
                    ?.focus();

            }

        }
    );


    /* =========================================================
       EDIT FORM VALIDATION
    ========================================================= */

    editPmsForm?.addEventListener(
        'submit',
        event => {

            updateEditMaintenanceType();


            if (
                !editFinalMaintenanceType
                    ?.value
                    .trim()
            ) {

                event.preventDefault();

                editCustomMaintenanceType
                    ?.focus();

            }

        }
    );


    /* =========================================================
       BACKDROP CLOSE
    ========================================================= */

    document.addEventListener(
        'click',
        event => {

            const modal =
                event.target.closest(
                    '.ui-form-overlay, ' +
                    '.pms-modal-overlay'
                );


            if (
                modal &&
                event.target === modal
            ) {

                closeModal(
                    modal
                );

            }

        }
    );


    /* =========================================================
       ESC CLOSE
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


            document
                .querySelectorAll(
                    '.ui-form-overlay.show, ' +
                    '.pms-modal-overlay.show'
                )
                .forEach(modal => {

                    closeModal(
                        modal
                    );

                });

        }
    );


    /* =========================================================
       INITIAL STATE
    ========================================================= */

    updateSelectedBusData();

    updateAddMaintenanceType();

});