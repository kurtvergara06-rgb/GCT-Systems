document.addEventListener('DOMContentLoaded', () => {
    const routeModal = document.getElementById('routeModal');
    const routeOrigin = document.getElementById('routeOrigin');
    const routeDestination = document.getElementById('routeDestination');
    const routeStopList = document.getElementById('routeStopList');
    const mapElement = document.getElementById('routeFormGpsMap');
    const existingPinButton = document.getElementById('pinActiveLocationBtn');
    const activeFieldLabel = document.getElementById('routeMapActiveField');

    if (!routeModal || !routeOrigin || !routeDestination || !existingPinButton) {
        return;
    }

    const roleFor = (input) => {
        if (input === routeOrigin) return 'Origin';
        if (input === routeDestination) return 'Destination';
        return input?.dataset?.role || 'Stop';
    };

    const clearActiveState = () => {
        routeModal
            .querySelectorAll('.route-location-active')
            .forEach((element) => element.classList.remove('route-location-active'));
    };

    const markActive = (input) => {
        clearActiveState();
        const target = input.closest('.ui-form-group') || input.closest('.route-stop-item');
        target?.classList.add('route-location-active');
    };

    const beginPinning = (input) => {
        if (!(input instanceof HTMLInputElement)) return;

        input.focus();
        input.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        markActive(input);

        if (activeFieldLabel) {
            activeFieldLabel.innerHTML = `
                <i class="fa-solid fa-location-crosshairs"></i>
                Pinning: ${roleFor(input)}
            `;
        }

        if (!existingPinButton.disabled) existingPinButton.click();

        window.setTimeout(() => {
            mapElement?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 80);
    };

    const createPinButton = (input, compact = false) => {
        if (!(input instanceof HTMLInputElement)) return;

        const host = compact
            ? input.closest('.route-stop-location-field')
            : input.closest('.ui-input-wrap');

        if (!host || host.querySelector(':scope > .route-direct-pin-btn')) return;

        host.classList.add('route-location-pin-host');

        const button = document.createElement('button');
        button.type = 'button';
        button.className = `route-direct-pin-btn${compact ? ' is-stop-pin' : ''}`;
        button.title = `Pin ${roleFor(input)} on map`;
        button.setAttribute('aria-label', `Pin ${roleFor(input)} on map`);
        button.innerHTML = compact
            ? '<i class="fa-solid fa-thumbtack"></i>'
            : '<i class="fa-solid fa-thumbtack"></i><span>Pin</span>';

        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => beginPinning(input));
        host.appendChild(button);

        input.addEventListener('focus', () => markActive(input));
        input.addEventListener('click', () => markActive(input));
    };

    const enhanceStopInputs = () => {
        routeStopList
            ?.querySelectorAll('input[name="stops[]"]')
            .forEach((input) => createPinButton(input, true));
    };

    createPinButton(routeOrigin);
    createPinButton(routeDestination);
    enhanceStopInputs();

    if (routeStopList) {
        const observer = new MutationObserver(enhanceStopInputs);
        observer.observe(routeStopList, { childList: true, subtree: true });
    }

    existingPinButton.innerHTML = `
        <i class="fa-solid fa-thumbtack"></i>
        Pin Selected Field
    `;
});
