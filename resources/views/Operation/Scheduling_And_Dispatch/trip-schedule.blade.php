<x-layout.app
    title="FROMS - Trip Schedule"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Scheduling_And_Dispatch/trip-schedule.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Scheduling_And_Dispatch/trip-schedule.js',
    ]"
>
    <div class="app">
       <x-layout.sidebar
    department="Operation"
    subtitle="Operation Module"
    icon="fa-bus"
    :items="[
        [
            'label' => 'Dashboard',
            'route' => 'dashboard-operation',
            'icon' => 'fa-table-cells-large',
        ],

        [
            'label' => 'Routes',
            'route' => 'operation.routes',
            'icon' => 'fa-route',
        ],

        [
            'label' => 'Scheduling',
            'icon' => 'fa-calendar-days',
            'children' => [
                [
                    'label' => 'Trip Schedule',
                    'route' => 'trip-schedule',
                    'icon' => 'fa-calendar-days',
                ],
                [
                    'label' => 'Driver & Bus Assignment',
                    'route' => 'driver-bus-assignment',
                    'icon' => 'fa-user-tie',
                ],
                [
                    'label' => 'Auto Scheduling',
                    'route' => 'auto-scheduling',
                    'icon' => 'fa-wand-magic-sparkles',
                ],
            ],
        ],

        [
            'label' => 'Personnel Management',
            'icon' => 'fa-address-book',
            'children' => [
                [
                    'label' => 'Driver Master List',
                    'route' => 'operation.personnel.drivers',
                    'icon' => 'fa-id-card',
                ],
                [
                    'label' => 'Mechanic Master List',
                    'route' => 'operation.personnel.mechanics',
                    'icon' => 'fa-users-gear',
                ],
            ],
        ],

        [
            'label' => 'Attendance',
            'icon' => 'fa-calendar-check',
            'children' => [
                [
                    'label' => 'Driver Attendance',
                    'route' => 'driver-attendance',
                    'icon' => 'fa-user-check',
                ],
                [
                    'label' => 'Mechanic Attendance',
                    'route' => 'mechanic-attendance',
                    'icon' => 'fa-clipboard-user',
                ],
            ],
        ],

        [
            'label' => 'Bus Master List',
            'route' => 'bus-master-list',
            'icon' => 'fa-bus',
        ],
    ]"
/>

        <main class="main trip-schedule-page">
            <x-layout.topbar
                title="Trip Schedule"
                subtitle="Create route schedules before assigning a driver and bus"
                notification-count="4"
            />

            @if($errors->any())
                <div class="alert-error">
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="trip-summary-grid">
                <article class="trip-summary-card">
                    <div class="trip-summary-icon blue">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <p>Total Trips Today</p>
                        <h2>{{ $totalTripsToday }}</h2>
                        <small>Scheduled today</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon green">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <p>Assigned Trips</p>
                        <h2>{{ $assignedTrips }}</h2>
                        <small>Driver and bus assigned</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon yellow">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <p>Pending Assignment</p>
                        <h2>{{ $pendingAssignments }}</h2>
                        <small>Awaiting resources</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon purple">
                        <i class="fa-solid fa-route"></i>
                    </div>
                    <div>
                        <p>Active Routes</p>
                        <h2>{{ $activeRoutesUsed }}</h2>
                        <small>Used today</small>
                    </div>
                </article>
            </section>

            <section class="trip-card">
                <div class="trip-card-header">
                    <div>
                        <h2>Trip Records</h2>
                        <p>
                            Create the trip route and schedule first. Driver and bus
                            assignment is handled on the next module.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="new-trip-btn"
                        id="openTripModal"
                    >
                        <i class="fa-solid fa-plus"></i>
                        New Trip
                    </button>
                </div>

                <form
                    method="GET"
                    action="{{ route('trip-schedule', [], false) }}"
                    class="trip-toolbar"
                >
                    <div class="trip-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search trip ID, route, status..."
                        >
                    </div>

                    <div class="trip-filter">
                        <label>Date</label>
                        <input
                            type="date"
                            name="trip_date"
                            value="{{ request('trip_date') }}"
                            onchange="this.form.requestSubmit()"
                        >
                    </div>

                    <div class="trip-filter">
                        <label>Status</label>
                        <select
                            name="status"
                            onchange="this.form.requestSubmit()"
                        >
                            <option value="all">All Statuses</option>
                            @foreach(['Scheduled', 'Ready', 'Dispatched', 'Completed', 'Cancelled'] as $status)
                                <option
                                    value="{{ $status }}"
                                    @selected(request('status') === $status)
                                >
                                    {{ $status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="trip-table-wrap">
                    <table class="trip-table">
                        <thead>
                            <tr>
                                <th>Trip ID</th>
                                <th>Date</th>
                                <th>Route</th>
                                <th>Departure</th>
                                <th>ETA</th>
                                <th>Shift</th>
                                <th>Assignment</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($trips as $trip)
                                @php
                                    $route = $trip->shuttleRoute;
                                    $canEdit = !in_array($trip->status, ['Dispatched', 'Completed'], true);
                                    $canDelete = in_array($trip->status, ['Scheduled', 'Cancelled'], true)
                                        && $trip->assignment_status === 'Unassigned';

                                    $viewTripData = [
                                        'tripCode' => $trip->trip_code,
                                        'date' => $trip->trip_date?->format('M d, Y'),
                                        'routeCode' => $route?->route_code,
                                        'routeName' => $route?->route_name,
                                        'origin' => $route?->origin,
                                        'destination' => $route?->destination,
                                        'departure' => \Carbon\Carbon::parse($trip->departure_time)->format('g:i A'),
                                        'arrival' => \Carbon\Carbon::parse($trip->estimated_arrival_time)->format('g:i A'),
                                        'shift' => $trip->shift,
                                        'assignment' => $trip->assignment_status,
                                        'status' => $trip->status,
                                        'notes' => $trip->notes,
                                    ];
                                @endphp

                                <tr class="{{ $trip->assignment_status === 'Unassigned' ? 'pending-row' : '' }}">
                                    <td>{{ $trip->trip_code }}</td>
                                    <td>{{ $trip->trip_date?->format('M d, Y') }}</td>
                                    <td>
                                        <div class="route-cell">
                                            <strong>{{ $route?->route_code ?? '—' }}</strong>
                                            <span>{{ $route?->route_name ?? 'Deleted route' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($trip->estimated_arrival_time)->format('g:i A') }}</td>
                                    <td>
                                        <span class="shift-badge {{ strtolower($trip->shift) }}">
                                            {{ $trip->shift }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($trip->assignment_status === 'Unassigned')
                                            <a
                                                href="{{ route('driver-bus-assignment', [], false) }}"
                                                class="assignment-badge unassigned"
                                            >
                                                Unassigned
                                            </a>
                                        @else
                                            <span class="assignment-badge assigned">
                                                Assigned
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="trip-status {{ strtolower($trip->status) }}">
                                            {{ $trip->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="trip-actions">
                                            <button
                                                type="button"
                                                class="trip-action view view-trip"
                                                title="View"
                                                data-trip='@json($viewTripData)'
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            @if($canEdit)
                                                <button
                                                    type="button"
                                                    class="trip-action edit edit-trip"
                                                    title="Edit"
                                                    data-id="{{ $trip->id }}"
                                                    data-trip-code="{{ $trip->trip_code }}"
                                                    data-trip-date="{{ $trip->trip_date?->format('Y-m-d') }}"
                                                    data-route-id="{{ $trip->shuttle_route_id }}"
                                                    data-departure-time="{{ \Carbon\Carbon::parse($trip->departure_time)->format('H:i') }}"
                                                    data-arrival-time="{{ \Carbon\Carbon::parse($trip->estimated_arrival_time)->format('H:i') }}"
                                                    data-status="{{ $trip->status }}"
                                                    data-notes="{{ $trip->notes }}"
                                                    data-update-url="{{ route('trip-schedule.update', $trip->id, false) }}"
                                                >
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                            @endif

                                            @if($canDelete)
                                                <form
                                                    id="deleteTripForm-{{ $trip->id }}"
                                                    method="POST"
                                                    action="{{ route('trip-schedule.destroy', $trip->id, false) }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="button"
                                                        class="trip-action delete delete-trip"
                                                        title="Delete"
                                                        data-form-id="deleteTripForm-{{ $trip->id }}"
                                                        data-trip-code="{{ $trip->trip_code }}"
                                                    >
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="9"
                                    message="No trip schedules found."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$trips" />
            </section>
        </main>
    </div>

    <x-ui.form-modal
        id="tripModal"
        title="New Trip"
        title-id="tripModalTitle"
        description="Create a shuttle trip using an active route."
        icon="fa-calendar-plus"
        size="large"
        form-id="tripForm"
        :action="route('trip-schedule.store', [], false)"
        method="POST"
        submit-text="Save Trip"
        submit-text-id="tripSubmitText"
        submit-icon="fa-floppy-disk"
        cancel-text="Cancel"
        cancel-id="cancelTripModal"
        close-id="closeTripModal"
    >
        <input type="hidden" name="_method" id="tripFormMethod" value="PUT" disabled>

        <div class="ui-form-grid trip-ui-form-grid">
            <x-ui.form-field label="Trip ID" name="trip_code_display" id="tripCode" value="Auto-generated" icon="fa-hashtag" :readonly="true" />
            <x-ui.form-field label="Trip Date" name="trip_date" id="tripDate" type="date" :value="old('trip_date', now()->format('Y-m-d'))" icon="fa-calendar-day" :required="true" />

            <div class="ui-form-group ui-form-full">
                <label for="tripRoute">Route <span class="ui-required">*</span></label>
                <div class="ui-input-wrap has-icon">
                    <span class="ui-input-icon"><i class="fa-solid fa-route"></i></span>
                    <select name="shuttle_route_id" id="tripRoute" required>
                        <option value="">Select active route</option>
                        @foreach($activeRoutes as $route)
                            <option value="{{ $route->id }}" data-duration="{{ $route->estimated_time_minutes ?: 60 }}" @selected((string) old('shuttle_route_id') === (string) $route->id)>
                                {{ $route->route_code }} - {{ $route->route_name }} ({{ $route->origin }} to {{ $route->destination }})
                            </option>
                        @endforeach
                    </select>
                </div>
                @error('shuttle_route_id')<span class="ui-field-error">{{ $message }}</span>@enderror
            </div>

            <x-ui.form-field label="Departure Time" name="departure_time" id="departureTime" type="time" :value="old('departure_time')" icon="fa-clock" :required="true" />
            <x-ui.form-field label="Estimated Arrival" name="estimated_arrival_time" id="arrivalTime" type="time" :value="old('estimated_arrival_time')" icon="fa-clock-rotate-left" />
            <x-ui.form-field label="Shift" name="shift_display" id="tripShift" value="Automatic" icon="fa-business-time" :readonly="true" />
            <x-ui.form-select label="Status" name="status" id="tripStatus" :options="['Scheduled' => 'Scheduled', 'Cancelled' => 'Cancelled']" selected="Scheduled" icon="fa-circle-check" :required="true" />

            <div class="ui-form-group ui-form-full">
                <label for="tripNotes">Notes</label>
                <div class="ui-input-wrap trip-textarea-wrap">
                    <textarea name="notes" id="tripNotes" rows="4" placeholder="Optional trip remarks...">{{ old('notes') }}</textarea>
                </div>
                @error('notes')<span class="ui-field-error">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="trip-form-note">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Driver and bus assignment is handled separately.</strong>
                <span>After creating the trip, assign resources through Driver & Bus Assignment or Auto Scheduling.</span>
            </div>
        </div>
    </x-ui.form-modal>

    <x-ui.form-modal
        id="viewTripModal"
        title="Trip Details"
        description="Complete schedule information."
        icon="fa-calendar-check"
        size="large"
        form-id="viewTripForm"
        action="#"
        method="POST"
        :show-actions="false"
        close-id="closeViewTripModal"
    >
        <div class="trip-details-grid" id="viewTripContent"></div>
        <div class="ui-form-actions">
            <button type="button" id="closeViewTripButton" class="ui-form-btn ui-form-btn-primary">
                <i class="fa-solid fa-check"></i><span>Close</span>
            </button>
        </div>
    </x-ui.form-modal>

    <x-ui.action-buttom-modal
        mode="delete"
        id="deleteTripModal"
        delete-title="Delete Trip Schedule?"
        delete-message="Are you sure you want to delete"
        name-id="deleteTripName"
        cancel-id="cancelDeleteTrip"
        confirm-id="confirmDeleteTrip"
    />
</x-layout.app>