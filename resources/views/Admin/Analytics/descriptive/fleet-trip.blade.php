<section class="fleet-trip-kpi-strip" aria-label="Fleet and trip summary KPIs">
    @php
        $kpis = [
            ['Distance Traveled', number_format($totalDistance, 1) . ' km', 'Total distance', 'fa-location-dot', 'tone-blue', $comparison['distance']],
            ['Average Speed', number_format($averageSpeed, 1) . ' km/h', 'Average in motion', 'fa-gauge-high', 'tone-green', $comparison['speed']],
            ['Idle Time', number_format($totalIdleMinutes / 60, 1) . ' hrs', 'Total recorded idle', 'fa-hourglass-half', 'tone-yellow', $comparison['idle']],
            ['Avg. Trip Duration', number_format($averageTripDuration, 1) . ' min', 'Average per trip', 'fa-clock', 'tone-purple', $comparison['duration']],
            ['Trips Processed', number_format($tripCount), 'Recorded trips', 'fa-route', 'tone-blue', $comparison['trips']],
            ['Buses Active', number_format($activeBuses), number_format($fleetAvailability, 1) . '% utilization', 'fa-bus', 'tone-green', null],
        ];
    @endphp

    @foreach($kpis as [$label, $value, $description, $icon, $tone, $delta])
        @php
            $isBusAvailability = $label === 'Buses Active';
            $change = $isBusAvailability
                ? number_format($activeBuses) . ' of ' . number_format($totalBuses) . ' buses'
                : ($delta === null ? 'No prior data' : $deltaText($delta) . ' ' . $comparison['label']);
            $changeClass = $isBusAvailability
                ? 'positive'
                : ($delta === null ? '' : ($delta < 0 ? 'negative' : 'positive'));
        @endphp
        <div class="fleet-trip-kpi-card {{ $tone }}">
            <div class="fleet-trip-kpi-icon">
                <i class="fa-solid {{ $icon }}"></i>
            </div>
            <div>
                <span>{{ $label }}</span>
                <strong>{{ $value }}</strong>
                <small class="{{ $changeClass }}">{{ $change }}</small>
            </div>
        </div>
    @endforeach
</section>

<section class="fleet-trip-dashboard">
    <div class="fleet-trip-left-column">
        {{-- Hero Trip Trend Chart --}}
        <article class="analytics-card fleet-trip-chart-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Processed Trip Activity <i class="fa-solid fa-chart-line"></i></h3>
                    <p>Trip-record volume trends across the selected period.</p>
                </div>
                <span>{{ $periodLabel }}</span>
            </div>

            <div class="trip-canvas-chart" data-trip-points='@json($tripChartData)'>
                <canvas class="trip-canvas" role="img" aria-label="Processed trip activity chart"></canvas>
                <div class="trip-canvas-tooltip" aria-hidden="true">
                    <strong></strong>
                    <span><i></i> Trips Processed <b></b></span>
                </div>
            </div>

            <div class="trip-canvas-legend">
                <span><i class="trip-legend-bar"></i> Trips Processed</span>
                @if($hasPartialBucket)
                    <span class="trip-canvas-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>
                @endif
            </div>
        </article>

        {{-- Top Routes Ranking --}}
        <article class="analytics-card fleet-trip-ranking-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Top Routes by Volume <i class="fa-solid fa-route"></i></h3>
                    <p>{{ $periodLabel }} &middot; highest-volume route corridors</p>
                </div>
                <span>{{ count($routes) }} Routes Active</span>
            </div>

            <div class="ranking-list refined-ranking-list">
                @forelse($routes as $route)
                    <div class="refined-ranking-row">
                        <span class="refined-rank-number">{{ $loop->iteration }}</span>
                        <div class="refined-ranking-main">
                            <div class="refined-ranking-title-row">
                                <strong>{{ $route->label }}</strong>
                                <span>{{ $route->trips }} {{ \Illuminate\Support\Str::plural('trip', $route->trips) }}</span>
                            </div>
                            <div class="refined-ranking-meta">
                                <span><i class="fa-regular fa-clock"></i> {{ number_format($route->average_duration, 1) }} min avg.</span>
                                <span><i class="fa-solid fa-chart-pie"></i> {{ number_format($route->share, 1) }}% of trips</span>
                            </div>
                            <div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No route records match the selected filters.</p>
                @endforelse
            </div>
        </article>
    </div>

    <div class="fleet-trip-right-column">
        {{-- Fleet Availability Donut --}}
        <article class="analytics-card fleet-trip-availability-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Fleet Availability <i class="fa-solid fa-bus"></i></h3>
                    <p>Current operational status across Bus Master List.</p>
                </div>
                <span>{{ $totalBuses }} buses</span>
            </div>

            <div class="analytics-availability-layout">
                <div class="availability-score">
                    <div
                        class="fleet-css-donut"
                        data-default-value="{{ number_format($fleetAvailability, 1) }}%"
                        data-default-label="Active"
                        data-active="{{ number_format($activePct, 2, '.', '') }}"
                        data-maintenance="{{ number_format($maintenancePct, 2, '.', '') }}"
                        data-inactive="{{ number_format($inactivePct, 2, '.', '') }}"
                        style="--fleet-active: {{ number_format($activePct, 2, '.', '') }}%; --fleet-maintenance-end: {{ number_format($maintenanceEndPct, 2, '.', '') }}%;"
                    >
                        <div class="fleet-css-donut-center">
                            <strong>{{ number_format($fleetAvailability, 1) }}%</strong>
                            <span>Active</span>
                        </div>
                        <div class="fleet-css-donut-tooltip" aria-hidden="true"><strong></strong><span></span></div>
                    </div>
                </div>

                <div class="availability-breakdown">
                    <div class="availability-row" data-donut-index="0" data-label="Active" data-value="{{ $activeBuses }}" data-percentage="{{ number_format($activePct, 1, '.', '') }}">
                        <div><span class="availability-dot operational"></span><span>Active</span></div>
                        <strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong>
                    </div>
                    <div class="availability-row" data-donut-index="1" data-label="Under Maintenance" data-value="{{ $underMaintenance }}" data-percentage="{{ number_format($maintenancePct, 1, '.', '') }}">
                        <div><span class="availability-dot maintenance"></span><span>Maintenance</span></div>
                        <strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong>
                    </div>
                    <div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}">
                        <div><span class="availability-dot inactive"></span><span>Inactive</span></div>
                        <strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong>
                    </div>
                </div>
            </div>
        </article>

        {{-- Busiest Buses Ranking --}}
        <article class="analytics-card fleet-trip-ranking-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Busiest Buses <i class="fa-solid fa-gauge"></i></h3>
                    <p>{{ $periodLabel }} &middot; highest recorded trip count</p>
                </div>
                <span>{{ count($busActivity) }} Units Active</span>
            </div>

            <div class="ranking-list refined-ranking-list">
                @forelse($busActivity as $bus)
                    <div class="refined-ranking-row">
                        <span class="refined-rank-number">{{ $loop->iteration }}</span>
                        <div class="refined-ranking-main">
                            <div class="refined-ranking-title-row">
                                <strong>{{ $bus->bus }}</strong>
                                <span>{{ $bus->trips }} {{ \Illuminate\Support\Str::plural('trip', $bus->trips) }}</span>
                            </div>
                            <div class="refined-ranking-meta">
                                <span><i class="fa-solid fa-road"></i> {{ number_format($bus->distance, 1) }} km</span>
                                <span><i class="fa-solid fa-chart-pie"></i> {{ number_format($bus->share, 1) }}% share</span>
                            </div>
                            <div class="metric-bar refined-metric-bar"><span style="width: {{ $bus->progress }}%"></span></div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No bus activity matches the selected filters.</p>
                @endforelse
            </div>
        </article>
    </div>
</section>

{{-- Lower Section: Alerts & Trip Insights --}}
<section class="fleet-trip-footer-grid" style="margin-top: 14px;">
    <article class="analytics-card fleet-trip-alerts-card">
        <div class="fleet-trip-card-heading">
            <div>
                <h3>Recent Trip Signals & Alerts <i class="fa-solid fa-bell"></i></h3>
                <p>Latest operational events requiring dispatcher review</p>
            </div>
            <a href="{{ route('admin.notifications') }}">View all <i class="fa-solid fa-arrow-right"></i></a>
        </div>

        @if($recentAlerts->isNotEmpty())
            <div class="fleet-trip-alerts-wrap">
                <table class="fleet-trip-alerts-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Entity</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentAlerts as $alert)
                            <tr>
                                <td>{{ $alert['date'] }}<br><small>{{ $alert['time'] }}</small></td>
                                <td><span class="fleet-trip-alert-type {{ strtolower($alert['type']) }}"><i></i>{{ $alert['type'] }}</span></td>
                                <td>
                                    <strong>{{ $alert['module'] ?? 'Trip Record' }}</strong>
                                    <small style="color: #64748b; margin-left: 4px;">#{{ $alert['reference'] !== '—' && $alert['reference'] !== '\u2014' ? $alert['reference'] : 'General' }}</small>
                                </td>
                                <td><span class="fleet-trip-alert-state {{ $alert['unread'] ? 'open' : 'resolved' }}">{{ $alert['unread'] ? 'Open' : 'Resolved' }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="analytics-compact-empty"><i class="fa-regular fa-bell-slash"></i><span>No recorded notifications are available.</span></div>
        @endif
    </article>

    <article class="analytics-card fleet-trip-insights-card">
        <div class="fleet-trip-card-heading">
            <div>
                <h3>Trip Performance Insights <i class="fa-solid fa-lightbulb"></i></h3>
                <p>Comparative variance against {{ $comparison['label'] }}</p>
            </div>
            <span>{{ $comparison['label'] }}</span>
        </div>

        @php
            $insights = [
                ['trips', 'Trip Volume Change', number_format($tripCount) . ' vs ' . number_format($comparison['previousTrips']), 'fa-route', 'tone-blue', $comparison['trips']],
                ['idle', 'Idling Exposure', number_format($totalIdleMinutes / 60, 1) . ' hrs vs ' . number_format($comparison['previousIdleMinutes'] / 60, 1) . ' hrs', 'fa-hourglass-half', 'tone-yellow', $comparison['idle']],
                ['distance', 'Distance Traveled', number_format($totalDistance, 1) . ' km vs ' . number_format($comparison['previousDistance'], 1) . ' km', 'fa-road', 'tone-green', $comparison['distance']],
                ['duration', 'Average Duration', number_format($averageTripDuration, 1) . ' min vs previous baseline', 'fa-clock', 'tone-purple', $comparison['duration']],
            ];
        @endphp
        <div class="fleet-trip-insights-grid">
            @foreach($insights as [$key, $label, $detail, $icon, $tone, $delta])
                <div class="fleet-trip-insight-tile {{ $delta !== null && $delta < 0 ? 'is-negative' : 'is-positive' }}">
                    <div class="fleet-trip-insight-icon {{ $tone }}">
                        <i class="fa-solid {{ $icon }}"></i>
                    </div>
                    <div class="fleet-trip-insight-body">
                        <div class="fleet-trip-insight-top">
                            <span class="fleet-trip-insight-delta {{ $delta !== null && $delta < 0 ? 'negative' : 'positive' }}">
                                {{ $deltaText($delta) }}
                            </span>
                            <span class="fleet-trip-insight-detail">{{ $detail }}</span>
                        </div>
                        <strong class="fleet-trip-insight-title">{{ $label }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    </article>
</section>
