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
