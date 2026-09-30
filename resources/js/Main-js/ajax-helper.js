/**
 * GCT-Systems Reusable AJAX Helper
 * Handles unified fetch requests, form submissions, CSRF, loading state,
 * error handling, toast notifications, and partial region refresh.
 */

const getCsrfToken = () => {
    return (
        document.querySelector('meta[name="csrf-token"]')?.content ||
        document.querySelector('input[name="_token"]')?.value ||
        ''
    );
};

let activeRefreshPromise = null;
let lastRefreshTime = 0;

/**
 * Perform a unified AJAX request.
 * @param {string} url
 * @param {Object} options
 * @returns {Promise<{ ok: boolean, data?: any, error?: string, status?: number }>}
 */
async function ajaxRequest(url, options = {}) {
    const rawMethod = (options.method || 'GET').toUpperCase();
    const headers = {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json, text/plain, */*',
        ...(options.headers || {}),
    };

    const csrf = getCsrfToken();
    if (csrf && !headers['X-CSRF-TOKEN'] && rawMethod !== 'GET' && rawMethod !== 'HEAD') {
        headers['X-CSRF-TOKEN'] = csrf;
    }

    let body = options.body;
    let method = rawMethod;

    if (body && !(body instanceof FormData) && typeof body === 'object') {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    }

    const button = options.button || null;
    const loadingText = options.loadingText || '';

    if (button && window.GCTLoading?.set) {
        window.GCTLoading.set(button, loadingText);
    }

    try {
        const response = await fetch(url, {
            method,
            headers,
            body,
            cache: options.cache || 'no-store',
        });

        const contentType = response.headers.get('content-type') || '';
        let data = null;

        if (contentType.includes('application/json')) {
            try {
                data = await response.json();
            } catch (jsonErr) {
                console.warn('Failed to parse JSON response:', jsonErr);
            }
        } else {
            try {
                data = await response.text();
            } catch (textErr) {
                console.warn('Failed to read response text:', textErr);
            }
        }

        if (!response.ok) {
            let errorMessage = 'An unexpected error occurred.';

            if (response.status === 419) {
                errorMessage = 'Your session has expired. Please refresh the page.';
            } else if (response.status === 422) {
                if (data && typeof data === 'object') {
                    if (data.message && !data.errors) {
                        errorMessage = data.message;
                    } else if (data.errors && typeof data.errors === 'object') {
                        const firstKey = Object.keys(data.errors)[0];
                        errorMessage = data.errors[firstKey]?.[0] || data.message || 'Validation error.';
                    } else {
                        errorMessage = data.message || 'Validation failed. Please check your input.';
                    }
                }
            } else if (data && typeof data === 'object' && data.message) {
                errorMessage = data.message;
            } else if (typeof data === 'string' && data.length > 0 && data.length < 200) {
                errorMessage = data;
            } else {
                errorMessage = `Action failed (HTTP ${response.status}).`;
            }

            if (options.showToast !== false && typeof window.showSystemToast === 'function') {
                window.showSystemToast(errorMessage, 'error', options.errorTitle || 'Action Failed');
            }

            if (typeof options.onError === 'function') {
                options.onError(errorMessage, data, response.status);
            }

            return { ok: false, error: errorMessage, data, status: response.status };
        }

        // Success (200-299)
        const successMessage = (data && typeof data === 'object' && data.message)
            ? data.message
            : (options.successMessage || null);

        if (successMessage && options.showToast !== false && typeof window.showSystemToast === 'function') {
            window.showSystemToast(successMessage, 'success', options.toastTitle || 'Success');
        }

        if (options.closeModal) {
            const modalEl = typeof options.closeModal === 'string'
                ? document.getElementById(options.closeModal)
                : options.closeModal;
            if (modalEl) {
                modalEl.classList.remove('show', 'active');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                if (window.GCTModalBackdrop?.sync) {
                    window.GCTModalBackdrop.sync();
                } else {
                    document.body.style.overflow = '';
                }
            }
        }

        if (options.refreshRegions !== false && window.GCTRegions?.refresh) {
            await window.GCTRegions.refresh(options.refreshUrl || window.location.href, options.regions || null);
        }

        if (typeof options.onSuccess === 'function') {
            options.onSuccess(data, response.status);
        }

        return { ok: true, data, status: response.status };
    } catch (networkError) {
        console.error('AJAX request network error:', networkError);
        const msg = networkError?.message || 'Network request failed. Please check your connection.';

        if (options.showToast !== false && typeof window.showSystemToast === 'function') {
            window.showSystemToast(msg, 'error', 'Connection Error');
        }

        if (typeof options.onError === 'function') {
            options.onError(msg, null, 0);
        }

        return { ok: false, error: msg, data: null, status: 0 };
    } finally {
        if (button && window.GCTLoading?.reset) {
            window.GCTLoading.reset(button);
        }
    }
}

/**
 * Submit an HTMLFormElement via AJAX.
 * @param {HTMLFormElement} form
 * @param {Object} options
 */
async function submitForm(form, options = {}) {
    if (!(form instanceof HTMLFormElement)) {
        throw new Error('GCTAjax.submitForm requires an HTMLFormElement.');
    }

    const url = form.getAttribute('action') || window.location.href;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const formData = new FormData(form, options.submitter || undefined);

    return ajaxRequest(url, {
        method,
        body: formData,
        button: options.button || form.querySelector('button[type="submit"], input[type="submit"]'),
        loadingText: options.loadingText || form.dataset.loadingText || '',
        ...options,
    });
}

window.GCTAjax = Object.freeze({
    request: ajaxRequest,
    submitForm,
    getCsrfToken,
});

// Intercept non-confirm forms marked with data-ajax-submit="true"
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.getAttribute('data-ajax-submit') !== 'true') return;
    if (form.hasAttribute('data-confirm-form')) return;
    if (event.defaultPrevented) return;

    event.preventDefault();

    const parentModalId = form.getAttribute('data-parent-modal-id');
    const regionsAttr = form.getAttribute('data-ajax-regions');
    const regions = regionsAttr
        ? regionsAttr.split(',').map(s => s.trim()).filter(Boolean)
        : null;

    window.GCTAjax.submitForm(form, {
        closeModal: parentModalId || null,
        regions: regions || undefined,
    });
});

// Intercept GET filter/search forms marked with data-ajax-filter="true"
document.addEventListener('submit', (event) => {
    const form = event.target.closest('[data-ajax-filter="true"]');
    if (!form || (form.getAttribute('method') || 'GET').toUpperCase() !== 'GET') return;

    event.preventDefault();
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
    const formData = new FormData(form);

    for (const [key, value] of formData.entries()) {
        const val = String(value).trim();
        if (val && !val.startsWith('All ')) {
            url.searchParams.set(key, val);
        } else {
            url.searchParams.delete(key);
        }
    }

    url.searchParams.delete('page');

    window.history.pushState({}, '', url.toString());
    if (window.GCTRegions?.refresh) {
        window.GCTRegions.refresh(url.toString());
    }
});

document.addEventListener('change', (event) => {
    const target = event.target;
    const form = target.closest('[data-ajax-filter="true"]');
    if (form && target.tagName === 'SELECT') {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
        } else {
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        }
    }
});

window.addEventListener('popstate', () => {
    if (window.GCTRegions?.refresh) {
        window.GCTRegions.refresh(window.location.href);
    }
});

export { ajaxRequest, submitForm, getCsrfToken };
