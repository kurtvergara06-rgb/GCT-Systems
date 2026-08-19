<section class="fleet-trip-kpi-strip">
    @php
        $fleetTripKpis = [
            ['Trips Processed', number_format($tripCount), 'Total trips recorded', 'fa-route', 'blue', $comparison['trips']],
            ['Distance Traveled', number_format($totalDistance, 1) . ' km', 'Total recorded distance', 'fa-road', 'green', $comparison['distance']],
            ['Average Speed', number_format($averageSpeed, 1) . ' km/h', 'Average while in motion', 'fa-gauge-high', 'purple', $comparison['speed']],
            ['Buses Active', number_format($activeBuses), 'Out of ' . number_format($totalBuses) . ' buses', 'fa-bus', 'green', null],
            ['Idle Time', number_format($totalIdleMinutes / 60, 1) . ' hrs', 'Total recorded idle time', 'fa-hourglass-half', 'yellow', $comparison['idle']],
            ['Avg. Trip Duration', number_format($averageTripDuration, 1) . ' min', 'Average per trip', 'fa-clock', 'blue', $comparison['duration']],
        ];
    @endphp

    @foreach($fleetTripKpis as [$label, $value, $small, $icon, $tone, $delta])
        <article class="fleet-trip-kpi-card tone-{{ $tone }}">
            <span class="fleet-trip-kpi-icon"><i class="fa-solid {{ $icon }}"></i></span>
            <div>
                <span>{{ $label }}</span>
                <strong>{{ $value }}</strong>
                @if($label === 'Buses Active')
                    <small>{{ number_format($fleetAvailability, 1) }}% utilization</small>
                @else
                    <small class="{{ $delta !== null && $delta < 0 ? 'negative' : 'positive' }}">
                        {{ $deltaText($delta) }} {{ $comparison['label'] }}
                    </small>
                @endif
            </div>
        </article>
    @endforeach
</section>

<section class="fleet-trip-dashboard">
    <div class="fleet-trip-left-column">
        <article class="analytics-card fleet-trip-chart-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Processed Trip Activity <i class="fa-regular fa-circle-info"></i></h3>
                    <p>Trip-record volume across the selected period.</p>
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
                <span><i></i> Trips Processed</span>
                @if($hasPartialBucket)
                    <span class="trip-canvas-partial-note"><i class="fa-regular fa-clock"></i> Current bucket is partial</span>
                @endif
            </div>
        </article>

        <article class="analytics-card fleet-trip-alerts-card">
            <div class="fleet-trip-card-heading fleet-trip-alerts-heading">
                <div>
                    <h3>Recent Alerts <i class="fa-regular fa-circle-info"></i></h3>
                    <p>Latest system alerts and notifications</p>
                </div>
                <a href="{{ route('admin.notifications') }}">View all alerts <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            @if($recentAlerts->isNotEmpty())
                <div class="fleet-trip-alerts-wrap">
                    <table class="fleet-trip-alerts-table">
                        <thead>
                            <tr><th>Time</th><th>Type</th><th>Message</th><th>Entity</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach($recentAlerts as $alert)
                                <tr>
                                    <td>{{ $alert['date'] }}<small>{{ $alert['time'] }}</small></td>
                                    <td><span class="fleet-trip-alert-type {{ strtolower($alert['type']) }}"><i></i>{{ $alert['type'] }}</span></td>
                                    <td>{{ $alert['message'] }}</td>
                                    <td>{{ $alert['reference'] !== '—' ? $alert['reference'] : $alert['module'] }}</td>
                                    <td><span class="fleet-trip-alert-state {{ $alert['unread'] ? 'open' : 'resolved' }}">{{ $alert['unread'] ? 'Open' : 'Read' }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="analytics-compact-empty"><i class="fa-regular fa-bell-slash"></i><span>No recorded notifications are available.</span></div>
            @endif
        </article>

        <article class="analytics-card fleet-trip-action-summary">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Action Summary <i class="fa-regular fa-circle-info"></i></h3>
                    <p>Current Fleet & Trip operating snapshot</p>
                </div>
            </div>

            @php
                $fleetTripActions = [
                    ['Trips Processed', $tripCount, 'Recorded in selected period', 'fa-route', 'blue'],
                    ['Idle Hours', number_format($totalIdleMinutes / 60, 1), 'Recorded idle time', 'fa-hourglass-half', 'yellow'],
                    ['Active Buses', $activeBuses, number_format($fleetAvailability, 1) . '% utilization', 'fa-bus', 'green'],
                    ['Under Maintenance', $underMaintenance, 'Buses currently in maintenance', 'fa-screwdriver-wrench', 'orange'],
                ];
            @endphp

            <div class="fleet-trip-action-grid">
                @foreach($fleetTripActions as [$label, $value, $detail, $icon, $tone])
                    <div class="fleet-trip-action-item tone-{{ $tone }}">
                        <span><i class="fa-solid {{ $icon }}"></i></span>
                        <div>
                            <strong>{{ $value }}</strong>
                            <b>{{ $label }}</b>
                            <small>{{ $detail }}</small>
                        </div>
                    </div>
                @endforeach
            </div>
        </article>
    </div>

    <aside class="fleet-trip-right-column">
        <article class="analytics-card fleet-trip-availability-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Fleet Availability <i class="fa-regular fa-circle-info"></i></h3>
                    <p>Current Bus Master List status.</p>
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
                        <div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div>
                        <strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong>
                    </div>
                    <div class="availability-row" data-donut-index="2" data-label="Inactive" data-value="{{ $inactiveBuses }}" data-percentage="{{ number_format($inactivePct, 1, '.', '') }}">
                        <div><span class="availability-dot inactive"></span><span>Inactive</span></div>
                        <strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong>
                    </div>
                    <div class="availability-total"><span>Total Buses</span><strong>{{ $totalBuses }}</strong></div>
                </div>
            </div>
        </article>

        <article class="analytics-card fleet-trip-ranking-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Top Routes by Trips <i class="fa-regular fa-circle-info"></i></h3>
                    <p>{{ $periodLabel }} · highest-volume routes</p>
                </div>
            </div>
            <div class="ranking-list refined-ranking-list">
                @forelse($routes as $route)
                    <div class="refined-ranking-row">
                        <span class="refined-rank-number">{{ $loop->iteration }}</span>
                        <div class="refined-ranking-main">
                            <div class="refined-ranking-title-row"><strong>{{ $route->label }}</strong><span>{{ $route->trips }} trips</span></div>
                            <div class="metric-bar refined-metric-bar"><span style="width: {{ $route->progress }}%"></span></div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No route records match the selected filters.</p>
                @endforelse
            </div>
        </article>

        <article class="analytics-card fleet-trip-ranking-card">
            <div class="fleet-trip-card-heading">
                <div>
                    <h3>Busiest Buses <i class="fa-regular fa-circle-info"></i></h3>
                    <p>{{ $periodLabel }} · highest recorded trip activity</p>
                </div>
            </div>
            <div class="ranking-list refined-ranking-list">
                @forelse($busActivity as $bus)
                    <div class="refined-ranking-row">
                        <span class="refined-rank-number">{{ $loop->iteration }}</span>
                        <div class="refined-ranking-main">
                            <div class="refined-ranking-title-row"><strong>{{ $bus->bus }}</strong><span>{{ $bus->trips }} trips</span></div>
                            <div class="metric-bar refined-metric-bar"><span style="width: {{ $bus->progress }}%"></span></div>
                        </div>
                    </div>
                @empty
                    <p class="ranking-empty">No bus activity matches the selected filters.</p>
                @endforelse
            </div>
        </article>

        <div class="fleet-trip-status-grid">
            <article class="analytics-card fleet-trip-status-card">
                <div class="fleet-trip-card-heading compact">
                    <div>
                        <h3>Fleet Status <i class="fa-regular fa-circle-info"></i></h3>
                        <p>Operational status overview</p>
                    </div>
                </div>
                <div class="availability-breakdown">
                    <div class="availability-row"><div><span class="availability-dot operational"></span><span>Active</span></div><strong>{{ $activeBuses }} <small>{{ number_format($activePct, 1) }}%</small></strong></div>
                    <div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Under Maintenance</span></div><strong>{{ $underMaintenance }} <small>{{ number_format($maintenancePct, 1) }}%</small></strong></div>
                    <div class="availability-row"><div><span class="availability-dot inactive"></span><span>Inactive</span></div><strong>{{ $inactiveBuses }} <small>{{ number_format($inactivePct, 1) }}%</small></strong></div>
                </div>
            </article>

            <article class="analytics-card fleet-trip-status-card">
                <div class="fleet-trip-card-heading compact">
                    <div>
                        <h3>Inventory Overview <i class="fa-regular fa-circle-info"></i></h3>
                        <p>Stock level summary</p>
                    </div>
                </div>
                <div class="availability-breakdown">
                    <div class="availability-row"><div><span class="availability-dot operational"></span><span>Well Stocked</span></div><strong>{{ $inventoryHealthy }} <small>({{ number_format($healthyPct) }}%)</small></strong></div>
                    <div class="availability-row"><div><span class="availability-dot maintenance"></span><span>Low Stock</span></div><strong>{{ $inventoryLow }} <small>({{ number_format($lowPct) }}%)</small></strong></div>
                    <div class="availability-row fleet-trip-out-of-stock"><div><span class="availability-dot critical"></span><span>Out of Stock</span></div><strong>{{ $inventoryCritical }} <small>({{ number_format($criticalPct) }}%)</small></strong></div>
                </div>
            </article>
        </div>
    </aside>
</section>