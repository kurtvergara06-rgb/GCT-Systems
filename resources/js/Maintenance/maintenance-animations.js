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
let globalInteractionsBound = false;

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

  const reduced = prefersReducedMotion();
  const yOffset = reduced ? 3 : 5;
  const duration = reduced ? 0.16 : 0.22;

  gsap.killTweensOf(rows);

  gsap.fromTo(
    rows,
    {
      opacity: 0.65,
      y: yOffset,
    },
    {
      opacity: 1,
      y: 0,
      duration: duration,
      stagger: 0.015,
      ease: 'power2.out',
      clearProps: 'opacity,transform',
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

  const reduced = prefersReducedMotion();
  const yOffset = reduced ? 3 : 6;
  const duration = reduced ? 0.16 : 0.22;

  gsap.killTweensOf(cards);

  gsap.fromTo(
    cards,
    {
      opacity: 0.75,
      y: yOffset,
    },
    {
      opacity: 1,
      y: 0,
      duration: duration,
      stagger: 0.025,
      ease: 'power1.out',
      clearProps: 'opacity,transform',
    }
  );
};

const animateMaintenancePage = (root) => {
  if (!root) {
    return;
  }

  // Prevent duplicate execution within the same navigation tick
  if (root.dataset.gsapPageAnimated === 'true') {
    return;
  }
  root.dataset.gsapPageAnimated = 'true';
  window.setTimeout(() => {
    delete root.dataset.gsapPageAnimated;
  }, 500);

  const reduced = prefersReducedMotion();
  const topbarY = reduced ? 4 : 10;
  const cardY = reduced ? 6 : 18;
  const blockY = reduced ? 6 : 20;
  const durationScale = reduced ? 0.8 : 1;

  const topbar = root.querySelector(
    ':scope > .topbar, :scope > header.topbar'
  );

  const summaryCards = root.querySelectorAll(
    ':scope > .stats-grid > *, '
    + ':scope > [data-ajax-region="summary"] > *'
  );

  const blocks = Array.from(
    root.querySelectorAll(
      ':scope > .table-card, '
      + ':scope > [data-ajax-region="records"], '
      + ':scope > .maintenance-dashboard-grid > *, '
      + ':scope > .maintenance-two-col-grid > *, '
      + ':scope > .fuel-analytics-grid > *, '
      + ':scope > .fuel-insights-grid > *, '
      + ':scope > .fuel-monitoring-refined, '
      + ':scope > .jo-table-card'
    )
  );

  if (topbar) {
    gsap.killTweensOf(topbar);
    gsap.fromTo(
      topbar,
      {
        opacity: 0,
        y: 10,
      },
      {
        opacity: 1,
        y: 0,
        duration: 0.30 * durationScale,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      }
    );
  }

  if (summaryCards.length) {
    gsap.killTweensOf(summaryCards);
    gsap.fromTo(
      summaryCards,
      {
        opacity: 0,
        y: cardY,
      },
      {
        opacity: 1,
        y: 0,
        duration: 0.36 * durationScale,
        stagger: 0.05,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      }
    );
  }

  if (blocks.length) {
    gsap.killTweensOf(blocks);
    gsap.fromTo(
      blocks,
      {
        opacity: 0,
        y: blockY,
      },
      {
        opacity: 1,
        y: 0,
        duration: 0.40 * durationScale,
        stagger: 0.06,
        delay: summaryCards.length
          ? 0.06
          : 0,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      }
    );
  }

  window.setTimeout(
    () => animateRows(root),
    100
  );
};

const waitForMaintenanceReveal = (
  root,
  attempt = 0
) => {
  if (
    !root
    || !root.isConnected
  ) {
    return;
  }

  const loaderVisible =
    document.body.classList.contains(
      'gct-navigation-loading'
    );

  const mainHeld =
    root.classList.contains(
      'gct-main-loader-hold'
    )
    || root.classList.contains(
      'gct-main-entering'
    );

  if (
    (loaderVisible || mainHeld)
    && attempt < 35
  ) {
    window.setTimeout(
      () => waitForMaintenanceReveal(
        root,
        attempt + 1
      ),
      24
    );

    return;
  }

  // Ensure DOM is ready and visible before triggering entrance animation
  window.requestAnimationFrame(() => {
    window.setTimeout(() => {
      animateMaintenancePage(root);
    }, 48);
  });
};

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
  const surfaceY = reduced ? 6 : 16;
  const surfaceScale = reduced ? 0.99 : 0.975;
  const duration = reduced ? 0.20 : 0.30;

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
      duration: 0.16,
      ease: 'power1.out',
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
      scale: 0.975,
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
    || prefersReducedMotion()
    || !getMaintenanceRoot()
  ) {
    return;
  }

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
      y: 5,
    },
    {
      opacity: 1,
      y: 0,
      duration: 0.18,
      stagger: 0.02,
      ease: 'power2.out',
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
  if (
    modalObserver
    || !document.body
  ) {
    return;
  }

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
  const xOffset = reduced ? -1 : -3;

  gsap.fromTo(
    activeItems,
    {
      opacity: 0.82,
      x: xOffset,
    },
    {
      opacity: 1,
      x: 0,
      duration: 0.2,
      ease: 'power2.out',
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
  const subY = reduced ? -2 : -4;
  const itemX = reduced ? -1 : -3;

  gsap.fromTo(
    submenu,
    {
      opacity: 0,
      y: subY,
    },
    {
      opacity: 1,
      y: 0,
      duration: 0.16,
      ease: 'power1.out',
      clearProps: 'opacity,transform',
    }
  );

  if (items.length) {
    gsap.fromTo(
      items,
      {
        opacity: 0.78,
        x: itemX,
      },
      {
        opacity: 1,
        x: 0,
        duration: 0.18,
        stagger: 0.025,
        ease: 'power1.out',
        clearProps: 'opacity,transform',
      }
    );
  }
};

const animateSidebarShellState = (
  sidebar
) => {
  if (
    !sidebar
    || prefersReducedMotion()
  ) {
    return;
  }

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
      opacity: 0.78,
      scale: 0.96,
    },
    {
      opacity: 1,
      scale: 1,
      duration: 0.18,
      stagger: 0.01,
      ease: 'power1.out',
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

  if (sidebarObserver) {
    return;
  }

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
  if (globalInteractionsBound) {
    return;
  }

  globalInteractionsBound =
    true;

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
      const scaleTarget = reduced ? 0.992 : 0.985;

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
          x: 5,
          duration: 0.16,
          ease: 'power1.out',
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
          duration: 0.16,
          ease: 'power1.out',
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

  waitForMaintenanceReveal(
    root
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
