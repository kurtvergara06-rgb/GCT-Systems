import Chart from 'chart.js/auto';

let charts = [];
function initDepartmentAnalyticsCharts() {
    charts.forEach(chart => chart.destroy());
    charts = [];
    const element = document.getElementById('departmentAnalyticsChartData');
    if (!element) return;
    let data;
    try { data = JSON.parse(element.textContent || '{}'); }
    catch { return; }
    const dark = document.documentElement.dataset.gctTheme === 'dark';
    const muted = dark ? '#b1bfd2' : '#64748b';
    const grid = dark ? '#344256' : '#e4eaf2';
    const surface = dark ? '#162235' : '#ffffff';
    const activity = document.getElementById('departmentActivityChart');
    if (activity) {
        charts.push(new Chart(activity, {
            type:'bar',
            data:{labels:data.months || [], datasets:[{label:'Verified Recorded Entries', data:data.monthly || [], backgroundColor:'#3b82f6', borderRadius:6, maxBarThickness:38}]},
            options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}}, scales:{
                x:{grid:{display:false}, ticks:{color:muted}},
                y:{beginAtZero:true, ticks:{precision:0, color:muted}, grid:{color:grid}},
            }},
        }));
    }
    const sources = document.getElementById('departmentSourcesChart');
    if (sources) {
        const hasRecords = (data.counts || []).some(value => value > 0);
        sources.hidden = !hasRecords;
        if (hasRecords) charts.push(new Chart(sources, {
            type:'doughnut',
            data:{labels:data.sources || [], datasets:[{data:data.counts || [], backgroundColor:['#3b82f6','#22c55e','#f59e0b','#a78bfa'], borderColor:surface, borderWidth:2}]},
            options:{responsive:true, maintainAspectRatio:false, cutout:'74%', plugins:{legend:{display:false}}},
        }));
    }
    (data.statuses || []).forEach((status, index) => {
        const canvas = document.getElementById('departmentStatusChart' + index);
        if (!canvas || !(status.counts || []).some(value => Number(value) > 0)) return;
        charts.push(new Chart(canvas, {
            type:'doughnut',
            data:{labels:status.labels || [], datasets:[{data:status.counts || [], backgroundColor:['#3b82f6','#22c55e','#f59e0b','#a78bfa','#ef4444','#14b8a6','#f97316','#64748b'], borderColor:surface, borderWidth:2}]},
            options:{responsive:true, maintainAspectRatio:false, cutout:'74%', plugins:{legend:{display:false}}},
        }));
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initDepartmentAnalyticsCharts, {once:true});
else initDepartmentAnalyticsCharts();
document.addEventListener('gct:navigation-complete', initDepartmentAnalyticsCharts);
new MutationObserver(initDepartmentAnalyticsCharts).observe(document.documentElement, {attributes:true, attributeFilter:['data-gct-theme']});
