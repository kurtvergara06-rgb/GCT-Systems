<x-layout.app
    title="FROMS - Encode Daily Driver Report"
    :assets="[
        'resources/css/Main-styles/main.css',
        'resources/css/Main-styles/sidebar.css',
        'resources/css/Main-styles/form-components.css',
        'resources/css/Operation/Daily_Driver_Reports/daily-driver-reports.css',
        'resources/js/Main-js/sidebar.js',
        'resources/js/Operation/Daily_Driver_Reports/daily-driver-reports.js',
    ]"
>
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main ddr-page">
            <x-layout.topbar
                title="Encode Daily Driver Report"
                subtitle="Record actual trip execution from a physical daily driver report"
            />

            @if ($errors->any())
                <div class="ddr-alert ddr-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>Unable to save the report.</strong>
                        <span>Please review the highlighted fields below.</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="ddr-alert ddr-alert-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>
                        <strong>{{ session('error') }}</strong>
                    </div>
                </div>
            @endif

            <div class="ddr-encode-layout">
                <!-- Encoding Form -->
                <section class="ddr-card">
                    <div class="ddr-card-header">
                        <div>
                            <h2>Report Details</h2>
                            <p>Values are transcribed from the physical DDR; nothing is generated or estimated.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('daily-driver-reports.store') }}" class="ddr-form">
                        @csrf

                        <div class="ddr-form-grid">
                            <x-ui.form-field
                                label="Report Date"
                                name="report_date"
                                type="date"
                                value="{{ old('report_date') }}"
                                required
                                icon="fa-calendar-day"
                            />

                            <x-ui.form-field
                                label="Trip Ticket No."
                                name="trip_ticket"
                                value="{{ old('trip_ticket') }}"
                                placeholder="Ticket number printed on the DDR"
                                required
                                icon="fa-ticket"
                                full="1"
                                list="ddrTripTicketList"
                            />
                            <datalist id="ddrTripTicketList">
                                @foreach($tripTicketSuggestions as $ticket)
                                    <option value="{{ $ticket }}"></option>
                                @endforeach
                            </datalist>

                            <div class="ddr-form-group full">
                                <label for="driverCombo">
                                    Driver
                                    <span class="ui-required">*</span>
                                </label>

                                <div class="ddr-combo" data-combo data-field="driver">
                                    <input
                                        type="text"
                                        class="ddr-combo-input"
                                        data-combo-input
                                        placeholder="Search driver by name or ID..."
                                        autocomplete="off"
                                    >
                                    <input
                                        type="hidden"
                                        name="driver_id"
                                        value="{{ old('driver_id') }}"
                                        data-combo-value
                                    >
                                    <button type="button" class="ddr-combo-clear" data-combo-clear tabindex="-1" title="Clear selection">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                    <ul class="ddr-combo-list" data-combo-list>
                                        @foreach($drivers as $driver)
                                            <li
                                                data-combo-option
                                                data-value="{{ $driver->driver_id }}"
                                                data-search="{{ strtolower($driver->driver_name . ' ' . $driver->driver_id . ' ' . $driver->shift . ' ' . $driver->employment_status) }}"
                                            >
                                                <strong>{{ $driver->driver_name }}</strong>
                                                <small>{{ $driver->driver_id }} &bull; {{ $driver->shift }} &bull; {{ $driver->employment_status }}</small>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                @error('driver_id')
                                    <span class="ui-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="ddr-form-group full">
                                <label for="busCombo">
                                    Bus
                                    <span class="ui-required">*</span>
                                </label>

                                <div class="ddr-combo" data-combo data-field="bus">
                                    <input
                                        type="text"
                                        class="ddr-combo-input"
                                        data-combo-input
                                        placeholder="Search bus number or plate..."
                                        autocomplete="off"
                                    >
                                    <input
                                        type="hidden"
                                        name="bus_id"
                                        value="{{ old('bus_id') }}"
                                        data-combo-value
                                    >
                                    <button type="button" class="ddr-combo-clear" data-combo-clear tabindex="-1" title="Clear selection">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>

                                    <ul class="ddr-combo-list" data-combo-list>
                                        @foreach($activeBuses as $bus)
                                            <li
                                                data-combo-option
                                                data-value="{{ $bus->id }}"
                                                data-search="{{ strtolower($bus->bus_no . ' ' . $bus->plate_no . ' ' . $bus->bus_model . ' ' . $bus->status) }}"
                                            >
                                                <strong>{{ $bus->bus_no }}</strong>
                                                <small>{{ $bus->plate_no }} &bull; {{ $bus->bus_model }} &bull; {{ $bus->status }}</small>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>

                                @error('bus_id')
                                    <span class="ui-field-error">{{ $message }}</span>
                                @enderror
                            </div>

                            <x-ui.form-field
                                label="From Location"
                                name="from_location"
                                value="{{ old('from_location') }}"
                                placeholder="Origin terminal / stop"
                                required
                                icon="fa-circle-play"
                            />

                            <x-ui.form-field
                                label="To Location"
                                name="to_location"
                                value="{{ old('to_location') }}"
                                placeholder="Destination terminal / stop"
                                required
                                icon="fa-circle-flag"
                                unit=""
                            />

                            <x-ui.form-field
                                label="Departure Time"
                                name="departure_time"
                                type="time"
                                value="{{ old('departure_time') }}"
                                required
                                icon="fa-clock"
                            />

                            <x-ui.form-field
                                label="Arrival Time"
                                name="arrival_time"
                                type="time"
                                value="{{ old('arrival_time') }}"
                                required
                                icon="fa-flag-checkered"
                            />

                            <x-ui.form-field
                                label="Passengers"
                                name="passengers"
                                type="number"
                                value="{{ old('passengers') }}"
                                min="0"
                                step="1"
                                placeholder="0"
                                required
                                icon="fa-users"
                            />
                        </div>

                        <div class="ddr-form-note">
                            <i class="fa-solid fa-circle-info"></i>
                            <div>
                                <strong>Overnight trips are supported.</strong>
                                <span>Arrival times earlier than the departure time mean the trip ran past midnight. Entered times are saved exactly as encoded on the DDR and are never modified.</span>
                            </div>
                        </div>

                        <div class="ddr-form-actions">
                            <a href="{{ route('daily-driver-reports') }}" class="ddr-secondary-btn">
                                Cancel
                            </a>
                            <button type="submit" class="ddr-primary-btn">
                                <i class="fa-solid fa-floppy-disk"></i>
                                Save Report
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Schedule Context Panel -->
                <aside class="ddr-sched-panel">
                    <div class="ddr-sched-panel-head">
                        <i class="fa-solid fa-calendar-check"></i>
                        <div>
                            <h3>Scheduled Trip Match</h3>
                            <p>Reference panel &mdash; for comparison only, never stored.</p>
                        </div>
                    </div>

                    <div class="ddr-sched-panel-body">
                        <div id="sfcStatus" class="ddr-sched-status idle">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Select a date, driver, and bus to check matching scheduled trips.</span>
                        </div>

                        <div id="sfcList" class="ddr-sched-list"></div>
                    </div>

                    <div class="ddr-sched-panel-foot">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>A match only exists when a real trip is scheduled for this driver and bus on the selected date. Otherwise the comparison shows &ldquo;Schedule match unavailable&rdquo;.</span>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    @push('scripts')
        <script>
            window.ddrScheduleLookupUrl = "{{ route('daily-driver-reports.schedule-lookup', [], false) }}";
        </script>
    @endpush
</x-layout.app>