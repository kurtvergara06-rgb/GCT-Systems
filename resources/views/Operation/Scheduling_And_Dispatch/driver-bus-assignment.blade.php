<x-layout.app
    title="FROMS - Driver & Bus Assignment"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css',
        'resources/css/Operation/Scheduling_And_Dispatch/assignment-calendar.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js',
        'resources/js/Operation/Scheduling_And_Dispatch/assignment-calendar.js',
    ]"
>
    @php
        $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
        $canApproveOperation = auth()->user()?->canApproveReadyTripChanges() ?? false;
    @endphp

    <div class="app">
       <x-layout.sidebar department="Operation" />

        <main class="main assignment-page">
            <x-layout.topbar
                title="Driver & Bus Assignment"
                subtitle="View, manage, and print driver and bus assignments for scheduled trips"
                notification-count="4"
            />

            @if($errors->any())
                <div class="assignment-alert error">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section data-ajax-region="summary" class="assignment-summary-grid">
                <article class="assignment-summary-card">
                    <div class="summary-icon blue">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <p>Active Trips</p>
                        <h2>{{ $scheduledTripsForDate }}</h2>
                        <small>Scheduled or ready on {{ \Carbon\Carbon::parse($selectedTripDate)->format('M d') }}</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <p>Eligible Drivers</p>
                        <h2>{{ $availableDrivers->total() }}</h2>
                        <small>Present only; checked against each trip time</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon purple">
                        <i class="fa-solid fa-bus"></i>
                    </div>
                    <div>
                        <p>Eligible Buses</p>
                        <h2>{{ $availableBuses->total() }}</h2>
                        <small>Active; checked against each trip time</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon yellow">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </div>
                    <div>
                        <p>Unassigned Trips</p>
                        <h2>{{ $pendingAssignments }}</h2>
                        <small>Need assignment on selected date</small>
                    </div>
                </article>
            </section>

            <section data-ajax-region="records" class="assignment-card">
                <div class="assignment-card-header gct-section-header">
                    <div class="assignment-card-heading">
                        <span class="assignment-heading-icon" aria-hidden="true"><i class="fa-solid fa-calendar-check"></i></span>
                        <div>
                        <h2>Trip Assignments</h2>
                        <p>Manage the driver and bus assigned to each trip schedule.</p>
                        </div>
                    </div>

                    <div class="assignment-header-actions" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button type="button" data-direct-assignment-print
                           data-print-url="{{ route('driver-bus-assignment.print', ['trip_date' => $selectedTripDate, 'embedded' => 1], false) }}"
                           class="new-assignment-btn"
                           style="display:inline-flex;align-items:center;gap:7px;background:#ffbf00;color:#10233f;border:1px solid #e9aa00;text-decoration:none;box-shadow:0 3px 9px rgba(180,125,0,.12);"
                           title="Print saved assignments for {{ \Carbon\Carbon::parse($selectedTripDate)->format('M d, Y') }} (not all History records)">
                            <i class="fa-solid fa-print" aria-hidden="true"></i> Print Assignment Sheet · {{ \Carbon\Carbon::parse($selectedTripDate)->format('M d') }}
                        </button>
                    </div>
                </div>

                <div class="assignment-calendar-toolbar">
                <div class="assignment-date-picker" data-assignment-calendar
                     data-calendar-url="{{ route('driver-bus-assignment.calendar') }}"
                     data-selected-date="{{ $selectedTripDate }}">
                    <button type="button" class="assignment-date-trigger" data-assignment-calendar-trigger aria-expanded="false" aria-controls="assignmentDateCalendar">
                        <span class="assignment-date-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span>
                        <span class="assignment-date-copy"><small>Showing assignments for</small><strong>{{ \Carbon\Carbon::parse($selectedTripDate)->format('M d, Y') }}</strong></span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </button>
                    <div class="assignment-date-calendar" id="assignmentDateCalendar" data-assignment-calendar-panel hidden>
                        <div class="assignment-calendar-heading"><strong>Select Assignment Date</strong>
                            <button type="button" data-assignment-calendar-close aria-label="Close calendar"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <p class="assignment-calendar-hint">Colored dots show assignment outcomes and operational trip statuses. Multiple dots indicate different states on the same date.</p>
                        <div class="assignment-calendar-month">
                            <button type="button" data-assignment-calendar-prev aria-label="Previous month"><i class="fa-solid fa-chevron-left"></i></button>
                            <strong data-assignment-calendar-month></strong>
                            <button type="button" data-assignment-calendar-next aria-label="Next month"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                        <div class="assignment-calendar-weekdays" aria-hidden="true"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div>
                        <div class="assignment-calendar-days" data-assignment-calendar-days role="group" aria-label="Choose assignment date"></div>
                        <div class="assignment-calendar-legend" aria-label="Assignment and trip status color legend">
                            <strong>Trip Status Legend</strong>
                            <div class="assignment-calendar-legend-grid">
                                <span><i class="assignment-calendar-dot is-unassigned"></i> Scheduled / Unassigned</span>
                                <span><i class="assignment-calendar-dot is-partial"></i> Partially Assigned</span>
                                <span><i class="assignment-calendar-dot is-assigned"></i> Fully Assigned / Ready</span>
                                <span><i class="assignment-calendar-dot is-in_progress"></i> In Progress</span>
                                <span><i class="assignment-calendar-dot is-pending_ddr"></i> Pending DDR</span>
                                <span><i class="assignment-calendar-dot is-completed"></i> Completed</span>
                                <span><i class="assignment-calendar-dot is-missed"></i> Missed</span>
                                <span><i class="assignment-calendar-dot is-cancelled"></i> Cancelled</span>
                                <span><i class="assignment-calendar-dot is-delayed"></i> Delayed</span>
                            </div>
                        </div>
                        <div class="assignment-calendar-footer">
                            <span><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Multiple dots = different states</span>
                            <button type="button" data-assignment-calendar-today>Today</button>
                        </div>
                        <p class="assignment-calendar-feedback" data-assignment-calendar-feedback role="status" aria-live="polite"></p>
                    </div>
                </div>
                <div class="assignment-record-controls">
                <x-ui.table-toolbar action="{{ route('driver-bus-assignment', [], false) }}" search-label="Search Assignments" search-placeholder="Search trip, route, driver, or bus..." :filter-count="(int) (request()->filled('status') && request('status') !== 'all')" :show-filter-count="false" filter-title="Filter Assignments" :sort-options="['departure_time' => 'Schedule Time', 'trip_code' => 'Trip ID', 'status' => 'Trip Status']" default-sort="departure_time" data-server-filter-navigation="true">
                    <x-slot:hidden><input type="hidden" name="trip_date" value="{{ $selectedTripDate }}"></x-slot:hidden>
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Status" name="status" default="all"><option value="all">All Statuses</option>@foreach(['Ready', 'Assigned', 'Unassigned', 'Dispatched', 'Completed', 'Cancelled', 'Missed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>
                </div>
                </div>

                @if($trips->total() === 0 && $allTripsForDate > 0 && !request()->filled('search') && (!request()->filled('status') || request('status') === 'all'))
                    <div class="assignment-history-notice" role="status">
                        <span class="assignment-history-notice-icon" aria-hidden="true"><i class="fa-solid fa-clock-rotate-left"></i></span>
                        <div class="assignment-history-notice-copy">
                            <strong>{{ $allTripsForDate }} {{ \Illuminate\Support\Str::plural('trip', $allTripsForDate) }} recorded on {{ \Carbon\Carbon::parse($selectedTripDate)->format('M d, Y') }}</strong>
                            <p>These trips are no longer shown in active Driver &amp; Bus Assignment. The calendar dots include historical trips; review their saved driver and bus details in Trip History.</p>
                        </div>
                        <a class="assignment-history-notice-link" href="{{ route('trip-records', ['date_from' => $selectedTripDate, 'date_to' => $selectedTripDate]) }}">
                            <i class="fa-solid fa-eye" aria-hidden="true"></i> View Trip History
                        </a>
                    </div>
                @endif
                @php
                    $visibleAssignmentTrips = collect($trips->items());
                    $directionGroups = [
                        ['key' => 'incoming', 'title' => 'Incoming Trips', 'description' => 'Buses transporting employees to work', 'icon' => 'fa-arrow-down', 'style' => 'incoming'],
                        ['key' => 'outgoing', 'title' => 'Outgoing Trips', 'description' => 'Buses transporting employees home', 'icon' => 'fa-arrow-up', 'style' => 'outgoing'],
                    ];
                    $otherDirectionTrips = $visibleAssignmentTrips->filter(fn ($trip) => !in_array(strtolower(trim((string) ($trip->direction ?? ''))), ['incoming', 'outgoing'], true));
                    if ($otherDirectionTrips->isNotEmpty()) {
                        $directionGroups[] = ['key' => 'other', 'title' => 'Direction Not Set', 'description' => 'Trips awaiting service-direction classification', 'icon' => 'fa-circle-question', 'style' => 'other'];
                    }
                @endphp
                @foreach($directionGroups as $directionGroup)
                @php
                    $directionTrips = $directionGroup['key'] === 'other'
                        ? $otherDirectionTrips
                        : $visibleAssignmentTrips->filter(fn ($trip) => strtolower(trim((string) ($trip->direction ?? ''))) === $directionGroup['key']);
                @endphp
                <div class="assignment-direction-section assignment-direction-{{ $directionGroup['style'] }}">
                    <div class="assignment-direction-heading">
                        <span class="assignment-direction-icon"><i class="fa-solid {{ $directionGroup['icon'] }}" aria-hidden="true"></i></span>
                        <div class="assignment-direction-title"><h3>{{ $directionGroup['title'] }}</h3><p>{{ $directionGroup['description'] }}</p></div>
                        <span class="assignment-direction-count">{{ $directionTrips->count() }} {{ \Illuminate\Support\Str::plural('trip', $directionTrips->count()) }}</span>
                    </div>
                <div class="assignment-table-wrap gct-responsive-records gct-sticky-actions">
                    <table class="assignment-table">
                        <thead>
                            <tr>
                                <th>Time / Trip ID</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Bus</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @php
                                $lastClientRouteKey = null;
                                $sortedDirectionTrips = $directionTrips->sortBy(fn ($trip) => implode('|', [
                                    strtolower(trim((string) ($trip->client_name ?: 'No Client Allocation'))),
                                    strtolower((string) ($trip->shuttleRoute?->route_code ?? $trip->route_code_snapshot ?? '')),
                                    (string) $trip->departure_time,
                                    (string) $trip->id,
                                ]));
                            @endphp
                            @forelse($sortedDirectionTrips as $trip)
                                @php
                                    $clientLabel = trim((string) ($trip->client_name ?? '')) ?: 'No Client Allocation';
                                    $routeLabel = $trip->shuttleRoute?->route_code ?? $trip->route_code_snapshot ?? '—';
                                    $routeNameLabel = $trip->shuttleRoute?->route_name ?? $trip->route_name_snapshot ?? 'Unknown route';
                                    $clientRouteKey = strtolower($clientLabel) . '|' . strtolower((string) $routeLabel);
                                    $showClientRouteHeading = $lastClientRouteKey !== $clientRouteKey;
                                    $lastClientRouteKey = $clientRouteKey;
                                    $route = $trip->shuttleRoute;
                                    $departureDateTime = $trip->departureDateTime();
                                    $arrivalDateTime = $trip->estimatedArrivalDateTime();
                                    $arrivalDisplay = $arrivalDateTime->isSameDay($departureDateTime)
                                        ? $arrivalDateTime->format('g:i A')
                                        : $arrivalDateTime->format('M d · g:i A');
                                    $isHistorical = $trip->hasDeparted();
                                    $isScheduleEnded = $arrivalDateTime->lessThanOrEqualTo(now(config('app.business_timezone', 'Asia/Manila')));
                                    $requiredCount = max(1, (int) ($trip->required_buses ?? 1));
                                    $tripAssignments = $trip->assignments->isNotEmpty()
                                        ? $trip->assignments
                                        : ($trip->assignment ? collect([$trip->assignment]) : collect());
                                    $assignedRentals = $trip->externalRentals->where('status', 'Assigned');
                                    $assignedCount = $tripAssignments->count() + $assignedRentals->count();
                                    $usedSlotNumbers = $tripAssignments
                                        ->concat($assignedRentals)
                                        ->pluck('slot_number')
                                        ->filter(fn ($slot) => $slot !== null)
                                        ->map(fn ($slot) => (int) $slot);
                                    $nextOpenSlot = collect(range(1, $requiredCount))
                                        ->first(fn ($slot) => !$usedSlotNumbers->contains($slot)) ?? ($assignedCount + 1);
                                    $externalRental = $trip->externalRentals
                                        ->whereIn('status', ['Pending', 'Confirmed'])
                                        ->firstWhere('slot_number', $nextOpenSlot);
                                    $pendingSlotDecision = $trip->slotDecisions
                                        ->firstWhere('slot_number', $nextOpenSlot);
                                    $pendingDecisionLabel = match ($pendingSlotDecision?->decision) {
                                        'manual_later' => 'Pending Manual Assignment',
                                        'external_bus' => 'External Bus Pending',
                                        default => null,
                                    };
                                    if ($externalRental) {
                                        $pendingDecisionLabel = $externalRental->status === 'Confirmed'
                                            ? 'Rental Confirmed — Slot Pending'
                                            : 'External Rental Pending';
                                    }
                                    $tripLocked = $isHistorical || in_array($trip->status, ['Cancelled', 'Dispatched', 'Completed'], true);
                                    $tripDisplayStatus = match (true) {
                                        $trip->status === 'Cancelled' => 'Cancelled',
                                        $trip->status === 'Completed' => 'Completed',
                                        $trip->status === 'Dispatched' => 'In Progress',
                                        $trip->status === 'Pending DDR' => 'Pending DDR',
                                        $trip->status === 'Missed' => 'Missed',
                                        $isHistorical && $assignedCount === 0 => 'Missed',
                                        $isHistorical && $assignedCount < $requiredCount => 'Partially Missed',
                                        $assignedCount === 0 => 'Unassigned',
                                        $assignedCount < $requiredCount => 'Partially Assigned',
                                        default => $trip->status,
                                    };
                                @endphp

                                @if($showClientRouteHeading)
                                    <tr class="assignment-client-route-group">
                                        <th colspan="6" scope="rowgroup">
                                            <span>Client: {{ $clientLabel }}</span>
                                            <strong>Route {{ $routeLabel }} · {{ $routeNameLabel }}</strong>
                                        </th>
                                    </tr>
                                @endif

                                @if($tripAssignments->isEmpty() && $assignedRentals->isEmpty())
                                    <tr class="unassigned-row">
                                        <td class="assignment-trip-schedule-cell"><div class="assignment-trip-schedule"><div class="schedule-cell">
                                                <strong>{{ $departureDateTime->format('g:i A') }}</strong>
                                                <span>{{ $trip->trip_date?->format('M d, Y') }}</span>
                                            </div><div class="assignment-trip-reference"><x-ui.id-badge :value="$trip->trip_code" />
                                            @if($requiredCount > 1)
                                                <span class="assignment-slot-badge" style="display: block; font-size: 0.725rem; color: var(--assign-muted); margin-top: 2px;">
                                                    0/{{ $requiredCount }} Buses
                                                </span>
                                            @endif
                                            @if($pendingDecisionLabel)
                                                <span class="assignment-slot-decision">
                                                    <i class="fa-solid {{ ($externalRental || $pendingSlotDecision?->decision === 'external_bus') ? 'fa-building' : 'fa-user-gear' }}" aria-hidden="true"></i>
                                                    {{ $pendingDecisionLabel }}
                                                </span>
                                            @endif</div></div></td>

                                        <td>
                                            <div class="route-cell">
                                                <strong>{{ $route?->route_code ?? '—' }}</strong>
                                                <span>{{ $route?->route_name ?? 'Deleted route' }}</span>
                                            </div>
                                        </td>

                                        <td>
                                            <div class="assignment-resource-empty"><span class="assignment-resource-icon">—</span><strong>Not Assigned</strong></div>
                                        </td>

                                        <td>
                                            <div class="assignment-resource-empty"><span class="assignment-resource-icon">—</span><strong>Not Assigned</strong></div>
                                        </td>

                                        <td>
                                            <x-ui.status-badge
                                                :status="$tripDisplayStatus"
                                                :label="$tripDisplayStatus === 'Partially Assigned' ? 'Partial' : null"
                                                class="assignment-status {{ \App\Support\StatusBadge::normalize($tripDisplayStatus) }}"
                                                 :title="$tripDisplayStatus === 'Scheduled Done' ? 'The scheduled arrival time has passed. Actual trip completion is not verified.' : ($tripDisplayStatus === 'Missed' ? 'Departure time has already passed.' : null)"
                                            />
                                        </td>

                                        <td>
                                            <div class="assignment-actions">
                                                @if($canEditOperation && !$tripLocked && $trip->status === 'Scheduled')
                                                    <button
                                                        type="button"
                                                        class="assign-now-btn open-assignment"
                                                        data-trip-id="{{ $trip->id }}"
                                                        data-slot-number="{{ $nextOpenSlot }}"
                                                        data-trip-label="{{ $trip->trip_code }}"
                                                        data-rental-company="{{ $externalRental?->company_name }}"
                                                        data-rental-contact="{{ $externalRental?->contact_details }}"
                                                        data-rental-plate="{{ $externalRental?->plate_number }}"
                                                        data-rental-driver="{{ $externalRental?->driver_name }}"
                                                        data-rental-status="{{ $externalRental?->status ?? 'Pending' }}"
                                                        data-rental-notes="{{ $externalRental?->notes }}"
                                                        data-availability-url="{{ route('driver-bus-assignment.availability', $trip, false) }}"
                                                    >
                                                        Assign
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        class="assign-now-btn assignment-action-disabled"
                                                        disabled
                                                        aria-disabled="true"
                                                        title="{{ ($trip->daily_driver_reports_count ?? 0) > 0 || ($trip->incidents_count ?? 0) > 0 ? 'Unavailable: operational history is linked to this trip' : 'Unavailable for this trip status or departure time' }}"
                                                    >
                                                        Assign
                                                    </button>
                                                @endif

                                            </div>
                                        </td>
                                    </tr>
                                @else
                                    @foreach($tripAssignments as $index => $assignment)
                                        @php
                                            $driver = $assignment->driverAttendance;
                                            $bus = $assignment->bus;
                                            // Operational incident status belongs to this bus slot, not the whole trip.
                                            $slotBreakdown = $trip->incidents
                                                ->where('incident_type', 'Bus Breakdown')
                                                ->first(fn ($incident) =>
                                                    ($incident->trip_assignment_id && (int) $incident->trip_assignment_id === (int) $assignment->id)
                                                    || (! $incident->trip_assignment_id && (int) $incident->bus_id === (int) $assignment->bus_id)
                                                    || (! $incident->trip_assignment_id && $assignment->original_bus_id && (int) $incident->bus_id === (int) $assignment->original_bus_id)
                                                );
                                            $needsMaintenanceReplacement = ! $isHistorical
                                                && in_array($trip->status, ['Scheduled', 'Ready'], true)
                                                && (! $bus || $bus->status !== 'Active');
                                            $slotDisplayStatus = $needsMaintenanceReplacement
                                                ? 'Replacement Required' : $tripDisplayStatus;
                                            $slotStatusTooltip = $needsMaintenanceReplacement
                                                ? 'Assigned bus is unavailable. Operations Head must approve a replacement before departure.'
                                                : $tripDisplayStatus;
                                            // A sibling bus breakdown may downgrade the shared trip from Ready
                                            // to Scheduled; healthy assigned slots must still display Ready.
                                            if (! $slotBreakdown && $trip->status === 'Scheduled'
                                                && $trip->assignment_status === 'Assigned'
                                                && $bus?->status === 'Active'
                                                && $trip->incidents->contains(fn ($incident) => $incident->incident_type === 'Bus Breakdown'
                                                    && ! in_array($incident->status, ['Resolved', 'Cancelled'], true))) {
                                                $slotDisplayStatus = 'Ready';
                                                $slotStatusTooltip = 'This bus remains ready; another slot has a breakdown.';
                                            }
                                            if ($slotBreakdown && ! in_array($slotBreakdown->status, ['Resolved', 'Cancelled'], true)) {
                                                if ($slotBreakdown->replacement && in_array($trip->status, ['Ready', 'Dispatched'], true)) {
                                                    $slotDisplayStatus = $trip->status === 'Ready' ? 'Ready · Reserve' : 'In Progress · Reserve';
                                                    $slotStatusTooltip = $trip->status === 'Ready' ? 'Ready — Replacement Assigned' : 'In Progress — Reserve Deployed';
                                                } elseif (! $slotBreakdown->replacement && in_array($trip->status, ['Ready', 'Scheduled', 'Dispatched', 'Pending DDR'], true)) {
                                                    $oicResponseRecorded = $slotBreakdown->status !== 'Reported'
                                                        || (bool) $slotBreakdown->has_oic_response;
                                                    $slotDisplayStatus = $oicResponseRecorded ? 'Awaiting Reserve' : (in_array($trip->status, ['Dispatched', 'Pending DDR'], true) ? 'Breakdown' : 'OIC Review');
                                                    $slotStatusTooltip = $oicResponseRecorded
                                                        ? ($trip->status === 'Dispatched' ? 'In Progress — Awaiting Replacement' : 'Incident — Awaiting Replacement')
                                                        : ($trip->status === 'Dispatched' ? 'In Progress — Breakdown Reported' : 'Breakdown Reported — OIC Review');
                                                }
                                            }
                                            // The affected slot displays Incident Hold immediately; ETA
                                            // alone cannot release it. Unaffected slots retain their status.
                                            if ($slotBreakdown
                                                && ($slotBreakdown->status !== 'Resolved'
                                                    || ! preg_match('/\\[Trip outcome:\\s*Continued\\]/i', (string) $slotBreakdown->resolution_notes))) {
                                                $slotDisplayStatus = $slotBreakdown->status === 'Resolved'
                                                    ? (str_contains((string) $slotBreakdown->resolution_notes, '[Trip outcome: Interrupted]')
                                                        ? 'Interrupted · Review' : 'Outcome Review')
                                                    : 'Incident Hold';
                                                $slotStatusTooltip = $slotBreakdown->status === 'Resolved'
                                                    ? 'Incident resolved without a verified Continued outcome. Operations review is required.'
                                                    : 'Breakdown remains active after ETA. Awaiting verified trip outcome.';
                                            }
                                            $asgnHistory = ($assignment->daily_driver_reports_count ?? 0) > 0
                                                || ($assignment->incidents_count ?? 0) > 0;
                                            $isLocked = $tripLocked || $asgnHistory;
                                            // Explicit action semantics: mutable Scheduled slots
                                            // can be edited/removed; Ready or unavailable-bus
                                            // slots must go through Head-approved replacement.
                                            $requiresHeadReplacement = $trip->status === 'Ready' || $needsMaintenanceReplacement;
                                            $canChangeSlot = $canEditOperation && ! $isLocked
                                                && (! $requiresHeadReplacement || $canApproveOperation);
                                            $details = [
                                                'tripCode' => $trip->trip_code,
                                                'date' => $trip->trip_date?->format('M d, Y'),
                                                'departure' => $departureDateTime->format('g:i A'),
                                                'arrival' => $arrivalDisplay,
                                                'route' => trim(($route?->route_code ?? '') . ' - ' . ($route?->route_name ?? '')),
                                                'driver' => $assignment->driver_name,
                                                'driverStatus' => $driver?->status,
                                                'bus' => $bus?->plate_no ?: $bus?->bus_no,
                                                'status' => $trip->status,
                                                'assignmentStatus' => $trip->assignment_status,
                                                'replacementHistory' => ($replacementHistoryByAssignment->get($assignment->id) ?? collect())->map(fn ($audit) => [
                                                    'date' => \Illuminate\Support\Carbon::parse($audit->created_at)->format('M d, Y g:i A'),
                                                    'previousDriver' => $audit->previous_driver_name ?: $audit->previous_driver_id ?: 'Unknown',
                                                    'newDriver' => $audit->new_driver_name ?: $audit->new_driver_id ?: 'Unknown',
                                                    'previousBus' => $audit->previous_bus_label ?: $audit->previous_bus_id ?: 'Unknown',
                                                    'newBus' => $audit->new_bus_label ?: $audit->new_bus_id ?: 'Unknown',
                                                    'reason' => $audit->reason,
                                                    'approvedBy' => $audit->approved_by_name ?: 'Account '.$audit->performed_by,
                                                ])->values()->all(),
                                            ];
                                        @endphp
                                        <tr>
                                            <td class="assignment-trip-schedule-cell"><div class="assignment-trip-schedule"><div class="schedule-cell">
                                                    <strong>{{ $departureDateTime->format('g:i A') }}</strong>
                                                    <span>{{ $trip->trip_date?->format('M d, Y') }}</span>
                                                </div><div class="assignment-trip-reference"><x-ui.id-badge :value="$trip->trip_code" />
                                                @if($requiredCount > 1)
                                                    <span class="assignment-slot-badge" style="display: block; font-size: 0.725rem; color: var(--assign-muted); margin-top: 2px;">
                                                        Slot {{ $assignment->slot_number ?? ($index + 1) }} of {{ $requiredCount }}
                                                    </span>
                                                @endif</div></div></td>

                                            <td>
                                                <div class="route-cell">
                                                    <strong>{{ $route?->route_code ?? '—' }}</strong>
                                                    <span>{{ $route?->route_name ?? 'Deleted route' }}</span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="driver-cell">
                                                    <div class="driver-avatar">
                                                        {{ collect(explode(' ', $assignment->driver_name))
                                                            ->filter()
                                                            ->take(2)
                                                            ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                                            ->implode('') }}
                                                    </div>
                                                    <div>
                                                        <strong>{{ $assignment->driver_name }}</strong>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="assignment-bus-cell">
                                                    <span class="assignment-resource-icon"><i class="fa-solid fa-bus" aria-hidden="true"></i></span>
                                                    <div class="assignment-bus-info">
                                                        <strong>{{ $bus?->bus_no ?: 'Bus ID not recorded' }}</strong>
                                                        <span>{{ $bus?->bus_model ?: 'Model not recorded' }}</span>
                                                        @if($needsMaintenanceReplacement)
                                                            <span class="assignment-maintenance-warning" title="Current bus status: {{ $bus?->status ?? 'Unavailable' }}. Assignment history is preserved.">
                                                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                                                {{ $bus?->status === 'Under Maintenance' ? 'Under Maintenance' : 'Bus Unavailable' }}
                                                            </span>
                                                        @endif
                                                        @if($slotBreakdown)
                                                            <a href="{{ route('incidents.show', ['incident' => $slotBreakdown->incident_no], false) }}"
                                                               class="assignment-incident-indicator"
                                                               title="Incident {{ $slotBreakdown->incident_no }} · {{ $slotBreakdown->status }} — original assignment remains recorded">
                                                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                                                {{ $slotBreakdown->replacement ? 'Reserve Deployed' : (in_array($slotBreakdown->status, ['Resolved', 'Cancelled']) ? 'Breakdown Recorded' : 'Breakdown Reported') }}
                                                            </a>
                                                        @endif
                                                        @if($bus?->fleet_purpose === 'Reserve')
                                                            @if($assignment->reserveApproval)
                                                                <span class="assignment-oic-approved" title="OIC approval recorded through {{ $assignment->reserveApproval->approval_method }} on {{ $assignment->reserveApproval->approved_at?->format('M d, Y g:i A') }}">
                                                                    <i class="fa-solid fa-shield-halved"></i> OIC Approved
                                                                </span>
                                                            @elseif($slotBreakdown?->replacement)
                                                                <span class="assignment-oic-approved" title="OIC-authorized breakdown replacement recorded for Incident {{ $slotBreakdown->incident_no }} on {{ $slotBreakdown->replacement->dispatched_at?->format('M d, Y g:i A') }}">
                                                                    <i class="fa-solid fa-shield-halved"></i> OIC Approved
                                                                </span>
                                                            @else
                                                                <span class="assignment-oic-missing" title="No recorded OIC approval is linked to this historical or manual Reserve Bus assignment.">
                                                                    <i class="fa-solid fa-triangle-exclamation"></i> Approval Not Recorded
                                                                </span>
                                                            @endif
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <x-ui.status-badge
                                                    :status="$slotDisplayStatus"
                                                :label="$slotDisplayStatus === 'Partially Assigned' ? 'Partial' : null"
                                                    class="assignment-status {{ \App\Support\StatusBadge::normalize($slotDisplayStatus) }}"
                                                    :title="$slotStatusTooltip"
                                                />
                                            </td>

                                            <td>
                                                <div class="assignment-actions">
                                                    <x-ui.action-button
                                                        type="view"
                                                        class="assignment-action view-assignment"
                                                        title="View Assignment"
                                                        aria-label="View assignment for {{ $trip->trip_code }}"
                                                        :data-details="$details ? json_encode($details, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '{}'"
                                                    />

                                                    @if($canChangeSlot)
                                                        <x-ui.action-button
                                                            type="edit"
                                                            :icon="$requiresHeadReplacement ? 'fa-arrow-right-arrow-left' : 'fa-pen-to-square'"
                                                            class="assignment-action edit-assignment"
                                                            title="{{ $requiresHeadReplacement ? 'Replace Driver / Bus — Operations Head approval and reason required' : 'Edit Assignment' }}"
                                                            aria-label="{{ $requiresHeadReplacement ? 'Replace assigned driver or bus' : 'Edit assigned driver or bus' }}"
                                                            data-ready-replacement="{{ $requiresHeadReplacement ? 'true' : 'false' }}"
                                                            data-assignment-id="{{ $assignment->id }}"
                                                            data-slot-number="{{ $assignment->slot_number }}"
                                                            data-trip-id="{{ $trip->id }}"
                                                            data-trip-label="{{ $trip->trip_code }} — {{ $trip->trip_date?->format('M d, Y') }}"
                                                            data-availability-url="{{ route('driver-bus-assignment.availability', $trip, false) }}"
                                                            data-driver-id="{{ $assignment->driver_id }}"
                                                            data-bus-id="{{ $assignment->bus_id }}"
                                                            data-update-url="{{ route('driver-bus-assignment.update', $assignment->id, false) }}"
                                                        />

                                                        @if(! $requiresHeadReplacement)
                                                        <form
                                                            id="removeAssignmentForm-{{ $assignment->id }}"
                                                            method="POST"
                                                            action="{{ route('driver-bus-assignment.destroy', $assignment->id, false) }}"
                                                        >
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="return_trip_date" value="{{ $selectedTripDate }}">
                                                            <input type="hidden" name="return_search" value="{{ request('search') }}">
                                                            <input type="hidden" name="return_status" value="{{ request('status', 'all') }}">
                                                            <input type="hidden" name="trip_page" value="{{ request('trip_page') }}">
                                                            <input type="hidden" name="driver_page" value="{{ request('driver_page') }}">
                                                            <input type="hidden" name="bus_page" value="{{ request('bus_page') }}">

                                                            <x-ui.action-button
                                                                type="delete"
                                                                icon="fa-link-slash"
                                                                class="assignment-action remove remove-assignment"
                                                                title="Remove Assignment"
                                                                aria-label="Remove assignment for {{ $trip->trip_code }}"
                                                                data-form-id="removeAssignmentForm-{{ $assignment->id }}"
                                                                data-trip-code="{{ $trip->trip_code }}"
                                                            />
                                                        </form>
                                                        @endif
                                                    @elseif($canEditOperation && $requiresHeadReplacement)
                                                        <x-ui.action-button
                                                            type="edit"
                                                            icon="fa-arrow-right-arrow-left"
                                                            class="assignment-action assignment-action-disabled"
                                                            :disabled="true"
                                                            :title="$isLocked ? 'Replacement unavailable: trip departed or operational history exists' : 'Only Operations Head can authorize this replacement'"
                                                            aria-label="Replacement requires Operations Head authorization"
                                                        />
                                                    @elseif($canEditOperation)
                                                        <x-ui.action-button
                                                            type="edit"
                                                            class="assignment-action assignment-action-disabled"
                                                            :disabled="true"
                                                            :title="$asgnHistory ? 'Unavailable: operational history is linked to this assignment' : 'Unavailable for this trip status or departure time'"
                                                            aria-label="Edit assignment unavailable"
                                                        />
                                                        <x-ui.action-button
                                                            type="delete"
                                                            icon="fa-link-slash"
                                                            class="assignment-action remove assignment-action-disabled"
                                                            :disabled="true"
                                                            :title="$asgnHistory ? 'Unavailable: operational history is linked to this assignment' : 'Unavailable for this trip status or departure time'"
                                                            aria-label="Remove assignment unavailable"
                                                        />
                                                    @endif

                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach


                                    @foreach($assignedRentals as $external)
                                        <tr>
                                            <td class="assignment-trip-schedule-cell"><div class="assignment-trip-schedule"><div class="schedule-cell"><strong>{{ $departureDateTime->format('g:i A') }}</strong><span>{{ $trip->trip_date?->format('M d, Y') }}</span></div><div class="assignment-trip-reference"><x-ui.id-badge :value="$trip->trip_code" /><div class="assignment-slot-badge">Slot {{ $external->slot_number }} of {{ $requiredCount }}</div></div></div></td>
                                            <td><div class="route-cell"><strong>{{ $route?->route_code ?? '—' }}</strong><span>{{ $route?->route_name ?? '—' }}</span></div></td>
                                            <td><strong>{{ $external->driver_name }}</strong><div>External driver</div></td>
                                            <td><strong>{{ $external->plate_number }}</strong><div>{{ $external->company_name }} · Rented Bus</div></td>
                                            <td><x-ui.status-badge :status="$tripDisplayStatus"
                                                :label="$tripDisplayStatus === 'Partially Assigned' ? 'Partial' : null" class="assignment-status {{ \App\Support\StatusBadge::normalize($tripDisplayStatus) }}" :title="$tripDisplayStatus === 'Scheduled Done' ? 'Scheduled trip window ended; actual completion is not verified.' : null" /></td>
                                            <td><span title="External rental assignments are preserved for operational history">External Rental</span></td>
                                        </tr>
                                    @endforeach

                                    @if($assignedCount < $requiredCount && $canEditOperation && !$tripLocked && in_array($trip->status, ['Scheduled', 'Ready'], true))
                                        <tr class="unassigned-row">
                                            <td class="assignment-trip-schedule-cell"><div class="assignment-trip-schedule"><div class="schedule-cell">
                                                    <strong>{{ $departureDateTime->format('g:i A') }}</strong>
                                                    <span>{{ $trip->trip_date?->format('M d, Y') }}</span>
                                                </div><div class="assignment-trip-reference"><x-ui.id-badge :value="$trip->trip_code" />
                                                <span class="assignment-slot-badge" style="display: block; font-size: 0.725rem; color: #b45309; font-weight: 600; margin-top: 2px;">
                                                    Slot {{ $nextOpenSlot }} of {{ $requiredCount }} ({{ $pendingDecisionLabel ?? 'Pending' }})
                                                </span></div></div></td>

                                            <td>
                                                <div class="route-cell">
                                                    <strong>{{ $route?->route_code ?? '—' }}</strong>
                                                    <span>{{ $route?->route_name ?? 'Deleted route' }}</span>
                                                </div>
                                            </td>

                                            <td>
                                                <div class="assignment-resource-empty"><span class="assignment-resource-icon">—</span><strong>Not Assigned</strong></div>
                                            </td>

                                            <td>
                                                <div class="assignment-resource-empty"><span class="assignment-resource-icon">—</span><strong>Not Assigned</strong></div>
                                            </td>

                                            <td>
                                                <x-ui.status-badge
                                                     :status="$tripDisplayStatus"
                                                :label="$tripDisplayStatus === 'Partially Assigned' ? 'Partial' : null"
                                                    class="assignment-status {{ \App\Support\StatusBadge::normalize($tripDisplayStatus) }}"
                                                    :title="$tripDisplayStatus === 'Scheduled Done' ? 'Scheduled arrival time has passed; actual completion is not verified.' : 'Requires more buses to complete assignment.'"
                                                />
                                            </td>

                                            <td>
                                                <div class="assignment-actions">
                                                    <button
                                                        type="button"
                                                        class="assign-now-btn open-assignment"
                                                        data-trip-id="{{ $trip->id }}"
                                                        data-slot-number="{{ $nextOpenSlot }}"
                                                        data-trip-label="{{ $trip->trip_code }}"
                                                        data-rental-company="{{ $externalRental?->company_name }}"
                                                        data-rental-contact="{{ $externalRental?->contact_details }}"
                                                        data-rental-plate="{{ $externalRental?->plate_number }}"
                                                        data-rental-driver="{{ $externalRental?->driver_name }}"
                                                        data-rental-status="{{ $externalRental?->status ?? 'Pending' }}"
                                                        data-rental-notes="{{ $externalRental?->notes }}"
                                                        data-availability-url="{{ route('driver-bus-assignment.availability', $trip, false) }}"
                                                    >
                                                        Assign
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endif
                            @empty
                                <x-ui.empty-row
                                    colspan="6"
                                    icon="fa-route"
                                    message="No Trip Schedules Found"
                                    description="No scheduled services match the selected date or filters."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
                @endforeach

                <x-ui.table-footer :items="$trips" />
            </section>

            <section data-ajax-region="resources" class="resource-grid">
                <article class="resource-card">
                    <div class="resource-card-header">
                        <div class="resource-title-group">
                            <span class="resource-header-icon workforce"><i class="fa-solid fa-users"></i></span>
                            <div><span>Workforce</span><h2>Eligible Drivers</h2></div>
                        </div>
                        <strong class="resource-total resource-total--available">{{ $availableDrivers->total() }} Eligible</strong>
                    </div>

                    <div class="resource-filter-bar" data-resource-filter="drivers">
                        <label class="resource-filter-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" placeholder="Search driver name or ID..." aria-label="Search visible available drivers"></label>
                    </div>
                    <div class="resource-list-head resource-list-head--drivers" aria-hidden="true"><span>Driver</span><span>Authorized Buses</span><span>Scheduling Status</span></div>
                    @forelse($availableDrivers as $driver)
                        <div class="resource-record" data-resource-row="drivers" data-resource-text="{{ strtolower($driver->driver_name.' '.$driver->driver_id) }}">
                            <div class="driver-avatar">
                                {{ collect(explode(' ', $driver->driver_name))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                    ->implode('') }}
                            </div>

                            <div class="resource-record-info">
                                <strong>{{ $driver->driver_name }}</strong>
                                <span class="resource-mobile-detail">{{ $driver->authorizedBuses?->count() ?? 0 }} authorized bus(es)</span>
                            </div>
                            <span class="resource-row-detail">{{ $driver->authorizedBuses?->count() ?? 0 }} bus(es)</span>

                            <span class="gct-pill gct-pill--unassigned">Assumed Available</span>
                        </div>
                    @empty
                        <p class="resource-empty">No eligible drivers for the selected date.</p>
                    @endforelse

                    <div class="resource-pagination">
                        <x-ui.table-footer :items="$availableDrivers" />
                    </div>
                </article>

                <article class="resource-card">
                    <div class="resource-card-header">
                        <div class="resource-title-group">
                            <span class="resource-header-icon fleet"><i class="fa-solid fa-bus"></i></span>
                            <div><span>Fleet</span><h2>Eligible Buses</h2></div>
                        </div>
                        <strong class="resource-total resource-total--available">{{ $availableBuses->total() }} Eligible</strong>
                    </div>

                    <div class="resource-filter-bar" data-resource-filter="buses">
                        <label class="resource-filter-search"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" placeholder="Search plate number or model..." aria-label="Search visible unallocated buses"></label>
                    </div>
                    <div class="resource-list-head resource-list-head--buses" aria-hidden="true"><span>Bus ID</span><span>Model</span><span>Status</span></div>
                    @forelse($availableBuses as $bus)
                        <div class="resource-record" data-resource-row="buses" data-resource-text="{{ strtolower($bus->plate_no.' '.$bus->bus_model.' '.$bus->bus_no) }}" data-resource-category="{{ strtolower($bus->bus_model) }}">
                            <div class="bus-resource-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="resource-record-info">
                                <strong>{{ $bus->bus_no ?: 'Bus ID not recorded' }}</strong>
                                <span class="resource-mobile-detail">{{ $bus->bus_model ?: 'Operational bus' }}</span>
                            </div>
                            <span class="resource-row-detail">{{ $bus->bus_model ?: 'Operational bus' }}</span>

                            <x-ui.status-badge status="Active" class="availability available" />
                        </div>
                    @empty
                        <p class="resource-empty">No unallocated active buses for the selected date.</p>
                    @endforelse

                    <div class="resource-pagination">
                        <x-ui.table-footer :items="$availableBuses" />
                    </div>
                </article>
            </section>
        </main>
    </div>

    <div data-ajax-region="assignment-modal">
    @if($canEditOperation)
    <x-ui.form-modal
        id="assignmentModal"
        title="Driver & Bus Assignment"
        title-id="assignmentModalTitle"
        description="Select an available driver and active bus for the trip."
        icon="fa-user-tie"
        size="large"
        form-id="assignmentForm"
        :action="route('driver-bus-assignment.store', [], false)"
        method="POST"
        submit-text="Confirm Assignment"
        submit-text-id="assignmentSubmitText"
        submit-icon="fa-check"
        cancel-text="Cancel"
        cancel-id="cancelAssignmentModal"
        close-id="closeAssignmentModal"
    >
        <input
            type="hidden"
            name="_method"
            id="assignmentFormMethod"
            value="PUT"
            disabled
        >
        <input
            type="hidden"
            name="trip_assignment_id"
            id="assignmentEditId"
            value="{{ old('trip_assignment_id') }}"
        >
        <input type="hidden" name="return_trip_date" value="{{ $selectedTripDate }}">
        <input type="hidden" name="return_search" value="{{ request('search') }}">
        <input type="hidden" name="return_status" value="{{ request('status', 'all') }}">
        <input type="hidden" name="trip_page" value="{{ request('trip_page') }}">
        <input type="hidden" name="driver_page" value="{{ request('driver_page') }}">
        <input type="hidden" name="bus_page" value="{{ request('bus_page') }}">
        <span
            id="assignmentRestoreState"
            hidden
            data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
            data-trip-id="{{ old('trip_schedule_id') }}"
            data-driver-id="{{ old('driver_id', old('driver_attendance_id')) }}"
            data-bus-id="{{ old('bus_id') }}"
            data-method="{{ old('_method') }}"
            data-replacement-reason="{{ old('replacement_reason') }}"
        ></span>

        <div class="assignment-form-grid" id="companyAssignmentFields">
            <div class="ui-form-group ui-form-full">
                <label for="assignmentTrip">
                    Trip
                    <span class="ui-required">*</span>
                </label>

                <div class="ui-input-wrap has-icon">
                    <span class="ui-input-icon">
                        <i class="fa-solid fa-calendar-days"></i>
                    </span>

                    <select
                        name="trip_schedule_id"
                        id="assignmentTrip"
                        required
                    >
                        <option value="">Select unassigned trip</option>

                        @foreach($unassignedTrips as $trip)
                            @php
                                $tripAssignmentsCount = $trip->assignments->count() ?: ($trip->assignment ? 1 : 0);
                                $reqBuses = max(1, (int) ($trip->required_buses ?? 1));
                                $slotInfo = $reqBuses > 1 ? " ({$tripAssignmentsCount}/{$reqBuses} assigned)" : "";
                            @endphp
                            <option
                                value="{{ $trip->id }}"
                                data-availability-url="{{ route('driver-bus-assignment.availability', $trip, false) }}"
                            >
                                {{ $trip->trip_code }}{{ $slotInfo }}
                                — {{ $trip->trip_date?->format('M d, Y') }}
                                — {{ \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') }}
                                — {{ $trip->shuttleRoute?->route_code }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="ui-form-group">
    <label for="assignmentDriverTrigger">
        Driver
        <span class="ui-required">*</span>
    </label>

    <div
        class="assignment-combobox"
        id="assignmentDriverCombobox"
    >
        <input
            type="hidden"
            name="driver_id"
            id="assignmentDriver"
            required
        >

        <button
            type="button"
            class="assignment-combobox-trigger"
            id="assignmentDriverTrigger"
            aria-expanded="false"
        >
            <span class="assignment-combobox-icon">
                <i class="fa-solid fa-id-card"></i>
            </span>

            <span
                class="assignment-combobox-label placeholder"
                id="assignmentDriverLabel"
            >
                Select available driver
            </span>

            <i class="fa-solid fa-chevron-down"></i>
        </button>

        <div
            class="assignment-combobox-menu"
            id="assignmentDriverMenu"
        >
            <div class="assignment-combobox-search">
                <i class="fa-solid fa-magnifying-glass"></i>

                <input
                    type="search"
                    id="assignmentDriverSearch"
                    placeholder="Search driver name or ID..."
                    autocomplete="off"
                >
            </div>

            <div class="assignment-combobox-options" id="assignmentDriverOptions">
                <p class="assignment-combobox-empty">
                    Select a trip to load eligible drivers.
                </p>
            </div>
        </div>
    </div>

    @error('driver_attendance_id')
        <span class="ui-field-error">
            {{ $message }}
        </span>
    @enderror
</div>

            <div class="ui-form-group">
                <label for="assignmentBusTrigger">
                    Shuttle Bus
                    <span class="ui-required">*</span>
                </label>

                <div class="assignment-combobox" id="assignmentBusCombobox">
                    <input type="hidden" name="bus_id" id="assignmentBus" required>

                    <button type="button" class="assignment-combobox-trigger" id="assignmentBusTrigger" aria-expanded="false">
                        <span class="assignment-combobox-icon"><i class="fa-solid fa-bus"></i></span>
                        <span class="assignment-combobox-label placeholder" id="assignmentBusLabel">Select available bus</span>
                        <i class="fa-solid fa-chevron-down"></i>
                    </button>

                    <div class="assignment-combobox-menu" id="assignmentBusMenu">
                        <div class="assignment-combobox-search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="search" id="assignmentBusSearch" placeholder="Search bus number or model..." autocomplete="off">
                        </div>

                        <div class="assignment-combobox-options" id="assignmentBusOptions">
                            <p class="assignment-combobox-empty">Select a trip to load available buses.</p>
                        </div>
                    </div>
                </div>

                @error('bus_id')
                    <span class="ui-field-error">{{ $message }}</span>
                @enderror
            </div>
        </div>

        <div class="assignment-pair-trip" id="assignmentPairTrip" hidden>
            <div class="assignment-pair-trip-main">
                <strong id="assignmentPairTripName">Select a trip</strong>
                <span id="assignmentPairTripDetail"></span>
            </div>
            <span id="assignmentPairSlot"></span>
        </div>

        <section class="assignment-pair-picker assignment-driver-first" id="assignmentPairPicker" aria-label="Choose a driver and one eligible bus">
            <div class="assignment-pair-header">
                <div><strong>Choose Driver & Bus</strong><small>Choose a driver first, then one eligible bus. Multiple authorized buses may belong to one driver.</small></div>
                <span id="assignmentPairCount" aria-live="polite">Select a trip</span>
            </div>
            <div class="assignment-fleet-tabs" role="group" aria-label="Bus fleet type">
                <button type="button" class="assignment-fleet-tab is-active" data-assignment-fleet="Regular" aria-pressed="true">Regular Buses</button>
                <button type="button" class="assignment-fleet-tab" data-assignment-fleet="Reserve" aria-pressed="false">Reserve Buses</button>
            </div>
            <p class="assignment-fleet-note" id="assignmentFleetNote">Regular buses require driver authorization and a matching client.</p>
            <div class="assignment-driver-first-step">
                <div class="assignment-step-heading"><strong>1. Select Driver</strong><span id="assignmentDriverCount"></span></div>
                <input type="search" id="assignmentPairSearch" placeholder="Search driver name or ID..." aria-label="Search eligible drivers">
                <div class="assignment-driver-first-list" id="assignmentDriverFirstList" role="group" aria-label="Eligible drivers"></div>
            </div>
            <div class="assignment-driver-first-step">
                <div class="assignment-step-heading"><strong>2. Select Bus</strong><span id="assignmentSelectedDriverSummary">Choose a driver above</span></div>
                <div class="assignment-driver-first-buses" id="assignmentPairList" role="group" aria-label="Eligible buses for selected driver" aria-live="polite">
                    <p class="assignment-pair-empty">Select a driver to see available buses.</p>
                </div>
            </div>
        </section>

        <div class="ui-form-group ui-form-full" id="readyReplacementReasonGroup" hidden>
            <label for="readyReplacementReason">Reason for replacement <span class="ui-required">*</span></label>
            <textarea id="readyReplacementReason" name="replacement_reason" rows="3" minlength="10" maxlength="1000" placeholder="Explain the driver absence, bus breakdown, or replacement reason"></textarea>
            <small>Operations Head approval is required. The previous and replacement resources will be recorded.</small>
            @error('replacement_reason')<span class="ui-field-error">{{ $message }}</span>@enderror
        </div>

        <div class="assignment-validation">
            <i class="fa-solid fa-circle-check"></i>
            <div>
                <strong>Assignment validation</strong>
                <span>
                    The system checks driver attendance, bus status,
                    and overlapping schedules before saving.
                </span>
            </div>
        </div>
    </x-ui.form-modal>
    @endif

    <x-ui.form-modal
        id="viewAssignmentModal"
        title="Assignment Details"
        description="Trip, driver, and bus information."
        icon="fa-clipboard-check"
        size="large"
        form-id="viewAssignmentForm"
        action="#"
        method="POST"
        :show-actions="false"
        close-id="closeViewAssignmentModal"
    >
        <div
            class="assignment-details-grid"
            id="viewAssignmentContent"
        ></div>

        <div class="ui-form-actions">
            <button
                type="button"
                id="closeViewAssignmentButton"
                class="ui-form-btn ui-form-btn-primary"
            >
                <i class="fa-solid fa-check"></i>
                <span>Close</span>
            </button>
        </div>
    </x-ui.form-modal>


    @if($canEditOperation)
    <x-ui.action-buttom-modal
        mode="delete"
        id="removeAssignmentModal"
        delete-title="Remove Assignment?"
        delete-message="Remove the driver and bus from"
        name-id="removeAssignmentName"
        cancel-id="cancelRemoveAssignment"
        confirm-id="confirmRemoveAssignment"
    />
    @endif
    </div>
</x-layout.app>
