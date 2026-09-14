import Chart from 'chart.js/auto';

Chart.defaults.font.family = 'Poppins, Inter, Arial, Helvetica, sans-serif';

const data = window.predictiveChartData || {};

const AXIS_GRID = '#e8edf5';
const AXIS_TEXT = '#64748b';

const legend = (position = 'bottom') => ({
    position,
    labels: {
        usePointStyle: true,
        pointStyle: 'circle',
        boxWidth: 8,
        boxHeight: 8,
        padding: 14,
        color: AXIS_TEXT,
        font: { size: 10, weight: '600' },
    },
});

const axes = {
    x: { grid: { display: false }, ticks: { color: AXIS_TEXT, font: { size: 9 } }, border: { display: false } },
    y: { beginAtZero: true, grid: { color: AXIS_GRID }, ticks: { color: AXIS_TEXT, font: { size: 9 } }, border: { display: false } },
};

const fuelXAxis = (labels, offset = false) => ({
    grid: { display: false },
    offset,
    ticks: {
        color: AXIS_TEXT,
        autoSkip: true,
        maxTicksLimit: Math.min(labels.length || 1, 8),
        maxRotation: 0,
        font: { size: 9 },
    },
    border: { display: false },
});

const fuelYAxis = (values, beginAtZero = true) => {
    const numericValues = values
        .filter((value) => value !== null && value !== '' && Number.isFinite(Number(value)))
        .map(Number);
    const minimum = numericValues.length ? Math.min(...numericValues) : 0;
    const maximum = numericValues.length ? Math.max(...numericValues) : 1;
    const range = Math.max(maximum - minimum, maximum * 0.08, 1);

    return {
        beginAtZero,
        suggestedMin: beginAtZero ? 0 : Math.max(0, minimum - range * 0.15),
        suggestedMax: maximum + range * 0.15,
        grid: { color: AXIS_GRID },
        ticks: { color: AXIS_TEXT, font: { size: 9 }, maxTicksLimit: 6 },
        border: { display: false },
    };
};

const fuelPlugins = {
    legend: legend(),
    tooltip: {
        callbacks: {
            label: (context) => `${context.dataset.label}: ${context.parsed.y ?? '—'}`,
        },
    },
};

function showFuelEmptyState(canvas) {
    const container = canvas.parentElement;
    if (!container) return;

    container.classList.add('has-empty-chart');
    canvas.setAttribute('aria-hidden', 'true');
    const message = document.createElement('p');
    message.className = 'analytics-compact-empty';
    message.textContent = 'No fuel trend data is available for the selected period.';
    container.appendChild(message);
}

function hasNumericValues(values) {
    return values.some((value) => value !== null && value !== '' && Number.isFinite(Number(value)));
}

function donutCenter(id, total) {
    const label = document.getElementById(id);
    if (label) {
        label.textContent = Number(total || 0).toLocaleString();
    }
}

function createGradient(ctx, height, color) {
    const gradient = ctx.createLinearGradient(0, 0, 0, height || 240);
    gradient.addColorStop(0, color);
    gradient.addColorStop(1, 'rgba(45, 99, 184, 0)');
    return gradient;
}

function destroyExistingChart(canvas) {
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
}

document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------------------------------------
    // ALL — Cross-domain overview (grouped bar)
    // ------------------------------------------------------------------
    const overviewCanvas = document.getElementById('predictionOverviewChart');
    if (overviewCanvas) {
        destroyExistingChart(overviewCanvas);
        const pData = window.predictiveChartData || data || {};
        const overview = pData.overview || { labels: [], records: [], at_risk: [] };
        const labels = Array.isArray(overview.labels) && overview.labels.length > 0
            ? overview.labels
            : ['Fleet & Trip', 'Fuel', 'Bus Health', 'Inventory'];
        const records = Array.isArray(overview.records) && overview.records.length > 0
            ? overview.records
            : [0, 0, 0, 0];
        const atRisk = Array.isArray(overview.at_risk) && overview.at_risk.length > 0
            ? overview.at_risk
            : [0, 0, 0, 0];

        new Chart(overviewCanvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Total Monitored',
                        data: records,
                        backgroundColor: '#2563eb',
                        borderRadius: 4,
                        barPercentage: 0.65,
                        categoryPercentage: 0.65,
                    },
                    {
                        label: 'At Risk / Flagged',
                        data: atRisk,
                        backgroundColor: '#ef4444',
                        borderRadius: 4,
                        barPercentage: 0.65,
                        categoryPercentage: 0.65,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            padding: 14,
                            font: { size: 10, weight: '700' },
                            color: '#64748b',
                        },
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        padding: 8,
                        cornerRadius: 6,
                        titleFont: { size: 11, weight: '700' },
                        bodyFont: { size: 10 },
                    },
                },
                scales: {
                    x: {
                        type: 'category',
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: '#475569',
                            font: { size: 10, weight: '700' },
                            maxRotation: 0,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: AXIS_GRID, drawTicks: false },
                        ticks: {
                            color: '#64748b',
                            font: { size: 9 },
                            padding: 6,
                        },
                    },
                },
            },
        });
    }

    // ------------------------------------------------------------------
    // ALL / FLEET — Risk distribution (doughnut)
    // ------------------------------------------------------------------
    const riskDonut = document.getElementById('riskDonut');
    if (riskDonut) {
        destroyExistingChart(riskDonut);
        const risk = data.risk || { low: 0, medium: 0, high: 0, total: 0 };
        donutCenter('riskDonutTotal', risk.total);
        new Chart(riskDonut, {
            type: 'doughnut',
            data: {
                labels: ['Low Risk', 'Medium Risk', 'High Risk'],
                datasets: [{
                    data: [risk.low || 0, risk.medium || 0, risk.high || 0],
                    backgroundColor: ['#16a34a', '#fbbf24', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false } },
            },
        });
    }

    // ------------------------------------------------------------------
    // ALL — Fuel demand forecast (bar + line)
    // ------------------------------------------------------------------
    const fuelForecastChart = document.getElementById('fuelForecastChart');
    if (fuelForecastChart) {
        destroyExistingChart(fuelForecastChart);
        const labels = data.fuel_labels || [];
        const actual = data.fuel_actual || [];
        const forecast = data.fuel_forecast || [];
        new Chart(fuelForecastChart, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Recorded',
                        data: actual,
                        backgroundColor: '#2563eb',
                        borderRadius: 4,
                        maxBarThickness: 26,
                    },
                    {
                        type: 'line',
                        label: 'Forecast',
                        data: forecast,
                        borderColor: '#16a34a',
                        backgroundColor: '#16a34a',
                        borderDash: [6, 4],
                        tension: 0.4,
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: legend() },
                scales: axes,
            },
        });
    }

    // ------------------------------------------------------------------
    // FLEET — Trip risk forecast (line)
    // ------------------------------------------------------------------
    const tripRiskChart = document.getElementById('tripRiskChart');
    if (tripRiskChart) {
        destroyExistingChart(tripRiskChart);
        const series = data.tripRisk || { labels: [], values: [] };
        new Chart(tripRiskChart, {
            type: 'line',
            data: {
                labels: series.labels || [],
                datasets: [{
                    label: 'Trip Volume',
                    data: series.values || [],
                    borderColor: '#2563eb',
                    backgroundColor: '#2563eb',
                    tension: 0.4,
                    pointRadius: 3,
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: legend('top') },
                scales: axes,
            },
        });
    }

    // ------------------------------------------------------------------
    // FLEET — Performance (recorded vs forecast)
    // ------------------------------------------------------------------
    const performanceChart = document.getElementById('performanceChart');
    if (performanceChart) {
        destroyExistingChart(performanceChart);
        const performance = data.performance || { labels: [], recorded: [], forecast: [] };
        new Chart(performanceChart, {
            data: {
                labels: performance.labels || [],
                datasets: [
                    {
                        type: 'line',
                        label: 'Recorded',
                        data: performance.recorded || [],
                        borderColor: '#f59e0b',
                        backgroundColor: '#f59e0b',
                        tension: 0.4,
                        pointRadius: 3,
                        borderWidth: 2,
                    },
                    {
                        type: 'line',
                        label: 'Forecast',
                        data: performance.forecast || [],
                        borderColor: '#2563eb',
                        backgroundColor: '#2563eb',
                        borderDash: [6, 5],
                        tension: 0.4,
                        pointRadius: 3,
                        borderWidth: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: legend() },
                scales: axes,
            },
        });
    }

    // ------------------------------------------------------------------
    // FUEL — Consumption trend (line)
    // ------------------------------------------------------------------
    const consumptionChart = document.getElementById('consumptionChart');
    if (consumptionChart) {
        destroyExistingChart(consumptionChart);
        const labels = data.fuel_labels || [];
        const actual = data.fuel_actual || [];
        const forecast = data.fuel_forecast || [];
        if (!hasNumericValues([...actual, ...forecast])) {
            showFuelEmptyState(consumptionChart);
        } else {
            new Chart(consumptionChart, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Recorded',
                            data: actual,
                            borderColor: '#2563eb',
                            backgroundColor: '#2563eb',
                            tension: 0.4,
                            pointRadius: 4,
                            borderWidth: 2.5,
                            fill: true,
                            spanGaps: true,
                            backgroundColor: (context) => createGradient(context.chart.ctx, context.chart.height, 'rgba(37, 99, 235, .18)'),
                        },
                        {
                            label: 'Forecast',
                            data: forecast,
                            borderColor: '#16a34a',
                            backgroundColor: '#16a34a',
                            borderDash: [6, 4],
                            tension: 0.4,
                            pointRadius: 3,
                            borderWidth: 2,
                            spanGaps: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: fuelPlugins,
                    scales: {
                        x: fuelXAxis(labels),
                        y: fuelYAxis([...actual, ...forecast]),
                    },
                },
            });
        }
    }

    // ------------------------------------------------------------------
    // FUEL — Efficiency trend (line)
    // ------------------------------------------------------------------
    const efficiencyChart = document.getElementById('efficiencyChart');
    if (efficiencyChart) {
        destroyExistingChart(efficiencyChart);
        const labels = data.efficiency_labels || [];
        const actual = data.efficiency_actual || [];
        const forecast = data.efficiency_forecast || [];
        if (!hasNumericValues([...actual, ...forecast])) {
            showFuelEmptyState(efficiencyChart);
        } else {
            new Chart(efficiencyChart, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: 'Recorded',
                            data: actual,
                            borderColor: '#2563eb',
                            backgroundColor: '#2563eb',
                            tension: 0.4,
                            pointRadius: 4,
                            borderWidth: 2.5,
                            spanGaps: true,
                        },
                        {
                            label: 'Forecast',
                            data: forecast,
                            borderColor: '#16a34a',
                            backgroundColor: '#16a34a',
                            borderDash: [6, 4],
                            tension: 0.4,
                            pointRadius: 3,
                            borderWidth: 2,
                            spanGaps: true,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: fuelPlugins,
                    scales: {
                        x: fuelXAxis(labels),
                        y: fuelYAxis([...actual, ...forecast], false),
                    },
                },
            });
        }
    }

    // ------------------------------------------------------------------
    // BUS HEALTH — Fleet status (doughnut)
    // ------------------------------------------------------------------
    const busHealthDonut = document.getElementById('busHealthDonut');
    if (busHealthDonut) {
        destroyExistingChart(busHealthDonut);
        const health = data.busHealth || { active: 0, maintenance: 0, inactive: 0, total: 0 };
        donutCenter('busHealthDonutTotal', health.total);
        new Chart(busHealthDonut, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Under Maintenance', 'Inactive'],
                datasets: [{
                    data: [health.active || 0, health.maintenance || 0, health.inactive || 0],
                    backgroundColor: ['#16a34a', '#f59e0b', '#94a3b8'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false } },
            },
        });
    }

    // ------------------------------------------------------------------
    // INVENTORY — Stock levels (doughnut)
    // ------------------------------------------------------------------
    const inventoryDonut = document.getElementById('inventoryDonut');
    if (inventoryDonut) {
        destroyExistingChart(inventoryDonut);
        const stock = data.inventory || { healthy: 0, low: 0, critical: 0, total: 0 };
        donutCenter('inventoryDonutTotal', stock.total);
        new Chart(inventoryDonut, {
            type: 'doughnut',
            data: {
                labels: ['Well Stocked', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [stock.healthy || 0, stock.low || 0, stock.critical || 0],
                    backgroundColor: ['#16a34a', '#fbbf24', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false } },
            },
        });
    }

    // ------------------------------------------------------------------
    // FUEL — Risk distribution (doughnut)
    // ------------------------------------------------------------------
    const fuelRiskDonut = document.getElementById('fuelRiskDonut');
    if (fuelRiskDonut) {
        destroyExistingChart(fuelRiskDonut);
        const risk = data.risk || { low: 0, medium: 0, high: 0, total: 0 };
        new Chart(fuelRiskDonut, {
            type: 'doughnut',
            data: {
                labels: ['Low Risk', 'Medium Risk', 'High Risk'],
                datasets: [{
                    data: [risk.low || 0, risk.medium || 0, risk.high || 0],
                    backgroundColor: ['#16a34a', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false } },
            },
        });
    }
});