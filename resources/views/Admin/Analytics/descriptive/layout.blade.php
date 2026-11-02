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

                <!-- 1. KPI Cards Row (5 Cards) -->
                <div class="descriptive-kpi-grid">
                    <!-- Scheduled Trips -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">Scheduled Trips</span>
                            <div class="d-kpi-icon blue"><i class="fa-solid fa-route"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($totalScheduledTrips) }}</span>
                            <span class="d-kpi-badge {{ $tripCompletionRate >= 80 ? 'badge-green' : ($tripCompletionRate > 0 ? 'badge-blue' : 'badge-amber') }}">
                                {{ $tripCompletionRate }}% Done
                            </span>
                        </div>
                        <p class="d-kpi-subtext">
                            {{ $completedTrips }} Completed · {{ $pendingDdrTrips }} Pending · {{ $missedTrips }} Missed
                        </p>
                    </div>

                    <!-- Assignments -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">Trip Assignments</span>
                            <div class="d-kpi-icon purple"><i class="fa-solid fa-people-arrows"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($totalAssignedBuses) }}</span>
                            <span class="d-kpi-badge {{ $assignmentFulfillmentRate >= 80 ? 'badge-green' : ($assignmentFulfillmentRate > 0 ? 'badge-blue' : 'badge-amber') }}">
                                {{ $assignmentFulfillmentRate }}% Filled
                            </span>
                        </div>
                        <p class="d-kpi-subtext">
                            {{ $totalAssignedBuses }} buses assigned of {{ $totalRequiredBuses }} required
                        </p>
                    </div>

                    <!-- Fleet Readiness -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">Fleet Readiness</span>
                            <div class="d-kpi-icon green"><i class="fa-solid fa-bus"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ $fleetAvailability }}%</span>
                            <span class="d-kpi-badge badge-green">{{ $activeBuses }}/{{ $totalBuses }}</span>
                        </div>
                        <p class="d-kpi-subtext">
                            {{ $underMaintenance }} in repair bay · {{ $inactiveBuses }} inactive
                        </p>
                    </div>

                    <!-- GPS Telemetry -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">GPS Records</span>
                            <div class="d-kpi-icon cyan"><i class="fa-solid fa-location-dot"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($gpsTripCount) }}</span>
                            <span class="d-kpi-badge badge-blue">{{ number_format($gpsTotalDistance, 1) }} km</span>
                        </div>
                        <p class="d-kpi-subtext">
                            Avg speed: {{ round($gpsAverageSpeed, 1) }} km/h · {{ number_format($gpsTotalIdleMinutes) }}m idle
                        </p>
                    </div>

                    <!-- Incidents -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">Total Incidents</span>
                            <div class="d-kpi-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($totalIncidents) }}</span>
                            <span class="d-kpi-badge {{ $totalIncidents > 0 ? 'badge-amber' : 'badge-green' }}">
                                {{ $resolvedIncidents }} Resolved
                            </span>
                        </div>
                        <p class="d-kpi-subtext">
                            {{ $breakdownsCount }} breakdowns · {{ $replacementsDispatched }} replacements dispatched
                        </p>
                    </div>
                </div>

                <!-- 2. Charts Row 1: Activity Trend (Wide) + Fleet Health Donut -->
                <div class="descriptive-grid grid-wide-left">
                    <!-- Scheduled vs GPS Activity Trend -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-chart-column"></i> Operational Activity & Telemetry Trend</h3>
                                <p>Scheduled trips, completions, processed GPS telemetry, and incident volume by date bucket.</p>
                            </div>
                            <span class="d-panel-pill">{{ $periodLabel }}</span>
                        </div>
                        @if($totalScheduledTrips > 0 || $gpsTripCount > 0 || $totalIncidents > 0)
                            <div class="d-chart-container">
                                <canvas id="operationalActivityTrendChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-chart-line"></i></div>
                                <strong>No Activity in Selected Period</strong>
                                <p>No scheduled trips, GPS batch logs, or incidents match {{ $periodLabel }}. Select a broader date range.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Fleet Health Donut -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-chart-pie"></i> Fleet Availability Distribution</h3>
                                <p>Live operational status of the {{ $totalBuses }} registered fleet buses.</p>
                            </div>
                            <span class="d-panel-pill">Current State</span>
                        </div>
                        @if($totalBuses > 0)
                            <div class="d-donut-row">
                                <div class="d-donut-chart-box">
                                    <canvas id="operationalFleetDonutChart"></canvas>
                                    <div class="d-donut-center-stat">
                                        <strong>{{ $fleetAvailability }}%</strong>
                                        <small>Available</small>
                                    </div>
                                </div>
                                <div class="d-donut-legend-stack">
                                    <div class="d-legend-item">
                                        <span class="d-legend-left"><i class="d-legend-dot" style="background:#16a34a"></i> Active Fleet</span>
                                        <span class="d-legend-val">{{ $activeBuses }}</span>
                                    </div>
                                    <div class="d-legend-item">
                                        <span class="d-legend-left"><i class="d-legend-dot" style="background:#f59e0b"></i> Under Maintenance</span>
                                        <span class="d-legend-val">{{ $underMaintenance }}</span>
                                    </div>
                                    <div class="d-legend-item">
                                        <span class="d-legend-left"><i class="d-legend-dot" style="background:#94a3b8"></i> Inactive / Reserve</span>
                                        <span class="d-legend-val">{{ $inactiveBuses }}</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-bus"></i></div>
                                <strong>No Buses Found</strong>
                                <p>The Bus Master List is currently empty.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. Charts Row 2: Trip Status Donut + Incident Types -->
                <div class="descriptive-grid">
                    <!-- Trip Status Distribution -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-list-check"></i> Trip Execution Status</h3>
                                <p>Breakdown of {{ $totalScheduledTrips }} scheduled trips for {{ $periodLabel }}.</p>
                            </div>
                        </div>
                        @if($totalScheduledTrips > 0)
                            <div class="d-chart-container short">
                                <canvas id="operationalTripStatusChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-route"></i></div>
                                <strong>No Scheduled Trips Recorded</strong>
                                <p>No scheduled trips match the selected period or bus filter.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Incidents Breakdown -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-triangle-exclamation"></i> Incidents by Category</h3>
                                <p>Breakdown of reported incident causes and dispatch responses.</p>
                            </div>
                            <span class="d-panel-pill">{{ $totalIncidents }} Total</span>
                        </div>
                        @if($totalIncidents > 0)
                            <div class="d-chart-container short">
                                <canvas id="operationalIncidentChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-shield-heart"></i></div>
                                <strong>Zero Incidents Recorded</strong>
                                <p>No breakdown or road incident was logged for the selected period.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 4. Tables Row: Recent Scheduled Trips & Recent Incidents -->
                <div class="descriptive-grid">
                    <!-- Recent Scheduled Trips Table -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Scheduled Trips</h3>
                                <p>Latest dispatch tickets and completion records from Operations.</p>
                            </div>
                        </div>
                        @if($recentTrips->isNotEmpty())
                            <div class="d-table-wrapper">
                                <table class="d-table">
                                    <thead>
                                        <tr>
                                            <th>Trip Code</th>
                                            <th>Date & Shift</th>
                                            <th>Route / Client</th>
                                            <th>Bus / Driver</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentTrips as $trip)
                                            @php
                                                $assignedBus = $trip->assignments->first()?->bus?->bus_no ?? 'Unassigned';
                                                $driver = $trip->assignments->first()?->driver_name ?? '—';
                                                $statusClass = match($trip->status) {
                                                    'Completed' => 'pill-green',
                                                    'Scheduled' => 'pill-blue',
                                                    'Pending DDR' => 'pill-amber',
                                                    'Missed' => 'pill-red',
                                                    default => 'pill-gray',
                                                };
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $trip->trip_code }}</strong></td>
                                                <td>
                                                    {{ \Carbon\Carbon::parse($trip->trip_date)->format('M j, Y') }}
                                                    <br><small style="color:#64748b">{{ $trip->shift }} ({{ $trip->departure_time ? \Carbon\Carbon::parse($trip->departure_time)->format('h:i A') : '—' }})</small>
                                                </td>
                                                <td>
                                                    <strong>{{ $trip->route_name_snapshot ?: ($trip->shuttleRoute?->route_name ?? 'Shuttle Service') }}</strong>
                                                    <br><small style="color:#64748b">{{ $trip->client_name ?: 'Internal Fleet' }}</small>
                                                </td>
                                                <td>
                                                    <span style="font-weight:600">{{ $assignedBus }}</span>
                                                    <br><small style="color:#64748b">{{ $driver }}</small>
                                                </td>
                                                <td>
                                                    <span class="d-status-pill {{ $statusClass }}">{{ $trip->status }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-route"></i></div>
                                <strong>No Scheduled Trips Available</strong>
                                <p>No scheduled trip tickets have been recorded for the selected filter.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recent Incidents Table -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-triangle-exclamation"></i> Recent Incident Reports</h3>
                                <p>Latest road breakdown and service disruption records.</p>
                            </div>
                        </div>
                        @if($recentIncidents->isNotEmpty())
                            <div class="d-table-wrapper">
                                <table class="d-table">
                                    <thead>
                                        <tr>
                                            <th>Incident #</th>
                                            <th>Type</th>
                                            <th>Bus & Driver</th>
                                            <th>Reported Date</th>
                                            <th>Status / Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentIncidents as $incident)
                                            @php
                                                $busNo = $incident->bus?->bus_no ?? 'Unassigned';
                                                $repBus = $incident->replacement?->replacementBus?->bus_no;
                                                $statusClass = match($incident->status) {
                                                    'Resolved' => 'pill-green',
                                                    'Replacement Bus Dispatched' => 'pill-blue',
                                                    'Reported' => 'pill-amber',
                                                    default => 'pill-red',
                                                };
                                                $reportedAt = $incident->incident_reported_at ? \Carbon\Carbon::parse($incident->incident_reported_at) : $incident->created_at;
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $incident->incident_no }}</strong></td>
                                                <td>
                                                    {{ $incident->incident_type }}
                                                    @if($repBus)
                                                        <br><small style="color:#2563eb"><i class="fa-solid fa-repeat"></i> Repl: {{ $repBus }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <strong>{{ $busNo }}</strong>
                                                    <br><small style="color:#64748b">{{ $incident->driver_name ?: '—' }}</small>
                                                </td>
                                                <td>{{ $reportedAt ? $reportedAt->format('M j, Y h:i A') : '—' }}</td>
                                                <td>
                                                    <span class="d-status-pill {{ $statusClass }}">{{ $incident->status }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-shield-halved"></i></div>
                                <strong>No Incident Reports</strong>
                                <p>No breakdowns or vehicle incidents match the selected period.</p>
                            </div>
                        @endif
                    </div>
                </div>

            @else
                <!-- =========================================================================
                     THESIS OBJECTIVE 6.1.2 — ADMINISTRATIVE SUMMARY
                     ========================================================================= -->

                <!-- 1. KPI Cards Row (4 Cards) -->
                <div class="descriptive-kpi-grid admin-kpis">
                    <!-- Active Users -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">Active Users</span>
                            <div class="d-kpi-icon blue"><i class="fa-solid fa-user-check"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($activeUsers) }}</span>
                            <span class="d-kpi-badge badge-green">{{ round(($activeUsers / max(1, $totalUsers)) * 100) }}% Active</span>
                        </div>
                        <p class="d-kpi-subtext">
                            {{ $totalUsers }} total registered accounts across all modules
                        </p>
                    </div>

                    <!-- Pending / Inactive Users -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">Pending / Inactive</span>
                            <div class="d-kpi-icon amber"><i class="fa-solid fa-user-clock"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($inactiveUsers) }}</span>
                            <span class="d-kpi-badge badge-amber">Awaiting</span>
                        </div>
                        <p class="d-kpi-subtext">
                            Accounts requiring role assignment or pending onboarding
                        </p>
                    </div>

                    <!-- User Activities (Audit Logs) -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">User Activities</span>
                            <div class="d-kpi-icon green"><i class="fa-solid fa-clipboard-list"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($totalActivities) }}</span>
                            <span class="d-kpi-badge badge-blue">Audit Trail</span>
                        </div>
                        <p class="d-kpi-subtext">
                            Documented system actions and mutations in {{ $periodLabel }}
                        </p>
                    </div>

                    <!-- System Notifications -->
                    <div class="d-kpi-card">
                        <div class="d-kpi-top">
                            <span class="d-kpi-label">System Notifications</span>
                            <div class="d-kpi-icon purple"><i class="fa-solid fa-bell"></i></div>
                        </div>
                        <div class="d-kpi-middle">
                            <span class="d-kpi-value">{{ number_format($totalNotifications) }}</span>
                            <span class="d-kpi-badge badge-purple">Dispatched</span>
                        </div>
                        <p class="d-kpi-subtext">
                            Automated threshold alerts and workflow notifications
                        </p>
                    </div>
                </div>

                <!-- 2. Charts Row 1: Activity Trend & Activity by Module -->
                <div class="descriptive-grid grid-wide-left">
                    <!-- User Activity Trend -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-chart-line"></i> User Activity & System Alerts Over Time</h3>
                                <p>Volume of user action audit logs and system notifications across {{ $periodLabel }}.</p>
                            </div>
                            <span class="d-panel-pill">{{ $periodLabel }}</span>
                        </div>
                        @if($totalActivities > 0 || $totalNotifications > 0)
                            <div class="d-chart-container">
                                <canvas id="adminActivityTrendChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                                <strong>No Activity Recorded</strong>
                                <p>No audit trail records or notifications exist for {{ $periodLabel }}.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Activity by Module -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-chart-pie"></i> Activity by Department Module</h3>
                                <p>Distribution of {{ number_format($totalActivities) }} actions across FROMS departments.</p>
                            </div>
                        </div>
                        @if(!empty($activityByModule))
                            <div class="d-chart-container">
                                <canvas id="adminModuleDistributionChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-cubes"></i></div>
                                <strong>No Module Activity</strong>
                                <p>No department interactions logged during this period.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 3. Charts Row 2: Notifications by Module & User Departments -->
                <div class="descriptive-grid">
                    <!-- Notifications by Module -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-bell"></i> Notifications by Department</h3>
                                <p>System alerts generated per operational department.</p>
                            </div>
                            <span class="d-panel-pill">{{ $totalNotifications }} Alerts</span>
                        </div>
                        @if(!empty($notificationsByModule))
                            <div class="d-chart-container short">
                                <canvas id="adminNotificationCategoryChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-bell-slash"></i></div>
                                <strong>No Notifications Generated</strong>
                                <p>No notification alerts were triggered in the selected period.</p>
                            </div>
                        @endif
                    </div>

                    <!-- User Account Distribution -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-users"></i> Users by Department</h3>
                                <p>Head and staff personnel distribution across departments.</p>
                            </div>
                            <span class="d-panel-pill">{{ $totalUsers }} Total</span>
                        </div>
                        @if(!empty($usersByDepartment))
                            <div class="d-chart-container short">
                                <canvas id="adminUserDistributionChart"></canvas>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-users"></i></div>
                                <strong>No Users Found</strong>
                                <p>No user accounts recorded in the database.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 4. Tables Row: Recent System Activities & Recent Notifications -->
                <div class="descriptive-grid">
                    <!-- Recent Activities Table -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent System Activities (Audit Trail)</h3>
                                <p>Latest actions, updates, and status mutations logged across FROMS.</p>
                            </div>
                        </div>
                        @if($recentActivities->isNotEmpty())
                            <div class="d-table-wrapper">
                                <table class="d-table">
                                    <thead>
                                        <tr>
                                            <th>User & Role</th>
                                            <th>Action / Activity</th>
                                            <th>Module</th>
                                            <th>IP & Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentActivities as $act)
                                            @php
                                                $modClass = match($act->module) {
                                                    'Admin' => 'pill-blue',
                                                    'Operation' => 'pill-cyan',
                                                    'Maintenance' => 'pill-amber',
                                                    'Purchase' => 'pill-purple',
                                                    'Warehouse' => 'pill-green',
                                                    default => 'pill-gray',
                                                };
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ $act->user_name ?: 'System' }}</strong>
                                                    <br><small style="color:#64748b">{{ $act->department ?: 'General' }} ({{ $act->user_role ?: 'User' }})</small>
                                                </td>
                                                <td>
                                                    {{ $act->activity }}
                                                    @if($act->reference)
                                                        <br><small style="color:#2563eb">Ref: {{ $act->reference }}</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="d-status-pill {{ $modClass }}">{{ $act->module ?: 'General' }}</span>
                                                </td>
                                                <td>
                                                    <span style="font-weight:600">{{ $act->created_at->format('M j, Y h:i A') }}</span>
                                                    <br><small style="color:#64748b">{{ $act->ip_address ?: '127.0.0.1' }}</small>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                                <strong>No Activity Records</strong>
                                <p>No audit trail logs exist for the selected filter.</p>
                            </div>
                        @endif
                    </div>

                    <!-- Recent Notifications Table -->
                    <div class="d-panel">
                        <div class="d-panel-header">
                            <div class="d-panel-title-area">
                                <h3><i class="fa-solid fa-bell"></i> Recent System Notifications</h3>
                                <p>Latest dispatch alerts and threshold events.</p>
                            </div>
                        </div>
                        @if($recentNotifications->isNotEmpty())
                            <div class="d-table-wrapper">
                                <table class="d-table">
                                    <thead>
                                        <tr>
                                            <th>Module</th>
                                            <th>Entity</th>
                                            <th>Action</th>
                                            <th>Message</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentNotifications as $notif)
                                            @php
                                                $modClass = match($notif->module) {
                                                    'Admin' => 'pill-blue',
                                                    'Operation' => 'pill-cyan',
                                                    'Maintenance' => 'pill-amber',
                                                    'Purchase' => 'pill-purple',
                                                    'Warehouse' => 'pill-green',
                                                    default => 'pill-gray',
                                                };
                                            @endphp
                                            <tr>
                                                <td><span class="d-status-pill {{ $modClass }}">{{ $notif->module }}</span></td>
                                                <td><strong>{{ $notif->entity }}</strong></td>
                                                <td>
                                                    <span class="d-status-pill pill-gray">{{ str_replace('_', ' ', $notif->action) }}</span>
                                                </td>
                                                <td style="max-width:280px;line-height:1.3">{{ $notif->message }}</td>
                                                <td>{{ $notif->created_at->format('M j, h:i A') }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="d-empty-state">
                                <div class="d-empty-icon"><i class="fa-solid fa-bell-slash"></i></div>
                                <strong>No Notifications</strong>
                                <p>No system notifications have been recorded for this period.</p>
                            </div>
                        @endif
                    </div>
                </div>

            @endif
        </main>
    </div>
</x-layout.app>
