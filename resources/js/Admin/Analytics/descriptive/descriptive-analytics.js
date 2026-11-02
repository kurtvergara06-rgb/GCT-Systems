import Chart from 'chart.js/auto';

const chartInstances = {};

function destroyChart(canvasId) {
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
        delete chartInstances[canvasId];
    }
    const canvas = document.getElementById(canvasId);
    if (canvas) {
        Chart.getChart(canvas)?.destroy();
    }
}

function destroyAllDescriptiveCharts() {
    Object.keys(chartInstances).forEach((id) => destroyChart(id));
    document.querySelectorAll('.descriptive-rebuild canvas').forEach((canvas) => {
        Chart.getChart(canvas)?.destroy();
    });
}

window.addEventListener('gct:navigation-before', () => {
    destroyAllDescriptiveCharts();
});

function initDescriptiveAnalytics() {
    destroyAllDescriptiveCharts();

    const dataPayloadElement = document.getElementById('descriptiveAnalyticsData');
    if (!dataPayloadElement) return;

    let payload = {};
    try {
        payload = JSON.parse(dataPayloadElement.textContent || '{}');
    } catch (e) {
        console.error('Failed to parse descriptive analytics data:', e);
        return;
    }

    if (payload.activeSummary === 'operational') {
        initOperationalCharts(payload);
    } else {
        initAdministrativeCharts(payload);
    }
}

/* =========================================================================
   THESIS OBJECTIVE 6.1.1 — OPERATIONAL SUMMARY CHARTS
   ========================================================================= */

function initOperationalCharts(data) {
    // 1. Activity & Telemetry Trend Chart
    const trendCanvas = document.getElementById('operationalActivityTrendChart');
    if (trendCanvas && data.trendLabels && data.trendLabels.length > 0) {
        destroyChart('operationalActivityTrendChart');
        chartInstances['operationalActivityTrendChart'] = new Chart(trendCanvas, {
            type: 'bar',
            data: {
                labels: data.trendLabels,
                datasets: [
                    {
                        label: 'Scheduled Trips',
                        data: data.trendScheduled || [],
                        backgroundColor: '#2563eb',
                        borderRadius: 6,
                        maxBarThickness: 32,
                        barPercentage: 0.65,
                        categoryPercentage: 0.75,
                    },
                    {
                        label: 'Completed Trips',
                        data: data.trendCompleted || [],
                        backgroundColor: '#16a34a',
                        borderRadius: 6,
                        maxBarThickness: 32,
                        barPercentage: 0.65,
                        categoryPercentage: 0.75,
                    },
                    {
                        label: 'GPS Telemetry Records',
                        data: data.trendGps || [],
                        backgroundColor: '#0ea5e9',
                        borderRadius: 6,
                        maxBarThickness: 32,
                        barPercentage: 0.65,
                        categoryPercentage: 0.75,
                    },
                    {
                        label: 'Incidents Reported',
                        data: data.trendIncidents || [],
                        backgroundColor: '#ef4444',
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
                            font: { size: 11, weight: '600', family: 'Inter, system-ui, sans-serif' },
                            color: '#334155',
                            padding: 14,
                        },
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.94)',
                        padding: 10,
                        cornerRadius: 8,
                        titleFont: { size: 12, weight: '700' },
                        bodyFont: { size: 11 },
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

    // 2. Fleet Health Donut
    const fleetCanvas = document.getElementById('operationalFleetDonutChart');
    if (fleetCanvas && data.totalBuses > 0) {
        destroyChart('operationalFleetDonutChart');
        chartInstances['operationalFleetDonutChart'] = new Chart(fleetCanvas, {
            type: 'doughnut',
            data: {
                labels: ['Active', 'Under Maintenance', 'Inactive / Reserve'],
                datasets: [
                    {
                        data: [data.activeBuses || 0, data.underMaintenance || 0, data.inactiveBuses || 0],
                        backgroundColor: ['#16a34a', '#f59e0b', '#94a3b8'],
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
                                const total = data.totalBuses || 1;
                                const val = ctx.parsed;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.label}: ${val} buses (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    // 3. Trip Status Donut
    const tripStatusCanvas = document.getElementById('operationalTripStatusChart');
    if (tripStatusCanvas && data.tripStatusLabels && data.tripStatusLabels.length > 0) {
        destroyChart('operationalTripStatusChart');
        const colors = {
            'Completed': '#16a34a',
            'Scheduled': '#2563eb',
            'Pending DDR': '#f59e0b',
            'Missed': '#ef4444',
            'Cancelled': '#64748b',
        };
        const bgColors = data.tripStatusLabels.map((lbl) => colors[lbl] || '#3b82f6');

        chartInstances['operationalTripStatusChart'] = new Chart(tripStatusCanvas, {
            type: 'doughnut',
            data: {
                labels: data.tripStatusLabels,
                datasets: [
                    {
                        data: data.tripStatusValues,
                        backgroundColor: bgColors,
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
                                const total = data.tripStatusValues.reduce((a, b) => a + b, 0) || 1;
                                const val = ctx.parsed;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.label}: ${val} trips (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    // 4. Incident Types Breakdown
    const incidentCanvas = document.getElementById('operationalIncidentChart');
    if (incidentCanvas && data.incidentLabels && data.incidentLabels.length > 0) {
        destroyChart('operationalIncidentChart');
        chartInstances['operationalIncidentChart'] = new Chart(incidentCanvas, {
            type: 'bar',
            data: {
                labels: data.incidentLabels,
                datasets: [
                    {
                        label: 'Incidents',
                        data: data.incidentValues,
                        backgroundColor: '#ef4444',
                        borderRadius: 6,
                        maxBarThickness: 32,
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
   THESIS OBJECTIVE 6.1.2 — ADMINISTRATIVE SUMMARY CHARTS
   ========================================================================= */

function initAdministrativeCharts(data) {
    // 1. Audit Log Activity Trend (Smooth Area Curves)
    const activityTrendCanvas = document.getElementById('adminActivityTrendChart');
    if (activityTrendCanvas && data.trendLabels && data.trendLabels.length > 0) {
        destroyChart('adminActivityTrendChart');
        const ctx = activityTrendCanvas.getContext('2d');
        const gradBlue = ctx.createLinearGradient(0, 0, 0, 240);
        gradBlue.addColorStop(0, 'rgba(37, 99, 235, 0.20)');
        gradBlue.addColorStop(1, 'rgba(37, 99, 235, 0.00)');

        const gradAmber = ctx.createLinearGradient(0, 0, 0, 240);
        gradAmber.addColorStop(0, 'rgba(245, 158, 11, 0.16)');
        gradAmber.addColorStop(1, 'rgba(245, 158, 11, 0.00)');

        chartInstances['adminActivityTrendChart'] = new Chart(activityTrendCanvas, {
            type: 'line',
            data: {
                labels: data.trendLabels,
                datasets: [
                    {
                        label: 'User Activities (Audit Logs)',
                        data: data.trendActivities || [],
                        borderColor: '#2563eb',
                        borderWidth: 2.5,
                        backgroundColor: gradBlue,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'System Notifications',
                        data: data.trendNotifications || [],
                        borderColor: '#f59e0b',
                        borderWidth: 2.5,
                        backgroundColor: gradAmber,
                        fill: true,
                        tension: 0.35,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#f59e0b',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
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
                        ticks: { font: { size: 10 }, color: '#64748b' },
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

    // 2. Activity by Module
    const moduleCanvas = document.getElementById('adminModuleDistributionChart');
    if (moduleCanvas && data.moduleLabels && data.moduleLabels.length > 0) {
        destroyChart('adminModuleDistributionChart');
        const moduleColorMap = {
            'Admin': '#3b82f6',
            'Operation': '#0ea5e9',
            'Maintenance': '#f59e0b',
            'Purchase': '#8b5cf6',
            'Warehouse': '#10b981',
            'Auth': '#64748b',
        };
        const bgColors = data.moduleLabels.map((lbl) => moduleColorMap[lbl] || '#94a3b8');

        chartInstances['adminModuleDistributionChart'] = new Chart(moduleCanvas, {
            type: 'doughnut',
            data: {
                labels: data.moduleLabels,
                datasets: [
                    {
                        data: data.moduleValues,
                        backgroundColor: bgColors,
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
                                const total = data.moduleValues.reduce((a, b) => a + b, 0) || 1;
                                const val = ctx.parsed;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.label}: ${val} logs (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    // 3. Notifications by Department
    const notifCanvas = document.getElementById('adminNotificationCategoryChart');
    if (notifCanvas && data.notifModuleLabels && data.notifModuleLabels.length > 0) {
        destroyChart('adminNotificationCategoryChart');
        const notifColorMap = {
            'Admin': '#3b82f6',
            'Operation': '#0ea5e9',
            'Maintenance': '#f59e0b',
            'Purchase': '#8b5cf6',
            'Warehouse': '#10b981',
        };
        const bgColors = data.notifModuleLabels.map((lbl) => notifColorMap[lbl] || '#38bdf8');

        chartInstances['adminNotificationCategoryChart'] = new Chart(notifCanvas, {
            type: 'bar',
            data: {
                labels: data.notifModuleLabels,
                datasets: [
                    {
                        label: 'Notifications',
                        data: data.notifModuleValues,
                        backgroundColor: bgColors,
                        borderRadius: 6,
                        maxBarThickness: 34,
                        barPercentage: 0.6,
                    },
                ],
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
                        ticks: { font: { size: 10, weight: '600' }, color: '#334155' },
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { precision: 0, font: { size: 10 }, color: '#64748b' },
                    },
                },
            },
        });
    }

    // 4. User Department & Status Distribution
    const userCanvas = document.getElementById('adminUserDistributionChart');
    if (userCanvas && data.userDeptLabels && data.userDeptLabels.length > 0) {
        destroyChart('adminUserDistributionChart');
        const deptColorMap = {
            'Admin': '#3b82f6',
            'Operation': '#0ea5e9',
            'Maintenance': '#f59e0b',
            'Purchase': '#8b5cf6',
            'Warehouse': '#10b981',
            'Unassigned': '#94a3b8',
        };
        const bgColors = data.userDeptLabels.map((lbl) => deptColorMap[lbl] || '#64748b');

        chartInstances['adminUserDistributionChart'] = new Chart(userCanvas, {
            type: 'doughnut',
            data: {
                labels: data.userDeptLabels,
                datasets: [
                    {
                        data: data.userDeptValues,
                        backgroundColor: bgColors,
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
                                const total = data.userDeptValues.reduce((a, b) => a + b, 0) || 1;
                                const val = ctx.parsed;
                                const pct = Math.round((val / total) * 100);
                                return ` ${ctx.label}: ${val} users (${pct}%)`;
                            },
                        },
                    },
                },
            },
        });
    }
}

// Initializer registration
if (window.GCTPartialNavigation) {
    window.GCTPartialNavigation.registerInitializer(
        'descriptive-analytics-dashboard',
        '.descriptive-rebuild',
        initDescriptiveAnalytics
    );
}

document.addEventListener('DOMContentLoaded', initDescriptiveAnalytics);
window.addEventListener('ajax:content-updated', initDescriptiveAnalytics);
