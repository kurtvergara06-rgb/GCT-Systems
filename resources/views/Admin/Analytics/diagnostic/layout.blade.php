@php
    // Objective 6.2: reuse recorded fuel diagnostics while incident analysis
    // awaits validated incident/replacement aggregation. Never render demo counts.
    $activeAnalysis = request('domain') === 'fuel' ? 'fuel' : 'fleet-trip';
    $diagnosticUrl = route('analytics.stage', ['stage' => 'diagnostic'], false);
    $normalizedSelectedBus = strtoupper(trim((string) $selectedBus));
    $fuelData = $diagnostic->fuel;
    $fuelTrend = collect($fuelData->trend ?? []);
    $fuelReviews = collect($fuelData->review_units ?? []);
    $fuelUnits = collect($fuelData->bus_summaries ?? []);
    $totalFuel = (float) ($fuelData->total_fuel ?? 0);
    $fleetEfficiency = (float) ($fuelData->fleet_average ?? 0);
    $currentFilters = request()->only(['period','bus']);
@endphp
<x-layout.app title="FROMS - Diagnostic Analytics" :assets="['resources/css/Admin/Analytics/overview/analytics-stage-hub.css', 'resources/css/Admin/Analytics/design-system.css']" :partial-navigation="false">
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main diagnostic-reference-page">
            <x-layout.topbar title="Diagnostic Analytics" subtitle="Review recorded incidents, replacement activity, and fuel consumption inconsistencies." />
            <section class="dr-toolbar">
                <nav class="dr-tabs" aria-label="Diagnostic analysis type">
                    <a class="{{ $activeAnalysis === 'fleet-trip' ? 'is-selected' : '' }}" href="{{ $diagnosticUrl.'?'.http_build_query(array_merge($currentFilters,['domain'=>'fleet-trip'])) }}" @if($activeAnalysis === 'fleet-trip') aria-current="page" @endif><i class="fa-solid fa-bus"></i> Incident &amp; Replacement Analysis</a>
                    <a class="{{ $activeAnalysis === 'fuel' ? 'is-selected' : '' }}" href="{{ $diagnosticUrl.'?'.http_build_query(array_merge($currentFilters,['domain'=>'fuel'])) }}" @if($activeAnalysis === 'fuel') aria-current="page" @endif><i class="fa-solid fa-gas-pump"></i> Fuel &amp; Consumption Analysis</a>
                </nav>
                <form class="dr-filters" method="GET" action="{{ $diagnosticUrl }}">
                    <input type="hidden" name="domain" value="{{ $activeAnalysis }}">
                    <label>Period<select name="period" onchange="this.form.requestSubmit()">
                        <option value="this-month" @selected($period === 'this-month')>This Month</option>
                        <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                        <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                        <option value="this-year" @selected($period === 'this-year')>This Year</option>
                    </select></label>
                    <label>Bus<select name="bus" onchange="this.form.requestSubmit()"><option value="all">All Buses</option>@foreach($busOptions as $busNo)<option value="{{ $busNo }}" @selected($normalizedSelectedBus === strtoupper((string)$busNo))>{{ $busNo }}</option>@endforeach</select></label>
                </form>
            </section>

            @if($activeAnalysis === 'fleet-trip')
                <section class="dr-kpi-row">
                    @foreach([
                        ['Total Incidents','fa-triangle-exclamation','Total recorded incidents for the period'],
                        ['Bus Replacements','fa-bus','Confirmed replacement assignments'],
                        ['Average Downtime','fa-clock','Verified incident-to-return intervals'],
                        ['Most Common Cause','fa-gears','Most frequently documented incident cause'],
                    ] as [$title,$icon,$description])
                        <article class="dr-card dr-kpi"><div class="dr-kpi-name"><i class="fa-solid {{ $icon }}"></i><strong>{{ $title }}</strong></div><div class="dr-value">—</div><small>{{ $description }}</small><span class="dr-await">Not connected to incident records yet</span></article>
                    @endforeach
                </section>
                <section class="dr-three">
                    <article class="dr-card"><h3><i class="fa-solid fa-chart-line"></i> Incidents Trend</h3><p>Documented incident frequency over the selected period</p><div class="dr-no-data">Incident timeline not connected yet.</div></article>
                    <article class="dr-card"><h3><i class="fa-solid fa-chart-pie"></i> Incidents by Cause</h3><p>Categories from recorded incident causes</p><div class="dr-no-data">Cause breakdown not connected yet.</div></article>
                    <article class="dr-card"><h3><i class="fa-solid fa-arrow-right-arrow-left"></i> Replacement Outcome</h3><p>Original buses versus documented replacements</p><div class="dr-no-data">Replacement links not connected yet.</div></article>
                </section>
                <section class="dr-card dr-evidence"><h3><i class="fa-solid fa-file-lines"></i> Incident Evidence</h3><p>Incident records, original buses, replacements, and linked maintenance referrals will appear here after source validation.</p><div class="dr-no-data">No incident-level analysis is connected in this layout yet. This is not a claim that no incidents occurred.</div></section>
            @else
                <section class="dr-fuel-heading"><h2><i class="fa-solid fa-gas-pump"></i> Fuel Consumption &amp; Inconsistency Analysis</h2><p>Recorded fleet fuel usage and review signals. These are advisory, not proof of an equipment fault.</p></section>
                <section class="dr-fuel-grid">
                    <div class="dr-stacked">
                        <article class="dr-card dr-kpi"><div class="dr-kpi-name"><i class="fa-solid fa-droplet"></i><strong>Total Fuel Consumption</strong></div><div class="dr-value">{{ $totalFuel > 0 ? number_format($totalFuel,1).' L' : '—' }}</div><small>{{ $totalFuel > 0 ? 'Recorded fuel volume for the selected filters' : 'No valid fuel volume recorded for this selection' }}</small></article>
                        <article class="dr-card dr-kpi"><div class="dr-kpi-name"><i class="fa-solid fa-gauge-high"></i><strong>Average Fuel Efficiency</strong></div><div class="dr-value">{{ $totalFuel > 0 && $fleetEfficiency > 0 ? number_format($fleetEfficiency,2).' km/L' : '—' }}</div><small>{{ $fleetEfficiency > 0 ? 'Derived from recorded fuel and distance' : 'Insufficient valid distance and fuel data' }}</small></article>
                        <article class="dr-card dr-kpi"><div class="dr-kpi-name"><i class="fa-solid fa-triangle-exclamation"></i><strong>Buses Requiring Review</strong></div><div class="dr-value">{{ number_format($fuelReviews->count()) }}</div><small>Current fuel review signals under existing rules</small></article>
                    </div>
                    <article class="dr-card"><h3><i class="fa-solid fa-chart-column"></i> Fuel Consumption Trend</h3><p>Recorded volume and efficiency by period</p>
                        @if($fuelTrend->isEmpty())<div class="dr-no-data">No fuel trend data available for the selected filters.</div>
                        @else
                        <div class="dr-fuel-trend" aria-label="Fuel trend summary">
                            @foreach($fuelTrend->take(12) as $point)
                                @php $fuelValue = (float)($point->fuel_liters ?? $point->liters ?? $point->fuel ?? 0); @endphp
                                <div><span>{{ number_format($fuelValue,1) }} L</span><div class="dr-trend-track"><div style="height:{{ min(100,max(0,$totalFuel > 0 ? 100*$fuelValue/$totalFuel : 0)) }}%"></div></div><small>{{ $point->label ?? $point->month ?? 'Period' }}</small></div>
                            @endforeach
                        </div>
                        @endif
                    </article>
                    <article class="dr-card"><h3><i class="fa-solid fa-bus"></i> Buses with Fuel Review Signals</h3><p>Recorded observations requiring further investigation</p>
                        @if($fuelReviews->isEmpty())<div class="dr-no-data">No units flagged for review under current recorded-data rules.</div>
                        @else
                        <div class="dr-table-wrap"><table class="dr-table"><thead><tr><th>Bus</th><th>Fuel (L)</th><th>Efficiency</th><th>Status</th></tr></thead><tbody>
                        @foreach($fuelReviews->take(8) as $unit)
                            <tr><td><strong>{{ $unit->bus_no }}</strong></td><td>{{ number_format((float)($unit->fuel_liters ?? 0),1) }}</td><td>{{ (float)($unit->km_per_liter ?? 0) > 0 ? number_format((float)$unit->km_per_liter,2).' km/L' : 'No data' }}</td><td>{{ $unit->status ?? 'Review' }}</td></tr>
                        @endforeach
                        </tbody></table></div>
                        @endif
                    </article>
                </section>
                <section class="dr-card dr-evidence"><h3><i class="fa-solid fa-circle-info"></i> Historical Baseline Requirement</h3><p>A fuel anomaly must not be treated as confirmed solely because it is below the fleet average. Objective 6.2.2 requires a vehicle-specific historical baseline with at least three prior valid measurements and a decrease of at least 20%.</p></section>
            @endif
        </main>
    </div>
    <style>
    .diagnostic-reference-page{padding-bottom:38px}.dr-toolbar{display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap;margin:17px 0}.dr-tabs{display:flex;gap:8px;flex-wrap:wrap}.dr-tabs a{display:inline-flex;gap:9px;align-items:center;text-decoration:none;color:#173455;border:1px solid #dbe5f4;border-radius:9px;background:white;padding:12px 16px;font-size:12px;font-weight:700}.dr-tabs a.is-selected{background:#1265e0;color:white;border-color:#1265e0}.dr-filters{display:flex;flex-wrap:wrap;gap:10px}.dr-filters label{display:grid;gap:4px;font-size:11px;font-weight:700;color:#60728a}.dr-filters select{min-width:145px;border:1px solid #d6e2f1;border-radius:9px;padding:10px;background:white;color:#203a5b}.dr-kpi-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.dr-card{min-width:0;border:1px solid #dce6f5;background:#fff;border-radius:13px;padding:17px;box-shadow:0 4px 12px rgba(21,54,96,.045)}.dr-kpi{background:linear-gradient(135deg,#fff,#f4f8ff)}.dr-kpi-name{display:flex;align-items:center;gap:9px;font-size:12px;color:#243d5b}.dr-kpi-name i{color:#1569e8;font-size:18px}.dr-value{font-size:28px;line-height:1.3;font-weight:800;color:#152f54;margin:12px 0 5px}.dr-kpi small{display:block;font-size:11px;color:#6a7d94}.dr-await{display:block;font-size:10px;color:#8494a8;margin-top:10px}.dr-three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:12px 0}.dr-card h3{font-size:14px;color:#1b3456;margin:0 0 5px;font-weight:800}.dr-card h3 i,.dr-fuel-heading i{color:#1569e8;margin-right:7px}.dr-card p,.dr-fuel-heading p{font-size:11px;color:#697f98;margin:0 0 8px}.dr-no-data{min-height:180px;padding:18px;display:flex;align-items:center;justify-content:center;text-align:center;color:#8495aa;font-size:12px}.dr-evidence{margin-top:12px}.dr-evidence .dr-no-data{min-height:65px}.dr-fuel-heading{margin:20px 0 12px}.dr-fuel-heading h2{font-size:16px;margin:0 0 6px;color:#183557}.dr-fuel-grid{display:grid;grid-template-columns:minmax(180px,.8fr) minmax(250px,1.4fr) minmax(250px,1.3fr);gap:12px;align-items:stretch}.dr-stacked{display:grid;gap:10px}.dr-table-wrap{overflow-x:auto}.dr-table{width:100%;border-collapse:collapse;font-size:11px}.dr-table th{text-align:left;background:#f0f5fc;color:#4a617e;padding:9px}.dr-table td{border-bottom:1px solid #edf1f7;padding:9px;color:#263f5b}.dr-fuel-trend{display:flex;align-items:end;gap:6px;height:240px;overflow-x:auto;padding-top:18px}.dr-fuel-trend>div{flex:1;min-width:34px;text-align:center;font-size:9px;color:#708197;height:100%;display:flex;flex-direction:column;gap:5px}.dr-trend-track{flex:1;display:flex;align-items:end;border-radius:4px;background:#f3f7fc}.dr-trend-track>div{background:#398ef0;width:100%;min-height:2px;border-radius:4px 4px 0 0}.dr-fuel-trend small{font-size:9px;white-space:nowrap}@media(max-width:1150px){.dr-fuel-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dr-stacked{grid-column:span 2;grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:800px){.dr-kpi-row,.dr-three,.dr-fuel-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dr-stacked{grid-column:span 2}}@media(max-width:560px){.dr-kpi-row,.dr-three,.dr-fuel-grid,.dr-stacked{grid-template-columns:minmax(0,1fr)}.dr-stacked{grid-column:auto}.dr-filters,.dr-filters label,.dr-filters select{width:100%}}
    </style>
</x-layout.app>
