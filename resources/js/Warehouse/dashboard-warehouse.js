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
    titleFont: { size: 12, weight: '700', family: 'Poppins, Inter, sans-serif' },
    bodyFont: { size: 11, family: 'Poppins, Inter, sans-serif' },
};

const axisText = '#475569';
const axisGrid = '#f1f5f9';

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
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 48,
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
                        font: { size: 11, weight: '600', family: 'Poppins, Inter, sans-serif' },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: axisGrid, drawTicks: false },
                    border: { display: false },
                    ticks: {
                        color: axisText,
                        precision: 0,
                        padding: 8,
                        font: { size: 10.5, family: 'Poppins, Inter, sans-serif' },
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
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.08)',
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#10b981',
                    borderWidth: 2.5,
                    tension: 0.35,
                },
                {
                    label: 'Issued',
                    data: issued,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.08)',
                    fill: true,
                    pointRadius: 3,
                    pointHoverRadius: 6,
                    pointBackgroundColor: '#2563eb',
                    borderWidth: 2.5,
                    tension: 0.35,
                },
                {
                    label: 'Adjusted',
                    data: adjusted,
                    borderColor: '#f59e0b',
                    backgroundColor: 'transparent',
                    pointRadius: 2.5,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#f59e0b',
                    borderWidth: 2,
                    borderDash: [4, 4],
                    tension: 0.35,
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
                duration: 450,
            },
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        padding: 14,
                        color: axisText,
                        font: { size: 11, weight: '600', family: 'Poppins, Inter, sans-serif' },
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
                        maxTicksLimit: 9,
                        maxRotation: 0,
                        font: { size: 10, family: 'Poppins, Inter, sans-serif' },
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: axisGrid, drawTicks: false },
                    border: { display: false },
                    ticks: {
                        color: axisText,
                        precision: 0,
                        padding: 8,
                        font: { size: 10, family: 'Poppins, Inter, sans-serif' },
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
