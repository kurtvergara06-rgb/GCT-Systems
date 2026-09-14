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
    $displayCompletionForecast = $completionForecastPct > 0 ? number_format($completionForecastPct, 1) . '%' : ($tripCount > 0 ? '100.0%' : '—');

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
            'label' => 'Trips at Risk',
            'value' => (string) $displayTripsAtRisk,
            'description' => 'Trips predicted to experience delays or operational issues.',
            'icon' => 'fa-triangle-exclamation',
            'variant' => 'red',
            'change' => $displayTripsAtRisk > 0 ? $displayTripsAtRisk . ' flagged for review' : 'Nominal schedule',
            'change_type' => $displayTripsAtRisk > 0 ? 'negative' : 'positive',
        ],
        [
            'label' => 'Predicted Delays',
            'value' => (string) $displayPredictedDelays,
            'description' => 'Trips likely to exceed their expected schedule.',
            'icon' => 'fa-clock',
            'variant' => 'yellow',
            'change' => $displayPredictedDelays > 0 ? $displayPredictedDelays . ' variance alerts' : 'On schedule',
            'change_type' => $displayPredictedDelays > 0 ? 'negative' : 'positive',
        ],
        [
            'label' => 'Fleet Utilization Forecast',
            'value' => $displayUtilization,
            'description' => 'Expected percentage of active fleet utilization.',
            'icon' => 'fa-chart-line',
            'variant' => 'green',
            'change' => $activeBuses . ' of ' . $totalBuses . ' buses active',
            'change_type' => 'positive',
        ],
        [
            'label' => 'High Idle Risk',
            'value' => (string) $displayHighIdleRisk,
            'description' => 'Buses predicted to experience excessive idle time.',
            'icon' => 'fa-clock',
            'variant' => 'yellow',
            'change' => $displayHighIdleRisk > 0 ? $displayHighIdleRisk . ' elevated units' : 'Efficient idling',
            'change_type' => $displayHighIdleRisk > 0 ? 'warning' : 'positive',
        ],
        [
            'label' => 'Trip Completion Forecast',
            'value' => $displayCompletionForecast,
            'description' => 'Predicted successful trip completion rate.',
            'icon' => 'fa-circle-check',
            'variant' => 'purple',
            'change' => $growthChangeText,
            'change_type' => $growthChangeType,
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Top Predicted Issues
    |--------------------------------------------------------------------------
    */
    $fleetIssues = collect($issues ?? [])->isNotEmpty()
        ? collect($issues)->map(fn ($iss) => [
            'title' => $iss['title'] ?? 'Operational Signal',
            'description' => $iss['description'] ?? 'Requires dispatch review.',
            'icon' => $iss['icon'] ?? 'fa-triangle-exclamation',
            'tone' => $iss['class'] ?? ($iss['tone'] ?? 'danger'),
            'level' => $iss['level'] ?? 'Medium',
            'count' => $iss['count'] ?? '1 item',
        ])->all()
        : [
            [
                'title' => 'Trip delay risk',
                'description' => 'Trips predicted to be delayed due to traffic and route conditions.',
                'icon' => 'fa-clock',
                'tone' => 'danger',
                'level' => 'High',
                'count' => $displayPredictedDelays . ' trips',
            ],
            [
                'title' => 'High idle risk',
                'description' => 'Buses predicted to have excessive idle time during operations.',
                'icon' => 'fa-clock',
                'tone' => 'warning',
                'level' => 'Medium',
                'count' => $displayHighIdleRisk . ' buses',
            ],
            [
                'title' => 'Route performance risk',
                'description' => 'Routes with negative performance based on historical trip duration.',
                'icon' => 'fa-route',
                'tone' => 'warning',
                'level' => 'Medium',
                'count' => max(1, $routePredictionsRaw->count()) . ' routes',
            ],
            [
                'title' => 'Fleet availability risk',
                'description' => 'Buses currently in maintenance or inactive.',
                'icon' => 'fa-bus-simple',
                'tone' => 'purple',
                'level' => 'Low',
                'count' => $unavailableBuses . ' buses',
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
    $insights = [
        [
            'title' => 'Trip activity trend',
            'text' => $tripGrowthTrend !== null
                ? sprintf('Trip volume is %s by %.1f%% compared to previous period.', $tripGrowth >= 0 ? 'up' : 'down', abs($tripGrowth))
                : 'Trip volume remains steady across scheduled operating corridors.',
            'icon' => 'fa-chart-line',
            'tone' => 'blue',
        ],
        [
            'title' => 'Route delay pattern',
            'text' => sprintf('Route "%s" exhibits elevated variance and delay exposure based on trip duration.', $delayedRouteName),
            'icon' => 'fa-clock',
            'tone' => 'orange',
        ],
        [
            'title' => 'Idle behavior telemetry',
            'text' => $displayHighIdleRisk > 0
                ? sprintf('%d units exceed recommended 15-minute idle threshold.', $displayHighIdleRisk)
                : 'Fleet idle intensity is within nominal operating bounds.',
            'icon' => 'fa-gas-pump',
            'tone' => 'yellow',
        ],
        [
            'title' => 'Fleet utilization forecast',
            'text' => sprintf('Active fleet availability is %s with %d serviceable shuttles.', $displayUtilization, $activeBuses),
            'icon' => 'fa-bus-simple',
            'tone' => 'green',
        ],
    ];

    /*
    |--------------------------------------------------------------------------
    | Chart Data Setup
    |--------------------------------------------------------------------------
    */
    $chartLabels = $fleetTrend->pluck('label')->values()->all();
    if (empty($chartLabels)) {
        $chartLabels = ['Day 1', 'Day 2', 'Day 3', 'Day 4', 'Day 5', 'Day 6', 'Day 7'];
    }

    $chartTripsAtRisk = [];
    $chartPredictedDelays = [];
    $chartHighIdleEvents = [];
    $chartRouteRisk = [];
    $perfActiveBuses = [];
    $perfTripVolume = [];
    $perfAvgDuration = [];

    foreach ($fleetTrend as $b) {
        $cnt = is_object($b) ? (int) ($b->count ?? 0) : (int) ($b['count'] ?? 0);
        $perfTripVolume[] = $cnt;
        $chartTripsAtRisk[] = (int) round($cnt * ($tripsAtRiskCount > 0 ? min(0.6, $tripsAtRiskCount / max(1, $tripCount)) : 0.15));
        $chartPredictedDelays[] = (int) round($cnt * ($predictedDelaysCount > 0 ? min(0.4, $predictedDelaysCount / max(1, $tripCount)) : 0.1));
        $chartHighIdleEvents[] = (int) round($cnt * ($highIdleRiskCount > 0 ? min(0.3, $highIdleRiskCount / max(1, $tripCount)) : 0.08));
        $chartRouteRisk[] = (int) round($cnt * 0.35);
        $perfActiveBuses[] = max(1, $activeBuses);
        $perfAvgDuration[] = (int) round($averageTripDuration > 0 ? $averageTripDuration : 35);
    }

    if (empty($perfTripVolume)) {
        $perfTripVolume = [24, 30, 28, 35, 30, 32, 36];
        $chartTripsAtRisk = [8, 10, 11, 15, 14, 17, 19];
        $chartPredictedDelays = [5, 7, 7, 8, 7, 10, 13];
        $chartHighIdleEvents = [4, 5, 5, 6, 5, 6, 7];
        $chartRouteRisk = [12, 13, 15, 14, 13, 14, 17];
        $perfActiveBuses = [52, 60, 58, 65, 59, 63, 68];
        $perfAvgDuration = [20, 35, 42, 45, 40, 48, 55];
    }
@endphp

<div class="predictive-page predictive-fleet-page">

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

    {{-- =========================================================
        MIDDLE 3-COLUMN GRID (USING <x-analytics.card>)
    ========================================================== --}}
    <section class="ft-top-grid">

        {{-- 1. Trip Risk Forecast (Line Chart) --}}
        <x-analytics.card
            class="ft-card ft-chart-card"
            title="Trip Risk Forecast"
            description="Predicted trip performance and operational risk for the selected period."
        >
            <x-slot:headerActions>
                <span class="ft-telemetry-badge">
                    <i class="fa-solid fa-bullseye"></i>
                    <span>Target On-Time: ≥ 92%</span>
                </span>
                <span class="ft-select-badge">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>{{ $periodText }}</span>
                </span>
            </x-slot:headerActions>

            <div class="ft-chart-legend" aria-hidden="true">
                <span>
                    <i class="legend-dot blue"></i>
                    Trips at Risk
                </span>
                <span>
                    <i class="legend-dot orange"></i>
                    Predicted Delays
                </span>
                <span>
                    <i class="legend-dot red"></i>
                    High Idle Events
                </span>
                <span>
                    <i class="legend-dot green"></i>
                    Route Risk
                </span>
            </div>

            <div class="ft-chart-wrapper ft-main-line-chart">
                <canvas id="tripRiskChart" role="img" aria-label="Trip risk forecast chart"></canvas>
            </div>
        </x-analytics.card>

        {{-- 2. Fleet & Trip Risk Level (Donut Chart) --}}
        <x-analytics.card
            class="ft-card ft-risk-card"
            title="Fleet & Trip Risk Level"
            description="Distribution of predicted operational risk."
        >
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

            {{-- Dispatch Confidence Index Ratio Meter --}}
            <div class="health-ratio-bar-wrap" style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #e2e8f0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <span style="font-size: 10px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.03em;">
                        <i class="fa-solid fa-shield-check" style="color: #10b981; margin-right: 4px;"></i> Dispatch Confidence Index
                    </span>
                    <strong style="font-size: 11.5px; font-weight: 800; color: #0f172a;">{{ $displayCompletionForecast }}</strong>
                </div>
                <div class="health-ratio-bar" title="{{ $displayCompletionForecast }} On-Time Dispatch Confidence">
                    <div class="ratio-segment ratio-healthy" style="width: {{ min(100, (float) $displayCompletionForecast) }}%"></div>
                    <div class="ratio-segment ratio-critical" style="width: {{ max(0, 100 - (float) $displayCompletionForecast) }}%"></div>
                </div>
            </div>
        </x-analytics.card>

        {{-- 3. Top Predicted Issues (Priority Cards) --}}
        <x-analytics.card
            class="ft-card ft-issues-card"
            title="Top Predicted Issues"
        >
            <div class="ft-issues-list">
                @foreach ($fleetIssues as $index => $issue)
                    <article class="ft-issue">
                        <div class="ft-issue-number">
                            {{ $index + 1 }}
                        </div>
                        <div class="ft-issue-icon {{ $issue['tone'] }}">
                            <i class="fa-solid {{ $issue['icon'] }}"></i>
                        </div>
                        <div class="ft-issue-content">
                            <h5>{{ $issue['title'] }}</h5>
                            <p>{{ $issue['description'] }}</p>
                        </div>
                        <span class="ft-badge {{ strtolower($issue['level']) }}">
                            {{ $issue['level'] }}
                        </span>
                        <strong class="ft-issue-count">
                            {{ $issue['count'] }}
                        </strong>
                    </article>
                @endforeach
            </div>

            <div class="ft-issues-footer">
                <a href="#" class="ft-view-link">
                    View all issues
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
        </x-analytics.card>

    </section>

    {{-- =========================================================
        TRIP PREDICTIONS TABLE (USING <x-analytics.card>)
    ========================================================== --}}
    <x-analytics.card
        id="tripPredictionsTable"
        class="ft-card ft-table-card"
        title="Trip Predictions"
        description="Trips identified as having potential operational risk."
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
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tripPredictions as $prediction)
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
                            <td>{{ $prediction[7] ?? 'Scheduled' }}</td>
                            <td style="text-align: right;">
                                <a href="{{ route('trip-schedule') }}" class="ft-action-chip" title="Inspect trip schedule and bus allocation">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="9" message="No trip predictions available for the selected filters." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-analytics.card>

    {{-- =========================================================
        LOWER 3-COLUMN GRID (USING <x-analytics.card>)
    ========================================================== --}}
    <section class="ft-lower-grid">

        {{-- 1. Fleet Performance Forecast --}}
        <x-analytics.card
            class="ft-card ft-performance-card"
            title="Fleet Performance Forecast"
            description="Historical performance vs forecast for the selected period."
        >
            <div class="ft-chart-legend ft-perf-legend" aria-hidden="true">
                <span>
                    <i class="legend-bar blue"></i>
                    Active Buses
                </span>
                <span>
                    <i class="legend-bar green"></i>
                    Trip Volume
                </span>
                <span>
                    <i class="legend-dot orange"></i>
                    Avg. Trip Duration (mins)
                </span>
            </div>

            <div class="ft-chart-wrapper ft-perf-chart-wrapper">
                <canvas id="performanceChart" role="img" aria-label="Fleet performance forecast chart"></canvas>
            </div>

            <div class="ft-perf-bottom-legend" aria-hidden="true">
                <span><i class="legend-solid-line"></i> Historical</span>
                <span><i class="legend-dashed-line"></i> Forecast</span>
            </div>
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
                                    <i class="fa-solid fa-chart-line"></i> {{ $delayPct >= 70 ? '+15m deviation' : 'nominal' }}
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
                    <div class="analytics-compact-empty">
                        <i class="fa-regular fa-folder-open"></i>
                        <span>No route risk data available.</span>
                    </div>
                @endforelse
            </div>
        </x-analytics.card>

        {{-- 3. Fleet & Trip Predictive Insights --}}
        <x-analytics.card
            class="ft-card ft-insights-card"
            title="Fleet & Trip Predictive Insights"
        >
            <div class="ft-insights-grid">
                @foreach ($insights as $insight)
                    <div class="ft-insight-tile ft-insight-tile--{{ $insight['tone'] }}">
                        <div class="ft-insight-icon {{ $insight['tone'] }}">
                            <i class="fa-solid {{ $insight['icon'] }}"></i>
                        </div>
                        <div class="ft-insight-body">
                            <h6>{{ $insight['title'] }}</h6>
                            <p>{{ $insight['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-analytics.card>

    </section>

    {{-- =========================================================
        FOOTER DISCLAIMER
    ========================================================== --}}
    <div class="predictive-footer">
        <i class="fa-solid fa-circle-info"></i>
        <span>Predictions are generated based on historical data and current trends. Results may vary.</span>
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
        },

        tripRisk: {
            labels: @json($chartLabels),
            trips_at_risk: @json($chartTripsAtRisk),
            predicted_delays: @json($chartPredictedDelays),
            high_idle_events: @json($chartHighIdleEvents),
            route_risk: @json($chartRouteRisk)
        },

        performance: {
            labels: @json($chartLabels),
            active_buses: @json($perfActiveBuses),
            trip_volume: @json($perfTripVolume),
            avg_duration: @json($perfAvgDuration)
        }
    };
</script>