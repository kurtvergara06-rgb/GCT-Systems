document.addEventListener('DOMContentLoaded', () => {
  const topbarActions = document.getElementById('topbarActions');

  if (topbarActions) {
    const summaryUrl = topbarActions.dataset.summaryUrl;
    const actionItems = topbarActions.querySelectorAll('.topbar-action-item');
    const pendingButton = actionItems[1]?.querySelector('.icon-btn');
    const activityButton = actionItems[2]?.querySelector('.icon-btn');

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

    if (summaryUrl) {
      fetch(summaryUrl, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
      })
        .then((response) => response.ok ? response.json() : null)
        .then((summary) => {
          if (!summary) {
            return;
          }

          const pendingTotal = Array.isArray(summary.pending_actions)
            ? summary.pending_actions.reduce(
                (total, item) => total + (Number(item.count) || 0),
                0
              )
            : 0;

          const activityTotal = Array.isArray(summary.recent_activity)
            ? summary.recent_activity.length
            : 0;

          updateBadge(pendingBadge, pendingTotal);
          updateBadge(activityBadge, activityTotal);
        })
        .catch(() => {
          updateBadge(pendingBadge, 0);
          updateBadge(activityBadge, 0);
        });
    }
  }

  document.querySelectorAll('.profile-logout-form').forEach((form) => {
    if (form.dataset.logoutConfirmationBound === 'true') {
      return;
    }

    form.dataset.logoutConfirmationBound = 'true';

    form.addEventListener('submit', (event) => {
      const confirmed = window.confirm('Are you sure you want to log out?');

      if (!confirmed) {
        event.preventDefault();
      }
    });
  });
});
