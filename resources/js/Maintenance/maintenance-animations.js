import gsap from 'gsap';

const MAINTENANCE_ROOT_SELECTOR = [
  '.maintenance-dashboard-main',
  '.referrals-page',
  '.jo-page',
  '.pms-page',
  '.mechanic-page',
  '.fuel-page',
  '.purchase-page',
].join(', ');

const MODAL_OVERLAY_SELECTOR = [
  '.ui-form-overlay',
  '.modal-overlay',
  '.delete-modal-overlay',
  '.fuel-modal-overlay',
  '.pms-modal-overlay',
].join(', ');

let sidebarObserver = null;

const prefersReducedMotion = () => {
  if (
    window.__GCT_FORCE_MOTION__ === true
    || window.__GCT_ENABLE_MOTION__ === true
    || document.documentElement.dataset.gctMotion === 'enabled'
  ) {
    return false;
  }

  return (
    window.matchMedia?.(
      '(prefers-reduced-motion: reduce)'
    )?.matches ?? false
  );
};

const getMaintenanceRoot = () => (
  document.querySelector(
    MAINTENANCE_ROOT_SELECTOR
  )
);

const isMaintenanceShell = () => (
  document
    .getElementById('appSidebar')
    ?.dataset
    .gctShell === 'maintenance'
);

/*
 * Backward-compatible facade for older Maintenance callers. Page reveal
 * ownership now lives in the shared system animation lifecycle.
 */
window.GCTMaintenanceReveal =
  Object.freeze({
    isApplicable: () => (
      window.GCTSystemAnimations
        ?.isPageApplicable?.() === true
    ),
    prepare: () => (
      window.GCTSystemAnimations
        ?.preparePageReveal?.() ?? false
    ),
    reveal: (options) => (
      window.GCTSystemAnimations
        ?.revealPage?.(options) ?? false
    ),
  });

const animateSidebarActiveItem = () => {
  if (
    !isMaintenanceShell()
  ) {
    return;
  }

  const activeItems =
    document.querySelectorAll(
      '#appSidebar .menu-item.active, '
      + '#appSidebar .submenu-item.active'
    );

  if (!activeItems.length) {
    return;
  }

  const reduced = prefersReducedMotion();
  const xOffset = reduced ? -7 : -14;

  gsap.fromTo(
    activeItems,
    {
      opacity: 0.55,
      x: xOffset,
    },
    {
      opacity: 1,
      x: 0,
      duration: reduced ? 0.28 : 0.38,
      ease: 'power3.out',
      clearProps: 'opacity,transform',
    }
  );
};

const animateSidebarShellState = (
  sidebar
) => {
  if (!sidebar) {
    return;
  }

  const reduced =
    prefersReducedMotion();

  const icons =
    sidebar.querySelectorAll(
      '.brand-icon, '
      + '.menu-item > i, '
      + '.submenu-item > i'
    );

  if (!icons.length) {
    return;
  }

  gsap.fromTo(
    icons,
    {
      opacity: reduced ? 0.78 : 0.6,
      scale: reduced ? 0.97 : 0.92,
    },
    {
      opacity: 1,
      scale: 1,
      duration: reduced ? 0.18 : 0.26,
      stagger: reduced ? 0.01 : 0.02,
      ease: 'power2.out',
      clearProps: 'opacity,transform',
    }
  );
};

const ensureSidebarObserver = () => {
  const sidebar =
    document.getElementById(
      'appSidebar'
    );

  if (
    !sidebar
    || sidebar.dataset
      .gctShell !== 'maintenance'
  ) {
    sidebarObserver?.disconnect();
    sidebarObserver = null;
    return;
  }

  sidebarObserver?.disconnect();

  sidebarObserver =
    new MutationObserver(
      (mutations) => {
        mutations.forEach(
          (mutation) => {
            const target =
              mutation.target;

            if (target === sidebar) {
              animateSidebarShellState(
                sidebar
              );
            }
          }
        );
      }
    );

  sidebarObserver.observe(
    sidebar,
    {
      subtree: true,
      attributes: true,
      attributeFilter: [
        'class',
      ],
    }
  );
};

const bindGlobalInteractions = () => {
  const pressSelector = [
    '.primary-btn',
    '.secondary-btn',
    '.action-btn',
    '.pms-add-btn',
    '.btn-ref-action',
    '.manual-entry-btn',
    '.daily-save-btn',
  ].join(', ');

  document.addEventListener(
    'pointerdown',
    (event) => {
      const root =
        getMaintenanceRoot();

      if (!root) {
        return;
      }

      const button =
        event.target.closest?.(
          pressSelector
        );

      if (
        !button
        || button.disabled
        || !(
          root.contains(button)
          || button.closest(
            MODAL_OVERLAY_SELECTOR
          )
        )
      ) {
        return;
      }

      const reduced = prefersReducedMotion();
      const scaleTarget = reduced ? 0.985 : 0.965;

      gsap.to(
        button,
        {
          scale: scaleTarget,
          duration: 0.08,
          ease: 'power1.out',
          overwrite: true,
        }
      );
    }
  );

  const releasePressedButton = (
    event
  ) => {
    if (!getMaintenanceRoot()) {
      return;
    }

    const button =
      event.target.closest?.(
        pressSelector
      );

    if (!button) {
      return;
    }

    gsap.to(
      button,
      {
        scale: 1,
        duration: 0.10,
        ease: 'power1.out',
        overwrite: true,
        clearProps: 'transform',
      }
    );
  };

  document.addEventListener(
    'pointerup',
    releasePressedButton
  );

  document.addEventListener(
    'pointercancel',
    releasePressedButton
  );

  document.addEventListener(
    'pointerover',
    (event) => {
      if (!isMaintenanceShell()) {
        return;
      }

      const item =
        event.target.closest?.(
          '#appSidebar .menu-item, '
          + '#appSidebar .submenu-item'
        );

      if (
        !item
        || (
          event.relatedTarget instanceof Node
          && item.contains(
            event.relatedTarget
          )
        )
      ) {
        return;
      }

      gsap.to(
        item,
        {
          x: 10,
          duration: 0.24,
          ease: 'power2.out',
          overwrite: true,
        }
      );
    }
  );

  document.addEventListener(
    'pointerout',
    (event) => {
      if (!isMaintenanceShell()) {
        return;
      }

      const item =
        event.target.closest?.(
          '#appSidebar .menu-item, '
          + '#appSidebar .submenu-item'
        );

      if (
        !item
        || (
          event.relatedTarget instanceof Node
          && item.contains(
            event.relatedTarget
          )
        )
      ) {
        return;
      }

      gsap.to(
        item,
        {
          x: 0,
          duration: 0.24,
          ease: 'power2.out',
          overwrite: true,
          clearProps: 'transform',
        }
      );
    }
  );

  window.addEventListener(
    'gct:navigation-ready',
    () => {
      if (!getMaintenanceRoot()) {
        return;
      }

      ensureSidebarObserver();
      animateSidebarActiveItem();
    }
  );
};

const initializeMaintenanceAnimations = () => {
  const root =
    getMaintenanceRoot();

  if (!root) {
    return;
  }

  bindGlobalInteractions();
  ensureSidebarObserver();

  animateSidebarActiveItem();
};

if (
  window.GCTPartialNavigation
    ?.registerInitializer
) {
  window.GCTPartialNavigation
    .registerInitializer(
      'maintenance-simple-gsap',
      MAINTENANCE_ROOT_SELECTOR,
      initializeMaintenanceAnimations
    );
} else {
  document.addEventListener(
    'DOMContentLoaded',
    initializeMaintenanceAnimations
  );
}

export {
  initializeMaintenanceAnimations,
};
