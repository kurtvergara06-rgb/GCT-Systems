<x-operation.trip-history-layout active-view="unified" heading="Unified Trip History" description="Read-only assignment fulfillment and operational outcomes for every historical trip.">
    <x-slot:kpis>
                <article class="trip-summary-card">
                    <div class="trip-summary-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p>Total Completed Trips (All Time)</p>
                        <h2>{{ number_format($totalCompletedTrips) }}</h2>
                        <small>Completed shuttle runs</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon blue">
                        <i class="fa-solid fa-gauge-high"></i>
                    </div>
                    <div>
                        <p>On-Time Rate</p>
                        <h2>{{ $onTimeRate === null ? '—' : $onTimeRate.'%' }}</h2>
                        <small>Based on stored trip actual timestamps; DDR verification shown below</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon yellow">
                        <i class="fa-solid fa-road"></i>
                    </div>
                    <div>
                        <p>Recorded Distance</p>
                        <h2>{{ number_format($totalDistanceKm, 1) }} <span style="font-size: 15px; font-weight: 600;">km</span></h2>
                        <small>Distance entered in matched driver reports</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon purple">
                        <i class="fa-solid fa-bus"></i>
                    </div>
                    <div>
                        <p>Delayed Trips (All Time)</p>
                        <h2>{{ number_format($delayedTripsCount) }}</h2>
                        <small>Trip delays recorded</small>
                    </div>
                </article>
    </x-slot:kpis>

    <x-slot:headerAction>
                    <button class="trip-export-btn trip-export-header" type="submit" form="tripRecordsFilterForm"
                        formaction="{{ route('trip-records.export') }}" formmethod="GET">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export CSV
                    </button>
    </x-slot:headerAction>

    <x-slot:toolbar>
                <x-ui.table-toolbar action="{{ route('trip-records', [], false) }}" form-id="tripRecordsFilterForm" search-label="Search Trip Records" search-placeholder="Search trip code, bus, driver, or route..." :filter-count="collect(['date_from','date_to'])->filter(fn ($key) => request()->filled($key))->count() + (int) (request()->filled('status') && request('status') !== 'all') + (int) (request()->filled('route') && request('route') !== 'all')" filter-title="Filter Trip Records" :sort-options="['trip_date' => 'Trip Date', 'trip_code' => 'Trip ID', 'departure_time' => 'Departure Time', 'status' => 'Status']" default-sort="trip_date" default-direction="desc">

                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Date From" name="date_from" type="date" />
                        <x-ui.table-toolbar-field label="Date To" name="date_to" type="date" />
                        <x-ui.table-toolbar-field label="Status" name="status" default="all"><option value="all">All Statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Route" name="route" default="all"><option value="all">All Routes</option>@foreach($routes as $route)<option value="{{ $route->id }}" @selected(request('route') == $route->id)>{{ $route->route_code }} - {{ $route->route_name }}</option>@endforeach</x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>
    </x-slot:toolbar>


                    <table class="trip-table trip-history-table unified-trip-history-table">
                        <colgroup>
                            <col class="history-col-trip">
                            <col class="history-col-schedule">
                            <col class="history-col-route">
                            <col class="history-col-resources">
                            <col class="history-col-slots">
                            <col class="history-col-assignment">
                            <col class="history-col-status">
                            <col class="history-col-actual">
                            <col class="history-col-action">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Trip ID</th>
                                <th>Schedule</th>
                                <th>Route</th>
                                <th>Assigned Resources</th>
                                <th>Bus Slots</th>
                                <th>Assignment Outcome</th>
                                <th>Operational Status</th>
                                <th>Actual Times</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($trips as $trip)
                                @php
                                    $units = $trip->assignments->sortBy('id')->values();
                                    $externalUnits = $trip->externalRentals->where('status', 'Assigned')->values();
                                    $assignedCount = $units->count() + $externalUnits->count();
                                    $requiredCount = max(1, (int) ($trip->required_buses ?? 1));
                                    $assignmentOutcome = match (true) {
                                        $trip->status === 'Cancelled' => 'Cancelled',
                                        $trip->status === 'Missed' => 'Missed',
                                        $assignedCount === 0 => 'No Assignments',
                                        $assignedCount < $requiredCount => 'Partially Assigned',
                                        default => 'Fully Assigned',
                                    };
                                    $resources = $units->map(fn ($unit) => [
                                        'driver_name' => $unit->driver_name ?: 'Driver unavailable',
                                        'driver_id' => $unit->driver_id,
                                        'bus_no' => $unit->bus?->bus_no,
                                        'plate_no' => $unit->bus?->plate_no,
                                        'bus_model' => $unit->bus?->bus_model,
                                        'actual_duration_minutes' => $unit->actual_duration_minutes,
                                        'external' => false,
                                    ])->concat($externalUnits->map(fn ($unit) => [
                                        'driver_name' => $unit->driver_name ?: 'External driver',
                                        'driver_id' => null,
                                        'bus_no' => null,
                                        'plate_no' => $unit->plate_number,
                                        'bus_model' => 'External rental',
                                        'actual_duration_minutes' => null,
                                        'external' => true,
                                    ]))->values();
                                    $firstUnit = $resources->first();
                                    $route = $trip->shuttleRoute;
                                    $recordedDistance = (float) ($trip->primary_recorded_distance_km ?? 0) + (float) ($trip->additional_recorded_distance_km ?? 0);
                                    $ddrTimes = \App\Support\Operation\TripDdrActualTimes::forTrip($trip);
                                    $singleDdrTime = $ddrTimes->count() === 1 ? $ddrTimes->first() : null;
                                    $tripDataJson = json_encode([
                                        'trip_code' => $trip->trip_code,
                                        'trip_date' => $trip->trip_date?->format('M d, Y'),
                                        'shift' => $trip->shift,
                                        'status' => $trip->status,
                                        'assignment_outcome' => $assignmentOutcome,
                                        'required_buses' => $requiredCount,
                                        'assigned_buses' => $assignedCount,
                                        'route_code' => $trip->route_code_snapshot ?: $route?->route_code,
                                        'route_name' => $trip->route_name_snapshot ?: $route?->route_name,
                                        'origin' => $trip->route_origin_snapshot ?: $route?->origin,
                                        'destination' => $trip->route_destination_snapshot ?: $route?->destination,
                                        'distance_km' => $recordedDistance > 0 ? number_format($recordedDistance, 2) : null,
                                        'planned_distance_km' => $trip->planned_distance_km ?? $route?->distance_km,
                                        'estimated_time_minutes' => $trip->planned_duration_minutes ?? $route?->estimated_time_minutes,
                                        'bus_no' => $firstUnit['bus_no'] ?? null,
                                        'plate_no' => $firstUnit['plate_no'] ?? null,
                                        'bus_model' => $firstUnit['bus_model'] ?? null,
                                        'driver_name' => $firstUnit['driver_name'] ?? null,
                                        'driver_id' => $firstUnit['driver_id'] ?? null,
                                        'assignments' => $resources->all(),
                                        'departure_time' => $trip->departure_time,
                                        'estimated_arrival_time' => $trip->estimated_arrival_time,
                                        'actual_departure_time' => $singleDdrTime['departure'] ?? '—',
                                        'actual_arrival_time' => $singleDdrTime['arrival'] ?? '—',
                                        'ddr_times' => $ddrTimes->all(),
                                        'actual_duration_minutes' => $units->first()?->actual_duration_minutes,
                                        'notes' => $trip->notes,
                                    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                                @endphp
                                <tr>
                                    <td><x-ui.id-badge :value="$trip->trip_code" /></td>
                                    <td class="unified-trip-date"><strong>{{ $trip->trip_date?->format('M d, Y') }}</strong><small>{{ $trip->departureDateTime()->format('g:i A') }} – {{ $trip->estimatedArrivalDateTime()->format('g:i A') }}</small></td>
                                    <td><strong>{{ $trip->route_name_snapshot ?: ($route?->route_name ?? 'Route unavailable') }}</strong></td>
                                    <td class="unified-trip-resource">
                                        @if($firstUnit)
                                            <strong>{{ $firstUnit['driver_name'] }}</strong>
                                            <small>{{ $firstUnit['bus_no'] ?: ($firstUnit['plate_no'] ?: 'No bus number') }}</small>
                                        @else
                                            <span class="trip-resource-empty">Not assigned</span>
                                        @endif
                                    </td>
                                    <td><span class="trip-history-slots {{ $assignedCount >= $requiredCount ? 'is-full' : ($assignedCount > 0 ? 'is-partial' : 'is-empty') }}">{{ $assignedCount }}/{{ $requiredCount }}</span></td>
                                    <td><x-ui.status-badge class="trip-history-outcome" :status="$assignmentOutcome" :title="'Assignment fulfillment; not proof of completed transportation'" /></td>
                                    <td><x-ui.status-badge :status="$trip->status === 'Dispatched' ? 'In Progress' : $trip->status" class="trip-history-operational-status" /></td>
                                    <td class="unified-trip-actual-times">
                                        @if($singleDdrTime)
                                            <strong>{{ $singleDdrTime['departure'] ?: '—' }} → {{ $singleDdrTime['arrival'] ?: '—' }}</strong>
                                            <small>DDR {{ $singleDdrTime['ddr_no'] }}</small>
                                        @elseif($ddrTimes->isNotEmpty())
                                            <strong>{{ $ddrTimes->count() }} DDR trip records</strong>
                                            <small>View individual driver/bus times</small>
                                        @else
                                            <span class="trip-resource-empty">{{ $trip->status === 'Pending DDR' ? 'Awaiting DDR' : 'No DDR recorded' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button" class="trip-history-view-action view-trip-btn" title="View unified trip history" aria-label="View history for {{ $trip->trip_code }}" data-trip='{{ $tripDataJson }}'>
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i><span class="view-trip-label">View</span>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="9" icon="fa-route" message="No Trip History Found" description="No historical trips matched the selected filters." />
                            @endforelse
                        </tbody>
                    </table>

    <x-slot:pagination><x-ui.table-footer :items="$trips" data-lazy-pagination="true" /></x-slot:pagination>

    <x-slot:after>
    <!-- Trip Record Detail Inspection Modal -->
    <div class="trip-modal-overlay" id="tripDetailModal">
        <div class="trip-modal">
            <div class="trip-modal-header">
                <div>
                    <div class="trip-modal-title-row">
                        <h2 id="modalTripCode">Trip Details</h2>
                        <span id="modalStatus" class="trip-status scheduled">—</span>
                    </div>
                    <p>Read-only assignment details and actual operational records for this trip.</p>
                </div>
                <button type="button" class="trip-modal-close" aria-label="Close modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="trip-detail-body">
                <section class="unified-trip-ddr-times" aria-label="DDR reported actual times">
                    <h3>Driver-Reported Actual Times (DDR)</h3>
                    <p>Only linked DDR records are shown. Each bus or additional trip is listed separately.</p>
                    <div class="unified-trip-ddr-table-wrap">
                        <table>
                            <thead><tr><th>DDR</th><th>Driver / Bus</th><th>Departure</th><th>Arrival</th></tr></thead>
                            <tbody id="modalDdrTimeRows"><tr><td colspan="4">Select a trip to view DDR times.</td></tr></tbody>
                        </table>
                    </div>
                </section>
                <section class="unified-trip-modal-summary" aria-label="Assignment fulfillment">
                    <div><small>Required Bus Slots</small><strong id="modalRequiredSlots">—</strong></div>
                    <div><small>Assigned Bus Slots</small><strong id="modalAssignedSlots">—</strong></div>
                    <div><small>Assignment Outcome</small><strong id="modalAssignmentOutcome">—</strong></div>
                </section>
                <!-- Saved unit assignments for this historical trip (no placeholder records). -->
                <div class="unit-records-card">
                    <div class="unit-records-header">
                        <div class="unit-records-title-group">
                            <h3>Assigned Units</h3>
                            <p>Actual saved driver and bus assignments for this trip.</p>
                        </div>
                        <div class="unit-records-controls">
                            <label class="unit-records-search">
                                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                                <input type="search" id="unitRecordSearchInput" placeholder="Search plate, driver, or bus..." aria-label="Search assigned units" />
                            </label>
                            <button type="button" class="unit-records-export-btn" id="unitExportBtn"><i class="fa-solid fa-file-csv" aria-hidden="true"></i> Export Units CSV</button>
                        </div>
                    </div>
                    <div class="unit-records-table-wrap">
                        <table class="unit-records-table">
                            <thead><tr><th>Slot</th><th>Bus / Plate</th><th>Driver</th><th>Scheduled Departure</th><th>Trip Status</th></tr></thead>
                            <tbody id="actualTripUnitRows"><tr><td colspan="5">Select a trip to inspect its saved assignments.</td></tr></tbody>
                        </table>
                    </div>
                    <div class="unit-records-footer" id="unitTableEntriesCount"></div>
                </div>

                <!-- Underlying Database Telemetry Drawer (Preserves all verified historical record fields) -->
                <details class="trip-database-telemetry-drawer">
                    <summary>
                        <div class="telemetry-drawer-summary-left">
                            <i class="fa-solid fa-database"></i>
                            <div>
                                <strong>Underlying System Database Record</strong>
                                <span>Schedule timings, snapshots, and driver notes recorded in the database</span>
                            </div>
                        </div>
                        <i class="fa-solid fa-chevron-down telemetry-drawer-chevron"></i>
                    </summary>

                    <div class="telemetry-drawer-content">
                        <!-- Overview & Status -->
                        <div class="trip-detail-section">
                            <h3 class="trip-detail-section-title">Record Date</h3>
                            <div class="trip-detail-grid">
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Operational Date</span>
                                    <span id="modalTripDate" class="trip-detail-value">—</span>
                                </div>

                            </div>
                        </div>

                        <!-- Route Information -->
                        <div class="trip-detail-section">
                            <h3 class="trip-detail-section-title">Route & Corridor</h3>
                            <div class="trip-detail-grid">
                                <div class="trip-detail-item full">
                                    <span class="trip-detail-label">Shuttle Route</span>
                                    <span id="modalRouteName" class="trip-detail-value" style="color: var(--trip-blue);">—</span>
                                </div>
                                <div class="trip-detail-item full">
                                    <span class="trip-detail-label">Origin & Destination</span>
                                    <span id="modalRouteSpan" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Recorded Distance</span>
                                    <span id="modalDistance" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Estimated Transit Time</span>
                                    <span id="modalEstTime" class="trip-detail-value">—</span>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle & Personnel Assignment -->
                        <div class="trip-detail-section">
                            <h3 class="trip-detail-section-title">Primary Assigned Bus & Driver</h3>
                            <div class="trip-detail-grid">
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Assigned Plate Number</span>
                                    <span id="modalBusNo" class="trip-detail-value" style="color: var(--trip-blue);">—</span>
                                    <small id="modalBusDetails" style="color: #64748b; font-size: 11px;">—</small>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Assigned Driver</span>
                                    <span id="modalDriverName" class="trip-detail-value">—</span>
                                    <small id="modalDriverId" style="color: #64748b; font-size: 11px;">—</small>
                                </div>
                            </div>
                        </div>

                        <!-- Timings Breakdown -->
                        <div class="trip-detail-section">
                            <h3 class="trip-detail-section-title">Execution Timings</h3>
                            <div class="trip-detail-grid">
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Scheduled Departure</span>
                                    <span id="modalSchedDept" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Estimated Arrival</span>
                                    <span id="modalSchedArr" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Actual Departure</span>
                                    <span id="modalActualDept" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Actual Arrival</span>
                                    <span id="modalActualArr" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item full">
                                    <span class="trip-detail-label">Total Actual Run Duration</span>
                                    <span id="modalDuration" class="trip-detail-value">—</span>
                                </div>
                            </div>
                        </div>

                        <!-- Log Notes -->
                        <div class="trip-detail-section">
                            <h3 class="trip-detail-section-title">Operational Notes</h3>
                            <div class="trip-detail-item full" style="background: #ffffff; border: 1px dashed #cbd5e1;">
                                <p id="modalNotes" style="margin: 0; font-size: 13px; color: #334155; line-height: 1.5;">—</p>
                            </div>
                        </div>
                    </div>
                </details>
            </div>

            <div class="trip-modal-actions">
                <span class="trip-modal-footer-caption">
                    <i class="fa-solid fa-shield-halved"></i> GCT-Systems • Saved Operational Records
                </span>
                <button type="button" class="trip-secondary-btn trip-modal-dismiss">
                    Close
                </button>
            </div>
        </div>
    </div>
    </x-slot:after>
</x-operation.trip-history-layout>
