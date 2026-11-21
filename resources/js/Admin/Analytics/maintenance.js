import Chart from 'chart.js/auto';

let charts = [];
let requestController;
let lastTheme;
function initializeMaintenanceAnalytics() {
    charts.forEach(chart => chart.destroy());
    charts = [];
    const root = document.querySelector('.ma-content');
    if (!root) return;
    const data = JSON.parse(root.querySelector('#maintenanceAnalyticsData')?.textContent || '{}');
    lastTheme = document.documentElement.dataset.gctTheme;
    const dark = lastTheme === 'dark';
    const text = dark ? '#cbd5e1' : '#52637b';
    const grid = dark ? '#30415a' : '#e4eaf2';
    const colors = ['#22c55e','#3b82f6','#eab308','#a78bfa','#fb7185','#14b8a6'];
    const draw = (id, type, labels, datasets, extra = {}) => {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        charts.push(new Chart(canvas, {type, data:{labels, datasets}, options:{
            responsive:true, maintainAspectRatio:false,
            plugins:{legend:{position:'bottom', labels:{color:text, usePointStyle:true, padding:18}}, tooltip:{mode:'index', intersect:false}},
            scales:type === 'doughnut' ? {} : {x:{ticks:{color:text}, grid:{display:false}},y:{beginAtZero:true,ticks:{color:text},grid:{color:grid}}},
            ...extra,
        }}));
    };
    const series = (label, values, color, extra = {}) => ({label, data:values, backgroundColor:color, borderColor:color, borderRadius:4, maxBarThickness:34, ...extra});
    const monthly = data.monthly || [];
    draw('maJobsChart','bar',monthly.map(row=>row.label),[
        series('Created Job Orders',monthly.map(row=>row.created),'#3b82f6'),series('Completed Repairs',monthly.map(row=>row.completed),'#22c55e'),
    ]);
    draw('maFuelChart','line',monthly.map(row=>row.label),[series('Fuel Consumed (Liters)',monthly.map(row=>row.fuel),'#3b82f6',{fill:true,backgroundColor:dark?'#2563eb30':'#2563eb18',pointRadius:4,tension:.2})]);
    draw('maStatusChart','doughnut',(data.statuses || []).map(row=>row.label),[{
        data:(data.statuses || []).map(row=>row.count),
        backgroundColor:(data.statuses || []).map(row=>row.color || '#94a3b8'),
        borderWidth:0,
        hoverOffset:3,
    }],{cutout:'72%',plugins:{legend:{display:false}}});
    draw('maFactorsChart','bar',(data.factors || []).map(row=>row.label),[series('Recorded signals',(data.factors || []).map(row=>row.count),'#3b82f6')],{
        indexAxis:'y',scales:{x:{beginAtZero:true,ticks:{color:text,precision:0},grid:{color:grid}},y:{ticks:{color:text},grid:{display:false}}},plugins:{legend:{display:false}},
    });
    draw('maComparisonChart','bar',(data.comparison || []).map(row=>row.bus),[
        series('Actual Consumption',(data.comparison || []).map(row=>row.actual),'#3b82f6'),series('Distance-Adjusted Baseline',(data.comparison || []).map(row=>row.expected),dark?'#a2b4cf':'#94a3b8'),
    ]);
    draw('maRepairForecastChart','bar',(data.repair || []).map(row=>row.label),[series('Rule-Based Mean Duration (hours)',(data.repair || []).map(row=>row.value),'#a78bfa')]);
    const history = data.fuelHistory || [], forecast = data.fuelForecast || [];
    draw('maFuelForecastChart','line',[...history,...forecast].map(row=>row.label),[
        series('Historical Fuel Usage',history.map(row=>row.value).concat(forecast.map(()=>null)),'#3b82f6',{spanGaps:false,pointRadius:4}),
        series('Forecast (Rule-Based)',history.map(()=>null).concat(forecast.map(row=>row.value)),'#a78bfa',{borderDash:[6,5],spanGaps:false,pointRadius:4}),
    ]);
    if (root.dataset.initialized) return;
    root.dataset.initialized = '1';
    root.addEventListener('click', event => {
        const open = event.target.closest('.ma-detail-open');
        if (open) {
            const dialog = document.getElementById(open.dataset.dialog);
            // Dialog lives inside the themed page, so both tokens and color-scheme inherit.
            dialog?.showModal();
        }
        event.target.closest('.ma-detail-close')?.closest('dialog')?.close();
        // No backdrop listener: clicking outside a dialog never closes it.
    });
    // The descriptive form is part of the topbar, outside .ma-content.
    // Use native GET navigation for accurate charts, history, and filter state.
    const filterForm = document.querySelector('.department-analytics-page .topbar-toolbar .ma-filters--compact');
    if (filterForm && !filterForm.dataset.maBound) {
        filterForm.dataset.maBound = 'true';
        const period = filterForm.querySelector('[data-ma-period]');
        const bus = filterForm.querySelector('[data-ma-bus]');
        const custom = filterForm.querySelector('[data-ma-custom-range]');
        const syncCustom = () => {
            const active = period?.value === '';
            if (custom) custom.hidden = !active;
            custom?.querySelectorAll('input').forEach(input => { input.disabled = !active; });
            return active;
        };
        period?.addEventListener('change', () => {
            if (!syncCustom()) filterForm.requestSubmit();
        });
        bus?.addEventListener('change', () => filterForm.requestSubmit());
        filterForm.querySelectorAll('[data-ma-auto]').forEach(select => {
            select.addEventListener('change', () => filterForm.requestSubmit());
        });
        syncCustom();
    }
    root.querySelector('.ma-filters')?.addEventListener('submit', async event => {
        event.preventDefault();
        requestController?.abort();
        requestController = new AbortController();
        const controller = requestController;
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(new FormData(event.target)).toString();
        const loading = root.querySelector('.ma-loading');
        root.setAttribute('aria-busy','true');
        if (loading) loading.hidden = false;
        try {
            const response = await fetch(url,{signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'}});
            if (!response.ok) throw new Error(response.status === 422 ? 'Choose valid dates, a range of at most one year, and an authorized bus.' : 'Unable to load Analytics. Refresh to check your current access.');
            const documentResponse = new DOMParser().parseFromString(await response.text(),'text/html');
            const replacement = documentResponse.querySelector('.ma-content');
            const validationError = replacement?.querySelector('[role="alert"]:not([hidden])');
            if (validationError?.textContent.trim()) throw new Error(validationError.textContent.trim());
            if (!replacement) throw new Error('Analytics access changed. Refresh the page to continue.');
            if (controller.signal.aborted || !root.isConnected) return;
            root.replaceWith(replacement);
            window.history.replaceState(null,'',url);
            initializeMaintenanceAnalytics();
        } catch (error) {
            if (error.name === 'AbortError') return;
            const alert = root.querySelector('.ma-filter-error');
            if (alert) { alert.textContent = error.message; alert.hidden = false; }
        } finally {
            root.removeAttribute('aria-busy');
            if (loading) loading.hidden = true;
        }
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',initializeMaintenanceAnalytics,{once:true});
else initializeMaintenanceAnalytics();
document.addEventListener('gct:navigation-complete',initializeMaintenanceAnalytics);
document.addEventListener('gct:navigation-before',()=>{requestController?.abort();charts.forEach(chart=>chart.destroy());charts=[];});
new MutationObserver(()=>{if(lastTheme !== document.documentElement.dataset.gctTheme) initializeMaintenanceAnalytics();})
    .observe(document.documentElement,{attributes:true,attributeFilter:['data-gct-theme']});
