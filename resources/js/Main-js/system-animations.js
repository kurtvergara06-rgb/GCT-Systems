import gsap from 'gsap';

const MODULE_PAGE_CONFIG = Object.freeze({
    admin: Object.freeze({
        roots: '.admin-dashboard-main, .users-main, .permissions-page, .records-page, .import-export-page, .batch-main, .generic-batch-page, .data-history-page, .activity-logs-page, .general-settings-page, .security-settings-page, .notification-settings-page, .analytics-overview-page, .analytics-stage-page',
    }),
    operation: Object.freeze({
        roots: '.operation-dashboard-main, .trip-records-page, .bus-master-list-page, .trip-schedule-page, .assignment-page, .auto-scheduling-page, .routes-page, .personnel-master-page, .driver-attendance-page, .mechanic-attendance-page, .inc-page, .ddr-page',
    }),
    maintenance: Object.freeze({
        roots: '.maintenance-dashboard-main, .referrals-page, .jo-page, .pms-page, .mechanic-page, .fuel-page, .purchase-page',
    }),
    warehouse: Object.freeze({
        roots: '.warehouse-dashboard-main, .warehouse-inventory-page, .warehouse-part-main, .stock-movement-page, .incoming-delivery-page',
    }),
    purchase: Object.freeze({
        roots: '.purchase-dashboard-main, .purchase-history-page, .purchase-orders-page, .scheduled-purchase-page, .purchase-maintenance-requests-page, .inventory-restock-page',
    }),
});

const MAJOR_PANEL_SELECTOR = [
    '.topbar',
    '.stats-grid',
    '.summary-grid',
    '.dashboard-grid',
    '[class*="dashboard-grid"]',
    '[class*="two-col-grid"]',
    '.table-card',
    '.content-card',
    '.filter-card',
    '.toolbar',
    '.analytics-card',
    '.analytics-panel',
    '.settings-card',
    '.form-card',
    'section',
].join(', ');

const MODAL_OVERLAY_SELECTOR = [
    '.modal-overlay',
    '.delete-modal-overlay',
    '.feedback-modal-overlay',
    '.batch-feedback-overlay',
    '.success-modal-overlay',
    '.ui-form-overlay',
    '.admin-modal-overlay',
    '.records-modal-overlay',
    '.batch-delete-modal-overlay',
    '.generic-batch-review-overlay',
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
    '.system-confirm-overlay',
    '.confirmation-modal-overlay',
    '[class*="modal-overlay"]',
    '[data-modal-overlay]',
    '[data-gct-modal-overlay]',
].join(', ');

const MODAL_SURFACE_SELECTOR = [
    '.ui-form-modal',
    '.modal-card',
    '.delete-modal-box',
    '.fuel-modal',
    '.pms-modal',
    '.success-modal-box',
    '.admin-modal',
    '.records-modal',
    '.history-modal',
    '.activity-modal',
    '.personnel-modal',
    '.route-modal',
    '.trip-modal',
    '.warehouse-view-modal',
    '.ai-resolution-modal',
    '[role="dialog"]',
].join(', ');

const DYNAMIC_ITEM_SELECTOR = [
    '[data-gct-animate-in]',
    '.part-needed-row',
    '.pms-task-row',
    '.validation-feedback',
    '.invalid-feedback',
    '.fuel-alert',
].join(', ');

const pageState = {
    root: null,
    panels: [],
    preparePromise: null,
    animation: null,
};

const modalStates = new WeakMap();
const pendingRegionElements = new Set();
let regionAnimationFrame = null;
let modalObserver = null;
let dynamicObserver = null;

const prefersReducedMotion = () => (
    window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false
);

const wait = (milliseconds) => new Promise((resolve) => {
    window.setTimeout(resolve, milliseconds);
});

const nextFrames = () => new Promise((resolve) => {
    requestAnimationFrame(() => requestAnimationFrame(resolve));
});

const currentModule = () => (
    document.getElementById('appSidebar')?.dataset.gctShell?.trim().toLowerCase() || null
);

const getPageRoot = () => {
    const moduleName = currentModule();
    const config = MODULE_PAGE_CONFIG[moduleName];
    const main = document.querySelector('main.main');

    if (!config || !main || !main.matches(config.roots)) {
        return null;
    }

    return main;
};

const getMajorPanels = (root) => {
    if (!root) return [];

    const directPanels = Array.from(root.children).filter((element) => (
        element.matches(MAJOR_PANEL_SELECTOR)
        && !element.matches('script, style, template')
    ));

    if (directPanels.length) return directPanels;

    return Array.from(root.children).filter((element) => (
        !element.matches('script, style, template, [hidden]')
    ));
};

const resetPageReveal = () => {
    pageState.animation?.kill();

    if (pageState.root?.isConnected) {
        gsap.set(pageState.panels, { clearProps: 'opacity,transform' });
        pageState.panels.forEach((panel) => panel.classList.remove('gct-system-reveal-panel'));
        pageState.root.classList.remove('gct-system-gsap-reveal');
        delete pageState.root.dataset.gctRevealState;
    }

    pageState.root = null;
    pageState.panels = [];
    pageState.preparePromise = null;
    pageState.animation = null;
};

const preparePageReveal = async () => {
    const root = getPageRoot();
    if (!root) {
        resetPageReveal();
        return false;
    }

    if (pageState.root === root && pageState.preparePromise) {
        return pageState.preparePromise;
    }

    resetPageReveal();
    pageState.root = root;
    pageState.panels = getMajorPanels(root);
    root.dataset.gctRevealState = 'preparing';
    root.classList.add('gct-system-gsap-reveal');

    gsap.killTweensOf(pageState.panels);
    pageState.panels.forEach((panel) => panel.classList.add('gct-system-reveal-panel'));
    gsap.set(pageState.panels, {
        opacity: 0,
        y: prefersReducedMotion() ? 4 : 10,
    });

    pageState.preparePromise = (async () => {
        if (document.fonts?.ready) {
            try {
                await Promise.race([document.fonts.ready, wait(600)]);
            } catch (error) {
                console.warn('Font readiness check failed; continuing with the page reveal.', error);
            }
        }

        await nextFrames();
        if (!root.isConnected || root !== getPageRoot()) return false;

        root.getBoundingClientRect();
        pageState.panels.forEach((panel) => panel.getBoundingClientRect());
        root.dataset.gctRevealState = 'ready';
        return true;
    })();

    return pageState.preparePromise;
};

const revealPage = ({ initialOpen = false } = {}) => {
    const root = getPageRoot();

    if (
        !root
        || pageState.root !== root
        || root.dataset.gctRevealState === 'revealing'
        || root.dataset.gctRevealState === 'shown'
    ) {
        return false;
    }

    root.dataset.gctRevealState = 'revealing';
    pageState.animation?.kill();
    gsap.killTweensOf(pageState.panels);

    if (!pageState.panels.length) {
        root.dataset.gctRevealState = 'shown';
        root.classList.remove('gct-system-gsap-reveal');
        return true;
    }

    const reduced = prefersReducedMotion();
    pageState.animation = gsap.to(pageState.panels, {
        opacity: 1,
        y: 0,
        duration: reduced ? 0.34 : (initialOpen ? 0.82 : 0.74),
        stagger: reduced ? 0.02 : (initialOpen ? 0.065 : 0.055),
        ease: 'power3.out',
        clearProps: 'opacity,transform',
        onComplete: () => {
            if (!root.isConnected) return;
            pageState.panels.forEach((panel) => panel.classList.remove('gct-system-reveal-panel'));
            root.dataset.gctRevealState = 'shown';
            root.classList.remove('gct-system-gsap-reveal');
            pageState.animation = null;
        },
    });

    return true;
};

const showPageImmediately = () => {
    const root = getPageRoot();
    if (!root) return false;

    pageState.animation?.kill();
    const panels = pageState.root === root ? pageState.panels : getMajorPanels(root);
    gsap.set(panels, { clearProps: 'opacity,transform' });
    panels.forEach((panel) => panel.classList.remove('gct-system-reveal-panel'));
    root.classList.remove('gct-system-gsap-reveal');
    root.dataset.gctRevealState = 'shown';
    pageState.root = root;
    pageState.panels = panels;
    pageState.animation = null;
    return true;
};

const isModalVisible = (overlay) => {
    if (!overlay?.isConnected || overlay.hidden) return false;
    if (overlay.getAttribute('aria-hidden') === 'true') return false;

    const style = getComputedStyle(overlay);
    return style.display !== 'none'
        && style.visibility !== 'hidden'
        && Number(style.opacity || 1) > 0;
};

const getModalSurface = (overlay) => {
    if (!overlay) return null;

    const explicitSurface = overlay.querySelector(MODAL_SURFACE_SELECTOR);
    if (explicitSurface) return explicitSurface;

    /*
     * Several legacy/module modals use their own surface class names while the
     * overlay still follows the "*-modal-overlay" convention. Fall back to the
     * first real child so the shared GSAP lifecycle can animate every module
     * without each feature re-implementing modal motion.
     */
    return Array.from(overlay.children).find((child) => (
        child instanceof HTMLElement
        && !child.matches('script, style, template')
    )) || null;
};

const clearModalAnimation = (overlay, surface) => {
    gsap.set([overlay, surface].filter(Boolean), { clearProps: 'opacity,transform' });
    overlay?.classList.remove('gct-system-modal-animated');
    surface?.classList.remove('gct-system-modal-surface-animated');
};

const animateModalOpen = (overlay) => {
    if (!overlay?.isConnected) return false;

    const existing = modalStates.get(overlay);
    if (existing?.phase === 'opening' || existing?.phase === 'shown') return false;

    const surface = getModalSurface(overlay);
    const reduced = prefersReducedMotion();
    const state = {
        phase: 'opening',
        surface,
        display: getComputedStyle(overlay).display === 'none'
            ? 'flex'
            : getComputedStyle(overlay).display,
        animation: null,
    };

    modalStates.set(overlay, state);
    overlay.classList.add('gct-system-modal-animated');
    surface?.classList.add('gct-system-modal-surface-animated');
    gsap.killTweensOf([overlay, surface].filter(Boolean));

    state.animation = gsap.timeline({
        onComplete: () => {
            clearModalAnimation(overlay, surface);
            state.phase = 'shown';
            state.animation = null;
        },
    });

    state.animation.fromTo(overlay, { opacity: 0 }, {
        opacity: 1,
        duration: reduced ? 0.14 : 0.22,
        ease: 'power1.out',
        overwrite: 'auto',
    });

    if (surface) {
        state.animation.fromTo(surface, {
            opacity: 0,
            y: reduced ? 5 : 12,
            scale: reduced ? 0.995 : 0.985,
            force3D: true,
        }, {
            opacity: 1,
            y: 0,
            scale: 1,
            force3D: true,
            duration: reduced ? 0.18 : 0.30,
            ease: 'power2.out',
            overwrite: 'auto',
        }, 0);
    }

    return true;
};

const animateModalClose = (overlay, finalize = null, { restoreHiddenState = false } = {}) => {
    if (!overlay?.isConnected) {
        finalize?.();
        return false;
    }

    const previous = modalStates.get(overlay) || {};
    if (previous.phase === 'closing') return false;

    const surface = previous.surface || getModalSurface(overlay);
    const wasHidden = overlay.hidden;
    const ariaHidden = overlay.getAttribute('aria-hidden');
    const reduced = prefersReducedMotion();
    const state = {
        ...previous,
        phase: 'closing',
        surface,
        animation: null,
    };

    modalStates.set(overlay, state);
    overlay.classList.add('gct-system-modal-animated');
    surface?.classList.add('gct-system-modal-surface-animated');

    if (restoreHiddenState) {
        overlay.hidden = false;
        overlay.style.setProperty('display', previous.display || 'flex', 'important');
    }

    gsap.killTweensOf([overlay, surface].filter(Boolean));
    state.animation = gsap.timeline({
        onComplete: () => {
            clearModalAnimation(overlay, surface);
            overlay.style.removeProperty('display');
            if (restoreHiddenState && wasHidden) overlay.hidden = true;
            if (restoreHiddenState && ariaHidden !== null) {
                overlay.setAttribute('aria-hidden', ariaHidden);
            }
            state.phase = 'idle';
            state.animation = null;
            finalize?.();
            window.GCTModalBackdrop?.sync?.();
        },
    });

    if (surface) {
        state.animation.to(surface, {
            opacity: 0,
            y: reduced ? 4 : 8,
            scale: reduced ? 0.997 : 0.992,
            force3D: true,
            duration: reduced ? 0.12 : 0.18,
            ease: 'power2.inOut',
            overwrite: 'auto',
        }, 0);
    }

    state.animation.to(overlay, {
        opacity: 0,
        duration: reduced ? 0.10 : 0.18,
        ease: 'power1.inOut',
        overwrite: 'auto',
    }, 0);

    return true;
};

const syncModalAnimation = (overlay) => {
    if (!(overlay instanceof Element) || !overlay.matches(MODAL_OVERLAY_SELECTOR)) return;

    const state = modalStates.get(overlay);

    /*
     * Ignore mutations produced by our own GSAP open/close timelines.
     * Without this guard, restoring a hidden overlay for its close animation
     * makes the observer see it as visible and can restart the open animation.
     */
    if (state?.phase === 'opening' || state?.phase === 'closing') {
        return;
    }

    const visible = isModalVisible(overlay);

    if (visible) {
        animateModalOpen(overlay);
    } else if (state?.phase === 'shown') {
        animateModalClose(overlay, null, { restoreHiddenState: true });
    }
};

const queueRegionAnimation = (element) => {
    if (!(element instanceof Element) || !element.isConnected) return;

    pendingRegionElements.add(element);
    if (regionAnimationFrame) return;

    regionAnimationFrame = requestAnimationFrame(() => {
        const elements = Array.from(pendingRegionElements).filter((node) => node.isConnected);
        pendingRegionElements.clear();
        regionAnimationFrame = null;
        if (!elements.length) return;

        gsap.killTweensOf(elements);
        elements.forEach((node) => node.classList.add('gct-system-region-animating'));
        gsap.fromTo(elements, { opacity: 0.76, y: prefersReducedMotion() ? 0 : 3 }, {
            opacity: 1,
            y: 0,
            duration: prefersReducedMotion() ? 0.12 : 0.22,
            ease: 'power2.out',
            clearProps: 'opacity,transform',
            onComplete: () => {
                elements.forEach((node) => node.classList.remove('gct-system-region-animating'));
            },
        });
    });
};

const animateDynamicItem = (element) => {
    if (!(element instanceof Element) || element.closest('[data-ajax-region]')) return;

    const targets = [
        ...(element.matches(DYNAMIC_ITEM_SELECTOR) ? [element] : []),
        ...element.querySelectorAll(DYNAMIC_ITEM_SELECTOR),
    ];

    if (!targets.length) return;
    gsap.killTweensOf(targets);
    gsap.fromTo(targets, { opacity: 0, y: prefersReducedMotion() ? 0 : 6 }, {
        opacity: 1,
        y: 0,
        duration: prefersReducedMotion() ? 0.14 : 0.26,
        stagger: 0.025,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
    });
};

const animateToastIn = (toast) => {
    if (!toast?.isConnected || toast.dataset.gctToastState === 'shown') return false;
    toast.dataset.gctToastState = 'shown';
    toast.classList.add('is-visible');
    gsap.killTweensOf(toast);
    gsap.fromTo(toast, { opacity: 0, y: -8 }, {
        opacity: 1,
        y: 0,
        duration: prefersReducedMotion() ? 0.14 : 0.24,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
    });
    return true;
};

const animateToastOut = (toast, onComplete) => {
    if (!toast?.isConnected || toast.dataset.gctToastState === 'removing') return false;
    toast.dataset.gctToastState = 'removing';
    gsap.killTweensOf(toast);
    gsap.to(toast, {
        opacity: 0,
        y: -6,
        duration: prefersReducedMotion() ? 0.10 : 0.18,
        ease: 'power1.in',
        onComplete,
    });
    return true;
};

const ensureObservers = () => {
    if (!document.body) return;

    modalObserver?.disconnect();
    const observeOverlay = (overlay) => {
        modalObserver.observe(overlay, {
            attributes: true,
            attributeOldValue: true,
            attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'],
        });
    };

    modalObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes') {
                syncModalAnimation(mutation.target);
                return;
            }

            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches(MODAL_OVERLAY_SELECTOR)) {
                    observeOverlay(node);
                    syncModalAnimation(node);
                }
                node.querySelectorAll?.(MODAL_OVERLAY_SELECTOR).forEach((overlay) => {
                    observeOverlay(overlay);
                    syncModalAnimation(overlay);
                });
            });
        });
    });
    modalObserver.observe(document.body, {
        subtree: true,
        childList: true,
    });

    dynamicObserver?.disconnect();
    dynamicObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach(animateDynamicItem);
        });
    });
    dynamicObserver.observe(document.body, { subtree: true, childList: true });

    document.querySelectorAll(MODAL_OVERLAY_SELECTOR).forEach((overlay) => {
        observeOverlay(overlay);
        syncModalAnimation(overlay);
    });
};

const initializeSystemAnimations = () => {
    ensureObservers();
    void preparePageReveal().then((ready) => {
        if (!ready) return;

        const root = getPageRoot();
        const navigationCovered = document.body.classList.contains('gct-navigation-loading')
            || root?.classList.contains('gct-main-loader-hold')
            || root?.classList.contains('gct-main-entering');

        if (!navigationCovered) revealPage({ initialOpen: true });
    }).catch((error) => {
        console.warn('Shared page animation initialization failed; showing the page immediately.', error);
        showPageImmediately();
    });
};

document.addEventListener('system:region-replaced', (event) => {
    queueRegionAnimation(event.detail?.element);
});

document.addEventListener('ajax:content-updated', (event) => {
    const names = event.detail?.regions || event.detail?.names || [];
    names.forEach((name) => queueRegionAnimation(window.GCTRegions?.get?.(name)));
});

/*
 * Server-side instant search replaces only the table body and intentionally
 * does not publish named AJAX regions. Animate that replaced table content
 * directly so search/filter updates stay local and never replay page reveal.
 */
document.addEventListener('system:table-filtered', (event) => {
    const table = event.detail?.table;
    const body = table?.tBodies?.[0];

    queueRegionAnimation(body || table);
});

window.addEventListener('gct:navigation-before', resetPageReveal);

window.GCTSystemAnimations = Object.freeze({
    moduleConfig: MODULE_PAGE_CONFIG,
    isPageApplicable: () => Boolean(getPageRoot()),
    preparePageReveal,
    revealPage,
    resetPageReveal,
    showPageImmediately,
    animateModalOpen,
    animateModalClose,
    animateToastIn,
    animateToastOut,
    queueRegionAnimation,
});

if (window.GCTPartialNavigation?.registerInitializer) {
    window.GCTPartialNavigation.registerInitializer(
        'system-animations',
        'main.main',
        initializeSystemAnimations
    );
} else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeSystemAnimations, { once: true });
} else {
    initializeSystemAnimations();
}

export { MODULE_PAGE_CONFIG, initializeSystemAnimations };
