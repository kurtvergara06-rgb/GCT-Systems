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
 * Modal interaction policy:
 * clicking the backdrop itself must never dismiss a modal.
 * Users must use an explicit X / Cancel / action button so an
 * accidental outside click cannot discard form state.
 */
function preventBackdropDismissal(event) {
  const target = event.target;

  if (!(target instanceof Element)) {
    return;
  }

  if (!target.matches(OVERLAY_QUERY)) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
}

document.addEventListener(
  'click',
  preventBackdropDismissal,
  true
);

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
let scrollLockActive = false;

const supportsStableScrollbarGutter = (
  typeof CSS !== 'undefined'
  && typeof CSS.supports === 'function'
  && CSS.supports('scrollbar-gutter: stable')
);

function lockBodyScroll() {
  const body = document.body;
  if (!body) return;

  if (!scrollLockActive) {
    const computedPaddingRight = window.getComputedStyle(body).paddingRight || '0px';
    const scrollbarWidth = supportsStableScrollbarGutter
      ? 0
      : Math.max(0, window.innerWidth - document.documentElement.clientWidth);

    body.style.setProperty('--gct-modal-base-padding-right', computedPaddingRight);
    body.style.setProperty('--gct-modal-scrollbar-compensation', `${scrollbarWidth}px`);
    scrollLockActive = true;
  }

  body.classList.add('modal-open');
  body.style.overflow = 'hidden';
}

function unlockBodyScroll() {
  const body = document.body;
  if (!body) return;

  scrollLockActive = false;
  body.classList.remove('modal-open');
  body.style.overflow = '';
  body.style.removeProperty('--gct-modal-base-padding-right');
  body.style.removeProperty('--gct-modal-scrollbar-compensation');
}

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
      lockBodyScroll();

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
      unlockBodyScroll();
      document.body.classList.remove('has-stacked-modal');

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
window.addEventListener('resize', queueSync);

// Public API
window.GCTModalBackdrop = {
  sync: syncState,
  getVisibleOverlays,
  isOverlayVisible,
  lock: lockBodyScroll,
  unlock: () => {
    syncState();
  }
};

export default window.GCTModalBackdrop;
