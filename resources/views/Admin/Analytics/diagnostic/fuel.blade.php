@php
    $d = $diagnostic->fuel;
    $review = $d->review_units;
    $busSummaries = $d->bus_summaries;
    $trend = $d->trend;
    $healthy = $busSummaries->filter(fn($r) => !$r->needs_review)->count();
    $reviewCount = $review->count();
    $priority = $busSummaries->where('status','Priority Review')->count();
    $totalBuses = max(1,$busSummaries->count());
    $efficient = $busSummaries->where('status','Efficient')->count();
    $normal = $busSummaries->where('status','Normal')->count();
@endphp

<section class="diag-stack">
    <div class="diag-kpis">
        <article class="diag-kpi"><div class="diag-kpi-icon"><i class="fa-solid fa-droplet"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Total Fuel Consumed</span><div class="diag-kpi-value">{{ number_format($d->total_fuel,1) }} L</div><small>Recorded fuel reports in the selected period.</small></div></article>
        <article class="diag-kpi" data-tone="green"><div class="diag-kpi-icon"><i class="fa-solid fa-gauge-high"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Average Fuel Efficiency</span><div class="diag-kpi-value">{{ number_format($d->fleet_average,2) }} km/L</div><small>Distance divided by recorded fuel consumption.</small></div></article>
        <article class="diag-kpi" data-tone="orange"><div class="diag-kpi-icon"><i class="fa-solid fa-bus"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Buses Requiring Review</span><div class="diag-kpi-value">{{ number_format($reviewCount) }}</div><small>Units with low efficiency or elevated idling intensity.</small></div></article>
        <article class="diag-kpi" data-tone="red"><div class="diag-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Priority Review</span><div class="diag-kpi-value">{{ number_format($priority) }}</div><small>Efficiency more than 20% below the selected fleet baseline.</small></div></article>
    </div>

    <div class="fuel-main-grid">
        <article class="diag-card">
            <div class="diag-card-head"><div><h3>High-Risk Buses</h3><p>Lowest-efficiency review units first.</p></div></div>
            @if($review->isEmpty())<div class="diag-empty">No bus currently meets the fuel review thresholds.</div>@else<div class="diag-list">@foreach($review->sortBy('km_per_liter')->take(6) as $bus)<div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-bus"></i></span><div><strong>{{ $bus->bus_no }}</strong><small>{{ number_format($bus->fuel_liters,1) }} L · {{ number_format($bus->distance_km,1) }} km</small></div><div class="diag-list-value">{{ number_format($bus->km_per_liter,2) }} km/L<br><span class="diag-badge {{ $bus->status === 'Priority Review' ? 'high' : 'medium' }}">{{ $bus->status }}</span></div></div>@endforeach</div>@endif
            <div style="margin-top:14px" class="diag-card-head"><div><h3>Top Diagnostic Drivers</h3><p>Only factors supported by current fuel + trip data.</p></div></div>
            <div class="diag-bars">
                <div class="diag-bar-row"><span>Below fleet efficiency baseline</span><div class="diag-bar-track"><i class="diag-bar-fill red" style="width:{{ min(100,($reviewCount/$totalBuses)*100) }}%"></i></div><b>{{ $reviewCount }}</b></div>
                <div class="diag-bar-row"><span>Elevated idling intensity</span><div class="diag-bar-track"><i class="diag-bar-fill orange" style="width:{{ min(100,($d->high_idling_units->count()/$totalBuses)*100) }}%"></i></div><b>{{ $d->high_idling_units->count() }}</b></div>
            </div>
        </article>

        <article class="diag-card fuel-matrix">
            <div class="diag-card-head"><div><h3>Fuel Diagnostic Matrix</h3><p>How supported operational factors relate to the observed fleet efficiency result.</p></div></div>
            <div class="fuel-matrix-factors">
                <div class="fuel-factor"><strong>Low Efficiency Units</strong><span>{{ $reviewCount }} buses are below review thresholds.</span><span class="diag-badge {{ $reviewCount > 0 ? 'high' : 'low' }}">{{ $reviewCount > 0 ? 'Review' : 'Normal' }}</span></div>
                <div class="fuel-factor"><strong>High Idling Intensity</strong><span>{{ $d->high_idling_units->count() }} buses exceed the selected fleet idling median rule.</span><span class="diag-badge {{ $d->high_idling_units->isNotEmpty() ? 'medium' : 'low' }}">{{ $d->high_idling_units->isNotEmpty() ? 'Investigate' : 'Normal' }}</span></div>
            </div>
            <div class="fuel-matrix-center"><span class="diag-kpi-label">Observed Fleet Efficiency</span><strong>{{ number_format($d->fleet_average,2) }} km/L</strong><small>{{ number_format($d->total_distance,1) }} km recorded distance / {{ number_format($d->total_fuel,1) }} L fuel</small></div>
            <div class="fuel-matrix-factors">
                <div class="fuel-factor"><strong>Efficient Units</strong><span>{{ $efficient }} buses are at least 5% above the fleet baseline.</span><span class="diag-badge low">Context</span></div>
                <div class="fuel-factor"><strong>Data Boundary</strong><span>Driving behavior, route congestion, load, and fuel cost are not asserted unless recorded fields support them.</span><span class="diag-badge info">Evidence rule</span></div>
            </div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Fuel Consumption Trend</h3><p>Recorded liters by day in the selected fuel trend window.</p></div></div>
            @if($trend->isEmpty())<div class="diag-empty">No fuel trend data is available.</div>@else<div class="diag-bars">@php $maxFuel=max(1,(float)$trend->max('fuel_liters')); @endphp @foreach($trend as $point)<div class="diag-bar-row"><span>{{ $point->label }}</span><div class="diag-bar-track"><i class="diag-bar-fill" style="width:{{ ($point->fuel_liters/$maxFuel)*100 }}%"></i></div><b>{{ number_format($point->fuel_liters,0) }} L</b></div>@endforeach</div>@endif
            <div style="margin-top:14px" class="diag-card-head"><div><h3>Efficiency Distribution</h3></div></div>
            <div class="diag-list"><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-circle-check"></i></span><div><strong>Efficient</strong><small>≥ 5% above fleet average</small></div><span class="diag-list-value">{{ $efficient }}</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-minus"></i></span><div><strong>Normal</strong><small>Within current baseline range</small></div><span class="diag-list-value">{{ $normal }}</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>Review / Priority</strong><small>Below baseline or elevated idling</small></div><span class="diag-list-value">{{ $reviewCount }}</span></div></div>
        </article>
    </div>

    <div class="fuel-secondary-grid">
        <article class="diag-card"><div class="diag-card-head"><div><h3>Signals Requiring Investigation</h3><p>Recorded fuel evidence that deserves closer review.</p></div></div>@if($review->isEmpty())<div class="diag-empty">No current review signal.</div>@else<div class="diag-list">@foreach($review->take(5) as $bus)<div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-gas-pump"></i></span><div><strong>{{ $bus->bus_no }}</strong><small>{{ $bus->signals->implode(' ') }}</small></div><span class="diag-badge {{ $bus->status === 'Priority Review' ? 'high' : 'medium' }}">{{ number_format($bus->km_per_liter,2) }} km/L</span></div>@endforeach</div>@endif</article>
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Insight</h3><p>@if($reviewCount > 0){{ $reviewCount }} buses show fuel-review evidence. {{ $d->high_idling_units->count() }} of those also show elevated idling intensity, making idling the strongest supported operational factor available in the current dataset.@else Current fuel records do not cross the configured fleet-relative review thresholds. @endif</p></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Investigation Priorities</h3></div></div><ol class="diag-priority-list"><li>Review the lowest-efficiency buses against maintenance history.</li><li>Inspect units with elevated idling intensity.</li><li>Compare fuel efficiency across buses with similar operating periods.</li><li>Verify fuel-report completeness before interpreting unexplained variation.</li></ol></article>
    </div>

    <article class="diag-card"><div class="diag-card-head"><div><h3>Fuel Diagnostic Breakdown</h3><p>Bus-level evidence from fuel reports and processed trip context.</p></div></div>@if($busSummaries->isEmpty())<div class="diag-empty">No fuel reports available for the selected period.</div>@else<div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Bus</th><th>Fuel Used</th><th>Distance</th><th>Efficiency</th><th>Vs Fleet Avg</th><th>Idle Minutes</th><th>Status</th></tr></thead><tbody>@foreach($busSummaries->take(10) as $bus)<tr><td><strong>{{ $bus->bus_no }}</strong></td><td>{{ number_format($bus->fuel_liters,1) }} L</td><td>{{ number_format($bus->distance_km,1) }} km</td><td>{{ number_format($bus->km_per_liter,2) }} km/L</td><td>{{ $d->fleet_average > 0 ? number_format($bus->vs_average,1).'%' : '—' }}</td><td>{{ number_format($bus->idling_minutes,1) }}</td><td><span class="diag-badge {{ $bus->status === 'Priority Review' ? 'high' : ($bus->status === 'Review' ? 'medium' : 'low') }}">{{ $bus->status }}</span></td></tr>@endforeach</tbody></table></div>@endif</article>
</section>
