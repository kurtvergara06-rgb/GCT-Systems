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
    bodyColor: '#ffffff',
    padding: 9,
    cornerRadius: 7,
    titleFont: { size: 11, weight: '700' },
    bodyFont: { size: 10 },
};

const axisText = '#64748b';
const axisGrid = '#e8edf5';

function initializeInventoryBar() {
    const canvas = document.getElementById('warehouseInventoryBar');
    if (!canvas) return;

    destroyChart(canvas);

    const labels = readJson(canvas, 'labels');
    const values = readJson(canvas, 'values');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Items',
                data: values,
                backgroundColor: ['#22c55e', '#f2b705', '#ef4444'],
                borderRadius: 7,
                borderSkipped: false,
                maxBarThickness: 62,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 420,
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
                        font: { size: 10, weight: '600' },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: axisGrid, drawTicks: false },
                    border: { display: false },
                    ticks: {
                        color: axisText,
                        precision: 0,
                        padding: 7,
                        font: { size: 9 },
                    },
                },
            },
        },
    });
}

function initializeInventoryDonut() {
    const canvas = document.getElementById('warehouseInventoryDonut');
    if (!canvas) return;

    destroyChart(canvas);

    const labels = readJson(canvas, 'labels');
    const values = readJson(canvas, 'values');
    const hasData = values.some((value) => Number(value) > 0);

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: hasData ? values : [1],
                backgroundColor: hasData
                    ? ['#22c55e', '#f2b705', '#ef4444']
                    : ['#e2e8f0'],
                borderWidth: 0,
                hoverOffset: hasData ? 4 : 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            animation: {
                duration: 420,
            },
            plugins: {
                legend: { display: false },
                tooltip: hasData ? tooltipOptions : { enabled: false },
            },
        },
    });
}

function initializeMovementTrend() {
    const canvas = document.getElementById('warehouseMovementTrend');
    if (!canvas) return;

    destroyChart(canvas);

    const labels = readJson(canvas, 'labels');
    const received = readJson(canvas, 'received');
    const issued = readJson(canvas, 'issued');
    const adjusted = readJson(canvas, 'adjusted');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    label: 'Received',
                    data: received,
                    borderColor: '#16a34a',
                    backgroundColor: '#16a34a',
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                    tension: 0.32,
                },
                {
                    label: 'Issued',
                    data: issued,
                    borderColor: '#2563eb',
                    backgroundColor: '#2563eb',
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                    tension: 0.32,
                },
                {
                    label: 'Adjusted',
                    data: adjusted,
                    borderColor: '#f59e0b',
                    backgroundColor: '#f59e0b',
                    pointRadius: 2,
                    pointHoverRadius: 4,
                    borderWidth: 2,
                    tension: 0.32,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index',
            },
            animation: {
                duration: 420,
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 7,
                        boxHeight: 7,
                        padding: 12,
                        color: axisText,
                        font: { size: 9, weight: '600' },
                    },
                },
                tooltip: tooltipOptions,
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: {
                        color: axisText,
                        maxTicksLimit: 7,
                        maxRotation: 0,
                        font: { size: 8 },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: axisGrid, drawTicks: false },
                    border: { display: false },
                    ticks: {
                        color: axisText,
                        precision: 0,
                        padding: 6,
                        font: { size: 8 },
                    },
                },
            },
        },
    });
}

function initializeWarehouseDashboard() {
    if (!document.querySelector('.warehouse-dashboard-page')) return;

    initializeInventoryBar();
    initializeInventoryDonut();
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
