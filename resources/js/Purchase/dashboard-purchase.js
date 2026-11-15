import Chart from 'chart.js/auto';

Chart.defaults.font.family = 'Poppins, Inter, Arial, Helvetica, sans-serif';

const chartIds = [
    'purchaseFulfillmentChart',
    'purchaseSpendTrendChart',
];

const destroyChart = (canvas) => {
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
};

const destroyPurchaseDashboardCharts = () => {
    chartIds.forEach((id) => destroyChart(document.getElementById(id)));
};

const formatPeso = (value) => {
    return '₱' + Number(value || 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
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
 * 1. Fulfillment & Order Lifecycle Distribution (Doughnut Chart)
 */
function initializeFulfillmentChart() {
    const canvas = document.getElementById('purchaseFulfillmentChart');
    if (!canvas) return;

    destroyChart(canvas);

    let labels = [];
    let values = [];
    let colors = [];

    try {
        const data = JSON.parse(canvas.dataset.distribution || '{}');
        labels = data.labels || ['For Purchase', 'Ordered', 'For Pick-up', 'For Delivery', 'Delivered'];
        values = data.values || [0, 0, 0, 0, 0];
        colors = data.colors || ['#f59e0b', '#3b82f6', '#8b5cf6', '#06b6d4', '#10b981'];
    } catch (e) {
        console.warn('Failed to parse purchase fulfillment data:', e);
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
                            return ` ${ctx.label}: ${val} orders (${pct}%)`;
                        },
                    },
                },
            },
        },
    });
}

/**
 * 2. Procurement Spending & PO Volume Trend (Grouped / Styled Bar Chart)
 */
function initializeSpendTrendChart() {
    const canvas = document.getElementById('purchaseSpendTrendChart');
    if (!canvas) return;

    destroyChart(canvas);

    const groupingSelect = document.getElementById('purchaseSpendGrouping');
    const grouping = groupingSelect ? groupingSelect.value : 'monthly';

    let trendData = [];
    try {
        const rawMonthly = JSON.parse(canvas.dataset.monthly || '[]');
        const rawWeekly = JSON.parse(canvas.dataset.weekly || '[]');
        trendData = grouping === 'weekly' ? rawWeekly : rawMonthly;
    } catch (e) {
        console.warn('Failed to parse purchase spend trend data:', e);
        return;
    }

    const labels = trendData.map((d) => grouping === 'weekly' ? d.short_label : d.label);
    const fullLabels = trendData.map((d) => d.full_label || d.label);
    const spendValues = trendData.map((d) => Number(d.spend || 0));
    const orderValues = trendData.map((d) => Number(d.orders || 0));

    const maxSpend = Math.max(0, ...spendValues);
    const hasData = spendValues.some((v) => v > 0) || orderValues.some((v) => v > 0);

    // Dynamic gradient for bars
    const ctx = canvas.getContext('2d');
    const barGradient = ctx.createLinearGradient(0, 0, 0, 240);
    barGradient.addColorStop(0, '#0b40b5');
    barGradient.addColorStop(1, '#3b82f6');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    label: 'Spend Amount',
                    data: spendValues,
                    backgroundColor: barGradient,
                    hoverBackgroundColor: '#062d82',
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: grouping === 'weekly' ? 36 : 44,
                    yAxisID: 'ySpend',
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
                    border: { display: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 11, weight: '600', family: 'Poppins, Inter, sans-serif' },
                    },
                },
                ySpend: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    suggestedMax: maxSpend > 0 ? maxSpend * 1.15 : 5000,
                    grid: {
                        color: 'rgba(226, 232, 240, 0.65)',
                        borderDash: [3, 3],
                        drawTicks: false,
                    },
                    border: { display: false },
                    ticks: {
                        color: '#64748b',
                        font: { size: 10, family: 'Poppins, Inter, sans-serif' },
                        maxTicksLimit: 5,
                        callback: (val) => {
                            if (val >= 1000000) return '₱' + (val / 1000000).toFixed(1) + 'M';
                            if (val >= 1000) return '₱' + (val / 1000).toFixed(0) + 'k';
                            return '₱' + val;
                        },
                    },
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    ...tooltipStyle,
                    callbacks: {
                        title: (items) => {
                            const index = items[0]?.dataIndex;
                            return fullLabels[index] || items[0]?.label || '';
                        },
                        label: (ctx) => ` Spend: ${formatPeso(ctx.parsed.y)}`,
                        afterBody: (items) => {
                            const idx = items[0]?.dataIndex;
                            const ord = orderValues[idx] || 0;
                            return ` Purchase Orders: ${ord} order${ord === 1 ? '' : 's'}`;
                        },
                    },
                },
            },
        },
    });

    // Update empty state indicator if present
    const emptyState = document.getElementById('purchaseSpendEmpty');
    if (emptyState) {
        emptyState.style.display = hasData ? 'none' : 'flex';
    }
}

/**
 * Initialize all dashboard elements
 */
function initializePurchaseDashboard() {
    if (!document.querySelector('.purchase-dashboard-page')) return;

    initializeFulfillmentChart();
    initializeSpendTrendChart();

    const groupingSelect = document.getElementById('purchaseSpendGrouping');
    if (groupingSelect && !groupingSelect.dataset.bound) {
        groupingSelect.dataset.bound = 'true';
        groupingSelect.addEventListener('change', initializeSpendTrendChart);
    }
}

// Partial navigation & DOM event registration
if (window.GCTPartialNavigation?.registerInitializer) {
    window.GCTPartialNavigation.registerInitializer(
        'purchase-dashboard-redesign',
        '.purchase-dashboard-page',
        initializePurchaseDashboard
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePurchaseDashboard, { once: true });
} else {
    initializePurchaseDashboard();
}

document.addEventListener('ajax:content-updated', initializePurchaseDashboard);

window.addEventListener('gct:navigation-before', () => {
    destroyPurchaseDashboardCharts();
});
