import Chart from 'chart.js/auto';

const diagnosticChartInstances = {};

function destroyDiagnosticChart(canvasId) {
    if (diagnosticChartInstances[canvasId]) {
        diagnosticChartInstances[canvasId].destroy();
        delete diagnosticChartInstances[canvasId];
    }
    const canvas = document.getElementById(canvasId);
    if (canvas) {
        Chart.getChart(canvas)?.destroy();
    }
}

function destroyAllDiagnosticCharts() {
    Object.keys(diagnosticChartInstances).forEach((id) => destroyDiagnosticChart(id));
    document.querySelectorAll('.diagnostic-rebuild canvas').forEach((canvas) => {
        Chart.getChart(canvas)?.destroy();
    });
}

window.addEventListener('gct:navigation-before', () => {
    destroyAllDiagnosticCharts();
});

function initDiagnosticAnalytics() {
    destroyAllDiagnosticCharts();

    const dataPayloadElement = document.getElementById('diagnosticAnalyticsData');
    if (!dataPayloadElement) return;

    let payload = {};
    try {
        payload = JSON.parse(dataPayloadElement.textContent || '{}');
    } catch (e) {
        console.error('Failed to parse diagnostic analytics payload:', e);
        return;
    }

    if (payload.activeAnalysis === 'fuel') {
        initFuelDiagnosticCharts(payload);
    } else {
        initIncidentDiagnosticCharts(payload);
    }
}

/* =========================================================================
   THESIS OBJECTIVE 6.2.1 — INCIDENT & REPLACEMENT ANALYSIS CHARTS
   ========================================================================= */
function initIncidentDiagnosticCharts(data) {
    // 1. Incidents & Replacement Trend Chart
    const trendCanvas = document.getElementById('diagnosticIncidentTrendChart');
    if (trendCanvas && data.trendLabels && data.trendLabels.length > 0) {
        destroyDiagnosticChart('diagnosticIncidentTrendChart');
        diagnosticChartInstances['diagnosticIncidentTrendChart'] = new Chart(trendCanvas, {
            type: 'bar',
            data: {
                labels: data.trendLabels,
                datasets: [
                    {
                        label: 'Incidents Reported',
                        data: data.trendIncidents || [],
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        maxBarThickness: 32,
                        barPercentage: 0.65,
                        categoryPercentage: 0.75,
                    },
                    {
                        label: 'Replacements Dispatched',
                        data: data.trendReplacements || [],
                        backgroundColor: '#7c3aed',
                        borderRadius: 6,
                        maxBarThickness: 32,
                        barPercentage: 0.65,
                        categoryPercentage: 0.75,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, weight: '600' },
                            color: '#334155',
                            padding: 14,
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '500' }, color: '#64748b' },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { size: 10 }, color: '#64748b', precision: 0 },
                    },
                },
            },
        });
    }

    // 2. Incident Root Causes Donut Chart
    const causeCanvas = document.getElementById('diagnosticCauseDonutChart');
    if (causeCanvas && data.causeLabels && data.causeLabels.length > 0) {
        destroyDiagnosticChart('diagnosticCauseDonutChart');
        diagnosticChartInstances['diagnosticCauseDonutChart'] = new Chart(causeCanvas, {
            type: 'doughnut',
            data: {
                labels: data.causeLabels,
                datasets: [
                    {
                        data: data.causeValues,
                        backgroundColor: data.causeColors || ['#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#10b981', '#64748b'],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                const total = data.causeValues.reduce((a, b) => a + b, 0) || 1;
                                const val = ctx.parsed;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.label}: ${val} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    // 3. Replacement Response Time Intervals Chart
    const intervalCanvas = document.getElementById('diagnosticReplacementIntervalChart');
    if (intervalCanvas && data.intervalLabels && data.intervalLabels.length > 0) {
        destroyDiagnosticChart('diagnosticReplacementIntervalChart');
        diagnosticChartInstances['diagnosticReplacementIntervalChart'] = new Chart(intervalCanvas, {
            type: 'bar',
            data: {
                labels: data.intervalLabels,
                datasets: [
                    {
                        label: 'Dispatches',
                        data: data.intervalValues,
                        backgroundColor: data.intervalColors || ['#10b981', '#3b82f6', '#ef4444', '#94a3b8'],
                        borderRadius: 6,
                        maxBarThickness: 34,
                        barPercentage: 0.6,
                    },
                ],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                return ` ${ctx.parsed.x} incident(s)`;
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { precision: 0, font: { size: 10 } },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { size: 11, weight: '600' }, color: '#334155' },
                    },
                },
            },
        });
    }
}

/* =========================================================================
   THESIS OBJECTIVE 6.2.2 — FUEL & CONSUMPTION ANALYSIS CHARTS
   ========================================================================= */
function initFuelDiagnosticCharts(data) {
    // 1. Dual-Axis Fuel Consumption (L) & Efficiency (km/L) Trend Chart
    const fuelTrendCanvas = document.getElementById('diagnosticFuelTrendChart');
    if (fuelTrendCanvas && data.fuelTrendLabels && data.fuelTrendLabels.length > 0) {
        destroyDiagnosticChart('diagnosticFuelTrendChart');
        diagnosticChartInstances['diagnosticFuelTrendChart'] = new Chart(fuelTrendCanvas, {
            type: 'bar',
            data: {
                labels: data.fuelTrendLabels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Fuel Consumed (L)',
                        data: data.fuelTrendLiters || [],
                        backgroundColor: '#3b82f6',
                        borderRadius: 6,
                        maxBarThickness: 32,
                        yAxisID: 'yLiters',
                        order: 2,
                    },
                    {
                        type: 'line',
                        label: 'Efficiency (km/L)',
                        data: data.fuelTrendEfficiency || [],
                        borderColor: '#10b981',
                        borderWidth: 2.5,
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        pointBackgroundColor: '#10b981',
                        pointBorderColor: '#ffffff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        yAxisID: 'yEfficiency',
                        order: 1,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 10,
                            boxHeight: 10,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: { size: 11, weight: '600' },
                            color: '#334155',
                            padding: 14,
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        padding: 10,
                        cornerRadius: 8,
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '500' }, color: '#64748b' },
                    },
                    yLiters: {
                        type: 'linear',
                        position: 'left',
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        title: { display: true, text: 'Liters (L)', font: { size: 10, weight: '600' }, color: '#64748b' },
                        ticks: { font: { size: 10 }, color: '#64748b' },
                    },
                    yEfficiency: {
                        type: 'linear',
                        position: 'right',
                        beginAtZero: false,
                        grid: { display: false },
                        title: { display: true, text: 'km / L', font: { size: 10, weight: '600' }, color: '#10b981' },
                        ticks: { font: { size: 10 }, color: '#10b981' },
                    },
                },
            },
        });
    }

    // 2. Fuel Efficiency Tiers Donut Chart
    const efficiencyCanvas = document.getElementById('diagnosticEfficiencyDonutChart');
    if (efficiencyCanvas && data.efficiencyLabels && data.efficiencyLabels.length > 0) {
        destroyDiagnosticChart('diagnosticEfficiencyDonutChart');
        diagnosticChartInstances['diagnosticEfficiencyDonutChart'] = new Chart(efficiencyCanvas, {
            type: 'doughnut',
            data: {
                labels: data.efficiencyLabels,
                datasets: [
                    {
                        data: data.efficiencyValues,
                        backgroundColor: data.efficiencyColors || ['#10b981', '#3b82f6', '#ef4444'],
                        borderWidth: 3,
                        borderColor: '#ffffff',
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label(ctx) {
                                const total = data.efficiencyValues.reduce((a, b) => a + b, 0) || 1;
                                const val = ctx.parsed;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.label}: ${val} (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDiagnosticAnalytics);
} else {
    initDiagnosticAnalytics();
}

window.initDiagnosticAnalytics = initDiagnosticAnalytics;
