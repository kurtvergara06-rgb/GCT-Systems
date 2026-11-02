import Chart from 'chart.js/auto';

const prescriptiveChartInstances = {};

function destroyPrescriptiveChart(canvasId) {
    if (prescriptiveChartInstances[canvasId]) {
        prescriptiveChartInstances[canvasId].destroy();
        delete prescriptiveChartInstances[canvasId];
    }
    const canvas = document.getElementById(canvasId);
    if (canvas) {
        Chart.getChart(canvas)?.destroy();
    }
}

function destroyAllPrescriptiveCharts() {
    Object.keys(prescriptiveChartInstances).forEach((id) => destroyPrescriptiveChart(id));
    document.querySelectorAll('.prescriptive-rebuild canvas').forEach((canvas) => {
        Chart.getChart(canvas)?.destroy();
    });
}

window.addEventListener('gct:navigation-before', () => {
    destroyAllPrescriptiveCharts();
});

function initPrescriptiveAnalytics() {
    destroyAllPrescriptiveCharts();

    const dataPayloadElement = document.getElementById('prescriptiveAnalyticsData');
    if (!dataPayloadElement) return;

    let payload = {};
    try {
        payload = JSON.parse(dataPayloadElement.textContent || '{}');
    } catch (e) {
        console.error('Failed to parse prescriptive analytics payload:', e);
        return;
    }

    if (payload.activeDomain === 'inventory' || payload.activeDomain === 'restocking') {
        initRestockingPrescriptiveCharts(payload);
    } else {
        initOperationsPrescriptiveCharts(payload);
    }
}

/* =========================================================================
   THESIS OBJECTIVE 6.4.1 — OPERATIONS PRESCRIPTIVE CHARTS
   ========================================================================= */
function initOperationsPrescriptiveCharts(data) {
    // Both charts display independent observed counts; unassigned trips are a
    // subset of scheduled trips and must never be stacked onto them.
    const valueLabels = {
        id: 'prescriptiveCountLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            ctx.save();
            ctx.fillStyle = '#334155';
            ctx.font = '600 11px Poppins, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            chart.data.datasets.forEach((dataset, datasetIndex) => {
                const meta = chart.getDatasetMeta(datasetIndex);
                meta.data.forEach((bar, index) => {
                    const value = Number(dataset.data[index] || 0);
                    if (value <= 0) return;
                    ctx.fillText(String(value), bar.x + 7, bar.y);
                });
            });
            ctx.restore();
        },
    };

    const shiftCanvas = document.getElementById('prescriptiveShiftChart');
    if (shiftCanvas) {
        destroyPrescriptiveChart('prescriptiveShiftChart');
        const labels = data.shiftLabels || [];
        const scheduled = data.scheduledByShift || [];
        const unassigned = data.recommendedByShift || [];
        const active = labels.map((label, i) => ({ label, scheduled: Number(scheduled[i] || 0), unassigned: Number(unassigned[i] || 0) }))
            .filter((row) => row.scheduled > 0 || row.unassigned > 0);
        const rows = active.length ? active : [{ label: 'No scheduled trips', scheduled: 0, unassigned: 0 }];

        prescriptiveChartInstances.prescriptiveShiftChart = new Chart(shiftCanvas, {
            type: 'bar',
            data: {
                labels: rows.map((row) => row.label),
                datasets: [
                    { label: 'Scheduled Trips', data: rows.map((row) => row.scheduled), backgroundColor: '#93a6c4', borderRadius: 5, maxBarThickness: 23, categoryPercentage: 0.56 },
                    { label: 'Unassigned Trips', data: rows.map((row) => row.unassigned), backgroundColor: '#2563eb', borderRadius: 5, maxBarThickness: 23, categoryPercentage: 0.56 },
                ],
            },
            plugins: [valueLabels],
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 26, left: 8, top: 4, bottom: 4 } },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'rectRounded', boxWidth: 10, padding: 16, color: '#475569', font: { family: 'Poppins', size: 10, weight: '600' } } },
                    tooltip: { backgroundColor: '#0f172a', padding: 10 },
                },
                scales: {
                    x: { beginAtZero: true, suggestedMax: Math.max(2, ...rows.map((row) => Math.max(row.scheduled, row.unassigned))) + 0.5, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#eef2f8' }, border: { display: false } },
                    y: { grid: { display: false }, border: { display: false }, ticks: { color: '#334155', font: { family: 'Poppins', size: 11, weight: '600' } } },
                },
            },
        });
    }

    const signalsCanvas = document.getElementById('prescriptiveReadinessDonutChart');
    if (signalsCanvas) {
        destroyPrescriptiveChart('prescriptiveReadinessDonutChart');
        const labels = data.readinessLabels || [];
        const values = data.readinessValues || [];
        const colors = data.readinessColors || [];
        prescriptiveChartInstances.prescriptiveReadinessDonutChart = new Chart(signalsCanvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Recorded Count',
                    data: values,
                    backgroundColor: labels.map((_, i) => colors[i] || '#2563eb'),
                    borderRadius: 5,
                    maxBarThickness: 22,
                    categoryPercentage: 0.7,
                }],
            },
            plugins: [valueLabels],
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { right: 27, left: 4, top: 4, bottom: 4 } },
                plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0f172a', padding: 10 } },
                scales: {
                    x: { beginAtZero: true, suggestedMax: Math.max(2, ...values.map((v) => Number(v) || 0)) + 0.5, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#eef2f8' }, border: { display: false } },
                    y: { ticks: { color: '#334155', font: { family: 'Poppins', size: 10, weight: '600' }, autoSkip: false }, grid: { display: false }, border: { display: false } },
                },
            },
        });
    }
}

/* =========================================================================
   THESIS OBJECTIVE 6.4.2 — RESTOCKING PRESCRIPTIVE CHARTS
   ========================================================================= */
function initRestockingPrescriptiveCharts(data) {
    // 1. Recommended Restock Quantity by Category (Horizontal Bar Chart)
    const categoryCanvas = document.getElementById('prescriptiveCategoryBarChart');
    if (categoryCanvas && data.categoryLabels && data.categoryLabels.length > 0) {
        destroyPrescriptiveChart('prescriptiveCategoryBarChart');
        const defaultPalette = ['#2563eb', '#3b82f6', '#0ea5e9', '#6366f1', '#8b5cf6', '#14b8a6'];
        const barColors = (data.categoryColors && data.categoryColors.length === data.categoryLabels.length)
            ? data.categoryColors
            : data.categoryLabels.map((_, i) => defaultPalette[i % defaultPalette.length]);

        prescriptiveChartInstances['prescriptiveCategoryBarChart'] = new Chart(categoryCanvas, {
            type: 'bar',
            data: {
                labels: data.categoryLabels,
                datasets: [
                    {
                        label: 'Threshold Gap (Units)',
                        data: data.categoryValues || [],
                        backgroundColor: barColors,
                        borderRadius: 6,
                        maxBarThickness: 28,
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
                        padding: 10,
                        backgroundColor: '#0f172a',
                        titleFont: { family: 'Poppins', size: 12, weight: '700' },
                        bodyFont: { family: 'Poppins', size: 11 },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { family: 'Poppins', size: 11 }, color: '#64748b' },
                        grid: { color: '#f1f5f9' },
                    },
                    y: {
                        grid: { display: false },
                        ticks: { font: { family: 'Poppins', size: 11, weight: '600' }, color: '#334155' },
                    },
                },
            },
        });
    }

    // 2. Stock Coverage & Buffer Deficit Projection (Grouped Bar Chart)
    const coverageCanvas = document.getElementById('prescriptiveStockCoverageChart');
    if (coverageCanvas && data.coverageLabels && data.coverageLabels.length > 0) {
        destroyPrescriptiveChart('prescriptiveStockCoverageChart');
        prescriptiveChartInstances['prescriptiveStockCoverageChart'] = new Chart(coverageCanvas, {
            type: 'bar',
            data: {
                labels: data.coverageLabels,
                datasets: [
                    {
                        label: 'Available On Hand',
                        data: data.coverageOnHand || [],
                        backgroundColor: '#3b82f6',
                        borderRadius: 4,
                        maxBarThickness: 20,
                    },
                    {
                        label: 'Reorder Point',
                        data: data.coverageReorderPoint || [],
                        backgroundColor: '#f59e0b',
                        borderRadius: 4,
                        maxBarThickness: 20,
                    },
                    {
                        label: 'Suggested Replenishment',
                        data: data.coverageRestock || [],
                        backgroundColor: '#8b5cf6',
                        borderRadius: 4,
                        maxBarThickness: 20,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 11,
                            font: { family: 'Poppins', size: 11, weight: '600' },
                            color: '#475569',
                        },
                    },
                    tooltip: {
                        padding: 10,
                        backgroundColor: '#0f172a',
                        titleFont: { family: 'Poppins', size: 12, weight: '700' },
                        bodyFont: { family: 'Poppins', size: 11 },
                    },
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Poppins', size: 10.5 }, color: '#64748b' },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { family: 'Poppins', size: 11 }, color: '#64748b' },
                        grid: { color: '#f1f5f9' },
                    },
                },
            },
        });
    }

    // 3. Replenishment Priority Distribution (Donut Chart)
    const priorityCanvas = document.getElementById('prescriptivePriorityDonutChart');
    if (priorityCanvas && data.priorityLabels && data.priorityLabels.length > 0) {
        destroyPrescriptiveChart('prescriptivePriorityDonutChart');
        prescriptiveChartInstances['prescriptivePriorityDonutChart'] = new Chart(priorityCanvas, {
            type: 'doughnut',
            data: {
                labels: data.priorityLabels,
                datasets: [
                    {
                        data: data.priorityValues || [],
                        backgroundColor: data.priorityColors || ['#dc2626', '#ea580c', '#eab308', '#16a34a'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            boxWidth: 11,
                            padding: 10,
                            font: { family: 'Poppins', size: 10.5, weight: '600' },
                            color: '#334155',
                        },
                    },
                    tooltip: {
                        padding: 10,
                        backgroundColor: '#0f172a',
                        titleFont: { family: 'Poppins', size: 12, weight: '700' },
                        bodyFont: { family: 'Poppins', size: 11 },
                    },
                },
            },
        });
    }
}

// Lifecycle registration
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPrescriptiveAnalytics);
} else {
    initPrescriptiveAnalytics();
}

window.addEventListener('gct:page-loaded', initPrescriptiveAnalytics);
