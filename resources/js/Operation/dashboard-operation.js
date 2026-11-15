import Chart from 'chart.js/auto';

const palette = {
    navy: '#0f2742',
    blue: '#2563eb',
    blueSoft: 'rgba(37, 99, 235, 0.82)',
    blueBorder: '#1d4ed8',
    green: '#10b981',
    greenSoft: 'rgba(16, 185, 129, 0.85)',
    greenBorder: '#059669',
    amber: '#f59e0b',
    amberSoft: 'rgba(245, 158, 11, 0.85)',
    red: '#ef4444',
    redSoft: 'rgba(239, 68, 68, 0.85)',
    slate: '#94a3b8',
    grid: 'rgba(148, 163, 184, 0.16)',
};

const chartAppearance = (root) => {
    const styles = getComputedStyle(root);
    return {
        text: styles.getPropertyValue('--gct-ui-text').trim() || palette.navy,
        muted: styles.getPropertyValue('--gct-ui-muted').trim() || '#64748b',
    };
};

const destroyChart = (canvas) => {
    if (canvas) Chart.getChart(canvas)?.destroy();
};

const parseData = (element, key, fallback) => {
    try {
        return JSON.parse(element?.dataset?.[key] || JSON.stringify(fallback));
    } catch {
        return fallback;
    }
};

const renderFleetChart = (root, dataElement) => {
    const canvas = root.querySelector('#operationFleetChart');
    if (!canvas) return;
    destroyChart(canvas);

    const data = parseData(dataElement, 'fleetReadiness', { labels: [], values: [] });
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: data.labels,
            datasets: [{
                data: data.values,
                backgroundColor: [palette.green, palette.amber, palette.red, palette.slate],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '75%',
            animation: { duration: 400, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: palette.navy,
                    titleFont: { family: 'Poppins', size: 12, weight: 'bold' },
                    bodyFont: { family: 'Poppins', size: 12 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: (context) => ` ${context.label}: ${context.parsed} buses`,
                    },
                },
            },
        },
    });
};

// Draw accurate data counts over the actual Chart.js bars (including zero days).
const operationBarValueLabels = {
    id: 'operationBarValueLabels',
    afterDatasetsDraw(chart, _args, options) {
        const { ctx, chartArea } = chart;
        if (!chartArea) return;
        ctx.save();
        ctx.font = '600 11px Poppins, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'bottom';
        chart.data.datasets.forEach((dataset, index) => {
            const meta = chart.getDatasetMeta(index);
            if (meta.hidden) return;
            meta.data.forEach((bar, pointIndex) => {
                const value = Number(dataset.data[pointIndex] ?? 0);
                if (!Number.isFinite(value)) return;
                const y = Math.max(chartArea.top + 12, bar.y - 7);
                ctx.fillStyle = value === 0 ? options.zeroColor : (options.colors?.[index] ?? options.textColor);
                ctx.fillText(String(value), bar.x, y);
            });
        });
        ctx.restore();
    },
};

const renderTripPerformance = (root, dataElement) => {
    const canvas = root.querySelector('#operationTripPerformanceChart');
    if (!canvas) return;
    destroyChart(canvas);

    const points = parseData(dataElement, 'tripPerformance', []);
    const appearance = chartAppearance(root);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: points.map((point) => point.label),
            datasets: [
                {
                    label: 'Scheduled',
                    data: points.map((point) => point.scheduled),
                    backgroundColor: 'rgba(37, 99, 235, 0.88)',
                    borderColor: palette.blueBorder,
                    borderWidth: 1.75,
                    borderRadius: 5,
                    maxBarThickness: 26,
                    categoryPercentage: 0.70,
                    barPercentage: 0.80,
                },
                {
                    label: 'Completed',
                    data: points.map((point) => point.completed),
                    backgroundColor: 'rgba(16, 185, 129, 0.90)',
                    borderColor: palette.greenBorder,
                    borderWidth: 1.75,
                    borderRadius: 5,
                    maxBarThickness: 26,
                    categoryPercentage: 0.70,
                    barPercentage: 0.80,
                },
            ],
        },
        plugins: [operationBarValueLabels],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400, easing: 'easeOutQuart' },
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: appearance.muted,
                        font: { family: 'Poppins', size: 11, weight: 600 },
                    },
                },
                y: {
                    beginAtZero: true,
                    grace: '18%',
                    ticks: {
                        precision: 0,
                        color: appearance.muted,
                        font: { family: 'Poppins', size: 10 },
                    },
                    grid: {
                        color: palette.grid,
                        borderDash: [3, 3],
                    },
                    border: { display: false },
                },
            },
            plugins: {
                operationBarValueLabels: { colors: [appearance.text, appearance.text], textColor: appearance.text, zeroColor: appearance.muted },
                legend: {
                    display: false,
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 8,
                        boxHeight: 8,
                        color: appearance.text,
                        font: { family: 'Poppins', size: 11, weight: 600 },
                        padding: 14,
                    },
                },
                tooltip: {
                    backgroundColor: palette.navy,
                    titleFont: { family: 'Poppins', size: 12, weight: 'bold' },
                    bodyFont: { family: 'Poppins', size: 11 },
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        title: (items) => {
                            const index = items[0]?.dataIndex;
                            const point = points[index];
                            return point?.date ? `Date: ${point.date}` : '';
                        },
                        afterBody: (items) => {
                            const sched = items.find((i) => i.dataset.label === 'Scheduled')?.parsed.y || 0;
                            const comp = items.find((i) => i.dataset.label === 'Completed')?.parsed.y || 0;
                            const rate = sched > 0 ? Math.round((comp / sched) * 100) : 0;
                            return `Completion: ${rate}%`;
                        },
                    },
                },
            },
        },
    });
};

const renderIncidentTrend = (root, dataElement) => {
    const canvas = root.querySelector('#operationIncidentTrendChart');
    if (!canvas) return;
    destroyChart(canvas);

    const points = parseData(dataElement, 'incidentPerformance', []);
    const appearance = chartAppearance(root);
    new Chart(canvas, {
        type: 'bar',
        data: {
            labels: points.map((point) => point.label),
            datasets: [{
                label: 'Incidents',
                data: points.map((point) => point.total),
                backgroundColor: palette.redSoft,
                borderColor: palette.red,
                borderWidth: 1.5,
                borderRadius: 6,
                maxBarThickness: 24,
            }],
        },
        plugins: [operationBarValueLabels],
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 350, easing: 'easeOutQuart' },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: appearance.muted, font: { family: 'Poppins', size: 10, weight: 600 } },
                },
                y: {
                    beginAtZero: true,
                    grace: '20%',
                    ticks: { precision: 0, color: appearance.muted, font: { family: 'Poppins', size: 9 } },
                    grid: { color: palette.grid, borderDash: [2, 2] },
                    border: { display: false },
                },
            },
            plugins: {
                operationBarValueLabels: { colors: [appearance.text], textColor: appearance.text, zeroColor: appearance.muted },
                legend: { display: false },
                tooltip: {
                    backgroundColor: palette.navy,
                    titleFont: { family: 'Poppins', size: 11, weight: 'bold' },
                    bodyFont: { family: 'Poppins', size: 11 },
                    padding: 8,
                    cornerRadius: 6,
                    callbacks: {
                        title: (items) => points[items[0]?.dataIndex]?.date || '',
                        label: (context) => ` Incidents: ${context.parsed.y}`,
                    },
                },
            },
        },
    });
};

const initializeDashboard = (root) => {
    const dataElement = root.querySelector('[data-operation-dashboard]');
    if (!dataElement) return;

    renderFleetChart(root, dataElement);
    renderTripPerformance(root, dataElement);
    renderIncidentTrend(root, dataElement);


    // Canvas text does not inherit CSS when the account theme changes.
    const themeObserver = new MutationObserver(() => {
        const appearance = chartAppearance(root);
        for (const selector of ['#operationTripPerformanceChart', '#operationIncidentTrendChart']) {
            const canvas = root.querySelector(selector);
            const chart = canvas && Chart.getChart(canvas);
            if (!chart) continue;
            for (const scale of Object.values(chart.options.scales)) {
                if (scale.ticks) scale.ticks.color = appearance.muted;
            }
            const labels = chart.options.plugins.operationBarValueLabels;
            labels.colors = chart.data.datasets.map(() => appearance.text);
            labels.textColor = appearance.text;
            labels.zeroColor = appearance.muted;
            if (chart.options.plugins.legend.labels) chart.options.plugins.legend.labels.color = appearance.text;
            chart.update('none');
        }
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-gct-theme'] });

    return () => {
        themeObserver.disconnect();
        destroyChart(root.querySelector('#operationFleetChart'));
        destroyChart(root.querySelector('#operationTripPerformanceChart'));
        destroyChart(root.querySelector('#operationIncidentTrendChart'));
    };
};

const register = () => {
    const partialNavigation = window.GCTPartialNavigation;
    if (partialNavigation?.registerInitializer) {
        partialNavigation.registerInitializer(
            'operation-dashboard',
            '.operation-dashboard-page',
            initializeDashboard
        );
        return;
    }

    const root = document.querySelector('.operation-dashboard-page');
    if (root) initializeDashboard(root);
};

register();
