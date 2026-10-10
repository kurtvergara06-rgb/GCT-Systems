<x-layout.app
    title="GCT - Bus Availability"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main bus-master-list-page">
            <x-layout.topbar
                title="Bus Availability"
                subtitle="Operational readiness based on current bus records and trip assignments"
            />

            <section data-ajax-region="summary" class="stats-grid">
                <x-ui.summary-card label="Total Buses" value="{{ $totalBuses }}" small="Registered buses" icon="fa-bus" color="blue" />
                <x-ui.summary-card label="Active" value="{{ $activeBuses }}" small="Operational master status" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="Under Maintenance" value="{{ $maintenanceBuses }}" small="Unavailable for dispatch" icon="fa-screwdriver-wrench" color="red" />
                <x-ui.summary-card label="Inactive" value="{{ $inactiveBuses }}" small="Out of service" icon="fa-circle-pause" color="yellow" />
            </section>

            <section data-ajax-region="records" class="table-card">
                <div class="section-header">
                    <div>
                        <h2>Bus Readiness</h2>
                        <p>This is a read-only operational overview. Update official bus status in Bus Master List or Maintenance.</p>
                    </div>
                </div>

                <form method="GET" action="{{ route('bus-availability') }}" class="toolbar bus-toolbar" data-server-filter="true">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search bus number, plate or model..." aria-label="Search buses">
                    </div>

                    <div class="filter-group">
                        <label for="busAvailabilityStatus">Master Status</label>
                        <select id="busAvailabilityStatus" name="status" aria-label="Filter by bus master status">
                            <option value="">All statuses</option>
                            <option value="Active" @selected(request('status') === 'Active')>Active</option>
                            <option value="Under Maintenance" @selected(request('status') === 'Under Maintenance')>Under Maintenance</option>
                            <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
                        </select>
                    </div>
                    <button class="primary-btn" type="submit">Apply Filters</button>
                </form>

                <div class="table-wrap">
                    <table class="bus-table">
                        <thead>
                            <tr>
                                <th>Bus ID (Plate Number)</th>
                                <th>Unit No.</th>
                                <th>Model</th>
                                <th>Availability</th>
                                <th>Next / Active Assignment</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($buses as $bus)
                                @php
                                    $readiness = match (true) {
                                        $bus->status === 'Under Maintenance' => 'Under Maintenance',
                                        $bus->status !== 'Active' => 'Out of Service',
                                        $bus->is_on_trip => 'On Trip',
                                        default => 'Available',
                                    };
                                    $readinessColor = match ($readiness) {
                                        'Available' => '#147d46',
                                        'On Trip' => '#1d4ed8',
                                        'Under Maintenance' => '#b42318',
                                        default => '#667085',
                                    };
                                @endphp
                                <tr>
                                    <td><strong>{{ $bus->plate_no ?: 'Plate not recorded' }}</strong></td>
                                    <td>{{ $bus->bus_no }}</td>
                                    <td>{{ $bus->bus_model ?: '—' }}</td>
                                    <td>
                                        <span style="font-weight:600;color:{{ $readinessColor }}">{{ $readiness }}</span>
                                    </td>
                                    <td>
                                        @if($bus->next_assignment)
                                            <strong>{{ $bus->next_assignment->trip_date?->format('M d, Y') }}</strong>
                                            {{ $bus->next_assignment->departure_time }}
                                            <span>— {{ $bus->next_assignment->route_name_snapshot ?: ($bus->next_assignment->shuttleRoute?->route_name ?? 'Route unavailable') }}</span>
                                        @else
                                            <span>No upcoming trip assignment</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5">No buses match your filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div style="padding:16px">{{ $buses->links() }}</div>
            </section>
            <p style="margin:12px 0;color:#667085;font-size:12px">
                Scheduled assignments do not automatically mean a bus is currently on a trip.
                Confirm departure and completion through the existing Trip Management workflow.
            </p>
        </main>
    </div>
</x-layout.app>
