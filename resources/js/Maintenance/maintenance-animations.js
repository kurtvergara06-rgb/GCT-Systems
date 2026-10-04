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
  preparedRevealRoot = null;
  preparedRevealPromise = null;
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

  gsap.set(
    root,
    {
      opacity: 0,
    }
  );

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

const revealMaintenancePage = () => {
  const root = getMaintenanceRoot();

  if (!root) {
    return false;
  }

  gsap.killTweensOf(root);

  root.dataset.gctRevealState =
    'revealing';

  gsap.fromTo(
    root,
    {
      opacity: 0,
    },
    {
      opacity: 1,
      duration:
        prefersReducedMotion()
          ? 0.16
          : 0.28,
      ease: 'power1.out',
      clearProps: 'opacity',
      onComplete: () => {
        root.dataset.gctRevealState =
          'shown';
      },
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
          revealMaintenancePage();
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
