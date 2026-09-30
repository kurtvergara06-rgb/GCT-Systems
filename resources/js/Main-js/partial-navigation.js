const MAIN_SELECTOR = 'main.main, main';
const NAVIGATION_EVENT = 'gct:navigation-ready';
const BEFORE_NAVIGATION_EVENT = 'gct:navigation-before';
const loadedAssets = new Set();
const initializerCleanups = new Map();

let activeRequest = null;

const runInitializer = (key, rootSelector, initializer) => {
    const root = document.querySelector(rootSelector);
    if (!root) return;

    initializerCleanups.get(key)?.();

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
    document.body.querySelectorAll('script').forEach((script) => {
        if (!main?.contains(script)) script.dataset.gctTransientScript = 'true';
    });
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

    window.dispatchEvent(new CustomEvent(BEFORE_NAVIGATION_EVENT));
    currentMain.classList.add('gct-main-leaving');
    await new Promise((resolve) => window.setTimeout(resolve, 150));

    const nextMain = document.importNode(parsedMain, true);
    nextMain.classList.add('gct-main-entering');
    currentMain.replaceWith(nextMain);

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

const fallback = (url) => {
    window.location.href = url;
};

const navigate = async (url, options = {}) => {
    const targetUrl = absoluteUrl(url);
    if (!targetUrl) return;

    activeRequest?.abort();
    const controller = new AbortController();
    activeRequest = controller;
    document.querySelector(MAIN_SELECTOR)?.classList.add('gct-main-fetching');

    try {
        const response = await fetch(targetUrl, {
            method: 'GET',
            headers: {
                Accept: 'text/html,application/xhtml+xml',
                'X-GCT-Partial-Navigation': '1',
            },
            credentials: 'same-origin',
            signal: controller.signal,
        });

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
        if (error.name !== 'AbortError') fallback(targetUrl);
    } finally {
        if (activeRequest === controller) activeRequest = null;
        document.querySelector(MAIN_SELECTOR)?.classList.remove('gct-main-fetching');
    }
};

const eligibleLink = (link, event) => {
    if (!link || event.defaultPrevented || event.button !== 0) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (link.hasAttribute('download') || link.hasAttribute('data-no-page-loader')) return false;
    if (link.hasAttribute('data-no-partial-navigation') || link.closest('[data-ajax-region]')) return false;
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

window.GCTPartialNavigation = Object.freeze({ navigate, registerInitializer });
