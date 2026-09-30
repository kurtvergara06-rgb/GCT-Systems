const progressKey = 'gct-navigation-progress';
let progressDelayTimer = null;
let progressTickTimer = null;

const getProgress = () => document.getElementById('gctNavigationProgress');
const getProgressBar = () => getProgress()?.querySelector('span');

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

const revealPageSections = () => {
    const body = document.body;
    if (!body) return;

    body.classList.add('gct-initial-reveal');

    window.setTimeout(() => {
        body.classList.remove('gct-initial-reveal');
    }, 500);
};

const resetProgressTimers = () => {
    if (progressDelayTimer) window.clearTimeout(progressDelayTimer);
    if (progressTickTimer) window.clearInterval(progressTickTimer);
    progressDelayTimer = null;
    progressTickTimer = null;
};

const showProgress = () => {
    const progress = getProgress();
    const bar = getProgressBar();
    if (!progress || !bar) return;

    sessionStorage.setItem(progressKey, '1');
    progress.style.opacity = '1';
    bar.style.width = '18%';

    let width = 18;
    progressTickTimer = window.setInterval(() => {
        width = Math.min(86, width + Math.max(2, (86 - width) * 0.12));
        bar.style.width = `${width}%`;
    }, 180);
};

const startNavigationProgress = () => {
    resetProgressTimers();
    syncSidebarOffset();

    progressDelayTimer = window.setTimeout(() => {
        showProgress();
    }, 110);
};

const finishNavigationProgress = () => {
    resetProgressTimers();

    const progress = getProgress();
    const bar = getProgressBar();
    if (!progress || !bar) return;

    const wasNavigating = sessionStorage.getItem(progressKey) === '1';
    sessionStorage.removeItem(progressKey);

    if (!wasNavigating) {
        progress.style.opacity = '0';
        bar.style.width = '0';
        return;
    }

    progress.style.opacity = '1';
    bar.style.width = '100%';

    window.setTimeout(() => {
        progress.style.opacity = '0';
    }, 120);

    window.setTimeout(() => {
        bar.style.width = '0';
    }, 300);
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

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (shouldTrackLink(link, event)) startNavigationProgress();
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (event.defaultPrevented) return;
    if (form.dataset.ajaxSubmit === 'true' || form.dataset.noPageLoader === 'true') return;

    startNavigationProgress();
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
    finishNavigationProgress();
    if (event.persisted) revealPageSections();
});

window.GCTPageTransition = Object.freeze({
    show: startNavigationProgress,
    hide: finishNavigationProgress,
    syncSidebarOffset,
});
