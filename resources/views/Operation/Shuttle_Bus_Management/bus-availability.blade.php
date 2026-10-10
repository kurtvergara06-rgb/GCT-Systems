<x-layout.app
    title="GCT - Bus Availability"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Shuttle_Bus_Management/bus-availability.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main bus-availability-page">
            <x-layout.topbar
                title="Bus Availability"
                subtitle="Live operational readiness from bus and trip records"
            />

            <section data-ajax-region="summary" class="availability-stats" aria-label="Fleet status summary">
                <x-ui.summary-card label="Total Buses" value="{{ $totalBuses }}" small="Registered fleet" icon="fa-bus" color="blue" />
                <x-ui.summary-card label="Active" value="{{ $activeBuses }}" small="Active master status" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="Under Maintenance" value="{{ $maintenanceBuses }}" small="Not ready for dispatch" icon="fa-screwdriver-wrench" color="red" />
                <x-ui.summary-card label="Inactive" value="{{ $inactiveBuses }}" small="Out of service" icon="fa-circle-pause" color="yellow" />
            </section>

            <section data-ajax-region="records" class="availability-panel">
                <div class="availability-heading">
                    <div class="availability-heading-copy">
                        <span class="availability-heading-icon"><i class="fa-solid fa-bus-simple" aria-hidden="true"></i></span>
                        <div>
                            <h2>Bus Readiness</h2>
                            <p>Read-only fleet overview. Manage official records in Bus Master List or Maintenance.</p>
                        </div>
                    </div>
                    <div class="availability-heading-actions">
                        <span class="availability-as-of"><i class="fa-regular fa-clock" aria-hidden="true"></i> As of {{ now(config('app.business_timezone', 'Asia/Manila'))->format('M d, Y g:i A') }}</span>
                        <a href="{{ route('bus-availability', request()->only(['search', 'status', 'model'])) }}" class="availability-refresh"><i class="fa-solid fa-rotate" aria-hidden="true"></i> Refresh</a>
                    </div>
                </div>

                <form method="GET" action="{{ route('bus-availability') }}" class="availability-toolbar" data-server-filter="true">
                    <label class="availability-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <span class="sr-only">Search by plate number or model</span>
                        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search plate number or model...">
                    </label>
                    <label class="availability-select">
                        <span class="sr-only">Filter by master status</span>
                        <select name="status" aria-label="Filter by master status">
                            <option value="">All Statuses</option>
                            <option value="Active" @selected(request('status') === 'Active')>Active</option>
                            <option value="Under Maintenance" @selected(request('status') === 'Under Maintenance')>Under Maintenance</option>
                            <option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option>
                        </select>
                    </label>
                    <label class="availability-select">
                        <span class="sr-only">Filter by model</span>
                        <select name="model" aria-label="Filter by bus model">
                            <option value="">All Models</option>
                            @foreach($models as $model)
                                <option value="{{ $model }}" @selected(request('model') === $model)>{{ $model }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="availability-apply" type="submit"><i class="fa-solid fa-filter" aria-hidden="true"></i> Apply Filters</button>
                </form>

                <div class="availability-table-wrap">
                    <table class="availability-table">
                        <thead>
                            <tr>
                                <th>Bus ID (Plate Number)</th>
                                <th>Model</th>
                                <th>Current Status</th>
                                <th>Next / Active Assignment</th>
                                <th class="availability-actions-header">Actions</th>
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
                                    $tone = match ($readiness) {
                                        'Available' => 'ready',
                                        'On Trip' => 'trip',
                                        'Under Maintenance' => 'maintenance',
                                        default => 'inactive',
                                    };
                                @endphp
                                <tr>
                                    <td><span class="availability-plate">{{ $bus->plate_no ?: 'Plate not recorded' }}</span></td>
                                    <td>{{ $bus->bus_model ?: '—' }}</td>
                                    <td><span class="availability-status availability-status--{{ $tone }}"><i class="fa-solid {{ $tone === 'ready' ? 'fa-circle-check' : ($tone === 'maintenance' ? 'fa-screwdriver-wrench' : ($tone === 'trip' ? 'fa-bus' : 'fa-circle-pause')) }}" aria-hidden="true"></i> {{ $readiness }}</span></td>
                                    <td>
                                        @if($bus->next_assignment)
                                            <span class="availability-assignment">
                                                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                                                {{ $bus->next_assignment->trip_date?->format('M d, Y') }} at {{ $bus->next_assignment->departure_time }}
                                                — {{ $bus->next_assignment->route_name_snapshot ?: ($bus->next_assignment->shuttleRoute?->route_name ?? 'Route unavailable') }}
                                            </span>
                                        @else
                                            <span class="availability-no-trip"><i class="fa-regular fa-calendar" aria-hidden="true"></i> No upcoming trip assignment</span>
                                        @endif
                                    </td>
                                    <td class="availability-actions-cell">
                                        <details class="availability-detail">
                                            <summary aria-label="View details for {{ $bus->plate_no ?: 'bus with no recorded plate' }}" title="View bus details"><i class="fa-regular fa-eye" aria-hidden="true"></i></summary>
                                            <div class="availability-detail-content">
                                                <strong>Bus Details</strong>
                                                <p><b>Plate Number:</b> {{ $bus->plate_no ?: 'Not recorded' }}</p>
                                                <p><b>Model:</b> {{ $bus->bus_model ?: 'Not recorded' }}</p>
                                                <p><b>Master Status:</b> {{ $bus->status }}</p>
                                                <p><b>Current Availability:</b> {{ $readiness }}</p>
                                                <p><b>Next Trip:</b> {{ $bus->next_assignment?->trip_date?->format('M d, Y') ?: 'None scheduled' }}</p>
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="availability-empty">No buses match your filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="availability-footer">
                    <span>Showing {{ $buses->firstItem() ?? 0 }} to {{ $buses->lastItem() ?? 0 }} of {{ $buses->total() }} buses</span>
                    {{ $buses->links() }}
                </div>
            </section>
            <p class="availability-note">A future assignment does not make a bus unavailable now. Availability is determined from recorded master status and trip status, not live GPS position.</p>
        </main>
    </div>
</x-layout.app>
