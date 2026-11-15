@php
    $stats = $stats ?? [
        'tripsAtRisk' => 8,
        'predictedDelays' => 12,
        'utilization' => 76.8,
        'highIdleRisk' => 6,
        'completionForecast' => 93.4,
    ];

    $issues = $issues ?? [
        [
            'rank' => 1,
            'title' => 'Trip delay risk',
            'description' => 'Trips predicted to be delayed due to historical route conditions.',
            'level' => 'High',
            'count' => '12 trips',
            'icon' => 'fa-clock',
            'class' => 'danger',
        ],
        [
            'rank' => 2,
            'title' => 'High idle risk',
            'description' => 'Buses predicted to have excessive idle time during operations.',
            'level' => 'Medium',
            'count' => '6 buses',
            'icon' => 'fa-clock-rotate-left',
            'class' => 'warning',
        ],
        [
            'rank' => 3,
            'title' => 'Route performance risk',
            'description' => 'Routes with negative performance based on historical trip data.',
            'level' => 'Medium',
            'count' => '5 routes',
            'icon' => 'fa-route',
            'class' => 'success',
        ],
        [
            'rank' => 4,
            'title' => 'Bus utilization risk',
            'description' => 'Buses predicted to have low utilization in the next 30 days.',
            'level' => 'Low',
            'count' => '3 buses',
            'icon' => 'fa-bus',
            'class' => 'purple',
        ],
    ];

    $predictions = $predictions ?? [
        ['TRIP-1025', 'Bus 07', 'Route 3 - Ayala - SM City', 'May 9, 2026 07:00 AM', 82, 'Possible Delay', 'High', 'Scheduled'],
        ['TRIP-1012', 'Bus 12', 'Route 5 - Talisay - Parkmall', 'May 9, 2026 08:00 AM', 74, 'High Idle Risk', 'Medium', 'Scheduled'],
        ['TRIP-1041', 'Bus 05', 'Route 2 - Fuente - Ayala', 'May 9, 2026 09:00 AM', 65, 'Route Performance Risk', 'Medium', 'Scheduled'],
        ['TRIP-1017', 'Bus 03', 'Route 1 - Talamban - IT Park', 'May 9, 2026 10:00 AM', 58, 'Extended Trip Duration', 'Low', 'Scheduled'],
        ['TRIP-1050', 'Bus 09', 'Route 4 - Parkmall - SM City', 'May 9, 2026 11:00 AM', 48, 'High Idle Risk', 'Low', 'Scheduled'],
    ];

    $routes = $routes ?? [
        ['Route 3 - Ayala - SM City', 48, 78, 82, 'High'],
        ['Route 5 - Talisay - Parkmall', 42, 65, 74, 'Medium'],
        ['Route 2 - Fuente - Ayala', 55, 70, 65, 'Medium'],
        ['Route 1 - Talamban - IT Park', 36, 55, 58, 'Low'],
        ['Route 4 - Parkmall - SM City', 40, 50, 48, 'Low'],
    ];
@endphp

<div class="predictive-fleet-page">

    {{-- KPI CARDS --}}
    <section class="predictive-kpis">

        <article class="predictive-kpi">
            <div class="predictive-kpi-icon danger">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <div>
                <span>Trips at Risk</span>
                <strong>{{ $stats['tripsAtRisk'] }}</strong>
                <small><b class="trend-up">▲ 33%</b> vs last month</small>
            </div>
        </article>

        <article class="predictive-kpi">
            <div class="predictive-kpi-icon warning">
                <i class="fa-solid fa-clock"></i>
            </div>

            <div>
                <span>Predicted Delays</span>
                <strong>{{ $stats['predictedDelays'] }}</strong>
                <small><b class="trend-up">▲ 28%</b> vs last month</small>
            </div>
        </article>

        <article class="predictive-kpi">
            <div class="predictive-kpi-icon success">
                <i class="fa-solid fa-chart-line"></i>
            </div>

            <div>
                <span>Fleet Utilization Forecast</span>
                <strong>{{ $stats['utilization'] }}%</strong>
                <small><b class="trend-good">▲ 5.4%</b> vs last month</small>
            </div>
        </article>

        <article class="predictive-kpi">
            <div class="predictive-kpi-icon warning">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>

            <div>
                <span>High Idle Risk</span>
                <strong>{{ $stats['highIdleRisk'] }}</strong>
                <small><b class="trend-up">▲ 20%</b> vs last month</small>
            </div>
        </article>

        <article class="predictive-kpi">
            <div class="predictive-kpi-icon purple">
                <i class="fa-solid fa-circle-check"></i>
            </div>

            <div>
                <span>Trip Completion Forecast</span>
                <strong>{{ $stats['completionForecast'] }}%</strong>
                <small><b class="trend-good">▲ 2.8%</b> vs last month</small>
            </div>
        </article>

    </section>


    {{-- MAIN FORECAST --}}
    <section class="predictive-main-grid">

        <article class="predictive-card forecast-card">

            <div class="card-heading">
                <div>
                    <h3>Trip Risk Forecast</h3>
                    <p>Predicted trip performance and operational risk for the selected period.</p>
                </div>

                <select>
                    <option>This Month</option>
                    <option>Last 30 Days</option>
                </select>
            </div>

            <div class="chart-container large-chart">
                <canvas id="tripRiskChart"></canvas>
            </div>

        </article>


        <article class="predictive-card risk-card">

            <div class="card-heading">
                <div>
                    <h3>Fleet & Trip Risk Level</h3>
                    <p>Overall predicted operational risk.</p>
                </div>
            </div>

            <div class="risk-content">

                <div class="donut-wrapper">
                    <canvas id="riskDonut"></canvas>

                    <div class="donut-center">
                        <strong id="riskDonutTotal">{{ number_format($predictive?->all->risk->total ?? 0) }}</strong>
                        <span>Predicted<br>Records</span>
                    </div>
                </div>

                <div class="risk-legend">

                    <div>
                        <span class="legend-dot low"></span>
                        <span>Low Risk</span>
                        <strong>{{ number_format($predictive?->all->risk->low ?? 0) }} ({{ ($predictive?->all->risk->total ?? 0) > 0 ? number_format((($predictive->all->risk->low ?? 0) / $predictive->all->risk->total) * 100, 1) : 0 }}%)</strong>
                    </div>

                    <div>
                        <span class="legend-dot medium"></span>
                        <span>Medium Risk</span>
                        <strong>{{ number_format($predictive?->all->risk->medium ?? 0) }} ({{ ($predictive?->all->risk->total ?? 0) > 0 ? number_format((($predictive->all->risk->medium ?? 0) / $predictive->all->risk->total) * 100, 1) : 0 }}%)</strong>
                    </div>

                    <div>
                        <span class="legend-dot high"></span>
                        <span>High Risk</span>
                        <strong>{{ number_format($predictive?->all->risk->high ?? 0) }} ({{ ($predictive?->all->risk->total ?? 0) > 0 ? number_format((($predictive->all->risk->high ?? 0) / $predictive->all->risk->total) * 100, 1) : 0 }}%)</strong>
                    </div>

                </div>

            </div>

        </article>


        <article class="predictive-card issues-card">

            <div class="card-heading">
                <div>
                    <h3>Top Predicted Issues</h3>
                    <p>Operational risks based on current trends.</p>
                </div>
            </div>

            <div class="issue-list">

                @forelse($issues as $issue)

                    <div class="issue-row">

                        <span class="issue-rank">
                            {{ $issue['rank'] }}
                        </span>

                        <div class="issue-icon {{ $issue['class'] }}">
                            <i class="fa-solid {{ $issue['icon'] }}"></i>
                        </div>

                        <div class="issue-info">
                            <strong>{{ $issue['title'] }}</strong>
                            <span>{{ $issue['description'] }}</span>
                        </div>

                        <span class="risk-badge {{ strtolower($issue['level']) }}">
                            {{ $issue['level'] }}
                        </span>

                        <strong class="issue-count">
                            {{ $issue['count'] }}
                        </strong>

                    </div>

                @empty

                    <div class="issue-row">
                        <div class="issue-info">
                            <strong>No predicted issues</strong>
                            <span>No risk signals were found for the selected period.</span>
                        </div>
                    </div>

                @endforelse

            </div>

            <a href="#" class="card-link">
                View all issues
                <i class="fa-solid fa-arrow-right"></i>
            </a>

        </article>

    </section>


    {{-- TRIP PREDICTIONS --}}
    <section class="predictive-card predictions-card">

        <div class="card-heading">
            <div>
                <h3>Trip Predictions</h3>
                <p>Trips identified as having potential operational risk.</p>
            </div>

            <a href="#" class="card-link">
                View all trip predictions
                <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="table-responsive">

            <table class="predictive-table">

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
                    </tr>
                </thead>

                <tbody>

                    @forelse($predictions as $prediction)

                        <tr>

                            <td>{{ $prediction[0] }}</td>
                            <td>{{ $prediction[1] }}</td>
                            <td>{{ $prediction[2] }}</td>
                            <td>{{ $prediction[3] }}</td>

                            <td>

                                <div class="risk-progress">

                                    <span>{{ $prediction[4] }}%</span>

                                    <div class="progress-track">
                                        <div
                                            class="progress-fill"
                                            style="width: {{ $prediction[4] }}%"
                                        ></div>
                                    </div>

                                </div>

                            </td>

                            <td>{{ $prediction[5] }}</td>

                            <td>
                                <span class="risk-badge {{ strtolower($prediction[6]) }}">
                                    {{ $prediction[6] }}
                                </span>
                            </td>

                            <td>{{ $prediction[7] }}</td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="8">No upcoming scheduled trips have enough comparable prediction history yet.</td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </section>


    {{-- BOTTOM GRID --}}
    <section class="predictive-bottom-grid">


        {{-- PERFORMANCE FORECAST --}}
        <article class="predictive-card">

            <div class="card-heading">
                <div>
                    <h3>Fleet Performance Forecast</h3>
                    <p>Historical performance vs forecast.</p>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>

        </article>


        {{-- ROUTE RISK --}}
        <article class="predictive-card">

            <div class="card-heading">
                <div>
                    <h3>Route Risk Analysis</h3>
                    <p>Routes ranked by predicted operational risk.</p>
                </div>
            </div>

            <div class="table-responsive">

                <table class="predictive-table route-table">

                    <thead>
                        <tr>
                            <th>Route</th>
                            <th>Total Trips</th>
                            <th>Avg. Duration</th>
                            <th>Delay Risk</th>
                            <th>Overall Risk</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($routes as $route)

                            <tr>

                                <td>{{ $route[0] }}</td>
                                <td>{{ $route[1] }}</td>
                                <td>{{ $route[2] }} min</td>
                                <td>{{ $route[3] }}%</td>

                                <td>
                                    <span class="risk-badge {{ strtolower($route[4]) }}">
                                        {{ $route[4] }}
                                    </span>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td colspan="5">No route records are available for the selected period.</td>
                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </article>


        {{-- INSIGHTS --}}
        <article class="predictive-card insights-card">

            <div class="card-heading">
                <div>
                    <h3>Fleet & Trip Predictive Insights</h3>
                    <p>Forecast insights generated from current trends.</p>
                </div>
            </div>

            <div class="insight-grid">

                <div class="insight-item">
                    <div class="insight-icon blue">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>

                    <div>
                        <strong>Trip activity trend</strong>
                        <p>Trip volume is expected to increase by 8% in the next 30 days.</p>
                    </div>
                </div>

                <div class="insight-item">
                    <div class="insight-icon orange">
                        <i class="fa-solid fa-clock"></i>
                    </div>

                    <div>
                        <strong>Route delay pattern</strong>
                        <p>Route 3 has the highest predicted delay risk.</p>
                    </div>
                </div>

                <div class="insight-item">
                    <div class="insight-icon yellow">
                        <i class="fa-solid fa-bus"></i>
                    </div>

                    <div>
                        <strong>Idle behavior trend</strong>
                        <p>Idle time is expected to increase by 14%.</p>
                    </div>
                </div>

                <div class="insight-item">
                    <div class="insight-icon green">
                        <i class="fa-solid fa-chart-pie"></i>
                    </div>

                    <div>
                        <strong>Fleet utilization forecast</strong>
                        <p>Fleet utilization is forecasted to remain stable.</p>
                    </div>
                </div>

            </div>

        </article>

    </section>


    <p class="predictive-footer">
        Predictions are generated based on historical data and current trends. Results may vary.
    </p>

</div>


@php
    $fleetTrend = collect($fleet['trend'] ?? [])->values();
    $fleetTrendLabels = $fleetTrend->map(fn ($bucket) => $bucket->label ?? '')->values();
    $fleetTrendCounts = $fleetTrend->map(fn ($bucket) => (int) ($bucket->count ?? 0))->values();
    $fleetForecastCounts = $fleetTrendCounts->map(fn ($value) => round($value * (1 + max(-0.25, min(0.25, ((float) ($fleet['tripGrowth'] ?? 0)) / 100)))))->values();
@endphp

<script>
    window.predictiveChartData = {
        risk: {
            low: {{ $predictive?->all->risk->low ?? 0 }},
            medium: {{ $predictive?->all->risk->medium ?? 0 }},
            high: {{ $predictive?->all->risk->high ?? 0 }},
            total: {{ $predictive?->all->risk->total ?? 0 }},
        },
        tripRisk: {
            labels: @json($fleetTrendLabels),
            values: @json($fleetTrendCounts),
        },
        performance: {
            labels: @json($fleetTrendLabels),
            recorded: @json($fleetTrendCounts),
            forecast: @json($fleetForecastCounts),
        },
    };
</script>