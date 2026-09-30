const loaderId = 'gctPageLoader';

const getLoader = () => document.getElementById(loaderId);

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

const showLoader = () => {
    const loader = getLoader();
    if (!loader) return;

    syncSidebarOffset();
    loader.classList.remove('is-hidden');
    document.body?.classList.add('gct-page-entering');
    document.body?.classList.remove('gct-page-ready');
};

const hideLoader = () => {
    const loader = getLoader();

    syncSidebarOffset();
    document.body?.classList.remove('gct-page-entering');
    document.body?.classList.add('gct-page-ready', 'gct-initial-reveal');

    window.requestAnimationFrame(() => {
        loader?.classList.add('is-hidden');
    });

    window.setTimeout(() => {
        document.body?.classList.remove('gct-initial-reveal');
    }, 650);
};

const shouldShowForLink = (link, event) => {
    if (!link || event.defaultPrevented) return false;
    if (event.button !== 0) return false;
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
    if (shouldShowForLink(link, event)) showLoader();
}, true);

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (event.defaultPrevented) return;
    if (form.dataset.ajaxSubmit === 'true' || form.dataset.noPageLoader === 'true') return;

    showLoader();
}, true);

window.addEventListener('resize', syncSidebarOffset);
window.addEventListener('load', hideLoader, { once: true });

window.addEventListener('pageshow', (event) => {
    syncSidebarOffset();
    if (event.persisted) hideLoader();
});

document.addEventListener('DOMContentLoaded', syncSidebarOffset, { once: true });

window.GCTPageTransition = Object.freeze({
    show: showLoader,
    hide: hideLoader,
    syncSidebarOffset,
});