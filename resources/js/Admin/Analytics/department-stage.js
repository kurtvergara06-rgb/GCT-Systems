import Chart from 'chart.js/auto';

function initDepartmentAnalyticsCharts() {
    const element = document.getElementById('departmentAnalyticsChartData');
    if (!element) return;

    let data;
    try {
        data = JSON.parse(element.textContent || '{}');
    } catch (error) {
        console.error('Invalid department chart data', error);
        return;
    }

    const activity = document.getElementById('departmentActivityChart');
    if (activity) {
        Chart.getChart(activity)?.destroy();
        new Chart(activity, {
            type: 'bar',
            data: {
                labels: data.months || [],
                datasets: [{
                    label: 'Recorded Entries',
                    data: data.monthly || [],
                    backgroundColor: '#2563eb',
                    borderRadius: 6,
                    maxBarThickness: 38,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#64748b' } },
                    y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#e8edf5' } },
                },
            },
        });
    }

    const sources = document.getElementById('departmentSourcesChart');
    if (sources) {
        Chart.getChart(sources)?.destroy();
        new Chart(sources, {
            type: 'doughnut',
            data: {
                labels: data.sources || [],
                datasets: [{
                    data: data.counts || [],
                    backgroundColor: ['#2563eb', '#16a34a', '#f59e0b', '#8b5cf6'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, boxWidth: 9, font: { size: 11 }, color: '#475569' },
                    },
                },
            },
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDepartmentAnalyticsCharts, { once: true });
} else {
    initDepartmentAnalyticsCharts();
}
document.addEventListener('gct:navigation-complete', initDepartmentAnalyticsCharts);
