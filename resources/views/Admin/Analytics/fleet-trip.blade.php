<x-layout.app
    title="FROMS - Fleet & Trip Analytics"
    :assets="[
        'resources/css/Admin/Analytics/fleet-trip.css',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />

        <main class="main fleet-trip-page">
            <x-layout.topbar
                title="Fleet & Trip Analytics"
                subtitle="Analyze distance, speed, idle time, trip duration, operating patterns, ETA, and delay risk"
                notification-count="6"
            />

            <section class="trip-hero">
                <div class="trip-hero-content">
                    <span class="trip-hero-label">
                        <i class="fa-solid fa-route"></i>
                        {{ $prediction->available ? 'Live Descriptive, Diagnostic & Predictive Analytics' : 'Live Descriptive & Diagnostic Analytics' }}
                    </span>

                    <h2>
                        {{ number_format($tripCount) }} processed GPS trip records are included in the current {{ strtolower($periodLabel) }} view.
                    </h2>

                    <p>
                        Fleet & Trip Analytics reads processed GPS Trip Records for mileage, motion time,
                        idle time, duration, route activity, and explainable diagnostics. Python forecasting
                        uses historical records plus real upcoming Trip Schedules when the Python Engine is available.
                    </p>

                    <div class="trip-hero-stats">
                        <div>
                            <span>Trip Records</span>
                            <strong>{{ number_format($tripCount) }}</strong>
                        </div>

                        <div>
                            <span>Trips for Review</span>
                            <strong>{{ number_format($diagnostics->review_count) }}</strong>
                        </div>

                        <div>
                            <span>Python Forecasts</span>
                            <strong>{{ $prediction->available ? number_format($prediction->predicted_target_count) : 'Offline' }}</strong>
                        </div>
                    </div>
                </div>

                <div class="trip-hero-visual">
                    <div class="trip-route-line">
                        <div class="route-node start"><i class="fa-solid fa-location-dot"></i></div>
                        <div class="route-track">
                            <span class="route-progress"></span>
                            <div class="bus-marker"><i class="fa-solid fa-bus"></i></div>
                        </div>
                        <div class="route-node finish"><i class="fa-solid fa-location-dot"></i></div>
                    </div>

                    <div class="trip-route-meta">
                        <span>Current Filter</span>
                        <strong>{{ $selectedBus === 'ALL' ? 'All Buses' : $selectedBus }}</strong>
                        <small>{{ $periodLabel }} · Processed GPS records only</small>
                    </div>
                </div>
            </section>

            <section class="section-heading">
                <div>
                    <span class="section-kicker">5.1 Descriptive Analytics</span>
                    <h2>Trip Performance Indicators</h2>
                    <p>Calculated directly from processed GPS Trip Records in the selected period.</p>
                </div>

                <span class="period-pill">{{ $periodLabel }}</span>
            </section>

            <section data-ajax-region="summary" class="stats-grid fleet-summary-grid">
                <x-ui.summary-card
                    label="Distance Traveled"
                    :value="number_format($totalDistance, 1) . ' km'"
                    small="Sum of recorded GPS mileage"
                    icon="fa-road"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Average Speed"
                    :value="number_format($averageSpeed, 1) . ' km/h'"
                    small="Distance divided by recorded motion time"
                    icon="fa-gauge-high"
                    color="green"
                />

                <x-ui.summary-card
                    label="Idle Time"
                    :value="number_format($totalIdleMinutes / 60, 1) . ' hrs'"
                    small="Sum of recorded idling minutes"
                    icon="fa-hourglass-half"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Avg. Trip Duration"
                    :value="number_format($averageTripDuration, 1) . ' min'"
                    small="Average recorded trip duration"
                    icon="fa-clock"
                    color="blue"
                />
            </section>

            <section class="fleet-filter-bar">
                <div>
                    <span class="section-kicker">Trip Analysis</span>
                    <h2>Fleet Performance</h2>
                    <p>Filter live descriptive, diagnostic, and historical forecasting data by period and shuttle unit.</p>
                </div>

                <form class="fleet-filters" method="GET" action="{{ route('analytics.fleet-trip') }}">
                    <select name="period" aria-label="Analysis period">
                        <option value="this-month" @selected($period === 'this-month')>This Month</option>
                        <option value="last-30-days" @selected($period === 'last-30-days')>Last 30 Days</option>
                        <option value="last-3-months" @selected($period === 'last-3-months')>Last 3 Months</option>
                        <option value="this-year" @selected($period === 'this-year')>This Year</option>
                    </select>

                    <select name="bus" aria-label="Shuttle bus">
                        <option value="all" @selected($selectedBus === 'ALL')>All Buses</option>
                        @foreach($busOptions as $busOption)
                            <option
                                value="{{ $busOption->bus_no }}"
                                @selected($selectedBus === strtoupper(trim($busOption->bus_no)))
                            >
                                {{ $busOption->bus_no }}
                            </option>
                        @endforeach
                    </select>

                    <button type="submit" class="fleet-filter-button">
                        <i class="fa-solid fa-filter"></i>
                        Apply
                    </button>
                </form>
            </section>

            <section class="trip-primary-grid">
                <article class="trip-panel trip-activity-panel">
                    <div class="trip-panel-header">
                        <div>
                            <span class="section-kicker">Descriptive Analytics</span>
                            <h2>Trip Activity Trend</h2>
                            <p>Processed GPS records distributed across the selected analysis window.</p>
                        </div>

                        @if($tripGrowth !== null)
                            <span class="trend-badge {{ $tripGrowth >= 0 ? 'positive' : 'negative' }}">
                                <i class="fa-solid {{ $tripGrowth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }}"></i>
                                {{ $tripGrowth >= 0 ? '+' : '' }}{{ number_format($tripGrowth, 1) }}%
                            </span>
                        @else
                            <span class="trend-badge neutral">No prior baseline</span>
                        @endif
                    </div>

                    @php
                        $trendMax = max(1, (int) $trend->max('count'));
                        $scale = [
                            $trendMax,
                            (int) round($trendMax * 0.75),
                            (int) round($trendMax * 0.50),
                            (int) round($trendMax * 0.25),
                            0,
                        ];
                    @endphp

                    <div class="trip-chart-area">
                        <div class="chart-scale">
                            @foreach($scale as $scaleValue)
                                <span>{{ $scaleValue }}</span>
                            @endforeach
                        </div>

                        <div class="trip-bars">
                            <div class="chart-grid-line grid-1"></div>
                            <div class="chart-grid-line grid-2"></div>
                            <div class="chart-grid-line grid-3"></div>
                            <div class="chart-grid-line grid-4"></div>

                            @foreach($trend as $bucket)
                                <div class="trip-bar-column">
                                    <span class="bar-value">{{ $bucket->count }}</span>
                                    <div class="trip-bar" style="height: {{ $bucket->height }}%;"></div>
                                    <small>{{ $bucket->label }}</small>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="trip-chart-footer">
                        <div>
                            <span class="chart-dot"></span>
                            <span>Processed GPS Trip Records</span>
                        </div>
                        <strong>{{ number_format($tripCount) }} Total</strong>
                    </div>
                </article>

                <article class="trip-panel fleet-availability-panel">
                    <div class="trip-panel-header">
                        <div>
                            <span class="section-kicker">Fleet Status</span>
                            <h2>Fleet Availability</h2>
                            <p>Current Bus Master List status, independent of the trip-period filter.</p>
                        </div>
                    </div>

                    <div class="availability-score">
                        <div
                            class="availability-ring"
                            style="--availability-angle: {{ min(360, max(0, $fleetAvailability * 3.6)) }}deg;"
                        >
                            <div class="availability-ring-center">
                                <strong>{{ number_format($fleetAvailability, 1) }}%</strong>
                                <span>Active</span>
                            </div>
                        </div>
                    </div>

                    <div class="availability-breakdown">
                        <div class="availability-row">
                            <div><span class="availability-dot operational"></span><span>Active</span></div>
                            <strong>{{ number_format($activeBuses) }}</strong>
                        </div>
                        <div class="availability-row">
                            <div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div>
                            <strong>{{ number_format($underMaintenance) }}</strong>
                        </div>
                        <div class="availability-row">
                            <div><span class="availability-dot inactive"></span><span>Inactive</span></div>
                            <strong>{{ number_format($inactiveBuses) }}</strong>
                        </div>
                    </div>
                </article>
            </section>

            <section class="trip-panel route-leaderboard-panel">
                <div class="trip-panel-header">
                    <div>
                        <span class="section-kicker">Descriptive Route Analysis</span>
                        <h2>Route Performance Comparison</h2>
                        <p>Top recorded origin-to-destination pairs by GPS trip count and average duration.</p>
                    </div>
                    <span class="period-pill">{{ $periodLabel }}</span>
                </div>

                <div class="route-leaderboard">
                    @forelse($routes as $route)
                        <div class="route-ranking {{ $loop->first ? 'first' : '' }}">
                            <div class="ranking-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="route-ranking-icon"><i class="fa-solid fa-route"></i></div>
                            <div class="route-ranking-content">
                                <strong>{{ $route->label }}</strong>
                                <span>
                                    {{ number_format($route->trips) }} records ·
                                    {{ $route->average_duration > 0 ? number_format($route->average_duration, 1) . ' min avg. duration' : 'duration unavailable' }}
                                </span>
                                <div class="route-ranking-progress"><span style="width: {{ $route->progress }}%;"></span></div>
                            </div>
                            <div class="route-ranking-value">
                                <strong>{{ number_format($route->share, 1) }}%</strong>
                                <span>of filtered records</span>
                            </div>
                        </div>
                    @empty
                        <div class="route-ranking first">
                            <div class="ranking-number">—</div>
                            <div class="route-ranking-icon"><i class="fa-solid fa-route"></i></div>
                            <div class="route-ranking-content">
                                <strong>No processed GPS trip records found</strong>
                                <span>Change the selected period or bus filter to review another data window.</span>
                            </div>
                            <div class="route-ranking-value"><strong>0</strong><span>records</span></div>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="trip-utilization-section">
                <div class="section-heading">
                    <div>
                        <span class="section-kicker">Descriptive Fleet Comparison</span>
                        <h2>Bus Activity</h2>
                        <p>Compare each bus using its share of filtered GPS trip records and recorded distance.</p>
                    </div>
                    <span class="diagnostic-pill">Live Records</span>
                </div>

                <div class="utilization-card-grid">
                    @forelse($busActivity as $bus)
                        @php
                            $statusClass = match($bus->status) {
                                'Under Maintenance' => 'maintenance',
                                'Active' => 'normal',
                                default => 'high',
                            };
                        @endphp

                        <article class="bus-utilization-card">
                            <div class="bus-utilization-header">
                                <div class="bus-identity">
                                    <div class="bus-icon"><i class="fa-solid fa-bus"></i></div>
                                    <div>
                                        <strong>{{ $bus->bus }}</strong>
                                        <span>{{ number_format($bus->trips) }} trip records</span>
                                    </div>
                                </div>
                                <span class="usage-badge {{ $statusClass }}">{{ $bus->status }}</span>
                            </div>

                            <div class="bus-utilization-score">
                                <strong>{{ number_format($bus->share, 1) }}%</strong>
                                <span>Share of filtered trip records</span>
                            </div>

                            <div class="bus-utilization-progress">
                                <span class="{{ $statusClass }}" style="width: {{ min(100, $bus->share) }}%;"></span>
                            </div>

                            <div class="bus-utilization-details">
                                <div>
                                    <span>Distance</span>
                                    <strong>{{ number_format($bus->distance, 1) }} km</strong>
                                </div>
                                <div>
                                    <span>Avg. Trip</span>
                                    <strong>{{ number_format($bus->average_trip_distance, 1) }} km</strong>
                                </div>
                            </div>
                        </article>
                    @empty
                        <article class="bus-utilization-card">
                            <div class="bus-utilization-header">
                                <div class="bus-identity">
                                    <div class="bus-icon"><i class="fa-solid fa-bus"></i></div>
                                    <div>
                                        <strong>No bus activity</strong>
                                        <span>No processed GPS records match this filter.</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforelse
                </div>
            </section>

            <section class="section-heading diagnostic-heading">
                <div>
                    <span class="section-kicker">5.2 Diagnostic Analytics</span>
                    <h2>Delay & Operating Pattern Review</h2>
                    <p>Explainable rule-based findings using comparable route duration, moving speed, and idling behavior.</p>
                </div>
                <span class="diagnostic-pill">Live Diagnostics</span>
            </section>

            <section class="stats-grid fleet-summary-grid diagnostic-summary-grid">
                <x-ui.summary-card
                    label="Delay Indicators"
                    :value="number_format($diagnostics->delay_count)"
                    small="Duration above route baseline threshold"
                    icon="fa-clock-rotate-left"
                    color="red"
                />

                <x-ui.summary-card
                    label="Slow-Movement Patterns"
                    :value="number_format($diagnostics->slow_movement_count)"
                    small="Moving speed below 80% of route median"
                    icon="fa-gauge-simple-low"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="High-Idling Trips"
                    :value="number_format($diagnostics->high_idle_count)"
                    small="15+ idle min and at least 20% of trip time"
                    icon="fa-hourglass-half"
                    color="yellow"
                />

                <x-ui.summary-card
                    label="Baseline Coverage"
                    :value="number_format($diagnostics->baseline_coverage_percent, 1) . '%'"
                    :small="number_format($diagnostics->baseline_covered) . ' records have a 3+ trip route baseline'"
                    icon="fa-chart-column"
                    color="blue"
                />
            </section>

            <section class="trip-panel route-leaderboard-panel diagnostic-review-panel">
                <div class="trip-panel-header">
                    <div>
                        <span class="section-kicker">Diagnostic Evidence</span>
                        <h2>Trips Requiring Review</h2>
                        <p>
                            A delay indicator means duration is above both 120% of the route median and at least 10 minutes above it.
                            Slow movement and high idling are contributing patterns, not automatic proof of traffic or mechanical cause.
                        </p>
                    </div>
                    <span class="diagnostic-pill">{{ number_format($diagnostics->review_count) }} Flagged</span>
                </div>

                <div class="route-leaderboard">
                    @forelse($diagnostics->top_records as $diagnostic)
                        <div class="route-ranking {{ $loop->first ? 'first' : '' }} diagnostic-record">
                            <div class="ranking-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                            <div class="route-ranking-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                            <div class="route-ranking-content">
                                <strong>
                                    {{ $diagnostic->record->bus_no ?: 'Unknown Bus' }} · {{ $diagnostic->route }}
                                </strong>
                                <span>
                                    {{ $diagnostic->record->record_no ?: 'GPS record' }} ·
                                    {{ number_format($diagnostic->duration, 1) }} min duration ·
                                    {{ number_format($diagnostic->speed, 1) }} km/h ·
                                    {{ number_format($diagnostic->idle_minutes, 1) }} idle min
                                </span>
                                @if($diagnostic->has_baseline)
                                    <small class="diagnostic-baseline">
                                        Route median: {{ number_format($diagnostic->baseline_duration, 1) }} min ·
                                        {{ number_format($diagnostic->baseline_speed, 1) }} km/h
                                    </small>
                                @endif
                            </div>
                            <div class="route-ranking-value">
                                <strong>{{ $diagnostic->factors->implode(' + ') }}</strong>
                                <span>{{ $diagnostic->record->beginning_at?->format('M j, g:i A') }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="route-ranking first">
                            <div class="ranking-number">—</div>
                            <div class="route-ranking-icon"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="route-ranking-content">
                                <strong>No trips meet the current diagnostic review rules.</strong>
                                <span>Records without at least three comparable trips are not classified as delayed or slow-moving.</span>
                            </div>
                            <div class="route-ranking-value"><strong>0</strong><span>flagged</span></div>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="trip-findings-layout diagnostic-findings-layout">
                <div class="trip-findings-heading diagnostic-method-heading">
                    <span class="section-kicker">Diagnostic Interpretation</span>
                    <h2>What may be contributing?</h2>
                    <p>
                        FROMS reports measurable patterns and preserves the distinction between evidence and cause.
                        A slow or idle trip can suggest congestion or operational delay, but does not prove the root cause by itself.
                    </p>
                </div>

                <div class="trip-findings-list">
                    <article class="trip-finding warning">
                        <div class="finding-icon"><i class="fa-solid fa-car-side"></i></div>
                        <div>
                            <span>Delay + Slow Movement</span>
                            <strong>{{ number_format($diagnostics->delayed_with_slow_movement) }} delayed trips also show below-baseline moving speed.</strong>
                            <p>These are candidates for congestion or slow-movement review, not confirmed traffic incidents.</p>
                        </div>
                    </article>

                    <article class="trip-finding warning">
                        <div class="finding-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div>
                            <span>Delay + High Idling</span>
                            <strong>{{ number_format($diagnostics->delayed_with_high_idle) }} delayed trips also show significant idling.</strong>
                            <p>Review stop duration, dispatch activity, loading/waiting time, and traffic context before assigning a cause.</p>
                        </div>
                    </article>

                    <article class="trip-finding info">
                        <div class="finding-icon"><i class="fa-solid fa-route"></i></div>
                        <div>
                            <span>Route Deviation</span>
                            <strong>Not classified from the current GPS record structure.</strong>
                            <p>A full traveled path or sequence of coordinates is required before FROMS can defensibly compare actual travel against route geometry.</p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="section-heading diagnostic-heading">
                <div>
                    <span class="section-kicker">5.3 Predictive Analytics</span>
                    <h2>Python Historical Forecasting</h2>
                    <p>Forecast ETA, trip duration, delay risk, and historical peak/slow periods from processed GPS history.</p>
                </div>
                <span class="diagnostic-pill">{{ $prediction->available ? 'Python Live' : 'Python Unavailable' }}</span>
            </section>

            <section class="stats-grid fleet-summary-grid diagnostic-summary-grid">
                <x-ui.summary-card
                    label="Historical Sample"
                    :value="number_format($prediction->historical_records)"
                    small="Up to 90 days of processed GPS history"
                    icon="fa-database"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Upcoming Targets"
                    :value="number_format($prediction->target_count)"
                    small="Scheduled trips in the next 7 days"
                    icon="fa-calendar-day"
                    color="blue"
                />

                <x-ui.summary-card
                    label="Predicted Trips"
                    :value="number_format($prediction->predicted_target_count)"
                    :small="$prediction->available ? 'Targets with enough comparable history' : 'Python Engine unavailable'"
                    icon="fa-chart-line"
                    :color="$prediction->available ? 'green' : 'yellow'"
                />

                <x-ui.summary-card
                    label="Peak / Slow Periods"
                    :value="number_format($prediction->peak_periods->count())"
                    small="Historical 2-hour periods above route-normal travel time or below route-normal speed"
                    icon="fa-traffic-light"
                    color="yellow"
                />
            </section>

            @php
                $forecastChartRows = $prediction->predictions->take(6)->values();
                $durationChartMax = max(
                    1,
                    (float) ($forecastChartRows->max(fn ($forecast) => max(
                        $forecast->predicted_duration_minutes,
                        $forecast->baseline_duration_minutes
                    )) ?? 0)
                );
            @endphp

            @if($forecastChartRows->isNotEmpty())
                <section class="trip-primary-grid">
                    <article class="trip-panel">
                        <div class="trip-panel-header">
                            <div>
                                <span class="section-kicker">Predictive Duration Chart</span>
                                <h2>Predicted vs Historical Duration</h2>
                                <p>Compare Python-predicted trip duration against the historical route baseline for each forecastable trip.</p>
                            </div>
                            <span class="period-pill">Minutes</span>
                        </div>

                        <div class="route-leaderboard">
                            @foreach($forecastChartRows as $forecast)
                                @php
                                    $baselineWidth = min(100, ($forecast->baseline_duration_minutes / $durationChartMax) * 100);
                                    $predictedWidth = min(100, ($forecast->predicted_duration_minutes / $durationChartMax) * 100);
                                @endphp
                                <div class="route-ranking {{ $loop->first ? 'first' : '' }}">
                                    <div class="ranking-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                                    <div class="route-ranking-icon"><i class="fa-solid fa-chart-column"></i></div>
                                    <div class="route-ranking-content">
                                        <strong>{{ $forecast->trip_code }} · {{ $forecast->route }}</strong>
                                        <span>Historical baseline · {{ number_format($forecast->baseline_duration_minutes, 1) }} min</span>
                                        <div class="route-ranking-progress"><span style="width: {{ $baselineWidth }}%;"></span></div>
                                        <span>Predicted duration · {{ number_format($forecast->predicted_duration_minutes, 1) }} min</span>
                                        <div class="route-ranking-progress"><span style="width: {{ $predictedWidth }}%;"></span></div>
                                    </div>
                                    <div class="route-ranking-value">
                                        <strong>{{ number_format($forecast->predicted_duration_minutes - $forecast->baseline_duration_minutes, 1) }}</strong>
                                        <span>min vs baseline</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>

                    <article class="trip-panel">
                        <div class="trip-panel-header">
                            <div>
                                <span class="section-kicker">Predictive Risk Chart</span>
                                <h2>Delay Risk by Upcoming Trip</h2>
                                <p>Historical share of comparable trips that exceeded the diagnostic delay threshold.</p>
                            </div>
                            <span class="period-pill">0–100%</span>
                        </div>

                        <div class="route-leaderboard">
                            @foreach($forecastChartRows as $forecast)
                                <div class="route-ranking {{ $loop->first ? 'first' : '' }}">
                                    <div class="ranking-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                                    <div class="route-ranking-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                    <div class="route-ranking-content">
                                        <strong>{{ $forecast->trip_code }}</strong>
                                        <span>{{ $forecast->risk_level }} delay risk · {{ number_format($forecast->sample_size) }} comparable records</span>
                                        <div class="route-ranking-progress">
                                            <span style="width: {{ min(100, max(0, $forecast->delay_risk_percent)) }}%;"></span>
                                        </div>
                                    </div>
                                    <div class="route-ranking-value">
                                        <strong>{{ number_format($forecast->delay_risk_percent, 1) }}%</strong>
                                        <span>delay risk</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                </section>
            @else
                <section class="trip-panel route-leaderboard-panel">
                    <div class="trip-panel-header">
                        <div>
                            <span class="section-kicker">Predictive Charts</span>
                            <h2>Forecast Visualizations</h2>
                            <p>Duration comparison and delay-risk charts will appear automatically when Python produces at least one eligible upcoming-trip forecast.</p>
                        </div>
                        <span class="period-pill">Awaiting Forecast</span>
                    </div>

                    <div class="route-leaderboard">
                        <div class="route-ranking first">
                            <div class="ranking-number">—</div>
                            <div class="route-ranking-icon"><i class="fa-solid fa-chart-column"></i></div>
                            <div class="route-ranking-content">
                                <strong>No predictive chart values are fabricated.</strong>
                                <span>The charts remain empty until a scheduled or ready trip has enough comparable historical route data.</span>
                            </div>
                            <div class="route-ranking-value"><strong>0</strong><span>forecast rows</span></div>
                        </div>
                    </div>
                </section>
            @endif

            <section class="trip-panel route-leaderboard-panel">
                <div class="trip-panel-header">
                    <div>
                        <span class="section-kicker">Predictive ETA & Delay Risk</span>
                        <h2>Upcoming Trip Forecasts</h2>
                        <p>
                            Python first looks for at least three same-route records near the same departure hour and weekday,
                            then falls back to broader same-route history. Delay risk is the historical share of comparable trips
                            that exceeded the same diagnostic delay threshold.
                        </p>
                    </div>
                    <span class="period-pill">{{ $prediction->model ?? 'Service Offline' }}</span>
                </div>

                <div class="route-leaderboard">
                    @if(! $prediction->available)
                        <div class="route-ranking first">
                            <div class="ranking-number">—</div>
                            <div class="route-ranking-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="route-ranking-content">
                                <strong>Python prediction service is unavailable.</strong>
                                <span>Descriptive and diagnostic analytics remain live; no forecast values are fabricated.</span>
                            </div>
                            <div class="route-ranking-value"><strong>Offline</strong><span>5.3 only</span></div>
                        </div>
                    @else
                        @forelse($prediction->predictions->take(6) as $forecast)
                            <div class="route-ranking {{ $loop->first ? 'first' : '' }}">
                                <div class="ranking-number">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                                <div class="route-ranking-icon"><i class="fa-solid fa-clock"></i></div>
                                <div class="route-ranking-content">
                                    <strong>{{ $forecast->trip_code }} · {{ $forecast->route }}</strong>
                                    <span>
                                        Departs {{ $forecast->departure_at?->format('M j, g:i A') }} ·
                                        predicted {{ number_format($forecast->predicted_duration_minutes, 1) }} min ·
                                        ETA {{ $forecast->estimated_arrival_at?->format('M j, g:i A') }}
                                    </span>
                                    <small class="diagnostic-baseline">
                                        {{ $forecast->method }} · {{ number_format($forecast->sample_size) }} comparable records ·
                                        route baseline {{ number_format($forecast->baseline_duration_minutes, 1) }} min
                                    </small>
                                </div>
                                <div class="route-ranking-value">
                                    <strong>{{ number_format($forecast->delay_risk_percent, 1) }}%</strong>
                                    <span>{{ $forecast->risk_level }} delay risk</span>
                                </div>
                            </div>
                        @empty
                            <div class="route-ranking first">
                                <div class="ranking-number">—</div>
                                <div class="route-ranking-icon"><i class="fa-solid fa-chart-line"></i></div>
                                <div class="route-ranking-content">
                                    <strong>Python is online, but there is no forecastable upcoming trip yet.</strong>
                                    <span>{{ $prediction->message }}</span>
                                </div>
                                <div class="route-ranking-value"><strong>0</strong><span>predictions</span></div>
                            </div>
                        @endforelse
                    @endif
                </div>
            </section>

            <section class="trip-findings-layout diagnostic-findings-layout">
                <div class="trip-findings-heading diagnostic-method-heading">
                    <span class="section-kicker">Historical Peak-Period Forecast</span>
                    <h2>When do trips tend to slow down?</h2>
                    <p>
                        Each trip is normalized against its own route median before time blocks are compared.
                        This reduces route-distance bias and describes historical slow periods rather than claiming live traffic conditions.
                    </p>
                </div>

                <div class="trip-findings-list">
                    @forelse($prediction->peak_periods as $peak)
                        <article class="trip-finding warning">
                            <div class="finding-icon"><i class="fa-solid fa-traffic-light"></i></div>
                            <div>
                                <span>{{ $peak->period }}</span>
                                <strong>
                                    Duration index {{ number_format($peak->duration_index, 2) }}×
                                    @if($peak->speed_index !== null)
                                        · Speed index {{ number_format($peak->speed_index, 2) }}×
                                    @endif
                                </strong>
                                <p>{{ number_format($peak->sample_size) }} historical records · {{ $peak->interpretation }}</p>
                            </div>
                        </article>
                    @empty
                        <article class="trip-finding info">
                            <div class="finding-icon"><i class="fa-solid fa-circle-info"></i></div>
                            <div>
                                <span>Peak-period evidence</span>
                                <strong>No time block currently meets the historical slow-period rule.</strong>
                                <p>More processed GPS history may be needed, or current periods are close to their route-normal performance.</p>
                            </div>
                        </article>
                    @endforelse
                </div>
            </section>

            <section class="trip-findings-layout">
                <div class="trip-findings-heading">
                    <span class="section-kicker">Live Descriptive Findings</span>
                    <h2>What the current records show</h2>
                    <p>These observations summarize the selected processed GPS records before diagnostic and predictive interpretation.</p>
                </div>

                <div class="trip-findings-list">
                    <article class="trip-finding {{ ($tripGrowth ?? 0) >= 0 ? 'good' : 'warning' }}">
                        <div class="finding-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <div>
                            <span>Trip Activity</span>
                            <strong>
                                @if($tripGrowth === null)
                                    No comparable previous-period baseline is available.
                                @else
                                    Trip-record volume {{ $tripGrowth >= 0 ? 'increased' : 'decreased' }} by {{ number_format(abs($tripGrowth), 1) }}%.
                                @endif
                            </strong>
                            <p>{{ number_format($tripCount) }} processed records are included in the selected window.</p>
                        </div>
                    </article>

                    <article class="trip-finding info">
                        <div class="finding-icon"><i class="fa-solid fa-road"></i></div>
                        <div>
                            <span>Distance & Motion</span>
                            <strong>{{ number_format($totalDistance, 1) }} km recorded at {{ number_format($averageSpeed, 1) }} km/h weighted average speed.</strong>
                            <p>The speed calculation uses total distance against recorded in-motion time, with trip duration as fallback.</p>
                        </div>
                    </article>

                    <article class="trip-finding warning">
                        <div class="finding-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                        <div>
                            <span>Idle Time</span>
                            <strong>{{ number_format($totalIdleMinutes / 60, 1) }} hours of idling are recorded in the current filter.</strong>
                            <p>{{ number_format($diagnostics->high_idle_count) }} records meet the stricter diagnostic high-idling rule.</p>
                        </div>
                    </article>
                </div>
            </section>

            <section class="trip-panel route-leaderboard-panel">
                <div class="trip-panel-header">
                    <div>
                        <span class="section-kicker">Analytics Implementation Status</span>
                        <h2>Fleet & Trip Analytics Pipeline</h2>
                        <p>5.1 and 5.2 run in Laravel; 5.3 is implemented in Python and consumed by Laravel; 5.4 remains the next decision-support phase.</p>
                    </div>
                    <span class="period-pill">
                        {{ $prediction->available ? '3 Implemented · 1 Queued' : '2 Live · Python Offline · 1 Queued' }}
                    </span>
                </div>

                <div class="route-leaderboard">
                    <div class="route-ranking first">
                        <div class="ranking-number">5.2</div>
                        <div class="route-ranking-icon"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                        <div class="route-ranking-content">
                            <strong>Diagnostic Analytics</strong>
                            <span>Delay indicators, slow-movement patterns, high idling, baseline coverage, and evidence limitations are calculated from live records.</span>
                        </div>
                        <div class="route-ranking-value"><strong>Live</strong><span>Laravel</span></div>
                    </div>

                    <div class="route-ranking">
                        <div class="ranking-number">5.3</div>
                        <div class="route-ranking-icon"><i class="fa-solid fa-chart-line"></i></div>
                        <div class="route-ranking-content">
                            <strong>Predictive Analytics</strong>
                            <span>Historical-statistical Python forecasting for trip duration, ETA, delay risk, and peak/slow time periods.</span>
                        </div>
                        <div class="route-ranking-value">
                            <strong>{{ $prediction->available ? 'Implemented' : 'Unavailable' }}</strong>
                            <span>Python Engine</span>
                        </div>
                    </div>

                    <div class="route-ranking">
                        <div class="ranking-number">5.4</div>
                        <div class="route-ranking-icon"><i class="fa-solid fa-lightbulb"></i></div>
                        <div class="route-ranking-content">
                            <strong>Prescriptive Analytics</strong>
                            <span>Shuttle assignment, route adjustment, and schedule modification recommendations based on validated findings and predictions.</span>
                        </div>
                        <div class="route-ranking-value"><strong>Next</strong><span>decision support</span></div>
                    </div>
                </div>
            </section>

            <section class="fleet-recommendation">
                <div class="recommendation-icon"><i class="fa-solid fa-circle-info"></i></div>
                <div class="recommendation-content">
                    <span>Analytics Boundary</span>
                    <h2>Predictions use historical processed GPS records; they are not live traffic data and are not presented when Python is unavailable.</h2>
                    <p>
                        Diagnostics still require comparable route history, route deviation still requires actual traveled-path evidence,
                        and Python forecasts require at least three comparable historical trips before generating an ETA or delay-risk result.
                    </p>
                </div>
                <a href="{{ route('analytics.recommendations') }}" class="recommendation-link">
                    View Recommendations
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </section>
        </main>
    </div>
</x-layout.app>