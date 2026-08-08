/* =========================================================
   GLOBAL FORM BUTTON LOADING STATE
   Applies to non-GET form submissions across the application.
========================================================= */

const loadingWords = [
    ['save', 'Saving...'],
    ['update', 'Updating...'],
    ['create', 'Creating...'],
    ['add', 'Adding...'],
    ['submit', 'Submitting...'],
    ['approve', 'Approving...'],
    ['reject', 'Rejecting...'],
    ['delete', 'Deleting...'],
    ['remove', 'Removing...'],
    ['import', 'Importing...'],
    ['upload', 'Uploading...'],
    ['generate', 'Generating...'],
    ['recalculate', 'Recalculating...'],
    ['calculate', 'Calculating...'],
    ['assign', 'Assigning...'],
    ['finish', 'Finishing...'],
    ['complete', 'Completing...'],
    ['confirm', 'Confirming...'],
    ['send', 'Sending...'],
    ['receive', 'Receiving...'],
    ['issue', 'Issuing...'],
    ['process', 'Processing...'],
    ['login', 'Signing in...'],
    ['sign in', 'Signing in...'],
    ['register', 'Creating account...'],
];

function buttonText(button) {
    if (button instanceof HTMLInputElement) {
        return String(button.value || '').trim();
    }

    return String(button.textContent || '')
        .replace(/\s+/g, ' ')
        .trim();
}

function resolveLoadingText(button) {
    const explicit = button.dataset.loadingText?.trim();

    if (explicit) {
        return explicit;
    }

    const current = buttonText(button);

    if (/\b(?:saving|updating|creating|adding|submitting|approving|rejecting|deleting|removing|importing|uploading|generating|recalculating|calculating|assigning|finishing|completing|confirming|sending|receiving|issuing|processing|signing)\b/i.test(current)) {
        return current;
    }

    const normalized = current.toLowerCase();
    const match = loadingWords.find(([word]) => normalized.includes(word));

    return match?.[1] || 'Processing...';
}

function ensureSpinner(button) {
    if (!(button instanceof HTMLButtonElement)) {
        return null;
    }

    let spinner = button.querySelector(':scope > .gct-spinner');

    if (!spinner) {
        spinner = document.createElement('span');
        spinner.className = 'gct-spinner gct-spinner-sm';
        spinner.setAttribute('aria-hidden', 'true');
        spinner.innerHTML = '<span class="gct-spinner-ring"></span>';
        button.prepend(spinner);
    }

    spinner.hidden = false;
    return spinner;
}

function setButtonLabel(button, text) {
    if (button instanceof HTMLInputElement) {
        button.value = text;
        return;
    }

    const preferredLabel = button.querySelector(
        '[data-loading-label], [id$="Text"], .ui-button-label, .button-label'
    );

    if (preferredLabel) {
        preferredLabel.textContent = text;
        return;
    }

    const directSpan = Array.from(button.children)
        .find((child) => child.tagName === 'SPAN' && !child.classList.contains('gct-spinner'));

    if (directSpan) {
        directSpan.textContent = text;
        return;
    }

    Array.from(button.childNodes)
        .filter((node) => node.nodeType === Node.TEXT_NODE)
        .forEach((node) => node.remove());

    const label = document.createElement('span');
    label.className = 'gct-loading-label';
    label.textContent = text;
    button.appendChild(label);
}

export function setButtonLoading(button, text = null) {
    if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement)) {
        return;
    }

    if (button.dataset.noLoading !== undefined || button.getAttribute('aria-busy') === 'true') {
        return;
    }

    const loadingText = text || resolveLoadingText(button);

    if (!button.dataset.originalLoadingHtml && button instanceof HTMLButtonElement) {
        button.dataset.originalLoadingHtml = button.innerHTML;
    }

    if (!button.dataset.originalLoadingValue && button instanceof HTMLInputElement) {
        button.dataset.originalLoadingValue = button.value;
    }

    ensureSpinner(button);
    setButtonLabel(button, loadingText);

    button.classList.add('gct-is-loading');
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;
}

export function resetButtonLoading(button) {
    if (!(button instanceof HTMLButtonElement || button instanceof HTMLInputElement)) {
        return;
    }

    if (button instanceof HTMLButtonElement && button.dataset.originalLoadingHtml) {
        button.innerHTML = button.dataset.originalLoadingHtml;
        delete button.dataset.originalLoadingHtml;
    }

    if (button instanceof HTMLInputElement && button.dataset.originalLoadingValue) {
        button.value = button.dataset.originalLoadingValue;
        delete button.dataset.originalLoadingValue;
    }

    button.classList.remove('gct-is-loading');
    button.removeAttribute('aria-busy');
    button.disabled = false;
}

window.GCTLoading = {
    set: setButtonLoading,
    reset: resetButtonLoading,
};

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const method = String(form.getAttribute('method') || 'GET').toUpperCase();

    if (
        method === 'GET' ||
        form.dataset.noLoading !== undefined ||
        form.target === '_blank'
    ) {
        return;
    }

    const submitter = event.submitter instanceof HTMLElement
        ? event.submitter
        : form.querySelector('button[type="submit"], input[type="submit"]');

    window.setTimeout(() => {
        if (event.defaultPrevented || !submitter) {
            return;
        }

        setButtonLoading(submitter);
    }, 0);
});
