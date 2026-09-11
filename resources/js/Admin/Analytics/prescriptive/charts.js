import Chart from 'chart.js/auto';

Chart.defaults.font.family = 'Poppins, Inter, Arial, Helvetica, sans-serif';

const AXIS_GRID = '#e8edf5';
const AXIS_TEXT = '#64748b';

document.addEventListener('DOMContentLoaded', () => {
    initPrescriptiveImpactChart();
    initActionDonut();
    initFleetPrescriptiveChart();
    initFuelPrescriptiveChart();
    initBusHealthPrescriptiveChart();
    initInventoryPrescriptiveChart();
});

// 1. ALL DOMAIN - Prescriptive Impact Chart
function initPrescriptiveImpactChart() {
    const canvas = document.getElementById('prescriptiveImpactChart');
    if (!canvas) return;

    const data = window.prescriptiveChartData?.savings || {
        labels: ['Fleet Optimization', 'Fuel Conservation', 'Preventive PMS', 'Bulk Procurement'],
        current: [12000, 15000, 18000, 22000],
        prescriptive: [24000, 32000, 41000, 48500],
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Business as Usual (₱)',
                    data: data.current,
                    backgroundColor: 'rgba(59, 130, 246, 0.45)',
                    borderColor: '#3b82f6',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
                },
                {
                    label: 'Prescriptive Optimization (₱)',
                    data: data.prescriptive,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barPercentage: 0.6,
                    categoryPercentage: 0.7,
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
                        label: (ctx) => ` ${ctx.dataset.label}: ₱${Number(ctx.raw).toLocaleString()}`,
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
                        callback: (v) => `₱${Number(v).toLocaleString()}`,
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

    const data = window.fleetPrescriptiveData || {
        labels: ['Route 3', 'Route 5', 'Route 2', 'Route 1'],
        unmitigated: [18, 22, 16, 12],
        prescribed: [4, 12, 8, 3],
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Unmitigated Delay (mins)',
                    data: data.unmitigated,
                    backgroundColor: 'rgba(239, 68, 68, 0.65)',
                    borderColor: '#ef4444',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: 'Prescribed Headway (mins)',
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
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw} mins`,
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
                        callback: (v) => `${v}m`,
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

    const data = window.fuelPrescriptiveData || {
        labels: ['Bus 07', 'Bus 12', 'Bus 05', 'Bus 03'],
        current: [142, 178, 125, 110],
        prescribed: [128, 155, 117, 103],
    };

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Current Consumption (L)',
                    data: data.current,
                    backgroundColor: 'rgba(239, 68, 68, 0.55)',
                    borderColor: '#ef4444',
                    borderWidth: 1.5,
                    borderRadius: 6,
                },
                {
                    label: 'Prescribed Target (L)',
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
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw} Liters`,
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
                        callback: (v) => `${v} L`,
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

    const data = window.busHealthPrescriptiveData || {
        labels: ['GCT-108 (Brakes)', 'GCT-101 (Radiator)', 'GCT-110 (Clutch)', 'GCT-104 (Alternator)'],
        standard: [48, 36, 24, 18],
        accelerated: [12, 10, 8, 6],
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

    const data = window.inventoryPrescriptiveData || {
        labels: ['BRK-PAD-01', 'FLT-OIL-04', 'LUB-15W40-DR', 'CLT-R50-5L'],
        current: [0, 1, 0, 2],
        restocked: [12, 16, 2, 10],
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
                        label: (ctx) => ` ${ctx.dataset.label}: ${ctx.raw} units`,
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
                        callback: (v) => `${v} pcs`,
                    },
                    border: { display: false },
                },
            },
        },
    });
}
