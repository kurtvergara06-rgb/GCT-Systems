import Chart from 'chart.js/auto';

const parseFuelAnalytics = () => {
  const source = document.getElementById('fuelAnalyticsData');

  if (!source) {
    return null;
  }

  try {
    return JSON.parse(source.textContent.trim());
  } catch (error) {
    console.error('Unable to parse fuel analytics data.', error);
    return null;
  }
};

const prepareScrollableCanvas = (canvas, itemCount) => {
  const container = canvas?.closest('.fuel-chart-container');

  if (!container) {
    return;
  }

  let inner = container.querySelector('.fuel-chart-scroll-inner');

  if (!inner) {
    inner = document.createElement('div');
    inner.className = 'fuel-chart-scroll-inner';
    canvas.parentNode.insertBefore(inner, canvas);
    inner.appendChild(canvas);
  }

  const minimumWidth = Math.max(container.clientWidth || 640, itemCount * 52);
  inner.style.width = `${minimumWidth}px`;
  inner.style.height = '100%';
};

const sharedOptions = {
  responsive: true,
  maintainAspectRatio: false,
  normalized: true,
  animation: {
    duration: 450,
  },
  interaction: {
    mode: 'index',
    intersect: false,
  },
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        boxWidth: 8,
        padding: 18,
      },
    },
  },
};

const renderEfficiencyChart = data => {
  const canvas = document.getElementById('fuelEfficiencyChart');
  const labels = Array.isArray(data.labels) ? data.labels : [];
  const efficiency = Array.isArray(data.efficiency) ? data.efficiency.map(Number) : [];
  const fleetAverage = Number(data.fleetAverage || 0);

  if (!canvas || labels.length === 0) {
    return;
  }

  prepareScrollableCanvas(canvas, labels.length);
  Chart.getChart(canvas)?.destroy();

  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Vehicle Efficiency',
          data: efficiency,
          borderColor: '#0b40b5',
          backgroundColor: 'rgba(11, 64, 181, 0.12)',
          borderWidth: 2.5,
          pointRadius: labels.length > 40 ? 0 : 3,
          pointHoverRadius: 5,
          pointHitRadius: 12,
          fill: true,
          tension: 0.28,
        },
        {
          label: 'Fleet Average',
          data: labels.map(() => fleetAverage),
          borderColor: '#d89b00',
          backgroundColor: 'transparent',
          borderWidth: 2,
          borderDash: [7, 5],
          pointRadius: 0,
          pointHoverRadius: 0,
          tension: 0,
        },
      ],
    },
    options: {
      ...sharedOptions,
      plugins: {
        ...sharedOptions.plugins,
        tooltip: {
          callbacks: {
            label(context) {
              return `${context.dataset.label}: ${Number(context.raw || 0).toFixed(2)} km/L`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            autoSkip: labels.length > 24,
            maxTicksLimit: 24,
            maxRotation: 0,
          },
        },
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: 'Fuel efficiency (km/L)',
          },
          grid: {
            color: 'rgba(148, 163, 184, 0.18)',
          },
        },
      },
    },
  });
};

const renderUsageChart = data => {
  const canvas = document.getElementById('fuelUsageChart');
  const labels = Array.isArray(data.labels) ? data.labels : [];
  const distance = Array.isArray(data.distance) ? data.distance.map(Number) : [];
  const fuel = Array.isArray(data.fuel) ? data.fuel.map(Number) : [];

  if (!canvas || labels.length === 0) {
    return;
  }

  prepareScrollableCanvas(canvas, labels.length);
  Chart.getChart(canvas)?.destroy();

  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Distance',
          data: distance,
          borderColor: '#0b40b5',
          backgroundColor: 'rgba(11, 64, 181, 0.08)',
          borderWidth: 2.5,
          pointRadius: labels.length > 40 ? 0 : 3,
          pointHoverRadius: 5,
          pointHitRadius: 12,
          tension: 0.28,
          yAxisID: 'distanceAxis',
        },
        {
          label: 'Fuel Used',
          data: fuel,
          borderColor: '#d89b00',
          backgroundColor: 'rgba(255, 196, 0, 0.08)',
          borderWidth: 2.5,
          pointRadius: labels.length > 40 ? 0 : 3,
          pointHoverRadius: 5,
          pointHitRadius: 12,
          tension: 0.28,
          yAxisID: 'fuelAxis',
        },
      ],
    },
    options: {
      ...sharedOptions,
      plugins: {
        ...sharedOptions.plugins,
        tooltip: {
          callbacks: {
            label(context) {
              const value = Number(context.raw || 0).toFixed(2);
              return context.dataset.label === 'Distance'
                ? `Distance: ${value} km`
                : `Fuel Used: ${value} L`;
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            autoSkip: labels.length > 24,
            maxTicksLimit: 24,
            maxRotation: 0,
          },
        },
        distanceAxis: {
          type: 'linear',
          position: 'left',
          beginAtZero: true,
          title: {
            display: true,
            text: 'Distance (km)',
          },
          grid: {
            color: 'rgba(148, 163, 184, 0.18)',
          },
        },
        fuelAxis: {
          type: 'linear',
          position: 'right',
          beginAtZero: true,
          title: {
            display: true,
            text: 'Fuel used (L)',
          },
          grid: {
            drawOnChartArea: false,
          },
        },
      },
    },
  });
};

const enhanceFuelModal = () => {
  const modal = document.querySelector('#fuelModal .fuel-modal');
  const header = modal?.querySelector('.fuel-modal-header');

  if (!modal || !header || header.dataset.enhanced === 'true') {
    return;
  }

  header.dataset.enhanced = 'true';
  modal.classList.add('fuel-modal-job-order-style');

  const heading = header.querySelector(':scope > div');

  if (heading) {
    const icon = document.createElement('div');
    icon.className = 'fuel-modal-title-icon';
    icon.innerHTML = '<i class="fa-solid fa-gas-pump"></i>';
    header.insertBefore(icon, heading);
  }
};

window.addEventListener('load', () => {
  if (!document.querySelector('.fuel-page')) {
    return;
  }

  enhanceFuelModal();

  window.requestAnimationFrame(() => {
    const data = parseFuelAnalytics();

    if (!data) {
      return;
    }

    renderEfficiencyChart(data);
    renderUsageChart(data);
  });
});
