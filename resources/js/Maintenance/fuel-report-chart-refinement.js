import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
  window.setTimeout(() => {
    const page = document.querySelector('.fuel-page');
    const dataElement = document.getElementById('fuelAnalyticsData');

    if (!page || !dataElement) {
      return;
    }

    let analytics;

    try {
      analytics = JSON.parse(dataElement.textContent.trim());
    } catch (error) {
      console.error('Unable to read Fuel Reports chart data.', error);
      return;
    }

    const labels = Array.isArray(analytics.labels) ? analytics.labels : [];
    const efficiency = Array.isArray(analytics.efficiency)
      ? analytics.efficiency.map((value) => Number(value) || 0)
      : [];
    const distance = Array.isArray(analytics.distance)
      ? analytics.distance.map((value) => Number(value) || 0)
      : [];
    const fuel = Array.isArray(analytics.fuel)
      ? analytics.fuel.map((value) => Number(value) || 0)
      : [];
    const fleetAverage = Number(analytics.fleetAverage) || 0;

    const removeEmptyState = (canvas) => {
      const card = canvas?.closest('.fuel-chart-card');
      card?.querySelector('.fuel-chart-empty-state')?.remove();
      card?.classList.remove('has-empty-chart');
    };

    const showEmptyState = (canvas, message) => {
      const card = canvas?.closest('.fuel-chart-card');
      const container = canvas?.closest('.fuel-chart-container');

      if (!card || !container || container.querySelector('.fuel-chart-empty-state')) {
        return;
      }

      const state = document.createElement('div');
      state.className = 'fuel-chart-empty-state';
      state.innerHTML = `
        <i class="fa-solid fa-chart-line"></i>
        <strong>No chart data yet</strong>
        <p>${message}</p>
      `;
      container.appendChild(state);
      card.classList.add('has-empty-chart');
    };

    const commonOptions = {
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
            color: '#475569',
            font: {
              family: 'Poppins',
              size: 11,
              weight: '600',
            },
          },
        },
      },
    };

    const efficiencyCanvas = document.getElementById('fuelEfficiencyChart');

    if (efficiencyCanvas) {
      Chart.getChart(efficiencyCanvas)?.destroy();
      removeEmptyState(efficiencyCanvas);

      if (labels.length && efficiency.some((value) => value > 0)) {
        new Chart(efficiencyCanvas, {
          type: 'line',
          data: {
            labels,
            datasets: [
              {
                label: 'Vehicle Efficiency',
                data: efficiency,
                borderColor: '#0b40b5',
                backgroundColor: 'rgba(11, 64, 181, 0.10)',
                borderWidth: 2.25,
                pointRadius: labels.length > 35 ? 0 : 3,
                pointHoverRadius: 5,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0b40b5',
                pointBorderWidth: 2,
                tension: 0.28,
                fill: true,
              },
              {
                label: 'Fleet Average',
                data: labels.map(() => fleetAverage),
                borderColor: '#e2a900',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [7, 5],
                pointRadius: 0,
                tension: 0,
              },
            ],
          },
          options: {
            ...commonOptions,
            plugins: {
              ...commonOptions.plugins,
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
                  autoSkip: true,
                  maxTicksLimit: 14,
                  maxRotation: 0,
                  color: '#64748b',
                  font: { family: 'Poppins', size: 10 },
                },
              },
              y: {
                beginAtZero: true,
                suggestedMax: Math.max(8, ...efficiency) + 1,
                title: {
                  display: true,
                  text: 'KM/L',
                  color: '#64748b',
                  font: { family: 'Poppins', size: 11, weight: '600' },
                },
                ticks: {
                  color: '#64748b',
                  font: { family: 'Poppins', size: 10 },
                },
                grid: { color: 'rgba(148, 163, 184, 0.18)' },
              },
            },
          },
        });
      } else {
        showEmptyState(
          efficiencyCanvas,
          'Add fuel records for the selected reporting period to generate the efficiency trend.'
        );
      }
    }

    const usageCanvas = document.getElementById('fuelUsageChart');

    if (usageCanvas) {
      Chart.getChart(usageCanvas)?.destroy();
      removeEmptyState(usageCanvas);

      if (labels.length && (distance.some((value) => value > 0) || fuel.some((value) => value > 0))) {
        new Chart(usageCanvas, {
          type: 'line',
          data: {
            labels,
            datasets: [
              {
                label: 'Distance',
                data: distance,
                borderColor: '#0b40b5',
                backgroundColor: 'rgba(11, 64, 181, 0.08)',
                borderWidth: 2.25,
                pointRadius: labels.length > 35 ? 0 : 3,
                pointHoverRadius: 5,
                tension: 0.28,
                fill: false,
                yAxisID: 'distanceAxis',
              },
              {
                label: 'Fuel Used',
                data: fuel,
                borderColor: '#e2a900',
                backgroundColor: 'rgba(255, 196, 0, 0.10)',
                borderWidth: 2.25,
                pointRadius: labels.length > 35 ? 0 : 3,
                pointHoverRadius: 5,
                tension: 0.28,
                fill: false,
                yAxisID: 'fuelAxis',
              },
            ],
          },
          options: {
            ...commonOptions,
            plugins: {
              ...commonOptions.plugins,
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
                  autoSkip: true,
                  maxTicksLimit: 14,
                  maxRotation: 0,
                  color: '#64748b',
                  font: { family: 'Poppins', size: 10 },
                },
              },
              distanceAxis: {
                type: 'linear',
                position: 'left',
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Distance (km)',
                  color: '#64748b',
                  font: { family: 'Poppins', size: 11, weight: '600' },
                },
                ticks: { color: '#64748b', font: { family: 'Poppins', size: 10 } },
                grid: { color: 'rgba(148, 163, 184, 0.18)' },
              },
              fuelAxis: {
                type: 'linear',
                position: 'right',
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Fuel (L)',
                  color: '#64748b',
                  font: { family: 'Poppins', size: 11, weight: '600' },
                },
                ticks: { color: '#64748b', font: { family: 'Poppins', size: 10 } },
                grid: { drawOnChartArea: false },
              },
            },
          },
        });
      } else {
        showEmptyState(
          usageCanvas,
          'Add fuel records for the selected reporting period to compare distance and fuel usage.'
        );
      }
    }
  }, 150);
});
