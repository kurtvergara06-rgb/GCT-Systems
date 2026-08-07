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

  const addFuelChartEmptyStates = () => {
    document.querySelectorAll('.fuel-chart-card').forEach((card) => {
      const container = card.querySelector('.fuel-chart-container');
      const canvas = container?.querySelector('canvas');

      if (!container || !canvas || card.classList.contains('has-empty-chart')) {
        return;
      }

      let hasData = false;

      if (window.Chart && typeof window.Chart.getChart === 'function') {
        const chart = window.Chart.getChart(canvas);
        hasData = Boolean(chart?.data?.datasets?.some((dataset) =>
          Array.isArray(dataset.data)
          && dataset.data.some((value) => Number(value) !== 0)
        ));
      }

      if (hasData) {
        return;
      }

      const title = card.querySelector('h2')?.textContent?.trim() || 'Chart';
      const state = document.createElement('div');
      state.className = 'fuel-chart-empty-state';
      state.innerHTML = `
        <i class="fa-solid fa-chart-column"></i>
        <strong>No ${title.toLowerCase()} data yet</strong>
        <p>Add a fuel record to generate this chart for the selected reporting period.</p>
      `;

      container.style.position = 'relative';
      container.appendChild(state);
      card.classList.add('has-empty-chart');
    });
  };

  window.setTimeout(addFuelChartEmptyStates, 900);
});
