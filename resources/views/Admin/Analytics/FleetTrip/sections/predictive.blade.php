<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <article class="analytics-kpi"><div class="analytics-kpi-icon"><i class="fa-solid fa-database"></i></div><div><span>Historical Sample</span><strong>{{ number_format($prediction->historical_records) }}</strong><small>Processed GPS trips used for historical comparison</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon green"><i class="fa-solid fa-bullseye"></i></div><div><span>Upcoming Targets</span><strong>{{ number_format($prediction->target_count) }}</strong><small>Scheduled or ready trips in the next 7 days</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon purple"><i class="fa-solid fa-arrow-trend-up"></i></div><div><span>Predicted Trips</span><strong>{{ number_format($prediction->predicted_target_count) }}</strong><small>{{ $prediction->available ? 'Targets with enough comparable history' : 'Python Engine unavailable' }}</small></div></article>
        <article class="analytics-kpi"><div class="analytics-kpi-icon yellow"><i class="fa-solid fa-clock"></i></div><div><span>Peak / Slow Periods</span><strong>{{ number_format($prediction->peak_periods->count()) }}</strong><small>Historical time blocks outside route-normal performance</small></div></article>
    </section>

    @php
        $forecastRows = $prediction->predictions->take(8)->values();
        $durationChartMax = max(1, (float) ($forecastRows->max(fn ($forecast) => max($forecast->predicted_duration_minutes, $forecast->baseline_duration_minutes)) ?? 0));
        $durationScaleMax = max(10, ceil($durationChartMax / 10) * 10);
        $forecastCoverage = $prediction->target_count > 0
            ? ($prediction->predicted_target_count / $prediction->target_count) * 100
            : 0;
        $primaryForecast = $forecastRows->first();
        $primaryDelta = $primaryForecast
            ? $primaryForecast->predicted_duration_minutes - $primaryForecast->baseline_duration_minutes
            : null;
        $primaryPeak = $prediction->peak_periods->first();
    @endphp

    <section class="analytics-main-grid analytics-main-grid-balanced predictive-reference-grid">
        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Predicted vs Historical Duration</h3><p>Average trip duration in minutes by forecastable upcoming trip.</p></div><span class="analytics-card-badge">{{ $prediction->available ? 'Python Live' : 'Python Offline' }}</span></div>

            @if($forecastRows->isNotEmpty())
                <div class="forecast-comparison-chart">
                    <div class="forecast-y-axis">
                        <span>{{ number_format($durationScaleMax) }}</span>
                        <span>{{ number_format($durationScaleMax * .75) }}</span>
                        <span>{{ number_format($durationScaleMax * .5) }}</span>
                        <span>{{ number_format($durationScaleMax * .25) }}</span>
                        <span>0</span>
                    </div>
                    <div class="forecast-plot">
                        <div class="forecast-grid-lines"><i></i><i></i><i></i><i></i><i></i></div>
                        <div class="forecast-groups">
                            @foreach($forecastRows as $forecast)
                                @php
                                    $baselineHeight = min(100, ($forecast->baseline_duration_minutes / $durationScaleMax) * 100);
                                    $predictedHeight = min(100, ($forecast->predicted_duration_minutes / $durationScaleMax) * 100);
                                @endphp
                                <div class="forecast-group">
                                    <div class="forecast-bars">
                                        <div class="forecast-bar baseline" style="height: {{ max(4, $baselineHeight) }}%"><span>{{ number_format($forecast->baseline_duration_minutes, 1) }}</span></div>
                                        <div class="forecast-bar predicted" style="height: {{ max(4, $predictedHeight) }}%"><span>{{ number_format($forecast->predicted_duration_minutes, 1) }}</span></div>
                                    </div>
                                    <strong>{{ $forecast->trip_code }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="forecast-chart-legend"><span><i class="baseline"></i>Historical Avg</span><span><i class="predicted"></i>Predicted Duration</span></div>
                @if($primaryForecast)
                    <p class="predictive-summary-note"><strong>{{ $primaryForecast->trip_code }}</strong> is forecast at {{ number_format($primaryForecast->predicted_duration_minutes, 1) }} min, {{ $primaryDelta >= 0 ? number_format(abs($primaryDelta), 1) . ' min above' : number_format(abs($primaryDelta), 1) . ' min below' }} its {{ number_format($primaryForecast->baseline_duration_minutes, 1) }} min historical baseline. Predicted ETA: {{ $primaryForecast->estimated_arrival_at?->format('g:i A') }}.</p>
                @endif
            @else
                <p class="predictive-summary-note">{{ $prediction->message }}</p>
            @endif
        </article>

        <article class="analytics-card predictive-forecast-card">
            <div class="analytics-card-header">
                <div><h3>Upcoming Forecasts</h3><p>Forecastable trips with duration, ETA, risk, and supporting history in one view.</p></div>
                <span class="analytics-card-badge">{{ number_format($forecastRows->count()) }} forecastable</span>
            </div>

            <div class="predictive-forecast-list">
                @forelse($forecastRows as $forecast)
                    @php
                        $riskClass = $forecast->risk_level === 'High' ? 'red' : ($forecast->risk_level === 'Moderate' ? 'yellow' : 'green');
                        $delta = $forecast->predicted_duration_minutes - $forecast->baseline_duration_minutes;
                    @endphp
                    <div class="predictive-forecast-row">
                        <div class="predictive-forecast-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="predictive-forecast-body">
                            <div class="predictive-forecast-head">
                                <div>
                                    <strong>{{ $forecast->trip_code }}</strong>
                                    <span>{{ $forecast->route }}</span>
                                </div>
                                <div class="predictive-forecast-risk">
                                    <span class="issue-pill {{ $riskClass }}">{{ $forecast->risk_level }}</span>
                                    <strong>{{ number_format($forecast->delay_risk_percent, 1) }}%</strong>
                                </div>
                            </div>

                            <div class="predictive-forecast-metrics">
                                <div><span>Departure</span><strong>{{ $forecast->departure_at?->format('M j, g:i A') ?? '—' }}</strong></div>
                                <div><span>Predicted</span><strong>{{ number_format($forecast->predicted_duration_minutes, 1) }} min</strong></div>
                                <div><span>ETA</span><strong>{{ $forecast->estimated_arrival_at?->format('g:i A') ?? '—' }}</strong></div>
                                <div><span>Vs. baseline</span><strong>{{ $delta >= 0 ? '+' : '−' }}{{ number_format(abs($delta), 1) }} min</strong></div>
                            </div>

                            <div class="predictive-forecast-evidence">
                                <span><i class="fa-solid fa-layer-group"></i>{{ number_format($forecast->sample_size) }} comparable records</span>
                                <span><i class="fa-solid fa-clock-rotate-left"></i>{{ number_format($forecast->baseline_duration_minutes, 1) }} min historical baseline</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">{{ $prediction->message }}</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="predictive-support-grid">
        <article class="analytics-card predictive-readiness-card">
            <div class="analytics-card-header"><div><h3>Forecast Readiness</h3><p>Share of upcoming trips with enough comparable history.</p></div><span class="analytics-card-badge">{{ number_format($forecastCoverage, 1) }}%</span></div>
            <div class="predictive-readiness-layout">
                <div class="predictive-readiness-ring" style="--coverage-angle: {{ min(360, max(0, $forecastCoverage * 3.6)) }}deg;">
                    <div><strong>{{ number_format($forecastCoverage, 1) }}%</strong><span>Covered</span></div>
                </div>
                <div class="predictive-readiness-stats">
                    <div><span>Upcoming targets</span><strong>{{ number_format($prediction->target_count) }}</strong></div>
                    <div><span>Forecastable</span><strong>{{ number_format($prediction->predicted_target_count) }}</strong></div>
                    <div><span>Still waiting for history</span><strong>{{ number_format(max(0, $prediction->target_count - $prediction->predicted_target_count)) }}</strong></div>
                </div>
            </div>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Forecast Evidence</h3><p>Evidence behind the currently displayed Python output.</p></div><span class="analytics-card-badge">{{ $prediction->model ?? 'Offline' }}</span></div>
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-database"></i></span><div><strong>{{ number_format($prediction->historical_records) }} historical trips</strong><small>Processed GPS history supplied to the prediction engine.</small></div><div class="analytics-rank-value">History</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-layer-group"></i></span><div><strong>{{ $primaryForecast ? number_format($primaryForecast->sample_size) : 0 }} comparable records</strong><small>Evidence used for the first current forecast.</small></div><div class="analytics-rank-value">Sample</div></div>
            </div>
        </article>

        <article class="analytics-card">
            <div class="analytics-card-header"><div><h3>Historical Outlook</h3><p>Current slow-period evidence and first-trip risk context.</p></div></div>
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-traffic-light"></i></span><div><strong>{{ $primaryPeak?->period ?? 'No slow period flagged' }}</strong><small>{{ $primaryPeak ? 'Top historical peak/slow-period indicator in the current response.' : 'No time block currently meets the slow-period rule.' }}</small></div><div class="analytics-rank-value">{{ $primaryPeak ? number_format($primaryPeak->duration_index, 2) . '×' : '—' }}</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $primaryForecast?->risk_level ?? 'No forecast' }} delay risk</strong><small>Risk level for the first forecastable upcoming trip.</small></div><div class="analytics-rank-value">{{ $primaryForecast ? number_format($primaryForecast->delay_risk_percent, 1) . '%' : '—' }}</div></div>
            </div>
        </article>
    </section>

    <section class="analytics-card">
        <div class="analytics-card-header"><div><h3>Historical Peak / Slow Periods</h3><p>Time windows identified from normalized historical route performance.</p></div><span class="analytics-card-badge">{{ number_format($prediction->peak_periods->count()) }} periods</span></div>
        <div class="analytics-rank-list">
            @forelse($prediction->peak_periods as $peak)
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-traffic-light"></i></span><div><strong>{{ $peak->period }}</strong><small>{{ number_format($peak->sample_size) }} records · {{ $peak->interpretation }}</small></div><div class="analytics-rank-value">{{ number_format($peak->duration_index, 2) }}×<small>duration @if($peak->speed_index !== null) · {{ number_format($peak->speed_index, 2) }}× speed @endif</small></div></div>
            @empty
                <p class="ranking-empty">No time block meets the current historical slow-period rule.</p>
            @endforelse
        </div>
    </section>
</section>
