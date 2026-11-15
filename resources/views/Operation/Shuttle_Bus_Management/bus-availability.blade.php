<x-layout.app
    title="FROMS - Bus Availability"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Shuttle_Bus_Management/bus-master-list.css',
        'resources/css/Operation/Shuttle_Bus_Management/bus-availability.css',
        'resources/js/Main-js/sidebar.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main bus-master-list-page bus-availability-page">
            <x-layout.topbar
                title="Bus Availability"
                subtitle="Operational readiness from current bus and trip records"
            />

            <section data-ajax-region="summary" class="stats-grid availability-stats" aria-label="Fleet status summary">
                <x-ui.summary-card label="Total Buses" value="{{ $totalBuses }}" small="Registered fleet" icon="fa-bus" color="blue" />
                <x-ui.summary-card label="Eligible Active" value="{{ $activeBuses }}" small="Active without open Job Orders" icon="fa-circle-check" color="green" />
                <x-ui.summary-card label="Maintenance Restricted" value="{{ $maintenanceBuses }}" small="Master status or open JO" icon="fa-screwdriver-wrench" color="red" />
                <x-ui.summary-card label="Inactive" value="{{ $inactiveBuses }}" small="Out of service" icon="fa-circle-pause" color="yellow" />
            </section>

            <section data-ajax-region="records" class="table-card availability-panel">
                <div class="section-header">
                    <div>
                        <h2>Bus Readiness</h2>
                        <p>Read-only bus status from Maintenance and trip records.</p>
                    </div>
                </div>

                <x-ui.table-toolbar action="{{ route('bus-availability') }}" search-label="Search Bus Availability" search-placeholder="Search Bus ID, plate, or model..." :filter-count="(int) request()->filled('status') + (int) request()->filled('model')" filter-title="Filter Bus Availability" :sort-options="['bus_no' => 'Bus ID', 'plate_no' => 'Plate Number', 'bus_model' => 'Model', 'status' => 'Status']" default-sort="bus_no">
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Status" name="status"><option value="">All Statuses</option><option value="Active" @selected(request('status') === 'Active')>Active</option><option value="Under Maintenance" @selected(request('status') === 'Under Maintenance')>Under Maintenance</option><option value="Inactive" @selected(request('status') === 'Inactive')>Inactive</option></x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Model" name="model"><option value="">All Models</option>@foreach($models as $model)<option value="{{ $model }}" @selected(request('model') === $model)>{{ $model }}</option>@endforeach</x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>

                <div class="table-wrap availability-table-wrap" style="margin-top: 16px;">
                    <table class="bus-table availability-table">
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
                                @php $readiness = $bus->effective_availability; @endphp
                                <tr>
                                    <td>
                                        <div style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <x-ui.id-badge :value="$bus->bus_no" />
                                            <span style="font-size: 12px; font-weight: 600; color: #475569;">({{ $bus->plate_no ?: 'Plate not recorded' }})</span>
                                        </div>
                                    </td>
                                    <td><strong>{{ $bus->bus_model ?: '—' }}</strong></td>
                                    <td><x-ui.status-badge :status="$readiness" class="availability-status" /></td>
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
                                        <div class="actions">
                                            <x-ui.action-button type="view" class="open-availability-bus"
                                                title="View Bus Details"
                                                aria-label="View details for {{ $bus->plate_no ?: 'bus with no recorded plate' }}"
                                                data-plate="{{ $bus->plate_no ?: 'Not recorded' }}"
                                                data-model="{{ $bus->bus_model ?: 'Not recorded' }}"
                                                data-master-status="{{ $bus->status }}"
                                                data-availability="{{ $readiness }}"
                                                data-next-trip="{{ $bus->next_assignment ? (($bus->next_assignment->trip_date?->format('M d, Y') ?? '') . ' ' . $bus->next_assignment->departure_time) : 'None scheduled' }}"
                                                data-next-route="{{ $bus->next_assignment ? ($bus->next_assignment->route_name_snapshot ?: ($bus->next_assignment->shuttleRoute?->route_name ?? 'Route unavailable')) : '—' }}"
                                            />
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="5"
                                    icon="fa-bus"
                                    message="No Buses Found"
                                    description="No shuttle buses matched your search or filter criteria. Try adjusting the status or model filters."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="table-footer availability-footer">
                    <span>Showing {{ $buses->firstItem() ?? 0 }} to {{ $buses->lastItem() ?? 0 }} of {{ $buses->total() }} buses</span>
                    {{ $buses->links() }}
                </div>
            </section>
            <p class="availability-note">A future assignment does not make a bus unavailable now. Availability reflects recorded bus status, unresolved Maintenance Job Orders and trip status, not live GPS position.</p>
        </main>
    </div>
    {{-- Body-level read-only modal, outside scrollable table to prevent clipping. --}}
    <div id="availabilityBusModal" class="modal-overlay" aria-hidden="true">
        <div class="modal-box wide-modal bus-details-modal availability-view-modal" role="dialog" aria-modal="true" aria-labelledby="availabilityBusTitle" tabindex="-1">
            <div class="bus-details-header">
                <span class="bus-details-icon" aria-hidden="true"><i class="fa-solid fa-bus"></i></span>
                <div class="bus-details-heading">
                    <h2 id="availabilityBusTitle">Bus Availability Details</h2>
                    <p>Operational status and next assignment. Read only.</p>
                </div>
                <button class="close-btn" type="button" data-close-availability-bus aria-label="Close bus details">&times;</button>
            </div>
            <div class="bus-details-body">
                <div class="bus-details-section">
                    <i class="fa-regular fa-clipboard" aria-hidden="true"></i>
                    <div><strong>Bus Information</strong><p>Official identification and recorded operational status</p></div>
                </div>
                <dl class="bus-details-grid">
                    <div><dt>Plate Number (Bus ID)</dt><dd data-availability-detail="plate">—</dd></div>
                    <div><dt>Bus Model</dt><dd data-availability-detail="model">—</dd></div>
                    <div><dt>Master Status</dt><dd data-availability-detail="masterStatus">—</dd></div>
                    <div><dt>Current Availability</dt><dd><span class="bus-details-status" data-availability-detail="availability">—</span></dd></div>
                </dl>
                <div class="bus-details-section">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    <div><strong>Next / Active Trip Assignment</strong><p>Based on trip scheduling records</p></div>
                </div>
                <dl class="bus-details-grid">
                    <div><dt>Scheduled Departure</dt><dd data-availability-detail="nextTrip">—</dd></div>
                    <div><dt>Route</dt><dd data-availability-detail="nextRoute">—</dd></div>
                </dl>
            </div>
            <div class="bus-details-footer">
                <button type="button" class="primary-btn" data-close-availability-bus>Close</button>
            </div>
        </div>
    </div>
</x-layout.app>
