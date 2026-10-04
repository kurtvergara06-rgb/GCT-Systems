import gsap from 'gsap';

const MODULE_PAGE_CONFIG = Object.freeze({
    admin: Object.freeze({
        roots: '.admin-dashboard-main, .users-main, .permissions-page, .records-page, .import-export-page, .batch-main, .generic-batch-page, .data-history-page, .activity-logs-page, .general-settings-page, .security-settings-page, .notification-settings-page, .analytics-overview-page, .analytics-stage-page',
    }),
    operation: Object.freeze({
        roots: '.trip-records-page, .bus-master-list-page, .trip-schedule-page, .assignment-page, .auto-scheduling-page, .routes-page, .personnel-master-page, .driver-attendance-page, .mechanic-attendance-page, .inc-page, .ddr-page, main.main',
    }),
    maintenance: Object.freeze({
        roots: '.maintenance-dashboard-main, .referrals-page, .jo-page, .pms-page, .mechanic-page, .fuel-page, .purchase-page',
    }),
    warehouse: Object.freeze({
        roots: '.warehouse-dashboard-main, .warehouse-inventory-page, .warehouse-part-main, .stock-movement-page, .incoming-delivery-page',
    }),
    purchase: Object.freeze({
        roots: '.purchase-dashboard-main, .purchase-orders-page, .scheduled-purchase-page, .purchase-maintenance-requests-page, .inventory-restock-page, main.main',
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
    gsap.set(pageState.panels, {
        opacity: 0,
        y: prefersReducedMotion() ? 4 : 10,
    });

    pageState.preparePromise = (async () => {
        if (document.fonts?.ready) {
            await Promise.race([document.fonts.ready, wait(600)]);
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
            root.dataset.gctRevealState = 'shown';
            root.classList.remove('gct-system-gsap-reveal');
            pageState.animation = null;
        },
    });

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

const getModalSurface = (overlay) => overlay?.querySelector(MODAL_SURFACE_SELECTOR) || null;

const clearModalAnimation = (overlay, surface) => {
    gsap.set([overlay, surface].filter(Boolean), { clearProps: 'opacity,transform' });
    overlay?.classList.remove('gct-system-modal-animated');
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
        duration: reduced ? 0.16 : 0.24,
        ease: 'power2.out',
    });

    if (surface) {
        state.animation.fromTo(surface, {
            opacity: 0,
            y: reduced ? 8 : 18,
            scale: reduced ? 0.99 : 0.975,
        }, {
            opacity: 1,
            y: 0,
            scale: 1,
            duration: reduced ? 0.22 : 0.34,
            ease: 'power3.out',
        }, 0.01);
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
            y: reduced ? 6 : 12,
            scale: reduced ? 0.995 : 0.985,
            duration: reduced ? 0.14 : 0.20,
            ease: 'power1.in',
        }, 0);
    }

    state.animation.to(overlay, {
        opacity: 0,
        duration: reduced ? 0.12 : 0.18,
        ease: 'power2.in',
    }, 0);

    return true;
};

const syncModalAnimation = (overlay) => {
    if (!(overlay instanceof Element) || !overlay.matches(MODAL_OVERLAY_SELECTOR)) return;

    const state = modalStates.get(overlay);
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
        gsap.fromTo(elements, { opacity: 0.76, y: prefersReducedMotion() ? 0 : 3 }, {
            opacity: 1,
            y: 0,
            duration: prefersReducedMotion() ? 0.12 : 0.22,
            ease: 'power2.out',
            clearProps: 'opacity,transform',
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
    modalObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes') {
                syncModalAnimation(mutation.target);
                return;
            }

            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof Element)) return;
                if (node.matches(MODAL_OVERLAY_SELECTOR)) syncModalAnimation(node);
                node.querySelectorAll?.(MODAL_OVERLAY_SELECTOR).forEach(syncModalAnimation);
            });
        });
    });
    modalObserver.observe(document.body, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeOldValue: true,
        attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'],
    });

    dynamicObserver?.disconnect();
    dynamicObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach(animateDynamicItem);
        });
    });
    dynamicObserver.observe(document.body, { subtree: true, childList: true });

    document.querySelectorAll(MODAL_OVERLAY_SELECTOR).forEach(syncModalAnimation);
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
    });
};

document.addEventListener('system:region-replaced', (event) => {
    queueRegionAnimation(event.detail?.element);
});

document.addEventListener('ajax:content-updated', (event) => {
    const names = event.detail?.regions || event.detail?.names || [];
    names.forEach((name) => queueRegionAnimation(window.GCTRegions?.get?.(name)));
});

window.addEventListener('gct:navigation-before', resetPageReveal);

window.GCTSystemAnimations = Object.freeze({
    moduleConfig: MODULE_PAGE_CONFIG,
    isPageApplicable: () => Boolean(getPageRoot()),
    preparePageReveal,
    revealPage,
    resetPageReveal,
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
