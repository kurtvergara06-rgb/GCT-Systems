import Chart from 'chart.js/auto';

// Ensure charts are cleanly disposed when navigating between SPA partial pages
window.addEventListener('gct:navigation-before', () => {
  document.querySelectorAll('.admin-dashboard-main canvas').forEach((canvas) => {
    Chart.getChart(canvas)?.destroy();
  });
});

window.GCTPartialNavigation.registerInitializer('admin-dashboard', '.admin-dashboard-main', () => {
  const data = window.adminDashboardData || {};

  createDepartmentDistribution(data.distribution || {}, data.totalRecords);
  initVelocityCharts(data.monthLabels || [], data.monthlyActivity || [], data.dayLabels || [], data.dailyActivity || []);
  initUserTableControls();
  initAdminMlDetails();
});

/**
 * Creates the high-contrast executive Department Distribution Donut Chart.
 */
function createDepartmentDistribution(distribution, totalOverride) {
  const canvas = document.getElementById('departmentDistributionChart');
  if (!canvas) return;

  // Clean previous instance if re-initializing
  Chart.getChart(canvas)?.destroy();

  const labels = ['Maintenance', 'Warehouse', 'Purchase', 'Operations'];
  const values = [
    Number(distribution['Maintenance'] || 0),
    Number(distribution['Warehouse'] || 0),
    Number(distribution['Purchase'] || 0),
    Number(distribution['Operations'] || 0),
  ];

  const total = totalOverride || values.reduce((sum, v) => sum + v, 0);

  // Modern vibrant theme palette
  const backgroundColors = ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6'];
  const hoverColors = ['#1d4ed8', '#059669', '#d97706', '#7c3aed'];

  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: backgroundColors,
        hoverBackgroundColor: hoverColors,
        borderWidth: 0,
        hoverOffset: 6,
        spacing: 2,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '72%',
      animation: {
        animateScale: true,
        animateRotate: true,
        duration: 850,
      },
      plugins: {
        legend: {
          display: false, // Replaced by our custom responsive stat cards below the chart
        },
        tooltip: {
          backgroundColor: '#0f172a',
          titleColor: '#ffffff',
          bodyColor: '#e2e8f0',
          titleFont: { size: 12, weight: '700', family: 'Poppins, sans-serif' },
          bodyFont: { size: 11, family: 'Poppins, sans-serif' },
          padding: 12,
          cornerRadius: 10,
          boxPadding: 6,
          usePointStyle: true,
          callbacks: {
            label(context) {
              const val = Number(context.raw || 0);
              const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
              return ` ${context.label}: ${val.toLocaleString()} records (${pct}%)`;
            },
          },
        },
      },
    },
    plugins: [{
      id: 'adminDoughnutCenterLabel',
      afterDraw(chart) {
        const meta = chart.getDatasetMeta(0);
        if (!meta.data?.length) return;

        const { x, y } = meta.data[0];
        const ctx = chart.ctx;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // Draw big total number
        ctx.fillStyle = document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a';
        ctx.font = '800 24px Poppins, -apple-system, sans-serif';
        ctx.fillText(total.toLocaleString(), x, y - 6);

        // Draw subtitle
        ctx.fillStyle = document.documentElement.classList.contains('dark') ? '#94a3b8' : '#64748b';
        ctx.font = '600 10.5px Poppins, -apple-system, sans-serif';
        ctx.fillText('TOTAL RECORDS', x, y + 16);
        ctx.restore();
      },
    }],
  });
}

/**
 * Manages the dual-view System Velocity switcher (Monthly Volume vs Daily Velocity).
 */
function initVelocityCharts(monthLabels, monthlyValues, dayLabels, dailyValues) {
  let monthlyChart = null;
  let dailyChart = null;

  const monthlyCanvas = document.getElementById('monthlyActivityChart');
  const dailyCanvas = document.getElementById('systemActivityChart');

  if (monthlyCanvas) {
    Chart.getChart(monthlyCanvas)?.destroy();
    monthlyChart = buildMonthlyChart(monthlyCanvas, monthLabels, monthlyValues);
  }

  if (dailyCanvas) {
    Chart.getChart(dailyCanvas)?.destroy();
    dailyChart = buildDailyChart(dailyCanvas, dayLabels, dailyValues);
  }

  // Handle Tab Switcher Buttons
  const tabButtons = document.querySelectorAll('.chart-tab-btn');
  const panelMonthly = document.getElementById('panel-monthly');
  const panelDaily = document.getElementById('panel-daily');

  tabButtons.forEach((btn) => {
    btn.addEventListener('click', () => {
      const view = btn.dataset.view;

      tabButtons.forEach((b) => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });

      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      if (view === 'monthly') {
        if (panelMonthly) panelMonthly.style.display = 'block';
        if (panelDaily) panelDaily.style.display = 'none';
        monthlyChart?.resize();
      } else {
        if (panelMonthly) panelMonthly.style.display = 'none';
        if (panelDaily) panelDaily.style.display = 'block';
        dailyChart?.resize();
      }
    });
  });
}

/**
 * Builds the 6-Month Volume Bar Chart.
 */
function buildMonthlyChart(canvas, labels, values) {
  const isDark = document.documentElement.classList.contains('dark');

  const bgColors = values.map((_, index) => {
    return index === values.length - 1 ? '#0057f8' : '#60a5fa';
  });

  const hoverColors = values.map((_, index) => {
    return index === values.length - 1 ? '#003db3' : '#3b82f6';
  });

  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Monthly Record Volume',
        data: values,
        backgroundColor: bgColors,
        hoverBackgroundColor: hoverColors,
        borderRadius: 8,
        borderSkipped: false,
        maxBarThickness: 36,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      animation: { duration: 750 },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0f172a',
          titleColor: '#ffffff',
          bodyColor: '#e2e8f0',
          titleFont: { size: 12, weight: '700', family: 'Poppins, sans-serif' },
          bodyFont: { size: 11, family: 'Poppins, sans-serif' },
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label(context) {
              return ` Records Created: ${Number(context.raw || 0).toLocaleString()} entries`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: isDark ? '#94a3b8' : '#64748b',
            font: { size: 10, weight: '600', family: 'Poppins, sans-serif' },
          },
          border: { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: isDark ? '#94a3b8' : '#64748b',
            font: { size: 10, family: 'Poppins, sans-serif' },
            precision: 0,
          },
          grid: {
            color: isDark ? 'rgba(255, 255, 255, 0.06)' : '#eef2f7',
          },
          border: { display: false },
        },
      },
    },
  });
}

/**
 * Builds the 7-Day Velocity Area-Gradient Spline Chart.
 */
function buildDailyChart(canvas, labels, values) {
  const isDark = document.documentElement.classList.contains('dark');
  const ctx = canvas.getContext('2d');

  const gradient = ctx.createLinearGradient(0, 0, 0, 240);
  gradient.addColorStop(0, 'rgba(37, 99, 235, 0.35)');
  gradient.addColorStop(0.7, 'rgba(37, 99, 235, 0.08)');
  gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

  return new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'System Activity Velocity',
        data: values,
        borderColor: '#2563eb',
        backgroundColor: gradient,
        fill: true,
        tension: 0.38,
        borderWidth: 2.5,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBackgroundColor: '#2563eb',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      animation: { duration: 750 },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#0f172a',
          titleColor: '#ffffff',
          bodyColor: '#e2e8f0',
          titleFont: { size: 12, weight: '700', family: 'Poppins, sans-serif' },
          bodyFont: { size: 11, family: 'Poppins, sans-serif' },
          padding: 12,
          cornerRadius: 8,
          callbacks: {
            label(context) {
              return ` Daily Velocity: ${Number(context.raw || 0).toLocaleString()} actions`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: isDark ? '#94a3b8' : '#64748b',
            font: { size: 10, weight: '600', family: 'Poppins, sans-serif' },
          },
          border: { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: isDark ? '#94a3b8' : '#64748b',
            font: { size: 10, family: 'Poppins, sans-serif' },
            precision: 0,
          },
          grid: {
            color: isDark ? 'rgba(255, 255, 255, 0.06)' : '#eef2f7',
          },
          border: { display: false },
        },
      },
    },
  });
}

/**
 * Initializes client-side instant search and department filtering on the User Management table.
 */
function initUserTableControls() {
  const searchInput = document.getElementById('adminUserTableSearch');
  const clearBtn = document.getElementById('clearUserSearchBtn');
  const filterBtns = document.querySelectorAll('.dept-filter-btn');
  const table = document.getElementById('adminUsersTable');
  const countBadge = document.getElementById('userDirectoryCount');
  const noMatchesRow = document.getElementById('noUserMatchesRow');

  if (!table) return;

  const rows = Array.from(table.querySelectorAll('tbody tr:not(#noUserMatchesRow)'));
  let currentFilter = 'all';
  let searchQuery = '';

  function applyFilters() {
    let visibleCount = 0;

    rows.forEach((row) => {
      const rowDept = row.dataset.dept || '';
      const rowSearch = row.dataset.search || '';

      const matchesDept = currentFilter === 'all' || rowDept.includes(currentFilter);
      const matchesSearch = !searchQuery || rowSearch.includes(searchQuery);

      if (matchesDept && matchesSearch) {
        row.style.display = '';
        visibleCount++;
      } else {
        row.style.display = 'none';
      }
    });

    if (noMatchesRow) {
      noMatchesRow.style.display = visibleCount === 0 ? '' : 'none';
    }

    if (countBadge) {
      countBadge.innerHTML = `<strong>${visibleCount}</strong> visible`;
    }

    if (clearBtn) {
      clearBtn.hidden = !searchQuery;
    }
  }

  // Live Instant Search
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value.trim().toLowerCase();
      applyFilters();
    });
  }

  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      if (searchInput) {
        searchInput.value = '';
        searchQuery = '';
        searchInput.focus();
        applyFilters();
      }
    });
  }

  // Department Filter Chips
  filterBtns.forEach((btn) => {
    btn.addEventListener('click', () => {
      filterBtns.forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      currentFilter = btn.dataset.dept || 'all';
      applyFilters();
    });
  });
}


/**
 * Keep the ML details dialog text-only and scoped to the current dashboard.
 * Data comes from the server-normalized model health snapshot; never render HTML.
 */
function initAdminMlDetails() {
  const root = document.querySelector('.admin-dashboard-main');
  const dialog = root?.querySelector('#adminMlDetailsDialog');
  if (!root || !dialog || dialog.dataset.mlInitialized === '1') return;

  dialog.dataset.mlInitialized = '1';
  let lastTrigger = null;

  root.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-ml-details]');
    if (trigger && root.contains(trigger)) {
      lastTrigger = trigger;
      dialog.querySelector('#adminMlDialogTitle').textContent = trigger.dataset.mlTitle || 'Model Details';
      for (const key of ['status', 'source', 'dataset', 'samples', 'version', 'components', 'reason']) {
        const field = dialog.querySelector('[data-ml-field="' + key + '"]');
        if (field) field.textContent = trigger.dataset['ml' + key.charAt(0).toUpperCase() + key.slice(1)] || 'Not reported';
      }
      if (!dialog.open) dialog.showModal();
      dialog.querySelector('[data-ml-close]')?.focus();
      return;
    }
    if (event.target.closest('[data-ml-close]') && dialog.open) {
      dialog.close();
      lastTrigger?.focus();
    }
  });

  // No backdrop click dismissal; Escape remains the native accessible close.
  dialog.addEventListener('close', () => lastTrigger?.focus());
}
