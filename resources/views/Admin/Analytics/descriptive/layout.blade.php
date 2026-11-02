@php
    $url = route('analytics.descriptive', [], false);
    $periodOptions = [
        'this-month' => 'This Month',
        'last-30-days' => 'Last 30 Days',
        'last-3-months' => 'Last 3 Months',
        'this-year' => 'This Year',
    ];

    if ($activeSummary === 'operational') {
        $chartPayload = [
            'activeSummary' => 'operational',
            'trendLabels' => $operationalTrend->pluck('label')->toArray(),
            'trendScheduled' => $operationalTrend->pluck('scheduled')->toArray(),
            'trendCompleted' => $operationalTrend->pluck('completed')->toArray(),
            'trendGps' => $operationalTrend->pluck('gps')->toArray(),
            'trendIncidents' => $operationalTrend->pluck('incidents')->toArray(),
            'totalBuses' => $totalBuses,
            'activeBuses' => $activeBuses,
            'underMaintenance' => $underMaintenance,
            'inactiveBuses' => $inactiveBuses,
            'fleetAvailability' => $fleetAvailability,
            'tripStatusLabels' => array_keys($tripStatusDistribution),
            'tripStatusValues' => array_values($tripStatusDistribution),
            'incidentLabels' => array_keys($incidentTypeDistribution),
            'incidentValues' => array_values($incidentTypeDistribution),
        ];
    } else {
        $activeUsersRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0;
        $chartPayload = [
            'activeSummary' => 'administrative',
            'trendLabels' => $adminTrend->pluck('label')->toArray(),
            'trendActivities' => $adminTrend->pluck('activities')->toArray(),
            'trendNotifications' => $adminTrend->pluck('notifications')->toArray(),
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'inactiveUsers' => $inactiveUsers,
            'moduleLabels' => array_keys($activityByModule),
            'moduleValues' => array_values($activityByModule),
            'notifModuleLabels' => array_keys($notificationsByModule),
            'notifModuleValues' => array_values($notificationsByModule),
            'userDeptLabels' => array_keys($usersByDepartment),
            'userDeptValues' => array_values($usersByDepartment),
        ];
    }
@endphp
<x-layout.app
    title="FROMS - Descriptive Analytics"
    :assets="[
        'resources/css/Admin/Analytics/design-system.css',
        'resources/css/Admin/Analytics/descriptive/descriptive-rebuild.css',
        'resources/js/Admin/Analytics/descriptive/descriptive-analytics.js'
    ]"
    :partial-navigation="false"
>
    <div class="app">
        <x-layout.sidebar department="Admin" />
        <main class="main descriptive-rebuild">
            <x-layout.topbar
                title="Descriptive Analytics"
                subtitle="Descriptive operational telemetry and administrative activity audits aligned with Thesis Objective 6.1."
            />

            <!-- Hidden JSON payload consumed by Chart.js script -->
            <script type="application/json" id="descriptiveAnalyticsData">
                {!! json_encode($chartPayload) !!}
            </script>

            <!-- Controls & Filters Bar -->
            <div class="descriptive-controls-bar">
                <nav class="descriptive-nav-pills" aria-label="Descriptive analytics views">
                    <a href="{{ $url . '?' . http_build_query(array_merge(request()->only(['period', 'bus']), ['view' => 'operational'])) }}"
                       class="{{ $activeSummary === 'operational' ? 'active' : '' }}"
                       @if($activeSummary === 'operational') aria-current="page" @endif>
                        <i class="fa-solid fa-bus"></i> Operational Summary
                    </a>
                    <a href="{{ $url . '?' . http_build_query(array_merge(request()->only(['period', 'bus']), ['view' => 'administrative'])) }}"
                       class="{{ $activeSummary === 'administrative' ? 'active' : '' }}"
                       @if($activeSummary === 'administrative') aria-current="page" @endif>
                        <i class="fa-solid fa-users-gear"></i> Administrative Summary
                    </a>
                </nav>

                <form method="GET" action="{{ $url }}" class="descriptive-filters-form">
                    <input type="hidden" name="view" value="{{ $activeSummary }}">

                    <div class="descriptive-filter-group">
                        <label for="periodSelect"><i class="fa-regular fa-calendar"></i> Period</label>
                        <select id="periodSelect" name="period" onchange="this.form.submit()">
                            @foreach($periodOptions as $val => $lbl)
                                <option value="{{ $val }}" @selected($period === $val)>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($activeSummary === 'operational')
                        <div class="descriptive-filter-group">
                            <label for="busSelect"><i class="fa-solid fa-bus-simple"></i> Bus</label>
                            <select id="busSelect" name="bus" onchange="this.form.submit()">
                                <option value="all" @selected($selectedBus === 'ALL')>All Fleet Buses</option>
                                @foreach($buses as $bus)
                                    <option value="{{ $bus->bus_no }}" @selected($selectedBus === strtoupper($bus->bus_no))>
                                        {{ $bus->bus_no }} ({{ $bus->status }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="descriptive-date-badge">
                        <i class="fa-regular fa-clock"></i> {{ $dateRangeDisplay }}
                    </div>
                </form>
            </div>

            @if($activeSummary === 'operational')
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.1.1 — OPERATIONAL SUMMARY
                     ========================================================================= -->

                <!-- 1. Top KPI Summary Cards Row (5 Cards - Aligned with Maintenance Dashboard) -->
                <section class="stats-grid descriptive-stats-grid">
                    <!-- Scheduled Trips -->
                    <x-ui.summary-card
                        label="Scheduled Trips"
                        value="{{ number_format($totalScheduledTrips) }}"
                        small="{{ $completedTrips }} Completed · {{ $pendingDdrTrips }} Pending · {{ $missedTrips }} Missed"
                        icon="fa-route"
                        color="blue"
                        href="{{ route('trip-schedule') }}"
                    />

                    <!-- Trip Assignments -->
                    <x-ui.summary-card
                        label="Trip Assignments"
                        value="{{ number_format($totalAssignedBuses) }}"
                        small="{{ $assignmentFulfillmentRate }}% Filled · {{ $totalAssignedBuses }} of {{ $totalRequiredBuses }} req"
                        icon="fa-people-arrows"
                        color="purple"
                        href="{{ route('driver-bus-assignment') }}"
                    />

                    <!-- Fleet Readiness -->
                    <x-ui.summary-card
                        label="Fleet Readiness"
                        value="{{ $fleetAvailability }}%"
                        small="{{ $activeBuses }}/{{ $totalBuses }} Active · {{ $underMaintenance }} in repair"
                        icon="fa-bus"
                        color="green"
                        href="{{ route('bus-availability') }}"
                    />

                    <!-- GPS Records -->
                    <x-ui.summary-card
                        label="GPS Records"
                        value="{{ number_format($gpsTripCount) }}"
                        small="{{ number_format($gpsTotalDistance, 1) }} km · {{ round($gpsAverageSpeed, 1) }} km/h avg"
                        icon="fa-location-dot"
                        color="cyan"
                    />

                    <!-- Total Incidents -->
                    <x-ui.summary-card
                        label="Total Incidents"
                        value="{{ number_format($totalIncidents) }}"
                        small="{{ $resolvedIncidents }} Resolved · {{ $breakdownsCount }} Breakdowns"
                        icon="fa-triangle-exclamation"
                        color="red"
                        href="{{ route('incidents') }}"
                    />
                </section>

                <!-- 2. Charts Row 1: Activity Trend (Wide) + Fleet Health Donut -->
                <div class="descriptive-grid grid-wide-left">
                    <!-- Scheduled vs GPS Activity Trend -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">OPERATIONAL TELEMETRY</span>
                                <h2><i class="fa-solid fa-chart-column"></i> Trip Scheduling & Telemetry Trend</h2>
                                <p>Scheduled trips, completions, and processed GPS telemetry across {{ $periodLabel }}.</p>
                            </div>

                            <div class="chart-header-actions">
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-route text-blue"></i>
                                    <span>Trips: <strong>{{ $totalScheduledTrips }}</strong></span>
                                </div>
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-circle-check text-green"></i>
                                    <span>Done: <strong>{{ $completedTrips }}</strong></span>
                                </div>
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-location-dot text-cyan"></i>
                                    <span>GPS: <strong>{{ $gpsTripCount }}</strong></span>
                                </div>
                            </div>
                        </div>

                        @if($totalScheduledTrips > 0 || $gpsTripCount > 0 || $totalIncidents > 0)
                            <div class="descriptive-chart-shell">
                                <canvas id="operationalActivityTrendChart"></canvas>
                            </div>

                            <!-- Telemetry Summary Strip (Eliminates visual emptiness) -->
                            <div class="telemetry-summary-row">
                                <div class="telemetry-tile">
                                    <i class="fa-solid fa-road text-blue"></i>
                                    <div>
                                        <strong>{{ number_format($gpsTotalDistance, 1) }} km</strong>
                                        <small>Total Distance Traveled</small>
                                    </div>
                                </div>
                                <div class="telemetry-tile">
                                    <i class="fa-solid fa-gauge text-cyan"></i>
                                    <div>
                                        <strong>{{ round($gpsAverageSpeed, 1) }} km/h</strong>
                                        <small>Avg Telemetry Speed</small>
                                    </div>
                                </div>
                                <div class="telemetry-tile">
                                    <i class="fa-solid fa-stopwatch text-amber"></i>
                                    <div>
                                        <strong>{{ number_format($gpsTotalIdleMinutes) }} mins</strong>
                                        <small>Engine Idle Duration</small>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-chart-line"></i></div>
                                <h3>No Activity in Selected Period</h3>
                                <p>No scheduled trips, GPS batch logs, or incidents match {{ $periodLabel }}. Select a broader date range.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Fleet Health Donut -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">FLEET READINESS</span>
                                <h2><i class="fa-solid fa-chart-pie"></i> Fleet Availability Distribution</h2>
                                <p>Live operational status of the {{ $totalBuses }} registered fleet buses.</p>
                            </div>
                            <a href="{{ route('bus-availability') }}" class="dashboard-view-link">
                                <span>Fleet Bay</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        @if($totalBuses > 0)
                            <div class="fleet-health-visual-wrap">
                                <div class="fleet-health-chart-container">
                                    <canvas id="operationalFleetDonutChart"></canvas>
                                    <div class="fleet-health-chart-center" aria-hidden="true">
                                        <strong>{{ $fleetAvailability }}%</strong>
                                        <span>Available</span>
                                    </div>
                                </div>
                                <div class="fleet-health-status-stack">
                                    @foreach($fleetStatusStats as $item)
                                        <div class="fleet-health-row">
                                            <div class="fleet-health-left">
                                                <span class="status-indicator-dot" style="background:{{ $item->color }}"></span>
                                                <div class="status-name-desc">
                                                    <strong>{{ $item->label }}</strong>
                                                    <small>{{ $item->label === 'Active' ? 'Available for daily dispatch' : ($item->label === 'Under Maintenance' ? 'Under shop repair bay' : 'Standby or restricted') }}</small>
                                                </div>
                                            </div>
                                            <div class="fleet-health-right">
                                                <span class="fleet-count" style="color:{{ $item->color }}">{{ $item->count }}</span>
                                                <small>{{ $item->pct }}%</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-bus"></i></div>
                                <h3>No Buses Found</h3>
                                <p>The Bus Master List is currently empty.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. Charts Row 2: Trip Status Donut + Incidents & Safety Overview -->
                <div class="descriptive-grid">
                    <!-- Trip Status Distribution -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">DISPATCH BREAKDOWN</span>
                                <h2><i class="fa-solid fa-list-check"></i> Trip Execution Status</h2>
                                <p>Breakdown of {{ $totalScheduledTrips }} scheduled trips for {{ $periodLabel }}.</p>
                            </div>
                            <a href="{{ route('trip-schedule') }}" class="dashboard-view-link">
                                <span>All Schedules</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        @if($totalScheduledTrips > 0)
                            <div class="fleet-health-visual-wrap">
                                <div class="fleet-health-chart-container">
                                    <canvas id="operationalTripStatusChart"></canvas>
                                    <div class="fleet-health-chart-center" aria-hidden="true">
                                        <strong>{{ $totalScheduledTrips }}</strong>
                                        <span>Trips</span>
                                    </div>
                                </div>
                                <div class="fleet-health-status-stack">
                                    @foreach($tripStatusStats as $item)
                                        <div class="fleet-health-row">
                                            <div class="fleet-health-left">
                                                <span class="status-indicator-dot" style="background:{{ $item->color }}"></span>
                                                <div class="status-name-desc">
                                                    <strong>{{ $item->label }}</strong>
                                                    <small>{{ $item->label === 'Completed' ? 'Successfully fulfilled' : ($item->label === 'Pending DDR' ? 'Awaiting driver report' : ($item->label === 'Scheduled' ? 'Upcoming dispatch' : 'Trip cancelled/missed')) }}</small>
                                                </div>
                                            </div>
                                            <div class="fleet-health-right">
                                                <span class="fleet-count" style="color:{{ $item->color }}">{{ $item->count }}</span>
                                                <small>{{ $item->pct }}%</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-route"></i></div>
                                <h3>No Scheduled Trips Recorded</h3>
                                <p>No scheduled trips match the selected period or bus filter.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Incidents Breakdown or Fleet Safety Excellence -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">SAFETY & DISRUPTION</span>
                                <h2><i class="fa-solid fa-shield-halved"></i> Incidents & Safety Overview</h2>
                                <p>Road disruptions, breakdowns, and dispatch responses for {{ $periodLabel }}.</p>
                            </div>
                            <a href="{{ route('incidents') }}" class="dashboard-view-link">
                                <span>Incidents</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        @if($totalIncidents > 0)
                            <div class="descriptive-chart-shell short">
                                <canvas id="operationalIncidentChart"></canvas>
                            </div>
                        @else
                            <!-- Executive Safety Excellence Card (eliminates gray empty void) -->
                            <div class="safety-excellence-wrap">
                                <div class="safety-excellence-badge">
                                    <div class="safety-icon-box">
                                        <i class="fa-solid fa-shield-heart"></i>
                                    </div>
                                    <div class="safety-text">
                                        <h3>Zero Incidents Recorded</h3>
                                        <p>100% Incident-Free Fleet Operations. Zero vehicle breakdowns, road collisions, or service interruptions recorded in {{ $periodLabel }}.</p>
                                    </div>
                                </div>

                                <div class="safety-metric-row">
                                    <div class="safety-metric-tile">
                                        <span class="safety-metric-num">0</span>
                                        <span class="safety-metric-label">Breakdowns</span>
                                        <small>Zero vehicle failures</small>
                                    </div>
                                    <div class="safety-metric-tile">
                                        <span class="safety-metric-num">0</span>
                                        <span class="safety-metric-label">Dispatches</span>
                                        <small>No emergency swaps</small>
                                    </div>
                                    <div class="safety-metric-tile">
                                        <span class="safety-metric-num">100%</span>
                                        <span class="safety-metric-label">Reliability</span>
                                        <small>Clean road operations</small>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 4. Tables Row 1: Recent Scheduled Trips (Full Width Modern Item Cards) -->
                <div class="descriptive-dashboard-card full-width">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="dashboard-eyebrow">DISPATCH LOGS</span>
                            <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Scheduled Trips</h2>
                            <p>Latest dispatch tickets and completion records from the Operations scheduling module.</p>
                        </div>
                        <a href="{{ route('trip-schedule') }}" class="dashboard-view-link">
                            <span>View All Trips</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @if($recentTrips->isNotEmpty())
                        <div class="recent-descriptive-list">
                            @foreach($recentTrips as $trip)
                                @php
                                    $statusKey = strtolower(str_replace(' ', '-', $trip->status ?? 'scheduled'));
                                    $assignedBusesCount = $trip->assignments->count();
                                    $requiredBuses = (int) ($trip->required_buses ?? 1);
                                @endphp
                                <div class="recent-descriptive-item">
                                    <div class="item-icon-box blue">
                                        <i class="fa-solid fa-bus"></i>
                                    </div>
                                    <div class="item-main">
                                        <div class="item-header-row">
                                            <div class="item-identifiers">
                                                <strong class="item-code">{{ $trip->trip_code ?: 'T-'.$trip->id }}</strong>
                                                <span class="badge-chip shift-chip">
                                                    <i class="fa-regular fa-clock"></i> {{ $trip->shift ?? 'Regular' }} ({{ \Carbon\Carbon::parse($trip->departure_time)->format('h:i A') }})
                                                </span>
                                                <span class="badge-chip">
                                                    <i class="fa-solid fa-route"></i> {{ $trip->shuttleRoute->route_name ?? 'Standard Route' }}
                                                </span>
                                                @if(!empty($trip->client_name) || !empty($trip->shuttleRoute?->client_name))
                                                    <span class="badge-chip">
                                                        <i class="fa-solid fa-building"></i> {{ $trip->client_name ?? $trip->shuttleRoute->client_name }}
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="status-pill status-{{ $statusKey }}">
                                                {{ $trip->status ?? 'Scheduled' }}
                                            </span>
                                        </div>

                                        <div class="item-details-row">
                                            <span>
                                                <i class="fa-solid fa-bus-simple"></i>
                                                {{ $assignedBusesCount }}/{{ $requiredBuses }} buses assigned
                                            </span>
                                            @if($trip->assignments->isNotEmpty())
                                                <span>
                                                    <i class="fa-solid fa-id-badge"></i>
                                                    Fleet: {{ $trip->assignments->map(fn($a) => ($a->bus?->bus_no ?? 'Bus').($a->driver_id ? ' ['.$a->driver_id.']' : ''))->implode(', ') }}
                                                </span>
                                            @else
                                                <span class="text-amber">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> No bus or driver assigned yet
                                                </span>
                                            @endif
                                            <span class="text-muted">
                                                <i class="fa-regular fa-calendar"></i> {{ \Carbon\Carbon::parse($trip->trip_date)->format('M d, Y') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="dashboard-empty-state">
                            <div class="dashboard-empty-icon"><i class="fa-solid fa-route"></i></div>
                            <h3>No Recent Trips Found</h3>
                            <p>No dispatch records recorded for the selected filter criteria.</p>
                        </div>
                    @endif
                </div>

                <!-- 5. Tables Row 2: Recent Incidents (Full Width Modern Item Cards) -->
                @if($recentIncidents->isNotEmpty())
                    <div class="descriptive-dashboard-card full-width">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">INCIDENT AUDIT</span>
                                <h2><i class="fa-solid fa-triangle-exclamation"></i> Recent Incident Reports</h2>
                                <p>Latest road breakdown and service disruption logs recorded by Operations.</p>
                            </div>
                            <a href="{{ route('incidents') }}" class="dashboard-view-link">
                                <span>View All Incidents</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="recent-descriptive-list">
                            @foreach($recentIncidents as $inc)
                                @php
                                    $incStatusKey = strtolower(str_replace(' ', '-', $inc->status ?? 'reported'));
                                    $isBreakdown = $inc->incident_type === 'Bus Breakdown';
                                @endphp
                                <div class="recent-descriptive-item {{ $isBreakdown ? 'urgent' : '' }}">
                                    <div class="item-icon-box {{ $isBreakdown ? 'red' : 'amber' }}">
                                        <i class="fa-solid {{ $isBreakdown ? 'fa-triangle-exclamation' : 'fa-circle-exclamation' }}"></i>
                                    </div>
                                    <div class="item-main">
                                        <div class="item-header-row">
                                            <div class="item-identifiers">
                                                <strong class="item-code">{{ $inc->incident_no ?: 'INC-'.$inc->id }}</strong>
                                                <span class="badge-chip bus-chip">
                                                    <i class="fa-solid fa-bus"></i> {{ $inc->bus?->bus_no ?? 'Bus N/A' }}
                                                </span>
                                                <span class="badge-chip">
                                                    {{ $inc->incident_type ?? 'General Incident' }}
                                                </span>
                                            </div>
                                            <span class="status-pill status-{{ $incStatusKey }}">
                                                {{ $inc->status ?? 'Reported' }}
                                            </span>
                                        </div>

                                        <div class="item-description-text">
                                            {{ $inc->description ?: 'No narrative description provided for this incident.' }}
                                        </div>

                                        <div class="item-details-row">
                                            @if($inc->replacement)
                                                <span class="text-green">
                                                    <i class="fa-solid fa-shield-check"></i> Replacement Dispatched: {{ $inc->replacement->replacementBus?->bus_no ?? 'Bus' }}
                                                </span>
                                            @endif
                                            @if($inc->driver_name)
                                                <span><i class="fa-solid fa-user"></i> Driver: {{ $inc->driver_name }}</span>
                                            @endif
                                            <span class="text-muted">
                                                <i class="fa-regular fa-clock"></i> {{ ($inc->incident_reported_at ?: $inc->created_at)->format('M d, Y h:i A') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            @else
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.1.2 — ADMINISTRATIVE SUMMARY
                     ========================================================================= -->

                <!-- 1. Top KPI Summary Cards Row (4 Cards - Aligned with Purchase & Maintenance) -->
                <section class="stats-grid descriptive-stats-grid admin-stats-grid">
                    <!-- Active Users -->
                    <x-ui.summary-card
                        label="Active Users"
                        value="{{ $activeUsers }}"
                        small="{{ $activeUsersRate }}% Active · {{ $totalUsers }} total registered accounts"
                        icon="fa-users"
                        color="blue"
                        href="{{ route('admin.users') }}"
                    />

                    <!-- Pending / Inactive -->
                    <x-ui.summary-card
                        label="Pending / Inactive"
                        value="{{ $inactiveUsers }}"
                        small="{{ $inactiveUsers }} awaiting review or deactivated"
                        icon="fa-user-clock"
                        color="yellow"
                        href="{{ route('admin.applications') }}"
                    />

                    <!-- User Activities -->
                    <x-ui.summary-card
                        label="User Activities"
                        value="{{ number_format($totalActivities) }}"
                        small="Documented audit logs in {{ $periodLabel }}"
                        icon="fa-file-shield"
                        color="green"
                        href="{{ route('admin.activity-logs') }}"
                    />

                    <!-- System Notifications -->
                    <x-ui.summary-card
                        label="System Notifications"
                        value="{{ number_format($totalNotifications) }}"
                        small="Automated threshold alerts & workflow events"
                        icon="fa-bell"
                        color="purple"
                        href="{{ route('admin.notifications') }}"
                    />
                </section>

                <!-- 2. Charts Row 1: Activity Trend (Smooth Curve) + Activity by Module Donut -->
                <div class="descriptive-grid grid-wide-left">
                    <!-- Activity & Notification Volume Over Time -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">AUDIT & SECURITY</span>
                                <h2><i class="fa-solid fa-chart-line"></i> User Activity & System Alerts Over Time</h2>
                                <p>Volume of user action audit logs and system notifications across {{ $periodLabel }}.</p>
                            </div>

                            <div class="chart-header-actions">
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-file-shield text-blue"></i>
                                    <span>Logs: <strong>{{ number_format($totalActivities) }}</strong></span>
                                </div>
                                <div class="chart-kpi-pill">
                                    <i class="fa-solid fa-bell text-amber"></i>
                                    <span>Alerts: <strong>{{ number_format($totalNotifications) }}</strong></span>
                                </div>
                            </div>
                        </div>

                        @if($totalActivities > 0 || $totalNotifications > 0)
                            <div class="descriptive-chart-shell">
                                <canvas id="adminActivityTrendChart"></canvas>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-chart-line"></i></div>
                                <h3>No System Activity Recorded</h3>
                                <p>No audit events or notifications were logged in {{ $periodLabel }}.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Activity by Department Module Donut -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">CROSS-MODULE UTILIZATION</span>
                                <h2><i class="fa-solid fa-chart-pie"></i> Activity by Module</h2>
                                <p>Distribution of {{ number_format($totalActivities) }} actions across FROMS departments.</p>
                            </div>
                            <a href="{{ route('admin.activity-logs') }}" class="dashboard-view-link">
                                <span>Audit Logs</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        @if($totalActivities > 0)
                            <div class="fleet-health-visual-wrap">
                                <div class="fleet-health-chart-container">
                                    <canvas id="adminActivityModuleChart"></canvas>
                                    <div class="fleet-health-chart-center" aria-hidden="true">
                                        <strong>{{ number_format($totalActivities) }}</strong>
                                        <span>Logs</span>
                                    </div>
                                </div>
                                <div class="fleet-health-status-stack">
                                    @foreach($moduleStats as $item)
                                        <div class="fleet-health-row">
                                            <div class="fleet-health-left">
                                                <span class="status-indicator-dot" style="background:{{ $item->color }}"></span>
                                                <div class="status-name-desc">
                                                    <strong>{{ $item->label }} Module</strong>
                                                    <small>Actions in department</small>
                                                </div>
                                            </div>
                                            <div class="fleet-health-right">
                                                <span class="fleet-count" style="color:{{ $item->color }}">{{ $item->count }}</span>
                                                <small>{{ $item->pct }}%</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-chart-pie"></i></div>
                                <h3>No Module Activity</h3>
                                <p>No audit trail records found for {{ $periodLabel }}.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. Charts Row 2: Notifications by Module Bar + Users by Department Donut -->
                <div class="descriptive-grid">
                    <!-- Notifications by Department -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">DISPATCHED ALERTS</span>
                                <h2><i class="fa-solid fa-bell"></i> Notifications by Department</h2>
                                <p>System alerts generated per operational department.</p>
                            </div>
                            <a href="{{ route('admin.notifications') }}" class="dashboard-view-link">
                                <span>Notification Center</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        @if($totalNotifications > 0)
                            <div class="descriptive-chart-shell short">
                                <canvas id="adminNotificationsModuleChart"></canvas>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-bell-slash"></i></div>
                                <h3>No Notifications Generated</h3>
                                <p>No system alerts or threshold events were triggered in {{ $periodLabel }}.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Users by Department Donut -->
                    <div class="descriptive-dashboard-card">
                        <div class="dashboard-card-header">
                            <div>
                                <span class="dashboard-eyebrow">WORKFORCE ROSTER</span>
                                <h2><i class="fa-solid fa-users"></i> Users by Department</h2>
                                <p>Head and staff personnel distribution across {{ $totalUsers }} total accounts.</p>
                            </div>
                            <a href="{{ route('admin.users') }}" class="dashboard-view-link">
                                <span>User Directory</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>

                        @if($totalUsers > 0)
                            <div class="fleet-health-visual-wrap">
                                <div class="fleet-health-chart-container">
                                    <canvas id="adminUserDepartmentChart"></canvas>
                                    <div class="fleet-health-chart-center" aria-hidden="true">
                                        <strong>{{ $totalUsers }}</strong>
                                        <span>Users</span>
                                    </div>
                                </div>
                                <div class="fleet-health-status-stack">
                                    @foreach($userDeptStats as $item)
                                        <div class="fleet-health-row">
                                            <div class="fleet-health-left">
                                                <span class="status-indicator-dot" style="background:{{ $item->color }}"></span>
                                                <div class="status-name-desc">
                                                    <strong>{{ $item->label }}</strong>
                                                    <small>Active personnel</small>
                                                </div>
                                            </div>
                                            <div class="fleet-health-right">
                                                <span class="fleet-count" style="color:{{ $item->color }}">{{ $item->count }}</span>
                                                <small>{{ $item->pct }}%</small>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-empty-state">
                                <div class="dashboard-empty-icon"><i class="fa-solid fa-users"></i></div>
                                <h3>No Accounts Registered</h3>
                                <p>No user accounts exist in the system database.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 4. Tables Row 1: Recent System Activities (Full Width Modern Item Cards) -->
                <div class="descriptive-dashboard-card full-width">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="dashboard-eyebrow">USER AUDIT TRAIL</span>
                            <h2><i class="fa-solid fa-shield-halved"></i> Recent System Activities (Audit Trail)</h2>
                            <p>Latest actions, updates, and state mutations logged across FROMS.</p>
                        </div>
                        <a href="{{ route('admin.activity-logs') }}" class="dashboard-view-link">
                            <span>View All Logs</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @if($recentActivities->isNotEmpty())
                        <div class="recent-descriptive-list">
                            @foreach($recentActivities as $act)
                                @php
                                    $actModuleKey = strtolower($act->module ?? 'admin');
                                    $initials = strtoupper(substr($act->user->name ?? 'System', 0, 2));
                                @endphp
                                <div class="recent-descriptive-item">
                                    <div class="item-avatar-box">
                                        {{ $initials }}
                                    </div>
                                    <div class="item-main">
                                        <div class="item-header-row">
                                            <div class="item-identifiers">
                                                <strong class="item-user-name">{{ $act->user_name ?: ($act->user?->name ?: 'System Administrator') }}</strong>
                                                <span class="badge-chip dept-{{ $actModuleKey }}">
                                                    {{ $act->module ?? 'Admin' }}
                                                </span>
                                                <span class="badge-chip">
                                                    {{ ucfirst(strtolower($act->event_type ?? 'Action')) }}
                                                </span>
                                            </div>
                                            <span class="item-time-ago">
                                                <i class="fa-regular fa-clock"></i> {{ $act->created_at ? $act->created_at->diffForHumans() : '—' }}
                                            </span>
                                        </div>

                                        <div class="item-description-text">
                                            {{ $act->activity ?: ($act->description ?: ($act->details ?: 'System action executed.')) }}
                                        </div>

                                        <div class="item-details-row">
                                            @if(!empty($act->ip_address))
                                                <span><i class="fa-solid fa-network-wired"></i> IP: {{ $act->ip_address }}</span>
                                            @endif
                                            <span class="text-muted">
                                                <i class="fa-regular fa-calendar"></i> {{ $act->created_at ? $act->created_at->format('M d, Y h:i A') : '—' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="dashboard-empty-state">
                            <div class="dashboard-empty-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
                            <h3>No Activity Records</h3>
                            <p>No user actions were logged for the selected period.</p>
                        </div>
                    @endif
                </div>

                <!-- 5. Tables Row 2: Recent System Notifications (Full Width Modern Item Cards) -->
                <div class="descriptive-dashboard-card full-width">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="dashboard-eyebrow">SYSTEM BROADCASTS</span>
                            <h2><i class="fa-solid fa-bell"></i> Recent System Notifications</h2>
                            <p>Latest dispatch alerts, threshold reminders, and background processing notifications.</p>
                        </div>
                        <a href="{{ route('admin.notifications') }}" class="dashboard-view-link">
                            <span>View All Notifications</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @if($recentNotifications->isNotEmpty())
                        <div class="recent-descriptive-list">
                            @foreach($recentNotifications as $notif)
                                @php
                                    $notifModKey = strtolower($notif->module ?? 'general');
                                @endphp
                                <div class="recent-descriptive-item">
                                    <div class="item-icon-box notif-{{ $notifModKey }}">
                                        <i class="fa-solid fa-bell"></i>
                                    </div>
                                    <div class="item-main">
                                        <div class="item-header-row">
                                            <div class="item-identifiers">
                                                <span class="badge-chip dept-{{ $notifModKey }}">
                                                    {{ $notif->module ?? 'General' }}
                                                </span>
                                                <strong class="item-entity-name">{{ $notif->entity_type ?: 'System Event' }}</strong>
                                                <span class="badge-chip">
                                                    {{ ucfirst(strtolower(str_replace('_', ' ', $notif->action ?? 'alert'))) }}
                                                </span>
                                            </div>
                                            <span class="item-time-ago">
                                                <i class="fa-regular fa-clock"></i> {{ $notif->created_at ? $notif->created_at->diffForHumans() : '—' }}
                                            </span>
                                        </div>

                                        <div class="item-description-text">
                                            {{ $notif->message ?: 'Notification triggered.' }}
                                        </div>

                                        <div class="item-details-row">
                                            <span class="text-muted">
                                                <i class="fa-regular fa-calendar"></i> {{ $notif->created_at ? $notif->created_at->format('M d, Y h:i A') : '—' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="dashboard-empty-state">
                            <div class="dashboard-empty-icon"><i class="fa-solid fa-bell-slash"></i></div>
                            <h3>No Notifications</h3>
                            <p>No system alerts or threshold events were triggered in {{ $periodLabel }}.</p>
                        </div>
                    @endif
                </div>

            @endif
        </main>
    </div>
</x-layout.app>
