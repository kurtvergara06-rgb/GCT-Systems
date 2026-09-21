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
                            @include('Operation.Incidents._form-fields')
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