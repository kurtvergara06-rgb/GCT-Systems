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
    @php
        $canEditOperation = auth()->user()?->hasSystemPermission('operation', 'edit') ?? false;
    @endphp
    <div class="app">
        <x-layout.sidebar department="Operation" />

        <main class="main ddr-page">
            <x-layout.topbar
                title="Daily Drivers Report"
                subtitle="Encoded actual trip execution from physical daily driver reports"
            />

            <!-- Summary KPI Cards -->
            <section data-ajax-region="summary" class="ddr-summary-grid">
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
            <section data-ajax-region="records" class="ddr-card">
                <div class="ddr-card-header">
                    <div>
                        <h2>Daily Driver Report Records</h2>
                        <p>Actual shuttle trip execution encoded from physical daily driver reports.</p>
                    </div>

                    @if($canEditOperation)
                    <button type="button" id="openEncodeReportModal" class="ddr-new-btn">
                        <i class="fa-solid fa-plus"></i>
                        Encode New Report
                    </button>
                    @else
                    <span title="You don't have authorized access to this action." aria-label="You don't have authorized access to this action." style="display:inline-flex;cursor:not-allowed">
                      <button type="button" class="ddr-new-btn" disabled aria-disabled="true" tabindex="-1" style="opacity:.45;filter:grayscale(1);pointer-events:none"><i class="fa-solid fa-plus"></i> Encode New Report</button>
                    </span>
                    @endif
                </div>

                <!-- Filter & Search Toolbar -->
                <x-ui.table-toolbar action="{{ route('daily-driver-reports') }}" search-label="Search Daily Driver Reports" search-placeholder="Search DDR no., driver, ticket, or location..." :filter-count="(int) request()->filled('report_date') + (int) (request()->filled('driver') && request('driver') !== 'all') + (int) (request()->filled('bus') && request('bus') !== 'all')" filter-title="Filter Daily Driver Reports" :sort-options="['report_date' => 'Report Date', 'ddr_no' => 'DDR Number', 'driver_name' => 'Driver Name', 'passengers' => 'Passengers']" default-sort="report_date" default-direction="desc">
                    <x-slot:filters>
                        <x-ui.table-toolbar-field label="Report Date" name="report_date" type="date" />
                        <x-ui.table-toolbar-field label="Driver" name="driver" default="all"><option value="all">All Drivers</option>@foreach($drivers as $driver)<option value="{{ $driver->driver_id }}" @selected(request('driver') == $driver->driver_id)>{{ $driver->driver_name }} ({{ $driver->driver_id }})</option>@endforeach</x-ui.table-toolbar-field>
                        <x-ui.table-toolbar-field label="Bus" name="bus" default="all"><option value="all">All Buses</option>@foreach($buses as $bus)<option value="{{ $bus->id }}" @selected(request('bus') == $bus->id)>{{ $bus->bus_no }} - {{ $bus->plate_no }}</option>@endforeach</x-ui.table-toolbar-field>
                    </x-slot:filters>
                </x-ui.table-toolbar>

                <!-- DDR Table Container with Contained Scroll -->
                <div class="table-wrap ddr-table-wrap" style="margin-top: 16px;">
                    <table class="ddr-table">
                        <thead>
                            <tr>
                                <th>DDR No.</th>
                                <th>Report Date</th>
                                <th>Driver</th>
                                <th>Bus</th>
                                <th>Trips</th>
                                <th>First Route</th>
                                <th>First Trip Times</th>
                                <th>Total Passengers</th>
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
                                        <span class="ddr-passenger-pill">{{ 1 + $report->additionalTrips->count() }} {{ (1 + $report->additionalTrips->count()) === 1 ? "trip" : "trips" }}</span>
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
                                            {{ number_format($report->passengers + $report->additionalTrips->sum('passengers')) }}
                                        </span>
                                    </td>

                                    <td>
                                        <div class="ddr-actions">
                                            <button type="button" class="ddr-action view ddr-open-modal" data-ddr-modal="ddrView-{{ $report->id }}" title="View Report Details" aria-label="View {{ $report->ddr_no }}"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                            @php
                                                $ddrLinked = $report->trip_schedule_id !== null || $report->trip_assignment_id !== null
                                                    || $report->additionalTrips->contains(fn ($entry) => $entry->trip_schedule_id !== null || $entry->trip_assignment_id !== null);
                                                $canDeleteDdr = (auth()->user()?->hasSystemPermission('operation', 'edit') ?? false) && ! $ddrLinked;
                                            @endphp
                                            @if($canDeleteDdr)
                                                <button type="button" class="ddr-action ddr-edit-action ddr-open-modal" data-ddr-modal="ddrEdit-{{ $report->id }}" title="Edit report" aria-label="Edit {{ $report->ddr_no }}"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>
                                            @else
                                                <button type="button" class="ddr-action ddr-edit-action" disabled
                                                    title="Unavailable: linked history or insufficient permission"
                                                    aria-label="Edit unavailable for {{ $report->ddr_no }}">
                                                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                            <button type="button" class="ddr-action ddr-delete-trigger"
                                                data-ddr-id="{{ $report->ddr_no }}"
                                                data-delete-url="{{ route('daily-driver-reports.destroy', ['dailyDriverReport' => $report->ddr_no], false) }}"
                                                aria-label="Delete {{ $report->ddr_no }}"
                                                title="{{ $canDeleteDdr ? 'Delete unlinked DDR' : 'Unavailable: linked history or insufficient permission' }}"
                                                @disabled(! $canDeleteDdr)>
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row
                                    colspan="9"
                                    icon="fa-file-lines"
                                    message="No Daily Driver Reports Found"
                                    description="No encoded reports matched your search or filter criteria. Try adjusting the date, driver, or bus filters."
                                />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-ui.table-footer :items="$reports" />
            </section>

        {{-- View and correction forms are page-owned modal dialogs, never separate screens. --}}
        @foreach($reports as $report)
            <div id="ddrView-{{ $report->id }}" class="ddr-record-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="ddrViewTitle-{{ $report->id }}">
                <section class="ddr-record-dialog">
                    <header class="ddr-record-header">
                        <div class="ddr-record-title-icon"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></div><div><h2 id="ddrViewTitle-{{ $report->id }}">Daily Driver Report Details</h2><p>{{ $report->ddr_no }} · Actual encoded trip record</p></div>
                        <button type="button" class="ddr-record-close" aria-label="Close report details"><i class="fa-solid fa-xmark"></i></button>
                    </header>
                    <div class="ddr-record-body">
                        <div class="ddr-record-info ddr-record-info-iconic">
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>Report Date</small><strong>{{ $report->report_date?->format('M d, Y') }}</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>Driver</small><strong>{{ $report->driver_name }} ({{ $report->driver_id }})</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-bus" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>Bus</small><strong>{{ $report->bus?->bus_no ?? '—' }} · {{ $report->bus?->plate_no ?? '—' }}</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-ticket" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>Trip Ticket</small><strong>{{ $report->trip_ticket }}</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-route" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>First Route</small><strong>{{ $report->from_location }} → {{ $report->to_location }}</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>First Trip Time</small><strong>{{ ($report->getRawOriginal('departure_time') ? \Carbon\Carbon::parse($report->getRawOriginal('departure_time'))->format('h:i A') : '—') }} → {{ ($report->getRawOriginal('arrival_time') ? \Carbon\Carbon::parse($report->getRawOriginal('arrival_time'))->format('h:i A') : '—') }}</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>First Trip Passengers</small><strong>{{ $report->passengers }}</strong></span></div>
                            <div><span class="ddr-info-icon"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i></span><span class="ddr-info-copy"><small>First Trip KM</small><strong>{{ $report->km !== null ? (string) (float) $report->km : '—' }}</strong></span></div>
                        </div>
                        @if($report->unscheduled_reason)
                        <p class="ddr-record-note"><strong>Unscheduled trip reason:</strong> {{ $report->unscheduled_reason }}</p>
                    @endif
                    <h3>Trip Entries ({{ 1 + $report->additionalTrips->count() }})</h3>
                        <div class="ddr-record-table-wrap"><table class="ddr-record-table">
                            <thead><tr><th>#</th><th>Ticket</th><th>From → To</th><th>Departure</th><th>Arrival</th><th>Passengers</th><th>KM</th></tr></thead>
                            <tbody>
                                <tr><td>1</td><td>{{ $report->trip_ticket }}</td><td>{{ $report->from_location }} → {{ $report->to_location }}</td><td>{{ ($report->getRawOriginal('departure_time') ? \Carbon\Carbon::parse($report->getRawOriginal('departure_time'))->format('h:i A') : '—') }}</td><td>{{ ($report->getRawOriginal('arrival_time') ? \Carbon\Carbon::parse($report->getRawOriginal('arrival_time'))->format('h:i A') : '—') }}</td><td>{{ $report->passengers }}</td><td>{{ $report->km !== null ? (string) (float) $report->km : '—' }}</td></tr>
                                @foreach($report->additionalTrips as $entry)
                                    <tr><td>{{ $loop->iteration + 1 }}</td><td>{{ $entry->trip_ticket }}</td><td>{{ $entry->from_location }} → {{ $entry->to_location }}</td><td>{{ ($entry->departure_time ? \Carbon\Carbon::parse($entry->departure_time)->format('h:i A') : '—') }}</td><td>{{ ($entry->arrival_time ? \Carbon\Carbon::parse($entry->arrival_time)->format('h:i A') : '—') }}</td><td>{{ $entry->passengers }}</td><td>{{ $entry->km !== null ? (string) (float) $entry->km : '—' }}</td></tr>
                                @endforeach
                            </tbody>
                        </table></div>
                        <div class="ddr-record-total"><div><i class="fa-solid fa-users" aria-hidden="true"></i><span>Total Passengers<strong>{{ $report->passengers + $report->additionalTrips->sum('passengers') }}</strong></span></div><div><i class="fa-solid fa-road" aria-hidden="true"></i><span>Total KM<strong>{{ (string) ((float) $report->km + $report->additionalTrips->sum('km')) }}</strong></span></div></div>
                        <div class="ddr-record-compare">
                            <h3><i class="fa-solid fa-calendar-check" aria-hidden="true"></i> Schedule Comparison</h3>
                            <p>{{ $report->trip_schedule_id ? 'A trip schedule is linked to this report. The original recorded values are shown above.' : 'Schedule match unavailable: no matching trip schedule was recorded for this DDR.' }}</p>
                        </div>
                    </div>
                    <footer class="ddr-record-footer"><button type="button" class="ddr-record-close">Close</button></footer>
                </section>
            </div>

            @if((auth()->user()?->hasSystemPermission('operation', 'edit') ?? false)
                && !$report->trip_schedule_id && !$report->trip_assignment_id
                && !$report->additionalTrips->contains(fn ($entry) => $entry->trip_schedule_id !== null || $entry->trip_assignment_id !== null))
                <div id="ddrEdit-{{ $report->id }}" class="ddr-record-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="ddrEditTitle-{{ $report->id }}">
                    <section class="ddr-record-dialog ddr-record-dialog-edit">
                        <header class="ddr-record-header">
                            <div><h2 id="ddrEditTitle-{{ $report->id }}">Edit Daily Driver Report</h2><p>{{ $report->ddr_no }} · Correct values from the physical DDR</p></div>
                            <button type="button" class="ddr-record-close" aria-label="Close edit report"><i class="fa-solid fa-xmark"></i></button>
                        </header>
                        <form method="POST" action="{{ route('daily-driver-reports.update', ['dailyDriverReport' => $report->ddr_no], false) }}">
                            @csrf
                            @method('PUT')
                            <div class="ddr-record-body">
                                <p class="ddr-record-note">Select a trip to correct its details. Report identity, driver, bus, and trip tickets remain protected.</p>
                                <div class="ddr-edit-trip-list">
                                    <details class="ddr-edit-trip" open>
                                        <summary><span>Trip 1 <small>{{ $report->trip_ticket }}</small></span><i class="fa-solid fa-chevron-down"></i></summary>
                                        <div class="ddr-record-fields">
                                            <label>From *<input name="from_location" value="{{ $report->from_location }}" maxlength="150" required></label>
                                            <label>To *<input name="to_location" value="{{ $report->to_location }}" maxlength="150" required></label>
                                            <label>Departure *<input type="time" name="departure_time" value="{{ substr((string) $report->getRawOriginal('departure_time'), 0, 5) }}" required></label>
                                            <label>Arrival *<input type="time" name="arrival_time" value="{{ substr((string) $report->getRawOriginal('arrival_time'), 0, 5) }}" required></label>
                                            <label>Passengers *<input type="number" name="passengers" value="{{ $report->passengers }}" min="0" required></label>
                                            <label>Kilometers<input type="number" name="km" value="{{ $report->km }}" min="0" step=".01"></label>
                                        </div>
                                    </details>
                                    @foreach($report->additionalTrips as $entry)
                                        <details class="ddr-edit-trip">
                                            <summary><span>Trip {{ $loop->iteration + 1 }} <small>{{ $entry->trip_ticket }}</small></span><i class="fa-solid fa-chevron-down"></i></summary>
                                            <input type="hidden" name="additional_trips[{{ $loop->index }}][id]" value="{{ $entry->id }}">
                                            <div class="ddr-record-fields">
                                                <label>From *<input name="additional_trips[{{ $loop->index }}][from_location]" value="{{ $entry->from_location }}" maxlength="150" required></label>
                                                <label>To *<input name="additional_trips[{{ $loop->index }}][to_location]" value="{{ $entry->to_location }}" maxlength="150" required></label>
                                                <label>Departure *<input type="time" name="additional_trips[{{ $loop->index }}][departure_time]" value="{{ substr((string) $entry->departure_time, 0, 5) }}" required></label>
                                                <label>Arrival *<input type="time" name="additional_trips[{{ $loop->index }}][arrival_time]" value="{{ substr((string) $entry->arrival_time, 0, 5) }}" required></label>
                                                <label>Passengers *<input type="number" name="additional_trips[{{ $loop->index }}][passengers]" value="{{ $entry->passengers }}" min="0" required></label>
                                                <label>Kilometers<input type="number" name="additional_trips[{{ $loop->index }}][km]" value="{{ $entry->km }}" min="0" step=".01"></label>
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </div>
                            <footer class="ddr-record-footer"><button type="button" class="ddr-record-close">Cancel</button><button type="submit" class="ddr-record-save"><i class="fa-solid fa-floppy-disk"></i> Save Corrections</button></footer>
                        </form>
                    </section>
                </div>
            @endif
        @endforeach

        <x-ui.action-buttom-modal
            mode="delete"
            id="ddrDeleteModal"
            delete-title="Delete Daily Driver Report?"
            delete-message="Are you sure you want to delete"
            name-id="ddrDeleteNumber"
            cancel-id="ddrDeleteCancel"
            confirm-id="ddrDeleteConfirm"
        />
        <form id="ddrDeleteForm" method="POST" action="" hidden>
            @csrf
            @method('DELETE')
        </form>

        {{-- Modals are part of the navigable page so their handlers reinitialize on return. --}}


    @if($canEditOperation)
    <x-ui.form-modal
        id="ddrEncodeModal"
        title="Encode New Report"
        description="Based on the GCT physical Daily Drivers Report"
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

        <input type="hidden" name="selected_trip_schedule_id" id="ddrSelectedTripId" value="{{ old('selected_trip_schedule_id') }}">
        <section class="ddr-exception-switch" aria-label="Unscheduled trip exception">
            <label class="ddr-exception-choice" for="ddrUnscheduledMode">
                <input type="checkbox" name="unscheduled_mode" id="ddrUnscheduledMode" value="1" @checked(old('unscheduled_mode'))>
                <span class="ddr-exception-choice-icon"><i class="fa-solid fa-route" aria-hidden="true"></i></span>
                <span class="ddr-exception-choice-copy">
                    <strong>Record Unscheduled Trip</strong>
                    <small>For emergency or unplanned services without an assigned schedule</small>
                </span>
                <span class="ddr-exception-choice-tag">Exception</span>
            </label>
            <div id="ddrUnscheduledReasonWrap" class="ddr-exception-reason" hidden>
                <label for="ddrUnscheduledReason">Reason for Unscheduled Trip <span class="ui-required">*</span></label>
                <textarea id="ddrUnscheduledReason" name="unscheduled_reason" rows="3" maxlength="500" placeholder="Describe the actual reason this trip was not scheduled...">{{ old('unscheduled_reason') }}</textarea>
                <small>Required for audit history. At least 10 characters.</small>
                @error('unscheduled_reason') <span class="ui-field-error">{{ $message }}</span> @enderror
            </div>
            @error('selected_trip_schedule_id') <span class="ui-field-error">{{ $message }}</span> @enderror
        </section>
        <div class="ddr-report-heading"><strong>Report Information</strong><small>One physical DDR · shared report date, driver and bus</small></div>
        <div class="ddr-form-grid ddr-modal-form-grid">
            <x-ui.form-field
                label="Report Date"
                name="report_date"
                type="date"
                value="{{ old('report_date') }}"
                required

            />

            <x-ui.form-field
                label="Trip Ticket No."
                name="trip_ticket"
                value="{{ old('trip_ticket') }}"
                placeholder="Ticket number printed on the DDR"
                required

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

            />

            <x-ui.form-field
                label="To Location"
                name="to_location"
                value="{{ old('to_location') }}"
                placeholder="Destination terminal / stop"
                required

                unit=""
            />


            <x-ui.form-field
                label="Departure Time"
                name="departure_time"
                type="time"
                value="{{ old('departure_time') }}"
                required

            />

            <x-ui.form-field
                label="Arrival Time"
                name="arrival_time"
                type="time"
                value="{{ old('arrival_time') }}"
                required

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

            />
            <x-ui.form-field label="KM Traveled" name="km" type="number" value="{{ old('km') }}" min="0" max="99999999.99" step="0.01" placeholder="KM recorded on the DDR" />
        </div>

        <section class="ddr-multi-trips" data-ddr-multi-trips>
            <div class="ddr-multi-header">
                <div><strong>Trip Entries</strong><p>Enter the actual details from each trip recorded on this DDR.</p></div>
                <span class="ddr-trip-count" data-ddr-trip-count>1 trip</span>
            </div>
            <div data-ddr-trip-rows></div>
            <button type="button" class="ddr-add-trip" data-ddr-add-trip>
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Add Another Trip</span>
                <i class="fa-solid fa-chevron-down ddr-add-trip-chevron" aria-hidden="true"></i>
            </button>
            <script type="application/json" data-ddr-old-trips>@json(array_values(old("additional_trips", [])))</script>
            <template data-ddr-trip-template>
                <details class="ddr-extra-trip">
                    <summary class="ddr-extra-trip-heading">
                        <strong data-trip-title>Additional Trip</strong>
                        <span class="ddr-extra-trip-tag">Assigned trip</span>
                        <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                    </summary>
                    <div class="ddr-extra-trip-body">
                        <div class="ddr-extra-trip-actions">
                            <p>Enter the actual journey details recorded on the physical DDR.</p>
                            <button type="button" data-ddr-remove-trip class="ddr-remove-trip"><i class="fa-solid fa-trash" aria-hidden="true"></i><span>Remove</span></button>
                        </div>
                        <div class="ddr-extra-trip-grid">
                        <div class="ui-form-group ddr-extra-assignment-group"><label>Assigned Trip <span class="ui-required">*</span></label><small class="ddr-extra-trip-hint">Choose the correct scheduled trip for this entry.</small><select class="ddr-extra-assignment" data-trip-field="selected_trip_schedule_id"><option value="">Select an assigned trip...</option></select></div>
                        <x-ui.form-field label="Trip Ticket No." name="ddr_trip_template_ticket" required placeholder="Trip ticket number" maxlength="50" data-trip-field="trip_ticket" />
                        <x-ui.form-field label="From Location" name="ddr_trip_template_from" required placeholder="Origin terminal / stop" maxlength="150" data-trip-field="from_location" />
                        <x-ui.form-field label="To Location" name="ddr_trip_template_to" required placeholder="Destination terminal / stop" maxlength="150" data-trip-field="to_location" />
                        <x-ui.form-field label="Departure Time" name="ddr_trip_template_departure" type="time" required data-trip-field="departure_time" />
                        <x-ui.form-field label="Arrival Time" name="ddr_trip_template_arrival" type="time" required data-trip-field="arrival_time" />
                        <x-ui.form-field label="Passengers" name="ddr_trip_template_passengers" type="number" min="0" step="1" required placeholder="0" data-trip-field="passengers" />
                        <x-ui.form-field label="KM Traveled" name="ddr_trip_template_km" type="number" min="0" max="99999999.99" step="0.01" placeholder="KM from the DDR" data-trip-field="km" />
                        </div>
                    </div>
                </details>
            </template>
        </section>

        <div class="ddr-form-note">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <strong>Overnight trips are supported.</strong>
                <span>Arrival times earlier than the departure time mean the trip ran past midnight. Entered times are saved exactly as encoded on the DDR and are never modified.</span>
            </div>
        </div>

        <div class="ddr-sched-panel ddr-modal-sched-panel ddr-sched-panel-compact">
            <label for="sfcList">Assigned Trip <span class="ui-required">*</span></label>
            <p class="ddr-assigned-trip-hint">Select the assigned trip to link its bus. Enter actual locations and times manually.</p>
            <div id="sfcList" class="ddr-sched-list" aria-live="polite"></div>
            <div id="sfcStatus" class="ddr-sched-status idle">
                <i class="fa-solid fa-magnifying-glass"></i>
                <span>Select a report date and driver to find eligible trips.</span>
            </div>
        </div>
    </x-ui.form-modal>
    @endif

        </main>
    </div>
    @push('scripts')
        <script>
            window.ddrScheduleLookupUrl = "{{ route('daily-driver-reports.schedule-lookup', [], false) }}";
            window.ddrAssignedDriversUrl = "{{ route('daily-driver-reports.assigned-drivers', [], false) }}";
        </script>
    @endpush
</x-layout.app>
