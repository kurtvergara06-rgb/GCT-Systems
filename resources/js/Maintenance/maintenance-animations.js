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

};

const initializeMaintenanceAnimations = () => {
  const root =
    getMaintenanceRoot();

  if (!root) {
    return;
  }

  bindGlobalInteractions();
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
