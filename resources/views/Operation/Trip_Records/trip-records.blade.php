<x-operation.trip-history-layout active-view="operations" heading="Operational Records" description="Completed, delayed, cancelled, and missed trips. Upcoming and scheduled trips remain in Scheduling.">
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
                        <small>Completed trips with verified arrival times</small>
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
                <x-ui.table-toolbar action="{{ route('trip-records', ['view' => 'operations'], false) }}" form-id="tripRecordsFilterForm" search-label="Search Trip Records" search-placeholder="Search trip code, bus, driver, or route..." :filter-count="collect(['date_from','date_to'])->filter(fn ($key) => request()->filled($key))->count() + (int) (request()->filled('status') && request('status') !== 'all') + (int) (request()->filled('route') && request('route') !== 'all') + (int) (request()->filled('shift') && request('shift') !== 'all')" filter-title="Filter Trip Records" :sort-options="['trip_date' => 'Trip Date', 'trip_code' => 'Trip ID', 'departure_time' => 'Departure Time', 'status' => 'Status']" default-sort="trip_date" default-direction="desc">
                    <x-slot:hidden><input type="hidden" name="view" value="operations"></x-slot:hidden>
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Date From" name="date_from" type="date" />
                        <x-ui.table-toolbar-field label="Date To" name="date_to" type="date" />
                        <x-ui.table-toolbar-field label="Status" name="status" default="all"><option value="all">All Statuses</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Route" name="route" default="all"><option value="all">All Routes</option>@foreach($routes as $route)<option value="{{ $route->id }}" @selected(request('route') == $route->id)>{{ $route->route_code }} - {{ $route->route_name }}</option>@endforeach</x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Shift" name="shift" default="all"><option value="all">All Shifts</option>@foreach($shifts as $shift)<option value="{{ $shift }}" @selected(request('shift') === $shift)>{{ $shift }}</option>@endforeach</x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>
    </x-slot:toolbar>

    <x-slot:helper><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Operational records reflect saved trip execution and matched driver-report data.</x-slot:helper>

                    <table class="trip-table trip-history-table">
                        <thead>
                            <tr>
                                <th>Trip ID</th>
                                <th>Date & Shift</th>
                                <th>Route & Terminals</th>
                                <th>Plate Number</th>
                                <th>Driver</th>
                                <th>Scheduled</th>
                                <th>Actual Times</th>
                                <th>Recorded Distance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($trips as $trip)
                                @php
                                    $allAssignments = $trip->assignments->sortBy('id')->values();
                                    $assignment = $allAssignments->first() ?: $trip->assignment;
                                    $bus = $assignment?->bus;
                                    $route = $trip->shuttleRoute;
                                    $routeCode = $trip->route_code_snapshot ?: $route?->route_code;
                                    $routeName = $trip->route_name_snapshot ?: $route?->route_name;
                                    $routeOrigin = $trip->route_origin_snapshot ?: $route?->origin;
                                    $routeDestination = $trip->route_destination_snapshot ?: $route?->destination;
                                    $plannedDistance = $trip->planned_distance_km ?? $route?->distance_km;
                                    $recordedDistance = (float) ($trip->primary_recorded_distance_km ?? 0)
                                        + (float) ($trip->additional_recorded_distance_km ?? 0);
                                    $tripDateFormatted = \Carbon\Carbon::parse($trip->trip_date)->format('M d, Y');
                                    $shiftClass = strtolower($trip->shift);
                                    $statusClass = strtolower($trip->status);

                                    $departureFmt = $trip->departure_time ? \Carbon\Carbon::parse($trip->departure_time)->format('H:i') : '—';
                                    $estArrivalFmt = $trip->estimated_arrival_time ? \Carbon\Carbon::parse($trip->estimated_arrival_time)->format('H:i') : '—';

                                    $actualDeptFmt = $trip->actual_departure_time ? \Carbon\Carbon::parse($trip->actual_departure_time)->format('H:i') : null;
                                    $actualArrFmt = $trip->actual_arrival_time ? \Carbon\Carbon::parse($trip->actual_arrival_time)->format('H:i') : null;

                                    $tripDataJson = json_encode([
                                        'trip_code' => $trip->trip_code,
                                        'trip_date' => $tripDateFormatted,
                                        'shift' => $trip->shift,
                                        'status' => $trip->status,
                                        'route_code' => $routeCode,
                                        'route_name' => $routeName,
                                        'origin' => $routeOrigin,
                                        'destination' => $routeDestination,
                                        'distance_km' => $recordedDistance > 0 ? number_format($recordedDistance, 2) : null,
                                        'planned_distance_km' => $plannedDistance ? number_format($plannedDistance, 2) : null,
                                        'estimated_time_minutes' => $trip->planned_duration_minutes ?? $route?->estimated_time_minutes,
                                        'bus_no' => $bus?->bus_no,
                                        'plate_no' => $bus?->plate_no,
                                        'bus_model' => $bus?->bus_model,
                                        'driver_name' => $assignment?->driver_name,
                                        'driver_id' => $assignment?->driver_id,
                                        'assignments' => $allAssignments->map(fn ($a) => [
                                            'bus_no' => $a->bus?->bus_no,
                                            'plate_no' => $a->bus?->plate_no,
                                            'bus_model' => $a->bus?->bus_model,
                                            'driver_name' => $a->driver_name,
                                            'driver_id' => $a->driver_id,
                                            'actual_duration_minutes' => $a->actual_duration_minutes,
                                        ])->values()->all(),
                                        'departure_time' => $departureFmt,
                                        'estimated_arrival_time' => $estArrivalFmt,
                                        'actual_departure_time' => $actualDeptFmt ?: '—',
                                        'actual_arrival_time' => $actualArrFmt ?: '—',
                                        'actual_duration_minutes' => $assignment?->actual_duration_minutes,
                                        'notes' => $trip->notes,
                                    ]);
                                @endphp

                                <tr>
                                    <td>
                                        <x-ui.id-badge :value="$trip->trip_code" />
                                    </td>

                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-start;">
                                            <strong style="font-size: 12px; color: var(--trip-navy);">{{ $tripDateFormatted }}</strong>
                                            <span class="shift-badge {{ $shiftClass }}">{{ $trip->shift }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="route-cell">
                                            <strong>{{ $routeName ?: '—' }}</strong>
                                            <span>
                                                {{ $routeOrigin ?: '—' }}
                                                <i class="fa-solid fa-arrow-right-long" style="font-size: 9px; margin: 0 3px; color: var(--trip-blue);"></i>
                                                {{ $routeDestination ?: '—' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        @forelse($allAssignments as $unit)
                                            <div style="margin-bottom: 3px;"><x-ui.id-badge :value="$unit->bus?->plate_no ?: ($unit->bus?->bus_no ?: 'No plate')" /></div>
                                        @empty
                                            <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                                        @endforelse
                                    </td>

                                    <td>
                                        @forelse($allAssignments as $unit)
                                            <div style="display: flex; flex-direction: column; gap: 2px; margin-bottom: 3px;">
                                                <span style="font-weight: 700; color: var(--trip-navy);">{{ $unit->driver_name ?: '—' }}</span>
                                                @if($unit->driver_id)
                                                    <small style="color: var(--trip-muted); font-size: 11px;">{{ $unit->driver_id }}</small>
                                                @endif
                                            </div>
                                        @empty
                                            <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                                        @endforelse
                                    </td>

                                    <td>
                                        <span style="font-weight: 600; color: #334155; font-size: 12px;">
                                            {{ $departureFmt }} <span style="color: #94a3b8;">→</span> {{ $estArrivalFmt }}
                                        </span>
                                    </td>

                                    <td>
                                        @if($actualDeptFmt || $actualArrFmt)
                                            <div style="display: flex; flex-direction: column; gap: 2px; align-items: center;">
                                                <span style="font-weight: 700; color: var(--trip-navy); font-size: 12px;">
                                                    {{ $actualDeptFmt ?: '—' }} → {{ $actualArrFmt ?: '—' }}
                                                </span>
                                                @if($assignment?->actual_duration_minutes)
                                                    <small style="color: var(--trip-muted); font-size: 11px;">{{ $assignment->actual_duration_minutes }} mins run</small>
                                                @endif
                                            </div>
                                        @else
                                            <span style="color: #94a3b8;">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <strong style="color: var(--trip-navy); font-size: 12px;">
                                            {{ $recordedDistance > 0 ? number_format($recordedDistance, 1) . ' km' : '—' }}
                                        </strong>
                                    </td>

                                    <td>
                                        <x-ui.status-badge :status="$trip->status" class="trip-status {{ $statusClass }}" />
                                    </td>

                                    <td>
                                        <div class="trip-actions">
                                            <button
                                                type="button"
                                                class="trip-action view view-trip-btn"
                                                title="View Trip Details"
                                                data-trip="{{ $tripDataJson }}"
                                            >
                                                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="10"
                                    icon="fa-route"
                                    message="No Trip Records Found"
                                    description="No historical trips matched your search or filter criteria. Try adjusting the date, route, or status filters."
                                />
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
                    <p>Comprehensive operational log & multi-unit schedule execution breakdown.</p>
                </div>
                <button type="button" class="trip-modal-close" aria-label="Close modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="trip-detail-body">
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
                            <h3 class="trip-detail-section-title">Record Dates & Shift</h3>
                            <div class="trip-detail-grid">
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Operational Date</span>
                                    <span id="modalTripDate" class="trip-detail-value">—</span>
                                </div>
                                <div class="trip-detail-item">
                                    <span class="trip-detail-label">Shift</span>
                                    <span id="modalShift" class="trip-detail-value">—</span>
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
