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

    $tripCount = (int) ($fleet['tripCount'] ?? 0);
    $totalBuses = (int) ($fleet['totalBuses'] ?? 0);
    $activeBuses = (int) ($fleet['activeBuses'] ?? 0);
    $unavailableBuses = max(0, $totalBuses - $activeBuses);
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
            'label' => 'Trips at Risk',
            'value' => $tripCount > 0 ? (string) $displayTripsAtRisk : '—',
            'description' => 'Trips predicted to experience delays or operational issues.',
            'icon' => 'fa-triangle-exclamation',
            'variant' => 'red',
            'change' => $tripCount === 0 ? null : ($displayTripsAtRisk > 0 ? $displayTripsAtRisk . ' flagged for review' : 'No trips flagged'),
            'change_type' => $displayTripsAtRisk > 0 ? 'negative' : 'positive',
        ],
        [
            'label' => 'Predicted Delays',
            'value' => $tripCount > 0 ? (string) $displayPredictedDelays : '—',
            'description' => 'Trips likely to exceed their expected schedule.',
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
            'label' => 'High Idle Risk',
            'value' => $tripCount > 0 ? (string) $displayHighIdleRisk : '—',
            'description' => 'Buses predicted to experience excessive idle time.',
            'icon' => 'fa-clock',
            'variant' => 'yellow',
            'change' => $tripCount === 0 ? null : ($displayHighIdleRisk > 0 ? $displayHighIdleRisk . ' elevated units' : 'No high-idle risk flagged'),
            'change_type' => $displayHighIdleRisk > 0 ? 'warning' : 'positive',
        ],
        [
            'label' => 'Trip Completion Forecast',
            'value' => $displayCompletionForecast,
            'description' => 'Predicted successful trip completion rate.',
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
            'title' => 'Route delay pattern',
            'text' => sprintf('Route "%s" has the highest delay risk among the available route records.', $delayedRouteName),
            'icon' => 'fa-clock',
            'tone' => 'orange',
        ]);
    }
    if ($displayHighIdleRisk > 0 && $tripCount > 0) {
        $insights->push([
            'title' => 'Idle behavior telemetry',
            'text' => sprintf('%d units have a recorded high-idle risk signal.', $displayHighIdleRisk),
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

    <div class="ft-dashboard-filter-row" aria-label="Fleet analytics filters">
        <div class="ft-dashboard-filter-heading">
            <strong><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Fleet performance overview</strong>
            <span>Recorded operations and source-labeled predictions</span>
        </div>
        <form method="GET" action="{{ $predictiveUrl }}" class="ft-dashboard-filters">
            <input type="hidden" name="domain" value="fleet-trip">
            <label for="ft-dashboard-period">Period
                <select id="ft-dashboard-period" name="period" onchange="this.form.requestSubmit()">
                    <option value="this-month" @selected($period === 'this-month')>This Month</option>
                    <option value="this-week" @selected($period === 'this-week')>This Week</option>
                    <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                    <option value="last-90-days" @selected($period === 'last-90-days')>Last 90 Days</option>
                    <option value="last-12-months" @selected($period === 'last-12-months')>Last 12 Months</option>
                </select>
            </label>
            <label for="ft-dashboard-bus">Bus
                <select id="ft-dashboard-bus" name="bus" onchange="this.form.requestSubmit()">
                    <option value="all" @selected($normalizedSelectedBus === 'all')>All Buses</option>
                    @foreach($busOptions as $busNo)
                        <option value="{{ $busNo }}" @selected(strtoupper((string) $selectedBus) === strtoupper((string) $busNo))>{{ $busNo }}</option>
                    @endforeach
                </select>
            </label>
        </form>
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
            description="Distribution of predicted operational risk."
        >
            @if ($fleetRiskTotal === 0)
                <div class="ft-data-empty" role="status">
                    <span class="ft-data-empty__icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                    <strong>No risk distribution available</strong>
                    <span>Risk levels appear when trip predictions are available for the selected filters.</span>
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
                <strong>No trip predictions available for the selected filters.</strong>
                <span>Try another period or bus. Trip-level risk rows appear when prediction results are available.</span>
            </div>
        @endif
    </x-analytics.card>

    {{-- =========================================================
        LOWER 3-COLUMN GRID (USING <x-analytics.card>)
    ========================================================== --}}
    <section class="ft-lower-grid">

        <x-analytics.card
            class="ft-card ft-performance-card"
            title="Fleet Availability & Utilization"
            description="Actual bus master status snapshot, separate from predicted idle risk."
        >
            <div class="ft-availability-summary">
                <div><span>Active buses</span><strong>{{ number_format($activeBuses) }}</strong></div>
                <div><span>Other bus statuses</span><strong>{{ number_format($unavailableBuses) }}</strong></div>
                <div><span>Total registered</span><strong>{{ number_format($totalBuses) }}</strong></div>
                <div><span>Active fleet share</span><strong>{{ $totalBuses > 0 ? number_format($activeBuses / $totalBuses * 100, 1) . '%' : '—' }}</strong></div>
            </div>
            <p class="ft-evidence-note">Active share measures registered bus status, not time-based bus utilization.</p>
        </x-analytics.card>

        {{-- 2. Route Risk Analysis --}}
        <x-analytics.card
            class="ft-card ft-route-card"
            title="Route Risk Analysis"
            description="Routes ranked by predicted operational risk."
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
                                @if($delayPct >= 75)
                                    <span class="ft-route-tag danger"><i class="fa-solid fa-triangle-exclamation"></i> Bottleneck</span>
                                @elseif($delayPct >= 60)
                                    <span class="ft-route-tag warning"><i class="fa-solid fa-clock"></i> Idle Zone</span>
                                @else
                                    <span class="ft-route-tag success"><i class="fa-solid fa-circle-check"></i> Optimal</span>
                                @endif
                            </div>
                            <div class="ft-route-meta">
                                <span><i class="fa-solid fa-bus"></i> {{ number_format((float) ($route[1] ?? 0)) }} trips</span>
                                <span><i class="fa-regular fa-clock"></i> {{ number_format((float) ($route[2] ?? 0)) }}m avg</span>
                                <span class="ft-route-variance {{ $delayPct >= 70 ? 'danger' : 'safe' }}">
                                    <i class="fa-solid fa-chart-line"></i> {{ number_format($delayPct, 1) }}% delay risk
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
                                <span class="ft-route-score ft-route-score--{{ $routeClass }}">{{ number_format($delayPct, 0) }}%</span>
                                <span class="ft-route-score-label">Delay Risk</span>
                            </div>
                            <span class="ft-badge {{ $routeClass }}">
                                {{ ucfirst($routeClass) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="ft-data-empty ft-data-empty--compact" role="status">
                        <span class="ft-data-empty__icon"><i class="fa-solid fa-map-location-dot" aria-hidden="true"></i></span>
                        <strong>No route risk data available</strong>
                        <span>Route rankings appear when trip predictions include route evidence.</span>
                    </div>
                @endforelse
            </div>
        </x-analytics.card>

        {{-- 3. Fleet & Trip Predictive Insights --}}
        <x-analytics.card
            class="ft-card ft-insights-card"
            title="Operational Insights & Model Readiness"
        >
            <div class="ft-insights-grid">
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
