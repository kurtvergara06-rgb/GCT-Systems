<x-layout.app
    title="FROMS - Operation Dashboard"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Operation/dashboard-operation.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app operation-dashboard-page">
        <x-layout.sidebar department="Operation" />

        <main class="main operation-dashboard-main">
            <x-layout.topbar
                title="Operation Dashboard"
                subtitle="Live fleet monitoring, trip fulfillment, workforce readiness, and incident operations"
                :notification-count="$activeIncidents + $pendingDecisions"
            />

            {{-- 1. Operation Command Bar --}}
            <div class="operation-command-header">
                <div>
                    <span class="section-eyebrow">DAILY OPERATIONS &middot; {{ \Carbon\Carbon::parse($today)->format('F j, Y') }}</span>
                    <h2>Operations Command Center</h2>
                    <p>Live operational status &middot; Asia/Manila business date</p>
                </div>
                <div class="operation-command-links">
                    <a href="{{ route('trip-schedule', [], false) }}">
                        <i class="fa-solid fa-calendar-days"></i> Trip Schedule
                    </a>
                    <a href="{{ route('driver-bus-assignment', [], false) }}">
                        <i class="fa-solid fa-user-tie"></i> Driver & Bus Assignment
                    </a>
                    <a href="{{ route('auto-scheduling', [], false) }}" class="command-link-highlight">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Scheduling
                    </a>
                </div>
            </div>

            {{-- 2. Top Summary KPI Cards (Standard Shared Component) --}}
            <section class="stats-grid operation-top-stats" aria-label="Operation Key Performance Indicators" data-ajax-region="operation-kpis">
                <x-ui.summary-card
                    label="Scheduled Trips"
                    value="{{ $trips->count() }}"
                    small="{{ $assignedSlots }}/{{ $requiredSlots }} bus slots fulfilled"
                    icon="fa-route"
                    color="blue"
                    href="{{ route('trip-schedule', [], false) }}"
                />

                <x-ui.summary-card
                    label="Active Fleet"
                    value="{{ $activeBuses }} / {{ $totalBuses }}"
                    small="{{ $maintenanceBuses }} under maintenance · {{ $inactiveBuses }} inactive"
                    icon="fa-bus"
                    color="green"
                    href="{{ route('bus-availability', [], false) }}"
                />

                <x-ui.summary-card
                    label="Driver Availability"
                    value="{{ $driversAvailable }} / {{ $totalDrivers }}"
                    small="{{ (int) ($driverAttendance['Present'] ?? 0) }} present · {{ (int) ($driverAttendance['Late'] ?? 0) }} late"
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
                        </div>
                        <a class="view-all-link" href="{{ route('trip-schedule', [], false) }}">
                            View Schedule <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    {{-- Progress Summary Banner --}}
                    <div class="operation-fulfillment-banner">
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">Bus Slots Assigned</span>
                            <div class="fulfillment-metric__value">
                                <strong>{{ $assignedSlots }}</strong>
                                <span class="fulfillment-metric__total">/ {{ $requiredSlots }}</span>
                            </div>
                        </div>
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">Remaining Slots</span>
                            <div class="fulfillment-metric__value">
                                <strong class="{{ $requiredSlots - $assignedSlots > 0 ? 'text-amber' : 'text-green' }}">
                                    {{ max(0, $requiredSlots - $assignedSlots) }}
                                </strong>
                            </div>
                        </div>
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">Fulfillment Rate</span>
                            <div class="fulfillment-metric__value">
                                <strong class="text-blue">
                                    {{ $requiredSlots > 0 ? round(($assignedSlots / $requiredSlots) * 100) : 0 }}%
                                </strong>
                            </div>
                        </div>
                        <div class="fulfillment-metric">
                            <span class="fulfillment-metric__label">Active Routes</span>
                            <div class="fulfillment-metric__value">
                                <strong>{{ $totalRoutes }}</strong>
                            </div>
                        </div>
                    </div>

                    {{-- Progress Bar --}}
                    <div class="operation-slot-progress-bar" role="progressbar" aria-label="Assigned bus slots" aria-valuemin="0" aria-valuemax="{{ $requiredSlots }}" aria-valuenow="{{ $assignedSlots }}">
                        <span style="width: {{ $requiredSlots > 0 ? min(100, round(($assignedSlots / $requiredSlots) * 100)) : 0 }}%"></span>
                    </div>

                    {{-- Compact live trip table matching the approved design reference --}}
                    <div class="operation-reference-table-wrap">
                        <table class="operation-reference-table">
                            <thead><tr><th>Status</th><th>Trip ID</th><th>Origin → Destination</th><th>Departure</th><th>Assigned</th><th>Bus / Driver</th><th>Action</th></tr></thead>
                            <tbody>
                            @forelse($trips->take(6) as $trip)
                                @php
                                    $assigned = $trip->assigned_buses_count;
                                    $required = max(1, (int) $trip->required_buses);
                                    $first = $trip->assignments->first();
                                    $firstRental = $trip->externalRentals->firstWhere('status', 'Assigned');
                                    $origin = $trip->route_origin_snapshot ?: $trip->shuttleRoute?->origin;
                                    $destination = $trip->route_destination_snapshot ?: $trip->shuttleRoute?->destination;
                                    $displayStatus = $assigned >= $required ? 'Ready' : ($assigned > 0 ? 'Partial' : 'Unassigned');
                                @endphp
                                <tr>
                                    <td><span class="operation-reference-status {{ $assigned >= $required ? 'is-ready' : ($assigned > 0 ? 'is-partial' : 'is-open') }}">{{ $trip->status === 'Scheduled' ? $displayStatus : $trip->status }}</span></td>
                                    <td><strong>{{ $trip->trip_code }}</strong></td>
                                    <td><strong>{{ $origin && $destination ? $origin.' → '.$destination : ($trip->route_name_snapshot ?: ($trip->shuttleRoute?->route_name ?? 'Route unavailable')) }}</strong></td>
                                    <td>{{ $trip->departure_time ? \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') : '—' }}</td>
                                    <td><span class="{{ $assigned >= $required ? 'text-green' : 'text-amber' }}">{{ $assigned }}/{{ $required }}</span></td>
                                    <td>
                                        @if($first)
                                            <strong>{{ $first->bus?->bus_no ?? $first->bus?->plate_no ?? 'Bus not recorded' }}</strong><small>{{ $first->driver_name }}</small>
                                        @elseif($firstRental)
                                            <strong>{{ $firstRental->plate_number }}</strong><small>{{ $firstRental->driver_name }} · External</small>
                                        @else
                                            <span class="text-amber">Pending assignment</span>
                                        @endif
                                    </td>
                                    <td><a class="operation-reference-view" href="{{ route('driver-bus-assignment', ['trip_date' => $today], false) }}">{{ $assigned ? 'View' : 'Assign' }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="operation-reference-empty">No scheduled trips today.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="operation-reference-footer"><span>Showing {{ min(6, $trips->count()) }} of {{ $trips->count() }} scheduled trips</span><a href="{{ route('trip-schedule', [], false) }}">See All Trips <i class="fa-solid fa-arrow-right"></i></a></div>
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
                            View Fleet <i class="fa-solid fa-arrow-right"></i>
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
                        <div class="operation-fleet-donut" role="img" aria-label="Fleet: {{ $activeBuses }} active, {{ $maintenanceBuses }} under maintenance, {{ $inactiveBuses }} inactive, {{ $otherBuses }} other"
                            style="--fleet-active:{{ $activePercent }}%;--fleet-maintenance:{{ $activePercent + $maintenancePercent }}%;--fleet-inactive:{{ $activePercent + $maintenancePercent + $inactivePercent }}%">
                            <div class="operation-fleet-donut-center"><strong>{{ $totalBuses }}</strong><span>Total Fleet</span></div>
                        </div>
                        <div class="operation-fleet-legend">
                            <div><i class="fleet-dot active"></i><span>Active & Ready</span><strong>{{ $activeBuses }}</strong><small>{{ round($activePercent) }}%</small></div>
                            <div><i class="fleet-dot maintenance"></i><span>Under Maintenance</span><strong>{{ $maintenanceBuses }}</strong><small>{{ round($maintenancePercent) }}%</small></div>
                            <div><i class="fleet-dot inactive"></i><span>Inactive / Out of Service</span><strong>{{ $inactiveBuses }}</strong><small>{{ round($inactivePercent) }}%</small></div>
                            <div><i class="fleet-dot other"></i><span>Other / Inspection</span><strong>{{ $otherBuses }}</strong><small>{{ round($otherPercent) }}%</small></div>
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
                                <strong>Active Ready Fleet</strong>
                                <small>Available for daily assignment</small>
                            </div>
                            <span class="fleet-status-item__count text-green">{{ $activeBuses }}</span>
                        </div>

                        <div class="fleet-status-item fleet-status-item--maintenance">
                            <div class="fleet-status-item__icon yellow">
                                <i class="fa-solid fa-screwdriver-wrench"></i>
                            </div>
                            <div class="fleet-status-item__body">
                                <strong>Under Maintenance</strong>
                                <small>Active job orders in progress</small>
                            </div>
                            <span class="fleet-status-item__count text-amber">{{ $maintenanceBuses }}</span>
                        </div>

                        <div class="fleet-status-item fleet-status-item--inactive">
                            <div class="fleet-status-item__icon red">
                                <i class="fa-solid fa-circle-xmark"></i>
                            </div>
                            <div class="fleet-status-item__body">
                                <strong>Inactive / Out of Service</strong>
                                <small>Decommissioned or restricted</small>
                            </div>
                            <span class="fleet-status-item__count text-red">{{ $inactiveBuses }}</span>
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
                            Review in Auto-Scheduling <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </section>

            {{-- 4. Row 2: Attendance Rosters (Drivers & Mechanics) + Operational Activity --}}
            <section class="operation-bottom-grid mb-4">
                {{-- Driver Attendance Summary --}}
                <div class="dashboard-card operation-attendance-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">WORKFORCE</span>
                            <h2>Driver Attendance</h2>
                            <p>Today's shift attendance check-in</p>
                        </div>
                        <a class="view-all-link" href="{{ route('driver-attendance', [], false) }}">
                            View Roster <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="operation-attendance-matrix">
                        <div class="attendance-stat-box attendance-present">
                            <small>Present</small>
                            <strong>{{ (int) ($driverAttendance['Present'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-green"><i class="fa-solid fa-check"></i> On Time</span>
                        </div>
                        <div class="attendance-stat-box attendance-late">
                            <small>Late</small>
                            <strong>{{ (int) ($driverAttendance['Late'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-amber"><i class="fa-solid fa-clock"></i> Delayed</span>
                        </div>
                        <div class="attendance-stat-box attendance-absent">
                            <small>Absent</small>
                            <strong>{{ (int) ($driverAttendance['Absent'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-red"><i class="fa-solid fa-xmark"></i> Unexcused</span>
                        </div>
                        <div class="attendance-stat-box attendance-leave">
                            <small>On Leave</small>
                            <strong>{{ (int) ($driverAttendance['On Leave'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-blue"><i class="fa-solid fa-calendar-minus"></i> Authorized</span>
                        </div>
                    </div>
                </div>

                {{-- Mechanic Attendance Summary --}}
                <div class="dashboard-card operation-attendance-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">SUPPORT PERSONNEL</span>
                            <h2>Mechanic Attendance</h2>
                            <p>Maintenance staff on duty today</p>
                        </div>
                        <a class="view-all-link" href="{{ route('mechanic-attendance', [], false) }}">
                            View Roster <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>
                    <div class="operation-attendance-matrix">
                        <div class="attendance-stat-box attendance-present">
                            <small>Present</small>
                            <strong>{{ (int) ($mechanicAttendance['Present'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-green"><i class="fa-solid fa-screwdriver-wrench"></i> On Duty</span>
                        </div>
                        <div class="attendance-stat-box attendance-late">
                            <small>Late</small>
                            <strong>{{ (int) ($mechanicAttendance['Late'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-amber"><i class="fa-solid fa-clock"></i> Delayed</span>
                        </div>
                        <div class="attendance-stat-box attendance-absent">
                            <small>Absent</small>
                            <strong>{{ (int) ($mechanicAttendance['Absent'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-red"><i class="fa-solid fa-xmark"></i> Unexcused</span>
                        </div>
                        <div class="attendance-stat-box attendance-leave">
                            <small>On Leave</small>
                            <strong>{{ (int) ($mechanicAttendance['On Leave'] ?? 0) }}</strong>
                            <span class="attendance-tag tag-blue"><i class="fa-solid fa-calendar-minus"></i> Authorized</span>
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
                    <div class="operation-workspace-links">
                        <a href="{{ route('auto-scheduling', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon blue"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
                            <div class="workspace-link-text">
                                <strong>Auto Scheduling Engine</strong>
                                <small>AI/ML recommended assignments</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('driver-bus-assignment', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon purple"><i class="fa-solid fa-users-gear"></i></div>
                            <div class="workspace-link-text">
                                <strong>Driver & Bus Assignment</strong>
                                <small>Daily schedule slot assignments</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('incidents', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="workspace-link-text">
                                <strong>Incident Management</strong>
                                <small>{{ $activeIncidents }} unresolved case{{ $activeIncidents === 1 ? '' : 's' }}</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>

                        <a href="{{ route('daily-driver-reports', [], false) }}" class="workspace-link-item">
                            <div class="workspace-link-icon green"><i class="fa-solid fa-file-lines"></i></div>
                            <div class="workspace-link-text">
                                <strong>Daily Driver Reports</strong>
                                <small>Trip logs, mileage & passengers</small>
                            </div>
                            <i class="fa-solid fa-chevron-right link-chevron"></i>
                        </a>
                    </div>
                </div>
            </section>

            {{-- 5. Row 3: Recent Operational Incidents & Daily Driver Reports --}}
            <section class="operation-dashboard-grid operation-activity-grid">
                {{-- Recent Incidents List --}}
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">SAFETY & DISPATCH</span>
                            <h2>Recent Operational Incidents</h2>
                            <p>Latest road breakdown, safety, and delay reports</p>
                        </div>
                        <a class="view-all-link" href="{{ route('incidents', [], false) }}">
                            View All Incidents <i class="fa-solid fa-arrow-right"></i>
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
                                            <span><i class="fa-solid fa-bus"></i> {{ $incident->bus->bus_no }}</span>
                                            <span class="meta-sep">&middot;</span>
                                        @endif
                                        <span><i class="fa-solid fa-location-dot"></i> {{ $incident->location ?: 'Location unrecorded' }}</span>
                                    </div>
                                </div>
                                <div class="activity-status">
                                    <x-ui.status-badge :status="$incident->status" type="default" />
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
                </div>

                {{-- Recent Daily Driver Reports --}}
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <div>
                            <span class="section-eyebrow">LOGISTICS AUDIT</span>
                            <h2>Recent Driver Reports</h2>
                            <p>Logged passenger and mileage vouchers</p>
                        </div>
                        <a class="view-all-link" href="{{ route('daily-driver-reports', [], false) }}">
                            View All DDR <i class="fa-solid fa-arrow-right"></i>
                        </a>
                    </div>

                    <div class="operation-activity-list">
                        @forelse($recentReports as $report)
                            <div class="operation-activity-row">
                                <div class="activity-icon-badge badge-blue">
                                    <i class="fa-solid fa-file-lines"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-top-line">
                                        <x-ui.id-badge :value="$report->ddr_no" />
                                        <strong class="activity-title">{{ $report->driver_name ?: 'Driver report' }}</strong>
                                        <span class="activity-time">{{ $report->created_at ? $report->created_at->diffForHumans() : 'Recently' }}</span>
                                    </div>
                                    <p class="activity-description">
                                        {{ $report->from_location }} &rarr; {{ $report->to_location }}
                                    </p>
                                    <div class="activity-meta">
                                        @if($report->bus)
                                            <span><i class="fa-solid fa-bus"></i> {{ $report->bus->bus_no }}</span>
                                            <span class="meta-sep">&middot;</span>
                                        @endif
                                        <span><i class="fa-solid fa-users"></i> {{ $report->passengers ?? 0 }} passengers</span>
                                        <span class="meta-sep">&middot;</span>
                                        <span><i class="fa-solid fa-gauge"></i> {{ $report->km ?? 0 }} km</span>
                                    </div>
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
                </div>
            </section>
        </main>
    </div>
</x-layout.app>
