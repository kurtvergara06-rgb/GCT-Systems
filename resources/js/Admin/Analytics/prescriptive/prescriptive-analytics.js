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
    // 1. Shift & Schedule Recommendation Distribution Chart
    const shiftCanvas = document.getElementById('prescriptiveShiftChart');
    if (shiftCanvas && data.shiftLabels && data.shiftLabels.length > 0) {
        destroyPrescriptiveChart('prescriptiveShiftChart');
        prescriptiveChartInstances['prescriptiveShiftChart'] = new Chart(shiftCanvas, {
            type: 'bar',
            data: {
                labels: data.shiftLabels,
                datasets: [
                    {
                        label: 'Scheduled Trips',
                        data: data.scheduledByShift || [],
                        backgroundColor: '#94a3b8',
                        borderRadius: 6,
                        maxBarThickness: 34,
                        barPercentage: 0.65,
                        categoryPercentage: 0.75,
                    },
                    {
                        label: 'Prescribed / Staggered Dispatches',
                        data: data.recommendedByShift || [],
                        backgroundColor: '#2563eb',
                        borderRadius: 6,
                        maxBarThickness: 34,
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
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
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
                        ticks: { font: { family: 'Poppins', size: 11 }, color: '#64748b' },
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

    // 2. Resource Readiness & Dispatch Status Donut Chart
    const donutCanvas = document.getElementById('prescriptiveReadinessDonutChart');
    if (donutCanvas && data.readinessLabels && data.readinessLabels.length > 0) {
        destroyPrescriptiveChart('prescriptiveReadinessDonutChart');
        prescriptiveChartInstances['prescriptiveReadinessDonutChart'] = new Chart(donutCanvas, {
            type: 'doughnut',
            data: {
                labels: data.readinessLabels,
                datasets: [
                    {
                        data: data.readinessValues || [],
                        backgroundColor: data.readinessColors || ['#16a34a', '#2563eb', '#ea580c', '#dc2626'],
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
                            padding: 12,
                            font: { family: 'Poppins', size: 11, weight: '600' },
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
                        label: 'Suggested Restock Quantity (Units)',
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
                        label: 'Safety Buffer Target',
                        data: data.coverageBuffer || [],
                        backgroundColor: '#f59e0b',
                        borderRadius: 4,
                        maxBarThickness: 20,
                    },
                    {
                        label: 'Prescribed Restock Qty',
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
