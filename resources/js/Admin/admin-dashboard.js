import Chart from 'chart.js/auto';

document.addEventListener('DOMContentLoaded', () => {
  const data = window.adminDashboardData || {};

  createDepartmentDistribution(data.distribution || {});
  createMonthlyActivity(data.monthLabels || [], data.monthlyActivity || []);
  createSystemActivity(data.dayLabels || [], data.dailyActivity || []);
});

function createDepartmentDistribution(distribution) {
  const canvas = document.getElementById('departmentDistributionChart');
  if (!canvas) return;

  const labels = Object.keys(distribution);
  const values = Object.values(distribution).map((value) => Number(value || 0));

  new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#7c3aed'],
        borderWidth: 0,
        hoverOffset: 5,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          position: 'bottom',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            boxWidth: 8,
            boxHeight: 8,
            padding: 14,
            color: '#475569',
            font: { size: 10, weight: '600' },
          },
        },
        tooltip: {
          callbacks: {
            label(context) {
              return `${context.label}: ${Number(context.raw || 0).toLocaleString()}`;
            },
          },
        },
      },
    },
    plugins: [{
      id: 'adminDoughnutCenter',
      afterDraw(chart) {
        const meta = chart.getDatasetMeta(0);
        if (!meta.data?.length) return;

        const total = values.reduce((sum, value) => sum + value, 0);
        const { x, y } = meta.data[0];
        const ctx = chart.ctx;

        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#0f172a';
        ctx.font = '800 20px Arial';
        ctx.fillText(total.toLocaleString(), x, y - 5);
        ctx.fillStyle = '#64748b';
        ctx.font = '600 10px Arial';
        ctx.fillText('Total Records', x, y + 14);
        ctx.restore();
      },
    }],
  });
}

function createMonthlyActivity(labels, values) {
  const canvas = document.getElementById('monthlyActivityChart');
  if (!canvas) return;

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Records',
        data: values,
        backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#7c3aed', '#0891b2', '#16a34a'],
        borderRadius: 7,
        borderSkipped: false,
        maxBarThickness: 28,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#64748b', font: { size: 9, weight: '600' } },
          border: { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: '#64748b',
            font: { size: 9 },
            precision: 0,
          },
          grid: { color: '#eef2f7' },
          border: { display: false },
        },
      },
    },
  });
}

function createSystemActivity(labels, values) {
  const canvas = document.getElementById('systemActivityChart');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, 240);
  gradient.addColorStop(0, 'rgba(37, 99, 235, .22)');
  gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'System Activity',
        data: values,
        borderColor: '#2563eb',
        backgroundColor: gradient,
        fill: true,
        tension: .35,
        borderWidth: 2.5,
        pointRadius: 3,
        pointHoverRadius: 5,
        pointBackgroundColor: '#2563eb',
        pointBorderColor: '#ffffff',
        pointBorderWidth: 2,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: { legend: { display: false } },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#64748b', font: { size: 9, weight: '600' } },
          border: { display: false },
        },
        y: {
          beginAtZero: true,
          ticks: { color: '#64748b', font: { size: 9 }, precision: 0 },
          grid: { color: '#eef2f7' },
          border: { display: false },
        },
      },
    },
  });
}
