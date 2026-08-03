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

const initAutoSchedulingResolutionUx = () => {
    if (!window.location.pathname.includes('/operation/auto-scheduling')) {
        return;
    }

    if (window.__autoSchedulingResolutionUxInitialized) {
        return;
    }

    window.__autoSchedulingResolutionUxInitialized = true;

    const nativeAlert = window.alert.bind(window);

    window.alert = (message) => {
        const text = String(message ?? '');

        if (/^Trip\s+.+\s+was resolved successfully\.?$/i.test(text)) {
            window.showSystemToast(
                text,
                'success',
                'AI resolution applied',
                { timeout: 5000 }
            );
            return;
        }

        nativeAlert(message);
    };

    const improveResolutionCopy = () => {
        document
            .querySelectorAll(
                '.ai-action-item span, '
                + '.ai-resolution-description, '
                + '.ai-resolution-note p'
            )
            .forEach((element) => {
                element.textContent = String(element.textContent || '')
                    .replaceAll(
                        'Laravel must recheck',
                        'The system will recheck'
                    )
                    .replaceAll(
                        'Laravel will check',
                        'The system will verify'
                    );
            });

        const applyButton = document.getElementById(
            'applyAiResolutionButton'
        );

        if (
            applyButton
            && !applyButton.disabled
            && !/applying/i.test(applyButton.textContent || '')
        ) {
            applyButton.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                Confirm & Apply Resolution
            `;
        }
    };

    improveResolutionCopy();

    const observer = new MutationObserver(
        improveResolutionCopy
    );

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

document.addEventListener('DOMContentLoaded', () => {
    initSystemToasts();
    initAutoSchedulingResolutionUx();
});

document.addEventListener('turbo:load', () => {
    initSystemToasts();
    initAutoSchedulingResolutionUx();
});

window.addEventListener('load', () => {
    initSystemToasts();
    initAutoSchedulingResolutionUx();
});
