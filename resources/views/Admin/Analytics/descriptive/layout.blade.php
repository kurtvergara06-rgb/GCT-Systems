@php
    // Objective 6.1 dashboard. All connected metrics use existing recorded data;
    // other sections are deliberately labeled, never populated with demo values.
    $activeSummary = request('view') === 'administrative' ? 'administrative' : 'operational';
    $periodOptions = [
        'this-month' => 'This Month',
        'last-30-days' => 'Last 30 Days',
        'last-3-months' => 'Last 3 Months',
        'this-year' => 'This Year',
    ];
    $url = route('analytics.descriptive', [], false);
    $currentParams = request()->only(['period', 'bus']);
    $activePct = $totalBuses ? round(($activeBuses / $totalBuses) * 100, 1) : null;
    $maintenancePct = $totalBuses ? round(($underMaintenance / $totalBuses) * 100, 1) : 0;
    $inactivePct = $totalBuses ? round(($inactiveBuses / $totalBuses) * 100, 1) : 0;
    $maxTrend = max(1, (int) $trend->max('count'));
@endphp
<x-layout.app title="FROMS - Descriptive Analytics" :assets="['resources/css/Admin/Analytics/design-system.css', 'resources/css/Admin/Analytics/overview/analytics-stage-hub.css']" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main descriptive-rebuild">
            <x-layout.topbar title="Descriptive Analytics" subtitle="Summaries of recorded operational and administrative data for fleet monitoring and analysis." />
            <div class="descriptive-controls">
                <nav class="descriptive-switch" aria-label="Descriptive analytics summaries">
                    <a href="{{ $url.'?'.http_build_query(array_merge($currentParams, ['view'=>'operational'])) }}" class="{{ $activeSummary === 'operational' ? 'selected' : '' }}" @if($activeSummary === 'operational') aria-current="page" @endif><i class="fa-solid fa-bus"></i> Operational Summary</a>
                    <a href="{{ $url.'?'.http_build_query(array_merge($currentParams, ['view'=>'administrative'])) }}" class="{{ $activeSummary === 'administrative' ? 'selected' : '' }}" @if($activeSummary === 'administrative') aria-current="page" @endif><i class="fa-solid fa-users"></i> Administrative Summary</a>
                </nav>
                <form method="GET" action="{{ $url }}" class="descriptive-filters">
                    <input type="hidden" name="view" value="{{ $activeSummary }}">
                    <label>Period <select name="period" onchange="this.form.requestSubmit()">@foreach($periodOptions as $value => $label)<option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>@endforeach</select></label>
                    @if($activeSummary === 'operational')
                    <label>Bus <select name="bus" onchange="this.form.requestSubmit()"><option value="all">All Buses</option>@foreach($buses as $bus)<option value="{{ $bus->bus_no }}" @selected($selectedBus === strtoupper($bus->bus_no))>{{ $bus->bus_no }}</option>@endforeach</select></label>
                    @endif
                </form>
            </div>
            @if($activeSummary === 'operational')
                <div class="descriptive-kpis">
                    <section class="descriptive-kpi"><div class="d-kpi-head"><i class="fa-solid fa-route"></i><strong>Total Trips</strong></div><div class="d-kpi-value">—</div><span class="d-help">Scheduled / completed / cancelled</span><small>Not connected to scheduling records yet</small></section>
                    <section class="descriptive-kpi"><div class="d-kpi-head"><i class="fa-solid fa-people-arrows"></i><strong>Total Assignments</strong></div><div class="d-kpi-value">—</div><span class="d-help">Active / completed assignments</span><small>Not connected to assignment records yet</small></section>
                    <section class="descriptive-kpi"><div class="d-kpi-head"><i class="fa-solid fa-bus"></i><strong>Total Buses</strong></div><div class="d-kpi-value">{{ number_format($totalBuses) }}</div><span class="d-help">{{ number_format($activeBuses) }} Active · {{ number_format($underMaintenance) }} Under Maintenance</span><small>Current Bus Master List status</small></section>
                    <section class="descriptive-kpi"><div class="d-kpi-head"><i class="fa-solid fa-location-dot"></i><strong>GPS Trips Processed</strong></div><div class="d-kpi-value">{{ number_format($tripCount) }}</div><span class="d-help">{{ number_format($totalDistance, 1) }} km valid travel</span><small>Confirmed processed GPS trips · selected period</small></section>
                    <section class="descriptive-kpi"><div class="d-kpi-head"><i class="fa-solid fa-triangle-exclamation"></i><strong>Total Incidents</strong></div><div class="d-kpi-value">—</div><span class="d-help">Open / resolved / replacement used</span><small>Not connected to incident records yet</small></section>
                </div>
                <div class="descriptive-visuals">
                    <section class="d-panel d-wide">
                        <div class="d-panel-header"><div><h3>Processed GPS Trip Activity</h3><p>Valid GPS trip records per period bucket. Not scheduled-trip completion.</p></div><span class="d-tag">{{ $periodLabel }}</span></div>
                        @if($trend->isNotEmpty() && $tripCount > 0)
                        <div class="d-trend" role="img" aria-label="Processed GPS trip volume by period">
                            @foreach($trend as $point)
                                <div class="d-trend-col" title="{{ $point->label }}: {{ (int) $point->count }} processed trips"><span>{{ (int) $point->count }}</span><div class="d-trend-track"><div class="d-trend-fill" style="height: {{ round(100 * (int) $point->count / $maxTrend, 1) }}%"></div></div><small>{{ $point->label }}</small></div>
                            @endforeach
                        </div>
                        @else <div class="d-empty">No confirmed GPS trips match the selected period.</div> @endif
                    </section>
                    <section class="d-panel">
                        <div class="d-panel-header"><div><h3>Fleet Availability</h3><p>Current Bus Master List status</p></div></div>
                        @if($totalBuses)
                        <div class="d-fleet">
                            <div class="d-donut" style="--active:{{ $activePct }}%;--maint:{{ $activePct + $maintenancePct }}%"><div><strong>{{ $activePct }}%</strong><small>Active</small></div></div>
                            <div class="d-fleet-key"><span><i class="d-dot d-green"></i> Active <b>{{ $activeBuses }}</b></span><span><i class="d-dot d-orange"></i> Under Maintenance <b>{{ $underMaintenance }}</b></span><span><i class="d-dot d-gray"></i> Inactive <b>{{ $inactiveBuses }}</b></span><span>Total Buses <b>{{ $totalBuses }}</b></span></div>
                        </div>
                        @else <div class="d-empty">No buses recorded.</div> @endif
                    </section>
                    <section class="d-panel"><div class="d-panel-header"><div><h3>Trip Status Trend</h3><p>Scheduled, completed and cancelled trips by date</p></div></div><div class="d-empty">Not connected to scheduled trip records yet.</div></section>
                    <section class="d-panel"><div class="d-panel-header"><div><h3>Assignment Status</h3><p>Driver and bus assignment activity</p></div></div><div class="d-empty">Not connected to assignment records yet.</div></section>
                    <section class="d-panel"><div class="d-panel-header"><div><h3>Incidents by Category</h3><p>Documented incident types and replacement bus usage</p></div></div><div class="d-empty">Not connected to incident reports yet.</div></section>
                    <section class="d-panel d-wide"><div class="d-panel-header"><div><h3>Recent Trips</h3><p>Latest actual scheduled trips and DDR confirmations</p></div></div><div class="d-empty">Not connected to Operations trip records yet.</div></section>
                </div>
            @else
                <div class="descriptive-kpis d-admin-kpis">
                    @foreach(['Active Users' => 'Current active accounts', 'Inactive Users' => 'Current inactive accounts', 'Total Notifications' => 'Recorded notifications for selected period', 'Unread Notifications' => 'Unread notifications for authorized users'] as $metric => $caption)
                        <section class="descriptive-kpi"><div class="d-kpi-head"><i class="fa-solid {{ str_contains($metric, 'Notification') ? 'fa-bell' : 'fa-users' }}"></i><strong>{{ $metric }}</strong></div><div class="d-kpi-value">—</div><span class="d-help">{{ $caption }}</span><small>Not connected to administrative records yet</small></section>
                    @endforeach
                </div>
                <div class="descriptive-visuals d-admin-grid">
                    <section class="d-panel"><div class="d-panel-header"><div><h3>User Activity Trend</h3><p>Logged user actions over the selected period</p></div></div><div class="d-empty">Not connected to audit logs yet.</div></section>
                    <section class="d-panel"><div class="d-panel-header"><div><h3>Activity by Module</h3><p>Recorded actions grouped by department</p></div></div><div class="d-empty">Not connected to audit logs yet.</div></section>
                    <section class="d-panel"><div class="d-panel-header"><div><h3>Recent System Activities</h3><p>Latest actions from the audit trail</p></div></div><div class="d-empty">Not connected to audit logs yet.</div></section>
                    <section class="d-panel"><div class="d-panel-header"><div><h3>Notifications by Category</h3><p>Recorded system notification breakdown</p></div></div><div class="d-empty">Not connected to notification records yet.</div></section>
                </div>
            @endif
        </main>
    </div>
    <style>
    .descriptive-rebuild{padding-bottom:40px}.descriptive-controls{display:flex;justify-content:space-between;align-items:end;gap:14px;flex-wrap:wrap;margin:18px 0}.descriptive-switch{display:flex;flex-wrap:wrap;gap:8px}.descriptive-switch a{display:inline-flex;gap:9px;align-items:center;text-decoration:none;padding:11px 16px;border:1px solid #dce5f2;border-radius:10px;background:#fff;color:#173253;font-size:13px;font-weight:700}.descriptive-switch a.selected{background:#1262dc;color:white;border-color:#1262dc}.descriptive-filters{display:flex;gap:10px;flex-wrap:wrap}.descriptive-filters label{font-size:11px;color:#546a85;font-weight:700;display:grid;gap:5px}.descriptive-filters select{min-width:150px;border:1px solid #d4dfed;border-radius:9px;background:white;padding:10px;color:#18304e}.descriptive-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.descriptive-kpis.d-admin-kpis{grid-template-columns:repeat(4,minmax(0,1fr))}.descriptive-kpi,.d-panel{border:1px solid #dce6f4;background:#fff;border-radius:13px;box-shadow:0 3px 12px rgba(26,63,111,.04)}.descriptive-kpi{min-width:0;padding:17px 15px}.d-kpi-head{display:flex;align-items:center;gap:9px;font-size:12px;color:#183656}.d-kpi-head i{color:#1469df;font-size:18px}.d-kpi-value{font-size:27px;font-weight:800;color:#142e50;margin:10px 0 6px}.d-help{font-size:11px;display:block;color:#395477}.descriptive-kpi small{display:block;font-size:10px;color:#76889e;margin-top:8px}.descriptive-visuals{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;margin-top:14px}.d-panel{padding:17px;min-width:0;min-height:210px}.d-panel.d-wide{grid-column:span 2}.d-panel-header{display:flex;justify-content:space-between;gap:10px;align-items:start}.d-panel h3{font-size:14px;font-weight:800;color:#162f52;margin:0}.d-panel p{font-size:11px;color:#627892;margin:5px 0 0}.d-tag{background:#eff5ff;color:#315887;border-radius:6px;padding:4px 7px;font-size:10px}.d-empty{min-height:140px;display:flex;align-items:center;justify-content:center;color:#7a8ca5;font-size:12px;text-align:center;padding:20px}.d-trend{display:flex;align-items:end;gap:7px;height:230px;overflow-x:auto;padding:15px 2px 0}.d-trend-col{min-width:34px;flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;font-size:9px;color:#49617b}.d-trend-col span{height:13px}.d-trend-track{height:calc(100% - 42px);width:100%;display:flex;align-items:end;background:linear-gradient(0deg,#f2f6ff,#fff);border-radius:5px}.d-trend-fill{background:#1769e8;width:100%;min-height:2px;border-radius:4px 4px 0 0}.d-trend-col small{white-space:nowrap;font-size:9px}.d-fleet{display:flex;align-items:center;justify-content:center;gap:26px;flex-wrap:wrap;padding-top:25px}.d-donut{height:158px;width:158px;border-radius:50%;background:conic-gradient(#1db76c 0 var(--active),#f5a623 var(--active) var(--maint),#a9b8cc var(--maint) 100%);display:grid;place-items:center}.d-donut>div{height:110px;width:110px;border-radius:50%;background:white;display:flex;flex-direction:column;align-items:center;justify-content:center}.d-donut strong{font-size:24px;color:#142e50}.d-donut small{font-size:10px;color:#60758d}.d-fleet-key{flex:1;min-width:160px;display:grid;gap:9px}.d-fleet-key span{display:flex;align-items:center;gap:7px;font-size:11px;color:#354e6d}.d-fleet-key b{margin-left:auto;color:#142e50}.d-dot{width:8px;height:8px;border-radius:50%;display:inline-block}.d-green{background:#1db76c}.d-orange{background:#f5a623}.d-gray{background:#a9b8cc}@media(max-width:1180px){.descriptive-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.descriptive-kpis,.descriptive-kpis.d-admin-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.descriptive-visuals{grid-template-columns:minmax(0,1fr)}.d-panel.d-wide{grid-column:auto}.d-fleet{gap:14px}}@media(max-width:440px){.descriptive-kpis,.descriptive-kpis.d-admin-kpis{grid-template-columns:minmax(0,1fr)}.descriptive-filters,.descriptive-filters label,.descriptive-filters select{width:100%}}
    </style>
</x-layout.app>
