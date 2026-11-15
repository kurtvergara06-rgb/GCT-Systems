import Chart from 'chart.js/auto';

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

document.addEventListener('DOMContentLoaded', () => {
    // ------------------------------------------------------------------
    // ALL — Cross-domain overview (grouped bar)
    // ------------------------------------------------------------------
    const overviewCanvas = document.getElementById('predictionOverviewChart');
    if (overviewCanvas) {
        const overview = data.overview || { labels: [], records: [], at_risk: [] };
        new Chart(overviewCanvas, {
            type: 'bar',
            data: {
                labels: overview.labels || [],
                datasets: [
                    {
                        label: 'Recorded',
                        data: overview.records || [],
                        backgroundColor: '#2563eb',
                        borderRadius: 6,
                        maxBarThickness: 26,
                    },
                    {
                        label: 'At Risk',
                        data: overview.at_risk || [],
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        maxBarThickness: 26,
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
    // ALL / FLEET — Risk distribution (doughnut)
    // ------------------------------------------------------------------
    const riskDonut = document.getElementById('riskDonut');
    if (riskDonut) {
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
        const labels = data.fuel_labels || [];
        const actual = data.fuel_actual || [];
        const forecast = data.fuel_forecast || [];
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
                        pointRadius: 3,
                        borderWidth: 2,
                        fill: true,
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
    // FUEL — Efficiency trend (line)
    // ------------------------------------------------------------------
    const efficiencyChart = document.getElementById('efficiencyChart');
    if (efficiencyChart) {
        const labels = data.efficiency_labels || [];
        const actual = data.efficiency_actual || [];
        const forecast = data.efficiency_forecast || [];
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
                        pointRadius: 3,
                        borderWidth: 2,
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
    // BUS HEALTH — Fleet status (doughnut)
    // ------------------------------------------------------------------
    const busHealthDonut = document.getElementById('busHealthDonut');
    if (busHealthDonut) {
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
});