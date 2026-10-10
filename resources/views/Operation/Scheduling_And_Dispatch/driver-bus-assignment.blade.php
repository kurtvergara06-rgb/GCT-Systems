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
    @php
        $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
    @endphp

    <div class="app">
       <x-layout.sidebar department="Operation" />

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
                        <p>Unallocated Drivers</p>
                        <h2>{{ $availableDrivers->total() }}</h2>
                        <small>Present or late and unused on selected date</small>
                    </div>
                </article>

                <article class="assignment-summary-card">
                    <div class="summary-icon purple">
                        <i class="fa-solid fa-bus"></i>
                    </div>
                    <div>
                        <p>Unallocated Buses</p>
                        <h2>{{ $availableBuses->total() }}</h2>
                        <small>Active and unused on selected date</small>
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

                    @if($canEditOperation)
                        <button
                            type="button"
                            class="new-assignment-btn gct-section-new-button"
                            id="openAssignmentModal"
                        >
                            <i class="fa-solid fa-plus"></i>
                            New Assignment
                        </button>
                    @endif
                </div>

                <form
                    method="GET"
                    action="{{ route('driver-bus-assignment', [], false) }}"
                    class="assignment-toolbar"
                    data-server-filter="true"
                    data-server-filter-navigation="true"
                >
                    <div class="assignment-search search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search trip, route, driver, bus..."
                        >
                    </div>

                    <div class="assignment-filter assignment-filter--date">
                        <label>Date</label>
                        <input
                            type="date"
                            name="trip_date"
                            value="{{ $selectedTripDate }}"
                            onchange="this.form.requestSubmit()"
                        >
                    </div>

                    <div class="assignment-filter">
                        <label>Status</label>
                        <select
                            name="status"
                        >
                            <option value="all">All Statuses</option>
                            @foreach(['Ready', 'Assigned', 'Unassigned', 'Dispatched', 'Completed', 'Cancelled', 'Missed'] as $status)
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
                                    $departureDateTime = $trip->departureDateTime();
                                    $arrivalDateTime = $trip->estimatedArrivalDateTime();
                                    $arrivalDisplay = $arrivalDateTime->isSameDay($departureDateTime)
                                        ? $arrivalDateTime->format('g:i A')
                                        : $arrivalDateTime->format('M d · g:i A');
                                    $isHistorical = $trip->hasDeparted();
                                    $hasLinkedHistory = ($trip->daily_driver_reports_count ?? 0) > 0
                                        || ($trip->incidents_count ?? 0) > 0
                                        || ($trip->assignment?->daily_driver_reports_count ?? 0) > 0
                                        || ($trip->assignment?->incidents_count ?? 0) > 0;
                                    $isLocked = $isHistorical || $hasLinkedHistory || in_array(
                                        $trip->status,
                                        ['Cancelled', 'Dispatched', 'Completed'],
                                        true
                                    );
                                    $displayStatus = match (true) {
                                        $trip->status === 'Cancelled' => 'Cancelled',
                                        $isHistorical && $trip->assignment_status === 'Unassigned' => 'Missed',
                                        $trip->assignment_status === 'Unassigned' => 'Unassigned',
                                        default => $trip->status,
                                    };

                                    $details = [
                                        'tripCode' => $trip->trip_code,
                                        'date' => $trip->trip_date?->format('M d, Y'),
                                        'departure' => $departureDateTime->format('g:i A'),
                                        'arrival' => $arrivalDisplay,
                                        'route' => trim(($route?->route_code ?? '') . ' - ' . ($route?->route_name ?? '')),
                                        'driver' => $assignment?->driver_name,
                                        'driverStatus' => $driver?->status,
                                        'bus' => $bus?->bus_no,
                                        'status' => $trip->status,
                                        'assignmentStatus' => $trip->assignment_status,
                                    ];
                                @endphp

                                <tr class="{{ $trip->assignment_status === 'Unassigned' ? 'unassigned-row' : '' }}">
                                    <td><x-ui.id-badge :value="$trip->trip_code" /></td>

                                    <td>
                                        <div class="schedule-cell">
                                            <strong>{{ $departureDateTime->format('g:i A') }}</strong>
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
                                            <x-ui.id-badge :value="$bus->plate_no ?: 'Plate not recorded'" />
                                        @else
                                            <span class="not-assigned">Not Assigned</span>
                                        @endif
                                    </td>

                                    <td>
                                        <span
                                            class="assignment-status {{ strtolower($displayStatus) }}"
                                            @if($displayStatus === 'Missed') title="Departure time has already passed." @endif
                                        >
                                            {{ $displayStatus }}
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

                                            @if($canEditOperation && !$assignment && !$isLocked && $trip->status === 'Scheduled')
                                                <button
                                                    type="button"
                                                    class="assign-now-btn open-assignment"
                                                    data-trip-id="{{ $trip->id }}"
                                                >
                                                    Assign
                                                </button>
                                            @elseif($canEditOperation && $assignment && !$isLocked)
                                                <button
                                                    type="button"
                                                    class="assignment-action edit edit-assignment"
                                                    title="Edit Assignment"
                                                    data-assignment-id="{{ $assignment->id }}"
                                                    data-trip-id="{{ $trip->id }}"
                                                    data-trip-label="{{ $trip->trip_code }} — {{ $trip->trip_date?->format('M d, Y') }}"
                                                    data-availability-url="{{ route('driver-bus-assignment.availability', $trip, false) }}"
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
                                                    <input type="hidden" name="return_trip_date" value="{{ $selectedTripDate }}">
                                                    <input type="hidden" name="return_search" value="{{ request('search') }}">
                                                    <input type="hidden" name="return_status" value="{{ request('status', 'all') }}">
                                                    <input type="hidden" name="trip_page" value="{{ request('trip_page') }}">
                                                    <input type="hidden" name="driver_page" value="{{ request('driver_page') }}">
                                                    <input type="hidden" name="bus_page" value="{{ request('bus_page') }}">

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
                                            @elseif($canEditOperation && !$assignment)
                                                <button
                                                    type="button"
                                                    class="assign-now-btn assignment-action-disabled"
                                                    disabled
                                                    aria-disabled="true"
                                                    title="{{ $hasLinkedHistory ? 'Unavailable: operational history is linked to this trip' : 'Unavailable for this trip status or departure time' }}"
                                                >
                                                    Assign
                                                </button>
                                            @elseif($canEditOperation && $assignment)
                                                <button
                                                    type="button"
                                                    class="assignment-action edit assignment-action-disabled"
                                                    disabled
                                                    aria-disabled="true"
                                                    title="{{ $hasLinkedHistory ? 'Unavailable: operational history is linked to this assignment' : 'Unavailable for this trip status or departure time' }}"
                                                >
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="assignment-action remove assignment-action-disabled"
                                                    disabled
                                                    aria-disabled="true"
                                                    title="{{ $hasLinkedHistory ? 'Unavailable: operational history is linked to this assignment' : 'Unavailable for this trip status or departure time' }}"
                                                >
                                                    <i class="fa-solid fa-link-slash"></i>
                                                </button>
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

            <section data-ajax-region="resources" class="resource-grid">
                <article class="resource-card">
                    <div class="resource-card-header">
                        <div class="resource-title-group">
                            <span class="resource-header-icon workforce"><i class="fa-solid fa-users"></i></span>
                            <div><span>Workforce</span><h2>Unallocated Drivers</h2></div>
                        </div>
                        <strong class="resource-total resource-total--available">{{ $availableDrivers->total() }} Available</strong>
                    </div>

                    <div class="resource-list-head" aria-hidden="true"><span>Driver</span><span>Shift</span><span>Status</span></div>
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
                                <span class="resource-mobile-detail">{{ $driver->shift }} Shift</span>
                            </div>
                            <span class="resource-row-detail">{{ $driver->shift }} Shift</span>

                            <span class="availability available">
                                {{ $driver->status }}
                            </span>
                        </div>
                    @empty
                        <p class="resource-empty">No unallocated, eligible drivers for the selected date.</p>
                    @endforelse

                    <div class="resource-pagination">
                        <x-ui.table-footer :items="$availableDrivers" />
                    </div>
                </article>

                <article class="resource-card">
                    <div class="resource-card-header">
                        <div class="resource-title-group">
                            <span class="resource-header-icon fleet"><i class="fa-solid fa-bus"></i></span>
                            <div><span>Fleet</span><h2>Unallocated Buses</h2></div>
                        </div>
                        <strong class="resource-total resource-total--available">{{ $availableBuses->total() }} Available</strong>
                    </div>

                    <div class="resource-list-head" aria-hidden="true"><span>Bus</span><span>Model</span><span>Status</span></div>
                    @forelse($availableBuses as $bus)
                        <div class="resource-record">
                            <div class="bus-resource-icon">
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="resource-record-info">
                                <strong>{{ $bus->plate_no ?: 'Plate not recorded' }}</strong>
                                <span class="resource-mobile-detail">{{ $bus->bus_model ?: 'Operational bus' }}</span>
                            </div>
                            <span class="resource-row-detail">{{ $bus->bus_model ?: 'Operational bus' }}</span>

                            <span class="availability available">Active</span>
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
            data-driver-id="{{ old('driver_attendance_id') }}"
            data-bus-id="{{ old('bus_id') }}"
            data-method="{{ old('_method') }}"
        ></span>

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
                            <option
                                value="{{ $trip->id }}"
                                data-availability-url="{{ route('driver-bus-assignment.availability', $trip, false) }}"
                            >
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
