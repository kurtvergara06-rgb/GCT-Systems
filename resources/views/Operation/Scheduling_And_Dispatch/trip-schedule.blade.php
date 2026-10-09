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
    @php
        $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
        $tripValidationMode = session('trip_validation_mode');
        $tripValidationId = session('trip_validation_id');
        $tripValidationCode = session('trip_validation_code');
    @endphp

    <div class="app">
       <x-layout.sidebar department="Operation" />

        <main class="main trip-schedule-page">
            <x-layout.topbar
                title="Trip Schedule"
                subtitle="Create scheduled trips before assigning drivers and buses"
                notification-count="4"
            />

            <section data-ajax-region="summary" class="trip-summary-grid">
                <article class="trip-summary-card">
                    <div class="trip-summary-icon blue">
                        <i class="fa-solid fa-calendar-days"></i>
                    </div>
                    <div>
                        <p>Total Trips Today</p>
                        <h2>{{ $totalTripsToday }}</h2>
                        <small>Active trips today</small>
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
                        <small>Scheduled and awaiting resources</small>
                    </div>
                </article>

                <article class="trip-summary-card">
                    <div class="trip-summary-icon purple">
                        <i class="fa-solid fa-route"></i>
                    </div>
                    <div>
                        <p>Active Routes</p>
                        <h2>{{ $activeRoutesUsed }}</h2>
                        <small>Used by active trips today</small>
                    </div>
                </article>
            </section>

            <section data-ajax-region="records" class="trip-card">
                <div class="trip-card-header">
                    <div>
                        <h2>Trip Records</h2>
                        <p>
                            Create and manage scheduled trips. Driver and bus assignment
                            is handled separately.
                        </p>
                    </div>
                    @if($canEditOperation)
                        <button
                            type="button"
                            class="new-trip-btn gct-section-new-button"
                            id="openTripModal"
                        >
                            <i class="fa-solid fa-plus"></i>
                            <span>New Trip</span>
                        </button>
                    @endif
                </div>

                <form
                    method="GET"
                    action="{{ route('trip-schedule', [], false) }}"
                    class="toolbar trip-toolbar"
                    data-server-filter="true"
                >
                    <div class="trip-search search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search trip ID, route, status..."
                            autocomplete="off"
                        >
                    </div>

                    <div class="trip-filter">
                        <input
                            type="date"
                            name="trip_date"
                            value="{{ $selectedTripDate }}"
                            onchange="this.form.requestSubmit()"
                            aria-label="Date"
                        >
                    </div>

                    <div class="trip-filter">
                        <select
                            name="status"
                            aria-label="Status"
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
                        <colgroup>
                            <col class="trip-col-id">
                            <col class="trip-col-date">
                            <col class="trip-col-route">
                            <col class="trip-col-departure">
                            <col class="trip-col-eta">
                            <col class="trip-col-shift">
                            <col class="trip-col-assignment">
                            <col class="trip-col-status">
                            <col class="trip-col-actions">
                        </colgroup>
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
                                    $departureDateTime = $trip->departureDateTime();
                                    $arrivalDateTime = $trip->estimatedArrivalDateTime();
                                    $isHistorical = $trip->hasDeparted();
                                    $hasAssignment = $trip->assignment !== null
                                        || $trip->assignment_status !== 'Unassigned';
                                    $displayAssignmentStatus = $hasAssignment
                                        ? 'Assigned'
                                        : 'Unassigned';
                                    $canManageSchedule = $trip->canBeManagedFromSchedule();

                                    $canEdit = $canEditOperation
                                        && $canManageSchedule;

                                    $canDelete = $canEdit;

                                    $arrivalDisplay = $arrivalDateTime->isSameDay($departureDateTime)
                                        ? $arrivalDateTime->format('g:i A')
                                        : $arrivalDateTime->format('M d · g:i A');

                                    $viewTripData = [
                                        'tripCode' => $trip->trip_code,
                                        'date' => $trip->trip_date?->format('M d, Y'),
                                        'routeCode' => $route?->route_code,
                                        'routeName' => $route?->route_name,
                                        'origin' => $route?->origin,
                                        'destination' => $route?->destination,
                                        'departure' => $departureDateTime->format('g:i A'),
                                        'arrival' => $arrivalDisplay,
                                        'shift' => $trip->shift,
                                        'assignment' => $displayAssignmentStatus,
                                        'status' => $trip->status,
                                        'notes' => $trip->notes,
                                    ];
                                @endphp

                                <tr class="{{ ! $hasAssignment && $trip->status === 'Scheduled' ? 'pending-row' : '' }}">
                                    <td><x-ui.id-badge :value="$trip->trip_code" /></td>
                                    <td>{{ $trip->trip_date?->format('M d, Y') }}</td>
                                    <td>
                                        <div class="route-cell">
                                            <strong>{{ $route?->route_code ?? '—' }}</strong>
                                            <span>{{ $route?->route_name ?? 'Deleted route' }}</span>
                                        </div>
                                    </td>
                                    <td>{{ $departureDateTime->format('g:i A') }}</td>
                                    <td>{{ $arrivalDisplay }}</td>
                                    <td>
                                        <span class="shift-badge {{ strtolower($trip->shift) }}">
                                            {{ $trip->shift }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(! $hasAssignment)
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
                                                    data-departure-time="{{ $departureDateTime->format('H:i') }}"
                                                    data-arrival-time="{{ $arrivalDateTime->format('H:i') }}"
                                                    data-status="{{ $trip->status }}"
                                                    data-notes="{{ $trip->notes }}"
                                                    data-update-url="{{ route('trip-schedule.update', $trip->id, false) }}"
                                                    data-allow-past="{{ $isHistorical ? 'true' : 'false' }}"
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
<input type="hidden" name="return_trip_date" value="{{ $selectedTripDate }}">
                                                    <input type="hidden" name="return_search" value="{{ request('search') }}">
                                                    <input type="hidden" name="return_status" value="{{ request('status', 'all') }}">

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

    @if($errors->any() && in_array($tripValidationMode, ['create', 'edit'], true))
        @php
            $tripValidationRecoveryPayload = [
                'mode' => $tripValidationMode,
                'tripId' => $tripValidationId,
                'tripCode' => $tripValidationCode,
                'updateUrl' => $tripValidationId
                    ? route('trip-schedule.update', $tripValidationId, false)
                    : null,
            ];
        @endphp
        <script type="application/json" id="tripValidationRecovery">{!! json_encode(
            $tripValidationRecoveryPayload,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) !!}</script>
    @endif

    @if($canEditOperation)
        <x-ui.form-modal
            id="tripModal"
            title="New Trip"
            title-id="tripModalTitle"
            description="Set the route, trip date, departure time, and schedule status."
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
            <input type="hidden" name="return_trip_date" value="{{ $selectedTripDate }}">
            <input type="hidden" name="return_search" value="{{ request('search') }}">
            <input type="hidden" name="return_status" value="{{ request('status', 'all') }}">

            <div class="trip-editor-intro">
                <div class="trip-editor-intro-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>
                <div>
                    <strong>Schedule Information</strong>
                    <span>Choose the active route, trip date, and departure time. Arrival and shift are calculated automatically.</span>
                </div>
            </div>

            <div class="ui-form-grid trip-ui-form-grid trip-editor-grid">
                <x-ui.form-field label="Trip ID" name="trip_code_display" id="tripCode" value="Auto-generated" icon="fa-hashtag" :readonly="true" />
                <x-ui.form-field
                    label="Trip Date"
                    name="trip_date"
                    id="tripDate"
                    type="date"
                    :value="old('trip_date', now(config('app.business_timezone', 'Asia/Manila'))->format('Y-m-d'))"
                    :min="now(config('app.business_timezone', 'Asia/Manila'))->format('Y-m-d')"
                    icon="fa-calendar-day"
                    :required="true"
                />

                <div class="ui-form-group ui-form-full">
                    <label for="tripRoute">Route <span class="ui-required">*</span></label>
                    <div class="ui-input-wrap has-icon">
                        <span class="ui-input-icon"><i class="fa-solid fa-route"></i></span>
                        <select name="shuttle_route_id" id="tripRoute" required>
                            <option value="">Select active route</option>
                            @foreach($activeRoutes as $route)
                                <option value="{{ $route->id }}" data-duration="{{ $route->calculated_time_minutes ?: $route->estimated_time_minutes ?: 60 }}" @selected((string) old('shuttle_route_id') === (string) $route->id)>
                                    {{ $route->route_code }} - {{ $route->route_name }} ({{ $route->origin }} to {{ $route->destination }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('shuttle_route_id')<span class="ui-field-error">{{ $message }}</span>@enderror
                </div>

                <x-ui.form-field label="Departure Time" name="departure_time" id="departureTime" type="time" :value="old('departure_time')" icon="fa-clock" :required="true" />
                <x-ui.form-field label="Estimated Arrival (Auto)" name="estimated_arrival_time_display" id="arrivalTime" type="time" :value="old('estimated_arrival_time_display')" icon="fa-clock-rotate-left" :readonly="true" />
                <x-ui.form-field label="Shift" name="shift_display" id="tripShift" value="Automatic" icon="fa-business-time" :readonly="true" />
                <x-ui.form-select
                    label="Status"
                    name="status"
                    id="tripStatus"
                    :options="['Scheduled' => 'Scheduled', 'Cancelled' => 'Cancelled']"
                    :selected="old('status', 'Scheduled')"
                    icon="fa-circle-check"
                    :required="true"
                />

                <div class="ui-form-group ui-form-full trip-notes-group">
                    <label for="tripNotes">Notes</label>
                    <div class="ui-input-wrap trip-textarea-wrap">
                        <span class="trip-textarea-icon">
                            <i class="fa-solid fa-pen"></i>
                        </span>
                        <textarea name="notes" id="tripNotes" rows="4" maxlength="2000" placeholder="Optional trip remarks...">{{ old('notes') }}</textarea>
                    </div>
                    <div class="trip-notes-meta">
                        <span>Optional operational remarks</span>
                        <span id="tripNotesCount">0 / 2000</span>
                    </div>
                    @error('notes')<span class="ui-field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="trip-form-note">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Keep at least 15 minutes between active departures for the same route.</strong>
                    <span>Cancelled trips no longer occupy the departure slot. Driver and bus assignment is handled separately afterward.</span>
                </div>
            </div>
        </x-ui.form-modal>
    @endif

    <x-ui.form-modal
        id="viewTripModal"
        title="Trip Details"
        description="Route, timing, assignment, and schedule information."
        icon="fa-calendar-check"
        size="large"
        form-id="viewTripForm"
        action="#"
        method="POST"
        :show-actions="false"
        close-id="closeViewTripModal"
    >
        <div class="trip-details-hero">
            <div class="trip-details-hero-icon">
                <i class="fa-solid fa-route"></i>
            </div>
            <div>
                <strong id="viewTripHeroTitle">Scheduled Trip</strong>
                <span>Route, timing, assignment, and schedule status.</span>
            </div>
        </div>

        <div class="trip-details-grid" id="viewTripContent"></div>
        <div class="ui-form-actions">
            <button type="button" id="closeViewTripButton" class="ui-form-btn ui-form-btn-primary">
                <i class="fa-solid fa-check"></i><span>Close</span>
            </button>
        </div>
    </x-ui.form-modal>

    @if($canEditOperation)
        <x-ui.action-buttom-modal
            mode="delete"
            id="deleteTripModal"
            delete-title="Delete Trip Schedule?"
            delete-message="Are you sure you want to delete"
            name-id="deleteTripName"
            cancel-id="cancelDeleteTrip"
            confirm-id="confirmDeleteTrip"
        />
    @endif
</x-layout.app>
