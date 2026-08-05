<x-layout.app
    title="FROMS - Driver & Bus Assignment"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Scheduling_And_Dispatch/driver-bus-assignment.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Scheduling_And_Dispatch/driver-bus-assignment.js',
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

        <main class="main assignment-page">
            <x-layout.topbar
                title="Driver & Bus Assignment"
                subtitle="Assign available drivers and active buses to scheduled trips"
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

            <section class="assignment-summary-grid">
                <article class="assignment-summary-card">
                    <div class="summary-icon blue">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <p>Scheduled Trips</p>
                        <h2>{{ $scheduledTripsToday }}</h2>
                        <small>Trips for today</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon green">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                    <div>
                        <p>Available Drivers</p>
                        <h2>{{ $availableDrivers->total() }}</h2>
                        <small>Present or late today</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon purple">
                        <i class="fa-solid fa-bus"></i>
                    </div>
                    <div>
                        <p>Available Buses</p>
                        <h2>{{ $availableBuses->total() }}</h2>
                        <small>Active vehicles</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon yellow">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </div>
                    <div>
                        <p>Unassigned Trips</p>
                        <h2>{{ $pendingAssignments }}</h2>
                        <small>Need assignment today</small>
                    </div>
                </article>
            </section>

            <section class="assignment-card">
                <div class="assignment-card-header">
                    <div>
                        <h2>Trip Assignments</h2>
                        <p>Manage the driver and bus assigned to each trip schedule.</p>
                    </div>

                    <button
                        type="button"
                        class="new-assignment-btn"
                        id="openAssignmentModal"
                    >
                        <i class="fa-solid fa-plus"></i>
                        New Assignment
                    </button>
                </div>

                <form
                    method="GET"
                    action="{{ route('driver-bus-assignment', [], false) }}"
                    class="assignment-toolbar"
                >
                    <div class="assignment-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search trip, route, driver, bus..."
                        >
                    </div>

                    <div class="assignment-filter">
                        <label>Date</label>
                        <input
                            type="date"
                            name="trip_date"
                            value="{{ request('trip_date') }}"
                            onchange="this.form.requestSubmit()"
                        >
                    </div>

                    <div class="assignment-filter">
                        <label>Status</label>
                        <select
                            name="status"
                            onchange="this.form.requestSubmit()"
                        >
                            <option value="all">All Statuses</option>
                            @foreach(['Ready', 'Assigned', 'Unassigned', 'Dispatched', 'Completed'] as $status)
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

                <div class="assignment-table-wrap">
                    <table class="assignment-table">
                        <thead>
                            <tr>
                                <th>Trip ID</th>
                                <th>Schedule</th>
                                <th>Route</th>
                                <th>Driver</th>
                                <th>Bus</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($trips as $trip)
                                @php
                                    $route = $trip->shuttleRoute;
                                    $assignment = $trip->assignment;
                                    $driver = $assignment?->driverAttendance;
                                    $bus = $assignment?->bus;
                                    $isLocked = in_array(
                                        $trip->status,
                                        ['Dispatched', 'Completed'],
                                        true
                                    );

                                    $details = [
                                        'tripCode' => $trip->trip_code,
                                        'date' => $trip->trip_date?->format('M d, Y'),
                                        'departure' => \Carbon\Carbon::parse($trip->departure_time)->format('g:i A'),
                                        'arrival' => \Carbon\Carbon::parse($trip->estimated_arrival_time)->format('g:i A'),
                                        'route' => trim(($route?->route_code ?? '') . ' - ' . ($route?->route_name ?? '')),
                                        'driver' => $assignment?->driver_name,
                                        'driverStatus' => $driver?->status,
                                        'bus' => $bus?->bus_no,
                                        'status' => $trip->status,
                                        'assignmentStatus' => $trip->assignment_status,
                                    ];
                                @endphp

                                <tr class="{{ $trip->assignment_status === 'Unassigned' ? 'unassigned-row' : '' }}">
                                    <td>{{ $trip->trip_code }}</td>

                                    <td>
                                        <div class="schedule-cell">
                                            <strong>{{ \Carbon\Carbon::parse($trip->departure_time)->format('g:i A') }}</strong>
                                            <span>{{ $trip->trip_date?->format('M d, Y') }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="route-cell">
                                            <strong>{{ $route?->route_code ?? '—' }}</strong>
                                            <span>{{ $route?->route_name ?? 'Deleted route' }}</span>
                                        </div>
                                    </td>

                                    <td>
                                        @if($assignment)
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
                                                    <span>{{ $driver?->status ?? 'Recorded' }}</span>
                                                </div>
                                            </div>
                                        @else
                                            <span class="not-assigned">Not Assigned</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if($bus)
                                            <span class="bus-badge">{{ $bus->bus_no }}</span>
                                        @else
                                            <span class="not-assigned">Not Assigned</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span class="assignment-status {{ strtolower($trip->assignment_status === 'Unassigned' ? 'unassigned' : $trip->status) }}">
                                            {{ $trip->assignment_status === 'Unassigned' ? 'Unassigned' : $trip->status }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="assignment-actions">
                                            <button
                                                type="button"
                                                class="assignment-action view view-assignment"
                                                title="View"
                                                data-details='@json($details)'
                                            >
                                                <i class="fa-solid fa-eye"></i>
                                            </button>

                                            @if(!$assignment)
                                                <button
                                                    type="button"
                                                    class="assign-now-btn open-assignment"
                                                    data-trip-id="{{ $trip->id }}"
                                                >
                                                    Assign
                                                </button>
                                            @elseif(!$isLocked)
                                                <button
                                                    type="button"
                                                    class="assignment-action edit edit-assignment"
                                                    title="Edit Assignment"
                                                    data-assignment-id="{{ $assignment->id }}"
                                                    data-trip-id="{{ $trip->id }}"
                                                    data-driver-id="{{ $assignment->driver_attendance_id }}"
                                                    data-bus-id="{{ $assignment->bus_id }}"
                                                    data-update-url="{{ route('driver-bus-assignment.update', $assignment->id, false) }}"
                                                >
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <form
                                                    id="removeAssignmentForm-{{ $assignment->id }}"
                                                    method="POST"
                                                    action="{{ route('driver-bus-assignment.destroy', $assignment->id, false) }}"
                                                >
                                                    @csrf
                                                    @method('DELETE')

                                                    <button
                                                        type="button"
                                                        class="assignment-action remove remove-assignment"
                                                        title="Remove Assignment"
                                                        data-form-id="removeAssignmentForm-{{ $assignment->id }}"
                                                        data-trip-code="{{ $trip->trip_code }}"
                                                    >
                                                        <i class="fa-solid fa-link-slash"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="7"
                                    message="No trip schedules found."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$trips" />
            </section>

            <section class="resource-grid">
                <article class="resource-card">
                    <div class="resource-card-header">
                        <div>
                            <span>Workforce</span>
                            <h2>Available Drivers</h2>
                        </div>
                        <strong class="resource-total">{{ $availableDrivers->total() }}</strong>
                    </div>

                    @forelse($availableDrivers as $driver)
                        <div class="resource-record">
                            <div class="driver-avatar">
                                {{ collect(explode(' ', $driver->driver_name))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                    ->implode('') }}
                            </div>

                            <div class="resource-record-info">
                                <strong>{{ $driver->driver_name }}</strong>
                                <span>{{ $driver->shift }} Shift</span>
                            </div>

                            <span class="availability available">
                                {{ $driver->status }}
                            </span>
                        </div>
                    @empty
                        <p class="resource-empty">No available drivers today.</p>
                    @endforelse

                    <div class="resource-pagination">
                        <x-ui.table-footer :items="$availableDrivers" />
                    </div>
                </article>

                <article class="resource-card">
                    <div class="resource-card-header">
                        <div>
                            <span>Fleet</span>
                            <h2>Available Buses</h2>
                        </div>
                        <strong class="resource-total">{{ $availableBuses->total() }}</strong>
                    </div>

                    @forelse($availableBuses as $bus)
                        <div class="resource-record">
                            <div class="bus-resource-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="resource-record-info">
                                <strong>{{ $bus->bus_no }}</strong>
                                <span>{{ $bus->bus_model ?: 'Operational bus' }}</span>
                            </div>

                            <span class="availability available">Available</span>
                        </div>
                    @empty
                        <p class="resource-empty">No active buses available.</p>
                    @endforelse

                    <div class="resource-pagination">
                        <x-ui.table-footer :items="$availableBuses" />
                    </div>
                </article>
            </section>
        </main>
    </div>

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

        <div class="assignment-form-grid">
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
                            <option value="{{ $trip->id }}">
                                {{ $trip->trip_code }}
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
            name="driver_attendance_id"
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

            <div class="assignment-combobox-options">
                @forelse($driverOptions as $driver)
                    @php
                        $driverLabel = $driver->driver_name
                            . ' — '
                            . $driver->shift
                            . ' Shift';
                    @endphp

                    <button
                        type="button"
                        class="assignment-combobox-option assignment-driver-option"
                        data-value="{{ $driver->id }}"
                        data-label="{{ $driverLabel }}"
                        data-search="{{ strtolower(
                            $driver->driver_id
                            . ' '
                            . $driver->driver_name
                            . ' '
                            . $driver->shift
                            . ' '
                            . $driver->status
                        ) }}"
                    >
                        <span>
                            <strong>{{ $driver->driver_name }}</strong>

                            <small>
                                {{ $driver->driver_id }}
                                — {{ $driver->shift }} Shift
                                — {{ $driver->status }}
                            </small>
                        </span>

                        <i class="fa-solid fa-check"></i>
                    </button>
                @empty
                    <p class="assignment-combobox-empty">
                        No available drivers for today.
                    </p>
                @endforelse
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

                        <div class="assignment-combobox-options">
                            @forelse($busOptions as $bus)
                                @php
                                    $busLabel = $bus->bus_no . ($bus->bus_model ? ' — ' . $bus->bus_model : '');
                                @endphp
                                <button
                                    type="button"
                                    class="assignment-combobox-option"
                                    data-value="{{ $bus->id }}"
                                    data-label="{{ $busLabel }}"
                                    data-search="{{ strtolower($bus->bus_no . ' ' . ($bus->bus_model ?? '') . ' ' . ($bus->plate_no ?? '')) }}"
                                >
                                    <span>
                                        <strong>{{ $bus->bus_no }}</strong>
                                        <small>{{ $bus->bus_model ?: 'Operational bus' }}</small>
                                    </span>
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            @empty
                                <p class="assignment-combobox-empty">No active buses available.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                @error('bus_id')
                    <span class="ui-field-error">{{ $message }}</span>
                @enderror
            </div>
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

    <x-ui.action-buttom-modal
        mode="delete"
        id="removeAssignmentModal"
        delete-title="Remove Assignment?"
        delete-message="Remove the driver and bus from"
        name-id="removeAssignmentName"
        cancel-id="cancelRemoveAssignment"
        confirm-id="confirmRemoveAssignment"
    />
</x-layout.app>