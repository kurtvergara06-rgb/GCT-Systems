/* =========================================================
   GCT SYSTEM
   Predictive Fleet & Trip Analytics - Charts
   ========================================================= */

import Chart from 'chart.js/auto';

const data = window.predictiveChartData || {};

Chart.defaults.font.family = 'Poppins, Inter, -apple-system, BlinkMacSystemFont, sans-serif';
Chart.defaults.font.size = 10;
Chart.defaults.color = '#64748b';

const AXIS_GRID = 'rgba(148, 163, 184, 0.12)';
const AXIS_TEXT = '#64748b';
const TOOLTIP_BG = '#0f172a';

const defaultTooltip = {
    backgroundColor: TOOLTIP_BG,
    titleColor: '#ffffff',
    bodyColor: '#ffffff',
    padding: 8,
    cornerRadius: 6,
    titleFont: { size: 10, weight: '700' },
    bodyFont: { size: 10 },
};

function getCanvas(id) {
    return document.getElementById(id);
}

function destroyExistingChart(canvas) {
    if (!canvas) return;
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }
}

const list = (val) => Array.isArray(val) ? val : [];

/* =========================================================
   1. TRIP RISK FORECAST CHART (LINE)
   ========================================================= */
const tripRiskCanvas = getCanvas('tripRiskChart');

if (tripRiskCanvas) {
    destroyExistingChart(tripRiskCanvas);

    const tripRisk = data.tripRisk || {};
    const labels = list(tripRisk.labels);
    const tripsAtRiskData = list(tripRisk.trips_at_risk);
    const predictedDelaysData = list(tripRisk.predicted_delays);
    const idleEventsData = list(tripRisk.high_idle_events);
    const routeRiskData = list(tripRisk.route_risk);

    const ctxRisk = tripRiskCanvas.getContext('2d');
    const blueGradient = ctxRisk.createLinearGradient(0, 0, 0, 220);
    blueGradient.addColorStop(0, 'rgba(37, 99, 235, 0.22)');
    blueGradient.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

    new Chart(ctxRisk, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Trips at Risk',
                    data: tripsAtRiskData,
                    borderColor: '#2563eb',
                    backgroundColor: blueGradient,
                    fill: true,
                    borderWidth: 2.2,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2563eb',
                    tension: 0.4,
                },
                {
                    label: 'Predicted Delays',
                    data: predictedDelaysData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    borderWidth: 2.2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#f59e0b',
                    tension: 0.4,
                },
                {
                    label: 'High Idle Events',
                    data: idleEventsData,
                    borderColor: '#ef4444',
                    backgroundColor: 'transparent',
                    borderWidth: 2.2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#ef4444',
                    tension: 0.4,
                },
                {
                    label: 'Route Risk',
                    data: routeRiskData,
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    borderWidth: 2.2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#10b981',
                    tension: 0.4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: defaultTooltip,
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 9 },
                        maxRotation: 0,
                    },
                },
                y: {
                    beginAtZero: true,
                    max: 25,
                    border: { display: false },
                    grid: { color: AXIS_GRID, drawTicks: false },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 9 },
                        stepSize: 5,
                        padding: 6,
                    },
                },
            },
        },
    });
}

/* =========================================================
   2. FLEET & TRIP RISK DONUT
   ========================================================= */
const riskCanvas = getCanvas('riskDonut');

if (riskCanvas) {
    destroyExistingChart(riskCanvas);

    const risk = data.risk || {};
    const low = Number.isFinite(Number(risk.low)) ? Number(risk.low) : 0;
    const medium = Number.isFinite(Number(risk.medium)) ? Number(risk.medium) : 0;
    const high = Number.isFinite(Number(risk.high)) ? Number(risk.high) : 0;

    new Chart(riskCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Low Risk', 'Medium Risk', 'High Risk'],
            datasets: [
                {
                    data: [low, medium, high],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...defaultTooltip,
                    callbacks: {
                        label: function (context) {
                            const total = context.dataset.data.reduce((sum, val) => sum + Number(val), 0);
                            const val = Number(context.raw);
                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ` ${context.label}: ${val} (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
}

/* =========================================================
   3. FLEET PERFORMANCE FORECAST (COMBO CHART DUAL AXIS)
   ========================================================= */
const performanceCanvas = getCanvas('performanceChart');

if (performanceCanvas) {
    destroyExistingChart(performanceCanvas);

    const perf = data.performance || {};
    const labels = list(perf.labels);
    const activeBuses = list(perf.active_buses);
    const tripVolume = list(perf.trip_volume);
    const avgDuration = list(perf.avg_duration);

    new Chart(performanceCanvas.getContext('2d'), {
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'Active Buses',
                    data: activeBuses,
                    backgroundColor: (ctx) => ctx.dataIndex >= 4 ? 'rgba(37, 99, 235, 0.45)' : '#2563eb',
                    borderColor: '#2563eb',
                    borderWidth: (ctx) => ctx.dataIndex >= 4 ? 1.5 : 0,
                    borderRadius: 4,
                    barPercentage: 0.55,
                    categoryPercentage: 0.65,
                    yAxisID: 'y',
                },
                {
                    type: 'bar',
                    label: 'Trip Volume',
                    data: tripVolume,
                    backgroundColor: (ctx) => ctx.dataIndex >= 4 ? 'rgba(16, 185, 129, 0.45)' : '#10b981',
                    borderColor: '#10b981',
                    borderWidth: (ctx) => ctx.dataIndex >= 4 ? 1.5 : 0,
                    borderRadius: 4,
                    barPercentage: 0.55,
                    categoryPercentage: 0.65,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Avg. Trip Duration (mins)',
                    data: avgDuration,
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    borderWidth: 2.2,
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#f59e0b',
                    tension: 0.35,
                    yAxisID: 'y1',
                    segment: {
                        borderDash: (ctx) => ctx.p0DataIndex >= 3 ? [5, 4] : undefined,
                    },
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: defaultTooltip,
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 9 },
                        maxRotation: 0,
                    },
                },
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    max: 80,
                    border: { display: false },
                    grid: { color: AXIS_GRID, drawTicks: false },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 9 },
                        stepSize: 20,
                        padding: 6,
                    },
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    max: 120,
                    border: { display: false },
                    grid: { display: false },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 9 },
                        stepSize: 30,
                        padding: 6,
                    },
                },
            },
        },
    });
}
console.log('GCT Predictive Fleet Analytics initialized.');