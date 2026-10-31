import Chart from 'chart.js/auto';

Chart.defaults.font.family = 'Poppins, Inter, Arial, Helvetica, sans-serif';

const chartIds = [
    'warehouseInventoryBar',
    'warehouseInventoryDonut',
    'warehouseMovementTrend',
];

const readJson = (element, key) => {
    try {
        return JSON.parse(element?.dataset?.[key] || '[]');
    } catch (error) {
        console.warn(`Warehouse dashboard chart data "${key}" could not be parsed.`, error);
        return [];
    }
};

const destroyChart = (canvas) => {
    if (!canvas) return;

    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
};

const destroyWarehouseDashboardCharts = () => {
    chartIds.forEach((id) => destroyChart(document.getElementById(id)));
};

const tooltipOptions = {
    backgroundColor: '#0f172a',
    titleColor: '#ffffff',
    bodyColor: '#e2e8f0',
    padding: 11,
    cornerRadius: 8,
    boxPadding: 4,
    usePointStyle: true,
    titleFont: { size: 13, weight: '700', family: 'Poppins, Inter, sans-serif' },
    bodyFont: { size: 12, family: 'Poppins, Inter, sans-serif' },
};

const axisText = '#475569';
const axisGrid = '#f1f5f9';

function initializeInventoryBar(statusData = null) {
    const canvas = document.getElementById('warehouseInventoryBar');
    if (!canvas) return;

    destroyChart(canvas);

    const labels = statusData
        ? ['Sufficient', 'Low Stock', 'Out of Stock', 'For Reorder']
        : readJson(canvas, 'labels');
    const values = statusData
        ? [statusData.available, statusData.low, statusData.out, statusData.reorder]
        : readJson(canvas, 'values');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Items',
                data: values,
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#3b82f6'],
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 52,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 450,
            },
            plugins: {
                legend: { display: false },
                tooltip: tooltipOptions,
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: axisText,
                        font: { size: 12.5, weight: '600', family: 'Poppins, Inter, sans-serif' },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: axisGrid, drawTicks: false },
                    border: { display: false },
                    title: {
                        display: true,
                        text: 'Quantity',
                        color: axisText,
                        font: { size: 12, weight: '600', family: 'Poppins, Inter, sans-serif' },
                    },
                    ticks: {
                        color: axisText,
                        precision: 0,
                        padding: 8,
                        font: { size: 11.5, family: 'Poppins, Inter, sans-serif' },
                    },
                },
            },
        },
    });
}

function initializeInventoryDonut(statusData = null) {
    const canvas = document.getElementById('warehouseInventoryDonut');
    if (!canvas) return;

    destroyChart(canvas);

    const labels = statusData
        ? ['Sufficient Stock', 'Low Stock', 'Out of Stock']
        : readJson(canvas, 'labels');
    const values = statusData
        ? [statusData.available, statusData.low, statusData.out]
        : readJson(canvas, 'values');
    const hasData = values.some((value) => Number(value) > 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: hasData ? values : [1],
                backgroundColor: hasData
                    ? ['#10b981', '#f59e0b', '#ef4444']
                    : ['#e2e8f0'],
                borderWidth: hasData ? 2 : 0,
                borderColor: '#ffffff',
                hoverOffset: hasData ? 5 : 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '74%',
            animation: {
                duration: 450,
            },
            plugins: {
                legend: { display: false },
                tooltip: hasData ? tooltipOptions : { enabled: false },
            },
        },
    });
}

function updateInventorySummary(statusData) {
    if (!statusData) return;

    const total = Number(statusData.total || 0);
    const counts = {
        available: Number(statusData.available || 0),
        low: Number(statusData.low || 0),
        out: Number(statusData.out || 0),
        reorder: Number(statusData.reorder || 0),
    };

    const totalNode = document.querySelector('[data-dashboard-total]');
    if (totalNode) {
        totalNode.textContent = total.toLocaleString();
    }

    Object.entries(counts).forEach(([key, value]) => {
        const countNode = document.querySelector(`[data-dashboard-count="${key}"]`);
        if (countNode) {
            countNode.textContent = value.toLocaleString();
        }

        const percentNode = document.querySelector(`[data-dashboard-percent="${key}"]`);
        if (percentNode) {
            const percent = total > 0 ? Math.round((value / total) * 100) : 0;
            percentNode.textContent = `(${percent}%)`;
        }
    });
}

function bindInventoryCategoryFilter() {
    const select = document.getElementById('warehouseCategoryFilter');
    if (!select || select.dataset.dashboardBound === 'true') return;

    let statusMap = {};
    try {
        statusMap = JSON.parse(select.dataset.statusMap || '{}');
    } catch (error) {
        console.warn('Warehouse dashboard category data could not be parsed.', error);
        return;
    }

    select.dataset.dashboardBound = 'true';

    select.addEventListener('change', () => {
        const selected = statusMap[select.value] || statusMap['All Categories'];
        if (!selected) return;

        initializeInventoryBar(selected);
        initializeInventoryDonut(selected);
        updateInventorySummary(selected);
    });
}

function initializeMovementTrend() {
    const canvas = document.getElementById('warehouseMovementTrend');
    if (!canvas) return;
    destroyChart(canvas);

    const labels = readJson(canvas, 'labels');
    const series = {
        received: readJson(canvas, 'received'),
        issued: readJson(canvas, 'issued'),
        adjusted: readJson(canvas, 'adjusted'),
    };
    const grouping = document.getElementById('warehouseTrendGrouping')?.value || 'weekly';
    // Group only the already-loaded, real ledger values. Preserve chronological
    // order and show the actual date span instead of ambiguous Week 1..5 labels.
    const groupSize = grouping === 'daily' ? 1 : grouping === 'monthly' ? Math.max(1, labels.length) : 7;
    const groups = Array.from({ length: Math.ceil(labels.length / groupSize) }, (_, index) => {
        const from = index * groupSize;
        const to = Math.min(labels.length, from + groupSize);
        const first = String(labels[from] || '');
        const last = String(labels[to - 1] || '');
        return {
            label: grouping === 'daily' ? first : grouping === 'monthly'
                ? (first && last ? first + ' – ' + last : 'This month')
                : (first === last ? first : first + ' – ' + last),
            received: series.received.slice(from, to).reduce((sum, v) => sum + (Number(v) || 0), 0),
            issued: series.issued.slice(from, to).reduce((sum, v) => sum + (Number(v) || 0), 0),
            adjusted: series.adjusted.slice(from, to).reduce((sum, v) => sum + (Number(v) || 0), 0),
        };
    });
    const rows = groups.length ? groups : [{ label: 'No activity', received: 0, issued: 0, adjusted: 0 }];
    const hasActivity = rows.some(row => row.received !== 0 || row.issued !== 0 || row.adjusted !== 0);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map(row => row.label),
            datasets: [
                { label: 'Received', data: rows.map(row => row.received), backgroundColor: '#289b79', hoverBackgroundColor: '#167c60' },
                { label: 'Issued', data: rows.map(row => row.issued), backgroundColor: '#467bbd', hoverBackgroundColor: '#2e62a7' },
                { label: 'Adjusted', data: rows.map(row => row.adjusted), backgroundColor: '#d6a151', hoverBackgroundColor: '#b98029' },
            ].map(dataset => ({
                ...dataset, borderWidth: 0, borderRadius: 5, borderSkipped: false,
                maxBarThickness: 20, categoryPercentage: 0.65, barPercentage: 0.84,
            })),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 350 },
            interaction: { mode: 'index', intersect: false },
            layout: { padding: { top: 3, bottom: 2, left: 2, right: 8 } },
            plugins: {
                legend: {
                    position: 'top', align: 'end',
                    labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 7,
                        boxHeight: 7, padding: 13, color: '#53667e',
                        font: { size: 10, weight: '600', family: 'Poppins, Inter, sans-serif' } },
                },
                tooltip: {
                    ...tooltipOptions,
                    callbacks: {
                        title: items => items[0]?.label || '',
                        label: item => ' ' + item.dataset.label + ': ' + Number(item.parsed.y || 0).toLocaleString() + ' units',
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false }, border: { display: false },
                    ticks: { color: '#697b91', autoSkip: grouping === 'daily',
                        maxTicksLimit: grouping === 'daily' ? 6 : 7, maxRotation: 0,
                        font: { size: grouping === 'weekly' ? 9 : 10, family: 'Poppins, Inter, sans-serif' } },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: hasActivity ? undefined : 5,
                    grid: { color: '#edf2f7', drawTicks: false },
                    border: { display: false },
                    ticks: { color: '#76859b', precision: 0, maxTicksLimit: 5,
                        padding: 8, font: { size: 10, family: 'Poppins, Inter, sans-serif' } },
                },
            },
        },
    });
}

function initializeWarehouseDashboard() {
    if (!document.querySelector('.warehouse-dashboard-page')) return;

    initializeInventoryBar();
    initializeInventoryDonut();

    const categoryFilter = document.getElementById('warehouseCategoryFilter');
    if (categoryFilter) {
        try {
            const statusMap = JSON.parse(categoryFilter.dataset.statusMap || '{}');
            updateInventorySummary(statusMap[categoryFilter.value] || statusMap['All Categories']);
        } catch (error) {
            console.warn('Warehouse dashboard category data could not be parsed.', error);
        }
    }

    bindInventoryCategoryFilter();
    const grouping = document.getElementById('warehouseTrendGrouping');
    if (grouping && !grouping.dataset.chartBound) {
        grouping.dataset.chartBound = 'true';
        grouping.addEventListener('change', initializeMovementTrend);
    }
    initializeMovementTrend();
}

if (window.GCTPartialNavigation?.registerInitializer) {
    window.GCTPartialNavigation.registerInitializer(
        'warehouse-dashboard-redesign',
        '.warehouse-dashboard-page',
        initializeWarehouseDashboard
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeWarehouseDashboard, { once: true });
} else {
    initializeWarehouseDashboard();
}

document.addEventListener('ajax:content-updated', initializeWarehouseDashboard);

window.addEventListener('gct:navigation-before', () => {
    destroyWarehouseDashboardCharts();
});
