<x-layout.app
    title="FROMS - Edit Incident"
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
                title="Edit Incident"
                subtitle="Correct the incident location and description before processing begins"
            />
            <section class="inc-card inc-edit-card">
                <div class="inc-card-header">
                    <div>
                        <h2>{{ $incident->incident_no }}</h2>
                        <p>{{ $incident->incident_type }} — {{ $incident->bus?->bus_no ?? 'No bus' }}</p>
                    </div>
                    <a class="inc-secondary-btn" href="{{ route('incidents.show', ['incident' => $incident->incident_no]) }}">Back to Details</a>
                </div>
                <div class="inc-edit-notice">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Trip assignments, incident type, and status are protected. Only details can be corrected before response processing.
                </div>
                <form method="POST" action="{{ route('incidents.details.update', ['incident' => $incident->incident_no]) }}" class="inc-edit-form">
                    @csrf
                    @method('PATCH')
                    <div class="inc-form-group">
                        <label for="editIncidentLocation">Current Location <span class="ui-required">*</span></label>
                        <input id="editIncidentLocation" name="location" type="text" maxlength="255" required value="{{ old('location', $incident->location) }}" />
                        @error('location')<span class="ui-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="inc-form-group">
                        <label for="editIncidentDescription">Description / Details</label>
                        <textarea id="editIncidentDescription" name="description" maxlength="2000" rows="5">{{ old('description', $incident->description) }}</textarea>
                        @error('description')<span class="ui-field-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="inc-form-actions">
                        <a href="{{ route('incidents.show', ['incident' => $incident->incident_no]) }}" class="inc-secondary-btn">Cancel</a>
                        <button type="submit" class="inc-primary-btn"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Save Changes</button>
                    </div>
                </form>
            </section>
        </main>
    </div>
</x-layout.app>
