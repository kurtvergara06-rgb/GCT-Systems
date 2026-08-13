<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <x-analytics.kpi label="Trips Requiring Review" :value="number_format($diagnostics->review_count)" small="Records with at least one review indicator" icon="fa-magnifying-glass-chart" />
        <x-analytics.kpi label="Slow Movement" :value="number_format($diagnostics->slow_movement_count)" small="Below 80% of route median speed" icon="fa-gauge-simple-low" tone="yellow" />
        <x-analytics.kpi label="High Idling" :value="number_format($diagnostics->high_idle_count)" small="15+ idle min and 20%+ of trip time" icon="fa-hourglass-half" tone="purple" />
        <x-analytics.kpi label="Delay Indicators" :value="number_format($diagnostics->delay_count)" small="Above the route delay threshold" icon="fa-clock-rotate-left" tone="red" />
    </section>

    @php
        $indicatorTotal = max(1, $diagnostics->delay_count + $diagnostics->slow_movement_count + $diagnostics->high_idle_count);
        $delayAngle = ($diagnostics->delay_count / $indicatorTotal) * 360;
        $slowAngle = $delayAngle + (($diagnostics->slow_movement_count / $indicatorTotal) * 360);
        $indicatorMax = max(1, $diagnostics->delay_count, $diagnostics->slow_movement_count, $diagnostics->high_idle_count);
    @endphp

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card diagnostic-reference-panel">
            <x-analytics.card-header title="Diagnostic Indicator Profile" description="Relative volume of current review indicators. Counts may overlap across trips." :badge="number_format($diagnostics->baseline_coverage_percent, 1) . '% baseline coverage'" />
            <div class="diagnostic-profile-bars">
                <div class="diagnostic-profile-row"><div><span class="signal-dot red"></span><strong>Delay Indicators</strong></div><div class="diagnostic-profile-track"><span class="red" style="width: {{ ($diagnostics->delay_count / $indicatorMax) * 100 }}%"></span></div><b>{{ number_format($diagnostics->delay_count) }}</b></div>
                <div class="diagnostic-profile-row"><div><span class="signal-dot blue"></span><strong>Slow Movement</strong></div><div class="diagnostic-profile-track"><span class="blue" style="width: {{ ($diagnostics->slow_movement_count / $indicatorMax) * 100 }}%"></span></div><b>{{ number_format($diagnostics->slow_movement_count) }}</b></div>
                <div class="diagnostic-profile-row"><div><span class="signal-dot yellow"></span><strong>High Idling</strong></div><div class="diagnostic-profile-track"><span class="yellow" style="width: {{ ($diagnostics->high_idle_count / $indicatorMax) * 100 }}%"></span></div><b>{{ number_format($diagnostics->high_idle_count) }}</b></div>
            </div>
            <p class="predictive-summary-note">A delay must exceed both 120% of its route median and at least 10 minutes above that median. Slow movement and high idling are supporting indicators, not confirmed root causes.</p>
        </article>

        <article class="analytics-card diagnostic-donut-panel">
            <x-analytics.card-header title="Issue Breakdown" description="Share of diagnostic indicator occurrences." />
            <div class="diagnostic-donut-layout">
                <div class="diagnostic-donut" style="--delay-angle: {{ $delayAngle }}deg; --slow-angle: {{ $slowAngle }}deg;">
                    <div class="diagnostic-donut-center"><strong>{{ number_format($indicatorTotal) }}</strong><span>Indicator<br>occurrences</span></div>
                </div>
                <div class="diagnostic-donut-legend">
                    <div><span><i class="red"></i>Delay Indicators</span><strong>{{ number_format($diagnostics->delay_count) }}</strong></div>
                    <div><span><i class="blue"></i>Slow Movement</span><strong>{{ number_format($diagnostics->slow_movement_count) }}</strong></div>
                    <div><span><i class="yellow"></i>High Idling</span><strong>{{ number_format($diagnostics->high_idle_count) }}</strong></div>
                    <div class="diagnostic-donut-total"><span>Total indicator occurrences</span><strong>{{ number_format($indicatorTotal) }}</strong></div>
                </div>
            </div>
        </article>
    </section>

    <section class="analytics-list-grid">
        <article class="analytics-card flagged-review-card">
            <x-analytics.card-header title="Flagged Trips" :description="number_format($diagnostics->review_count) . ' trips currently require diagnostic review.'" :badge="'Top ' . number_format($diagnostics->top_records->count()) . ' priority records'" />

            <div class="flagged-review-list" data-scroll-record-list data-record-selector="[data-scroll-record]">
                @forelse($diagnostics->top_records as $diagnostic)
                    @php
                        $indicatorCount = $diagnostic->factors->count();
                        $priorityClass = $indicatorCount >= 3 ? 'critical' : ($indicatorCount === 2 ? 'warning' : 'info');
                        $recordNo = $diagnostic->record->record_no ?: 'GPS';
                        $busNo = $diagnostic->record->bus_no ?: 'Unknown Bus';
                        $occurred = $diagnostic->record->beginning_at?->format('M j · g:i A');
                    @endphp
                    <div class="flagged-review-row {{ $priorityClass }}" data-scroll-record>
                        <div class="flagged-review-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="flagged-review-body">
                            <div class="flagged-review-title-row">
                                <div><strong>{{ $recordNo }}</strong><span>{{ $busNo }} · {{ $diagnostic->route }}</span></div>
                                <div class="flagged-review-time"><strong>{{ $diagnostic->duration > 0 ? number_format($diagnostic->duration, 1) . ' min' : '—' }}</strong><span>{{ $occurred ?: 'Time unavailable' }}</span></div>
                            </div>
                            <div class="flagged-review-indicators">
                                @foreach($diagnostic->factors as $factor)
                                    <span class="issue-pill {{ $factor === 'Delay' ? 'red' : ($factor === 'Slow movement' ? 'blue' : 'yellow') }}">{{ $factor }}</span>
                                @endforeach
                            </div>
                            <div class="flagged-review-evidence">
                                <span><i class="fa-solid fa-gauge-high"></i>{{ $diagnostic->speed > 0 ? number_format($diagnostic->speed, 1) . ' km/h' : 'Speed unavailable' }}</span>
                                <span><i class="fa-solid fa-hourglass-half"></i>{{ $diagnostic->idle_minutes > 0 ? number_format($diagnostic->idle_minutes, 1) . ' min idle' : 'No recorded idling' }}</span>
                                @if($diagnostic->baseline_duration > 0)
                                    <span><i class="fa-solid fa-chart-line"></i>{{ number_format($diagnostic->baseline_duration, 1) }} min route baseline</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No trips meet the current review rules.</p>
                @endforelse
            </div>

            @if($diagnostics->review_count > $diagnostics->top_records->count())
                <div class="flagged-review-footer">Showing the {{ number_format($diagnostics->top_records->count()) }} highest-priority records from {{ number_format($diagnostics->review_count) }} flagged trips.</div>
            @endif
        </article>

        <article class="analytics-card">
            <x-analytics.card-header title="Evidence Signals" description="Patterns that can guide investigation without claiming a confirmed cause." />
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index">1</span><div><strong>Delay + Slow Movement</strong><small>Possible congestion or slow-movement pattern for review.</small></div><div class="analytics-rank-value">{{ number_format($diagnostics->delayed_with_slow_movement) }}</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index">2</span><div><strong>Delay + High Idling</strong><small>Review stops, dispatch, loading, waiting, and traffic context.</small></div><div class="analytics-rank-value">{{ number_format($diagnostics->delayed_with_high_idle) }}</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index">3</span><div><strong>Route Deviation</strong><small>Requires a full traveled path or coordinate sequence.</small></div><div class="analytics-rank-value"><span class="issue-pill blue">Not supported</span></div></div>
            </div>
        </article>
    </section>

    <section class="analytics-list-grid">
        <article class="analytics-card">
            <x-analytics.card-header title="High Idle Alerts" description="Trips meeting the strict high-idling rule." badge="Top review records" />
            <div class="analytics-rank-list">
                @forelse($diagnostics->top_records->where('is_high_idle', true)->take(3) as $item)
                    <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $item->record->bus_no ?: 'Unknown Bus' }}</strong><small>{{ $item->route }} · {{ $item->record->beginning_at?->format('M j, g:i A') }}</small></div><div class="analytics-rank-value">{{ number_format($item->idle_minutes, 1) }} min</div></div>
                @empty
                    <p>No high-idling trips in the current top review set.</p>
                @endforelse
            </div>
        </article>

        <article class="analytics-card">
            <x-analytics.card-header title="Combined Delay Patterns" description="How often a delay indicator appears together with another measurable pattern." badge="Supporting evidence" />
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-gauge-simple-low"></i></span><div><strong>Delayed + Slow Movement</strong><small>Delayed records that also fall below their route-speed baseline.</small><div class="metric-bar"><span style="width: {{ $diagnostics->delay_count > 0 ? min(100, ($diagnostics->delayed_with_slow_movement / $diagnostics->delay_count) * 100) : 0 }}%"></span></div></div><div class="analytics-rank-value"><strong>{{ number_format($diagnostics->delayed_with_slow_movement) }}</strong><small>of {{ number_format($diagnostics->delay_count) }} delays</small></div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-hourglass-half"></i></span><div><strong>Delayed + High Idling</strong><small>Delayed records that also meet the strict high-idling rule.</small><div class="metric-bar"><span style="width: {{ $diagnostics->delay_count > 0 ? min(100, ($diagnostics->delayed_with_high_idle / $diagnostics->delay_count) * 100) : 0 }}%"></span></div></div><div class="analytics-rank-value"><strong>{{ number_format($diagnostics->delayed_with_high_idle) }}</strong><small>of {{ number_format($diagnostics->delay_count) }} delays</small></div></div>
            </div>
        </article>
    </section>
</section>
