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
        title.textContent = 'Distance vs Fuel Consumption';
    }

    if (description) {
        description.textContent = 'Each point is a vehicle. Compare travelled distance with recorded fuel use to spot unusual consumption.';
    }

    if (tag) {
        tag.textContent = 'Vehicle Relationship';
    }

    if (icon) {
        icon.className = 'fa-solid fa-chart-scatter';
    }
};

const renderDistanceFuelScatter = () => {
    const canvas = document.getElementById('fuelUsageChart');
    const analytics = readFuelAnalytics();

    if (!canvas || !analytics) {
        return;
    }

    const rows = normalizeFuelRows(analytics)
        .filter((row) => row.distance > 0 && row.fuel > 0)
        .sort((a, b) => b.distance - a.distance)
        .slice(0, 10);

    updateUsageCardCopy(canvas);

    if (!rows.length) {
        return;
    }

    Chart.getChart(canvas)?.destroy();

    const fleetAverage = Number(analytics.fleetAverage || 0);
    const points = rows.map((row) => ({
        x: row.distance,
        y: row.fuel,
        busNo: row.label,
        efficiency: row.efficiency > 0 ? row.efficiency : row.distance / row.fuel,
    }));

    new Chart(canvas, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Vehicles',
                data: points,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointBorderWidth: 2,
                pointBackgroundColor: points.map((point) =>
                    fleetAverage > 0 && point.efficiency < fleetAverage
                        ? 'rgba(239, 68, 68, 0.78)'
                        : 'rgba(11, 64, 181, 0.82)'
                ),
                pointBorderColor: points.map((point) =>
                    fleetAverage > 0 && point.efficiency < fleetAverage
                        ? '#dc2626'
                        : '#0b40b5'
                ),
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 350 },
            interaction: {
                mode: 'nearest',
                intersect: true,
            },
            layout: {
                padding: { top: 10, right: 14, bottom: 4, left: 4 },
            },
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    backgroundColor: '#061f3d',
                    titleColor: '#ffffff',
                    bodyColor: '#e2e8f0',
                    padding: 11,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        title(items) {
                            return items[0]?.raw?.busNo || 'Vehicle';
                        },
                        label(context) {
                            const point = context.raw;
                            const benchmark = fleetAverage > 0
                                ? point.efficiency < fleetAverage
                                    ? 'Below fleet average'
                                    : 'At/above fleet average'
                                : 'Fleet benchmark unavailable';

                            return [
                                `Distance: ${Number(point.x).toFixed(2)} km`,
                                `Fuel used: ${Number(point.y).toFixed(2)} L`,
                                `Efficiency: ${Number(point.efficiency).toFixed(2)} km/L`,
                                benchmark,
                            ];
                        },
                    },
                },
            },
            scales: {
                x: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Distance Travelled (km)',
                        color: '#64748b',
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '700' },
                    },
                    ticks: {
                        color: '#64748b',
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 10.5, weight: '600' },
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.65)' },
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Fuel Used (L)',
                        color: '#64748b',
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 11, weight: '700' },
                    },
                    ticks: {
                        color: '#64748b',
                        font: { family: "'Plus Jakarta Sans', sans-serif", size: 10.5, weight: '600' },
                    },
                    grid: { color: 'rgba(226, 232, 240, 0.65)' },
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
    renderDistanceFuelScatter();
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