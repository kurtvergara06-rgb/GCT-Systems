const readOldInput = () => {
    const source = document.getElementById('routeValidationOldInput');

    if (!source) {
        return null;
    }

    try {
        return JSON.parse(source.textContent.trim());
    } catch (error) {
        console.error('Unable to restore route form validation state.', error);
        return null;
    }
};

const setValue = (id, value) => {
    const element = document.getElementById(id);

    if (!element || value === undefined || value === null) {
        return;
    }

    element.value = String(value);
};

const restorePlace = (input, prefix, oldInput) => {
    if (!(input instanceof HTMLInputElement)) {
        return;
    }

    const latitude = oldInput[`${prefix}_latitude`];
    const longitude = oldInput[`${prefix}_longitude`];

    if (latitude === undefined || longitude === undefined || latitude === '' || longitude === '') {
        return;
    }

    input.dataset.selectedName = input.value;
    input.dataset.address = String(oldInput[`${prefix}_address`] || input.value || '');
    input.dataset.latitude = String(latitude);
    input.dataset.longitude = String(longitude);
    input.dataset.source = String(oldInput[`${prefix}_source`] || 'Saved Input');

    const wrapper = input.closest('.location-autocomplete');
    const status = wrapper?.querySelector('.location-confirmation-status');

    if (status) {
        status.className = 'location-confirmation-status confirmed';
        status.innerHTML = `
            <i class="fa-solid fa-circle-check"></i>
            <span>Confirmed • ${input.dataset.source}</span>
        `;
    }
};

const restoreStops = (oldInput) => {
    const stops = Array.isArray(oldInput.stops) ? oldInput.stops : [];
    const addresses = Array.isArray(oldInput.stop_addresses) ? oldInput.stop_addresses : [];
    const latitudes = Array.isArray(oldInput.stop_latitudes) ? oldInput.stop_latitudes : [];
    const longitudes = Array.isArray(oldInput.stop_longitudes) ? oldInput.stop_longitudes : [];
    const sources = Array.isArray(oldInput.stop_sources) ? oldInput.stop_sources : [];

    document.querySelectorAll('#routeStopList input[name="stops[]"]').forEach((input, index) => {
        if (!(input instanceof HTMLInputElement)) {
            return;
        }

        if (stops[index] !== undefined) {
            input.value = String(stops[index] || '');
        }

        const latitude = latitudes[index];
        const longitude = longitudes[index];

        if (latitude === undefined || longitude === undefined || latitude === '' || longitude === '') {
            return;
        }

        input.dataset.selectedName = input.value;
        input.dataset.address = String(addresses[index] || input.value || '');
        input.dataset.latitude = String(latitude);
        input.dataset.longitude = String(longitude);
        input.dataset.source = String(sources[index] || 'Saved Input');

        const wrapper = input.closest('.location-autocomplete');
        const status = wrapper?.querySelector('.location-confirmation-status');

        if (status) {
            status.className = 'location-confirmation-status confirmed';
            status.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <span>Confirmed • ${input.dataset.source}</span>
            `;
        }
    });
};

const restoreHiddenFields = (oldInput) => {
    setValue('routeOriginAddress', oldInput.origin_address);
    setValue('routeOriginLatitude', oldInput.origin_latitude);
    setValue('routeOriginLongitude', oldInput.origin_longitude);
    setValue('routeOriginSource', oldInput.origin_source);

    setValue('routeDestinationAddress', oldInput.destination_address);
    setValue('routeDestinationLatitude', oldInput.destination_latitude);
    setValue('routeDestinationLongitude', oldInput.destination_longitude);
    setValue('routeDestinationSource', oldInput.destination_source);

    setValue('routeCalculatedDistance', oldInput.calculated_distance_km);
    setValue('routeCalculatedTime', oldInput.calculated_time_minutes);
    setValue('routeDistanceSource', oldInput.distance_source);
    setValue('routeDistanceManual', oldInput.distance_is_manual ?? '0');
    setValue('routeTimeManual', oldInput.time_is_manual ?? '0');
    setValue('routeGeometry', oldInput.route_geometry);
};

const restoreVisibleFields = (oldInput) => {
    setValue('routeName', oldInput.route_name);
    setValue('routeOrigin', oldInput.origin);
    setValue('routeDestination', oldInput.destination);
    setValue('routeDistance', oldInput.distance_km);
    setValue('routeTime', oldInput.estimated_time_minutes);
    setValue('routeStatus', oldInput.status || 'Active');
};

const focusFirstError = () => {
    const error = document.querySelector('#routeModal .ui-field-error');
    const field = error
        ?.closest('.ui-form-group')
        ?.querySelector('input:not([type="hidden"]), select, textarea');

    if (field instanceof HTMLElement) {
        window.setTimeout(() => field.focus(), 100);
    }
};

const recoverRouteValidation = () => {
    const oldInput = readOldInput();
    const openButton = document.getElementById('openRouteModal');
    const routeModal = document.getElementById('routeModal');

    if (!oldInput || !openButton || !routeModal) {
        return;
    }

    const legacyValidationModal = document.getElementById('routeValidationModal');

    if (legacyValidationModal) {
        legacyValidationModal.classList.remove('show', 'active');
        legacyValidationModal.setAttribute('aria-hidden', 'true');
    }

    // Use the existing New Route handler so the normal modal/map initialization
    // still runs, then restore Laravel's old input after the form reset.
    openButton.click();

    window.setTimeout(() => {
        restoreVisibleFields(oldInput);
        restoreHiddenFields(oldInput);

        const origin = document.getElementById('routeOrigin');
        const destination = document.getElementById('routeDestination');

        restorePlace(origin, 'origin', oldInput);
        restorePlace(destination, 'destination', oldInput);
        restoreStops(oldInput);

        const recalculate = document.getElementById('recalculateRouteBtn');
        const hasOrigin = origin?.dataset.latitude && origin?.dataset.longitude;
        const hasDestination = destination?.dataset.latitude && destination?.dataset.longitude;

        if (recalculate && hasOrigin && hasDestination) {
            recalculate.disabled = false;
            recalculate.click();
        }

        focusFirstError();
    }, 220);
};

if (document.readyState === 'complete') {
    recoverRouteValidation();
} else {
    window.addEventListener('load', recoverRouteValidation, { once: true });
}
