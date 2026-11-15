document.addEventListener('DOMContentLoaded', () => {
    const modalOverlay = document.getElementById('tripDetailModal');
    if (!modalOverlay) return;

    const closeBtn = modalOverlay.querySelector('.trip-modal-close');
    const dismissBtn = modalOverlay.querySelector('.trip-modal-dismiss');

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

        if (elBusNo) elBusNo.textContent = data.bus_no || '—';
        if (elBusDetails) elBusDetails.textContent = [data.plate_no, data.bus_model].filter(Boolean).join(' • ') || '—';

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

    document.querySelectorAll('.view-trip-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            const raw = btn.getAttribute('data-trip');
            if (!raw) return;
            try {
                const data = JSON.parse(raw);
                openModal(data);
            } catch (err) {
                console.error('Failed to parse trip record data', err);
            }
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (dismissBtn) dismissBtn.addEventListener('click', closeModal);

    modalOverlay.addEventListener('click', (e) => {
        if (e.target === modalOverlay) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalOverlay.classList.contains('show')) {
            closeModal();
        }
    });
});

