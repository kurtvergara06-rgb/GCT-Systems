import '../../echo';

window.GCTPartialNavigation.registerInitializer('operation-bus-master-list', '.bus-master-list-page', () => {

    // Popovers live in the AJAX-swapped records region: bind per initializer run.
    const compactFilters = document.getElementById('busListFilters');
    if (compactFilters) {
        const tools = Array.from(compactFilters.querySelectorAll('[data-bus-tool]'));
        const closeTools = () => {
            tools.forEach((tool) => {
                tool.querySelector('.bus-tool-popover')?.setAttribute('hidden', '');
                tool.querySelector('[data-bus-tool-trigger]')?.setAttribute('aria-expanded', 'false');
            });
        };
        const syncToolbarTriggerStates = () => {
            const searchInput = compactFilters.querySelector('input[name="search"]');
            const searchTrigger = compactFilters.querySelector('[data-bus-tool-trigger="search"]');
            if (searchTrigger && searchInput) {
                searchTrigger.classList.toggle('bus-tool-active', searchInput.value.trim() !== '');
            }

            const statusSelect = compactFilters.querySelector('[name="status"]');
            const filterBadge = compactFilters.querySelector('[data-bus-filter-count]');
            const hasStatus = Boolean(statusSelect && statusSelect.value && statusSelect.value !== 'All Status');
            const statusBox = compactFilters.querySelector('#busStatusFilter')?.closest('.bus-filter-select-box');
            if (statusBox && statusSelect) {
                const statusTone = statusSelect.value === 'Active' ? 'active'
                    : statusSelect.value === 'Under Maintenance' ? 'maintenance'
                    : statusSelect.value === 'Inactive' ? 'inactive' : 'all';
                statusBox.dataset.statusTone = statusTone;
                const label = compactFilters.querySelector('.bus-filter-label');
                if (label) label.dataset.statusTone = statusTone;
            }
            if (filterBadge) {
                filterBadge.textContent = hasStatus ? '1' : '0';
                filterBadge.hidden = !hasStatus;
            }

            const sortSelect = compactFilters.querySelector('[name="sort"]');
            const directionSelect = compactFilters.querySelector('[name="direction"]');
            const sortTrigger = compactFilters.querySelector('[data-bus-tool-trigger="sort"]');
            if (sortTrigger) {
                const isNonDefaultSort = (sortSelect && sortSelect.value !== 'bus_no')
                    || (directionSelect && directionSelect.value !== 'asc');
                sortTrigger.classList.toggle('bus-tool-active', Boolean(isNonDefaultSort));
            }
        };

        tools.forEach((tool) => {
            const trigger = tool.querySelector('[data-bus-tool-trigger]');
            const popover = tool.querySelector('.bus-tool-popover');
            trigger?.addEventListener('click', () => {
                const opening = popover?.hasAttribute('hidden');
                closeTools();
                if (opening) {
                    popover?.removeAttribute('hidden');
                    trigger.setAttribute('aria-expanded', 'true');
                    if (tool.dataset.busTool === 'search') popover.querySelector('input')?.focus();
                }
            });
        });
        const onOutside = (event) => {
            if (!compactFilters.isConnected) {
                document.removeEventListener('pointerdown', onOutside);
                return;
            }
            if (!compactFilters.contains(event.target)) closeTools();
        };
        document.addEventListener('pointerdown', onOutside);
        const onEscape = (event) => {
            if (!compactFilters.isConnected) {
                document.removeEventListener('keydown', onEscape);
                return;
            }
            if (event.key === 'Escape') closeTools();
        };
        document.addEventListener('keydown', onEscape);
        compactFilters.querySelector('[data-bus-clear-status]')?.addEventListener('click', () => {
            const status = compactFilters.querySelector('#busStatusFilter');
            if (status) status.value = 'All Status';
            compactFilters.requestSubmit();
        });
        compactFilters.querySelector('[data-bus-reset-sort]')?.addEventListener('click', () => {
            const sort = compactFilters.querySelector('#busSortField');
            const direction = compactFilters.querySelector('#busSortDirection');
            if (sort) sort.value = 'bus_no';
            if (direction) direction.value = 'asc';
            compactFilters.requestSubmit();
        });
        compactFilters.querySelectorAll('[data-bus-popover-close]').forEach((button) => {
            button.addEventListener('click', closeTools);
        });
        compactFilters.addEventListener('input', syncToolbarTriggerStates);
        compactFilters.addEventListener('change', syncToolbarTriggerStates);
        syncToolbarTriggerStates();
    }

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
            fleetPurpose: button.dataset.fleetPurpose,
            routeGrouping: button.dataset.displayRoute || button.dataset.routeGrouping,
            clientAllocation: button.dataset.clientAllocationType
                ? button.dataset.clientAllocationType + ' — ' + (button.dataset.clientNames || '')
                : 'Not allocated',
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

                const allocationType = document.getElementById('edit_client_allocation_type');
                const companyNames = document.getElementById('edit_client_names');
                if (allocationType) allocationType.value = button.dataset.clientAllocationType || '';
                if (companyNames) companyNames.value = button.dataset.clientNames || '';

                if (editBusNo) {
                    editBusNo.value = button.dataset.busNo || '';
                    const locked = button.dataset.busNoLocked === '1';
                    editBusNo.readOnly = locked;
                    editBusNo.classList.toggle('bus-locked-input', locked);
                    editBusNo.setAttribute('aria-readonly', locked ? 'true' : 'false');
                    editBusNo.title = locked
                        ? 'Bus No. cannot be changed after operational history exists.'
                        : '';
                    const hint = document.getElementById('editBusNoLockHint');
                    if (hint) hint.hidden = !locked;
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
                const editFleetPurpose = document.getElementById('edit_fleet_purpose');
                if (editFleetPurpose) editFleetPurpose.value = button.dataset.fleetPurpose || 'Regular';


                // Operation cannot edit any master-list field while Maintenance
                // owns this bus. Preserve displayed values for read-only review.
                const maintenanceLocked = button.dataset.status === 'Under Maintenance';
                editBusForm.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((field) => {
                    if (maintenanceLocked) {
                        field.dataset.busMaintenanceLocked = '1';
                        if (field.matches('select')) {
                            field.disabled = true;
                        } else {
                            field.readOnly = true;
                        }
                        field.classList.add('bus-maintenance-locked');
                    } else if (field.dataset.busMaintenanceLocked === '1') {
                        field.removeAttribute('data-bus-maintenance-locked');
                        field.disabled = false;
                        field.readOnly = field.id === 'edit_route_grouping';
                        field.classList.remove('bus-maintenance-locked');
                    }
                });
                // Route / Grouping is always view-only, for both Regular and Reserve buses.
                if (editRouteGrouping) {
                    editRouteGrouping.readOnly = true;
                    editRouteGrouping.setAttribute('aria-readonly', 'true');
                }
                // Reapply the independent history-based Bus No. restriction.
                if (!maintenanceLocked && editBusNo) {
                    editBusNo.readOnly = button.dataset.busNoLocked === '1';
                }
                const fleetPurposeLocked = button.dataset.fleetPurposeLocked === '1';
                if (editFleetPurpose) {
                    editFleetPurpose.disabled = maintenanceLocked || fleetPurposeLocked;
                    editFleetPurpose.classList.toggle('bus-maintenance-locked', maintenanceLocked || fleetPurposeLocked);
                    editFleetPurpose.title = fleetPurposeLocked
                        ? 'Bus Purpose cannot change while an ongoing or future trip assignment exists.'
                        : '';
                }
                const fleetPurposeHint = document.getElementById('editFleetPurposeLockHint');
                if (fleetPurposeHint) fleetPurposeHint.hidden = !fleetPurposeLocked;
                const updateButton = editBusForm.querySelector('button[type="submit"]');
                if (updateButton) {
                    updateButton.disabled = maintenanceLocked;
                    updateButton.hidden = maintenanceLocked;
                }
                const lockNotice = document.getElementById('editBusMaintenanceNotice');
                if (lockNotice) lockNotice.hidden = !maintenanceLocked;

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
        closeModal(viewBusModal);
        viewBusModal?.setAttribute('aria-hidden', 'true');
    });

});
