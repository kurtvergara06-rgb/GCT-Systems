<x-layout.app
    title="FROMS - Trip Records"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Trip_Records/trip-records.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Trip_Records/trip-records.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main trip-records-page">
            <x-layout.topbar
                title="Trip Records"
                subtitle="Review completed shuttle trips and operational history"
                notification-count="4"
            />

            <!-- Summary KPI Cards -->
            <section class="trip-summary-grid">
                <article class="trip-summary-card">
                    <div class="trip-summary-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p>Total Completed Trips</p>
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
                        <h2>{{ $onTimeRate }}%</h2>
                        <small>Punctual arrivals</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon yellow">
                        <i class="fa-solid fa-road"></i>
                    </div>
                    <div>
                        <p>Total Distance Logged</p>
                        <h2>{{ number_format($totalDistanceKm, 1) }} <span style="font-size: 15px; font-weight: 600;">km</span></h2>
                        <small>Recorded operational mileage</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon purple">
                        <i class="fa-solid fa-bus"></i>
                    </div>
                    <div>
                        <p>Active Fleet Logged</p>
                        <h2>{{ $activeFleetCount }}</h2>
                        <small>Buses deployed historically</small>
                    </div>
                </article>
            </section>

            <!-- Main Records Card -->
            <section class="trip-card">
                <div class="trip-card-header">
                    <div>
                        <h2>Operational Trip History</h2>
                        <p>Complete historical log of shuttle bus trips, scheduled vs actual timings, vehicle assignments, and route performance.</p>
                    </div>
                </div>

                <!-- Filter & Search Toolbar -->
                <form method="GET" action="{{ route('trip-records') }}" class="trip-toolbar">
                    <div class="trip-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search by trip code, bus, driver, route..."
                        />
                    </div>

                    <div class="trip-filter date-filter">
                        <label for="filterDate">Date</label>
                        <input
                            type="date"
                            id="filterDate"
                            name="trip_date"
                            value="{{ request('trip_date') }}"
                            onchange="this.form.submit()"
                        />
                    </div>

                    <div class="trip-filter">
                        <label for="filterRoute">Route</label>
                        <select id="filterRoute" name="route" onchange="this.form.submit()">
                            <option value="all">All Routes</option>
                            @foreach($routes as $route)
                                <option value="{{ $route->id }}" @selected(request('route') == $route->id)>
                                    {{ $route->route_code }} - {{ $route->route_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="trip-filter">
                        <label for="filterShift">Shift</label>
                        <select id="filterShift" name="shift" onchange="this.form.submit()">
                            <option value="all">All Shifts</option>
                            @foreach($shifts as $shift)
                                <option value="{{ $shift }}" @selected(request('shift') === $shift)>
                                    {{ $shift }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="trip-filter">
                        <label for="filterStatus">Status</label>
                        <select id="filterStatus" name="status" onchange="this.form.submit()">
                            <option value="all">All Statuses</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request()->anyFilled(['search', 'trip_date', 'route', 'shift', 'status']))
                        <a href="{{ route('trip-records') }}" class="trip-clear-btn" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    @endif
                </form>

                <!-- Trip Table Container with Contained Scroll -->
                <div class="trip-table-wrap">
                    <table class="trip-table">
                        <thead>
                            <tr>
                                <th>Trip ID</th>
                                <th>Date & Shift</th>
                                <th>Route & Terminals</th>
                                <th>Bus No.</th>
                                <th>Driver</th>
                                <th>Scheduled</th>
                                <th>Actual Times</th>
                                <th>Distance</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($trips as $trip)
                                @php
                                    $assignment = $trip->assignment;
                                    $bus = $assignment?->bus;
                                    $route = $trip->shuttleRoute;
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
                                        'route_code' => $route?->route_code,
                                        'route_name' => $route?->route_name,
                                        'origin' => $route?->origin,
                                        'destination' => $route?->destination,
                                        'distance_km' => $route?->distance_km ? number_format($route->distance_km, 2) : null,
                                        'estimated_time_minutes' => $route?->estimated_time_minutes,
                                        'bus_no' => $bus?->bus_no,
                                        'plate_no' => $bus?->plate_no,
                                        'bus_model' => $bus?->bus_model,
                                        'driver_name' => $assignment?->driver_name,
                                        'driver_id' => $assignment?->driver_id,
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
                                            <strong>{{ $route?->route_name ?: '—' }}</strong>
                                            <span>
                                                {{ $route?->origin ?: '—' }}
                                                <i class="fa-solid fa-arrow-right-long" style="font-size: 9px; margin: 0 3px; color: var(--trip-blue);"></i>
                                                {{ $route?->destination ?: '—' }}
                                            </span>
                                        </div>
                                    </td>

                                    <td>
                                        @if($bus)
                                            <x-ui.id-badge :value="$bus->bus_no" />
                                        @else
                                            <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($assignment?->driver_name)
                                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                                <span style="font-weight: 700; color: var(--trip-navy);">{{ $assignment->driver_name }}</span>
                                                @if($assignment->driver_id)
                                                    <small style="color: var(--trip-muted); font-size: 11px;">{{ $assignment->driver_id }}</small>
                                                @endif
                                            </div>
                                        @else
                                            <span style="color: #94a3b8; font-style: italic;">Unassigned</span>
                                        @endif
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
                                            {{ $route?->distance_km ? number_format($route->distance_km, 1) . ' km' : '—' }}
                                        </strong>
                                    </td>

                                    <td>
                                        <span class="trip-status {{ $statusClass }}">
                                            {{ $trip->status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="trip-actions">
                                            <button
                                                type="button"
                                                class="trip-action view view-trip-btn"
                                                title="View Trip Details"
                                                data-trip="{{ $tripDataJson }}"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" style="text-align: center; padding: 48px 20px;">
                                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: var(--trip-muted);">
                                            <i class="fa-solid fa-route" style="font-size: 32px; color: #cbd5e1;"></i>
                                            <strong style="font-size: 15px; color: var(--trip-navy);">No Trip Records Found</strong>
                                            <p style="font-size: 13px; margin: 0; max-width: 400px;">
                                                No historical trips matched your search or filter criteria. Try adjusting the date, route, or status filters.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="trip-table-footer">
                    <span>
                        Showing {{ $trips->firstItem() ?? 0 }} to {{ $trips->lastItem() ?? 0 }} of {{ $trips->total() }} trip records
                    </span>

                    {{ $trips->links() }}
                    <div class="trip-pagination">
                        @if ($trips->onFirstPage())
                            <button type="button" class="disabled" disabled>
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </button>
                        @else
                            <a href="{{ $trips->previousPageUrl() }}">
                                <i class="fa-solid fa-chevron-left"></i> Previous
                            </a>
                        @endif

                        <span>Page {{ $trips->currentPage() }} of {{ $trips->lastPage() }}</span>

                        @if ($trips->hasMorePages())
                            <a href="{{ $trips->nextPageUrl() }}">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        @else
                            <button type="button" class="disabled" disabled>
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Trip Record Detail Inspection Modal -->
    <div class="trip-modal-overlay" id="tripDetailModal">
        <div class="trip-modal">
            <div class="trip-modal-header">
                <div>
                    <h2 id="modalTripCode">Trip Details</h2>
                    <p>Comprehensive operational log breakdown for this shuttle trip.</p>
                </div>
                <button type="button" class="trip-modal-close" aria-label="Close modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="trip-detail-body">
                <!-- Status & Timing Header -->
                <div class="trip-detail-section">
                    <h3 class="trip-detail-section-title">Overview & Status</h3>
                    <div class="trip-detail-grid">
                        <div class="trip-detail-item">
                            <span class="trip-detail-label">Current Status</span>
                            <span id="modalStatus" class="trip-status scheduled">—</span>
                        </div>
                        <div class="trip-detail-item">
                            <span class="trip-detail-label">Operational Date & Shift</span>
                            <span class="trip-detail-value"><span id="modalTripDate">—</span> • <span id="modalShift">—</span></span>
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
                            <span class="trip-detail-label">Route Distance</span>
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
                    <h3 class="trip-detail-section-title">Assigned Bus & Driver</h3>
                    <div class="trip-detail-grid">
                        <div class="trip-detail-item">
                            <span class="trip-detail-label">Assigned Bus No.</span>
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

            <div class="trip-modal-actions" style="padding: 14px 22px; margin: 0; background: #f8fafc; border-top: 1px solid var(--trip-border); border-radius: 0 0 18px 18px;">
                <button type="button" class="trip-secondary-btn trip-modal-dismiss" style="margin-left: auto;">
                    Close
                </button>
            </div>
        </div>
    </div>
</x-layout.app>
