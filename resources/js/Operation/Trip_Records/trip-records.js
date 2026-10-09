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

    const openModal = (data) => {
        if (!data) return;

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

        modalOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modalOverlay.classList.remove('show');
        document.body.style.overflow = '';
    };

    // The modal lives outside <main>; handle its controls on the overlay.
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
