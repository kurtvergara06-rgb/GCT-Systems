import Chart from 'chart.js/auto';

Chart.defaults.font.family = 'Poppins, Inter, Arial, Helvetica, sans-serif';

const chartIds = [
    'maintenanceWorkloadChart',
    'maintenanceFleetHealthChart',
];

const destroyChart = (canvas) => {
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
};

const destroyMaintenanceDashboardCharts = () => {
    chartIds.forEach((id) => destroyChart(document.getElementById(id)));
};

const tooltipStyle = {
    backgroundColor: '#0f172a',
    titleColor: '#ffffff',
    bodyColor: '#e2e8f0',
    padding: 11,
    cornerRadius: 8,
    boxPadding: 5,
    usePointStyle: true,
    titleFont: { size: 12.5, weight: '700', family: 'Poppins, Inter, sans-serif' },
    bodyFont: { size: 11.5, family: 'Poppins, Inter, sans-serif' },
};

/**
 * 1. Work Order Activity & Repair Completion Trend (Chart.js Bar Chart)
 */
function initializeWorkloadChart() {
    const canvas = document.getElementById('maintenanceWorkloadChart');
    if (!canvas) return;

    destroyChart(canvas);

    const groupingSelect = document.getElementById('maintenanceWorkloadGrouping');
    const grouping = groupingSelect ? groupingSelect.value : 'daily';

    let trendData = [];
    try {
        const rawDaily = JSON.parse(canvas.dataset.daily || '[]');
        const rawWeekly = JSON.parse(canvas.dataset.weekly || '[]');
        const rawMonthly = JSON.parse(canvas.dataset.monthly || '[]');

        if (grouping === 'weekly') {
            trendData = rawWeekly;
        } else if (grouping === 'monthly') {
            trendData = rawMonthly;
        } else {
            trendData = rawDaily;
        }
    } catch (e) {
        console.warn('Failed to parse maintenance workload data:', e);
        return;
    }

    const labels = trendData.map((d) => d.label);
    const fullLabels = trendData.map((d) => d.full_label || d.label);
    const createdValues = trendData.map((d) => Number(d.created || 0));
    const completedValues = trendData.map((d) => Number(d.completed || 0));

    const maxVal = Math.max(0, ...createdValues, ...completedValues);
    const hasData = createdValues.some((v) => v > 0) || completedValues.some((v) => v > 0);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Created',
                    data: createdValues,
                    backgroundColor: 'rgba(37, 99, 235, 0.82)',
                    borderColor: '#1d4ed8',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    maxBarThickness: grouping === 'weekly' ? 32 : 36,
                    categoryPercentage: 0.75,
                    barPercentage: 0.85,
                },
                {
                    label: 'Completed',
                    data: completedValues,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#059669',
                    borderWidth: 1.5,
                    borderRadius: 8,
                    maxBarThickness: grouping === 'weekly' ? 32 : 36,
                    categoryPercentage: 0.75,
                    barPercentage: 0.85,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 11, weight: '600', family: 'Poppins, Inter, sans-serif' },
                    },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: maxVal > 0 ? maxVal + 1 : 5,
                    ticks: {
                        precision: 0,
                        color: '#64748b',
                        font: { size: 10, family: 'Poppins, Inter, sans-serif' },
                    },
                    grid: {
                        color: 'rgba(226, 232, 240, 0.65)',
                        borderDash: [3, 3],
                        drawTicks: false,
                    },
                    border: { display: false },
                },
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    align: 'start',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        color: '#0f2742',
                        font: { size: 11, weight: '600', family: 'Poppins, Inter, sans-serif' },
                        padding: 16,
                    },
                },
                tooltip: {
                    ...tooltipStyle,
                    callbacks: {
                        title: (items) => {
                            const index = items[0]?.dataIndex;
                            return fullLabels[index] || items[0]?.label || '';
                        },
                    },
                },
            },
        },
    });

    const emptyState = document.getElementById('maintenanceWorkloadEmpty');
    if (emptyState) {
        emptyState.style.display = hasData ? 'none' : 'flex';
    }
}

/**
 * 2. Fleet Health & Readiness Distribution (Doughnut Chart)
 */
function initializeFleetHealthChart() {
    const canvas = document.getElementById('maintenanceFleetHealthChart');
    if (!canvas) return;

    destroyChart(canvas);

    let labels = [];
    let values = [];
    let colors = [];

    try {
        const data = JSON.parse(canvas.dataset.distribution || '{}');
        labels = data.labels || ['Active Ready Fleet', 'Under Maintenance', 'Inactive / Restricted'];
        values = data.values || [0, 0, 0];
        colors = data.colors || ['#10b981', '#f59e0b', '#ef4444'];
    } catch (e) {
        console.warn('Failed to parse fleet health distribution:', e);
        return;
    }

    const total = values.reduce((sum, v) => sum + Number(v || 0), 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: total === 0 ? [1] : values,
                backgroundColor: total === 0 ? ['#e2e8f0'] : colors,
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 6,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '74%',
            animation: { duration: 400, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: total === 0 ? { enabled: false } : {
                    ...tooltipStyle,
                    callbacks: {
                        label: (ctx) => {
                            const val = ctx.raw || 0;
                            const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                            return ` ${ctx.label}: ${val} buses (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
}

/**
 * Main initializer
 */
function initializeMaintenanceDashboard() {
    if (!document.querySelector('.maintenance-dashboard-page')) return;

    initializeWorkloadChart();
    initializeFleetHealthChart();

    const groupingSelect = document.getElementById('maintenanceWorkloadGrouping');
    if (groupingSelect && !groupingSelect.dataset.bound) {
        groupingSelect.dataset.bound = 'true';
        groupingSelect.addEventListener('change', initializeWorkloadChart);
    }
}

// Partial navigation & DOM event registration
if (window.GCTPartialNavigation?.registerInitializer) {
    window.GCTPartialNavigation.registerInitializer(
        'maintenance-dashboard-redesign',
        '.maintenance-dashboard-page',
        initializeMaintenanceDashboard
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeMaintenanceDashboard, { once: true });
} else {
    initializeMaintenanceDashboard();
}

document.addEventListener('ajax:content-updated', initializeMaintenanceDashboard);

window.addEventListener('gct:navigation-before', () => {
    destroyMaintenanceDashboardCharts();
});
