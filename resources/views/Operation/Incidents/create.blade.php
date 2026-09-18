<x-layout.app
    title="FROMS - Report Incident"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Incidents/incidents.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Incidents/incidents.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main inc-page">
            <x-layout.topbar
                title="Report Incident"
                subtitle="Report an operational incident encountered during an active trip"
            />

            @if ($errors->any())
                <div class="inc-alert inc-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>Unable to save the incident report.</strong>
                        <span>Please review the highlighted fields below.</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="inc-alert inc-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>{{ session('error') }}</strong>
                    </div>
                </div>
            @endif

            <div class="inc-report-layout">
                <!-- Reporting Form -->
                <section class="inc-card">
                    <div class="inc-card-header">
                        <div>
                            <h2>Incident Details</h2>
                            <p>Background trip, bus, and driver information is taken from your active assignment.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('incidents.store') }}" class="inc-form">
                        @csrf

                        <div class="inc-form-grid">
                            <!-- Assigned trip auto-detected -->
                            <div class="inc-form-group full">
                                <label for="tripSelect">
                                    Current Trip
                                    <span class="ui-required">*</span>
                                </label>

                                <select
                                    id="tripSelect"
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
                                <label for="incident_type">
                                    Incident Type
                                    <span class="ui-required">*</span>
                                </label>

                                <select name="incident_type" id="incident_type" required>
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
                                <label for="description">
                                    Description / Details
                                </label>

                                <textarea
                                    name="description"
                                    id="description"
                                    placeholder="Describe what happened, vehicles involved, passengers affected, and any help needed..."
                                >{{ old('description') }}</textarea>

                                @error('description')
                                    <span class="ui-field-error">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="inc-form-note">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                <strong>Incidents are timestamped automatically.</strong>
                                <span>The reported time is captured when you submit this form. A unique incident number is generated for this report and Operations is notified immediately.</span>
                            </div>
                        </div>

                        <div class="inc-form-actions">
                            <a href="{{ route('incidents') }}" class="inc-secondary-btn">
                                Cancel
                            </a>
                            <button type="submit" class="inc-primary-btn">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Report Incident
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Assigned Trip Context Panel -->
                <aside class="inc-context-panel">
                    <div class="inc-context-panel-head">
                        <i class="fa-solid fa-bus"></i>
                        <div>
                            <h3>Your Active Assignment</h3>
                            <p>Context used to prefill this report</p>
                        </div>
                    </div>

                    <div class="inc-context-panel-body">
                        @if($activeTripAssignment)
                            <div class="inc-context-item">
                                <div>
                                    <strong>Trip {{ $activeTripAssignment->tripSchedule?->trip_code }}</strong>
                                    <small>{{ $activeTripAssignment->tripSchedule?->trip_date?->format('M d, Y') }}</small>
                                </div>
                                <i class="fa-solid fa-circle-check" style="color: var(--inc-green);"></i>
                            </div>

                            <div class="inc-context-item">
                                <div>
                                    <strong>{{ $activeTripAssignment->bus?->bus_no }}</strong>
                                    <small>{{ $activeTripAssignment->bus?->plate_no }}</small>
                                </div>
                                <i class="fa-solid fa-bus"></i>
                            </div>

                            <div class="inc-context-item">
                                <div>
                                    <strong>{{ $activeTripAssignment->driver_name }}</strong>
                                    <small>{{ $activeTripAssignment->driver_id }}</small>
                                </div>
                                <i class="fa-solid fa-user"></i>
                            </div>

                            @if($activeTripAssignment->tripSchedule?->shuttleRoute)
                                <div class="inc-context-item">
                                    <div>
                                        <strong>{{ $activeTripAssignment->tripSchedule->shuttleRoute->route_name }}</strong>
                                        <small>
                                            {{ $activeTripAssignment->tripSchedule->shuttleRoute->origin }}
                                            &rarr;
                                            {{ $activeTripAssignment->tripSchedule->shuttleRoute->destination }}
                                        </small>
                                    </div>
                                    <i class="fa-solid fa-route"></i>
                                </div>
                            @endif
                        @else
                            <div class="inc-no-match">
                                <i class="fa-solid fa-file-circle-question"></i>
                                <div>
                                    <strong>No active assignment found</strong>
                                    <span>Select a trip from the list to prefill its bus and driver. You can also enter the bus and driver manually.</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="inc-context-panel-foot">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>Trip, bus, and driver are taken from the existing schedule assignment wherever possible so they never need to be typed again.</span>
                    </div>
                </aside>
            </div>
        </main>
    </div>
</x-layout.app>