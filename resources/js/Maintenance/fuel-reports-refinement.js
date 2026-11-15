import Chart from 'chart.js/auto';
import '../../css/Maintenance/fuel-reports-refinement.css';

const readFuelAnalytics = () => {
    const source = document.getElementById('fuelAnalyticsData');

    if (!source) {
        return null;
    }

    try {
        return JSON.parse(source.textContent.trim());
    } catch (error) {
        console.error('Unable to parse fuel analytics data for refinement.', error);
        return null;
    }
};

const normalizeFuelRows = (data) => {
    const labels = Array.isArray(data?.labels) ? data.labels : [];
    const efficiency = Array.isArray(data?.efficiency) ? data.efficiency : [];
    const distance = Array.isArray(data?.distance) ? data.distance : [];
    const fuel = Array.isArray(data?.fuel) ? data.fuel : [];

    return labels.map((label, index) => ({
        label,
        efficiency: Number(efficiency[index] || 0),
        distance: Number(distance[index] || 0),
        fuel: Number(fuel[index] || 0),
    }));
};

const updateUsageCardCopy = (canvas) => {
    const card = canvas.closest('.fuel-chart-card');

    if (!card) {
        return;
    }

    const title = card.querySelector('.fuel-chart-header h2');
    const description = card.querySelector('.fuel-chart-header p');
    const tag = card.querySelector('.fuel-chart-tag');
    const icon = card.querySelector('.fuel-chart-icon i');

    if (title) {
        title.textContent = 'Fuel Consumption & Efficiency';
    }

    if (description) {
        description.textContent = 'Compare fuel consumed by each vehicle with the efficiency achieved from that fuel.';
    }

    if (tag) {
        tag.textContent = 'Top 10';
    }

    if (icon) {
        icon.className = 'fa-solid fa-gas-pump';
    }
};

const renderFuelConsumptionEfficiencyChart = () => {
    const canvas = document.getElementById('fuelUsageChart');
    const analytics = readFuelAnalytics();

    if (!canvas || !analytics) {
        return;
    }

    const rows = normalizeFuelRows(analytics)
        .filter((row) => row.fuel > 0 && row.efficiency > 0)
        .sort((a, b) => b.fuel - a.fuel)
        .slice(0, 10);

    updateUsageCardCopy(canvas);

    if (!rows.length) {
        return;
    }

    Chart.getChart(canvas)?.destroy();

    const fleetAverage = Number(analytics.fleetAverage || 0);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: rows.map((row) => row.label),
            datasets: [
                {
                    type: 'bar',
                    label: 'Fuel Used (L)',
                    data: rows.map((row) => row.fuel),
                    backgroundColor: 'rgba(245, 158, 11, 0.72)',
                    hoverBackgroundColor: '#f59e0b',
                    borderColor: '#d97706',
                    borderWidth: 1.5,
                    borderRadius: 6,
                    barThickness: 22,
                    yAxisID: 'fuelAxis',
                    order: 2,
                },
                {
                    type: 'line',
                    label: 'Efficiency (km/L)',
                    data: rows.map((row) => row.efficiency),
                    borderColor: '#0b40b5',
                    backgroundColor: '#0b40b5',
                    pointBackgroundColor: rows.map((row) =>
                        fleetAverage > 0 && row.efficiency < fleetAverage
                            ? '#dc2626'
                            : '#0b40b5'
                    ),
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4.5,
                    pointHoverRadius: 7,
                    borderWidth: 2.5,
                    tension: 0.32,
                    fill: false,
                    yAxisID: 'efficiencyAxis',
                    order: 1,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 350 },
            interaction: {
                mode: 'index',
                intersect: false,
            },
            layout: {
                padding: { top: 10, right: 8, bottom: 4, left: 4 },
            },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 9,
                        padding: 16,
                        color: '#475569',
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 10.5,
                            weight: '600',
                        },
                    },
                },
                tooltip: {
                    backgroundColor: '#061f3d',
                    titleColor: '#ffffff',
                    bodyColor: '#e2e8f0',
                    padding: 11,
                    cornerRadius: 8,
                    callbacks: {
                        afterBody(items) {
                            const row = rows[items[0]?.dataIndex ?? -1];

                            if (!row) {
                                return [];
                            }

                            const benchmark = fleetAverage > 0
                                ? row.efficiency < fleetAverage
                                    ? 'Below fleet average'
                                    : 'At/above fleet average'
                                : 'Fleet benchmark unavailable';

                            return [
                                `Distance: ${row.distance.toFixed(2)} km`,
                                benchmark,
                            ];
                        },
                        label(context) {
                            const value = Number(context.raw || 0);

                            return context.dataset.yAxisID === 'fuelAxis'
                                ? `Fuel Used: ${value.toFixed(2)} L`
                                : `Efficiency: ${value.toFixed(2)} km/L`;
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#0f172a',
                        autoSkip: false,
                        maxRotation: 28,
                        minRotation: 28,
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 10.5,
                            weight: '700',
                        },
                    },
                },
                fuelAxis: {
                    type: 'linear',
                    position: 'left',
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Fuel Used (L)',
                        color: '#b45309',
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 11,
                            weight: '700',
                        },
                    },
                    ticks: {
                        color: '#b45309',
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 10.5,
                            weight: '600',
                        },
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.65)' },
                },
                efficiencyAxis: {
                    type: 'linear',
                    position: 'right',
                    beginAtZero: true,
                    suggestedMax: Math.max(6, ...rows.map((row) => row.efficiency)) + 0.5,
                    title: {
                        display: true,
                        text: 'Efficiency (km/L)',
                        color: '#0b40b5',
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 11,
                            weight: '700',
                        },
                    },
                    ticks: {
                        color: '#0b40b5',
                        font: {
                            family: "'Plus Jakarta Sans', sans-serif",
                            size: 10.5,
                            weight: '600',
                        },
                    },
                    grid: { drawOnChartArea: false },
                },
            },
        },
    });
};

const refineMonitoringTable = () => {
    const card = document.querySelector('.daily-monitoring-card');

    if (!card) {
        return;
    }

    card.classList.add('fuel-monitoring-refined');
    card.querySelector('.table-wrap')?.classList.add('fuel-monitoring-scroll');
};

const applyFuelReportRefinement = () => {
    if (!document.querySelector('.fuel-page')) {
        return;
    }

    refineMonitoringTable();
    renderFuelConsumptionEfficiencyChart();
};

const scheduleRefinement = () => {
    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(applyFuelReportRefinement);
    });
};

if (document.readyState === 'complete') {
    window.setTimeout(scheduleRefinement, 0);
} else {
    window.addEventListener('load', scheduleRefinement, { once: true });
}
