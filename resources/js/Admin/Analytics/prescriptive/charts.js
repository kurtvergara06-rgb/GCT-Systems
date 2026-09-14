import Chart from 'chart.js/auto';

Chart.defaults.font.family = 'Poppins, Inter, Arial, Helvetica, sans-serif';

const AXIS_GRID = '#e8edf5';
const AXIS_TEXT = '#64748b';

function destroyExistingChart(canvas) {
    if (!canvas) return;
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();
}

export function initAllPrescriptiveCharts() {
    initPrescriptiveImpactChart();
    initActionDonut();
    initFleetPrescriptiveChart();
    initFuelPrescriptiveChart();
    initBusHealthPrescriptiveChart();
    initInventoryPrescriptiveChart();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllPrescriptiveCharts);
} else {
    initAllPrescriptiveCharts();
}

// 1. ALL DOMAIN - Prescriptive Impact Chart
function initPrescriptiveImpactChart() {
    const canvas = document.getElementById('prescriptiveImpactChart');
    if (!canvas) return;
    destroyExistingChart(canvas);

    const data = window.prescriptiveChartData?.savings || {
        labels: ['Fleet Dispatch', 'Fuel Efficiency', 'PMS Uptime', 'Inventory Buffer'],
        current: [82, 74, 76, 68],
        prescriptive: [96, 91, 95, 94],
    };

    const labels = (data.labels || []).map((label) => {
        if (typeof label === 'string' && label.includes(' ')) {
            return label.split(' ');
        }
        return label;
    });

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Current Baseline (%)',
                    data: data.current,
                    backgroundColor: 'rgba(59, 130, 246, 0.45)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.75,
                    categoryPercentage: 0.82,
                },
                {
                    label: 'Optimized Trajectory (%)',
                    data: data.prescriptive,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.75,
                    categoryPercentage: 0.82,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: {
                    top: 6,
                    bottom: 2,
                    left: 4,
                    right: 4,
                },
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw}%`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 11, weight: '600' },
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: false,
                    },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: AXIS_GRID },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 10.5 },
                        callback: (v) => v + '%',
                    },
                    border: { display: false },
                },
            },
        },
    });
}

// 2. ALL DOMAIN - Action Pipeline Donut
function initActionDonut() {
    const canvas = document.getElementById('actionDonut');
    if (!canvas) return;
    destroyExistingChart(canvas);

    const stats = window.prescriptiveChartData?.execution || {
        completed: 8,
        in_progress: 5,
        pending: 7,
    };

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Adopted & Deployed', 'In Active Deployment', 'Pending Operations Review'],
            datasets: [
                {
                    data: [stats.completed, stats.in_progress, stats.pending],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
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
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.label}: ${ctx.raw} playbooks`,
                    },
                },
            },
        },
    });
}

// 3. FLEET & TRIP DOMAIN - Headway Chart
function initFleetPrescriptiveChart() {
    const canvas = document.getElementById('fleetPrescriptiveChart');
    if (!canvas) return;
    destroyExistingChart(canvas);

    const rawData = window.fleetPrescriptiveData || {};
    const routes = rawData.routes || [
        { route_no: 'Route 3', corridor: 'Ayala - SM City', full: 'Route 3 - Ayala - SM City' },
        { route_no: 'Route 5', corridor: 'Talisay - Parkmall', full: 'Route 5 - Talisay - Parkmall' },
        { route_no: 'Route 2', corridor: 'Fuente - Ayala', full: 'Route 2 - Fuente - Ayala' },
        { route_no: 'Route 1', corridor: 'Bulacao - Colon', full: 'Route 1 - Bulacao - Colon' },
    ];

    const unmitigated = rawData.unmitigated || [18, 22, 16, 12];
    const prescribed = rawData.prescribed || [4, 12, 8, 3];

    // Multiline labels: [Route Number, Corridor Name]
    const labels = routes.map((r) => {
        if (r.corridor) {
            return [r.route_no, r.corridor];
        }
        if (typeof r === 'string' && r.includes(' - ')) {
            return r.split(' - ');
        }
        return r.route_no || r;
    });

    const ctx = canvas.getContext('2d');

    // Subtle vertical gradients matching system design tokens
    const delayGradient = ctx.createLinearGradient(0, 0, 0, 240);
    delayGradient.addColorStop(0, 'rgba(244, 63, 94, 0.85)');
    delayGradient.addColorStop(1, 'rgba(244, 63, 94, 0.35)');

    const prescribedGradient = ctx.createLinearGradient(0, 0, 0, 240);
    prescribedGradient.addColorStop(0, 'rgba(14, 165, 233, 0.90)');
    prescribedGradient.addColorStop(1, 'rgba(14, 165, 233, 0.40)');

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Unmitigated Delay',
                    data: unmitigated,
                    backgroundColor: delayGradient,
                    borderColor: '#f43f5e',
                    borderWidth: 1.5,
                    borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom',
                    barPercentage: 0.58,
                    categoryPercentage: 0.62,
                },
                {
                    label: 'Prescribed Headway',
                    data: prescribed,
                    backgroundColor: prescribedGradient,
                    borderColor: '#0284c7',
                    borderWidth: 1.5,
                    borderRadius: { topLeft: 6, topRight: 6, bottomLeft: 0, bottomRight: 0 },
                    borderSkipped: 'bottom',
                    barPercentage: 0.58,
                    categoryPercentage: 0.62,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            layout: {
                padding: { top: 6, bottom: 2, left: 4, right: 4 },
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0b1f44',
                    titleColor: '#ffffff',
                    bodyColor: '#f1f5f9',
                    borderColor: 'rgba(255, 255, 255, 0.12)',
                    borderWidth: 1,
                    padding: 12,
                    boxPadding: 6,
                    cornerRadius: 8,
                    titleFont: { size: 12, weight: '700' },
                    bodyFont: { size: 11.5, weight: '500' },
                    callbacks: {
                        title: (tooltipItems) => {
                            const idx = tooltipItems[0]?.dataIndex ?? 0;
                            const r = routes[idx];
                            return r ? (r.full || `${r.route_no} - ${r.corridor}`) : 'Route';
                        },
                        label: (ctx) => {
                            return ` ${ctx.dataset.label}: ${ctx.raw} mins`;
                        },
                        afterBody: (tooltipItems) => {
                            const idx = tooltipItems[0]?.dataIndex ?? 0;
                            const unmit = unmitigated[idx] || 0;
                            const presc = prescribed[idx] || 0;
                            const diff = unmit - presc;
                            const pct = unmit > 0 ? Math.round((diff / unmit) * 100) : 0;
                            return `\n⚡ Delay Recovered: -${diff} mins (${pct}% improvement)`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: (ctx) => (ctx.index !== undefined ? '#1e293b' : AXIS_TEXT),
                        font: (context) => {
                            // Sub-label line distinction
                            return {
                                size: 11,
                                weight: '600',
                            };
                        },
                        maxRotation: 0,
                        minRotation: 0,
                        autoSkip: false,
                        padding: 6,
                    },
                    border: {
                        color: '#e2e8f0',
                        width: 1,
                    },
                },
                y: {
                    beginAtZero: true,
                    suggestedMax: 25,
                    grid: {
                        color: '#f1f5f9',
                        drawBorder: false,
                    },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 10.5, weight: '500' },
                        callback: (v) => `${v}m`,
                        padding: 8,
                        stepSize: 5,
                    },
                    border: { display: false },
                },
            },
        },
    });
}

// 4. FUEL DOMAIN - Fuel Conservation Chart
function initFuelPrescriptiveChart() {
    const canvas = document.getElementById('fuelPrescriptiveChart');
    if (!canvas) return;
    destroyExistingChart(canvas);

    const data = window.fuelPrescriptiveData || {
        labels: ['Bus 07', 'Bus 12', 'Bus 05', 'Bus 03'],
        current: [0, 0, 0, 0],
        prescribed: [0, 0, 0, 0],
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Current Consumption (L / wk)',
                    data: data.current,
                    backgroundColor: 'rgba(239, 68, 68, 0.55)',
                    borderColor: '#ef4444',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: 'Prescribed Target (L / wk)',
                    data: data.prescribed,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw} L/wk`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: AXIS_TEXT, font: { size: 10.5, weight: '600' } },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: AXIS_GRID },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 10 },
                        callback: (v) => `${v} L/wk`,
                    },
                    border: { display: false },
                },
            },
        },
    });
}

// 5. BUS HEALTH DOMAIN - Turnaround Compression Chart
function initBusHealthPrescriptiveChart() {
    const canvas = document.getElementById('healthPrescriptiveChart');
    if (!canvas) return;
    destroyExistingChart(canvas);

    const data = window.busHealthPrescriptiveData || {
        labels: ['GCT-108 (Brakes)', 'GCT-101 (Radiator)', 'GCT-110 (Clutch)', 'GCT-104 (Alternator)'],
        standard: [0, 0, 0, 0],
        accelerated: [0, 0, 0, 0],
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Standard Turnaround (Hours)',
                    data: data.standard,
                    backgroundColor: 'rgba(239, 68, 68, 0.65)',
                    borderColor: '#ef4444',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: 'Prescribed Accelerated (Hours)',
                    data: data.accelerated,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw} hours`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: AXIS_TEXT, font: { size: 10, weight: '600' } },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: AXIS_GRID },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 10 },
                        callback: (v) => `${v}h`,
                    },
                    border: { display: false },
                },
            },
        },
    });
}

// 6. INVENTORY DOMAIN - Reorder Simulation Chart
function initInventoryPrescriptiveChart() {
    const canvas = document.getElementById('inventoryPrescriptiveChart');
    if (!canvas) return;
    destroyExistingChart(canvas);

    const data = window.inventoryPrescriptiveData || {
        labels: ['BRK-PAD-01', 'FLT-OIL-04', 'LUB-15W40-DR', 'CLT-R50-5L'],
        current: [0, 0, 0, 0],
        restocked: [0, 0, 0, 0],
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Current On Hand (Qty)',
                    data: data.current,
                    backgroundColor: 'rgba(239, 68, 68, 0.65)',
                    borderColor: '#ef4444',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: 'Restocked Buffer (Qty)',
                    data: data.restocked,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw} Qty`,
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: AXIS_TEXT, font: { size: 10.5, weight: '600' } },
                    border: { display: false },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: AXIS_GRID },
                    ticks: {
                        color: AXIS_TEXT,
                        font: { size: 10 },
                        callback: (v) => `${v} Qty`,
                    },
                    border: { display: false },
                },
            },
        },
    });
}
