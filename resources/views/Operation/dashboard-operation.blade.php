@php
    $fleetChartData = [
        'labels' => ['Active', 'Under Maintenance', 'Inactive', 'Other'],
        'values' => [$activeBuses, $maintenanceBuses, $inactiveBuses, $otherBuses],
    ];
@endphp

<x-layout.app
    title="FROMS - Operation Dashboard"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/dashboard-operation.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/dashboard-operation.js',
    ]"
>
    <div class="app operation-dashboard-page">
        <x-layout.sidebar department="Operation" />

        <main class="main operation-dashboard-main">
            <x-layout.topbar
                title="Operations Command Center"
                subtitle="Monitor daily operations, fleet readiness, trip fulfillment, driver deployment, and incident response."
                :notification-count="$activeIncidents + $pendingDecisions"
            />

            {{-- 1. Operation Command Bar --}}
            <div class="operation-command-header" data-operation-dashboard
                data-trip-performance='@json($tripPerformance, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                data-incident-performance='@json($incidentPerformance, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
                data-fleet-readiness='@json($fleetChartData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'>
                <div class="operation-command-meta">
                    <div class="operation-command-title-row">
                        <span class="section-eyebrow">DAILY OPERATIONS &middot; {{ \Carbon\Carbon::parse($today)->format('F j, Y') }}</span>
                        <span class="operation-live-badge"><i class="fa-solid fa-database" aria-hidden="true"></i> Database Overview</span>
                    </div>
                    <h2 class="operation-command-heading">Operations Command Center</h2>
                    <p class="operation-business-date">
                        <i class="fa-regular fa-clock"></i>
                        {{ \Carbon\Carbon::parse($today)->format('D, F j, Y') }}
                        <span aria-hidden="true">&middot;</span>
                        {{ config('app.business_timezone', 'Asia/Manila') }}
                        <span aria-hidden="true">&middot;</span>
                        <span data-dashboard-updated>Updated {{ now(config('app.business_timezone', 'Asia/Manila'))->format('g:i A') }}</span>
                    </p>
                </div>

                <div class="operation-command-controls">
                    <form method="GET" action="{{ route('dashboard-operation', [], false) }}" class="operation-date-filter" aria-label="Dashboard filters">
                        <label>
                            <span>Operational date</span>
                            <div class="operation-input-with-icon">
                                <i class="fa-regular fa-calendar"></i>
                                <input type="date" name="date" value="{{ $today }}">
                            </div>
                        </label>
                        <label>
                            <span>Trip status</span>
                            <div class="operation-input-with-icon">
                                <i class="fa-solid fa-list-check"></i>
                                <select name="status">
                                    @foreach(['all' => 'All statuses', 'Scheduled' => 'Scheduled', 'Ready' => 'Ready', 'Dispatched' => 'Dispatched', 'Pending DDR' => 'Pending DDR', 'Completed' => 'Completed', 'Delayed' => 'Delayed', 'Missed' => 'Missed', 'Cancelled' => 'Cancelled'] as $value => $label)
                                        <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </label>
                        <button type="submit"><i class="fa-solid fa-filter"></i><span>Apply</span></button>
                    </form>
                </div>

                <div class="operation-command-links">
                    <a href="{{ route('auto-scheduling', [], false) }}" class="command-link-highlight">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span>Auto-Scheduling Engine</span>
                        <span class="command-link-badge">Hybrid scheduling</span>
                    </a>
                    <a href="{{ route('trip-schedule', [], false) }}">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Trip Schedule</span>
                    </a>
                    <a href="{{ route('driver-bus-assignment', [], false) }}">
                        <i class="fa-solid fa-user-tie"></i>
                        <span>Driver & Bus Assignment</span>
                    </a>
                    <a href="{{ route('driver-attendance', [], false) }}">
                        <i class="fa-solid fa-user-check"></i>
                        <span>Attendance</span>
                    </a>
                    <a href="{{ route('bus-availability', [], false) }}">
                        <i class="fa-solid fa-bus-simple"></i>
                        <span>Bus Availability</span>
                    </a>
                    <a href="{{ route('daily-driver-reports', [], false) }}">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>Daily Reports</span>
                    </a>
                    <a href="{{ route('incidents', [], false) }}">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>Incidents</span>
                        @if($activeIncidents > 0)
                            <span class="command-badge-alert">{{ $activeIncidents }}</span>
                        @endif
                    </a>
                </div>
            </div>

            {{-- 2. Top Summary KPI Cards (Standard Shared Component) --}}
            <section class="stats-grid operation-top-stats" aria-label="Operation Key Performance Indicators" data-ajax-region="operation-kpis">
                <x-ui.summary-card
                    label="Trips on Date"
                    value="{{ $trips->count() }}"
                    small="{{ $assignedSlots }}/{{ $requiredSlots }} actionable slots fulfilled"
                    icon="fa-route"
                    color="blue"
                    href="{{ route('trip-schedule', [], false) }}"
                />

                <x-ui.summary-card
                    label="Completed Trips"
                    value="{{ $completedTrips }} / {{ $trips->count() }}"
                    small="Completed with linked DDR evidence"
                    icon="fa-circle-check"
                    color="green"
                    href="{{ route('trip-records', [], false) }}"
                />

                <x-ui.summary-card
                    label="Driver Availability"
                    value="{{ $driversAvailable }} / {{ $activeDrivers }}"
                    small="Active drivers minus Absent and On Leave records"
                    icon="fa-id-card"
                    color="purple"
                    href="{{ route('driver-attendance', [], false) }}"
                />

                <x-ui.summary-card
                    label="Active Incidents"
                    value="{{ $activeIncidents }}"
                    small="{{ $pendingDecisions }} pending slot decision{{ $pendingDecisions === 1 ? '' : 's' }}"
                    icon="fa-triangle-exclamation"
                    :color="$activeIncidents > 0 ? 'red' : 'yellow'"
                    href="{{ route('incidents', [], false) }}"
                />
            </section>

            {{-- 3. Row 1: Today's Scheduled Trips (Left) + Fleet & Assignment Readiness (Right) --}}
            <section class="operation-dashboard-grid operation-readiness-grid mb-4">
                {{-- Trip and Assignment Overview --}}
                <div class="dashboard-card operation-trips-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">TODAY'S OPERATIONS</span>
                            <h2>Trip & Assignment Fulfillment</h2>
                            <p>Real schedules, required slots, and assigned fleet units</p>
                            <small class="operation-kpi-scope">Fulfillment excludes completed, cancelled and missed trips. Trip status filtering applies to the table only.</small>
                        </div>
                        <a class="view-all-link" href="{{ route('trip-schedule', [], false) }}">
                            <span>View Schedule</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    {{-- Progress Summary Banner --}}
                    <div class="operation-fulfillment-banner" aria-label="Actionable nonterminal trips on selected date">
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">
                                <i class="fa-solid fa-bus-simple text-blue"></i>
                                Bus Slots Assigned (Open Trips)
                            </span>
                            <div class="fulfillment-metric__value">
                                <strong>{{ $assignedSlots }}</strong>
                                <span class="fulfillment-metric__total">/ {{ $requiredSlots }}</span>
                            </div>
                        </div>
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">
                                <i class="fa-solid fa-hourglass-half {{ $requiredSlots - $assignedSlots > 0 ? 'text-amber' : 'text-green' }}"></i>
                                Remaining Slots
                            </span>
                            <div class="fulfillment-metric__value">
                                <strong class="{{ $requiredSlots - $assignedSlots > 0 ? 'text-amber' : 'text-green' }}">
                                    {{ max(0, $requiredSlots - $assignedSlots) }}
                                </strong>
                            </div>
                        </div>
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">
                                <i class="fa-solid fa-chart-pie text-blue"></i>
                                Fulfillment Rate
                            </span>
                            <div class="fulfillment-metric__value">
                                <strong class="text-blue">
                                    {{ $requiredSlots > 0 ? round(($assignedSlots / $requiredSlots) * 100) : 0 }}%
                                </strong>
                            </div>
                        </div>
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">
                                <i class="fa-solid fa-map-location-dot text-purple"></i>
                                Active Routes
                            </span>
                            <div class="fulfillment-metric__value">
                                <strong>{{ $totalRoutes }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="operation-slot-progress-bar" role="progressbar" aria-label="Assigned bus slots" aria-valuemin="0" aria-valuemax="{{ $requiredSlots }}" aria-valuenow="{{ $assignedSlots }}">
                        <span style="width: {{ $requiredSlots > 0 ? min(100, round(($assignedSlots / $requiredSlots) * 100)) : 0 }}%"></span>
                    </div>

                    {{-- Live trip table: GCT assignments only; historical rentals are not offered here. --}}
                    <div class="operation-reference-table-wrap">
                        <table class="operation-reference-table">
                            <thead>
                                <tr>
                                    <th>Trip</th>
                                    <th>Client / Route</th>
                                    <th>Direction</th>
                                    <th>Departure</th>
                                    <th>Required</th>
                                    <th>Assigned</th>
                                    <th>Drivers</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($displayTrips->take(8) as $trip)
                                @php
                                    $assigned = $trip->assignments->count();
                                    $required = max(1, (int) $trip->required_buses);
                                    $isFulfilled = $assigned >= $required;
                                    $displayStatus = $trip->status === 'Scheduled'
                                        ? ($isFulfilled ? 'Ready' : ($assigned > 0 ? 'Partially Assigned' : 'Scheduled'))
                                        : $trip->status;
                                    $statusClass = match($displayStatus) {
                                        'Ready', 'Completed' => 'is-ready',
                                        'Partially Assigned', 'Pending DDR', 'Delayed' => 'is-partial',
                                        'Cancelled', 'Missed' => 'is-critical',
                                        default => 'is-open',
                                    };
                                    $routeLabel = $trip->route_name_snapshot ?: ($trip->shuttleRoute?->route_name ?? 'Route unavailable');
                                @endphp
                                <tr>
                                    <td>
                                        <span class="operation-trip-code-chip">
                                            <i class="fa-solid fa-hashtag"></i>{{ $trip->trip_code }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="operation-route-cell">
                                            <strong class="operation-client-name">{{ $trip->client_name ?: $routeLabel }}</strong>
                                            <span class="operation-route-subtitle">{{ $trip->client_name ? $routeLabel : 'GCT service route' }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="operation-direction-chip {{ $trip->direction === 'Incoming' ? 'direction-incoming' : 'direction-outgoing' }}">
                                            <i class="fa-solid fa-{{ $trip->direction === 'Incoming' ? 'arrow-right-to-bracket' : 'arrow-right-from-bracket' }}"></i>
                                            {{ $trip->direction }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="operation-time-chip">
                                            <i class="fa-regular fa-clock"></i>
                                            {{ $trip->departure_time ? \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') : '—' }}
                                        </span>
                                    </td>
                                    <td><strong>{{ $required }}</strong></td>
                                    <td>
                                        <span class="operation-assigned-count {{ $isFulfilled ? 'is-fulfilled' : 'is-pending' }}">
                                            <i class="fa-solid fa-{{ $isFulfilled ? 'circle-check' : 'circle-notch' }}"></i>
                                            {{ $assigned }} / {{ $required }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($trip->assignments->isNotEmpty())
                                            <div class="operation-driver-cell">
                                                <div class="operation-driver-avatar">
                                                    {{ strtoupper(substr($trip->assignments->first()->driver_name ?? 'D', 0, 1)) }}
                                                </div>
                                                <div class="operation-driver-info">
                                                    <strong>{{ $trip->assignments->pluck('driver_name')->filter()->take(2)->join(', ') ?: 'Driver not recorded' }}</strong>
                                                    <span class="operation-bus-plate">
                                                        <i class="fa-solid fa-bus"></i>
                                                        {{ $trip->assignments->pluck('bus.bus_no')->filter()->take(2)->join(', ') }}
                                                    </span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="operation-pending-badge">
                                                <i class="fa-solid fa-clock"></i> Pending assignment
                                            </span>
                                        @endif
                                    </td>
                                    <td><span class="operation-reference-status {{ $statusClass }}">{{ $displayStatus }}</span></td>
                                    <td>
                                        <a class="operation-reference-view {{ $canEditOperation && ! $isFulfilled ? 'is-cta' : '' }}" href="{{ route('driver-bus-assignment', ['trip_date' => $today], false) }}">
                                            <span>{{ $canEditOperation && ! $isFulfilled ? 'Assign' : 'View' }}</span>
                                            <i class="fa-solid fa-arrow-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="operation-reference-empty">
                                        <div class="operation-empty-wrap">
                                            <i class="fa-solid fa-calendar-xmark"></i>
                                            <p>No trips match the selected operational date and status.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="operation-reference-footer">
                        <span>Showing {{ min(8, $displayTrips->count()) }} of {{ $displayTrips->count() }} matching trips</span>
                        <a href="{{ route('trip-schedule', ['trip_date' => $today], false) }}">
                            See All Trips <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                {{-- Bus Fleet Availability & Allocation --}}
                <div class="dashboard-card operation-fleet-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">FLEET READINESS</span>
                            <h2>Bus Availability</h2>
                            <p>Current operational fleet distribution</p>
                        </div>
                        <a class="view-all-link" href="{{ route('bus-availability', [], false) }}">
                            <span>View Fleet</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @php
                        $otherBuses = max(0, $totalBuses - $activeBuses - $maintenanceBuses - $inactiveBuses);
                        $denom = max(1, $totalBuses);
                        $activePercent = round($activeBuses / $denom * 100, 2);
                        $maintenancePercent = round($maintenanceBuses / $denom * 100, 2);
                        $inactivePercent = round($inactiveBuses / $denom * 100, 2);
                        $otherPercent = max(0, 100 - $activePercent - $maintenancePercent - $inactivePercent);
                    @endphp
                    <div class="operation-fleet-visual">
                        <div class="operation-fleet-chart" aria-label="Fleet readiness distribution">
                            <canvas id="operationFleetChart" role="img" aria-label="Fleet: {{ $activeBuses }} active, {{ $maintenanceBuses }} under maintenance, {{ $inactiveBuses }} inactive, {{ $otherBuses }} other"></canvas>
                            <div class="operation-fleet-chart__center" aria-hidden="true">
                                <strong>{{ $totalBuses }}</strong>
                                <span>Total fleet</span>
                            </div>
                        </div>
                        <div class="operation-fleet-legend">
                            <div class="fleet-legend-row">
                                <i class="fleet-dot active"></i>
                                <span>Active / Eligible</span>
                                <strong>{{ $activeBuses }}</strong>
                                <small>{{ round($activePercent) }}%</small>
                            </div>
                            <div class="fleet-legend-row">
                                <i class="fleet-dot maintenance"></i>
                                <span>Under Maintenance</span>
                                <strong>{{ $maintenanceBuses }}</strong>
                                <small>{{ round($maintenancePercent) }}%</small>
                            </div>
                            <div class="fleet-legend-row">
                                <i class="fleet-dot inactive"></i>
                                <span>Inactive / Out of Service</span>
                                <strong>{{ $inactiveBuses }}</strong>
                                <small>{{ round($inactivePercent) }}%</small>
                            </div>
                            <div class="fleet-legend-row">
                                <i class="fleet-dot other"></i>
                                <span>Other / Inspection</span>
                                <strong>{{ $otherBuses }}</strong>
                                <small>{{ round($otherPercent) }}%</small>
                            </div>
                        </div>
                    </div>

                    <div class="operation-fleet-details-title">Fleet Status Details</div>
                    {{-- Fleet Breakdown Cards --}}
                    <div class="operation-fleet-status-stack">
                        <div class="fleet-status-item fleet-status-item--active">
                            <div class="fleet-status-item__icon green">
                                <i class="fa-solid fa-circle-check"></i>
                            </div>
                            <div class="fleet-status-item__body">
                                <div class="fleet-status-item__title-row">
                                    <strong>Active Ready Fleet</strong>
                                    <span class="fleet-status-item__count text-green">{{ $activeBuses }}</span>
                                </div>
                                <small>Available for daily assignment</small>
                                <div class="fleet-status-item__progress">
                                    <span style="width: {{ $activePercent }}%"></span>
                                </div>
                            </div>
                        </div>

                        <div class="fleet-status-item fleet-status-item--maintenance">
                            <div class="fleet-status-item__icon yellow">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>
                            <div class="fleet-status-item__body">
                                <div class="fleet-status-item__title-row">
                                    <strong>Under Maintenance</strong>
                                    <span class="fleet-status-item__count text-amber">{{ $maintenanceBuses }}</span>
                                </div>
                                <small>Active job orders in progress</small>
                                <div class="fleet-status-item__progress progress-amber">
                                    <span style="width: {{ $maintenancePercent }}%"></span>
                                </div>
                            </div>
                        </div>

                        <div class="fleet-status-item fleet-status-item--inactive">
                            <div class="fleet-status-item__icon red">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </div>
                            <div class="fleet-status-item__body">
                                <div class="fleet-status-item__title-row">
                                    <strong>Inactive / Out of Service</strong>
                                    <span class="fleet-status-item__count text-red">{{ $inactiveBuses }}</span>
                                </div>
                                <small>Decommissioned or restricted</small>
                                <div class="fleet-status-item__progress progress-red">
                                    <span style="width: {{ $inactivePercent }}%"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Pending Scheduling Decisions Callout --}}
                    <div class="operation-scheduling-alert {{ $pendingDecisions > 0 ? 'has-pending' : '' }}">
                        <div class="scheduling-alert-header">
                            <div class="scheduling-alert-icon">
                                <i class="fa-solid fa-{{ $pendingDecisions > 0 ? 'triangle-exclamation' : 'circle-check' }}"></i>
                            </div>
                            <div>
                                <strong>{{ $pendingDecisions }} Pending Slot Decision{{ $pendingDecisions === 1 ? '' : 's' }}</strong>
                                <p>{{ $pendingDecisions > 0 ? 'Slots require manual or replacement approval before trip confirmation.' : 'All automated scheduling suggestions are reconciled.' }}</p>
                            </div>
                        </div>
                        <a href="{{ route('auto-scheduling', [], false) }}" class="scheduling-alert-cta">
                            <span>Review in Auto-Scheduling</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </section>

            {{-- 4. Row 2: Driver Attendance + Operational Workspace --}}
            <section class="operation-bottom-grid mb-4">
                {{-- Driver Attendance Summary --}}
                <div class="dashboard-card operation-attendance-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">WORKFORCE</span>
                            <h2>Driver Attendance</h2>
                            <p>DDR-confirmed attendance, separate from planning eligibility</p>
                        </div>
                        <a class="view-all-link" href="{{ route('driver-attendance', [], false) }}">
                            <span>View Roster</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @php
                        $deploymentRate = $presentDrivers > 0 ? round(($assignedPresent / $presentDrivers) * 100) : 0;
                        $standbyRate = max(0, 100 - $deploymentRate);
                    @endphp

                    {{-- Deployment & Capacity Meter --}}
                    <div class="attendance-capacity-summary">
                        <div class="attendance-capacity-header">
                            <div class="capacity-stat">
                                <span class="capacity-label">
                                    <i class="fa-solid fa-chart-pie text-blue"></i>
                                    Deployment Ratio
                                </span>
                                <strong class="capacity-val">
                                    {{ $assignedPresent }} <small>/ {{ $presentDrivers }} Present</small>
                                </strong>
                            </div>
                            <div class="capacity-badge {{ $unassignedPresent > 0 ? 'is-reserve' : 'is-full' }}">
                                <i class="fa-solid fa-shield-halved"></i>
                                <span>{{ $unassignedPresent }} Unassigned Present</span>
                            </div>
                        </div>
                        <div class="attendance-capacity-bar" role="progressbar" aria-label="Driver deployment ratio" aria-valuenow="{{ $deploymentRate }}" aria-valuemin="0" aria-valuemax="100">
                            <span class="capacity-fill-assigned" style="width: {{ $deploymentRate }}%" title="Assigned Present: {{ $assignedPresent }} ({{ $deploymentRate }}%)"></span>
                            <span class="capacity-fill-standby" style="width: {{ $standbyRate }}%" title="Unassigned Present: {{ $unassignedPresent }} ({{ $standbyRate }}%)"></span>
                        </div>
                        <div class="attendance-capacity-legend">
                            <span><i class="dot dot-blue"></i> <strong>{{ $assignedPresent }}</strong> Assigned ({{ $deploymentRate }}%)</span>
                            <span><i class="dot dot-amber"></i> <strong>{{ $unassignedPresent }}</strong> Unassigned Present ({{ $standbyRate }}%)</span>
                            @if($isCurrentOperationalDate)
                                <span class="attendance-turnout-chip"><i class="fa-solid fa-user-check text-green"></i> DDR-confirmed records</span>
                            @else
                                <span class="attendance-turnout-chip"><i class="fa-solid fa-calendar-days text-blue"></i> Historical attendance date</span>
                            @endif
                        </div>
                    </div>

                    <div class="operation-attendance-matrix">
                        <div class="attendance-stat-box attendance-present">
                            <div class="attendance-stat-box__top">
                                <i class="fa-solid fa-user-check text-green"></i>
                                <small>Present</small>
                            </div>
                            <strong>{{ $presentDrivers }}</strong>
                            <span class="attendance-tag tag-green"><i class="fa-solid fa-check"></i> DDR-confirmed</span>
                        </div>
                        <div class="attendance-stat-box attendance-late">
                            <div class="attendance-stat-box__top">
                                <i class="fa-solid fa-id-badge text-blue"></i>
                                <small>Assigned Present</small>
                            </div>
                            <strong>{{ $assignedPresent }}</strong>
                            <span class="attendance-tag tag-blue"><i class="fa-solid fa-route"></i> Active assignment</span>
                        </div>
                        <div class="attendance-stat-box attendance-absent">
                            <div class="attendance-stat-box__top">
                                <i class="fa-solid fa-user-clock text-amber"></i>
                                <small>Unassigned Present</small>
                            </div>
                            <strong>{{ $unassignedPresent }}</strong>
                            <span class="attendance-tag tag-amber"><i class="fa-solid fa-hourglass-half"></i> No active assignment</span>
                        </div>
                        <div class="attendance-stat-box attendance-leave">
                            <div class="attendance-stat-box__top">
                                <i class="fa-regular fa-clipboard text-purple"></i>
                                <small>Attendance Records</small>
                            </div>
                            <strong>{{ $attendanceRecords }}</strong>
                            <span class="attendance-tag tag-purple"><i class="fa-solid fa-clipboard-check"></i> Saved for date</span>
                        </div>
                    </div>
                </div>

                {{-- Quick Operations Workspace Actions --}}
                <div class="dashboard-card operation-workspace-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">NAVIGATE</span>
                            <h2>Operation Workspace</h2>
                            <p>Quick access to primary modules</p>
                        </div>
                    </div>

                    {{-- Featured Full-Width Launcher Tile for Auto Scheduling Engine --}}
                    <div class="operation-workspace-featured">
                        <a href="{{ route('auto-scheduling', [], false) }}" class="workspace-featured-tile">
                            <div class="workspace-featured-content">
                                <div class="workspace-featured-icon">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </div>
                                <div class="workspace-featured-text">
                                    <div class="workspace-featured-title">
                                        <strong>Hybrid AI Scheduling</strong>
                                        <span class="workspace-status-chip is-ai">AI Engine</span>
                                    </div>
                                    <p>ML-assisted bus recommendations, driver eligibility checks, and scheduling conflict detection</p>
                                </div>
                                <div class="workspace-ai-capabilities" aria-label="Scheduling capabilities">
                                    <span><i class="fa-solid fa-bus-simple" aria-hidden="true"></i> Bus recommendations</span>
                                    <span><i class="fa-solid fa-user-check" aria-hidden="true"></i> Driver eligibility</span>
                                    <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Conflict detection</span>
                                    <span><i class="fa-solid fa-gears" aria-hidden="true"></i> Scheduling assistance</span>
                                </div>
                            </div>
                            <div class="workspace-featured-action">
                                <span>Launch Engine</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </div>
                        </a>
                    </div>

                    {{-- 6 Balanced 2-Column Grid Tiles --}}
                    <div class="operation-workspace-links">
                        <a href="{{ route('trip-schedule', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon blue"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="workspace-link-text">
                                <div class="workspace-link-title-row">
                                    <strong>Trip Schedule</strong>
                                    <span class="workspace-status-chip">Master</span>
                                </div>
                                <small>Create and review service trips</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('driver-bus-assignment', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon purple"><i class="fa-solid fa-users-gear"></i></div>
                            <div class="workspace-link-text">
                                <div class="workspace-link-title-row">
                                    <strong>Driver & Bus Assignment</strong>
                                    <span class="workspace-status-chip is-purple">{{ $assignedSlots }}/{{ $requiredSlots }}</span>
                                </div>
                                <small>Daily schedule slot assignments</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('driver-attendance', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon green"><i class="fa-solid fa-user-check"></i></div>
                            <div class="workspace-link-text">
                                <div class="workspace-link-title-row">
                                    <strong>Driver Attendance</strong>
                                    <span class="workspace-status-chip is-green">{{ $presentDrivers }} Present</span>
                                </div>
                                <small>Review DDR-confirmed attendance</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('bus-availability', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon blue"><i class="fa-solid fa-bus-simple"></i></div>
                            <div class="workspace-link-text">
                                <div class="workspace-link-title-row">
                                    <strong>Bus Availability</strong>
                                    <span class="workspace-status-chip is-blue">{{ $activeBuses }} Ready</span>
                                </div>
                                <small>Review fleet readiness</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('incidents', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="workspace-link-text">
                                <div class="workspace-link-title-row">
                                    <strong>Incident Management</strong>
                                    <span class="workspace-status-chip {{ $activeIncidents > 0 ? 'is-red' : 'is-green' }}">{{ $activeIncidents }} Active</span>
                                </div>
                                <small>{{ $activeIncidents }} unresolved case{{ $activeIncidents === 1 ? '' : 's' }}</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('daily-driver-reports', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon green"><i class="fa-solid fa-file-lines"></i></div>
                            <div class="workspace-link-text">
                                <div class="workspace-link-title-row">
                                    <strong>Daily Driver Reports</strong>
                                    <span class="workspace-status-chip">Logs</span>
                                </div>
                                <small>Trip logs, mileage & passengers</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>
                    </div>
                </div>
            </section>

            {{-- 5. Operational Analytics Card --}}
            <section class="dashboard-card operation-analytics-card" aria-labelledby="tripPerformanceTitle">
                <div class="dashboard-card-header">
                    <div>
                        <span class="section-eyebrow">OPERATIONAL ANALYTICS</span>
                        <h2 id="tripPerformanceTitle">Trip Performance — Last 7 Days</h2>
                        <p>Scheduled services compared with DDR-confirmed completed trips</p>
                    </div>
                    <div class="operation-analytics-summary" aria-label="Trip performance summary">
                        <span class="analytics-pill">
                            <i class="fa-solid fa-calendar-check text-blue"></i>
                            <strong>{{ $trendScheduled }}</strong> scheduled
                        </span>
                        <span class="analytics-pill">
                            <i class="fa-solid fa-circle-check text-green"></i>
                            <strong>{{ $trendCompleted }}</strong> completed
                        </span>
                        <span class="analytics-pill {{ $completionRate >= 80 ? 'is-positive' : 'is-watch' }}">
                            <i class="fa-solid fa-gauge-high"></i>
                            <strong>{{ $completionRate }}%</strong> completion
                        </span>
                    </div>
                </div>

                <div class="operation-analytics-body">
                    <div class="operation-trip-chart-card">
                        <div class="operation-trip-chart-heading">
                            <div>
                                <span class="section-eyebrow">TRIP PERFORMANCE</span>
                                <h3>Scheduled vs Completed Trips</h3>
                            </div>
                            <div class="operation-chart-key" aria-label="Chart legend">
                                <span><i class="operation-chart-dot is-scheduled"></i>Scheduled</span>
                                <span><i class="operation-chart-dot is-completed"></i>Completed</span>
                            </div>
                        </div>
                    <div class="operation-chart-shell" data-chart-empty="{{ $trendScheduled === 0 ? 'true' : 'false' }}">
                        <canvas id="operationTripPerformanceChart" role="img" aria-label="Scheduled and completed trips for the seven days ending {{ $operationalDate->format('F j, Y') }}"></canvas>
                        <div class="operation-chart-empty" @if($trendScheduled > 0) hidden @endif>
                            <i class="fa-regular fa-chart-bar"></i>
                            <strong>No trip activity in this period</strong>
                            <span>Schedule data will appear here when trips are recorded.</span>
                        </div>
                    </div>

                    </div>

                    <div class="operation-incident-trend">
                        <div class="operation-incident-trend__header">
                            <div>
                                <span class="section-eyebrow">SAFETY & RESPONSE</span>
                                <h3>Incident Trend</h3>
                            </div>
                            <a class="view-all-link" href="{{ route('incidents', [], false) }}">
                                <span>View All</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="operation-incident-chart-shell" data-incident-chart-empty="{{ $incidentPerformance->sum('total') === 0 ? 'true' : 'false' }}">
                            <canvas id="operationIncidentTrendChart" role="img" aria-label="Reported incidents for the seven days ending {{ $operationalDate->format('F j, Y') }}"></canvas>
                            <div class="operation-chart-empty" @if($incidentPerformance->sum('total') > 0) hidden @endif>
                                <i class="fa-solid fa-shield-halved"></i>
                                <strong>No reported incidents</strong>
                                <span>Incident counts will appear here.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 6. Recent Operational Incidents & Daily Driver Reports --}}
            <section class="operation-dashboard-grid operation-activity-grid">
                {{-- Recent Incidents List --}}
                <div class="dashboard-card operation-incidents-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">SAFETY & DISPATCH</span>
                            <h2>Recent Operational Incidents</h2>
                            <p>Latest road breakdown, safety, and delay reports</p>
                        </div>
                        <a class="view-all-link" href="{{ route('incidents', [], false) }}">
                            <span>View All Incidents</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="operation-activity-list">
                        @forelse($recentIncidents as $incident)
                            <div class="operation-activity-row">
                                <div class="activity-icon-badge {{ $incident->incident_type === 'Bus Breakdown' ? 'badge-red' : 'badge-amber' }}">
                                    <i class="fa-solid {{ $incident->incident_type === 'Bus Breakdown' ? 'fa-bus' : 'fa-triangle-exclamation' }}"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-top-line">
                                        <x-ui.id-badge :value="$incident->incident_no" />
                                        <strong class="activity-title">{{ $incident->incident_type }}</strong>
                                        <span class="activity-time">{{ $incident->incident_reported_at ? $incident->incident_reported_at->diffForHumans() : 'Recently' }}</span>
                                    </div>
                                    <p class="activity-description">
                                        {{ $incident->description ?: 'No additional details logged.' }}
                                    </p>
                                    <div class="activity-meta">
                                        @if($incident->bus)
                                            <span class="meta-chip meta-chip-bus"><i class="fa-solid fa-bus"></i> {{ $incident->bus->bus_no }}</span>
                                            <span class="meta-sep">&middot;</span>
                                        @endif
                                        @if($incident->tripSchedule)
                                            <span class="meta-chip meta-chip-trip"><i class="fa-solid fa-route"></i> {{ $incident->tripSchedule->trip_code }}</span>
                                            <span class="meta-sep">&middot;</span>
                                        @endif
                                        <span class="meta-chip meta-chip-loc"><i class="fa-solid fa-location-dot"></i> {{ $incident->location ?: 'Location unrecorded' }}</span>
                                    </div>
                                </div>
                                <div class="activity-status">
                                    <x-ui.status-badge :status="$incident->status" type="default" />
                                    <a href="{{ route('incidents.show', $incident, false) }}" aria-label="View incident {{ $incident->incident_no }}">
                                        <span>View</span>
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty-state
                                icon="fa-shield-halved"
                                title="No Recent Incidents"
                                description="No operational incidents logged recently. All routes running normally."
                            />
                        @endforelse
                    </div>

                    @if($recentIncidents->isNotEmpty())
                        <div class="operation-activity-footer">
                            <span>Showing {{ min(5, $recentIncidents->count()) }} recent incident{{ $recentIncidents->count() === 1 ? '' : 's' }}</span>
                            <a href="{{ route('incidents', [], false) }}">
                                <span>Audit All Incidents</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Recent Daily Driver Reports --}}
                <div class="dashboard-card operation-reports-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">LOGISTICS AUDIT</span>
                            <h2>Recent Driver Reports</h2>
                            <p>Logged passenger and mileage vouchers</p>
                        </div>
                        <a class="view-all-link" href="{{ route('daily-driver-reports', [], false) }}">
                            <span>View All DDR</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    @php
                        $totalReportPassengers = $recentReports->sum(fn ($r) => (int) ($r->passengers ?? 0) + (int) $r->additionalTrips->sum('passengers'));
                        $totalReportDistance = $recentReports->sum(fn ($r) => (float) ($r->km ?? 0) + (float) $r->additionalTrips->sum('km'));
                    @endphp

                    {{-- Logistics Audit Summary Strip: Balances card height and provides quick operational throughput metrics --}}
                    <div class="operation-logistics-strip">
                        <div class="logistics-stat">
                            <div class="logistics-stat-icon blue">
                                <i class="fa-solid fa-file-invoice"></i>
                            </div>
                            <div class="logistics-stat-body">
                                <span class="logistics-label">Logged DDR</span>
                                <strong class="logistics-val">{{ $recentReports->count() }}</strong>
                            </div>
                        </div>
                        <div class="logistics-stat">
                            <div class="logistics-stat-icon green">
                                <i class="fa-solid fa-users"></i>
                            </div>
                            <div class="logistics-stat-body">
                                <span class="logistics-label">Passengers</span>
                                <strong class="logistics-val">{{ number_format($totalReportPassengers) }}</strong>
                            </div>
                        </div>
                        <div class="logistics-stat">
                            <div class="logistics-stat-icon purple">
                                <i class="fa-solid fa-route"></i>
                            </div>
                            <div class="logistics-stat-body">
                                <span class="logistics-label">Total Distance</span>
                                <strong class="logistics-val">{{ number_format($totalReportDistance, 1) }} <small>km</small></strong>
                            </div>
                        </div>
                    </div>

                    <div class="operation-activity-list">
                        @forelse($recentReports as $report)
                            @php
                                $reportPassengers = (int) ($report->passengers ?? 0) + (int) $report->additionalTrips->sum('passengers');
                                $reportDistance = (float) ($report->km ?? 0) + (float) $report->additionalTrips->sum('km');
                                $reportTrip = $report->tripSchedule ?: $report->additionalTrips->first()?->tripSchedule;
                            @endphp
                            <div class="operation-activity-row">
                                <div class="activity-icon-badge badge-blue">
                                    <i class="fa-solid fa-file-lines"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-top-line">
                                        <x-ui.id-badge :value="$report->ddr_no" />
                                        <strong class="activity-title">{{ $report->driver_name ?: 'Driver report' }}</strong>
                                        <span class="activity-time">{{ $report->report_date?->format('M j, Y') ?? 'Date unavailable' }}</span>
                                    </div>
                                    <p class="activity-description">
                                        <span class="route-from">{{ $report->from_location }}</span>
                                        <i class="fa-solid fa-arrow-right-long route-arrow"></i>
                                        <span class="route-to">{{ $report->to_location }}</span>
                                    </p>
                                    <div class="activity-meta">
                                        @if($report->bus)
                                            <span class="meta-chip meta-chip-bus"><i class="fa-solid fa-bus"></i> {{ $report->bus->bus_no }}</span>
                                            <span class="meta-sep">&middot;</span>
                                        @endif
                                        @if($reportTrip)
                                            <span class="meta-chip meta-chip-trip"><i class="fa-solid fa-route"></i> {{ $reportTrip->trip_code }}</span>
                                            <span class="meta-sep">&middot;</span>
                                        @endif
                                        <span class="meta-chip meta-chip-passengers"><i class="fa-solid fa-users"></i> {{ $reportPassengers }} pax</span>
                                        <span class="meta-sep">&middot;</span>
                                        <span class="meta-chip meta-chip-km"><i class="fa-solid fa-road"></i> {{ number_format($reportDistance, 1) }} km</span>
                                    </div>
                                </div>
                                <div class="activity-status">
                                    <span class="ddr-status-chip is-confirmed"><i class="fa-solid fa-check"></i> Logged</span>
                                    <a href="{{ route('daily-driver-reports', [], false) }}" aria-label="View DDR {{ $report->ddr_no }}">
                                        <span>View</span>
                                        <i class="fa-solid fa-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        @empty
                            <x-ui.empty-state
                                icon="fa-file-circle-check"
                                title="No Driver Reports Yet"
                                description="Daily Driver Reports filed by drivers or encoded by Operations will display here."
                            />
                        @endforelse
                    </div>

                    @if($recentReports->isNotEmpty())
                        <div class="operation-activity-footer">
                            <span>Showing {{ min(5, $recentReports->count()) }} latest driver log{{ $recentReports->count() === 1 ? '' : 's' }}</span>
                            <a href="{{ route('daily-driver-reports', [], false) }}">
                                <span>Audit All DDR</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
