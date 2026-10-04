const MAIN_SELECTOR = 'main.main, main';
const LOADER_ID = 'gctNavigationLoader';
const MIN_LOADER_MS = 280;
const LOADER_FADE_OUT_MS = 170;
let loaderShownAt = 0;
let hideTimer = null;

const getMainElement = () => document.querySelector(MAIN_SELECTOR);

const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

const nextFrames = () => new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve));
});

const ensureLoader = () => {
    let loader = document.getElementById(LOADER_ID);
    if (loader) return loader;

    loader = document.createElement('div');
    loader.id = LOADER_ID;
    loader.className = 'gct-navigation-loader';
    loader.setAttribute('aria-hidden', 'true');
    loader.innerHTML = `
        <div class="gct-navigation-loader__content" role="status" aria-live="polite">
            <span class="gct-navigation-loader__spinner" aria-hidden="true"></span>
            <span class="gct-navigation-loader__label">Loading...</span>
        </div>
    `;

    document.body.appendChild(loader);
    return loader;
};

const syncLoaderOffset = () => {
    const loader = ensureLoader();
    const sidebar = document.getElementById('appSidebar');

    if (!sidebar || window.matchMedia('(max-width: 900px)').matches) {
        loader.style.setProperty('--gct-loader-left', '0px');
        return;
    }

    const right = Math.max(0, Math.round(sidebar.getBoundingClientRect().right));
    loader.style.setProperty('--gct-loader-left', `${right}px`);
};

const showLoader = () => {
    if (hideTimer) {
        window.clearTimeout(hideTimer);
        hideTimer = null;
    }

    const loader = ensureLoader();
    syncLoaderOffset();
    loaderShownAt = performance.now();
    loader.classList.remove('is-hiding');
    loader.classList.add('is-visible');
    loader.setAttribute('aria-hidden', 'false');
    document.body.classList.add('gct-navigation-loading');
};

const hideLoader = async ({
    revealMain = true,
    beforeFade = null,
} = {}) => {
    const loader = ensureLoader();
    const elapsed = performance.now() - loaderShownAt;
    const remaining = Math.max(0, MIN_LOADER_MS - elapsed);

    if (remaining > 0) await wait(remaining);
    await nextFrames();

    if (typeof beforeFade === 'function') {
        await beforeFade();
    }

    loader.classList.add('is-hiding');
    loader.classList.remove('is-visible');
    loader.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('gct-navigation-loading');

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        await wait(LOADER_FADE_OUT_MS);
    }

    loader.classList.remove('is-hiding');

    if (revealMain) {
        const main = getMainElement();
        if (main) {
            main.classList.remove(
                'gct-main-fetching',
                'gct-main-leaving',
                'gct-main-entering',
                'gct-main-entered',
                'gct-main-loader-hold',
            );
            main.classList.add('gct-main-after-loader');
            hideTimer = window.setTimeout(() => {
                main.classList.remove('gct-main-after-loader');
                hideTimer = null;
            }, 420);
        }
    }
};

const show = () => {
    showLoader();
};

const hide = () => {
    hideLoader();
};

const holdIncomingMain = () => {
    const main = getMainElement();
    if (!main) return;

    main.classList.add('gct-main-loader-hold');
};

const normalizeSidebarActiveState = () => {
    const sidebar = document.getElementById('appSidebar');
    if (!sidebar) return;

    sidebar.querySelectorAll('.menu-dropdown').forEach((dropdown) => {
        const activeChild = dropdown.querySelector('.submenu-item.active');
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const shouldBeActive = Boolean(activeChild);

        dropdown.classList.toggle('active', shouldBeActive);
        toggle?.classList.toggle('active', shouldBeActive);

        if (shouldBeActive) {
            dropdown.classList.add('open');
            toggle?.setAttribute('aria-expanded', 'true');
        } else if (toggle?.getAttribute('aria-expanded') !== 'true') {
            dropdown.classList.remove('open');
        }
    });

    sidebar.querySelectorAll('a.active').forEach((link) => {
        if (link.classList.contains('submenu-item')) return;
        const href = new URL(link.href, window.location.href);
        const current = new URL(window.location.href);
        const sameDestination = href.pathname === current.pathname;
        link.classList.toggle('active', sameDestination);
    });
};

window.addEventListener('gct:navigation-before', () => {
    showLoader();
});

window.addEventListener('gct:navigation-ready', async () => {
    holdIncomingMain();
    normalizeSidebarActiveState();

    const maintenanceReveal =
        window.GCTMaintenanceReveal;

    const useMaintenanceReveal =
        maintenanceReveal?.isApplicable?.() === true
        && await maintenanceReveal.prepare();

    if (useMaintenanceReveal) {
        /*
         * Keep the completed Maintenance layout hidden until the loader has
         * fully faded. Starting GSAP underneath the loader makes most of the
         * motion invisible, so reveal only after the cover is gone.
         */
        await hideLoader({
            revealMain: false,
        });

        const main = getMainElement();
        if (!main) return;

        main.classList.remove(
            'gct-main-fetching',
            'gct-main-leaving',
            'gct-main-entering',
            'gct-main-entered',
            'gct-main-loader-hold',
            'gct-main-after-loader',
        );

        await nextFrames();
        maintenanceReveal.reveal();

        return;
    }

    await hideLoader({ revealMain: false });

    const main = getMainElement();
    if (!main) return;

    main.classList.remove(
        'gct-main-fetching',
        'gct-main-leaving',
        'gct-main-entering',
        'gct-main-entered',
        'gct-main-loader-hold',
    );
    main.classList.add('gct-main-after-loader');

    window.setTimeout(() => {
        main.classList.remove('gct-main-after-loader');
    }, 420);
});

window.addEventListener('pageshow', () => {
    normalizeSidebarActiveState();
    document.body.classList.remove('gct-navigation-loading');
    ensureLoader().classList.remove('is-visible', 'is-hiding');
});

window.addEventListener('resize', syncLoaderOffset);

window.GCTPageTransition = Object.freeze({
    show,
    hide,
    showLoader,
    hideLoader,
    syncLoaderOffset,
});
