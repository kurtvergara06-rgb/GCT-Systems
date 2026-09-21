<x-layout.app
    title="FROMS - Daily Drivers Report"
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
                title="Daily Drivers Report"
                subtitle="Encoded actual trip execution from physical daily driver reports"
            />

            @if (session('success'))
                <div class="ddr-alert ddr-alert-success" role="alert">
                    <i class="fa-solid fa-circle-check"></i>
                    <div>
                        <strong>{{ session('success') }}</strong>
                    </div>
                </div>
            @endif

            <!-- Summary KPI Cards -->
            <section class="ddr-summary-grid">
                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon blue">
                        <i class="fa-solid fa-file-lines"></i>
                    </div>
                    <div>
                        <p>Total Reports Encoded</p>
                        <h2>{{ number_format($totalReports) }}</h2>
                        <small>All daily driver reports</small>
                    </div>
                </article>

                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon green">
                        <i class="fa-solid fa-calendar-day"></i>
                    </div>
                    <div>
                        <p>Reports Today</p>
                        <h2>{{ number_format($reportsToday) }}</h2>
                        <small>Encoded for today</small>
                    </div>
                </article>

                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon yellow">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div>
                        <p>Passengers Today</p>
                        <h2>{{ number_format($passengersToday) }}</h2>
                        <small>Total passengers carried today</small>
                    </div>
                </article>

                <article class="ddr-summary-card">
                    <div class="ddr-summary-icon purple">
                        <i class="fa-solid fa-chart-simple"></i>
                    </div>
                    <div>
                        <p>Avg Passengers / Trip</p>
                        <h2>{{ number_format($passengerCount, 1) }}</h2>
                        <small>Load average for today</small>
                    </div>
                </article>
            </section>

            <!-- Main Records Card -->
            <section class="ddr-card">
                <div class="ddr-card-header">
                    <div>
                        <h2>Daily Driver Report Records</h2>
                        <p>Actual shuttle trip execution encoded from physical daily driver reports.</p>
                    </div>

                    <button type="button" id="openEncodeReportModal" class="ddr-new-btn">
                        <i class="fa-solid fa-plus"></i>
                        Encode New Report
                    </button>
                </div>

                <!-- Filter & Search Toolbar -->
                <form method="GET" action="{{ route('daily-driver-reports') }}" class="ddr-toolbar">
                    <div class="ddr-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Search by DDR no, driver, trip ticket, location..."
                        />
                    </div>

                    <div class="ddr-filter date-filter">
                        <label for="filterDate">Report Date</label>
                        <input
                            type="date"
                            id="filterDate"
                            name="report_date"
                            value="{{ request('report_date') }}"
                            onchange="this.form.submit()"
                        />
                    </div>

                    <div class="ddr-filter">
                        <label for="filterDriver">Driver</label>
                        <select id="filterDriver" name="driver" onchange="this.form.submit()">
                            <option value="all">All Drivers</option>
                            @foreach($drivers as $driver)
                                <option value="{{ $driver->driver_id }}" @selected(request('driver') == $driver->driver_id)>
                                    {{ $driver->driver_name }} ({{ $driver->driver_id }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="ddr-filter">
                        <label for="filterBus">Bus</label>
                        <select id="filterBus" name="bus" onchange="this.form.submit()">
                            <option value="all">All Buses</option>
                            @foreach($buses as $bus)
                                <option value="{{ $bus->id }}" @selected(request('bus') == $bus->id)>
                                    {{ $bus->bus_no }} - {{ $bus->plate_no }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if(request()->anyFilled(['search', 'report_date', 'driver', 'bus']))
                        <a href="{{ route('daily-driver-reports') }}" class="ddr-clear-btn" title="Reset all filters">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    @endif
                </form>

                <!-- DDR Table Container with Contained Scroll -->
                <div class="table-wrap ddr-table-wrap">
                    <table class="ddr-table">
                        <thead>
                            <tr>
                                <th>DDR No.</th>
                                <th>Report Date</th>
                                <th>Driver</th>
                                <th>Bus</th>
                                <th>Trip Ticket</th>
                                <th>Route</th>
                                <th>Actual Times</th>
                                <th>Passengers</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($reports as $report)
                                @php
                                    $dateFormatted = $report->report_date
                                        ? $report->report_date->format('M d, Y')
                                        : '—';

                                    $departureFmt = $report->departure_time
                                        ? $report->departure_time->format('H:i')
                                        : '—';

                                    $arrivalFmt = $report->arrival_time
                                        ? $report->arrival_time->format('H:i')
                                        : '—';

                                    $routeFmt = trim(e($report->from_location))
                                        . ' → '
                                        . trim(e($report->to_location));
                                @endphp

                                <tr>
                                    <td>
                                        <x-ui.id-badge :value="$report->ddr_no" />
                                    </td>

                                    <td>
                                        <strong style="font-size: 12px; color: var(--ddr-navy);">{{ $dateFormatted }}</strong>
                                    </td>

                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 2px;">
                                            <span style="font-weight: 700; color: var(--ddr-navy);">{{ $report->driver_name }}</span>
                                            @if($report->driver_id)
                                                <small style="color: var(--ddr-muted); font-size: 11px;">{{ $report->driver_id }}</small>
                                            @endif
                                        </div>
                                    </td>

                                    <td>
                                        @if($report->bus)
                                            <x-ui.id-badge :value="$report->bus->bus_no" />
                                        @else
                                            <span style="color: #94a3b8; font-style: italic;">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        <x-ui.id-badge :value="$report->trip_ticket" tone="neutral" />
                                    </td>

                                    <td>
                                        <div class="ddr-route-cell">
                                            <span>{!! $routeFmt !!}</span>
                                        </div>
                                    </td>

                                    <td>
                                        <span style="font-weight: 700; color: var(--ddr-navy); font-size: 12px;">
                                            {{ $departureFmt }} <span style="color: #94a3b8;">→</span> {{ $arrivalFmt }}
                                        </span>
                                        <br>
                                        <small style="color: var(--ddr-muted); font-size: 11px;">
                                            {{ number_format($report->passengers) }} pax
                                        </small>
                                    </td>

                                    <td>
                                        <span class="ddr-passenger-pill">
                                            {{ number_format($report->passengers) }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="ddr-actions">
                                            <a
                                                href="{{ route('daily-driver-reports.show', ['dailyDriverReport' => $report->ddr_no]) }}"
                                                class="ddr-action view"
                                                title="View Report Details"
                                            >
                                                <i class="fa-regular fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="empty-row">
                                    <td colspan="9" style="text-align: center; padding: 48px 20px;">
                                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; color: var(--ddr-muted);">
                                            <i class="fa-solid fa-file-lines" style="font-size: 32px; color: #cbd5e1;"></i>
                                            <strong style="font-size: 15px; color: var(--ddr-navy);">No Daily Driver Reports Found</strong>
                                            <p style="font-size: 13px; margin: 0; max-width: 420px;">
                                                No encoded reports matched your search or filter criteria. Try adjusting the date, driver, or bus filters.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$reports" />
            </section>
        </main>
    </div>

    <x-ui.form-modal
        id="ddrEncodeModal"
        title="Encode New Report"
        description="Values are transcribed from the physical DDR; nothing is generated or estimated."
        icon="fa-file-lines"
        size="wide"
        form-id="ddrEncodeForm"
        :action="route('daily-driver-reports.store', [], false)"
        method="POST"
        submit-text="Save Report"
        submit-text-id="ddrEncodeSubmitText"
        submit-icon="fa-floppy-disk"
        cancel-text="Cancel"
        cancel-id="cancelEncodeReport"
        close-id="closeEncodeReport"
    >
        @if ($errors->any() || session('error'))
            <div class="ddr-alert ddr-alert-error ddr-modal-alert" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <div>
                    <strong>{{ session('error') ?? 'Unable to save the report.' }}</strong>
                    <span>Please review the highlighted fields below.</span>
                </div>
            </div>
        @endif

        <div class="ddr-form-grid ddr-modal-form-grid">
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
                list="ddrTripTicketList"
            />
            <datalist id="ddrTripTicketList">
                @foreach($tripTicketSuggestions as $ticket)
                    <option value="{{ $ticket }}"></option>
                @endforeach
            </datalist>

            <div class="ddr-form-group">
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

            <div class="ddr-form-group">
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

        <details class="ddr-sched-panel ddr-modal-sched-panel">
            <summary class="ddr-sched-panel-head">
                <i class="fa-solid fa-calendar-check"></i>
                <div>
                    <h3>Scheduled Trip Match</h3>
                    <p>Reference panel &mdash; for comparison only, never stored.</p>
                </div>
            </summary>

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
        </details>
    </x-ui.form-modal>

    @push('scripts')
        <script>
            window.ddrScheduleLookupUrl = "{{ route('daily-driver-reports.schedule-lookup', [], false) }}";
        </script>
    @endpush
</x-layout.app>