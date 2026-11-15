import '../../../css/Operation/Scheduling_And_Dispatch/trip-toolbar-alignment.css';

function alignTripScheduleToolbar() {
    const page = document.querySelector('.trip-schedule-page');
    const toolbar = page?.querySelector('.trip-toolbar');
    const actions = page?.querySelector('.trip-card-header .ui-form-actions');

    if (!toolbar || !actions || toolbar.contains(actions)) {
        return;
    }

    actions.classList.add('trip-toolbar-actions');
    toolbar.appendChild(actions);
}

window.GCTPartialNavigation.registerInitializer(
    'operation-trip-toolbar-alignment',
    '.trip-schedule-page',
    alignTripScheduleToolbar,
);
