document.addEventListener('DOMContentLoaded', () => {
  const normalizeText = (value) => String(value || '').trim().toLowerCase();

  document.querySelectorAll('.jo-page, .maintenance-dashboard-page').forEach((scope) => {
    scope.querySelectorAll('.badge, .stat-card p, .stat-card-link p').forEach((element) => {
      if (normalizeText(element.textContent) === 'on going') {
        element.textContent = 'In Progress';
      }
    });
  });

  document.querySelectorAll('.jo-page tbody tr, .purchase-request-page tbody tr').forEach((row) => {
    const text = normalizeText(row.textContent);

    if (text.includes('rejected')) {
      row.dataset.uiStatus = 'rejected';
    } else if (text.includes('completed') || text.includes('issued')) {
      row.dataset.uiStatus = 'completed';
    } else if (text.includes('on going') || text.includes('in progress')) {
      row.dataset.uiStatus = 'progress';
    } else if (text.includes('on hold') || text.includes('submitted')) {
      row.dataset.uiStatus = 'hold';
    }

    row.querySelectorAll('.badge').forEach((badge) => {
      if (normalizeText(badge.textContent) === 'on going') {
        badge.textContent = 'In Progress';
      }

      if (normalizeText(badge.textContent) === 'submitted') {
        badge.textContent = 'Pending Review';
      }

      if (normalizeText(badge.textContent) === 'for purchase') {
        badge.textContent = 'Ready for Purchase';
      }

      if (normalizeText(badge.textContent) === 'rejected') {
        badge.textContent = 'Needs Revision';
      }
    });

    const badges = Array.from(row.querySelectorAll('.badge'));
    const partStatus = badges.find((badge) => {
      const value = normalizeText(badge.textContent);
      return [
        'needs revision',
        'requested',
        'approved',
        'ready for purchase',
        'issued',
      ].includes(value);
    });

    row.querySelectorAll('*').forEach((element) => {
      if (normalizeText(element.textContent) !== 'locked') {
        return;
      }

      const partValue = normalizeText(partStatus?.textContent);
      element.textContent = partValue && partValue !== 'issued'
        ? 'Waiting for parts'
        : 'Pending completion';
      element.setAttribute('title', element.textContent);
    });
  });
});
