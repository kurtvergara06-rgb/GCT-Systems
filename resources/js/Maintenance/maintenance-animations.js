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

const JO_OWNED_MODAL_IDS = new Set([
  'jobModal',
  'editJobModal',
  'finishJobModal',
  'deleteJobModal',
]);

let modalObserver = null;
let sidebarObserver = null;
let preparedRevealRoot = null;
let preparedRevealPromise = null;
let activeRevealAnimation = null;

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

const visibleRows = (root) => (
  Array.from(
    root.querySelectorAll(
      'tbody tr'
    )
  )
    .filter((row) => (
      row.offsetParent !== null
      && !row.querySelector(
        '.empty-state, [data-empty-state]'
      )
    ))
    .slice(0, 18)
);

const animateRows = (root) => {
  if (!root) {
    return;
  }

  const rows = visibleRows(root);

  if (!rows.length) {
    return;
  }

  gsap.killTweensOf(rows);

  gsap.fromTo(
    rows,
    {
      opacity: 0.72,
    },
    {
      opacity: 1,
      duration:
        prefersReducedMotion()
          ? 0.14
          : 0.20,
      ease: 'power1.out',
      clearProps: 'opacity',
    }
  );
};

const animateSummaryRefresh = (root) => {
  if (!root) {
    return;
  }

  const cards = root.querySelectorAll(
    '[data-ajax-region="summary"] > *'
  );

  if (!cards.length) {
    return;
  }

  gsap.killTweensOf(cards);

  gsap.fromTo(
    cards,
    {
      opacity: 0.78,
    },
    {
      opacity: 1,
      duration:
        prefersReducedMotion()
          ? 0.14
          : 0.20,
      ease: 'power1.out',
      clearProps: 'opacity',
    }
  );
};

const wait = (ms) => (
  new Promise(
    (resolve) => window.setTimeout(
      resolve,
      ms
    )
  )
);

const nextFrames = () => (
  new Promise(
    (resolve) => {
      window.requestAnimationFrame(
        () => window.requestAnimationFrame(
          resolve
        )
      );
    }
  )
);

const waitForFonts = async () => {
  if (!document.fonts?.ready) {
    return;
  }

  await Promise.race([
    document.fonts.ready,
    wait(600),
  ]);
};

const measureStableMaintenanceLayout = (
  root
) => {
  root.getBoundingClientRect();

  root
    .querySelectorAll(
      '.stats-grid, '
      + '.table-card, '
      + '.maintenance-dashboard-grid, '
      + '.maintenance-two-col-grid, '
      + '.fuel-analytics-grid, '
      + '.fuel-insights-grid, '
      + '.fuel-monitoring-refined, '
      + '.jo-table-card, '
      + 'table'
    )
    .forEach(
      (element) => {
        element.getBoundingClientRect();
      }
    );
};

const resetPreparedReveal = () => {
  activeRevealAnimation?.kill();
  activeRevealAnimation = null;
  preparedRevealRoot = null;
  preparedRevealPromise = null;
};

const dashboardRevealPanels = (root) => {
  if (
    !root?.classList.contains(
      'maintenance-dashboard-main'
    )
  ) {
    return [];
  }

  return Array.from(
    root.children
  ).filter(
    (element) => element.matches(
      '.topbar, '
      + '.maintenance-stats-grid, '
      + '.maintenance-dashboard-grid, '
      + '.maintenance-two-col-grid'
    )
  );
};

const animateDashboardPanels = (
  root,
  {
    initialOpen = false,
    onComplete = null,
  } = {}
) => {
  const panels =
    dashboardRevealPanels(root);

  if (!panels.length) {
    onComplete?.();
    return null;
  }

  const reduced =
    prefersReducedMotion();

  gsap.killTweensOf(panels);

  return gsap.to(
    panels,
    {
      opacity: 1,
      y: 0,
      duration:
        initialOpen
          ? (reduced ? 0.42 : 0.58)
          : (reduced ? 0.36 : 0.50),
      stagger:
        initialOpen
          ? (reduced ? 0.025 : 0.045)
          : (reduced ? 0.02 : 0.035),
      ease: 'power3.out',
      clearProps:
        'opacity,transform',
      onComplete,
    }
  );
};

const prepareMaintenanceReveal = async () => {
  const root = getMaintenanceRoot();

  if (!root) {
    resetPreparedReveal();
    return false;
  }

  if (
    preparedRevealRoot === root
    && preparedRevealPromise
  ) {
    return preparedRevealPromise;
  }

  preparedRevealRoot = root;

  gsap.killTweensOf(root);

  root.classList.add(
    'gct-maintenance-gsap-reveal'
  );

  gsap.set(
    root,
    {
      opacity: 0,
    }
  );

  const dashboardPanels =
    dashboardRevealPanels(root);

  if (dashboardPanels.length) {
    gsap.killTweensOf(
      dashboardPanels
    );
    gsap.set(
      dashboardPanels,
      {
        opacity: 0,
        y: prefersReducedMotion()
          ? 4
          : 10,
      }
    );
  }

  root.dataset.gctRevealState =
    'preparing';

  preparedRevealPromise = (
    async () => {
      await waitForFonts();
      await nextFrames();

      if (!root.isConnected) {
        return false;
      }

      measureStableMaintenanceLayout(
        root
      );

      await nextFrames();

      if (!root.isConnected) {
        return false;
      }

      root.dataset.gctRevealState =
        'ready';

      return true;
    }
  )();

  return preparedRevealPromise;
};

const revealMaintenancePage = ({
  initialOpen = false,
} = {}) => {
  const root = getMaintenanceRoot();

  if (!root) {
    return false;
  }

  const reduced =
    prefersReducedMotion();

  if (
    root.dataset.gctRevealState
      === 'revealing'
    || root.dataset.gctRevealState
      === 'shown'
  ) {
    return false;
  }

  const isDashboard =
    root.classList.contains(
      'maintenance-dashboard-main'
    );

  const yOffset =
    initialOpen
      ? (reduced ? 34 : 72)
      : (reduced ? 28 : 56);

  const startScale =
    initialOpen
      ? (reduced ? 0.975 : 0.94)
      : (reduced ? 0.98 : 0.955);

  const duration =
    initialOpen
      ? (reduced ? 0.78 : 1.15)
      : (reduced ? 0.62 : 0.90);

  gsap.killTweensOf(root);
  activeRevealAnimation?.kill();
  activeRevealAnimation = null;

  root.dataset.gctRevealState =
    'revealing';

  const finishReveal = () => {
    if (!root.isConnected) {
      return;
    }

    root.dataset.gctRevealState =
      'shown';
    root.classList.remove(
      'gct-maintenance-gsap-reveal'
    );
    activeRevealAnimation = null;
  };

  if (isDashboard) {
    /*
     * The dashboard uses one panel sequence only. Making the completed root
     * visible without tweening it prevents the generic main transition and
     * the nested dashboard panels from animating on top of each other.
     */
    gsap.set(
      root,
      {
        opacity: 1,
        y: 0,
        scale: 1,
        clearProps:
          'opacity,transform,transformOrigin',
      }
    );

    activeRevealAnimation =
      animateDashboardPanels(
        root,
        {
          initialOpen,
          onComplete: finishReveal,
        }
      );

    return true;
  }

  /*
   * Animate the already-finished page as one unit.
   * Direct page open is intentionally slower so the GSAP motion is obvious,
   * while partial navigation stays a little faster.
   */
  activeRevealAnimation = gsap.fromTo(
    root,
    {
      opacity: 0,
      y: yOffset,
      scale: startScale,
      transformOrigin: '50% 12%',
    },
    {
      opacity: 1,
      y: 0,
      scale: 1,
      duration,
      ease: 'power3.out',
      clearProps:
        'opacity,transform,transformOrigin',
      onComplete: finishReveal,
    }
  );

  return true;
};

window.GCTMaintenanceReveal =
  Object.freeze({
    isApplicable: () => (
      Boolean(
        getMaintenanceRoot()
      )
    ),
    prepare:
      prepareMaintenanceReveal,
    reveal:
      revealMaintenancePage,
  });

const getModalSurface = (overlay) => (
  overlay?.querySelector(
    '.ui-form-modal, '
    + '.modal-card, '
    + '.delete-modal-box, '
    + '.fuel-modal, '
    + '.pms-modal, '
    + '.success-modal-box'
  ) || null
);

const isModalOpen = (overlay) => {
  if (!overlay) {
    return false;
  }

  if (
    overlay.hidden
    || overlay.getAttribute(
      'aria-hidden'
    ) === 'true'
  ) {
    return false;
  }

  const openClass = [
    'show',
    'active',
    'open',
    'is-open',
  ].some(
    (className) => (
      overlay.classList.contains(
        className
      )
    )
  );

  if (openClass) {
    return true;
  }

  const style =
    window.getComputedStyle(
      overlay
    );

  return (
    style.display !== 'none'
    && style.visibility !== 'hidden'
    && Number(style.opacity || 1) > 0
  );
};

const animateModalOpen = (overlay) => {
  if (
    !overlay
    || JO_OWNED_MODAL_IDS.has(
      overlay.id
    )
  ) {
    return;
  }

  if (
    overlay.dataset
      .maintenanceGsapOpen === 'true'
  ) {
    return;
  }

  overlay.dataset
    .maintenanceGsapOpen = 'true';

  const surface =
    getModalSurface(
      overlay
    );

  const reduced = prefersReducedMotion();
  const surfaceY = reduced ? 20 : 38;
  const surfaceScale = reduced ? 0.975 : 0.94;
  const duration = reduced ? 0.34 : 0.46;

  gsap.killTweensOf(
    overlay
  );

  gsap.fromTo(
    overlay,
    {
      opacity: 0,
    },
    {
      opacity: 1,
      duration: reduced ? 0.22 : 0.28,
      ease: 'power2.out',
      clearProps: 'opacity',
    }
  );

  if (!surface) {
    return;
  }

  gsap.killTweensOf(
    surface
  );

  gsap.fromTo(
    surface,
    {
      opacity: 0,
      y: surfaceY,
      scale: surfaceScale,
    },
    {
      opacity: 1,
      y: 0,
      scale: 1,
      duration: duration,
      ease: 'power2.out',
      clearProps: 'opacity,transform',
    }
  );
};

const animateInsertedRow = (node) => {
  if (
    !(node instanceof Element)
    || !getMaintenanceRoot()
  ) {
    return;
  }

  const reduced =
    prefersReducedMotion();

  const rows = [];

  if (
    node.matches(
      '.part-needed-row, '
      + '.pms-task-row'
    )
  ) {
    rows.push(node);
  }

  node
    .querySelectorAll?.(
      '.part-needed-row, '
      + '.pms-task-row'
    )
    .forEach(
      (row) => rows.push(row)
    );

  if (!rows.length) {
    return;
  }

  gsap.fromTo(
    rows,
    {
      opacity: 0,
      x: reduced ? -7 : -14,
      y: reduced ? -3 : -6,
    },
    {
      opacity: 1,
      x: 0,
      y: 0,
      duration: reduced ? 0.26 : 0.36,
      stagger: reduced ? 0.02 : 0.04,
      ease: 'power3.out',
      clearProps: 'opacity,transform',
    }
  );
};

const syncModalAnimation = (overlay) => {
  if (
    !(overlay instanceof Element)
    || !overlay.matches(
      MODAL_OVERLAY_SELECTOR
    )
  ) {
    return;
  }

  if (!getMaintenanceRoot()) {
    return;
  }

  if (
    isModalOpen(
      overlay
    )
  ) {
    animateModalOpen(
      overlay
    );
    return;
  }

  delete overlay.dataset
    .maintenanceGsapOpen;
};

const scanOpenModals = () => {
  if (!getMaintenanceRoot()) {
    return;
  }

  document
    .querySelectorAll(
      MODAL_OVERLAY_SELECTOR
    )
    .forEach(
      syncModalAnimation
    );
};

const ensureModalObserver = () => {
  if (!document.body) {
    return;
  }

  modalObserver?.disconnect();

  modalObserver =
    new MutationObserver(
      (mutations) => {
        if (!getMaintenanceRoot()) {
          return;
        }

        mutations.forEach(
          (mutation) => {
            if (
              mutation.type ===
              'attributes'
            ) {
              syncModalAnimation(
                mutation.target
              );
              return;
            }

            mutation.addedNodes
              .forEach(
                (node) => {
                  if (
                    !(node instanceof Element)
                  ) {
                    return;
                  }

                  animateInsertedRow(
                    node
                  );

                  if (
                    node.matches(
                      MODAL_OVERLAY_SELECTOR
                    )
                  ) {
                    syncModalAnimation(
                      node
                    );
                  }

                  node
                    .querySelectorAll?.(
                      MODAL_OVERLAY_SELECTOR
                    )
                    .forEach(
                      syncModalAnimation
                    );
                }
              );
          }
        );
      }
    );

  modalObserver.observe(
    document.body,
    {
      subtree: true,
      childList: true,
      attributes: true,
      attributeFilter: [
        'class',
        'style',
        'hidden',
        'aria-hidden',
      ],
    }
  );
};

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

const animateOpenedSubmenu = (
  dropdown
) => {
  if (
    !dropdown
    || !dropdown.classList.contains(
      'open'
    )
  ) {
    return;
  }

  const submenu =
    dropdown.querySelector(
      '.submenu'
    );

  if (!submenu) {
    return;
  }

  const items =
    submenu.querySelectorAll(
      '.submenu-item'
    );

  const reduced = prefersReducedMotion();
  const subY = reduced ? -7 : -14;
  const itemX = reduced ? -5 : -12;

  gsap.fromTo(
    submenu,
    {
      opacity: 0,
      y: subY,
    },
    {
      opacity: 1,
      y: 0,
      duration: reduced ? 0.20 : 0.28,
      ease: 'power2.out',
      clearProps: 'opacity,transform',
    }
  );

  if (items.length) {
    gsap.fromTo(
      items,
      {
        opacity: 0.55,
        x: itemX,
      },
      {
        opacity: 1,
        x: 0,
        duration: reduced ? 0.24 : 0.34,
        stagger: reduced ? 0.03 : 0.055,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      }
    );
  }
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

            if (
              target instanceof Element
              && target.classList
                .contains(
                  'menu-dropdown'
                )
            ) {
              animateOpenedSubmenu(
                target
              );
              return;
            }

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

  document.addEventListener(
    'ajax:content-updated',
    () => {
      const root =
        getMaintenanceRoot();

      if (!root) {
        return;
      }

      animateSummaryRefresh(
        root
      );

      animateRows(
        root
      );

      scanOpenModals();
    }
  );

  window.addEventListener(
    'gct:navigation-before',
    () => {
      resetPreparedReveal();
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
      scanOpenModals();
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
  ensureModalObserver();
  ensureSidebarObserver();

  void prepareMaintenanceReveal()
    .then(
      (ready) => {
        if (!ready) {
          return;
        }

        const main =
          root.closest(
            'main'
          );

        const navigationCovered =
          document.body.classList
            .contains(
              'gct-navigation-loading'
            )
          || main?.classList
            .contains(
              'gct-main-loader-hold'
            )
          || main?.classList
            .contains(
              'gct-main-entering'
            );

        /*
         * During partial navigation, page-transitions.js owns the reveal
         * and crossfades this already-laid-out root with the loader.
         * On a hard load there is no navigation cover, so reveal here.
         */
        if (!navigationCovered) {
          revealMaintenancePage({
            initialOpen: true,
          });
        }
      }
    );

  animateSidebarActiveItem();
  scanOpenModals();
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
