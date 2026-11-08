window.GCTPartialNavigation.registerInitializer('operation-trip-records', '.trip-records-page', () => {
    const page = document.querySelector('.trip-records-page');
    const modalOverlay = document.getElementById('tripDetailModal');
    if (!page || !modalOverlay) return;

    window.__gctTripRecordsAbortController?.abort();
    const abortController = new AbortController();
    const { signal } = abortController;
    window.__gctTripRecordsAbortController = abortController;

    // Fields
    const elTripCode = document.getElementById('modalTripCode');
    const elStatus = document.getElementById('modalStatus');
    const elTripDate = document.getElementById('modalTripDate');
    const elShift = document.getElementById('modalShift');
    const elRouteName = document.getElementById('modalRouteName');
    const elRouteSpan = document.getElementById('modalRouteSpan');
    const elDistance = document.getElementById('modalDistance');
    const elEstTime = document.getElementById('modalEstTime');
    const elBusNo = document.getElementById('modalBusNo');
    const elBusDetails = document.getElementById('modalBusDetails');
    const elDriverName = document.getElementById('modalDriverName');
    const elDriverId = document.getElementById('modalDriverId');
    const elSchedDept = document.getElementById('modalSchedDept');
    const elSchedArr = document.getElementById('modalSchedArr');
    const elActualDept = document.getElementById('modalActualDept');
    const elActualArr = document.getElementById('modalActualArr');
    const elDuration = document.getElementById('modalDuration');
    const elNotes = document.getElementById('modalNotes');
    const elRequiredSlots = document.getElementById('modalRequiredSlots');
    const elAssignedSlots = document.getElementById('modalAssignedSlots');
    const elAssignmentOutcome = document.getElementById('modalAssignmentOutcome');

    const openModal = (data) => {
        if (!data) return;
        const rows = modalOverlay.querySelector('#actualTripUnitRows');
        if (rows) {
            rows.replaceChildren();
            const assignments = Array.isArray(data.assignments) ? data.assignments : [];
            if (!assignments.length) {
                const row = rows.insertRow();
                const cell = row.insertCell();
                cell.colSpan = 5;
                cell.textContent = 'No saved bus or driver assignments for this historical trip.';
            } else {
                assignments.forEach((assignment, index) => {
                    const row = rows.insertRow();
                    [
                        'Slot ' + (assignment.slot_number || (index + 1)),
                        [assignment.bus_no, assignment.plate_no, assignment.bus_model].filter(Boolean).join(' • ') || '—',
                        [assignment.driver_name, assignment.driver_id].filter(Boolean).join(' • ') || '—',
                        data.departure_time || '—',
                        data.status || '—',
                    ].forEach(value => { row.insertCell().textContent = value; });
                });
            }
        }

        if (elRequiredSlots) elRequiredSlots.textContent = data.required_buses ?? '—';
        if (elAssignedSlots) elAssignedSlots.textContent = data.assigned_buses ?? '—';
        if (elAssignmentOutcome) elAssignmentOutcome.textContent = data.assignment_outcome || '—';
        // Primary trip summary uses saved schedule and assignment data only.
        const overview = {
            tripOverviewDate: data.trip_date || '—',
            tripOverviewDeparture: data.departure_time || '—',
            tripOverviewArrival: data.estimated_arrival_time || '—',
            tripOverviewRoute: [data.route_code, data.route_name].filter(Boolean).join(' · ') || 'Route unavailable',
            tripOverviewCorridor: [data.origin, data.destination].filter(Boolean).join(' → '),
            tripOverviewSlots: `${data.assigned_buses ?? 0} / ${data.required_buses ?? 0} buses`,
            tripOverviewOutcome: data.assignment_outcome || '—',
        };
        Object.entries(overview).forEach(([id, value]) => {
            const node = modalOverlay.querySelector('#' + id);
            if (node) node.textContent = value;
        });
        const assignedCount = modalOverlay.querySelector('#tripOverviewAssignedCount');
        if (assignedCount) {
            const count = Array.isArray(data.assignments) ? data.assignments.length : 0;
            assignedCount.textContent = `${count} ${count === 1 ? 'unit' : 'units'}`;
        }

        const ddrBody = modalOverlay.querySelector('#modalDdrTimeRows');
        if (ddrBody) {
            ddrBody.replaceChildren();
            const ddrTimes = Array.isArray(data.ddr_times) ? data.ddr_times : [];
            const hasDdr = ddrTimes.length > 0;
            const recordedSection = modalOverlay.querySelector('#tripDdrRecordedSection');
            const emptySection = modalOverlay.querySelector('#tripDdrEmptyState');
            const emptyText = modalOverlay.querySelector('#tripDdrEmptyText');
            if (recordedSection) recordedSection.hidden = !hasDdr;
            if (emptySection) emptySection.hidden = hasDdr;
            if (!hasDdr) {
                const status = String(data.status || '').toLowerCase();
                const message = ['completed'].includes(status)
                    ? 'This trip is marked Completed, but no linked DDR was found. Verification may be required.'
                    : ['in progress', 'ongoing', 'dispatched', 'pending ddr'].includes(status)
                        ? 'Awaiting driver-reported actual departure and arrival times.'
                        : 'Actual trip times will be available after DDR submission.';
                if (emptyText) emptyText.textContent = message;
            } else {
                ddrTimes.forEach(item => {
                    const row = ddrBody.insertRow();
                    [
                        item.ddr_no || '—',
                        [item.driver, item.bus, item.source].filter(Boolean).join(' • '),
                        item.departure || '—',
                        item.arrival || '—',
                    ].forEach(value => { row.insertCell().textContent = value; });
                });
            }
        }
        if (elTripCode) elTripCode.textContent = data.trip_code || '—';
        
        if (elStatus) {
            elStatus.textContent = data.status || '—';
            elStatus.className = 'trip-status ' + (data.status ? data.status.toLowerCase() : 'scheduled');
        }

        if (elTripDate) elTripDate.textContent = data.trip_date || '—';
        if (elShift) elShift.textContent = data.shift || '—';

        if (elRouteName) elRouteName.textContent = data.route_name ? `${data.route_code || ''} - ${data.route_name}` : '—';
        if (elRouteSpan) elRouteSpan.textContent = (data.origin && data.destination) ? `${data.origin} → ${data.destination}` : '—';
        if (elDistance) elDistance.textContent = data.distance_km ? `${data.distance_km} km` : '—';
        if (elEstTime) elEstTime.textContent = data.estimated_time_minutes ? `${data.estimated_time_minutes} mins` : '—';

        if (elBusNo) elBusNo.textContent = data.plate_no || 'No plate recorded';
        if (elBusDetails) elBusDetails.textContent = [data.bus_no ? `Internal Bus: ${data.bus_no}` : null, data.bus_model].filter(Boolean).join(' • ') || '—';

        if (elDriverName) elDriverName.textContent = data.driver_name || '—';
        if (elDriverId) elDriverId.textContent = data.driver_id ? `ID: ${data.driver_id}` : '';

        if (elSchedDept) elSchedDept.textContent = data.departure_time || '—';
        if (elSchedArr) elSchedArr.textContent = data.estimated_arrival_time || '—';
        if (elActualDept) elActualDept.textContent = data.actual_departure_time || '—';
        if (elActualArr) elActualArr.textContent = data.actual_arrival_time || '—';
        if (elDuration) elDuration.textContent = data.actual_duration_minutes ? `${data.actual_duration_minutes} mins` : '—';
        if (elNotes) elNotes.textContent = data.notes || 'No notes logged for this trip record.';

        // Contextualize sample corridor in preview table if corridor data exists
        if (data.origin && data.destination) {
            const corridorText = `${data.origin} → ${data.destination}`;
            modalOverlay.querySelectorAll('.unit-corridor-span').forEach((el) => {
                el.textContent = corridorText;
            });
        }

        // Reset search input and row visibility
        const searchInput = modalOverlay.querySelector('#unitRecordSearchInput');
        if (searchInput) {
            searchInput.value = '';
            const numberOfUnits = Array.isArray(data.assignments) ? data.assignments.length : 0;
            const searchControl = searchInput.closest('.unit-records-controls');
            if (searchControl) searchControl.hidden = numberOfUnits < 5;
            filterDemoRows('');
        }

        modalOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';

        const detailBody = modalOverlay.querySelector('.trip-detail-body');
        if (detailBody) {
            detailBody.scrollTop = 0;
        }
    };

    const closeModal = () => {
        modalOverlay.classList.remove('show');
        document.body.style.overflow = '';
    };

    // Client-side search filtering for sample demonstration units
    const filterDemoRows = (query) => {
        const term = (query || '').toLowerCase().trim();
        const rows = modalOverlay.querySelectorAll('.unit-records-table tbody tr');
        let visibleCount = 0;

        rows.forEach((row) => {
            const searchData = (row.getAttribute('data-search') || '').toLowerCase();
            const textContent = row.textContent.toLowerCase();
            if (!term || searchData.includes(term) || textContent.includes(term)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const countEl = modalOverlay.querySelector('#unitTableEntriesCount');
        if (countEl) {
            countEl.textContent = `Showing ${visibleCount} of ${rows.length} entries`;
        }
    };

    const searchInput = modalOverlay.querySelector('#unitRecordSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            filterDemoRows(e.target.value);
        }, { signal });
    }

    // The modal lives outside <main>; close it only using explicit controls.
    modalOverlay.addEventListener('click', (event) => {
        if (event.target.closest('.trip-modal-close, .trip-modal-dismiss')) {
            closeModal();
        }
    }, { signal });

    page.addEventListener('click', (event) => {
        const viewButton = event.target.closest('.view-trip-btn');
        if (viewButton && page.contains(viewButton)) {
            const raw = viewButton.getAttribute('data-trip');
            if (!raw) return;

            try {
                const data = JSON.parse(raw);
                openModal(data);
            } catch (err) {
                console.error('Failed to parse trip record data', err);
            }
            return;
        }

        const closeButton = event.target.closest('.trip-modal-close, .trip-modal-dismiss');
        if (closeButton && modalOverlay.contains(closeButton)) {
            closeModal();
        }
    }, { signal });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalOverlay.classList.contains('show')) {
            closeModal();
        }
    }, { signal });
});

if (!window.__gctTripRecordsRealtimeReinitBound) {
    window.__gctTripRecordsRealtimeReinitBound = true;

    window.addEventListener(
        'system-regions-refreshed',
        (event) => {
            if (!document.querySelector('.trip-records-page')) {
                return;
            }

            const regions = Array.isArray(event.detail?.regions)
                ? event.detail.regions
                : [];

            if (!regions.some((name) => ["summary","records"].includes(name))) {
                return;
            }

            window.dispatchEvent(
                new CustomEvent(
                    'gct:navigation-ready',
                    {
                        detail: {
                            source: 'realtime-regions',
                        },
                    }
                )
            );
        }
    );
}
