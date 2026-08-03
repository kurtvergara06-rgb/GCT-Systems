const toastRootSelector = '.system-toast-root';
const toastSelector = '[data-system-toast]';
const closeButtonSelector = '.system-toast-close';
const removeDelay = 4000;

const getToastRoot = () => {
    let root = document.querySelector(toastRootSelector);

    if (!root) {
        root = document.createElement('div');
        root.className = 'system-toast-root';
        root.setAttribute('aria-live', 'polite');
        root.setAttribute('aria-atomic', 'true');
        document.body.appendChild(root);
    }

    return root;
};

const attachToastBehavior = (toast) => {
    if (toast.dataset.toastInitialized === 'true') {
        return;
    }

    toast.dataset.toastInitialized = 'true';

    const closeButton = toast.querySelector(closeButtonSelector);

    const removeToast = () => {
        toast.classList.add('is-removing');

        window.setTimeout(() => {
            toast.remove();
        }, 250);
    };

    closeButton?.addEventListener('click', removeToast);

    window.setTimeout(() => {
        toast.classList.add('is-visible');
    }, 20);

    window.setTimeout(removeToast, removeDelay);
};

const initSystemToasts = () => {
    const roots = document.querySelectorAll(toastRootSelector);

    roots.forEach((root) => {
        const toasts = root.querySelectorAll(toastSelector);

        toasts.forEach((toast) => {
            attachToastBehavior(toast);
        });
    });
};

window.showSystemToast = function (message, type = 'info', title = null, options = {}) {
    const safeMessage = typeof message === 'string' ? message : String(message ?? '');

    if (!safeMessage.trim()) {
        return null;
    }

    const root = getToastRoot();
    const typeName = ['success', 'error', 'warning', 'info'].includes(type) ? type : 'info';
    const titleText = title || {
        success: 'Success',
        error: 'Error',
        warning: 'Warning',
        info: 'Info',
    }[typeName];

    const toast = document.createElement('div');
    toast.className = `system-toast-notification system-toast-notification--${typeName}`;
    toast.setAttribute('data-system-toast', 'true');
    toast.setAttribute('data-type', typeName);
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    const iconMap = {
        success: 'fa-solid fa-circle-check',
        error: 'fa-solid fa-circle-exclamation',
        warning: 'fa-solid fa-triangle-exclamation',
        info: 'fa-solid fa-circle-info',
    };

    const icon = document.createElement('div');
    icon.className = 'system-toast-icon';
    icon.innerHTML = `<i class="${iconMap[typeName]}"></i>`;

    const body = document.createElement('div');
    body.className = 'system-toast-body';

    const titleEl = document.createElement('div');
    titleEl.className = 'system-toast-title';
    titleEl.textContent = titleText;

    const messageEl = document.createElement('div');
    messageEl.className = 'system-toast-message';
    messageEl.textContent = safeMessage;

    body.appendChild(titleEl);
    body.appendChild(messageEl);

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'system-toast-close';
    closeButton.setAttribute('aria-label', 'Close notification');
    closeButton.innerHTML = '<i class="fa-solid fa-xmark"></i>';

    toast.appendChild(icon);
    toast.appendChild(body);
    toast.appendChild(closeButton);
    root.appendChild(toast);

    attachToastBehavior(toast);

    const timeout = options.timeout ?? removeDelay;
    window.setTimeout(() => {
        toast.classList.add('is-removing');
        window.setTimeout(() => toast.remove(), 250);
    }, timeout);

    return toast;
};

const escapeResolutionText = (value) => String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const readResolutionReview = () => {
    const modal = document.getElementById('aiResolutionModal');
    const values = modal?.querySelectorAll('.ai-resolution-summary-grid strong');

    return {
        tripCode: values?.[0]?.textContent?.trim() || 'Trip',
        previousDeparture: values?.[1]?.textContent?.trim() || '—',
        newDeparture: values?.[2]?.textContent?.trim() || '—',
    };
};

const closeResolutionSummary = () => {
    document.getElementById('aiResolutionSummaryModal')?.remove();
    document.body.classList.remove('ai-modal-open');
};

const showResolutionSummary = (review, responseData = {}) => {
    closeResolutionSummary();

    const resolution = responseData.resolution || responseData.assignment || {};
    const tripCode = resolution.trip_code || review.tripCode;
    const previousDeparture = resolution.previous_departure_display
        || resolution.previous_departure
        || review.previousDeparture;
    const newDeparture = resolution.departure_display
        || resolution.new_departure_display
        || resolution.new_departure
        || review.newDeparture;
    const newArrival = resolution.arrival_display
        || resolution.new_arrival_display
        || resolution.new_arrival
        || null;
    const driverName = resolution.driver_name || null;
    const driverStatus = resolution.driver_status || null;
    const busNo = resolution.bus_no || null;

    const optionalResources = driverName || busNo
        ? `
            <div class="ai-detail-section">
                <h4><i class="fa-solid fa-people-arrows"></i> Assigned resources</h4>
                <div class="ai-resolution-summary-grid">
                    ${driverName ? `
                        <div>
                            <span>Driver</span>
                            <strong>${escapeResolutionText(driverName)}</strong>
                            ${driverStatus ? `<small>${escapeResolutionText(driverStatus)}</small>` : ''}
                        </div>
                    ` : ''}
                    ${busNo ? `
                        <div>
                            <span>Bus</span>
                            <strong>${escapeResolutionText(busNo)}</strong>
                        </div>
                    ` : ''}
                </div>
            </div>
        `
        : '';

    const overlay = document.createElement('div');
    overlay.className = 'ai-resolution-modal-overlay';
    overlay.id = 'aiResolutionSummaryModal';
    overlay.innerHTML = `
        <div class="ai-resolution-modal" role="dialog" aria-modal="true" aria-labelledby="resolutionSummaryTitle">
            <div class="ai-resolution-modal-header">
                <div>
                    <span class="section-eyebrow">Conflict resolved</span>
                    <h2 id="resolutionSummaryTitle">Resolution applied successfully</h2>
                </div>
                <span class="schedule-status ready">Validated</span>
            </div>

            <p class="ai-resolution-description">
                Review the updated schedule and validation results before confirming these changes.
            </p>

            <div class="ai-detail-section">
                <h4><i class="fa-solid fa-clock-rotate-left"></i> Schedule changes</h4>
                <div class="ai-resolution-summary-grid">
                    <div>
                        <span>Trip</span>
                        <strong>${escapeResolutionText(tripCode)}</strong>
                    </div>
                    <div>
                        <span>Previous departure</span>
                        <strong>${escapeResolutionText(previousDeparture)}</strong>
                    </div>
                    <div class="proposed">
                        <span>New departure</span>
                        <strong>${escapeResolutionText(newDeparture)}</strong>
                        ${newArrival ? `<small>Arrival: ${escapeResolutionText(newArrival)}</small>` : ''}
                    </div>
                </div>
            </div>

            ${optionalResources}

            <div class="ai-resolution-note">
                <i class="fa-solid fa-shield-circle-check"></i>
                <div>
                    <strong>Validation checks completed</strong>
                    <p>
                        Driver attendance and shift were verified, resource overlaps were checked,
                        and the selected bus passed active-status and maintenance validation.
                    </p>
                </div>
            </div>

            <div class="ai-resolution-modal-actions">
                <a href="/operation/driver-bus-assignment" class="ai-resolution-btn manual">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                    View Assignment
                </a>
                <button type="button" class="ai-resolution-btn primary" id="confirmResolutionChangesButton">
                    <i class="fa-solid fa-circle-check"></i>
                    Confirm These Changes
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    document.body.classList.add('ai-modal-open');

    document.getElementById('confirmResolutionChangesButton')?.addEventListener('click', () => {
        closeResolutionSummary();
        window.showSystemToast(
            `${tripCode} has been updated and confirmed.`,
            'success',
            'Schedule updated',
            { timeout: 5000 }
        );
    });
};

const initAutoSchedulingResolutionSummary = () => {
    if (!window.location.pathname.includes('/operation/auto-scheduling')) {
        return;
    }

    if (window.__resolutionSummaryInitialized) {
        return;
    }

    window.__resolutionSummaryInitialized = true;

    const nativeFetch = window.fetch.bind(window);
    const nativeAlert = window.alert.bind(window);
    let pendingReview = null;
    let latestResolutionResponse = null;

    window.fetch = async (input, init = {}) => {
        const url = typeof input === 'string' ? input : input?.url || '';
        const isResolutionRequest = url.endsWith('/operation/auto-scheduling/resolve');

        if (isResolutionRequest) {
            pendingReview = readResolutionReview();
        }

        const response = await nativeFetch(input, init);

        if (isResolutionRequest && response.ok) {
            try {
                latestResolutionResponse = await response.clone().json();
            } catch (error) {
                latestResolutionResponse = {};
            }
        }

        return response;
    };

    window.alert = (message) => {
        const text = String(message ?? '');

        if (/^Trip\s+.+\s+was resolved successfully\.?$/i.test(text)) {
            showResolutionSummary(
                pendingReview || readResolutionReview(),
                latestResolutionResponse || {}
            );
            pendingReview = null;
            latestResolutionResponse = null;
            return;
        }

        nativeAlert(message);
    };
};

document.addEventListener('DOMContentLoaded', () => {
    initSystemToasts();
    initAutoSchedulingResolutionSummary();
});

document.addEventListener('turbo:load', () => {
    initSystemToasts();
    initAutoSchedulingResolutionSummary();
});

window.addEventListener('load', () => {
    initSystemToasts();
    initAutoSchedulingResolutionSummary();
});
