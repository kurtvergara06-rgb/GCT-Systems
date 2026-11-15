<!-- Assigned trip auto-detected -->
<div class="inc-form-group full">
    <label for="{{ $formPrefix ?? '' }}tripSelect">
        Current Trip
        <span class="ui-required">*</span>
    </label>

    <select
        id="{{ $formPrefix ?? '' }}tripSelect"
        name="trip_schedule_id"
        data-trip-select
        @if($tripSchedule) required @endif
    >
        <option value="">
            {{ $activeTripAssignment ? 'Trip already selected from your assignment' : 'Select the trip you are currently running...' }}
        </option>

        @if($tripSchedule)
            <option
                value="{{ $tripSchedule->id }}"
                data-assignment-id="{{ $activeTripAssignment?->id }}"
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
                data-assignment-id="{{ $trip->assignment?->id }}"
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
        <select name="bus_id" required>
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
        <input
            type="text"
            name="driver_name"
            value="{{ old('driver_name') }}"
            placeholder="Driver name / ID"
            required
        />
        <input type="hidden" name="driver_id" value="{{ old('driver_id') }}" />
        @error('driver_name')
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
