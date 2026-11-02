@php
    // Objective 6.4: advisory interface. Never display demonstration playbooks
    // as actual schedules, quantities, or approved procurement actions.
    $prescriptiveUrl = route('analytics.stage', ['stage' => 'prescriptive'], false);
    $activeDomain = request('domain') === 'inventory' ? 'inventory' : 'fleet-trip';
    $isRestocking = $activeDomain === 'inventory';
    $title = $isRestocking ? 'Prescriptive Analytics — Restocking' : 'Prescriptive Analytics — Operations';
    $subtitle = $isRestocking
        ? 'Review inventory replenishment suggestions backed by issuance history, stock and confirmed incoming deliveries.'
        : 'Review potential scheduling and eligible driver and bus assignment recommendations.';
    $filterParams = request()->only(['period', 'bus']);
    $periodOptions = ['this-month'=>'This Month','this-week'=>'This Week','last-30-days'=>'Last 30 Days','last-90-days'=>'Last 90 Days','last-12-months'=>'Last 12 Months'];
    $inventoryTotal = (int) ($inventory->total ?? 0);
    $inventoryLow = (int) ($inventory->low ?? 0);
    $inventoryCritical = (int) ($inventory->critical ?? 0);
@endphp

<x-layout.app title="FROMS - {{ $title }}" :assets="['resources/css/Admin/Analytics/design-system.css','resources/css/Admin/Analytics/overview/analytics-stage-hub.css']" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main prescriptive-reference-page">
            <x-layout.topbar :title="$title" :subtitle="$subtitle" />
            <div class="pr-controls">
                <nav class="pr-tabs" aria-label="Prescriptive analytics categories">
                    <a href="{{ $prescriptiveUrl.'?'.http_build_query(array_merge($filterParams,['domain'=>'fleet-trip'])) }}" class="{{ !$isRestocking ? 'current' : '' }}" @if(!$isRestocking) aria-current="page" @endif><i class="fa-solid fa-bus"></i> Operations Recommendations</a>
                    <a href="{{ $prescriptiveUrl.'?'.http_build_query(array_merge($filterParams,['domain'=>'inventory'])) }}" class="{{ $isRestocking ? 'current' : '' }}" @if($isRestocking) aria-current="page" @endif><i class="fa-solid fa-boxes-stacked"></i> Restocking Recommendations</a>
                </nav>
                <form method="GET" action="{{ $prescriptiveUrl }}" class="pr-filters">
                    <input type="hidden" name="domain" value="{{ $activeDomain }}">
                    <label>Planning Period<select name="period" onchange="this.form.requestSubmit()">@foreach($periodOptions as $value=>$name)<option value="{{ $value }}" @selected($period === $value)>{{ $name }}</option>@endforeach</select></label>
                    @if(!$isRestocking)
                    <label>Bus<select name="bus" onchange="this.form.requestSubmit()"><option value="all">All Buses</option>@foreach($busOptions as $busNo)<option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>@endforeach</select></label>
                    @endif
                </form>
            </div>
            <div class="pr-advisory"><i class="fa-solid fa-shield-halved"></i><span><strong>Decision support only.</strong> Suggestions require verified source data and approval by the responsible department. Nothing on this page dispatches vehicles or creates PRs/POs automatically.</span></div>
            <details class="pr-model-readiness"><summary><i class="fa-solid fa-circle-info"></i> Model readiness and genuine-data provenance</summary><x-analytics.model-status /></details>

            @if(!$isRestocking)
                <section class="pr-kpis">
                    @foreach([
                        ['Trips Needing Assignment','fa-calendar-days','Requires scheduled-trip eligibility checks'],
                        ['Eligible Drivers','fa-users','Attendance, availability, authorization and conflicts'],
                        ['Available Buses','fa-bus','Maintenance, dispatch and route restrictions'],
                        ['Scheduling Conflicts','fa-triangle-exclamation','Overlaps and required route gaps'],
                    ] as [$name,$icon,$description])
                        <article class="pr-card pr-kpi"><div class="pr-kpi-title"><i class="fa-solid {{ $icon }}"></i><strong>{{ $name }}</strong></div><div class="pr-num">—</div><small>{{ $description }}</small><span class="pr-placeholder">Eligibility summary not yet connected</span></article>
                    @endforeach
                </section>
                <section class="pr-body-grid">
                    <article class="pr-card pr-large"><h3><i class="fa-solid fa-calendar-check"></i> Schedule Recommendations</h3><p>Recommended departures and arrival estimates must use comparable completed trips and scheduling rules.</p>
                        <div class="pr-scroll"><table class="pr-table"><thead><tr><th>Route</th><th>Suggested Departure</th><th>Estimated Arrival</th><th>Historical Duration</th><th>Reason</th></tr></thead><tbody><tr><td colspan="5" class="pr-empty">No verified schedule recommendations connected to this view yet.</td></tr></tbody></table></div>
                    </article>
                    <div class="pr-side">
                        <article class="pr-card"><h3><i class="fa-solid fa-id-card"></i> Driver Assignment Recommendations</h3><p>Rank drivers only after attendance, duty hours, bus authorization and trip conflict checks.</p><div class="pr-scroll"><table class="pr-table"><thead><tr><th>Driver</th><th>Attendance</th><th>Eligibility</th><th>Reason</th></tr></thead><tbody><tr><td colspan="4" class="pr-empty">Verified driver recommendations not yet connected.</td></tr></tbody></table></div></article>
                        <article class="pr-card"><h3><i class="fa-solid fa-bus-simple"></i> Bus Assignment Recommendations</h3><p>Recommend only fleet units cleared of active maintenance and dispatch restrictions.</p><div class="pr-scroll"><table class="pr-table"><thead><tr><th>Bus</th><th>Operational Status</th><th>Eligibility</th><th>Reason</th></tr></thead><tbody><tr><td colspan="4" class="pr-empty">Verified bus recommendations not yet connected.</td></tr></tbody></table></div></article>
                    </div>
                </section>
                <section class="pr-card pr-guidance"><h3><i class="fa-solid fa-user-shield"></i> Operations Approval &amp; Safeguards</h3><p>These are proposed choices only. Operations retains dispatch authority. Maintain the 15-minute same-route gap, conflict checks, attendance requirements, driver authorization and bus maintenance restrictions. No automatic assignment is performed here.</p></section>
            @else
                <section class="pr-kpis">
                    <article class="pr-card pr-kpi"><div class="pr-kpi-title"><i class="fa-solid fa-box-open"></i><strong>Inventory Items</strong></div><div class="pr-num">{{ number_format($inventoryTotal) }}</div><small>Current Warehouse inventory records</small></article>
                    <article class="pr-card pr-kpi"><div class="pr-kpi-title"><i class="fa-solid fa-triangle-exclamation"></i><strong>Low Stock</strong></div><div class="pr-num">{{ number_format($inventoryLow) }}</div><small>Current recorded on-hand and reorder thresholds</small></article>
                    <article class="pr-card pr-kpi"><div class="pr-kpi-title"><i class="fa-solid fa-boxes-stacked"></i><strong>Out of Stock</strong></div><div class="pr-num">{{ number_format($inventoryCritical) }}</div><small>Current recorded on-hand stock</small></article>
                    <article class="pr-card pr-kpi"><div class="pr-kpi-title"><i class="fa-solid fa-cart-shopping"></i><strong>Recommended Restock Qty</strong></div><div class="pr-num">—</div><small>Needs verified issuance forecast, reserves and incoming supply</small><span class="pr-placeholder">Not connected</span></article>
                </section>
                <section class="pr-three">
                    <article class="pr-card"><h3><i class="fa-solid fa-chart-column"></i> Recommended Quantity by Category</h3><p>Sum approved candidate quantities by spare-parts category.</p><div class="pr-empty">Recommendation quantities not yet verified.</div></article>
                    <article class="pr-card"><h3><i class="fa-solid fa-chart-line"></i> Stock Coverage Projection</h3><p>Compare current inventory coverage with suggested restocking.</p><div class="pr-empty">Insufficient verified forecast and incoming delivery data.</div></article>
                    <article class="pr-card"><h3><i class="fa-solid fa-chart-pie"></i> Priority Distribution</h3><p>Suggested priorities based on forecast shortage risk.</p><div class="pr-empty">No validated replenishment priority results connected.</div></article>
                </section>
                <section class="pr-body-grid">
                    <article class="pr-card pr-large"><h3><i class="fa-solid fa-list-check"></i> Restocking Recommendations</h3><p>Suggested quantities must account for available stock, JO reservations, actual issuances and confirmed incoming deliveries.</p><div class="pr-scroll"><table class="pr-table"><thead><tr><th>Part Code</th><th>Part</th><th>Available</th><th>Forecast Demand</th><th>Incoming</th><th>Suggested Qty</th><th>Priority</th></tr></thead><tbody><tr><td colspan="7" class="pr-empty">No validated per-item replenishment recommendations connected yet.</td></tr></tbody></table></div></article>
                    <article class="pr-card"><h3><i class="fa-solid fa-lightbulb"></i> Key Recommendations</h3><div class="pr-empty">Suggested actions will appear only after demand and delivery evidence is verified.</div></article>
                </section>
                <section class="pr-card pr-guidance"><h3><i class="fa-solid fa-calendar-days"></i> Restocking Timeline &amp; Action Plan</h3><p>Planning dates require recorded supplier lead times and confirmed forecast inputs. Warehouse Head must review suggested quantities before creating a PR. Purchasing approvals and receiving remain unchanged.</p><div class="pr-empty">No validated restocking timeline available.</div></section>
            @endif
        </main>
    </div>
    <style>
    .prescriptive-reference-page{padding-bottom:36px}.pr-controls{display:flex;justify-content:space-between;align-items:end;gap:14px;flex-wrap:wrap;margin:18px 0}.pr-tabs{display:flex;gap:8px;flex-wrap:wrap}.pr-tabs a{display:inline-flex;align-items:center;gap:9px;padding:11px 16px;color:#1a3b62;background:#fff;border:1px solid #dae5f4;border-radius:9px;text-decoration:none;font-size:12px;font-weight:700}.pr-tabs a.current{background:#1264df;border-color:#1264df;color:#fff}.pr-filters{display:flex;gap:10px;flex-wrap:wrap}.pr-filters label{display:grid;gap:5px;font-size:11px;font-weight:700;color:#647894}.pr-filters select{min-width:145px;background:white;border:1px solid #d5e1ef;border-radius:9px;color:#203d5e;padding:10px}.pr-advisory{display:flex;gap:10px;align-items:start;background:#f0f6ff;border:1px solid #cadcf5;color:#234a77;border-radius:9px;padding:11px 13px;font-size:11px;margin-bottom:12px}.pr-model-readiness{background:white;border:1px solid #dbe5f3;border-radius:9px;padding:10px 14px;margin-bottom:14px}.pr-model-readiness summary{font-size:12px;color:#1c497a;cursor:pointer;font-weight:700}.pr-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:11px;margin-bottom:13px}.pr-card{min-width:0;background:white;border:1px solid #dbe5f3;border-radius:12px;box-shadow:0 4px 12px rgba(18,48,88,.04);padding:16px}.pr-kpi{background:linear-gradient(125deg,#f5f9ff,white)}.pr-kpi-title{font-size:12px;color:#223e60;display:flex;align-items:center;gap:9px}.pr-kpi-title i,.pr-card h3 i{color:#1669e7;font-size:17px}.pr-num{font-size:27px;font-weight:800;color:#153455;margin:10px 0 5px}.pr-kpi small,.pr-placeholder{display:block;font-size:10px;color:#647c95}.pr-placeholder{margin-top:8px;color:#8293a7}.pr-card h3{font-size:14px;color:#19385c;margin:0 0 6px}.pr-card p{font-size:11px;color:#647891;line-height:1.5;margin:0 0 10px}.pr-body-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:12px;margin-bottom:12px}.pr-side{display:grid;gap:12px}.pr-scroll{overflow-x:auto}.pr-table{width:100%;border-collapse:collapse;font-size:11px}.pr-table th{padding:10px 8px;text-align:left;white-space:nowrap;color:#3e5a7a;background:#f0f5fc}.pr-table td{padding:10px 8px;border-bottom:1px solid #e8eef7}.pr-empty{padding:27px 12px!important;text-align:center;color:#8091a7;font-size:11px;min-height:65px}.pr-guidance{margin-top:12px}.pr-three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:12px}@media(max-width:1000px){.pr-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.pr-three{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:750px){.pr-body-grid,.pr-three{grid-template-columns:minmax(0,1fr)}}@media(max-width:520px){.pr-kpis{grid-template-columns:minmax(0,1fr)}.pr-filters,.pr-filters label,.pr-filters select{width:100%}}
    </style>
</x-layout.app>
