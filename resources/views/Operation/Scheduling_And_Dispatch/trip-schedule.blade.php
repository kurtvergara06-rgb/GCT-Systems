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

            <section data-ajax-region="summary" class="trip-summary-grid service-summary-grid">
                <x-ui.summary-card
                    label="Total Scheduled Trips"
                    :value="$scheduledServices"
                    small="Services on selected date"
                    icon="fa-bus"
                    color="blue"
                />
                <x-ui.summary-card
                    label="Incoming Trips"
                    :value="$incomingServices"
                    small="Employees going to work"
                    icon="fa-arrow-down"
                    color="green"
                />
                <x-ui.summary-card
                    label="Outgoing Trips"
                    :value="$outgoingServices"
                    small="Employees going home"
                    icon="fa-arrow-up"
                    color="yellow"
                />
                <x-ui.summary-card
                    label="Total Buses Required"
                    :value="$totalBusesRequired"
                    small="Planned units across services"
                    icon="fa-bus-simple"
                    color="purple"
                />
            </section>

            <section data-ajax-region="records" class="service-schedule-records">
                <div class="service-controls-card">
                    <x-ui.section-header
                        title="Trip Schedules"
                        subtitle="Create and manage incoming and outgoing employee transportation services."
                        icon="fa-calendar-check"
                        class="service-schedule-toolbar"
                    >
                        <x-slot:actions>
                            @if($canEditOperation && request('tab') !== 'history')
                                <button type="button" class="new-trip-btn gct-section-new-button" id="openTripModal">
                                    <i class="fa-solid fa-plus"></i>
                                    <span>Create Schedule</span>
                                </button>
                            @endif
                        </x-slot:actions>
                    </x-ui.section-header>

                    <div class="trip-record-controls">
                        <nav class="trip-record-tabs" aria-label="Trip schedule record groups">
                            <a class="trip-record-tab {{ request('tab', 'active') !== 'history' ? 'is-active' : '' }}" href="{{ route('trip-schedule', array_merge(request()->except(['tab', 'status', 'page']), ['tab' => 'active'])) }}"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Active Trips</a>
                            <a class="trip-record-tab {{ request('tab') === 'history' ? 'is-active' : '' }}" href="{{ route('trip-schedule', array_merge(request()->except(['tab', 'status', 'page', 'trip_date']), ['tab' => 'history'])) }}"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Schedule History</a>
                        </nav>
                    <x-ui.table-toolbar action="{{ route('trip-schedule', [], false) }}" data-server-filter="true" search-label="Search Trip Schedules" search-placeholder="Search trip, route, or status..." :filter-count="(int) request()->filled('trip_date') + (int) (request()->filled('direction') && request('direction') !== 'all') + (int) (request()->filled('route') && request('route') !== 'all') + (int) (request()->filled('status') && request('status') !== 'all')" filter-title="Filter Trip Schedules" :show-filter-count="false" filter-view="Operation.Scheduling_And_Dispatch.partials.trip-schedule-filters" :filter-data="compact('selectedTripDate', 'activeRoutes')" :sort-options="['departure_time' => 'Departure Time', 'trip_code' => 'Trip ID', 'trip_date' => 'Trip Date', 'required_buses' => 'Required Buses', 'status' => 'Status']" default-sort="departure_time">
                        <x-slot:hidden><input type="hidden" name="tab" value="{{ request('tab') === 'history' ? 'history' : 'active' }}"></x-slot:hidden>
                    </x-ui.table-toolbar>
                    </div>
                    @if(request('tab') === 'history')
                        <p class="trip-schedule-history-note" style="margin: 10px 0 0; color: #64748b; font-size: 12px;">
                            Read-only scheduling audit: planned time, route, required buses, and final status.
                            For actual drivers, buses, recorded distance, and execution details,
                            <a href="{{ route('trip-records') }}">open Trip Records</a>.
                            @unless(request()->filled('trip_date')) Showing all historical dates. @endunless
                        </p>
                    @endif
                </div>

                <div class="service-panels-surface">
                @foreach(['Incoming', 'Outgoing'] as $serviceDirection)
                    @php
                        $directionTrips = $trips->filter(fn ($item) => $item->direction === $serviceDirection);
                    @endphp
                    <div class="service-direction-panel {{ strtolower($serviceDirection) }}">
                        <header class="service-direction-header">
                            <div class="service-direction-heading">
                                <span class="service-direction-icon">
                                    <i class="fa-solid {{ $serviceDirection === 'Incoming' ? 'fa-arrow-down' : ($serviceDirection === 'Outgoing' ? 'fa-arrow-up' : 'fa-circle-question') }}"></i>
                                </span>
                                <div>
                                    <h3>{{ $serviceDirection === 'Unspecified' ? 'Unclassified Trips' : $serviceDirection.' Trips' }}</h3>
                                    <p>{{ $serviceDirection === 'Incoming' ? 'Buses transporting employees to work.' : ($serviceDirection === 'Outgoing' ? 'Buses transporting employees home.' : 'Existing trips that need an Incoming or Outgoing direction.') }}</p>
                                </div>
                            </div>
                            <div class="service-direction-metrics">
                                <div class="service-metric-stat">
                                    <span>Total Buses</span>
                                    <strong>{{ $directionTrips->where('status', '!=', 'Cancelled')->sum(fn ($item) => $item->required_buses ?? 1) }}</strong>
                                </div>
                                <div class="service-metric-stat">
                                    <span>Reserve Deployed</span>
                                    <strong>{{ $directionTrips->sum(fn ($item) => $reserveDeployedByTrip->get($item->id, 0)) }}</strong>
                                </div>
                                <div class="service-metric-chips">
                                    <span class="service-chip planned">
                                        <i class="fa-solid fa-calendar-check"></i> Scheduled {{ $directionTrips->where('status', 'Scheduled')->count() }}
                                    </span>
                                    <span class="service-chip modified">
                                        <i class="fa-solid fa-user-check"></i> Assigned {{ $directionTrips->filter(fn ($item) => $item->assignment !== null)->count() }}
                                    </span>
                                    <span class="service-chip cancelled">
                                        <i class="fa-solid fa-ban"></i> Cancelled {{ $directionTrips->where('status', 'Cancelled')->count() }}
                                    </span>
                                </div>
                            </div>
                        </header>
                        <div class="service-table-wrap service-table-scroll" role="region" aria-label="{{ $serviceDirection }} trip schedules" tabindex="0">
                            <table class="service-table">
                                <colgroup>
                                    <col class="service-col-expand">
                                    <col class="service-col-time">
                                    <col class="service-col-route">
                                    <col class="service-col-point">
                                    <col class="service-col-required">
                                    <col class="service-col-assigned">
                                    <col class="service-col-status">
                                    <col class="service-col-actions">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th class="service-expander"></th>
                                        <th>Time</th>
                                        <th>Route</th>
                                        <th>{{ $serviceDirection === 'Outgoing' ? 'Remarks / Drop-off Point' : 'Remarks / Pickup Point' }}</th>
                                        <th class="text-center">Required Buses</th>
                                        <th>Assigned Buses</th>
                                        <th>{{ request('tab') === 'history' ? 'Trip Status' : 'Assignment' }}</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($directionTrips as $trip)
                                        @php
                                            $route = $trip->shuttleRoute;
                                            // Use the saved route point, ignoring generic designer placeholders.
                                            $locationCandidates = $serviceDirection === 'Incoming'
                                                ? [$trip->route_origin_snapshot, $route?->origin]
                                                : [$trip->route_destination_snapshot, $route?->destination];
                                            $servicePoint = collect($locationCandidates)
                                                ->map(fn ($value) => trim((string) $value))
                                                ->first(fn ($value) => $value !== '' && ! in_array(strtolower($value), ['destination', 'origin', 'pickup point', 'drop-off point', 'drop off point', '—', 'n/a'], true))
                                                ?? 'Not specified';
                                            $departureDateTime = $trip->departureDateTime();
                                            $arrivalDateTime = $trip->estimatedArrivalDateTime();
                                            $isHistorical = $trip->hasDeparted();
                                            $assignedCount = $trip->assignments->count() ?: ($trip->assignment !== null ? 1 : 0);
                                            $requiredCount = max(1, (int) ($trip->required_buses ?? 1));
                                            $hasAssignment = $assignedCount > 0 || $trip->assignment_status !== 'Unassigned';
                                            $displayAssignmentStatus = $assignedCount >= $requiredCount ? 'Assigned' : ($assignedCount > 0 ? 'Partially Assigned' : 'Unassigned');
                                            $canManageSchedule = $trip->canBeManagedFromSchedule();
                                            $canEdit = $canEditOperation && $canManageSchedule && request('tab') !== 'history';
                                            $canDelete = $canEdit;
                                            $arrivalDisplay = $arrivalDateTime->isSameDay($departureDateTime) ? $arrivalDateTime->format('g:i A') : $arrivalDateTime->format('M d · g:i A');
                                            $assignmentLabel = in_array($trip->status, ['Missed', 'Cancelled', 'Completed'], true) ? $trip->status : ($assignedCount === 0 ? 'Pending' : ($assignedCount >= $requiredCount ? 'Fully Assigned' : 'Partially Assigned'));
                                            $viewTripData = [
                                                'tripCode' => $trip->trip_code, 'date' => $trip->trip_date?->format('M d, Y'),
                                                'routeCode' => $route?->route_code, 'routeName' => $route?->route_name,
                                                'origin' => $route?->origin, 'destination' => $route?->destination,
                                                'departure' => $departureDateTime->format('g:i A'), 'arrival' => $arrivalDisplay,
                                                'shift' => $trip->shift, 'assignment' => $displayAssignmentStatus,
                                                'status' => $trip->status, 'notes' => $trip->notes,
                                                'direction' => $trip->direction, 'requiredBuses' => $requiredCount,
                                            ];
                                        @endphp
                                        <tr>
                                            <td>
                                                <button type="button" class="service-expand-button" aria-label="Expand {{ $trip->trip_code }}" aria-expanded="false">
                                                    <i class="fa-solid fa-chevron-right"></i>
                                                </button>
                                            </td>
                                            <td class="service-time">
                                                <span class="trip-time-badge">
                                                    <i class="fa-regular fa-clock"></i>
                                                    {{ $departureDateTime->format('g:i A') }}
                                                </span>
                                            </td>
                                            <td class="service-route">
                                                <div class="service-route-cell">
                                                    @if($route?->route_code)
                                                        <x-ui.id-badge :value="$route->route_code" />
                                                    @endif
                                                    <span class="service-route-title">{{ $route?->route_name ?? $trip->route_name_snapshot ?? 'Deleted route' }}</span>
                                                </div>
                                            </td>
                                            <td class="service-pickup">
                                                <span class="service-location-text">
                                                    <i class="fa-solid fa-location-dot"></i>
                                                    {{ $servicePoint }}
                                                </span>
                                            </td>
                                            <td class="service-count text-center">
                                                <span class="service-buses-badge">
                                                    <i class="fa-solid fa-bus"></i> {{ $requiredCount }}
                                                </span>
                                            </td>
                                            <td class="service-assigned-col">
                                                <div class="service-assigned-wrap">
                                                    <span class="service-assigned-value">{{ $assignedCount }} / {{ $requiredCount }}</span>
                                                    <div class="service-progress">
                                                        <div class="{{ $assignedCount >= $requiredCount ? 'is-complete' : ($assignedCount > 0 ? 'is-partial' : '') }}" style="width:{{ round($assignedCount / $requiredCount * 100) }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <x-ui.status-badge :status="$assignmentLabel" class="service-assignment-badge {{ strtolower(str_replace(' ', '-', $assignmentLabel)) }}" />
                                            </td>
                                            <td>
                                                <div class="service-row-actions">
                                                    <div class="trip-actions">
                                                        <x-ui.action-button
                                                            type="view"
                                                            class="trip-action view-trip"
                                                            title="View Details"
                                                            data-trip='@json($viewTripData)'
                                                        />
                                                                                                                    <x-ui.action-button
                                                                type="edit"
                                                                class="trip-action edit-trip"
                                                                title="{{ $canEdit ? 'Edit' : 'Editing unavailable for this trip' }}"
                                                                :disabled="! $canEdit"
                                                                data-id="{{ $trip->id }}"
                                                                data-trip-code="{{ $trip->trip_code }}"
                                                                data-trip-date="{{ $trip->trip_date?->format('Y-m-d') }}"
                                                                data-route-id="{{ $trip->shuttle_route_id }}"
                                                                data-direction="{{ $trip->direction }}"
                                                                data-required-buses="{{ $trip->required_buses ?? 1 }}"
                                                                data-departure-time="{{ $departureDateTime->format('H:i') }}"
                                                                data-arrival-time="{{ $arrivalDateTime->format('H:i') }}"
                                                                data-status="{{ $trip->status }}"
                                                                data-notes="{{ $trip->notes }}"
                                                                data-update-url="{{ route('trip-schedule.update', $trip->id, false) }}"
                                                                data-allow-past="{{ $isHistorical ? 'true' : 'false' }}"
                                                            />
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

                                                                <x-ui.action-button
                                                                    type="delete"
                                                                    class="trip-action delete-trip"
                                                                    title="{{ $canDelete ? 'Delete' : 'Deleting unavailable for this trip' }}"
                                                                    :disabled="! $canDelete"
                                                                    data-form-id="deleteTripForm-{{ $trip->id }}"
                                                                    data-trip-code="{{ $trip->trip_code }}"
                                                                />
                                                            </form>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="service-details-row" hidden>
                                            <td colspan="8">
                                                <div class="service-details-content">
                                                    <div class="service-detail-item">
                                                        <span class="detail-label">Trip Code:</span>
                                                        <x-ui.id-badge :value="$trip->trip_code" />
                                                    </div>
                                                    <div class="service-detail-item">
                                                        <span class="detail-label"><i class="fa-solid fa-calendar-day"></i> Schedule:</span>
                                                        <strong>{{ $trip->trip_date?->format('M d, Y') }}</strong> · {{ $trip->shift }} shift
                                                    </div>
                                                    <div class="service-detail-item">
                                                        <span class="detail-label"><i class="fa-solid fa-clock-rotate-left"></i> ETA:</span>
                                                        <strong>{{ $arrivalDisplay }}</strong>
                                                    </div>
                                                    <div class="service-detail-item">
                                                        <span class="detail-label"><i class="fa-solid fa-bus"></i> Assigned Buses ({{ $assignedCount }}/{{ $requiredCount }}):</span>
                                                        @if($trip->assignments->isNotEmpty())
                                                            <div class="service-assigned-chips" style="display: inline-flex; gap: 0.5rem; flex-wrap: wrap;">
                                                                @foreach($trip->assignments as $asgn)
                                                                    <x-ui.id-badge :value="($asgn->bus?->plate_no ?: 'Bus #'.$asgn->bus_id) . ' · ' . ($asgn->driverAttendance?->driver?->full_name ?: $asgn->driver_name ?: 'Driver')" />
                                                                @endforeach
                                                            </div>
                                                        @elseif($trip->assignment)
                                                            <x-ui.id-badge :value="$trip->assignment->bus?->plate_no ?: 'Assigned'" />
                                                        @else
                                                            <span class="text-muted">No bus assigned</span>
                                                        @endif
                                                    </div>
                                                    @if($assignedCount < $requiredCount && $trip->status !== 'Cancelled')
                                                        <div class="service-planning-status" role="status" style="display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; border: 1px solid #fcd34d; border-radius: 7px; background: #fffbeb; color: #92400e; font-size: 12px; font-weight: 650;">
                                                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                                                            Requires {{ $requiredCount - $assignedCount }} more {{ \Illuminate\Support\Str::plural('bus', $requiredCount - $assignedCount) }} to complete assignment.
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <x-ui.empty-row colspan="8" icon="fa-calendar-xmark" :message="'No ' . $serviceDirection . ' Trips Found'" description="There are no schedules matching your selected date and filters." />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <footer class="service-direction-footer">
                            @if($directionTrips->isEmpty())
                                <span>Showing 0 to 0 of 0 entries</span>
                            @else
                                <span>Showing 1 to {{ $directionTrips->count() }} of {{ $directionTrips->count() }} entries</span>
                            @endif
                        </footer>
                    </div>
                @endforeach
                </div>
            </section>

        {{-- Modals are part of the navigable page so their handlers reinitialize on return. --}}


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
            description="Set the route, trip date, departure time, and schedule details."
            icon="fa-calendar-plus"
            size="large"
            form-id="tripForm"
            :action="route('trip-schedule.store', [], false)"
            method="POST"
            submit-text="Create Trip"
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

                <div class="trip-service-planning-fields">
                    <div class="ui-form-group">
                        <label for="tripDirection">Service Direction</label>
                        <select name="direction" id="tripDirection">
                            <option value="">Not specified (legacy)</option>
                            <option value="Incoming" @selected(old('direction') === 'Incoming')>Incoming — Employees going to work</option>
                            <option value="Outgoing" @selected(old('direction') === 'Outgoing')>Outgoing — Employees going home</option>
                        </select>
                        @error('direction')<span class="ui-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <x-ui.form-field label="Departure Time" name="departure_time" id="departureTime" type="time" :value="old('departure_time')" icon="fa-clock" :required="true" />
                <div class="trip-service-planning-fields trip-required-bus-field">
                    <div class="ui-form-group">
                        <label for="tripRequiredBuses">Required Buses</label>
                        <input name="required_buses" id="tripRequiredBuses" type="number" min="1" max="30" step="1" value="{{ old('required_buses', 1) }}" required>
                        <small>Planning demand only. Additional buses cannot be assigned until grouped scheduling is enabled.</small>
                        @error('required_buses')<span class="ui-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>
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
        icon="fa-bus"
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
        </main>
    </div>
</x-layout.app>
