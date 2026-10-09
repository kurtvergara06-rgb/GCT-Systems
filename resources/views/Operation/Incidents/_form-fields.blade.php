<!-- Bus-first lookup uses actual assignment data already loaded for today. -->
<div class="inc-form-group full inc-bus-lookup" data-incident-bus-lookup>
    <label for="{{ $formPrefix ?? '' }}incidentBusLookup">Find Assigned Trip by Bus ID <small class="inc-optional-label">(Optional)</small></label>
    <input id="{{ $formPrefix ?? '' }}incidentBusLookup" type="search" data-incident-bus-search
        placeholder="Type Bus ID or plate number (e.g. GCT-205)" autocomplete="off" />
    <div class="inc-bus-lookup-results" data-incident-bus-results role="status" aria-live="polite" hidden></div>
    <div class="inc-bus-trip-context" data-incident-trip-context hidden></div>
</div>

<!-- Assigned trip auto-detected -->
<div class="inc-form-group full">
    <label for="{{ $formPrefix ?? '' }}tripSelect">
        Current Trip <small class="inc-optional-label">(Optional)</small>
    </label>

    <select
        id="{{ $formPrefix ?? '' }}tripSelect"
        name="trip_schedule_id"
        data-trip-select
    >
        <option value="">
            {{ $activeTripAssignment ? 'Trip already selected from your assignment' : 'Select the trip you are currently running...' }}
        </option>

        @if($tripSchedule)
            <option
                value="{{ $tripSchedule->id }}"
                data-assignment-id="{{ $activeTripAssignment?->id }}" data-driver-id="{{ $activeTripAssignment?->driver_id }}" data-bus-id="{{ $activeTripAssignment?->bus_id }}" data-bus-no="{{ $activeTripAssignment?->bus?->bus_no }}" data-plate-no="{{ $activeTripAssignment?->bus?->plate_no }}" data-driver-name="{{ $activeTripAssignment?->driver_name }}" data-route="{{ $tripSchedule->shuttleRoute?->route_name }}" data-origin="{{ $tripSchedule->shuttleRoute?->origin }}" data-destination="{{ $tripSchedule->shuttleRoute?->destination }}" data-departure="{{ $tripSchedule->departure_time }}" data-arrival="{{ $tripSchedule->estimated_arrival_time }}" data-trip-status="{{ $tripSchedule->status }}"
                selected
            >
                {{ $tripSchedule->trip_code }}
                &bull; {{ $tripSchedule->trip_date?->format('M d, Y') }}
                &bull; {{ $tripSchedule->trip_date && $tripSchedule->shuttleRoute ? $tripSchedule->shuttleRoute->route_name : '' }}
            </option>
        @endif

        @foreach($availableTrips as $trip)
            @if($trip->id === $tripSchedule?->id)
                @continue
            @endif
            <option
                value="{{ $trip->id }}"
                data-assignment-id="{{ $trip->assignment?->id }}" data-driver-id="{{ $trip->assignment?->driver_id }}" data-bus-id="{{ $trip->assignment?->bus_id }}" data-bus-no="{{ $trip->assignment?->bus?->bus_no }}" data-plate-no="{{ $trip->assignment?->bus?->plate_no }}" data-driver-name="{{ $trip->assignment?->driver_name }}" data-route="{{ $trip->shuttleRoute?->route_name }}" data-origin="{{ $trip->shuttleRoute?->origin }}" data-destination="{{ $trip->shuttleRoute?->destination }}" data-departure="{{ $trip->departure_time }}" data-arrival="{{ $trip->estimated_arrival_time }}" data-trip-status="{{ $trip->status }}"
                @selected(old('trip_schedule_id') == $trip->id)
            >
                {{ $trip->trip_code }}
                &bull; {{ $trip->trip_date?->format('M d, Y') }}
                &bull; {{ $trip->shuttleRoute?->route_name }}
            </option>
        @endforeach
    </select>

    <input
        type="hidden"
        name="trip_assignment_id"
        value="{{ $activeTripAssignment?->id }}"
        data-trip-assignment-id
    />

    @error('trip_schedule_id')
        <span class="ui-field-error">{{ $message }}</span>
    @enderror
</div>

<!-- Read-only trip / bus / driver context from assignment -->
<div class="inc-form-group">
    <label>Bus Assigned</label>

    @if($activeTripAssignment?->bus)
        <input
            type="text"
            value="{{ $activeTripAssignment->bus->bus_no }} ({{ $activeTripAssignment->bus->plate_no }})"
            readonly
            disabled
        />
        <input type="hidden" name="bus_id" value="{{ $activeTripAssignment->bus_id }}" />
    @else
        <select name="bus_id" data-incident-bus-select>
            <option value="">Select bus...</option>
            @foreach($availableTrips->pluck('assignment.bus')->unique('id')->filter() as $bus)
                <option value="{{ $bus->id }}" @selected(old('bus_id') == $bus->id)>
                    {{ $bus->bus_no }} ({{ $bus->plate_no }})
                </option>
            @endforeach
        </select>
        @error('bus_id')
            <span class="ui-field-error">{{ $message }}</span>
        @enderror
    @endif
</div>

<div class="inc-form-group">
    <label>Driver on Trip</label>

    @if($activeTripAssignment?->driver_name)
        <input
            type="text"
            value="{{ $activeTripAssignment->driver_name }} ({{ $activeTripAssignment->driver_id }})"
            readonly
            disabled
        />
        <input type="hidden" name="driver_id" value="{{ $activeTripAssignment->driver_id }}" />
        <input type="hidden" name="driver_name" value="{{ $activeTripAssignment->driver_name }}" />
    @else
        <select name="driver_id" data-incident-driver-select>
            <option value="">Select driver from master list (optional)...</option>
            @foreach($activeDrivers as $driver)
                <option value="{{ $driver->driver_id }}" @selected(old('driver_id') == $driver->driver_id)>
                    {{ $driver->driver_name }} ({{ $driver->driver_id }})
                </option>
            @endforeach
        </select>
        <input type="hidden" name="driver_name" value="" />
        @error('driver_id')
            <span class="ui-field-error">{{ $message }}</span>
        @enderror
    @endif
</div>

<div class="inc-form-group">
    <label for="{{ $formPrefix ?? '' }}incident_type">
        Incident Type
        <span class="ui-required">*</span>
    </label>

    <select name="incident_type" id="{{ $formPrefix ?? '' }}incident_type" required>
        <option value="">Select incident type...</option>
        @foreach(['Traffic', 'Bus Breakdown', 'Accident/Road Incident', 'Other'] as $incidentType)
            <option value="{{ $incidentType }}" @selected(old('incident_type') == $incidentType)>
                {{ $incidentType }}
            </option>
        @endforeach
    </select>

    @error('incident_type')
        <span class="ui-field-error">{{ $message }}</span>
    @enderror
</div>

<x-ui.form-field
    label="Current Location"
    name="location"
    value="{{ old('location') }}"
    placeholder="Landmark, street, or area of the incident"
    required
    icon="fa-location-dot"
/>

<div class="inc-form-group full">
    <label for="{{ $formPrefix ?? '' }}description">
        Description / Details
    </label>

    <textarea
        name="description"
        id="{{ $formPrefix ?? '' }}description"
        placeholder="Describe what happened, vehicles involved, passengers affected, and any help needed..."
    >{{ old('description') }}</textarea>

    @error('description')
        <span class="ui-field-error">{{ $message }}</span>
    @enderror
</div>
