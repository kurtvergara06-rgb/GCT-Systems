const progressKey = 'gct-navigation-progress';
let progressDelayTimer = null;
let progressTickTimer = null;
let navigationSafetyTimer = null;
let viewTransitionActive = false;

const getProgress = () => document.getElementById('gctNavigationProgress');
const getProgressBar = () => getProgress()?.querySelector('span');

const getMainElement = () => document.querySelector('main, .main');

const syncSidebarOffset = () => {
    const sidebar = document.getElementById('appSidebar');
    const root = document.documentElement;
    if (!root) return;

    if (!sidebar || window.matchMedia('(max-width: 900px)').matches) {
        root.style.setProperty('--gct-sidebar-offset', '0px');
        return;
    }

    const width = Math.max(0, Math.round(sidebar.getBoundingClientRect().width));
    root.style.setProperty('--gct-sidebar-offset', `${width}px`);
};

// Detect if native view transition handles the reveal
if ('pagereveal' in window) {
    window.addEventListener('pagereveal', (event) => {
        if (event.viewTransition) {
            viewTransitionActive = true;
        }
    });
}

const revealPageSections = () => {
    // If native View Transition ran, skip JS fallback to avoid double-animation
    if (viewTransitionActive) return;

    const body = document.body;
    if (!body) return;

    body.classList.add('gct-initial-reveal');

    window.setTimeout(() => {
        body.classList.remove('gct-initial-reveal');
    }, 320);
};

const clearLeavingState = () => {
    const main = getMainElement();
    if (main) {
        main.classList.remove('gct-main-leaving');
    }
    if (navigationSafetyTimer) {
        window.clearTimeout(navigationSafetyTimer);
        navigationSafetyTimer = null;
    }
};

const resetProgressTimers = () => {
    if (progressDelayTimer) window.clearTimeout(progressDelayTimer);
    if (progressTickTimer) window.clearInterval(progressTickTimer);
    progressDelayTimer = null;
    progressTickTimer = null;
};

const setProgress = (value) => {
    const bar = getProgressBar();
    if (!bar) return;
    const clamped = Math.max(0, Math.min(1, value));
    bar.style.transform = `scaleX(${clamped})`;
};

const showProgress = () => {
    const progress = getProgress();
    if (!progress) return;

    sessionStorage.setItem(progressKey, '1');
    progress.classList.add('is-visible');
    setProgress(0.12);

    let value = 0.12;
    progressTickTimer = window.setInterval(() => {
        value += (0.88 - value) * 0.08;
        setProgress(value);
    }, 140);
};

const startNavigation = () => {
    resetProgressTimers();
    syncSidebarOffset();

    // 1. Softly fade out main content area (sidebar remains completely untouched)
    const main = getMainElement();
    if (main) {
        main.classList.add('gct-main-leaving');
    }

    // 2. Start thin progress bar after a subtle 80ms delay to avoid flash on instant loads
    progressDelayTimer = window.setTimeout(showProgress, 80);

    // 3. Safety fallback timer: clear leaving state if navigation is aborted/delayed
    if (navigationSafetyTimer) window.clearTimeout(navigationSafetyTimer);
    navigationSafetyTimer = window.setTimeout(() => {
        clearLeavingState();
        finishNavigationProgress();
    }, 6000);
};

const finishNavigationProgress = () => {
    resetProgressTimers();
    clearLeavingState();

    const progress = getProgress();
    if (!progress) return;

    const wasNavigating = sessionStorage.getItem(progressKey) === '1';
    sessionStorage.removeItem(progressKey);

    if (!wasNavigating) {
        progress.classList.remove('is-visible');
        setProgress(0);
        return;
    }

    progress.classList.add('is-visible');
    setProgress(1);

    window.setTimeout(() => {
        progress.classList.remove('is-visible');
    }, 160);

    window.setTimeout(() => {
        setProgress(0);
    }, 380);
};

const shouldTrackLink = (link, event) => {
    if (!link || event.defaultPrevented || event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.hasAttribute('download')) return false;
    if ((link.getAttribute('target') || '').toLowerCase() === '_blank') return false;
    if (link.dataset.noPageLoader === 'true') return false;

    const rawHref = link.getAttribute('href');
    if (!rawHref || rawHref.startsWith('#') || rawHref.startsWith('javascript:')) return false;

    let url;
    try {
        url = new URL(link.href, window.location.href);
    } catch {
        return false;
    }

    if (url.origin !== window.location.origin) return false;

    const sameDocumentHashOnly = url.pathname === window.location.pathname
        && url.search === window.location.search
        && url.hash !== window.location.hash;

    return !sameDocumentHashOnly;
};

// Listen for link clicks across sidebar and application navigation
document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (shouldTrackLink(link, event)) startNavigation();
}, true);

// Listen for traditional form submissions that navigate away
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (event.defaultPrevented) return;
    if (form.dataset.ajaxSubmit === 'true' || form.dataset.noPageLoader === 'true') return;

    startNavigation();
}, true);

document.addEventListener('DOMContentLoaded', () => {
    syncSidebarOffset();
    finishNavigationProgress();
    revealPageSections();
}, { once: true });

window.addEventListener('load', finishNavigationProgress, { once: true });
window.addEventListener('resize', syncSidebarOffset);

window.addEventListener('pageshow', (event) => {
    syncSidebarOffset();
    clearLeavingState();
    finishNavigationProgress();
    if (event.persisted) revealPageSections();
});

window.GCTPageTransition = Object.freeze({
    show: startNavigation,
    hide: finishNavigationProgress,
    syncSidebarOffset,
});
