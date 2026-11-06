window.GCTPartialNavigation.registerInitializer('shared-shell-enhancements', 'main', () => {
  const topbarActions = document.getElementById('topbarActions');

  if (topbarActions) {
    const pendingButton = topbarActions.querySelector('[data-dropdown-target="pendingActionsDropdown"]');
    const activityButton = topbarActions.querySelector('[data-dropdown-target="recentActivityDropdown"]');

    const ensureBadge = (button, id) => {
      if (!button) {
        return null;
      }

      let badge = button.querySelector(`#${id}`);

      if (!badge) {
        badge = document.createElement('span');
        badge.id = id;
        badge.className = 'dynamic-action-badge';
        badge.hidden = true;
        button.appendChild(badge);
      }

      return badge;
    };

    const pendingBadge = ensureBadge(pendingButton, 'pendingActionsBadge');
    const activityBadge = ensureBadge(activityButton, 'recentActivityBadge');

    const updateBadge = (badge, count) => {
      if (!badge) {
        return;
      }

      const normalizedCount = Math.max(0, Number(count) || 0);
      badge.textContent = normalizedCount > 99 ? '99+' : String(normalizedCount);
      badge.hidden = normalizedCount === 0;
    };

    const applySummaryBadges = (summary) => {
      const pendingTotal = Array.isArray(summary?.pending_actions)
        ? summary.pending_actions.reduce(
            (total, item) => total + (Number(item.count) || 0),
            0
          )
        : 0;

      const activityTotal = Array.isArray(summary?.recent_activity)
        ? summary.recent_activity.length
        : 0;

      updateBadge(pendingBadge, pendingTotal);
      updateBadge(activityBadge, activityTotal);
    };

    // topbar.js is the single caller of /topbar/summary; reuse its result here
    // instead of issuing a second duplicate request on every page load.
    window.addEventListener('topbar-summary-loaded', (event) => {
      if (event.detail) {
        applySummaryBadges(event.detail);
      } else {
        updateBadge(pendingBadge, 0);
        updateBadge(activityBadge, 0);
      }
    });
  }

  document.querySelectorAll('.profile-logout-form').forEach((form) => {
    if (form.dataset.logoutConfirmationBound === 'true') {
      return;
    }

    form.dataset.logoutConfirmationBound = 'true';

    // If already handled by data-confirm-form, confirmation-modal.js handles the modal
    if (form.matches('[data-confirm-form]')) {
      return;
    }

    form.addEventListener('submit', (event) => {
      if (form.dataset.confirmed === 'true') {
        form.dataset.confirmed = 'false';
        return;
      }

      event.preventDefault();

      if (typeof window.openSystemConfirmation === 'function') {
        window.openSystemConfirmation({
          title: 'Log Out',
          message: 'Are you sure you want to log out?',
          button: 'Log Out',
          type: 'logout',
          loadingText: 'Logging out...',
        }, () => {
          form.dataset.confirmed = 'true';
          if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
          } else {
            form.submit();
          }
        });
      } else {
        form.dataset.confirmed = 'true';
        form.submit();
      }
    });
  });
});


/*
 * Shared record-table drag scrolling and overflow state.
 * Only real table scrollports in main content participate; never modals,
 * form controls, rich editors, or specialized analytics/pivot surfaces.
 * Bind once per wrapper, then rescan after partial navigation/AJAX updates.
 */
(() => {
  const wrapperSelector = [
    'main .gct-responsive-records',
    'main .table-wrap',
    'main .table-responsive',
    'main .table-wrapper',
    'main .table-container',
    'main .service-table-scroll',
    'main .assignment-table-wrap',
    'main .auto-table-wrap',
  ].join(', ');
  const excluded = [
    '.ui-form-overlay', '.modal-overlay', '[role="dialog"]',
    '.gct-conflict-table-scroll', '.analytics-table-wrap',
    '.pivot-table-wrap', '[data-no-drag-scroll]',
  ].join(', ');
  const interactive = [
    'button', 'a', 'input', 'select', 'textarea', 'label',
    'summary', '[role="button"]', '[contenteditable]',
    '[draggable="true"]',
  ].join(', ');
  const bound = new WeakSet();
  const observers = new Set();

  const setup = (wrap) => {
    if (bound.has(wrap) || wrap.closest(excluded)) return;
    const table = wrap.querySelector(':scope > table');
    if (!table) return;
    bound.add(wrap);

    const update = () => {
      if (!wrap.isConnected) return;
      const overflows = wrap.scrollWidth > wrap.clientWidth + 2;
      wrap.classList.toggle('has-horizontal-overflow', overflows);
      wrap.classList.toggle('gct-drag-scroll-ready', overflows);
    };
    if (typeof ResizeObserver !== 'undefined') {
      const observer = new ResizeObserver(update);
      observer.observe(wrap);
      observer.observe(table);
      observers.add(observer);
    }
    update();

    let pointerId = null;
    let x = 0;
    let y = 0;
    let left = 0;
    let dragged = false;
    let blockClick = false;

    wrap.addEventListener('pointerdown', (event) => {
      if (event.button !== 0 || event.pointerType !== 'mouse'
          || event.target.closest(interactive)
          || wrap.scrollWidth <= wrap.clientWidth + 2) return;
      event.preventDefault(); // Prevent native selection before dragging starts.
      pointerId = event.pointerId;
      dragged = false;
      x = event.clientX;
      y = event.clientY;
      left = wrap.scrollLeft;
      wrap.setPointerCapture(pointerId);
    });

    wrap.addEventListener('pointermove', (event) => {
      if (pointerId !== event.pointerId) return;
      const dx = event.clientX - x;
      const dy = event.clientY - y;
      if (!dragged) {
        if (Math.abs(dx) < 6 || Math.abs(dx) <= Math.abs(dy)) return;
        dragged = true;
        wrap.classList.add('is-drag-scrolling');
      }
      wrap.scrollLeft = left - dx;
      event.preventDefault();
    });

    const endDrag = (event) => {
      if (pointerId !== event.pointerId) return;
      pointerId = null;
      if (dragged) blockClick = true;
      dragged = false;
      wrap.classList.remove('is-drag-scrolling');
      if (wrap.hasPointerCapture(event.pointerId)) wrap.releasePointerCapture(event.pointerId);
    };
    wrap.addEventListener('pointerup', endDrag);
    wrap.addEventListener('pointercancel', endDrag);
    wrap.addEventListener('click', (event) => {
      if (!blockClick) return;
      blockClick = false;
      event.preventDefault();
      event.stopPropagation();
    }, true);
  };

  const scan = () => {
    document.querySelectorAll(wrapperSelector).forEach(setup);
  };
  // This file is loaded once; shared event handlers survive partial navigation.
  document.addEventListener('DOMContentLoaded', scan);
  document.addEventListener('ajax:content-updated', scan);
  document.addEventListener('system-regions-refreshed', scan);
  document.addEventListener('gct:navigation-ready', scan);
  window.addEventListener('resize', scan);
  window.addEventListener('gct:navigation-before', () => {
    observers.forEach((observer) => observer.disconnect());
    observers.clear();
  });
  if (document.readyState !== 'loading') scan();
})();
