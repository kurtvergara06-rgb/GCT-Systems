const MAIN_SELECTOR = 'main.main, main';
const NAVIGATION_EVENT = 'gct:navigation-ready';
const BEFORE_NAVIGATION_EVENT = 'gct:navigation-before';
const loadedAssets = new Set();
const initializerCleanups = new Map();

const PAGE_OVERLAY_SELECTOR = [
    '.modal',
    '.modal-overlay',
    '.delete-modal-overlay',
    '.feedback-modal-overlay',
    '.success-modal-overlay',
    '.ui-form-overlay',
    '.admin-modal-overlay',
    '.records-modal-overlay',
    '.history-modal-overlay',
    '.activity-modal-overlay',
    '.transfer-activity-modal-overlay',
    '.fuel-modal-overlay',
    '.pms-modal-overlay',
    '.personnel-modal-overlay',
    '.route-modal-overlay',
    '.trip-modal-overlay',
    '.batch-time-modal-overlay',
    '.batch-attendance-overlay',
    '.ai-resolution-modal-overlay',
    '.restock-view-overlay',
    '.requested-pr-view-overlay',
    '.schedule-modal-overlay',
    '.po-status-modal-overlay',
    '.warehouse-view-overlay',
    '.edit-trip-modal-overlay',
    '.confirm-modal-overlay',
    '.confirmation-modal-overlay',
    '[data-modal-overlay]',
    '[data-gct-modal-overlay]',
].join(', ');

const PAGE_PORTAL_SELECTOR = [
    '[data-page-owned]',
    '.gct-picker-popover',
    '.modal-backdrop',
    '.ui-modal-backdrop',
    '.global-modal-backdrop',
    '[data-modal-backdrop]',
    '[role="tooltip"]',
    '.tooltip',
    '.popover',
].join(', ');

const PERSISTENT_OVERLAY_SELECTOR = [
    '#globalConfirmationModal',
    '.global-confirmation-overlay',
    '[data-global-confirmation-modal]',
    '#systemToastContainer',
    '.system-toast-container',
    '.system-toast-root',
    '[data-gct-persistent-ui]',
].join(', ');

let activeRequest = null;

const cleanupInitializer = (key) => {
    initializerCleanups.get(key)?.();
    initializerCleanups.delete(key);
};

const cleanupAllInitializers = () => {
    Array.from(initializerCleanups.keys()).forEach(cleanupInitializer);
};

const runInitializer = (key, rootSelector, initializer) => {
    // Always clear the previous page instance first. The old implementation
    // returned when the selector was absent, leaving document/window listeners
    // from the previous page alive after a partial-navigation swap.
    cleanupInitializer(key);

    const root = document.querySelector(rootSelector);
    if (!root) return;

    const controller = new AbortController();
    const observers = [];
    const intervals = [];
    const timeouts = [];
    const nativeAddEventListener = EventTarget.prototype.addEventListener;
    const NativeMutationObserver = window.MutationObserver;
    const nativeSetInterval = window.setInterval;
    const nativeSetTimeout = window.setTimeout;

    EventTarget.prototype.addEventListener = function (type, listener, options) {
        if (options?.signal) {
            return nativeAddEventListener.call(this, type, listener, options);
        }

        const scopedOptions = typeof options === 'boolean'
            ? { capture: options, signal: controller.signal }
            : { ...(options || {}), signal: controller.signal };

        return nativeAddEventListener.call(this, type, listener, scopedOptions);
    };

    if (NativeMutationObserver) {
        window.MutationObserver = class ScopedMutationObserver extends NativeMutationObserver {
            constructor(callback) {
                super(callback);
                observers.push(this);
            }
        };
    }

    window.setInterval = (...args) => {
        const id = nativeSetInterval(...args);
        intervals.push(id);
        return id;
    };
    window.setTimeout = (...args) => {
        const id = nativeSetTimeout(...args);
        timeouts.push(id);
        return id;
    };

    try {
        initializer(root);
    } finally {
        EventTarget.prototype.addEventListener = nativeAddEventListener;
        window.MutationObserver = NativeMutationObserver;
        window.setInterval = nativeSetInterval;
        window.setTimeout = nativeSetTimeout;
    }

    initializerCleanups.set(key, () => {
        controller.abort();
        observers.forEach((observer) => observer.disconnect());
        intervals.forEach((id) => window.clearInterval(id));
        timeouts.forEach((id) => window.clearTimeout(id));
    });
};

const registerInitializer = (key, rootSelector, initializer) => {
    const run = () => runInitializer(key, rootSelector, initializer);
    window.addEventListener(NAVIGATION_EVENT, run);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, { once: true });
    } else {
        queueMicrotask(run);
    }
};

const absoluteUrl = (value, base = window.location.href) => {
    try {
        return new URL(value, base).href;
    } catch {
        return null;
    }
};

const rememberLoadedAssets = () => {
    document.querySelectorAll('link[rel="stylesheet"][href], script[src]').forEach((element) => {
        const source = element.getAttribute('href') || element.getAttribute('src');
        const resolved = absoluteUrl(source);
        if (resolved) loadedAssets.add(resolved);
        if (element.matches('link[rel="stylesheet"]')) {
            element.dataset.gctManagedStyle = 'true';
        }
    });

    const main = document.querySelector(MAIN_SELECTOR);
    const app = main?.closest('.app') || document.body;

    Array.from(app?.children || []).forEach((element) => {
        if (
            element !== main
            && element.id !== 'appSidebar'
            && element.tagName !== 'SCRIPT'
            && !element.matches(PERSISTENT_OVERLAY_SELECTOR)
        ) {
            element.dataset.pageOwned = 'true';
        }
    });

    Array.from(document.body.children).forEach((element) => {
        if (
            element !== app
            && element !== main
            && element.id !== 'appSidebar'
            && element.tagName !== 'SCRIPT'
            && !element.matches(PERSISTENT_OVERLAY_SELECTOR)
            && element.matches(PAGE_OVERLAY_SELECTOR)
        ) {
            element.dataset.pageOwned = 'true';
        }
    });

    document.body.querySelectorAll('script').forEach((script) => {
        if (!main?.contains(script)) script.dataset.gctTransientScript = 'true';
    });
};

const markPageOwned = (element) => {
    if (element instanceof HTMLElement) element.dataset.pageOwned = 'true';
    return element;
};

const closeOverlay = (overlay) => {
    if (overlay instanceof HTMLDialogElement && overlay.open) {
        overlay.close();
    }

    overlay.classList.remove(
        'show',
        'active',
        'open',
        'is-open',
        'visible',
        'is-visible',
        'is-stacked-modal',
    );
    overlay.setAttribute('aria-hidden', 'true');
    overlay.removeAttribute('inert');

    if (overlay instanceof HTMLElement) {
        overlay.style.display = 'none';
    }
};

const cleanupPageOverlays = () => {
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());

    document.querySelectorAll(PAGE_OVERLAY_SELECTOR).forEach((overlay) => {
        closeOverlay(overlay);

        if (!overlay.matches(PERSISTENT_OVERLAY_SELECTOR) && !overlay.closest(MAIN_SELECTOR)) {
            overlay.remove();
        }
    });

    document.querySelectorAll(PAGE_PORTAL_SELECTOR).forEach((element) => {
        if (!element.matches(PERSISTENT_OVERLAY_SELECTOR) && !element.closest('#appSidebar')) {
            element.remove();
        }
    });

    document.querySelectorAll('[data-page-owned="true"]').forEach((element) => {
        if (
            !element.matches(PERSISTENT_OVERLAY_SELECTOR)
            && !element.closest('#appSidebar')
            && !element.closest(MAIN_SELECTOR)
        ) {
            element.remove();
        }
    });

    const transientClasses = [
        'modal-open',
        'has-stacked-modal',
        'overflow-hidden',
        'batch-modal-open',
        'transfer-activity-modal-open',
        'ai-modal-open',
    ];

    document.body.classList.remove(...transientClasses);
    document.documentElement.classList.remove(...transientClasses);

    [document.body, document.documentElement].forEach((element) => {
        element.style.removeProperty('overflow');
        element.style.removeProperty('padding-right');
        element.style.removeProperty('touch-action');
        element.removeAttribute('aria-hidden');
        element.removeAttribute('inert');
    });

    document.querySelectorAll('#appSidebar, main').forEach((element) => {
        element.removeAttribute('aria-hidden');
        element.removeAttribute('inert');
    });

    window.GCTModalBackdrop?.sync?.();
};

const syncPageOwnedElements = (nextDocument) => {
    const currentMain = document.querySelector(MAIN_SELECTOR);
    const nextMain = nextDocument.querySelector(MAIN_SELECTOR);
    const currentApp = currentMain?.closest('.app');
    const nextApp = nextMain?.closest('.app');

    if (currentApp && nextApp && nextApp.className) {
        currentApp.className = nextApp.className;
    }

    if (currentApp) {
        Array.from(currentApp.children).forEach((element) => {
            if (element.hasAttribute('data-page-owned') || element.dataset.pageOwned === 'true') {
                element.remove();
            }
        });
    }

    Array.from(document.body.children).forEach((element) => {
        if (
            element !== currentApp
            && (
                element.hasAttribute('data-page-owned')
                || element.dataset.pageOwned === 'true'
                || (element.matches?.(PAGE_OVERLAY_SELECTOR) && !element.matches?.(PERSISTENT_OVERLAY_SELECTOR))
            )
        ) {
            element.remove();
        }
    });

    if (currentApp && nextApp) {
        Array.from(nextApp.children).forEach((element) => {
            if (
                element === nextMain
                || element.id === 'appSidebar'
                || element.tagName === 'SCRIPT'
                || element.matches(PERSISTENT_OVERLAY_SELECTOR)
                || element.matches(PAGE_OVERLAY_SELECTOR)
            ) return;
            currentApp.appendChild(markPageOwned(document.importNode(element, true)));
        });
    }

    Array.from(nextDocument.body.children).forEach((element) => {
        if (
            element === nextApp
            || element === nextMain
            || element.id === 'appSidebar'
            || element.tagName === 'SCRIPT'
            || element.matches(PERSISTENT_OVERLAY_SELECTOR)
            || !element.matches(PAGE_OVERLAY_SELECTOR)
        ) return;
        document.body.appendChild(markPageOwned(document.importNode(element, true)));
    });
};

const beginMainExit = (main) => {
    window.dispatchEvent(new CustomEvent(BEFORE_NAVIGATION_EVENT));
    cleanupAllInitializers();
    cleanupPageOverlays();
    main.classList.remove('gct-main-entering', 'gct-main-entered', 'gct-main-fetching');
    main.classList.add('gct-main-leaving');

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return Promise.resolve();
    }

    return new Promise((resolve) => window.setTimeout(resolve, 150));
};

const shellId = (root) => root.querySelector('#appSidebar')?.dataset.gctShell || null;

const isCompatibleDocument = (nextDocument) => {
    if (nextDocument.querySelector('meta[name="gct-partial-navigation"]')?.content !== 'enabled') {
        return false;
    }

    const currentMain = document.querySelector(MAIN_SELECTOR);
    const nextMain = nextDocument.querySelector(MAIN_SELECTOR);
    const currentShell = shellId(document);
    const nextShell = shellId(nextDocument);

    return Boolean(currentMain && nextMain && currentShell && currentShell === nextShell);
};

const elementAssetUrl = (element, baseUrl) => absoluteUrl(
    element.getAttribute('href') || element.getAttribute('src'),
    baseUrl,
);

const loadStylesheet = (source, baseUrl) => new Promise((resolve, reject) => {
    const href = elementAssetUrl(source, baseUrl);
    const existing = Array.from(document.querySelectorAll('link[rel="stylesheet"][href]'))
        .find((link) => absoluteUrl(link.getAttribute('href')) === href);

    if (existing) {
        existing.disabled = false;
        existing.dataset.gctManagedStyle = 'true';
        loadedAssets.add(href);
        resolve();
        return;
    }

    if (!href || loadedAssets.has(href)) {
        resolve();
        return;
    }

    const link = document.createElement('link');
    Array.from(source.attributes).forEach(({ name, value }) => link.setAttribute(name, value));
    link.href = href;
    link.dataset.gctPageAsset = 'true';
    link.dataset.gctManagedStyle = 'true';
    link.addEventListener('load', () => {
        loadedAssets.add(href);
        resolve();
    }, { once: true });
    link.addEventListener('error', reject, { once: true });
    document.head.appendChild(link);
});

const loadScript = (source, baseUrl) => new Promise((resolve, reject) => {
    const src = elementAssetUrl(source, baseUrl);
    if (!src || loadedAssets.has(src)) {
        resolve();
        return;
    }

    const script = document.createElement('script');
    Array.from(source.attributes).forEach(({ name, value }) => script.setAttribute(name, value));
    script.src = src;
    script.dataset.gctPageAsset = 'true';
    script.addEventListener('load', () => {
        loadedAssets.add(src);
        resolve();
    }, { once: true });
    script.addEventListener('error', reject, { once: true });
    document.head.appendChild(script);
});

const loadDocumentAssets = async (nextDocument, baseUrl) => {
    const styles = Array.from(nextDocument.querySelectorAll('head link[rel="stylesheet"][href]'));
    await Promise.all(styles.map((style) => loadStylesheet(style, baseUrl)));

    const scripts = Array.from(nextDocument.querySelectorAll('head script[src]'));
    for (const script of scripts) {
        await loadScript(script, baseUrl);
    }
};

const syncStylesheetState = (nextDocument, baseUrl) => {
    const desired = new Set(Array.from(nextDocument.querySelectorAll('head link[rel="stylesheet"][href]'))
        .map((link) => elementAssetUrl(link, baseUrl))
        .filter(Boolean));

    document.querySelectorAll('link[data-gct-managed-style][href]').forEach((link) => {
        link.disabled = !desired.has(absoluteUrl(link.getAttribute('href')));
    });
};

const syncBodyScripts = async (nextDocument, baseUrl) => {
    document.querySelectorAll('[data-gct-transient-script]').forEach((script) => script.remove());

    const nextMain = nextDocument.querySelector(MAIN_SELECTOR);
    const scripts = Array.from(nextDocument.body.querySelectorAll('script'))
        .filter((script) => !nextMain?.contains(script));

    for (const source of scripts) {
        if (source.src) {
            await loadScript(source, baseUrl);
            continue;
        }

        const script = document.createElement('script');
        Array.from(source.attributes).forEach(({ name, value }) => script.setAttribute(name, value));
        script.dataset.gctTransientScript = 'true';
        script.textContent = source.textContent;
        const nativeDocumentAddEventListener = document.addEventListener;
        document.addEventListener = function (type, listener, options) {
            if (type === 'DOMContentLoaded' && document.readyState !== 'loading') {
                queueMicrotask(() => listener.call(document, new Event('DOMContentLoaded')));
                return;
            }
            return nativeDocumentAddEventListener.call(document, type, listener, options);
        };

        try {
            document.body.appendChild(script);
        } finally {
            document.addEventListener = nativeDocumentAddEventListener;
        }
    }
};

const activateMainScripts = async (main, baseUrl) => {
    const scripts = Array.from(main.querySelectorAll('script'));

    for (const oldScript of scripts) {
        const script = document.createElement('script');
        Array.from(oldScript.attributes).forEach(({ name, value }) => script.setAttribute(name, value));

        const rawSrc = oldScript.getAttribute('src');
        if (rawSrc) {
            const src = absoluteUrl(rawSrc, baseUrl);
            if (!src || loadedAssets.has(src)) {
                oldScript.remove();
                continue;
            }
            script.src = src;
        } else {
            script.textContent = oldScript.textContent;
        }

        await new Promise((resolve, reject) => {
            if (script.src) {
                script.addEventListener('load', () => {
                    loadedAssets.add(script.src);
                    resolve();
                }, { once: true });
                script.addEventListener('error', reject, { once: true });
            }
            oldScript.replaceWith(script);
            if (!script.src) resolve();
        });
    }
};

const syncSidebarState = (nextDocument) => {
    const currentSidebar = document.querySelector('#appSidebar');
    const nextSidebar = nextDocument.querySelector('#appSidebar');
    if (!currentSidebar || !nextSidebar) return;

    const nextLinks = new Map(Array.from(nextSidebar.querySelectorAll('a[href]')).map((link) => [
        absoluteUrl(link.getAttribute('href')),
        link,
    ]));

    currentSidebar.querySelectorAll('a[href]').forEach((link) => {
        const nextLink = nextLinks.get(absoluteUrl(link.getAttribute('href')));
        link.classList.toggle('active', nextLink?.classList.contains('active') === true);
    });

    const currentDropdowns = currentSidebar.querySelectorAll('.menu-dropdown');
    const nextDropdowns = nextSidebar.querySelectorAll('.menu-dropdown');
    currentDropdowns.forEach((dropdown, index) => {
        const nextDropdown = nextDropdowns[index];
        const open = nextDropdown?.classList.contains('open') === true;
        dropdown.classList.toggle('open', open);
        dropdown.classList.toggle('active', nextDropdown?.classList.contains('active') === true);
        dropdown.querySelector('.dropdown-toggle')?.setAttribute('aria-expanded', String(open));
    });
};

const replaceMain = async (nextDocument, finalUrl, { push = true, restoreScroll = null } = {}) => {
    const currentMain = document.querySelector(MAIN_SELECTOR);
    const parsedMain = nextDocument.querySelector(MAIN_SELECTOR);
    if (!currentMain || !parsedMain) throw new Error('Missing main content');

    const nextMain = document.importNode(parsedMain, true);
    nextMain.classList.add('gct-main-entering');
    currentMain.replaceWith(nextMain);
    syncPageOwnedElements(nextDocument);

    syncStylesheetState(nextDocument, finalUrl);
    syncSidebarState(nextDocument);
    document.title = nextDocument.title || document.title;

    const csrf = nextDocument.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', csrf);

    if (push) {
        history.replaceState({ ...history.state, gctScrollY: window.scrollY }, '', window.location.href);
        history.pushState({ gctPartial: true, gctScrollY: 0 }, '', finalUrl);
    }

    await syncBodyScripts(nextDocument, finalUrl);
    await activateMainScripts(nextMain, finalUrl);
    window.dispatchEvent(new CustomEvent(NAVIGATION_EVENT, {
        detail: { url: finalUrl, main: nextMain },
    }));

    requestAnimationFrame(() => {
        nextMain.classList.remove('gct-main-entering');
        nextMain.classList.add('gct-main-entered');
        window.setTimeout(() => nextMain.classList.remove('gct-main-entered'), 220);
    });

    window.scrollTo({ top: restoreScroll ?? 0, left: 0, behavior: 'auto' });
};

const cleanupTransitionState = () => {
    const main = document.querySelector(MAIN_SELECTOR);
    if (main) {
        main.classList.remove(
            'gct-main-fetching',
            'gct-main-leaving',
            'gct-main-entering',
            'gct-main-loader-hold'
        );
    }
    document.body.classList.remove('gct-main-loader-hold');
    document.documentElement.classList.remove('gct-main-loader-hold');
    (window.GCTPageTransition || window.GCTPageTransitions)?.hideLoader?.({ revealMain: true });
};

const fallback = (url) => {
    window.location.href = url;
};

const navigate = async (url, options = {}) => {
    const targetUrl = absoluteUrl(url);
    if (!targetUrl) return;

    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    const currentMain = document.querySelector(MAIN_SELECTOR);
    if (!currentMain) {
        fallback(targetUrl);
        return;
    }

    const exitPromise = beginMainExit(currentMain);

    try {
        const responsePromise = fetch(targetUrl, {
            method: 'GET',
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-GCT-Partial-Navigation': '1',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

        await exitPromise;
        const response = await responsePromise;

        const contentType = response.headers.get('content-type') || '';
        if (!response.ok || !contentType.includes('text/html')) {
            fallback(targetUrl);
            return;
        }

        const html = await response.text();
        const nextDocument = new DOMParser().parseFromString(html, 'text/html');
        if (!isCompatibleDocument(nextDocument)) {
            fallback(response.url || targetUrl);
            return;
        }

        await loadDocumentAssets(nextDocument, response.url || targetUrl);
        await replaceMain(nextDocument, response.url || targetUrl, options);
    } catch (error) {
        if (error.name === 'AbortError') {
            if (activeRequest === controller) {
                cleanupTransitionState();
            }
        } else {
            cleanupTransitionState();
            fallback(targetUrl);
        }
    } finally {
        if (activeRequest === controller) {
            activeRequest = null;
            document.querySelector(MAIN_SELECTOR)?.classList.remove('gct-main-fetching');
        }
    }
};

const eligibleLink = (link, event) => {
    if (!link || event.defaultPrevented || event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.hasAttribute('download') || link.hasAttribute('data-no-page-loader')) return false;
    if (link.hasAttribute('data-no-partial-navigation')) return false;

    /*
     * Confirmation links are owned by confirmation-modal.js.
     * Do not begin partial navigation before the user confirms.
     */
    if (link.hasAttribute('data-confirm-action')) return false;
    if (link.closest('[data-ajax-region]') && !link.hasAttribute('data-allow-partial-navigation')) return false;
    if (link.closest('form') || link.getAttribute('role') === 'button') return false;

    const target = (link.getAttribute('target') || '').toLowerCase();
    if (target && target !== '_self') return false;

    const rawHref = link.getAttribute('href')?.trim();
    if (!rawHref || rawHref.startsWith('#') || rawHref.toLowerCase().startsWith('javascript:')) return false;

    const url = absoluteUrl(rawHref);
    if (!url) return false;
    const parsed = new URL(url);
    if (parsed.origin !== window.location.origin) return false;

    return !(parsed.pathname === window.location.pathname
        && parsed.search === window.location.search
        && parsed.hash);
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!eligibleLink(link, event)) return;

    event.preventDefault();
    navigate(link.href);
});

window.addEventListener('popstate', (event) => {
    navigate(window.location.href, {
        push: false,
        restoreScroll: event.state?.gctScrollY ?? 0,
    });
});

let scrollStateFrame = null;
window.addEventListener('scroll', () => {
    if (scrollStateFrame) return;
    scrollStateFrame = requestAnimationFrame(() => {
        history.replaceState({ ...history.state, gctScrollY: window.scrollY }, '', window.location.href);
        scrollStateFrame = null;
    });
}, { passive: true });

rememberLoadedAssets();

window.GCTPartialNavigation = Object.freeze({
    navigate,
    registerInitializer,
    cleanupPageOverlays,
    markPageOwned,
});
