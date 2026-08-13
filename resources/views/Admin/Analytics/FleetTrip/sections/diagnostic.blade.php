<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <article class="analytics-kpi"><div class="analytics-kpi-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div><div><span>Trips Requiring Review</span><strong>{{ number_format($diagnostics->review_count) }}</strong><small>Records with at least one review indicator</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon yellow"><i class="fa-solid fa-gauge-simple-low"></i></div><div><span>Slow Movement</span><strong>{{ number_format($diagnostics->slow_movement_count) }}</strong><small>Below 80% of route median speed</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon purple"><i class="fa-solid fa-hourglass-half"></i></div><div><span>High Idling</span><strong>{{ number_format($diagnostics->high_idle_count) }}</strong><small>15+ idle min and 20%+ of trip time</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon red"><i class="fa-solid fa-clock-rotate-left"></i></div><div><span>Delay Indicators</span><strong>{{ number_format($diagnostics->delay_count) }}</strong><small>Above the route delay threshold</small></div></article>
    </section>

    @php
        $indicatorMax = max(1, $diagnostics->delay_count, $diagnostics->slow_movement_count, $diagnostics->high_idle_count);
    @endphp

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Diagnostic Indicator Breakdown</h3><p>Counts may overlap because one trip can meet more than one diagnostic rule.</p></div><span class="analytics-card-badge">{{ number_format($diagnostics->baseline_coverage_percent, 1) }}% baseline coverage</span></div>
            <div class="analytics-signal-bars">
                <div class="analytics-signal-row"><div><span class="signal-dot red"></span><strong>Delay Indicators</strong></div><div class="analytics-signal-track"><span class="red" style="width: {{ ($diagnostics->delay_count / $indicatorMax) * 100 }}%"></span></div><b>{{ number_format($diagnostics->delay_count) }}</b></div>
                <div class="analytics-signal-row"><div><span class="signal-dot blue"></span><strong>Slow Movement</strong></div><div class="analytics-signal-track"><span class="blue" style="width: {{ ($diagnostics->slow_movement_count / $indicatorMax) * 100 }}%"></span></div><b>{{ number_format($diagnostics->slow_movement_count) }}</b></div>
                <div class="analytics-signal-row"><div><span class="signal-dot yellow"></span><strong>High Idling</strong></div><div class="analytics-signal-track"><span class="yellow" style="width: {{ ($diagnostics->high_idle_count / $indicatorMax) * 100 }}%"></span></div><b>{{ number_format($diagnostics->high_idle_count) }}</b></div>
            </div>
            <p class="predictive-summary-note">A delay must exceed both 120% of its route median and at least 10 minutes above that median. Slow movement and high idling are supporting indicators, not confirmed root causes.</p>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Evidence Signals</h3><p>Patterns that can guide review without claiming a confirmed cause.</p></div></div>
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index">1</span><div><strong>Delay + Slow Movement</strong><small>Possible congestion or slow-movement pattern for review.</small></div><div class="analytics-rank-value">{{ number_format($diagnostics->delayed_with_slow_movement) }}</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index">2</span><div><strong>Delay + High Idling</strong><small>Review stops, dispatch, loading, waiting, and traffic context.</small></div><div class="analytics-rank-value">{{ number_format($diagnostics->delayed_with_high_idle) }}</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index">3</span><div><strong>Route Deviation</strong><small>Requires a full traveled path or coordinate sequence.</small></div><div class="analytics-rank-value"><span class="issue-pill blue">Not supported</span></div></div>
            </div>
        </article>
    </section>

    <section class="analytics-card">
        <div class="analytics-card-header"><div><h3>Flagged Trips</h3><p>Highest-priority records based on the current diagnostic rules.</p></div><span class="analytics-card-badge">{{ number_format($diagnostics->review_count) }} flagged</span></div>
        <div class="table-responsive">
            <table class="analytics-table">
                <thead><tr><th>Trip</th><th>Bus</th><th>Route</th><th>Indicators</th><th>Duration</th><th>Speed</th><th>Idle</th><th>Occurred</th></tr></thead>
                <tbody>
                    @forelse($diagnostics->top_records as $diagnostic)
                        <tr>
                            <td><strong>{{ $diagnostic->record->record_no ?: 'GPS' }}</strong></td>
                            <td>{{ $diagnostic->record->bus_no ?: 'Unknown Bus' }}</td>
                            <td>{{ $diagnostic->route }}</td>
                            <td>@foreach($diagnostic->factors as $factor)<span class="issue-pill {{ $factor === 'Delay' ? 'red' : ($factor === 'Slow movement' ? 'blue' : 'yellow') }}">{{ $factor }}</span> @endforeach</td>
                            <td>{{ number_format($diagnostic->duration, 1) }} min</td>
                            <td>{{ number_format($diagnostic->speed, 1) }} km/h</td>
                            <td>{{ number_format($diagnostic->idle_minutes, 1) }} min</td>
                            <td>{{ $diagnostic->record->beginning_at?->format('M j, g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">No trips meet the current review rules.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="analytics-list-grid analytics-list-grid-three">
        <article class="analytics-card"><div class="analytics-card-header"><div><h3>High Idle Alerts</h3><p>Trips meeting the strict high-idling rule.</p></div></div><div class="analytics-rank-list">@forelse($diagnostics->top_records->where('is_high_idle', true)->take(3) as $item)<div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $item->record->bus_no ?: 'Unknown Bus' }}</strong><small>{{ $item->route }}</small></div><div class="analytics-rank-value">{{ number_format($item->idle_minutes, 1) }} min</div></div>@empty<p>No high-idling trips in the current top review set.</p>@endforelse</div></article>
        <article class="analytics-card"><div class="analytics-card-header"><div><h3>Delayed + Slow</h3><p>Delayed records that also show below-baseline speed.</p></div></div><div class="analytics-center-metric"><strong>{{ number_format($diagnostics->delayed_with_slow_movement) }}</strong><span>combined indicators</span></div></article>
        <article class="analytics-card"><div class="analytics-card-header"><div><h3>Delayed + High Idle</h3><p>Delayed records that also show significant idling.</p></div></div><div class="analytics-center-metric"><strong>{{ number_format($diagnostics->delayed_with_high_idle) }}</strong><span>combined indicators</span></div></article>
    </section>
</section>
