<section class="analytics-stage analytics-stage-clean">
    <section class="analytics-kpi-strip">
        <x-analytics.kpi label="Historical Sample" :value="number_format($prediction->historical_records)" small="Processed GPS trips used for historical comparison" icon="fa-database" />
        <x-analytics.kpi label="Upcoming Targets" :value="number_format($prediction->target_count)" small="Scheduled or ready trips in the next 7 days" icon="fa-bullseye" tone="green" />
        <x-analytics.kpi label="Predicted Trips" :value="number_format($prediction->predicted_target_count)" :small="$prediction->available ? 'Targets with enough comparable history' : 'Python Engine unavailable'" icon="fa-arrow-trend-up" tone="purple" />
        <x-analytics.kpi label="Peak / Slow Periods" :value="number_format($prediction->peak_periods->count())" small="Historical time blocks outside route-normal performance" icon="fa-clock" tone="yellow" />
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
            <x-analytics.card-header title="Predicted vs Historical Duration" description="Average trip duration in minutes by forecastable upcoming trip." :badge="$prediction->available ? 'Python Live' : 'Python Offline'" />

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
            <x-analytics.card-header title="Upcoming Forecasts" description="Forecastable trips with duration, ETA, risk, and supporting history in one view." :badge="number_format($forecastRows->count()) . ' forecastable'" />

            <div class="predictive-forecast-list" data-scroll-record-list data-record-selector="[data-scroll-record]">
                @forelse($forecastRows as $forecast)
                    @php
                        $riskClass = $forecast->risk_level === 'High' ? 'red' : ($forecast->risk_level === 'Moderate' ? 'yellow' : 'green');
                        $delta = $forecast->predicted_duration_minutes - $forecast->baseline_duration_minutes;
                    @endphp
                    <div class="predictive-forecast-row" data-scroll-record>
                        <div class="predictive-forecast-index">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                        <div class="predictive-forecast-body">
                            <div class="predictive-forecast-head">
                                <div><strong>{{ $forecast->trip_code }}</strong><span>{{ $forecast->route }}</span></div>
                                <div class="predictive-forecast-risk"><span class="issue-pill {{ $riskClass }}">{{ $forecast->risk_level }}</span><strong>{{ number_format($forecast->delay_risk_percent, 1) }}%</strong></div>
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
            <x-analytics.card-header title="Forecast Readiness" description="Share of upcoming trips with enough comparable history." :badge="number_format($forecastCoverage, 1) . '%'" />
            <div class="predictive-readiness-layout">
                <div class="predictive-readiness-ring" style="--coverage-angle: {{ min(360, max(0, $forecastCoverage * 3.6)) }}deg;"><div><strong>{{ number_format($forecastCoverage, 1) }}%</strong><span>Covered</span></div></div>
                <div class="predictive-readiness-stats">
                    <div><span>Upcoming targets</span><strong>{{ number_format($prediction->target_count) }}</strong></div>
                    <div><span>Forecastable</span><strong>{{ number_format($prediction->predicted_target_count) }}</strong></div>
                    <div><span>Still waiting for history</span><strong>{{ number_format(max(0, $prediction->target_count - $prediction->predicted_target_count)) }}</strong></div>
                </div>
            </div>
        </article>

        <article class="analytics-card">
            <x-analytics.card-header title="Forecast Evidence" description="Evidence behind the currently displayed Python output." :badge="$prediction->model ?? 'Offline'" />
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-database"></i></span><div><strong>{{ number_format($prediction->historical_records) }} historical trips</strong><small>Processed GPS history supplied to the prediction engine.</small></div><div class="analytics-rank-value">History</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-layer-group"></i></span><div><strong>{{ $primaryForecast ? number_format($primaryForecast->sample_size) : 0 }} comparable records</strong><small>Evidence used for the first current forecast.</small></div><div class="analytics-rank-value">Sample</div></div>
            </div>
        </article>

        <article class="analytics-card">
            <x-analytics.card-header title="Historical Outlook" description="Current slow-period evidence and first-trip risk context." />
            <div class="analytics-rank-list">
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-traffic-light"></i></span><div><strong>{{ $primaryPeak?->period ?? 'No slow period flagged' }}</strong><small>{{ $primaryPeak ? 'Top historical peak/slow-period indicator in the current response.' : 'No time block currently meets the slow-period rule.' }}</small></div><div class="analytics-rank-value">{{ $primaryPeak ? number_format($primaryPeak->duration_index, 2) . '×' : '—' }}</div></div>
                <div class="analytics-rank-row"><span class="analytics-rank-index"><i class="fa-solid fa-triangle-exclamation"></i></span><div><strong>{{ $primaryForecast?->risk_level ?? 'No forecast' }} delay risk</strong><small>Risk level for the first forecastable upcoming trip.</small></div><div class="analytics-rank-value">{{ $primaryForecast ? number_format($primaryForecast->delay_risk_percent, 1) . '%' : '—' }}</div></div>
            </div>
        </article>
    </section>

    <section class="analytics-card">
        <x-analytics.card-header title="Historical Peak / Slow Periods" description="Time windows identified from normalized historical route performance." :badge="number_format($prediction->peak_periods->count()) . ' periods'" />
        <div class="analytics-rank-list" data-scroll-record-list data-record-selector="[data-scroll-record]">
            @forelse($prediction->peak_periods as $peak)
                <div class="analytics-rank-row" data-scroll-record><span class="analytics-rank-index"><i class="fa-solid fa-traffic-light"></i></span><div><strong>{{ $peak->period }}</strong><small>{{ number_format($peak->sample_size) }} records · {{ $peak->interpretation }}</small></div><div class="analytics-rank-value">{{ number_format($peak->duration_index, 2) }}×<small>duration @if($peak->speed_index !== null) · {{ number_format($peak->speed_index, 2) }}× speed @endif</small></div></div>
            @empty
                <p class="ranking-empty">No time block meets the current historical slow-period rule.</p>
            @endforelse
        </div>
    </section>
</section>
