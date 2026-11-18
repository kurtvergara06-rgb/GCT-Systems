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

// Canvas labels do not inherit dark-mode CSS; use the same theme tokens as
// the surrounding Maintenance dashboard and update existing charts on toggle.
const dashboardChartAppearance = () => {
    const dark = document.documentElement.dataset.gctTheme === 'dark';
    const styles = getComputedStyle(document.documentElement);
    return {
        text: dark ? (styles.getPropertyValue('--gct-theme-fg').trim() || '#edf3fc') : '#0f2742',
        muted: dark ? (styles.getPropertyValue('--gct-theme-muted').trim() || '#b1bfd2') : '#64748b',
        grid: dark ? 'rgba(148, 163, 184, 0.25)' : 'rgba(226, 232, 240, 0.65)',
        ring: dark ? (styles.getPropertyValue('--gct-theme-surface').trim() || '#162235') : '#ffffff',
    };
};

const updateDashboardChartAppearance = () => {
    const colors = dashboardChartAppearance();
    const workload = Chart.getChart(document.getElementById('maintenanceWorkloadChart'));
    if (workload) {
        workload.options.scales.x.ticks.color = colors.muted;
        workload.options.scales.y.ticks.color = colors.muted;
        workload.options.scales.y.grid.color = colors.grid;
        workload.options.plugins.legend.labels.color = colors.text;
        workload.update('none');
    }
    const fleet = Chart.getChart(document.getElementById('maintenanceFleetHealthChart'));
    if (fleet) {
        fleet.data.datasets[0].borderColor = colors.ring;
        fleet.update('none');
    }
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

    const colors = dashboardChartAppearance();
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
                        color: colors.muted,
                        font: { size: 11, weight: '600', family: 'Poppins, Inter, sans-serif' },
                    },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: maxVal > 0 ? maxVal + 1 : 5,
                    ticks: {
                        precision: 0,
                        color: colors.muted,
                        font: { size: 10, family: 'Poppins, Inter, sans-serif' },
                    },
                    grid: {
                        color: colors.grid,
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
                        color: colors.text,
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
    const colorsForTheme = dashboardChartAppearance();

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: total === 0 ? [1] : values,
                backgroundColor: total === 0 ? ['#e2e8f0'] : colors,
                borderColor: colorsForTheme.ring,
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
let maintenanceThemeObserver = null;

const disconnectMaintenanceThemeObserver = () => {
    maintenanceThemeObserver?.disconnect();
    maintenanceThemeObserver = null;
};

function initializeMaintenanceDashboard() {
    if (!document.querySelector('.maintenance-dashboard-page')) {
        disconnectMaintenanceThemeObserver();
        return;
    }

    initializeWorkloadChart();
    initializeFleetHealthChart();

    if (!maintenanceThemeObserver) {
        maintenanceThemeObserver = new MutationObserver(updateDashboardChartAppearance);
        maintenanceThemeObserver.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-gct-theme'],
        });
    }

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

// Partial navigation must release observers as well as canvases; the
// next dashboard initialization attaches one fresh observer.
window.addEventListener('gct:navigation-before', () => {
    disconnectMaintenanceThemeObserver();
    destroyMaintenanceDashboardCharts();
});
