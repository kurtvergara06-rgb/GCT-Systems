<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <article class="analytics-kpi"><div class="analytics-kpi-icon"><i class="fa-solid fa-database"></i></div><div><span>Historical Sample</span><strong>{{ number_format($prediction->historical_records) }}</strong><small>Processed GPS trips used for historical comparison</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon green"><i class="fa-solid fa-bullseye"></i></div><div><span>Upcoming Targets</span><strong>{{ number_format($prediction->target_count) }}</strong><small>Scheduled or ready trips in the next 7 days</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon purple"><i class="fa-solid fa-arrow-trend-up"></i></div><div><span>Predicted Trips</span><strong>{{ number_format($prediction->predicted_target_count) }}</strong><small>{{ $prediction->available ? 'Targets with enough comparable history' : 'Python Engine unavailable' }}</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon yellow"><i class="fa-solid fa-clock"></i></div><div><span>Peak / Slow Periods</span><strong>{{ number_format($prediction->peak_periods->count()) }}</strong><small>Historical time blocks outside route-normal performance</small></div></article>
    </section>

    @php
        $forecastRows = $prediction->predictions->take(6)->values();
        $durationChartMax = max(1, (float) ($forecastRows->max(fn ($forecast) => max($forecast->predicted_duration_minutes, $forecast->baseline_duration_minutes)) ?? 0));
        $durationScaleMax = max(10, ceil($durationChartMax / 10) * 10);
    @endphp

    <section class="analytics-main-grid analytics-main-grid-balanced">
        <article class="analytics-card predictive-chart-panel">
            <div class="analytics-card-header"><div><h3>Predicted vs Historical Duration</h3><p>Python forecast compared with each trip's historical route baseline.</p></div><span class="analytics-card-badge">{{ $prediction->available ? 'Python Live' : 'Python Offline' }}</span></div>
            @if($forecastRows->isNotEmpty())
                <div class="predictive-chart-wrap">
                    <div class="predictive-y-axis"><span>{{ number_format($durationScaleMax) }}</span><span>{{ number_format($durationScaleMax * .75) }}</span><span>{{ number_format($durationScaleMax * .5) }}</span><span>{{ number_format($durationScaleMax * .25) }}</span><span>0</span></div>
                    <div class="predictive-plot">
                        <div class="predictive-grid-line line-1"></div><div class="predictive-grid-line line-2"></div><div class="predictive-grid-line line-3"></div><div class="predictive-grid-line line-4"></div>
                        @foreach($forecastRows as $forecast)
                            @php
                                $baselineHeight = min(100, ($forecast->baseline_duration_minutes / $durationScaleMax) * 100);
                                $predictedHeight = min(100, ($forecast->predicted_duration_minutes / $durationScaleMax) * 100);
                            @endphp
                            <div class="predictive-category"><div class="predictive-bar-group"><div class="predictive-bar baseline" style="height: {{ max(2, $baselineHeight) }}%;"><span>{{ number_format($forecast->baseline_duration_minutes, 1) }}</span></div><div class="predictive-bar predicted" style="height: {{ max(2, $predictedHeight) }}%;"><span>{{ number_format($forecast->predicted_duration_minutes, 1) }}</span></div></div><strong>{{ $forecast->trip_code }}</strong></div>
                        @endforeach
                    </div>
                </div>
                <div class="predictive-chart-legend"><span><i class="legend-swatch baseline"></i>Historical baseline</span><span><i class="legend-swatch predicted"></i>Predicted duration</span></div>
            @else
                <p class="predictive-summary-note">{{ $prediction->message }}</p>
            @endif
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Delay Risk by Upcoming Trip</h3><p>Forecastable upcoming trips ranked by historical delay risk.</p></div><span class="analytics-card-badge">Next {{ $forecastRows->count() }} trips</span></div>
            <div class="analytics-rank-list">
                @forelse($forecastRows as $forecast)
                    @php $riskClass = $forecast->risk_level === 'High' ? 'red' : ($forecast->risk_level === 'Moderate' ? 'yellow' : 'green'); @endphp
                    <div class="analytics-rank-row predictive-risk-row">
                        <span class="analytics-rank-index">{{ $loop->iteration }}</span>
                        <div><strong>{{ $forecast->trip_code }} · {{ $forecast->route }}</strong><small>Departs {{ $forecast->departure_at?->format('g:i A') }} · {{ number_format($forecast->sample_size) }} comparable records</small><div class="metric-bar"><span style="width: {{ min(100, max(1, $forecast->delay_risk_percent)) }}%"></span></div></div>
                        <div class="analytics-rank-value"><span class="issue-pill {{ $riskClass }}">{{ $forecast->risk_level }}</span><small>{{ number_format($forecast->delay_risk_percent, 1) }}%</small></div>
                    </div>
                @empty
                    <p>No forecastable upcoming trips yet.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="analytics-list-grid">
        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Upcoming Trip Forecasts</h3><p>Detailed forecast for upcoming trips with enough comparable history.</p></div><span class="analytics-card-badge">{{ $prediction->model ?? 'Service Offline' }}</span></div>
            <div class="table-responsive"><table class="analytics-table"><thead><tr><th>Trip</th><th>Route</th><th>Departure</th><th>Predicted Duration</th><th>ETA</th><th>Risk</th><th>Evidence</th></tr></thead><tbody>
                @forelse($prediction->predictions->take(6) as $forecast)
                    <tr><td><strong>{{ $forecast->trip_code }}</strong></td><td>{{ $forecast->route }}</td><td>{{ $forecast->departure_at?->format('M j, g:i A') }}</td><td>{{ number_format($forecast->predicted_duration_minutes, 1) }} min</td><td>{{ $forecast->estimated_arrival_at?->format('g:i A') }}</td><td><span class="issue-pill {{ $forecast->risk_level === 'High' ? 'red' : ($forecast->risk_level === 'Moderate' ? 'yellow' : 'green') }}">{{ number_format($forecast->delay_risk_percent, 1) }}% {{ $forecast->risk_level }}</span></td><td>{{ number_format($forecast->sample_size) }} comparable</td></tr>
                @empty
                    <tr><td colspan="7">{{ $prediction->message }}</td></tr>
                @endforelse
            </tbody></table></div>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Historical Peak / Slow Periods</h3><p>Time windows identified from normalized historical route performance.</p></div></div>
            <div class="analytics-rank-list">
                @forelse($prediction->peak_periods as $peak)
                    <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-traffic-light"></i></span><div><strong>{{ $peak->period }}</strong><small>{{ number_format($peak->sample_size) }} records · {{ $peak->interpretation }}</small></div><div class="analytics-rank-value">{{ number_format($peak->duration_index, 2) }}×<small>duration @if($peak->speed_index !== null) · {{ number_format($peak->speed_index, 2) }}× speed @endif</small></div></div>
                @empty
                    <p>No time block meets the current historical slow-period rule.</p>
                @endforelse
            </div>
        </article>
    </section>
</section>
