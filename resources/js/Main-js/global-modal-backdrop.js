/**
 * Global Modal Backdrop & Stacking Manager
 *
 * Provides system-wide standardization for modal backdrop states,
 * background scroll locking, and stacked/nested modal depth handling.
 */

const OVERLAY_SELECTORS = [
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
  '[data-gct-modal-overlay]'
];

const OVERLAY_QUERY = OVERLAY_SELECTORS.join(', ');

/**
 * Checks whether a given modal overlay element is currently open and visible to the user.
 *
 * @param {HTMLElement} el
 * @returns {boolean}
 */
function isOverlayVisible(el) {
  if (!el || !el.isConnected) return false;

  // Explicit inline hide
  if (el.style.display === 'none' || el.style.visibility === 'hidden') {
    return false;
  }

  // Aria hidden check (unless overridden by active classes)
  const isAriaHidden = el.getAttribute('aria-hidden') === 'true';
  const hasActiveClass = (
    el.classList.contains('show') ||
    el.classList.contains('active') ||
    el.classList.contains('is-open')
  );

  if (isAriaHidden && !hasActiveClass) {
    return false;
  }

  // Computed style check
  const style = window.getComputedStyle(el);
  if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
    return false;
  }

  // Layout check
  return el.offsetWidth > 0 || el.offsetHeight > 0 || el.getClientRects().length > 0;
}

/**
 * Returns all currently visible modal overlays ordered by DOM position.
 *
 * @returns {HTMLElement[]}
 */
function getVisibleOverlays() {
  const allOverlays = Array.from(document.querySelectorAll(OVERLAY_QUERY));
  return allOverlays.filter(isOverlayVisible);
}

let syncQueued = false;
let isSyncing = false;

/**
 * Synchronizes body scroll lock and stacked modal states based on visible overlays.
 */
function syncState() {
  syncQueued = false;
  if (isSyncing) return;
  isSyncing = true;

  try {
    const visibleOverlays = getVisibleOverlays();
    const count = visibleOverlays.length;

    if (count > 0) {
      if (!document.body.classList.contains('modal-open')) {
        document.body.classList.add('modal-open');
      }
      document.body.style.overflow = 'hidden';

      if (count > 1) {
        document.body.classList.add('has-stacked-modal');

        // Mark topmost overlay for stacked styling to avoid double-darkening
        visibleOverlays.forEach((overlay, idx) => {
          const isTopmost = idx === count - 1;
          if (isTopmost || overlay.classList.contains('global-confirmation-overlay')) {
            overlay.classList.add('is-stacked-modal');
          } else {
            overlay.classList.remove('is-stacked-modal');
          }
        });
      } else {
        document.body.classList.remove('has-stacked-modal');
        visibleOverlays[0].classList.remove('is-stacked-modal');
      }
    } else {
      document.body.classList.remove('modal-open', 'has-stacked-modal');
      document.body.style.overflow = '';

      document.querySelectorAll('.is-stacked-modal').forEach((el) => {
        el.classList.remove('is-stacked-modal');
      });
    }
  } finally {
    isSyncing = false;
  }
}

/**
 * Schedules a sync on the next animation frame.
 */
function queueSync() {
  if (syncQueued) return;
  syncQueued = true;
  requestAnimationFrame(syncState);
}

// Observe DOM mutations for modal additions, removals, or class/style changes
const observer = new MutationObserver((mutations) => {
  for (const mutation of mutations) {
    if (mutation.type === 'childList') {
      queueSync();
      return;
    }

    if (mutation.type === 'attributes') {
      const target = mutation.target;
      if (
        target === document.body ||
        (target.matches && target.matches(OVERLAY_QUERY)) ||
        target.querySelector?.(OVERLAY_QUERY)
      ) {
        queueSync();
        return;
      }
    }
  }
});

// Initialize on DOMContentLoaded and immediately
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['class', 'style', 'aria-hidden']
    });
    syncState();
  });
} else {
  observer.observe(document.body, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['class', 'style', 'aria-hidden']
  });
  syncState();
}

window.addEventListener('load', syncState);

// Public API
window.GCTModalBackdrop = {
  sync: syncState,
  getVisibleOverlays,
  isOverlayVisible,
  lock: () => {
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
  },
  unlock: () => {
    syncState();
  }
};

export default window.GCTModalBackdrop;
