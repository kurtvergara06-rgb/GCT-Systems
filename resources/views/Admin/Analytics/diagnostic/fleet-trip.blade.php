@php
    $d = $diagnostic->fleet->diagnostics;
    $routes = $diagnostic->fleet->routes;
    $busActivity = $diagnostic->fleet->bus_activity;
    $topRecords = collect($d->top_records ?? []);
    $routeReview = $topRecords->groupBy('route')->map(function($rows, $route){ return (object)['route'=>$route,'count'=>$rows->count(),'score'=>$rows->sum('score'),'idle'=>$rows->sum('idle_minutes')]; })->sortByDesc('score')->take(5)->values();
@endphp

<section class="diag-stack">
    <div class="diag-kpis">
        <article class="diag-kpi"><div class="diag-kpi-icon"><i class="fa-regular fa-clock"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Average Trip Duration</span><div class="diag-kpi-value">{{ number_format($diagnostic->fleet->average_trip_duration, 1) }} min</div><small>Observed duration across selected trip records.</small></div></article>
        <article class="diag-kpi" data-tone="red"><div class="diag-kpi-icon"><i class="fa-solid fa-wave-square"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Diagnostic Signals</span><div class="diag-kpi-value">{{ number_format((int) ($d->review_count ?? 0)) }}</div><small>Trip records with at least one supported review factor.</small></div></article>
        <article class="diag-kpi" data-tone="green"><div class="diag-kpi-icon"><i class="fa-solid fa-hourglass-half"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Idle Exposure</span><div class="diag-kpi-value">{{ number_format($diagnostic->fleet->total_idle_minutes / 60, 1) }} hrs</div><small>{{ number_format((int) ($d->high_idle_count ?? 0)) }} high-idle records detected.</small></div></article>
        <article class="diag-kpi" data-tone="purple"><div class="diag-kpi-icon"><i class="fa-solid fa-road-circle-exclamation"></i></div><div class="diag-kpi-copy"><span class="diag-kpi-label">Delay / Movement Signals</span><div class="diag-kpi-value">{{ number_format((int) ($d->delay_count ?? 0) + (int) ($d->slow_movement_count ?? 0)) }}</div><small>Delay and slow-movement records based on route baselines.</small></div></article>
    </div>

    <div class="fleet-main-grid">
        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Why Fleet & Trip Performance Shifted</h3><p>Diagnostic evidence derived from route medians, speed, duration, and idling.</p></div></div>
            <div class="diag-grid-3-equal">
                <div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-clock"></i></span><div><strong>Delayed Trips</strong><small>Duration exceeds supported route baseline.</small></div><span class="diag-list-value">{{ number_format((int) ($d->delay_count ?? 0)) }}</span></div>
                <div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-gauge-low"></i></span><div><strong>Slow Movement</strong><small>Speed materially below route median.</small></div><span class="diag-list-value">{{ number_format((int) ($d->slow_movement_count ?? 0)) }}</span></div>
                <div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-hourglass-half"></i></span><div><strong>High Idling</strong><small>At least 15 minutes and 20% of duration.</small></div><span class="diag-list-value">{{ number_format((int) ($d->high_idle_count ?? 0)) }}</span></div>
            </div>
            <div style="margin-top:14px" class="diag-bars">
                @php $driverMax = max(1,(int)max($d->delay_count ?? 0,$d->slow_movement_count ?? 0,$d->high_idle_count ?? 0)); @endphp
                <div class="diag-bar-row"><span>Delay</span><div class="diag-bar-track"><i class="diag-bar-fill red" style="width:{{ (($d->delay_count ?? 0)/$driverMax)*100 }}%"></i></div><b>{{ $d->delay_count ?? 0 }}</b></div>
                <div class="diag-bar-row"><span>Slow movement</span><div class="diag-bar-track"><i class="diag-bar-fill orange" style="width:{{ (($d->slow_movement_count ?? 0)/$driverMax)*100 }}%"></i></div><b>{{ $d->slow_movement_count ?? 0 }}</b></div>
                <div class="diag-bar-row"><span>High idling</span><div class="diag-bar-track"><i class="diag-bar-fill green" style="width:{{ (($d->high_idle_count ?? 0)/$driverMax)*100 }}%"></i></div><b>{{ $d->high_idle_count ?? 0 }}</b></div>
            </div>
        </article>

        <article class="diag-card">
            <div class="diag-card-head"><div><h3>Top Diagnostic Drivers</h3><p>Supported factors ranked by observed record count.</p></div></div>
            <div class="diag-list">
                @foreach(collect([['Delay',(int)($d->delay_count ?? 0),'High'],['High idling',(int)($d->high_idle_count ?? 0),'Medium'],['Slow movement',(int)($d->slow_movement_count ?? 0),'Medium']])->sortByDesc(1) as $i=>$driver)
                    <div class="diag-list-row"><span class="diag-list-rank">{{ $loop->iteration }}</span><div><strong>{{ $driver[0] }}</strong><small>Observed in processed GPS trip evidence.</small></div><span class="diag-badge {{ strtolower($driver[2]) }}">{{ number_format($driver[1]) }}</span></div>
                @endforeach
            </div>
            <div style="margin-top:12px" class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-database"></i></span><div><strong>Baseline Coverage</strong><small>Records with enough same-route history to evaluate duration.</small></div><span class="diag-list-value">{{ number_format((float) ($d->baseline_coverage_percent ?? 0), 1) }}%</span></div>
        </article>
    </div>

    <div class="fleet-lower-grid">
        <article class="diag-card"><div class="diag-card-head"><div><h3>Route Bottleneck Analysis</h3><p>Routes represented most often in review records.</p></div></div>@if($routeReview->isEmpty())<div class="diag-empty">No route-level review evidence for the selected period.</div>@else<div class="diag-bars">@php $m=max(1,(int)$routeReview->max('score')); @endphp @foreach($routeReview as $row)<div class="diag-bar-row"><span>{{ $row->route }}</span><div class="diag-bar-track"><i class="diag-bar-fill" style="width:{{ ($row->score/$m)*100 }}%"></i></div><b>{{ $row->count }}</b></div>@endforeach</div>@endif</article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Bus Utilization Distribution</h3><p>Trip contribution by the most active buses in the selected data.</p></div></div>@if($busActivity->isEmpty())<div class="diag-empty">No bus activity records available.</div>@else<div class="diag-bars">@foreach($busActivity as $bus)<div class="diag-bar-row"><span>{{ $bus->bus }}</span><div class="diag-bar-track"><i class="diag-bar-fill green" style="width:{{ min(100,$bus->share) }}%"></i></div><b>{{ number_format($bus->trips) }}</b></div>@endforeach</div>@endif</article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Route Performance Context</h3><p>Trip count and average duration for leading routes.</p></div></div>@if($routes->isEmpty())<div class="diag-empty">No route records available.</div>@else<div class="diag-list">@foreach($routes as $route)<div class="diag-list-row"><span class="diag-list-rank">{{ $loop->iteration }}</span><div><strong>{{ $route->label }}</strong><small>{{ number_format($route->average_duration,1) }} min avg duration</small></div><span class="diag-list-value">{{ number_format($route->trips) }} trips</span></div>@endforeach</div>@endif</article>
    </div>

    <article class="diag-card diag-evidence-card">
        <div class="diag-card-head"><div><h3>Diagnostic Evidence Table</h3><p>Highest-scoring records with supported delay, movement, or idling factors.</p></div></div>
        @if($topRecords->isEmpty())<div class="diag-empty">No trip record currently meets the diagnostic review thresholds.</div>@else<div class="diag-table-wrap"><table class="diag-table"><thead><tr><th>Bus</th><th>Route</th><th>Duration</th><th>Speed</th><th>Idle</th><th>Factors</th><th>Evidence Score</th></tr></thead><tbody>@foreach($topRecords as $row)<tr><td><strong>{{ $row->record->bus_no ?: '—' }}</strong></td><td>{{ $row->route }}</td><td>{{ number_format($row->duration,1) }} min</td><td>{{ number_format($row->speed,1) }} km/h</td><td>{{ number_format($row->idle_minutes,1) }} min</td><td>{{ $row->factors->implode(', ') }}</td><td><span class="diag-badge {{ $row->score >= 5 ? 'high' : 'medium' }}">{{ $row->score }}</span></td></tr>@endforeach</tbody></table></div>@endif
    </article>

    <div class="diag-grid-3-equal">
        <article class="diag-card diag-insight"><div class="diag-insight-icon"><i class="fa-regular fa-lightbulb"></i></div><div><h3>Key Diagnostic Insight</h3><p>{{ ($d->review_count ?? 0) > 0 ? number_format((int)$d->review_count).' trip records require review. The page separates delay, slow movement, and idling so the investigation can follow the recorded evidence instead of assuming a cause.' : 'No trip record currently crosses the configured diagnostic thresholds.' }}</p></div></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Priority Investigations</h3></div></div><ol class="diag-priority-list"><li>Review delayed records on routes with sufficient baseline coverage.</li><li>Inspect buses with repeated high-idle records.</li><li>Compare slow-movement records against route and time context.</li></ol></article>
        <article class="diag-card"><div class="diag-card-head"><div><h3>Data Support</h3><p>Evidence used by this diagnostic page.</p></div></div><div class="diag-list"><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-route"></i></span><div><strong>Processed GPS Trips</strong><small>Trips analyzed</small></div><span class="diag-list-value">{{ number_format($diagnostic->fleet->trip_count) }}</span></div><div class="diag-list-row"><span class="diag-list-rank"><i class="fa-solid fa-chart-line"></i></span><div><strong>Route Baseline Coverage</strong><small>Supported comparison records</small></div><span class="diag-list-value">{{ number_format((float)($d->baseline_coverage_percent ?? 0),1) }}%</span></div></div></article>
    </div>
</section>
