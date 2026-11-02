@php
    $fleetStats = $stats ?? [
        'tripsAtRisk' => 0,
        'predictedDelays' => 0,
        'utilization' => 0,
        'highIdleRisk' => 0,
        'completionForecast' => 0,
    ];

    $tripPredictionsRaw = collect($predictions ?? []);
    $routePredictionsRaw = collect($routes ?? []);
    $fleetTrend = collect($fleet['trend'] ?? [])->values();

    $tripGrowthTrend = $fleet['tripGrowth'] ?? null;
    $tripGrowth = (float) $tripGrowthTrend;

    $predictionAvailable = (bool) ($fleet['prediction']->available ?? false);
    $predictionStatus = $fleet['prediction'] ?? null;
    $forecastTargetCount = (int) ($predictionStatus->target_count ?? 0);
    $forecastHistoryCount = (int) ($predictionStatus->historical_records ?? 0);
    $forecastEmptyReason = ! $predictionAvailable
        ? 'The Python forecasting service is unavailable. Historical GPS diagnostics are still shown separately.'
        : ($forecastTargetCount === 0
            ? 'No future Scheduled or Ready trips with active routes were found in the next seven days.'
            : 'No eligible trip-level forecasts were returned. Comparable route history or prediction eligibility may be insufficient.');

    $tripCount = (int) ($fleet['tripCount'] ?? 0);
    $totalBuses = (int) ($fleet['totalBuses'] ?? 0);
    $activeBuses = (int) ($fleet['activeBuses'] ?? 0);
    $unavailableBuses = max(0, $totalBuses - $activeBuses);
    $maintenanceBuses = (int) ($fleet['underMaintenance'] ?? 0);
    $inactiveBuses = (int) ($fleet['inactiveBuses'] ?? 0);
    $otherStatusBuses = max(0, $unavailableBuses - $maintenanceBuses - $inactiveBuses);
    $averageTripDuration = (float) ($fleet['averageTripDuration'] ?? 0);

    $tripsAtRiskCount = (int) ($fleetStats['tripsAtRisk'] ?? 0);
    $predictedDelaysCount = (int) ($fleetStats['predictedDelays'] ?? 0);
    $highIdleRiskCount = (int) ($fleetStats['highIdleRisk'] ?? 0);
    $utilizationPct = (float) ($fleetStats['utilization'] ?? 0);
    $completionForecastPct = (float) ($fleetStats['completionForecast'] ?? 0);

    // Baseline display values based on genuine metrics
    $displayTripsAtRisk = $tripsAtRiskCount;
    $displayPredictedDelays = $predictedDelaysCount;
    $displayUtilization = $utilizationPct > 0 ? number_format($utilizationPct, 1) . '%' : '0.0%';
    $displayHighIdleRisk = $highIdleRiskCount;
    $displayCompletionForecast = $tripCount > 0 && $completionForecastPct > 0 ? number_format($completionForecastPct, 1) . '%' : '—';

    $periodTextMap = [
        'this-week' => 'This Week',
        'this-month' => 'This Month',
        'last-30-days' => 'Last 30 Days',
        'last-90-days' => 'Last 90 Days',
        'last-12-months' => 'Last 12 Months',
    ];
    $periodText = $periodTextMap[$period] ?? 'This Month';

    $riskBadge = fn (string $level): string => match (strtolower(trim($level))) {
        'high' => 'high',
        'medium' => 'medium',
        default => 'low',
    };

    /*
    |--------------------------------------------------------------------------
    | Fleet Risk Distribution
    |--------------------------------------------------------------------------
    */
    $tripLevelCounts = $tripPredictionsRaw->countBy(
        fn ($row) => strtolower(trim((string) ($row[6] ?? 'low')))
    );

    $fleetRisk = [
        'low' => (int) ($tripLevelCounts['low'] ?? 0),
        'medium' => (int) ($tripLevelCounts['medium'] ?? 0),
        'high' => (int) ($tripLevelCounts['high'] ?? 0),
    ];
    $fleetRiskTotal = array_sum($fleetRisk);

    $fleetRiskPercent = [
        'low' => $fleetRiskTotal > 0 ? round(($fleetRisk['low'] / $fleetRiskTotal) * 100, 1) : 0,
        'medium' => $fleetRiskTotal > 0 ? round(($fleetRisk['medium'] / $fleetRiskTotal) * 100, 1) : 0,
        'high' => $fleetRiskTotal > 0 ? round(($fleetRisk['high'] / $fleetRiskTotal) * 100, 1) : 0,
    ];

    /*
    |--------------------------------------------------------------------------
    | KPI Cards
    |--------------------------------------------------------------------------
    */
    $growthChangeText = $tripGrowthTrend !== null
        ? ($tripGrowth >= 0 ? '▲ ' : '▼ ') . abs(round($tripGrowth, 1)) . '% vs prev'
        : 'Based on current period';
    $growthChangeType = $tripGrowthTrend !== null
        ? ($tripGrowth >= 0 ? 'positive' : 'negative')
        : 'neutral';

    $kpiCards = [
        [
            'label' => 'Recorded Trips',
            'value' => number_format($tripCount),
            'description' => 'Actual GPS trip records during the selected period.',
            'icon' => 'fa-route',
            'variant' => 'blue',
            'change' => $tripGrowthTrend !== null ? $growthChangeText : 'Historical GPS records',
            'change_type' => $growthChangeType,
        ],
        [
            'label' => 'GPS Records Flagged', 
            'value' => $tripCount > 0 ? (string) $displayTripsAtRisk : '—',
            'description' => 'Historical GPS records flagged by diagnostic thresholds; not future trip predictions.',
            'icon' => 'fa-triangle-exclamation',
            'variant' => 'red',
            'change' => $tripCount === 0 ? null : ($displayTripsAtRisk > 0 ? $displayTripsAtRisk . ' flagged for review' : 'No trips flagged'),
            'change_type' => $displayTripsAtRisk > 0 ? 'negative' : 'positive',
        ],
        [
            'label' => 'Delay Indicators',
            'value' => $tripCount > 0 ? (string) $displayPredictedDelays : '—',
            'description' => 'Historical delay and slow-movement indicators; not validated future delays.',
            'icon' => 'fa-clock',
            'variant' => 'yellow',
            'change' => $tripCount === 0 ? null : ($displayPredictedDelays > 0 ? $displayPredictedDelays . ' variance alerts' : 'No delays flagged'),
            'change_type' => $displayPredictedDelays > 0 ? 'negative' : 'positive',
        ],
        [
            'label' => 'Active Fleet Share',
            'value' => $displayUtilization,
            'description' => 'Current active buses as a share of the fleet.',
            'icon' => 'fa-chart-line',
            'variant' => 'green',
            'change' => $activeBuses . ' of ' . $totalBuses . ' buses active',
            'change_type' => 'positive',
        ],
        [
            'label' => 'High-Idle Records',
            'value' => $tripCount > 0 ? (string) $displayHighIdleRisk : '—',
            'description' => 'GPS records with elevated idle indicators; not a count of distinct buses.',
            'icon' => 'fa-clock',
            'variant' => 'yellow',
            'change' => $tripCount === 0 ? null : ($displayHighIdleRisk > 0 ? $displayHighIdleRisk . ' elevated units' : 'No high-idle risk flagged'),
            'change_type' => $displayHighIdleRisk > 0 ? 'warning' : 'positive',
        ],
        [
            'label' => 'Unflagged GPS Share',
            'value' => $displayCompletionForecast,
            'description' => 'Share of GPS records not flagged for review; not a trip completion prediction.',
            'icon' => 'fa-circle-check',
            'variant' => 'purple',
            'change' => $tripCount > 0 ? $growthChangeText : null,
            'change_type' => $growthChangeType,
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Trip Predictions & Route Risk Analysis Data
    |--------------------------------------------------------------------------
    */
    $tripPredictions = $tripPredictionsRaw;
    $routePredictions = $routePredictionsRaw;

    $topDelayedRoute = $routePredictionsRaw->sortByDesc(fn ($r) => (int) ($r[3] ?? 0))->first();
    $delayedRouteName = is_array($topDelayedRoute) ? ($topDelayedRoute[0] ?? 'primary transit corridors') : 'primary transit corridors';

    /*
    |--------------------------------------------------------------------------
    | Insights
    |--------------------------------------------------------------------------
    */
    $insights = collect();
    if ($tripGrowthTrend !== null && $tripCount > 0) {
        $insights->push([
            'title' => 'Trip activity trend',
            'text' => sprintf('Recorded trip volume is %s by %.1f%% compared with the prior period.', $tripGrowth >= 0 ? 'up' : 'down', abs($tripGrowth)),
            'icon' => 'fa-chart-line',
            'tone' => 'blue',
        ]);
    }
    if ($topDelayedRoute) {
        $insights->push([
            'title' => 'Historical route activity',
            'text' => sprintf('Route "%s" has the highest relative GPS activity and duration score among the available route records. This is not a predicted delay probability.', $delayedRouteName),
            'icon' => 'fa-clock',
            'tone' => 'orange',
        ]);
    }
    if ($displayHighIdleRisk > 0 && $tripCount > 0) {
        $insights->push([
            'title' => 'Idle behavior telemetry',
            'text' => sprintf('%d historical GPS records have elevated idle indicators; these are not distinct bus counts.', $displayHighIdleRisk),
            'icon' => 'fa-gas-pump',
            'tone' => 'yellow',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Chart Data Setup
    |--------------------------------------------------------------------------
    */
    $tripTrendMax = max(1, (int) $fleetTrend->max('count'));
@endphp

<div class="predictive-page predictive-fleet-page">

    <div class="ft-dashboard-filter-row" aria-label="Fleet analytics context">
        <div class="ft-dashboard-filter-heading">
            <strong><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Fleet performance overview</strong>
            <span>Recorded operations and source-labeled predictions</span>
        </div>
        @php
            $pythonStatus = $fleet['pythonServiceStatus'] ?? ['online' => false, 'eta_ready' => false];
        @endphp
        <div class="ft-python-status" role="status" aria-label="Python service and ETA model status">
            <span class="ft-python-status__item {{ $pythonStatus['online'] ? 'is-online' : 'is-offline' }}">
                <i class="fa-solid fa-circle" aria-hidden="true"></i>
                Python API {{ $pythonStatus['online'] ? 'Online' : 'Offline' }}
            </span>
            <span class="ft-python-status__item {{ $pythonStatus['eta_ready'] ? 'is-online' : 'is-muted' }}">
                <i class="fa-solid {{ $pythonStatus['eta_ready'] ? 'fa-circle-check' : 'fa-circle-info' }}" aria-hidden="true"></i>
                ETA ML {{ $pythonStatus['eta_ready'] ? 'Ready' : 'Not Ready' }}
            </span>
            <small>Checked on page load</small>
        </div>
    </div>

    {{-- =========================================================
        KPI CARDS STRIP (USING <x-analytics.kpi>)
    ========================================================== --}}
    <section class="analytics-kpi-strip ft-kpi-strip" aria-label="Predictive fleet metrics">
        @foreach ($kpiCards as $card)
            <x-analytics.kpi
                :label="$card['label']"
                :value="$card['value']"
                :description="$card['description']"
                :icon="$card['icon']"
                :icon-variant="$card['variant']"
                :change="$card['change']"
                :change-type="$card['change_type']"
            />
        @endforeach
    </section>

    {{-- Actual recorded activity and model-dependent risk, together on one page. --}}
    <section class="ft-top-grid">
        <x-analytics.card
            class="ft-card ft-chart-card"
            title="Trip Performance Trend"
            description="Recorded GPS trip volume for the selected reporting period; not a forecast."
        >
            @if ($tripCount === 0)
                <div class="ft-data-empty" role="status">
                    <span class="ft-data-empty__icon"><i class="fa-solid fa-route" aria-hidden="true"></i></span>
                    <strong>No trips recorded for {{ strtolower($periodText) }}</strong>
                    <span>Try a wider period or another bus to view recorded GPS activity.</span>
                </div>
            @else
                <div class="analytics-trip-bars" role="img" aria-label="Recorded GPS trips by reporting interval">
                    @foreach ($fleetTrend as $bucket)
                        <div class="analytics-trip-bars__item">
                            <span>{{ data_get($bucket, 'label') }}</span>
                            <div><i style="width: {{ ((int) data_get($bucket, 'count', 0) / $tripTrendMax) * 100 }}%"></i></div>
                            <strong>{{ number_format((int) data_get($bucket, 'count', 0)) }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="ft-evidence-note"><i class="fa-solid fa-circle-info"></i> On-time rate and actual delays require linked schedule and actual arrival records; unavailable results are not estimated.</p>
        </x-analytics.card>

        {{-- 2. Fleet & Trip Risk Level (Donut Chart) --}}
        <x-analytics.card
            class="ft-card ft-risk-card"
            title="Fleet & Trip Risk Level"
            description="Distribution of available future trip-level forecast risks."
        >
            @if ($fleetRiskTotal === 0)
                <div class="ft-data-empty" role="status">
                    <span class="ft-data-empty__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <strong>No risk distribution available</strong>
                    <span>{{ $forecastEmptyReason }}</span>
                </div>
            @else
            <div class="ft-risk-body">
                <div class="ft-risk-ring">
                    <canvas id="riskDonut" role="img" aria-label="Fleet and trip risk distribution donut"></canvas>
                    <div class="ft-risk-ring-center">
                        <strong id="riskDonutTotal">{{ number_format($fleetRiskTotal) }}</strong>
                        <span>Total Risk</span>
                    </div>
                </div>

                <ul class="ft-risk-list">
                    <li>
                        <span class="ft-risk-dot low"></span>
                        <span class="ft-risk-name">Low Risk</span>
                        <strong class="ft-risk-num">
                            {{ number_format($fleetRisk['low']) }}
                            <small>({{ number_format($fleetRiskPercent['low'], 1) }}%)</small>
                        </strong>
                    </li>
                    <li>
                        <span class="ft-risk-dot medium"></span>
                        <span class="ft-risk-name">Medium Risk</span>
                        <strong class="ft-risk-num">
                            {{ number_format($fleetRisk['medium']) }}
                            <small>({{ number_format($fleetRiskPercent['medium'], 1) }}%)</small>
                        </strong>
                    </li>
                    <li>
                        <span class="ft-risk-dot high"></span>
                        <span class="ft-risk-name">High Risk</span>
                        <strong class="ft-risk-num">
                            {{ number_format($fleetRisk['high']) }}
                            <small>({{ number_format($fleetRiskPercent['high'], 1) }}%)</small>
                        </strong>
                    </li>
                </ul>
            </div>
            @endif

        </x-analytics.card>

    </section>

    {{-- =========================================================
        TRIP PREDICTIONS TABLE (USING <x-analytics.card>)
    ========================================================== --}}
    <x-analytics.card
        id="tripPredictionsTable"
        class="ft-card ft-table-card"
        title="Trip Records & Risk Monitoring"
        description="Trip-level risk signals where model results or supported operational evidence are available."
    >
        <x-slot:headerActions>
            <a href="{{ route('trip-schedule') }}" class="ft-view-link">
                View all trip schedules
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </x-slot:headerActions>

        <div class="table-responsive">
            <table class="ft-table">
                <thead>
                    <tr>
                        <th>Trip ID</th>
                        <th>Bus</th>
                        <th>Route</th>
                        <th>Scheduled Date</th>
                        <th>Predicted Risk</th>
                        <th>Predicted Issue</th>
                        <th>Risk Level</th>
                        <th>Status</th>
                        <th>ML ETA</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($tripPredictions as $prediction)
                        @php
                            $delayRisk = (float) ($prediction[4] ?? 0);
                            $predictionLevel = $riskBadge((string) ($prediction[6] ?? 'low'));
                            $riskClass = match (true) {
                                $delayRisk >= 75 || $predictionLevel === 'high' => 'high',
                                $delayRisk >= 55 || $predictionLevel === 'medium' => 'medium',
                                default => 'low',
                            };
                        @endphp
                        <tr>
                            <td class="ft-cell-strong">
                                <x-ui.id-badge :value="$prediction[0] ?? '—'" />
                            </td>
                            <td>
                                <span class="table-bus-chip">
                                    <i class="fa-solid fa-bus" style="font-size: 9px; opacity: 0.75; margin-right: 3px;"></i>{{ $prediction[1] ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 5px; font-weight: 600; color: #1e293b;">
                                    <i class="fa-solid fa-route" style="color: #64748b; font-size: 11px;"></i>
                                    {{ $prediction[2] ?? '—' }}
                                </span>
                            </td>
                            <td>{{ $prediction[3] ?? '—' }}</td>
                            <td>
                                <div class="ft-riskbar">
                                    <span class="ft-risk-pct ft-risk-pct--{{ $riskClass }}">{{ number_format($delayRisk, 0) }}%</span>
                                    <span class="ft-track">
                                        <i class="ft-riskbar-fill ft-riskbar-fill--{{ $riskClass }}" style="width: {{ min(100, $delayRisk) }}%"></i>
                                    </span>
                                </div>
                            </td>
                            <td>{{ $prediction[5] ?? 'Possible Delay' }}</td>
                            <td>
                                <span class="ft-badge {{ $riskClass }}">
                                    {{ ucfirst($riskClass) }}
                                </span>
                            </td>
                            <td>@if(filled($prediction[7] ?? null))<x-ui.status-badge :status="$prediction[7]" />@else — @endif</td>
                            <td>
                                <span class="ft-ml-eta" title="{{ ($prediction['eta_source'] ?? null) === 'ml' ? 'Predicted by the ETA ML model' : 'Estimated from route history (ML service offline)' }}">
                                    <i class="fa-solid fa-clock"></i>
                                    <span>{{ $prediction[8] ?? '—' }}</span>
                                    @if (($prediction['eta_source'] ?? null) === 'ml')
                                        <em class="ft-eta-src ft-eta-src--ml">ML</em>
                                    @elseif (($prediction['eta_source'] ?? null) === 'statistical')
                                        <em class="ft-eta-src ft-eta-src--stat">Fallback</em>
                                    @endif
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('trip-schedule') }}" class="ft-action-chip" title="Inspect trip schedule and bus allocation">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Review
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($tripPredictions->isEmpty())
            <div class="ft-data-empty ft-data-empty--table" role="status">
                <span class="ft-data-empty__icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
                <strong>No eligible future trip forecasts available.</strong>
                <span>{{ $forecastEmptyReason }} Historical GPS records: {{ number_format($forecastHistoryCount) }}; upcoming eligible targets: {{ number_format($forecastTargetCount) }}.</span>
            </div>
        @endif
    </x-analytics.card>

    {{-- =========================================================
        LOWER 3-COLUMN GRID (USING <x-analytics.card>)
    ========================================================== --}}
    <section class="ft-lower-grid">

        <x-analytics.card
            class="ft-card ft-performance-card"
            title="Fleet Availability Overview"
            description="Actual registered bus statuses; not dispatch eligibility or time-based utilization."
        >
            <div class="ft-availability-summary">
                <div><span>Active</span><strong>{{ number_format($activeBuses) }}</strong></div>
                <div><span>Under Maintenance</span><strong>{{ number_format($maintenanceBuses) }}</strong></div>
                <div><span>Inactive</span><strong>{{ number_format($inactiveBuses) }}</strong></div>
                <div><span>Other statuses</span><strong>{{ number_format($otherStatusBuses) }}</strong></div>
            </div>
            <div class="ft-availability-detail">
                <div><strong>{{ number_format($totalBuses) }}</strong> registered buses</div>
                <div><strong>{{ $totalBuses > 0 ? number_format($activeBuses / $totalBuses * 100, 1) . '%' : '—' }}</strong> Active share</div>
            </div>
            <div class="ft-availability-progress" role="img" aria-label="Active buses: {{ $activeBuses }} of {{ $totalBuses }}">
                <span style="width: {{ $totalBuses > 0 ? ($activeBuses / $totalBuses) * 100 : 0 }}%"></span>
            </div>
            <p class="ft-evidence-note">Based on bus master status only. Dispatch eligibility requires separate Operations and Maintenance checks.</p>
        </x-analytics.card>

        {{-- Historical route activity score (not a predicted delay probability). --}}
        <x-analytics.card
            class="ft-card ft-route-card"
            title="Historical Route Indicators"
            description="Relative GPS activity and duration score (0–100), not a delay probability or ML forecast."
        >
            <div class="ft-route-list">
                @forelse ($routePredictions as $route)
                    @php
                        $routeLevel = $riskBadge((string) ($route[4] ?? 'low'));
                        $delayPct = (float) ($route[3] ?? 0);
                        $routeClass = match(true) {
                            $delayPct >= 75 || $routeLevel === 'high' => 'high',
                            $delayPct >= 55 || $routeLevel === 'medium' => 'medium',
                            default => 'low',
                        };
                    @endphp
                    <div class="ft-route-item ft-route-item--{{ $routeClass }}">
                        <div class="ft-route-info">
                            <div class="ft-route-title-row">
                                <strong class="ft-route-name" title="{{ $route[0] ?? '—' }}">
                                    {{ $route[0] ?? '—' }}
                                </strong>
                                <span class="ft-route-tag {{ $routeClass === 'high' ? 'warning' : 'success' }}"><i class="fa-solid fa-chart-column"></i> GPS History</span>
                            </div>
                            <div class="ft-route-meta">
                                <span><i class="fa-solid fa-bus"></i> {{ number_format((float) ($route[1] ?? 0)) }} trips</span>
                                <span><i class="fa-regular fa-clock"></i> {{ number_format((float) ($route[2] ?? 0)) }}m avg</span>
                                <span class="ft-route-variance {{ $delayPct >= 70 ? 'danger' : 'safe' }}">
                                    <i class="fa-solid fa-chart-line"></i> {{ number_format($delayPct, 0) }}/100 historical score
                                </span>
                            </div>
                            <div class="ft-route-meter-wrap">
                                <div class="ft-route-meter-track">
                                    <div class="ft-route-meter-fill ft-route-meter-fill--{{ $routeClass }}" style="width: {{ min(100, $delayPct) }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="ft-route-risk-col">
                            <div class="ft-route-score-wrap">
                                <span class="ft-route-score ft-route-score--{{ $routeClass }}">{{ number_format($delayPct, 0) }}/100</span>
                                <span class="ft-route-score-label">Activity Score</span>
                            </div>
                            <span class="ft-badge {{ $routeClass }}">
                                {{ $routeClass === 'high' ? 'Higher' : ($routeClass === 'medium' ? 'Moderate' : 'Lower') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="ft-data-empty ft-data-empty--compact" role="status">
                        <span class="ft-data-empty__icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                        <strong>No historical route records available</strong>
                        <span>Historical route indicators appear when processed GPS records are available for the selected filters.</span>
                    </div>
                @endforelse
            </div>
        </x-analytics.card>

        {{-- 3. Fleet & Trip Predictive Insights --}}
        <x-analytics.card
            class="ft-card ft-insights-card"
            title="Operational Insights"
        >
            <div class="ft-insights-grid">
                <div class="ft-insight-tile ft-insight-tile--blue">
                    <div class="ft-insight-icon blue"><i class="fa-solid fa-chart-line"></i></div>
                    <div class="ft-insight-body">
                        <h6>Future forecast availability</h6>
                        <p>{{ $forecastTargetCount }} upcoming eligible targets; {{ $tripPredictionsRaw->count() }} forecasts available. {{ $predictionAvailable ? 'Forecast service responded.' : 'Python forecasting unavailable; Laravel statistical estimates require at least five matching route records.' }}</p>
                    </div>
                </div>
                @forelse ($insights as $insight)
                    <div class="ft-insight-tile ft-insight-tile--{{ $insight['tone'] }}">
                        <div class="ft-insight-icon {{ $insight['tone'] }}">
                            <i class="fa-solid {{ $insight['icon'] }}"></i>
                        </div>
                        <div class="ft-insight-body">
                            <h6>{{ $insight['title'] }}</h6>
                            <p>{{ $insight['text'] }}</p>
                        </div>
                    </div>
                @empty
                    <div class="ft-data-empty ft-data-empty--compact" role="status">
                        <span class="ft-data-empty__icon"><i class="fa-solid fa-lightbulb" aria-hidden="true"></i></span>
                        <strong>No operational insights yet</strong>
                        <span>Insights appear when the selected period has trip or route signals.</span>
                    </div>
                @endforelse
            </div>
        </x-analytics.card>

    </section>

    {{-- =========================================================
        FOOTER DISCLAIMER
    ========================================================== --}}
    <div class="predictive-footer">
        <i class="fa-solid fa-circle-info"></i>
        <span>GPS activity and fleet status are recorded data. Risk and ETA values are displayed only when their source is available; statistical fallbacks are labeled.</span>
    </div>

</div>

{{-- =============================================================
    CHART DATA
============================================================= --}}
<script>
    window.predictiveChartData = {
        risk: {
            low: @json($fleetRisk['low']),
            medium: @json($fleetRisk['medium']),
            high: @json($fleetRisk['high']),
            total: @json($fleetRiskTotal)
        }
    };
</script>
